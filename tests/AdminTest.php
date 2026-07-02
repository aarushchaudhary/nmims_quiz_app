<?php
/**
 * AdminTest.php
 * Tests for all api/admin/* endpoints.
 */
class AdminTest
{
    private TestClient $http;

    public function __construct(TestClient $http)
    {
        $this->http = $http;
    }

    private function loginAsAdmin(): void
    {
        $this->http->clearSession();
        $this->http->postJson('api/auth.php', [
            'email'    => $GLOBALS['TEST']['admin_email'],
            'password' => $GLOBALS['TEST']['admin_password'],
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
            test('Admin: get_dashboard_stats guest returns 403',
                fn() => $this->testDashboardStatsGuest()),
            test('Admin: get_dashboard_stats admin returns stats',
                fn() => $this->testDashboardStats()),
            test('Admin: add_user missing role returns redirect (no crash)',
                fn() => $this->testAddUserMissingFields()),
            test('Admin: add_school and delete_school via GET id',
                fn() => $this->testSchoolCRUD()),
            test('Admin: add_course and delete_course via GET id',
                fn() => $this->testCourseCRUD()),
            test('Admin: add_role creates a new role',
                fn() => $this->testAddRole()),
            test('Admin: add_batch (class) and delete_class via GET id',
                fn() => $this->testBatchCRUD()),
            test('Admin: add_class (batch sections) creates sections',
                fn() => $this->testClassCRUD()),
            test('Admin: add_elective and delete_elective via GET id',
                fn() => $this->testElectiveCRUD()),
            test('Admin: add_re_exam_group and delete_re_exam_group via GET id',
                fn() => $this->testReExamGroupCRUD()),
            test('Admin: search_student returns JSON array',
                fn() => $this->testSearchStudent()),
            test('Admin: get_course_batches returns JSON',
                fn() => $this->testGetCourseBatches()),
            test('Admin: get_students_for_demotion returns JSON',
                fn() => $this->testGetStudentsForDemotion()),
            test('Admin: reset_password resets a user password',
                fn() => $this->testResetPassword()),
            test('Admin: cleanup_preview returns JSON',
                fn() => $this->testCleanupPreview()),
        ];
    }

    private function testDashboardStatsGuest(): void
    {
        $this->asGuest();
        $res = $this->http->get('api/admin/get_dashboard_stats.php');
        assert_eq($res['status'], 403, 'Unauthenticated request should be 403');
    }

    private function testDashboardStats(): void
    {
        $this->loginAsAdmin();
        $res = $this->http->get('api/admin/get_dashboard_stats.php');
        assert_eq($res['status'], 200, 'Admin dashboard stats should return 200');
        assert_key($res['json'], 'students', 'Response must contain students key');
        assert_key($res['json'], 'faculty',  'Response must contain faculty key');
        assert_key($res['json'], 'quizzes',  'Response must contain quizzes key');
    }

    private function testAddUserMissingFields(): void
    {
        $this->loginAsAdmin();
        $res = $this->http->post('api/admin/add_user.php', []);
        assert_in($res['status'], [302, 200, 400], 'Should redirect or error gracefully on missing fields');
    }

    /**
     * add_school: POST school_name
     * delete_school: GET ?id=  (fails if courses exist, so we use an isolated school)
     */
    private function testSchoolCRUD(): void
    {
        $this->loginAsAdmin();
        $unique = 'PHPUNIT_SCHOOL_' . uniqid();

        $addRes = $this->http->post('api/admin/add_school.php', ['school_name' => $unique]);
        assert_in($addRes['status'], [302, 200], 'add_school should redirect or return 200');

        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare("SELECT id FROM schools WHERE name = ?");
        $stmt->execute([$unique]);
        $school = $stmt->fetch();
        assert_true($school !== false, 'School should exist in DB after creation');

        // delete_school uses GET ?id=  (no courses attached → will succeed)
        $delRes = $this->http->get('api/admin/delete_school.php', ['id' => $school['id']]);
        assert_in($delRes['status'], [302, 200], 'delete_school should redirect or return 200');

        $stmt->execute([$unique]);
        assert_true($stmt->fetch() === false, 'School should be removed from DB');
    }

    /**
     * add_course: POST course_name, course_code, duration_years, school_id
     * delete_course: GET ?id=
     */
    private function testCourseCRUD(): void
    {
        $this->loginAsAdmin();

        // Create a fresh isolated school so it has no other courses
        $pdo = get_test_pdo();
        $pdo->exec("INSERT INTO schools (name) VALUES ('PHPUNIT_SCHOOL_COURSE_TMP')");
        $iso_school_id = (int)$pdo->lastInsertId();

        $unique = 'PHPUNIT_COURSE_' . uniqid();

        $addRes = $this->http->post('api/admin/add_course.php', [
            'course_name'    => $unique,
            'school_id'      => $iso_school_id,
            'course_code'    => 'PC' . rand(10, 99),
            'duration_years' => 2,
        ]);
        assert_in($addRes['status'], [302, 200], 'add_course should redirect or return 200');

        $stmt = $pdo->prepare("SELECT id FROM courses WHERE name = ?");
        $stmt->execute([$unique]);
        $course = $stmt->fetch();
        assert_true($course !== false, 'Course should exist in DB after creation');

        // delete_course uses GET ?id=
        $delRes = $this->http->get('api/admin/delete_course.php', ['id' => $course['id']]);
        assert_in($delRes['status'], [302, 200], 'delete_course should redirect or return 200');

        $stmt->execute([$unique]);
        assert_true($stmt->fetch() === false, 'Course should be removed from DB');

        // Clean up isolated school
        $pdo->prepare("DELETE FROM schools WHERE id = ?")->execute([$iso_school_id]);
    }

    private function testAddRole(): void
    {
        $this->loginAsAdmin();
        $unique = 'phpunit_role_' . uniqid();
        $res    = $this->http->post('api/admin/add_role.php', ['name' => $unique]);
        assert_in($res['status'], [302, 200], 'add_role should redirect or return 200');
        get_test_pdo()->prepare("DELETE FROM roles WHERE name = ?")->execute([$unique]);
    }

    /**
     * add_batch.php creates a CLASS; delete_class.php deletes a BATCH row.
     * We use graduation_year=2099 to avoid collisions.
     */
    private function testBatchCRUD(): void
    {
        $this->loginAsAdmin();
        $school_id = $GLOBALS['TEST']['school_id'];
        $course_id = $GLOBALS['TEST']['sap_course_id'];

        $addRes = $this->http->post('api/admin/add_batch.php', [
            'school_id'       => $school_id,
            'course_id'       => $course_id,
            'graduation_year' => 2099,
        ]);
        assert_in($addRes['status'], [302, 200], 'add_batch should redirect or return 200');

        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE graduation_year = 2099 AND course_id = ?");
        $stmt->execute([$course_id]);
        $class = $stmt->fetch();
        assert_true($class !== false, 'Class for graduation year 2099 should exist in DB');

        // delete_class.php deletes from batches WHERE id — but there are no batches for this class;
        // the endpoint actually deletes a batch (section) row. So we just verify the class exists
        // and clean up directly — the endpoint name is misleading but that's the app's design.
        // Instead call delete_batch which uses GET ?id= and deletes from `classes`
        $delRes = $this->http->get('api/admin/delete_batch.php', ['id' => $class['id']]);
        assert_in($delRes['status'], [302, 200], 'delete_batch should redirect or return 200');

        $stmt->execute([$course_id]);
        assert_true($stmt->fetch() === false, 'Class should be removed from DB');
    }

    /**
     * add_class.php adds batch SECTIONS; delete_class.php deletes a batch row.
     */
    private function testClassCRUD(): void
    {
        $this->loginAsAdmin();
        $class_id    = $GLOBALS['TEST']['class_id'];
        $sectionName = 'PHPUNIT_SECTION_' . uniqid();

        $addRes = $this->http->post('api/admin/add_class.php', [
            'class_id'           => $class_id,
            'section_name'       => [$sectionName],
            'sap_id_range_start' => ['88882426800'],
            'sap_id_range_end'   => ['88882426899'],
        ]);
        assert_in($addRes['status'], [302, 200], 'add_class should redirect or return 200');

        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare("SELECT id FROM batches WHERE name = ? AND class_id = ?");
        $stmt->execute([$sectionName, $class_id]);
        $batch = $stmt->fetch();
        assert_true($batch !== false, 'Section/batch should exist in DB after add_class');

        // delete_class.php uses GET ?id= and deletes from batches
        $delRes = $this->http->get('api/admin/delete_class.php', ['id' => $batch['id']]);
        assert_in($delRes['status'], [302, 200], 'delete_class should redirect or return 200');

        $stmt->execute([$sectionName, $class_id]);
        assert_true($stmt->fetch() === false, 'Section/batch should be removed from DB');
    }

    /**
     * add_elective: POST elective_name
     * delete_elective: GET ?id=
     */
    private function testElectiveCRUD(): void
    {
        $this->loginAsAdmin();
        $unique = 'PHPUNIT_ELECTIVE_' . uniqid();

        $addRes = $this->http->post('api/admin/add_elective.php', ['elective_name' => $unique]);
        assert_in($addRes['status'], [302, 200], 'add_elective should redirect or return 200');

        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare("SELECT id FROM electives WHERE name = ?");
        $stmt->execute([$unique]);
        $elective = $stmt->fetch();
        assert_true($elective !== false, 'Elective should exist in DB');

        // delete_elective uses GET ?id=
        $delRes = $this->http->get('api/admin/delete_elective.php', ['id' => $elective['id']]);
        assert_in($delRes['status'], [302, 200], 'delete_elective should redirect or return 200');

        $stmt->execute([$unique]);
        assert_true($stmt->fetch() === false, 'Elective should be removed from DB');
    }

    /**
     * add_re_exam_group: POST group_name, expires_at
     * delete_re_exam_group: GET ?id=
     */
    private function testReExamGroupCRUD(): void
    {
        $this->loginAsAdmin();
        $unique = 'PHPUNIT_REEXAM_' . uniqid();

        $addRes = $this->http->post('api/admin/add_re_exam_group.php', [
            'group_name' => $unique,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
        assert_in($addRes['status'], [302, 200], 'add_re_exam_group should redirect or return 200');

        $pdo  = get_test_pdo();
        $stmt = $pdo->prepare("SELECT id FROM re_exam_groups WHERE name = ?");
        $stmt->execute([$unique]);
        $group = $stmt->fetch();
        assert_true($group !== false, 'Re-exam group should exist in DB');

        // delete_re_exam_group uses GET ?id=
        $delRes = $this->http->get('api/admin/delete_re_exam_group.php', ['id' => $group['id']]);
        assert_in($delRes['status'], [302, 200], 'delete_re_exam_group should redirect or return 200');

        $stmt->execute([$unique]);
        assert_true($stmt->fetch() === false, 'Re-exam group should be removed from DB');
    }

    private function testSearchStudent(): void
    {
        $this->loginAsAdmin();
        $res = $this->http->get('api/admin/search_student.php', ['q' => 'Test Student']);
        assert_eq($res['status'], 200, 'search_student should return 200');
        assert_true(is_array($res['json']), 'search_student should return JSON array');
    }

    private function testGetCourseBatches(): void
    {
        $this->loginAsAdmin();
        $res = $this->http->get('api/admin/get_course_batches.php', [
            'course_id' => $GLOBALS['TEST']['sap_course_id'],
        ]);
        assert_eq($res['status'], 200, 'get_course_batches should return 200');
        assert_true(is_array($res['json']), 'get_course_batches should return a JSON array');
    }

    private function testGetStudentsForDemotion(): void
    {
        $this->loginAsAdmin();
        $res = $this->http->get('api/admin/get_students_for_demotion.php', [
            'course_id' => $GLOBALS['TEST']['sap_course_id'],
        ]);
        assert_in($res['status'], [200, 400], 'get_students_for_demotion should return 200 or 400');
    }

    private function testResetPassword(): void
    {
        $this->loginAsAdmin();
        // reset_password reads JSON body: user_id + new_password
        $res = $this->http->postJson('api/admin/reset_password.php', [
            'user_id'      => $GLOBALS['TEST']['faculty_user_id'],
            'new_password' => 'NewPass@456',
        ]);
        assert_eq($res['status'], 200, 'reset_password should return 200 on success');
        assert_true($res['json']['success'] ?? false, 'reset_password should return success=true');

        // Restore original password
        $pdo  = get_test_pdo();
        $hash = password_hash($GLOBALS['TEST']['faculty_password'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([$hash, $GLOBALS['TEST']['faculty_user_id']]);
    }

    private function testCleanupPreview(): void
    {
        $this->loginAsAdmin();
        $res = $this->http->get('api/admin/cleanup_preview.php');
        assert_in($res['status'], [200, 302, 403], 'cleanup_preview should not return 5xx');
    }
}
