<?php
    // CORS 헤더 추가
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');
    
    // OPTIONS 요청 처리 (preflight)
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    ini_set('memory_limit', '1024M');
    ini_set('max_execution_time', '600');
    ini_set('max_input_time', '600');
    ini_set('post_max_size', '100M');
    ini_set('upload_max_filesize', '100M');
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
    
    // 웹 서버 타임아웃 방지
    if (function_exists('set_time_limit')) {
        set_time_limit(600);
    }
    
    // 출력 버퍼링 비활성화
    if (ob_get_level()) {
        ob_end_clean();
    }

try {
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

if (empty($comp_id) || empty($event_no)) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// 이벤트 정보 로드
$runorder_file = __DIR__ . "/data/{$comp_id}/RunOrder_Tablet.txt";
if (!file_exists($runorder_file)) {
        echo json_encode(['success' => false, 'error' => 'RunOrder file not found: ' . $runorder_file]);
    exit;
}

$events = [];
$lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
        $parts = explode(",", $line);
        if (count($parts) >= 10) {
        $events[] = [
            'no' => trim($parts[0]),
            'desc' => trim($parts[1]),
                'round' => trim($parts[2]) ?: 'Final',
                'detail_no' => trim($parts[11]) ?: trim($parts[0]),
                'event_no' => trim($parts[0]),
                'dances' => array_filter(array_map('trim', array_slice($parts, 6, 5))),
                'panel' => trim($parts[11]) ?: 'A'
        ];
    }
}

// 현재 이벤트 찾기
$current_event = null;
foreach ($events as $event) {
    if ($event['no'] == $event_no || $event['event_no'] == $event_no) {
        $current_event = $event;
        break;
    }
    if ($event['detail_no'] == $event_no) {
        $current_event = $event;
        break;
    }
        // "숫자-숫자" 형태의 이벤트 번호로 매칭 (예: 1-1)
        if (preg_match('/^(\d+)-(\d+)$/', $event_no, $event_matches)) {
            $event_base = $event_matches[1];
            $event_detail = $event_matches[2];
            if ($event['no'] == $event_base) {
                if ($event_base == "1" && $event_detail == "1" && $event['detail_no'] == "SA") {
        $current_event = $event;
        break;
                }
            }
    }
}

if (!$current_event) {
    // 이벤트가 event_info.json에 없는 경우, 이벤트별 기본값 설정
    if ($event_no === '52') {
        $current_event = [
            'no' => $event_no,
            'desc' => '프로페셔널 라틴',
            'round' => 'Final',
            'dances' => ['6', '7', '8', '9', '10'], // 라틴 5종목
            'panel' => 'LC'
        ];
    } else {
        $current_event = [
            'no' => $event_no,
            'dances' => ['6', '7', '8', '9', '10'], // 기본값: 라틴 5종목
            'panel' => 'LC' // 기본값: 라틴 패널
        ];
    }
}

// 선수 정보 로드 (전체 선수 파일에서 해당 이벤트 선수들 찾기)
$players = [];
$all_players_file = __DIR__ . "/data/{$comp_id}/players.txt";
$event_players_file = __DIR__ . "/data/{$comp_id}/players_{$event_no}.txt";

// 전체 선수 데이터 로드
$all_players = [];
if (file_exists($all_players_file)) {
    $lines = file($all_players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(',', trim($line));
        if (count($parts) >= 3) {
            $all_players[$parts[0]] = [
                'number' => $parts[0],
                'male' => $parts[1],
                'female' => $parts[2]
            ];
        }
    }
}

// 이벤트별 선수 번호 로드
if (file_exists($event_players_file)) {
    $lines = file($event_players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $player_no = trim($line);
        if (!empty($player_no)) {
            if (isset($all_players[$player_no])) {
                $players[] = $all_players[$player_no];
            } else {
                $players[] = ['number' => $player_no, 'male' => '', 'female' => ''];
            }
        }
    }
}

// 심사위원 정보 로드 (실제 채점 파일에서 심사위원 찾기)
$adjudicators = [];

// 이벤트의 실제 채점 파일들을 분석해서 심사위원 찾기
$event_judges = [];
$dance_codes = $current_event['dances'] ?? ['6', '7', '8', '9', '10']; // 기본값: 라틴 5종목

