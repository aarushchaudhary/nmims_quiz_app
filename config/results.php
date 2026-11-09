<?php
/*
 * config/results.php
 * The one results table for a quiz, used by the faculty and placecom/head reports
 * (JSON) and both Excel exports. Callers do the access check first.
 */

/**
 * Every attempt of the quiz plus a summary. Attempts still open have status
 * 'In progress' and total_score null; the average counts graded attempts only.
 */
function quiz_results(PDO $pdo, int $quiz_id): array
{
    $stmt = $pdo->prepare(
        "SELECT st.name AS student_name, st.sap_id, sa.total_score,
                sa.started_at, sa.submitted_at, sa.is_disqualified,
                q.title AS quiz_title, q.show_results_immediately
         FROM student_attempts sa
         LEFT JOIN students st ON sa.student_id = st.user_id
         JOIN quizzes q ON sa.quiz_id = q.id
         WHERE sa.quiz_id = ?
         ORDER BY sa.submitted_at IS NULL, sa.total_score DESC"
    );
    $stmt->execute([$quiz_id]);
    $rows = $stmt->fetchAll();

    $graded = [];
    $disqualified = 0;
    foreach ($rows as &$row) {
        $row['student_name'] = $row['student_name'] ?? '[Deleted]';
        $row['sap_id']       = $row['sap_id'] ?? 'N/A';
        if ($row['submitted_at'] === null) {
            $row['status'] = 'In progress';
        } elseif ($row['is_disqualified']) {
            $row['status'] = 'Disqualified';
            $disqualified++;
        } else {
            $row['status'] = 'Completed';
            $graded[] = (float) $row['total_score'];
        }
    }
    unset($row);

    return [
        'summary' => [
            'total_attempts'     => count($rows),
            'average_score'      => number_format($graded ? array_sum($graded) / count($graded) : 0, 2),
            'disqualified_count' => $disqualified,
            'is_published'       => $rows ? (bool) $rows[0]['show_results_immediately'] : false,
        ],
        'details' => $rows,
    ];
}

/** Streams the quiz's results as an .xlsx download and exits. */
function send_results_xlsx(PDO $pdo, int $quiz_id): never
{
    require_once __DIR__ . '/../lib/vendor/autoload.php';

    $results = quiz_results($pdo, $quiz_id)['details'];
    if (!$results) {
        exit('No results found for this quiz.');
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $quiz_title = $results[0]['quiz_title'];
    $sheet->setTitle(substr(preg_replace('#[\\\\/?*\[\]:]#', ' ', $quiz_title), 0, 31)); // Excel sheet-name rules

    $sheet->fromArray(['Student Name', 'SAP ID', 'Score', 'Time Taken (seconds)', 'Status', 'Submitted At'], null, 'A1');
    $sheet->getStyle('A1:F1')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
    ]);

    $rowNum = 2;
    foreach ($results as $row) {
        $time_taken = ($row['started_at'] && $row['submitted_at'])
            ? strtotime($row['submitted_at']) - strtotime($row['started_at'])
            : 'N/A';
        $sheet->fromArray([
            $row['student_name'], $row['sap_id'], $row['total_score'] ?? '—',
            $time_taken, $row['status'], $row['submitted_at'] ?? '',
        ], null, 'A' . $rowNum++);
    }
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Quotes and slashes in a title would break the download filename.
    $filename = 'quiz_results_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $quiz_title) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit();
}
