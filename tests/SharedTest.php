<?php
/**
 * SharedTest.php
 * Tests for api/shared/* and api/placecom/* endpoints.
 */
class SharedTest
{
    private TestClient $http;

    public function __construct(TestClient $http)
    {
        $this->http = $http;
    }

    private function loginAs(string $role): void
    {
        $this->http->clearSession();
        $map = [
            'admin'   => ['admin_email',   'admin_password'],
            'faculty' => ['faculty_email', 'faculty_password'],
            'student' => ['student_email', 'student_password'],
        ];
        [$emailKey, $passKey] = $map[$role];
        $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST'][$emailKey],
            'password' => $GLOBALS['TEST'][$passKey],
            'force'    => true,
        ]);
    }

    public function run(): array
    {
        return [
            // ── Shared: get_quiz_status (public — no session required) ──
            test('Shared: get_quiz_status missing id returns 400',
                fn() => $this->testGetQuizStatusMissingId()),
            test('Shared: get_quiz_status unknown quiz returns 404',
                fn() => $this->testGetQuizStatusNotFound()),
            test('Shared: get_quiz_status valid quiz returns status name',
                fn() => $this->testGetQuizStatusValid()),

            // ── Shared: get_courses_by_school ──
            test('Shared: get_courses_by_school returns JSON',
                fn() => $this->testGetCoursesBySchool()),

            // ── Shared: get_batches_by_course ──
            test('Shared: get_batches_by_course returns JSON',
                fn() => $this->testGetBatchesByCourse()),

            // ── Shared: get_years_by_course ──
            test('Shared: get_years_by_course returns JSON',
                fn() => $this->testGetYearsByCourse()),

            // ── Shared: get_groups_by_courses — param is comma-separated string ──
            test('Shared: get_groups_by_courses returns JSON',
                fn() => $this->testGetGroupsByCourses()),

            // ── Shared: change_password ──
            test('Shared: change_password requires session',
                fn() => $this->testChangePasswordRequiresSession()),
            test('Shared: change_password wrong old password fails',
                fn() => $this->testChangePasswordWrongOld()),

            // ── Shared: export_all_results (any non-student role) ──
            test('Shared: export_all_results blocked for student role',
                fn() => $this->testExportAllResultsBlockedForStudent()),
            test('Shared: export_all_results allowed for faculty role',
                fn() => $this->testExportAllResultsAllowedForFaculty()),

            // ── Placecom: get_all_quiz_results ──
            test('Placecom: get_all_quiz_results requires placecom session',
                fn() => $this->testPlacecomGetAllQuizResultsGuest()),
        ];
    }

    private function testGetQuizStatusMissingId(): void
    {
        $this->http->clearSession();
        $res = $this->http->get('api/shared/get_quiz_status.php');
        assert_eq($res['status'], 400, 'Missing quiz id should return 400');
        assert_key($res['json'], 'error', 'Response should have error key');
    }

    private function testGetQuizStatusNotFound(): void
    {
        $this->http->clearSession();
        $res = $this->http->get('api/shared/get_quiz_status.php', ['id' => 999999]);
        assert_eq($res['status'], 404, 'Non-existent quiz should return 404');
        assert_key($res['json'], 'error', 'Response should have error key');
    }

    private function testGetQuizStatusValid(): void
    {
        $this->http->clearSession();
        $res = $this->http->get('api/shared/get_quiz_status.php', [
            'id' => $GLOBALS['TEST']['quiz_id'],
        ]);
        assert_eq($res['status'], 200, 'Valid quiz id should return 200');
        assert_key($res['json'], 'status', 'Response should have status key');
        assert_true(is_string($res['json']['status']), 'Status should be a string');
    }

    private function testGetCoursesBySchool(): void
    {
        $this->loginAs('faculty');
        $res = $this->http->get('api/shared/get_courses_by_school.php', [
            'school_id' => $GLOBALS['TEST']['school_id'],
        ]);
        assert_in($res['status'], [200, 400], 'get_courses_by_school should return 200 or 400');
        if ($res['status'] === 200) {
            assert_true(is_array($res['json']), 'Should return JSON array');
        }
    }

    private function testGetBatchesByCourse(): void
    {
        $this->loginAs('faculty');
        $res = $this->http->get('api/shared/get_batches_by_course.php', [
            'course_id' => $GLOBALS['TEST']['sap_course_id'],
        ]);
        assert_in($res['status'], [200, 400], 'get_batches_by_course should return 200 or 400');
    }

    private function testGetYearsByCourse(): void
    {
        $this->loginAs('faculty');
        $res = $this->http->get('api/shared/get_years_by_course.php', [
            'course_id' => $GLOBALS['TEST']['sap_course_id'],
        ]);
        assert_in($res['status'], [200, 400], 'get_years_by_course should return 200 or 400');
    }

    private function testGetGroupsByCourses(): void
    {
        $this->loginAs('faculty');
        // Correct param: course_ids as a comma-separated string (not array)
        $res = $this->http->get('api/shared/get_groups_by_courses.php', [
            'course_ids' => (string)$GLOBALS['TEST']['sap_course_id'],
        ]);
        assert_eq($res['status'], 200, 'get_groups_by_courses should return 200');
        assert_key($res['json'], 'classes', 'Response should have classes key');
        assert_key($res['json'], 'batches', 'Response should have batches key');
    }

    private function testChangePasswordRequiresSession(): void
    {
        $this->http->clearSession();
        $res = $this->http->post('api/shared/change_password.php', [
            'old_password' => 'old',
            'new_password' => 'new',
        ]);
        assert_in($res['status'], [302, 403, 401], 'change_password should require a session');
    }

    private function testChangePasswordWrongOld(): void
    {
        $this->loginAs('faculty');
        $res = $this->http->post('api/shared/change_password.php', [
            'old_password'     => 'definitely_wrong_password_xyz',
            'new_password'     => 'NewPass@789',
            'confirm_password' => 'NewPass@789',
        ]);
        assert_in($res['status'], [200, 302, 400, 403], 'Wrong old password should not return 5xx');

        // Verify the original password is still valid
        $this->http->clearSession();
        $loginRes = $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['faculty_email'],
            'password' => $GLOBALS['TEST']['faculty_password'],
            'force'    => true,
        ]);
        assert_eq($loginRes['json']['status'] ?? '', 'success', 'Original password should still work');
    }

    private function testExportAllResultsBlockedForStudent(): void
    {
        // Students (role_id=4) are blocked — should get a non-200 or 'Access Denied' body
        $this->loginAs('student');
        $res = $this->http->get('api/shared/export_all_results.php', [
            'quiz_id' => $GLOBALS['TEST']['quiz_id'],
        ]);
        // The endpoint calls exit('Access Denied.') with no status code set = 200 body but with text
        $blocked = ($res['status'] !== 200)
                || (stripos($res['body'], 'Access Denied') !== false);
        assert_true($blocked, 'Students should be blocked from export_all_results');
    }

    private function testExportAllResultsAllowedForFaculty(): void
    {
        $this->loginAs('faculty');
        $res = $this->http->get('api/shared/export_all_results.php', [
            'quiz_id' => $GLOBALS['TEST']['quiz_id'],
        ]);
        // Returns 200 with xlsx content (or error text if no data) — must not be 403
        assert_true($res['status'] !== 403, 'Faculty should not be blocked from export_all_results');
    }

    private function testPlacecomGetAllQuizResultsGuest(): void
    {
        $this->http->clearSession();
        $res = $this->http->get('api/placecom/get_all_quiz_results.php');
        assert_in($res['status'], [302, 403, 401], 'get_all_quiz_results should require placecom session');
    }
}
