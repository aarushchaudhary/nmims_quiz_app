<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = trim($_POST['class_id'] ?? '');
    
    $batch_names = $_POST['batch_name'] ?? [];
    $sap_id_starts = $_POST['sap_id_range_start'] ?? [];
    $sap_id_ends = $_POST['sap_id_range_end'] ?? [];

    if (empty($class_id)) {
        header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("Class (Section) is required."));
        exit();
    }

    if (!is_array($batch_names) || count($batch_names) === 0) {
        header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("At least one batch must be provided."));
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO batches (name, class_id, sap_id_range_start, sap_id_range_end) VALUES (?, ?, ?, ?)");
        
        $added_count = 0;
        for ($i = 0; $i < count($batch_names); $i++) {
            $name = trim($batch_names[$i]);
            $start = trim($sap_id_starts[$i] ?? '');
            $end = trim($sap_id_ends[$i] ?? '');

            if (!empty($name) && !empty($start) && !empty($end)) {
                $stmt->execute([$name, $class_id, $start, $end]);
                $added_count++;
            }
        }
        
        $pdo->commit();
        
        if ($added_count > 0) {
            header('Location: ' . get_base_url() . 'views/admin/batches.php?success=' . urlencode("$added_count batch(es) added successfully."));
        } else {
            header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("No valid batches were submitted. Make sure all fields are filled."));
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("Error adding batches. Please try again."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/batches.php');
}
?>
