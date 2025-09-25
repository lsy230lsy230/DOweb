<?php
// JSON 출력을 위한 헤더 설정
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// 오류 출력 비활성화 (JSON 응답을 위해)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

if (empty($comp_id) || empty($event_no)) {
    echo json_encode(['error' => 'Missing parameters', 'debug' => [
        'comp_id' => $comp_id,
        'event_no' => $event_no,
        'get_params' => $_GET
    ]]);
    exit;
}

// 이벤트 정보 로드
$runorder_file = "data/{$comp_id}/RunOrder_Tablet.txt";
if (!file_exists($runorder_file)) {
    echo json_encode(['error' => 'RunOrder file not found']);
    exit;
}

$events = [];
$lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $parts = explode(",", $line);  // 쉼표로 분리
    if (count($parts) >= 14) {  // 충분한 컬럼이 있는지 확인
        $events[] = [
            'no' => trim($parts[0]),
            'desc' => trim($parts[1]),
            'round' => trim($parts[2]),
            'detail_no' => trim($parts[11]),  // 12번째 컬럼이 detail_no (패널)
            'event_no' => trim($parts[13]),   // 14번째 컬럼이 이벤트 번호 (1-1, 1-2 등)
            'dances' => array_filter(array_map('trim', array_slice($parts, 6, 5))), // 7-11번째 컬럼이 댄스
            'panel' => trim($parts[11])  // 12번째 컬럼이 패널
        ];
    }
}

// 현재 이벤트 찾기
$current_event = null;
error_log("Looking for event_no: " . $event_no);
error_log("Available events: " . json_encode($events));

foreach ($events as $event) {
    // event_no (1-1, 1-2 등) 또는 detail_no (SA, LA 등)로 매칭
    $event_key = $event['event_no'] ?: $event['detail_no'] ?: $event['no'];
    error_log("Checking event_key: " . $event_key . " against " . $event_no);
    if ($event_key == $event_no) {
        $current_event = $event;
        error_log("Found matching event: " . json_encode($event));
        break;
    }
}

if (!$current_event) {
    error_log("Event not found for event_no: " . $event_no);
    echo json_encode(['error' => 'Event not found', 'debug' => [
        'requested_event' => $event_no,
        'requested_event_type' => gettype($event_no),
        'available_events' => array_map(function($e) {
            return [
                'no' => $e['no'],
                'detail_no' => $e['detail_no'],
                'key' => $e['detail_no'] ? $e['detail_no'] : $e['no'],
                'desc' => $e['desc'],
                'round' => $e['round']
            ];
        }, $events)
    ]]);
    exit;
}

// 선수 정보 로드
$players_file = "data/{$comp_id}/players_{$event_no}.txt";
$players = [];
if (file_exists($players_file)) {
    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            // 선수 번호만 있는 경우
            $players[] = [
                'number' => $line,
                'male' => '',
                'female' => ''
            ];
        }
    }
}

// 심사위원 정보 로드
$adjudicators_file = "data/{$comp_id}/adjudicators.txt";
$adjudicators = [];
if (file_exists($adjudicators_file)) {
    $lines = file($adjudicators_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(",", $line);  // 쉼표로 분리
        if (count($parts) >= 2) {
            $adjudicators[] = [
                'code' => trim($parts[0]),
                'name' => trim($parts[1])
            ];
        }
    }
}
error_log("심사위원 수: " . count($adjudicators));

// 댄스별 채점 데이터 수집
$dance_results = [];
$dance_names = [
    '6' => 'Cha Cha Cha',
    '7' => 'Samba', 
    '8' => 'Rumba',
    '9' => 'Paso Doble',
    '10' => 'Jive'
];

foreach ($current_event['dances'] as $dance_code) {
    $dance_name = $dance_names[$dance_code] ?? $dance_code;
    $dance_results[$dance_code] = [
        'name' => $dance_name,
        'code' => $dance_code,
        'judge_scores' => [],
        'final_rankings' => []
    ];
    
    // 각 심사위원의 채점 파일에서 데이터 수집
    foreach ($adjudicators as $judge) {
        $score_file = "data/{$comp_id}/{$event_no}_{$dance_code}_{$judge['code']}.adj";
        if (file_exists($score_file)) {
            $content = file_get_contents($score_file);
            $lines = explode("\n", $content);
            
            $judge_scores = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^(\d+)\s+(\d+)$/', $line, $matches)) {
                    $player_no = $matches[1];
                    $rank = intval($matches[2]);
                    $judge_scores[$player_no] = $rank;
                }
            }
            
            if (!empty($judge_scores)) {
                $dance_results[$dance_code]['judge_scores'][$judge['code']] = $judge_scores;
            }
        }
    }
    
    // 스케이팅 시스템으로 댄스별 순위 계산
    $dance_results[$dance_code]['final_rankings'] = calculateSkatingRankings(
        $dance_results[$dance_code]['judge_scores'], 
        $players
    );
}

// 최종 순위 계산 (SUM of Places)
$final_rankings = calculateFinalRankings($dance_results, $players);

// 결과 반환
$result = [
    'event_info' => $current_event,
    'players' => $players,
    'adjudicators' => $adjudicators,
    'dance_results' => $dance_results,
    'final_rankings' => $final_rankings,
    'debug' => [
        'total_events' => count($events),
        'event_found' => $current_event ? 'yes' : 'no',
        'players_count' => count($players),
        'adjudicators_count' => count($adjudicators),
        'dance_results_count' => count($dance_results)
    ]
];

