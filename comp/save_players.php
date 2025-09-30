<?php
// CORS 헤더 추가
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS 요청 처리 (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

// 디버깅 정보 추가
error_log("Save players debug - comp_id: $comp_id, eventNo: $eventNo, detailNo: $detailNo");
error_log("Save players debug - file path: $file");
error_log("Save players debug - players count: " . count($players));
error_log("Save players debug - content: " . substr($content, 0, 200));

// 디렉토리 존재 확인
if (!is_dir($data_dir)) {
    error_log("Save players debug - data directory does not exist: $data_dir");
    echo json_encode(['success'=>false, 'error'=>'데이터 디렉토리가 존재하지 않습니다: ' . $data_dir]);
    exit;
}

// 파일 쓰기 권한 확인
if (!is_writable($data_dir)) {
    error_log("Save players debug - data directory is not writable: $data_dir");
    echo json_encode(['success'=>false, 'error'=>'데이터 디렉토리에 쓰기 권한이 없습니다: ' . $data_dir]);
    exit;
}

$result = file_put_contents($file, $content);
if ($result !== false) {
    error_log("Save players debug - file saved successfully, bytes written: $result");
    echo json_encode(['success'=>true, 'message'=>'선수 정보가 저장되었습니다']);
} else {
    $error = error_get_last();
    error_log("Save players debug - file save failed: " . ($error['message'] ?? 'Unknown error'));
    echo json_encode(['success'=>false, 'error'=>'파일 저장 실패: ' . ($error['message'] ?? '알 수 없는 오류')]);
}
?>