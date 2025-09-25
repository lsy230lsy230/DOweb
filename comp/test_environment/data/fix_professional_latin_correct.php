<?php
// 프로페셔널 라틴의 다음 이벤트 번호를 올바르게 수정하는 스크립트

$runorder_file = "RunOrder_Tablet.txt";

// 파일 읽기
$lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$events = [];

// 이벤트 데이터 파싱
foreach ($lines as $line_idx => $line) {
    if (preg_match('/^bom/', $line)) {
        continue;
    }
    
    $cols = array_map('trim', explode(',', $line));
    if (count($cols) < 2) continue;
    
    $events[] = [
        'raw_no' => $cols[0] ?? '',
        'name' => $cols[1] ?? '',
        'cols' => $cols
    ];
}

// 이벤트명별로 그룹화
$name_groups = [];
foreach ($events as $idx => $event) {
    $name = $event['name'];
    if (!isset($name_groups[$name])) {
        $name_groups[$name] = [];
    }
    $name_groups[$name][] = ['idx' => $idx, 'event' => $event];
}

// 각 그룹별로 다음 이벤트 번호 계산
$next_events = [];
foreach ($name_groups as $name => $group) {
    $total_events = count($group);
    
    // 같은 이벤트명을 가진 이벤트들을 순번 순으로 정렬
    usort($group, function($a, $b) {
        $raw_no_a = intval($a['event']['raw_no']);
        $raw_no_b = intval($b['event']['raw_no']);
        
        // 순번이 같으면 이벤트명으로 정렬
        if ($raw_no_a === $raw_no_b) {
            return strcmp($a['event']['name'], $b['event']['name']);
        }
        
        return $raw_no_a - $raw_no_b;
    });
    
    // 디버깅: 프로페셔널 라틴 그룹 확인
    if ($name === '프로페셔널 라틴') {
        echo "프로페셔널 라틴 그룹:\n";
        foreach ($group as $pos => $item) {
            echo "  위치 $pos: 순번 {$item['event']['raw_no']}\n";
        }
    }
    
    foreach ($group as $pos => $item) {
        $idx = $item['idx'];
        
        if ($pos < $total_events - 1) {
            // 다음 라운드가 있는 경우
            $next_item = $group[$pos + 1];
            $next_events[$idx] = $next_item['event']['raw_no'];
        } else {
            // 마지막 라운드인 경우
            $next_events[$idx] = '';
        }
    }
}

// 파일 업데이트
$updated_lines = [];
$event_counter = 0;

foreach ($lines as $line_idx => $line) {
    if (preg_match('/^bom/', $line)) {
        $updated_lines[] = $line;
        continue;
    }
    
    $cols = array_map('trim', explode(',', $line));
    
    // 다음 이벤트 번호 업데이트 (5번째 컬럼)
    if (isset($next_events[$event_counter])) {
        $cols[5] = $next_events[$event_counter];
    }
    
    $updated_lines[] = implode(',', $cols);
    $event_counter++;
}

// 파일 저장
file_put_contents($runorder_file, implode("\n", $updated_lines) . "\n");

echo "프로페셔널 라틴 다음 이벤트 번호 수정 완료!\n";
?>






