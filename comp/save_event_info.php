<?php
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$comp_id = $data['comp_id'] ?? '';
$event_no = $data['eventNo'] ?? '';
$from_event = $data['fromEvent'] ?? '';
$to_event = $data['toEvent'] ?? '';
$recall = $data['recall'] ?? '';
$heats = $data['heats'] ?? '';

// 정규화: BOM 제거 및 허용 문자만 유지
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$event_no = preg_replace('/\x{FEFF}/u', '', $event_no);
$from_event = preg_replace('/\x{FEFF}/u', '', $from_event);
$to_event = preg_replace('/\x{FEFF}/u', '', $to_event);
$recall = preg_replace('/\x{FEFF}/u', '', $recall);
$heats = preg_replace('/\x{FEFF}/u', '', $heats);

$comp_id = preg_replace('/[^0-9\-]/', '', $comp_id);
$event_no = preg_replace('/\D+/', '', $event_no);
$from_event = preg_replace('/\D+/', '', $from_event);
$to_event = preg_replace('/\D+/', '', $to_event);

$comp_id = trim($comp_id);
$event_no = trim($event_no);
$from_event = trim($from_event);
$to_event = trim($to_event);
$recall = trim($recall);
$heats = trim($heats);

if (!$comp_id || !$event_no) {
    echo json_encode(['success' => false, 'error' => 'comp_id 또는 eventNo 누락']);
    exit;
}

if (!preg_match('/^\d{8}-\d+$/', $comp_id)) {
    echo json_encode(['success' => false, 'error' => '잘못된 대회 ID']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
$event_info_file = "$data_dir/event_info.json";

// 기존 이벤트 정보 로드
$event_info = [];
if (file_exists($event_info_file)) {
    $content = file_get_contents($event_info_file);
    $event_info = json_decode($content, true) ?: [];
}

// 현재 이벤트 정보 업데이트
$event_info[$event_no] = [
    'from_event' => $from_event,
    'to_event' => $to_event,
    'recall' => $recall,
    'heats' => $heats,
    'updated_at' => date('Y-m-d H:i:s')
];

// 파일 저장
if (file_put_contents($event_info_file, json_encode($event_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'error' => '파일 저장 실패']);
    exit;
}

echo json_encode(['success' => true]);
?>
