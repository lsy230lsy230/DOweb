<?php
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$comp_id = $data['comp_id'] ?? '';
$event_no = $data['eventNo'] ?? '';
// comp_id/event_no 정규화: BOM 제거 및 허용 문자만 유지
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$event_no = preg_replace('/\x{FEFF}/u', '', $event_no);
$comp_id = preg_replace('/[^0-9\-]/', '', $comp_id);
$event_no = preg_replace('/\D+/', '', $event_no);
$comp_id = trim($comp_id);
$event_no = trim($event_no);
$hits = $data['hits'] ?? '';
if (!$comp_id || !$event_no || !$hits || !is_array($hits)) {
    echo json_encode(['success'=>false, 'error'=>'데이터 부족']);
    exit;
}
// comp_id 형식 검증
if (!preg_match('/^\d{8}-\d+$/', $comp_id)) {
    echo json_encode(['success'=>false, 'error'=>'잘못된 대회 ID']);
    exit;
}
$data_dir = __DIR__ . "/data/$comp_id";
if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0777, true);
}
$file = "$data_dir/players_hits_{$event_no}.json";
if (file_put_contents($file, json_encode($hits, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success'=>false, 'error'=>'저장 실패']);
} else {
    echo json_encode(['success'=>true]);
}