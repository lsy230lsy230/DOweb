<?php
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

// Remove BOM and normalize
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$event_no = preg_replace('/\x{FEFF}/u', '', $event_no);

$comp_id = preg_replace('/[^\d-]/', '', $comp_id);
$event_no = preg_replace('/\D+/', '', $event_no);

// Language setting
$lang = $_GET['lang'] ?? 'ko';
if (!in_array($lang, ['ko', 'en', 'zh', 'ja'])) {
    $lang = 'ko';
}

if (!$comp_id || !$event_no) {
    echo "<h1>잘못된 대회 ID 또는 이벤트 번호입니다.</h1>";
    exit;
}

// Load scores
$scores_file = __DIR__ . "/data/$comp_id/scores_$event_no.json";
$scores_data = null;
$recall_data = null;

if (file_exists($scores_file)) {
    $content = file_get_contents($scores_file);
    $scores_data = json_decode($content, true);
} else {
    // Look for .adj files (recall round)
    $adj_files = glob("$data_dir/{$event_no}_*.adj");
    $recall_data = [];
    
    foreach ($adj_files as $file) {
        $filename = basename($file);
        if (preg_match('/^(\d+)_(.+)_(\d+)\.adj$/', $filename, $matches)) {
            $file_dance = $matches[2];
            $file_judge_id = $matches[3];
            
            // Read file content
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $players = [];
            
            foreach ($lines as $line) {
                $line = trim($line, '"');
                if ($line && is_numeric($line)) {
                    $players[] = $line;
                }
            }
            
            if (!isset($recall_data[$file_dance])) {
                $recall_data[$file_dance] = [];
            }
            
            $recall_data[$file_dance][$file_judge_id] = $players;
        }
    }
}

// Load event data
$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$event_data = null;

