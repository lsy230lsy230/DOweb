<?php
$comp_id = $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
if (!preg_match('/^\d{8}-\d+$/', $comp_id) || !is_file($info_file)) {
    echo "<h1>잘못된 대회 ID 또는 대회 정보가 없습니다.</h1>";
    exit;
}
$info = json_decode(file_get_contents($info_file), true);

function count_lines($file) {
    if (!is_file($file)) return 0;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return count($lines);
}

// 기존 방식(선수/심사위원/시간표)은 txt 줄 수로 셈
$counts = [
    'competitor'   => count_lines("$data_dir/players.txt"),
    // events.txt는 라인수 그대로 사용 (필요시 RunOrder 기반으로 변경)
    'events_txt'   => count_lines("$data_dir/events.txt"),
    'adjudicator'  => count_lines("$data_dir/adjudicators.txt"),
    'timetable'    => count_lines("$data_dir/timetable.txt"),
];

// ---- RunOrder 기반 '경기 이벤트' 갯수 구하기 ----
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$event_count = 0;
if (is_file($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        // 댄스 1~5 중 하나라도 값이 있으면 경기 이벤트로 간주
        $has_dance = false;
        for ($i=6; $i<=10; $i++) {
            if (isset($cols[$i]) && $cols[$i] !== '') {
                $has_dance = true;
                break;
            }
        }
        if ($has_dance) $event_count++;
    }
}
function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= h($info['title']) ?> 대시보드 | danceoffice.net COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#1a1a1a; font-family:sans-serif; margin:0;}
        .dash-main { max-width:850px; margin:3vh auto 0 auto; background:#222; border-radius:18px; box-shadow:0 6px 32px #00339922; padding:2.7em 2em 2.2em 2em;}
        h1 { color:#fff; font-size:1.35em; margin-bottom:1.4em; text-align:center;}
        .dash-grid { display:grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr; gap:1.7em; margin-bottom:2.5em;}
        .dash-block {
            background:#181818; border-radius:10px; padding:1.2em 0.8em;
            display:flex; flex-direction:column; align-items:center;
            box-shadow:0 2px 12px #0004; cursor:pointer; transition:background 0.15s;
            min-width:120px; min-height:112px;
        }
        .dash-block:hover { background:#2c2c2c;}
        .dash-block .num { font-size:2.1em; font-weight:700; color:#03C75A; margin-bottom:0.25em;}
        .dash-block .lbl { color:#fff; font-size:1.08em; font-weight:600; margin-bottom:0.25em;}
        .dash-block .sub { color:#bbb; font-size:0.96em;}
        .dash-btns { margin-top:2.5em; display:flex; gap:1.1em; justify-content:center;}
        .dash-btn {
            display:inline-block; background:#03C75A; color:#fff; border-radius:13px;
            padding:0.7em 2.5em; font-weight:700; font-size:1.12em; text-decoration:none;
            box-shadow:0 2px 8px #03c75a22; transition:background 0.13s;
        }
        .dash-btn:hover { background:#00BFAE;}
        .compinfo {text-align:center; color:#bbb; margin-bottom:2.2em;}
        .dash-icons {margin-top:2em; text-align:center;}
        .dash-icons img {width:36px; margin:0 0.8em; opacity:0.8; transition:opacity 0.2s;}
        .dash-icons img:hover {opacity:1;}
        a { text-decoration:none;}
        .goto-list {display:inline-block; margin-bottom:1.2em; color:#bbb;}
        .goto-list:hover {color:#03C75A;}
        footer {margin-top:2.2em;text-align:center;font-size:0.92em;color:#aaa; padding: 1em 0;}
        @media (max-width:1050px) {
            .dash-main {max-width:99vw;}
            .dash-grid {grid-template-columns: 1fr 1fr 1fr;}
        }
        @media (max-width:700px) {
            .dash-main {padding:1.3em 0.2em;}
            .dash-grid {grid-template-columns: 1fr 1fr;}
        }
        @media (max-width:430px) {
            .dash-grid {grid-template-columns: 1fr;}
        }
    </style>
</head>
<body>
<div class="dash-main">
    <a href="index.php" class="goto-list">&lt; 대회 목록으로</a>
    <h1><?= h($info['title']) ?> <span style="font-size:0.8em; color:#03C75A;">대시보드</span></h1>
    <div class="compinfo">
        <b>일자:</b> <?= h($info['date']) ?> <b>장소:</b> <?= h($info['place']) ?> <b>주최/주관:</b> <?= h($info['host']) ?>
    </div>
    <div class="dash-grid">
        <a class="dash-block" href="manage_competitors.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num"><?= $counts['competitor'] ?></div>
            <div class="lbl">Competitors</div>
            <div class="sub">선수 명단</div>
        </a>
        <a class="dash-block" href="manage_events.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num"><?= $event_count ?></div>
            <div class="lbl">Events</div>
            <div class="sub">종목/이벤트</div>
        </a>
        <a class="dash-block" href="manage_adjudicators.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num"><?= $counts['adjudicator'] ?></div>
            <div class="lbl">Adjudicators</div>
            <div class="sub">심사위원</div>
        </a>
        <a class="dash-block" href="manage_timetable.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num"><?= $counts['timetable'] ?></div>
            <div class="lbl">Timetable</div>
            <div class="sub">시간표</div>
        </a>
        <a class="dash-block" href="live_panel.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num">LIVE</div>
            <div class="lbl">Live Panel</div>
            <div class="sub">진행/심사 컨트롤</div>
        </a>
    </div>
    <div class="dash-btns">
        <a class="dash-btn" href="data_manage.php?comp_id=<?= urlencode($comp_id) ?>">데이터 관리</a>
        <a class="dash-btn" href="scoring_login.php?comp_id=<?= urlencode($comp_id) ?>">심사/채점</a>
        <a class="dash-btn" href="#">결과 집계</a>
        <a class="dash-btn" href="#">상장 인쇄</a>
    </div>
    <div class="dash-icons" style="margin-top:2.7em;">
        <a href="index.php" title="홈"><img src="assets/home_icon.svg" alt="Home"></a>
        <a href="data_manage.php?comp_id=<?= urlencode($comp_id) ?>" title="DB 관리"><img src="assets/db_icon.svg" alt="DB"></a>
        <a href="#" title="설정"><img src="assets/settings_icon.svg" alt="설정"></a>
        <a href="#" title="리셋"><img src="assets/reset_icon.svg" alt="초기화"></a>
    </div>
</div>
<footer>
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>
</body>
</html>