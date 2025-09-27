<?php
header('Content-Type: application/json; charset=utf-8');

// 경쟁 ID 가져오기
$comp_id = $_GET['comp_id'] ?? '';

if (empty($comp_id)) {
    echo json_encode(['success' => false, 'error' => 'Competition ID is required']);
    exit;
}

// 데이터 디렉토리 설정
$data_dir = "data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";

$events = [];
$event_groups = [];

if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        
        // 이벤트 번호 정규화: BOM 및 숫자 이외 문자 제거
        $no = $cols[0] ?? '';
        if (preg_match('/^\xEF\xBB\xBF/', $no)) {
            $no = substr($no, 3);
        }
        $no = preg_replace('/[^\d]/', '', $no);
        
        if (empty($no) || !is_numeric($no)) continue;
        
        $events[] = [
            'no' => $no,
            'desc' => $cols[1] ?? '',
            'round' => $cols[2] ?? '',
            'recall' => $cols[4] ?? '',
            'recall_count' => intval($cols[4] ?? 0),
            'panel' => $cols[11] ?? '',
            'dances' => array_filter([
                $cols[6] ?? '',
                $cols[7] ?? '',
                $cols[8] ?? '',
                $cols[9] ?? '',
                $cols[10] ?? ''
            ]),
            'detail_no' => $cols[13] ?? '',
            'next_event' => $cols[5] ?? ''
        ];
    }
}

// 이벤트를 그룹별로 정리
$groups = [];
foreach ($events as $event) {
    $group_no = $event['no'];
    if (!isset($groups[$group_no])) {
        $groups[$group_no] = [
            'group_no' => $group_no,
            'group_name' => $event['desc'],
            'events' => [],
            'is_multi' => false,
            'recall_count' => $event['recall_count'],
            'panel' => $event['panel']
        ];
    }
    
    $groups[$group_no]['events'][] = $event;
    
    // 여러 이벤트가 있으면 멀티 이벤트로 표시
    if (count($groups[$group_no]['events']) > 1) {
        $groups[$group_no]['is_multi'] = true;
    }
}

// 그룹 배열로 변환
$groupData = array_values($groups);

echo json_encode([
    'success' => true,
    'events' => $events,
    'groups' => $groupData
]);
?>
