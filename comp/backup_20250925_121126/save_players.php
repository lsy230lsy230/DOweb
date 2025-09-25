<?php
// POST: {"eventNo": "1", "players": ["101", "102", ...]}
$data = json_decode(file_get_contents("php://input"), true);
$comp_id = $_GET['comp_id'] ?? '';
$eventNo = $data['eventNo'] ?? '';
$detailNo = $data['detailNo'] ?? '';

// 정규화: BOM 제거 및 허용 문자만 유지
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$eventNo = preg_replace('/\x{FEFF}/u', '', $eventNo);
$detailNo = preg_replace('/\x{FEFF}/u', '', $detailNo);
$comp_id = preg_replace('/[^0-9\-]/', '', $comp_id);
$eventNo = preg_replace('/\D+/', '', $eventNo);
$detailNo = preg_replace('/[^0-9\-]/', '', $detailNo);
$comp_id = trim($comp_id);
$eventNo = trim($eventNo);
$detailNo = trim($detailNo);
$players = $data['players'] ?? [];

if (!$comp_id || !$eventNo) {
    echo json_encode(['success'=>false, 'error'=>'comp_id 또는 eventNo 누락']); exit;
}
if (!preg_match('/^\d{8}-\d+$/', $comp_id)) {
    echo json_encode(['success'=>false, 'error'=>'잘못된 대회 ID']); exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
if (!is_dir($data_dir)) @mkdir($data_dir, 0777, true);

// 세부번호가 있으면 세부번호별 파일 사용, 없으면 원본 이벤트 번호 사용
$file = $detailNo ? "$data_dir/players_{$detailNo}.txt" : "$data_dir/players_{$eventNo}.txt";

$content = implode("\n", $players);
if (file_put_contents($file, $content)!==false) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'error'=>'파일 저장 실패']);
}
?>