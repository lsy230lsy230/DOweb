<?php
// RunOrder_Tablet.txt에서 시간표 순서대로 이벤트 목록 가져오기
function getEventsFromRunOrder($comp_data_path) {
    if (!$comp_data_path) return [];
    
    $runorder_file = $comp_data_path . '/RunOrder_Tablet.txt';
    if (!file_exists($runorder_file)) return [];
    
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $events = [];
    $processed_events = []; // 중복 방지 (이벤트번호-세부번호 조합)
    
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue; // 헤더 라인 스킵
        
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 14) {
            $event_no = $cols[0];
            $event_name = $cols[1];
            $round = $cols[2];
            $display_number = isset($cols[13]) ? $cols[13] : ''; // 세부번호 (1-1, 1-2, 3-1, 3-2...)
            
            if (!empty($event_no) && is_numeric($event_no)) {
                // 세부번호가 있는 경우 이벤트번호-세부번호 조합으로 중복 체크
                $unique_key = $event_no . ($display_number ? '-' . $display_number : '');
                
                // 51번, 52번 이벤트 디버깅
                if ($event_no == '51' || $event_no == '52') {
                    echo "Event $event_no: unique_key='$unique_key', display_number='$display_number', already_processed=" . (in_array($unique_key, $processed_events) ? 'YES' : 'NO') . "\n";
                }
                
                if (!in_array($unique_key, $processed_events)) {
                    $processed_events[] = $unique_key;
                    
                    // 이벤트명은 원본 그대로 사용 (세부번호 추가하지 않음)
                    $full_event_name = $event_name;
                    
                    $events[] = [
                        'event_no' => intval($event_no),
                        'display_number' => $display_number ?: $event_no,
                        'event_name' => $full_event_name,
                        'round' => $round,
                        'detail_no' => $display_number
                    ];
                }
            }
        }
    }
    
    return $events;
}

// 테스트 실행
$comp_data_dir = __DIR__ . '/comp/data/20250913-001';
$events_list = getEventsFromRunOrder($comp_data_dir);

echo "Total events: " . count($events_list) . "\n";

// 51번, 52번 이벤트만 필터링
$filtered_events = array_filter($events_list, function($event) {
    return $event['event_no'] == 51 || $event['event_no'] == 52;
});

echo "Events 51 and 52:\n";
foreach ($filtered_events as $event) {
    echo "Event {$event['event_no']}: {$event['event_name']} (display: {$event['display_number']}, detail_no: {$event['detail_no']})\n";
}
?>