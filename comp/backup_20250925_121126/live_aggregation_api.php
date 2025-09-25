<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 명령행에서 실행되는 경우와 웹에서 실행되는 경우 모두 처리
if (php_sapi_name() === 'cli') {
    // 명령행에서 실행되는 경우
    $comp_id = $argv[1] ?? '';
    $event_no = $argv[2] ?? '';
} else {
    // 웹에서 실행되는 경우
    $comp_id = $_GET['comp_id'] ?? '';
    $event_no = $_GET['event_no'] ?? '';
}

error_log("=== Live Aggregation API Called ===");
error_log("GET parameters: " . json_encode($_GET ?? []));
error_log("comp_id: '$comp_id', event_no: '$event_no'");

if (empty($comp_id) || empty($event_no)) {
    error_log("Missing parameters - comp_id: '$comp_id', event_no: '$event_no'");
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$data_dir = "data/{$comp_id}";
error_log("data_dir: $data_dir");

// 이벤트 정보 가져오기
error_log("Looking for event: $event_no");
$event_info = getEventInfo($data_dir, $event_no);
if (!$event_info) {
    error_log("Event not found: $event_no");
    echo json_encode(['success' => false, 'error' => 'Event not found']);
    exit;
}
error_log("Event found: " . json_encode($event_info));

// 댄스 목록 가져오기
$dances = $event_info['dances'];
if (empty($dances)) {
    echo json_encode(['success' => false, 'error' => 'No dances found for this event']);
    exit;
}

// 심사위원 목록 가져오기
$judges = getJudgesForEvent($data_dir, $event_no);
if (empty($judges)) {
    echo json_encode(['success' => false, 'error' => 'No judges found for this event']);
    exit;
}

// 선수 목록 가져오기
$players = getPlayersForEvent($data_dir, $event_no);
if (empty($players)) {
    echo json_encode(['success' => false, 'error' => 'No players found for this event']);
    exit;
}

// 전체 선수 정보 가져오기 (실제 선수명을 위해)
$all_players = [];
$players_file = "$data_dir/players.txt";
if (file_exists($players_file)) {
    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 3) {
            $all_players[$cols[0]] = [
                'male' => $cols[1] ?? '',
                'female' => $cols[2] ?? '',
            ];
        }
    }
}

// 집계 데이터 수집
$aggregation_data = collectAggregationData($data_dir, $event_no, $dances, $judges, $players, $all_players);

echo json_encode([
    'success' => true,
    'event_info' => $event_info,
    'judges' => $judges,
    'players' => $players,
    'all_players' => $all_players,
    'aggregation' => $aggregation_data
]);

function getEventInfo($data_dir, $event_no) {
    $runorder_file = "$data_dir/RunOrder_Tablet.txt";
    if (!file_exists($runorder_file)) {
        return null;
    }
    
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $cols = explode(',', $line);
        if (count($cols) >= 13) {
            $file_event_no = preg_replace('/\x{FEFF}/u', '', $cols[0] ?? '');
            $file_event_no = preg_replace('/\D+/', '', $file_event_no);
            $detail_no = trim($cols[13] ?? '');
            
            $match_event_no = !empty($detail_no) ? $detail_no : $file_event_no;
            
            // 디버그 로그 추가
            error_log("Event matching: file_event_no='$file_event_no', detail_no='$detail_no', match_event_no='$match_event_no', target='$event_no'");
            
            if ($match_event_no === $event_no) {
                $dances = [];
                for ($i = 6; $i <= 10; $i++) {
                    if (!empty($cols[$i]) && $cols[$i] !== '0') {
                        $dances[] = $cols[$i];
                    }
                }
                
                return [
                    'no' => $cols[0],
                    'detail_no' => $detail_no,
                    'name' => $cols[1],
                    'round' => $cols[2],
                    'dances' => $dances,
                    'panel' => $cols[11] ?? ''
                ];
            }
        }
    }
    
    return null;
}

function getJudgesForEvent($data_dir, $event_no) {
    $panel_file = "$data_dir/panel_list.json";
    if (!file_exists($panel_file)) {
        error_log("Panel file not found: $panel_file");
        return [];
    }
    
    $panel_data = json_decode(file_get_contents($panel_file), true);
    if (!$panel_data) {
        error_log("Failed to parse panel file: $panel_file");
        return [];
    }
    
    // 이벤트의 패널 코드 가져오기
    $event_info = getEventInfo($data_dir, $event_no);
    if (!$event_info || !isset($event_info['panel'])) {
        error_log("No panel info for event: $event_no");
        return [];
    }
    
    $panel_code = $event_info['panel'];
    error_log("Looking for judges in panel: $panel_code");
    
    // 해당 패널의 심사위원들 찾기
    $judges = [];
    foreach ($panel_data as $item) {
        if (isset($item['panel_code']) && $item['panel_code'] === $panel_code) {
            $judges[] = [
                'code' => $item['adj_code'],
                'name' => "Judge {$item['adj_code']}" // 실제 이름이 없으므로 코드로 대체
            ];
        }
    }
    
    error_log("Found " . count($judges) . " judges for panel: $panel_code");
    return $judges;
}

