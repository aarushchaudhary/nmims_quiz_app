<?php
/**
 * SecurityTest.php
 * Regression tests for the five critical authorization vulnerabilities fixed in
 * the Phase 2 security patch.  Each test verifies that the exploit described in
 * the audit brief is now correctly rejected.
 *
 * Fix #1 — Unauthenticated access to detailed exam results
 * Fix #2 — Student A can write answers into Student B's attempt
 * Fix #3 — Client-controlled is_disqualified flag
 * Fix #4 — Client-controlled is_forced flag in change_password
 * Fix #7 — Faculty A can access Faculty B's quiz data / write to their questions
 */
class SecurityTest
{
    private TestClient $http;

    public function __construct(TestClient $http)
    {
        $this->http = $http;
    }

    // ── session helpers ───────────────────────────────────────────────────────

    private function loginAsStudent(): void
    {
        $this->http->clearSession();
        $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['student_email'],
            'password' => $GLOBALS['TEST']['student_password'],
            'force'    => true,
        ]);
    }

    private function loginAsStudentB(): void
    {
        $this->http->clearSession();
        $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['student_b_email'],
            'password' => $GLOBALS['TEST']['student_b_password'],
            'force'    => true,
        ]);
    }

    private function loginAsFaculty(): void
    {
        $this->http->clearSession();
        $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['faculty_email'],
            'password' => $GLOBALS['TEST']['faculty_password'],
            'force'    => true,
        ]);
    }

    private function loginAsFacultyB(): void
    {
        $this->http->clearSession();
        $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['faculty_b_email'],
            'password' => $GLOBALS['TEST']['faculty_b_password'],
            'force'    => true,
        ]);
    }

    private function asGuest(): void
    {
        $this->http->clearSession();
    }

    // ── helper: create an attempt for student A and return its ID ─────────────

    private function createAttemptForStudentA(): int
    {
        $this->loginAsStudent();
        $pdo     = get_test_pdo();
        $quiz_id = $GLOBALS['TEST']['quiz_id'];

        // Clean any stale attempt so fetch_exam_questions creates a fresh one.
        $pdo->prepare('DELETE FROM student_attempts WHERE quiz_id = ? AND student_id = ?')
            ->execute([$quiz_id, $GLOBALS['TEST']['student_user_id']]);

        $res = $this->http->get('api/student/fetch_exam_questions.php', ['id' => $quiz_id]);
        if ($res['status'] !== 200 || empty($res['json']['attempt_id'])) {
            throw new RuntimeException('Failed to create attempt for Student A in SecurityTest');
        }
        $attempt_id = (int) $res['json']['attempt_id'];
        $GLOBALS['TEST']['security_attempt_id'] = $attempt_id;
        return $attempt_id;
    }

    // ── test list ─────────────────────────────────────────────────────────────

    public function run(): array
    {
        return [
            // Fix #1
            test('Security Fix #1: get_detailed_results denied for unauthenticated user',
                fn() => $this->testFix1GuestDenied()),
            test('Security Fix #1: get_detailed_results denied for student B accessing student A\'s attempt',
                fn() => $this->testFix1CrossStudentDenied()),
            test('Security Fix #1: get_detailed_results blocked when show_results_immediately=0',
                fn() => $this->testFix1PublicationGate()),

            // Fix #2
            test('Security Fix #2: save_answer denied for student B targeting student A\'s attempt',
                fn() => $this->testFix2CrossAttemptDenied()),
            test('Security Fix #2: save_answer denied for valid attempt but question from different quiz',
                fn() => $this->testFix2CrossQuizQuestionDenied()),

            // Fix #3
            test('Security Fix #3: finish_exam denied for student B targeting student A\'s attempt',
                fn() => $this->testFix3CrossAttemptDenied()),
            test('Security Fix #3: finish_exam ignores client-sent is_disqualified (no violations logged)',
                fn() => $this->testFix3IsDisqualifiedIgnored()),

            // Fix #4 (covered in SharedTest, repeated here for traceability)
            test('Security Fix #4: change_password without current_password fails when not forced',
                fn() => $this->testFix4BypassFails()),

            // Fix #7 — Faculty cross-quiz read denials
            test('Security Fix #7: Faculty A denied get_lobby_students for Faculty B\'s quiz',
                fn() => $this->testFix7LobbyDenied()),
            test('Security Fix #7: Faculty A denied get_live_monitoring_data for Faculty B\'s quiz',
                fn() => $this->testFix7MonitoringDenied()),
            test('Security Fix #7: Faculty A denied get_item_analysis for Faculty B\'s quiz',
                fn() => $this->testFix7ItemAnalysisDenied()),

            // Fix #7 — Faculty cross-quiz write denials
            test('Security Fix #7: Faculty A denied add_manual_question to Faculty B\'s quiz',
                fn() => $this->testFix7AddQuestionDenied()),
            test('Security Fix #7: Faculty A denied upload_questions to Faculty B\'s quiz',
                fn() => $this->testFix7UploadQuestionsDenied()),
            test('Security Fix #7: Faculty A denied update_question on Faculty B\'s question',
                fn() => $this->testFix7UpdateQuestionDenied()),
        ];
    }

    // ── Fix #1 implementations ────────────────────────────────────────────────

    private function testFix1GuestDenied(): void
    {
        // Ensure we have a known attempt ID (from a previous test run or create one).
        $attempt_id = $GLOBALS['TEST']['attempt_id']
                   ?? $GLOBALS['TEST']['security_attempt_id']
                   ?? $this->createAttemptForStudentA();

        $this->asGuest();
        $res = $this->http->get('api/student/get_detailed_results.php', [
            'attempt_id' => $attempt_id,
        ]);
        assert_eq($res['status'], 403, 'Fix #1: unauthenticated user must get 403');
    }

    private function testFix1CrossStudentDenied(): void
    {
        $attempt_id = $GLOBALS['TEST']['attempt_id']
                   ?? $GLOBALS['TEST']['security_attempt_id']
                   ?? $this->createAttemptForStudentA();

        // Student B tries to read Student A's results.
        $this->loginAsStudentB();
        $res = $this->http->get('api/student/get_detailed_results.php', [
            'attempt_id' => $attempt_id,
        ]);
        assert_eq($res['status'], 403, 'Fix #1: Student B must get 403 for Student A\'s attempt');
    }

    private function testFix1PublicationGate(): void
    {
        $attempt_id = $GLOBALS['TEST']['attempt_id']
                   ?? $GLOBALS['TEST']['security_attempt_id']
                   ?? $this->createAttemptForStudentA();

        // Ensure the quiz does NOT have show_results_immediately=1.
        // (The test fixture creates it with the default, which is 0.)
        $pdo = get_test_pdo();
        $pdo->prepare('UPDATE quizzes SET show_results_immediately = 0 WHERE id = ?')
            ->execute([$GLOBALS['TEST']['quiz_id']]);

        $this->loginAsStudent();
        $res = $this->http->get('api/student/get_detailed_results.php', [
            'attempt_id' => $attempt_id,
        ]);
        // Should return 200 with results_pending=true (not the full answer list).
        assert_eq($res['status'], 200, 'Fix #1 publication gate: must return 200');
        assert_true(
            isset($res['json']['results_pending']) && $res['json']['results_pending'] === true,
            'Fix #1 publication gate: response must be results_pending=true when not published'
        );
    }

    // ── Fix #2 implementations ────────────────────────────────────────────────

    private function testFix2CrossAttemptDenied(): void
    {
        $attempt_id = $GLOBALS['TEST']['attempt_id']
                   ?? $GLOBALS['TEST']['security_attempt_id']
                   ?? $this->createAttemptForStudentA();
        $question_id = $GLOBALS['TEST']['question_id'];

        // Student B tries to write an answer into Student A's attempt.
        $this->loginAsStudentB();
        $res = $this->http->postJson('api/student/save_answer.php', [
            'attempt_id'          => $attempt_id,
            'question_id'         => $question_id,
            'selected_option_ids' => [],
            'time_spent'          => 1,
        ]);
        assert_eq($res['status'], 403, 'Fix #2: Student B must get 403 for Student A\'s attempt');
    }

    private function testFix2CrossQuizQuestionDenied(): void
    {
        // Student A has an attempt on quiz_id. question_b_id belongs to quiz_b_id.
        // Even with a valid attempt, using a question from a different quiz must fail.
        $attempt_id  = $GLOBALS['TEST']['attempt_id']
                    ?? $GLOBALS['TEST']['security_attempt_id']
                    ?? $this->createAttemptForStudentA();
        $question_b_id = $GLOBALS['TEST']['question_b_id'];

        $this->loginAsStudent();
        $res = $this->http->postJson('api/student/save_answer.php', [
            'attempt_id'          => $attempt_id,
            'question_id'         => $question_b_id, // belongs to quiz_b, not quiz_a
            'selected_option_ids' => [],
            'time_spent'          => 1,
        ]);
        assert_eq($res['status'], 403,
            'Fix #2: question from a different quiz must be rejected (403)');
    }

    // ── Fix #3 implementations ────────────────────────────────────────────────

    private function testFix3CrossAttemptDenied(): void
    {
        $attempt_id = $GLOBALS['TEST']['attempt_id']
                   ?? $GLOBALS['TEST']['security_attempt_id']
                   ?? $this->createAttemptForStudentA();

        // Student B tries to finish Student A's attempt.
        $this->loginAsStudentB();
        $res = $this->http->postJson('api/student/finish_exam.php', [
            'attempt_id' => $attempt_id,
        ]);
        assert_eq($res['status'], 403, 'Fix #3: Student B must get 403 for Student A\'s attempt');
    }

    private function testFix3IsDisqualifiedIgnored(): void
    {
        // Create a fresh attempt for Student A to finish.
        $attempt_id = $this->createAttemptForStudentA();

        $this->loginAsStudent();

        // POST with is_disqualified=true in the body (old exploit).
        // No violations are in event_logs, so the server must NOT disqualify.
        $res = $this->http->postJson('api/student/finish_exam.php', [
            'attempt_id'      => $attempt_id,
            'is_disqualified' => true,  // attacker's payload — must be ignored
        ]);
        assert_eq($res['status'], 200, 'Fix #3: finish_exam must succeed even with attacker payload');
        assert_true($res['json']['success'] ?? false, 'Fix #3: finish_exam must return success=true');

        // Confirm DB: is_disqualified must be 0 (server ignored the client flag).
        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare('SELECT is_disqualified, submitted_at FROM student_attempts WHERE id = ?');
        $stmt->execute([$attempt_id]);
        $row = $stmt->fetch();
        assert_true($row !== false, 'Fix #3: attempt row must exist');
        assert_eq((int)$row['is_disqualified'], 0,
            'Fix #3: is_disqualified must be 0 (server ignored client flag; 0 violations in event_logs)');
        assert_true($row['submitted_at'] !== null, 'Fix #3: submitted_at must be set');

        // Propagate the finished attempt_id so subsequent StudentTest ordering still works.
        $GLOBALS['TEST']['attempt_id'] = $attempt_id;
    }

    // ── Fix #4 implementation ─────────────────────────────────────────────────

    private function testFix4BypassFails(): void
    {
        // Log in as faculty (session does NOT have force_password_change=true).
        $this->loginAsFaculty();

        // Attempt to bypass the current_password requirement by omitting it.
        // Old code checked $input['is_forced']; new code reads from session only.
        $res = $this->http->postJson('api/shared/change_password.php', [
            'new_password'     => 'Bypass@Attack99',
            'confirm_password' => 'Bypass@Attack99',
            // is_forced deliberately absent — matches the attack vector
        ]);
        $noSuccess = !isset($res['json']['success']) || $res['json']['success'] !== true;
        assert_true($noSuccess, 'Fix #4: missing current_password without session flag must fail');

        // Confirm the original password still works.
        $this->http->clearSession();
        $loginRes = $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['faculty_email'],
            'password' => $GLOBALS['TEST']['faculty_password'],
            'force'    => true,
        ]);
        assert_eq($loginRes['json']['status'] ?? '', 'success',
            'Fix #4: original faculty password must still work after attack');
    }

    // ── Fix #7 implementations (read denials) ─────────────────────────────────

    private function testFix7LobbyDenied(): void
    {
        // Faculty A (owns quiz_id) tries to read lobby for quiz_b_id (owned by Faculty B).
        $this->loginAsFaculty();
        $res = $this->http->get('api/faculty/get_lobby_students.php', [
            'id' => $GLOBALS['TEST']['quiz_b_id'],
        ]);
        assert_eq($res['status'], 403,
            'Fix #7: Faculty A must get 403 for Faculty B\'s quiz lobby');
    }

    private function testFix7MonitoringDenied(): void
    {
        $this->loginAsFaculty();
        $res = $this->http->get('api/faculty/get_live_monitoring_data.php', [
            'id' => $GLOBALS['TEST']['quiz_b_id'],
        ]);
        assert_eq($res['status'], 403,
            'Fix #7: Faculty A must get 403 for Faculty B\'s monitoring data');
    }

    private function testFix7ItemAnalysisDenied(): void
    {
        $this->loginAsFaculty();
        $res = $this->http->get('api/faculty/get_item_analysis.php', [
            'quiz_id' => $GLOBALS['TEST']['quiz_b_id'],
        ]);
        assert_eq($res['status'], 403,
            'Fix #7: Faculty A must get 403 for Faculty B\'s item analysis');
    }

    // ── Fix #7 implementations (write denials) ────────────────────────────────

    private function testFix7AddQuestionDenied(): void
    {
        // Faculty A tries to add a question to Faculty B's quiz.
        $this->loginAsFaculty();
        $res = $this->http->post('api/faculty/add_manual_question.php', [
            'quiz_id'               => $GLOBALS['TEST']['quiz_b_id'],
            'question_text'         => 'INJECTION: fake question',
            'question_type_id'      => 1,
            'difficulty_id'         => 1,
            'points'                => 1,
            'options'               => ['a', 'b', 'c', 'd'],
            'correct_answer_single' => '0',
        ]);
        // Ownership check redirects (302) to manage_quizzes.php — never inserts.
        assert_eq($res['status'], 302,
            'Fix #7: Faculty A must get a redirect (302) when adding to Faculty B\'s quiz');

        // Double-check DB: no INJECTION question was inserted into quiz_b.
        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM questions WHERE quiz_id = ? AND question_text LIKE 'INJECTION%'"
        );
        $stmt->execute([$GLOBALS['TEST']['quiz_b_id']]);
        $count = (int) $stmt->fetchColumn();
        assert_eq($count, 0, 'Fix #7: no injected question must appear in Faculty B\'s quiz');
    }

    private function testFix7UploadQuestionsDenied(): void
    {
        // Faculty A tries to upload questions to Faculty B's quiz.
        // We can't easily send multipart/form-data via TestClient, so just send
        // a form POST without a file — the ownership check fires before the file check.
        $this->loginAsFaculty();
        $res = $this->http->post('api/faculty/upload_questions.php', [
            'quiz_id' => $GLOBALS['TEST']['quiz_b_id'],
        ]);
        // ownership denial → redirect to manage_quizzes.php?error=not_found (302)
        // or 403 JSON — either is acceptable, never 200 success.
        assert_in($res['status'], [302, 403],
            'Fix #7: Faculty A must not be able to upload questions to Faculty B\'s quiz');
    }

    private function testFix7UpdateQuestionDenied(): void
    {
        // Faculty A tries to update Faculty B's question (question_b_id in quiz_b).
        $this->loginAsFaculty();
        $question_b_id = $GLOBALS['TEST']['question_b_id'];
        $res = $this->http->post('api/faculty/update_question.php', [
            'question_id'       => $question_b_id,
            'quiz_id'           => $GLOBALS['TEST']['quiz_b_id'],
            'question_text'     => 'INJECTED TEXT',
            'question_type_id'  => 1,
            'difficulty_id'     => 1,
            'points'            => 99,
            'options'           => ['hacked'],
            'option_ids'        => [1],
            'correct_answers'   => [0],
        ]);
        // Ownership check → redirect (302)
        assert_eq($res['status'], 302,
            'Fix #7: Faculty A must get a redirect when updating Faculty B\'s question');

        // Verify the question text was NOT changed.
        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare('SELECT question_text FROM questions WHERE id = ?');
        $stmt->execute([$question_b_id]);
        $row = $stmt->fetch();
        assert_true($row !== false, 'Fix #7: question_b must still exist');
        assert_true(
            strpos($row['question_text'], 'INJECTED') === false,
            'Fix #7: question text must not be modified by Faculty A'
        );
    }
}
