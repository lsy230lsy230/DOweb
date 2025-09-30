<?php
// 테스트 페이지 2: 고급 멀티 이벤트 탭 구조 (실제 데이터 연동)
$comp_id = $_GET['comp_id'] ?? '20250913-001';
$data_dir = __DIR__ . "/data/$comp_id";

// 집계 결과 표시 모드 확인
$view_mode = $_GET['view'] ?? '';
$view_event_no = $_GET['event_no'] ?? '';
$aggregation_result = null;

// 집계 결과 표시 모드인 경우
if ($view_mode === 'aggregation' && $view_event_no) {
    // 직접 API 파일을 include하여 실행
    $aggregation_result = null;
    
    try {
        // GET 매개변수 설정
        $_GET['comp_id'] = $comp_id;
        $_GET['event_no'] = $view_event_no;
        
        // API 파일 직접 실행하여 결과 캡처
        ob_start();
        $include_result = include 'final_aggregation_api.php';
        $aggregation_data = ob_get_clean();
        
        // include 실패 확인
        if ($include_result === false) {
            error_log("final_aggregation_api.php include 실패");
            $aggregation_data = json_encode(['success' => false, 'error' => 'API 파일을 로드할 수 없습니다.']);
        }
        
        error_log("집계 데이터 길이: " . strlen($aggregation_data));
        error_log("집계 데이터: " . substr($aggregation_data, 0, 500));
        
        if ($aggregation_data) {
            $aggregation_result = json_decode($aggregation_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON 파싱 오류: " . json_last_error_msg());
                error_log("원본 데이터: " . substr($aggregation_data, 0, 500));
                $aggregation_result = null;
            } else {
                error_log("집계 결과 파싱 성공: " . json_encode($aggregation_result));
            }
        } else {
            error_log("집계 데이터 로드 실패");
        }
        
        // 디버깅: 집계 결과 상태 확인
        error_log("집계 결과 최종 상태: " . ($aggregation_result ? "성공" : "실패"));
        if ($aggregation_result) {
            error_log("집계 결과 키: " . implode(', ', array_keys($aggregation_result)));
        }
    } catch (Exception $e) {
        error_log("집계 API 실행 오류: " . $e->getMessage());
        error_log("오류 스택 트레이스: " . $e->getTraceAsString());
        $aggregation_result = null;
    } catch (Error $e) {
        error_log("집계 API 치명적 오류: " . $e->getMessage());
        error_log("오류 스택 트레이스: " . $e->getTraceAsString());
        $aggregation_result = null;
    }
}

// --- 댄스종목 약어->이름 매핑 (DanceName.txt 기준) ---
$dancename_file = "$data_dir/DanceName.txt";
$dance_map_en = [];
if (is_file($dancename_file)) {
    foreach (file($dancename_file) as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) < 3 || $cols[2] == '-' || $cols[2] == '') continue;
        // 영문 코드를 키로 사용
        $dance_map_en[$cols[2]] = $cols[1];
        // 숫자 코드도 키로 사용 (28번 이벤트 등에서 사용)
        $dance_map_en[$cols[0]] = $cols[1];
    }
}

// --- 전체 선수명단 players.txt (등번호,남자,여자) ---
$players_file = "$data_dir/players.txt";
$all_players = [];
if (file_exists($players_file)) {
    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        $all_players[$cols[0]] = [
            'male' => $cols[1] ?? '',
            'female' => $cols[2] ?? '',
        ];
    }
}

// 실제 이벤트 데이터 로드 (live_panel.php와 동일한 로직)
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
        $no = preg_replace('/\x{FEFF}/u', '', $no); // UTF-8 BOM 제거
        $no = preg_replace('/\D+/', '', $no);       // 숫자만 남김
        $no = trim($no);
        $desc = $cols[1] ?? '';
        $roundtype = $cols[2] ?? '';
        $panel = isset($cols[11]) ? strtoupper($cols[11]) : '';
        $recall = $cols[4] ?? ''; // 4번째 컬럼이 리콜 수 (인덱스 4)
        $heats = $cols[14] ?? ''; // 히트는 15번째 컬럼 (인덱스 14)
        $dance_codes = [];
        // 7-11번째 컬럼의 숫자를 댄스 코드로 사용 (6번째 컬럼은 next_event이므로 건너뛰기)
        for ($i=7; $i<=11; $i++) {
            if (isset($cols[$i]) && is_numeric($cols[$i]) && $cols[$i] > 0) {
                $dance_codes[] = $cols[$i];
            }
        }
        
        // 디버깅: 30번 이벤트의 댄스 코드 확인
        if ($no === '30') {
            error_log("30번 이벤트 댄스 코드: " . implode(',', $dance_codes));
            error_log("30번 이벤트 컬럼 7-11: " . implode(',', array_slice($cols, 7, 5)));
        }
        // 댄스 코드를 실제 댄스명으로 변환
        $dance_names = [];
        foreach ($dance_codes as $code) {
            $dance_names[] = $dance_map_en[$code] ?? $code; // 매핑된 이름 또는 코드
        }
        
        $next_event = $cols[5] ?? ''; // 6번째 컬럼에서 next_event 읽기
        
        // 디버깅: 30번 이벤트의 next_event 확인
        if ($no === '30') {
            error_log("30번 이벤트 next_event: " . $next_event);
            error_log("30번 이벤트 전체 컬럼: " . implode(',', $cols));
        }
        
        $events[] = [
            'no' => $no,
            'desc' => $desc,
            'round' => $roundtype,
            'panel' => $panel,
            'recall' => $recall,
            'recall_count' => (is_numeric($recall) ? intval($recall) : 0),
            'heats' => $heats,
            'dances' => $dance_codes,
            'dance_names' => $dance_names,
            'detail_no' => $cols[13] ?? '', // 14번째 컬럼에서 detail_no 읽기
            'next_event' => $next_event // 6번째 컬럼에서 next_event 읽기
        ];
    }
}

// 실제 심사위원 데이터 로드 (live_panel.php와 동일한 로직)
$adjudicator_file = "$data_dir/adjudicators.txt";
$adjudicator_dict = [];
if (file_exists($adjudicator_file)) {
    $lines = file($adjudicator_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) < 2) continue;
        $code = (string)$cols[0];
        $adjudicator_dict[$code] = [
            'code' => $cols[0],
            'name' => $cols[1],
            'nation' => $cols[2] ?? '',
            'id' => $cols[3] ?? ''
        ];
    }
}

// 선수 데이터 로드
$player_file = "$data_dir/players.txt";
$player_dict = [];
if (file_exists($player_file)) {
    $lines = file($player_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) < 2) continue;
        $number = (string)$cols[0];
        $player_dict[$number] = $cols[1];
    }
}

// 패널 매핑 데이터 로드
$panel_map_file = "$data_dir/panel_list.json";
$panel_map = [];
if (file_exists($panel_map_file)) {
    $panel_map = json_decode(file_get_contents($panel_map_file), true);
}

// 실제 선수 데이터 로드 (live_panel.php와 동일한 로직)
$players_by_event = [];
foreach ($events as $ev) {
    $eno = $ev['no'];
    $detail_no = $ev['detail_no'] ?? '';
    
    // 세부번호가 있으면 세부번호로, 없으면 이벤트 번호로
    $file_key = !empty($detail_no) ? $detail_no : $eno;
    $pfile = "$data_dir/players_$file_key.txt";
    
    if (file_exists($pfile)) {
        $lines = file($pfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $player_data = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // 등번과 이름 분리 (공백으로 구분)
                $parts = explode(' ', $line, 2);
                $number = trim($parts[0]);
                $name = isset($parts[1]) ? trim($parts[1]) : '';
                
                // 등번 정규화 (숫자와 하이픈만 남김)
            if (!empty($detail_no)) {
                    $num = preg_replace('/[^0-9\-]/', '', $number);
            } else {
                    $num = preg_replace('/[^0-9]/', '', $number);
                }
                
                if (!empty($num)) {
                    $arr = [
                        'number' => $num,
                        'name' => $name,
                        'display_name' => $name ? "$num $name" : $num,
                        'type' => 'couple' // 기본값, 실제로는 데이터에서 판단
                    ];
                    $player_data[] = $arr;
                }
            }
        }
        $players_by_event[$eno] = $player_data;
        // 세부번호가 있으면 세부번호 키로도 저장
        if (!empty($detail_no)) {
            $players_by_event[$detail_no] = $player_data;
        }
    }
}

// 이벤트 그룹 생성 (실제 데이터 기반)
$event_groups = [];
$grouped_events = [];

// 이벤트를 그룹화 (detail_no가 있는 경우 멀티 이벤트로 처리)
$processed_events = []; // 이미 처리된 이벤트 추적

foreach ($events as $event) {
    $group_key = $event['detail_no'] ? $event['no'] : $event['no'];
    
    // 이벤트 고유 식별자 생성 (중복 방지)
    $event_id = $event['no'] . '_' . $event['desc'] . '_' . ($event['detail_no'] ?: '');
    
    // 이미 처리된 이벤트인지 확인
    if (in_array($event_id, $processed_events)) {
        continue; // 중복 이벤트는 건너뛰기
    }
    
    $processed_events[] = $event_id;
    
    if (!isset($grouped_events[$group_key])) {
        $grouped_events[$group_key] = [
            'group_no' => $group_key,
            'group_name' => $event['desc'],
            'events' => [],
            'is_multi' => false,
            'recall_count' => intval($event['recall_count'] ?? 0)
        ];
    }
    
    $grouped_events[$group_key]['events'][] = $event;
}

// 멀티 이벤트 확인
foreach ($grouped_events as $group_key => $group) {
    if (count($group['events']) > 1) {
        $grouped_events[$group_key]['is_multi'] = true;
    }
}

$event_groups = array_values($grouped_events);

// 디버깅: 50번 이상 그룹들 확인
$high_number_groups = array_filter($event_groups, function($group) {
    return intval($group['group_no']) >= 50;
});
error_log("50번 이상 그룹 수: " . count($high_number_groups));
foreach ($high_number_groups as $group) {
    error_log("그룹 " . $group['group_no'] . ": " . $group['group_name'] . " (이벤트 수: " . count($group['events']) . ")");
}


// 이벤트별 선수 데이터 로드 함수
function getPlayersForEvent($data_dir, $event_key, $all_players) {
    $players = [];
    
    // 이벤트별 선수 파일 확인
    $players_file = "$data_dir/players_{$event_key}.txt";
    if (file_exists($players_file)) {
        $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $player_data = $all_players[$line] ?? null;
                if ($player_data) {
                    // 싱글/커플 구분: 여자 이름이 있으면 커플, 없으면 싱글
                    $isCouple = !empty($player_data['female']);
                    $players[] = [
                        'number' => $line,
                        'name' => $player_data['male'] . ($player_data['female'] ? ' & ' . $player_data['female'] : ''),
                        'male' => $player_data['male'],
                        'female' => $player_data['female'],
                        'type' => $isCouple ? 'couple' : 'single'
                    ];
                } else {
                    $players[] = [
                        'number' => $line,
                        'name' => "선수 {$line}",
                        'male' => '',
                        'female' => '',
                        'type' => 'single' // 기본값은 싱글
                    ];
                }
            }
        }
    }
    
    return $players;
}

