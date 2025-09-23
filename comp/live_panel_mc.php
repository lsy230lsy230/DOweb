<?php
$comp_id = $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$adjudicator_file = "$data_dir/adjudicators.txt";
$panel_map_file = "$data_dir/panel_list.json";
$dancename_file = "$data_dir/DanceName.txt";

require_once 'detail_numbers_manager.php';

// --- 댄스종목 약어->이름 매핑 (DanceName.txt 기준) ---
$dance_map_en = [];
if (is_file($dancename_file)) {
    foreach (file($dancename_file) as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) < 3 || $cols[2] == '-' || $cols[2] == '') continue;
        $dance_map_en[$cols[2]] = $cols[1];
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
        $no = $cols[0] ?? '';
        $desc = $cols[1] ?? '';
        $roundtype = $cols[2] ?? '';
        $panel = isset($cols[11]) ? strtoupper($cols[11]) : '';
        $recall = $cols[4] ?? '';
        $heats = $cols[12] ?? '';
        // 6번째 필드는 "5" 등 숫자이므로, 7~11번째(W,T,V,S,Q 등)만 추출
        $dance_codes = [];
        for ($i=6; $i<=10; $i++) {
            if (isset($cols[$i]) && preg_match('/^[A-Z\.]{1,6}$/', $cols[$i]) && $cols[$i]!='AD' && $cols[$i]!='2') {
                // BOM 제거
                $codestr = $cols[$i];
                if (substr($codestr, 0, 3) === "\xEF\xBB\xBF") $codestr = substr($codestr, 3);
                $dance_codes[] = $codestr;
            }
        }
        $events[] = [
            'no' => $no,
            'desc' => $desc,
            'round' => $roundtype,
            'panel' => $panel,
            'recall' => $recall,
            'heats' => $heats,
            'dances' => $dance_codes
        ];
    }
}

// 세부번호 추가
foreach ($events as $idx => &$event) {
    $event['detail_no'] = getDetailNumber($comp_id, $event['no'], $event['desc']);
}

// 팀수 자동 계산 함수 (세부번호별)
function calculateTeamCountByDetail($comp_id, $detail_no) {
    // 세부번호별 선수 데이터 확인
    $players = getPlayersByDetailNumber($comp_id, $detail_no);
    if (!empty($players)) {
        return count($players);
    }
    
    // players_{detail_no}.txt 파일에서 선수 수 계산
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
    
    return '-';
}

// 기존 함수 (하위 호환성 유지)
function calculateTeamCount($comp_id, $event_no, $players_by_event) {
    // 선수 데이터가 있으면 선수 수 반환
    if (isset($players_by_event[$event_no]) && !empty($players_by_event[$event_no])) {
        return count($players_by_event[$event_no]);
    }
    
    // players_{event_no}.txt 파일에서 선수 수 계산
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
    
    // 폴백: 전체 players.txt에서 계산
    $players_file = __DIR__ . "/data/$comp_id/players.txt";
    if (file_exists($players_file)) {
        $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = 0;
        foreach ($lines as $line) {
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) >= 3 && !empty($cols[1]) && !empty($cols[2])) {
                $count++;
            }
        }
        return $count > 0 ? $count : '-';
    }
    
    return '-';
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

