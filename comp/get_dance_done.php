<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

// BOM 제거
if (substr($event_no, 0, 3) === "\xEF\xBB\xBF") {
    $event_no = substr($event_no, 3);
}

if (!$comp_id || !$event_no) {
    http_response_code(400);
    echo json_encode(['error' => '필수 파라미터 누락']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
if (!is_dir($data_dir)) {
    http_response_code(404);
    echo json_encode(['error' => '대회 디렉토리 없음']);
    exit;
}

$file = "$data_dir/dance_done_$event_no.json";
if (!file_exists($file)) {
    // 파일이 없으면 빈 객체 반환
    echo json_encode([]);
    exit;
}

$content = file_get_contents($file);
if ($content === false) {
    http_response_code(500);
    echo json_encode(['error' => '파일 읽기 실패']);
    exit;
}

$json = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'JSON 파싱 실패: ' . json_last_error_msg()]);
    exit;
}

echo json_encode($json ?: []);
?>

