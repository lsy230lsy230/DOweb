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
        include 'final_aggregation_api.php';
        $aggregation_data = ob_get_clean();
        
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
        $recall = $cols[4] ?? '';
        $heats = $cols[14] ?? ''; // 히트는 15번째 컬럼 (인덱스 14)
        $dance_codes = [];
        // 6-10번째 컬럼의 숫자를 댄스 코드로 사용 (정확한 데이터)
        for ($i=6; $i<=10; $i++) {
            if (isset($cols[$i]) && is_numeric($cols[$i]) && $cols[$i] > 0) {
                $dance_codes[] = $cols[$i];
            }
        }
        // 댄스 코드를 실제 댄스명으로 변환
        $dance_names = [];
        foreach ($dance_codes as $code) {
            $dance_names[] = $dance_map_en[$code] ?? $code; // 매핑된 이름 또는 코드
        }
        
        $events[] = [
            'no' => $no,
            'desc' => $desc,
            'round' => $roundtype,
            'panel' => $panel,
            'recall' => $recall,
            'heats' => $heats,
            'dances' => $dance_codes,
            'dance_names' => $dance_names,
            'detail_no' => $cols[13] ?? '' // 14번째 컬럼에서 detail_no 읽기
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
foreach ($events as $event) {
    $group_key = $event['detail_no'] ? $event['no'] : $event['no'];
    
    if (!isset($grouped_events[$group_key])) {
        $grouped_events[$group_key] = [
            'group_no' => $group_key,
            'group_name' => $event['desc'],
            'events' => [],
            'is_multi' => false
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
        }
        
        .left-panel {
            width: 350px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .right-panel {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
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
            color: #2c3e50;
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
        }
        
        .event-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
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
        
        .event-card-left {
            flex: 1;
            padding: 15px;
            border-right: 1px solid #dee2e6;
            background: #fafbfc;
        }
        
        .event-card-right {
            flex: 1;
            padding: 15px;
            background: white;
        }
        
        .event-card-title {
            font-size: 14px;
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.4;
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
        }
        
        .judges-header {
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .event-title {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
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
                }
        
        .players-header {
            font-size: 11px;
                    font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
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
            max-height: 200px;
            overflow-y: auto;
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
                    <?php foreach ($group['events'] as $event): ?>
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
        let expandedGroups = new Set();
        let completedGroups = new Set();
        let hideCompleted = false;
        let currentEventForPlayerModal = null;
        let currentPlayers = [];
        let disabledJudgesByEvent = {};
        
        function toggleGroup(groupNo) {
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
        
        function toggleGroupComplete(groupNo) {
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
            
            // 숨김 모드가 활성화되어 있으면 업데이트
            if (hideCompleted) {
                updateGroupVisibility();
            }
        }
        
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
            
            const event = group.events.find(e => (e.detail_no || e.no) === eventId);
            console.log('Found event:', event);
            
            if (!event) {
                console.error('Event not found for eventId:', eventId);
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
                                            📈 집계
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
                // 싱글 이벤트인 경우 기존 상세 정보 표시
                content += `
                    <div class="group-info-header">
                        <div>
                            <div class="group-title">싱글 이벤트 상세 정보</div>
                            <div class="group-subtitle">
                                <strong>패널:</strong> ${event.panel || 'N/A'} | 
                                <strong>댄스:</strong> 
                                <span class="dance-sequence">
                                    ${event.dance_names ? event.dance_names.join(' → ') : (event.dances ? event.dances.join(' → ') : 'N/A')}
                                </span>
</div>
</div>
</div>
                    <div class="single-event-view">
                        <div class="event-details">
                            <div class="detail-card">
                                <div class="detail-title">이벤트 정보</div>
                                <div class="detail-content">
                                    <strong>이벤트 번호:</strong> ${eventId}<br>
                                    <strong>라운드:</strong> ${event.round}<br>
                                    <strong>카테고리:</strong> ${group.group_name}
                                </div>
                            </div>
                            
                            <div class="detail-card">
                                <div class="detail-title">그룹 정보</div>
                                <div class="detail-content">
                                    <strong>그룹 번호:</strong> ${groupId}<br>
                                    <strong>총 이벤트:</strong> ${group.events.length}개<br>
                                    <strong>타입:</strong> 싱글 이벤트
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-info" onclick="viewScores('${eventId}')">
                                📊 점수 보기
                            </button>
                            <button class="btn btn-success" onclick="openAggregation('${eventId}')">
                                📈 결과 집계
                            </button>
                            <button class="btn btn-warning" onclick="openAwards('${eventId}')">
                                🏆 상장 발급
                            </button>
                        </div>
                </div>
            `;
            }
            
            rightContent.innerHTML = content;
            
            // 실시간 업데이트는 startJudgeStatusMonitoring에서 처리
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
                        const resultUrl = `${baseUrl}/comp/results_reports/${compId}/Event_${eventId}/final_result.html`;
                        console.log('결승 집계 성공, 결과 파일로 이동:', resultUrl);
                        
                        const newWindow = window.open(resultUrl, '_blank', 'width=1200,height=900,scrollbars=yes,resizable=yes');
                        
                        if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                            alert('팝업이 차단되었습니다. 브라우저 설정에서 팝업을 허용해주세요.');
                        } else {
                            console.log('결승 결과 창이 열렸습니다.');
                            // 성공 메시지 표시
                            setTimeout(() => {
                                const successMsg = document.createElement('div');
                                successMsg.innerHTML = `
                                    <div style="position: fixed; top: 20px; right: 20px; background: #27ae60; color: white; 
                                         padding: 15px 20px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                                         z-index: 10000; font-family: 'Noto Sans KR';">
                                        ✅ 결승 결과가 성공적으로 생성되었습니다!
                                    </div>
                                `;
                                document.body.appendChild(successMsg);
                                setTimeout(() => {
                                    if (successMsg.parentNode) {
                                        document.body.removeChild(successMsg);
                                    }
                                }, 3000);
                            }, 500);
                        }
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
        
        function getDanceSequenceDisplay(danceSequence) {
            if (!danceSequence || danceSequence.length === 0) return 'N/A';
            
            return danceSequence.map(item => {
                const typeLabel = item.type === 'common' ? '(공동)' : 
                                 item.type === 'individual' ? '(개별)' : '';
                return `${item.dance_name || item.dance}${typeLabel}`;
            }).join(' → ');
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
            const formData = new FormData();
            formData.append('comp_id', compId);
            formData.append('event_no', currentEventForPlayerModal);
            formData.append('players', JSON.stringify(currentPlayers));
            
            fetch('https://www.danceoffice.net/comp/save_players.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('선수 목록이 저장되었습니다.');
                    closePlayerModal();
                    // 카드 새로고침
                    if (selectedEvent) {
                        updateRightPanel(selectedEvent, selectedGroup);
                    }
                } else {
                    alert('저장 중 오류가 발생했습니다: ' + (data.message || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('Error saving players:', error);
                alert('저장 중 오류가 발생했습니다.');
            });
        }
        
        // 심사위원 제외/포함 토글
        function toggleAdjudicator(eventNo, judgeCode) {
            if (!disabledJudgesByEvent[eventNo]) {
                disabledJudgesByEvent[eventNo] = [];
            }
            const arr = disabledJudgesByEvent[eventNo];
            const idx = arr.indexOf(judgeCode);
            if (idx === -1) {
                arr.push(judgeCode);
            } else {
                arr.splice(idx, 1);
            }
            
            // 해당 이벤트의 카드 새로고침
            if (selectedEvent === eventNo) {
                updateRightPanel(selectedEvent, selectedGroup);
            }
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
</script>
</body>
</html>
