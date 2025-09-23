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
$counts = [
    'competitor'   => count_lines("$data_dir/players.txt"),
    'events'       => count_lines("$data_dir/events.txt"),
    'adjudicator'  => count_lines("$data_dir/adjudicators.txt"),
    'timetable'    => count_lines("$data_dir/timetable.txt"),
];
function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= h($info['title']) ?> 데이터 관리 | danceoffice.net COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#1a1a1a; font-family:sans-serif; margin:0;}
        .mainbox { max-width:900px; margin:3vh auto 0 auto; background:#222; border-radius:18px; box-shadow:0 6px 32px #00339922; padding:2.7em 2em 2.2em 2em;}
        h1 { color:#fff; font-size:1.25em; margin-bottom:1.4em; text-align:center;}
        .compinfo {text-align:center; color:#bbb; margin-bottom:2.2em;}
        .db-grid { display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:1.5em; margin-bottom:2.5em;}
        .db-block {
            background:#181818; border-radius:10px; padding:1.3em 0.8em;
            display:flex; flex-direction:column; align-items:center;
            box-shadow:0 2px 12px #0004; cursor:pointer; transition:background 0.15s;
            min-width:120px; min-height:110px;
        }
        .db-block:hover { background:#2c2c2c;}
        .db-block .num { font-size:2em; font-weight:700; color:#03C75A; margin-bottom:0.2em;}
        .db-block .lbl { color:#fff; font-size:1.05em; font-weight:600; margin-bottom:0.22em;}
        .db-block .sub { color:#bbb; font-size:0.97em;}
        .goto-dash {display:inline-block; margin-bottom:1.4em; color:#bbb;}
        .goto-dash:hover {color:#03C75A;}
        footer {margin-top:2.2em;text-align:center;font-size:0.92em;color:#aaa; padding: 1em 0;}
        @media (max-width:1050px) {
            .mainbox {max-width:99vw;}
            .db-grid {grid-template-columns: 1fr 1fr;}
        }
        @media (max-width:700px) {
            .mainbox {padding:1.3em 0.2em;}
            .db-grid {grid-template-columns: 1fr;}
        }
    </style>
</head>
<body>
<div class="mainbox">
    <a href="dashboard.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-dash">&lt; 대시보드로</a>
    <h1><?= h($info['title']) ?> 데이터 관리</h1>
    <div class="compinfo">
        <b>일자:</b> <?= h($info['date']) ?> <b>장소:</b> <?= h($info['place']) ?> <b>주최/주관:</b> <?= h($info['host']) ?>
    </div>
    <div class="db-grid">
        <a class="db-block" href="manage_competitors.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num"><?= $counts['competitor'] ?></div>
            <div class="lbl">Competitors</div>
            <div class="sub">선수 명단 관리</div>
        </a>
        <a class="db-block" href="manage_events.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num"><?= $counts['events'] ?></div>
            <div class="lbl">Events</div>
            <div class="sub">종목/이벤트 관리</div>
        </a>
        <a class="db-block" href="manage_adjudicators.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num"><?= $counts['adjudicator'] ?></div>
            <div class="lbl">Adjudicators</div>
            <div class="sub">심사위원 관리</div>
        </a>
        <a class="db-block" href="manage_timetable.php?comp_id=<?= urlencode($comp_id) ?>">
            <div class="num"><?= $counts['timetable'] ?></div>
            <div class="lbl">Timetable</div>
            <div class="sub">시간표 관리</div>
        </a>
    </div>
</div>
<footer>
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>
</body>
</html>