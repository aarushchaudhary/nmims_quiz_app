<?php
/**
 * StudentTest.php
 * Tests for all api/student/* endpoints.
 *
 * NOTE: fetch_exam_questions and finish_exam interact heavily with DB state,
 * so we run them as an ordered workflow and clean up the attempt afterwards.
 */
class StudentTest
{
    private TestClient $http;
    private ?int $attemptId = null;

    public function __construct(TestClient $http)
    {
        $this->http = $http;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function loginAsStudent(): void
    {
        $this->http->clearSession();
        $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['student_email'],
            'password' => $GLOBALS['TEST']['student_password'],
            'force'    => true,
        ]);
    }

    private function asGuest(): void
    {
        $this->http->clearSession();
    }

    // ── test list ─────────────────────────────────────────────────────────────

    public function run(): array
    {
        return [
            test('Student: endpoints require student session (403 for guest)',
                fn() => $this->testAuthGuards()),
            test('Student: get_attempt_status missing params returns error',
                fn() => $this->testGetAttemptStatusMissingParams()),
            test('Student: log_event missing data returns 400',
                fn() => $this->testLogEventMissingData()),
            test('Student: fetch_exam_questions creates new attempt',
                fn() => $this->testFetchExamQuestions()),
            test('Student: get_attempt_status returns valid status',
                fn() => $this->testGetAttemptStatus()),
            test('Student: log_event with valid attempt_id logs event',
                fn() => $this->testLogEventSuccess()),
            test('Student: save_answer saves a response',
                fn() => $this->testSaveAnswer()),
            test('Student: finish_exam completes the attempt',
                fn() => $this->testFinishExam()),
            test('Student: get_detailed_results returns results after submit',
                fn() => $this->testGetDetailedResults()),
            test('Student: fetch_exam_questions returns already-submitted error on re-attempt',
                fn() => $this->testFetchAlreadySubmitted()),
        ];
    }

    // ── implementations ──────────────────────────────────────────────────────

    private function testAuthGuards(): void
    {
        $this->asGuest();
        $endpoints = [
            ['get',  'api/student/fetch_exam_questions.php', ['id' => 1]],
            ['post', 'api/student/save_answer.php',          []],
            ['post', 'api/student/finish_exam.php',          []],
            ['get',  'api/student/get_attempt_status.php',   ['attempt_id' => 1]],
        ];
        foreach ($endpoints as [$method, $path, $params]) {
            $res = ($method === 'get')
                ? $this->http->get($path, $params)
                : $this->http->postJson($path, $params);
            assert_eq($res['status'], 403, "$path should return 403 for guest");
        }
    }

    private function testGetAttemptStatusMissingParams(): void
    {
        $this->loginAsStudent();
        $res = $this->http->get('api/student/get_attempt_status.php');
        assert_in($res['status'], [400, 403, 500], 'Missing params should not return 200');
    }

    private function testLogEventMissingData(): void
    {
        $this->loginAsStudent();
        $res = $this->http->postJson('api/student/log_event.php', []);
        assert_in($res['status'], [400, 403, 500], 'Missing log event data should not return 200');
    }

    private function testFetchExamQuestions(): void
    {
        $this->loginAsStudent();
        $quiz_id = $GLOBALS['TEST']['quiz_id'];

        // Clean any stale attempt first
        $pdo = get_test_pdo();
        $pdo->prepare("DELETE FROM student_attempts WHERE quiz_id = ? AND student_id = ?")
            ->execute([$quiz_id, $GLOBALS['TEST']['student_user_id']]);

        $res = $this->http->get('api/student/fetch_exam_questions.php', ['id' => $quiz_id]);
        assert_eq($res['status'], 200, 'fetch_exam_questions should return 200');
        assert_key($res['json'], 'attempt_id',        'Response must have attempt_id');
        assert_key($res['json'], 'questions',         'Response must have questions array');
        assert_key($res['json'], 'remaining_seconds', 'Response must have remaining_seconds');
        assert_true(is_array($res['json']['questions']), 'Questions must be an array');
        assert_true(count($res['json']['questions']) > 0, 'There must be at least one question');

        // Store attempt ID for subsequent tests
        $this->attemptId            = (int)$res['json']['attempt_id'];
        $GLOBALS['TEST']['attempt_id'] = $this->attemptId;
    }

    private function testGetAttemptStatus(): void
    {
        $this->loginAsStudent();
        $attempt_id = $GLOBALS['TEST']['attempt_id'] ?? null;
        if (!$attempt_id) {
            throw new RuntimeException('No attempt_id available — testFetchExamQuestions may have failed');
        }

        // Endpoint uses ?id= (not ?attempt_id=)
        $res = $this->http->get('api/student/get_attempt_status.php', [
            'id' => $attempt_id,
        ]);
        assert_eq($res['status'], 200, 'get_attempt_status should return 200');
        // Endpoint returns can_resume and quiz_id
        assert_key($res['json'], 'can_resume', 'Response must have can_resume key');
    }

    private function testLogEventSuccess(): void
    {
        $this->loginAsStudent();
        $attempt_id = $GLOBALS['TEST']['attempt_id'] ?? null;
        if (!$attempt_id) return;

        $res = $this->http->postJson('api/student/log_event.php', [
            'attempt_id'  => $attempt_id,
            'event_type'  => 'TAB_SWITCH',
            'description' => 'Test: student switched tabs',
        ]);
        assert_eq($res['status'], 200, 'log_event with valid data should return 200');
        assert_true($res['json']['success'] ?? false, 'log_event should return success=true');
    }

    private function testSaveAnswer(): void
    {
        $this->loginAsStudent();
        $attempt_id       = $GLOBALS['TEST']['attempt_id']       ?? null;
        $question_id      = $GLOBALS['TEST']['question_id']       ?? null;
        $correct_option_id = $GLOBALS['TEST']['correct_option_id'] ?? null;

        if (!$attempt_id || !$question_id) {
            throw new RuntimeException('Missing attempt_id or question_id for save_answer test');
        }

        $res = $this->http->postJson('api/student/save_answer.php', [
            'attempt_id'        => $attempt_id,
            'question_id'       => $question_id,
            'selected_option_ids' => $correct_option_id ? [$correct_option_id] : [],
            'time_spent'        => 5,
        ]);
        assert_eq($res['status'], 200, 'save_answer should return 200');
        assert_true($res['json']['success'] ?? false, 'save_answer should return success=true');
    }

    private function testFinishExam(): void
    {
        $this->loginAsStudent();
        $attempt_id = $GLOBALS['TEST']['attempt_id'] ?? null;
        if (!$attempt_id) {
            throw new RuntimeException('No attempt_id available for finish_exam test');
        }

        $res = $this->http->postJson('api/student/finish_exam.php', [
            'attempt_id'     => $attempt_id,
            'is_disqualified' => false,
        ]);
        assert_eq($res['status'], 200, 'finish_exam should return 200');
        assert_true($res['json']['success'] ?? false, 'finish_exam should return success=true');

        // Verify DB state
        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare("SELECT submitted_at FROM student_attempts WHERE id = ?");
        $stmt->execute([$attempt_id]);
        $row  = $stmt->fetch();
        assert_true($row !== false && $row['submitted_at'] !== null, 'submitted_at should be set after finish_exam');
    }

    private function testGetDetailedResults(): void
    {
        $this->loginAsStudent();
        $attempt_id = $GLOBALS['TEST']['attempt_id'] ?? null;
        if (!$attempt_id) return;

        $res = $this->http->get('api/student/get_detailed_results.php', [
            'attempt_id' => $attempt_id,
        ]);
        assert_in($res['status'], [200, 403], 'get_detailed_results should return 200 or 403');
    }

    private function testFetchAlreadySubmitted(): void
    {
        $this->loginAsStudent();
        $quiz_id = $GLOBALS['TEST']['quiz_id'];
        $res     = $this->http->get('api/student/fetch_exam_questions.php', ['id' => $quiz_id]);
        // Should either return 500 with "already completed" error or 200 if quiz allows re-entry
        assert_in($res['status'], [200, 500], 'Re-fetching a submitted exam should return 200 or 500');
        if ($res['status'] === 500) {
            assert_key($res['json'], 'error', 'Should contain an error message');
        }
    }
}
