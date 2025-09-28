<?php
header('Content-Type: application/json; charset=utf-8');

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

if (empty($comp_id) || empty($event_no)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$data_dir = __DIR__ . "/data/{$comp_id}";
$scoring_dir = $data_dir . "/scoring_files/Event_{$event_no}";

if (!is_dir($scoring_dir)) {
    echo json_encode(['success' => false, 'error' => 'Event directory not found']);
    exit;
}

// 채점파일 목록 가져오기
$files = glob($scoring_dir . "/event_{$event_no}_scoring_*.json");

$file_list = [];
foreach ($files as $file) {
    $file_info = [
        'filename' => basename($file),
        'filepath' => $file,
        'size' => filesize($file),
        'timestamp' => date('Y-m-d H:i:s', filemtime($file)),
        'modified_time' => filemtime($file)
    ];
    $file_list[] = $file_info;
}

// 수정시간 순으로 정렬 (최신순)
usort($file_list, function($a, $b) {
    return $b['modified_time'] - $a['modified_time'];
});

echo json_encode([
    'success' => true,
    'files' => $file_list,
    'event_no' => $event_no,
    'total_count' => count($file_list)
]);
?>