// 모든 이벤트에 대해 실제 채점 파일에서 심사위원 찾기
foreach ($dance_codes as $dance_code) {
    $pattern = "data/{$comp_id}/{$event_no}_{$dance_code}_*.adj";
    $absolute_pattern = __DIR__ . "/" . $pattern;
    $files = glob($absolute_pattern);
    foreach ($files as $file) {
        // 파일명에서 심사위원 코드 추출: {event_no}_{dance_code}_{judge_code}.adj
        $filename = basename($file);
        if (preg_match('/' . preg_quote($event_no) . '_' . preg_quote($dance_code) . '_(\d+)\.adj$/', $filename, $matches)) {
            $judge_code = $matches[1];
            if (!in_array($judge_code, $event_judges)) {
                $event_judges[] = $judge_code;
            }
        }
    }
}

error_log("Event $event_no judges found: " . implode(', ', $event_judges));

// 전체 심사위원 정보 로드
$adjudicators_file = __DIR__ . "/data/{$comp_id}/adjudicators.txt";
$all_adjudicators = [];
if (file_exists($adjudicators_file)) {
    $lines = file($adjudicators_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(",", $line);
        if (count($parts) >= 2) {
            $all_adjudicators[$parts[0]] = [
                'code' => trim($parts[0]),
                'name' => trim($parts[1])
            ];
        }
    }
}

// 실제 채점한 심사위원들만 추가 (A, B, C... 순서로) - 심사위원 수에 따라 동적으로
$judge_index = 0;
foreach ($event_judges as $judge_code) {
    if (isset($all_adjudicators[$judge_code])) {
        $adjudicators[] = [
            'code' => chr(65 + $judge_index), // A, B, C, D... (A부터 시작)
            'name' => $all_adjudicators[$judge_code]['name'],
            'original_code' => $judge_code
        ];
        $judge_index++;
    }
}

error_log("Event $event_no judges found: " . implode(', ', $event_judges));
error_log("Adjudicators loaded: " . count($adjudicators));
error_log("Adjudicators details: " . json_encode($adjudicators));
error_log("Dance codes: " . implode(', ', $dance_codes));
error_log("Event judges count: " . count($event_judges));

// 댄스별 채점 데이터 수집
$dance_results = [];
$dance_names = [
        '2' => 'Tango',
    '6' => 'Cha Cha Cha',
    '7' => 'Samba', 
    '8' => 'Rumba',
    '9' => 'Paso Doble',
    '10' => 'Jive'
];

