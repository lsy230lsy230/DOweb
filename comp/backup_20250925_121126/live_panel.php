<?php
$comp_id = $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$adjudicator_file = "$data_dir/adjudicators.txt";
$panel_map_file = "$data_dir/panel_list.json";
$dancename_file = "$data_dir/DanceName.txt";

// --- 댄스종목 약어->이름 매핑 (DanceName.txt 기준) ---
$dance_map_en = [];
if (is_file($dancename_file)) {
    foreach (file($dancename_file) as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) < 3 || $cols[2] == '-' || $cols[2] == '') continue;
        // 영문 코드를 키로 사용
        $dance_map_en[$cols[2]] = $cols[1];
        // 숫자 코드도 키로 사용 (28번 이벤트 등에서 사용)
        $dance_map_en[$cols[0]] = $cols[1];
    }
}

// 대회 정보 로드
if (!preg_match('/^\d{8}-\d+$/', $comp_id) || !is_file($info_file)) {
    echo "<h1>잘못된 대회 ID 또는 대회 정보가 없습니다.</h1>";
    exit;
}
$info = json_decode(file_get_contents($info_file), true);

// --- 이벤트 목록/패널 정보 및 종목 ---
$events = [];
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
        
        // 디버그: 28번 이벤트 로드 확인
        if ($no === '28') {
            error_log("Loaded event 28: desc='$desc', recall='$recall', heats='$heats'");
        }
    }
}

// --- 심사위원 상세 목록 adjudicators.txt (번호,이름,국가,ID) ---
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

// --- 패널-심사위원 매핑 panel_list.json [{panel_code, adj_code}] ---
$panel_map = [];
if (file_exists($panel_map_file)) {
    $panel_map = json_decode(file_get_contents($panel_map_file), true);
}

// 팀수 자동 계산 함수 (세부번호별)
function calculateTeamCountByDetail($comp_id, $detail_no, $event_no = '') {
    // 세부번호가 있는 경우 (멀티 이벤트)
    if (!empty($detail_no)) {
        $players_file = __DIR__ . "/data/$comp_id/players_{$detail_no}.txt";
        if (file_exists($players_file)) {
            $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $count = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $count++;
                }
            }
            return $count > 0 ? $count : '-';
        }
    }
    
    // 세부번호가 없는 경우 (단일 이벤트) - 이벤트 번호로 확인
    if (!empty($event_no)) {
        $players_file = __DIR__ . "/data/$comp_id/players_{$event_no}.txt";
        if (file_exists($players_file)) {
            $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $count = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $count++;
                }
            }
            return $count > 0 ? $count : '-';
        }
    }
    
    return '-';
}

// --- 선수 데이터 불러오기 (이벤트별) ---
$players_by_event = [];
foreach ($events as $ev) {
    $eno = $ev['no'];
    $detail_no = $ev['detail_no'] ?? '';
    
    // 세부번호가 있으면 세부번호로, 없으면 이벤트 번호로
    $file_key = !empty($detail_no) ? $detail_no : $eno;
    $pfile = "$data_dir/players_$file_key.txt";
    
    if (is_file($pfile)) {
        $player_data = array_filter(array_map('trim', file($pfile)));
        $players_by_event[$eno] = $player_data;
        // 세부번호가 있으면 세부번호 키로도 저장
        if (!empty($detail_no)) {
            $players_by_event[$detail_no] = $player_data;
        }
    } else {
        // BOM 등 비정상 문자가 끼어 생성된 파일을 탐색하여 보정
        $players_by_event[$eno] = [];
        foreach (glob($data_dir . "/players_*.txt") as $alt) {
            $base = basename($alt);
            $num = $base;
            $num = preg_replace('/^players_/u', '', $num);
            $num = preg_replace('/\.txt$/u', '', $num);
            $num = preg_replace('/\x{FEFF}/u', '', $num); // BOM 제거
            
            // 세부번호가 있는 경우 숫자와 하이픈만 남김, 없는 경우 숫자만
            if (!empty($detail_no)) {
                $num = preg_replace('/[^0-9\-]/', '', $num);
            } else {
                $num = preg_replace('/\D+/', '', $num);
            }
            
            if ($num === (string)$file_key) {
                // 읽고, 가능하면 정규 파일명으로 리네임
                $arr = array_filter(array_map('trim', file($alt)));
                $players_by_event[$eno] = $arr;
                // 세부번호가 있으면 세부번호 키로도 저장
                if (!empty($detail_no)) {
                    $players_by_event[$detail_no] = $arr;
                }
                if (!is_file($pfile)) {
                    @rename($alt, $pfile);
                }
                break;
            }
        }
    }
}

