<?php
header('Content-Type: application/json; charset=utf-8');

$comp_id = $_GET['comp_id'] ?? '20250913-001';
$event_no = $_GET['event_no'] ?? '';

if (!$event_no) {
    echo json_encode(['success' => false, 'error' => '이벤트 번호가 필요합니다.']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";

try {
    // 이벤트 정보 로드
    $runorder_file = "$data_dir/RunOrder_Tablet.txt";
    if (!file_exists($runorder_file)) {
        throw new Exception("RunOrder_Tablet.txt 파일을 찾을 수 없습니다.");
    }
    
    $events = [];
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 12) {
            $event = [
                'no' => trim($parts[0]),
                'desc' => trim($parts[1]),
                'round' => trim($parts[2]) ?: 'Final',
                'recall_count' => intval(trim($parts[3]) ?: 0), // 4번째 컬럼이 리콜 수
                'detail_no' => trim($parts[11]) ?: trim($parts[0]),
                'event_no' => trim($parts[0]),
                'dances' => array_slice($parts, 3, 8),
                'panel' => trim($parts[11]) ?: 'A'
            ];
            $events[] = $event;
        }
    }
    
    // 현재 이벤트 찾기
    $current_event = null;
    foreach ($events as $event) {
        if ($event['detail_no'] == $event_no || $event['no'] == $event_no || $event['event_no'] == $event_no) {
            $current_event = $event;
            break;
        }
    }
    
    if (!$current_event) {
        throw new Exception("이벤트를 찾을 수 없습니다: $event_no");
    }
    
    // 패널 정보 로드
    $panel_file = "$data_dir/panel_list.json";
    $panel_data = [];
    if (file_exists($panel_file)) {
        $panel_data = json_decode(file_get_contents($panel_file), true);
    }
    
    // 현재 이벤트의 패널에 해당하는 심사위원 찾기
    $current_panel = $current_event['panel'];
    $judges = [];
    foreach ($panel_data as $panel) {
        if ($panel['panel_code'] === $current_panel) {
            $judges[] = $panel['adj_code'];
        }
    }
    
    // 선수 정보 로드
    $players_file = "$data_dir/players_$event_no.txt";
    $players = [];
    if (file_exists($players_file)) {
        $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $players[] = trim($line);
        }
    }
    
    // 선수 이름 매핑 로드
    $player_names_file = "$data_dir/players.txt";
    $player_names = [];
    if (file_exists($player_names_file)) {
        $lines = file($player_names_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $player_names[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
    
    // 리콜 데이터 분석
    $player_recalls = [];
    $recall_files = glob("$data_dir/{$event_no}_*_*.adj");
    
    foreach ($players as $player_num) {
        $recall_count = 0;
        $recalling_judges = [];
        
        foreach ($recall_files as $file) {
            $filename = basename($file);
            // 파일명에서 심사위원 번호 추출 (예: 28_6_12.adj -> 12)
            if (preg_match('/_(\d+)\.adj$/', $filename, $matches)) {
                $judge_num = $matches[1];
                
                // 현재 패널의 심사위원인지 확인
                if (in_array($judge_num, $judges)) {
                    $content = file_get_contents($file);
                    $lines = explode("\n", trim($content));
                    
                    foreach ($lines as $line) {
                        $line = trim($line, '"');
                        if ($line === $player_num) {
                            $recall_count++;
                            $recalling_judges[] = $judge_num;
                            break;
                        }
                    }
                }
            }
        }
        
        $player_name = $player_names[$player_num] ?? "선수 $player_num";
        $player_recalls[] = [
            'player_number' => $player_num,
            'player_name' => $player_name,
            'recall_count' => $recall_count,
            'judges' => $recalling_judges
        ];
    }
    
    // 리콜 횟수로 정렬 (내림차순)
    usort($player_recalls, function($a, $b) {
        return $b['recall_count'] - $a['recall_count'];
    });
    
    // RunOrder_Tablet.txt에서 리콜 수 가져오기
    $recall_count = 0;
    foreach ($events as $event) {
        if ($event['detail_no'] == $event_no || $event['no'] == $event_no || $event['event_no'] == $event_no) {
            // RunOrder_Tablet.txt의 4번째 컬럼이 리콜 수
            $recall_count = intval($event['recall_count'] ?? 0);
            break;
        }
    }
    
    // 리콜 기준 계산 (RunOrder_Tablet.txt의 리콜 수 또는 심사위원 수의 절반 이상)
    $total_judges = count($judges);
    $recall_threshold = $recall_count > 0 ? $recall_count : ceil($total_judges / 2);
    
    // 진출 선수 필터링
    $advancing_players = array_filter($player_recalls, function($player) use ($recall_threshold) {
        return $player['recall_count'] >= $recall_threshold;
    });
    
    // 결과 반환
    $result = [
        'success' => true,
        'event_info' => $current_event,
        'total_judges' => $total_judges,
        'recall_count_from_file' => $recall_count,
        'recall_threshold' => $recall_threshold,
        'player_recalls' => $player_recalls,
        'advancing_players' => array_values($advancing_players),
        'judges' => $judges
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
