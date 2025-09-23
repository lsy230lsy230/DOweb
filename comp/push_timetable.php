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
if (!isset($_SESSION['user']) || !hasPermission($_SESSION['user']['role'], 'create_comp')) {
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
    
    // 타임테이블 데이터 처리
    $timetable_data = [];
    $events = [];
    
    // RunOrder에서 이벤트 불러오기
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^bom/', $line)) continue;
            
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) < 14) continue;
            
            $events[] = [
                'no' => $cols[0] ?? '',
                'desc' => $cols[1] ?? '',
                'roundtype' => $cols[2] ?? '',
                'roundnum' => $cols[3] ?? '',
                'dances' => array_filter(array_slice($cols, 6, 5)), // 6~10번째 컬럼
                'extra_time' => isset($cols[14]) && !empty($cols[14]) ? intval($cols[14]) : 0
            ];
        }
    }
    
    // 특별 이벤트 불러오기
    $special_events = [];
    if (file_exists($special_events_file)) {
        $special_events = json_decode(file_get_contents($special_events_file), true) ?? [];
    }
    
    // 타임테이블 데이터 구성
    $timetable_data = [
        'comp_id' => $comp_id,
        'competition_info' => $info,
        'events' => $events,
        'special_events' => $special_events,
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