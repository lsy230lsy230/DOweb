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
            // RunOrder_Tablet.txt에서 읽어온 음악 시간 사용 (댄스당 시간)
            $music_time_per_dance = floatval($selected_event['time'] ?? 0);
            if ($music_time_per_dance > 0) {
                $duration = $music_time_per_dance * $max_dance_count; // 댄스당 시간 × 댄스 개수
            } else {
                $base_time = 1.5; // 기본 시간 (분) - 음악 시간이 없을 때만 사용
                $duration = $base_time * $max_dance_count;
            }
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
            
            // 디버깅: 순번 1의 시간 계산 확인
            if ($selected_event['no'] == '1') {
                echo "<!-- 디버깅 export: 순번 1, music_time_per_dance=" . $music_time_per_dance . ", max_dance_count=" . $max_dance_count . ", duration=" . $duration . ", total_duration=" . $total_duration . " -->";
            }
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
$current_time = 9 * 60 + 1; // 09:01 시작 (분 단위) - 기본값

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
    
    $current_time += $event['duration']; // 연속 진행
    
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
            
            $current_time += $special_event['duration']; // 연속 진행
        }
    }
}

// HTML to PDF 출력
$filename = $info['title'] . '_시간표_' . date('Y-m-d') . '.html';

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($info['title']) ?> 시간표</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 20mm;
        }
        
        body {
            font-family: 'Malgun Gothic', '맑은 고딕', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #03C75A;
            padding-bottom: 20px;
        }
        
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #03C75A;
            margin-bottom: 10px;
        }
        
        .subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info {
            font-size: 14px;
            color: #888;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background-color: #34495E;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #2C3E50;
        }
        
        td {
            padding: 10px 8px;
            border: 1px solid #BDC3C7;
            text-align: center;
        }
        
        tr:nth-child(even) {
            background-color: #F8F9FA;
        }
        
        tr:nth-child(odd) {
            background-color: #FFFFFF;
        }
        
        .no-col { width: 8%; }
        .detail-col { width: 12%; }
        .desc-col { width: 40%; }
        .dance-col { width: 20%; }
        .time-col { width: 8%; }
        .duration-col { width: 8%; }
        
        .desc-col {
            text-align: left;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #888;
            border-top: 1px solid #BDC3C7;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title"><?= htmlspecialchars($info['title']) ?> 시간표</div>
        <div class="subtitle">대회일: <?= htmlspecialchars($info['date']) ?></div>
        <div class="info">장소: <?= htmlspecialchars($info['place']) ?> | 주최/주관: <?= htmlspecialchars($info['host']) ?></div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="no-col">순번</th>
                <th class="time-col">시작시간</th>
                <th class="time-col">종료시간</th>
                <th class="detail-col">세부번호</th>
                <th class="desc-col">이벤트명</th>
                <th class="dance-col">댄스</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $grouped_rows = [];
            $current_group = null;
            
            // 같은 순번과 시간의 행들을 그룹화
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
            
            foreach ($grouped_rows as $group): 
                $row_count = count($group['rows']);
            ?>
                <?php foreach ($group['rows'] as $index => $row): ?>
                <tr>
                    <?php if ($index === 0): ?>
                        <td rowspan="<?= $row_count ?>"><?= htmlspecialchars($row['no']) ?></td>
                        <td rowspan="<?= $row_count ?>"><?= htmlspecialchars($row['start_time']) ?></td>
                        <td rowspan="<?= $row_count ?>"><?= htmlspecialchars($row['end_time']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($row['detail_no']) ?></td>
                    <td class="desc-col"><?= htmlspecialchars($row['desc']) ?></td>
                    <td><?= htmlspecialchars($row['dances']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>생성일: <?= date('Y-m-d H:i:s') ?> | danceoffice.net</p>
    </div>
</body>
</html>
