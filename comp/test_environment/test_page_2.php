<?php
// 테스트 페이지 2: 고급 멀티 이벤트 탭 구조 (실제 데이터 연동)
$comp_id = '20250913-001';
$data_dir = __DIR__ . "/../data/$comp_id";

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
        $events[] = [
            'no' => $no,
            'desc' => $desc,
            'round' => $roundtype,
            'panel' => $panel,
            'recall' => $recall,
            'heats' => $heats,
            'dances' => $dance_codes,
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

// 이벤트별 심사위원 상태 확인 함수
function getJudgeStatus($data_dir, $event_key, $dances, $panel_map, $adjudicator_dict) {
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
        $event['players'] = $players_by_event[$event_key] ?? [];
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
        if (count($event_list) > 1) {
            // 여러 이벤트에서 공통으로 사용되는 댄스
            $common_dances[] = [
                'dance' => $dance,
                'events' => $event_list,
                'type' => 'common'
            ];
        } else {
            // 개별 이벤트에서만 사용되는 댄스
            $individual_dances[] = [
                'dance' => $dance,
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
                $group['dance_sequence'][] = [
                    'dance' => $dance,
                    'events' => [$group['events'][0]['detail_no'] ?: $group['events'][0]['no']],
                    'type' => 'single'
                ];
            }
        }
    }
}

