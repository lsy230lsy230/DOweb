<?php
/**
 * 실시간 스코어링 파일 모니터링 API
 * scoring_files 폴더를 감시하여 최신 결과를 live_tv 형식으로 반환
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
error_log("Initial event_no from GET: '$event_no'");

if (empty($comp_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing comp_id parameter']);
    exit;
}

// 전역 변수 초기화
$latest_file = null;

// 이벤트 번호가 지정되지 않은 경우 최신 스코어링 파일이 있는 이벤트 찾기
error_log("Checking if event_no is empty: '$event_no'");
if (empty($event_no)) {
    $scoring_base_dir = __DIR__ . "/data/{$comp_id}/scoring_files";
    
    if (!is_dir($scoring_base_dir)) {
        echo json_encode(['success' => false, 'error' => 'Scoring base directory not found']);
        exit;
    }
    
    // Results 폴더에서 최신 파일 찾기
    $results_base_dir = __DIR__ . "/data/{$comp_id}/Results";
    error_log("Results base directory: $results_base_dir");
    error_log("Results directory exists: " . (is_dir($results_base_dir) ? 'yes' : 'no'));
    
    $event_dirs = glob($results_base_dir . "/Event_*", GLOB_ONLYDIR);
    error_log("Found event directories: " . count($event_dirs));
    
    $latest_time = 0;
    $latest_event = null;
    
    // 모든 이벤트 디렉토리에서 가장 최신 파일 찾기
    foreach ($event_dirs as $event_dir) {
        // 결과 HTML 파일 확인
        $result_file = $event_dir . "/" . basename($event_dir) . "_result.html";
        error_log("Checking result file: $result_file");
        if (file_exists($result_file)) {
            $file_time = filemtime($result_file);
            error_log("Found result file: $result_file, time: $file_time");
            if ($file_time > $latest_time) {
                $latest_time = $file_time;
                $latest_file = $result_file;
                // 폴더명에서 이벤트 번호 추출 (Event_28 -> 28)
                $latest_event = basename($event_dir);
                $latest_event = str_replace('Event_', '', $latest_event);
                error_log("New latest file: $latest_file, event: $latest_event");
            }
        }
    }
    
    // Results 폴더에서 파일을 찾았으면 스코어링 파일 확인하지 않음
    if ($latest_file) {
        error_log("Found result file in Results folder, skipping scoring files");
        $event_no = $latest_event;
        $scoring_dir = dirname($latest_file);
        error_log("Using result file: $latest_file for event: $event_no");
        error_log("Final event_no: $event_no, latest_event: $latest_event");
        
        // Results 폴더에서 파일을 찾았으므로 스코어링 파일 확인하지 않음
        // $scoring_base_dir = null; // 스코어링 파일 확인 비활성화 - 주석 처리
    }
    
    // Results 폴더에 파일이 없으면 스코어링 파일과 히트 파일 확인
    if (!$latest_file) {
        error_log("No result files found in Results folder, checking scoring files and hits files");
        
        // 1. 스코어링 파일 확인
        $event_dirs = glob($scoring_base_dir . "/Event_*", GLOB_ONLYDIR);
        
        foreach ($event_dirs as $event_dir) {
            $files = glob($event_dir . "/event_*_scoring_*.json");
            if (!empty($files)) {
                // 가장 최신 파일 찾기
                foreach ($files as $file) {
                    $file_time = filemtime($file);
                    if ($file_time > $latest_time) {
                        $latest_time = $file_time;
                        $latest_file = $file;
                        // 폴더명에서 이벤트 번호 추출 (Event_28 -> 28)
                        $latest_event = basename($event_dir);
                        $latest_event = str_replace('Event_', '', $latest_event);
                        error_log("Found scoring file: $latest_file, event: $latest_event");
                    }
                }
            }
        }
        
        // 2. 히트 파일 확인 (스코어링 파일이 없을 때)
        if (!$latest_file) {
            $hits_files = glob($data_dir . "/players_hits_*.json");
            error_log("Checking hits files: " . count($hits_files));
            
            foreach ($hits_files as $hits_file) {
                $file_time = filemtime($hits_file);
                error_log("Checking hits file: $hits_file, time: $file_time");
                
                if ($file_time > $latest_time) {
                    $latest_time = $file_time;
                    $latest_file = $hits_file;
                    
                    // 파일명에서 이벤트 번호 추출 (players_hits_28.json -> 28)
                    if (preg_match('/players_hits_(\d+)\.json/', basename($hits_file), $matches)) {
                        $latest_event = $matches[1];
                        error_log("Found hits file: $latest_file, event: $latest_event");
                    }
                }
            }
        }
    }
    
    if (!$latest_file) {
        error_log("No result files found in any event");
        echo json_encode(['success' => false, 'error' => 'No result files found in any event']);
        exit;
    }
    
    if (empty($event_no)) {
        $event_no = $latest_event;
    }
    $scoring_dir = dirname($latest_file);
    
    error_log("Found latest result file: $latest_file for event: $event_no");
} else {
    // Results 폴더에서 결과 파일 찾기
    $results_dir = __DIR__ . "/data/{$comp_id}/Results/Event_{$event_no}";
    $result_file = $results_dir . "/Event_{$event_no}_result.html";
    
    if (file_exists($result_file)) {
        $latest_file = $result_file;
        $scoring_dir = $results_dir;
    } else {
        // 결과 파일이 없으면 스코어링 파일 찾기
$scoring_dir = __DIR__ . "/data/{$comp_id}/scoring_files/Event_{$event_no}";

if (!is_dir($scoring_dir)) {
            echo json_encode(['success' => false, 'error' => 'Event directory not found']);
    exit;
        }
    }
}

/**
 * 최신 스코어링 파일 찾기
 */
