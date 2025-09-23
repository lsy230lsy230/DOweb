<?php
header('Content-Type: application/json; charset=utf-8');

$comp_id = $_GET['comp_id'] ?? '';
$comp_id = preg_replace('/[^\d-]/', '', $comp_id);

if (!$comp_id) {
    echo json_encode(['success' => false, 'error' => 'comp_id 누락']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0777, true);
}

$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$round_info_file = "$data_dir/round_info.json";

if (!file_exists($runorder_file)) {
    echo json_encode(['success' => false, 'error' => 'RunOrder 파일을 찾을 수 없습니다']);
    exit;
}

// RunOrder 파일 읽기
$events = [];
$lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    // Remove BOM from line
    $line = preg_replace('/\x{FEFF}/u', '', $line);
    $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
    $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
    
    if (preg_match('/^bom/', $line)) continue;
    $cols = array_map('trim', explode(',', $line));
    $dance_abbr = [];
    for ($i=6; $i<=10; $i++) {
        if (isset($cols[$i]) && trim($cols[$i])!=='') $dance_abbr[] = $cols[$i];
    }
    // 경기 이벤트만 처리
    if (count($dance_abbr) > 0) {
        $events[] = [
            'raw_no' => preg_replace('/\x{FEFF}/u', '', $cols[0] ?? ''), // Remove BOM from raw_no
            'name' => $cols[1] ?? '',
            'round_type' => $cols[2] ?? '',
            'round_num' => $cols[3] ?? '',
        ];
    }
}

// 라운드 정보 계산 (RunOrder_Tablet.txt 기반)
function calculateRoundInfo($events) {
    $name_groups = [];
    foreach ($events as $idx => $evt) {
        $name = $evt['name'];
        if (!isset($name_groups[$name])) {
            $name_groups[$name] = [];
        }
        $name_groups[$name][] = ['idx' => $idx, 'event' => $evt];
    }
    
    $round_info = [];
    foreach ($name_groups as $name => $group) {
        $total_events = count($group);
        
        // Sort by event number to get correct order
        usort($group, function($a, $b) {
            return intval($a['event']['raw_no']) - intval($b['event']['raw_no']);
        });
        
        foreach ($group as $pos => $item) {
            $event_no = $item['event']['raw_no'];
            $stage_text = '';
            
            if ($total_events === 1) {
                $stage_text = 'Final';
            } else if ($total_events === 2) {
                if ($pos === 0) $stage_text = 'Semi-Final';
                else $stage_text = 'Final';
            } else if ($total_events === 3) {
                if ($pos === 0) $stage_text = 'Round 1';
                else if ($pos === 1) $stage_text = 'Semi-Final';
                else $stage_text = 'Final';
            } else if ($total_events === 4) {
                if ($pos === 0) $stage_text = 'Round 1';
                else if ($pos === 1) $stage_text = 'Round 2';
                else if ($pos === 2) $stage_text = 'Semi-Final';
                else $stage_text = 'Final';
            } else if ($total_events === 5) {
                if ($pos === 0) $stage_text = 'Round 1';
                else if ($pos === 1) $stage_text = 'Round 2';
                else if ($pos === 2) $stage_text = 'Round 3';
                else if ($pos === 3) $stage_text = 'Semi-Final';
                else $stage_text = 'Final';
            } else {
                $stage_text = ($pos + 1) . '/' . $total_events;
            }
            
            $round_info[$event_no] = $stage_text;
        }
    }
    
    return $round_info;
}

$round_info = calculateRoundInfo($events);

// 라운드 정보를 파일에 저장
$save_data = [
    'events' => $events,
    'round_info' => $round_info,
    'updated_at' => date('Y-m-d H:i:s')
];

if (file_put_contents($round_info_file, json_encode($save_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'error' => '라운드 정보 저장 실패']);
} else {
    echo json_encode(['success' => true, 'message' => '라운드 정보가 저장되었습니다']);
}
?>
