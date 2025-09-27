<?php
header('Content-Type: application/json; charset=utf-8');

// POST 데이터 받기
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

$event_id = $data['event_id'] ?? '';
$scoring_data = $data['scoring_data'] ?? [];
$comp_id = $data['comp_id'] ?? '20250913-001'; // 기본값 설정

if (empty($event_id)) {
    echo json_encode(['success' => false, 'error' => 'Event ID is required']);
    exit;
}

// 대회별 디렉토리 구조 생성
$base_dir = "data/{$comp_id}";
$scoring_dir = "{$base_dir}/scoring_files";
$event_dir = "{$scoring_dir}/Event_{$event_id}";

// 디렉토리 생성
if (!file_exists($base_dir)) {
    if (!mkdir($base_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => '대회 디렉토리 생성에 실패했습니다.']);
        exit;
    }
}

if (!file_exists($scoring_dir)) {
    if (!mkdir($scoring_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => '채점 파일 디렉토리 생성에 실패했습니다.']);
        exit;
    }
}

if (!file_exists($event_dir)) {
    if (!mkdir($event_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => '이벤트 디렉토리 생성에 실패했습니다.']);
        exit;
    }
}

// 파일명 생성 (이벤트번호_날짜시간.json)
$timestamp = date('Y-m-d_H-i-s');
$filename = "event_{$event_id}_scoring_{$timestamp}.json";
$filepath = $event_dir . '/' . $filename;

// 채점 데이터를 JSON 파일로 저장
$save_data = [
    'event_id' => $event_id,
    'timestamp' => date('Y-m-d H:i:s'),
    'scoring_data' => $scoring_data
];

$result = file_put_contents($filepath, json_encode($save_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($result !== false) {
    echo json_encode([
        'success' => true, 
        'filename' => $filename,
        'filepath' => $filepath,
        'message' => '채점 파일이 저장되었습니다.'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => '파일 저장에 실패했습니다.']);
}
?>