function getPlayersForEvent($data_dir, $event_no) {
    // live_panel.php와 동일한 방식으로 선수 정보 로드
    $players_file = "$data_dir/players_{$event_no}.txt";
    if (!file_exists($players_file)) {
        // BOM 등 비정상 문자가 끼어 생성된 파일을 탐색하여 보정
        foreach (glob($data_dir . "/players_*.txt") as $alt) {
            $base = basename($alt);
            $num = $base;
            $num = preg_replace('/^players_/u', '', $num);
            $num = preg_replace('/\.txt$/u', '', $num);
            $num = preg_replace('/\x{FEFF}/u', '', $num); // BOM 제거
            $num = preg_replace('/\D+/', '', $num); // 숫자만 남김
            
            if ($num === (string)$event_no) {
                $players_file = $alt;
                break;
            }
        }
    }
    
    if (!file_exists($players_file)) {
        error_log("Players file not found for event: $event_no");
        return [];
    }
    
    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $players = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode(',', $line);
        if (count($parts) >= 2) {
            $players[] = [
                'number' => trim($parts[0]),
                'name' => trim($parts[1])
            ];
        } else {
            // 등번호만 있는 경우
            $player_number = trim($line);
            if (!empty($player_number)) {
                $players[] = [
                    'number' => $player_number,
                    'name' => "선수 {$player_number}"
                ];
            }
        }
    }
    
    error_log("Loaded " . count($players) . " players for event: $event_no");
    return $players;
}

function collectAggregationData($data_dir, $event_no, $dances, $judges, $players, $all_players = []) {
    $player_scores = [];
    $judge_status = [];
    
    // 각 선수별 점수 초기화
    foreach ($players as $player) {
        $player_number = $player['number'];
        $player_name = $player['name'];
        
        // 전체 선수 정보에서 실제 선수명 가져오기
        if (isset($all_players[$player_number])) {
            $male = $all_players[$player_number]['male'] ?? '';
            $female = $all_players[$player_number]['female'] ?? '';
            if ($male && $female) {
                $player_name = "{$male} / {$female}";
            } elseif ($male) {
                $player_name = $male;
            } elseif ($female) {
                $player_name = $female;
            }
        }
        
        $player_scores[$player_number] = [
            'name' => $player_name,
            'total_recall' => 0,
            'dance_scores' => []
        ];
    }
    
    // 각 심사위원별 상태 초기화
    foreach ($judges as $judge) {
        $judge_status[$judge['code']] = [
            'name' => $judge['name'],
            'completed_dances' => 0,
            'total_dances' => count($dances),
            'status' => 'waiting'
        ];
    }
    
    // 각 댄스별로 .adj 파일 확인
    foreach ($dances as $dance) {
        foreach ($judges as $judge) {
            $adj_file = "$data_dir/{$event_no}_{$dance}_{$judge['code']}.adj";
            
            if (file_exists($adj_file)) {
                $judge_status[$judge['code']]['completed_dances']++;
                $judge_status[$judge['code']]['status'] = 'scoring';
                
                // .adj 파일에서 Recall 데이터 읽기
                $recall_data = file($adj_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($recall_data as $line) {
                    $player_number = trim($line, '"');
                    if (isset($player_scores[$player_number])) {
                        $player_scores[$player_number]['total_recall']++;
                        $player_scores[$player_number]['dance_scores'][$dance] = 
                            ($player_scores[$player_number]['dance_scores'][$dance] ?? 0) + 1;
                    }
                }
            }
        }
    }
    
    // 심사위원 상태 업데이트
    foreach ($judge_status as $code => &$status) {
        if ($status['completed_dances'] === 0) {
            $status['status'] = 'waiting';
        } elseif ($status['completed_dances'] < $status['total_dances']) {
            $status['status'] = 'scoring';
        } else {
            $status['status'] = 'completed';
        }
    }
    
    // 선수별 점수로 정렬
    uasort($player_scores, function($a, $b) {
        return $b['total_recall'] - $a['total_recall'];
    });
    
    return [
        'player_scores' => $player_scores,
        'judge_status' => $judge_status,
        'total_judges' => count($judges),
        'completed_judges' => count(array_filter($judge_status, function($status) {
            return $status['status'] === 'completed';
        })),
        'progress_rate' => round((count(array_filter($judge_status, function($status) {
            return $status['status'] === 'completed';
        })) / count($judges)) * 100, 1)
    ];
}
?>
