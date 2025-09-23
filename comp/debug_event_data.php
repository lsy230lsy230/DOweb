<?php
/**
 * 이벤트 데이터 디버깅 API
 */

header('Content-Type: application/json');

// 이벤트 순서 파일 경로
$eventFile = __DIR__ . '/uploads/event_schedule.txt';
$statusFile = __DIR__ . '/uploads/event_status.json';

$events = [];
$debugInfo = [];

if (file_exists($eventFile)) {
    $lines = file($eventFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $debugInfo['file_exists'] = true;
    $debugInfo['file_lines'] = count($lines);
    
    // 상태 로드
    $status = [];
    if (file_exists($statusFile)) {
        $status = json_decode(file_get_contents($statusFile), true) ?: [];
    }
    
    foreach ($lines as $lineNum => $line) {
        $parts = explode(',', $line);
        $debugInfo['line_' . ($lineNum + 1)] = [
            'raw' => $line,
            'parts' => $parts,
            'part_count' => count($parts)
        ];
        
        if (count($parts) >= 3) {
            $eventId = trim($parts[0]);
            $showResult = trim($parts[2]) === '1';
            
            $events[] = [
                'id' => $eventId,
                'name' => trim($parts[1]),
                'show_result' => isset($status[$eventId]) ? $status[$eventId] : $showResult
            ];
        }
    }
} else {
    $debugInfo['file_exists'] = false;
}

// 그룹화 테스트
$groupedEvents = [];
foreach ($events as $event) {
    $eventNumber = preg_replace('/[^0-9]/', '', $event['id']);
    if ($eventNumber && $eventNumber !== '') {
        if (!isset($groupedEvents[$eventNumber])) {
            $groupedEvents[$eventNumber] = [];
        }
        $groupedEvents[$eventNumber][] = $event;
    }
}

// 숫자 순으로 정렬
ksort($groupedEvents, SORT_NUMERIC);

echo json_encode([
    'success' => true,
    'events' => $events,
    'grouped_events' => $groupedEvents,
    'debug_info' => $debugInfo,
    'total_events' => count($events),
    'total_groups' => count($groupedEvents)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>




