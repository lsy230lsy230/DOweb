<?php
/**
 * 타임테이블 데이터를 메인 시스템으로 푸시하는 API
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'auth.php';

// 권한 확인
if (!isset($_SESSION['user']) || !hasPermission($_SESSION['user'], 'create_comp')) {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

$comp_id = $_POST['comp_id'] ?? '';
if (!$comp_id || !preg_match('/^\d{8}-\d+$/', $comp_id)) {
    echo json_encode(['success' => false, 'message' => '유효하지 않은 대회 ID입니다.']);
    exit;
}

try {
    $data_dir = __DIR__ . "/data/$comp_id";
    $info_file = "$data_dir/info.json";
    $runorder_file = "$data_dir/RunOrder_Tablet.txt";
    $special_events_file = "$data_dir/special_events.json";
    
    // 대회 정보 확인
    if (!file_exists($info_file)) {
        throw new Exception('대회 정보를 찾을 수 없습니다.');
    }
    
    $info = json_decode(file_get_contents($info_file), true);
    if (!$info) {
        throw new Exception('대회 정보를 읽을 수 없습니다.');
    }
    
    // 시간 계산 함수들
    function padzero($n) { return str_pad($n, 2, "0", STR_PAD_LEFT); }
    function to_time($s) {
        if (strpos($s, ':') !== false) {
            [$h, $m] = explode(':', $s);
            return intval($h) * 60 + intval($m);
        }
        return intval($s);
    }
    function to_hm($m) {
        $h = floor($m / 60);
        $m = (int)$m % 60;
        return padzero($h) . ':' . padzero($m);
    }
    
    // 기본 시간 설정 (기본값으로 사용)
    $start_time_min = to_time('09:00');
    $opening_time_min = to_time('10:30');
    
    // 저장된 추가 시간 불러오기
    $extra_times = [];
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^bom/', $line)) continue;
            $cols = explode(",", $line);
            if (count($cols) >= 15) {
                $event_no = $cols[0];
                $extra_time = !empty($cols[14]) ? intval($cols[14]) : 0;
                $extra_times[$event_no] = $extra_time;
            }
        }
    }
    
    // 타임테이블 데이터 처리
    $events = [];
    $raw_no_groups = []; // raw_no별로 그룹화
    
    // RunOrder에서 이벤트 불러오기
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^bom/', $line)) continue;
            
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) < 14) continue;
            
            $no = $cols[0] ?? '';
            $desc = $cols[1] ?? '';
            $roundtype = $cols[2] ?? '';
            $roundnum = $cols[3] ?? '';
            $detail_no = $cols[13] ?? '';
            $dances = [];
            for ($i = 6; $i <= 10; $i++) {
                if (!empty($cols[$i])) $dances[] = $cols[$i];
            }
            if (count($dances) === 0) continue; // 경기 외 이벤트는 타임테이블에서 제외
            
            // raw_no별로 그룹화
            if (!isset($raw_no_groups[$no])) {
                $raw_no_groups[$no] = [];
            }
            $extra_time = isset($cols[14]) && !empty($cols[14]) ? intval($cols[14]) : 0;
            
            $raw_no_groups[$no][] = [
                'no' => $no,
                'desc' => $desc,
                'roundtype' => $roundtype,
                'roundnum' => $roundnum,
                'detail_no' => $detail_no,
                'dances' => $dances,
                'dance_count' => count($dances),
                'extra_time' => $extra_time
            ];
        }
    }
    
    // 각 raw_no 그룹에서 댄스 수가 가장 많은 이벤트를 찾아 시간 계산용으로 사용
    foreach ($raw_no_groups as $raw_no => $group) {
        // 댄스 수가 가장 많은 이벤트 찾기 (시간 계산용)
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
            $duration = $base_time * $max_dance_count; // 종목수만큼 곱하기!
            
            // 멀티이벤트의 경우 raw_no 기준으로 추가 시간 사용
            $extra_time = isset($extra_times[$raw_no]) ? $extra_times[$raw_no] : 0;
            
            $events[] = [
                'no' => $selected_event['no'],
                'desc' => $selected_event['desc'],
                'roundtype' => $selected_event['roundtype'],
                'roundnum' => $selected_event['roundnum'],
                'detail_no' => $selected_event['detail_no'],
                'dances' => $selected_event['dances'],
                'dance_count' => $max_dance_count,
                'duration' => $duration,
                'extra_time' => $extra_time,
                'group_events' => $group
            ];
        }
    }
    
    // 특별 이벤트 불러오기
    $special_events = [];
    if (file_exists($special_events_file)) {
        $special_events = json_decode(file_get_contents($special_events_file), true) ?? [];
    }
    
    // 타임테이블 계산: 각 이벤트의 시작/종료 시간 구하기
    $timetable_rows = [];
    $cur_min = $start_time_min;
    $opening_row_idx = null;
    
    for ($i = 0; $i < count($events); $i++) {
        // 개회식 삽입 체크
        if ($cur_min < $opening_time_min && $cur_min + $events[$i]['duration'] >= $opening_time_min && $opening_row_idx === null) {
            // 개회식 삽입
            $timetable_rows[] = [
                'type' => 'opening',
                'title' => '개회식',
                'desc' => '대회 개회식',
                'start' => $opening_time_min,
                'end' => $opening_time_min + 20,
                'start_time' => to_hm($opening_time_min),
                'end_time' => to_hm($opening_time_min + 20),
                'is_opening' => true
            ];
            $cur_min = $opening_time_min + 20;
            $opening_row_idx = count($timetable_rows) - 1;
        }
        
        // 추가 시간 적용
        $extra_time = $events[$i]['extra_time'] ?? 0;
        $total_duration = $events[$i]['duration'] + $extra_time;
        
        $timetable_rows[] = [
            'type' => 'event',
            'no' => $events[$i]['no'],
            'title' => $events[$i]['desc'],
            'desc' => $events[$i]['desc'],
            'roundtype' => $events[$i]['roundtype'],
            'roundnum' => $events[$i]['roundnum'],
            'dances' => $events[$i]['dances'],
            'dance_count' => $events[$i]['dance_count'],
            'duration' => $events[$i]['duration'],
            'start' => $cur_min,
            'end' => $cur_min + $total_duration,
            'start_time' => to_hm($cur_min),
            'end_time' => to_hm($cur_min + $total_duration),
            'extra_time' => $extra_time,
            'group_events' => $events[$i]['group_events']
        ];
        $cur_min += $total_duration;
        
        // 특별 이벤트 확인 (현재 이벤트 번호 후에 삽입할 특별 이벤트)
        $event_no = $events[$i]['no'];
        foreach ($special_events as $special_event) {
            if ($special_event['after_event'] == $event_no) {
                $special_duration = intval($special_event['duration']);
                $timetable_rows[] = [
                    'type' => 'special',
                    'no' => '',
                    'title' => $special_event['name'],
                    'desc' => $special_event['name'],
                    'roundtype' => '',
                    'roundnum' => '',
                    'dances' => [],
                    'dance_count' => 0,
                    'duration' => $special_duration,
                    'start' => $cur_min,
                    'end' => $cur_min + $special_duration,
                    'start_time' => to_hm($cur_min),
                    'end_time' => to_hm($cur_min + $special_duration),
                    'is_special' => true,
                    'special_type' => $special_event['name']
                ];
                $cur_min += $special_duration;
            }
        }
    }
    
    // 타임테이블 데이터 구성
    $timetable_data = [
        'comp_id' => $comp_id,
        'competition_info' => $info,
        'events' => $events,
        'special_events' => $special_events,
        'timetable_rows' => $timetable_rows, // 시간이 계산된 전체 타임테이블
        'start_time' => to_hm($start_time_min),
        'opening_time' => to_hm($opening_time_min),
        'generated_at' => date('Y-m-d H:i:s'),
        'generated_by' => $_SESSION['user']['username']
    ];
    
    // 메인 시스템 타임테이블 디렉토리에 저장
    $main_timetable_dir = __DIR__ . '/../data/timetables';
    if (!is_dir($main_timetable_dir)) {
        mkdir($main_timetable_dir, 0755, true);
    }
    
    $timetable_file = "$main_timetable_dir/timetable_$comp_id.json";
    $result = file_put_contents($timetable_file, json_encode($timetable_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($result === false) {
        throw new Exception('타임테이블 데이터 저장에 실패했습니다.');
    }
    
    // 메인 시스템의 competitions.json 업데이트
    $competitions_file = __DIR__ . '/../data/competitions.json';
    $competitions = [];
    
    if (file_exists($competitions_file)) {
        $competitions = json_decode(file_get_contents($competitions_file), true) ?? [];
    }
    
    // 기존 대회 정보 찾기
    $competition_updated = false;
    foreach ($competitions as &$competition) {
        if (isset($competition['our_system_id']) && $competition['our_system_id'] === $comp_id) {
            $competition['timetable_updated_at'] = date('Y-m-d H:i:s');
            $competition['timetable_file'] = "timetables/timetable_$comp_id.json";
            $competition['has_timetable'] = true;
            $competition_updated = true;
            break;
        }
    }
    
    // 새로운 대회라면 추가
    if (!$competition_updated) {
        $competitions[] = [
            'id' => 'comp_' . $comp_id,
            'name' => $info['title'],
            'title' => $info['title'],
            'subtitle' => '',
            'date' => $info['date'],
            'location' => $info['place'],
            'place' => $info['place'],
            'host' => $info['host'],
            'country' => $info['country'] ?? 'KR',
            'status' => (strtotime($info['date']) < time()) ? 'completed' : 'upcoming',
            'our_system_id' => $comp_id,
            'timetable_file' => "timetables/timetable_$comp_id.json",
            'timetable_updated_at' => date('Y-m-d H:i:s'),
            'has_timetable' => true,
            'created_at' => date('Y-m-d H:i:s', $info['created']),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // competitions.json 저장
    file_put_contents($competitions_file, json_encode($competitions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'message' => '타임테이블이 성공적으로 대회 대쉬보드에 푸시되었습니다.',
        'data' => [
            'comp_id' => $comp_id,
            'competition_title' => $info['title'],
            'events_count' => count($events),
            'special_events_count' => count($special_events),
            'total_rows_count' => count($timetable_rows),
            'timetable_file' => $timetable_file,
            'pushed_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '타임테이블 푸시 중 오류가 발생했습니다: ' . $e->getMessage()
    ]);
}
?>