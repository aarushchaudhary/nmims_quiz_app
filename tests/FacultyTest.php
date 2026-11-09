<?php
/**
 * FacultyTest.php
 * Tests for all api/faculty/* endpoints.
 */
class FacultyTest
{
    private TestClient $http;

    public function __construct(TestClient $http)
    {
        $this->http = $http;
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

    private function asGuest(): void
    {
        $this->http->clearSession();
    }

    public function run(): array
    {
        return [
            test('Faculty: endpoints require faculty session (403 for guest)',
                fn() => $this->testAuthGuards()),
            test('Faculty: get_quiz_results missing quiz_id returns 400',
                fn() => $this->testGetQuizResultsMissingId()),
            test('Faculty: get_quiz_results returns JSON for valid quiz',
                fn() => $this->testGetQuizResults()),
            test('Faculty: get_lobby_students returns JSON',
                fn() => $this->testGetLobbyStudents()),
            test('Faculty: get_live_monitoring_data returns JSON',
                fn() => $this->testGetLiveMonitoringData()),
            test('Faculty: add_manual_question creates a question',
                fn() => $this->testAddManualQuestion()),
            test('Faculty: delete_question removes the question',
                fn() => $this->testDeleteQuestion()),
            test('Faculty: update_quiz_status transitions quiz status',
                fn() => $this->testUpdateQuizStatus()),
            test('Faculty: publish_results succeeds via JSON',
                fn() => $this->testPublishResults()),
            test('Faculty: get_item_analysis returns JSON',
                fn() => $this->testGetItemAnalysis()),
            test('Faculty: ending a quiz grades attempts the student never submitted',
                fn() => $this->testCompleteGradesOpenAttempts()),
            test('Faculty: Excel upload keeps an option "0" and rejects MCQs with no correct answer',
                fn() => $this->testUploadQuestionsValidation()),
            test('Faculty: results report open attempts as In progress and average graded ones only',
                fn() => $this->testResultsSummaryIgnoresOpenAttempts()),
            test('Faculty: export_results returns content',
                fn() => $this->testExportResults()),
        ];
    }

    /** A throwaway quiz owned by test faculty A, so test data never mixes into TEST_QUIZ's bank. */
    private function makeQuiz(): int
    {
        $T = $GLOBALS['TEST'];
        get_test_pdo()->exec("INSERT INTO quizzes
                        (title, faculty_id, course_id, start_time, end_time, duration_minutes, status_id,
                         config_easy_count, config_medium_count, config_hard_count)
                    VALUES ('TEST_QUIZ', {$T['faculty_user_id']}, {$T['sap_course_id']},
                            DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_ADD(NOW(), INTERVAL 50 MINUTE),
                            60, 3, 0, 0, 0)");
        return (int) get_test_pdo()->lastInsertId();
    }

    private function testUploadQuestionsValidation(): void
    {
        require_once __DIR__ . '/../lib/vendor/autoload.php';
        $pdo     = get_test_pdo();
        $quiz_id = $this->makeQuiz();
        $file    = sys_get_temp_dir() . '/nmims_upload_test_' . getmypid() . '.xlsx';

        // Columns: A text, B type, C difficulty, D points, E-H options, I correct option number(s)
        $upload = function (array $row) use ($file, $quiz_id) {
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $book->getActiveSheet()->fromArray(['Question', 'Type', 'Difficulty', 'Points', 'O1', 'O2', 'O3', 'O4', 'Correct'], null, 'A1');
            $book->getActiveSheet()->fromArray($row, null, 'A2', true);
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);
            return $this->http->post('api/faculty/upload_questions.php',
                ['quiz_id' => $quiz_id, 'question_file' => new CURLFile($file)]);
        };
        try {
            $this->loginAsFaculty();
            $upload(['What is 5 - 5?', 1, 1, 1, 0, 5, 10, -5, '1']);
            $stmt = $pdo->prepare('SELECT o.option_text, o.is_correct FROM options o
                                   JOIN questions q ON q.id = o.question_id WHERE q.quiz_id = ? ORDER BY o.id');
            $stmt->execute([$quiz_id]);
            $opts = $stmt->fetchAll();
            assert_eq(count($opts), 4, 'All four options, including "0", should be saved');
            assert_eq([$opts[0]['option_text'], (int) $opts[0]['is_correct']], ['0', 1], 'Option "0" should be saved as the correct one');

            $res = $upload(['MCQ with no correct answer', 1, 1, 1, 'a', 'b', 'c', 'd', '']);
            assert_true(str_contains($res['headers'], 'error='), 'A row with no correct option should be rejected');
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ?');
            $stmt->execute([$quiz_id]);
            assert_eq((int) $stmt->fetchColumn(), 1, 'The rejected upload must not add a question');
        } finally {
            @unlink($file);
            $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([$quiz_id]); // cascades questions, options
        }
    }

    private function testResultsSummaryIgnoresOpenAttempts(): void
    {
        $pdo     = get_test_pdo();
        $T       = $GLOBALS['TEST'];
        $quiz_id = $this->makeQuiz();
        // Student A finished with 4 points; student B is still taking it.
        $pdo->prepare('INSERT INTO student_attempts (quiz_id, student_id, total_score, submitted_at) VALUES (?, ?, 4, NOW())')
            ->execute([$quiz_id, $T['student_user_id']]);
        $pdo->prepare('INSERT INTO student_attempts (quiz_id, student_id) VALUES (?, ?)')
            ->execute([$quiz_id, $T['student_b_user_id']]);
        try {
            $this->loginAsFaculty();
            $res = $this->http->get('api/faculty/get_quiz_results.php', ['quiz_id' => $quiz_id]);
            assert_eq($res['status'], 200, 'get_quiz_results should return 200');
            assert_eq($res['json']['summary']['total_attempts'], 2, 'Both attempts are listed');
            assert_eq($res['json']['summary']['average_score'], '4.00', 'Average must ignore the open attempt');
            assert_eq(array_column($res['json']['details'], 'status'), ['Completed', 'In progress'], 'Open attempt shows as In progress');
        } finally {
            $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([$quiz_id]); // cascades attempts
        }
    }

    private function testCompleteGradesOpenAttempts(): void
    {
        $pdo = get_test_pdo();
        $T   = $GLOBALS['TEST'];
        $pdo->exec("INSERT INTO quizzes
                        (title, faculty_id, course_id, start_time, end_time, duration_minutes, status_id,
                         config_easy_count, config_medium_count, config_hard_count)
                    VALUES ('TEST_QUIZ', {$T['faculty_user_id']}, {$T['sap_course_id']},
                            DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_ADD(NOW(), INTERVAL 50 MINUTE),
                            60, 3, 0, 0, 0)");
        $quiz_id = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO questions (quiz_id, question_type_id, difficulty_id, question_text, points)
                       VALUES (?, 1, 1, 'TEST auto-submit question', 3)")->execute([$quiz_id]);
        $qid = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, 'right', 1)")->execute([$qid]);
        $right = (int) $pdo->lastInsertId();

        // A student answered correctly, then closed the laptop without submitting.
        $pdo->prepare('INSERT INTO student_attempts (quiz_id, student_id, attempt_end_time, questions_json)
                       VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?)')
            ->execute([$quiz_id, $T['student_user_id'], json_encode([['id' => $qid]])]);
        $attempt_id = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO student_answers (attempt_id, question_id, selected_option_ids, time_spent_seconds)
                       VALUES (?, ?, ?, 0)')->execute([$attempt_id, $qid, json_encode([$right])]);

        try {
            $this->loginAsFaculty();
            $res = $this->http->postJson('api/faculty/update_quiz_status.php', ['quiz_id' => $quiz_id, 'new_status_id' => 4]);
            assert_eq($res['status'], 200, 'Completing the quiz should succeed');
            assert_eq($res['json']['auto_submitted'] ?? null, 1, 'One open attempt should be auto-submitted');

            $stmt = $pdo->prepare('SELECT submitted_at, total_score FROM student_attempts WHERE id = ?');
            $stmt->execute([$attempt_id]);
            $row = $stmt->fetch();
            assert_true($row['submitted_at'] !== null, 'Open attempt should be submitted when the quiz ends');
            assert_eq((float) $row['total_score'], 3.0, 'Open attempt should be graded');
        } finally {
            $pdo->prepare('DELETE FROM student_attempts WHERE id = ?')->execute([$attempt_id]);
            $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([$quiz_id]);
        }
    }

    private function testAuthGuards(): void
    {
        $this->asGuest();
        $endpoints = [
            ['api/faculty/get_quiz_results.php', ['quiz_id' => 1]],
            ['api/faculty/get_lobby_students.php', ['id' => 1]],
            ['api/faculty/get_item_analysis.php', ['quiz_id' => 1]],
            ['api/faculty/get_live_monitoring_data.php', ['id' => 1]],
        ];
        foreach ($endpoints as [$path, $params]) {
            $res = $this->http->get($path, $params);
            assert_eq($res['status'], 403, "$path should return 403 for guest");
        }
    }

    private function testGetQuizResultsMissingId(): void
    {
        $this->loginAsFaculty();
        $res = $this->http->get('api/faculty/get_quiz_results.php');
        assert_eq($res['status'], 400, 'Missing quiz_id should return 400');
        assert_key($res['json'], 'error', 'Should have error key');
    }

    private function testGetQuizResults(): void
    {
        $this->loginAsFaculty();
        $quiz_id = $GLOBALS['TEST']['quiz_id'];
        $res     = $this->http->get('api/faculty/get_quiz_results.php', ['quiz_id' => $quiz_id]);
        assert_eq($res['status'], 200, 'get_quiz_results should return 200');
        assert_key($res['json'], 'summary', 'Response should contain summary key');
        assert_key($res['json'], 'details', 'Response should contain details key');
    }

    private function testGetLobbyStudents(): void
    {
        $this->loginAsFaculty();
        // Correct param is ?id= (not ?quiz_id=)
        $res = $this->http->get('api/faculty/get_lobby_students.php', [
            'id' => $GLOBALS['TEST']['quiz_id'],
        ]);
        assert_eq($res['status'], 200, 'get_lobby_students should return 200');
        assert_true(is_array($res['json']), 'Response should be a JSON array');
    }

    private function testGetLiveMonitoringData(): void
    {
        $this->loginAsFaculty();
        // Correct param is ?id= (not ?quiz_id=); requires faculty session
        $res = $this->http->get('api/faculty/get_live_monitoring_data.php', [
            'id' => $GLOBALS['TEST']['quiz_id'],
        ]);
        assert_eq($res['status'], 200, 'get_live_monitoring_data should return 200');
        assert_true(is_array($res['json']) || is_object($res['json']), 'Should return JSON');
    }

    private function testAddManualQuestion(): void
    {
        $this->loginAsFaculty();
        $quiz_id = $GLOBALS['TEST']['quiz_id'];

        // Correct params: options[], correct_answer_single (index of correct option)
        $res = $this->http->post('api/faculty/add_manual_question.php', [
            'quiz_id'               => $quiz_id,
            'question_text'         => 'TEST: What is the capital of France?',
            'question_type_id'      => 1,   // MCQ
            'difficulty_id'         => 1,   // Easy
            'points'                => 1,
            'options'               => ['Paris', 'London', 'Berlin', 'Rome'],
            'correct_answer_single' => '0', // Index 0 = Paris is correct
        ]);
        assert_in($res['status'], [302, 200], 'add_manual_question should not fail');

        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare(
            "SELECT id FROM questions WHERE quiz_id = ? AND question_text LIKE '%France%' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$quiz_id]);
        $row = $stmt->fetch();
        if ($row) {
            $GLOBALS['TEST']['temp_question_id'] = (int)$row['id'];
        }
        assert_true($row !== false, 'Question should exist in DB after add_manual_question');
    }

    private function testDeleteQuestion(): void
    {
        $this->loginAsFaculty();
        $question_id = $GLOBALS['TEST']['temp_question_id'] ?? null;
        if (!$question_id) return;

        // delete_question reads JSON body: question_id
        $res = $this->http->postJson('api/faculty/delete_question.php', [
            'question_id' => $question_id,
        ]);
        assert_eq($res['status'], 200, 'delete_question should return 200');
        assert_true($res['json']['success'] ?? false, 'delete_question should return success=true');

        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare("SELECT id FROM questions WHERE id = ?");
        $stmt->execute([$question_id]);
        assert_true($stmt->fetch() === false, 'Question should be removed from DB');
    }

    private function testUpdateQuizStatus(): void
    {
        $this->loginAsFaculty();
        $quiz_id = $GLOBALS['TEST']['quiz_id'];

        // Uses JSON body: quiz_id + new_status_id
        // Move to Completed (4) — allowed from any status
        $res = $this->http->postJson('api/faculty/update_quiz_status.php', [
            'quiz_id'       => $quiz_id,
            'new_status_id' => 4,
        ]);
        assert_eq($res['status'], 200, 'update_quiz_status to Completed should return 200');
        assert_true($res['json']['success'] ?? false, 'update_quiz_status should return success=true');

        // Restore to In Progress (3) so student tests that depend on it still work
        $this->http->postJson('api/faculty/update_quiz_status.php', [
            'quiz_id'       => $quiz_id,
            'new_status_id' => 3,
        ]);
    }

    private function testPublishResults(): void
    {
        $this->loginAsFaculty();
        // publish_results reads JSON body: quiz_id (+ optional action)
        $res = $this->http->postJson('api/faculty/publish_results.php', [
            'quiz_id' => $GLOBALS['TEST']['quiz_id'],
            'action'  => 'publish',
        ]);
        assert_eq($res['status'], 200, 'publish_results should return 200');
        assert_true($res['json']['success'] ?? false, 'publish_results should return success=true');
    }

    private function testGetItemAnalysis(): void
    {
        $this->loginAsFaculty();
        $res = $this->http->get('api/faculty/get_item_analysis.php', [
            'quiz_id' => $GLOBALS['TEST']['quiz_id'],
        ]);
        assert_eq($res['status'], 200, 'get_item_analysis should return 200');
        assert_true(is_array($res['json']), 'get_item_analysis should return JSON array');
    }

    private function testExportResults(): void
    {
        $this->loginAsFaculty();
        $res = $this->http->get('api/faculty/export_results.php', [
            'quiz_id' => $GLOBALS['TEST']['quiz_id'],
        ]);
        assert_in($res['status'], [200, 302, 404], 'export_results should not return 5xx');
    }
}
