<?php
/*
 * config/authz.php
 * Shared authorization helpers.  Safe to require_once from both API endpoints
 * (../../config/authz.php) and views.  Does NOT start a session and does NOT
 * emit output of its own.
 *
 * Every helper that looks up a record uses a single SELECT with the ownership
 * predicate in the WHERE clause.  Zero rows means "not found OR not yours" —
 * both cases return the same generic error so we don't leak existence.
 */

/**
 * Starting password of every account an admin creates (form or Excel upload).
 * users.must_change_password = 1 forces the change at first login, and
 * api/shared/change_password.php refuses this value as the new password.
 */
const DEFAULT_PASSWORD = 'password';

/** Emit a JSON error with the given HTTP status code and exit. */
function json_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    exit(json_encode(['error' => $message]));
}

/**
 * 401 (JSON) or redirect-to-login (HTML) if no session user.
 * Returns the authenticated user_id as int.
 */
function require_login(bool $json = true): int
{
    if (empty($_SESSION['user_id'])) {
        if ($json) {
            json_fail(401, 'Not authenticated.');
        }
        redirect('login.php');
        exit();
    }
    return (int) $_SESSION['user_id'];
}

/**
 * 403 (JSON) or redirect (HTML) unless the session role_id is in $allowed.
 */
function require_role(array $allowed_role_ids, bool $json = true): void
{
    if (!in_array($_SESSION['role_id'] ?? null, $allowed_role_ids, true)) {
        if ($json) {
            json_fail(403, 'Not found or not authorized.');
        }
        redirect('login.php');
        exit();
    }
}

/**
 * Returns the quiz row if it exists AND quizzes.faculty_id = $faculty_id.
 * 403s and exits otherwise.  Never returns null.
 */
function require_owned_quiz(
    PDO $pdo,
    int $quiz_id,
    int $faculty_id,
    bool $json = true
): array {
    $stmt = $pdo->prepare(
        'SELECT * FROM quizzes WHERE id = ? AND faculty_id = ?'
    );
    $stmt->execute([$quiz_id, $faculty_id]);
    $row = $stmt->fetch();
    if (!$row) {
        if ($json) {
            json_fail(403, 'Not found or not authorized.');
        }
        redirect('views/faculty/manage_quizzes.php?error=not_found');
        exit();
    }
    return $row;
}

/**
 * Returns the question row if it exists AND the quiz it belongs to is owned
 * by $faculty_id.  403s and exits otherwise.
 */
function require_owned_question(
    PDO $pdo,
    int $question_id,
    int $faculty_id,
    bool $json = true
): array {
    $stmt = $pdo->prepare(
        'SELECT q.* FROM questions q
         JOIN quizzes qz ON q.quiz_id = qz.id
         WHERE q.id = ? AND qz.faculty_id = ?'
    );
    $stmt->execute([$question_id, $faculty_id]);
    $row = $stmt->fetch();
    if (!$row) {
        if ($json) {
            json_fail(403, 'Not found or not authorized.');
        }
        redirect('views/faculty/manage_quizzes.php?error=not_found');
        exit();
    }
    return $row;
}

/**
 * Resolves ownership via student_attempts.quiz_id → quizzes.faculty_id.
 * Returns the attempt row.  403s and exits on failure.
 */
function require_owned_attempt_as_faculty(
    PDO $pdo,
    int $attempt_id,
    int $faculty_id,
    bool $json = true
): array {
    $stmt = $pdo->prepare(
        'SELECT sa.* FROM student_attempts sa
         JOIN quizzes qz ON sa.quiz_id = qz.id
         WHERE sa.id = ? AND qz.faculty_id = ?'
    );
    $stmt->execute([$attempt_id, $faculty_id]);
    $row = $stmt->fetch();
    if (!$row) {
        if ($json) {
            json_fail(403, 'Not found or not authorized.');
        }
        redirect('views/faculty/manage_quizzes.php?error=not_found');
        exit();
    }
    return $row;
}

/**
 * Returns the attempt row only if student_attempts.student_id = $student_id.
 * 403s and exits otherwise.
 */
function require_own_attempt(
    PDO $pdo,
    int $attempt_id,
    int $student_id,
    bool $json = true
): array {
    $stmt = $pdo->prepare(
        'SELECT * FROM student_attempts WHERE id = ? AND student_id = ?'
    );
    $stmt->execute([$attempt_id, $student_id]);
    $row = $stmt->fetch();
    if (!$row) {
        if ($json) {
            json_fail(403, 'Not found or not authorized.');
        }
        redirect('views/student/dashboard.php?error=not_found');
        exit();
    }
    return $row;
}


/**
 * SQL condition: student row `s` is assigned to quiz row `q` — manually, by class
 * or batch SAP range, by elective, or by re-exam group.  The one copy of this rule;
 * used by the student dashboard and require_eligible_quiz().
 */
function student_quiz_eligibility_sql(): string
{
    return "(
        EXISTS (SELECT 1 FROM quiz_manual_students qms
                WHERE qms.quiz_id = q.id AND qms.student_id = s.user_id)
        OR EXISTS (SELECT 1 FROM quiz_classes qc JOIN classes c ON c.id = qc.class_id
                   WHERE qc.quiz_id = q.id
                     AND c.course_id = s.course_id AND c.graduation_year = s.graduation_year
                     AND CAST(s.sap_id AS UNSIGNED) BETWEEN c.sap_id_range_start AND c.sap_id_range_end)
        OR EXISTS (SELECT 1 FROM quiz_batches qb JOIN batches b ON b.id = qb.batch_id
                   JOIN classes bc ON bc.id = b.class_id
                   WHERE qb.quiz_id = q.id
                     AND bc.course_id = s.course_id AND bc.graduation_year = s.graduation_year
                     AND CAST(s.sap_id AS UNSIGNED) BETWEEN b.sap_id_range_start AND b.sap_id_range_end)
        OR EXISTS (SELECT 1 FROM quiz_electives qe
                   JOIN elective_students els ON els.elective_id = qe.elective_id
                   WHERE qe.quiz_id = q.id AND els.student_id = s.user_id)
        OR EXISTS (SELECT 1 FROM quiz_re_exam_groups qrg
                   JOIN re_exam_group_students rgs ON rgs.group_id = qrg.group_id
                   WHERE qrg.quiz_id = q.id AND rgs.student_id = s.user_id)
    )";
}

/**
 * Returns the quiz row if the student is assigned to it.
 * 403s (JSON) or redirects to the dashboard (HTML) otherwise.
 */
function require_eligible_quiz(PDO $pdo, int $quiz_id, int $student_id, bool $json = true): array
{
    $stmt = $pdo->prepare(
        'SELECT q.* FROM quizzes q JOIN students s ON s.user_id = ?
         WHERE q.id = ? AND ' . student_quiz_eligibility_sql()
    );
    $stmt->execute([$student_id, $quiz_id]);
    $row = $stmt->fetch();
    if (!$row) {
        if ($json) {
            json_fail(403, 'Not found or not authorized.');
        }
        redirect('views/student/dashboard.php?error=not_found');
    }
    return $row;
}

/** School heads (role 5) may only see quizzes of their own school; other staff are unrestricted. */
function head_can_view_quiz(PDO $pdo, int $quiz_id): bool
{
    if (($_SESSION['role_id'] ?? null) != 5) {
        return true;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM quizzes q
         JOIN courses c ON c.id = q.course_id
         JOIN heads h ON h.school_id = c.school_id
         WHERE q.id = ? AND h.user_id = ?'
    );
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    return (bool) $stmt->fetchColumn();
}