// --- 선수 데이터 불러오기 (이벤트별) ---
$players_by_event = [];
foreach ($events as $ev) {
    $eno = $ev['no'];
    // BOM 제거
    if (substr($eno, 0, 3) === "\xEF\xBB\xBF") $eno = substr($eno, 3);
    $pfile = "$data_dir/players_$eno.txt";
    if (is_file($pfile)) {
        $players_by_event[$eno] = array_filter(array_map('trim', file($pfile)));
    } else {
        $players_by_event[$eno] = [];
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
    <title><?= h($info['title']) ?> 사회자 컨트롤 패널</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        html, body { height:100%; margin:0; padding:0; }
        body { background:#1a1a1a; font-family:sans-serif; margin:0; height:100vh; }
        .live-root { width:100vw; height:100vh; min-height:100vh; min-width:100vw; background:#bdbdbd; margin:0; padding:0; display: flex; flex-direction:column; align-items:stretch; justify-content:stretch; }
        .live-frame { width:100vw; height:100vh; min-height:100vh; min-width:100vw; background: #fff; border: 0; box-sizing: border-box; display: flex; flex-direction: row; overflow: hidden; border-radius: 0; box-shadow: none; }
        .side-events {
            flex: 0 0 25vw;
            min-width: 220px;
            max-width: 35vw;
            background: #ededed;
            border-right: 3px solid #071d6e;
            overflow-y: auto;
            padding: 0.7em 0.2em 0.7em 0.7em;
            box-sizing: border-box;
        }
        .side-events h2 { font-size: 1.09em; margin: 0.2em 0 0.5em 0.2em; color: #071d6e; letter-spacing:0.1em;}
        .event-list { width: 100%; border-collapse: collapse; font-size: 1em;}
        .event-list tr.selected { background: #d0e6ff; }
        .event-list tr:hover { background: #e6f1ff; cursor:pointer;}
        .event-list td { border-bottom: 1px solid #c7d1e0; padding: 0.28em 0.3em; color: #222; font-size: 0.96em;}
        .main-panel { flex: 1 1 0; display: flex; flex-direction: column; background: #0d2c96; padding: 0; width: 75vw; min-width:0; }
        .event-header-panel {
            background: #bdbdbd;
            border: 3px solid #071d6e;
            border-radius: 0 0 16px 16px;
            padding: 0.8em 1.2em;
            margin: 0 0 1.2em 0;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 0.8em;
            width: 100%;
            box-sizing: border-box;
            min-height: 130px;
        }
        .event-header-box { background: #bdbdbd; border: 3px solid #071d6e; border-radius: 6px; padding: 0.5em 0.8em; width: 100%; max-width: 900px; min-width: 300px; font-family: Arial, sans-serif; margin-right: 0; box-sizing: border-box;}
        .event-row1, .event-row2 { display: flex; align-items: center; gap: 0.6em; margin-bottom: 0.25em;}
        .event-row2 {margin-bottom: 0;}
        .ev-arrow-btn { width: 1.8em; height: 1.8em; background: #fff; border: 2px solid #333; border-radius: 3px; padding: 0; margin: 0; font-size: 1.05em; font-weight: 700; display: flex; align-items: center; justify-content: center; cursor: pointer;}
        .ev-arrow-btn:active {background:#dfdfdf;}
        .ev-idx {width: 2.2em; text-align: center; font-size: 1.15em; border: 1.5px solid #333; border-radius: 2px; background:#fff;}
        .ev-title {flex:1; font-size: 1.02em; font-weight: 600; background:#fff; border:1.5px solid #333; border-radius:6px; padding:0.18em 0.6em; min-height: 2em;}
        .ev-refresh-btn { background: #fff; border:2px solid #071d6e; border-radius: 8px; width:2.6em; height:2.6em; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.6em; margin-left:0;}
        .ev-refresh-btn:active {background:#e5e5e5;}
        .ev-fromto, .ev-recall, .ev-heats { background:#fff; border:1px solid #222; border-radius:6px; font-size:0.95em; width:3.0em; text-align:center; padding:0.18em 0.28em;}
        .ev-fromto {width:3.2em;}
        .ev-label-bold {font-weight:700;}
        .ev-ctrl-btn {background:none;border:none;padding:0;margin:0;}
        .ev-row2-label {font-size: 0.9em; min-width: 2.4em; color:#0d2c96; font-weight:600;}
        .event-row2 { flex-wrap: wrap; }
        .main-content-row { display: flex; flex-direction: row; gap: 1.2em; height: 93%;}
        .adjudicator-list-panel { flex: 1.3; background: #eaf0ff; border-radius: 8px; margin-top: 0.2em; padding: 1em 1em 1em 1em; min-width: 220px; max-width: 340px; box-sizing: border-box; display: flex; flex-direction: column; align-items: stretch;}
        .adjudicator-list-panel h3 { font-size: 1.1em; color: #0d2c96; margin: 0 0 0.6em 0;}
        .adjudicator-list { list-style: none; padding:0; margin:0;}
        .adjudicator-list li { margin-bottom: 0.28em; padding: 0.13em 0.2em; background: #fff; border-radius: 4px; font-size: 0.97em; color: #282828; display: flex; align-items: center; justify-content: space-between;}
        .adjudicator-list li.disabled { color: #aaa; text-decoration: line-through; background: #f5f5f5;}
        .adjudicator-x-btn { background:#dc3232;color:#fff;border:none;border-radius:3px;padding:2px 8px;font-size:1em;cursor:pointer;margin-left:0.5em;}
        .adjudicator-x-btn:disabled {background:#ccc; color:#888; cursor:default;}
        .adjudicator-list-panel .empty {color:#888; margin-top:0.7em; font-size:0.98em;}
        .player-dance-row { display: flex; flex-direction: row; gap: 2.2em; align-items: flex-start;}
        .player-list-panel { flex: 1.1; background: #f5f5fa; border-radius: 8px; margin-top: 0.2em; padding: 1em 1em 1em 1em; min-width: 180px; max-width: 260px; box-sizing: border-box; display: flex; flex-direction: column; align-items: stretch;}
        .player-list-panel h3 { font-size:1.1em; color:#0d2c96; margin:0 0 0.6em 0;}
        .player-list-panel .player-controls-row {
            display: flex;
            gap: 0.6em;
            margin-bottom: 0.8em;
            align-items: center;
            flex-wrap: wrap;
        }
        .player-list {list-style:none; padding:0; margin:0;}
        .player-list li {margin-bottom:0.3em; padding:0.17em 0.3em; background:#fff; border-radius:4px; font-size:1.04em; display:flex; align-items:center; justify-content:space-between;}
        .add-player-btn, .show-entry-list-btn {
            background: #1c7aee;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 0.37em 1.15em;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            height: 2.3em;
            box-sizing: border-box;
            transition: background 0.13s;
            display:inline-flex; align-items:center; justify-content:center;
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
            min-width: 180px;
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
            width: 100%;
        }
        .dance-block .dance-item {
            font-size: 1.05em;
            color: #885e00;
            margin-bottom: 0.12em;
            line-height: 1.6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.7em;
            width: 100%;
        }
        .done-btn {
            margin-left:0.7em; padding:0.2em 1.1em; border-radius:5px; border:1.5px solid #e9b200; font-weight:bold;
            background:#fff; color:#b36b00; cursor:pointer; font-size:1em;
        }
        .done-btn.done {
            background: #ffe082;
            color: #b36b00;
        }
        @media (max-width: 860px) {
            .event-header-panel { flex-direction: column; align-items: stretch; gap: 0.8em; min-height: unset; }
            .ev-refresh-btn { align-self: center; }
        }
    </style>
</head>
<body>
<div class="live-root">
    <div class="live-frame">
        <!-- 이벤트 리스트 (좌측) -->
        <div class="side-events">
            <h2>이벤트 리스트</h2>
            <table class="event-list" id="event-table">
                <thead>
                    <tr>
                        <th style="width:3em;">번호</th>
                        <th style="min-width:6em;">이벤트명</th>
                        <th style="width:6em;">라운드</th>
                        <th style="width:3em;">팀수</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($events as $ix=>$evt): ?>
                    <tr data-idx="<?=$ix?>">
                        <td><?=h($evt['detail_no'])?></td>
                        <td><?=h($evt['desc'])?></td>
                        <td><?=h($evt['round'])?></td>
                        <td><?=h(calculateTeamCountByDetail($comp_id, $evt['detail_no']))?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- 메인 컨트롤 (가운데) -->
        <div class="main-panel">
            <!-- 상단: 이벤트 헤더 -->
            <div class="event-header-panel">
                <div class="event-header-box">
                    <div class="event-row1">
                        <button class="ev-arrow-btn" id="evtPrev" title="이전 이벤트"><span style="font-size:1.1em;">▲</span></button>
                        <input type="text" class="ev-idx" id="evtNo" readonly>
                        <input type="text" class="ev-title" id="evtName" readonly>
                        <button class="ev-arrow-btn" id="evtNext" title="다음 이벤트"><span style="font-size:1.1em;">▼</span></button>
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
                    </div>
                </div>
                <button class="ev-refresh-btn" id="evtRefresh" title="새로고침">↻</button>
            </div>
            <!-- 중단: 심사위원/선수+종목 -->
            <div class="main-content-row">
                <!-- 심사위원 리스트 (좌) -->
                <div class="adjudicator-list-panel" id="adjudicator-list-panel">
                    <h3>심사위원</h3>
                    <table style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width:2.1em;">#</th>
                                <th style="width:3.2em;">코드</th>
                                <th style="min-width:6em;">심사위원명</th>
                                <th style="width:2.2em;">국가</th>
                                <th style="width:2.2em;">X</th>
                            </tr>
                        </thead>
                        <tbody id="adjudicator-list">
                            <!-- 심사위원 행이 JS로 들어감 -->
                        </tbody>
                    </table>
                    <div class="empty" id="judge-empty" style="display:none;">심사위원이 없습니다</div>
                </div>
                <!-- 선수+종목 (우) -->
                <div class="player-dance-row">
                    <div class="player-list-panel" id="player-list-panel">
                        <h3>선수</h3>
                        <div class="player-controls-row">
                            <button class="add-player-btn" onclick="openPlayerModal()">선수 추가</button>
                            <button class="show-entry-list-btn" onclick="showEntryPlayers()">출전선수</button>
                        </div>
                        <ul class="player-list" id="player-list"></ul>
                    </div>
                </div>
                <!-- 진행종목(댄스) 완료 체크 UI -->
            <div class="dance-block" id="dance-block">
                <div class="dance-title">진행종목</div>
                <div class="dance-list" id="dance-list"></div>
            </div>
            </div>
        </div>
    </div>
    <!-- 선수 추가 모달 -->
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
    <!-- 출전선수 모달 -->
    <div class="modal-bg" id="entryPlayersModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); align-items:center; justify-content:center; z-index:100;">
        <div class="modal" style="background:#fff; border-radius:10px; padding:2em 2.2em; box-shadow:0 10px 40px #0002; min-width:340px;">
            <div class="modal-title">출전선수 리스트</div>
            <table style="margin-top:1em; width:100%; border-collapse:collapse;" id="entryPlayersTable">
                <thead>
                    <tr>
                        <th style="width:4em">등번호</th>
                        <th>남자선수</th>
                        <th>여자선수</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div style="margin-top:1em; text-align:right;">
                <button onclick="closeEntryPlayersModal()" style="font-size:1.08em; border-radius:4px; padding:0.29em 1.3em;">닫기</button>
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
let curIdx = 0;
let disabledJudgesByEvent = {};
let playersByEvent = <?=json_encode($players_by_event, JSON_UNESCAPED_UNICODE)?>;

// 진행종목(댄스) 완료 체크 추가
let danceDone = {};
function fetchDanceDone(eventNo) {
    if (typeof eventNo === "string") eventNo = eventNo.replace(/^\uFEFF/, "");
    fetch(`get_dance_done.php?comp_id=<?=urlencode($comp_id)?>&event_no=${encodeURIComponent(eventNo)}&${Date.now()}`)
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            }
            return r.json();
        })
        .then(obj => {
            danceDone = (typeof obj === 'object' && obj !== null) ? obj : {};
            renderDanceBlock(curIdx);
        })
        .catch(err => {
            console.error('dance_done fetch error:', err);
            danceDone = {};
            renderDanceBlock(curIdx);
        });
}
function toggleDanceDone(danceCode) {
    let eventNo = events[curIdx].no;
    if (typeof eventNo === "string") eventNo = eventNo.replace(/^\uFEFF/, "");
    const newDone = !(danceDone[danceCode] === true);
    
    // 즉시 UI 업데이트 (낙관적 업데이트)
    danceDone[danceCode] = newDone;
    renderDanceBlock(curIdx);
    
    fetch('dance_done_update.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `comp_id=<?=urlencode($comp_id)?>&event_no=${encodeURIComponent(eventNo)}&dance_code=${encodeURIComponent(danceCode)}&done=${newDone}`
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            // 실패 시 원래 상태로 되돌림
            danceDone[danceCode] = !newDone;
            renderDanceBlock(curIdx);
            alert("저장 실패: " + (res.error || ""));
        }
    })
    .catch(err => {
        // 네트워크 오류 시 원래 상태로 되돌림
        danceDone[danceCode] = !newDone;
        renderDanceBlock(curIdx);
        console.error('dance_done update error:', err);
        alert("저장 실패: 네트워크 오류");
    });
}

function renderDanceBlock(eventIdx) {
    const ev = events[eventIdx];
    const danceListDiv = document.getElementById('dance-list');
    let danceNames = [];
    if (ev.dances && ev.dances.length > 0) {
        danceNames = ev.dances.map(code => ({
            code,
            name: danceMapEn[code] || code,
            done: !!danceDone[code]
        }));
    }
    if (danceNames.length) {
        danceListDiv.innerHTML = danceNames.map((d, i) =>
            `<div class="dance-item">
                <span>${i+1}. ${d.name}</span>
                <button class="done-btn${d.done?' done':''}" onclick="toggleDanceDone('${d.code}')">
                    ${d.done?'✔ 완료':'완료'}
                </button>
            </div>`
        ).join('');
    } else {
        danceListDiv.innerHTML = `<div class="dance-item">-</div>`;
    }
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
            <td><button class="adjudicator-x-btn" onclick="toggleAdjudicator('${eventNo}','${j.code}')" title="이 이벤트에서 심사위원 제외" ${isDisabled ? 'disabled' : ''}>X</button></td>`;
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
function openPlayerModal() {
    document.getElementById('playerInput').value = '';
    document.getElementById('playerModalBg').style.display = 'flex';
    setTimeout(()=>{document.getElementById('playerInput').focus();}, 180);
}
function closePlayerModal() {
    document.getElementById('playerModalBg').style.display = 'none';
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
    let result = [];
    input.split(',').forEach(part=>{
        part = part.trim();
        if (/^\d+\s*~\s*\d+$/.test(part)) {
            let [start, end] = part.split('~').map(x=>parseInt(x.trim(),10));
            if (start && end && start <= end && end-start<100) {
                for (let i=start; i<=end; i++) result.push(String(i));
            }
        } else if (/^\d+$/.test(part)) {
            result.push(part);
        }
    });
    return result;
}
function submitPlayerModal(closeAfter) {
    let val = document.getElementById('playerInput').value.trim();
    if (!val) {
        if (closeAfter) closePlayerModal();
        return;
    }
    let eventNo = events[curIdx].no;
    if (typeof eventNo === "string") eventNo = eventNo.replace(/^\uFEFF/, "");
    if (!playersByEvent[eventNo]) playersByEvent[eventNo] = [];
    let bibs = parseBibsFromInput(val);
    let added = 0;
    bibs.forEach(bib=>{
        if (!playersByEvent[eventNo].includes(bib)) {
            playersByEvent[eventNo].push(bib);
            added++;
        }
    });
    if (added>0) {
        playersByEvent[eventNo] = playersByEvent[eventNo].slice().sort((a, b) => Number(a) - Number(b));
        renderPlayerList(eventNo);
        savePlayersToServer(eventNo);
    }
    if (closeAfter) {
        closePlayerModal();
    } else {
        document.getElementById('playerInput').value = '';
        setTimeout(()=>{document.getElementById('playerInput').focus();}, 100);
    }
}
function savePlayersToServer(eventNo) {
    fetch('save_players.php?comp_id=<?=urlencode($comp_id)?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({eventNo: eventNo, players: playersByEvent[eventNo]})
    })
    .then(res => res.json())
    .then(data => {
        if(!data.success) alert("선수 저장 실패: " + (data.error||""));
    });
}
function removePlayer(bib) {
    let eventNo = events[curIdx].no;
    if (typeof eventNo === "string") eventNo = eventNo.replace(/^\uFEFF/, "");
    let arr = playersByEvent[eventNo] || [];
    playersByEvent[eventNo] = arr.filter(x => x !== bib);
    renderPlayerList(eventNo);
    savePlayersToServer(eventNo);
}
function showEntryPlayers() {
    let eventNo = events[curIdx].no;
    if (typeof eventNo === "string") eventNo = eventNo.replace(/^\uFEFF/, "");
    let entryBibs = playersByEvent[eventNo] || [];
    let sorted = entryBibs.slice().sort((a,b)=>Number(a)-Number(b));
    let tbody = document.querySelector('#entryPlayersTable tbody');
    tbody.innerHTML = '';
    if (!sorted.length) {
        tbody.innerHTML = '<tr><td colspan="3" style="color:#aaa;">출전선수 없음</td></tr>';
    } else {
        sorted.forEach(bib => {
            let p = allPlayers[bib] || {};
            let male = p.male || '';
            let female = p.female || '';
            let tr = document.createElement('tr');
            tr.innerHTML = `<td>${bib}</td><td>${male}</td><td>${female}</td>`;
            tbody.appendChild(tr);
        });
    }
    document.getElementById('entryPlayersModalBg').style.display = 'flex';
}
function closeEntryPlayersModal() {
    document.getElementById('entryPlayersModalBg').style.display = 'none';
}
function updatePanel(idx) {
    if (idx < 0) idx = 0;
    if (idx > events.length-1) idx = events.length-1;
    curIdx = idx;
    let ev = events[curIdx];
    let eventNo = ev.no;
    if (typeof eventNo === "string") eventNo = eventNo.replace(/^\uFEFF/, "");
    document.getElementById('evtNo').value = ev.no || '';
    document.getElementById('evtName').value = ev.desc || '';
    document.getElementById('evtRecall').value = ev.recall || '';
    document.getElementById('evtHeats').value = ev.heats || '';
    document.getElementById('evtFrom').value = '';
    document.getElementById('evtTo').value = '';
    document.querySelectorAll('#event-table tr').forEach(tr=>tr.classList.remove('selected'));
    let tr = document.querySelector('#event-table tr[data-idx="'+curIdx+'"]');
    if (tr) tr.classList.add('selected');
    renderAdjudicatorList(ev.panel, ev.no);
    renderPlayerList(eventNo);
    
    // 완료 상태를 먼저 초기화하고 서버에서 로드
    danceDone = {};
    fetchDanceDone(eventNo);
}
document.addEventListener("DOMContentLoaded", function(){
    document.getElementById('evtPrev').onclick = ()=>updatePanel(curIdx-1);
    document.getElementById('evtNext').onclick = ()=>updatePanel(curIdx+1);
    document.getElementById('evtRefresh').onclick = ()=>updatePanel(curIdx);
    document.getElementById('evtRangeMove').onclick = ()=>{ alert('범위 이동 기능(From~To): 구현 필요'); };
    document.querySelectorAll('#event-table tr[data-idx]').forEach(function(row) {
        row.addEventListener('click', function(){
            updatePanel(parseInt(this.dataset.idx));
        });
    });
    if(events.length) updatePanel(0);
});
window.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closePlayerModal();
});
</script>
</body>
</html>