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
            test('Faculty: export_results returns content',
                fn() => $this->testExportResults()),
        ];
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
