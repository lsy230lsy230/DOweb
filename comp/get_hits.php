<?php
header('Content-Type: application/json; charset=utf-8');
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['eventNo'] ?? '';

// 정규화: BOM 제거 및 허용 문자만 유지
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$event_no = preg_replace('/\x{FEFF}/u', '', $event_no);
$comp_id = preg_replace('/[^0-9\-]/', '', $comp_id);
$event_no = preg_replace('/\D+/', '', $event_no);
$comp_id = trim($comp_id);
$event_no = trim($event_no);

if (!$comp_id || !$event_no) {
    echo json_encode(['success' => false, 'error' => 'comp_id 또는 eventNo 누락']);
    exit;
}

if (!preg_match('/^\d{8}-\d+$/', $comp_id)) {
    echo json_encode(['success' => false, 'error' => '잘못된 대회 ID']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
$file = "$data_dir/players_hits_{$event_no}.json";

if (!is_file($file)) {
    echo json_encode(['success' => false, 'error' => '히트 파일이 없습니다']);
    exit;
}

$content = file_get_contents($file);
if ($content === false) {
    echo json_encode(['success' => false, 'error' => '파일 읽기 실패']);
    exit;
}

$hits = json_decode($content, true);
if ($hits === null) {
    echo json_encode(['success' => false, 'error' => 'JSON 파싱 실패']);
    exit;
}

echo json_encode(['success' => true, 'hits' => $hits]);
?>
