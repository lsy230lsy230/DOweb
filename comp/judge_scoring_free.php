<?php
session_start();
error_reporting(E_ALL); ini_set('display_errors', 1);

if (!isset($_SESSION['judge_id']) || !isset($_SESSION['judge_name'])) {
    // 이벤트 파라미터가 있으면 로그인 후 해당 이벤트로 리다이렉트
    $event_param = isset($_GET['event']) ? '?event=' . urlencode($_GET['event']) : '';
    header("Location: judge_login.php" . $event_param);
    exit;
}

$data_dir = __DIR__ . '/data';
$event_file = $data_dir . '/events.txt';

// 종목 목록 불러오기
$events = [];
if (file_exists($event_file)) {
    $lines = file($event_file);
    foreach ($lines as $line) {
        $cols = explode(',', trim($line));
        if (count($cols) >= 2) {
            $events[] = [
                'code' => $cols[0],
                'name' => $cols[1]
            ];
        }
    }
}

// 선택된 종목 (GET 파라미터 우선, 없으면 첫 번째 이벤트)
$selected_event = isset($_GET['event']) ? $_GET['event'] : (isset($events[0]) ? $events[0]['code'] : '');
$event_name = '';

// 이벤트가 존재하는지 확인
$event_exists = false;
foreach ($events as $ev) {
    if ($ev['code'] === $selected_event) {
        $event_name = $ev['name'];
        $event_exists = true;
        break;
    }
}

// 이벤트가 존재하지 않으면 첫 번째 이벤트로 설정
if (!$event_exists && !empty($events)) {
    $selected_event = $events[0]['code'];
    $event_name = $events[0]['name'];
}

// 해당 종목 선수 목록 불러오기
$event_players_file = $data_dir . '/event_players/' . $selected_event . '_players.txt';
$players = [];
if ($selected_event && file_exists($event_players_file)) {
    $lines = file($event_players_file);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 2) {
            $players[] = [
                'number' => $cols[0],
                'name' => $cols[1],
                'partner' => isset($cols[2]) ? $cols[2] : ''
            ];
        }
    }
}

// 점수 저장
$score_dir = $data_dir . '/scores';
if (!is_dir($score_dir)) mkdir($score_dir, 0777, true);

$score_file = $score_dir . "/score_{$selected_event}_J{$_SESSION['judge_id']}.txt";

$scoring_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_score']) || isset($_POST['finish_and_go_dashboard']))) {
    $scores = [];
    foreach ($players as $pl) {
        $num = $pl['number'];
        // 불참 처리: 값 강제
        if (isset($_POST['absent_'.$num]) && $_POST['absent_'.$num] === '1') {
            $score1 = 5;
            $score2 = 5;
            $score3 = 1;
        } else {
            $score1 = isset($_POST["score1_$num"]) ? trim($_POST["score1_$num"]) : '';
            $score2 = isset($_POST["score2_$num"]) ? trim($_POST["score2_$num"]) : '';
            $score3 = isset($_POST["score3_$num"]) ? trim($_POST["score3_$num"]) : '';
        }
        $scores[] = "$num,$score1,$score2,$score3";
    }
    $save_result = file_put_contents($score_file, implode("\n", $scores));
    if ($save_result !== false) {
        $scoring_msg = "<div class='score-msg success'>채점이 저장되었습니다!</div>";
    } else {
        $scoring_msg = "<div class='score-msg error'>점수 저장에 실패했습니다. 서버 권한이나 경로를 확인하세요.</div>";
    }

    if (isset($_POST['finish_and_go_dashboard'])) {
        header("Location: judge_dashboard.php");
        exit;
    }
}

