<?php
/**
 * 이벤트 상태 업데이트 API
 */

header('Content-Type: application/json');

$eventId = $_POST['event_id'] ?? '';
$showResult = $_POST['show_result'] === 'true';

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => '이벤트 ID가 필요합니다.']);
    exit;
}

// 이벤트 상태 파일 경로
$statusFile = __DIR__ . '/uploads/event_status.json';

// 기존 상태 로드
$status = [];
if (file_exists($statusFile)) {
    $status = json_decode(file_get_contents($statusFile), true) ?: [];
}

// 상태 업데이트
$status[$eventId] = $showResult;

// 파일 저장
if (file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => '상태가 업데이트되었습니다.']);
} else {
    echo json_encode(['success' => false, 'message' => '파일 저장에 실패했습니다.']);
}
?>