function extractCategory($desc) {
    if (strpos($desc, '솔로 일반부') !== false) return '솔로 일반부';
    if (strpos($desc, '솔로 초등부') !== false) return '솔로 초등부';
    if (strpos($desc, '솔로 중등부') !== false) return '솔로 중등부';
    if (strpos($desc, '솔로 유치부') !== false) return '솔로 유치부';
    if (strpos($desc, '초등부') !== false) return '초등부';
    if (strpos($desc, '유치부') !== false) return '유치부';
    return '기타';
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>테스트 페이지 2 - 멀티 이벤트 탭 구조</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .right-title {
            font-size: 18px;
            font-weight: bold;
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
        }
        
        .group-subtitle {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
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
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
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
        
        .event-card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .event-card-number {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .event-card-status {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: bold;
        }
        
        .event-card-body {
            display: flex;
            min-height: 200px;
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
            font-size: 12px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
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
            gap: 6px;
            max-height: 140px;
            overflow-y: auto;
        }
        
        .judge-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 11px;
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
        }
        
        .judge-progress {
            font-size: 10px;
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
            max-height: 120px;
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
        
        .event-card-btn-awards:hover {
            background: #e0a800;
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
    </style>
</head>
<body>
    <div class="container">
        <!-- 왼쪽 이벤트 리스트 패널 -->
        <div class="left-panel">
            <div class="panel-header">
                📋 이벤트 리스트
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
                    <span class="group-toggle">▶</span>
                </div>
                
                <div class="event-list" id="group-<?=h($group['group_no'])?>">
                    <?php foreach ($group['events'] as $event): ?>
                    <div class="event-item" 
                         data-event="<?=h($event['detail_no'] ?: $event['no'])?>"
                         data-group="<?=h($group['group_no'])?>"
                         onclick="selectEvent('<?=h($event['detail_no'] ?: $event['no'])?>', '<?=h($group['group_no'])?>', this)">
                        <div class="event-info">
                            <div class="event-number">
                                <?=h($event['detail_no'] ?: $event['no'])?>
                            </div>
                            <div class="event-desc">
                                <?=h($event['desc'])?>
                            </div>
                            <?php if (!empty($event['dances'])): ?>
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
            <div class="right-header">
                <div>
                    <div class="right-title">이벤트 관리</div>
                    <div class="right-subtitle">선택된 이벤트의 상세 정보 및 관리</div>
                </div>
            </div>
            
            <div id="right-content">
                <div class="no-selection">
                    <h3>이벤트를 선택해주세요</h3>
                    <p>왼쪽에서 이벤트를 선택하면 여기에 상세 정보가 표시됩니다.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedEvent = null;
        let selectedGroup = null;
        let expandedGroups = new Set();
        
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
        
        function selectEvent(eventId, groupId, element) {
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
            
            // 오른쪽 패널 업데이트
            updateRightPanel(eventId, groupId);
        }
        
        function updateRightPanel(eventId, groupId) {
            const rightContent = document.getElementById('right-content');
            
            // 이벤트 그룹 정보 가져오기
            const groupData = <?=json_encode($event_groups)?>;
            const group = groupData[groupId];
            const event = group.events.find(e => (e.detail_no || e.no) === eventId);
            
            if (!event) return;
            
            const isMultiEvent = group.events.length > 1;
            
            let content = `
                <div class="right-header">
                    <div>
                        <div class="right-title">통합이벤트 ${groupId} (${group.group_name})</div>
                        <div class="right-subtitle">${isMultiEvent ? '멀티 이벤트' : '싱글 이벤트'} | 총 ${group.events.length}개 이벤트</div>
                    </div>
                </div>
            `;
            
            if (isMultiEvent) {
                // 멀티 이벤트인 경우 카드 그리드 표시
                content += `
                    <div class="group-info-header">
                        <div>
                            <div class="group-title">멀티 이벤트 상세 정보</div>
                            <div class="group-subtitle">
                                <strong>패널:</strong> ${group.events[0].panel || 'N/A'} | 
                                <strong>댄스 순서:</strong> 
                                <span class="dance-sequence dance-sequence-editable" 
                                      onclick="openDanceEditModal('${groupId}')"
                                      title="댄스 순서 수정">
                                    ${getDanceSequenceDisplay(group.dance_sequence)}
                                    <span class="dance-edit-icon">✏️</span>
                                </span>
                            </div>
                        </div>
                    </div>
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
                    const eventJudges = evt.judges || [];
                    const eventPlayers = evt.players || [];
                    
                    content += `
                        <div class="event-card ${isSelected ? 'selected' : ''}" 
                             onclick="selectEventFromCard('${evt.detail_no || evt.no}', '${groupId}')">
                            <div class="event-card-header">
                                <div class="event-card-number">${evt.detail_no || evt.no}</div>
                                <div class="event-card-status ${statusClass}">${evt.round}</div>
                            </div>
                            
                            <div class="event-card-body">
                                <!-- 왼쪽: 심사위원 리스트 -->
                                <div class="event-card-left">
                                    <div class="event-card-judges">
                                        <div class="judges-header">
                                            <span>심사위원 현황</span>
                                            <span class="judges-progress">
                                                ${eventJudges.filter(j => j.status === 'completed').length}/${eventJudges.length} 완료
                                            </span>
                                        </div>
                                        <div class="judges-list">
                                            ${eventJudges.map(judge => `
                                                <div class="judge-item judge-status-${judge.status}">
                                                    <div class="judge-info">
                                                        <div class="judge-dot judge-dot-${judge.status}"></div>
                                                        <span class="judge-name">${judge.code}</span>
                                                    </div>
                                                    <div class="judge-actions">
                                                        <div class="judge-progress">
                                                            ${judge.completed}/${judge.total}
                                                        </div>
                                                        <button class="judge-btn judge-btn-edit" 
                                                                onclick="event.stopPropagation(); openJudgeScoring('${evt.detail_no || evt.no}', '${judge.code}')"
                                                                title="채점하기">
                                                            ✏️
                                                        </button>
                                                        <button class="judge-btn judge-btn-view" 
                                                                onclick="event.stopPropagation(); viewJudgeScores('${evt.detail_no || evt.no}', '${judge.code}')"
                                                                title="점수보기">
                                                            👁️
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
                                            <span class="event-card-detail-value">${evt.dances ? evt.dances.join(', ') : 'N/A'}</span>
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
                                                    <div class="player-name">${player.display_name}</div>
                                                    <div class="player-gender">
                                                        ${player.type === 'couple' ? '커플' : '싱글'}
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                    
                                    <div class="event-card-actions">
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
                                    ${event.dances ? event.dances.join(' → ') : 'N/A'}
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
            alert(`심사위원 ${judgeCode} 채점 패널 열기: ${eventId}`);
            // 실제 구현: window.open(`judge_scoring.php?comp_id=${compId}&event_no=${eventId}&judge_code=${judgeCode}`, '_blank');
        }
        
        function viewJudgeScores(eventId, judgeCode) {
            alert(`심사위원 ${judgeCode} 점수 보기: ${eventId}`);
            // 실제 구현: window.open(`view_scores.php?comp_id=${compId}&event_no=${eventId}&judge_code=${judgeCode}`, '_blank');
        }
        
        function viewScores(eventId) {
            alert(`전체 점수 보기: ${eventId}`);
            // 실제 구현: window.open(`view_scores.php?comp_id=${compId}&event_no=${eventId}`, '_blank');
        }
        
        function openAggregation(eventId) {
            alert(`결과 집계: ${eventId}`);
            // 실제 구현: window.open(`final_aggregation_api.php?comp_id=${compId}&event_no=${eventId}`, '_blank');
        }
        
        function openAwards(eventId) {
            alert(`상장 발급: ${eventId}`);
            // 실제 구현: window.open(`awards.php?comp_id=${compId}&event_no=${eventId}`, '_blank');
        }
        
        function getDanceSequenceDisplay(danceSequence) {
            if (!danceSequence || danceSequence.length === 0) return 'N/A';
            
            return danceSequence.map(item => {
                const typeLabel = item.type === 'common' ? '(공동)' : 
                                 item.type === 'individual' ? '(개별)' : '';
                return `${item.dance}${typeLabel}`;
            }).join(' → ');
        }
        
        function openDanceEditModal(groupId) {
            const groupData = <?=json_encode($event_groups)?>;
            const group = groupData[groupId];
            
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
                                    <div class="dance-name">${item.dance}</div>
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
        
        // 채점 관련 함수들
        function openJudgeScoring(eventId, judgeCode) {
            if (!eventId || !judgeCode) {
                alert('이벤트와 심사위원을 선택해주세요.');
                return;
            }
            
            // 새 창에서 채점 패널 열기
            const scoringUrl = `../live_panel.php?event=${eventId}&judge=${judgeCode}`;
            window.open(scoringUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
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
            
            // 새 창에서 점수 보기
            const scoresUrl = `../live_panel.php?view=scores&event=${eventId}`;
            window.open(scoresUrl, '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
        }
        
        function openAggregation(eventId) {
            if (!eventId) {
                alert('이벤트를 선택해주세요.');
                return;
            }
            
            // 새 창에서 집계 보기
            const aggUrl = `../live_panel.php?view=aggregation&event=${eventId}`;
            window.open(aggUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        }
        
        function openAwards(eventId) {
            if (!eventId) {
                alert('이벤트를 선택해주세요.');
                return;
            }
            
            // 새 창에서 상장 발급
            const awardsUrl = `../live_panel.php?view=awards&event=${eventId}`;
            window.open(awardsUrl, '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
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
            fetch(`../api/get_judge_status.php?event=${eventId}`)
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
        
        // 페이지 로드 시 첫 번째 그룹 확장 및 실시간 업데이트 시작
        document.addEventListener('DOMContentLoaded', function() {
            const firstGroup = document.querySelector('.event-group');
            if (firstGroup) {
                const groupNo = firstGroup.dataset.group;
                toggleGroup(groupNo);
            }
            
            // 실시간 업데이트 시작
            startRealTimeUpdates();
        });
    </script>
</body>
</html>
