<?php
/*
 * api/student/export_student_results.php
 * Generates and downloads an Excel file with a student's detailed results.
 */
session_start();
require_once '../../config/database.php';
require_once '../../lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4 || !isset($_GET['attempt_id'])) {
    exit('Access Denied.');
}
$attempt_id = filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT);
$student_user_id = $_SESSION['user_id'];

try {
    // 1. Fetch Summary Data
    $sql_summary = "SELECT s.name as student_name, q.title as quiz_title, sa.total_score, sa.submitted_at 
                    FROM student_attempts sa 
                    JOIN students s ON sa.student_id = s.user_id
                    JOIN quizzes q ON sa.quiz_id = q.id
                    WHERE sa.id = ? AND sa.student_id = ?";
    $stmt_summary = $pdo->prepare($sql_summary);
    $stmt_summary->execute([$attempt_id, $student_user_id]);
    $summary = $stmt_summary->fetch();
    if (!$summary) { exit('Report not found or not authorized.'); }

    // 2. Fetch Detailed Answers
    $sql_details = "SELECT q.question_text, sa.answer_text, sa.selected_option_ids, sa.is_correct, q.question_type_id 
                    FROM student_answers sa
                    JOIN questions q ON sa.question_id = q.id
                    WHERE sa.attempt_id = ?";
    $stmt_details = $pdo->prepare($sql_details);
    $stmt_details->execute([$attempt_id]);
    $details = $stmt_details->fetchAll();

    // --- Create Spreadsheet ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Quiz Result');

    // --- Populate Header ---
    $sheet->mergeCells('A1:D1');
    $sheet->setCellValue('A1', 'Quiz Result Report');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Student Name:')->getStyle('A3')->getFont()->setBold(true);
    $sheet->setCellValue('B3', $summary['student_name']);
    $sheet->setCellValue('A4', 'Quiz Title:')->getStyle('A4')->getFont()->setBold(true);
    $sheet->setCellValue('B4', $summary['quiz_title']);
    $sheet->setCellValue('A5', 'Final Score:')->getStyle('A5')->getFont()->setBold(true);
    $sheet->setCellValue('B5', $summary['total_score']);
    $sheet->setCellValue('A6', 'Date Submitted:')->getStyle('A6')->getFont()->setBold(true);
    $sheet->setCellValue('B6', date('M j, Y, g:i A', strtotime($summary['submitted_at'])));

    // --- Populate Detailed Results Table ---
    $sheet->setCellValue('A8', 'Question')->getStyle('A8')->getFont()->setBold(true);
    $sheet->setCellValue('B8', 'Your Answer')->getStyle('B8')->getFont()->setBold(true);
    $sheet->setCellValue('C8', 'Result')->getStyle('C8')->getFont()->setBold(true);

    $rowNum = 9;
    foreach ($details as $row) {
        $sheet->setCellValue('A' . $rowNum, $row['question_text']);
        
        $your_answer = '';
        if ($row['question_type_id'] == 3) {
            $your_answer = $row['answer_text'];
        } else {
            $selected_ids = json_decode($row['selected_option_ids'], true);
            if (!empty($selected_ids)) {
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                $stmt_opts = $pdo->prepare("SELECT option_text FROM options WHERE id IN ($placeholders)");
                $stmt_opts->execute($selected_ids);
                $your_answer = implode(', ', $stmt_opts->fetchAll(PDO::FETCH_COLUMN, 0));
            }
        }
        $sheet->setCellValue('B' . $rowNum, $your_answer);

        $result_text = '';
        if ($row['is_correct'] === null) {
            $result_text = 'Unanswered';
        } else if ($row['is_correct'] == 1) {
            $result_text = 'Correct';
        } else if ($row['is_correct'] == 2) {
            $result_text = 'Partially Correct';
        } else if ($row['is_correct'] == 3) {
            $result_text = 'To be Evaluated';
        } else {
            $result_text = 'Incorrect';
        }
        $sheet->setCellValue('C' . $rowNum, $result_text);
        
        $rowNum++;
    }
    
    // Auto-size columns
    foreach (range('A', 'C') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --- Output to Browser ---
    $filename = 'Quiz_Result_' . preg_replace('/[^a-zA-Z0-9]/', '_', $summary['quiz_title']) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (Exception $e) {
    error_log("Student result export failed: " . $e->getMessage());
    exit('An error occurred while generating the report.');
}
