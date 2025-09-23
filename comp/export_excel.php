<?php
$comp_id = $_GET['comp'] ?? $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$dance_file = "$data_dir/DanceName.txt";

// 대회 정보 로드
if (!preg_match('/^\d{8}-\d+$/', $comp_id) || !is_file($info_file)) {
    die('잘못된 대회 ID 또는 대회 정보가 없습니다.');
}
$info = json_decode(file_get_contents($info_file), true);

// 댄스 번호 => 풀네임 매핑
$dance_types = [];
if (file_exists($dance_file)) {
    $lines = file($dance_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        if (isset($cols[0]) && $cols[0] && isset($cols[1]) && $cols[1]) {
            $dance_types[$cols[0]] = $cols[1]; // 번호 => 풀네임
        }
    }
}

// RunOrder_Tablet.txt 파일 읽기
$lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$events = [];

foreach ($lines as $line) {
    $cols = explode(',', $line);
    if (count($cols) >= 15) {
        // 댄스 데이터는 6-10번째 컬럼에 있음 (인덱스 5-9)
        $dance_data = [];
        for ($i = 5; $i <= 9; $i++) {
            if (isset($cols[$i]) && trim($cols[$i]) !== '') {
                $dance_data[] = trim($cols[$i]);
            }
        }
        
        $events[] = [
            'no' => trim($cols[0]),
            'desc' => trim($cols[1]),
            'roundtype' => trim($cols[2]),
            'roundnum' => trim($cols[3]),
            'dances' => implode(' ', $dance_data), // 6-10번째 컬럼의 댄스 데이터
            'panel' => trim($cols[10]),
            'time' => trim($cols[11]),
            'detail_no' => trim($cols[13]),
            'extra_time' => intval($cols[14] ?? 0)
        ];
    }
}

// 이벤트 그룹화 및 시간 계산 (manage_timetable.php와 동일한 로직)
$raw_no_groups = [];
foreach ($events as $event) {
    $raw_no = $event['no'];
    if (!isset($raw_no_groups[$raw_no])) {
        $raw_no_groups[$raw_no] = [];
    }
    
    // 댄스 파싱
    $dance_numbers = explode(' ', $event['dances']);
    $dance_count = 0;
    $dance_list = [];
    
    foreach ($dance_numbers as $dance_num) {
        $dance_num = trim($dance_num);
        if ($dance_num && $dance_num !== '0' && $dance_num !== '?' && $dance_num !== 'None' && $dance_num !== '-') {
            $dance_count++;
            if (isset($dance_types[$dance_num])) {
                $dance_list[] = $dance_types[$dance_num];
            }
        }
    }
    
    $event['dance_count'] = $dance_count;
    $event['dance_list'] = implode(', ', $dance_list);
    $raw_no_groups[$raw_no][] = $event;
}

// manage_timetable.php와 동일한 이벤트 배열 생성 (순번 순서로 정렬)
$events_for_timetable = [];
$sorted_raw_nos = array_keys($raw_no_groups);
sort($sorted_raw_nos, SORT_NUMERIC);

foreach ($sorted_raw_nos as $raw_no) {
    $group = $raw_no_groups[$raw_no];
    if (count($group) > 0) {
        // 댄스 수가 가장 많은 이벤트 찾기
        $max_dance_count = 0;
        $selected_event = null;
        
        foreach ($group as $event) {
            if ($event['dance_count'] > $max_dance_count) {
                $max_dance_count = $event['dance_count'];
                $selected_event = $event;
            }
        }
        
        if ($selected_event) {
            $base_time = 1.5; // 기본 시간 (분)
            $duration = $base_time * $max_dance_count;
            $extra_time = $selected_event['extra_time'];
            $total_duration = $duration + $extra_time;
            
            $events_for_timetable[] = [
                'no' => $selected_event['no'],
                'desc' => $selected_event['desc'],
                'round' => 'Final', // 기본값
                'dances' => $selected_event['dance_list'],
                'duration' => $total_duration,
                'detail_no' => $selected_event['detail_no'],
                'extra_time' => $extra_time,
                'group_events' => $group
            ];
        }
    }
}