// 기존 점수 불러오기(있으면)
$saved_scores = [];
if (file_exists($score_file)) {
    $lines = file($score_file);
    foreach ($lines as $line) {
        $cols = explode(',', trim($line));
        if (count($cols) === 4) {
            $saved_scores[$cols[0]] = [
                'score1' => $cols[1],
                'score2' => $cols[2],
                'score3' => $cols[3],
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>채점 - 심사위원 | danceoffice.net</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .score-section {background:#23262b; border-radius:14px; padding:2em 2em 2.5em 2em; max-width:750px; margin:3em auto;}
        .score-title {font-size:1.2em; font-weight:700; color:#03C75A; margin-bottom:1.1em;}
        .event-select-form {margin-bottom:2em;}
        .event-select-form select {padding:0.5em 1em; border-radius:8px; background:#181B20; color:#03C75A; border:1px solid #31343a;}
        .score-table {width:100%; background:#222; border-radius:8px; border-collapse:collapse;}
        .score-table th,.score-table td {padding:0.7em 0.5em; border-bottom:1px solid #31343a;}
        .score-table th {background:#181B20; color:#03C75A;}
        .score-table td {color:#F5F7FA;}
        .score-table tr:last-child td {border-bottom:none;}
        .score-input {width:4em; padding:0.5em; border-radius:6px; border:1px solid #31343a; background:#1E2126; color:#fff; text-align:center;}
        .score-input[readonly] {background:#333;color:#aaf;cursor:pointer;}
        .score-msg {margin:1em 0; padding:0.8em 1em; border-radius:8px;}
        .score-msg.success {background:#232; color:#03C75A;}
        .score-msg.error {background:#311; color:#ff5e5e;}
        .score-btn, .finish-btn {background:linear-gradient(90deg,#03C75A 70%,#00BFAE 100%); color:#222; border:none; border-radius:20px; padding:0.7em 2em; font-weight:700; margin-right:0.8em;}
        .score-btn:hover, .finish-btn:hover {background:linear-gradient(90deg,#00BFAE 60%,#03C75A 100%); color:#fff;}
        .finish-btn {background:#0057b7; color:#fff;}
        .finish-btn:hover {background:#03C75A; color:#222;}
        .absent-btn {
            margin-left:0.5em;
            background:#f55; color:#fff; font-weight:700; border:none; border-radius:10px;
            padding:0.25em 0.95em; font-size:0.98em; cursor:pointer; transition:background 0.14s;
        }
        .absent-btn.absent-active, .absent-btn:focus { background:#a00; color:#fff;}
        .score-row-absent td {background:#fff5f5 !important; color:#a00;}
        .score-row-absent input {background:#fff4f4 !important; color:#a00;}
        /* 모달 등 기존 스타일 그대로 유지 */
        .modal-bg {
            display:none; position:fixed; left:0; top:0; width:100vw; height:100vh;
            background:rgba(10,20,30,0.85); z-index:9999;
        }
        .modal-wrap {
            background:#181C25; border-radius: 20px;
            box-sizing:border-box;
            width:96vw; max-width:650px; min-width:0;
            margin:4vh auto 0 auto; padding:2em 0.5em 2em 0.5em; position:relative;
            overflow-x: auto;
        }
        .modal-label {
            font-weight:900; color:#03C75A; font-size:1.6em; text-align:center; margin-bottom:1.3em;
        }
        .modal-btns {
            display:flex; flex-direction:column; gap:1.1em; width:100%; align-items:center;
            margin-bottom:2em;
        }
        .modal-btn-row {
            display:flex; flex-wrap:nowrap; gap:0.65em; justify-content:center; width:100%;
        }
        .modal-btn {
            background:#31343a; color:#fff; border:none; border-radius:10px;
            padding:0.8em 0.9em; min-width:2.1em;
            font-weight:900; font-size:1.35em;
            cursor:pointer; transition:background 0.15s;
            line-height:1.6;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .modal-btn.active, .modal-btn:focus {background:#03C75A; color:#222;}
        .modal-btn:hover {background:#00BFAE; color:#fff;}
        .modal-close {
            position:absolute; top:0.7em; right:1.5em; background:none; border:none;
            color:#ff5e5e; font-size:2.3em; cursor:pointer; font-weight:900;
        }
        .modal-btn.difficulty-btn {
            border-radius:50%;
            width:2.7em; height:2.7em; min-width:2.7em; min-height:2.7em;
            padding:0; font-size:1.25em; display:flex; align-items:center; justify-content:center;
            background:#222b37;
            margin-bottom:0.08em;
        }
        .modal-btn.difficulty-btn.active, .modal-btn.difficulty-btn:focus {background:#03C75A; color:#181c25;}
        .modal-btn.difficulty-btn:hover {background:#00BFAE; color:#fff;}
    </style>
</head>
<body>
    <main>
        <section class="score-section">
            <div class="score-title">심사 채점 패널</div>
            <div style="margin-bottom:1em;">
                <b><?= htmlspecialchars($_SESSION['judge_name']) ?></b> 심사위원님<br>
            </div>
            <form class="event-select-form" method="get">
                <label for="event_select" style="color:#03C75A; font-weight:700;">종목 선택:</label>
                <select name="event" id="event_select" onchange="this.form.submit()">
                    <?php foreach($events as $ev): ?>
                        <option value="<?= htmlspecialchars($ev['code']) ?>" <?= $selected_event === $ev['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ev['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <?php if (isset($_GET['event'])): ?>
            <div style="background: #e8f4fd; padding: 10px; border-radius: 6px; margin: 10px 0; border-left: 4px solid #2196F3;">
                <strong>🎭 프리스타일 채점 모드</strong><br>
                <small>이 종목은 프리스타일 심사 시스템으로 채점됩니다.</small>
            </div>
            <?php endif; ?>
            <div style="font-size:1.08em; color:#0ef; margin-bottom:0.7em;">
                [<?= htmlspecialchars($event_name) ?>]
            </div>
            <?php if ($scoring_msg) echo $scoring_msg; ?>
            <?php if ($players): ?>
            <form method="post" autocomplete="off" onsubmit="return lockButtonsOnSubmit(this)">
                <table class="score-table">
                    <thead>
                        <tr>
                            <th style="width:65px;">번호</th>
                            <th>이름</th>
                            <th>파트너</th>
                            <th style="width:100px;">기술</th>
                            <th style="width:100px;">안무</th>
                            <th style="width:100px;">난이도</th>
                            <th>불참</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($players as $pl): 
                            $num = $pl['number'];
                            $score1 = isset($saved_scores[$num]['score1']) ? $saved_scores[$num]['score1'] : '';
                            $score2 = isset($saved_scores[$num]['score2']) ? $saved_scores[$num]['score2'] : '';
                            $score3 = isset($saved_scores[$num]['score3']) ? $saved_scores[$num]['score3'] : '';
                            $is_absent = ($score1 == 5 && $score2 == 5 && $score3 == 1);
                        ?>
                        <tr id="score_row_<?= htmlspecialchars($num) ?>"<?= $is_absent ? ' class="score-row-absent"' : '' ?>>
                            <td><?= htmlspecialchars($pl['number']) ?></td>
                            <td><?= htmlspecialchars($pl['name']) ?></td>
                            <td><?= htmlspecialchars($pl['partner']) ?></td>
                            <td>
                                <input type="text" class="score-input" 
                                    name="score1_<?= htmlspecialchars($num) ?>" 
                                    id="score1_<?= htmlspecialchars($num) ?>"
                                    value="<?= htmlspecialchars($score1) ?>"
                                    readonly onclick="if(!isAbsent('<?= $num ?>'))openModal('기술', 'score1_<?= htmlspecialchars($num) ?>', 'tech')">
                            </td>
                            <td>
                                <input type="text" class="score-input" 
                                    name="score2_<?= htmlspecialchars($num) ?>" 
                                    id="score2_<?= htmlspecialchars($num) ?>"
                                    value="<?= htmlspecialchars($score2) ?>"
                                    readonly onclick="if(!isAbsent('<?= $num ?>'))openModal('안무', 'score2_<?= htmlspecialchars($num) ?>', 'choreo')">
                            </td>
                            <td>
                                <input type="text" class="score-input" 
                                    name="score3_<?= htmlspecialchars($num) ?>" 
                                    id="score3_<?= htmlspecialchars($num) ?>"
                                    value="<?= htmlspecialchars($score3) ?>"
                                    readonly onclick="if(!isAbsent('<?= $num ?>'))openModal('난이도', 'score3_<?= htmlspecialchars($num) ?>', 'difficult')">
                            </td>
                            <td>
                                <input type="hidden" name="absent_<?= htmlspecialchars($num) ?>" value="<?= $is_absent ? '1' : '0' ?>">
                                <button type="button" class="absent-btn<?= $is_absent ? ' absent-active' : '' ?>" id="absent_btn_<?= htmlspecialchars($num) ?>" onclick="toggleAbsent('<?= htmlspecialchars($num) ?>')"><?= $is_absent ? '불참 취소' : '불참' ?></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:1.4em;">
                    <button type="submit" name="save_score" class="score-btn">점수 저장</button>
                    <button type="submit" name="finish_and_go_dashboard" class="finish-btn">채점 완료(대시보드로)</button>
                </div>
            </form>
            <?php else: ?>
                <div class="score-msg error">선수 명단이 없습니다. 관리자에게 문의하세요.</div>
            <?php endif; ?>
        </section>
    </main>
    <!-- Modal -->
    <div id="modal-bg" class="modal-bg">
        <div class="modal-wrap">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div class="modal-label" id="modal-label"></div>
            <div class="modal-btns" id="modal-btns"></div>
        </div>
    </div>
    <script>
        let currentInputId = "";
        function openModal(label, inputId, mode) {
            currentInputId = inputId;
            document.getElementById('modal-label').textContent = label + " 점수 선택";
            let btnsHtml = '';
            if (mode === 'tech' || mode === 'choreo') {
                let min = 5.1, max = 10, step = 0.1;
                for (let rowStart = min; rowStart < max; rowStart += 1.0) {
                    let rowEnd = Math.min(rowStart + 0.9, max);
                    btnsHtml += "<div class='modal-btn-row'>";
                    for (let v = rowStart; v <= rowEnd + 0.0001; v += step) {
                        let val = parseFloat(v.toFixed(1));
                        btnsHtml += `<button type='button' class='modal-btn' data-score='${val}'>${val}</button>`;
                    }
                    btnsHtml += "</div>";
                }
            } else if (mode === 'difficult') {
                let min = 1.25, max = 2, step = 0.05;
                let col = 0, perRow = 7;
                for (let rowStart = min; rowStart <= max; rowStart += step * perRow) {
                    btnsHtml += "<div class='modal-btn-row'>";
                    for (let v = rowStart; v < rowStart + step * perRow && v <= max + 0.0001; v += step) {
                        let val = parseFloat(v.toFixed(2));
                        btnsHtml += `<button type='button' class='modal-btn difficulty-btn' data-score='${val}'>${val}</button>`;
                    }
                    btnsHtml += "</div>";
                }
            }
            document.getElementById('modal-btns').innerHTML = btnsHtml;
            document.getElementById('modal-bg').style.display = 'block';
        }
        function closeModal() {
            document.getElementById('modal-bg').style.display = 'none';
            currentInputId = "";
        }
        document.getElementById('modal-btns').addEventListener('click', function(e){
            if (e.target.classList.contains('modal-btn')) {
                let score = e.target.getAttribute('data-score');
                if (currentInputId) {
                    document.getElementById(currentInputId).value = score;
                }
                closeModal();
            }
        });
        document.addEventListener('keydown', function(e){
            if (e.key === "Escape") closeModal();
        });
        document.getElementById('modal-bg').addEventListener('click', function(e){
            if (e.target === this) closeModal();
        });

        // 불참 버튼 기능
        function toggleAbsent(num) {
            const btn = document.getElementById('absent_btn_' + num);
            const isNowAbsent = btn.classList.toggle('absent-active');
            const s1 = document.getElementsByName('score1_' + num)[0];
            const s2 = document.getElementsByName('score2_' + num)[0];
            const s3 = document.getElementsByName('score3_' + num)[0];
            const row = document.getElementById('score_row_' + num);
            if (isNowAbsent) {
                btn.innerText = '불참 취소';
                s1.value = 5; s2.value = 5; s3.value = 1;
                s1.readOnly = true; s2.readOnly = true; s3.readOnly = true;
                row.classList.add('score-row-absent');
                document.getElementsByName('absent_' + num)[0].value = '1';
            } else {
                btn.innerText = '불참';
                s1.readOnly = false; s2.readOnly = false; s3.readOnly = false;
                row.classList.remove('score-row-absent');
                document.getElementsByName('absent_' + num)[0].value = '0';
            }
        }
        function isAbsent(num) {
            return document.getElementById('absent_btn_' + num).classList.contains('absent-active');
        }
        function lockButtonsOnSubmit(f) {
            f.querySelectorAll("button[type=submit]").forEach(btn => btn.disabled = true);
            return true;
        }
    </script>
</body>
</html>