if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        if (($cols[0] ?? '') === $event_no) {
            $event_data = [
                'no' => $cols[0],
                'name' => $cols[1],
                'round_type' => $cols[2],
                'round_num' => $cols[3],
                'dances' => []
            ];
            for ($i = 6; $i <= 10; $i++) {
                if (!empty($cols[$i])) $event_data['dances'][] = $cols[$i];
            }
            break;
        }
    }
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>채점 결과 | <?=h($event_data['name'] ?? '이벤트')?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#1a1a1a; font-family:sans-serif; margin:0; padding:20px;}
        .container { max-width:1200px; margin:0 auto; background:#fff; border-radius:12px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,0.3);}
        h1 { color:#003399; margin-bottom:20px; text-align:center;}
        .event-info { background:#f0f4ff; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center;}
        .event-info h2 { margin:0 0 10px 0; color:#0d2c96;}
        .event-info p { margin:5px 0; color:#333;}
        .results-section { margin-bottom:30px;}
        .results-header { background:#03C75A; color:#fff; padding:10px 15px; border-radius:6px 6px 0 0; font-weight:bold;}
        .results-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #ddd;}
        .results-table th, .results-table td { padding:12px 8px; text-align:center; border:1px solid #ddd;}
        .results-table th { background:#f8f9fa; font-weight:bold; color:#333;}
        .results-table tr:nth-child(even) { background:#f8f9fa;}
        .place-1 { background:#FFD700; font-weight:bold; color:#000;}
        .place-2 { background:#C0C0C0; font-weight:bold; color:#000;}
        .place-3 { background:#CD7F32; font-weight:bold; color:#fff;}
        .adjudicator-section { margin-bottom:30px;}
        .adjudicator-header { background:#003399; color:#fff; padding:10px 15px; border-radius:6px 6px 0 0; font-weight:bold;}
        .adjudicator-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #ddd; font-size:14px;}
        .adjudicator-table th, .adjudicator-table td { padding:8px 4px; text-align:center; border:1px solid #ddd;}
        .adjudicator-table th { background:#f8f9fa; font-weight:bold; color:#333;}
        .adjudicator-table tr:nth-child(even) { background:#f8f9fa;}
        .no-data { text-align:center; padding:40px; color:#666; font-size:18px;}
        .back-btn { background:#6c757d; color:#fff; border:none; padding:10px 20px; font-size:14px; border-radius:6px; cursor:pointer; text-decoration:none; display:inline-block; margin-bottom:20px;}
        .back-btn:hover { background:#5a6268;}
        .stats { background:#e7f3ff; border:1px solid #b3d9ff; padding:15px; border-radius:6px; margin-bottom:20px;}
        .stats h3 { margin:0 0 10px 0; color:#003399;}
        .stats p { margin:5px 0; color:#333;}
    </style>
</head>
<body>
<div class="container">
    <a href="live_panel.php?comp_id=<?=h($comp_id)?>" class="back-btn">← 라이브 패널로 돌아가기</a>
    
    <h1>채점 결과</h1>
    
    <?php if ($event_data): ?>
    <div class="event-info">
        <h2><?=h($event_data['name'])?></h2>
        <p><strong>이벤트 번호:</strong> <?=h($event_data['no'])?></p>
        <p><strong>라운드:</strong> <?=h($event_data['round_type'])?> <?=h($event_data['round_num'])?></p>
        <p><strong>종목:</strong> <?=h(implode(', ', $event_data['dances']))?></p>
    </div>
    <?php endif; ?>

    <?php if ($scores_data): ?>
        <div class="stats">
            <h3>채점 정보 (결승전)</h3>
            <p><strong>채점 시간:</strong> <?=h($scores_data['calculated_at'])?></p>
            <p><strong>심사위원 수:</strong> <?=h($scores_data['num_adjudicators'])?>명</p>
            <p><strong>출전 선수:</strong> <?=h($scores_data['num_couples'])?>명</p>
        </div>
    <?php elseif ($recall_data): ?>
        <div class="stats">
            <h3>Recall 정보 (예선/준결)</h3>
            <p><strong>채점된 댄스:</strong> <?=count($recall_data)?>개</p>
            <?php 
            $total_judges = 0;
            foreach ($recall_data as $dance => $judges) {
                $total_judges = max($total_judges, count($judges));
            }
            ?>
            <p><strong>심사위원 수:</strong> <?=$total_judges?>명</p>
        </div>
    <?php endif; ?>

        <div class="results-section">
            <div class="results-header">
                최종 결과 (Skating System 적용)
            </div>
            <table class="results-table">
                <thead>
                    <tr>
                        <th style="width:80px;">순위</th>
                        <th style="width:100px;">선수 번호</th>
                        <th style="width:200px;">선수 정보</th>
                        <th style="width:150px;">총 점수</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $results = $scores_data['results']['results'];
                    $couples = $scores_data['results']['couples'];
                    $sortedResults = $results;
                    asort($sortedResults);
                    $place = 1;
                    foreach ($sortedResults as $coupleId => $finalPlace): 
                        $totalMarks = $couples[$coupleId]['total_marks'];
                        $placeClass = '';
                        if ($finalPlace == 1) $placeClass = 'place-1';
                        elseif ($finalPlace == 2) $placeClass = 'place-2';
                        elseif ($finalPlace == 3) $placeClass = 'place-3';
                    ?>
                    <tr>
                        <td class="<?=$placeClass?>"><strong><?=$finalPlace?></strong></td>
                        <td><strong><?=h($coupleId)?></strong></td>
                        <td>선수 #<?=h($coupleId)?></td>
                        <td><?=h($totalMarks)?></td>
                    </tr>
                    <?php $place++; endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adjudicator-section">
            <div class="adjudicator-header">
                심사위원별 상세 점수
            </div>
            <table class="adjudicator-table">
                <thead>
                    <tr>
                        <th style="width:100px;">선수 번호</th>
                        <?php foreach ($scores_data['adjudicator_marks'] as $adjudicatorId => $marks): ?>
                        <th style="width:60px;">심사위원 <?=h($adjudicatorId)?></th>
                        <?php endforeach; ?>
                        <th style="width:80px;">총점</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sortedResults as $coupleId => $finalPlace): ?>
                    <tr>
                        <td><strong><?=h($coupleId)?></strong></td>
                        <?php foreach ($scores_data['adjudicator_marks'] as $adjudicatorId => $marks): ?>
                        <td><?=h($marks[$coupleId] ?? '-')?></td>
                        <?php endforeach; ?>
                        <td><strong><?=h($couples[$coupleId]['total_marks'])?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="text-align:center; margin-top:30px;">
            <a href="judge_scoring.php?comp_id=<?=h($comp_id)?>&event_no=<?=h($event_no)?>" 
               style="background:#003399; color:#fff; border:none; padding:12px 24px; font-size:16px; font-weight:bold; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block;">
               점수 수정하기
            </a>
        </div>

    <?php elseif ($recall_data): ?>
        <!-- Recall Data Display -->
        <?php foreach ($recall_data as $dance => $judges): ?>
        <div class="results-section">
            <div class="results-header">
                <?=h($dance)?> - Recall 결과
            </div>
            <table class="results-table">
                <thead>
                    <tr>
                        <th style="width:100px;">심사위원</th>
                        <th>선택된 선수</th>
                        <th style="width:80px;">선택 수</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($judges as $judge_id => $selected_players): ?>
                    <tr>
                        <td><strong>심사위원 #<?=h($judge_id)?></strong></td>
                        <td style="text-align:left;">
                            <?php if (!empty($selected_players)): ?>
                                <?=h(implode(', ', $selected_players))?>
                            <?php else: ?>
                                <span style="color:#999;">선택 없음</span>
                            <?php endif; ?>
                        </td>
                        <td><?=count($selected_players)?>명</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center; margin-top:30px;">
            <a href="judge_scoring.php?comp_id=<?=h($comp_id)?>&event_no=<?=h($event_no)?>" 
               style="background:#003399; color:#fff; border:none; padding:12px 24px; font-size:16px; font-weight:bold; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block;">
               점수 수정하기
            </a>
        </div>

    <?php else: ?>
        <div class="no-data">
            <p>아직 채점이 완료되지 않았습니다.</p>
            <a href="judge_scoring.php?comp_id=<?=h($comp_id)?>&event_no=<?=h($event_no)?>" 
               style="background:#03C75A; color:#fff; border:none; padding:12px 24px; font-size:16px; font-weight:bold; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block; margin-top:20px;">
               채점 시작하기
            </a>
        </div>
    <?php endif; ?>
</div>

<footer style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9em; padding: 20px; border-top: 1px solid #eee;">
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>
</body>
</html>