// 특별 이벤트 로드
$special_events = [];
$special_events_file = "$data_dir/special_events.json";
if (file_exists($special_events_file)) {
    $special_events = json_decode(file_get_contents($special_events_file), true) ?? [];
}

// 시간표 계산 (manage_timetable.php와 동일한 로직)
$rows = [];
$current_time = 9 * 60; // 09:00 시작 (분 단위)

foreach ($events_for_timetable as $event) {
    $start_time = sprintf('%02d:%02d', floor($current_time / 60), $current_time % 60);
    $end_time = sprintf('%02d:%02d', floor(($current_time + $event['duration']) / 60), ($current_time + $event['duration']) % 60);
    
    // 멀티이벤트 처리: 각 세부번호별로 별도 행 생성
    if (count($event['group_events']) > 1) {
        // 멀티이벤트: 각 세부번호별로 행 생성
        foreach ($event['group_events'] as $group_event) {
            if (!empty($group_event['detail_no'])) {
                $rows[] = [
                    'no' => $event['no'],
                    'detail_no' => $group_event['detail_no'],
                    'desc' => $group_event['desc'],
                    'dances' => $group_event['dance_list'],
                    'start_time' => $start_time,
                    'end_time' => $end_time
                ];
            }
        }
    } else {
        // 단일 이벤트: 세부번호 없이 표시
        $rows[] = [
            'no' => $event['no'],
            'detail_no' => '',
            'desc' => $event['desc'],
            'dances' => $event['dances'],
            'start_time' => $start_time,
            'end_time' => $end_time
        ];
    }
    
    $current_time += $event['duration'];
    
    // 특별 이벤트 처리 (manage_timetable.php와 동일한 로직)
    foreach ($special_events as $special_event) {
        if ($special_event['after_event'] == $event['no']) {
            $special_start_time = sprintf('%02d:%02d', floor($current_time / 60), $current_time % 60);
            $special_end_time = sprintf('%02d:%02d', floor(($current_time + $special_event['duration']) / 60), ($current_time + $special_event['duration']) % 60);
            
            $rows[] = [
                'no' => '',
                'detail_no' => '',
                'desc' => $special_event['name'],
                'dances' => '',
                'start_time' => $special_start_time,
                'end_time' => $special_end_time
            ];
            
            $current_time += $special_event['duration'];
        }
    }
}

// CSV 파일 생성
$filename = $info['title'] . '_시간표_' . date('Y-m-d') . '.csv';

// UTF-8 BOM 추가 (엑셀에서 한글 깨짐 방지)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// BOM 출력
echo "\xEF\xBB\xBF";

// CSV 헤더
echo "순번,시작시간,종료시간,세부번호,이벤트명,댄스\n";

// 같은 순번과 시간의 행들을 그룹화하여 출력
$grouped_rows = [];
foreach ($rows as $row) {
    $key = $row['no'] . '|' . $row['start_time'] . '|' . $row['end_time'];
    if (!isset($grouped_rows[$key])) {
        $grouped_rows[$key] = [
            'no' => $row['no'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'rows' => []
        ];
    }
    $grouped_rows[$key]['rows'][] = $row;
}

// CSV 데이터 출력
foreach ($grouped_rows as $group) {
    $first_row = true;
    foreach ($group['rows'] as $row) {
        if ($first_row) {
            // 첫 번째 행: 순번과 시간 표시
            echo '"' . $row['no'] . '",';
            echo '"' . $row['start_time'] . '",';
            echo '"' . $row['end_time'] . '",';
            $first_row = false;
        } else {
            // 나머지 행: 순번과 시간은 빈 값으로 표시 (Excel에서 병합 효과)
            echo '"",';
            echo '"",';
            echo '"",';
        }
        echo '"' . $row['detail_no'] . '",';
        echo '"' . $row['desc'] . '",';
        echo '"' . $row['dances'] . '"';
        echo "\n";
    }
}
?>
