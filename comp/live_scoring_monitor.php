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

if (empty($comp_id) || empty($event_no)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// scoring_files 폴더 경로
$scoring_dir = __DIR__ . "/data/{$comp_id}/scoring_files/Event_{$event_no}";

if (!is_dir($scoring_dir)) {
    echo json_encode(['success' => false, 'error' => 'Scoring directory not found']);
    exit;
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

// 최신 스코어링 파일 가져오기
$latest_file = getLatestScoringFile($scoring_dir);

if (!$latest_file) {
    echo json_encode(['success' => false, 'error' => 'No scoring files found']);
    exit;
}

// JSON 파일 읽기
$json_content = file_get_contents($latest_file);
$scoring_data = json_decode($json_content, true);

if (!$scoring_data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON file']);
    exit;
}

// live_tv 형식으로 변환
$live_tv_data = convertToLiveTvFormat($scoring_data);

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

echo json_encode([
    'success' => true,
    'live_tv' => $live_tv_data,
    'last_updated' => date('Y-m-d H:i:s')
]);
?>