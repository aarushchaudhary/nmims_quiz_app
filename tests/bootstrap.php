<?php
/**
 * bootstrap.php
 * Provides test fixtures: creates all required test data before tests run,
 * then tears everything down cleanly afterwards.
 *
 * Run order:  bootstrap_setup()  →  run tests  →  bootstrap_teardown()
 *
 * DB credentials are pulled from the app's own config, so this file must be
 * included from the project root or the paths adjusted accordingly.
 */

// ─── DB connection (reuse app config constants) ───────────────────────────────
define('TEST_DB_HOST', '127.0.0.1');
define('TEST_DB_PORT', '3306');
define('TEST_DB_NAME', 'nmims_quiz_app');
define('TEST_DB_USER', 'nmims_quiz_app');
define('TEST_DB_PASS', '123456');

function get_test_pdo(): PDO
{
    static $pdo;
    if ($pdo) return $pdo;

    $dsn = "mysql:host=" . TEST_DB_HOST . ":" . TEST_DB_PORT
         . ";dbname=" . TEST_DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, TEST_DB_USER, TEST_DB_PASS, [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

// ─── Globals that tests can read ──────────────────────────────────────────────
$GLOBALS['TEST'] = [];

// ─── Pre-run cleanup (removes stale fixtures from interrupted runs) ───────────
function bootstrap_cleanup(): void
{
    $pdo = get_test_pdo();

    // Step 1: Find all TEST_QUIZ quiz IDs and delete their answers/attempts first
    $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE title = 'TEST_QUIZ'");
    $stmt->execute();
    $quizIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($quizIds as $qid) {
        $aStmt = $pdo->prepare("SELECT id FROM student_attempts WHERE quiz_id = ?");
        $aStmt->execute([$qid]);
        $attemptIds = $aStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($attemptIds as $aid) {
            $pdo->prepare("DELETE FROM student_answers WHERE attempt_id = ?")->execute([$aid]);
            $pdo->prepare("DELETE FROM event_logs      WHERE attempt_id = ?")->execute([$aid]);
        }
        $pdo->prepare("DELETE FROM student_attempts WHERE quiz_id = ?")->execute([$qid]);
    }

    // Step 2: Now safe to delete the quizzes (cascades to questions, options, etc.)
    $pdo->prepare("DELETE FROM quizzes WHERE title = 'TEST_QUIZ'")->execute();

    // Step 3: Look up test user IDs
    $testEmails = [
        'test_admin@nmims.in',
        'test_faculty@nmims.in',
        'test_student@nmims.in',
    ];
    $userIds = [];
    foreach ($testEmails as $email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $userIds[] = (int)$row['id'];
        }
    }

    // Step 4: Delete profile rows (no CASCADE on faculties/admins/students → users)
    foreach ($userIds as $uid) {
        $pdo->prepare("DELETE FROM admins            WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM faculties         WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM students          WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM placecom_officers WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM heads             WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM users             WHERE id      = ?")->execute([$uid]);
    }

    // Step 5: Delete structural lookup fixtures by known name (in safe FK order)
    $pdo->prepare("DELETE FROM electives      WHERE name = 'TEST_ELECTIVE_PHPUNIT'")->execute();
    $pdo->prepare("DELETE FROM re_exam_groups WHERE name = 'TEST_REEXAM' OR name LIKE 'PHPUNIT_REEXAM_%'")->execute();
    $pdo->prepare("DELETE FROM batches        WHERE name LIKE 'PHPUNIT_BATCH_%' OR name LIKE 'PHPUNIT_SECTION_%'")->execute();
    $pdo->prepare("DELETE FROM classes        WHERE name = 'TEST_CLASS' OR name LIKE 'PHPUNIT_CLASS_%'")->execute();
    $pdo->prepare("DELETE FROM courses        WHERE name IN ('TEST_COURSE','TEST_COURSE_SAP') OR name LIKE 'PHPUNIT_COURSE_%'")->execute();
    $pdo->prepare("DELETE FROM schools        WHERE name = 'TEST_SCHOOL_PHPUNIT'")->execute();
}


// ─── Setup ────────────────────────────────────────────────────────────────────
function bootstrap_setup(): void
{
    $pdo = get_test_pdo();

    // Always clean up leftovers from any previous interrupted run first
    bootstrap_cleanup();

    // --- School ---
    $pdo->exec("INSERT INTO schools (name) VALUES ('TEST_SCHOOL_PHPUNIT')");
    $school_id = (int)$pdo->lastInsertId();
    $GLOBALS['TEST']['school_id'] = $school_id;

    // --- Course (code starts with 4 chars matching SAP logic) ---
    $pdo->exec("INSERT INTO courses (school_id, name, code, duration_years)
                VALUES ($school_id, 'TEST_COURSE', 'TC01', 2)");
    $course_id = (int)$pdo->lastInsertId();
    $GLOBALS['TEST']['course_id'] = $course_id;

    // --- Roles are pre-seeded; just grab IDs ---
    //  1=admin, 2=faculty, 3=placecom, 4=student, 5=school_head

    // --- Admin user ---
    $admin_hash = password_hash('Admin@123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (email, password_hash, role_id)
                VALUES ('test_admin@nmims.in', '$admin_hash', 1)");
    $admin_user_id = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO admins (user_id, name) VALUES ($admin_user_id, 'Test Admin')");
    $GLOBALS['TEST']['admin_user_id']    = $admin_user_id;
    $GLOBALS['TEST']['admin_email']      = 'test_admin@nmims.in';
    $GLOBALS['TEST']['admin_password']   = 'Admin@123';

    // --- Faculty user ---
    $faculty_hash = password_hash('Faculty@123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (email, password_hash, role_id)
                VALUES ('test_faculty@nmims.in', '$faculty_hash', 2)");
    $faculty_user_id = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO faculties (user_id, name, sap_id, school_id, is_visiting)
                VALUES ($faculty_user_id, 'Test Faculty', 'FAC0001', $school_id, 0)");
    $GLOBALS['TEST']['faculty_user_id']  = $faculty_user_id;
    $GLOBALS['TEST']['faculty_email']    = 'test_faculty@nmims.in';
    $GLOBALS['TEST']['faculty_password'] = 'Faculty@123';

    // --- Student user (SAP format: CCCC + YY + YY + ### => 11 digits) ---
    // Using course code TC01 → "TC01" but SAP must be 11 numeric digits.
    // We'll use a pattern that reflects the DB: first 4 chars = numeric course code.
    // Create a numeric course code course for SAP-based student creation.
    $pdo->exec("INSERT INTO courses (school_id, name, code, duration_years)
                VALUES ($school_id, 'TEST_COURSE_SAP', '8888', 2)");
    $sap_course_id = (int)$pdo->lastInsertId();
    $GLOBALS['TEST']['sap_course_id'] = $sap_course_id;

    $sap_id    = '88882426001'; // course 8888, 2024-2026, student 001
    $stu_hash  = password_hash($sap_id, PASSWORD_DEFAULT); // initial password = sap_id
    $pdo->exec("INSERT INTO users (email, password_hash, role_id)
                VALUES ('test_student@nmims.in', '$stu_hash', 4)");
    $student_user_id = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO students (user_id, name, sap_id, course_id, batch, graduation_year)
                VALUES ($student_user_id, 'Test Student', '$sap_id', $sap_course_id, '2024-2026', 2026)");
    $GLOBALS['TEST']['student_user_id']  = $student_user_id;
    $GLOBALS['TEST']['student_email']    = 'test_student@nmims.in';
    $GLOBALS['TEST']['student_password'] = $sap_id;
    $GLOBALS['TEST']['student_sap_id']   = $sap_id;

    // --- Quiz (status 3 = In Progress, so student answers can be saved) ---
    $pdo->exec("INSERT INTO quizzes
                    (title, faculty_id, course_id, start_time, end_time,
                     duration_minutes, status_id,
                     config_easy_count, config_medium_count, config_hard_count)
                VALUES ('TEST_QUIZ', $faculty_user_id, $sap_course_id,
                        DATE_SUB(NOW(), INTERVAL 10 MINUTE),
                        DATE_ADD(NOW(), INTERVAL 50 MINUTE),
                        60, 3, 1, 0, 0)");
    $quiz_id = (int)$pdo->lastInsertId();
    $GLOBALS['TEST']['quiz_id'] = $quiz_id;

    // --- One easy question with options ---
    $pdo->exec("INSERT INTO questions (quiz_id, question_type_id, difficulty_id, question_text, points)
                VALUES ($quiz_id, 1, 1, 'TEST Q: What is 2+2?', 1.00)");
    $question_id = (int)$pdo->lastInsertId();
    $GLOBALS['TEST']['question_id'] = $question_id;

    $pdo->exec("INSERT INTO options (question_id, option_text, is_correct) VALUES
                ($question_id, '3', 0),
                ($question_id, '4', 1),
                ($question_id, '5', 0),
                ($question_id, '6', 0)");

    // Grab the correct option ID for use in save_answer tests
    $stmt = $pdo->prepare("SELECT id FROM options WHERE question_id = ? AND is_correct = 1");
    $stmt->execute([$question_id]);
    $correct_option = $stmt->fetch();
    $GLOBALS['TEST']['correct_option_id'] = $correct_option ? (int)$correct_option['id'] : null;

    // --- Elective ---
    $pdo->exec("INSERT INTO electives (name) VALUES ('TEST_ELECTIVE_PHPUNIT')");
    $GLOBALS['TEST']['elective_id'] = (int)$pdo->lastInsertId();

    // --- Class ---
    $pdo->exec("INSERT INTO classes (name, school_id, course_id, graduation_year, sap_id_range_start, sap_id_range_end)
                VALUES ('TEST_CLASS', $school_id, $sap_course_id, 2026, 88882426000, 88882426999)");
    $GLOBALS['TEST']['class_id'] = (int)$pdo->lastInsertId();

    // --- Re-exam group ---
    $pdo->exec("INSERT INTO re_exam_groups (name, expires_at) VALUES ('TEST_REEXAM', DATE_ADD(NOW(), INTERVAL 7 DAY))");
    $GLOBALS['TEST']['re_exam_group_id'] = (int)$pdo->lastInsertId();

    echo colorize("[SETUP]", 'cyan') . " Test fixtures created.\n";
}

// ─── Teardown ─────────────────────────────────────────────────────────────────
function bootstrap_teardown(): void
{
    $pdo = get_test_pdo();
    $T   = $GLOBALS['TEST'];

    // 1. Delete student_answers first (FK → questions which FK → quizzes)
    if (!empty($T['quiz_id'])) {
        // Get all attempt IDs for this quiz
        $stmt = $pdo->prepare("SELECT id FROM student_attempts WHERE quiz_id = ?");
        $stmt->execute([$T['quiz_id']]);
        $attemptIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($attemptIds as $aid) {
            $pdo->prepare("DELETE FROM student_answers WHERE attempt_id = ?")->execute([$aid]);
            $pdo->prepare("DELETE FROM event_logs WHERE attempt_id = ?")->execute([$aid]);
        }
        $pdo->prepare("DELETE FROM student_attempts WHERE quiz_id = ?")->execute([$T['quiz_id']]);
    }

    // 2. Now delete the quiz (cascades to questions, options, quiz_classes, etc.)
    if (!empty($T['quiz_id'])) {
        $pdo->prepare("DELETE FROM quizzes WHERE id = ?")->execute([$T['quiz_id']]);
    }

    // 3. Delete other lookup fixtures (classes before courses, electives/re-exam stand-alone)
    if (!empty($T['class_id'])) {
        $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$T['class_id']]);
    }
    foreach ([
        'elective_id'      => "DELETE FROM electives WHERE id = ?",
        're_exam_group_id' => "DELETE FROM re_exam_groups WHERE id = ?",
    ] as $key => $sql) {
        if (!empty($T[$key])) {
            $pdo->prepare($sql)->execute([$T[$key]]);
        }
    }

    // 4. Delete profile rows before users (no CASCADE on faculties/admins/students → users)
    foreach (['student_user_id', 'faculty_user_id', 'admin_user_id'] as $key) {
        if (!empty($T[$key])) {
            $uid = $T[$key];
            $pdo->prepare("DELETE FROM admins            WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM faculties         WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM students          WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM placecom_officers WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM heads             WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM users             WHERE id      = ?")->execute([$uid]);
        }
    }

    // 5. Courses (must go before school due to FK)
    foreach (['sap_course_id', 'course_id'] as $key) {
        if (!empty($T[$key])) {
            $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$T[$key]]);
        }
    }

    // 6. School (last — no more FK references)
    if (!empty($T['school_id'])) {
        $pdo->prepare("DELETE FROM schools WHERE id = ?")->execute([$T['school_id']]);
    }

    // 7. Clean up any PHPUNIT_* orphans left by individual tests
    $pdo->prepare("DELETE FROM courses WHERE name LIKE 'PHPUNIT_COURSE_%'")->execute();
    $pdo->prepare("DELETE FROM schools WHERE name LIKE 'PHPUNIT_SCHOOL_%'")->execute();

    echo colorize("[TEARDOWN]", 'cyan') . " Test fixtures removed.\n";
}

