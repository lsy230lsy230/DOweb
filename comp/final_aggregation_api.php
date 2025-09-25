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
                if (preg_match('/^(\d+),(\d+)$/', $line, $matches)) {
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

// 결과 보고서 생성
generateProfessionalReport($result, $comp_id, $event_no);

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
    
    // SUM of Places로 정렬 (낮은 합계가 우위)
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

// 전문적인 결과 보고서 생성
function generateProfessionalReport($result, $comp_id, $event_no) {
    $event_info = $result['event_info'];
    $players = $result['players'];
    $adjudicators = $result['adjudicators'];
    $dance_results = $result['dance_results'];
    $final_rankings = $result['final_rankings'];
    
    // 댄스 이름 매핑
    $dance_names = [
        '2' => 'Tango',
        '6' => 'Cha Cha Cha',
        '7' => 'Samba', 
        '8' => 'Rumba',
        '9' => 'Paso Doble',
        '10' => 'Jive'
    ];
    
    // HTML 보고서 생성 - DanceOffice 스타일
    $html = '<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $event_no . '. ' . htmlspecialchars($event_info['desc']) . ' - ' . $event_info['round'] . ' | DanceOffice</title>
<meta name="description" content="DanceOffice - ' . $event_no . '. ' . htmlspecialchars($event_info['desc']) . ' - ' . $event_info['round'] . '">

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: "Noto Sans KR", "Malgun Gothic", sans-serif; 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    color: #333;
}
.container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    border-radius: 20px;
    overflow: hidden;
    margin-top: 20px;
    margin-bottom: 20px;
}
.header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 30px;
    text-align: center;
    position: relative;
}
.header::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.05"%3E%3Ccircle cx="30" cy="30" r="2"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
}
.header h1 {
    font-size: 2.5em;
    font-weight: 700;
    margin-bottom: 10px;
    position: relative;
    z-index: 1;
}
.header .subtitle {
    font-size: 1.2em;
    opacity: 0.9;
    position: relative;
    z-index: 1;
}
.event-info {
    background: #f8f9fa;
    padding: 25px;
    border-bottom: 3px solid #e9ecef;
}
.event-title {
    font-size: 1.8em;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.event-badge {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
    color: white;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.7em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.event-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.detail-item {
    background: white;
    padding: 15px;
    border-radius: 10px;
    border-left: 4px solid #3498db;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.detail-label {
    font-size: 0.9em;
    color: #7f8c8d;
    font-weight: 500;
    margin-bottom: 5px;
}
.detail-value {
    font-size: 1.1em;
    color: #2c3e50;
    font-weight: 600;
}
.results-section {
    padding: 30px;
}
.section-title {
    font-size: 1.5em;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-title::before {
    content: "🏆";
    font-size: 1.2em;
}
.results-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.results-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 15px;
    text-align: center;
    font-weight: 600;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.results-table td {
    padding: 20px 15px;
    text-align: center;
    border-bottom: 1px solid #ecf0f1;
    font-weight: 500;
}
.results-table tr:nth-child(even) {
    background: #f8f9fa;
}
.results-table tr:hover {
    background: #e3f2fd;
    transform: scale(1.01);
    transition: all 0.2s ease;
}
.rank-1 { background: linear-gradient(45deg, #ffd700, #ffed4e) !important; color: #333 !important; }
.rank-2 { background: linear-gradient(45deg, #c0c0c0, #e8e8e8) !important; color: #333 !important; }
.rank-3 { background: linear-gradient(45deg, #cd7f32, #daa520) !important; color: white !important; }
.player-name {
    font-weight: 600;
    color: #2c3e50;
    text-align: left;
}
.judge-score {
    font-weight: 600;
    font-size: 1.1em;
}
.total-points {
    font-weight: 700;
    font-size: 1.2em;
    color: #e74c3c;
}
.adjudicators-section {
    background: #f8f9fa;
    padding: 30px;
    border-top: 3px solid #e9ecef;
}
.adjudicators-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 20px;
}
.adjudicator-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s ease;
}
.adjudicator-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.adjudicator-code {
    background: linear-gradient(45deg, #3498db, #2980b9);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1em;
}
.adjudicator-name {
    font-weight: 600;
    color: #2c3e50;
}
.footer {
    background: #2c3e50;
    color: white;
    padding: 25px;
    text-align: center;
    font-size: 0.9em;
}
.footer a {
    color: #3498db;
    text-decoration: none;
}
.footer a:hover {
    text-decoration: underline;
}
@media (max-width: 768px) {
    .container { margin: 10px; border-radius: 15px; }
    .header h1 { font-size: 2em; }
    .event-details { grid-template-columns: 1fr; }
    .results-table { font-size: 0.9em; }
    .results-table th, .results-table td { padding: 15px 10px; }
    .adjudicators-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="container">
<div class="header">
    <h1>2025 서초구청장배 댄스스포츠 대회</h1>
    <div class="subtitle">DanceOffice Competition Management System</div>
</div>
<div class="event-info">
    <div class="event-title">
        <span class="event-badge">' . $event_info['round'] . '</span>
        ' . $event_no . '. ' . htmlspecialchars($event_info['desc']) . '
    </div>
    <div class="event-details">
        <div class="detail-item">
            <div class="detail-label">이벤트 번호</div>
            <div class="detail-value">' . $event_no . '</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">라운드</div>
            <div class="detail-value">' . $event_info['round'] . '</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">패널</div>
            <div class="detail-value">' . $event_info['panel'] . '</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">댄스</div>
            <div class="detail-value">' . implode(', ', array_map(function($code) use ($dance_names) { return $dance_names[$code] ?? $code; }, $event_info['dances'])) . '</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">참가자 수</div>
            <div class="detail-value">' . count($players) . '명</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">심사위원 수</div>
            <div class="detail-value">' . count($adjudicators) . '명</div>
        </div>
    </div>
</div>
<div class="results-section">
    <div class="section-title">최종 결과</div>
    <table class="results-table">';

    // 결과 테이블 헤더
    $html .= '<thead><tr>
<th>순위</th>
<th>번호</th>
<th>선수명</th>';

    // 심사위원 컬럼 헤더
    foreach ($adjudicators as $index => $judge) {
        $html .= '<th>' . $judge['code'] . '</th>';
    }

    $html .= '<th>총점</th></tr></thead><tbody>';

    // 결과 행들
    foreach ($final_rankings as $index => $ranking) {
        $player_no = $ranking['player_no'];
        $place = $ranking['final_rank'];
        $points = $ranking['sum_of_places'];
        
        // 선수 정보 찾기
        $player_info = null;
        foreach ($players as $player) {
            if ($player['number'] == $player_no) {
                $player_info = $player;
                break;
            }
        }
        
        $player_name = $player_info ? ($player_info['male'] . ($player_info['female'] ? ' & ' . $player_info['female'] : '')) : "선수 " . $player_no;
        if (empty(trim($player_name))) {
            $player_name = "선수 " . $player_no;
        }
        
        $rank_class = '';
        if ($place == 1) $rank_class = 'rank-1';
        elseif ($place == 2) $rank_class = 'rank-2';
        elseif ($place == 3) $rank_class = 'rank-3';
        
        $html .= '<tr class="' . $rank_class . '">
<td><strong>' . $place . '</strong></td>
<td><strong>' . $player_no . '</strong></td>
<td class="player-name">' . htmlspecialchars($player_name) . '</td>';

        // 각 심사위원의 채점 결과
        foreach ($adjudicators as $judge) {
            $judge_code = $judge['code'];
            $score = '';
            
            // 해당 심사위원의 채점 찾기
            foreach ($dance_results as $dance_code => $dance_data) {
                if (isset($dance_data['judge_scores'][$judge_code][$player_no])) {
                    $score = $dance_data['judge_scores'][$judge_code][$player_no];
                    break;
                }
            }
            
            $html .= '<td class="judge-score">' . $score . '</td>';
        }

        $html .= '<td class="total-points">' . $points . '</td></tr>';
    }

    $html .= '</tbody></table></div>';

    // 심사위원 정보
    $html .= '<div class="adjudicators-section">
    <div class="section-title">심사위원</div>
    <div class="adjudicators-grid">';

    // 심사위원 카드들
    foreach ($adjudicators as $judge) {
        $html .= '<div class="adjudicator-card">
            <div class="adjudicator-code">' . $judge['code'] . '</div>
            <div class="adjudicator-name">' . htmlspecialchars($judge['name']) . '</div>
        </div>';
    }

    $html .= '</div>
</div>
</div>
<div class="footer">
    <p>&copy; 2025 <a href="http://www.danceoffice.net">DanceOffice</a> - Competition Management System</p>
    <p>Generated on ' . date('Y-m-d H:i:s') . ' | <a href="http://www.danceoffice.net">DanceOffice.net</a></p>
</div>
</body>
</html>';

    // 보고서 저장
    $report_dir = "results_reports/{$comp_id}/Event_{$event_no}";
    if (!is_dir($report_dir)) {
        mkdir($report_dir, 0755, true);
    }
    
    $report_file = "{$report_dir}/combined_report_{$event_no}.html";
    file_put_contents($report_file, $html);
    
    error_log("Professional report generated: " . $report_file);
}
?>