function getLatestScoringFile($dir) {
    $files = glob($dir . "/event_*_scoring_*.json");
    if (empty($files)) {
        return null;
    }
    
    // 파일 수정시간으로 정렬 (최신순)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    return $files[0];
}

/**
 * JSON 데이터를 live_tv 형식으로 변환
 */
function convertToLiveTvFormat($data) {
    if (!isset($data['scoring_data'])) {
        return null;
    }
    
    $scoring = $data['scoring_data'];
    $event_info = $scoring['event_info'] ?? [];
    
    $live_tv = [
        'event_title' => ($event_info['desc'] ?? '') . ' - ' . ($event_info['round'] ?? ''),
        'advancement_text' => '',
        'recall_info' => '',
        'participants' => []
    ];
    
    // 리콜 정보 생성
    $recall_count = $scoring['recall_count_from_file'] ?? 0;
    $total_judges = $scoring['total_judges'] ?? 0;
    $recall_threshold = $scoring['recall_threshold'] ?? 0;
    
    $live_tv['advancement_text'] = $recall_count . "커플이 다음라운에 진출합니다";
    $live_tv['recall_info'] = "파일 리콜 수: {$recall_count}명 | 심사위원 수: {$total_judges}명 | 리콜 기준: {$recall_threshold}명 이상";
    
    // 선수 데이터 변환
    if (isset($scoring['player_recalls'])) {
        $rank = 1;
        foreach ($scoring['player_recalls'] as $player) {
            $live_tv['participants'][] = [
                'rank' => $rank,
                'marks' => $player['recall_count'] ?? 0,
                'tag' => $player['player_number'] ?? '',
                'name' => $player['player_name'] ?? '',
                'from' => $event_info['desc'] ?? '',
                'qualified' => false // 일단 모두 false로 설정
            ];
            $rank++;
        }
        
        // 리콜 수로 정렬 (내림차순)
        usort($live_tv['participants'], function($a, $b) {
            return $b['marks'] - $a['marks'];
        });
        
        // 상위 리콜 수만큼만 진출로 설정
        for ($i = 0; $i < min($recall_count, count($live_tv['participants'])); $i++) {
            $live_tv['participants'][$i]['qualified'] = true;
        }
        
        // 순위 재조정
        $rank = 1;
        foreach ($live_tv['participants'] as &$participant) {
            $participant['rank'] = $rank++;
        }
    }
    
    return $live_tv;
}

/**
 * HTML 결과 파일을 live_tv 형식으로 변환
 */