// 모든 이벤트에 대해 댄스별 채점 데이터 수집
foreach ($current_event['dances'] as $dance_code) {
    $dance_name = $dance_names[$dance_code] ?? $dance_code;
    $dance_results[$dance_code] = [
        'name' => $dance_name,
        'code' => $dance_code,
        'judge_scores' => [],
        'final_rankings' => []
    ];
    
    // 각 심사위원의 순위 파일에서 데이터 수집
    foreach ($adjudicators as $judge) {
        $score_file = __DIR__ . "/data/{$comp_id}/{$event_no}_{$dance_code}_{$judge['original_code']}.adj";
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
                $dance_results[$dance_code]['judge_scores'][$judge['original_code']] = $judge_scores;
                error_log("Loaded scores for dance $dance_code, judge {$judge['original_code']} ({$judge['name']}): " . json_encode($judge_scores));
            } else {
                error_log("No scores found for dance $dance_code, judge {$judge['original_code']} ({$judge['name']})");
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

    // HTML 결과 파일 생성
    $html_content = generateFinalResultHTML($current_event, $players, $adjudicators, $dance_results, $final_rankings, $comp_id, $event_no);
    
    // JSON과 HTML 파일 저장
    $event_file_id = $event_no; // 1-1 그대로 사용
    
    // 1. JSON 파일 생성
    $scoring_dir = "data/{$comp_id}/scoring_files/Event_{$event_file_id}";
    if (!is_dir($scoring_dir)) {
        if (!mkdir($scoring_dir, 0755, true)) {
            error_log("Failed to create directory: $scoring_dir");
        }
    }
    $timestamp = date('Y-m-d_H-i-s');
    $json_filename = "event_{$event_file_id}_scoring_{$timestamp}.json";
    $json_filepath = $scoring_dir . '/' . $json_filename;
    
    $json_data = [
    'event_info' => $current_event,
    'players' => $players,
    'adjudicators' => $adjudicators,
    'dance_results' => $dance_results,
    'final_rankings' => $final_rankings,
        'timestamp' => date('Y-m-d H:i:s'),
        'comp_id' => $comp_id
    ];
    
    $json_success = file_put_contents($json_filepath, json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($json_success === false) {
        error_log("Failed to write JSON file: $json_filepath");
    }
    
    // 2. HTML 파일 생성
    $results_dir = "data/{$comp_id}/Results/Event_{$event_file_id}";
    if (!is_dir($results_dir)) {
        if (!mkdir($results_dir, 0755, true)) {
            error_log("Failed to create directory: $results_dir");
        }
    }
    $html_filename = "Event_{$event_file_id}_result.html";
    $html_filepath = $results_dir . '/' . $html_filename;
    
    $html_success = file_put_contents($html_filepath, $html_content);
    if ($html_success === false) {
        error_log("Failed to write HTML file: $html_filepath");
    }
    
    // 디버깅을 위한 로그 추가
    error_log("JSON file path: " . realpath($json_filepath));
    error_log("HTML file path: " . realpath($html_filepath));
    error_log("JSON success: " . ($json_success ? 'true' : 'false'));
    error_log("HTML success: " . ($html_success ? 'true' : 'false'));
    
    // 응답
    echo json_encode([
        'success' => true,
        'message' => 'Final aggregation completed successfully',
        'comp_id' => $comp_id,
        'event_no' => $event_no,
    'event_info' => $current_event,
    'players' => $players,
    'adjudicators' => $adjudicators,
    'dance_results' => $dance_results,
    'final_rankings' => $final_rankings,
        'files_created' => [
            'json' => $json_filepath,
            'html' => $html_filepath
        ],
        'players_count' => count($players),
        'adjudicators_count' => count($adjudicators),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

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
    
    // 순위 결정 (중복 제거)
    $ranked_players = [];
    $processed_players = [];
    foreach ($player_rankings as $player_no => $data) {
        // 중복 제거: 이미 처리된 선수는 건너뛰기
        if (!in_array($player_no, $processed_players)) {
            $ranked_players[] = [
                'player_no' => $player_no,
                'skating_data' => $data
            ];
            $processed_players[] = $player_no;
        }
    }
    
    // 스케이팅 시스템 규칙에 따라 정렬 (올바른 과반수 규칙 적용)
    // 각 선수에 대해 정렬 키를 계산
    foreach ($ranked_players as $index => $player) {
        $data = $player['skating_data'];
        $total_judges = 13; // 13명의 심사위원
        $majority_threshold = ceil($total_judges / 2); // 7명 이상
        
        // 레벨 계산
        $level = 0;
        if ($data['place_1'] > 0 && $data['place_1_2'] >= $majority_threshold) $level = 0;
        elseif ($data['place_1_2'] >= $majority_threshold) $level = 1;
        elseif ($data['place_1to3'] >= $majority_threshold) $level = 2;
        elseif ($data['place_1to4'] >= $majority_threshold) $level = 3;
        elseif ($data['place_1to5'] >= $majority_threshold) $level = 4;
        elseif ($data['place_1to6'] >= $majority_threshold) $level = 5;
        else $level = 6; // 과반 달성 못함
        
        // 정렬 키 생성 (레벨, 해당 레벨의 값, 합계)
        $sort_key = $level;
        switch ($level) {
            case 0: // 1위 수
                $sort_key .= sprintf('%02d', $data['place_1']);
                break;
        case 1: // 1&2위 수 (더 많은 수를 받은 선수가 우위)
            $sort_key .= sprintf('%02d', 99 - $data['place_1_2']); // 역순으로 정렬
            break;
        case 2: // 1to3위 수 (더 많은 수를 받은 선수가 우위)
            $sort_key .= sprintf('%02d', 99 - $data['place_1to3']); // 역순으로 정렬
            $sort_key .= sprintf('%03d', $data['sum_1to3']);
            break;
            case 3: // 1to4위 수 (더 많은 수를 받은 선수가 우위)
                $sort_key .= sprintf('%02d', 99 - $data['place_1to4']); // 역순으로 정렬
                $sort_key .= sprintf('%03d', $data['sum_1to4']);
                break;
            case 4: // 1to5위 수 (더 많은 수를 받은 선수가 우위)
                $sort_key .= sprintf('%02d', 99 - $data['place_1to5']); // 역순으로 정렬
                $sort_key .= sprintf('%03d', $data['sum_1to5']);
                break;
            case 5: // 1to6위 수 (더 많은 수를 받은 선수가 우위)
                $sort_key .= sprintf('%02d', 99 - $data['place_1to6']); // 역순으로 정렬
                $sort_key .= sprintf('%03d', $data['sum_1to6']);
                break;
            case 6: // 과반 달성 못한 경우
                $sort_key .= sprintf('%03d', $data['sum_1to6']);
                break;
        }
        
        $ranked_players[$index]['sort_key'] = $sort_key;
    }
    
    // 정렬 키로 정렬
    usort($ranked_players, function($a, $b) {
        return strcmp($a['sort_key'], $b['sort_key']);
    });
    
    // 최종 순위 부여 (동점 처리)
    $final_rankings = [];
    $current_rank = 1;
    $tied_players = [];
    
    for ($i = 0; $i < count($ranked_players); $i++) {
        $player = $ranked_players[$i];
        $tied_players[] = $player;
        
        // 다음 선수와 비교하여 동점인지 확인
        $is_tied = false;
        if ($i < count($ranked_players) - 1) {
            $next_player = $ranked_players[$i + 1];
            $is_tied = ($player['sort_key'] === $next_player['sort_key']);
        }
        
        // 동점이 아니거나 마지막 선수인 경우 등위 부여
        if (!$is_tied) {
            if (count($tied_players) == 1) {
                // 단독 등위
                $final_rankings[$tied_players[0]['player_no']] = $current_rank;
            } else {
                // 동점 등위 - 선수 번호 순으로 정렬 후 등위 부여
                usort($tied_players, function($a, $b) {
                    return strcmp($a['player_no'], $b['player_no']);
                });
                
                foreach ($tied_players as $tied_player) {
                    $final_rankings[$tied_player['player_no']] = $current_rank . "=";
                }
            }
            
            $current_rank += count($tied_players);
            $tied_players = [];
        }
    }
    
    return $final_rankings;
}

// 과반 계산 함수 (13명 심사위원 기준, 과반은 7명 이상)
function isMajority($count, $total_judges = 13) {
    $majority_threshold = ceil($total_judges / 2);
    return $count >= $majority_threshold;
}

// 과반 하이라이트를 위한 CSS 클래스 생성
function getMajorityClass($count, $total_judges = 13) {
    return isMajority($count, $total_judges) ? ' class="majority-highlight"' : '';
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

// HTML 결과 페이지 생성 (이미지 디자인 및 사용자 요청 순서)
function generateFinalResultHTML($event_info, $players, $adjudicators, $dance_results, $final_rankings, $comp_id, $event_no) {
    $dance_names = [
        '2' => 'Tango',
        '6' => 'Cha Cha Cha',
        '7' => 'Samba', 
        '8' => 'Rumba',
        '9' => 'Paso Doble',
        '10' => 'Jive'
    ];
    
    // 대회 정보 로드
    $competition_info = loadCompetitionInfo($comp_id);
    $competition_name = $competition_info['title'] ?? '제19회 빛고을배 전국 댄스스포츠대회 (전문체육)';
    
    // 날짜 추출 (실제 대회 날짜 사용)
    $event_date = '';
    if (isset($competition_info['date'])) {
        $event_date = date('Y. m. d', strtotime($competition_info['date']));
    } elseif (preg_match('/^(\d{8})/', $comp_id, $matches)) {
        $event_date = date('Y. m. d', strtotime($matches[1]));
    } else {
        $event_date = date('Y. m. d');
    }
    
    // 댄스 정보 추출
    $dance_codes = $event_info['dances'] ?? [];
    $dance_list = [];
    foreach ($dance_codes as $code) {
        $dance_list[] = $dance_names[$code] ?? $code;
    }
    $dance_string = implode(', ', $dance_list);
    
    $html = '<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>' . htmlspecialchars($event_info['desc']) . ' - Skating Report</title>
<meta name="description" content="DanceSportLive - ' . htmlspecialchars($event_info['desc']) . ' - Skating Report">

<style>
body { font: 100%/1.4 Arial, Helvetica, sans-serif; background: #fff; margin: 0; padding: 0; color: #000; }
.container { width: 960px; background: #fff; margin: 0 auto; padding: 0; border: 1px solid #000; }
.header { background: #fff; padding: 10px 0; text-align: center; border-bottom: 1px solid #000; }
.header h1 { margin: 0; font-size: 24px; color: #000; }
.header p { margin: 5px 0; font-size: 14px; color: #333; }
.content { padding: 10px; }
.section-title { font-size: 18px; font-weight: bold; margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
th, td { border: 1px solid #000; padding: 5px; text-align: center; font-size: 12px; }
th { background-color: #eee; font-weight: bold; }
.adjudicators-header { background-color: #ddd; }
.calculation-header { background-color: #ccc; }
.place-dance-header { background-color: #bbb; }
.final-rank-table th { background-color: #cceeff; }
.final-rank-table tr:nth-child(odd) { background-color: #f9f9f9; }
.final-rank-table tr:nth-child(even) { background-color: #f0f0f0; }
.rules-table th { background-color: #e6f3ff; }
.rules-table th.dance-header { background-color: #b3d9ff; }
.majority-highlight { background-color: #ffff99 !important; font-weight: bold; }
.adjudicator-list { margin-top: 30px; border-top: 1px solid #000; padding-top: 10px; }
.adjudicator-list h3 { font-size: 16px; margin-bottom: 10px; }
.adjudicator-list ul { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; }
.adjudicator-list li { width: 33%; margin-bottom: 5px; font-size: 12px; }
</style>
</head>
<body>
<div class="container">
<div class="header">
        <h1>' . htmlspecialchars($competition_name) . ' - Skating Report</h1>
        <p>날짜: ' . $event_date . ' | 주최: ' . htmlspecialchars($competition_info['host'] ?? 'KFD') . ' | 부문: Professional | 종목: ' . $dance_string . '</p>
</div>
    <div class="content">';

    // --- 1. 최종 등위 테이블 (Final Rankings) - 먼저 표기 ---
    $html .= '<div class="section-title">최종 등위 (Final Rankings)</div>';
    $html .= '<table class="final-rank-table">';
    $html .= '<thead><tr><th>Place</th><th>Tag</th><th>Competitor Name</th><th>Sum of Places</th></tr></thead>';
    $html .= '<tbody>';
    
    // 선수 번호 순으로 정렬
    usort($final_rankings, function($a, $b) {
        return strcmp($a['player_no'], $b['player_no']);
    });
    
    foreach ($final_rankings as $rank_entry) {
        $player_no = $rank_entry['player_no'];
        
        // 실제 선수명 찾기
        $player_name = '선수 ' . $player_no; // 기본값
        foreach ($players as $player) {
            if ($player['number'] == $player_no) {
                if (!empty($player['male']) && !empty($player['female'])) {
                    $player_name = htmlspecialchars($player['male'] . ' / ' . $player['female']);
                } elseif (!empty($player['male'])) {
                    $player_name = htmlspecialchars($player['male']);
                } elseif (!empty($player['female'])) {
                    $player_name = htmlspecialchars($player['female']);
                }
                error_log("Player $player_no found: " . json_encode($player) . " -> $player_name");
                break;
            }
        }
        error_log("Final player name for $player_no: $player_name");
        
        $html .= '<tr>';
        // 동점 표기 처리
        $display_rank = $rank_entry['final_rank'];
        if (strpos($display_rank, '=') !== false) {
            $display_rank = '(' . str_replace('=', '', $display_rank) . ')';
        }
        $html .= '<td>' . htmlspecialchars($display_rank) . '</td>';
        $html .= '<td>' . htmlspecialchars($player_no) . '</td>';
        $html .= '<td>' . $player_name . '</td>';
        $html .= '<td>' . htmlspecialchars($rank_entry['sum_of_places']) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';

    // --- 2. Rules 1-9 테이블 (각 댄스별 순위) ---
    $html .= '<div class="section-title">Rules 1 - 9</div>';
    $html .= '<table class="rules-table">';
    $html .= '<thead><tr><th>Cpl.NO</th>';
    
    // 댄스별 헤더
    foreach ($event_info['dances'] as $dance_code) {
        $dance_name = $dance_names[$dance_code] ?? $dance_code;
        $html .= '<th class="dance-header">' . $dance_name . '</th>';
    }
    
    $html .= '<th>SUM of Places</th><th>Place Skating</th></tr></thead>';
    $html .= '<tbody>';
    
    // 선수 번호 순으로 정렬 (이미 위에서 정렬했지만 명시적으로 다시 정렬)
    usort($final_rankings, function($a, $b) {
        return strcmp($a['player_no'], $b['player_no']);
    });
    
    foreach ($final_rankings as $rank_entry) {
        $player_no = $rank_entry['player_no'];
        
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($player_no) . '</td>';
        
        // 각 댄스별 순위
        foreach ($event_info['dances'] as $dance_code) {
            $dance_rank = isset($dance_results[$dance_code]['final_rankings'][$player_no]) ? 
                         $dance_results[$dance_code]['final_rankings'][$player_no] : '-';
            // 동점 표기 처리
            if (strpos($dance_rank, '=') !== false) {
                $dance_rank = '(' . str_replace('=', '', $dance_rank) . ')';
            }
            $html .= '<td>' . htmlspecialchars($dance_rank) . '</td>';
        }
        
        $html .= '<td>' . htmlspecialchars($rank_entry['sum_of_places']) . '</td>';
        // 동점 표기 처리
        $display_rank = $rank_entry['final_rank'];
        if (strpos($display_rank, '=') !== false) {
            $display_rank = '(' . str_replace('=', '', $display_rank) . ')';
        }
        $html .= '<td>' . htmlspecialchars($display_rank) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';

    // 동점이 있는지 확인
    $has_ties = checkForTies($final_rankings);
    
    if ($has_ties) {
        // --- 3. Rules 10 테이블 (동점이 있을 때만 표시) ---
        $html .= '<div class="section-title">Rules 10 - Number and sum Places in all dances</div>';
        $html .= '<table class="rules-table">';
        $html .= '<thead><tr><th>Cpl.NO.</th><th>1</th><th>1&2</th><th>1to3</th><th>1to4</th><th>1to5</th><th>1to6</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($final_rankings as $rank_entry) {
            $player_no = $rank_entry['player_no'];
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($player_no) . '</td>';
            
            // 전체 댄스에서의 스케이팅 계산
            $total_skating = calculateTotalSkatingData($dance_results, $player_no);
            $html .= '<td>' . htmlspecialchars($total_skating['place_1']) . '</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1_2']) . '>' . htmlspecialchars($total_skating['place_1_2']) . '</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1to3']) . '>' . htmlspecialchars($total_skating['place_1to3']) . ' (' . htmlspecialchars($total_skating['sum_1to3']) . ')</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1to4']) . '>' . htmlspecialchars($total_skating['place_1to4']) . ' (' . htmlspecialchars($total_skating['sum_1to4']) . ')</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1to5']) . '>' . htmlspecialchars($total_skating['place_1to5']) . ' (' . htmlspecialchars($total_skating['sum_1to5']) . ')</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1to6']) . '>' . htmlspecialchars($total_skating['place_1to6']) . ' (' . htmlspecialchars($total_skating['sum_1to6']) . ')</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';

        // --- 4. Rules 11 테이블 (동점이 있을 때만 표시) ---
        $html .= '<div class="section-title">Rules 11 - Number and sum of all judges in all dances</div>';
        $html .= '<table class="rules-table">';
        $html .= '<thead><tr><th>Cpl.NO.</th><th>1</th><th>1&2</th><th>1to3</th><th>1to4</th><th>1to5</th><th>1to6</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($final_rankings as $rank_entry) {
            $player_no = $rank_entry['player_no'];
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($player_no) . '</td>';
            
            // 전체 댄스에서의 스케이팅 계산 (Rules 11은 Rules 10과 동일)
            $total_skating = calculateTotalSkatingData($dance_results, $player_no);
            $html .= '<td>' . htmlspecialchars($total_skating['place_1']) . '</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1_2']) . '>' . htmlspecialchars($total_skating['place_1_2']) . '</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1to3']) . '>' . htmlspecialchars($total_skating['place_1to3']) . ' (' . htmlspecialchars($total_skating['sum_1to3']) . ')</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1to4']) . '>' . htmlspecialchars($total_skating['place_1to4']) . ' (' . htmlspecialchars($total_skating['sum_1to4']) . ')</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1to5']) . '>' . htmlspecialchars($total_skating['place_1to5']) . ' (' . htmlspecialchars($total_skating['sum_1to5']) . ')</td>';
            $html .= '<td' . getMajorityClass($total_skating['place_1to6']) . '>' . htmlspecialchars($total_skating['place_1to6']) . ' (' . htmlspecialchars($total_skating['sum_1to6']) . ')</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
    }

    // --- 5. 댄스별 상세 채점 (항상 표시) ---
    foreach ($event_info['dances'] as $dance_code) {
        $dance_name = $dance_names[$dance_code] ?? $dance_code;
        $dance_data = $dance_results[$dance_code] ?? [];

        $html .= '<div class="section-title">' . htmlspecialchars($dance_name) . '</div>';
        $html .= '<table>';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th rowspan="2">Cpl.<br>No.</th>';
        $html .= '<th colspan="' . count($adjudicators) . '" class="adjudicators-header">Adjudicators</th>';
        $html .= '<th colspan="6" class="calculation-header">Calculation</th>';
        $html .= '<th rowspan="2" class="place-dance-header">Place<br>Dance</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        // 심사위원 A-M 헤더 (심사위원 수에 따라 동적으로)
        for ($i = 0; $i < count($adjudicators); $i++) {
            $html .= '<th>' . chr(65 + $i) . '</th>';
        }
        $html .= '<th>1</th><th>1&2</th><th>1to3</th><th>1to4</th><th>1to5</th><th>1to6</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        // 선수별 데이터 (최종 순위 순서대로, 선수 번호 오름차순)
        // 선수 번호 순으로 정렬
        usort($final_rankings, function($a, $b) {
            return strcmp($a['player_no'], $b['player_no']);
        });
        
        foreach ($final_rankings as $rank_entry) {
            $player_no = $rank_entry['player_no'];
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($player_no) . '</td>';

            // 심사위원별 점수 (A-M) - original_code 사용, 심사위원 수에 따라 동적으로
            for ($i = 0; $i < count($adjudicators); $i++) {
                $score = '';
                if ($i < count($adjudicators)) {
                    $judge = $adjudicators[$i];
                    $original_code = $judge['original_code'];
                    if (isset($dance_data['judge_scores'][$original_code][$player_no])) {
                        $score = $dance_data['judge_scores'][$original_code][$player_no];
                        error_log("Found score for player $player_no, judge $original_code: $score");
                    } else {
                        error_log("No score found for player $player_no, judge $original_code");
                    }
                }
                $html .= '<td>' . htmlspecialchars($score) . '</td>';
            }

            // 스케이팅 계산 (과반수 표기 방식) - original_code 사용
            $judge_scores_for_calculation = [];
            foreach ($adjudicators as $judge) {
                $original_code = $judge['original_code'];
                if (isset($dance_data['judge_scores'][$original_code])) {
                    $judge_scores_for_calculation[$original_code] = $dance_data['judge_scores'][$original_code];
                }
            }
            $skating_data = calculateSkatingDataForPlayerWithMajority($judge_scores_for_calculation, $player_no, count($adjudicators));
            $html .= '<td>' . htmlspecialchars($skating_data['place_1']) . '</td>';
            $html .= '<td' . getMajorityClass($skating_data['place_1_2'], count($adjudicators)) . '>' . htmlspecialchars($skating_data['place_1_2']) . '</td>';
            $html .= '<td' . getMajorityClass($skating_data['place_1to3'], count($adjudicators)) . '>' . htmlspecialchars($skating_data['place_1to3']) . '</td>';
            $html .= '<td' . getMajorityClass($skating_data['place_1to4'], count($adjudicators)) . '>' . htmlspecialchars($skating_data['place_1to4']) . '</td>';
            $html .= '<td' . getMajorityClass($skating_data['place_1to5'], count($adjudicators)) . '>' . htmlspecialchars($skating_data['place_1to5']) . '</td>';
            $html .= '<td' . getMajorityClass($skating_data['place_1to6'], count($adjudicators)) . '>' . htmlspecialchars($skating_data['place_1to6']) . '</td>';

            // Place Dance (동점 표기 처리)
            $dance_place = $dance_data['final_rankings'][$player_no] ?? '';
            if (strpos($dance_place, '=') !== false) {
                // 동점인 경우 괄호로 표기
                $dance_place = '(' . str_replace('=', '', $dance_place) . ')';
            }
            $html .= '<td>' . htmlspecialchars($dance_place) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
    }

    // --- 5. 심사위원 명단 (Adjudicator List) ---
    $html .= '<div class="adjudicator-list">';
    $html .= '<h3>Adjudicators</h3>';
    $html .= '<ul>';
    
    // 실제로 이벤트에 참여한 심사위원만 표시 (기존 $adjudicators 배열 사용)
    $judge_letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
    
    for ($i = 0; $i < count($adjudicators); $i++) {
        $judge = $adjudicators[$i];
        $letter = isset($judge_letters[$i]) ? $judge_letters[$i] : chr(65 + $i);
        $html .= '<li>' . $letter . '. ' . htmlspecialchars($judge['name']) . '</li>';
    }
    $html .= '</ul>';
    $html .= '</div>';

    // Footer
    $html .= '<div style="margin-top: 30px; text-align: center; font-size: 12px; color: #666;">';
    $html .= '<p>The figures of rule 11 are shown only if the majority if 23 marks is reached!</p>';
    $html .= '<p>Number of dance "5" - Number of judges is "9" - Majority comes to "23" marks</p>';
    $html .= '</div>';

    $html .= '</div></div></body></html>';

    return $html;
}

// 대회 정보 로드
function loadCompetitionInfo($comp_id) {
    $info_file = __DIR__ . "/data/{$comp_id}/info.json";
    if (file_exists($info_file)) {
        $content = file_get_contents($info_file);
        $data = json_decode($content, true);
        return $data ?: ['title' => '제19회 빛고을배 전국 댄스스포츠대회 (전문체육)'];
    }
    return ['title' => '제19회 빛고을배 전국 댄스스포츠대회 (전문체육)'];
}

// 동점이 있는지 확인
function checkForTies($final_rankings) {
    $sums = [];
    foreach ($final_rankings as $rank_entry) {
        $sum = $rank_entry['sum_of_places'];
        if (isset($sums[$sum])) {
            return true; // 동점 발견
        }
        $sums[$sum] = true;
    }
    return false; // 동점 없음
}

// 전체 댄스에서의 스케이팅 데이터 계산
function calculateTotalSkatingData($dance_results, $player_no) {
    $all_rankings = [];
    
    // 모든 댄스에서의 순위 수집
    foreach ($dance_results as $dance_code => $dance_data) {
        foreach ($dance_data['judge_scores'] as $judge_code => $scores) {
            if (isset($scores[$player_no])) {
                $all_rankings[] = $scores[$player_no];
            }
        }
    }
    
    if (empty($all_rankings)) {
        return [
            'place_1' => 0,
            'place_1_2' => 0,
            'place_1to3' => 0,
            'place_1to4' => 0,
            'place_1to5' => 0,
            'place_1to6' => 0,
            'sum_1to3' => 0,
            'sum_1to4' => 0,
            'sum_1to5' => 0,
            'sum_1to6' => 0
        ];
    }
    
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
    
    foreach ($all_rankings as $rank) {
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

// 선수별 스케이팅 데이터 계산 (과반수 표기 방식)
function calculateSkatingDataForPlayerWithMajority($judge_scores, $player_no, $total_judges) {
    $rankings = [];
    
    // 각 심사위원이 부여한 순위 수집
    foreach ($judge_scores as $judge_code => $scores) {
        if (isset($scores[$player_no])) {
            $rankings[] = $scores[$player_no];
        }
    }
    
    if (empty($rankings)) {
        return [
            'place_1' => 0,
            'place_1_2' => 0,
            'place_1to3' => 0,
            'place_1to4' => 0,
            'place_1to5' => 0,
            'place_1to6' => 0
        ];
    }
    
    $place_1 = 0;
    $place_1_2 = 0;
    $place_1to3 = 0;
    $place_1to4 = 0;
    $place_1to5 = 0;
    $place_1to6 = 0;
    
    $sum_1_2 = 0; // 1위와 2위의 합계
    
    foreach ($rankings as $rank) {
        if ($rank == 1) {
            $place_1++;
            $place_1_2++; // 1등도 1&2에 포함
            $sum_1_2 += $rank;
        }
        if ($rank == 2) {
            $place_1_2++; // 2등도 1&2에 포함
            $sum_1_2 += $rank;
        }
        if ($rank <= 3) $place_1to3++;
        if ($rank <= 4) $place_1to4++;
        if ($rank <= 5) $place_1to5++;
        if ($rank <= 6) $place_1to6++;
    }
    
    // 과반수 계산
    $majority_threshold = floor($total_judges / 2) + 1;
    
    return [
        'place_1' => $place_1,
        'place_1_2' => $place_1_2 >= $majority_threshold ? $place_1_2 . ' (' . $sum_1_2 . ')' : $place_1_2,
        'place_1to3' => $place_1to3,
        'place_1to4' => $place_1to4,
        'place_1to5' => $place_1to5,
        'place_1to6' => $place_1to6
    ];
}

// 선수별 스케이팅 데이터 계산 (기존 방식)
function calculateSkatingDataForPlayer($judge_scores, $player_no) {
    $rankings = [];
    
    // 각 심사위원이 부여한 순위 수집
    foreach ($judge_scores as $judge_code => $scores) {
        if (isset($scores[$player_no])) {
            $rankings[] = $scores[$player_no];
        }
    }
    
    if (empty($rankings)) {
        return [
            'place_1' => 0,
            'place_1_2' => 0,
            'place_1to3' => 0,
            'place_1to4' => 0,
            'place_1to5' => 0,
            'place_1to6' => 0,
            'sum_1to3' => 0,
            'sum_1to4' => 0,
            'sum_1to5' => 0,
            'sum_1to6' => 0
        ];
    }
    
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
?>
