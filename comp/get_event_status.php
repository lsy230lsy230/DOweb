<?php
/**
 * 이벤트 상태 조회 API
 */

header('Content-Type: application/json');

$eventId = $_GET['event_id'] ?? '';

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => '이벤트 ID가 필요합니다.']);
    exit;
}

// 이벤트 상태 파일 경로
$statusFile = __DIR__ . '/uploads/event_status.json';

// 상태 로드
$status = [];
if (file_exists($statusFile)) {
    $status = json_decode(file_get_contents($statusFile), true) ?: [];
}

$showResult = isset($status[$eventId]) ? $status[$eventId] : false;

echo json_encode([
    'success' => true,
    'event_id' => $eventId,
    'show_result' => $showResult,
    'message' => $showResult ? '결과 발표 예정' : '결과 발표 안함'
]);
?>