function convertHtmlResultToLiveTvFormat($html_content, $event_no) {
    error_log("Converting HTML result file to live_tv format for event: $event_no");
    
    $live_tv = [
        'event_title' => '',
        'advancement_text' => '',
        'recall_info' => '',
        'participants' => []
    ];
    
    // DOM 파서 없이 정규식으로 데이터 추출
    
    // 1. 이벤트 제목 추출 (개선된 패턴)
    if (preg_match('/<td[^>]*style="[^"]*font-weight:bold[^"]*"[^>]*align="left"[^>]*>([^<]+)<\/td>/i', $html_content, $matches)) {
        $live_tv['event_title'] = trim($matches[1]);
        error_log("Found event title: " . $live_tv['event_title']);
    }
    
    // 2. 진출 텍스트 추출 (개선된 패턴)  
    if (preg_match('/<td[^>]*align="right"[^>]*>([^<]*\d+커플이[^<]*진출합니다[^<]*)<\/td>/i', $html_content, $matches)) {
        $live_tv['advancement_text'] = trim($matches[1]);
        error_log("Found advancement text: " . $live_tv['advancement_text']);
    }
    
    // 3. 리콜 정보 추출
    if (preg_match('/<strong>리콜 정보:<\/strong>\s*([^<]+)/i', $html_content, $matches)) {
        $live_tv['recall_info'] = trim($matches[1]);
        error_log("Found recall info: " . $live_tv['recall_info']);
    }
    
    // 4. 참가자 데이터 추출 (간단한 패턴으로 수정)
    $participants = [];
    
    // 테이블 행을 찾는 간단한 패턴
    if (preg_match_all('/<tr[^>]*style="font-weight:bold;"[^>]*>(.*?)<\/tr>/is', $html_content, $row_matches)) {
        error_log("Found " . count($row_matches[1]) . " participant rows");
        
        foreach ($row_matches[1] as $row_content) {
            // 각 셀 추출
            if (preg_match_all('/<td[^>]*>([^<]*(?:<[^>]*>[^<]*<\/[^>]*>)*[^<]*)<\/td>/is', $row_content, $cell_matches)) {
                $cells = $cell_matches[1];
                
                if (count($cells) >= 5) {
                    $rank = intval(trim(strip_tags($cells[0])));
                    $marks_raw = trim(strip_tags($cells[1]));
                    $marks = intval(str_replace(['(', ')'], '', $marks_raw));
                    $tag = trim(strip_tags($cells[2]));
                    $name = trim(strip_tags($cells[3]));
                    $from = trim(strip_tags($cells[4]));
                    
                    // 진출 여부 확인 (✅ 이모지나 "진출" 텍스트 포함 여부)
                    $qualified = strpos($cells[3], '✅') !== false || strpos($cells[3], '진출') !== false;
                    
                    if ($rank > 0 && !empty($name) && !empty($tag)) {
                        $participants[] = [
                            'rank' => $rank,
                            'marks' => $marks,
                            'tag' => $tag,
                            'name' => $name,
                            'from' => $from,
                            'qualified' => $qualified
                        ];
                        error_log("Added participant: Rank $rank, Tag $tag, Name $name, Marks $marks, Qualified: " . ($qualified ? 'yes' : 'no'));
                    }
                }
            }
        }
    }
    
    $live_tv['participants'] = $participants;
    
    error_log("HTML parsing results - Title: {$live_tv['event_title']}, Participants: " . count($participants));
    
    return $live_tv;
}

/**
 * 히트 파일을 live_tv 형식으로 변환
 */
function convertHitsToLiveTvFormat($hits_data, $event_no) {
    error_log("Converting hits file to live_tv format for event: $event_no");
    
    // 이벤트 정보 가져오기
    $data_dir = __DIR__ . "/data/{$GLOBALS['comp_id']}";
    $runorder_file = $data_dir . "/RunOrder_Tablet.txt";
    $event_info = null;
    
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 3 && trim($parts[0]) == $event_no) {
                $event_info = [
                    'no' => trim($parts[0]),
                    'desc' => trim($parts[1]),
                    'round' => trim($parts[2])
                ];
                break;
            }
        }
    }
    
    $event_title = $event_info ? "{$event_info['desc']} {$event_info['round']}" : "이벤트 {$event_no}";
    
    // 히트 데이터에서 참가자 정보 추출
    $participants = [];
    $rank = 1;
    
    foreach ($hits_data as $heat => $players) {
        foreach ($players as $player_no) {
            $participants[] = [
                'rank' => $rank++,
                'marks' => 1, // 히트 파일에서는 기본값 1
                'tag' => $player_no,
                'name' => "선수 {$player_no}",
                'from' => $event_title,
                'qualified' => true // 히트에 포함된 선수는 모두 진출
            ];
        }
    }
    
    $live_tv = [
        'event_title' => $event_title,
        'advancement_text' => count($participants) . "커플이 다음라운에 진출합니다",
        'recall_info' => "히트 파일에서 추출된 데이터",
        'participants' => $participants
    ];
    
    return $live_tv;
}

/**
 * HTML 결과 파일을 live_tv 형식으로 변환
 */
