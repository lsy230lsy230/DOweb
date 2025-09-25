<?php
header('Content-Type: application/json');

$comp_id = $_GET['comp_id'] ?? '20250913-001';
$data_dir = __DIR__ . "/../data/$comp_id";

$event_id = $_GET['event'] ?? '';

if (empty($event_id)) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required.']);
    exit;
}

// 이벤트 정보 로드 (RunOrder_Tablet.txt)
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$events_data = [];
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        $no = preg_replace('/\x{FEFF}/u', '', $cols[0] ?? '');
        $no = preg_replace('/\D+/', '', $no);
        $detail_no = $cols[13] ?? '';
        $event_key = !empty($detail_no) ? $detail_no : $no;

        if ($event_key === $event_id) {
            $dances = [];
            for ($i=6; $i<=10; $i++) {
                if (isset($cols[$i]) && is_numeric($cols[$i]) && $cols[$i] > 0) {
                    $dances[] = $cols[$i];
                }
            }
            $events_data = [
                'no' => $no,
                'detail_no' => $detail_no,
                'panel' => isset($cols[11]) ? strtoupper($cols[11]) : '',
                'dances' => $dances
            ];
            break;
        }
    }
}

if (empty($events_data)) {
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit;
}

// 심사위원 데이터 로드
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

// getJudgeStatus 함수 (test_page_2.php와 동일)
function getJudgeStatusAPI($data_dir, $event_key, $dances, $panel_map, $adjudicator_dict, $events_all) {
    $judge_status = [];
    
    $panel_code = '';
    foreach ($events_all as $event) {
        $event_key_check = $event['detail_no'] ?: $event['no'];
        if ($event_key_check === $event_key) {
            $panel_code = $event['panel'];
            break;
        }
    }
    
    if (empty($panel_code)) {
        return $judge_status;
    }
    
    $panel_judges = [];
    foreach ($panel_map as $mapping) {
        if (isset($mapping['panel_code']) && $mapping['panel_code'] === $panel_code) {
            $judge_code = $mapping['adj_code'];
            if (isset($adjudicator_dict[$judge_code])) {
                $panel_judges[] = $judge_code;
            }
        }
    }
    
    foreach ($panel_judges as $judge_code) {
        $judge_info = $adjudicator_dict[$judge_code];
        $completed = 0;
        $total = count($dances);
        
        // 각 댄스별로 채점 파일 확인
        foreach ($dances as $dance) {
            $adj_file = "$data_dir/{$event_key}_{$dance}_{$judge_code}.adj";
            if (file_exists($adj_file)) {
                $adj_data = file($adj_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (!empty($adj_data)) {
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

// 모든 이벤트 데이터 (getJudgeStatusAPI 함수에서 패널 코드 찾기 위함)
$all_events_for_judge_status = [];
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        $no = preg_replace('/\x{FEFF}/u', '', $cols[0] ?? '');
        $no = preg_replace('/\D+/', '', $no);
        $detail_no = $cols[13] ?? '';
        $panel = isset($cols[11]) ? strtoupper($cols[11]) : '';
        $all_events_for_judge_status[] = [
            'no' => $no,
            'detail_no' => $detail_no,
            'panel' => $panel
        ];
    }
}

$judges = getJudgeStatusAPI($data_dir, $event_id, $events_data['dances'], $panel_map, $adjudicator_dict, $all_events_for_judge_status);

echo json_encode(['success' => true, 'judges' => $judges]);
?>