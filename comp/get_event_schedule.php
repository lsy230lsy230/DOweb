<?php
/**
 * 이벤트 순서 조회 API
 */

header('Content-Type: application/json');

// 이벤트 순서 파일 경로
$eventFile = __DIR__ . '/uploads/event_schedule.txt';
$statusFile = __DIR__ . '/uploads/event_status.json';

$events = [];

if (file_exists($eventFile)) {
    $lines = file($eventFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // 상태 로드
    $status = [];
    if (file_exists($statusFile)) {
        $status = json_decode(file_get_contents($statusFile), true) ?: [];
    }
    
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 3) {
            $eventId = trim($parts[0]);
            $showResult = trim($parts[2]) === '1'; // 파일에서 읽은 값 우선
            
            $events[] = [
                'id' => $eventId,
                'name' => trim($parts[1]),
                'show_result' => $showResult // 파일에서 읽은 값 사용
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'events' => $events,
    'total' => count($events)
]);
?>
