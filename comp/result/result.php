<?php
session_start();

$data_dir = '/volume1/web/comp/data'; // 환경에 맞게 수정
$event_file = $data_dir . '/events.txt';
$adjudicator_file = $data_dir . '/Adjudicator.txt';

// 대회명/날짜 불러오기
$compinfo_file = $data_dir . '/competition_info.txt';
$comp_title = "";
$comp_date = "";
if (file_exists($compinfo_file)) {
    $lines = file($compinfo_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $comp_title = isset($lines[0]) ? trim($lines[0]) : "";
    $comp_date = isset($lines[1]) ? trim($lines[1]) : "";
}

function load_events($event_file) {
    $events = [];
    if (file_exists($event_file)) {
        $lines = file($event_file);
        foreach ($lines as $line) {
            $cols = explode(',', trim($line), 2);
            if (count($cols) >= 2) {
                $events[] = [
                    'code' => trim($cols[0]),
                    'name' => trim($cols[1])
                ];
            }
        }
    }
    return $events;
}
function load_adjudicators($adjudicator_file) {
    $adjs = [];
    if (file_exists($adjudicator_file)) {
        $lines = file($adjudicator_file);
        foreach ($lines as $line) {
            $cols = explode(',', trim($line));
            if (count($cols) >= 2) {
                $adjs[trim($cols[0])] = trim($cols[1]);
            }
        }
    }
    return $adjs;
}
function load_players($event_code, $data_dir) {
    $players = [];
    $player_file = $data_dir . '/event_players/' . $event_code . '_players.txt';
    if (file_exists($player_file)) {
        $lines = file($player_file);
        foreach ($lines as $line) {
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) >= 2) {
                $players[$cols[0]] = [
                    'number' => $cols[0],
                    'name' => $cols[1],
                    'partner' => isset($cols[2]) ? $cols[2] : '',
                ];
            }
        }
    }
    return $players;
}
function load_scores($event_code, $adjudicators, $data_dir) {
    $scores = [];
    foreach ($adjudicators as $adj_id => $adj_name) {
        $score_file = $data_dir . "/scores/score_{$event_code}_J{$adj_id}.txt";
        if (file_exists($score_file)) {
            $lines = file($score_file);
            foreach ($lines as $line) {
                $cols = explode(',', trim($line));
                if (count($cols) == 4) {
                    list($num, $tech, $choreo, $diff) = $cols;
                    if (!isset($scores[$num])) $scores[$num] = [];
                    $scores[$num][$adj_id] = [
                        'tech' => floatval($tech),
                        'choreo' => floatval($choreo),
                        'diff' => floatval($diff)
                    ];
                }
            }
        }
    }
    return $scores;
}

// 최고/최저 제외 평균
function calc_final_score($score_arr) {
    if (count($score_arr) < 3) return 0;
    sort($score_arr, SORT_NUMERIC);
    if (count($score_arr) > 3) {
        $score_arr = array_slice($score_arr, 1, count($score_arr) - 2); // Min/Max 제외
    }
    return array_sum($score_arr) / count($score_arr);
}

// 총점: (기술+안무) * 난이도
function calc_total_score($final_tech, $final_choreo, $final_diff) {
    return ($final_tech + $final_choreo) * $final_diff;
}

// 데이터 로딩
$events = load_events($event_file);
$adjudicators = load_adjudicators($adjudicator_file);

$selected_event = '';
$event_name = '';
if (isset($_GET['event'])) {
    foreach ($events as $ev) {
        if ($ev['code'] === $_GET['event']) {
            $selected_event = $ev['code'];
            $event_name = $ev['name'];
            break;
        }
    }
}

$players = $selected_event ? load_players($selected_event, $data_dir) : [];
$scores = ($selected_event && $adjudicators) ? load_scores($selected_event, $adjudicators, $data_dir) : [];