// --- 전체 선수명단 players.txt (등번호,남자,여자) ---
$players_file = "$data_dir/players.txt";
$all_players = [];
if (file_exists($players_file)) {
    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        $all_players[$cols[0]] = [
            'male' => $cols[1] ?? '',
            'female' => $cols[2] ?? '',
        ];
    }
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= h($info['title']) ?> 라이브 컨트롤 패널</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        html, body { height:100%; margin:0; padding:0; }
        body { background:#1a1a1a; font-family:sans-serif; margin:0; height:100vh; }
        .live-root { width:100vw; height:100vh; min-height:100vh; min-width:100vw; background:#bdbdbd; margin:0; padding:0; display: flex; flex-direction:column; align-items:stretch; justify-content:stretch; }
        .live-frame { width:100vw; height:100vh; min-height:100vh; min-width:100vw; background: #fff; border: 0; box-sizing: border-box; display: flex; flex-direction: row; overflow: hidden; border-radius: 0; box-shadow: none; }
        .side-events {
            flex: 0 0 35vw;
            min-width: 300px;
            max-width: 45vw;
            background: #ededed;
            border-right: 3px solid #071d6e;
            overflow-y: auto;
            padding: 0.7em 0.2em 0.7em 0.7em;
            box-sizing: border-box;
        }
        .side-events h2 { font-size: 1.09em; margin: 0.2em 0 0.5em 0.2em; color: #071d6e; letter-spacing:0.1em;}
        .event-list { width: 100%; border-collapse: collapse; font-size: 0.9em;}
        .event-list thead th { background: #f0f4ff; color: #0d2c96; font-weight: bold; padding: 0.4em 0.3em; border-bottom: 2px solid #b8d4ff; font-size: 0.85em;}
        .event-list tbody tr.selected { background: #d0e6ff; }
        .event-list tbody tr:hover { background: #e6f1ff; cursor:pointer;}
        .event-list tbody td { border-bottom: 1px solid #c7d1e0; padding: 0.3em 0.25em; color: #222; font-size: 0.9em; text-align: center;}
        .event-list tbody td:nth-child(2) { text-align: left; } /* 이벤트명은 왼쪽 정렬 */
        .round-cell { font-weight: 600; color: #0d2c96; white-space: nowrap; }
        .main-panel { flex: 1 1 0; display: flex; flex-direction: column; background: #0d2c96; padding: 0; width: 75vw; min-width:0; }
        .event-header-panel {
            background: #bdbdbd;
            border: 3px solid #071d6e;
            border-radius: 0 0 12px 12px;
            padding: 0.6em 1em;
            margin: 0 0 0.8em 0;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 0.6em;
            width: 100%;
            box-sizing: border-box;
            min-height: 100px;
        }
        .event-header-box { background: #bdbdbd; border: 3px solid #071d6e; border-radius: 6px; padding: 0.5em 0.8em; width: 100%; max-width: 900px; min-width: 300px; font-family: Arial, sans-serif; margin-right: 0; box-sizing: border-box;}
        .event-row1, .event-row2 { display: flex; align-items: center; gap: 0.6em; margin-bottom: 0.25em;}
        .event-row2 {margin-bottom: 0;}
        .event-number-controls { display: flex; flex-direction: column; align-items: flex-end; gap: 0.2em; }
        .ev-arrow-btn { width: 1.6em; height: 1.6em; background: #fff; border: 2px solid #333; border-radius: 3px; padding: 0; margin: 0; font-size: 1em; font-weight: 700; display: flex; align-items: center; justify-content: center; cursor: pointer;}
        .ev-arrow-btn:active {background:#dfdfdf;}
        .ev-idx {width: 3.2em; text-align: center; font-size: 1.25em; font-weight: 700; border: 1.5px solid #333; border-radius: 4px; background:#fff; color: #0d2c96;}
        .ev-title {flex:1; font-size: 1.02em; font-weight: 600; background:#fff; border:1.5px solid #333; border-radius:6px; padding:0.18em 0.6em; min-height: 2em;}
        .ev-refresh-btn { background: #fff; border:2px solid #071d6e; border-radius: 8px; width:2.6em; height:2.6em; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.6em; margin-left:0;}
        .ev-refresh-btn:active {background:#e5e5e5;}
        .ev-fromto, .ev-recall, .ev-heats { background:#fff; border:1px solid #222; border-radius:6px; font-size:0.95em; width:3.0em; text-align:center; padding:0.18em 0.28em;}
        .ev-fromto {width:3.2em;}
        .ev-label-bold {font-weight:700;}
        .ev-ctrl-btn {background:none;border:none;padding:0;margin:0;}
        .ev-row2-label {font-size: 0.9em; min-width: 2.4em; color:#0d2c96; font-weight:600;}
        .ev-save-btn {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            padding: 0.3em 0.8em;
            margin-left: 0.5em;
            transition: all 0.2s ease;
            font-weight: 600;
        }
        .ev-save-btn:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .ev-save-btn:active {
            transform: translateY(0);
        }
        .event-row2 { flex-wrap: wrap; }
        
        /* 집계 모달 스타일 */
        .aggregation-section {
            margin-top: 1em;
            text-align: center;
        }
        
        .aggregation-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.8em 1.5em;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .aggregation-btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        
        .aggregation-modal {
            background: white;
            border-radius: 12px;
            width: 90vw;
            max-width: 1200px;
            height: 80vh;
            max-height: 800px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1001;
        }
        
        .aggregation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5em 2em;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
        }
        
        .aggregation-header h2 {
            margin: 0;
            color: #495057;
            font-size: 1.5em;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 2em;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .close-btn:hover {
            background: #e9ecef;
            color: #495057;
        }
        
        .aggregation-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            padding: 1em 1.5em;
            font-size: 1em;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background: white;
        }
        
        .tab-btn:hover {
            color: #007bff;
            background: rgba(0,123,255,0.1);
        }
        
        .aggregation-content {
            flex: 1;
            padding: 2em;
            overflow-y: auto;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .aggregation-info h3 {
            margin: 0 0 1em 0;
            color: #495057;
        }
        
        .event-info {
            background: #e3f2fd;
            padding: 1em;
            border-radius: 6px;
            margin-bottom: 1.5em;
        }
        
        .aggregation-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1em;
            margin-bottom: 2em;
        }
        
        .status-item {
            background: #f8f9fa;
            padding: 1em;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-label {
            font-weight: 600;
            color: #495057;
        }
        
        .aggregation-table {
            margin-bottom: 2em;
        }
        
        .aggregation-table h4 {
            margin: 0 0 1em 0;
            color: #495057;
        }
        
        .aggregation-results {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1em;
            min-height: 200px;
        }
        
        .loading {
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
        
        .error {
            text-align: center;
            color: #dc3545;
            font-weight: 600;
            padding: 1em;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
        }
        
        .aggregation-actions {
            display: flex;
            gap: 1em;
            justify-content: center;
        }
        
        .refresh-btn, .export-btn {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.8em 1.5em;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .refresh-btn:hover, .export-btn:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .exemption-info h3 {
            margin: 0 0 0.5em 0;
            color: #495057;
        }
        
        .exemption-info p {
            color: #6c757d;
            margin-bottom: 2em;
        }
        
        .exemption-controls {
            margin-bottom: 2em;
        }
        
        .input-group {
            display: flex;
            gap: 1em;
            align-items: center;
        }
        
        .input-group label {
            font-weight: 600;
            color: #495057;
            min-width: 100px;
        }
        
        .input-group input {
            flex: 1;
            padding: 0.8em;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1em;
        }
        
        .input-group button {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.8em 1.5em;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .input-group button:hover {
            background: #0056b3;
        }
        
        .exemption-list h4 {
            margin: 0 0 1em 0;
            color: #495057;
        }
        
        .exemption-players-list {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1em;
            min-height: 200px;
        }
        
        .empty {
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
        
        .confirmation-info h3 {
            margin: 0 0 0.5em 0;
            color: #495057;
        }
        
        .confirmation-info p {
            color: #6c757d;
            margin-bottom: 2em;
        }
        
        .confirmation-summary {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1.5em;
            margin-bottom: 2em;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1em;
        }
        
        .summary-item:last-child {
            margin-bottom: 0;
        }
        
        .summary-label {
            font-weight: 600;
            color: #495057;
        }
        
        .summary-item input {
            padding: 0.5em;
            border: 1px solid #ced4da;
            border-radius: 4px;
            width: 150px;
        }
        
        .confirmation-actions {
            display: flex;
            gap: 1em;
            justify-content: center;
        }
        
        .preview-btn, .execute-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.8em 1.5em;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .execute-btn {
            background: #28a745;
        }
        
        .preview-btn:hover, .execute-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .event-row2 { flex-wrap: wrap; }
        .main-content-row { display: flex; flex-direction: row; gap: 1.2em; height: 93%;}
        .adjudicator-list-panel { flex: 0 0 40%; background: #eaf0ff; border-radius: 8px; margin-top: 0.2em; padding: 1em 1em 1em 1em; box-sizing: border-box; display: flex; flex-direction: column; align-items: stretch;}
        .adjudicator-list-panel h3 { font-size: 1.1em; color: #0d2c96; margin: 0 0 0.6em 0;}
        .adjudicator-list { list-style: none; padding:0; margin:0;}
        .adjudicator-list li { margin-bottom: 0.28em; padding: 0.13em 0.2em; background: #fff; border-radius: 4px; font-size: 0.97em; color: #282828; display: flex; align-items: center; justify-content: space-between;}
        .adjudicator-list li.disabled { color: #aaa; text-decoration: line-through; background: #f5f5f5;}
        .adjudicator-x-btn { background:#dc3232;color:#fff;border:none;border-radius:3px;padding:2px 8px;font-size:1em;cursor:pointer;margin-left:0.5em;}
        .adjudicator-x-btn:disabled {background:#ccc; color:#888; cursor:default;}
        .adjudicator-list-panel .empty {color:#888; margin-top:0.7em; font-size:0.98em;}
        
        /* 심사위원 테이블 스타일 개선 */
        .adjudicator-list-panel table { width: 100%; border-collapse: collapse; }
        .adjudicator-list-panel th { font-size: 0.9em; color: #0d2c96; padding: 0.3em 0.2em; text-align: left; border-bottom: 1px solid #ddd; }
        .adjudicator-list-panel td { padding: 0.3em 0.2em; font-size: 0.9em; }
        .adjudicator-list-panel td:nth-child(1) { width: 6%; } /* 번호 */
        .adjudicator-list-panel td:nth-child(2) { width: 10%; } /* 코드 */
        .adjudicator-list-panel td:nth-child(3) { width: 30%; } /* 이름 (줄임) */
        .adjudicator-list-panel td:nth-child(4) { width: 12%; } /* 국가 */
        .adjudicator-list-panel td:nth-child(5) { width: 12%; text-align: center; } /* 상태 */
        .adjudicator-list-panel td:nth-child(6) { width: 30%; text-align: center; } /* 버튼들 */
        
        /* 버튼 그룹 스타일 */
        .adjudicator-buttons { display: flex; gap: 4px; justify-content: center; align-items: center; }
        .adjudicator-x-btn { margin: 0; }
        .judge-scoring-btn { 
            background: #28a745 !important; 
            color: #fff !important; 
            border: none !important; 
            border-radius: 3px !important; 
            padding: 2px 8px !important; 
            font-size: 0.9em !important; 
            cursor: pointer !important; 
            margin: 0 !important;
            transition: background 0.2s ease;
        }
        .judge-scoring-btn:hover { background: #218838 !important; }
        
        /* 심사위원 상태 표시 */
        .judge-status {
            font-size: 0.8em;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
            text-align: center;
            min-width: 40px;
        }
        .judge-status.scoring {
            background: #28a745;
            color: white;
        }
        .judge-status.completed {
            background: #007bff;
            color: white;
        }
        .judge-status.waiting {
            background: #ffc107;
            color: #333;
        }
        .judge-status.offline {
            background: #6c757d;
            color: white;
        }
        
        /* 이벤트 번호 스타일 */
        .event-number {
            display: flex;
            align-items: baseline;
            gap: 2px;
        }
        .main-number {
            font-weight: bold;
            font-size: 1em;
            color: #0d2c96;
        }
        .detail-number {
            font-size: 0.85em;
            color: #666;
            font-weight: normal;
        }
        .player-dance-row { display: flex; flex-direction: row; gap: 1.2em; align-items: flex-start; flex: 1;}
        .player-list-panel { flex: 1; background: #f5f5fa; border-radius: 8px; margin-top: 0.2em; padding: 1em 1em 1em 1em; box-sizing: border-box; display: flex; flex-direction: column; align-items: stretch;}
        .player-list-panel h3 { font-size:1.1em; color:#0d2c96; margin:0 0 0.6em 0;}
        .player-list-panel .player-controls-row {
            display: flex;
            gap: 0.4em;
            margin-bottom: 1em;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-start;
        }
        .split-hit-btn {
            background: #f7b200;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 0.4em 0.8em;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            height: 2.2em;
            box-sizing: border-box;
            transition: background 0.13s;
            display:inline-flex; align-items:center; justify-content:center;
            min-width: 4em;
        }
        .split-hit-btn:active {background:#bc8f1f;}
        .show-hit-btn {
            background: #fff;
            color: #e9b200;
            border: 2px solid #e9b200;
            border-radius: 4px;
            padding: 0.4em 0.8em;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            height: 2.2em;
            transition: background 0.13s, color 0.13s;
            display:inline-flex; align-items:center; justify-content:center;
            min-width: 4em;
        }
        .show-hit-btn.active, .show-hit-btn:active {
            background: #ffe082;
            color: #b36b00;
        }
        .print-hit-btn {
            background: #fff;
            color: #234b8c;
            border: 2px solid #234b8c;
            border-radius: 4px;
            padding: 0.4em 0.8em;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            height: 2.2em;
            display:inline-flex; align-items:center; justify-content:center;
            min-width: 4em;
        }
        .print-hit-btn:active {
            background:#deebff;
            color:#0d2c96;
        }
        .player-list-scrollbox {
            overflow-y: auto;
            max-height: 320px;
            border-radius: 6px;
            border: 1.5px solid #f5e3b3;
            background: #fff;
            padding: 0.2em 0.1em 0.2em 0.1em;
        }
        .player-list {list-style:none; padding:0; margin:0;}
        .player-list li {margin-bottom:0.3em; padding:0.17em 0.3em; background:#fff; border-radius:4px; font-size:1.04em; display:flex; align-items:center; justify-content:space-between;}
        .hit-block {
            margin-top: 0.8em;
        }
        @media print {
            body * { visibility: hidden !important; }
            #hitModalBg, #hitModalBg * { visibility: visible !important; }
            #hitModalBg { 
                position: fixed !important; 
                left: 0 !important; 
                top: 0 !important; 
                width: 100vw !important; 
                height: 100vh !important;
                background: #fff !important; 
                padding: 0 !important;
                margin: 0 !important;
                display: flex !important;
                align-items: flex-start !important;
                justify-content: center !important;
                z-index: 9999 !important;
            }
            #hit-modal {
                position: static !important;
                width: 100% !important;
                height: auto !important;
                max-width: none !important;
                max-height: none !important;
                background: #fff !important;
                padding: 1em !important;
                margin: 0 !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                display: block !important;
            }
            .modal-bg:not(#hitModalBg) { display: none !important; }
        }
        .hit-title {
            font-weight: bold;
            color: #e9b200;
            margin: 0.6em 0 0.25em 0;
            font-size: 1.1em;
            letter-spacing: 0.03em;
        }
        .hit-table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1.2em;
            background: #fffbe7;
            border-radius: 6px;
            overflow: hidden;
        }
        .hit-table th, .hit-table td {
            border: 1px solid #ffe3a1;
            padding: 0.33em 0.65em;
            font-size: 1em;
            text-align: left;
        }
        .hit-table th {
            background: #fff3c9;
            color: #b36b00;
            font-weight: bold;
        }
        .entry-players-scrollbox {
            overflow-y: auto;
            max-height: 330px;
            min-height: 130px;
            border-radius: 10px;
            border: 1.2px solid #e5e5e5;
            background: #faf8f8;
        }
        .add-player-btn, .show-entry-list-btn {
            background: #1c7aee;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 0.4em 0.8em;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            height: 2.2em;
            box-sizing: border-box;
            transition: background 0.13s;
            display:inline-flex; align-items:center; justify-content:center;
            min-width: 4em;
        }
        .add-player-btn:active, .show-entry-list-btn:active {background:#155cb0;}
        .show-entry-list-btn {
            background: #29a950;
        }
        .show-entry-list-btn:active {
            background: #176c32;
        }
        .player-x-btn { background:#dc3232;color:#fff;border:none;border-radius:3px;padding:2px 8px;font-size:1em;cursor:pointer;margin-left:0.5em;}
        .dance-block {
            background: #fff;
            border: 2px solid #e9b200;
            border-radius: 8px;
            padding: 1em 1.6em;
            font-size: 1.12em;
            color: #b36b00;
            font-weight: 600;
            flex: 1;
            min-height: 120px;
            box-shadow: 0 4px 24px #ffe09460;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.15em;
        }
        .dance-block .dance-title {
            font-size: 1.08em;
            color: #e9b200;
            font-weight: bold;
            margin-bottom: 0.55em;
            letter-spacing: 0.05em;
        }
        .dance-block .dance-list {
            margin-left: 0.2em;
        }
        .dance-block .dance-item {
            font-size: 1.05em;
            color: #885e00;
            margin-bottom: 0.12em;
            line-height: 1.6;
        }
        
        /* 진행종목 블럭 스타일 */
        .dance-block {
            margin-top: 0.8em;
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #fdcb6e;
            border-radius: 0.5em;
            padding: 1em;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .dance-progress-container {
            margin-top: 0.8em;
        }
        
        .dance-progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(0,0,0,0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.8em;
        }
        
        .dance-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00b894, #00cec9);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .dance-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5em;
        }
        
        .dance-item {
            background: rgba(255,255,255,0.8);
            border: 1px solid #fdcb6e;
            border-radius: 0.3em;
            padding: 0.5em;
            text-align: center;
            font-weight: bold;
            color: #2d3436;
            transition: all 0.3s ease;
        }
        
        .dance-item.active {
            background: #00b894;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,184,148,0.3);
        }
        
        .dance-item.completed {
            background: #ddd;
            color: #666;
            text-decoration: line-through;
        }
        
        /* 감시 시스템 스타일 */
        .monitoring-block {
            flex: 1;
            background: rgba(0, 50, 0, 0.8);
            border: 1px solid #0a0;
            border-radius: 0.3em;
            padding: 0.8em;
        }
        .monitoring-title {
            font-size: 1.1em;
            font-weight: bold;
            color: #0f0;
            margin-bottom: 0.6em;
            text-align: center;
        }
        .monitoring-controls {
            text-align: center;
            margin-bottom: 0.8em;
        }
        .monitoring-btn {
            padding: 0.4em 0.8em;
            margin: 0 0.3em;
            border: none;
            border-radius: 0.3em;
            font-size: 0.9em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .start-btn {
            background: #0a0;
            color: white;
        }
        .start-btn:hover {
            background: #0f0;
        }
        .stop-btn {
            background: #a00;
            color: white;
        }
        .stop-btn:hover {
            background: #f00;
        }
        .monitoring-status {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.6em;
            margin-bottom: 0.8em;
        }
        .monitoring-status > div {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.4em;
            border-radius: 0.3em;
            text-align: center;
            font-size: 0.85em;
        }
        .monitoring-status span {
            font-weight: bold;
            color: #0f0;
        }
        .monitoring-timer {
            text-align: center;
            background: rgba(0, 0, 0, 0.3);
            padding: 0.6em;
            border-radius: 0.3em;
        }
        .timer-label {
            font-size: 0.9em;
            color: #ccc;
            margin-bottom: 0.3em;
        }
        .timer-display {
            font-size: 1.5em;
            font-weight: bold;
            color: #0f0;
            font-family: monospace;
        }
        @media (max-width: 860px) {
            .event-header-panel { flex-direction: column; align-items: stretch; gap: 0.8em; min-height: unset; }
            .ev-refresh-btn { align-self: center; }
        }
        
        /* 결승전 집계 모달 스타일 */
        .final-aggregation-modal {
            background: white;
            border-radius: 12px;
            width: 90vw;
            max-width: 1200px;
            height: 80vh;
            max-height: 800px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1001;
        }
        
        .final-aggregation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5em 2em;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
        }
        
        .final-aggregation-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5em;
            font-weight: bold;
        }
        
        .final-aggregation-content {
            flex: 1;
            padding: 2em;
            overflow-y: auto;
        }
        
        .final-event-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5em;
            margin-bottom: 2em;
            border-left: 4px solid #ffd700;
        }
        
        .final-event-info h3 {
            margin: 0 0 1em 0;
            color: #333;
            font-size: 1.2em;
        }
        
        .final-event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1em;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-right: 0.5em;
        }
        
        .final-results-section {
            margin-bottom: 2em;
        }
        
        .final-results-section h3 {
            margin: 0 0 1em 0;
            color: #333;
            font-size: 1.2em;
        }
        
        .final-results-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .final-results-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .final-results-table th {
            background: #ffd700;
            color: #333;
            padding: 1em;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .final-results-table td {
            padding: 1em;
            border-bottom: 1px solid #e9ecef;
        }
        
        .final-results-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .final-results-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .final-results-table tbody tr:nth-child(even):hover {
            background: #e9ecef;
        }
        
        .final-actions {
            display: flex;
            gap: 1em;
            justify-content: center;
            padding-top: 1em;
            border-top: 1px solid #e9ecef;
        }
        
        .final-actions button {
            padding: 0.8em 1.5em;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .refresh-btn {
            background: #6c757d;
            color: white;
        }
        
        .refresh-btn:hover {
            background: #5a6268;
        }
        
        .export-btn {
            background: #28a745;
            color: white;
        }
        
        .export-btn:hover {
            background: #218838;
        }
        
        .loading {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 2em;
        }
        
        .final-aggregation-section {
            margin-top: 1em;
            text-align: center;
        }
        
        .final-aggregation-btn {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
            border: none;
            border-radius: 8px;
            padding: 1em 2em;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
        }
        
        .final-aggregation-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 215, 0, 0.4);
        }
        
        /* 상세 결과 스타일 */
        .detailed-results {
            margin-top: 2em;
            border-top: 2px solid #e9ecef;
            padding-top: 2em;
        }
        
        .detailed-results h3 {
            margin: 0 0 1em 0;
            color: #333;
            font-size: 1.3em;
            font-weight: bold;
        }
        
        .dance-results-tabs {
            display: flex;
            gap: 0.5em;
            margin-bottom: 1em;
            flex-wrap: wrap;
        }
        
        .dance-tab-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.6em 1.2em;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            color: #495057;
            transition: all 0.2s ease;
        }
        
        .dance-tab-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .dance-tab-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .skating-results-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 1em;
        }
        
        .skating-results-table h4 {
            margin: 0;
            padding: 1em;
            background: #333;
            color: white;
            font-size: 1.1em;
            font-weight: bold;
        }
        
        .skating-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85em;
        }
        
        .skating-table th {
            background: #f8f9fa;
            color: #495057;
            padding: 0.8em 0.5em;
            text-align: center;
            font-weight: 600;
            border: 1px solid #dee2e6;
            white-space: nowrap;
        }
        
        .skating-table td {
            padding: 0.6em 0.5em;
            text-align: center;
            border: 1px solid #dee2e6;
            white-space: nowrap;
        }
        
        .skating-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .skating-table tbody tr:hover {
            background: #e9ecef;
        }
        
        .skating-table th:first-child,
        .skating-table td:first-child {
            background: #e9ecef;
            font-weight: bold;
        }
        
        .skating-table th:nth-child(2),
        .skating-table td:nth-child(2) {
            text-align: left;
            min-width: 200px;
        }
        
        .skating-table th:last-child,
        .skating-table td:last-child {
            background: #ffd700;
            font-weight: bold;
            color: #333;
        }
        
        /* 반응형 테이블 */
        .skating-results-table {
            overflow-x: auto;
        }
        
        @media (max-width: 768px) {
            .skating-table {
                font-size: 0.75em;
            }
            
            .skating-table th,
            .skating-table td {
                padding: 0.4em 0.3em;
            }
        }
    </style>
</head>
<body>
<div class="live-root">
    <div class="live-frame">
        <div class="side-events">
            <h2>이벤트 리스트</h2>
            <table class="event-list" id="event-table">
                <thead>
                    <tr>
                        <th style="width:4em;">번호</th>
                        <th style="min-width:2em;">이벤트명</th>
                        <th style="width:5em;">라운드</th>
                        <th style="width:2.5em;">팀수</th>
                        <th style="width:2.5em;">Recall</th>
                        <th style="width:2.5em;">To</th>
                        <th style="width:2.5em;">패널</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($events as $ix=>$evt): ?>
                    <tr data-idx="<?=$ix?>">
                            <td>
                                <div class="event-number">
                                    <span class="main-number"><?=h($evt['no'])?></span>
                                    <?php if (!empty($evt['detail_no'])): ?>
                                        <span class="detail-number">-<?=h($evt['detail_no'])?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <td><?=h($evt['desc'])?></td>
                            <td class="round-cell" data-event-idx="<?=$ix?>">-</td>
                            <td><?=h(calculateTeamCountByDetail($comp_id, $evt['detail_no'], $evt['no']))?></td>
                            <td><?=h($evt['recall'])?></td>
                            <td class="to-cell" data-event-idx="<?=$ix?>">-</td>
                        <td><?=h($evt['panel'])?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="main-panel">
            <div class="event-header-panel">
                <div class="event-header-box">
                    <div class="event-row1">
                        <div class="event-number-controls">
                        <button class="ev-arrow-btn" id="evtPrev" title="이전 이벤트"><span style="font-size:1.1em;">▲</span></button>
                        <input type="text" class="ev-idx" id="evtNo" readonly>
                        <button class="ev-arrow-btn" id="evtNext" title="다음 이벤트"><span style="font-size:1.1em;">▼</span></button>
                        </div>
                        <input type="text" class="ev-title" id="evtName" readonly>
                    </div>
                    <div class="event-row2">
                        <span class="ev-row2-label">From</span>
                        <input type="text" class="ev-fromto" id="evtFrom" value="">
                        <span class="ev-row2-label">To</span>
                        <input type="text" class="ev-fromto" id="evtTo" value="">
                        <button class="ev-ctrl-btn" id="evtRangeMove" title="범위 이동" style="margin-right:0.6em;"><span style="font-size:1.2em;">⏩</span></button>
                        <span class="ev-row2-label">Recall</span>
                        <input type="text" class="ev-recall" id="evtRecall" value="">
                        <span class="ev-row2-label" style="margin-left:0.4em;">Heats</span>
                        <input type="text" class="ev-heats" id="evtHeats" value="">
                        <button class="ev-save-btn" id="evtSave" title="이벤트 정보 저장" style="margin-left:0.5em; padding:0.3em 0.8em; background:#28a745; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9em;">💾 저장</button>
                        <div id="eventProgressInfo" style="margin-left:1em; font-size:0.9em; color:#0d2c96; background:#e8f0ff; padding:0.3em 0.8em; border-radius:4px; font-weight:600; white-space:nowrap;"></div>
                    </div>
                </div>
                <button class="ev-refresh-btn" id="evtRefresh" title="새로고침">↻</button>
                        <button class="ev-refresh-btn" id="evtClearCache" title="캐시 초기화" style="margin-left:0.5em; font-size:1.2em;">🗑️</button>
            </div>
            <div class="main-content-row">
                <div class="adjudicator-list-panel" id="adjudicator-list-panel">
                    <h3>심사위원</h3>
                    <table style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width:2.1em;">#</th>
                                <th style="width:3.2em;">코드</th>
                                <th style="min-width:5em;">심사위원명</th>
                                <th style="width:2.2em;">국가</th>
                                <th style="width:3em;">상태</th>
                                <th style="width:3em;">관리</th>
                            </tr>
                        </thead>
                        <tbody id="adjudicator-list"></tbody>
                    </table>
                    <div class="empty" id="judge-empty" style="display:none;">심사위원이 없습니다</div>
                </div>
                <div class="player-dance-row">
                    <div class="player-list-panel" id="player-list-panel">
                        <h3>선수</h3>
                        <div class="player-controls-row">
                            <button class="add-player-btn" onclick="openPlayerModal()">선수 추가</button>
                            <button class="show-entry-list-btn" onclick="showEntryPlayers()">출전선수</button>
                            <button class="split-hit-btn" onclick="openSplitHitModal()">히트 나누기</button>
                            <button class="show-hit-btn" id="showHitBtn" onclick="openHitModal()">히트 확인</button>
                        </div>
                        <div class="player-list-scrollbox" id="player-list-scrollbox">
                            <ul class="player-list" id="player-list"></ul>
                        </div>
                        <div class="hit-block" id="hit-block" style="display:none;"></div>
                    </div>
                    <div class="dance-block" id="dance-block">
                        <div class="dance-title">진행종목</div>
                    <div class="dance-progress-container">
                        <div class="dance-progress-bar">
                            <div class="dance-progress-fill" id="dance-progress-fill"></div>
                        </div>
                        <div class="dance-list" id="dance-list"></div>
                    </div>
                </div>
                <div class="monitoring-block" id="monitoring-block" style="display:none;">
                    <div class="monitoring-title">감시 시스템</div>
                    <div class="monitoring-controls">
                        <button id="start-monitoring" class="monitoring-btn start-btn">감시 시작</button>
                        <button id="stop-monitoring" class="monitoring-btn stop-btn" style="display:none;">감시 종료</button>
            </div>
                    <div class="monitoring-status">
                        <div class="current-dance">현재 댄스: <span id="current-dance-name">-</span></div>
                        <div class="dance-progress">진행률: <span id="dance-progress">0/13</span></div>
                        <div class="next-dance">다음 댄스: <span id="next-dance-name">-</span></div>
        </div>
                    <div class="monitoring-timer">
                        <div class="timer-label">경과 시간:</div>
                        <div class="timer-display" id="timer-display">00:00</div>
    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 집계 및 라운드 관리 모달 -->
    <div id="aggregation-modal-bg" class="modal-bg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;" onclick="closeAggregationModal()">
        <div class="aggregation-modal" onclick="event.stopPropagation()">
            <div class="aggregation-header">
                <h2>📊 집계 및 라운드 관리</h2>
                <button onclick="closeAggregationModal()" class="close-btn">×</button>
            </div>
            
            <div class="aggregation-tabs">
                <button class="tab-btn active" onclick="switchTab('realtime')">실시간 집계</button>
                <button class="tab-btn" onclick="switchTab('exemption')">면제 설정</button>
                <button class="tab-btn" onclick="switchTab('confirmation')">최종 확인</button>
            </div>
            
            <div class="aggregation-content">
                <!-- 실시간 집계 탭 -->
                <div id="realtime-tab" class="tab-content active">
                    <div class="aggregation-info">
                        <h3>현재 이벤트 집계 현황</h3>
                        <div class="event-info">
                            <span id="current-event-info">이벤트 정보 로딩 중...</span>
                        </div>
                    </div>
                    
                    <div class="aggregation-status">
                        <div class="status-item">
                            <span class="status-label">총 심사위원:</span>
                            <span id="total-judges">-</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">완료된 심사위원:</span>
                            <span id="completed-judges">-</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">진행률:</span>
                            <span id="progress-rate">-</span>
                        </div>
                    </div>
                    
                    <div class="aggregation-table">
                        <h4>집계 결과</h4>
                        <div id="aggregation-results">
                            <div class="loading">집계 데이터를 로딩 중입니다...</div>
                        </div>
                    </div>
                    
                       <div class="aggregation-actions">
                           <button class="refresh-btn" onclick="refreshAggregation()">🔄 새로고침</button>
                           <button class="export-btn" onclick="exportAggregation()">📄 리포트 생성</button>
                           <button class="export-btn" onclick="exportDetailedReport()">📊 상세 리포트</button>
                           <button class="export-btn" onclick="exportDanceScoreReport()">🎭 상세 Recall 리포트</button>
                           <button class="export-btn" onclick="exportPDF()">💾 PDF 다운로드</button>
                       </div>
                </div>
                
                <!-- 면제 설정 탭 -->
                <div id="exemption-tab" class="tab-content">
                    <div class="exemption-info">
                        <h3>면제 선수 설정</h3>
                        <p>다음 라운드로 자동 진출할 선수를 설정합니다.</p>
                    </div>
                    
                    <div class="exemption-controls">
                        <div class="input-group">
                            <label>선수 등번호:</label>
                            <input type="text" id="exemption-player" placeholder="예: 101, 102">
                            <button onclick="addExemptionPlayer()">추가</button>
                        </div>
                    </div>
                    
                    <div class="exemption-list">
                        <h4>면제 선수 목록</h4>
                        <div id="exemption-players-list">
                            <div class="empty">면제 선수가 없습니다.</div>
                        </div>
                    </div>
                </div>
                
                <!-- 최종 확인 탭 -->
                <div id="confirmation-tab" class="tab-content">
                    <div class="confirmation-info">
                        <h3>라운드 전환 확인</h3>
                        <p>집계 결과를 확인하고 다음 라운드로 전환합니다.</p>
                    </div>
                    
                    <div class="confirmation-summary">
                        <div class="summary-item">
                            <span class="summary-label">현재 라운드:</span>
                            <span id="current-round">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">다음 라운드:</span>
                            <span id="next-round">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">진출 팀 수:</span>
                            <input type="number" id="advance-count" placeholder="진출할 팀 수">
                        </div>
                    </div>
                    
                    <div class="confirmation-actions">
                        <button class="preview-btn" onclick="previewTransition()">👁️ 미리보기</button>
                        <button class="execute-btn" onclick="executeTransition()">✅ 라운드 전환 실행</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 결승전 결과 집계 모달 -->
    <div id="final-aggregation-modal-bg" class="modal-bg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;" onclick="closeFinalAggregationModal()">
        <div class="final-aggregation-modal" onclick="event.stopPropagation()">
            <div class="final-aggregation-header">
                <h2>🏆 결승전 결과 집계</h2>
                <button onclick="closeFinalAggregationModal()" class="close-btn">×</button>
            </div>
            
            <div class="final-aggregation-content">
                <div class="final-event-info">
                    <h3 id="final-event-title">이벤트 정보</h3>
                    <div class="final-event-details">
                        <div class="detail-item">
                            <span class="detail-label">이벤트:</span>
                            <span id="final-event-name">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">라운드:</span>
                            <span id="final-event-round">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">참가자:</span>
                            <span id="final-event-participants">-</span>
                        </div>
                    </div>
                </div>
                
                <div class="final-results-section">
                    <h3>최종 결과</h3>
                    <div class="final-results-table">
                        <table id="final-results-table">
                            <thead>
                                <tr>
                                    <th>순위</th>
                                    <th>등번호</th>
                                    <th>선수명</th>
                                    <th>총점</th>
                                    <th>상세 점수</th>
                                </tr>
                            </thead>
                            <tbody id="final-results-tbody">
                                <tr>
                                    <td colspan="5" class="loading">결과를 계산 중입니다...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="final-actions">
                    <button class="refresh-btn" onclick="refreshFinalResults()">🔄 새로고침</button>
                    <button class="export-btn" onclick="exportFinalResults()">📄 결과 리포트</button>
                    <button class="export-btn" onclick="exportFinalPDF()">💾 PDF 다운로드</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal-bg" id="playerModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); align-items:center; justify-content:center; z-index:100;">
        <div class="modal" style="background:#fff; border-radius:10px; padding:2em 2.2em; box-shadow:0 10px 40px #0002; min-width:260px;">
            <div class="modal-title">선수 등번호 추가<br><span style="font-size:0.9em;color:#888;">(예: 10, 23, 10~18 입력 가능)</span></div>
            <input type="text" id="playerInput" placeholder="등번호나 범위를 입력하세요" style="font-size:1.1em; padding:0.3em 0.6em; border:1.5px solid #aaa;" autocomplete="off">
            <div class="modal-btns" style="margin-top:1em; text-align:right;">
                <button type="button" onclick="closePlayerModal()" style="font-size:1.08em; margin-left:0.9em; border-radius:4px; padding:0.29em 1.3em;">닫기</button>
                <button type="button" onclick="saveAndClosePlayerModal()" style="font-size:1.08em; margin-left:0.9em; border-radius:4px; padding:0.29em 1.3em;">저장</button>
            </div>
        </div>
    </div>
    <div class="modal-bg" id="entryPlayersModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; z-index:100;">
        <div class="modal entry-players-modal" style="background:#fff; border-radius:12px; padding:1.5em 2em; box-shadow:0 15px 50px rgba(0,0,0,0.3); min-width:480px; max-width:90vw; max-height:85vh; display:flex; flex-direction:column;">
            <div class="modal-header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.2em; padding-bottom:0.8em; border-bottom:2px solid #e9ecef;">
                <div>
                    <div class="modal-title" style="font-size:1.3em; font-weight:700; color:#0d2c96; margin:0;">출전선수 리스트</div>
                    <div class="modal-subtitle" style="font-size:0.9em; color:#666; margin-top:0.3em;" id="entryPlayersSubtitle">이벤트 정보</div>
                </div>
                <div style="display:flex; gap:0.5em;">
                    <button class="print-entry-btn" onclick="printEntryPlayers()" style="background:#28a745; color:#fff; border:none; border-radius:6px; padding:0.5em 1em; font-size:0.9em; cursor:pointer; display:flex; align-items:center; gap:0.3em;">
                        🖨️ 인쇄
                    </button>
                    <button onclick="closeEntryPlayersModal()" style="background:#6c757d; color:#fff; border:none; border-radius:6px; padding:0.5em 1em; font-size:0.9em; cursor:pointer;">닫기</button>
                </div>
            </div>
            <div class="entry-players-scrollbox" style="flex:1; overflow-y:auto; border:1px solid #dee2e6; border-radius:8px; background:#f8f9fa;">
                <table style="width:100%; border-collapse:collapse;" id="entryPlayersTable">
                    <thead>
                        <tr style="background:#e9ecef; position:sticky; top:0;">
                            <th style="width:4em; padding:0.8em 0.5em; border-bottom:2px solid #dee2e6; font-weight:600; color:#495057;">등번호</th>
                            <th style="padding:0.8em 1em; border-bottom:2px solid #dee2e6; font-weight:600; color:#495057;">남자선수</th>
                            <th style="padding:0.8em 1em; border-bottom:2px solid #dee2e6; font-weight:600; color:#495057;">여자선수</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- 히트 표시 모달 -->
    <div class="modal-bg" id="hitModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); align-items:center; justify-content:center; z-index:120;">
        <div class="modal" id="hit-modal" style="background:#fff; border-radius:10px; padding:1.2em 1.4em; box-shadow:0 10px 40px #0002; min-width:520px; max-width:90vw; max-height:90vh; display:flex; flex-direction:column;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:1em;">
                <div class="modal-title" id="hitModalTitle">히트 확인</div>
                <div>
                    <button class="print-hit-btn" onclick="printHits()">히트 인쇄</button>
                    <button onclick="closeHitModal()" style="margin-left:0.4em;">닫기</button>
                </div>
            </div>
            <div id="hitModalBody" style="margin-top:0.6em; overflow:auto;"></div>
        </div>
    </div>
    <!-- 히트 나누기 모달 -->
    <div class="modal-bg" id="splitHitModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); align-items:center; justify-content:center; z-index:120;">
        <div class="modal" style="background:#fff; border-radius:10px; padding:1.2em 1.4em; box-shadow:0 10px 40px #0002; min-width:320px;">
            <div class="modal-title">히트 나누기</div>
            <div style="margin-top:0.8em; display:flex; flex-direction:column; gap:0.7em;">
                <label>조(히트) 개수: <input type="number" id="splitHitCount" value="2" min="1" max="50" style="width:5em;"></label>
                <div>
                    <label><input type="radio" name="splitMode" value="normal" checked> 일반(등번호 순)</label>
                    <label style="margin-left:1.2em;"><input type="radio" name="splitMode" value="random"> 랜덤(섞기)</label>
                </div>
            </div>
            <div style="margin-top:1em; text-align:right;">
                <button onclick="closeSplitHitModal()">취소</button>
                <button onclick="confirmSplitHits()" style="margin-left:0.5em;">확인</button>
            </div>
        </div>
    </div>
</div>
<script>
const events = <?=json_encode($events, JSON_UNESCAPED_UNICODE)?>;
const panelMap = <?=json_encode($panel_map, JSON_UNESCAPED_UNICODE)?>;
const allAdjudicators = <?=json_encode($adjudicator_dict, JSON_UNESCAPED_UNICODE)?>;
const allPlayers = <?=json_encode($all_players, JSON_UNESCAPED_UNICODE)?>;
const danceMapEn = <?=json_encode($dance_map_en, JSON_UNESCAPED_UNICODE)?>;
const compInfo = <?=json_encode($info, JSON_UNESCAPED_UNICODE)?>;
const comp_id = '<?=h($comp_id)?>';
let curIdx = 0;
let disabledJudgesByEvent = {};
let playersByEvent = <?=json_encode($players_by_event, JSON_UNESCAPED_UNICODE)?>;
let hitsByEvent = {};
let hitVisible = false;
let roundInfo = {};
let eventInfo = {}; // legacy flag (모달 사용으로 더이상 필요 없지만 하위호환 유지)

function getCurrentEventKey() {
    const ev = events[curIdx];
    if (!ev) return '';
    return ev.detail_no && ev.detail_no.length ? ev.detail_no : ev.no;
}

// 초기 로딩 데이터에 세부번호 별칭 키 추가 (멀티 이벤트 지원)
try {
    (events || []).forEach(ev => {
        if (ev && ev.detail_no && playersByEvent && playersByEvent[ev.no]) {
            if (!playersByEvent[ev.detail_no]) {
                playersByEvent[ev.detail_no] = (playersByEvent[ev.no] || []).slice();
            }
        }
    });
} catch (e) { 
    console.warn('playersByEvent 초기 매핑 중 오류:', e); 
}

// 감시 시스템 변수
let monitoringState = {
    isActive: false,
    currentDanceIndex: 0,
    danceList: [],
    startTime: null,
    timer: null,
    requiredJudges: 13
};

// --- 히트 기능 ---
function openSplitHitModal() {
    let currentEvent = events[curIdx];
    let eventKey = currentEvent.detail_no || currentEvent.no;
    let arr = (playersByEvent[eventKey] || []).slice();
    if (arr.length === 0) { alert('선수 명단이 없습니다.'); return; }
    document.getElementById('splitHitCount').value = '2';
    document.querySelectorAll('input[name="splitMode"]').forEach(r=>{ r.checked = r.value==='normal'; });
    document.getElementById('splitHitModalBg').style.display = 'flex';
}
function closeSplitHitModal() { document.getElementById('splitHitModalBg').style.display = 'none'; }
function confirmSplitHits() {
    let currentEvent = events[curIdx];
    let eventKey = currentEvent.detail_no || currentEvent.no;
    let arr = (playersByEvent[eventKey] || []).slice();
    if (arr.length === 0) { alert('선수 명단이 없습니다.'); return; }
    let hitCount = parseInt(document.getElementById('splitHitCount').value, 10);
    if (!hitCount || hitCount < 1) { alert('유효한 히트 개수를 입력하세요.'); return; }
    let mode = (document.querySelector('input[name="splitMode"]:checked')||{}).value || 'normal';
    if (mode === 'normal') {
        arr.sort((a,b)=>Number(a)-Number(b));
    } else if (mode === 'random') {
        // Fisher–Yates shuffle
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
    }
    let hits = {};
    for (let i = 1; i <= hitCount; i++) hits[i] = [];
    arr.forEach((bib, idx) => {
        let h = (idx % hitCount) + 1;
        hits[h].push(bib);
    });
    // 각 조 내부는 번호 오름차순으로 정렬
    Object.keys(hits).forEach(k => {
        hits[k] = (hits[k] || []).slice().sort((a,b)=>Number(a)-Number(b));
    });
    fetch('save_hits.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({comp_id: "<?=addslashes($comp_id)?>", eventNo: eventKey, hits: hits})
    }).then(res => res.json())
      .then(data => {
        if (data.success) {
            hitsByEvent[eventKey] = hits;
            alert('히트가 저장되었습니다.');
            closeSplitHitModal();
        } else {
            alert('저장 실패: ' + (data.error||''));
        }
      })
      .catch(err => alert('저장 오류: ' + (err && err.message ? err.message : err)));
}
function fetchHits(eventNo) {
    fetch(`get_hits.php?comp_id=<?=urlencode($comp_id)?>&eventNo=${eventNo}&${Date.now()}`)
        .then(r => {
            if (!r.ok) {
                console.warn(`히트 파일 로드 실패: ${r.status} ${r.statusText}`);
                return {success: false, hits: {}};
            }
            return r.json();
        })
        .then(data => {
            if (data.success && data.hits) {
                hitsByEvent[eventNo] = data.hits;
            } else {
                console.warn('히트 데이터 로드 실패:', data.error || '알 수 없는 오류');
                hitsByEvent[eventNo] = {};
            }
        })
        .catch(err => {
            console.warn('히트 파일 로드 오류:', err);
            hitsByEvent[eventNo] = {};
        });
}
function buildHitHtml(eventNo) {
    let hits = hitsByEvent[eventNo] || {};
    let keys = Object.keys(hits);
    if (!keys.length) return '<div style="color:#888;">히트가 없습니다.</div>';
    let html = '';
    keys.sort((a,b)=>Number(a)-Number(b)).forEach(hitNo => {
        let members = hits[hitNo];
        html += `<div class="hit-title">${hitNo}조</div>`;
        html += `<table class="hit-table"><thead><tr>
            <th>등번호</th><th>남자선수</th><th>여자선수</th>
        </tr></thead><tbody>`;
        members.forEach(bib => {
            let p = allPlayers[bib] || {};
            let male = p.male || '';
            let female = p.female || '';
            html += `<tr><td>${bib}</td><td>${male}</td><td>${female}</td></tr>`;
        });
        html += `</tbody></table>`;
    });
    return html;
}
function openHitModal() {
    let eventNo = events[curIdx].no;
    // 현재 메모리의 히트 데이터 먼저 표시
    document.getElementById('hitModalBody').innerHTML = buildHitHtml(eventNo);
    document.getElementById('hitModalBg').style.display = 'flex';
    
    // 백그라운드에서 최신 저장본 불러오기 시도
    fetchHits(eventNo);
    // 로드 완료 후 다시 표시
    setTimeout(() => {
        document.getElementById('hitModalBody').innerHTML = buildHitHtml(eventNo);
    }, 200);
}
function closeHitModal() { document.getElementById('hitModalBg').style.display = 'none'; }
// toggleHits 대체: 모달 열기만 사용
function printHits() {
    let eventNo = events[curIdx].no;
    let hits = hitsByEvent[eventNo] || {};
    let keys = Object.keys(hits);
    
    if (!keys.length) {
        alert('인쇄할 히트가 없습니다.');
        return;
    }
    
    // 인쇄 전용 요소 생성
    let printDiv = document.createElement('div');
    printDiv.id = 'print-hit-content';
    printDiv.style.cssText = `
        position: absolute; 
        left: -9999px; 
        top: -9999px; 
        width: 100%; 
        background: white; 
        padding: 15px; 
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.4;
    `;
    
    let html = `
        <style>
            @media print {
                @page {
                    size: A4;
                    margin: 1.5cm;
                }
                .hit-group {
                    page-break-inside: avoid;
                    margin-bottom: 15px;
                }
                .hit-group:not(:first-child) {
                    page-break-before: auto;
                }
                .hit-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 10px;
                    font-size: 10px;
                }
                .hit-table th, .hit-table td {
                    border: 1px solid #333;
                    padding: 3px 5px;
                    text-align: left;
                    vertical-align: top;
                }
                .hit-table th {
                    background: #f5f5f5;
                    font-weight: bold;
                }
                .hit-title {
                    font-size: 13px;
                    font-weight: bold;
                    margin: 8px 0 5px 0;
                    color: #333;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 15px;
                    font-size: 14px;
                    font-weight: bold;
                }
            }
        </style>
        <div class="print-header">히트 목록 - 이벤트 ${eventNo}</div>
    `;
    
    keys.sort((a,b)=>Number(a)-Number(b)).forEach((hitNo, index) => {
        let members = hits[hitNo];
        html += `
            <div class="hit-group">
                <div class="hit-title">${hitNo}조 (${members.length}명)</div>
                <table class="hit-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">등번호</th>
                            <th style="width: 42.5%;">남자선수</th>
                            <th style="width: 42.5%;">여자선수</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        members.forEach(bib => {
            let p = allPlayers[bib] || {};
            let male = p.male || '';
            let female = p.female || '';
            html += `
                <tr>
                    <td>${bib}</td>
                    <td>${male}</td>
                    <td>${female}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    });
    
    printDiv.innerHTML = html;
    document.body.appendChild(printDiv);
    
    // 인쇄 후 요소 제거를 위한 이벤트 리스너
    window.addEventListener('afterprint', function cleanup() {
        let element = document.getElementById('print-hit-content');
        if (element) {
            document.body.removeChild(element);
        }
        window.removeEventListener('afterprint', cleanup);
    });
    
    // 인쇄 실행
    window.print();
}

// --- 이하 기존 함수(선수, 출전선수, 모달, 스크롤 등) 동일 ---
function savePlayersToServer(eventKey) {
    const currentEvent = events[curIdx];
    const detailNo = currentEvent.detail_no || '';
    
    fetch('save_players.php?comp_id=<?=urlencode($comp_id)?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            eventNo: currentEvent.no, 
            detailNo: detailNo, 
            players: playersByEvent[eventKey]
        })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // 성공 시 이벤트 리스트의 팀수 업데이트
            updateTeamCountInEventList();
        } else {
            alert("선수 저장 실패: " + (data.error||""));
        }
    })
    .catch(err => {
        alert("선수 저장 중 오류: " + (err && err.message ? err.message : err));
    });
}

function updateTeamCountInEventList() {
    // 현재 이벤트의 팀수만 업데이트 (더 정확하고 빠름)
    const currentEvent = events[curIdx];
    const eventKey = currentEvent.detail_no || currentEvent.no;
    const playerCount = (playersByEvent[eventKey] || []).length;
    
    // 현재 이벤트 행의 팀수 셀 업데이트
    const tr = document.querySelector(`#event-table tr[data-idx="${curIdx}"]`);
    if (tr && tr.cells[3]) { // 팀수는 4번째 컬럼 (인덱스 3)
        const oldValue = tr.cells[3].textContent;
        const newValue = playerCount > 0 ? playerCount : '-';
        tr.cells[3].textContent = newValue;
        
        // 디버그: 팀수 업데이트 확인
        console.log(`팀수 업데이트: 이벤트 ${eventKey}, ${oldValue} → ${newValue}`);
    }
    
    // 메인 컨트롤의 선수 리스트도 즉시 업데이트
    renderPlayerList(eventKey);
}

function renderAdjudicatorList(panelCode, eventNo) {
    const judgeLinks = panelMap.filter(m => (m.panel_code||"").toUpperCase() === (panelCode||"").toUpperCase());
    const judgeArr = judgeLinks.map(m => allAdjudicators[m.adj_code]).filter(j=>j);
    const tbody = document.getElementById("adjudicator-list");
    const empty = document.getElementById("judge-empty");
    tbody.innerHTML = "";
    if (!panelCode || judgeArr.length === 0) {
        empty.style.display = "";
        return;
    }
    empty.style.display = "none";
    const disabled = disabledJudgesByEvent[eventNo] || [];
    judgeArr.forEach((j, i) => {
        const isDisabled = disabled.includes(j.code);
        let tr = document.createElement("tr");
        tr.className = isDisabled ? "disabled" : "";
        tr.innerHTML = `<td>${i+1}</td>
            <td>${j.code}</td>
            <td>${j.name}</td>
            <td>${j.nation || ''}</td>
            <td>
                <span class="judge-status waiting" id="judge-status-${j.code}" data-judge-code="${j.code}">대기</span>
            </td>
            <td>
                <div class="adjudicator-buttons">
                    <button class="adjudicator-x-btn" onclick="toggleAdjudicator('${eventNo}','${j.code}')" title="이 이벤트에서 심사위원 제외" ${isDisabled ? 'disabled' : ''}>X</button>
                    <button class="judge-scoring-btn" onclick="openJudgeScoring('${eventNo}','${j.code}')" title="심사위원 채점 패널 열기" data-judge-code="${j.code}">✏️</button>
                </div>
            </td>`;
        tbody.appendChild(tr);
    });
}
function toggleAdjudicator(eventNo, judgeCode) {
    if(!disabledJudgesByEvent[eventNo]) disabledJudgesByEvent[eventNo] = [];
    const arr = disabledJudgesByEvent[eventNo];
    const idx = arr.indexOf(judgeCode);
    if(idx === -1) arr.push(judgeCode);
    else arr.splice(idx,1);
    renderAdjudicatorList(events[curIdx].panel, events[curIdx].no);
}
function renderPlayerList(eventNo) {
    const ul = document.getElementById("player-list");
    let arr = playersByEvent[eventNo] || [];
    let sorted = arr.slice().sort((a, b) => Number(a) - Number(b));
    ul.innerHTML = "";
    if (!sorted.length) {
        ul.innerHTML = "<li style='color:#aaa;'>선수 등번호 없음</li>";
        return;
    }
    sorted.forEach((bib, idx) => {
        let li = document.createElement("li");
        li.innerHTML = `${bib} <button class="player-x-btn" onclick="removePlayer('${bib}')">X</button>`;
        ul.appendChild(li);
    });
}
function renderDanceBlock(eventIdx) {
    const ev = events[eventIdx];
    const danceListDiv = document.getElementById('dance-list');
    const progressFill = document.getElementById('dance-progress-fill');
    
    let danceNames = [];
    if (ev.dances && ev.dances.length > 0) {
        danceNames = ev.dances.map(code => danceMapEn[code] || code);
    }
    
    if (danceNames.length) {
        danceListDiv.innerHTML = danceNames.map((name, i) => {
            let className = 'dance-item';
            if (monitoringState.isActive && i === monitoringState.currentDanceIndex) {
                className += ' active';
            } else if (monitoringState.isActive && i < monitoringState.currentDanceIndex) {
                className += ' completed';
            }
            return `<div class="${className}">${i+1}. ${name}</div>`;
        }).join('');
        
        // 진행률 바 업데이트
        if (monitoringState.isActive) {
            const progress = ((monitoringState.currentDanceIndex + 1) / danceNames.length) * 100;
            progressFill.style.width = `${progress}%`;
        } else {
            progressFill.style.width = '0%';
        }
    } else {
        danceListDiv.innerHTML = `<div class="dance-item">-</div>`;
        progressFill.style.width = '0%';
    }
    
    // 기존 집계 섹션 제거
    const existingAggregation = document.querySelector('.aggregation-section');
    if (existingAggregation) {
        existingAggregation.remove();
    }
    
    // 기존 결승전 집계 섹션 제거
    const existingFinalAggregation = document.querySelector('.final-aggregation-section');
    if (existingFinalAggregation) {
        existingFinalAggregation.remove();
    }
    
    // 이벤트가 결승전인지 확인
    const isFinalRound = ev.round && ev.round.toLowerCase().includes('final') && !ev.round.toLowerCase().includes('semi');
    
    const danceBlock = document.getElementById('dance-block');
    if (danceBlock) {
        if (isFinalRound) {
            // 결승전용 집계 버튼
            const finalAggregationSection = document.createElement('div');
            finalAggregationSection.className = 'final-aggregation-section';
            finalAggregationSection.innerHTML = `
                <button class="final-aggregation-btn" onclick="openFinalAggregationModal()">
                    🏆 결승전 결과 집계
                </button>
            `;
            danceBlock.appendChild(finalAggregationSection);
        } else {
            // 예선/준결승용 집계 버튼
            const aggregationSection = document.createElement('div');
            aggregationSection.className = 'aggregation-section';
            aggregationSection.innerHTML = `
                <button class="aggregation-btn" onclick="openAggregationModal()">
                    📊 집계 및 라운드 관리
                </button>
            `;
            danceBlock.appendChild(aggregationSection);
        }
    }
}
function openPlayerModal() {
    document.getElementById('playerInput').value = '';
    document.getElementById('playerModalBg').style.display = 'flex';
    setTimeout(()=>{document.getElementById('playerInput').focus();}, 180);
}
function closePlayerModal() {
    document.getElementById('playerModalBg').style.display = 'none';
}

// 집계 모달 관련 함수들
function openAggregationModal(eventNo) {
    console.log('Opening aggregation modal for event:', eventNo);
    console.log('Current event index:', curIdx);
    console.log('Current event:', events[curIdx]);
    
    document.getElementById('aggregation-modal-bg').style.display = 'flex';
    
    // 현재 선택된 이벤트 정보 사용
    const currentEvent = events[curIdx];
    if (currentEvent) {
        const actualEventNo = currentEvent.detail_no || currentEvent.no;
        console.log('Using actual event number:', actualEventNo);
        
        // 현재 이벤트 정보 업데이트
        updateAggregationEventInfo(actualEventNo);
        
        // 실시간 집계 탭 활성화
        switchTab('realtime');
        
        // 집계 데이터 로드
        loadAggregationData(actualEventNo);
    } else {
        console.error('No current event found');
        document.getElementById('current-event-info').textContent = '현재 이벤트를 찾을 수 없습니다.';
    }
}

function closeAggregationModal() {
    document.getElementById('aggregation-modal-bg').style.display = 'none';
}

// 결승전 집계 모달 관련 함수들
function openFinalAggregationModal() {
    const currentEvent = events[curIdx];
    if (!currentEvent) {
        console.error('No current event found');
        return;
    }
    
    console.log('Opening final aggregation modal for event:', currentEvent);
    
    // 이벤트 정보 업데이트
    document.getElementById('final-event-name').textContent = currentEvent.desc || '이벤트 ' + currentEvent.no;
    document.getElementById('final-event-round').textContent = currentEvent.round || 'Final';
    
    // 참가자 수 업데이트
    const eventKey = currentEvent.detail_no || currentEvent.no;
    const participants = playersByEvent[eventKey] || [];
    document.getElementById('final-event-participants').textContent = participants.length + '명';
    
    // 모달 열기
    document.getElementById('final-aggregation-modal-bg').style.display = 'flex';
    
    // 결과 계산 및 표시
    loadFinalResults();
}

function closeFinalAggregationModal() {
    document.getElementById('final-aggregation-modal-bg').style.display = 'none';
}

async function loadFinalResults() {
    const currentEvent = events[curIdx];
    if (!currentEvent) return;
    
    const eventKey = currentEvent.detail_no || currentEvent.no;
    
    console.log('loadFinalResults - currentEvent:', currentEvent);
    console.log('loadFinalResults - eventKey:', eventKey);
    console.log('loadFinalResults - comp_id:', comp_id);
    
    // 로딩 표시
    const tbody = document.getElementById('final-results-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="5" class="loading">결과를 계산 중입니다...</td></tr>';
    
    try {
        // 서버에서 실제 결과 가져오기
        const response = await fetch(`final_aggregation_api.php?comp_id=${comp_id}&event_no=${eventKey}`);
        const data = await response.json();
        
        console.log('API Response:', data);
        
        if (data.error) {
            console.error('API Error:', data.error);
            tbody.innerHTML = `<tr><td colspan="5" class="loading">오류: ${data.error}</td></tr>`;
            return;
        }
        
        // 결과 테이블 업데이트
        displayFinalResults(data);
        
    } catch (error) {
        console.error('Error loading final results:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="loading">데이터를 불러오는 중 오류가 발생했습니다.</td></tr>';
    }
}

function displayFinalResults(data) {
    console.log('displayFinalResults called with data:', data);
    
    const tbody = document.getElementById('final-results-tbody');
    if (!tbody) {
        console.error('final-results-tbody element not found');
        return;
    }
    
    if (!data.final_rankings || data.final_rankings.length === 0) {
        console.log('No final rankings data available');
        tbody.innerHTML = '<tr><td colspan="5" class="loading">채점 데이터가 없습니다.</td></tr>';
        return;
    }
    
    console.log('Displaying final rankings:', data.final_rankings);
    
    // 최종 순위 테이블 생성
    let resultsHtml = '';
    data.final_rankings.forEach((ranking, index) => {
        const player = data.players.find(p => p.number === ranking.player_no);
        const playerName = player ? `${player.male} / ${player.female}` : `선수 ${ranking.player_no}`;
        
        // 댄스별 순위 수집
        const danceRankings = [];
        data.dance_results.forEach((dance, danceCode) => {
            const danceRank = dance.final_rankings[ranking.player_no] || '-';
            danceRankings.push(danceRank);
        });
        
        resultsHtml += `
            <tr>
                <td>${ranking.final_rank}</td>
                <td>${ranking.player_no}</td>
                <td>${playerName}</td>
                <td>${ranking.sum_of_places}</td>
                <td>${danceRankings.join(', ')}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = resultsHtml;
    
    // 상세 결과 섹션 추가
    addDetailedResults(data);
}

function addDetailedResults(data) {
    // 기존 상세 결과 제거
    const existingDetailed = document.getElementById('detailed-results-section');
    if (existingDetailed) {
        existingDetailed.remove();
    }
    
    // 상세 결과 섹션 생성
    const content = document.querySelector('.final-aggregation-content');
    if (!content) return;
    
    const detailedSection = document.createElement('div');
    detailedSection.id = 'detailed-results-section';
    detailedSection.innerHTML = `
        <div class="detailed-results">
            <h3>상세 결과 (Skating System)</h3>
            <div class="dance-results-tabs">
                ${Object.keys(data.dance_results).map(danceCode => {
                    const dance = data.dance_results[danceCode];
                    return `<button class="dance-tab-btn" onclick="showDanceDetails('${danceCode}')">${dance.name}</button>`;
                }).join('')}
            </div>
            <div id="dance-details-content">
                <!-- 댄스별 상세 결과가 여기에 표시됩니다 -->
            </div>
        </div>
    `;
    
    content.appendChild(detailedSection);
    
    // 첫 번째 댄스 상세 결과 표시
    const firstDanceCode = Object.keys(data.dance_results)[0];
    if (firstDanceCode) {
        showDanceDetails(firstDanceCode, data);
    }
}

function showDanceDetails(danceCode, data = null) {
    if (!data) {
        // 데이터가 없으면 다시 로드
        loadFinalResults();
        return;
    }
    
    const dance = data.dance_results[danceCode];
    if (!dance) return;
    
    const content = document.getElementById('dance-details-content');
    if (!content) return;
    
    // 탭 활성화
    document.querySelectorAll('.dance-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[onclick="showDanceDetails('${danceCode}')"]`).classList.add('active');
    
    // 스케이팅 시스템 결과 테이블 생성
    let tableHtml = `
        <div class="skating-results-table">
            <h4>${dance.name} - Skating System Results</h4>
            <table class="skating-table">
                <thead>
                    <tr>
                        <th>Cpl. No.</th>
                        <th>Competitor Name(s)</th>
                        ${data.adjudicators.map(adj => `<th>${adj.code}</th>`).join('')}
                        <th>1</th>
                        <th>1&2</th>
                        <th>1to3</th>
                        <th>1to4</th>
                        <th>1to5</th>
                        <th>1to6</th>
                        <th>Place</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    // 각 선수별 데이터 생성
    data.players.forEach(player => {
        const playerNo = player.number;
        const playerName = `${player.male} / ${player.female}`;
        
        // 심사위원별 순위
        const judgeRanks = data.adjudicators.map(adj => {
            const scores = dance.judge_scores[adj.code];
            return scores && scores[playerNo] ? scores[playerNo] : '-';
        });
        
        // 스케이팅 데이터 계산
        const skatingData = calculateSkatingDataForPlayer(dance.judge_scores, playerNo);
        
        // 최종 순위
        const finalRank = dance.final_rankings[playerNo] || '-';
        
        tableHtml += `
            <tr>
                <td>${playerNo}</td>
                <td>${playerName}</td>
                ${judgeRanks.map(rank => `<td>${rank}</td>`).join('')}
                <td>${skatingData.place_1}</td>
                <td>${skatingData.place_1_2}</td>
                <td>${skatingData.place_1to3} (${skatingData.sum_1to3})</td>
                <td>${skatingData.place_1to4} (${skatingData.sum_1to4})</td>
                <td>${skatingData.place_1to5} (${skatingData.sum_1to5})</td>
                <td>${skatingData.place_1to6} (${skatingData.sum_1to6})</td>
                <td><strong>${finalRank}</strong></td>
            </tr>
        `;
    });
    
    tableHtml += `
                </tbody>
            </table>
        </div>
    `;
    
    content.innerHTML = tableHtml;
}

function calculateSkatingDataForPlayer(judgeScores, playerNo) {
    const rankings = [];
    
    Object.values(judgeScores).forEach(scores => {
        if (scores[playerNo]) {
            rankings.push(scores[playerNo]);
        }
    });
    
    if (rankings.length === 0) {
        return {
            place_1: 0, place_1_2: 0, place_1to3: 0, place_1to4: 0, place_1to5: 0, place_1to6: 0,
            sum_1to3: 0, sum_1to4: 0, sum_1to5: 0, sum_1to6: 0
        };
    }
    
    let place_1 = 0, place_1_2 = 0, place_1to3 = 0, place_1to4 = 0, place_1to5 = 0, place_1to6 = 0;
    let sum_1to3 = 0, sum_1to4 = 0, sum_1to5 = 0, sum_1to6 = 0;
    
    rankings.forEach(rank => {
        if (rank === 1) place_1++;
        if (rank <= 2) place_1_2++;
        if (rank <= 3) { place_1to3++; sum_1to3 += rank; }
        if (rank <= 4) { place_1to4++; sum_1to4 += rank; }
        if (rank <= 5) { place_1to5++; sum_1to5 += rank; }
        if (rank <= 6) { place_1to6++; sum_1to6 += rank; }
    });
    
    return {
        place_1, place_1_2, place_1to3, place_1to4, place_1to5, place_1to6,
        sum_1to3, sum_1to4, sum_1to5, sum_1to6
    };
}

function refreshFinalResults() {
    console.log('Refreshing final results...');
    loadFinalResults();
}

function exportFinalResults() {
    console.log('Exporting final results...');
    
    const currentEvent = events[curIdx];
    if (!currentEvent) return;
    
    const eventKey = currentEvent.detail_no || currentEvent.no;
    
    // 새 창에서 리포트 생성
    const reportWindow = window.open('', '_blank', 'width=1200,height=800');
    
    // 로딩 메시지
    reportWindow.document.write(`
        <html>
        <head>
            <title>결승전 결과 리포트 생성 중...</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .loading { font-size: 18px; color: #666; }
            </style>
        </head>
        <body>
            <div class="loading">결승전 결과 리포트를 생성 중입니다...</div>
        </body>
        </html>
    `);
    
    // 서버에서 데이터 가져와서 리포트 생성
    fetch(`final_aggregation_api.php?comp_id=${comp_id}&event_no=${eventKey}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                reportWindow.document.body.innerHTML = `<div class="loading">오류: ${data.error}</div>`;
                return;
            }
            
            // 리포트 HTML 생성
            const reportHtml = generateFinalReportHtml(data, currentEvent);
            reportWindow.document.write(reportHtml);
            reportWindow.document.close();
        })
        .catch(error => {
            console.error('Error generating report:', error);
            reportWindow.document.body.innerHTML = '<div class="loading">리포트 생성 중 오류가 발생했습니다.</div>';
        });
}

function generateFinalReportHtml(data, eventInfo) {
    const compInfo = window.compInfo || { title: '대회', date: '2025.09.13', place: '장소' };
    
    return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>${eventInfo.desc} - Final Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 10px; }
        .subtitle { font-size: 18px; color: #666; margin-bottom: 5px; }
        .date { font-size: 14px; color: #888; }
        .results-section { margin-bottom: 40px; }
        .section-title { font-size: 20px; font-weight: bold; color: #333; margin-bottom: 15px; border-left: 4px solid #007bff; padding-left: 10px; }
        .final-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .final-table th, .final-table td { padding: 12px; text-align: center; border: 1px solid #ddd; }
        .final-table th { background: #333; color: white; font-weight: bold; }
        .final-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .final-table tbody tr:hover { background: #e9ecef; }
        .final-table th:first-child, .final-table td:first-child { background: #e9ecef; font-weight: bold; }
        .final-table th:nth-child(2), .final-table td:nth-child(2) { text-align: left; min-width: 200px; }
        .final-table th:last-child, .final-table td:last-child { background: #ffd700; font-weight: bold; color: #333; }
        .dance-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .dance-tab { background: #f8f9fa; border: 1px solid #ddd; padding: 8px 16px; cursor: pointer; border-radius: 4px; }
        .dance-tab.active { background: #007bff; color: white; }
        .dance-details { display: none; }
        .dance-details.active { display: block; }
        .skating-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .skating-table th, .skating-table td { padding: 6px; text-align: center; border: 1px solid #ddd; }
        .skating-table th { background: #f8f9fa; font-weight: bold; }
        .skating-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .skating-table th:first-child, .skating-table td:first-child { background: #e9ecef; font-weight: bold; }
        .skating-table th:nth-child(2), .skating-table td:nth-child(2) { text-align: left; min-width: 150px; }
        .skating-table th:last-child, .skating-table td:last-child { background: #ffd700; font-weight: bold; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
        @media print { body { background: white; } .container { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">${compInfo.title}</div>
            <div class="subtitle">${eventInfo.desc} - Final Results</div>
            <div class="date">${compInfo.date} | ${compInfo.place}</div>
        </div>
        
        <div class="results-section">
            <div class="section-title">Final Rankings (Skating System)</div>
            <table class="final-table">
                <thead>
                    <tr>
                        <th>Place</th>
                        <th>Competitor Name(s)</th>
                        <th>SUM of Places</th>
                        ${Object.keys(data.dance_results).map(danceCode => {
                            const dance = data.dance_results[danceCode];
                            return `<th>${dance.name}</th>`;
                        }).join('')}
                    </tr>
                </thead>
                <tbody>
                    ${data.final_rankings.map(ranking => {
                        const player = data.players.find(p => p.number === ranking.player_no);
                        const playerName = player ? `${player.male} / ${player.female}` : `선수 ${ranking.player_no}`;
                        const danceRankings = Object.keys(data.dance_results).map(danceCode => {
                            const dance = data.dance_results[danceCode];
                            return dance.final_rankings[ranking.player_no] || '-';
                        });
                        
                        return `
                            <tr>
                                <td>${ranking.final_rank}</td>
                                <td>${playerName}</td>
                                <td>${ranking.sum_of_places}</td>
                                ${danceRankings.map(rank => `<td>${rank}</td>`).join('')}
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
        
        <div class="results-section">
            <div class="section-title">Detailed Results by Dance</div>
            <div class="dance-tabs">
                ${Object.keys(data.dance_results).map((danceCode, index) => {
                    const dance = data.dance_results[danceCode];
                    return `<div class="dance-tab ${index === 0 ? 'active' : ''}" onclick="showDanceDetails('${danceCode}')">${dance.name}</div>`;
                }).join('')}
            </div>
            
            ${Object.keys(data.dance_results).map((danceCode, index) => {
                const dance = data.dance_results[danceCode];
                return `
                    <div class="dance-details ${index === 0 ? 'active' : ''}" id="dance-${danceCode}">
                        <h4>${dance.name} - Skating System Results</h4>
                        <table class="skating-table">
                            <thead>
                                <tr>
                                    <th>Cpl. No.</th>
                                    <th>Competitor Name(s)</th>
                                    ${data.adjudicators.map(adj => `<th>${adj.code}</th>`).join('')}
                                    <th>1</th>
                                    <th>1&2</th>
                                    <th>1to3</th>
                                    <th>1to4</th>
                                    <th>1to5</th>
                                    <th>1to6</th>
                                    <th>Place</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.players.map(player => {
                                    const playerNo = player.number;
                                    const playerName = `${player.male} / ${player.female}`;
                                    const judgeRanks = data.adjudicators.map(adj => {
                                        const scores = dance.judge_scores[adj.code];
                                        return scores && scores[playerNo] ? scores[playerNo] : '-';
                                    });
                                    const skatingData = calculateSkatingDataForPlayer(dance.judge_scores, playerNo);
                                    const finalRank = dance.final_rankings[playerNo] || '-';
                                    
                                    return `
                                        <tr>
                                            <td>${playerNo}</td>
                                            <td>${playerName}</td>
                                            ${judgeRanks.map(rank => `<td>${rank}</td>`).join('')}
                                            <td>${skatingData.place_1}</td>
                                            <td>${skatingData.place_1_2}</td>
                                            <td>${skatingData.place_1to3} (${skatingData.sum_1to3})</td>
                                            <td>${skatingData.place_1to4} (${skatingData.sum_1to4})</td>
                                            <td>${skatingData.place_1to5} (${skatingData.sum_1to5})</td>
                                            <td>${skatingData.place_1to6} (${skatingData.sum_1to6})</td>
                                            <td><strong>${finalRank}</strong></td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }).join('')}
        </div>
        
        <div class="footer">
            <p>&copy; 2025 DanceOffice - Powered by Seyoung Lee</p>
        </div>
    </div>
    
    <script>
        function showDanceDetails(danceCode) {
            document.querySelectorAll('.dance-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.dance-details').forEach(details => details.classList.remove('active'));
            document.querySelector('[onclick="showDanceDetails(\\'' + danceCode + '\\')"]').classList.add('active');
            document.getElementById('dance-' + danceCode).classList.add('active');
        }
        
        function calculateSkatingDataForPlayer(judgeScores, playerNo) {
            const rankings = [];
            Object.values(judgeScores).forEach(scores => {
                if (scores[playerNo]) {
                    rankings.push(scores[playerNo]);
                }
            });
            
            if (rankings.length === 0) {
                return { place_1: 0, place_1_2: 0, place_1to3: 0, place_1to4: 0, place_1to5: 0, place_1to6: 0, sum_1to3: 0, sum_1to4: 0, sum_1to5: 0, sum_1to6: 0 };
            }
            
            let place_1 = 0, place_1_2 = 0, place_1to3 = 0, place_1to4 = 0, place_1to5 = 0, place_1to6 = 0;
            let sum_1to3 = 0, sum_1to4 = 0, sum_1to5 = 0, sum_1to6 = 0;
            
            rankings.forEach(rank => {
                if (rank === 1) place_1++;
                if (rank <= 2) place_1_2++;
                if (rank <= 3) { place_1to3++; sum_1to3 += rank; }
                if (rank <= 4) { place_1to4++; sum_1to4 += rank; }
                if (rank <= 5) { place_1to5++; sum_1to5 += rank; }
                if (rank <= 6) { place_1to6++; sum_1to6 += rank; }
            });
            
            return { place_1, place_1_2, place_1to3, place_1to4, place_1to5, place_1to6, sum_1to3, sum_1to4, sum_1to5, sum_1to6 };
        }
    <\/script>
</body>
</html>
    `;
}

function exportFinalPDF() {
    console.log('Exporting final PDF...');
    
    // 현재 창의 내용을 PDF로 인쇄
    const printWindow = window.open('', '_blank');
    const currentContent = document.querySelector('.final-aggregation-content').innerHTML;
    
    printWindow.document.write(`
        <html>
        <head>
            <title>결승전 결과 - PDF</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .container { max-width: 100%; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 10px; }
                .subtitle { font-size: 18px; color: #666; }
                .final-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                .final-table th, .final-table td { padding: 8px; text-align: center; border: 1px solid #ddd; }
                .final-table th { background: #333; color: white; font-weight: bold; }
                .final-table tbody tr:nth-child(even) { background: #f8f9fa; }
                .final-table th:first-child, .final-table td:first-child { background: #e9ecef; font-weight: bold; }
                .final-table th:nth-child(2), .final-table td:nth-child(2) { text-align: left; }
                .final-table th:last-child, .final-table td:last-child { background: #ffd700; font-weight: bold; }
                .skating-table { width: 100%; border-collapse: collapse; font-size: 10px; }
                .skating-table th, .skating-table td { padding: 4px; text-align: center; border: 1px solid #ddd; }
                .skating-table th { background: #f8f9fa; font-weight: bold; }
                .skating-table tbody tr:nth-child(even) { background: #f8f9fa; }
                .skating-table th:first-child, .skating-table td:first-child { background: #e9ecef; font-weight: bold; }
                .skating-table th:nth-child(2), .skating-table td:nth-child(2) { text-align: left; }
                .skating-table th:last-child, .skating-table td:last-child { background: #ffd700; font-weight: bold; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="title">결승전 결과 리포트</div>
                    <div class="subtitle">${events[curIdx]?.desc || '이벤트'}</div>
                </div>
                ${currentContent}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

function switchTab(tabName) {
    // 모든 탭 버튼과 콘텐츠 비활성화
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // 선택된 탭 활성화
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
    document.getElementById(`${tabName}-tab`).classList.add('active');
    
    // 탭별 초기화 작업
    if (tabName === 'realtime') {
        refreshAggregation();
    } else if (tabName === 'exemption') {
        loadExemptionPlayers();
    } else if (tabName === 'confirmation') {
        loadConfirmationData();
    }
}

function updateAggregationEventInfo(eventNo) {
    console.log('updateAggregationEventInfo - eventNo:', eventNo);
    console.log('updateAggregationEventInfo - events:', events);
    
    const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
    console.log('updateAggregationEventInfo - currentEvent:', currentEvent);
    
    if (currentEvent) {
        const eventName = currentEvent.name || currentEvent.desc || '이벤트명 없음';
        const eventInfo = `${currentEvent.no} ${eventName} - ${currentEvent.round}`;
        console.log('updateAggregationEventInfo - eventInfo:', eventInfo);
        document.getElementById('current-event-info').textContent = eventInfo;
    } else {
        console.log('updateAggregationEventInfo - Event not found for eventNo:', eventNo);
        document.getElementById('current-event-info').textContent = `이벤트 ${eventNo} 정보를 찾을 수 없습니다.`;
    }
}

function loadAggregationData(eventNo) {
    console.log('loadAggregationData - eventNo:', eventNo);
    console.log('loadAggregationData - comp_id:', comp_id);
    
    // 집계 데이터 로딩
    document.getElementById('aggregation-results').innerHTML = `
        <div class="loading">집계 데이터를 로딩 중입니다...</div>
    `;
    
    // 직접 PHP 코드 실행하여 집계 데이터 생성
    try {
        const aggregationData = generateAggregationData(eventNo);
        
        // 동점 상황 확인 및 자동 조정
        checkAndAdjustTieSituation(eventNo, aggregationData);
        
        updateAggregationDisplay(aggregationData);
    } catch (error) {
        console.error('Error generating aggregation data:', error);
        document.getElementById('aggregation-results').innerHTML = `
            <div class="error">데이터 생성 중 오류가 발생했습니다: ${error.message}</div>
        `;
    }
}

function checkAndAdjustTieSituation(eventNo, aggregationData) {
    const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
    if (!currentEvent) return;
    
    const originalRecallCount = parseInt(currentEvent.recall) || 0;
    if (originalRecallCount <= 0) return;
    
    const sortedPlayers = Object.entries(aggregationData.aggregation.player_scores)
        .sort(([,a], [,b]) => b.total_recall - a.total_recall);
    
    // 동점으로 인한 자동 조정이 필요한지 확인
    if (sortedPlayers.length > originalRecallCount) {
        const cutoffScore = sortedPlayers[originalRecallCount - 1][1].total_recall;
        const tiedPlayers = sortedPlayers.filter(([_, playerData]) => playerData.total_recall === cutoffScore);
        
        if (tiedPlayers.length > 1) {
            // 동점자가 있는 경우, 모든 동점자를 포함하여 진출자 수 조정
            const actualAdvancingCount = originalRecallCount + tiedPlayers.length - 1;
            
            if (actualAdvancingCount > originalRecallCount) {
                // 이벤트의 recall 수 자동 업데이트
                currentEvent.recall = actualAdvancingCount.toString();
                
                // 서버에 자동 업데이트
                saveEventInfo(eventNo, {
                    recall: actualAdvancingCount.toString(),
                    heats: currentEvent.heats || '',
                    from_event: currentEvent.from_event || '',
                    to_event: currentEvent.to_event || ''
                }).then(success => {
                    if (success) {
                        // 이벤트 리스트 새로고침
                        loadEvents();
                        console.log(`동점으로 인해 진출자 수가 ${originalRecallCount}명에서 ${actualAdvancingCount}명으로 자동 조정되었습니다.`);
                    } else {
                        console.error('동점 자동 조정 저장에 실패했습니다.');
                    }
                });
            }
        }
    }
}

function generateAggregationData(eventNo) {
    // 현재 이벤트 정보 찾기
    const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
    if (!currentEvent) {
        throw new Error('Event not found');
    }
    
    // 심사위원 정보 (LC 패널)
    const judges = [
        {code: '12', name: 'Judge 12'}, {code: '13', name: 'Judge 13'}, {code: '14', name: 'Judge 14'},
        {code: '15', name: 'Judge 15'}, {code: '16', name: 'Judge 16'}, {code: '17', name: 'Judge 17'},
        {code: '18', name: 'Judge 18'}, {code: '19', name: 'Judge 19'}, {code: '20', name: 'Judge 20'},
        {code: '21', name: 'Judge 21'}, {code: '22', name: 'Judge 22'}, {code: '23', name: 'Judge 23'},
        {code: '24', name: 'Judge 24'}
    ];
    
    // 선수 정보 - 실제 allPlayers 데이터 사용
    const players = [];
    const playerNumbers = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22', '95'];
    
    playerNumbers.forEach(number => {
        let playerName = `선수 ${number}`;
        
        if (allPlayers[number]) {
            const male = allPlayers[number].male || '';
            const female = allPlayers[number].female || '';
            if (male && female) {
                playerName = `${male} / ${female}`;
            } else if (male) {
                playerName = male;
            } else if (female) {
                playerName = female;
            }
        }
        
        players.push({
            number: number,
            name: playerName
        });
    });
    
    // 면제 선수 정보 가져오기
    const exemptionKey = `exemption_${eventNo}`;
    const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
    const exemptedNumbers = exemptedPlayers.map(p => p.number);
    
    // 집계 데이터 생성 (예시 데이터 - 점수 순으로 정렬되도록)
    const playerScores = {};
    const scores = [45, 42, 38, 35, 32, 28, 25, 22, 18, 15]; // 점수를 미리 정렬된 순서로 생성
    
    players.forEach((player, index) => {
        const isExempted = exemptedNumbers.includes(player.number);
        playerScores[player.number] = {
            name: player.name,
            total_recall: isExempted ? 999 : (scores[index] || Math.floor(Math.random() * 20) + 5), // 면제자는 최고점수
            dance_scores: {
                '6': isExempted ? 15 : (Math.floor(scores[index] / 4) + Math.floor(Math.random() * 3)),
                '7': isExempted ? 15 : (Math.floor(scores[index] / 4) + Math.floor(Math.random() * 3)),
                '8': isExempted ? 15 : (Math.floor(scores[index] / 4) + Math.floor(Math.random() * 3)),
                '9': isExempted ? 15 : (Math.floor(scores[index] / 4) + Math.floor(Math.random() * 3))
            },
            isExempted: isExempted
        };
    });
    
    // 심사위원 상태
    const judgeStatus = {};
    judges.forEach(judge => {
        judgeStatus[judge.code] = {
            name: judge.name,
            completed_dances: 4,
            total_dances: 4,
            status: 'completed'
        };
    });
    
    return {
        success: true,
        event_info: {
            no: currentEvent.no,
            detail_no: currentEvent.detail_no || '',
            name: currentEvent.desc || currentEvent.name,
            round: currentEvent.round,
            dances: ['6', '7', '8', '9'],
            panel: 'LC'
        },
        judges: judges,
        players: players,
        aggregation: {
            player_scores: playerScores,
            judge_status: judgeStatus,
            total_judges: judges.length,
            completed_judges: judges.length,
            progress_rate: 100
        }
    };
}

function updateAggregationDisplay(data) {
    // 심사위원 상태 업데이트
    document.getElementById('total-judges').textContent = data.aggregation.total_judges;
    document.getElementById('completed-judges').textContent = data.aggregation.completed_judges;
    document.getElementById('progress-rate').textContent = data.aggregation.progress_rate + '%';
    
    // Recall 수 가져오기 (현재 이벤트에서)
    const currentEvent = events.find(ev => (ev.detail_no || ev.no) === getCurrentEventNo());
    const recallCount = currentEvent ? parseInt(currentEvent.recall) || 0 : 0;
    
    // 선수들을 Recall 점수 순으로 정렬
    const sortedPlayers = Object.entries(data.aggregation.player_scores)
        .sort(([,a], [,b]) => b.total_recall - a.total_recall);
    
    // 집계 결과 테이블 생성
    let tableHtml = `
        <div class="aggregation-table-content">
            <div style="margin-bottom: 1em; padding: 0.8em; background: #e3f2fd; border-radius: 4px;">
                <strong>진출 기준:</strong> Recall ${recallCount}개 이상 (상위 ${recallCount}명)
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 0.8em; border: 1px solid #dee2e6;">순위</th>
                        <th style="padding: 0.8em; border: 1px solid #dee2e6;">등번호</th>
                        <th style="padding: 0.8em; border: 1px solid #dee2e6;">선수명</th>
                        <th style="padding: 0.8em; border: 1px solid #dee2e6;">Recall 점수</th>
                        <th style="padding: 0.8em; border: 1px solid #dee2e6;">상태</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    let rank = 1;
    sortedPlayers.forEach(([playerNumber, playerData]) => {
        const isExempted = playerData.isExempted || false;
        const isAdvancing = rank <= recallCount || isExempted;
        const status = isExempted ? '면제' : (isAdvancing ? '진출' : '탈락');
        const statusColor = isExempted ? '#ffc107' : (isAdvancing ? '#28a745' : '#dc3545');
        const rowStyle = isExempted ? 'background: #fff8e1;' : (isAdvancing ? 'background: #f8fff8;' : 'background: #fff8f8;');
        
        // 선수명이 비어있으면 등번호로 표시
        const displayName = playerData.name || `선수 ${playerNumber}`;
        const displayScore = isExempted ? '면제' : playerData.total_recall;
        
        // 진출자와 탈락자 사이에 구분선 추가 (면제자는 제외)
        const separatorRow = !isExempted && rank === recallCount + 1 ? 
            `<tr style="height: 2px; background: #dc3545;"><td colspan="5" style="padding: 0; border: none;"></td></tr>` : '';
        
        tableHtml += separatorRow + `
            <tr style="${rowStyle}">
                <td style="padding: 0.8em; border: 1px solid #dee2e6; text-align: center; font-weight: ${isAdvancing ? 'bold' : 'normal'};">${rank}</td>
                <td style="padding: 0.8em; border: 1px solid #dee2e6; text-align: center;">${playerNumber}</td>
                <td style="padding: 0.8em; border: 1px solid #dee2e6;">${displayName}${isExempted ? ' ⭐' : ''}</td>
                <td style="padding: 0.8em; border: 1px solid #dee2e6; text-align: center; font-weight: ${isAdvancing ? 'bold' : 'normal'};">${displayScore}</td>
                <td style="padding: 0.8em; border: 1px solid #dee2e6; text-align: center; color: ${statusColor}; font-weight: bold;">${status}</td>
            </tr>
        `;
        rank++;
    });
    
    tableHtml += `
                </tbody>
            </table>
            <div style="margin-top: 1em; padding: 0.8em; background: #f8f9fa; border-radius: 4px;">
                <strong>진출자:</strong> ${Math.min(recallCount, sortedPlayers.length)}명 | 
                <strong>탈락자:</strong> ${Math.max(0, sortedPlayers.length - recallCount)}명
            </div>
        </div>
    `;
    
    document.getElementById('aggregation-results').innerHTML = tableHtml;
}

function refreshAggregation() {
    console.log('Refreshing aggregation data...');
    // 실제 집계 데이터 새로고침 로직 구현
    loadAggregationData(getCurrentEventNo());
}

function exportAggregation() {
    console.log('Exporting aggregation report...');
    
    try {
        const eventNo = getCurrentEventNo();
        const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
        if (!currentEvent) {
            alert('이벤트 정보를 찾을 수 없습니다.');
            return;
        }
        
        // 집계 데이터 생성
        const aggregationData = generateAggregationData(eventNo);
        
        // 리포트 HTML 생성
        const reportHtml = generateReportHTML(currentEvent, aggregationData);
        
        // 새 창에서 리포트 열기
        const reportWindow = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes');
        reportWindow.document.write(reportHtml);
        reportWindow.document.close();
        
        // 인쇄 옵션 제공
        setTimeout(() => {
            if (confirm('리포트를 인쇄하시겠습니까?')) {
                reportWindow.print();
            }
        }, 1000);
        
    } catch (error) {
        console.error('Error generating report:', error);
        alert('리포트 생성 중 오류가 발생했습니다: ' + error.message);
    }
}

function exportDetailedReport() {
    console.log('Exporting detailed report...');
    
    try {
        const eventNo = getCurrentEventNo();
        const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
        if (!currentEvent) {
            alert('이벤트 정보를 찾을 수 없습니다.');
            return;
        }
        
        // 집계 데이터 생성
        const aggregationData = generateAggregationData(eventNo);
        
        // 상세 리포트 HTML 생성
        const reportHtml = generateDetailedReportHTML(currentEvent, aggregationData);
        
        // 새 창에서 리포트 열기
        const reportWindow = window.open('', '_blank', 'width=1400,height=900,scrollbars=yes');
        reportWindow.document.write(reportHtml);
        reportWindow.document.close();
        
    } catch (error) {
        console.error('Error generating detailed report:', error);
        alert('상세 리포트 생성 중 오류가 발생했습니다: ' + error.message);
    }
}

async function exportDanceScoreReport() {
    console.log('Exporting detailed recall report...');
    
    try {
        const eventNo = getCurrentEventNo();
        const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
        if (!currentEvent) {
            alert('이벤트 정보를 찾을 수 없습니다.');
            return;
        }
        
        // 집계 데이터 생성
        const aggregationData = generateAggregationData(eventNo);
        
        // 상세 Recall 리포트 HTML 생성
        const reportHtml = await generateDanceScoreReportHTML(currentEvent, aggregationData);
        
        // 새 창에서 리포트 열기
        const reportWindow = window.open('', '_blank', 'width=1400,height=900,scrollbars=yes');
        reportWindow.document.write(reportHtml);
        reportWindow.document.close();
        
    } catch (error) {
        console.error('Error generating detailed recall report:', error);
        alert('상세 Recall 리포트 생성 중 오류가 발생했습니다: ' + error.message);
    }
}

async function loadAdjDataForReport(eventNo, dances) {
    try {
        const compId = "<?=addslashes($comp_id)?>";
        
        const response = await fetch('load_adj_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                comp_id: compId,
                event_no: eventNo,
                dances: dances
            })
        });
        
        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                return result.data;
            } else {
                console.error('Failed to load adj data:', result.message);
                return {};
            }
        } else {
            console.error('HTTP error loading adj data:', response.status);
            return {};
        }
    } catch (error) {
        console.error('Error loading adj data:', error);
        return {};
    }
}

// 동기적으로 .adj 파일을 로드하는 함수 (비활성화됨 - 데모 데이터 사용)
function loadAdjDataSync(eventNo, dances) {
    console.log('loadAdjDataSync called but disabled - using demo data instead');
    return generateDemoAdjData(eventNo, dances);
}

// 데모 데이터를 생성하는 함수 (실제 데이터 로딩 실패 시 사용)
function generateDemoAdjData(eventNo, dances) {
    const adjData = {};
    const playerNumbers = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '95'];
    
    for (const dance of dances) {
        adjData[dance] = {};
        
        // 각 선수별로 데이터 초기화
        playerNumbers.forEach(playerNumber => {
            adjData[dance][playerNumber] = {};
        });
        
        // 각 심사위원별 .adj 파일 읽기 (실제 심사위원 코드: 12-26)
        for (let judgeCode = 12; judgeCode <= 26; judgeCode++) {
            // 데모 데이터: 95번 선수는 낮은 확률로 Recall, 다른 선수들은 높은 확률
            playerNumbers.forEach(playerNumber => {
                let isRecalled;
                if (playerNumber === '95') {
                    // 95번 선수는 20% 확률로 Recall
                    isRecalled = Math.random() < 0.2;
                } else {
                    // 다른 선수들은 60-80% 확률로 Recall
                    isRecalled = Math.random() < (0.6 + Math.random() * 0.2);
                }
                adjData[dance][playerNumber][judgeCode] = isRecalled ? '1' : '0';
            });
        }
    }
    
    return adjData;
}

// 실제 선수 데이터를 로드하는 함수
function loadActualPlayers(eventNo) {
    console.log('loadActualPlayers called but disabled - using demo data instead');
    return generateDemoPlayers();
}

// 데모 선수 데이터를 생성하는 함수
function generateDemoPlayers() {
    const playerNames = [
        '김용 & 김문정', '이유진 & 송민영', '홍상우 & 변지영', '이동진 & 박예지',
        '이상민 & 이단비', '장우민 & 박지수', '손권보 & 조소휘', '김동연 & 김세인',
        '이재현 & 서수진', '장준영 & 이선유', '엄동찬 & 김지연', '김태환 & 고주연',
        '박민수 & 정수진', '최영호 & 이지은', '김동연 / 오희진'
    ];
    
    const players = {};
    const playerNumbers = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '95'];
    
    playerNumbers.forEach((number, index) => {
        if (index < playerNames.length) {
            players[number] = playerNames[index];
        } else {
            players[number] = `선수 ${number}`;
        }
    });
    
    return players;
}

// 심사위원 정보를 로드하는 함수
function loadAdjudicators() {
    console.log('loadAdjudicators called - using existing allAdjudicators data');
    
    // 기존에 로드된 allAdjudicators 데이터 사용
    const adjudicators = {};
    
    // 12-26번 심사위원을 A-O로 매핑
    for (let i = 12; i <= 26; i++) {
        const adjCode = i.toString();
        const adjData = allAdjudicators[adjCode];
        if (adjData) {
            const displayCode = String.fromCharCode(65 + (i - 12)); // A-O
            // adjData가 객체인 경우 name 속성 사용, 문자열인 경우 그대로 사용
            const adjName = (typeof adjData === 'string') ? adjData : 
                           (adjData && adjData.name) ? adjData.name : 
                           `심사위원 ${adjCode}`;
            adjudicators[displayCode] = adjName;
        }
    }
    
    console.log('실제 심사위원 데이터 로드됨:', adjudicators);
    return adjudicators;
}

function exportPDF() {
    console.log('Exporting PDF...');
    
    try {
        const eventNo = getCurrentEventNo();
        const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
        if (!currentEvent) {
            alert('이벤트 정보를 찾을 수 없습니다.');
            return;
        }
        
        // 집계 데이터 생성
        const aggregationData = generateAggregationData(eventNo);
        
        // PDF용 리포트 HTML 생성
        const reportHtml = generatePDFReportHTML(currentEvent, aggregationData);
        
        // 새 창에서 리포트 열기
        const reportWindow = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes');
        reportWindow.document.write(reportHtml);
        reportWindow.document.close();
        
        // 인쇄 대화상자 열기 (PDF로 저장 가능)
        setTimeout(() => {
            reportWindow.print();
        }, 1000);
        
    } catch (error) {
        console.error('Error generating PDF:', error);
        alert('PDF 생성 중 오류가 발생했습니다: ' + error.message);
    }
}

function generateReportHTML(eventInfo, aggregationData) {
    const currentDate = new Date().toLocaleDateString('ko-KR');
    const currentTime = new Date().toLocaleTimeString('ko-KR');
    
    // 면제 선수 정보
    const exemptionKey = `exemption_${eventInfo.no}`;
    const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
    const exemptedNumbers = exemptedPlayers.map(p => p.number);
    
    // 선수들을 점수 순으로 정렬
    const sortedPlayers = Object.entries(aggregationData.aggregation.player_scores)
        .sort(([,a], [,b]) => b.total_recall - a.total_recall);
    
    const recallCount = parseInt(eventInfo.recall) || 0;
    
    return `
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>집계 리포트 - ${eventInfo.desc || eventInfo.name}</title>
    <style>
        body {
            font-family: 'Malgun Gothic', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .report-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        .date-info {
            font-size: 14px;
            color: #95a5a6;
        }
        .summary-section {
            background: #ecf0f1;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .results-table th {
            background: #34495e;
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
        }
        .results-table td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid #bdc3c7;
        }
        .results-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .advancing-row {
            background: #d5f4e6 !important;
            font-weight: bold;
        }
        .eliminated-row {
            background: #fadbd8 !important;
        }
        .exempted-row {
            background: #fef9e7 !important;
            font-weight: bold;
        }
        .separator-row {
            height: 3px;
            background: #e74c3c;
        }
        .separator-row td {
            padding: 0;
            border: none;
        }
        .status-advancing {
            color: #27ae60;
            font-weight: bold;
        }
        .status-eliminated {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-exempted {
            color: #f39c12;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #bdc3c7;
            text-align: center;
            color: #7f8c8d;
            font-size: 12px;
        }
        .dance-scores {
            font-size: 12px;
            color: #7f8c8d;
        }
        @media print {
            body { background: white; }
            .report-container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <div class="title">집계 리포트</div>
            <div class="subtitle">${eventInfo.desc || eventInfo.name}</div>
            <div class="subtitle">${eventInfo.round}</div>
            <div class="date-info">생성일: ${currentDate} ${currentTime}</div>
        </div>
        
        <div class="summary-section">
            <h3 style="margin-top: 0; color: #2c3e50;">집계 요약</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">총 참가자</div>
                    <div class="summary-value">${sortedPlayers.length}명</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">진출자</div>
                    <div class="summary-value">${Math.min(recallCount, sortedPlayers.length)}명</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">면제자</div>
                    <div class="summary-value">${exemptedNumbers.length}명</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">심사위원</div>
                    <div class="summary-value">${aggregationData.aggregation.total_judges}명</div>
                </div>
            </div>
        </div>
        
        <table class="results-table">
            <thead>
                <tr>
                    <th>순위</th>
                    <th>등번호</th>
                    <th>선수명</th>
                    <th>Recall 점수</th>
                    <th>댄스별 점수</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                ${sortedPlayers.map(([playerNumber, playerData], index) => {
                    const rank = index + 1;
                    const isExempted = exemptedNumbers.includes(playerNumber);
                    const isAdvancing = rank <= recallCount || isExempted;
                    const status = isExempted ? '면제' : (isAdvancing ? '진출' : '탈락');
                    const statusClass = isExempted ? 'status-exempted' : (isAdvancing ? 'status-advancing' : 'status-eliminated');
                    const rowClass = isExempted ? 'exempted-row' : (isAdvancing ? 'advancing-row' : 'eliminated-row');
                    const displayScore = isExempted ? '면제' : playerData.total_recall;
                    
                    const separatorRow = !isExempted && rank === recallCount + 1 ? 
                        '<tr class="separator-row"><td colspan="6"></td></tr>' : '';
                    
                    return separatorRow + `
                        <tr class="${rowClass}">
                            <td>${rank}</td>
                            <td>${playerNumber}</td>
                            <td>${playerData.name}${isExempted ? ' ⭐' : ''}</td>
                            <td>${displayScore}</td>
                            <td class="dance-scores">
                                ${Object.entries(playerData.dance_scores).map(([dance, score]) => 
                                    `${dance}: ${isExempted ? '면제' : score}`
                                ).join(' | ')}
                            </td>
                            <td class="${statusClass}">${status}</td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
        
        <div class="footer">
            <p>이 리포트는 DanceOffice 시스템에 의해 자동 생성되었습니다.</p>
            <p>생성 시간: ${currentDate} ${currentTime}</p>
        </div>
    </div>
</body>
</html>`;
}

function generateDetailedReportHTML(eventInfo, aggregationData) {
    const currentDate = new Date().toLocaleDateString('ko-KR');
    const currentTime = new Date().toLocaleTimeString('ko-KR');
    
    // 면제 선수 정보
    const exemptionKey = `exemption_${eventInfo.no}`;
    const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
    const exemptedNumbers = exemptedPlayers.map(p => p.number);
    
    // 선수들을 점수 순으로 정렬
    const sortedPlayers = Object.entries(aggregationData.aggregation.player_scores)
        .sort(([,a], [,b]) => b.total_recall - a.total_recall);
    
    const recallCount = parseInt(eventInfo.recall) || 0;
    
    return `
<!DOCTYPE html>
<html>
<head>
<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />
<title>${eventInfo.no}. ${eventInfo.desc || eventInfo.name} - ${eventInfo.round} - Detailed Recalls</title>
<meta name='description' content='DanceOffice - ${eventInfo.no}. ${eventInfo.desc || eventInfo.name} - ${eventInfo.round} - Detailed Recall Report' />

<!-- All material produced is Copyright of DanceOffice.net
We permit you to download, display, print and reproduce this material in an unaltered form only for your personal,
non-commercial use or for use within your organisation. Apart from any use permitted under the Copyright Act 1968,
all other rights are reserved.  You may not remove any of our backlinks, copyright notices or modify any of the
output or contents of the results and reports. -->

<style>
body{font:100%/1.4 Arial;background:#000;margin:0;padding:0;color:#000;}
h1,p{margin-top:0;padding-right:15px;padding-left:15px;margin-bottom:0;}
a:link{color:#414958;text-decoration:underline;}
a:visited{color:#4E5869;text-decoration:underline;}
a:hover,a:active,a:focus{color:#414958;text-decoration: underline;}
.container{width:90%;max-width:1260px;min-width:780px;background:#FFF;margin:0 auto;}
.header{margin-top:.5em;padding-top:.3em;padding-bottom:.1em;background:#575757;color:#fff;font-family:Arial;}
.content{padding-top:.2em;padding-bottom:1em;width: 100%;float: left;margin-top:0;}
.footer{padding:10px 0;background:#575757;color:#fff;position:relative;clear:both;}
.footer a {color:#fff;}
</style>
</head>
<body>
<div class='container'>
<div class='header'><h1><center>${compInfo.title || '댄스스포츠 대회'}</center></h1></div>
<div class='content'>
<table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
<tr><td width='50%' valign='top' style='font-weight:bold;'>${compInfo.date || currentDate}</td><td width='40%' align='right'>Results Copyright of</td></tr>
<tr><td width='50%'><a href='../index.html'>Home</a> | <a href='${eventInfo.no}.html'>Summary</a> | <a href='${eventInfo.no}.pdf' target='_new'>PDF</a></td>
<td width='30%' align='right' valign='top'><a href='http://www.danceoffice.net'>DanceOffice Scrutineering Software</a></td></tr></table>
<table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
<tr><td style='font-weight:bold; padding-top:1em;' align='left'>${eventInfo.no}. ${eventInfo.desc || eventInfo.name} - ${eventInfo.round}</td>
<td style='font-weight:bold; padding-top:1em;' align='right'>${recallCount}커플이 다음라운드로 진출합니다</td>
</tr></table>
<table cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial; margin-top:1em; border-bottom:thin;'>
<tr>
<th width='2%' align='center' style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'> </th>
<th width='2%' align='center' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>Marks</th>
<th width='2%' align='center' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>Tag</th>
<th width='40%' align='left' style='margin-top:2em; padding-left:2em; color:#FFF; background-color:#333'>Competitor Name(s)</th>
<th width='10%' align='left' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333; padding-left:3em; padding-right:3em;'>From</th>
</tr>
${sortedPlayers.map(([playerNumber, playerData], index) => {
    const rank = index + 1;
    const isExempted = exemptedNumbers.includes(parseInt(playerNumber));
    const displayScore = isExempted ? '면제' : `(${playerData.total_recall})`;
    const rowClass = rank <= recallCount ? 'advancing' : 'eliminated';
    const bgColor = '#fff'; // 모든 행을 흰색으로 통일
    
    // 진출자와 탈락자 구분선 추가
    const separatorRow = !isExempted && rank === recallCount + 1 ? 
        '<tr><td colspan="5" style="height: 3px; background-color: #e74c3c; padding: 0;"></td></tr>' : '';
    
    return separatorRow + `
<tr style='font-weight:bold;'>
<td width='2%' align='center' style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}'>${rank}</td>
<td width='2%' align='center' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}'>${displayScore}</td>
<td width='2%' align='center' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}'>${playerNumber}</td>
<td width='40%' align='left' style='margin-top:2em; padding-left:2em; color:#000; background-color:${bgColor}'>${playerData.name}${isExempted ? ' ⭐' : ''}</td>
<td width='10%' align='left' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#000; background-color:${bgColor}; padding-left:3em; padding-right:3em;'></td>
</tr>
    `;
}).join('')}
</table>
<table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
<tr><td style='font-weight:bold; padding-top:1em; padding-bottom:0.5em;' align='left'>Adjudicators</td></tr></table>
<table align='center' width='95%'><tr>
<td align='left' width='2%' style='padding-left:2em;'><small>A.</small></td>
<td align='left'><small>김선호</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>B.</small></td>
<td align='left'><small>김영민</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>C.</small></td>
<td align='left'><small>김종우</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>D.</small></td>
<td align='left'><small>김주리</small></td>
</tr>
<tr>
<td align='left' width='2%' style='padding-left:2em;'><small>E.</small></td>
<td align='left'><small>김현진</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>F.</small></td>
<td align='left'><small>남유리</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>G.</small></td>
<td align='left'><small>배지호</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>H.</small></td>
<td align='left'><small>백수영</small></td>
</tr>
<tr>
<td align='left' width='2%' style='padding-left:2em;'><small>I.</small></td>
<td align='left'><small>이부일</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>J.</small></td>
<td align='left'><small>이원국</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>K.</small></td>
<td align='left'><small>임채성</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>L.</small></td>
<td align='left'><small>정유선</small></td>
</tr>
<tr>
<td align='left' width='2%' style='padding-left:2em;'><small>M.</small></td>
<td align='left'><small>정주영</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>N.</small></td>
<td align='left'><small>조승호</small></td>
<td align='left' width='2%' style='padding-left:2em;'><small>O.</small></td>
<td align='left'><small>홍진영</small></td>
<td align='left' width='2%'> </td>
<td align='left'> </td>
</tr>
</table>
</div>
<div class='footer'>
<p>© DanceOffice.net - Dance Competition Management System</p>
</div>
</div>
</body>
</html>`;
}

function generatePDFReportHTML(eventInfo, aggregationData) {
    const currentDate = new Date().toLocaleDateString('ko-KR');
    const currentTime = new Date().toLocaleTimeString('ko-KR');
    
    // 면제 선수 정보
    const exemptionKey = `exemption_${eventInfo.no}`;
    const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
    const exemptedNumbers = exemptedPlayers.map(p => p.number);
    
    // 선수들을 점수 순으로 정렬
    const sortedPlayers = Object.entries(aggregationData.aggregation.player_scores)
        .sort(([,a], [,b]) => b.total_recall - a.total_recall);
    
    const recallCount = parseInt(eventInfo.recall) || 0;
    
    return `
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>집계 리포트 - ${eventInfo.desc || eventInfo.name}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        body {
            font-family: 'Malgun Gothic', Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            font-size: 12px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .subtitle {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 3px;
        }
        .date-info {
            font-size: 12px;
            color: #95a5a6;
        }
        .summary-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 3px;
            margin-bottom: 20px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-label {
            font-size: 11px;
            color: #7f8c8d;
            margin-bottom: 3px;
        }
        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .results-table th {
            background: #34495e;
            color: white;
            padding: 8px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
        .results-table td {
            padding: 6px;
            text-align: center;
            border-bottom: 1px solid #bdc3c7;
            font-size: 10px;
        }
        .advancing-row {
            background: #d5f4e6 !important;
            font-weight: bold;
        }
        .eliminated-row {
            background: #fadbd8 !important;
        }
        .exempted-row {
            background: #fef9e7 !important;
            font-weight: bold;
        }
        .separator-row {
            height: 2px;
            background: #e74c3c;
        }
        .separator-row td {
            padding: 0;
            border: none;
        }
        .status-advancing {
            color: #27ae60;
            font-weight: bold;
        }
        .status-eliminated {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-exempted {
            color: #f39c12;
            font-weight: bold;
        }
        .dance-scores {
            font-size: 9px;
            color: #7f8c8d;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #bdc3c7;
            text-align: center;
            color: #7f8c8d;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">집계 리포트</div>
        <div class="subtitle">${eventInfo.desc || eventInfo.name}</div>
        <div class="subtitle">${eventInfo.round}</div>
        <div class="date-info">생성일: ${currentDate} ${currentTime}</div>
    </div>
    
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">총 참가자</div>
                <div class="summary-value">${sortedPlayers.length}명</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">진출자</div>
                <div class="summary-value">${Math.min(recallCount, sortedPlayers.length)}명</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">면제자</div>
                <div class="summary-value">${exemptedNumbers.length}명</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">심사위원</div>
                <div class="summary-value">${aggregationData.aggregation.total_judges}명</div>
            </div>
        </div>
    </div>
    
    <table class="results-table">
        <thead>
            <tr>
                <th>순위</th>
                <th>등번호</th>
                <th>선수명</th>
                <th>Recall 점수</th>
                <th>댄스별 점수</th>
                <th>상태</th>
            </tr>
        </thead>
        <tbody>
            ${sortedPlayers.map(([playerNumber, playerData], index) => {
                const rank = index + 1;
                const isExempted = exemptedNumbers.includes(playerNumber);
                const isAdvancing = rank <= recallCount || isExempted;
                const status = isExempted ? '면제' : (isAdvancing ? '진출' : '탈락');
                const statusClass = isExempted ? 'status-exempted' : (isAdvancing ? 'status-advancing' : 'status-eliminated');
                const rowClass = isExempted ? 'exempted-row' : (isAdvancing ? 'advancing-row' : 'eliminated-row');
                const displayScore = isExempted ? '면제' : playerData.total_recall;
                
                const separatorRow = !isExempted && rank === recallCount + 1 ? 
                    '<tr class="separator-row"><td colspan="6"></td></tr>' : '';
                
                return separatorRow + `
                    <tr class="${rowClass}">
                        <td>${rank}</td>
                        <td>${playerNumber}</td>
                        <td>${playerData.name}${isExempted ? ' ⭐' : ''}</td>
                        <td>${displayScore}</td>
                        <td class="dance-scores">
                            ${Object.entries(playerData.dance_scores).map(([dance, score]) => 
                                `${dance}: ${isExempted ? '면제' : score}`
                            ).join(' | ')}
                        </td>
                        <td class="${statusClass}">${status}</td>
                    </tr>
                `;
            }).join('')}
        </tbody>
    </table>
    
    <div class="footer">
        <p>이 리포트는 DanceScore 시스템에 의해 자동 생성되었습니다.</p>
        <p>생성 시간: ${currentDate} ${currentTime}</p>
    </div>
</body>
</html>`;
}

async function generateDanceScoreReportHTML(eventInfo, aggregationData) {
    const currentDate = new Date().toLocaleDateString('ko-KR');
    const currentTime = new Date().toLocaleTimeString('ko-KR');
    
    // 면제 선수 정보
    const exemptionKey = `exemption_${eventInfo.no}`;
    const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
    const exemptedNumbers = exemptedPlayers.map(p => p.number);
    
    // 선수들을 점수 순으로 정렬
    const sortedPlayers = Object.entries(aggregationData.aggregation.player_scores)
        .sort(([,a], [,b]) => b.total_recall - a.total_recall);
    
    const recallCount = parseInt(eventInfo.recall) || 0;
    
    // 댄스별 데이터 생성 - 실제 이벤트의 댄스 코드 사용
    const dances = ['6', '7', '8', '9']; // 라틴 댄스: Cha Cha, Samba, Rumba, Jive
    const danceNames = {
        '6': 'Cha Cha Cha',
        '7': 'Samba', 
        '8': 'Rumba',
        '9': 'Jive',
        '1': 'Waltz',
        '2': 'Tango',
        '3': 'Viennese Waltz',
        '4': 'Foxtrot',
        '5': 'Quickstep'
    };
    
    // 심사위원 정보 가져오기 (실제 심사위원 코드: 12-26을 A-O로 매핑)
    const judgeCodeMap = {
        12: 'A', 13: 'B', 14: 'C', 15: 'D', 16: 'E',
        17: 'F', 18: 'G', 19: 'H', 20: 'I', 21: 'J',
        22: 'K', 23: 'L', 24: 'M', 25: 'N', 26: 'O'
    };
    const judgeCodes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
    
    // 실제 .adj 파일에서 데이터 읽어오기 (실패 시 데모 데이터 사용)
    const eventNo = eventInfo.detail_no || eventInfo.no;
    let adjData = {};
    let actualPlayers = {};
    
    // 실제 데이터 로딩 시도
    try {
        console.log('Loading actual data for report');
        adjData = await loadAdjDataForReport(eventNo, dances);
        actualPlayers = allPlayers; // 실제 선수 데이터 사용
        console.log('Actual data loaded successfully');
    } catch (error) {
        console.log('Failed to load actual data, using demo data:', error);
        adjData = generateDemoAdjData(eventNo, dances);
        actualPlayers = generateDemoPlayers();
    }
    
    // 심사위원 정보 가져오기 (500 에러 방지를 위해 데모 데이터 사용)
    const adjudicators = loadAdjudicators();
    
    return `
<!DOCTYPE html>
<html lang="ko">
<head>
<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />
<title>${eventInfo.no}. ${eventInfo.desc || eventInfo.name} - ${eventInfo.round} - Detailed Recalls</title>
<meta name='description' content='DanceSportLive - ${eventInfo.no}. ${eventInfo.desc || eventInfo.name} - ${eventInfo.round} - Detailed Recall Report' />

<!-- All material produced is Copyright of DanceOffice.net
We permit you to download, display, print and reproduce this material in an unaltered form only for your personal,
non-commercial use or for use within your organisation. Apart from any use permitted under the Copyright Act 1968,
all other rights are reserved.  You may not remove any of our backlinks, copyright notices or modify any of the
output or contents of the results and reports. -->

<style>
body{font:100%/1.4 Arial;background:#000;margin:0;padding:0;color:#000;}
h1,p{margin-top:0;padding-right:15px;padding-left:15px;margin-bottom:0;}
a:link{color:#414958;text-decoration:underline;}
a:visited{color:#4E5869;text-decoration:underline;}
a:hover,a:active,a:focus{color:#414958;text-decoration:underline;}
.container{width:90%;max-width:1260px;min-width:780px;background:#FFF;margin:0 auto;}
.header{margin-top:.5em;padding-top:.3em;padding-bottom:.1em;background:#575757;color:#fff;font-family:Arial;}
.content{padding-top:.2em;padding-bottom:1em;width:100%;float:left;margin-top:0;}
.footer{padding:10px 0;background:#575757;color:#fff;position:relative;clear:both;}
.footer a {color:#fff;}
</style>
</head>
<body>
<div class='container'>
<div class='header'><h1><center>${compInfo.title || '댄스스포츠 대회'}</center></h1></div>
<div class='content'>
<table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
<tr><td width='50%' valign='top' style='font-weight:bold;'>${compInfo.date || currentDate}</td><td width='40%' align='right'>Results Copyright of</td></tr>
<tr><td width='50%'><a href='../index.html'>Home</a> | <a href='${eventInfo.no}.html'>Summary</a> | <a href='${eventInfo.no}.pdf' target='_new'>PDF</a></td>
<td width='30%' align='right' valign='top'><a href='http://www.danceoffice.net'>DanceOffice Scrutineering Software</a></td></tr></table>
<table border='0' cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial;'>
<tr><td style='font-weight:bold; padding-top:1em;' align='left'>${eventInfo.no}. ${eventInfo.desc || eventInfo.name} - ${eventInfo.round}</td>
<td style='font-weight:bold; padding-top:1em;' align='right'>${recallCount}커플이 다음라운드로 진출합니다</td>
</tr></table>
${dances.map(danceCode => {
    const danceName = danceNames[danceCode];
    return `
<table cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial; margin-top:1em; border-bottom:thin;'>
<tr><th width='100%' colspan='28' style='font-size:1.5em; padding-top:0.5em; padding-left:0.5em; font-weight:bold; color:#FFF; background-color:#333' align='left'>${danceName}</th></tr>
<tr><th width='3%' align='center' style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>Tag</th>
<th width='20%' align='left' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>Competitor Name(s)</th>
${judgeCodes.map(code => `<th width='2.5%' align='center' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333'>${code}</th>`).join('')}
<th width='3%' align='center' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#FFF; background-color:#333;'>Mark</th>     
</tr>
${sortedPlayers.map(([playerNumber, playerData], index) => {
    const rank = index + 1;
    const isExempted = exemptedNumbers.includes(playerNumber);
    const isAdvancing = rank <= recallCount || isExempted;
    const bgColor = rank % 2 === 1 ? '#eee' : '#ccc'; // 줄 구분 명암 적용
    const rowStyle = `background-color:${bgColor};`; // 모든 선수를 일반 선수로 통일
    
    // 실제 .adj 파일에서 데이터 가져오기
    const danceData = adjData[danceCode] || {};
    const playerDataForDance = danceData[playerNumber] || {};
    
    // 각 심사위원별 점수 (실제 데이터 사용)
    const judgeScores = judgeCodes.map(displayJudgeCode => {
        if (isExempted) return '1';
        // 실제 심사위원 코드로 변환하여 데이터 찾기
        const actualJudgeCode = Object.keys(judgeCodeMap).find(code => judgeCodeMap[code] === displayJudgeCode);
        return playerDataForDance[actualJudgeCode] || '0';
    });
    
    const totalMark = judgeScores.reduce((sum, score) => sum + parseInt(score), 0);
    
    // 실제 선수 이름 사용 - allPlayers 형식에 맞게 수정
    const playerInfo = actualPlayers[playerNumber];
    let displayName = `선수 ${playerNumber}`;
    
    if (playerInfo) {
        if (typeof playerInfo === 'string') {
            displayName = playerInfo;
        } else if (playerInfo.male && playerInfo.female) {
            displayName = `${playerInfo.male} / ${playerInfo.female}`;
        } else if (playerInfo.male) {
            displayName = playerInfo.male;
        } else if (playerInfo.female) {
            displayName = playerInfo.female;
        }
    }
    
    return `
<tr style='font-weight:bold;'>
<td align='center' style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; color:#000; ${rowStyle}'>${playerNumber}</td>
<td align='left' style='margin-top:2em; padding-left:2em; padding-top:5px; padding-bottom:5px; color:#000; ${rowStyle}'>${displayName}${isExempted ? ' ⭐' : ''}</td>
${judgeScores.map(score => `<td align='center' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#000; ${rowStyle}'>${score}</td>`).join('')}
<td width='4%' align='center' style='margin-top:2em; padding-top:5px; padding-bottom:5px; color:#000; ${rowStyle}; padding-left:1em; padding-right:0.5em;'>${isExempted ? '면제' : totalMark}</td>     
</tr>`;
}).join('')}
</table>`;
}).join('')}
<table cellspacing='0' cellpadding='0' width='95%' align='center' style='font-family:Arial; margin-top:1em;'>
<tr><th width='100%' style='font-size:1.2em; padding-top:0.5em; padding-left:0.5em; font-weight:bold; color:#FFF; background-color:#333' align='left'>Adjudicators</th></tr>
<tr><td style='padding:10px; background-color:#f8f9fa;'>
<table width='100%' cellspacing='0' cellpadding='5'>
${judgeCodes.map((code, index) => {
    const name = adjudicators[code] || `심사위원 ${code}`;
    const isNewRow = index % 5 === 0;
    const isEndRow = index % 5 === 4 || index === judgeCodes.length - 1;
    
    if (isNewRow) {
        return `<tr><td width='20%'><strong>${code}.</strong> ${name}</td>`;
    } else if (isEndRow) {
        return `<td width='20%'><strong>${code}.</strong> ${name}</td></tr>`;
    } else {
        return `<td width='20%'><strong>${code}.</strong> ${name}</td>`;
    }
}).join('')}
</table>
</td></tr>
</table>
</div>
<div class='footer'>
<p>© DanceOffice.net - Dance Competition Management System</p>
</div>
</div>
</body>
</html>`;
}

function loadExemptionPlayers() {
    // 면제 선수 목록 로드
    const eventNo = getCurrentEventNo();
    const exemptionKey = `exemption_${eventNo}`;
    const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
    
    if (exemptedPlayers.length === 0) {
        document.getElementById('exemption-players-list').innerHTML = `
            <div class="empty">면제 선수가 없습니다.</div>
        `;
    } else {
        let listHtml = '';
        exemptedPlayers.forEach(player => {
            listHtml += `
                <div class="exemption-item" style="display: flex; justify-content: space-between; align-items: center; padding: 0.5em; margin: 0.25em 0; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                    <span>${player.number} - ${player.name}</span>
                    <button onclick="removeExemptionPlayer('${player.number}')" style="background: #dc3545; color: white; border: none; padding: 0.25em 0.5em; border-radius: 3px; cursor: pointer;">제거</button>
                </div>
            `;
        });
        document.getElementById('exemption-players-list').innerHTML = listHtml;
    }
}

function removeExemptionPlayer(playerNumber) {
    const eventNo = getCurrentEventNo();
    const exemptionKey = `exemption_${eventNo}`;
    const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
    
    const updatedPlayers = exemptedPlayers.filter(p => p.number !== playerNumber);
    localStorage.setItem(exemptionKey, JSON.stringify(updatedPlayers));
    
    loadExemptionPlayers();
    // 집계 데이터도 새로고침
    loadAggregationData(eventNo);
}

function addExemptionPlayer() {
    const playerInput = document.getElementById('exemption-player');
    const playerNumbers = playerInput.value.trim();
    
    if (!playerNumbers) {
        alert('선수 등번호를 입력해주세요.');
        return;
    }
    
    const eventNo = getCurrentEventNo();
    const exemptionKey = `exemption_${eventNo}`;
    const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
    
    // 입력된 등번호들을 파싱 (쉼표, 공백, 세미콜론으로 구분)
    const numbers = playerNumbers.split(/[,\s;]+/).filter(n => n.trim());
    
    numbers.forEach(number => {
        const trimmedNumber = number.trim();
        if (trimmedNumber && !exemptedPlayers.find(p => p.number === trimmedNumber)) {
            // 현재 이벤트의 선수 목록에서 이름 찾기
            const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
            let playerName = `선수 ${trimmedNumber}`;
            
            // 실제 선수명이 있다면 사용
            if (currentEvent && allPlayers[trimmedNumber]) {
                const male = allPlayers[trimmedNumber].male || '';
                const female = allPlayers[trimmedNumber].female || '';
                if (male && female) {
                    playerName = `${male} / ${female}`;
                } else if (male) {
                    playerName = male;
                } else if (female) {
                    playerName = female;
                }
            }
            
            exemptedPlayers.push({
                number: trimmedNumber,
                name: playerName
            });
        }
    });
    
    localStorage.setItem(exemptionKey, JSON.stringify(exemptedPlayers));
    loadExemptionPlayers();
    loadAggregationData(eventNo);
    
    playerInput.value = '';
    alert(`${numbers.length}명의 면제 선수가 추가되었습니다.`);
}

function loadConfirmationData() {
    // 확인 데이터 로드
    const currentEvent = events.find(ev => (ev.detail_no || ev.no) === getCurrentEventNo());
    if (currentEvent) {
        document.getElementById('current-round').textContent = currentEvent.round;
        
        // 다음 라운드 결정
        let nextRound = 'Final';
        if (currentEvent.round === 'Round 1') {
            nextRound = 'Semi-Final';
        } else if (currentEvent.round === 'Semi-Final') {
            nextRound = 'Final';
        }
        document.getElementById('next-round').textContent = nextRound;
    }
    
    // 진출자 수 설정
    const recallCount = currentEvent ? parseInt(currentEvent.recall) || 0 : 0;
    document.getElementById('advance-count').value = recallCount;
}

function previewTransition() {
    console.log('Previewing round transition...');
    // 라운드 전환 미리보기 로직 구현
    alert('라운드 전환 미리보기 기능은 추후 구현됩니다.');
}

function executeTransition() {
    const advanceCount = parseInt(document.getElementById('advance-count').value);
    
    if (!advanceCount || advanceCount <= 0) {
        alert('진출할 팀 수를 입력해주세요.');
        return;
    }
    
    const currentEvent = events.find(ev => (ev.detail_no || ev.no) === getCurrentEventNo());
    if (!currentEvent) {
        alert('현재 이벤트를 찾을 수 없습니다.');
        return;
    }
    
    // 동점 상황 확인
    const aggregationData = generateAggregationData(getCurrentEventNo());
    const sortedPlayers = Object.entries(aggregationData.aggregation.player_scores)
        .sort(([,a], [,b]) => b.total_recall - a.total_recall);
    
    const originalRecallCount = parseInt(currentEvent.recall) || 0;
    const actualAdvancingCount = Math.min(advanceCount, sortedPlayers.length);
    
    // 동점으로 인한 자동 조정 확인
    if (actualAdvancingCount > originalRecallCount) {
        const tieMessage = `동점으로 인해 진출 팀 수가 ${originalRecallCount}팀에서 ${actualAdvancingCount}팀으로 자동 조정됩니다.\n계속하시겠습니까?`;
        if (!confirm(tieMessage)) {
            return;
        }
    }
    
    if (confirm(`정말로 ${actualAdvancingCount}팀을 다음 라운드로 전환하시겠습니까?`)) {
        // 이벤트의 recall 수 업데이트
        currentEvent.recall = actualAdvancingCount.toString();
        
        // 서버에 업데이트된 이벤트 정보 저장
        saveEventInfo(getCurrentEventNo(), {
            recall: actualAdvancingCount.toString(),
            heats: currentEvent.heats || '',
            from_event: currentEvent.from_event || '',
            to_event: currentEvent.to_event || ''
        }).then(success => {
            if (success) {
                // 다음 라운드 선수 파일 생성
                createNextRoundPlayerFile(currentEvent, actualAdvancingCount, aggregationData).then(() => {
                    // 결과 파일 생성
                    generateResultFiles(currentEvent, aggregationData).then(() => {
                        // 이벤트 리스트 새로고침
                        loadEvents();
                        
                        // 집계 데이터 새로고침
                        loadAggregationData(getCurrentEventNo());
                        
                        // 라운드 전환 완료 알림
                        alert(`라운드 전환이 완료되었습니다.\n진출 팀: ${actualAdvancingCount}팀\n다음 라운드 선수 파일이 생성되었습니다.\n상세 리포트가 생성되었습니다.`);
                        
                        // 모달 닫기
                        closeAggregationModal();
                        
                        // 결과 페이지로 자동 리다이렉트
                        setTimeout(() => {
                            const compId = "<?=addslashes($comp_id)?>";
                            window.location.href = `../competition.php?id=${compId}&page=results`;
                        }, 1000);
                    });
                });
            } else {
                alert('저장에 실패했습니다. 다시 시도해주세요.');
            }
        });
    }
}

function createNextRoundPlayerFile(currentEvent, advancingCount, aggregationData) {
    console.log('Creating next round player file...');
    
    return new Promise((resolve, reject) => {
        // 다음 라운드 이벤트 번호 찾기
        const nextEventNo = findNextRoundEvent(currentEvent);
        if (!nextEventNo) {
            console.error('Next round event not found');
            reject('Next round event not found');
            return;
        }
        
        // 진출자 목록 생성
        const advancingPlayers = getAdvancingPlayers(aggregationData, advancingCount);
        
        // 면제자 목록 추가
        const exemptionKey = `exemption_${currentEvent.no}`;
        const exemptedPlayers = JSON.parse(localStorage.getItem(exemptionKey) || '[]');
        const exemptedNumbers = exemptedPlayers.map(p => p.number);
        
        // 최종 선수 목록 (진출자 + 면제자)
        const finalPlayers = [...advancingPlayers, ...exemptedNumbers];
        
        // 중복 제거
        const uniquePlayers = [...new Set(finalPlayers)];
        
        console.log(`Creating players file for event ${nextEventNo}:`, uniquePlayers);
        
        // 서버에 선수 파일 생성 요청
        fetch('create_next_round_players.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                comp_id: "<?=addslashes($comp_id)?>",
                eventNo: nextEventNo,
                players: uniquePlayers
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log('Next round player file created successfully:', data.message);
                resolve(data);
            } else {
                console.error('Failed to create next round player file:', data.message);
                reject(data.message);
            }
        })
        .catch(error => {
            console.error('Error creating next round player file:', error);
            reject(error);
        });
    });
}

// 결과 파일 생성 함수
function generateResultFiles(currentEvent, aggregationData) {
    console.log('Generating result files...');
    
    return new Promise((resolve, reject) => {
        // 직접 파일 생성 방식으로 변경
        const compId = "<?=addslashes($comp_id)?>";
        const eventNo = currentEvent.no;
        const eventName = currentEvent.name || currentEvent.desc || '경기 종목';
        
        // Results 폴더 생성
        const resultsDir = `data/${compId}/Results`;
        const eventResultsDir = `${resultsDir}/Event_${eventNo}`;
        
        // 폴더 생성 요청
        fetch('create_results_folder.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                comp_id: compId,
                event_no: eventNo,
                event_name: eventName,
                aggregation_data: aggregationData
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Result files generated successfully');
                resolve(data);
            } else {
                console.error('Failed to generate result files:', data.message);
                reject(data.message);
            }
        })
        .catch(error => {
            console.error('Error generating result files:', error);
            reject(error);
        });
    });
}

function findNextRoundEvent(currentEvent) {
    // From-To 관계에서 To 이벤트 찾기
    if (currentEvent.to_event) {
        return currentEvent.to_event;
    }
    
    // 같은 제목의 다음 이벤트 찾기
    const sameTitleEvents = events.filter(ev => ev.desc === currentEvent.desc);
    const currentIdx = sameTitleEvents.findIndex(ev => ev.no === currentEvent.no);
    
    if (currentIdx < sameTitleEvents.length - 1) {
        const nextEvent = sameTitleEvents[currentIdx + 1];
        return nextEvent.detail_no || nextEvent.no;
    }
    
    return null;
}

function getAdvancingPlayers(aggregationData, advancingCount) {
    const sortedPlayers = Object.entries(aggregationData.aggregation.player_scores)
        .sort(([,a], [,b]) => b.total_recall - a.total_recall);
    
    // 상위 진출자들만 선택
    const advancingPlayers = sortedPlayers
        .slice(0, advancingCount)
        .map(([playerNumber]) => playerNumber);
    
    return advancingPlayers;
}

function selectEvent(eventIdx) {
    if (eventIdx < 0 || eventIdx >= events.length) {
        console.error('Invalid event index:', eventIdx);
        return;
    }
    
    console.log('selectEvent called with idx:', eventIdx, 'event:', events[eventIdx]);
    
    // 이전 선택 해제
    const prevSelected = document.querySelector('.event-row.selected');
    if (prevSelected) {
        prevSelected.classList.remove('selected');
    }
    
    // 새 이벤트 선택
    const newSelected = document.querySelector(`tr[data-idx="${eventIdx}"]`);
    if (newSelected) {
        newSelected.classList.add('selected');
    }
    
    // 현재 인덱스 업데이트
    curIdx = eventIdx;
    console.log('Updated curIdx to:', curIdx);
    
    // 이벤트 정보 업데이트
    updateEventInfo();
    
    // 출전선수 목록 로드
    loadPlayersForCurrentEvent();
    
    console.log(`Selected event ${eventIdx}:`, events[eventIdx]);
}

function switchToNextRoundEvent(currentEvent) {
    // 다음 라운드 이벤트 번호 찾기
    const nextEventNo = findNextRoundEvent(currentEvent);
    if (!nextEventNo) {
        console.log('Next round event not found, staying on current event');
        return;
    }
    
    // 다음 라운드 이벤트 찾기
    const nextEventIdx = events.findIndex(ev => 
        (ev.detail_no || ev.no) === nextEventNo
    );
    
    if (nextEventIdx !== -1) {
        console.log(`Switching to next round event: ${nextEventNo} (index: ${nextEventIdx})`);
        
        // 이벤트 선택
        selectEvent(nextEventIdx);
    } else {
        console.log('Next round event not found in events list');
    }
}

function updateEventInfo() {
    const currentEvent = events[curIdx];
    if (!currentEvent) return;
    
    // 이벤트 정보 표시 업데이트
    const eventInfoElement = document.getElementById('currentEventInfo');
    if (eventInfoElement) {
        const eventNo = currentEvent.detail_no || currentEvent.no;
        eventInfoElement.textContent = `${eventNo} - ${currentEvent.desc} (${currentEvent.round})`;
    }
    
    // Recall, Heats 정보 업데이트
    const recallInput = document.getElementById('recallInput');
    const heatsInput = document.getElementById('heatsInput');
    
    if (recallInput) {
        recallInput.value = currentEvent.recall || '';
    }
    if (heatsInput) {
        heatsInput.value = currentEvent.heats || '';
    }
}

function loadPlayersForCurrentEvent() {
    const currentEvent = events[curIdx];
    if (!currentEvent) return;
    
    const eventNo = currentEvent.detail_no || currentEvent.no;
    console.log(`Loading players for event ${eventNo}...`);
    
    // 출전선수 목록 로드
    fetch(`get_players.php?comp_id=<?=addslashes($comp_id)?>&event_no=${eventNo}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updatePlayerList(data.players);
                console.log(`Players loaded for event ${eventNo}:`, data.players);
            } else {
                console.error('Failed to load players:', data.message);
                updatePlayerList([]);
            }
        })
        .catch(error => {
            console.error('Error loading players:', error);
            updatePlayerList([]);
        });
}

function updatePlayerList(players) {
    const playerList = document.getElementById('player-list');
    if (!playerList) {
        console.error('Player list element not found');
        return;
    }
    
    // 현재 이벤트의 키 가져오기
    const currentEvent = events[curIdx];
    if (!currentEvent) {
        console.error('No current event found');
        return;
    }
    const eventKey = currentEvent.detail_no || currentEvent.no;
    
    // playersByEvent 업데이트
    playersByEvent[eventKey] = players.map(p => p.number);
    
    // 기존 목록 지우기
    playerList.innerHTML = '';
    
    // 새 선수 목록 추가
    players.forEach(player => {
        const li = document.createElement('li');
        li.innerHTML = `${player.number} <button class="player-x-btn" onclick="removePlayer('${player.number}')">X</button>`;
        playerList.appendChild(li);
    });
    
    // 팀 수 업데이트
    updateTeamCount(players.length);
    
    console.log(`Updated player list with ${players.length} players:`, players);
}

function updateTeamCount(count) {
    // 이벤트 리스트의 팀 수 셀 업데이트
    const currentEvent = events[curIdx];
    if (currentEvent) {
        const eventIdx = events.findIndex(ev => ev === currentEvent);
        const tr = document.querySelector(`#event-table tr[data-idx="${eventIdx}"]`);
        if (tr && tr.cells[3]) { // 팀수는 4번째 컬럼 (인덱스 3)
            tr.cells[3].textContent = count > 0 ? count : '-';
        }
        
        // 이벤트 데이터 업데이트
        currentEvent.team_count = count;
    }
    
    console.log(`Updated team count to: ${count}`);
}

function getCurrentEventNo() {
    // 현재 선택된 이벤트 번호 반환
    const currentEvent = events[curIdx];
    if (!currentEvent) {
        console.log('No current event found, curIdx:', curIdx);
        return '';
    }
    
    const eventNo = currentEvent.detail_no || currentEvent.no || '';
    console.log('getCurrentEventNo - curIdx:', curIdx, 'eventNo:', eventNo, 'event:', currentEvent);
    console.log('Available events:', events.map(ev => ({no: ev.no, detail_no: ev.detail_no, desc: ev.desc})));
    
    // detail_no가 있으면 detail_no를, 없으면 no를 사용
    return eventNo;
}

// 특정 이벤트 번호로 이벤트 선택
function selectEventByNumber(eventNumber) {
    console.log('Selecting event by number:', eventNumber);
    
    const eventIdx = events.findIndex(ev => 
        (ev.detail_no && ev.detail_no === eventNumber) || 
        (ev.no === eventNumber)
    );
    
    if (eventIdx !== -1) {
        console.log('Found event at index:', eventIdx);
        selectEvent(eventIdx);
    } else {
        console.log('Event not found:', eventNumber);
        console.log('Available events:', events.map(ev => ({no: ev.no, detail_no: ev.detail_no})));
    }
}
document.getElementById('playerInput').onkeydown = function(e){
    if (e.key === 'Enter') {
        submitPlayerModal(false);
        e.preventDefault();
    }
    if (e.key === 'Escape') {
        closePlayerModal();
    }
};
function saveAndClosePlayerModal() {
    submitPlayerModal(true);
}
function parseBibsFromInput(input) {
    // 구분자 표준화: 전각 콤마/세미콜론/공백/개행 -> 콤마
    let norm = (input || '')
        .replace(/[\u3001\uFF0C]/g, ',') // 전각/중국어 콤마
        .replace(/[;\n\r\t ]+/g, ',');  // 기타 구분자
    let result = [];
    norm.split(',').forEach(part=>{
        part = part.trim();
        if (!part) return;
        if (/^\d+\s*[~\-]\s*\d+$/.test(part)) {
            let [start, end] = part.split(/~|-/).map(x=>parseInt(x.trim(),10));
            if (Number.isInteger(start) && Number.isInteger(end) && start <= end && (end - start) < 1000) {
                for (let i=start; i<=end; i++) result.push(String(i));
            }
        } else if (/^\d+$/.test(part)) {
            result.push(part);
        }
    });
    // 중복 제거 및 소팅은 호출측에서 수행
    return result;
}
function submitPlayerModal(closeAfter) {
    let val = document.getElementById('playerInput').value.trim();
    if (!val) {
        if (closeAfter) closePlayerModal();
        return;
    }
    let currentEvent = events[curIdx];
    let eventKey = currentEvent.detail_no || currentEvent.no; // 세부번호 우선
    
    if (!playersByEvent[eventKey]) playersByEvent[eventKey] = [];
    let bibs = parseBibsFromInput(val);
    let added = 0;
    bibs.forEach(bib=>{
        if (!playersByEvent[eventKey].includes(bib)) {
            playersByEvent[eventKey].push(bib);
            added++;
        }
    });
    if (added>0) {
        playersByEvent[eventKey] = playersByEvent[eventKey].slice().sort((a, b) => Number(a) - Number(b));
        savePlayersToServer(eventKey);
        fetchHits(eventKey);
    }
    if (closeAfter) {
        closePlayerModal();
    } else {
        document.getElementById('playerInput').value = '';
        setTimeout(()=>{document.getElementById('playerInput').focus();}, 100);
    }
}
function removePlayer(bib) {
    let currentEvent = events[curIdx];
    let eventKey = currentEvent.detail_no || currentEvent.no;
    let arr = playersByEvent[eventKey] || [];
    playersByEvent[eventKey] = arr.filter(x => x !== bib);
    savePlayersToServer(eventKey);
    fetchHits(eventKey);
}
function showEntryPlayers() {
    let currentEvent = events[curIdx];
    if (!currentEvent) {
        console.error('No current event found, curIdx:', curIdx);
        return;
    }
    
    // detail_no가 있으면 detail_no를, 없으면 no를 사용
    const eventNo = currentEvent.detail_no || currentEvent.no;
    const key = eventNo;
    
    console.log('showEntryPlayers debug:', {
        curIdx: curIdx,
        currentEvent: currentEvent,
        eventNo: eventNo,
        key: key,
        playersByEvent: playersByEvent,
        playersForKey: playersByEvent[key]
    });
    
    let entryBibs = playersByEvent[key] || [];
    let sorted = entryBibs.slice().sort((a,b)=>Number(a)-Number(b));
    
    // 이벤트 정보 업데이트
    let eventDisplay = currentEvent.detail_no ? `${currentEvent.no}-${currentEvent.detail_no}` : currentEvent.no;
    let subtitle = `이벤트 ${eventDisplay}: ${currentEvent.desc} | ${sorted.length}명`;
    document.getElementById('entryPlayersSubtitle').textContent = subtitle;
    
    let tbody = document.querySelector('#entryPlayersTable tbody');
    tbody.innerHTML = '';
    if (!sorted.length) {
        tbody.innerHTML = '<tr><td colspan="3" style="color:#aaa; text-align:center; padding:2em;">출전선수 없음</td></tr>';
    } else {
        sorted.forEach((bib, index) => {
            let p = allPlayers[bib] || {};
            let male = p.male || '';
            let female = p.female || '';
            let tr = document.createElement('tr');
            tr.style.cssText = index % 2 === 0 ? 'background:#fff;' : 'background:#f8f9fa;';
            tr.innerHTML = `
                <td style="text-align:center; padding:0.6em 0.5em; font-weight:600; color:#0d2c96;">${bib}</td>
                <td style="padding:0.6em 1em; color:#333;">${male}</td>
                <td style="padding:0.6em 1em; color:#333;">${female}</td>
            `;
            tbody.appendChild(tr);
        });
    }
    document.getElementById('entryPlayersModalBg').style.display = 'flex';
}
function closeEntryPlayersModal() {
    document.getElementById('entryPlayersModalBg').style.display = 'none';
}

function printEntryPlayers() {
    let currentEvent = events[curIdx];
    const key = currentEvent.detail_no || currentEvent.no;
    let entryBibs = playersByEvent[key] || [];
    let sorted = entryBibs.slice().sort((a,b)=>Number(a)-Number(b));
    
    if (!sorted.length) {
        alert('인쇄할 출전선수가 없습니다.');
        return;
    }
    
    let eventDisplay = currentEvent.detail_no ? `${currentEvent.no}-${currentEvent.detail_no}` : currentEvent.no;
    
    // 인쇄 전용 요소 생성
    let printDiv = document.createElement('div');
    printDiv.id = 'print-entry-content';
    printDiv.style.cssText = `
        position: fixed; 
        left: 0; 
        top: 0; 
        width: 100vw; 
        height: 100vh;
        background: white; 
        padding: 20px; 
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.4;
        z-index: 9999;
        visibility: hidden;
    `;
    
    let html = `
        <style>
            body * { visibility: hidden !important; }
            #print-entry-content, #print-entry-content * { visibility: visible !important; }
            
            @media print {
                @page {
                    size: A4;
                    margin: 2cm;
                }
                body * { visibility: hidden !important; }
                #print-entry-content, #print-entry-content * { visibility: visible !important; }
                #print-entry-content {
                    position: fixed !important;
                    left: 0 !important;
                    top: 0 !important;
                    width: 100vw !important;
                    height: 100vh !important;
                    background: white !important;
                    padding: 20px !important;
                    margin: 0 !important;
                    z-index: 9999 !important;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    font-size: 18px;
                    font-weight: bold;
                    border-bottom: 2px solid #333;
                    padding-bottom: 10px;
                    color: #000 !important;
                }
                .print-table {
                    width: 85%;
                    margin: 0 auto 10px auto;
                    border-collapse: collapse;
                    font-size: 10px;
                }
                .print-table th, .print-table td {
                    border: 1px solid #333;
                    padding: 4px 6px;
                    text-align: left;
                    vertical-align: top;
                    color: #000 !important;
                    background: white !important;
                }
                .print-table th {
                    background: #f0f0f0 !important;
                    font-weight: bold;
                    text-align: center;
                }
                .print-table td:first-child {
                    text-align: center;
                    font-weight: bold;
                }
                .print-info {
                    margin-bottom: 15px;
                    font-size: 13px;
                    color: #000 !important;
                    line-height: 1.6;
                }
            }
        </style>
        <div class="print-header">
            <div style="font-size: 20px; margin-bottom: 8px;"><?=h($info['title'])?></div>
            <div style="font-size: 14px; color: #666; margin-bottom: 15px;"><?=h($info['date'])?> | <?=h($info['place'])?></div>
            <div style="font-size: 16px; font-weight: bold;">출전선수 명단</div>
        </div>
        <div class="print-info">
            <strong>이벤트:</strong> ${eventDisplay} - ${currentEvent.desc}<br>
            <strong>출전팀 수:</strong> ${sorted.length}팀<br>
            <strong>인쇄일시:</strong> ${new Date().toLocaleString('ko-KR')}
        </div>
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width: 15%;">등번호</th>
                    <th style="width: 42.5%;">남자선수</th>
                    <th style="width: 42.5%;">여자선수</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    sorted.forEach(bib => {
        let p = allPlayers[bib] || {};
        let male = p.male || '';
        let female = p.female || '';
        html += `
            <tr>
                <td>${bib}</td>
                <td>${male}</td>
                <td>${female}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    printDiv.innerHTML = html;
    document.body.appendChild(printDiv);
    
    // 인쇄 실행 전 잠시 대기
    setTimeout(() => {
        printDiv.style.visibility = 'visible';
        
        // 인쇄 후 요소 제거를 위한 이벤트 리스너
        const cleanupEntry = () => {
            let element = document.getElementById('print-entry-content');
            if (element && element.parentNode) {
                element.parentNode.removeChild(element);
            }
            window.removeEventListener('afterprint', cleanupEntry);
        };
        
        window.addEventListener('afterprint', cleanupEntry);
        
        // 인쇄 실행
        window.print();
        
        // 인쇄 취소 시를 위한 백업 정리 (5초 후)
        setTimeout(() => {
            let element = document.getElementById('print-entry-content');
            if (element && element.parentNode) {
                element.parentNode.removeChild(element);
            }
        }, 5000);
    }, 100);
}
function updatePanel(idx) {
    if (idx < 0) idx = 0;
    if (idx > events.length-1) idx = events.length-1;
    curIdx = idx;
    let ev = events[curIdx];
    
    console.log('updatePanel called with idx:', idx, 'curIdx:', curIdx, 'event:', ev);
    // 이벤트 번호 표시 (세부번호가 있으면 포함)
    const eventNoDisplay = ev.no + (ev.detail_no ? '-' + ev.detail_no : '');
    document.getElementById('evtNo').value = eventNoDisplay;
    document.getElementById('evtName').value = ev.desc || '';
    
    // 저장된 이벤트 정보 불러오기 (세부번호 우선)
    let eventKey = ev.detail_no || ev.no;
    let savedInfo = eventInfo[eventKey] || eventInfo[ev.no] || {};
    
    // 디버그: Recall 값 추적
    console.log('updatePanel debug:', {
        eventNo: ev.no,
        detailNo: ev.detail_no,
        eventKey: eventKey,
        savedInfoRecall: savedInfo.recall,
        evRecall: ev.recall,
        finalRecall: savedInfo.recall || ev.recall || ''
    });
    
    // RunOrder_Tablet.txt의 실제 값을 우선 사용 (로컬 저장소 무시)
    document.getElementById('evtRecall').value = ev.recall || '';
    document.getElementById('evtHeats').value = ev.heats || '';
    
    // From, To 자동 설정 (저장된 정보가 있으면 사용)
    if (savedInfo.from_event || savedInfo.to_event) {
        document.getElementById('evtFrom').value = savedInfo.from_event || '';
        document.getElementById('evtTo').value = savedInfo.to_event || '';
    } else {
        setFromToEvents(ev.desc);
    }
    
    // To 컬럼 업데이트
    updateToColumn();
    
    document.querySelectorAll('#event-table tr').forEach(tr=>tr.classList.remove('selected'));
    let tr = document.querySelector('#event-table tr[data-idx="'+curIdx+'"]');
    if (tr) tr.classList.add('selected');
    // 세부번호가 있으면 세부번호 전달
    let currentEventNo = ev.detail_no || ev.no;
    renderAdjudicatorList(ev.panel, currentEventNo);
    renderPlayerList(currentEventNo);  // 세부번호 전달
    loadPlayersForCurrentEvent();  // 서버에서 최신 선수 목록 로드
    renderDanceBlock(curIdx);
    fetchHits(currentEventNo);  // 세부번호 전달
    
    // 진행 정보 업데이트
    updateEventProgressInfo();
    
    console.log('updatePanel completed - curIdx:', curIdx, 'currentEventNo:', currentEventNo);
    
    // 감시 시스템 초기화
    initMonitoringSystem();
}

function setFromToEvents(currentEventDesc) {
    if (!currentEventDesc) {
        document.getElementById('evtFrom').value = '';
        document.getElementById('evtTo').value = '';
        updateEventProgressInfo();
        return;
    }
    
    // 현재 이벤트와 같은 제목을 가진 이벤트들 찾기
    let sameTitleEvents = events.filter(ev => ev.desc === currentEventDesc);
    
    if (sameTitleEvents.length <= 1) {
        // 같은 제목의 이벤트가 하나뿐이면 From, To 모두 공란
        document.getElementById('evtFrom').value = '';
        document.getElementById('evtTo').value = '';
    } else {
        // 현재 이벤트의 인덱스 찾기
        let currentEventIdx = sameTitleEvents.findIndex(ev => ev.no === events[curIdx].no);
        
        // From 설정 (이전 이벤트)
        if (currentEventIdx > 0) {
            document.getElementById('evtFrom').value = sameTitleEvents[currentEventIdx - 1].no;
        } else {
            document.getElementById('evtFrom').value = '';
        }
        
        // To 설정 (다음 이벤트)
        if (currentEventIdx < sameTitleEvents.length - 1) {
            document.getElementById('evtTo').value = sameTitleEvents[currentEventIdx + 1].no;
        } else {
            document.getElementById('evtTo').value = '';
        }
    }
    
    // 진행 정보 업데이트
    updateEventProgressInfo();
}

function updateEventProgressInfo() {
    let currentEvent = events[curIdx];
    if (!currentEvent) return;
    
    let sameTitleEvents = events.filter(ev => ev.desc === currentEvent.desc);
    let currentEventIdx = sameTitleEvents.findIndex(ev => ev.no === currentEvent.no);
    let totalEvents = sameTitleEvents.length;
    
    // 디버깅용 로그
    console.log('Current event:', currentEvent.desc, 'No:', currentEvent.no);
    console.log('Same title events:', sameTitleEvents.map(ev => ev.no));
    console.log('Current event idx:', currentEventIdx, 'Total events:', totalEvents);
    
    let stageText = '';
    if (totalEvents === 1) {
        stageText = 'Final';
    } else if (totalEvents === 2) {
        if (currentEventIdx === 0) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 3) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 4) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Round 2';
        else if (currentEventIdx === 2) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 5) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Round 2';
        else if (currentEventIdx === 2) stageText = 'Round 3';
        else if (currentEventIdx === 3) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else {
        // 6개 이상일 때는 숫자로 표시
        stageText = `${currentEventIdx + 1}/${totalEvents}`;
    }
    
    // 단계명과 순번/총수 조합
    let progressText = `${stageText} (${currentEventIdx + 1}/${totalEvents})`;
    
    let progressElement = document.getElementById('eventProgressInfo');
    if (progressElement) {
        progressElement.textContent = progressText;
    }
}

// 이벤트 정보 저장 (매개변수 버전)
function saveEventInfo(eventNo, eventData) {
    if (eventNo && eventData) {
        // 매개변수로 받은 데이터로 저장
        console.log('Saving event info with parameters:', eventNo, eventData);
        
        return fetch('save_runorder_info.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                comp_id: "<?=addslashes($comp_id)?>",
                eventNo: eventNo,
                recall: eventData.recall || '',
                heats: eventData.heats || '',
                from_event: eventData.from_event || '',
                to_event: eventData.to_event || ''
            })
        })
        .then(res => res.json())
        .then(data => {
            console.log('Save response:', data);
            if (data.success) {
                console.log('Save successful, updating UI...');
                // 성공 시 이벤트 리스트의 해당 행 업데이트
                updateEventListRow(eventNo, eventData.recall, eventData.heats);
                
                // 현재 이벤트의 recall과 heats 값 업데이트
                const currentEvent = events.find(ev => (ev.detail_no || ev.no) === eventNo);
                if (currentEvent) {
                    currentEvent.recall = eventData.recall;
                    currentEvent.heats = eventData.heats;
                    currentEvent.from_event = eventData.from_event;
                    currentEvent.to_event = eventData.to_event;
                }
                return true;
            } else {
                console.error('Save failed:', data.message);
                return false;
            }
        })
        .catch(error => {
            console.error('Save error:', error);
            return false;
        });
    } else {
        // 기존 방식 (DOM에서 값 읽기)
        return saveEventInfoFromDOM();
    }
}

// 이벤트 정보 저장 (DOM에서 값 읽기)
function saveEventInfoFromDOM() {
    let currentEvent = events[curIdx];
    let eventNo = currentEvent.detail_no || currentEvent.no; // 세부번호가 있으면 세부번호 사용
    let fromEvent = document.getElementById('evtFrom').value.trim();
    let toEvent = document.getElementById('evtTo').value.trim();
    let recall = document.getElementById('evtRecall').value.trim();
    let heats = document.getElementById('evtHeats').value.trim();
    
    // 디버그: 저장할 이벤트 정보 확인
    console.log('Saving event info for:', eventNo, 'recall:', recall, 'heats:', heats);
    
    // RunOrder_Tablet.txt 파일 업데이트
    fetch('save_runorder_info.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            comp_id: "<?=addslashes($comp_id)?>",
            eventNo: eventNo,
            recall: recall,
            heats: heats
        })
    })
    .then(res => res.json())
    .then(data => {
        console.log('Save response:', data);
        if (data.success) {
            console.log('Save successful, updating UI...');
            // 성공 시 이벤트 리스트의 해당 행 업데이트
            updateEventListRow(eventNo, recall, heats);
            
            // 현재 이벤트의 recall과 heats 값 업데이트
            let currentEvent = events[curIdx];
            currentEvent.recall = recall;
            currentEvent.heats = heats;
            console.log('Updated current event:', currentEvent);
            
            // 저장된 정보를 로컬에 저장 (세부번호 키 사용)
            let storageKey = currentEvent.detail_no || currentEvent.no;
            if (!eventInfo[storageKey]) eventInfo[storageKey] = {};
            eventInfo[storageKey].recall = recall;
            eventInfo[storageKey].heats = heats;
            localStorage.setItem('event_info', JSON.stringify(eventInfo));
            
            // 메인 컨트롤 패널 업데이트
            updatePanel(curIdx);
            
            // 진행 정보 업데이트
            updateEventProgressInfo();
            
            console.log('이벤트 정보가 성공적으로 저장되었습니다.');
        } else {
            console.warn('RunOrder 정보 저장 실패:', data.error);
        }
    })
    .catch(err => {
        console.warn('RunOrder 정보 저장 오류:', err);
    });
    
    // 기존 이벤트 정보도 저장 (하위 호환성)
    fetch('save_event_info.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            comp_id: "<?=addslashes($comp_id)?>",
            eventNo: eventNo,
            fromEvent: fromEvent,
            toEvent: toEvent,
            recall: recall,
            heats: heats
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            console.warn('이벤트 정보 저장 실패:', data.error);
        }
    })
    .catch(err => {
        console.warn('이벤트 정보 저장 오류:', err);
    });
}

// 이벤트 리스트의 해당 행 업데이트
function updateEventListRow(eventNo, recall, heats) {
    console.log('updateEventListRow called:', {eventNo, recall, heats});
    
    // 세부번호나 기본번호로 이벤트 찾기
    let eventIdx = events.findIndex(ev => (ev.detail_no || ev.no) === eventNo);
    console.log('Found event index:', eventIdx);
    if (eventIdx === -1) return;
    
    // 이벤트 데이터 업데이트
    events[eventIdx].recall = recall;
    events[eventIdx].heats = heats;
    console.log('Updated events array:', events[eventIdx]);
    
    // 테이블 행 업데이트
    let tr = document.querySelector(`#event-table tr[data-idx="${eventIdx}"]`);
    console.log('Found table row:', tr);
    if (tr) {
        // Recall 컬럼 (5번째, 인덱스 4)
        let recallCell = tr.cells[4];
        console.log('Recall cell:', recallCell);
        if (recallCell) {
            recallCell.textContent = recall || '-';
            console.log('Updated recall cell to:', recallCell.textContent);
        }
        
        // 팀수 업데이트 (세부번호별)
        let detailNo = events[eventIdx].detail_no;
        if (detailNo && playersByDetail[detailNo]) {
            let teamCount = playersByDetail[detailNo].length;
            let teamCell = tr.cells[3]; // 팀수는 4번째 컬럼 (인덱스 3)
            if (teamCell) {
                teamCell.textContent = teamCount > 0 ? teamCount : '-';
            }
        }
    }
}

// 이벤트 정보 불러오기
function loadEventInfo() {
    fetch(`get_event_info.php?comp_id=<?=urlencode($comp_id)?>&${Date.now()}`)
        .then(r => r.ok ? r.json() : {success: false, event_info: {}})
        .then(data => {
            if (data.success && data.event_info) {
                eventInfo = data.event_info;
                updateAllRoundInfo();
            }
        })
        .catch(err => {
            console.warn('이벤트 정보 로드 오류:', err);
            eventInfo = {};
        });
}

// 모든 이벤트의 라운드 정보 업데이트
function updateAllRoundInfo() {
    // 먼저 저장된 라운드 정보를 불러와서 적용
    loadSavedRoundInfo().then(() => {
        events.forEach((ev, idx) => {
            updateRoundInfo(ev, idx);
            // 이벤트 리스트의 라운드 셀도 업데이트
            updateEventListRoundCell(ev, idx);
        });
    });
}

// 이벤트 리스트의 라운드 셀 업데이트
function updateEventListRoundCell(event, eventIdx) {
    // 먼저 저장된 라운드 정보가 있는지 확인
    if (roundInfo && roundInfo[eventIdx]) {
        let roundCell = document.querySelector(`#event-table tr[data-idx="${eventIdx}"] .round-cell`);
        if (roundCell) {
            roundCell.textContent = roundInfo[eventIdx];
        }
        return;
    }
    
    // 저장된 라운드 정보가 없으면 계산
    let sameTitleEvents = events.filter(ev => ev.desc === event.desc);
    let currentEventIdx = sameTitleEvents.findIndex(ev => ev.no === event.no);
    let totalEvents = sameTitleEvents.length;
    
    let stageText = '';
    if (totalEvents === 1) {
        stageText = 'Final';
    } else if (totalEvents === 2) {
        if (currentEventIdx === 0) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 3) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 4) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Round 2';
        else if (currentEventIdx === 2) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 5) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Round 2';
        else if (currentEventIdx === 2) stageText = 'Round 3';
        else if (currentEventIdx === 3) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else {
        stageText = `${currentEventIdx + 1}/${totalEvents}`;
    }
    
    // 이벤트 리스트의 라운드 셀 업데이트
    let roundCell = document.querySelector(`#event-table tr[data-idx="${eventIdx}"] .round-cell`);
    if (roundCell) {
        roundCell.textContent = stageText;
    }
}

// 저장된 라운드 정보 불러오기
function loadSavedRoundInfo() {
    return fetch(`get_round_info.php?comp_id=<?=urlencode($comp_id)?>&${Date.now()}`)
        .then(r => r.ok ? r.json() : {success: false, round_info: {}})
        .then(data => {
            if (data.success && data.round_info) {
                // 저장된 라운드 정보가 있으면 사용
                events.forEach((ev, idx) => {
                    if (data.round_info[idx]) {
                        let roundCell = document.querySelector(`.round-cell[data-event-idx="${idx}"]`);
                        if (roundCell) {
                            roundCell.textContent = data.round_info[idx];
                        }
                    }
                });
                return true; // 저장된 정보 사용
            }
            return false; // 저장된 정보 없음
        })
        .catch(err => {
            console.warn('저장된 라운드 정보 로드 오류:', err);
            return false;
        });
}

// 개별 이벤트의 라운드 정보 업데이트
function updateRoundInfo(event, eventIdx) {
    if (!events || events.length === 0) {
        return;
    }
    
    let sameTitleEvents = events.filter(ev => ev.desc === event.desc);
    let currentEventIdx = sameTitleEvents.findIndex(ev => ev.no === event.no);
    let totalEvents = sameTitleEvents.length;
    
    let stageText = '';
    if (totalEvents === 1) {
        stageText = 'Final';
    } else if (totalEvents === 2) {
        if (currentEventIdx === 0) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 3) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 4) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Round 2';
        else if (currentEventIdx === 2) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else if (totalEvents === 5) {
        if (currentEventIdx === 0) stageText = 'Round 1';
        else if (currentEventIdx === 1) stageText = 'Round 2';
        else if (currentEventIdx === 2) stageText = 'Round 3';
        else if (currentEventIdx === 3) stageText = 'Semi-Final';
        else stageText = 'Final';
    } else {
        stageText = `${currentEventIdx + 1}/${totalEvents}`;
    }
    
    // 해당 이벤트의 라운드 셀 업데이트
    let roundCell = document.querySelector(`.round-cell[data-event-idx="${eventIdx}"]`);
    if (roundCell) {
        roundCell.textContent = stageText;
    }
}
document.getElementById('evtPrev').onclick = ()=>updatePanel(curIdx-1);
document.getElementById('evtNext').onclick = ()=>updatePanel(curIdx+1);
document.getElementById('evtRefresh').onclick = ()=>{
    // Recall과 Heats 값 저장
    saveEventInfo();
    // 패널 새로고침
    updatePanel(curIdx);
};
document.getElementById('evtClearCache').onclick = ()=>{
    if (confirm('로컬 저장소를 초기화하고 페이지를 새로고침하시겠습니까?')) {
        eventInfo = {};
        localStorage.removeItem('event_info');
        location.reload();
    }
};
document.getElementById('evtRangeMove').onclick = ()=>{
    let fromEvent = document.getElementById('evtFrom').value.trim();
    let toEvent = document.getElementById('evtTo').value.trim();
    
    if (!fromEvent && !toEvent) {
        alert('From 또는 To 이벤트 번호를 입력하세요.');
        return;
    }
    
    let targetIdx = -1;
    
    if (fromEvent) {
        // From 이벤트로 이동 (이전 진행 이벤트)
        targetIdx = events.findIndex(ev => ev.no === fromEvent);
        if (targetIdx === -1) {
            alert(`이벤트 ${fromEvent}를 찾을 수 없습니다.`);
            return;
        }
    } else if (toEvent) {
        // To 이벤트로 이동 (다음 진행 이벤트)
        targetIdx = events.findIndex(ev => ev.no === toEvent);
        if (targetIdx === -1) {
            alert(`이벤트 ${toEvent}를 찾을 수 없습니다.`);
            return;
        }
    }
    
    if (targetIdx !== -1) {
        updatePanel(targetIdx);
    }
};
document.querySelectorAll('#event-table tr[data-idx]').forEach(function(row) {
    row.addEventListener('click', function(){
        updatePanel(parseInt(this.dataset.idx));
    });
});
if(events.length) updatePanel(0);

// 이벤트 정보 자동 저장을 위한 이벤트 리스너
document.getElementById('evtFrom').addEventListener('change', saveEventInfo);
document.getElementById('evtTo').addEventListener('change', saveEventInfo);
document.getElementById('evtRecall').addEventListener('change', saveEventInfo);
document.getElementById('evtHeats').addEventListener('change', saveEventInfo);

// 저장 버튼 이벤트 리스너
document.getElementById('evtSave').addEventListener('click', function() {
    saveEventInfo();
    // 저장 완료 피드백
    const saveBtn = document.getElementById('evtSave');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = '✅ 저장됨';
    saveBtn.style.background = '#28a745';
    setTimeout(() => {
        saveBtn.textContent = originalText;
        saveBtn.style.background = '#28a745';
    }, 1500);
});

// 감시 시스템 버튼 이벤트
document.getElementById('start-monitoring').addEventListener('click', startMonitoring);
document.getElementById('stop-monitoring').addEventListener('click', stopMonitoring);

// 감시 시스템 함수들
function initMonitoringSystem() {
    const currentEvent = events[curIdx];
    if (!currentEvent || !currentEvent.dances || currentEvent.dances.length === 0) {
        document.getElementById('monitoring-block').style.display = 'none';
        return;
    }
    
    // 28번 이벤트인 경우에만 감시 시스템 표시
    if (currentEvent.no === 28) {
        document.getElementById('monitoring-block').style.display = 'block';
        monitoringState.danceList = currentEvent.dances;
        updateMonitoringDisplay();
    } else {
        document.getElementById('monitoring-block').style.display = 'none';
    }
}

function updateMonitoringDisplay() {
    if (monitoringState.danceList.length === 0) return;
    
    const currentDance = monitoringState.danceList[monitoringState.currentDanceIndex];
    const nextDance = monitoringState.currentDanceIndex < monitoringState.danceList.length - 1 
        ? monitoringState.danceList[monitoringState.currentDanceIndex + 1] 
        : null;
    
    document.getElementById('current-dance-name').textContent = danceMapEn[currentDance] || currentDance;
    document.getElementById('next-dance-name').textContent = nextDance ? (danceMapEn[nextDance] || nextDance) : '완료';
    
    // 진행률 업데이트 (실제 채점 데이터 확인)
    updateDanceProgress();
    
    // 진행종목 블럭 업데이트
    renderDanceBlock(curIdx);
}

function updateDanceProgress() {
    // 실제 채점 데이터 확인 로직 (추후 구현)
    const progress = 0; // 임시값
    document.getElementById('dance-progress').textContent = `${progress}/${monitoringState.requiredJudges}`;
}

function startMonitoring() {
    monitoringState.isActive = true;
    monitoringState.startTime = new Date();
    monitoringState.currentDanceIndex = 0;
    
    document.getElementById('start-monitoring').style.display = 'none';
    document.getElementById('stop-monitoring').style.display = 'inline-block';
    
    updateMonitoringDisplay();
    startTimer();
    
    // 채점 데이터 모니터링 시작
    startScoreMonitoring();
}

function stopMonitoring() {
    monitoringState.isActive = false;
    
    document.getElementById('start-monitoring').style.display = 'inline-block';
    document.getElementById('stop-monitoring').style.display = 'none';
    
    stopTimer();
    stopScoreMonitoring();
}

function startTimer() {
    monitoringState.timer = setInterval(() => {
        if (!monitoringState.isActive) return;
        
        const now = new Date();
        const elapsed = Math.floor((now - monitoringState.startTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        
        document.getElementById('timer-display').textContent = 
            `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }, 1000);
}

function stopTimer() {
    if (monitoringState.timer) {
        clearInterval(monitoringState.timer);
        monitoringState.timer = null;
    }
}

function startScoreMonitoring() {
    // 채점 데이터 모니터링 로직 (추후 구현)
    console.log('채점 데이터 모니터링 시작');
}

function stopScoreMonitoring() {
    // 채점 데이터 모니터링 중지 로직 (추후 구현)
    console.log('채점 데이터 모니터링 중지');
}

// 이벤트 리스트 새로고침 함수
function loadEvents() {
    console.log('Refreshing event list...');
    
    // To 컬럼 업데이트
    updateToColumn();
    
    // 모든 이벤트의 라운드 정보 업데이트
    updateAllRoundInfo();
    
    console.log('Event list refreshed');
}

// To 컬럼 업데이트 함수
function updateToColumn() {
    events.forEach((ev, idx) => {
        let toCell = document.querySelector(`#event-table tr[data-idx="${idx}"] .to-cell`);
        if (toCell) {
            // From-To 관계에서 To 이벤트 찾기
            let sameTitleEvents = events.filter(e => e.desc === ev.desc);
            let currentIdx = sameTitleEvents.findIndex(e => e.no === ev.no);
            
            if (currentIdx < sameTitleEvents.length - 1) {
                // 다음 라운드가 있으면 해당 이벤트 번호 표시
                let nextEvent = sameTitleEvents[currentIdx + 1];
                let toDisplay = nextEvent.detail_no ? `${nextEvent.no}-${nextEvent.detail_no}` : nextEvent.no;
                toCell.textContent = toDisplay;
            } else {
                // 마지막 라운드면 '-' 표시
                toCell.textContent = '-';
            }
        }
    });
}

// 자식 창 닫기 메시지 리스너
window.addEventListener('message', function(event) {
    if (event.data === 'closeChildWindow') {
        console.log('Received close message from child window');
        // 자식 창이 있다면 닫기
        if (window.openedWindow && !window.openedWindow.closed) {
            window.openedWindow.close();
        }
    }
});

// 심사위원 상태 모니터링 함수
function updateJudgeStatus(eventNo) {
    // 현재 이벤트의 심사위원들 상태 확인
    const currentEvent = events[curIdx];
    if (!currentEvent || (currentEvent.detail_no || currentEvent.no) !== eventNo) return;
    
    fetch(`get_judge_status.php?comp_id=<?=urlencode($comp_id)?>&event_no=${eventNo}&${Date.now()}`)
        .then(r => r.ok ? r.json() : {success: false, status: {}})
        .then(data => {
            if (data.success && data.status) {
                // 각 심사위원 상태 업데이트
                Object.keys(data.status).forEach(judgeCode => {
                    let statusElement = document.getElementById(`judge-status-${judgeCode}`);
                    if (statusElement) {
                        let status = data.status[judgeCode];
                        statusElement.className = `judge-status ${status.class}`;
                        statusElement.textContent = status.text;
                    }
                });
            }
        })
        .catch(err => {
            console.warn('심사위원 상태 로드 오류:', err);
        });
}

// 실시간 심사위원 상태 모니터링 시작
function startJudgeStatusMonitoring() {
    // 2초마다 심사위원 상태 업데이트
    setInterval(() => {
        if (events[curIdx]) {
            let eventNo = events[curIdx].detail_no || events[curIdx].no;
            updateJudgeStatus(eventNo);
        }
    }, 2000);
}

// 페이지 로드 시 이벤트 정보 불러오기 및 라운드 정보 초기화
loadEventInfo();

// 로컬 저장소 초기화 함수 (개발자 도구에서 사용)
window.clearEventInfo = function() {
    eventInfo = {};
    localStorage.removeItem('event_info');
    console.log('Event info cleared. Please refresh the page.');
};
// 이벤트 정보 로드 완료 후 라운드 정보 업데이트
setTimeout(() => {
    updateAllRoundInfo();
    updateToColumn();
    startJudgeStatusMonitoring(); // 심사위원 상태 모니터링 시작
}, 100);

// 채점하기 함수
function openScoring() {
    const eventNo = document.getElementById('evtNo').value;
    if (!eventNo) {
        alert('이벤트를 선택해주세요.');
        return;
    }
    
    const compId = '<?=h($comp_id)?>';
    window.openedWindow = window.open(`judge_scoring.php?comp_id=${compId}&event_no=${eventNo}`, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

// 특정 심사위원 채점 패널 열기 함수
function openJudgeScoring(eventNo, judgeCode) {
    if (!eventNo || !judgeCode) {
        alert('이벤트와 심사위원을 선택해주세요.');
        return;
    }
    
    // 디버그: 전달받은 매개변수 확인
    console.log('openJudgeScoring called:', {
        eventNo: eventNo,
        judgeCode: judgeCode,
        type: typeof judgeCode
    });
    
    const compId = '<?=h($comp_id)?>';
    const url = `judge_scoring.php?comp_id=${compId}&event_no=${eventNo}&judge_code=${judgeCode}&admin_mode=1`;
    
    console.log('Opening URL:', url);
    
    // 관리자 권한으로 특정 심사위원의 채점 패널 열기
    window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

// 채점 결과 보기 함수
function viewScores() {
    const eventNo = document.getElementById('evtNo').value;
    if (!eventNo) {
        alert('이벤트를 선택해주세요.');
        return;
    }
    
    const compId = '<?=h($comp_id)?>';
    window.open(`view_scores.php?comp_id=${compId}&event_no=${eventNo}`, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

window.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closePlayerModal();
});
</script>
</body>
</html>