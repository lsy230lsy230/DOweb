<?php
header('Content-Type: application/json; charset=utf-8');

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

if (!$comp_id || !$event_no) {
    echo json_encode(['success' => false, 'error' => '필수 매개변수 누락']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";

// 이벤트 번호 정규화 (하이픈 포함 허용)
$event_no = preg_replace('/[^0-9\-]/', '', $event_no);

// 심사위원 목록 로드
$adjudicator_file = "$data_dir/adjudicators.txt";
$panel_map_file = "$data_dir/panel_list.json";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";

$adjudicators = [];
$panel_map = [];
$event_panel = '';

// 심사위원 정보 로드
if (file_exists($adjudicator_file)) {
    $lines = file($adjudicator_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 2) {
            $adjudicators[$cols[0]] = [
                'code' => $cols[0],
                'name' => $cols[1],
                'nation' => $cols[2] ?? ''
            ];
        }
    }
}

// 패널 매핑 로드
if (file_exists($panel_map_file)) {
    $panel_map = json_decode(file_get_contents($panel_map_file), true) ?: [];
}

// 이벤트의 패널 정보 찾기
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        $no = preg_replace('/\x{FEFF}/u', '', $cols[0] ?? '');
        $detail_no = $cols[13] ?? '';
        
        // 이벤트 번호 매칭 (세부번호 우선)
        $file_event_no = !empty($detail_no) ? $detail_no : preg_replace('/\D+/', '', $no);
        
        if ($file_event_no === $event_no) {
            $event_panel = strtoupper($cols[11] ?? '');
            break;
        }
    }
}

// 해당 패널의 심사위원들 찾기
$event_judges = [];
foreach ($panel_map as $mapping) {
    if (strtoupper($mapping['panel_code'] ?? '') === $event_panel) {
        $judge_code = $mapping['adj_code'] ?? '';
        if (isset($adjudicators[$judge_code])) {
            $event_judges[] = $judge_code;
        }
    }
}

// 각 심사위원의 채점 상태 확인
$judge_status = [];
foreach ($event_judges as $judge_code) {
    $status = getJudgeScoringStatus($data_dir, $event_no, $judge_code);
    $judge_status[$judge_code] = $status;
}

echo json_encode([
    'success' => true,
    'status' => $judge_status,
    'event_panel' => $event_panel,
    'event_judges' => $event_judges
]);

function getJudgeScoringStatus($data_dir, $event_no, $judge_code) {
    // 댄스 목록 가져오기 (RunOrder_Tablet.txt에서)
    $dances = getDancesForEvent($data_dir, $event_no);
    
    if (empty($dances)) {
        return ['class' => 'offline', 'text' => '오프라인'];
    }
    
    $total_dances = count($dances);
    $completed_dances = 0;
    
    // 각 댄스별 .adj 파일 확인
    foreach ($dances as $dance) {
        $adj_file = "$data_dir/{$event_no}_{$dance}_{$judge_code}.adj";
        if (file_exists($adj_file)) {
            $completed_dances++;
        }
    }
    
    // 디버그: 상태 계산 로그
    error_log("Judge Status Debug - Event: $event_no, Judge: $judge_code, Dances: " . implode(',', $dances) . ", Completed: $completed_dances/$total_dances");
    
    if ($completed_dances === 0) {
        return ['class' => 'waiting', 'text' => '대기'];
    } elseif ($completed_dances < $total_dances) {
        return ['class' => 'scoring', 'text' => "{$completed_dances}/{$total_dances}"];
    } else {
        return ['class' => 'completed', 'text' => '완료'];
    }
}

function getDancesForEvent($data_dir, $event_no) {
    $runorder_file = "$data_dir/RunOrder_Tablet.txt";
    
    if (!file_exists($runorder_file)) {
        return [];
    }
    
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        $no = preg_replace('/\x{FEFF}/u', '', $cols[0] ?? '');
        $detail_no = $cols[13] ?? '';
        
        // 이벤트 번호 매칭
        $file_event_no = !empty($detail_no) ? $detail_no : preg_replace('/\D+/', '', $no);
        
        if ($file_event_no === $event_no) {
            $dances = [];
            // 6-10번째 컬럼의 댄스 코드 수집
            for ($i = 6; $i <= 10; $i++) {
                if (isset($cols[$i]) && is_numeric($cols[$i]) && $cols[$i] > 0) {
                    $dances[] = $cols[$i];
                }
            }
            return $dances;
        }
    }
    
    return [];
}
?>
