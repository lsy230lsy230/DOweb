<?php
header('Content-Type: application/json; charset=utf-8');
$comp_id = $_GET['comp_id'] ?? '';

// 정규화: BOM 제거 및 허용 문자만 유지
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$comp_id = preg_replace('/[^0-9\-]/', '', $comp_id);
$comp_id = trim($comp_id);

if (!$comp_id) {
    echo json_encode(['success' => false, 'error' => 'comp_id 누락']);
    exit;
}

if (!preg_match('/^\d{8}-\d+$/', $comp_id)) {
    echo json_encode(['success' => false, 'error' => '잘못된 대회 ID']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
$event_info_file = "$data_dir/event_info.json";

// 이벤트 정보 로드
$event_info = [];
if (file_exists($event_info_file)) {
    $content = file_get_contents($event_info_file);
    $event_info = json_decode($content, true) ?: [];
}

echo json_encode(['success' => true, 'event_info' => $event_info]);
?>