// 이벤트별 심사위원 상태 확인 함수
function getJudgeStatus($data_dir, $event_key, $dances, $panel_map, $adjudicator_dict) {
    global $events;
    $judge_status = [];
    
    // 해당 이벤트의 패널 코드 찾기 (실제 이벤트 데이터에서)
    $panel_code = '';
    foreach ($events as $event) {
        $event_key_check = $event['detail_no'] ?: $event['no'];
        if ($event_key_check === $event_key) {
            $panel_code = $event['panel'];
            break;
        }
    }
    
    if (empty($panel_code)) {
        return $judge_status;
    }
    
    // 패널에 속한 심사위원들 찾기
    $panel_judges = [];
    foreach ($panel_map as $mapping) {
        if (isset($mapping['panel_code']) && $mapping['panel_code'] === $panel_code) {
            $judge_code = $mapping['adj_code'];
            if (isset($adjudicator_dict[$judge_code])) {
                $panel_judges[] = $judge_code;
            }
        }
    }
    
    // 각 심사위원의 채점 상태 확인
    foreach ($panel_judges as $judge_code) {
        $judge_info = $adjudicator_dict[$judge_code];
        $completed = 0;
        $total = count($dances);
        
        // .adj 파일에서 실제 채점 상태 확인
        $adj_file = "$data_dir/{$event_key}.adj";
        if (file_exists($adj_file)) {
            $adj_data = file($adj_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($adj_data as $line) {
                if (strpos($line, $judge_code) === 0) {
                    $completed++;
                }
            }
        }
        
        $status = $completed === $total ? 'completed' : ($completed > 0 ? 'partial' : 'waiting');
        
        $judge_status[] = [
            'code' => $judge_code,
            'name' => $judge_info['name'],
            'country' => $judge_info['nation'],
            'status' => $status,
            'completed' => $completed,
            'total' => $total
        ];
    }
    
    return $judge_status;
}

// 이벤트 그룹에 심사위원/선수 데이터 추가
foreach ($event_groups as &$group) {
    foreach ($group['events'] as &$event) {
        $event_key = $event['detail_no'] ?: $event['no'];
        $event['judges'] = getJudgeStatus($data_dir, $event_key, $event['dances'], $panel_map, $adjudicator_dict);
        $event['players'] = getPlayersForEvent($data_dir, $event_key, $all_players);
    }
    
    // 멀티 이벤트인 경우 댄스 순서 생성
    if (count($group['events']) > 1) {
        $group['dance_sequence'] = generateDanceSequence($group['events']);
    }
}

// 카테고리 추출 함수
function extractCategory($desc) {
    // 이벤트 설명에서 카테고리 추출 (예: "1-1 탱고" -> "1-1")
    if (preg_match('/^(\d+-\d+)/', $desc, $matches)) {
        return $matches[1];
    }
    return $desc;
}

// 멀티이벤트의 댄스 순서 취합 함수
function generateDanceSequence($events) {
    global $dance_map_en;
    $all_dances = [];
    $dance_events = [];
    
    // 모든 이벤트의 댄스 수집
    foreach ($events as $event) {
        if (!empty($event['dances'])) {
            foreach ($event['dances'] as $dance) {
                if (!isset($all_dances[$dance])) {
                    $all_dances[$dance] = [];
                }
                $all_dances[$dance][] = $event['detail_no'] ?: $event['no'];
            }
        }
    }
    
    // 공동 댄스와 개별 댄스 분류
    $common_dances = [];
    $individual_dances = [];
    
    foreach ($all_dances as $dance => $event_list) {
        $dance_name = $dance_map_en[$dance] ?? $dance;
        if (count($event_list) > 1) {
            // 여러 이벤트에서 공통으로 사용되는 댄스
            $common_dances[] = [
                'dance' => $dance,
                'dance_name' => $dance_name,
                'events' => $event_list,
                'type' => 'common'
            ];
        } else {
            // 개별 이벤트에서만 사용되는 댄스
            $individual_dances[] = [
                'dance' => $dance,
                'dance_name' => $dance_name,
                'events' => $event_list,
                'type' => 'individual'
            ];
        }
    }
    
    // 공동 댄스를 먼저, 개별 댄스를 나중에 배치
    $sequence = array_merge($common_dances, $individual_dances);
    
    return $sequence;
}

// 각 그룹의 댄스 순서 생성
foreach ($event_groups as $group_key => &$group) {
    if (count($group['events']) > 1) {
        $group['dance_sequence'] = generateDanceSequence($group['events']);
    } else {
        // 싱글 이벤트는 기존 댄스 순서 유지
        $group['dance_sequence'] = [];
        if (!empty($group['events'][0]['dances'])) {
            foreach ($group['events'][0]['dances'] as $dance) {
                $dance_name = $dance_map_en[$dance] ?? $dance;
                $group['dance_sequence'][] = [
                    'dance' => $dance,
                    'dance_name' => $dance_name,
                    'events' => [$group['events'][0]['detail_no'] ?: $group['events'][0]['no']],
                    'type' => 'single'
                ];
            }
        }
    }
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>라이브 패널 - 멀티 이벤트 관리</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            display: flex;
            gap: 20px;
            max-width: 1600px;
            margin: 0 auto;
            height: 100vh;
            overflow: hidden;
        }
        
        .left-panel {
            width: 350px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            overflow-x: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .right-panel {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        #right-content {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .panel-header {
            background: #2c3e50;
            color: white;
            padding: 15px;
            font-weight: bold;
            font-size: 16px;
        }
        
        .event-group {
            border-bottom: 1px solid #eee;
        }
        
        .event-group:last-child {
            border-bottom: none;
        }
        
        .group-header {
            background: #34495e;
            color: white;
            padding: 12px 15px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s;
        }
        
        .group-header:hover {
            background: #2c3e50;
        }
        
        .group-header.selected {
            background: #e74c3c;
        }
        
        .group-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .group-title {
            font-size: 14px;
        }
        
        .group-subtitle {
            font-size: 11px;
            opacity: 0.8;
        }
        
        .group-toggle {
            font-size: 14px;
            transition: transform 0.3s;
        }
        
        .group-toggle.expanded {
            transform: rotate(90deg);
        }
        
        .event-list {
            display: none;
            background: #f8f9fa;
            flex: 1;
            overflow-y: auto;
            max-height: calc(100vh - 200px);
        }
        
        .event-list.expanded {
            display: block;
        }
        
        .event-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        
        .event-item:hover {
            background: #e3f2fd;
        }
        
        .event-item.selected {
            background: #bbdefb;
            border-left: 4px solid #2196f3;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-info {
            flex: 1;
        }
        
        .event-number {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 3px;
            font-size: 14px;
        }
        
        .event-desc {
            font-size: 11px;
            color: #666;
            line-height: 1.3;
            margin-bottom: 2px;
        }
        
        .event-dances {
            font-size: 10px;
            color: #888;
            font-style: italic;
        }
        
        .event-status {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            text-align: center;
            min-width: 50px;
        }
        
        .status-final {
            background: #d4edda;
            color: #155724;
        }
        
        .status-semi {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-prelim {
            background: #f8d7da;
            color: #721c24;
        }
        
        .right-header {
            background: #34495e;
            color: white;
            padding: 15px 20px;
        }
        
        .right-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .event-info-row {
            display: grid;
            grid-template-columns: 80px 80px 80px 1fr;
            gap: 20px;
            align-items: center;
            font-size: 12px;
        }
        
        /* 싱글이벤트 채점현황판 전용 스타일 */
        .single-event-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .single-event-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        
        .single-event-number {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 20px;
            font-weight: bold;
            min-width: 60px;
            text-align: center;
        }
        
        .single-event-name {
            font-size: 24px;
            font-weight: 600;
            flex: 1;
            min-width: 200px;
        }
        
        .single-event-round {
            background: rgba(255,255,255,0.15);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .single-event-content {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
            height: calc(100vh - 220px);
            overflow: hidden;
        }
        
        .single-event-main {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            overflow-y: auto;
        }
        
        .single-event-sidebar {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-y: auto;
        }
        
        .participants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .participant-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .participant-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .participant-number {
            background: #3498db;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .participant-names {
            font-size: 14px;
            color: #2c3e50;
            line-height: 1.4;
            margin-bottom: 8px;
        }
        
        .participant-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        
        .scoring-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .scoring-btn:hover {
            background: #229954;
        }
        
        .recall-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .recall-btn:hover {
            background: #c0392b;
        }
        
        .sidebar-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .sidebar-section:last-child {
            border-bottom: none;
        }
        
        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sidebar-content {
            font-size: 14px;
            color: #5a6c7d;
            line-height: 1.5;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 8px;
        }
        
        .status-waiting {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-active {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        /* 반응형 디자인 */
        @media (max-width: 1200px) {
            .single-event-content {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr auto;
            }
            
            .single-event-sidebar {
                max-height: 300px;
            }
            
            .participants-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .single-event-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .single-event-number {
                align-self: flex-start;
            }
            
            .participants-grid {
                grid-template-columns: 1fr;
            }
            
            .single-event-content {
                gap: 15px;
            }
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .info-label {
            font-weight: bold;
            color: #bdc3c7;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            color: white;
            font-size: 12px;
        }
        
        .dance-sequence-value {
            color: #3498db;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .dance-sequence-value:hover {
            text-decoration: underline;
        }
        
        .dance-edit-icon {
            font-size: 10px;
            opacity: 0.7;
        }
        
        .recall-count-value {
            color: #e74c3c;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .recall-count-value:hover {
            text-decoration: underline;
        }
        
        .recall-edit-icon {
            font-size: 10px;
            opacity: 0.7;
        }
        
        .right-subtitle {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .group-info-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .group-title {
            font-size: 16px;
            font-weight: bold;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .group-subtitle {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
        }
        
        .event-details {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            font-size: 10px;
            color: #666;
        }
        
        .event-detail-item {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 10px;
            white-space: nowrap;
        }
        
        .dance-sequence {
            color: #2196f3;
            font-weight: 500;
        }
        
        .dance-sequence-editable {
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .dance-sequence-editable:hover {
            background: rgba(33, 150, 243, 0.1);
        }
        
        .dance-edit-icon {
            margin-left: 5px;
            font-size: 10px;
            opacity: 0.7;
        }
        
        .recall-count-value {
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
        }
        
        .recall-count-value:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .recall-edit-icon {
            margin-left: 5px;
            font-size: 10px;
            opacity: 0.7;
        }
        
        .next-round-info {
            color: #28a745;
            font-weight: 600;
            font-size: 11px;
        }
        
        .recall-edit-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .recall-edit-content {
            background: white;
            border-radius: 8px;
            width: 400px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .recall-edit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .recall-edit-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .recall-edit-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .recall-edit-body {
            padding: 20px;
        }
        
        .recall-edit-field {
            margin-bottom: 20px;
        }
        
        .recall-edit-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .recall-edit-field input {
            width: 100px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .recall-edit-unit {
            margin-left: 8px;
            color: #666;
        }
        
        .recall-edit-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
        
        .recall-edit-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }
        
        .recall-edit-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .dance-edit-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .dance-edit-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .dance-edit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .dance-edit-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .dance-edit-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .dance-list-container {
            margin-bottom: 20px;
        }
        
        .dance-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 8px;
            background: #f8f9fa;
        }
        
        .dance-item.dragging {
            opacity: 0.5;
        }
        
        .dance-drag-handle {
            cursor: move;
            color: #666;
            font-size: 16px;
        }
        
        .dance-number {
            background: #2196f3;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .dance-info {
            flex: 1;
        }
        
        .dance-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .dance-events {
            font-size: 12px;
            color: #666;
        }
        
        .dance-actions {
            display: flex;
            gap: 5px;
        }
        
        .dance-action-btn {
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .dance-remove-btn {
            background: #dc3545;
            color: white;
        }
        
        .dance-remove-btn:hover {
            background: #c82333;
        }
        
        .dance-edit-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-save {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .event-cards-container {
            padding: 20px;
            min-height: 400px;
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .event-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
            flex: 1;
            overflow: hidden;
        }
        
        /* 싱글 이벤트용 넓은 그리드 */
        .event-cards-grid.single-event {
            grid-template-columns: 1fr;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .single-event-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #0d2c96;
            padding: 0;
            width: 100%;
            min-width: 0;
        }
        
        .event-header-panel {
            background: #bdbdbd;
            border: 3px solid #071d6e;
            border-radius: 0 0 12px 12px;
            padding: 0.6em 1em;
            margin: 0 0 0.8em 0;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 0.6em;
            width: 100%;
            box-sizing: border-box;
            min-height: 100px;
        }
        
        .event-header-box {
            background: #bdbdbd;
            border: 3px solid #071d6e;
            border-radius: 6px;
            padding: 0.5em 0.8em;
            width: 100%;
            max-width: 900px;
            min-width: 300px;
            font-family: Arial, sans-serif;
            margin-right: 0;
            box-sizing: border-box;
        }
        
        .event-row1, .event-row2 {
            display: flex;
            align-items: center;
            gap: 0.6em;
            margin-bottom: 0.25em;
        }
        
        .event-row2 {
            margin-bottom: 0;
        }
        
        .event-number-display {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.2em;
        }
        
        .event-number {
            font-size: 1.2em;
            font-weight: bold;
            color: #071d6e;
        }
        
        .event-title-display {
            flex: 1;
        }
        
        .event-title {
            font-size: 1.1em;
            font-weight: 600;
            color: white;
        }
        
        .ev-row2-label {
            font-weight: 600;
            color: #071d6e;
            font-size: 0.9em;
        }
        
        .ev-row2-value {
            color: #2c3e50;
            font-size: 0.9em;
            margin-right: 1em;
        }
        
        .event-content-panel {
            flex: 1;
            background: white;
            margin: 0 0.8em 0.8em 0.8em;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .judge-status-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 1.2em;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 8px;
        }
        
        .judge-progress {
            text-align: center;
            padding: 20px;
        }
        
        .progress-text {
            color: #666;
            font-size: 14px;
        }
        
        .main-content-row {
            display: flex;
            flex-direction: row;
            gap: 1.2em;
            height: 93%;
        }
        
        .adjudicator-list-panel {
            flex: 0 0 40%;
            background: #eaf0ff;
            border-radius: 8px;
            margin-top: 0.2em;
            padding: 1em 1em 1em 1em;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        
        .adjudicator-list-panel h3 {
            font-size: 1.1em;
            color: #0d2c96;
            margin: 0 0 0.6em 0;
        }
        
        .adjudicator-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .adjudicator-list li {
            margin-bottom: 0.28em;
            padding: 0.13em 0.2em;
            background: #fff;
            border-radius: 4px;
            font-size: 0.97em;
            color: #282828;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .adjudicator-list li.disabled {
            color: #aaa;
            text-decoration: line-through;
            background: #f5f5f5;
        }
        
        .adjudicator-x-btn {
            background: #dc3232;
            color: #fff;
            border: none;
            border-radius: 3px;
            padding: 2px 8px;
            font-size: 1em;
            cursor: pointer;
            margin-left: 0.5em;
        }
        
        .adjudicator-x-btn:disabled {
            background: #ccc;
            color: #888;
            cursor: default;
        }
        
        .adjudicator-list-panel .empty {
            color: #888;
            margin-top: 0.7em;
            font-size: 0.98em;
        }
        
        .adjudicator-list-panel table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .adjudicator-list-panel th {
            font-size: 0.9em;
            color: #0d2c96;
            padding: 0.3em 0.2em;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .adjudicator-list-panel td {
            padding: 0.3em 0.2em;
            font-size: 0.9em;
        }
        
        .adjudicator-list-panel td:nth-child(1) {
            width: 6%;
        }
        
        .adjudicator-list-panel td:nth-child(2) {
            width: 10%;
        }
        
        .adjudicator-list-panel td:nth-child(3) {
            width: 30%;
        }
        
        .adjudicator-list-panel td:nth-child(4) {
            width: 12%;
        }
        
        .adjudicator-list-panel td:nth-child(5) {
            width: 12%;
            text-align: center;
        }
        
        .adjudicator-list-panel td:nth-child(6) {
            width: 30%;
            text-align: center;
        }
        
        .player-dance-row {
            flex: 1;
            display: flex;
            flex-direction: row;
            gap: 1em;
        }
        
        .player-list-panel {
            background: #eaf0ff;
            border-radius: 8px;
            padding: 1em;
            flex: 1;
            min-width: 0;
        }
        
        .player-list-panel h3 {
            font-size: 1.1em;
            color: #0d2c96;
            margin: 0 0 0.6em 0;
        }
        
        .player-controls-row {
            display: flex;
            gap: 0.5em;
            margin-bottom: 1em;
            flex-wrap: wrap;
        }
        
        .add-player-btn, .show-entry-list-btn, .split-hit-btn, .show-hit-btn {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5em 1em;
            font-size: 0.9em;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .add-player-btn:hover, .show-entry-list-btn:hover, .split-hit-btn:hover, .show-hit-btn:hover {
            background: #218838;
        }
        
        .player-list-scrollbox {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .player-list-compact {
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            padding: 0.5em;
            max-height: none;
            overflow-y: visible;
        }
        
        .player-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .player-list li {
            padding: 0.3em 0.5em;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
        }
        
        .player-list li:last-child {
            border-bottom: none;
        }
        
        .dance-block {
            background: #eaf0ff;
            border-radius: 8px;
            padding: 1em;
            flex: 1;
            min-width: 0;
        }
        
        .dance-title {
            font-size: 1.1em;
            color: #0d2c96;
            margin: 0 0 0.6em 0;
            font-weight: bold;
        }
        
        .dance-progress-container {
            margin-top: 1em;
        }
        
        .dance-progress-bar {
            width: 100%;
            height: 20px;
            background: #ddd;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1em;
        }
        
        .dance-progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s ease;
            width: 0%;
        }
        
        .dance-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5em;
        }
        
        .dance-item {
            background: white;
            padding: 0.4em;
            border-radius: 4px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 0.9em;
        }
        
        .aggregation-section {
            background: transparent;
            border-radius: 8px;
            padding: 1em;
            margin-top: 1em;
        }
        
        .aggregation-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1em;
            margin-bottom: 2em;
        }
        
        .status-item {
            background: #f8f9fa;
            padding: 1em;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-label {
            font-weight: 600;
            color: #495057;
        }
        
        .aggregation-table h4 {
            margin: 0 0 1em 0;
            color: #495057;
        }
        
        .aggregation-results {
            background: transparent;
            border-radius: 6px;
            padding: 1em;
            min-height: 200px;
        }
        
        .loading {
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
        
        .judge-status {
            font-size: 0.8em;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
            text-align: center;
            min-width: 40px;
        }
        
        .judge-status.waiting {
            background: #ffc107;
            color: #212529;
        }
        
        .judge-status.scoring {
            background: #17a2b8;
            color: white;
        }
        
        .judge-status.completed {
            background: #28a745;
            color: white;
        }
        
        .dance-item {
            background: rgba(255,255,255,0.8);
            border: 1px solid #fdcb6e;
            border-radius: 0.3em;
            padding: 0.5em;
            text-align: center;
            font-size: 0.9em;
            color: #885e00;
            margin-bottom: 0.5em;
        }
        
        .dance-item.current {
            background: #e9b200;
            color: white;
            font-weight: bold;
        }
        
        .hit-block {
            margin-top: 0.8em;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1em;
        }
        
        .hit-title {
            font-size: 1em;
            color: #0d2c96;
            margin-bottom: 0.6em;
            font-weight: bold;
        }
        
        .hit-group {
            margin-bottom: 0.8em;
            padding: 0.5em;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .hit-number {
            font-weight: bold;
            color: #495057;
            margin-bottom: 0.3em;
        }
        
        .hit-players {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        /* 모달 스타일 */
        .modal-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            background: #0d2c96;
            color: white;
            padding: 1em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.2em;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-body {
            padding: 1.5em;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            background: #f8f9fa;
            padding: 1em;
            display: flex;
            justify-content: flex-end;
            gap: 0.5em;
        }
        
        .btn-primary, .btn-secondary {
            padding: 0.5em 1em;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .btn-primary {
            background: #0d2c96;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .modal-hit-group {
            margin-bottom: 1.5em;
            padding: 1em;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .modal-hit-group h4 {
            margin: 0 0 0.5em 0;
            color: #0d2c96;
        }
        
        .modal-hit-players {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5em;
        }
        
        .modal-player-item {
            padding: 0.3em 0.5em;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-size: 0.9em;
        }
        
        /* 진행종목 블록 컨트롤 */
        .dance-controls {
            margin-top: 1em;
            display: flex;
            gap: 0.5em;
            justify-content: center;
        }
        
        .aggregation-btn, .award-btn {
            background: #0d2c96;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5em 1em;
            font-size: 0.9em;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .aggregation-btn:hover, .award-btn:hover {
            background: #1a3bb8;
        }
        
        .award-btn {
            background: #28a745;
        }
        
        .award-btn:hover {
            background: #218838;
        }
        
        /* 상장 모달 스타일 */
        .award-options h4 {
            margin: 0 0 1em 0;
            color: #0d2c96;
        }
        
        .award-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5em;
            margin-bottom: 2em;
        }
        
        .award-type-btn {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            padding: 1em;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9em;
            text-align: center;
        }
        
        .award-type-btn:hover {
            background: #e9ecef;
            border-color: #0d2c96;
        }
        
        .award-preview {
            margin-top: 2em;
            padding: 1em;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .award-preview h4 {
            margin: 0 0 1em 0;
            color: #0d2c96;
        }
        
        .award-certificate {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* 집계 모달 스타일 */
        .aggregation-modal {
            width: 95%;
            max-width: 1400px;
            max-height: 90vh;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .aggregation-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .aggregation-table th {
            background: #0d2c96;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }
        
        .aggregation-table td {
            padding: 8px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
            font-size: 11px;
        }
        
        .aggregation-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .aggregation-table tr:hover {
            background: #e3f2fd;
        }
        
        .rank-1 { background: #ffd700 !important; color: #000; font-weight: bold; }
        .rank-2 { background: #c0c0c0 !important; color: #000; font-weight: bold; }
        .rank-3 { background: #cd7f32 !important; color: #fff; font-weight: bold; }
        
        .next-round-section {
            background: #e8f5e8;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .next-round-title {
            color: #28a745;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .next-round-players {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .next-round-player {
            background: white;
            border: 1px solid #28a745;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
            font-weight: 500;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        /* 리콜 결과 스타일 */
        .recall-stats-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #0d2c96;
        }
        
        .recall-results-section {
            margin: 20px 0;
        }
        
        .recall-results-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }
        
        .next-round-player {
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.1);
        }
        
        .player-rank {
            background: #28a745;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            margin-right: 15px;
        }
        
        .player-info {
            flex: 1;
        }
        
        .player-number {
            font-size: 16px;
            font-weight: bold;
            color: #0d2c96;
        }
        
        .player-name {
            font-size: 14px;
            color: #333;
            margin: 4px 0;
        }
        
        .recall-count {
            font-size: 12px;
            color: #666;
            background: #e8f5e8;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        
        /* 싱글 이벤트 카드용 추가 스타일 */
        .event-card .judge-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 6px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .event-card .judge-item.disabled {
            opacity: 0.5;
            text-decoration: line-through;
        }
        
        .event-card .judge-code {
            font-weight: bold;
            margin-right: 8px;
            min-width: 30px;
        }
        
        .event-card .judge-name {
            flex: 1;
            margin-right: 8px;
        }
        
        .event-card .judge-nation {
            margin-right: 8px;
            min-width: 30px;
        }
        
        .event-card .judge-status {
            margin-right: 8px;
        }
        
        .event-card .judge-btn-exclude {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 10px;
            cursor: pointer;
        }
        
        .event-card .judge-btn-exclude:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .event-card .judge-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 10px;
            cursor: pointer;
            margin-left: 4px;
        }
        
        .event-card .judge-btn:hover {
            transform: scale(1.1);
        }
        
        .event-card .judge-btn-edit {
            background: #2196f3;
            color: white;
        }
        
        .event-card .judge-btn-edit:hover {
            background: #1976d2;
        }
        
        .event-card .player-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 6px;
            background: white;
            border: 1px solid #28a745;
            border-radius: 6px;
            font-size: 12px;
            box-shadow: 0 1px 3px rgba(40, 167, 69, 0.1);
        }
        
        .event-card .player-number {
            font-weight: bold;
            margin-right: 8px;
            min-width: 30px;
        }
        
        .event-card .player-name {
            flex: 1;
        }
        
        .event-card .empty {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
        }
        
        .event-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
            overflow: hidden;
        }
        
        .event-card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .event-info-section {
            display: flex;
            align-items: center;
            gap: 20px;
            text-align: center;
        }
        
        .event-number {
            font-size: 24px;
            font-weight: bold;
            color: #0d2c96;
            background: #e3f2fd;
            padding: 8px 16px;
            border-radius: 8px;
            border: 2px solid #0d2c96;
        }
        
        .event-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            flex: 1;
        }
        
        .event-round {
            font-size: 16px;
            font-weight: 500;
            color: #28a745;
            background: #e8f5e8;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #28a745;
        }
        
        /* 싱글 이벤트 카드 넓게 */
        .event-cards-grid.single-event .event-card {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .event-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .event-card.selected {
            border-color: #2196f3;
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.2);
            transform: translateY(-2px);
        }
        
        
        .event-card-body {
            display: flex;
            min-height: 400px;
            padding-top: 15px;
        }
        
        /* 싱글 이벤트 카드 바디 더 넓게 */
        .event-cards-grid.single-event .event-card-body {
            min-height: 500px;
        }
        
        .event-card-left {
            flex: 1;
            padding: 15px;
            border-right: 1px solid #dee2e6;
            background: white;
        }
        
        .event-card-right {
            flex: 1;
            padding: 15px;
            background: white;
        }
        
        /* 싱글 이벤트 좌우 분할 더 넓게 */
        .event-cards-grid.single-event .event-card-left,
        .event-cards-grid.single-event .event-card-right {
            padding: 20px;
        }
        
        .event-card-title {
            font-size: 14px;
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        
        /* 싱글 이벤트 텍스트 크기 증가 */
        .event-cards-grid.single-event .event-card-title {
            font-size: 16px;
            margin-bottom: 16px;
        }
        
        .event-cards-grid.single-event .event-card-detail-row {
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .event-cards-grid.single-event .event-card-dances {
            font-size: 12px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        
        .event-card-details {
            margin-bottom: 15px;
        }
        
        .event-card-detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .event-card-detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .event-card-detail-value {
            color: #2c3e50;
        }
        
        .event-card-dances {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .event-card-judges {
            height: 100%;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 0;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .judges-header {
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #0d2c96;
            font-weight: bold;
            color: #0d2c96;
        }
        
        .event-title {
            font-size: 14px;
            font-weight: bold;
            color: white;
            margin-bottom: 8px;
        }
        
        .event-number {
            color: #007bff;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .judges-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .judges-progress {
            font-size: 10px;
            color: #666;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        .judges-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .judge-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .judge-item:hover {
            background: rgba(0,0,0,0.05);
        }
        
        .judge-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .judge-status-waiting {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }
        
        .judge-status-scoring {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .judge-status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        /* 심사위원 상태 표시 (백업 파일 방식) */
        .judge-status {
            font-size: 0.8em;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
            text-align: center;
            min-width: 40px;
        }
        .judge-status.scoring {
            background: #28a745;
            color: white;
        }
        .judge-status.completed {
            background: #007bff;
            color: white;
        }
        .judge-status.waiting {
            background: #ffc107;
            color: #333;
        }
        .judge-status.offline {
            background: #6c757d;
            color: white;
        }
        
        .judge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .judge-dot-waiting {
            background: #6c757d;
        }
        
        .judge-dot-scoring {
            background: #ffc107;
        }
        
        .judge-dot-completed {
            background: #28a745;
        }
        
        .judge-name {
            font-weight: 600;
            min-width: 20px;
            font-size: 10px;
        }
        
        .judge-progress {
            font-size: 9px;
            color: #666;
            background: rgba(255,255,255,0.7);
            padding: 1px 4px;
            border-radius: 3px;
        }
        
        .judge-actions {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        
        .judge-btn {
            width: 20px;
            height: 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            transition: all 0.2s;
        }
        
        .judge-btn:hover {
            transform: scale(1.1);
        }
        
        .judge-btn-edit {
            background: #2196f3;
            color: white;
        }
        
        .judge-btn-edit:hover {
            background: #1976d2;
        }
        
        .judge-btn-view {
            background: #6c757d;
            color: white;
        }
        
        .judge-btn-view:hover {
            background: #5a6268;
        }
        
        .judge-btn-exclude {
            background: #dc3545;
            color: white;
        }
        
        .judge-btn-exclude:hover {
            background: #c82333;
        }
        
        .event-card-players {
                    margin-bottom: 15px;
            background: #e8f5e8;
            border-radius: 8px;
            padding: 15px;
            margin-top: 0;
            border: 1px solid #28a745;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.1);
                }
        
        .players-header {
            font-size: 14px;
                    font-weight: bold;
            color: #28a745;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            padding-bottom: 8px;
            border-bottom: 2px solid #28a745;
            align-items: center;
        }
        
        .players-count {
            font-size: 10px;
            color: #666;
        }
        
        .players-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-height: none;
            overflow-y: visible;
        }
        
        .player-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            transition: background-color 0.2s;
        }
        
        .player-item:hover {
            background: #e3f2fd;
            border-color: #bbdefb;
        }
        
        .player-number {
            background: #2196f3;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
            min-width: 25px;
            text-align: center;
        }
        
        .player-name {
            font-size: 11px;
            color: #2c3e50;
            font-weight: 500;
            flex: 1;
            line-height: 1.2;
        }
        
        .player-gender {
            font-size: 9px;
            color: #666;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 500;
            min-width: 30px;
            text-align: center;
        }
        
        .event-card-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .event-card-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.2s;
            flex: 1;
            min-width: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        
        /* 싱글 이벤트 액션 버튼 더 크게 */
        .event-cards-grid.single-event .event-card-btn {
            padding: 12px 16px;
            font-size: 13px;
            min-width: 100px;
            gap: 6px;
        }
        
        .event-card-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .event-card-btn-scores {
            background: #17a2b8;
            color: white;
        }
        
        .event-card-btn-scores:hover {
            background: #138496;
        }
        
        .event-card-btn-aggregation {
            background: #28a745;
            color: white;
        }
        
        .event-card-btn-aggregation:hover {
            background: #218838;
        }
        
        .event-card-btn-awards {
            background: #ffc107;
            color: #212529;
        }
        
        .event-card-btn-players {
            background: #17a2b8;
            color: white;
        }
        
        .event-card-btn-awards:hover {
            background: #e0a800;
        }
        
        .event-card-btn-players:hover {
            background: #138496;
        }
        
        .single-event-view {
            padding: 20px;
            min-height: 400px;
        }
        
        .event-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
        }
        
        .detail-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .detail-content {
            font-size: 14px;
            color: #666;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #2196f3;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976d2;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        /* 진출자 수 설정 모달 스타일 */
        .recall-count-modal {
            text-align: center;
            padding: 20px;
        }
        
        .recall-count-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .recall-count-info {
            margin-bottom: 20px;
        }
        
        .recall-count-info p {
            margin: 10px 0;
            font-size: 14px;
        }
        
        .recall-count-input {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .recall-count-field {
            width: 80px;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            text-align: center;
            font-size: 16px;
        }
        
        .recall-count-unit {
            font-size: 14px;
            color: #666;
        }
        
        .recall-count-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        
        /* 모달 레이아웃 */
        .modal-layout {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        
        .results-section {
            flex: 2;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .recall-count-section {
            flex: 1;
            min-width: 300px;
        }
        
        .results-container {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }
        
        .overall-results {
            margin-bottom: 20px;
        }
        
        .overall-results h5 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 16px;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .results-table th,
        .results-table td {
            border: 1px solid #dee2e6;
            padding: 6px 8px;
            text-align: center;
        }
        
        .results-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .results-table tr.advancing {
            background: #d4edda;
        }
        
        .results-table tr.eliminated {
            background: #f8d7da;
        }
        
        .results-table tr.advancing td {
            color: #155724;
        }
        
        .results-table tr.eliminated td {
            color: #721c24;
        }
        
        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .judges-section {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .judges-section h6 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 14px;
            font-weight: bold;
        }
        
        .judges-section .judges-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .judges-section .judge-item {
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            color: #495057;
            display: inline-block;
        }
        
        /* 다음 라운드 생성 모달 스타일 */
        .next-round-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: flex !important;
            justify-content: center;
            align-items: center;
        }
        
        .next-round-modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .next-round-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }
        
        .next-round-modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .next-round-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .next-round-modal-close:hover {
            color: #000;
        }
        
        .next-round-modal-body {
            padding: 20px;
        }
        
        .advancing-players-list h4 {
            margin-top: 20px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .players-table {
            overflow-x: auto;
        }
        
        .players-table table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .players-table th,
        .players-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        
        .players-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .players-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .player-number-input {
            width: 60px;
            padding: 5px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            text-align: center;
        }
        
        .tie-checkbox {
            transform: scale(1.2);
        }
        
        .couple-count-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        
        .couple-count-section h4 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .couple-count-input {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .couple-count-input label {
            font-weight: bold;
            color: #555;
        }
        
        .couple-count-field {
            width: 80px;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            text-align: center;
            font-size: 16px;
        }
        
        .couple-count-field:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        .couple-count-info {
            color: #666;
            font-size: 14px;
        }
        
        .next-round-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .no-selection {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-selection h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .multi-event-indicator {
            display: inline-block;
            background: #ff9800;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .group-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .group-complete-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .group-complete-btn:hover {
            background: #218838;
        }
        
        .group-complete-btn.completed {
            background: #6c757d;
        }
        
        .group-complete-btn.completed:hover {
            background: #5a6268;
        }
        
        .event-group.completed {
            opacity: 0.5;
            background: #f8f9fa;
        }
        
        .event-group.completed .group-header {
            background: #6c757d;
        }
        
        .list-controls {
            background: #e9ecef;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .list-toggle-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .list-toggle-btn:hover {
            background: #0056b3;
        }
        
        .list-toggle-btn.hidden {
            background: #6c757d;
        }
        
        .list-toggle-btn.hidden:hover {
            background: #5a6268;
        }
        
        .event-group.hidden {
            display: none;
        }
        
        /* 집계 결과 스타일 */
        .aggregation-result {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .aggregation-result h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .aggregation-result h3 {
            color: #34495e;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .event-info {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .event-info p {
            margin: 5px 0;
        }
        
        .rankings-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .rankings-table th {
            background: #3498db;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: bold;
        }
        
        .rankings-table td {
            padding: 10px 12px;
            text-align: center;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .rankings-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .rankings-table tr:hover {
            background: #e8f4f8;
        }
        
        .players-info {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #27ae60;
        }
        
        .players-info ul {
            list-style: none;
            padding: 0;
        }
        
        .players-info li {
            padding: 5px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .players-info li:last-child {
            border-bottom: none;
        }
        
        /* 집계 오류 스타일 */
        .aggregation-error {
            padding: 20px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            margin: 20px 0;
            color: #721c24;
        }
        
        .aggregation-error h2 {
            color: #721c24;
            margin-bottom: 15px;
        }
        
        .aggregation-error p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 왼쪽 이벤트 리스트 패널 -->
        <div class="left-panel">
            <div class="panel-header">
                📋 이벤트 리스트
            </div>
            
            <div class="list-controls">
                <span style="font-size: 12px; color: #666;">리스트 관리</span>
                <button class="list-toggle-btn" onclick="toggleCompletedGroups()" id="toggleCompletedBtn">
                    완료된 이벤트 숨기기
                </button>
            </div>
        
            <?php foreach ($event_groups as $group): ?>
            <div class="event-group" data-group="<?=h($group['group_no'])?>">
                <div class="group-header" onclick="toggleGroup('<?=h($group['group_no'])?>')">
                    <div class="group-info">
                        <div class="group-title">
                            통합이벤트 <?=h($group['group_no'])?>
                            <?php if (count($group['events']) > 1): ?>
                                <span class="multi-event-indicator">멀티</span>
                            <?php endif; ?>
                        </div>
                        <div class="group-subtitle"><?=h($group['group_name'])?></div>
                    </div>
                    <div class="group-actions">
                        <button class="group-complete-btn" onclick="event.stopPropagation(); toggleGroupComplete('<?=h($group['group_no'])?>')" 
                                data-group="<?=h($group['group_no'])?>">
                            완료
                        </button>
                        <span class="group-toggle">▶</span>
                    </div>
                </div>
            
                <div class="event-list" id="group-<?=h($group['group_no'])?>">
                    <?php 
                    // 중복 제거를 위한 배열
                    $unique_events = [];
                    $seen_events = [];
                    
                    foreach ($group['events'] as $event): 
                        $event_key = $event['no'] . '_' . $event['desc'] . '_' . ($event['detail_no'] ?: '');
                        if (!in_array($event_key, $seen_events)) {
                            $unique_events[] = $event;
                            $seen_events[] = $event_key;
                        }
                    endforeach;
                    
                    foreach ($unique_events as $event): 
                    ?>
                    <div class="event-item" 
                         data-event="<?=h($event['detail_no'] ?: $event['no'])?>"
                         data-group="<?=h($group['group_no'])?>"
                         <?php if ($group['is_multi']): ?>
                         onclick="selectEvent('<?=h($group['group_no'])?>', '<?=h($group['group_no'])?>', this)"
                         <?php else: ?>
                         onclick="selectEvent('<?=h($event['detail_no'] ?: $event['no'])?>', '<?=h($group['group_no'])?>', this)"
                         <?php endif; ?>>
                        <div class="event-info">
                            <div class="event-number">
                                <?=h($event['detail_no'] ?: $event['no'])?>
                    </div>
                            <div class="event-desc">
                                <?=h($event['desc'])?>
        </div>
                            <?php if (!empty($event['dance_names'])): ?>
                            <div class="event-dances">
                                댄스: <?=h(implode(', ', $event['dance_names']))?>
                            </div>
                            <?php elseif (!empty($event['dances'])): ?>
                            <div class="event-dances">
                                댄스: <?=h(implode(', ', $event['dances']))?>
                            </div>
                            <?php endif; ?>
    </div>
                        <div class="event-status status-<?=strtolower($event['round'])?>">
                            <?=h($event['round'])?>
                </div>
            </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 오른쪽 메인 패널 -->
        <div class="right-panel">
            
            <div id="right-content">
                <?php if ($view_mode === 'aggregation'): ?>
                <?php if ($aggregation_result): ?>
                <!-- 집계 결과 표시 -->
                <div class="aggregation-result">
                    <h2>📊 집계 결과 - <?=h($aggregation_result['event_info']['desc'] ?? '알 수 없는 이벤트')?></h2>
                    
                    <!-- 디버깅 정보 -->
                    <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;">
                        <strong>디버깅 정보:</strong><br>
                        집계 결과 타입: <?=gettype($aggregation_result)?><br>
                        집계 결과 키: <?=implode(', ', array_keys($aggregation_result))?><br>
                        이벤트 정보: <?=json_encode($aggregation_result['event_info'] ?? '없음')?><br>
                        최종 순위 수: <?=count($aggregation_result['final_rankings'] ?? [])?>
                    </div>
                    
                    <div class="event-info">
                        <h3>이벤트 정보</h3>
                        <p><strong>이벤트 번호:</strong> <?=h($aggregation_result['event_info']['event_no'] ?? '')?></p>
                        <p><strong>라운드:</strong> <?=h($aggregation_result['event_info']['round'] ?? '')?></p>
                        <p><strong>패널:</strong> <?=h($aggregation_result['event_info']['panel'] ?? '')?></p>
                        <p><strong>댄스:</strong> <?=implode(', ', $aggregation_result['event_info']['dances'] ?? [])?></p>
                    </div>
                    
                    <div class="final-rankings">
                        <h3>🏆 최종 순위</h3>
                        <table class="rankings-table">
                            <thead>
                                <tr>
                                    <th>순위</th>
                                    <th>선수 번호</th>
                                    <th>합계</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aggregation_result['final_rankings'] ?? [] as $ranking): ?>
                                <tr>
                                    <td><?=h($ranking['final_rank'])?></td>
                                    <td><?=h($ranking['player_no'])?></td>
                                    <td><?=h($ranking['sum_of_places'])?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="players-info">
                        <h3>👥 참가 선수</h3>
                        <ul>
                            <?php foreach ($aggregation_result['players'] ?? [] as $player): ?>
                            <li>선수 번호: <?=h($player['number'])?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php else: ?>
                <!-- 집계 결과 로드 실패 -->
                <div class="aggregation-error">
                    <h2>❌ 집계 결과 로드 실패</h2>
                    <p><strong>이벤트:</strong> <?=h($view_event_no)?></p>
                    <p><strong>컴피티션 ID:</strong> <?=h($comp_id)?></p>
                    <p>집계 API에서 데이터를 가져올 수 없습니다.</p>
                    <div style="background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px;">
                        <strong>디버깅 정보:</strong><br>
                        URL: <?=h($aggregation_url ?? 'N/A')?><br>
                        데이터 길이: <?=strlen($aggregation_data ?? '')?><br>
                        오류: <?=error_get_last()['message'] ?? '없음'?>
                    </div>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="no-selection">
                    <h3>이벤트를 선택해주세요</h3>
                    <p>왼쪽에서 이벤트를 선택하면 여기에 상세 정보가 표시됩니다.</p>
                <?php endif; ?>
                    <?php if (isset($_GET['debug'])): ?>
                    <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 12px;">
                        <strong>디버그 정보:</strong><br>
                        이벤트 그룹 수: <?=count($event_groups)?><br>
                        첫 번째 그룹: <?=json_encode($event_groups[0] ?? '없음')?>
            </div>
                    <?php endif; ?>
        </div>
            </div>
        </div>
    </div>

    <!-- 선수 관리 모달 -->
    <div class="modal-bg" id="playerModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); align-items:center; justify-content:center; z-index:100;">
        <div class="modal" style="background:#fff; border-radius:10px; padding:2em 2.2em; box-shadow:0 10px 40px #0002; min-width:400px;">
            <div class="modal-title">선수 관리<br><span style="font-size:0.9em;color:#888;">등번호를 입력하세요 (예: 10, 23, 10~18)</span></div>
            <div style="display:flex; gap:0.5em; margin:1em 0;">
                <input type="text" id="playerInput" placeholder="등번호나 범위를 입력하세요" style="font-size:1.1em; padding:0.3em 0.6em; border:1.5px solid #aaa; flex:1;" autocomplete="off">
                <button onclick="addPlayers()" style="background:#007bff; color:white; border:none; padding:0.3em 1em; border-radius:4px; cursor:pointer;">추가</button>
            </div>
            <div id="currentPlayers" style="margin:1em 0; max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:0.5em; border-radius:4px;">
                <div style="font-weight:bold; margin-bottom:0.5em;">현재 선수 목록:</div>
                <div id="playersList"></div>
            </div>
            <div class="modal-btns" style="margin-top:1em; text-align:right;">
                <button type="button" onclick="closePlayerModal()" style="font-size:1.08em; margin-left:0.9em; border-radius:4px; padding:0.29em 1.3em;">닫기</button>
                <button type="button" onclick="savePlayers()" style="font-size:1.08em; margin-left:0.9em; border-radius:4px; padding:0.29em 1.3em; background:#28a745; color:white; border:none;">저장</button>
            </div>
        </div>
    </div>

    <script>
        let selectedEvent = null;
        let selectedGroup = null;
        
        // 전역 그룹 데이터
        const groupData = <?=json_encode($event_groups)?>;
        
        // 디버깅: 51번 그룹 확인
        console.log('GroupData loaded:', groupData.length, 'groups');
        const group51 = groupData.find(g => g.group_no == '51');
        if (group51) {
            console.log('51번 그룹:', group51);
            console.log('51번 그룹 이벤트 수:', group51.events.length);
            } else {
            console.log('51번 그룹을 찾을 수 없습니다.');
        }
        
        // 모든 그룹 번호 확인
        const allGroupNos = groupData.map(g => g.group_no);
        const duplicateGroups = allGroupNos.filter((item, index) => allGroupNos.indexOf(item) !== index);
        if (duplicateGroups.length > 0) {
            console.log('중복된 그룹 번호:', duplicateGroups);
        }
        
        // 52번 그룹 확인
        const group52 = groupData.find(g => g.group_no == '52');
        if (group52) {
            console.log('52번 그룹:', group52);
            } else {
            console.log('52번 그룹을 찾을 수 없습니다.');
            console.log('사용 가능한 그룹들:', groupData.map(g => g.group_no));
            
            // 52번 그룹이 없으면 강제로 추가 (임시 해결책)
            const missingGroup52 = {
                group_no: '52',
                group_name: '프로페셔널 라틴',
                events: [{
                    no: '52',
                    desc: '프로페셔널 라틴',
                    round: 'Final',
                    panel: 'LC',
                    dances: ['6', '7', '8', '9', '10'],
                    detail_no: ''
                }],
                is_multi: false,
                dance_sequence: [
                    {dance: '6', dance_name: 'Cha Cha', type: 'single'},
                    {dance: '7', dance_name: 'Samba', type: 'single'},
                    {dance: '8', dance_name: 'Rumba', type: 'single'},
                    {dance: '9', dance_name: 'Paso Doble', type: 'single'},
                    {dance: '10', dance_name: 'Jive', type: 'single'}
                ]
            };
            groupData.push(missingGroup52);
            console.log('52번 그룹을 강제로 추가했습니다.');
            
            // 52번 그룹을 DOM에 추가
            addMissingGroupToDOM(missingGroup52);
        }
        
        // 50번 이상 그룹들 확인
        const highNumberGroups = groupData.filter(g => parseInt(g.group_no) >= 50);
        console.log('50번 이상 그룹들:', highNumberGroups.map(g => g.group_no));
        let expandedGroups = new Set();
        let completedGroups = new Set();
        let hideCompleted = false;
        let currentEventForPlayerModal = null;
        let currentPlayers = [];
        let disabledJudgesByEvent = {};
        
        
        function toggleCompletedGroups() {
            const toggleBtn = document.getElementById('toggleCompletedBtn');
            hideCompleted = !hideCompleted;
            
            if (hideCompleted) {
                toggleBtn.textContent = '완료된 이벤트 보기';
                toggleBtn.classList.add('hidden');
            } else {
                toggleBtn.textContent = '완료된 이벤트 숨기기';
                toggleBtn.classList.remove('hidden');
            }
            
            updateGroupVisibility();
        }
        
        function updateGroupVisibility() {
            const groups = document.querySelectorAll('.event-group');
            groups.forEach(group => {
                const groupNo = group.dataset.group;
                if (hideCompleted && completedGroups.has(groupNo)) {
                    group.classList.add('hidden');
                } else {
                    group.classList.remove('hidden');
                }
            });
        }
        
        // 페이지 로드 시 완료된 이벤트 상태 확인
        function checkCompletedGroups() {
            const groups = document.querySelectorAll('.event-group');
            groups.forEach(group => {
                const groupNo = group.dataset.group;
                const completeBtn = group.querySelector('.group-complete-btn');
                if (completeBtn && completeBtn.textContent === '완료됨') {
                    completedGroups.add(groupNo);
                    group.classList.add('completed');
                    completeBtn.classList.add('completed');
                }
            });
        }
        
        // 페이지 로드 시 실행
        document.addEventListener('DOMContentLoaded', function() {
            checkCompletedGroups();
        });
        
        function selectEvent(eventId, groupId, element) {
            console.log('selectEvent called:', {eventId, groupId});
            
            // 이전 선택 해제
            document.querySelectorAll('.event-item.selected').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelectorAll('.group-header.selected').forEach(header => {
                header.classList.remove('selected');
            });
            
            // 현재 선택
            element.classList.add('selected');
            element.closest('.event-group').querySelector('.group-header').classList.add('selected');
            
            selectedEvent = eventId;
            selectedGroup = groupId;
            
            console.log('selectedEvent set to:', selectedEvent);
            console.log('selectedGroup set to:', selectedGroup);
            
            // 오른쪽 패널 업데이트
            updateRightPanel(eventId, groupId);
        }
        
        function updateRightPanel(eventId, groupId) {
            console.log('updateRightPanel called:', {eventId, groupId});
            const rightContent = document.getElementById('right-content');
            
            console.log('GroupData:', groupData);
            console.log('Looking for groupId:', groupId);
            
            const group = groupData.find(g => g.group_no == groupId);
            console.log('Found group:', group);
            
            if (!group) {
                console.error('Group not found for groupId:', groupId);
                return;
            }
            
            // eventId가 숫자만 있는 경우 (예: "1"), event_no가 "1-1", "1-2" 등인 이벤트를 찾음
            let event = group.events.find(e => (e.detail_no || e.no) === eventId);
            
            // eventId가 숫자만 있고 event_no가 "숫자-"로 시작하는 경우를 찾음
            if (!event && /^\d+$/.test(eventId)) {
                event = group.events.find(e => e.detail_no && e.detail_no.startsWith(eventId + "-"));
            }
            
            console.log('Found event:', event);
            
            if (!event) {
                console.error('Event not found for eventId:', eventId);
                console.log('Available events:', group.events.map(e => ({ no: e.no, detail_no: e.detail_no, desc: e.desc })));
            return;
        }
        
            const isMultiEvent = group.events.length > 1;
            console.log('isMultiEvent:', isMultiEvent);
            console.log('group.events.length:', group.events.length);
            
            let content = `
                <div class="right-header">
                    <div class="right-title">통합이벤트 ${groupId}</div>
                    <div class="event-info-row">
                        <div class="info-item">
                            <div class="info-label">유형</div>
                            <div class="info-value">${isMultiEvent ? '멀티 이벤트' : '싱글 이벤트'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">이벤트수</div>
                            <div class="info-value">${group.events.length}개</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">패널</div>
                            <div class="info-value">${group.events[0].panel || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">댄스순서</div>
                            <div class="dance-sequence-value" 
                                 onclick="openDanceEditModal('${groupId}')"
                                 title="댄스 순서 수정">
                                ${getDanceSequenceDisplay(group.dance_sequence)}
                                <span class="dance-edit-icon">✏️</span>
                            </div>
                        </div>
                        ${shouldShowRecallCount(group.events[0]) ? `
                        <div class="info-item">
                            <div class="info-label">Recall 수</div>
                            <div class="recall-count-value" 
                                 onclick="openRecallEditModal('${groupId}')"
                                 title="Recall 수 수정">
                                ${group.recall_count || 0}명
                                <span class="recall-edit-icon">✏️</span>
                            </div>
                        </div>
                        ` : ''}
                        ${getNextRoundInfo(group.events[0]) ? `
                        <div class="info-item">
                            <div class="info-label">다음 라운드</div>
                            <div class="info-value next-round-info">
                                ${getNextRoundInfo(group.events[0])}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            if (isMultiEvent) {
                // 멀티 이벤트인 경우 카드 그리드 표시
                content += `
                    <div class="event-cards-container">
                        <div class="event-cards-grid">
                `;
                
                group.events.forEach(evt => {
                    const isSelected = (evt.detail_no || evt.no) === eventId;
                    const statusClass = evt.round.toLowerCase().includes('final') ? 'status-final' : 
                                      evt.round.toLowerCase().includes('semi') ? 'status-semi' : 'status-prelim';
                    
                    // 이벤트 키 생성
                    const eventKey = evt.detail_no || evt.no;
                    
        // PHP에서 미리 계산된 데이터 사용
        let eventJudges = evt.judges || [];
        const eventPlayers = evt.players || [];
        
        // 제외된 심사위원 필터링
        const disabledJudges = disabledJudgesByEvent[evt.detail_no || evt.no] || [];
        eventJudges = eventJudges.filter(judge => !disabledJudges.includes(judge.code));
                    
                    // 이벤트 제목 가져오기
                    const eventTitle = evt.name || evt.title || `이벤트 ${evt.detail_no || evt.no}`;
                    
                    content += `
                        <div class="event-card ${isSelected ? 'selected' : ''}" 
                             data-event="${evt.detail_no || evt.no}"
                             onclick="selectEventFromCard('${evt.detail_no || evt.no}', '${groupId}')">
                            <div class="event-card-body">
                                <!-- 왼쪽: 심사위원 리스트 -->
                                <div class="event-card-left">
                                    <div class="event-card-judges">
                                        <div class="judges-header">
                                            <div class="event-title">
                                                <span class="event-number">${evt.detail_no || evt.no}</span>
                                                ${eventTitle}
                                            </div>
                                            <div class="judges-info">
                                                <span>심사위원 현황</span>
                                                <span class="judges-progress">
                                                    ${eventJudges.filter(j => j.status === 'completed').length}/${eventJudges.length} 완료
                                                </span>
                                            </div>
                                        </div>
                                        <div class="judges-list">
                                            ${eventJudges.map(judge => `
                                                <div class="judge-item" data-judge-code="${judge.code}">
                                                    <div class="judge-info">
                                                        <span class="judge-status waiting" id="judge-status-${judge.code}" data-judge-code="${judge.code}">대기</span>
                                                        <span class="judge-name">${judge.code}</span>
                                                    </div>
                                                    <div class="judge-actions">
                                                        <button class="judge-btn judge-btn-exclude" 
                                                                onclick="event.stopPropagation(); toggleAdjudicator('${evt.detail_no || evt.no}', '${judge.code}')"
                                                                title="이 이벤트에서 심사위원 제외">
                                                            X
                                                        </button>
                                                        <button class="judge-btn judge-btn-edit" 
                                                                onclick="event.stopPropagation(); openJudgeScoring('${evt.detail_no || evt.no}', '${judge.code}')"
                                                                title="채점하기">
                                                            ✏️
                                                        </button>
                                                    </div>
                                                </div>
                                            `).join('')}
        </div>
        </div>
    </div>
                                
                                <!-- 오른쪽: 나머지 정보 -->
                                <div class="event-card-right">
                                    <div class="event-card-title">${evt.desc}</div>
                                    
                                    <div class="event-card-details">
                                        <div class="event-card-detail-row">
                                            <span class="event-card-detail-label">라운드:</span>
                                            <span class="event-card-detail-value">${evt.round}</span>
</div>
                                        <div class="event-card-detail-row">
                                            <span class="event-card-detail-label">댄스:</span>
                                            <span class="event-card-detail-value">${evt.dance_names ? evt.dance_names.join(', ') : (evt.dances ? evt.dances.join(', ') : 'N/A')}</span>
                                        </div>
</div>
                                    
                                    <!-- 출전 선수 등번 -->
                                    <div class="event-card-players">
                                        <div class="players-header">
                                            <span>출전 선수</span>
                                            <span class="players-count">${eventPlayers.length}명</span>
    </div>
                                        <div class="players-list">
                                            ${eventPlayers.map(player => `
                                                <div class="player-item">
                                                    <div class="player-number">${player.number}</div>
                                                    <div class="player-name">${player.name || `선수 ${player.number}`}</div>
                                                    <div class="player-gender">
                                                        ${player.type === 'couple' ? '커플' : '싱글'}
            </div>
            </div>
                                            `).join('')}
            </div>
            </div>
                                    
                                    <div class="event-card-actions">
                                        <button class="event-card-btn event-card-btn-players" onclick="event.stopPropagation(); openPlayerModal('${evt.detail_no || evt.no}')">
                                            👥 선수
                                        </button>
                                        <button class="event-card-btn event-card-btn-scores" onclick="event.stopPropagation(); viewScores('${evt.detail_no || evt.no}')">
                                            📊 점수
                                        </button>
                                        <button class="event-card-btn event-card-btn-aggregation" onclick="event.stopPropagation(); openAggregation('${evt.detail_no || evt.no}')">
                                            ${evt.round && evt.round.toLowerCase().includes('final') && !evt.round.toLowerCase().includes('semi') ? '🏆 결승집계' : '📈 집계'}
                                        </button>
                                        <button class="event-card-btn event-card-btn-awards" onclick="event.stopPropagation(); openAwards('${evt.detail_no || evt.no}')">
                                            🏆 상장
                                        </button>
        </div>
    </div>
    </div>
                        </div>
                    `;
                });
                
                content += `
                        </div>
                    </div>
                `;
    } else {
                // 싱글 이벤트인 경우 멀티 이벤트와 동일한 스타일로 표시
                content += `
                    <div class="event-cards-container">
                        <div class="event-cards-grid single-event">
                            <div class="event-card selected" data-event="${eventId}" onclick="selectEventFromCard('${eventId}', '${groupId}')">
                                <div class="event-card-header">
                                    <div class="event-info-section">
                                        <div class="event-number">${eventId}</div>
                                        <div class="event-name">${event.desc}</div>
                                        <div class="event-round">${event.round}</div>
</div>
</div>
                                <div class="event-card-body">
                                    <div class="event-card-left">
                                        <div class="event-card-judges">
                                            <div class="judges-header">
                                                <span class="event-title">심사위원</span>
                                                <span class="judges-count" id="judges-count-${eventId}">-</span>
</div>
                                            <div class="judges-list" id="judges-list-${eventId}">
                                                <div class="loading">심사위원 정보를 로딩 중입니다...</div>
                                </div>
                            </div>
                                </div>
                                    <div class="event-card-right">
                                        <div class="event-card-players">
                                            <div class="players-header">
                                                <span>선수</span>
                                                <span class="players-count" id="players-count-${eventId}">-</span>
                            </div>
                                            <div class="players-list" id="players-list-${eventId}">
                                                <div class="loading">선수 정보를 로딩 중입니다...</div>
                        </div>
                                        </div>
                                        <div class="event-card-actions">
                                            <button class="event-card-btn event-card-btn-scores" onclick="openJudgeScoring('${eventId}')">
                                                📊 점수
                            </button>
                                            <button class="event-card-btn event-card-btn-aggregation" onclick="openAggregation('${eventId}')" data-event-id="${eventId}">
                                                ${event.round && event.round.toLowerCase().includes('final') && !event.round.toLowerCase().includes('semi') ? '🏆 결승집계' : '📈 집계'}
                            </button>
                                            <button class="event-card-btn event-card-btn-awards" onclick="openAwardModal()">
                                                🏆 상장
                                            </button>
                                            <button class="event-card-btn event-card-btn-players" onclick="openHitModal()">
                                                👥 히트
                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="event-card-footer">
                                    <div class="judge-progress" id="judge-progress-${eventId}">
                                        <div class="progress-text">심사위원 상태 로딩 중...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>
            `;
            }
            
            rightContent.innerHTML = content;
            
            // 싱글 이벤트인 경우 멀티 이벤트와 동일한 방식으로 렌더링
            if (!isMultiEvent) {
                renderEventCardContent(eventId, event);
                // 심사위원 상태 업데이트
                updateJudgeStatus(eventId);
            }
            
            // 실시간 업데이트는 startJudgeStatusMonitoring에서 처리
        }
        
        // 전역 변수들
        let panelMap = <?= json_encode($panel_map) ?>;
        let allAdjudicators = <?= json_encode($adjudicator_dict) ?>;
        let events = <?= json_encode($events) ?>;
        let playersByEvent = {};
        let allPlayers = <?= json_encode($player_dict) ?>;
        let hitsByEvent = {};
        
        // 그룹 토글 함수들 (HTML에서 호출되므로 먼저 정의)
        window.toggleGroup = function(groupNo) {
            const group = document.querySelector(`[data-group="${groupNo}"]`);
            const eventList = document.getElementById(`group-${groupNo}`);
            const toggle = group.querySelector('.group-toggle');
            
            if (expandedGroups.has(groupNo)) {
                eventList.classList.remove('expanded');
                toggle.classList.remove('expanded');
                expandedGroups.delete(groupNo);
            } else {
                eventList.classList.add('expanded');
                toggle.classList.add('expanded');
                expandedGroups.add(groupNo);
            }
        }
        
        window.toggleGroupComplete = function(groupNo) {
            const group = document.querySelector(`[data-group="${groupNo}"]`);
            const completeBtn = group.querySelector('.group-complete-btn');
            
            if (completedGroups.has(groupNo)) {
                // 완료 해제
                group.classList.remove('completed');
                completeBtn.classList.remove('completed');
                completeBtn.textContent = '완료';
                completedGroups.delete(groupNo);
            } else {
                // 완료 처리
                group.classList.add('completed');
                completeBtn.classList.add('completed');
                completeBtn.textContent = '완료됨';
                completedGroups.add(groupNo);
            }
        }
        
        // 심사위원 토글 함수
        function toggleAdjudicator(eventNo, judgeCode) {
            if (!disabledJudgesByEvent[eventNo]) disabledJudgesByEvent[eventNo] = [];
            const arr = disabledJudgesByEvent[eventNo];
            const idx = arr.indexOf(judgeCode);
            if (idx === -1) {
                arr.push(judgeCode);
            } else {
                arr.splice(idx, 1);
            }
            // 심사위원 리스트 다시 렌더링
            const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
            if (currentEvent) {
                renderAdjudicatorList(currentEvent.panel, eventNo);
            }
        }
        
        // 심사위원 채점 패널 열기 함수
        function openJudgeScoring(eventNo, judgeCode) {
            if (judgeCode) {
                // 특정 심사위원의 채점 패널
                alert(`심사위원 ${judgeCode}의 채점 패널을 열겠습니다. (이벤트: ${eventNo})`);
            } else {
                // 이벤트 전체 채점 패널
                alert(`이벤트 ${eventNo}의 채점 패널을 열겠습니다.`);
            }
        }
        
        // 선수 리스트 렌더링 함수
        function renderPlayerList(eventNo) {
            const ul = document.getElementById("player-list");
            if (!ul) return;
            
            let arr = playersByEvent[eventNo] || [];
            let sorted = arr.slice().sort((a, b) => Number(a.number) - Number(b.number));
            ul.innerHTML = "";
            
            if (!sorted.length) {
                ul.innerHTML = "<li style='color:#aaa;'>선수 등번호 없음</li>";
                return;
            }
            
            sorted.forEach((player, idx) => {
                let li = document.createElement("li");
                const playerName = player.name || `선수 ${player.number}`;
                li.innerHTML = `${player.number} - ${playerName} <button class="player-x-btn" onclick="removePlayer('${player.number}')">X</button>`;
                ul.appendChild(li);
            });
        }
        
        // 선수 제거 함수
        function removePlayer(bib) {
            const currentEvent = events.find(ev => (ev.detail_no || ev.no) === selectedEvent);
            if (!currentEvent) return;
            
            const eventNo = currentEvent.detail_no || currentEvent.no;
            if (!playersByEvent[eventNo]) return;
            
            const idx = playersByEvent[eventNo].indexOf(bib);
            if (idx !== -1) {
                playersByEvent[eventNo].splice(idx, 1);
                renderPlayerList(eventNo);
            }
        }
        
        // 현재 이벤트의 선수 데이터 로드
        function loadPlayersForCurrentEvent(eventNo) {
            if (!eventNo) return Promise.resolve();
            
            console.log(`Loading players for event ${eventNo}...`);
            
            // 출전선수 목록 로드
            return fetch(`get_players.php?comp_id=<?=addslashes($comp_id)?>&event_no=${eventNo}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // 선수 데이터 저장 (번호와 이름 모두)
                        playersByEvent[eventNo] = data.players || [];
                        renderPlayerList(eventNo);
                        console.log(`Players loaded for event ${eventNo}:`, data.players);
                    } else {
                        console.error('Failed to load players:', data.message);
                        playersByEvent[eventNo] = [];
                        renderPlayerList(eventNo);
                    }
                })
                .catch(err => {
                    console.error('Error loading players:', err);
                    playersByEvent[eventNo] = [];
                    renderPlayerList(eventNo);
                });
        }
        
        // 댄스 블록 렌더링 함수
        function renderDanceBlock(eventNo) {
            const danceListDiv = document.getElementById('dance-list');
            const progressFill = document.getElementById('dance-progress-fill');
            
            if (!danceListDiv) return;
            
            // 현재 이벤트 찾기
            const event = events.find(ev => (ev.detail_no || ev.no) === eventNo);
            if (!event) return;
            
            let danceNames = [];
            if (event.dances && event.dances.length > 0) {
                // 댄스 코드를 이름으로 변환
                const danceMap = {
                    '6': 'Cha Cha',
                    '7': 'Samba', 
                    '8': 'Rumba',
                    '9': 'Paso Doble',
                    '10': 'Jive',
                    '1': 'Waltz',
                    '2': 'Tango',
                    '3': 'Viennese Waltz',
                    '4': 'Foxtrot',
                    '5': 'Quickstep'
                };
                danceNames = event.dances.map(code => danceMap[code] || code);
            }
            
            if (danceNames.length) {
                danceListDiv.innerHTML = danceNames.map((name, i) => {
                    let className = 'dance-item';
                    if (i === 0) className += ' current';
                    return `<div class="${className}">${i + 1}. ${name}</div>`;
                }).join('');
                
                // 진행률 업데이트 (임시로 0%)
                if (progressFill) {
                    progressFill.style.width = '0%';
                }
            } else {
                danceListDiv.innerHTML = '<div class="dance-item">댄스 정보 없음</div>';
            }
        }
        
        // 히트 데이터 로드 함수
        function fetchHits(eventNo) {
            fetch(`get_hits.php?comp_id=<?=urlencode($comp_id)?>&eventNo=${eventNo}&${Date.now()}`)
                .then(r => {
                    if (!r.ok) {
                        console.warn(`히트 파일 로드 실패: ${r.status} ${r.statusText}`);
                        return {success: false, hits: {}};
                    }
                    return r.json();
                })
                .then(data => {
                    if (data.success && data.hits) {
                        hitsByEvent[eventNo] = data.hits;
                        renderHits(eventNo);
                        console.log(`Hits loaded for event ${eventNo}:`, data.hits);
                    } else {
                        console.warn('히트 데이터 로드 실패:', data.error || '알 수 없는 오류');
                        hitsByEvent[eventNo] = {};
                        renderHits(eventNo);
                    }
                })
                .catch(err => {
                    console.error('Error loading hits:', err);
                    hitsByEvent[eventNo] = {};
                    renderHits(eventNo);
                });
        }
        
        // 히트 정보 렌더링 함수 (히트 블록용 - 숨김 처리)
        function renderHits(eventNo) {
            const hitBlock = document.getElementById('hit-block');
            if (!hitBlock) return;
            
            // 히트 블록은 항상 숨김 (모달로만 표시)
            hitBlock.style.display = 'none';
        }
        
        // 히트 모달 열기 함수
        function openHitModal() {
            const currentEvent = events.find(ev => (ev.detail_no || ev.no) === selectedEvent);
            if (!currentEvent) return;
            
            const eventNo = currentEvent.detail_no || currentEvent.no;
            
            // 현재 메모리의 히트 데이터 먼저 표시
            document.getElementById('hitModalBody').innerHTML = buildHitHtml(eventNo);
            document.getElementById('hitModalBg').style.display = 'flex';
            
            // 백그라운드에서 최신 저장본 불러오기 시도
            fetchHits(eventNo);
            // 로드 완료 후 다시 표시
            setTimeout(() => {
                document.getElementById('hitModalBody').innerHTML = buildHitHtml(eventNo);
            }, 200);
        }
        
        // 히트 모달 닫기 함수
        function closeHitModal() {
            document.getElementById('hitModalBg').style.display = 'none';
        }
        
        // 히트 HTML 생성 함수
        function buildHitHtml(eventNo) {
            const hits = hitsByEvent[eventNo] || {};
            const hitKeys = Object.keys(hits).sort((a, b) => Number(a) - Number(b));
            
            if (hitKeys.length === 0) {
                return '<div class="empty">히트 정보가 없습니다.</div>';
            }
            
            let html = '';
            hitKeys.forEach(hitNo => {
                const players = hits[hitNo] || [];
                html += `<div class="modal-hit-group">`;
                html += `<h4>히트 ${hitNo}</h4>`;
                html += `<div class="modal-hit-players">`;
                players.forEach(player => {
                    const playerName = (allPlayers && allPlayers[player]) || `선수 ${player}`;
                    html += `<div class="modal-player-item">${player} - ${playerName}</div>`;
                });
                html += `</div></div>`;
            });
            
            return html;
        }
        
        // 히트 인쇄 함수
        function printHits() {
            const currentEvent = events.find(ev => (ev.detail_no || ev.no) === selectedEvent);
            if (!currentEvent) return;
            
            const eventNo = currentEvent.detail_no || currentEvent.no;
            const hits = hitsByEvent[eventNo] || {};
            const hitKeys = Object.keys(hits);
            
            if (!hitKeys.length) {
                alert('인쇄할 히트가 없습니다.');
                return;
            }
            
            // 인쇄용 새 창 열기
            const printWindow = window.open('', '_blank');
            let printContent = `
                <html>
                <head>
                    <title>히트 정보 - 이벤트 ${eventNo}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .hit-group { margin-bottom: 20px; }
                        .hit-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
                        .player-item { margin: 5px 0; }
                    </style>
                </head>
                <body>
                    <h2>이벤트 ${eventNo} - 히트 정보</h2>
            `;
            
            hitKeys.sort((a, b) => Number(a) - Number(b)).forEach(hitNo => {
                const players = hits[hitNo] || [];
                printContent += `<div class="hit-group">`;
                printContent += `<div class="hit-title">히트 ${hitNo}</div>`;
                players.forEach(player => {
                    const playerName = (allPlayers && allPlayers[player]) || `선수 ${player}`;
                    printContent += `<div class="player-item">${player} - ${playerName}</div>`;
                });
                printContent += `</div>`;
            });
            
            printContent += `</body></html>`;
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }
        
        // 상장 모달 열기 함수
        function openAwardModal() {
            document.getElementById('awardModalBg').style.display = 'flex';
        }
        
        // 상장 모달 닫기 함수
        function closeAwardModal() {
            document.getElementById('awardModalBg').style.display = 'none';
            document.getElementById('awardPreview').style.display = 'none';
            document.getElementById('printAwardBtn').style.display = 'none';
        }
        
        // 상장 생성 함수
        function generateAward(type) {
            const currentEvent = events.find(ev => (ev.detail_no || ev.no) === selectedEvent);
            if (!currentEvent) return;
            
            const eventNo = currentEvent.detail_no || currentEvent.no;
            const eventName = currentEvent.desc || `이벤트 ${eventNo}`;
            const eventDate = new Date().toLocaleDateString('ko-KR');
            
            let awardTitle = '';
            let awardColor = '';
            
            switch(type) {
                case '1st':
                    awardTitle = '1위';
                    awardColor = '#FFD700';
                    break;
                case '2nd':
                    awardTitle = '2위';
                    awardColor = '#C0C0C0';
                    break;
                case '3rd':
                    awardTitle = '3위';
                    awardColor = '#CD7F32';
                    break;
                case 'finalist':
                    awardTitle = '결승 진출';
                    awardColor = '#4CAF50';
                    break;
                case 'participation':
                    awardTitle = '참가상';
                    awardColor = '#2196F3';
                    break;
            }
            
            const awardHtml = `
                <div class="award-certificate" style="
                    border: 3px solid ${awardColor};
                    padding: 30px;
                    text-align: center;
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                    font-family: 'Times New Roman', serif;
                    max-width: 600px;
                    margin: 0 auto;
                ">
                    <h1 style="color: ${awardColor}; font-size: 2.5em; margin-bottom: 20px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                        상장
                    </h1>
                    <div style="font-size: 1.2em; margin: 20px 0; color: #333;">
                        위 선수는
                    </div>
                    <div style="font-size: 1.5em; font-weight: bold; margin: 20px 0; color: #2c3e50;">
                        ${eventName}
                    </div>
                    <div style="font-size: 1.2em; margin: 20px 0; color: #333;">
                        에서
                    </div>
                    <div style="font-size: 2em; font-weight: bold; margin: 20px 0; color: ${awardColor}; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
                        ${awardTitle}
                    </div>
                    <div style="font-size: 1.2em; margin: 20px 0; color: #333;">
                        를 수상하므로 이에 상장을 수여합니다.
                    </div>
                    <div style="margin-top: 40px; font-size: 1.1em; color: #666;">
                        ${eventDate}
                    </div>
                    <div style="margin-top: 20px; font-size: 1.1em; color: #666;">
                        대회 주최측
                    </div>
                </div>
            `;
            
            document.getElementById('awardContent').innerHTML = awardHtml;
            document.getElementById('awardPreview').style.display = 'block';
            document.getElementById('printAwardBtn').style.display = 'inline-block';
        }
        
        // 상장 인쇄 함수
        function printAward() {
            const printWindow = window.open('', '_blank');
            const awardContent = document.getElementById('awardContent').innerHTML;
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>상장</title>
                    <style>
                        body { margin: 0; padding: 20px; }
                        @media print {
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    ${awardContent}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // 이벤트 카드 내용 렌더링 함수 (싱글 이벤트용)
        function renderEventCardContent(eventId, event) {
            // 심사위원 정보 렌더링
            renderEventCardJudges(eventId, event.panel);
            // 선수 정보 렌더링
            renderEventCardPlayers(eventId);
        }
        
        // 이벤트 카드 심사위원 렌더링
        function renderEventCardJudges(eventId, panelCode) {
            const judgesList = document.getElementById(`judges-list-${eventId}`);
            const judgesCount = document.getElementById(`judges-count-${eventId}`);
            
            if (!judgesList) return;
            
            const judgeLinks = panelMap.filter(m => (m.panel_code||"").toUpperCase() === (panelCode||"").toUpperCase());
            const judgeArr = judgeLinks.map(m => allAdjudicators[m.adj_code]).filter(j=>j);
            
            if (!panelCode || judgeArr.length === 0) {
                judgesList.innerHTML = '<div class="empty">심사위원이 없습니다</div>';
                if (judgesCount) judgesCount.textContent = '0';
                return;
            }
            
            if (judgesCount) judgesCount.textContent = judgeArr.length;
            
            const disabled = disabledJudgesByEvent[eventId] || [];
            let html = '';
            judgeArr.forEach((j, i) => {
                const isDisabled = disabled.includes(j.code);
                html += `
                    <div class="judge-item ${isDisabled ? 'disabled' : ''}">
                        <span class="judge-code">${j.code}</span>
                        <span class="judge-name">${j.name || 'Unknown'}</span>
                        <span class="judge-nation">${j.nation || '-'}</span>
                        <span class="judge-status waiting" id="judge-status-${j.code}" data-judge-code="${j.code}">대기</span>
                        <button class="judge-btn judge-btn-edit" onclick="openJudgeScoring('${eventId}', '${j.code}')" title="채점하기">✏️</button>
                        <button class="judge-btn-exclude" onclick="toggleAdjudicator('${eventId}','${j.code}')" ${isDisabled ? 'disabled' : ''}>X</button>
                    </div>
                `;
            });
            judgesList.innerHTML = html;
        }
        
        // 이벤트 카드 선수 렌더링
        function renderEventCardPlayers(eventId) {
            const playersList = document.getElementById(`players-list-${eventId}`);
            const playersCount = document.getElementById(`players-count-${eventId}`);
            
            if (!playersList) return;
            
            // 선수 데이터 로드
            loadPlayersForCurrentEvent(eventId).then(() => {
                const players = playersByEvent[eventId] || [];
                
                if (playersCount) playersCount.textContent = players.length;
                
                if (players.length === 0) {
                    playersList.innerHTML = '<div class="empty">선수가 없습니다</div>';
                    return;
                }
                
                let html = '';
                players.forEach((player, i) => {
                    const playerName = player.name || `선수 ${player.number}`;
                    html += `
                        <div class="player-item">
                            <span class="player-number">${player.number}</span>
                            <span class="player-name">${playerName}</span>
                        </div>
                    `;
                });
                playersList.innerHTML = html;
            });
        }
        
        // 심사위원 리스트 렌더링 함수 (기존 테이블용)
        function renderAdjudicatorList(panelCode, eventNo) {
            const judgeLinks = panelMap.filter(m => (m.panel_code||"").toUpperCase() === (panelCode||"").toUpperCase());
            const judgeArr = judgeLinks.map(m => allAdjudicators[m.adj_code]).filter(j=>j);
            const tbody = document.getElementById("adjudicator-list");
            const empty = document.getElementById("judge-empty");
            
            if (!tbody) return;
            
            tbody.innerHTML = "";
            if (!panelCode || judgeArr.length === 0) {
                if (empty) empty.style.display = "";
                return;
            }
            if (empty) empty.style.display = "none";
            
            const disabled = disabledJudgesByEvent[eventNo] || [];
            judgeArr.forEach((j, i) => {
                const isDisabled = disabled.includes(j.code);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${i + 1}</td>
                    <td>${j.code}</td>
                    <td>${j.name || 'Unknown'}</td>
                    <td>${j.nation || '-'}</td>
                    <td>
                        <span class="judge-status waiting" id="judge-status-${j.code}" data-judge-code="${j.code}">대기</span>
                    </td>
                    <td>
                        <div class="adjudicator-buttons">
                            <button class="adjudicator-x-btn" onclick="toggleAdjudicator('${eventNo}','${j.code}')" title="이 이벤트에서 심사위원 제외" ${isDisabled ? 'disabled' : ''}>X</button>
                            <button class="judge-scoring-btn" onclick="openJudgeScoring('${eventNo}','${j.code}')" title="심사위원 채점 패널 열기" data-judge-code="${j.code}">✏️</button>
                        </div>
                    </td>`;
                tbody.appendChild(tr);
            });
        }
        
        function selectEventFromCard(eventId, groupId) {
            // 왼쪽 패널의 해당 이벤트도 선택 상태로 업데이트
            const eventElement = document.querySelector(`[data-event="${eventId}"]`);
            if (eventElement) {
                // 이전 선택 해제
                document.querySelectorAll('.event-item.selected').forEach(item => {
                    item.classList.remove('selected');
                });
                document.querySelectorAll('.group-header.selected').forEach(header => {
                    header.classList.remove('selected');
                });
                
                // 현재 선택
                eventElement.classList.add('selected');
                eventElement.closest('.event-group').querySelector('.group-header').classList.add('selected');
                
                selectedEvent = eventId;
                selectedGroup = groupId;
                
                // 오른쪽 패널 업데이트
                updateRightPanel(eventId, groupId);
            }
        }
        
        function openJudgeScoring(eventId, judgeCode) {
            if (!eventId || !judgeCode) {
                alert('이벤트와 심사위원을 선택해주세요.');
                return;
            }
            
            // 디버그: 전달받은 매개변수 확인
            console.log('openJudgeScoring called:', {
                eventId: eventId,
                judgeCode: judgeCode,
                type: typeof judgeCode
            });
            
            // 매개변수 유효성 검사
            if (!eventId || eventId === 'undefined' || eventId === 'null') {
                alert('이벤트 ID가 올바르지 않습니다: ' + eventId);
                return;
            }
            if (!judgeCode || judgeCode === 'undefined' || judgeCode === 'null') {
                alert('심사위원 코드가 올바르지 않습니다: ' + judgeCode);
                return;
            }
            
            const compId = '<?=$comp_id?>';
            
            // 여러 URL 옵션 시도
            const urls = [
                `judge_scoring.php?comp_id=${compId}&event_no=${eventId}&judge_code=${judgeCode}&admin_mode=1`,
                `./judge_scoring.php?comp_id=${compId}&event_no=${eventId}&judge_code=${judgeCode}&admin_mode=1`,
                `https://www.danceoffice.net/comp/judge_scoring.php?comp_id=${compId}&event_no=${eventId}&judge_code=${judgeCode}&admin_mode=1`
            ];
            
            // 첫 번째 URL 시도
            const url = urls[0];
            console.log('Opening URL:', url);
            console.log('Full URL details:', {
                compId: compId,
                eventId: eventId,
                judgeCode: judgeCode,
                baseUrl: window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')),
                finalUrl: url
            });
            
            // URL이 올바른지 확인
            if (url.includes('undefined') || url.includes('null')) {
                alert('URL에 잘못된 값이 포함되어 있습니다: ' + url);
                return;
            }
            
            // 새 창 열기
            const newWindow = window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
            
            // 새 창이 제대로 열렸는지 확인
            if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                console.error('팝업이 차단되었습니다. 팝업 차단을 해제해주세요.');
                alert('팝업이 차단되었습니다. 브라우저 설정에서 팝업을 허용해주세요.');
            } else {
                // 2초 후 페이지 로드 확인
                setTimeout(() => {
                    try {
                        if (newWindow.location.href === 'about:blank' || newWindow.location.href.includes('danceoffice.net')) {
                            console.log('채점 페이지가 정상적으로 로드되었습니다.');
                        } else {
                            console.warn('예상과 다른 페이지가 로드되었습니다:', newWindow.location.href);
                        }
                    } catch (e) {
                        console.log('크로스 오리진 정책으로 인해 페이지 내용을 확인할 수 없습니다.');
                    }
                }, 2000);
            }
        }
        
        function viewJudgeScores(eventId, judgeCode) {
            if (!eventId || !judgeCode) {
                alert('이벤트와 심사위원을 선택해주세요.');
            return;
            }
            
            // 점수 보기 (기존 viewScores 함수 활용)
            viewScores(eventId);
        }
        
        function viewScores(eventId) {
            if (!eventId) {
                alert('이벤트를 선택해주세요.');
                return;
            }
            
            const compId = '<?=$comp_id?>';
            const url = `https://www.danceoffice.net/comp/live_panel.php?comp_id=${compId}&view=scores&event_no=${eventId}`;
            
            console.log('Opening scores URL:', url);
            
            // 새 창에서 점수 보기
            window.open(url, '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
        }
        
        function openAggregation(eventId) {
            // eventId가 없으면 현재 선택된 이벤트 사용
            if (!eventId || eventId === 'undefined') {
                eventId = selectedEvent;
                if (!eventId) {
                    alert('이벤트를 선택해주세요.');
                    return;
                }
            }
            
            // 현재 이벤트 정보 찾기
            let currentEvent = null;
            for (let group of groupData) {
                currentEvent = group.events.find(e => (e.detail_no || e.no) === eventId);
                if (currentEvent) break;
            }
            
            if (!currentEvent) {
                alert('이벤트 정보를 찾을 수 없습니다.');
                return;
            }
            
            // 이벤트가 결승전인지 확인
            const isFinalRound = currentEvent.round && 
                                currentEvent.round.toLowerCase().includes('final') && 
                                !currentEvent.round.toLowerCase().includes('semi');
            
            if (isFinalRound) {
                // 결승전: 스케이팅 시스템으로 최종 순위 계산
                openFinalAggregation(eventId);
            } else {
                // 예선/준결승: 리콜 시스템으로 다음 라운드 진출자 선정
                openAggregationModal(eventId);
            }
        }
        
        // 결승전 집계 함수 (스케이팅 시스템)
        function openFinalAggregation(eventId) {
            if (!eventId) {
                alert('이벤트를 선택해주세요.');
                return;
            }
            
            const compId = '<?=$comp_id?>';
            
            console.log('결승 집계 시작:', {eventId, compId});
            
            // 올바른 서버 주소를 사용하여 집계 API 호출
            const currentProtocol = window.location.protocol;
            const currentHost = window.location.host;
            const baseUrl = `${currentProtocol}//${currentHost}`;
            
            // Final Aggregation API를 호출하여 결승 결과 생성
            const apiUrl = `${baseUrl}/comp/final_aggregation_api.php?comp_id=${compId}&event_no=${eventId}`;
            
            console.log('결승 집계 API 호출:', apiUrl);
            
            // 로딩 인디케이터 표시
            const loadingMsg = document.createElement('div');
            loadingMsg.innerHTML = `
                <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                     background: white; padding: 30px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                     z-index: 10000; font-family: 'Noto Sans KR'; text-align: center;">
                    <div style="font-size: 1.2em; margin-bottom: 15px; color: #333;">🏆 결승 결과 집계 중...</div>
                    <div style="font-size: 0.9em; color: #666;">스케이팅 시스템으로 최종 순위를 계산하고 있습니다.</div>
                    <div style="margin-top: 15px;">
                        <div style="width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; 
                             border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                    </div>
                </div>
                <style>
                    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                </style>
            `;
            document.body.appendChild(loadingMsg);
            
            fetch(apiUrl)
                .then(response => {
                    console.log('API 응답 상태:', response.status);
                    console.log('API 응답 헤더:', response.headers);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    // 응답을 텍스트로 먼저 읽어서 확인
                    return response.text().then(text => {
                        console.log('API 응답 텍스트 (처음 500자):', text.substring(0, 500));
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON 파싱 오류:', e);
                            console.error('원본 응답:', text);
                            throw new Error(`JSON 파싱 오류: ${e.message}`);
                        }
                    });
                })
                .then(data => {
                    console.log('파싱된 데이터:', data);
                    
                    // 로딩 인디케이터 제거
                    if (loadingMsg.parentNode) {
                        document.body.removeChild(loadingMsg);
                    }
                    
                    if (data.success && data.event_info && data.final_rankings) {
                        // 성공시 API를 통해 결과 HTML 가져오기
                        const resultApiUrl = `./get_event_result.php?comp_id=${compId}&event_no=${eventId}`;
                        console.log('결승 집계 성공, API를 통해 결과 가져오기:', resultApiUrl);
                        
                        fetch(resultApiUrl)
                            .then(response => {
                                console.log('Result API response status:', response.status);
                                if (response.ok) {
                                    return response.json();
                                } else {
                                    throw new Error(`Result API error: ${response.status}`);
                                }
                            })
                            .then(resultData => {
                                console.log('Result API response:', resultData);
                                if (resultData.success && resultData.html) {
                                    // 새 창에서 결과 표시
                                    const newWindow = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes,resizable=yes');
                                    if (newWindow) {
                                        newWindow.document.write(resultData.html);
                                        newWindow.document.close();
                                        console.log('결승 결과 창이 열렸습니다.');
                                    } else {
                                        // 팝업이 차단된 경우 현재 페이지에 결과 표시
                                        showAggregationResult(data, eventId);
                                    }
                                } else {
                                    console.error('Result API failed:', resultData.message);
                                    showAggregationResult(data, eventId);
                                }
                            })
                            .catch(error => {
                                console.error('Result API error:', error);
                                showAggregationResult(data, eventId);
                            });
                    } else {
                        console.error('결승 집계 실패:', data);
                        alert(`결승 집계 처리 중 오류가 발생했습니다: ${data.error || data.message || '알 수 없는 오류'}`);
                    }
                })
                .catch(error => {
                    // 로딩 인디케이터 제거
                    if (loadingMsg.parentNode) {
                        document.body.removeChild(loadingMsg);
                    }
                    console.error('결승 집계 API 호출 실패:', error);
                    alert(`결승 집계 처리 중 오류가 발생했습니다: ${error.message}`);
                });
        }
        
        // 간단한 집계 결과 표시 함수 (메인 화면용)
        function showSimpleAggregation(eventId) {
            const rightContent = document.getElementById('right-content');
            
            // 현재 이벤트 정보 찾기
            let currentEvent = null;
            if (events && events.length > 0) {
                currentEvent = events.find(e => (e.detail_no || e.no) == eventId);
            }
            
            if (!currentEvent) {
                rightContent.innerHTML = `
                    <div class="aggregation-error">
                        <h2>❌ 이벤트를 찾을 수 없습니다</h2>
                        <p>이벤트 ID: ${eventId}</p>
                    </div>
                `;
                return;
            }
            
            // 간단한 집계 결과 HTML 생성
            const resultHtml = `
                <div class="aggregation-result">
                    <h2>🏆 집계 결과 - ${currentEvent.desc || '알 수 없는 이벤트'}</h2>
                    
                    <div class="event-info">
                        <h3>이벤트 정보</h3>
                        <p><strong>이벤트 번호:</strong> ${eventId}</p>
                        <p><strong>라운드:</strong> ${currentEvent.round || 'Final'}</p>
                        <p><strong>패널:</strong> ${currentEvent.panel || 'A'}</p>
                        <p><strong>댄스:</strong> ${(currentEvent.dances || []).join(', ')}</p>
                    </div>
                    
                    <div class="final-rankings">
                        <h3>📊 최종 순위</h3>
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>순위</th>
                                    <th>번호</th>
                                    <th>선수명</th>
                                    <th>SUM of Places</th>
                                    <th>Place Skating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px; color: #666;">
                                        집계 데이터를 로딩 중입니다...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="next-round-section">
                        <h3>🎯 다음 라운드 진출자</h3>
                        <p>집계 완료 후 진출자가 표시됩니다.</p>
                    </div>
                </div>
            `;
            
            rightContent.innerHTML = resultHtml;
        }
        
        // 모달 내에서 간단한 집계 결과 표시 함수
        function showSimpleAggregationInModal(eventId, recallCount = null) {
            console.log('Showing aggregation in modal for eventId:', eventId, 'recallCount:', recallCount);
            
            // 로딩 숨기고 콘텐츠 표시
            document.getElementById('aggregationLoading').style.display = 'none';
            document.getElementById('aggregationContent').style.display = 'block';
            document.getElementById('printAggregationBtn').style.display = 'inline-block';
            document.getElementById('saveAggregationBtn').style.display = 'inline-block';
            document.getElementById('nextRoundBtn').style.display = 'inline-block';
            
            const content = document.getElementById('aggregationContent');
            
            // 현재 이벤트 정보 찾기
            let currentEvent = null;
            if (events && events.length > 0) {
                currentEvent = events.find(e => (e.detail_no || e.no) == eventId);
            }
            
            if (!currentEvent) {
                content.innerHTML = `
                    <div class="error-message" style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24;">
                        <h3>❌ 이벤트를 찾을 수 없습니다</h3>
                        <p>이벤트 ID: ${eventId}</p>
                    </div>
                `;
                return;
            }
            
            // 진출자 수 설정 (파라미터로 받은 값 우선, 없으면 이벤트의 recall_count 사용)
            const finalRecallCount = recallCount !== null ? recallCount : (currentEvent?.recall_count || 0);
            console.log('showSimpleAggregationInModal - finalRecallCount:', finalRecallCount, 'recallCount param:', recallCount);
            
            // 리콜 계산 실행
            calculateRecallResults(eventId, currentEvent, content, finalRecallCount);
        }
        
        // 리콜 결과 계산 함수
        function calculateRecallResults(eventId, currentEvent, content, recallCount = null) {
            console.log('Calculating recall results for eventId:', eventId, 'recallCount:', recallCount);
            
            // 리콜 데이터 로드
            fetch(`get_recall_data.php?comp_id=<?=$comp_id?>&event_no=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 진출자 수가 설정되어 있으면 데이터에 적용
                        if (recallCount !== null) {
                            data.recall_count_from_file = recallCount;
                            console.log('Applied recallCount to data:', recallCount);
                        }
                        displayRecallResults(data, currentEvent, content);
                    } else {
                        displayRecallError(data.error, content);
                    }
                })
                .catch(error => {
                    console.error('리콜 데이터 로드 실패:', error);
                    displayRecallError('리콜 데이터를 로드할 수 없습니다: ' + error.message, content);
                });
        }
        
        // 리콜 결과 표시 함수
        function displayRecallResults(data, currentEvent, content) {
            console.log('Displaying recall results:', data);
            console.log('recall_count_from_file:', data.recall_count_from_file);
            
            // 전체 참가자 수를 전역 변수로 저장
            window.totalParticipants = data.total_participants || 0;
            console.log('API 응답 데이터:', data);
            console.log('data.total_participants:', data.total_participants);
            console.log('설정된 window.totalParticipants:', window.totalParticipants);
            
            // DanceSportLive 스타일 HTML 생성
            let html = `
                <div class="dancesport-container aggregation-results">
                    <div class="dancesport-header">
                        <h1><center>${data.competition_info?.title || '2025 제9회 용인특례시 시민일보배'}</center></h1>
                    </div>
                    <div class="dancesport-content">
                        <table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
                            <tr>
                                <td width='50%' valign='top' style='font-weight:bold;'>${data.competition_info?.date || '2025년 9월 13일'}</td>
                                <td width='40%' align='right'></td>
                            </tr>
                        </table>
                        <table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
                            <tr>
                                <td style='font-weight:bold; padding-top:1em;' align='left'>${currentEvent.desc || '집계 결과'} - ${currentEvent.round || 'Semi'}</td>
                                <td style='font-weight:bold; padding-top:1em;' align='right'>${data.recall_count_from_file || data.advancing_players.length}커플이 다음라운에 진출합니다</td>
                            </tr>
                        </table>
                        <table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial; margin-top:0.5em;'>
                            <tr>
                                <td style='font-size:0.9em; color:#666;' align='left'>
                                    <strong>리콜 정보:</strong> 
                                    파일 리콜 수: ${data.recall_count_from_file || 0}명 | 
                                    심사위원 수: ${data.total_judges}명 | 
                                    리콜 기준: ${data.recall_threshold}명 이상
                                </td>
                            </tr>
                        </table>
            `;
            
            // 요약 테이블 (등위 순서) - 먼저 표시
            html += `
                <table cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial; margin-top:1em; border-bottom:thin;'>
                    <tr>
                        <th width='2%' align='center' style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'> </th>
                        <th width='2%' align='center' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>Marks</th>
                        <th width='2%' align='center' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>Tag</th>
                        <th width='40%' align='left' style='margin-top:2em; padding-left:2em; color:#FFF; background-color:#333'>Competitor Name(s)</th>
                        <th width='10%' align='left' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333; padding-left:3em; padding-right:3em;'>From</th>
                    </tr>
            `;
            
            // 사용자가 설정한 진출자 수 기준으로 진출자 결정
            const recallCount = data.recall_count_from_file || data.advancing_players?.length || 0;
            console.log('Using recallCount for display:', recallCount);
            
            // 모든 선수를 등위 순서로 표시 (상위 N커플 진출 기준으로 구분)
            data.player_recalls.forEach((player, index) => {
                const isAdvancing = index < recallCount; // 상위 N명이 진출
                const bgColor = isAdvancing ? '#e8f5e8' : '#f5f5f5'; // 진출자는 연한 초록, 탈락자는 연한 회색
                const borderStyle = isAdvancing ? 'border-left: 4px solid #28a745;' : 'border-left: 4px solid #dc3545;';
                const statusText = isAdvancing ? '✅ 진출' : '';
                
                html += `
                    <tr style='font-weight:bold;'>
                        <td width='2%' align='center' style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}; ${borderStyle}'>${index + 1}</td>
                        <td width='2%' align='center' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}'>(${player.recall_count})</td>
                        <td width='2%' align='center' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}'>${player.player_number}</td>
                        <td width='40%' align='left' style='margin-top:2em; padding-left:2em; color:#000; background-color:${bgColor}'>${player.player_name} <span style='font-size:0.8em; color:${isAdvancing ? '#28a745' : '#dc3545'};'>${statusText}</span></td>
                        <td width='10%' align='left' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}; padding-left:3em; padding-right:3em;'>${currentEvent.desc || 'Previous Round'}</td>
                    </tr>
                `;
            });
            
            html += `
                </table>
            `;
            
            // 댄스별 상세 리콜 테이블 (DanceSportLive 스타일) - 나중에 표시
            if (data.dance_recalls) {
                Object.keys(data.dance_recalls).forEach(danceCode => {
                    const danceData = data.dance_recalls[danceCode];
                    html += `
                        <table cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial; margin-top:1em; border-bottom:thin;'>
                            <tr>
                                <th width='100%' colspan='${data.total_judges + 3}' style='font-size:1.5em; padding-top:0.5em; padding-left:0.5em; font-weight:bold; color:#FFF; background-color:#333' align='left'>${danceData.dance_name}</th>
                            </tr>
                            <tr>
                                <th width='3%' align='center' style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>Tag</th>
                                <th width='20%' align='left' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>Competitor Name(s)</th>
                    `;
                    
                    // 심사위원 컬럼 헤더
                    data.judges.forEach((judge, index) => {
                        const letter = String.fromCharCode(65 + index); // A, B, C, ...
                        html += `
                            <th width='2.5%' align='center' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>${letter}</th>
                        `;
                    });
                    
                    html += `
                                <th width='3%' align='center' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333;'>Mark</th>     
                            </tr>
                    `;
                    
                    // 선수별 리콜 결과 표시 (등번호 오름차순 정렬, 배경색 통일)
                    const recallCount = data.recall_count_from_file || data.advancing_players?.length || 0;
                    
                    // 등번호로 정렬 (오름차순)
                    const sortedPlayers = danceData.player_recalls.sort((a, b) => {
                        const numA = parseInt(a.player_number) || 0;
                        const numB = parseInt(b.player_number) || 0;
                        return numA - numB;
                    });
                    
                    sortedPlayers.forEach((player, index) => {
                        const bgColor = '#eee'; // 모든 행에 동일한 배경색
                        const rowStyle = `font-weight:bold; background-color:${bgColor}`;
                        
                        html += `
                            <tr style='${rowStyle}'>
                                <td align='center' style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}'>${player.player_number}</td>
                                <td align='left' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}'>${player.player_name}</td>
                        `;
                        
                        // 각 심사위원별 리콜 여부 표시
                        data.judges.forEach(judge => {
                            const recalled = player.judges.includes(judge);
                            const mark = recalled ? '1' : '0';
                            html += `
                                <td align='center' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}'>${mark}</td>
                            `;
                        });
                        
                        html += `
                                <td width='4%' align='center' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}; padding-left:1em; padding-right:0.5em;'>${player.recall_count}</td>     
                            </tr>
                        `;
                    });
                    
                    html += `</table>`;
                });
            }
            
            // 심사위원 명단 추가 (저작권 문구 바로 위에)
            let judgesSection = '';
            if (data.judges && data.judges.length > 0) {
                // 심사위원 코드와 이름을 매핑
                const judgeMap = data.judges.map((judgeCode, index) => ({
                    code: judgeCode,
                    name: data.judge_names && data.judge_names[index] ? data.judge_names[index] : String.fromCharCode(65 + index)
                }));
                
                // 심사위원 코드 순서로 정렬
                judgeMap.sort((a, b) => parseInt(a.code) - parseInt(b.code));
                
                // 4명씩 한 줄로 나누어 표시
                let judgesHtml = '';
                for (let i = 0; i < judgeMap.length; i += 4) {
                    judgesHtml += '<tr>';
                    for (let j = 0; j < 4; j++) {
                        const judgeIndex = i + j;
                        if (judgeIndex < judgeMap.length) {
                            const judge = judgeMap[judgeIndex];
                            const letter = String.fromCharCode(65 + judgeIndex);
                            judgesHtml += `
                                <td align='left' width='2%' style='padding-left:2em;'><small>${letter}.</small></td>
                                <td align='left'><small>${judge.name}</small></td>
                            `;
                        } else {
                            judgesHtml += `
                                <td align='left' width='2%'> </td>
                                <td align='left'> </td>
                            `;
                        }
                    }
                    judgesHtml += '</tr>';
                }
                
                judgesSection = `
                    <table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
                        <tr><td style='font-weight:bold; padding-top:1em; padding-bottom:0.5em;' align='left'>Adjudicators</td></tr>
                    </table>
                    <table align='center' width='95%'>
                        ${judgesHtml}
                    </table>
                `;
            }
            
            html += `
                    </div>
                    ${judgesSection}
                    <div class="dancesport-footer">
                        <p style="padding:10px 0; background:#575757; color:#fff; position:relative; clear:both; text-align:center;">Results Copyright of
                            DanceScore Scrutineering Software
                        </p>
                    </div>
                </div>
            `;
            
            content.innerHTML = html;
            
            // 다음 라운드 생성 버튼 텍스트 업데이트
            updateNextRoundButtonText();
        }
        
        // 리콜 오류 표시 함수
        function displayRecallError(errorMessage, content) {
            content.innerHTML = `
                <div class="error-message" style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24;">
                    <h3>❌ 리콜 계산 오류</h3>
                    <p>${errorMessage}</p>
                </div>
            `;
        }
        
        // 기존 집계 함수 (참고용)
        function openAggregationOld(eventId) {
            if (!eventId) {
                alert('이벤트를 선택해주세요.');
                return;
            }
            
            const compId = '<?=$comp_id?>';
            
            console.log('집계 시작:', {eventId, compId});
            
            // 올바른 서버 주소를 사용하여 집계 API 호출
            const currentProtocol = window.location.protocol;
            const currentHost = window.location.host;
            const baseUrl = `${currentProtocol}//${currentHost}`;
            
            // Final Aggregation API를 호출하여 결승 결과 생성
            const apiUrl = `${baseUrl}/comp/final_aggregation_api.php?comp_id=${compId}&event_no=${eventId}`;
            
            console.log('결승 집계 API 호출:', apiUrl);
            
            // 로딩 인디케이터 표시
            const loadingMsg = document.createElement('div');
            loadingMsg.innerHTML = `
                <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                     background: white; padding: 30px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                     z-index: 10000; font-family: 'Noto Sans KR'; text-align: center;">
                    <div style="font-size: 1.2em; margin-bottom: 15px; color: #333;">🏆 결승 결과 집계 중...</div>
                    <div style="font-size: 0.9em; color: #666;">스케이팅 시스템으로 최종 순위를 계산하고 있습니다.</div>
                    <div style="margin-top: 15px;">
                        <div style="width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; 
                             border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                    </div>
                </div>
                <style>
                    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                </style>
            `;
            document.body.appendChild(loadingMsg);
            
            // API 호출 후 결과 페이지 열기
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // 로딩 인디케이터 제거
                    document.body.removeChild(loadingMsg);
                    
                    if (data.event_info && data.final_rankings) {
                        // 성공시 생성된 결과 HTML 파일 열기
                        const resultUrl = `${baseUrl}/comp/results_reports/${compId}/Event_${eventId}/combined_report_${eventId}.html`;
                        console.log('결승 집계 성공, 결과 파일로 이동:', resultUrl);
                        
                        // 새 창에서 결과 표시
                        const newWindow = window.open(resultUrl, '_blank', 'width=1200,height=900,scrollbars=yes,resizable=yes');
                        
                        if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                            // 팝업이 차단된 경우 현재 페이지에 결과 표시
                            showAggregationResult(data, eventId);
                        } else {
                            console.log('결승 결과 창이 열렸습니다.');
                        }
                        
                            // 성공 메시지 표시
                            setTimeout(() => {
                                const successMsg = document.createElement('div');
                                successMsg.innerHTML = `
                                    <div style="position: fixed; top: 20px; right: 20px; background: #27ae60; color: white; 
                                         padding: 15px 20px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                                         z-index: 10000; font-family: 'Noto Sans KR';">
                                    ✅ 결승 결과가 성공적으로 생성되었습니다!<br>
                                    <small>파일: combined_report_${eventId}.html</small>
                                    </div>
                                `;
                                document.body.appendChild(successMsg);
                                setTimeout(() => {
                                    if (successMsg.parentNode) {
                                        document.body.removeChild(successMsg);
                                    }
                            }, 4000);
                            }, 500);
                    } else {
                        console.error('집계 실패:', data.error || '데이터 형식 오류');
                        alert(`집계 실패: ${data.error || '결과 데이터를 생성할 수 없습니다.'}`);
                    }
                })
                .catch(error => {
                    // 로딩 인디케이터 제거
                    if (loadingMsg.parentNode) {
                        document.body.removeChild(loadingMsg);
                    }
                    console.error('결승 집계 API 호출 실패:', error);
                    alert(`결승 집계 처리 중 오류가 발생했습니다: ${error.message}`);
                });
        }
        
        function openAwards(eventId) {
            if (!eventId) {
                alert('이벤트를 선택해주세요.');
                return;
            }
            
            const compId = '<?=$comp_id?>';
            const url = `https://www.danceoffice.net/comp/live_panel.php?comp_id=${compId}&view=awards&event_no=${eventId}`;
            
            console.log('Opening awards URL:', url);
            
            // 새 창에서 상장 발급
            window.open(url, '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
        }
        
        // 집계 결과를 현재 페이지에 표시하는 함수
        function showAggregationResult(data, eventId) {
            const rightContent = document.getElementById('right-content');
            
            const resultHtml = `
                <div class="aggregation-result">
                    <h2>🏆 집계 결과 - ${data.event_info.desc || '알 수 없는 이벤트'}</h2>
                    
                    <div class="event-info">
                        <h3>이벤트 정보</h3>
                        <p><strong>이벤트 번호:</strong> ${data.event_info.event_no || ''}</p>
                        <p><strong>라운드:</strong> ${data.event_info.round || ''}</p>
                        <p><strong>패널:</strong> ${data.event_info.panel || ''}</p>
                        <p><strong>댄스:</strong> ${(data.event_info.dances || []).join(', ')}</p>
                    </div>
                    
                    <div class="final-rankings">
                        <h3>🏆 최종 순위</h3>
                        <table class="rankings-table">
                            <thead>
                                <tr>
                                    <th>순위</th>
                                    <th>선수 번호</th>
                                    <th>합계</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(data.final_rankings || []).map(ranking => `
                                    <tr>
                                        <td>${ranking.final_rank}</td>
                                        <td>${ranking.player_no}</td>
                                        <td>${ranking.sum_of_places}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="players-info">
                        <h3>👥 참가 선수</h3>
                        <ul>
                            ${(data.players || []).map(player => `
                                <li>선수 번호: ${player.number}</li>
                            `).join('')}
                        </ul>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <button class="btn btn-primary" onclick="window.open('${window.location.origin}/comp/results_reports/<?=$comp_id?>/Event_${eventId}/combined_report_${eventId}.html', '_blank')">
                            📄 전체 결과 보기
                        </button>
                        <button class="btn btn-secondary" onclick="updateRightPanel('${selectedEvent}', '${selectedGroup}')">
                            ← 돌아가기
                        </button>
                    </div>
                </div>
            `;
            
            rightContent.innerHTML = resultHtml;
        }
        
        function getDanceSequenceDisplay(danceSequence) {
            if (!danceSequence || danceSequence.length === 0) return 'N/A';
            
            return danceSequence.map(item => {
                const typeLabel = item.type === 'common' ? '(공동)' : 
                                 item.type === 'individual' ? '(개별)' : '';
                return `${item.dance_name || item.dance}${typeLabel}`;
            }).join(' → ');
        }
        
        // Recall 수 표시 여부 결정 함수
        function shouldShowRecallCount(event) {
            if (!event || !event.round) return false;
            
            const round = event.round.toLowerCase();
            // 예선전(Round 1, Round 2 등)과 준결승(Semi-Final)에서만 Recall 수 표시
            return round.includes('round') || round.includes('semi');
        }
        
        // 다음 라운드 정보 가져오기
        function getNextRoundInfo(event) {
            if (!event || !event.desc) {
                console.log('getNextRoundInfo: 이벤트 정보 없음');
                return '';
            }
            
            console.log('getNextRoundInfo 호출:', event);
            console.log('getNextRoundInfo: event.next_event:', event.next_event);
            
            // 먼저 next_event 정보가 있으면 사용
            if (event.next_event && event.next_event !== '') {
                console.log('getNextRoundInfo: next_event 정보 사용:', event.next_event);
                // next_event 번호로 해당 이벤트 찾기
                let foundEvent = false;
                for (const group of groupData) {
                    for (const groupEvent of group.events) {
                        if (groupEvent.no === event.next_event) {
                            console.log('getNextRoundInfo: next_event로 찾은 이벤트:', groupEvent);
                            foundEvent = true;
                            return `${groupEvent.no}번 ${groupEvent.round}`;
                        }
                    }
                }
                if (!foundEvent) {
                    console.log('getNextRoundInfo: next_event로 이벤트를 찾지 못함:', event.next_event);
                }
            }
            
            // next_event가 없으면 기존 로직 사용
            const currentEventDesc = event.desc;
            const currentRound = event.round;
            
            // 라운드 순서 정의
            const roundOrder = {
                'Round 1': 'Semi-Final',
                'Semi-Final': 'Final',
                'Final': ''
            };
            
            const nextRound = roundOrder[currentRound] || '';
            if (!nextRound) return '';
            
            // 같은 종목의 다음 라운드 이벤트 찾기
            for (const group of groupData) {
                for (const groupEvent of group.events) {
                    if (groupEvent.desc === currentEventDesc && groupEvent.round === nextRound) {
                        return `${groupEvent.no}번 ${nextRound}`;
                    }
                }
            }
            
            return '';
        }
        
        function openDanceEditModal(groupId) {
            const group = groupData.find(g => g.group_no == groupId);
            
            if (!group || !group.dance_sequence) {
                alert('댄스 순서 정보를 찾을 수 없습니다.');
        return;
    }
    
            const modal = document.createElement('div');
            modal.className = 'dance-edit-modal';
            modal.innerHTML = `
                <div class="dance-edit-content">
                    <div class="dance-edit-header">
                        <div class="dance-edit-title">댄스 순서 수정 - 통합이벤트 ${groupId}</div>
                        <button class="dance-edit-close" onclick="closeDanceEditModal()">&times;</button>
                    </div>
                    
                    <div class="dance-list-container" id="dance-list-container">
                        ${group.dance_sequence.map((item, index) => `
                            <div class="dance-item" data-index="${index}" draggable="true">
                                <div class="dance-drag-handle">⋮⋮</div>
                                <div class="dance-number">${index + 1}</div>
                                <div class="dance-info">
                                    <div class="dance-name">${item.dance_name || item.dance}</div>
                                    <div class="dance-events">
                                        ${item.type === 'common' ? '공동 댄스' : 
                                          item.type === 'individual' ? '개별 댄스' : '싱글 댄스'} 
                                        (이벤트: ${item.events.join(', ')})
                                    </div>
                                </div>
                                <div class="dance-actions">
                                    <button class="dance-action-btn dance-remove-btn" 
                                            onclick="removeDanceItem(${index})" title="제거">×</button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="dance-edit-buttons">
                        <button class="btn-cancel" onclick="closeDanceEditModal()">취소</button>
                        <button class="btn-save" onclick="saveDanceSequence('${groupId}')">저장</button>
        </div>
        </div>
            `;
            
            document.body.appendChild(modal);
            
            // 드래그 앤 드롭 기능 추가
            makeDanceListSortable();
        }
        
        function makeDanceListSortable() {
            const container = document.getElementById('dance-list-container');
            let draggedElement = null;
            
            container.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('dance-drag-handle')) {
                    draggedElement = e.target.closest('.dance-item');
                    draggedElement.classList.add('dragging');
                }
            });
            
            container.addEventListener('dragend', function(e) {
                if (draggedElement) {
                    draggedElement.classList.remove('dragging');
                    draggedElement = null;
                }
            });
            
            container.addEventListener('dragover', function(e) {
                e.preventDefault();
            });
            
            container.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedElement) {
                    const afterElement = getDragAfterElement(container, e.clientY);
                    if (afterElement == null) {
                        container.appendChild(draggedElement);
        } else {
                        container.insertBefore(draggedElement, afterElement);
                    }
                    updateDanceNumbers();
                }
            });
        }
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.dance-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
    } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
        
        function updateDanceNumbers() {
            const items = document.querySelectorAll('.dance-item');
            items.forEach((item, index) => {
                const numberElement = item.querySelector('.dance-number');
                numberElement.textContent = index + 1;
            });
        }
        
        function removeDanceItem(index) {
            const item = document.querySelector(`[data-index="${index}"]`);
            if (item) {
                item.remove();
                updateDanceNumbers();
            }
        }
        
        function saveDanceSequence(groupId) {
            const items = document.querySelectorAll('.dance-item');
            const newSequence = Array.from(items).map((item, index) => {
                const danceName = item.querySelector('.dance-name').textContent;
                const eventsText = item.querySelector('.dance-events').textContent;
                const events = eventsText.match(/이벤트: ([^)]+)/);
                
                return {
                    dance: danceName,
                    events: events ? events[1].split(', ') : [],
                    type: eventsText.includes('공동') ? 'common' : 
                          eventsText.includes('개별') ? 'individual' : 'single'
                };
            });
            
            // 실제 저장 로직 (서버로 전송)
            console.log('새로운 댄스 순서:', newSequence);
            alert('댄스 순서가 저장되었습니다.');
            closeDanceEditModal();
        }
        
        function closeDanceEditModal() {
            const modal = document.querySelector('.dance-edit-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Recall 수 편집 모달 열기
        function openRecallEditModal(groupId) {
            const group = groupData.find(g => g.group_no == groupId);
            
            if (!group) {
                alert('그룹 정보를 찾을 수 없습니다.');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'recall-edit-modal';
            modal.innerHTML = `
                <div class="recall-edit-content">
                    <div class="recall-edit-header">
                        <div class="recall-edit-title">Recall 수 수정 - 통합이벤트 ${groupId}</div>
                        <button class="recall-edit-close" onclick="closeRecallEditModal()">&times;</button>
                    </div>
                    
                    <div class="recall-edit-body">
                        <div class="recall-edit-field">
                            <label for="recallCountInput">Recall 수:</label>
                            <input type="number" id="recallCountInput" value="${group.recall_count || 0}" min="0" max="50">
                            <span class="recall-edit-unit">명</span>
                        </div>
                        <div class="recall-edit-info">
                            <p>• Recall 수는 다음 라운드로 진출할 선수의 수를 의미합니다.</p>
                            <p>• 0으로 설정하면 심사위원 수의 절반을 기준으로 합니다.</p>
                        </div>
                    </div>
                    
                    <div class="recall-edit-buttons">
                        <button class="btn-cancel" onclick="closeRecallEditModal()">취소</button>
                        <button class="btn-save" onclick="saveRecallCount('${groupId}')">저장</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        // Recall 수 편집 모달 닫기
        function closeRecallEditModal() {
            const modal = document.querySelector('.recall-edit-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Recall 수 저장
        function saveRecallCount(groupId) {
            const recallCount = parseInt(document.getElementById('recallCountInput').value);
            
            if (isNaN(recallCount) || recallCount < 0) {
                alert('올바른 Recall 수를 입력해주세요.');
                return;
            }
            
            // 그룹 데이터 업데이트
            const group = groupData.find(g => g.group_no == groupId);
            if (group) {
                group.recall_count = recallCount;
                
                // UI 업데이트
                updateRightPanel(selectedEvent, selectedGroup);
                
                // RunOrder_Tablet.txt 업데이트 (서버로 전송)
                updateRunOrderFile(groupId, recallCount);
            }
            
            closeRecallEditModal();
        }
        
        // RunOrder_Tablet.txt 파일 업데이트
        function updateRunOrderFile(groupId, recallCount) {
            // 서버로 Recall 수 업데이트 요청
            fetch('update_recall_count.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    group_id: groupId,
                    recall_count: recallCount
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Recall 수가 업데이트되었습니다.');
                } else {
                    console.error('Recall 수 업데이트 실패:', data.error);
                }
            })
            .catch(error => {
                console.error('Recall 수 업데이트 오류:', error);
            });
        }
        
        // 실시간 업데이트 기능
        function startRealTimeUpdates() {
            // 5초마다 심사위원 상태 업데이트
            setInterval(() => {
                updateAllJudgeStatus();
            }, 5000);
        }
        
        function updateAllJudgeStatus() {
            // 모든 이벤트의 심사위원 상태 업데이트
            const eventCards = document.querySelectorAll('.event-card');
            eventCards.forEach(card => {
                const eventId = card.dataset.event;
                if (eventId) {
                    updateEventJudgeStatus(eventId);
        }
    });
}

        function updateEventJudgeStatus(eventId) {
            // 서버에서 최신 심사위원 상태 가져오기
            const compId = '<?=$comp_id?>';
            fetch(`https://www.danceoffice.net/comp/api/get_judge_status.php?comp_id=${compId}&event=${eventId}`)
                .then(response => response.json())
        .then(data => {
                    if (data.success) {
                        updateJudgeStatusDisplay(eventId, data.judges);
                    }
                })
                .catch(error => {
                    console.log('심사위원 상태 업데이트 실패:', error);
                });
        }
        
        function updateJudgeStatusDisplay(eventId, judges) {
            const card = document.querySelector(`[data-event="${eventId}"]`);
            if (!card) return;
            
            const judgesList = card.querySelector('.judges-list');
            if (!judgesList) return;
            
            // 심사위원 상태 업데이트
            judges.forEach(judge => {
                const judgeItem = judgesList.querySelector(`[data-judge="${judge.code}"]`);
                if (judgeItem) {
                    const statusDot = judgeItem.querySelector('.judge-dot');
                    const progressText = judgeItem.querySelector('.judge-progress');
                    
                    if (statusDot) {
                        statusDot.className = `judge-dot judge-dot-${judge.status}`;
                    }
                    
                    if (progressText) {
                        progressText.textContent = `${judge.completed}/${judge.total}`;
                    }
                }
            });
        }
        
        // 심사위원 상태 모니터링 함수 (멀티 이벤트 지원)
        function updateJudgeStatus(eventNo) {
            // 현재 이벤트의 심사위원들 상태 확인
            if (!eventNo) return;
            
            console.log('updateJudgeStatus called for:', eventNo);
            
            fetch(`get_judge_status.php?comp_id=<?=urlencode($comp_id)?>&event_no=${eventNo}&${Date.now()}`)
                .then(r => r.ok ? r.json() : {success: false, status: {}})
                .then(data => {
                    console.log('Judge status response for', eventNo, ':', data);
                    
                    if (data.success && data.status) {
                        // 해당 이벤트의 카드 내에서만 상태 업데이트
                        const eventCard = document.querySelector(`.event-card[data-event="${eventNo}"]`);
                        console.log('Found event card:', eventCard);
                        
                        if (eventCard) {
                            let completedCount = 0;
                            let totalCount = 0;
                            
                            Object.keys(data.status).forEach(judgeCode => {
                                let statusElement = eventCard.querySelector(`#judge-status-${judgeCode}`);
                                if (statusElement) {
                                    let status = data.status[judgeCode];
                                    statusElement.className = `judge-status ${status.class}`;
                                    statusElement.textContent = status.text;
                                    
                                    // 완료된 심사위원 수 계산
                                    if (status.class === 'completed') {
                                        completedCount++;
                                    }
                                    totalCount++;
                                }
                            });
                            
                            // 심사위원 현황 진행률 업데이트
                            const progressElement = eventCard.querySelector('.judges-progress');
                            if (progressElement) {
                                progressElement.textContent = `${completedCount}/${totalCount} 완료`;
                                console.log('Updated progress:', `${completedCount}/${totalCount} 완료`);
                            }
                        }
                        
                        // 싱글 이벤트 심사위원 테이블에서 상태 업데이트
                        const tbody = document.getElementById('adjudicator-list');
                        if (tbody) {
                            let completedCount = 0;
                            let totalCount = 0;
                            
                            Object.keys(data.status).forEach(judgeCode => {
                                let statusElement = tbody.querySelector(`#judge-status-${judgeCode}`);
                                if (statusElement) {
                                    let status = data.status[judgeCode];
                                    statusElement.className = `judge-status ${status.class}`;
                                    statusElement.textContent = status.text;
                                    
                                    if (status.class === 'completed') {
                                        completedCount++;
                                    }
                                    totalCount++;
                                }
                            });
                            
                            // 집계 섹션의 심사위원 상태 업데이트
                            const totalJudgesElement = document.getElementById('total-judges');
                            const completedJudgesElement = document.getElementById('completed-judges');
                            const progressRateElement = document.getElementById('progress-rate');
                            
                            if (totalJudgesElement) totalJudgesElement.textContent = totalCount;
                            if (completedJudgesElement) completedJudgesElement.textContent = completedCount;
                            if (progressRateElement) {
                                const rate = totalCount > 0 ? Math.round((completedCount / totalCount) * 100) : 0;
                                progressRateElement.textContent = rate + '%';
                            }
                        }
                        
                        // 싱글 이벤트 카드의 심사위원 상태 업데이트
                        const singleEventCard = document.querySelector(`.event-card[data-event="${eventNo}"]`);
                        if (singleEventCard) {
                            let completedCount = 0;
                            let totalCount = 0;
                            
                            Object.keys(data.status).forEach(judgeCode => {
                                let statusElement = singleEventCard.querySelector(`#judge-status-${judgeCode}`);
                                if (statusElement) {
                                    let status = data.status[judgeCode];
                                    statusElement.className = `judge-status ${status.class}`;
                                    statusElement.textContent = status.text;
                                    
                                    if (status.class === 'completed') {
                                        completedCount++;
                                    }
                                    totalCount++;
                                }
                            });
                            
                            // 진행률 업데이트
                            const progressElement = singleEventCard.querySelector('.judge-progress .progress-text');
                            if (progressElement) {
                                progressElement.textContent = `${completedCount}/${totalCount} 완료`;
                            }
                        }
                    }
                })
                .catch(err => {
                    console.warn('심사위원 상태 로드 오류:', err);
                });
        }
        
        // 실시간 심사위원 상태 모니터링 시작
        function startJudgeStatusMonitoring() {
            // 2초마다 심사위원 상태 업데이트
            setInterval(() => {
                if (selectedEvent && selectedGroup) {
                    // 현재 선택된 그룹이 멀티 이벤트인지 확인
                    const group = groupData.find(g => g.group_no == selectedGroup);
                    if (group && group.is_multi) {
                        // 멀티 이벤트인 경우 각 이벤트별로 개별 업데이트
                        group.events.forEach(evt => {
                            const eventKey = evt.detail_no || evt.no;
                            updateJudgeStatus(eventKey);
                        });
                    } else {
                        // 싱글 이벤트인 경우 현재 이벤트만 업데이트
                        updateJudgeStatus(selectedEvent);
                    }
                }
            }, 2000);
        }
        
        // 선수 관리 모달 열기
        function openPlayerModal(eventId) {
            currentEventForPlayerModal = eventId;
            document.getElementById('playerModalBg').style.display = 'flex';
            
            // 현재 선수 목록 로드
            loadCurrentPlayers(eventId);
        }
        
        // 선수 관리 모달 닫기
        function closePlayerModal() {
            document.getElementById('playerModalBg').style.display = 'none';
            currentEventForPlayerModal = null;
            currentPlayers = [];
        }
        
        // 현재 선수 목록 로드
        function loadCurrentPlayers(eventId) {
            const compId = '<?=$comp_id?>';
            fetch(`https://www.danceoffice.net/comp/get_players.php?comp_id=${compId}&event_no=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentPlayers = data.players || [];
                        updatePlayersList();
                    }
                })
                .catch(error => {
                    console.error('Error loading players:', error);
                    currentPlayers = [];
                    updatePlayersList();
                });
        }
        
        // 선수 목록 업데이트
        function updatePlayersList() {
            const playersList = document.getElementById('playersList');
            if (currentPlayers.length === 0) {
                playersList.innerHTML = '<div style="color:#666; font-style:italic;">등록된 선수가 없습니다.</div>';
            } else {
                playersList.innerHTML = currentPlayers.map(player => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:0.3em; border-bottom:1px solid #eee;">
                        <span>${player.number} (${player.type === 'couple' ? '커플' : '싱글'})</span>
                        <button onclick="removePlayer(${player.number})" style="background:#dc3545; color:white; border:none; padding:0.2em 0.5em; border-radius:3px; cursor:pointer;">X</button>
                    </div>
                `).join('');
            }
        }
        
        // 선수 제거
        function removePlayer(playerNumber) {
            currentPlayers = currentPlayers.filter(p => p.number != playerNumber);
            updatePlayersList();
        }
        
        // 선수 추가 (입력 처리)
        function addPlayers() {
            const input = document.getElementById('playerInput').value.trim();
            if (!input) return;
            
            const newPlayers = parsePlayerInput(input);
            newPlayers.forEach(playerNum => {
                if (!currentPlayers.find(p => p.number == playerNum)) {
                    // 선수 번호로 전체 선수 데이터에서 정보 찾기
                    const allPlayers = <?=json_encode($all_players)?>;
                    const playerData = allPlayers[playerNum];
                    
                    let playerName = `선수 ${playerNum}`;
                    let playerType = 'single';
                    
                    if (playerData) {
                        const isCouple = playerData.female && playerData.female.trim() !== '';
                        playerName = playerData.male + (playerData.female ? ' & ' + playerData.female : '');
                        playerType = isCouple ? 'couple' : 'single';
                    }
                    
                    currentPlayers.push({
                        number: playerNum,
                        name: playerName,
                        type: playerType
                    });
                }
            });
            
            document.getElementById('playerInput').value = '';
            updatePlayersList();
        }
        
        // 선수 입력 파싱 (범위 지원)
        function parsePlayerInput(input) {
            const players = [];
            const parts = input.split(',');
            
            parts.forEach(part => {
                part = part.trim();
                if (part.includes('~')) {
                    const [start, end] = part.split('~').map(n => parseInt(n.trim()));
                    if (!isNaN(start) && !isNaN(end)) {
                        for (let i = start; i <= end; i++) {
                            players.push(i);
                        }
                    }
                } else {
                    const num = parseInt(part);
                    if (!isNaN(num)) {
                        players.push(num);
                    }
                }
            });
            
            return players;
        }
        
        // 선수 저장
        function savePlayers() {
            if (!currentEventForPlayerModal) return;
            
            const compId = '<?=$comp_id?>';
            const eventNo = currentEventForPlayerModal;
            const detailNo = currentEventForPlayerModal.includes('-') ? currentEventForPlayerModal.split('-')[1] : '';
            
            const requestData = {
                eventNo: eventNo,
                detailNo: detailNo,
                players: currentPlayers
            };
            
            console.log('Saving players:', requestData);
            
            fetch(`https://www.danceoffice.net/comp/save_players.php?comp_id=${compId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    alert('선수 목록이 저장되었습니다.');
                    closePlayerModal();
                    // 카드 새로고침
                    if (selectedEvent) {
                        updateRightPanel(selectedEvent, selectedGroup);
                    }
                } else {
                    alert('저장 중 오류가 발생했습니다: ' + (data.error || data.message || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('Error saving players:', error);
                alert('저장 중 오류가 발생했습니다: ' + error.message);
            });
        }
        
        
        // 입력 필드에서 Enter 키 처리
        document.addEventListener('DOMContentLoaded', function() {
            const playerInput = document.getElementById('playerInput');
            if (playerInput) {
                playerInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        addPlayers();
                    }
                });
            }
        });
        

        // 페이지 로드 시 첫 번째 그룹 확장 및 실시간 업데이트 시작
        document.addEventListener('DOMContentLoaded', function() {
            // 중복 그룹 제거 (임시 해결책)
            removeDuplicateGroups();
            
            const firstGroup = document.querySelector('.event-group');
            if (firstGroup) {
                const groupNo = firstGroup.dataset.group;
                toggleGroup(groupNo);
                
                // 첫 번째 이벤트 자동 선택
                const firstEvent = firstGroup.querySelector('.event-item');
                if (firstEvent) {
                    const eventId = firstEvent.dataset.event;
                    const groupId = firstEvent.dataset.group;
                    selectEvent(eventId, groupId, firstEvent);
                }
            }

            // 실시간 업데이트 시작
            startJudgeStatusMonitoring();
        });
        
        // 누락된 그룹을 DOM에 추가하는 함수
        function addMissingGroupToDOM(group) {
            const leftPanel = document.querySelector('.left-panel');
            if (!leftPanel) {
                console.error('왼쪽 패널을 찾을 수 없습니다.');
                return;
            }
            
            // 기존 이벤트 그룹들 다음에 추가
            const existingGroups = leftPanel.querySelectorAll('.event-group');
            const lastGroup = existingGroups[existingGroups.length - 1];
            
            // 52번 그룹 HTML 생성
            const groupHtml = `
                <div class="event-group" data-group="${group.group_no}">
                    <div class="group-header" onclick="toggleGroup('${group.group_no}')">
                        <div class="group-info">
                            <div class="group-title">
                                통합이벤트 ${group.group_no}
                            </div>
                            <div class="group-subtitle">${group.group_name}</div>
                        </div>
                        <div class="group-actions">
                            <button class="group-complete-btn" onclick="event.stopPropagation(); toggleGroupComplete('${group.group_no}')" 
                                    data-group="${group.group_no}">
                                완료
                            </button>
                            <span class="group-toggle">▶</span>
                        </div>
                    </div>
                
                    <div class="event-list" id="group-${group.group_no}">
                        ${group.events.map(event => `
                            <div class="event-item" 
                                 data-event="${event.detail_no || event.no}"
                                 data-group="${group.group_no}"
                                 onclick="selectEvent('${event.detail_no || event.no}', '${group.group_no}', this)">
                                <div class="event-info">
                                    <div class="event-number">
                                        ${event.detail_no || event.no}
                                    </div>
                                    <div class="event-desc">
                                        ${event.desc}
                                    </div>
                                    ${event.dances ? `
                                    <div class="event-dances">
                                        댄스: ${event.dances.join(', ')}
                                    </div>
                                    ` : ''}
                                </div>
                                <div class="event-status status-${event.round.toLowerCase()}">
                                    ${event.round}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            // HTML을 DOM에 추가
            if (lastGroup) {
                lastGroup.insertAdjacentHTML('afterend', groupHtml);
            } else {
                leftPanel.insertAdjacentHTML('beforeend', groupHtml);
            }
            
            console.log('52번 그룹이 DOM에 추가되었습니다.');
        }
        
        // 중복 그룹 제거 함수
        function removeDuplicateGroups() {
            const groups = document.querySelectorAll('.event-group');
            const seenGroups = new Set();
            let removedCount = 0;
            
            console.log('총 그룹 수 (DOM):', groups.length);
            
            groups.forEach(group => {
                const groupNo = group.dataset.group;
                if (seenGroups.has(groupNo)) {
                    console.log('중복 그룹 제거:', groupNo);
                    group.remove();
                    removedCount++;
                } else {
                    seenGroups.add(groupNo);
                }
            });
            
            if (removedCount > 0) {
                console.log('총 ' + removedCount + '개의 중복 그룹이 제거되었습니다.');
            }
            
            // 52번 그룹이 DOM에 있는지 확인
            const group52 = document.querySelector('[data-group="52"]');
            if (group52) {
                console.log('52번 그룹이 DOM에 존재합니다.');
            } else {
                console.log('52번 그룹이 DOM에 없습니다.');
            }
        }
        
        // 집계 모달 열기
        function openAggregationModal(eventId) {
            console.log('Opening aggregation modal for eventId:', eventId);
            
            // 모달 표시
            document.getElementById('aggregationModalBg').style.display = 'flex';
            document.getElementById('aggregationLoading').style.display = 'block';
            document.getElementById('aggregationContent').style.display = 'none';
            document.getElementById('printAggregationBtn').style.display = 'none';
            document.getElementById('saveAggregationBtn').style.display = 'none';
            document.getElementById('nextRoundBtn').style.display = 'none';
            
            // 진출자 수 설정 모달 먼저 표시
            setTimeout(() => {
                showRecallCountModal(eventId);
            }, 500);
        }
        
        // 진출자 수 설정 모달 표시
        function showRecallCountModal(eventId) {
            console.log('Showing recall count modal for eventId:', eventId);
            
            // 현재 이벤트 정보 찾기
            let currentEvent = null;
            for (const group of groupData) {
                for (const event of group.events) {
                    if ((event.detail_no || event.no) === eventId) {
                        currentEvent = event;
                        break;
                    }
                }
                if (currentEvent) break;
            }
            
            if (!currentEvent) {
                alert('이벤트 정보를 찾을 수 없습니다.');
                return;
            }
            
            const recallCount = currentEvent.recall_count || 0;
            
            // 로딩 숨기고 콘텐츠 표시
            document.getElementById('aggregationLoading').style.display = 'none';
            document.getElementById('aggregationContent').style.display = 'block';
            
            // 먼저 집계 결과를 가져와서 표시
            calculateRecallResultsForModal(eventId, currentEvent, recallCount);
        }
        
        // 모달용 집계 결과 계산
        function calculateRecallResultsForModal(eventId, currentEvent, recallCount) {
            console.log('Calculating recall results for modal, eventId:', eventId, 'recallCount:', recallCount);
            
            // 리콜 데이터 로드
            const apiUrl = `get_recall_data.php?comp_id=<?=$comp_id?>&event_no=${eventId}`;
            console.log('API URL:', apiUrl);
            
            fetch(apiUrl)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API Response:', data);
                    if (data.success) {
                        // 진출자 수가 설정되어 있으면 데이터에 적용
                        if (recallCount !== null) {
                            data.recall_count_from_file = recallCount;
                        }
                        displayRecallCountModal(eventId, currentEvent, data, recallCount);
                    } else {
                        console.error('API Error:', data.error);
                        displayRecallError(data.error, document.getElementById('aggregationContent'));
                    }
                })
                .catch(error => {
                    console.error('리콜 데이터 로드 실패:', error);
                    displayRecallError('리콜 데이터를 로드할 수 없습니다: ' + error.message, document.getElementById('aggregationContent'));
                });
        }
        
        // 진출자 수 설정 모달에 집계 결과 표시
        function displayRecallCountModal(eventId, currentEvent, data, recallCount) {
            console.log('displayRecallCountModal called with:', {eventId, currentEvent, data, recallCount});
            const content = document.getElementById('aggregationContent');
            
            // 집계 결과 테이블 생성 (종합 결과)
            let resultsTable = '';
            console.log('player_recalls data:', data.player_recalls);
            console.log('Full data object:', data);
            if (data.player_recalls && data.player_recalls.length > 0) {
                resultsTable = generateResultsTable(data, currentEvent, recallCount);
                console.log('Generated results table:', resultsTable);
            } else {
                console.log('No player_recalls data found');
                resultsTable = '<div class="no-results">집계 결과 데이터를 찾을 수 없습니다.</div>';
            }
            
            content.innerHTML = `
                <div class="recall-count-modal">
                    <h3>🏆 집계 결과 - ${currentEvent.desc}</h3>
                    
                    <div class="modal-layout">
                        <div class="results-section">
                            <h4>집계 결과</h4>
                            <div class="results-container">
                                ${resultsTable}
                            </div>
                        </div>
                        
                        <div class="recall-count-section">
                            <h4>진출자 수 설정</h4>
                            <div class="recall-count-info">
                                <p><strong>현재 리콜 수:</strong> ${recallCount}명</p>
                                <p><strong>설명:</strong> 다음 라운드로 진출할 수 있는 최대 인원 수입니다.</p>
                            </div>
                            <div class="recall-count-input">
                                <label for="recallCountInput">진출자 수:</label>
                                <input type="number" id="recallCountInput" value="${recallCount}" min="1" max="50" class="recall-count-field" oninput="updateResultsPreview()" onchange="updateResultsPreview()">
                                <span class="recall-count-unit">명</span>
                            </div>
                            <div class="recall-count-actions">
                                <button class="btn btn-primary" onclick="confirmRecallCount('${eventId}')">확인</button>
                                <button class="btn btn-secondary" onclick="closeAggregationModal()">취소</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            console.log('Modal content set, resultsTable length:', resultsTable.length);
            
            // 초기 로드 후 미리보기 업데이트 실행
            setTimeout(() => {
                updateResultsPreview();
            }, 100);
        }
        
        // 집계 결과 테이블 생성 (종합 결과)
        function generateResultsTable(data, currentEvent, recallCount) {
            console.log('generateResultsTable called with:', {data, currentEvent, recallCount});
            let html = '';
            
            // 종합 결과 테이블 (모든 댄스의 리콜 수 총합)
            if (data.player_recalls && data.player_recalls.length > 0) {
                // 리콜 수 기준으로 정렬 (높은 순)
                const sortedPlayers = data.player_recalls.sort((a, b) => b.recall_count - a.recall_count);
                
                html += `
                    <div class="overall-results">
                        <h5>종합 결과</h5>
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>등위</th>
                                    <th>등번호</th>
                                    <th>선수명</th>
                                    <th>리콜수</th>
                                    <th>상태</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                sortedPlayers.forEach((player, index) => {
                    const isAdvancing = index < recallCount;
                    const statusText = isAdvancing ? '✅ 진출' : '';
                    const rowClass = isAdvancing ? 'advancing' : 'eliminated';
                    
                    html += `
                        <tr class="${rowClass}">
                            <td>${index + 1}</td>
                            <td>${player.player_number}</td>
                            <td>${player.player_name || `선수 ${player.player_number}`}</td>
                            <td>${player.recall_count}</td>
                            <td>${statusText}</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            // 심사위원 명단 추가 (테이블과 별도로)
            console.log('Adding judges section...');
            console.log('Judges data:', data.judges);
            console.log('Judge names:', data.judge_names);
            console.log('Judges length:', data.judges ? data.judges.length : 'undefined');
            
            // 심사위원 명단 추가 (저작권 문구 바로 위에)
            if (data.judges && data.judges.length > 0) {
                // 심사위원 코드와 이름을 매핑
                const judgeMap = data.judges.map((judgeCode, index) => ({
                    code: judgeCode,
                    name: data.judge_names && data.judge_names[index] ? data.judge_names[index] : String.fromCharCode(65 + index)
                }));
                
                // 심사위원 코드 순서로 정렬
                judgeMap.sort((a, b) => parseInt(a.code) - parseInt(b.code));
                
                // 4명씩 한 줄로 나누어 표시
                let judgesHtml = '';
                for (let i = 0; i < judgeMap.length; i += 4) {
                    judgesHtml += '<tr>';
                    for (let j = 0; j < 4; j++) {
                        const judgeIndex = i + j;
                        if (judgeIndex < judgeMap.length) {
                            const judge = judgeMap[judgeIndex];
                            const letter = String.fromCharCode(65 + judgeIndex);
                            judgesHtml += `
                                <td align='left' width='2%' style='padding-left:2em;'><small>${letter}.</small></td>
                                <td align='left'><small>${judge.name}</small></td>
                            `;
                        } else {
                            judgesHtml += `
                                <td align='left' width='2%'> </td>
                                <td align='left'> </td>
                            `;
                        }
                    }
                    judgesHtml += '</tr>';
                }
                
                html += `
                    <table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
                        <tr><td style='font-weight:bold; padding-top:1em; padding-bottom:0.5em;' align='left'>Adjudicators</td></tr>
                    </table>
                    <table align='center' width='95%'>
                        ${judgesHtml}
                    </table>
                `;
                console.log('Judges section added successfully');
            } else {
                console.log('No judges data available, adding placeholder');
                html += `
                    <table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
                        <tr><td style='font-weight:bold; padding-top:1em; padding-bottom:0.5em;' align='left'>Adjudicators</td></tr>
                    </table>
                    <table align='center' width='95%'>
                        <tr>
                            <td align='left' width='2%' style='padding-left:2em;'><small>심사위원 정보 없음</small></td>
                        </tr>
                    </table>
                `;
            }
            
            console.log('Final HTML length:', html.length);
            return html;
        }
        
        // 실시간 미리보기 업데이트
        function updateResultsPreview() {
            const recallCount = parseInt(document.getElementById('recallCountInput').value) || 0;
            console.log('updateResultsPreview called with recallCount:', recallCount);
            const resultsContainer = document.querySelector('.results-container');
            
            if (resultsContainer) {
                console.log('Found results container, updating tables...');
                // 모든 테이블의 진출/탈락 상태 업데이트
                const tables = resultsContainer.querySelectorAll('.results-table tbody');
                console.log('Found tables:', tables.length);
                
                tables.forEach((table, tableIndex) => {
                    const allRows = table.querySelectorAll('tr');
                    console.log(`Table ${tableIndex} has ${allRows.length} total rows`);
                    
                    // 모든 행 처리
                    allRows.forEach((row, index) => {
                        const isAdvancing = index < recallCount;
                        const statusText = isAdvancing ? '✅ 진출' : '';
                        const rowClass = isAdvancing ? 'advancing' : 'eliminated';
                        
                        // 클래스 업데이트
                        row.className = rowClass;
                        
                        // 상태 텍스트 업데이트
                        const statusCell = row.querySelector('td:last-child');
                        if (statusCell) {
                            statusCell.textContent = statusText;
                        }
                        
                        console.log(`Row ${index}: isAdvancing=${isAdvancing}, statusText="${statusText}"`);
                    });
                });
            } else {
                console.log('Results container not found');
            }
        }
        
        // 진출자 수 확인 및 집계 결과 표시
        function confirmRecallCount(eventId) {
            const recallCount = parseInt(document.getElementById('recallCountInput').value);
            console.log('confirmRecallCount called with:', {eventId, recallCount});
            
            if (!recallCount || recallCount < 1) {
                alert('진출자 수를 올바르게 입력해주세요.');
                return;
            }
            
            // 진출자 수 업데이트 (모달 닫지 않음)
            updateRecallCountWithoutRefresh(eventId, recallCount);
            
            // 집계 결과 표시 (사용자가 입력한 진출자 수 전달)
            showSimpleAggregationInModal(eventId, recallCount);
        }
        
        // 집계 모달 닫기
        function closeAggregationModal() {
            document.getElementById('aggregationModalBg').style.display = 'none';
        }
        
        // 집계 실행
        function executeAggregation(eventId) {
            console.log('executeAggregation called with eventId:', eventId, 'type:', typeof eventId);
            
            // eventId 검증
            if (!eventId || eventId === 'undefined' || eventId === 'null') {
                displayAggregationError('이벤트 ID가 올바르지 않습니다: ' + eventId);
                return;
            }
            
            const compId = '<?=$comp_id?>';
            const currentProtocol = window.location.protocol;
            const currentHost = window.location.host;
            const baseUrl = `${currentProtocol}//${currentHost}`;
            const apiUrl = `${baseUrl}/comp/final_aggregation_api.php?comp_id=${compId}&event_no=${eventId}`;
            
            console.log('집계 API 호출:', apiUrl);
            console.log('eventId:', eventId, 'type:', typeof eventId);
            console.log('compId:', compId);
            
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('aggregationLoading').style.display = 'none';
                    document.getElementById('aggregationContent').style.display = 'block';
                    
                    console.log('집계 API 응답:', data);
                    
                    if (data.success) {
                        displayAggregationResult(data, eventId);
                        document.getElementById('printAggregationBtn').style.display = 'inline-block';
                        document.getElementById('saveAggregationBtn').style.display = 'inline-block';
                        document.getElementById('nextRoundBtn').style.display = 'inline-block';
                    } else {
                        let errorMessage = data.error || '집계 처리 중 오류가 발생했습니다.';
                        if (data.debug) {
                            errorMessage += '\n\n디버그 정보:\n';
                            errorMessage += '요청된 이벤트: ' + data.debug.requested_event + '\n';
                            errorMessage += '사용 가능한 이벤트: ' + JSON.stringify(data.debug.available_events, null, 2);
                        }
                        displayAggregationError(errorMessage);
                    }
                })
                .catch(error => {
                    document.getElementById('aggregationLoading').style.display = 'none';
                    document.getElementById('aggregationContent').style.display = 'block';
                    displayAggregationError('집계 API 호출 실패: ' + error.message);
                });
        }
        
        // 집계 결과 표시
        function displayAggregationResult(data, eventId) {
            const content = document.getElementById('aggregationContent');
            
            let html = `
                <div class="aggregation-header">
                    <h2>🏆 ${data.event_info?.desc || '집계 결과'}</h2>
                    <div class="event-details">
                        <span class="event-number">이벤트 ${eventId}</span>
                        <span class="event-round">${data.event_info?.round || ''}</span>
                    </div>
                </div>
            `;
            
            // 최종 순위 테이블
            if (data.final_rankings && data.final_rankings.length > 0) {
                html += `
                    <div class="results-section">
                        <h3>📊 최종 순위</h3>
                        <table class="aggregation-table">
                            <thead>
                                <tr>
                                    <th>순위</th>
                                    <th>번호</th>
                                    <th>선수명</th>
                                    <th>SUM of Places</th>
                                    <th>Place Skating</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.final_rankings.forEach((player, index) => {
                    const rank = index + 1;
                    const rankClass = rank <= 3 ? `rank-${rank}` : '';
                    html += `
                        <tr class="${rankClass}">
                            <td>${rank}</td>
                            <td>${player.number || ''}</td>
                            <td>${player.name || ''}</td>
                            <td>${player.sum_of_places || ''}</td>
                            <td>${player.place_skating || ''}</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            // 다음 라운드 진출자 섹션
            if (data.final_rankings && data.final_rankings.length > 0) {
                const nextRoundCount = Math.min(6, Math.ceil(data.final_rankings.length / 2));
                const nextRoundPlayers = data.final_rankings.slice(0, nextRoundCount);
                
                html += `
                    <div class="next-round-section">
                        <div class="next-round-title">🎯 다음 라운드 진출자 (상위 ${nextRoundCount}명)</div>
                        <div class="next-round-players">
                `;
                
                nextRoundPlayers.forEach((player, index) => {
                    html += `
                        <div class="next-round-player">
                            <div class="player-rank">${index + 1}위</div>
                            <div class="player-number">${player.number || ''}</div>
                            <div class="player-name">${player.name || ''}</div>
                        </div>
                    `;
                });
                
            // 심사위원 정보를 맨 마지막에 한 번만 표시
            if (data.judge_names && data.judge_names.length > 0) {
                const judgeInfo = data.judges.map((judgeCode, index) => {
                    const judgeName = data.judge_names[index] || judgeCode;
                    return `${judgeCode}. ${judgeName}`;
                }).join(', ');
                
                html += `
                    <div style='margin-top:1em; font-size:12px; color:#666; text-align:center;'>
                        <strong>심사위원:</strong> ${judgeInfo}
                    </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
            }
            
            content.innerHTML = html;
        }
        
        // 집계 오류 표시
        function displayAggregationError(errorMessage) {
            const content = document.getElementById('aggregationContent');
            content.innerHTML = `
                <div class="error-message" style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24;">
                    <h3>❌ 집계 오류</h3>
                    <p style="white-space: pre-line; font-family: monospace; font-size: 12px;">${errorMessage}</p>
                </div>
            `;
        }
        
        // 집계 결과 인쇄
        function printAggregation() {
            const content = document.getElementById('aggregationContent').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>집계 결과</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .aggregation-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .aggregation-table th, .aggregation-table td { border: 1px solid #ddd; padding: 8px; text-align: center; }
                        .aggregation-table th { background: #f5f5f5; }
                        .rank-1 { background: #ffd700 !important; }
                        .rank-2 { background: #c0c0c0 !important; }
                        .rank-3 { background: #cd7f32 !important; color: white; }
                    </style>
                </head>
                <body>
                    ${content}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // 집계 결과 파일 저장
        function saveAggregationResult() {
            const content = document.getElementById('aggregationContent').innerHTML;
            const currentEvent = events.find(ev => (ev.detail_no || ev.no) === selectedEvent);
            const eventNo = currentEvent ? (currentEvent.detail_no || currentEvent.no) : selectedEvent;
            
            // HTML 내용을 Blob으로 생성
            const htmlContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>집계 결과 - 이벤트 ${eventNo}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .aggregation-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .aggregation-table th, .aggregation-table td { border: 1px solid #ddd; padding: 8px; text-align: center; }
                        .aggregation-table th { background: #f5f5f5; }
                        .rank-1 { background: #ffd700 !important; }
                        .rank-2 { background: #c0c0c0 !important; }
                        .rank-3 { background: #cd7f32 !important; color: white; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .event-info { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
                        .timestamp { color: #666; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="event-info">집계 결과 - 이벤트 ${eventNo}</div>
                        <div class="timestamp">생성일시: ${new Date().toLocaleString('ko-KR')}</div>
                    </div>
                    ${content}
                </body>
                </html>
            `;
            
            const blob = new Blob([htmlContent], { type: 'text/html;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            
            // 다운로드 링크 생성
            const link = document.createElement('a');
            link.href = url;
            link.download = `집계결과_이벤트${eventNo}_${new Date().toISOString().slice(0,10)}.html`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // URL 해제
            URL.revokeObjectURL(url);
            
            alert(`집계 결과가 파일로 저장되었습니다.\n파일명: 집계결과_이벤트${eventNo}_${new Date().toISOString().slice(0,10)}.html`);
        }
        
        // 테스트용 다음 라운드 모달
        function testNextRoundModal() {
            console.log('테스트 모달 시작');
            const testPlayers = [
                {number: '15', name: '염태우', newNumber: '01'},
                {number: '16', name: '김민제', newNumber: '02'},
                {number: '18', name: '김재희', newNumber: '03'},
                {number: '13', name: '남기용', newNumber: '04'},
                {number: '14', name: '이유진', newNumber: '05'},
                {number: '17', name: '윤휘진', newNumber: '06'}
            ];
            showNextRoundModal(30, '프로페셔널 라틴 Semi-Final', testPlayers);
        }
        
        // 다음 라운드 생성
        function generateNextRound() {
            console.log('generateNextRound 함수 시작');
            
            // groupData에서 현재 이벤트 찾기
            let currentEvent = null;
            for (const group of groupData) {
                for (const event of group.events) {
                    if ((event.detail_no || event.no) === selectedEvent) {
                        currentEvent = event;
                        break;
                    }
                }
                if (currentEvent) break;
            }
            
            console.log('현재 이벤트:', currentEvent);
            if (!currentEvent) {
                alert('현재 이벤트 정보를 찾을 수 없습니다.');
                return;
            }
            
            // 집계 결과에서 진출자 정보 가져오기
            const aggregationResult = document.querySelector('#aggregationContent .dancesport-container.aggregation-results') || 
                                    document.querySelector('#aggregationContent .aggregation-results') || 
                                    document.querySelector('#aggregationContent') ||
                                    document.querySelector('.dancesport-container.aggregation-results') ||
                                    document.querySelector('.aggregation-results');
            console.log('집계 결과 요소:', aggregationResult);
            
            if (!aggregationResult) {
                alert('먼저 집계를 실행해주세요.');
                console.log('집계 결과를 찾을 수 없습니다. 사용 가능한 요소들:');
                console.log('aggregationContent:', document.querySelector('#aggregationContent'));
                console.log('aggregation-results:', document.querySelector('.aggregation-results'));
                console.log('dancesport-container:', document.querySelector('.dancesport-container'));
                console.log('전체 DOM 구조 확인:');
                console.log('aggregationContent 내용:', document.querySelector('#aggregationContent')?.innerHTML?.substring(0, 500));
                return;
            }
            
            // 집계 결과 요소의 내용 확인
            console.log('집계 결과 요소 내용 (처음 500자):', aggregationResult.innerHTML.substring(0, 500));
            
            // 현재 설정된 진출자 수 가져오기 (recall_count_from_file 우선 사용)
            const recallCountInput = document.getElementById('recallCountInput');
            let currentRecallCount = recallCountInput ? parseInt(recallCountInput.value) : 6;
            
            // 만약 집계 결과에서 recall_count_from_file이 있다면 그것을 사용
            const aggregationContent = document.querySelector('#aggregationContent');
            if (aggregationContent) {
                const recallInfoText = aggregationContent.textContent;
                const recallMatch = recallInfoText.match(/파일 리콜 수:\s*(\d+)명/);
                if (recallMatch) {
                    currentRecallCount = parseInt(recallMatch[1]);
                    console.log('집계 결과에서 파일 리콜 수 발견:', currentRecallCount);
                }
            }
            
            console.log('최종 사용할 진출자 수:', currentRecallCount);
            
            // 진출자 등번호 추출
            const advancingPlayers = [];
            
            // API에서 최신 데이터를 가져와서 진출자 추출
            fetch(`get_recall_data.php?comp_id=<?php echo $comp_id; ?>&event_no=${selectedEvent}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.player_recalls && data.player_recalls.length > 0) {
                        // 리콜 수 기준으로 정렬 (높은 순)
                        const sortedPlayers = data.player_recalls.sort((a, b) => b.recall_count - a.recall_count);
                        
                        // 상위 N명만 진출자로 선택
                        for (let i = 0; i < Math.min(currentRecallCount, sortedPlayers.length); i++) {
                            const player = sortedPlayers[i];
                            advancingPlayers.push({
                                number: player.player_number,
                                name: player.player_name || `선수 ${player.player_number}`,
                                newNumber: (i + 1).toString().padStart(2, '0')
                            });
                        }
                        
                        console.log('API에서 추출된 진출자들:', advancingPlayers);
                        console.log('진출자 수:', advancingPlayers.length);
                        
                        // 다음 라운드 생성 계속
                        continueNextRoundGeneration(advancingPlayers, currentEvent);
                    } else {
                        alert('진출자 데이터를 가져올 수 없습니다.');
                    }
                })
                .catch(error => {
                    console.error('진출자 데이터 가져오기 오류:', error);
                    alert('진출자 데이터를 가져오는 중 오류가 발생했습니다.');
                });
            
            return; // 비동기 처리로 인해 여기서 함수 종료
        }
        
        // 다음 라운드 생성 계속 함수
        function continueNextRoundGeneration(advancingPlayers, currentEvent) {
            console.log('continueNextRoundGeneration 시작:', {advancingPlayers, currentEvent});
            
            if (advancingPlayers.length === 0) {
                alert('진출자가 없습니다. 집계 결과를 확인해주세요.');
                return;
            }
            
            // 전체 참여 팀 수 계산 (API에서 가져온 total_participants 사용)
            let totalTeams = window.totalParticipants || 0;
            console.log('window.totalParticipants:', window.totalParticipants);
            console.log('API에서 가져온 전체 참가자 수:', totalTeams);
            
            if (totalTeams === 0) {
                // API에서 가져온 값이 없으면 현재 이벤트의 참가자 수 사용
                totalTeams = advancingPlayers.length;
                console.log('API 값이 없어서 현재 진출자 수 사용:', totalTeams);
            }
            
            // getNextRoundInfo 함수를 사용하여 다음 라운드 정보 가져오기
            const nextRoundInfo = getNextRoundInfo(currentEvent);
            console.log('continueNextRoundGeneration: getNextRoundInfo 결과:', nextRoundInfo);
            
            let nextEventNumber = parseInt(currentEvent.no) + 1; // 기본값
            let nextEventName = currentEvent.desc.replace(/Round \d+/, 'Semi-Final').replace(/Final/, 'Semi-Final');
            
            if (nextRoundInfo && nextRoundInfo !== '') {
                // "30번 Semi-Final" 형태에서 번호와 라운드 추출
                const match = nextRoundInfo.match(/(\d+)번\s+(.+)/);
                if (match) {
                    nextEventNumber = parseInt(match[1]);
                    nextEventName = `${currentEvent.desc} ${match[2]}`;
                    console.log('continueNextRoundGeneration: getNextRoundInfo에서 추출한 정보:', nextEventNumber, nextEventName);
                } else {
                    // 번호만 추출
                    const numberMatch = nextRoundInfo.match(/(\d+)번/);
                    if (numberMatch) {
                        nextEventNumber = parseInt(numberMatch[1]);
                        console.log('continueNextRoundGeneration: getNextRoundInfo에서 번호만 추출:', nextEventNumber);
                    }
                }
            } else {
                // next_event 정보가 있으면 사용
                if (currentEvent.next_event && currentEvent.next_event !== '') {
                    nextEventNumber = parseInt(currentEvent.next_event);
                    console.log('continueNextRoundGeneration: currentEvent.next_event:', currentEvent.next_event);
                    console.log('continueNextRoundGeneration: next_event 번호로 설정:', nextEventNumber);
                    
                    // next_event로 다음 라운드 정보 찾기
                    let foundNextEvent = false;
                    for (const group of groupData) {
                        for (const event of group.events) {
                            if (event.no === currentEvent.next_event) {
                                nextEventName = `${event.desc} ${event.round}`;
                                console.log('continueNextRoundGeneration: next_event 정보로 다음 라운드 발견:', nextEventNumber, nextEventName);
                                foundNextEvent = true;
                                break;
                            }
                        }
                        if (foundNextEvent) break;
                    }
                    
                    if (!foundNextEvent) {
                        console.log('continueNextRoundGeneration: next_event로 이벤트를 찾지 못함, 기본 이름 사용');
                    }
                } else {
                    // next_event가 없으면 기존 로직 사용
                    console.log('continueNextRoundGeneration: next_event 정보 없음, 기존 로직 사용');
                    const currentRound = currentEvent.round;
                    if (currentRound.includes('Round 1')) {
                        nextEventName = currentEvent.desc.replace('Round 1', 'Semi-Final');
                    } else if (currentRound.includes('Semi-Final')) {
                        nextEventName = currentEvent.desc.replace('Semi-Final', 'Final');
                    }
                }
            }
            
            console.log('다음 이벤트:', nextEventNumber, nextEventName);
            
            // 모달용 자동 찾은 다음 라운드 정보
            const autoNextRound = getNextRoundInfo(currentEvent);
            console.log('모달용 자동 찾은 다음 라운드:', autoNextRound);
            
            // 바로 다음 라운드 생성 실행
            createNextRoundDirectly(nextEventNumber, nextEventName, advancingPlayers, totalTeams);
        }
        
        
        // 바로 다음 라운드 생성 실행
        function createNextRoundDirectly(nextEventNumber, nextEventName, advancingPlayers, totalTeams) {
            console.log('createNextRoundDirectly: 바로 다음 라운드 생성 시작:', {nextEventNumber, nextEventName, advancingPlayers, totalTeams});
            console.log('createNextRoundDirectly: 전송할 데이터:', {
                current_event_id: selectedEvent,
                next_event_number: nextEventNumber,
                next_event_name: nextEventName,
                advancing_players: advancingPlayers
            });
            
            // 서버에 다음 라운드 생성 요청
            fetch('create_next_round.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    current_event_id: selectedEvent,
                    next_event_number: nextEventNumber,
                    next_event_name: nextEventName,
                    advancing_players: advancingPlayers
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // 현재 채점 파일을 저장소에 저장
                    saveCurrentScoringFile(selectedEvent);
                    
                    alert(`${nextEventNumber}번 라운드 생성 완료!\n이벤트: ${nextEventName}\n진출자: ${advancingPlayers.length}명\n채점 파일이 저장되었습니다.`);
                    closeAggregationModal();
                    
                    // 새로고침 없이 모달만 닫기
                } else {
                    alert('다음 라운드 생성 실패: ' + result.error);
                }
            })
            .catch(error => {
                console.error('다음 라운드 생성 오류:', error);
                alert('다음 라운드 생성 중 오류가 발생했습니다.');
            });
        }
        
        // 현재 채점 파일을 저장소에 저장하는 함수
        function saveCurrentScoringFile(eventId) {
            console.log('채점 파일 저장 시작:', eventId);
            
            // 현재 이벤트의 채점 데이터를 가져와서 파일로 저장
            fetch(`get_recall_data.php?event_id=${eventId}&comp_id=<?php echo $comp_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    console.log('채점 데이터 받음:', data);
                    if (data.success) {
                        // 채점 파일 저장 요청
                        fetch('save_scoring_file.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                event_id: eventId,
                                comp_id: '<?php echo $comp_id; ?>',
                                scoring_data: data
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            console.log('채점 파일 저장 결과:', result);
                            if (result.success) {
                                console.log('채점 파일 저장 완료:', result.filename);
                                
                                // HTML 파일도 함께 저장
                                saveHtmlReport(eventId, data);
                            } else {
                                console.error('채점 파일 저장 실패:', result.error);
                            }
                        })
                        .catch(error => {
                            console.error('채점 파일 저장 오류:', error);
                        });
                    } else {
                        console.error('채점 데이터 가져오기 실패:', data.error);
                    }
                })
                .catch(error => {
                    console.error('채점 데이터 가져오기 오류:', error);
                });
        }
        
        // HTML 리포트 저장 함수
        function saveHtmlReport(eventId, data) {
            console.log('HTML 리포트 저장 시작:', eventId);
            
            // 현재 집계 결과 HTML 가져오기
            const aggregationContent = document.querySelector('#aggregationContent');
            if (!aggregationContent) {
                console.error('집계 결과를 찾을 수 없습니다.');
                return;
            }
            
            const htmlContent = aggregationContent.innerHTML;
            // competition.php에서 찾는 형식으로 파일명 생성: Event_30_result.html
            const filename = `Event_${eventId}_result.html`;
            
            // HTML 파일 저장 요청
            fetch('save_html_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_id: eventId,
                    comp_id: '<?php echo $comp_id; ?>',
                    html_content: htmlContent,
                    filename: filename,
                    competition_data: data
                })
            })
            .then(response => response.json())
            .then(result => {
                console.log('HTML 리포트 저장 결과:', result);
                if (result.success) {
                    console.log('HTML 리포트 저장 완료:', result.filename);
                } else {
                    console.error('HTML 리포트 저장 실패:', result.error);
                }
            })
            .catch(error => {
                console.error('HTML 리포트 저장 오류:', error);
            });
        }
        
        // 이벤트 데이터 새로고침 함수
        function refreshEventData() {
            console.log('이벤트 데이터 새로고침 시작');
            
            // 현재 선택된 이벤트 저장
            const currentSelectedEvent = selectedEvent;
            
            // 이벤트 데이터 다시 로드
            fetch('get_events.php?comp_id=<?php echo $comp_id; ?>')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // groupData 업데이트
                        groupData = data.groups;
                        events = data.events;
                        
                        // 이벤트 카드 다시 렌더링
                        renderEventCards();
                        
                        // 이전에 선택된 이벤트가 있으면 다시 선택
                        if (currentSelectedEvent) {
                            selectedEvent = currentSelectedEvent;
                            // 해당 이벤트 카드에 선택 상태 표시
                            document.querySelectorAll('.event-card').forEach(card => {
                                card.classList.remove('selected');
                            });
                            const selectedCard = document.querySelector(`[data-group-id="${currentSelectedEvent}"]`);
                            if (selectedCard) {
                                selectedCard.classList.add('selected');
                            }
                        }
                        
                        console.log('이벤트 데이터 새로고침 완료');
                    } else {
                        console.error('이벤트 데이터 새로고침 실패:', data.error);
                    }
                })
                .catch(error => {
                    console.error('이벤트 데이터 새로고침 오류:', error);
                    // 오류 발생 시 페이지 새로고침으로 대체
                    console.log('오류로 인해 페이지를 새로고침합니다.');
                    location.reload();
                });
        }
        
        // 다음 라운드 생성 버튼 텍스트 업데이트
        function updateNextRoundButtonText() {
            const currentEvent = events.find(ev => (ev.detail_no || ev.no) === selectedEvent);
            if (!currentEvent) {
                console.log('updateNextRoundButtonText: 현재 이벤트를 찾을 수 없음');
                return;
            }
            
            console.log('updateNextRoundButtonText: 현재 이벤트:', currentEvent);
            console.log('updateNextRoundButtonText: next_event 정보:', currentEvent.next_event);
            
            // getNextRoundInfo 함수를 사용하여 다음 라운드 정보 가져오기
            const nextRoundInfo = getNextRoundInfo(currentEvent);
            console.log('updateNextRoundButtonText: getNextRoundInfo 결과:', nextRoundInfo);
            
            let nextEventNumber = parseInt(currentEvent.no) + 1; // 기본값
            
            if (nextRoundInfo && nextRoundInfo !== '') {
                // "30번 Semi-Final" 형태에서 번호 추출
                const match = nextRoundInfo.match(/(\d+)번/);
                if (match) {
                    nextEventNumber = parseInt(match[1]);
                    console.log('updateNextRoundButtonText: getNextRoundInfo에서 추출한 번호:', nextEventNumber);
                } else {
                    console.log('updateNextRoundButtonText: getNextRoundInfo에서 번호를 추출할 수 없음, 기본값 사용:', nextEventNumber);
                }
            } else {
                // next_event 정보가 있으면 사용
                if (currentEvent.next_event && currentEvent.next_event !== '') {
                    nextEventNumber = parseInt(currentEvent.next_event);
                    console.log('updateNextRoundButtonText: next_event 사용, 다음 이벤트 번호:', nextEventNumber);
                } else {
                    console.log('updateNextRoundButtonText: next_event 없음, 기본값 사용:', nextEventNumber);
                }
            }
            
            const nextRoundBtn = document.getElementById('nextRoundBtn');
            if (nextRoundBtn) {
                nextRoundBtn.textContent = `${nextEventNumber}번 라운드 생성`;
                console.log('updateNextRoundButtonText: 버튼 텍스트 업데이트 완료:', nextRoundBtn.textContent);
            } else {
                console.log('updateNextRoundButtonText: nextRoundBtn 요소를 찾을 수 없음');
            }
        }
        
        // 다음 라운드 생성 모달 표시
        function showNextRoundModal(nextEventNumber, nextEventName, advancingPlayers, totalTeams) {
            console.log('showNextRoundModal 함수 시작:', {nextEventNumber, nextEventName, advancingPlayers, totalTeams});
            console.log('totalTeams 값:', totalTeams, '타입:', typeof totalTeams);
            
            // totalTeams가 undefined이면 advancingPlayers.length를 사용
            if (totalTeams === undefined || totalTeams === null || totalTeams === 0) {
                totalTeams = advancingPlayers.length;
                console.log('totalTeams가 유효하지 않음, advancingPlayers.length 사용:', totalTeams);
            }
            
            // 현재 이벤트의 리콜 수 찾기 (진출할 수 있는 최대 인원)
            let currentEventRecallCount = 0;
            for (const group of groupData) {
                for (const event of group.events) {
                    if ((event.detail_no || event.no) === selectedEvent) {
                        currentEventRecallCount = event.recall_count || 0;
                        console.log('현재 이벤트 리콜 수 찾음:', currentEventRecallCount);
                        break;
                    }
                }
                if (currentEventRecallCount > 0) break;
            }
            
            // 기존 모달이 있으면 제거
            const existingModal = document.querySelector('.next-round-modal');
            if (existingModal) {
                existingModal.remove();
            }
            
            const modal = document.createElement('div');
            modal.className = 'next-round-modal';
            modal.innerHTML = `
                <div class="next-round-modal-content">
                    <div class="next-round-modal-header">
                        <h3>다음 라운드 생성 - 이벤트 ${nextEventNumber}</h3>
                        <button class="next-round-modal-close" onclick="closeNextRoundModal()">&times;</button>
                    </div>
                    <div class="next-round-modal-body">
                        <p><strong>이벤트명:</strong> ${nextEventName}</p>
                        <p><strong>리콜 수:</strong> ${currentEventRecallCount}명 (현재 라운드에서 진출할 수 있는 최대 인원)</p>
                        <div class="couple-count-section">
                            <h4>진출할 커플 수 설정</h4>
                            <div class="couple-count-input">
                                <label for="coupleCount">진출할 커플 수:</label>
                                <input type="number" id="coupleCount" value="${advancingPlayers.length}" 
                                       min="1" 
                                       onchange="updateCoupleCount()" class="couple-count-field">
                                <span class="couple-count-info">(자유 설정 가능)</span>
                            </div>
                        </div>
                        <!-- 진출자 목록은 제거 - 커플 수만 조정하면 됨 -->
                        <div class="next-round-actions">
                            <button onclick="createNextRound(${nextEventNumber}, '${nextEventName}')" 
                                    class="btn btn-primary">다음 라운드 생성</button>
                            <button onclick="closeNextRoundModal()" class="btn btn-secondary">취소</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            console.log('모달이 DOM에 추가되었습니다:', modal);
            
            // 전역 변수에 진출자 정보 저장
            window.advancingPlayersData = advancingPlayers;
            console.log('진출자 데이터 저장됨:', window.advancingPlayersData);
        }
        
        // 다음 라운드 모달 닫기
        function closeNextRoundModal() {
            const modal = document.querySelector('.next-round-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // 진출할 커플 수 업데이트
        function updateCoupleCount() {
            const coupleCount = parseInt(document.getElementById('coupleCount').value);
            
            // 최소값만 체크 (1 이상)
            if (coupleCount < 1) {
                document.getElementById('coupleCount').value = 1;
                return;
            }
            
            // 진출자 목록 테이블이 제거되었으므로 추가 처리 불필요
        }
        
        // recall 수 업데이트 함수 (모달 닫지 않음)
        function updateRecallCountWithoutRefresh(eventId, recallCount) {
            console.log('updateRecallCountWithoutRefresh 호출:', {eventId, recallCount});
            
            fetch('update_recall_count.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `group_id=${eventId}&recall_count=${recallCount}`
            })
            .then(response => {
                console.log('updateRecallCountWithoutRefresh 응답 상태:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('updateRecallCountWithoutRefresh 응답 텍스트:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        console.log('Recall 수 업데이트 성공:', recallCount);
                        // UI에서도 recall 수 업데이트
                        const recallElement = document.querySelector(`[data-group-id="${eventId}"] .recall-count-value`);
                        if (recallElement) {
                            recallElement.textContent = `${recallCount}명`;
                        }
                        // refreshEventData() 호출하지 않음 - 모달이 닫히지 않도록
                    } else {
                        console.error('Recall 수 업데이트 실패:', data.message || data.error);
                        alert('진출자 수 업데이트에 실패했습니다: ' + (data.message || data.error));
                    }
                } catch (e) {
                    console.error('JSON 파싱 오류:', e, '응답 텍스트:', text);
                    alert('진출자 수 업데이트 중 오류가 발생했습니다.');
                }
            })
            .catch(error => {
                console.error('Recall 수 업데이트 오류:', error);
                alert('진출자 수 업데이트 중 오류가 발생했습니다: ' + error.message);
            });
        }
        
        // recall 수 업데이트 함수
        function updateRecallCount(eventId, recallCount) {
            console.log('updateRecallCount 호출:', {eventId, recallCount});
            
            fetch('update_recall_count.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `group_id=${eventId}&recall_count=${recallCount}`
            })
            .then(response => {
                console.log('updateRecallCount 응답 상태:', response.status);
                return response.text(); // JSON 대신 텍스트로 먼저 받기
            })
            .then(text => {
                console.log('updateRecallCount 응답 텍스트:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        console.log('Recall 수 업데이트 성공:', recallCount);
                        // UI에서도 recall 수 업데이트
                        const recallElement = document.querySelector(`[data-group-id="${eventId}"] .recall-count-value`);
                        if (recallElement) {
                            recallElement.textContent = `${recallCount}명`;
                        }
                        
                        // 데이터 다시 가져와서 화면 새로고침
                        refreshEventData();
                    } else {
                        console.error('Recall 수 업데이트 실패:', data.message || data.error);
                    }
                } catch (e) {
                    console.error('JSON 파싱 오류:', e, '응답 텍스트:', text);
                }
            })
            .catch(error => {
                console.error('Recall 수 업데이트 오류:', error);
            });
        }
        
        // 다음 라운드 생성 실행
        function createNextRound(eventNumber, eventName) {
            if (!window.advancingPlayersData) {
                alert('진출자 정보를 찾을 수 없습니다.');
                return;
            }
            
            // 선택된 커플 수 가져오기
            const coupleCount = parseInt(document.getElementById('coupleCount').value);
            if (!coupleCount || coupleCount < 1) {
                alert('진출할 커플 수를 입력해주세요.');
                return;
            }
            
            // 현재 이벤트 정보 찾기
            const currentEvent = events.find(e => e.no == selectedEvent);
            if (!currentEvent) {
                alert('현재 이벤트 정보를 찾을 수 없습니다.');
                return;
            }
            
            // 다음 라운드 정보 자동 찾기
            const nextRoundInfo = getNextRoundInfo(currentEvent);
            let finalEventNumber = eventNumber;
            let finalEventName = eventName;
            
            if (nextRoundInfo) {
                // "30번 Semi-Final" 형식에서 번호와 라운드 추출
                const match = nextRoundInfo.match(/(\d+)번\s+(.+)/);
                if (match) {
                    finalEventNumber = parseInt(match[1]);
                    finalEventName = `${currentEvent.desc} ${match[2]}`;
                    console.log('자동 찾은 다음 라운드:', finalEventNumber, finalEventName);
                }
            }
            
            // 등번호만 추출 (등위와 상관없이)
            let playerNumbers = [];
            
            // 집계 결과에서 등번호만 추출
            const aggregationResult = document.querySelector('#aggregationContent .aggregation-results') || 
                                    document.querySelector('#aggregationContent') ||
                                    document.querySelector('.aggregation-results');
            
            if (aggregationResult) {
                console.log('집계 결과에서 등번호 추출 시작');
                
                // 모든 테이블을 찾아서 데이터가 있는 테이블 선택
                const allTables = aggregationResult.querySelectorAll('table');
                console.log('찾은 테이블 수:', allTables.length);
                
                let summaryTable = null;
                for (let i = 0; i < allTables.length; i++) {
                    const table = allTables[i];
                    const rows = table.querySelectorAll('tr');
                    console.log(`테이블 ${i} 행 수:`, rows.length);
                    if (rows.length > 1) { // 헤더 + 데이터 행이 있는 테이블
                        summaryTable = table;
                        console.log(`데이터가 있는 테이블 선택: 테이블 ${i}`);
                        break;
                    }
                }
                
                if (summaryTable) {
                    const rows = summaryTable.querySelectorAll('tr');
                    console.log('선택된 테이블 행 수:', rows.length);
                    
                    for (let i = 1; i < rows.length; i++) {
                        const row = rows[i];
                        const cells = row.querySelectorAll('td');
                        console.log(`행 ${i} 셀 수:`, cells.length);
                        
                        // 모든 셀의 내용을 확인
                        for (let j = 0; j < cells.length; j++) {
                            const cellText = cells[j]?.textContent?.trim();
                            console.log(`  셀 ${j}: "${cellText}"`);
                        }
                        
                        if (cells.length >= 3) {
                            const number = cells[2]?.textContent?.trim(); // 등번호는 3번째 컬럼
                            
                            if (number && !isNaN(parseInt(number))) {
                                playerNumbers.push(number);
                                console.log(`추가된 등번호: ${number}, 현재 총 ${playerNumbers.length}개`);
                            } else {
                                console.log(`등번호로 인식되지 않음: "${number}"`);
                            }
                        }
                    }
                }
            }
            
            // 요청된 수만큼만 등번호 선택
            const selectedNumbers = playerNumbers.slice(0, coupleCount);
            console.log(`선택된 등번호들:`, selectedNumbers);
            
            const players = selectedNumbers.map((number, index) => ({
                oldNumber: number,
                name: `선수${number}`, // 이름은 임시로 설정
                newNumber: (index + 1).toString().padStart(2, '0'), // 01, 02, 03... 순서대로
                rank: index + 1
            }));
            
            // 1. 등위 기반으로 진출자 추출 (리콜 수 업데이트 없이)
            console.log('등위 기반 진출자 추출:', {coupleCount, totalPlayers: players.length});
            
            // 서버에 다음 라운드 생성 요청
            const requestData = {
                eventNumber: finalEventNumber,
                eventName: finalEventName,
                players: players,
                comp_id: '20250913-001'
            };
            
            console.log('createNextRound 요청 데이터:', requestData);
            console.log('전달할 players 수:', players.length);
            console.log('eventNumber 타입:', typeof finalEventNumber, '값:', finalEventNumber);
            console.log('eventName 타입:', typeof finalEventName, '값:', finalEventName);
            console.log('players 배열:', players);
            
            fetch('create_next_round.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`이벤트 ${eventNumber}이 성공적으로 생성되었습니다.`);
                    closeNextRoundModal();
                    // 모달만 닫고 페이지는 새로고침하지 않음
                } else {
                    alert('다음 라운드 생성 실패: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('다음 라운드 생성 중 오류가 발생했습니다.');
            });
        }
</script>

<!-- 히트 확인 모달 -->
<div id="hitModalBg" class="modal-bg" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>히트 정보</h3>
            <button class="modal-close" onclick="closeHitModal()">&times;</button>
        </div>
        <div class="modal-body" id="hitModalBody">
            <div class="loading">히트 정보를 로딩 중입니다...</div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeHitModal()">닫기</button>
            <button class="btn-primary" onclick="printHits()">인쇄</button>
        </div>
    </div>
</div>

<!-- 상장 발급 모달 -->
<div id="awardModalBg" class="modal-bg" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>상장 발급</h3>
            <button class="modal-close" onclick="closeAwardModal()">&times;</button>
        </div>
        <div class="modal-body" id="awardModalBody">
            <div class="award-options">
                <h4>상장 종류 선택</h4>
                <div class="award-type-grid">
                    <button class="award-type-btn" onclick="generateAward('1st')">1위 상장</button>
                    <button class="award-type-btn" onclick="generateAward('2nd')">2위 상장</button>
                    <button class="award-type-btn" onclick="generateAward('3rd')">3위 상장</button>
                    <button class="award-type-btn" onclick="generateAward('finalist')">결승 진출</button>
                    <button class="award-type-btn" onclick="generateAward('participation')">참가상</button>
                </div>
                <div class="award-preview" id="awardPreview" style="display: none;">
                    <h4>상장 미리보기</h4>
                    <div id="awardContent"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAwardModal()">닫기</button>
            <button class="btn-primary" onclick="printAward()" id="printAwardBtn" style="display: none;">인쇄</button>
        </div>
    </div>
</div>

<!-- 집계 결과 모달 -->
<div id="aggregationModalBg" class="modal-bg" style="display: none;">
    <div class="modal-content aggregation-modal">
        <div class="modal-header">
            <h3>🏆 집계 결과</h3>
            <button class="modal-close" onclick="closeAggregationModal()">&times;</button>
        </div>
        <div class="modal-body" id="aggregationModalBody">
            <div class="loading" id="aggregationLoading">
                <div class="loading-spinner"></div>
                <div>집계 결과를 계산 중입니다...</div>
            </div>
            <div id="aggregationContent" style="display: none;">
                <!-- 집계 결과가 여기에 표시됩니다 -->
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAggregationModal()">닫기</button>
            <button class="btn-primary" onclick="printAggregation()" id="printAggregationBtn" style="display: none;">인쇄</button>
            <button class="btn-info" onclick="saveAggregationResult()" id="saveAggregationBtn" style="display: none;">파일 저장</button>
            <button class="btn-success" onclick="generateNextRound()" id="nextRoundBtn" style="display: none;">다음 라운드 생성</button>
        </div>
    </div>
</div>

</body>
</html>
