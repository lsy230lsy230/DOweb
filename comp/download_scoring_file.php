<?php
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$filename = $_GET['filename'] ?? '';

if (empty($comp_id) || empty($event_no) || empty($filename)) {
    http_response_code(400);
    echo "Missing parameters";
    exit;
}

$data_dir = __DIR__ . "/data/{$comp_id}";
$scoring_dir = $data_dir . "/scoring_files/Event_{$event_no}";
$file_path = $scoring_dir . "/" . $filename;

if (!file_exists($file_path)) {
    http_response_code(404);
    echo "File not found";
    exit;
}

// 파일 다운로드 헤더 설정
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// 파일 출력
readfile($file_path);
?>