error_log("Final aggregation result: " . json_encode($result));
echo json_encode($result);

// 스케이팅 시스템으로 댄스별 순위 계산
function calculateSkatingRankings($judge_scores, $players) {
    $player_rankings = [];
    
    foreach ($players as $player) {
        $player_no = $player['number'];
        $rankings = [];
        
        // 각 심사위원이 부여한 순위 수집
        foreach ($judge_scores as $judge_code => $scores) {
            if (isset($scores[$player_no])) {
                $rankings[] = $scores[$player_no];
            }
        }
        
        if (!empty($rankings)) {
            // 스케이팅 시스템 계산
            $skating_data = calculateSkatingData($rankings);
            $player_rankings[$player_no] = $skating_data;
        }
    }
    
    // 순위 결정
    $ranked_players = [];
    foreach ($player_rankings as $player_no => $data) {
        $ranked_players[] = [
            'player_no' => $player_no,
            'skating_data' => $data
        ];
    }
    
    // 스케이팅 시스템 규칙에 따라 정렬
    usort($ranked_players, function($a, $b) {
        $data_a = $a['skating_data'];
        $data_b = $b['skating_data'];
        
        // 1위 수 비교
        if ($data_a['place_1'] != $data_b['place_1']) {
            return $data_b['place_1'] - $data_a['place_1'];
        }
        
        // 1&2위 수 비교
        if ($data_a['place_1_2'] != $data_b['place_1_2']) {
            return $data_b['place_1_2'] - $data_a['place_1_2'];
        }
        
        // 1to3위 수 비교
        if ($data_a['place_1to3'] != $data_b['place_1to3']) {
            return $data_b['place_1to3'] - $data_a['place_1to3'];
        }
        
        // 1to3 합계 비교 (낮은 합계가 우위)
        if ($data_a['sum_1to3'] != $data_b['sum_1to3']) {
            return $data_a['sum_1to3'] - $data_b['sum_1to3'];
        }
        
        // 1to4 비교
        if ($data_a['place_1to4'] != $data_b['place_1to4']) {
            return $data_b['place_1to4'] - $data_a['place_1to4'];
        }
        
        // 1to4 합계 비교
        if ($data_a['sum_1to4'] != $data_b['sum_1to4']) {
            return $data_a['sum_1to4'] - $data_b['sum_1to4'];
        }
        
        // 1to5 비교
        if ($data_a['place_1to5'] != $data_b['place_1to5']) {
            return $data_b['place_1to5'] - $data_a['place_1to5'];
        }
        
        // 1to5 합계 비교
        if ($data_a['sum_1to5'] != $data_b['sum_1to5']) {
            return $data_a['sum_1to5'] - $data_b['sum_1to5'];
        }
        
        // 1to6 비교
        if ($data_a['place_1to6'] != $data_b['place_1to6']) {
            return $data_b['place_1to6'] - $data_a['place_1to6'];
        }
        
        // 1to6 합계 비교
        return $data_a['sum_1to6'] - $data_b['sum_1to6'];
    });
    
    // 최종 순위 부여
    $final_rankings = [];
    for ($i = 0; $i < count($ranked_players); $i++) {
        $final_rankings[$ranked_players[$i]['player_no']] = $i + 1;
    }
    
    return $final_rankings;
}

// 스케이팅 데이터 계산
function calculateSkatingData($rankings) {
    $place_1 = 0;
    $place_1_2 = 0;
    $place_1to3 = 0;
    $place_1to4 = 0;
    $place_1to5 = 0;
    $place_1to6 = 0;
    
    $sum_1to3 = 0;
    $sum_1to4 = 0;
    $sum_1to5 = 0;
    $sum_1to6 = 0;
    
    foreach ($rankings as $rank) {
        if ($rank == 1) $place_1++;
        if ($rank <= 2) $place_1_2++;
        if ($rank <= 3) {
            $place_1to3++;
            $sum_1to3 += $rank;
        }
        if ($rank <= 4) {
            $place_1to4++;
            $sum_1to4 += $rank;
        }
        if ($rank <= 5) {
            $place_1to5++;
            $sum_1to5 += $rank;
        }
        if ($rank <= 6) {
            $place_1to6++;
            $sum_1to6 += $rank;
        }
    }
    
    return [
        'place_1' => $place_1,
        'place_1_2' => $place_1_2,
        'place_1to3' => $place_1to3,
        'place_1to4' => $place_1to4,
        'place_1to5' => $place_1to5,
        'place_1to6' => $place_1to6,
        'sum_1to3' => $sum_1to3,
        'sum_1to4' => $sum_1to4,
        'sum_1to5' => $sum_1to5,
        'sum_1to6' => $sum_1to6
    ];
}

// 최종 순위 계산 (SUM of Places)
function calculateFinalRankings($dance_results, $players) {
    $player_sums = [];
    
    foreach ($players as $player) {
        $player_no = $player['number'];
        $sum = 0;
        
        foreach ($dance_results as $dance_code => $dance_data) {
            if (isset($dance_data['final_rankings'][$player_no])) {
                $sum += $dance_data['final_rankings'][$player_no];
            }
        }
        
        $player_sums[$player_no] = $sum;
    }
    
    // SUM of Places로 정렬
    asort($player_sums);
    
    // 최종 순위 부여
    $final_rankings = [];
    $rank = 1;
    foreach ($player_sums as $player_no => $sum) {
        $final_rankings[] = [
            'player_no' => $player_no,
            'sum_of_places' => $sum,
            'final_rank' => $rank++
        ];
    }
    
    return $final_rankings;
}
?>
