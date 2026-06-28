<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $search = $_GET['search'] ?? '';
    $school_id = $_GET['school_id'] ?? '';
    $course_id = $_GET['course_id'] ?? '';

    $query = "
        SELECT 
            s.user_id, s.name, s.sap_id, s.batch, s.graduation_year,
            c.name as course_name, sch.name as school_name
        FROM students s
        JOIN courses c ON s.course_id = c.id
        JOIN schools sch ON c.school_id = sch.id
        WHERE 1=1
    ";
    
    $params = [];

    if ($search !== '') {
        $query .= " AND (s.name LIKE ? OR s.sap_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($school_id !== '') {
        $query .= " AND sch.id = ?";
        $params[] = $school_id;
    }

    if ($course_id !== '') {
        $query .= " AND c.id = ?";
        $params[] = $course_id;
    }

    $query .= " ORDER BY s.graduation_year DESC, s.name ASC LIMIT 500";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'students' => $students]);

} catch (PDOException $e) {
    error_log("Get Students Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