function convertHtmlToLiveTvFormat($html_content, $event_no) {
    // HTML에서 이벤트 정보 추출
    $event_title = '';
    $participants = [];
    
    error_log("HTML content length: " . strlen($html_content));
    error_log("HTML content preview: " . substr($html_content, 0, 500));
    
    // 제목 추출 - 여러 패턴 시도
    if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/', $html_content, $matches)) {
        $event_title = trim($matches[1]);
        error_log("Found h2 title: " . $event_title);
    } elseif (preg_match('/<h1[^>]*>([^<]+)<\/h1>/', $html_content, $matches)) {
        $event_title = trim($matches[1]);
        error_log("Found h1 title: " . $event_title);
    } elseif (preg_match('/프로페셔널 라틴.*?Round \d+/', $html_content, $matches)) {
        $event_title = trim($matches[0]);
        error_log("Found pattern title: " . $event_title);
    }
    
    // 테이블에서 선수 정보 추출
    if (preg_match('/<table[^>]*class="results-table"[^>]*>(.*?)<\/table>/s', $html_content, $table_matches)) {
        $table_content = $table_matches[1];
        error_log("Found results-table");
        
        // tbody에서 행 추출
        if (preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', $table_content, $tbody_matches)) {
            $tbody_content = $tbody_matches[1];
            error_log("Found tbody content");
            
            // 모든 tr 태그 추출
            if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $tbody_content, $row_matches)) {
                error_log("Found " . count($row_matches[1]) . " rows in tbody");
                
                foreach ($row_matches[1] as $index => $row) {
                    // 각 셀에서 데이터 추출
                    if (preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cell_matches)) {
                        $cells = $cell_matches[1];
                        
                        if (count($cells) >= 4) {
                            $rank = trim(strip_tags($cells[0]));
                            $player_no = trim(strip_tags($cells[1]));
                            $player_name = trim(strip_tags($cells[2]));
                            $recall_count = trim(strip_tags($cells[3]));
                            
                            // 숫자가 포함된 행만 처리 (헤더 제외)
                            if (is_numeric($rank) && !empty($player_name)) {
                                $participants[] = [
                                    'rank' => intval($rank),
                                    'marks' => intval($recall_count),
                                    'tag' => $player_no,
                                    'name' => $player_name,
                                    'from' => $event_title,
                                    'qualified' => true
                                ];
                                error_log("Added participant: $rank, $player_no, $player_name, $recall_count");
                            }
                        }
                    }
                }
            }
        }
    }
    
    error_log("Total participants found: " . count($participants));
    
    // 리콜 수로 정렬 (내림차순)
    usort($participants, function($a, $b) {
        return $b['marks'] - $a['marks'];
    });
    
    // 순위 재조정
    $rank = 1;
    foreach ($participants as &$participant) {
        $participant['rank'] = $rank++;
    }
    
    $live_tv = [
        'event_title' => $event_title,
        'advancement_text' => count($participants) . "커플이 다음라운에 진출합니다",
        'recall_info' => "결과 파일에서 추출된 데이터",
        'participants' => $participants
    ];
    
    error_log("Final live_tv participants count: " . count($live_tv['participants']));
    
    return $live_tv;
}

// 최신 파일 가져오기
if (empty($event_no)) {
    // 이미 위에서 최신 파일을 찾았으므로 그대로 사용
    // $latest_file은 이미 설정됨
} else {
    // 결과 파일이 없으면 스코어링 파일 찾기
    if (!$latest_file) {
$latest_file = getLatestScoringFile($scoring_dir);
    }
}

if (!$latest_file) {
    echo json_encode(['success' => false, 'error' => 'No result files found']);
    exit;
}

// 파일 타입에 따라 처리
$file_extension = pathinfo($latest_file, PATHINFO_EXTENSION);

if ($file_extension === 'html') {
    // HTML 결과 파일인 경우
    $html_content = file_get_contents($latest_file);
    $live_tv_data = convertHtmlResultToLiveTvFormat($html_content, $event_no);
} else {
    // JSON 스코어링 파일인 경우
$json_content = file_get_contents($latest_file);
$scoring_data = json_decode($json_content, true);

if (!$scoring_data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON file']);
    exit;
}

// live_tv 형식으로 변환
$live_tv_data = convertToLiveTvFormat($scoring_data);
}

if (!$live_tv_data) {
    echo json_encode(['success' => false, 'error' => 'Failed to convert data']);
    exit;
}

// 파일 정보 추가
$live_tv_data['file_info'] = [
    'filename' => basename($latest_file),
    'timestamp' => date('Y-m-d H:i:s', filemtime($latest_file)),
    'size' => filesize($latest_file)
];

error_log("Final response - event_no: $event_no, latest_event: " . (isset($latest_event) ? $latest_event : 'not set') . ", latest_file: $latest_file");
error_log("Participants count: " . (isset($live_tv_data['participants']) ? count($live_tv_data['participants']) : 'not set'));

echo json_encode([
    'success' => true,
    'live_tv' => $live_tv_data,
    'event_no' => $event_no,
    'last_updated' => date('Y-m-d H:i:s')
]);
?>