// 선수별 점수 집계
$player_results = [];
if ($players && $scores) {
    foreach ($players as $num => $player) {
        $tech_scores = []; $choreo_scores = []; $diff_scores = [];
        foreach ($adjudicators as $adj_id => $adj_name) {
            $t = isset($scores[$num][$adj_id]) ? $scores[$num][$adj_id]['tech'] : null;
            $c = isset($scores[$num][$adj_id]) ? $scores[$num][$adj_id]['choreo'] : null;
            $d = isset($scores[$num][$adj_id]) ? $scores[$num][$adj_id]['diff'] : null;
            if ($t !== null) $tech_scores[$adj_id] = $t;
            if ($c !== null) $choreo_scores[$adj_id] = $c;
            if ($d !== null) $diff_scores[$adj_id] = $d;
        }
        $final_tech = calc_final_score(array_values($tech_scores));
        $final_choreo = calc_final_score(array_values($choreo_scores));
        $final_diff = calc_final_score(array_values($diff_scores));
        $total_score = calc_total_score($final_tech, $final_choreo, $final_diff);

        $player_results[$num] = [
            'number' => $player['number'],
            'name' => $player['name'],
            'partner' => $player['partner'],
            'tech_scores' => $tech_scores,
            'choreo_scores' => $choreo_scores,
            'diff_scores' => $diff_scores,
            'final_tech' => $final_tech,
            'final_choreo' => $final_choreo,
            'final_diff' => $final_diff,
            'total_score' => $total_score,
        ];
    }
    // 총점 순서로 정렬 & 순위 부여
    uasort($player_results, function($a, $b) {
        return $b['total_score'] <=> $a['total_score'];
    });
    $rank = 1;
    foreach ($player_results as &$result) {
        $result['rank'] = $rank++;
    }
    unset($result);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>채점 결과 | danceoffice.net</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#191b22; color:#222; }
        .result-section {background:#fff; border-radius:14px; padding:2em 2em 2.5em 2em; max-width:1200px; margin:3em auto; box-shadow:0 4px 30px #0002;}
        .result-title {font-size:1.7em; font-weight:700; color:#003399; margin-bottom:2em; text-align:center;}
        .comp-title {font-size:1.18em; color:#03C75A; font-weight:700; margin-bottom:0.25em; text-align:center;}
        .comp-date {font-size:1.03em; color:#0066b3; margin-bottom:1.2em; text-align:center;}
        .score-table {width:100%; margin-bottom:2.1em; border-collapse:collapse; font-size:1.05em;}
        .score-table th, .score-table td {padding:0.6em 0.4em; border:1px solid #c0c0c0; text-align:center;}
        .score-table th {background:#e3edfa; color:#1b4a90;}
        .score-table.ts th {background:#e3edfa;}
        .score-table.cp th {background:#f8ebff;}
        .score-table.dl th {background:#eafae4;}
        .score-table.ts .final, .score-table.cp .final, .score-table.dl .final { font-weight:700;}
        .score-table .min {background:#f9e5e5; color:#c02929;}
        .score-table .max {background:#e2f7e2; color:#178a1a;}
        .score-table .final {background:#f2f2fa;}
        .score-table .row-num {font-weight:700;}
        .score-table .avg {background:#fafafa; font-weight:700;}
        .summary-table {width:100%; margin-top:2.5em; border-collapse:collapse; font-size:1.13em;}
        .summary-table th, .summary-table td {padding:0.5em 0.3em; border:1px solid #bdbdbd; text-align:center;}
        .summary-table th {background:#e3edfa; color:#003399;}
        .summary-table .rank1 {background:#ffe966;}
        .summary-table .rank2 {background:#cde4ff;}
        .summary-table .rank3 {background:#fff0e2;}
        .summary-table tr:last-child td {border-bottom:1.7px solid #aab;}
        .print-btn {
            display:inline-block; padding:0.5em 1.4em; font-size:1.1em; font-weight:700;
            background:#003399; color:#fff; border:none; border-radius:28px; margin:0 0 2em 0; cursor:pointer;
            transition:background 0.18s;
        }
        .print-btn:hover { background:#0057b7; }
        @media (max-width:900px) {
            .result-section {padding:1em 0.2em;}
            .score-table th, .score-table td, .summary-table th, .summary-table td {font-size:0.98em;}
        }
        @media (max-width:600px) {
            .result-title {font-size:1.1em;}
            .score-table th, .score-table td, .summary-table th, .summary-table td {font-size:0.91em;}
        }
        /* 인쇄 스타일 */
        @media print {
            body { background:#fff !important; color:#000 !important; margin:0; }
            .result-section { box-shadow:none !important; margin:0 !important; padding:0.4cm 0.4cm 0.1cm 0.4cm !important;}
            .print-btn, .event-link, .event-list, nav, .no-print, .result-section > :not(.print-version) { display:none !important; }
            .print-version { display:block !important; }
            .print-version table { width:100%; border-collapse:collapse; font-size:0.91em; margin-bottom:0.7em;}
            .print-version th, .print-version td { border:1px solid #888; padding:0.18em 0.09em; text-align:center; }
            .print-version th { background:#e3edfa !important; color:#222 !important; }
            .print-version .cp th { background:#f8ebff !important; }
            .print-version .dl th { background:#eafae4 !important; }
            .print-version .min { background:#f9e5e5 !important; }
            .print-version .max { background:#e2f7e2 !important; }
            .print-version .final { background:#f2f2fa !important; font-weight:700; }
            .print-version .rank1 { background:#ffe966 !important; }
            .print-version .rank2 { background:#cde4ff !important; }
            .print-version .rank3 { background:#fff0e2 !important; }
            .print-version h2 { margin:0 0 0.25em 0; font-size:1.09em; }
            .print-version .desc { font-size:0.84em; color:#444; margin:0.1em 0 0 0;}
            .print-version .comp-title, .print-version .comp-date { display:block !important; }
            @page { size: A4 landscape; margin:0.6cm; }
        }
        @media screen {
            .print-version { display:none; }
        }
    </style>
    <script>
    function printResult() { window.print(); }
    </script>
</head>
<body>
<main>
<section class="result-section">
<?php if ($selected_event && $player_results): ?>
    <button class="print-btn no-print" onclick="printResult()">🖨️ 인쇄</button>
    <?php if ($comp_title): ?>
        <div class="comp-title"><?= htmlspecialchars($comp_title) ?></div>
    <?php endif; ?>
    <?php if ($comp_date): ?>
        <div class="comp-date"><?= htmlspecialchars($comp_date) ?></div>
    <?php endif; ?>
    <div class="result-title"><?= htmlspecialchars($event_name) ?> 결과지</div>
    <!-- 기술(TS) -->
    <table class="score-table ts">
        <tr>
            <th colspan="<?= 3+count($adjudicators) ?>">기술 (TS)</th>
        </tr>
        <tr>
            <th>번호</th>
            <?php foreach($adjudicators as $adj_name): ?>
                <th><?= htmlspecialchars($adj_name) ?></th>
            <?php endforeach; ?>
            <th class="min">Min</th>
            <th class="max">Max</th>
            <th class="final">평균</th>
        </tr>
        <?php foreach($player_results as $player):
            $scores = $player['tech_scores'];
            $min = (count($scores) >= 1) ? min($scores) : '';
            $max = (count($scores) >= 1) ? max($scores) : '';
        ?>
        <tr>
            <td class="row-num"><?= $player['number'] ?></td>
            <?php foreach($adjudicators as $adj_id => $adj_name): ?>
                <td><?= isset($scores[$adj_id]) ? number_format($scores[$adj_id], 2) : '-' ?></td>
            <?php endforeach; ?>
            <td class="min"><?= $min !== '' ? number_format($min,2) : '' ?></td>
            <td class="max"><?= $max !== '' ? number_format($max,2) : '' ?></td>
            <td class="final"><?= number_format($player['final_tech'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <!-- 안무(CP) -->
    <table class="score-table cp">
        <tr>
            <th colspan="<?= 3+count($adjudicators) ?>">안무 (CP)</th>
        </tr>
        <tr>
            <th>번호</th>
            <?php foreach($adjudicators as $adj_name): ?>
                <th><?= htmlspecialchars($adj_name) ?></th>
            <?php endforeach; ?>
            <th class="min">Min</th>
            <th class="max">Max</th>
            <th class="final">평균</th>
        </tr>
        <?php foreach($player_results as $player):
            $scores = $player['choreo_scores'];
            $min = (count($scores) >= 1) ? min($scores) : '';
            $max = (count($scores) >= 1) ? max($scores) : '';
        ?>
        <tr>
            <td class="row-num"><?= $player['number'] ?></td>
            <?php foreach($adjudicators as $adj_id => $adj_name): ?>
                <td><?= isset($scores[$adj_id]) ? number_format($scores[$adj_id], 2) : '-' ?></td>
            <?php endforeach; ?>
            <td class="min"><?= $min !== '' ? number_format($min,2) : '' ?></td>
            <td class="max"><?= $max !== '' ? number_format($max,2) : '' ?></td>
            <td class="final"><?= number_format($player['final_choreo'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <!-- 난이도(DL) -->
    <table class="score-table dl">
        <tr>
            <th colspan="<?= 3+count($adjudicators) ?>">난이도 (DL)</th>
        </tr>
        <tr>
            <th>번호</th>
            <?php foreach($adjudicators as $adj_name): ?>
                <th><?= htmlspecialchars($adj_name) ?></th>
            <?php endforeach; ?>
            <th class="min">Min</th>
            <th class="max">Max</th>
            <th class="final">평균</th>
        </tr>
        <?php foreach($player_results as $player):
            $scores = $player['diff_scores'];
            $min = (count($scores) >= 1) ? min($scores) : '';
            $max = (count($scores) >= 1) ? max($scores) : '';
        ?>
        <tr>
            <td class="row-num"><?= $player['number'] ?></td>
            <?php foreach($adjudicators as $adj_id => $adj_name): ?>
                <td><?= isset($scores[$adj_id]) ? number_format($scores[$adj_id], 2) : '-' ?></td>
            <?php endforeach; ?>
            <td class="min"><?= $min !== '' ? number_format($min,2) : '' ?></td>
            <td class="max"><?= $max !== '' ? number_format($max,2) : '' ?></td>
            <td class="final"><?= number_format($player['final_diff'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <!-- 종합 집계표 -->
    <table class="summary-table">
        <tr>
            <th>번호</th>
            <th>기술</th>
            <th>안무</th>
            <th>난이도</th>
            <th>총점<br><span style="font-size:0.94em;">(기술+안무)×난이도</span></th>
            <th>순위</th>
            <th>선수명</th>
            <th>소속</th>
        </tr>
        <?php foreach($player_results as $player):
            $rank_class = 'rank' . ($player['rank'] <= 3 ? $player['rank'] : '');
        ?>
        <tr class="<?= $rank_class ?>">
            <td><?= $player['number'] ?></td>
            <td><?= number_format($player['final_tech'], 2) ?></td>
            <td><?= number_format($player['final_choreo'], 2) ?></td>
            <td><?= number_format($player['final_diff'], 2) ?></td>
            <td><b><?= number_format($player['total_score'], 2) ?></b></td>
            <td><?= $player['rank'] ?>위</td>
            <td><?= htmlspecialchars($player['name']) ?></td>
            <td><?= htmlspecialchars($player['partner']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div style="margin:1.2em 0 0 0; color:#555; font-size:0.97em;">
        ※ 각 항목 점수는 심사위원 5명 중 최고/최저점 제외 3명 평균입니다.<br>
        ※ 총점 = <b>(기술점수 + 안무점수) × 난이도점수</b> 입니다.<br>
        ※ 심사위원 점수가 없으면 '-'로 표시됩니다.
    </div>
    <!-- 인쇄 전용: 화면과 완전히 동일한 레이아웃을 1페이지에! -->
    <div class="print-version">
        <?php if ($comp_title): ?>
            <div class="comp-title"><?= htmlspecialchars($comp_title) ?></div>
        <?php endif; ?>
        <?php if ($comp_date): ?>
            <div class="comp-date"><?= htmlspecialchars($comp_date) ?></div>
        <?php endif; ?>
        <h2><?= htmlspecialchars($event_name) ?> 결과지</h2>
        <!-- 기술 -->
        <table class="score-table ts">
            <tr>
                <th colspan="<?= 3+count($adjudicators) ?>">기술 (TS)</th>
            </tr>
            <tr>
                <th>번호</th>
                <?php foreach($adjudicators as $adj_name): ?>
                    <th><?= htmlspecialchars($adj_name) ?></th>
                <?php endforeach; ?>
                <th class="min">Min</th>
                <th class="max">Max</th>
                <th class="final">평균</th>
            </tr>
            <?php foreach($player_results as $player):
                $scores = $player['tech_scores'];
                $min = (count($scores) >= 1) ? min($scores) : '';
                $max = (count($scores) >= 1) ? max($scores) : '';
            ?>
            <tr>
                <td><?= $player['number'] ?></td>
                <?php foreach($adjudicators as $adj_id => $adj_name): ?>
                    <td><?= isset($scores[$adj_id]) ? number_format($scores[$adj_id], 2) : '-' ?></td>
                <?php endforeach; ?>
                <td class="min"><?= $min !== '' ? number_format($min,2) : '' ?></td>
                <td class="max"><?= $max !== '' ? number_format($max,2) : '' ?></td>
                <td class="final"><?= number_format($player['final_tech'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <!-- 안무 -->
        <table class="score-table cp">
            <tr>
                <th colspan="<?= 3+count($adjudicators) ?>">안무 (CP)</th>
            </tr>
            <tr>
                <th>번호</th>
                <?php foreach($adjudicators as $adj_name): ?>
                    <th><?= htmlspecialchars($adj_name) ?></th>
                <?php endforeach; ?>
                <th class="min">Min</th>
                <th class="max">Max</th>
                <th class="final">평균</th>
            </tr>
            <?php foreach($player_results as $player):
                $scores = $player['choreo_scores'];
                $min = (count($scores) >= 1) ? min($scores) : '';
                $max = (count($scores) >= 1) ? max($scores) : '';
            ?>
            <tr>
                <td><?= $player['number'] ?></td>
                <?php foreach($adjudicators as $adj_id => $adj_name): ?>
                    <td><?= isset($scores[$adj_id]) ? number_format($scores[$adj_id], 2) : '-' ?></td>
                <?php endforeach; ?>
                <td class="min"><?= $min !== '' ? number_format($min,2) : '' ?></td>
                <td class="max"><?= $max !== '' ? number_format($max,2) : '' ?></td>
                <td class="final"><?= number_format($player['final_choreo'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <!-- 난이도 -->
        <table class="score-table dl">
            <tr>
                <th colspan="<?= 3+count($adjudicators) ?>">난이도 (DL)</th>
            </tr>
            <tr>
                <th>번호</th>
                <?php foreach($adjudicators as $adj_name): ?>
                    <th><?= htmlspecialchars($adj_name) ?></th>
                <?php endforeach; ?>
                <th class="min">Min</th>
                <th class="max">Max</th>
                <th class="final">평균</th>
            </tr>
            <?php foreach($player_results as $player):
                $scores = $player['diff_scores'];
                $min = (count($scores) >= 1) ? min($scores) : '';
                $max = (count($scores) >= 1) ? max($scores) : '';
            ?>
            <tr>
                <td><?= $player['number'] ?></td>
                <?php foreach($adjudicators as $adj_id => $adj_name): ?>
                    <td><?= isset($scores[$adj_id]) ? number_format($scores[$adj_id], 2) : '-' ?></td>
                <?php endforeach; ?>
                <td class="min"><?= $min !== '' ? number_format($min,2) : '' ?></td>
                <td class="max"><?= $max !== '' ? number_format($max,2) : '' ?></td>
                <td class="final"><?= number_format($player['final_diff'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <!-- 집계표 -->
        <table class="summary-table">
            <tr>
                <th>번호</th>
                <th>기술</th>
                <th>안무</th>
                <th>난이도</th>
                <th>총점<br><span style="font-size:0.94em;">(기술+안무)×난이도</span></th>
                <th>순위</th>
                <th>선수명</th>
                <th>소속</th>
            </tr>
            <?php foreach($player_results as $player):
                $rank_class = 'rank' . ($player['rank'] <= 3 ? $player['rank'] : '');
            ?>
            <tr class="<?= $rank_class ?>">
                <td><?= $player['number'] ?></td>
                <td><?= number_format($player['final_tech'], 2) ?></td>
                <td><?= number_format($player['final_choreo'], 2) ?></td>
                <td><?= number_format($player['final_diff'], 2) ?></td>
                <td><b><?= number_format($player['total_score'], 2) ?></b></td>
                <td><?= $player['rank'] ?>위</td>
                <td><?= htmlspecialchars($player['name']) ?></td>
                <td><?= htmlspecialchars($player['partner']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div class="desc">
            ※ 각 항목 점수는 심사위원 5명 중 최고/최저점 제외 3명 평균입니다.<br>
            ※ 총점 = <b>(기술점수 + 안무점수) × 난이도점수</b>
        </div>
    </div>
<?php elseif ($selected_event): ?>
    <div style="margin:2em 0; padding:1.2em; background:#f6eaea; color:#e33; border-radius:8px;">
        참가자 또는 채점 데이터가 없습니다.
    </div>
<?php else: ?>
    <?php if ($comp_title): ?>
        <div class="comp-title"><?= htmlspecialchars($comp_title) ?></div>
    <?php endif; ?>
    <?php if ($comp_date): ?>
        <div class="comp-date"><?= htmlspecialchars($comp_date) ?></div>
    <?php endif; ?>
    <div class="result-title">채점 결과</div>
    <div class="event-list" style="text-align:center; margin-bottom:3em;">
        <?php if ($events): ?>
            <?php foreach ($events as $ev): ?>
                <a class="event-link" style="display:inline-block;margin:0.4em 0.8em 0.4em 0;padding:.5em 1.2em; border-radius:24px;
                background:#23262b; color:#03C75A; border:1px solid #3a3d44; text-decoration:none; font-weight:700;"
                   href="?event=<?= urlencode($ev['code']) ?>">
                    <?= htmlspecialchars($ev['name']) ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <span style="color:#f55;">등록된 종목이 없습니다.</span>
        <?php endif; ?>
    </div>
    <div style="color:#999; text-align:center;">종목을 선택해 주세요.</div>
<?php endif; ?>
</section>
</main>
</body>
</html>