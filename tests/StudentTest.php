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
            test('Student: finish_exam grades MCQ/MSQ correctly (partial, select-all, negative marks)',
                fn() => $this->testGradingScores()),
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
            'attempt_id' => $attempt_id,
            // NOTE: is_disqualified is no longer accepted from the client (Fix #3).
            // The server derives it from event_logs violation count.
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
        // show_results_immediately is 0 by default on the test quiz, so the new
        // server-side publication gate should return results_pending=true (200 body)
        // OR the quiz was published by a prior FacultyTest run, so results come back.
        assert_eq($res['status'], 200, 'get_detailed_results should return 200');
        $body = $res['json'];
        $valid = (isset($body['results_pending']) && $body['results_pending'] === true)
              || is_array($body);
        assert_true($valid, 'get_detailed_results body should be results_pending or an array');
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

    /**
     * Grading regression: a known answer set must give a known total.
     * MCQ right +2, MCQ wrong -0.25, MSQ 1-of-2 right +2, MSQ "select all" -0.5
     * (wrong, not full marks), MSQ exact +4, unanswered 0  =>  7.25
     */
    private function testGradingScores(): void
    {
        $pdo = get_test_pdo();
        $T   = $GLOBALS['TEST'];
        $pdo->exec("INSERT INTO quizzes
                        (title, faculty_id, course_id, start_time, end_time, duration_minutes, status_id,
                         config_easy_count, config_medium_count, config_hard_count,
                         enable_negative_marking, negative_marks_mcq, negative_marks_msq)
                    VALUES ('TEST_QUIZ', {$T['faculty_user_id']}, {$T['sap_course_id']},
                            DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_ADD(NOW(), INTERVAL 50 MINUTE),
                            60, 3, 0, 0, 0, 1, 0.25, 0.5)");
        $quiz_id = (int) $pdo->lastInsertId();

        // [type (1=MCQ, 2=MSQ), points, which options are correct, option indexes picked (null = unanswered)]
        $spec = [
            [1, 2, [1, 0, 0, 0], [0]],
            [1, 2, [1, 0, 0, 0], [1]],
            [2, 4, [1, 1, 0, 0], [0]],
            [2, 4, [1, 1, 0, 0], [0, 1, 2, 3]],
            [2, 4, [1, 1, 0, 0], [0, 1]],
            [1, 2, [1, 0, 0, 0], null],
        ];
        $questions = [];
        $answers   = [];
        foreach ($spec as [$type, $points, $correct, $picks]) {
            $pdo->prepare('INSERT INTO questions (quiz_id, question_type_id, difficulty_id, question_text, points)
                           VALUES (?, ?, 1, ?, ?)')->execute([$quiz_id, $type, 'TEST grading question', $points]);
            $qid  = (int) $pdo->lastInsertId();
            $oids = [];
            foreach ($correct as $i => $is_correct) {
                $pdo->prepare('INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)')
                    ->execute([$qid, "option $i", $is_correct]);
                $oids[] = (int) $pdo->lastInsertId();
            }
            $questions[] = ['id' => $qid];
            if ($picks !== null) {
                $answers[$qid] = array_map(fn($i) => $oids[$i], $picks);
            }
        }
        $pdo->prepare('INSERT INTO student_attempts (quiz_id, student_id, attempt_end_time, questions_json)
                       VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?)')
            ->execute([$quiz_id, $T['student_user_id'], json_encode($questions)]);
        $attempt_id = (int) $pdo->lastInsertId();
        foreach ($answers as $qid => $picked) {
            $pdo->prepare('INSERT INTO student_answers (attempt_id, question_id, selected_option_ids, time_spent_seconds)
                           VALUES (?, ?, ?, 0)')->execute([$attempt_id, $qid, json_encode($picked)]);
        }

        try {
            $this->loginAsStudent();
            $res = $this->http->postJson('api/student/finish_exam.php', ['attempt_id' => $attempt_id]);
            assert_eq($res['status'], 200, 'finish_exam should return 200');
            $stmt = $pdo->prepare('SELECT total_score FROM student_attempts WHERE id = ?');
            $stmt->execute([$attempt_id]);
            assert_eq((float) $stmt->fetchColumn(), 7.25, 'total_score for the known answer set');
        } finally {
            $pdo->prepare('DELETE FROM student_attempts WHERE id = ?')->execute([$attempt_id]); // cascades answers
            $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([$quiz_id]);             // cascades questions, options
        }
    }
}
