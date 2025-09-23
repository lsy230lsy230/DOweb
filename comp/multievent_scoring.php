<?php
session_start();

$comp_id = $_GET['comp_id'] ?? '';
$event_group = $_GET['event_group'] ?? '';

// Remove BOM and normalize
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$event_group = preg_replace('/\x{FEFF}/u', '', $event_group);

// Language setting
$lang = $_GET['lang'] ?? 'ko';
if (!in_array($lang, ['ko', 'en', 'zh', 'ja'])) {
    $lang = 'ko';
}

// Language texts
$texts = [
    'ko' => [
        'title' => '멀티이벤트 채점 시스템',
        'judge_info' => '심사위원',
        'event_info' => '이벤트 정보',
        'round' => '라운드',
        'dances' => '종목',
        'players' => '출전 선수',
        'submit' => '제출',
        'no_data' => '이벤트 데이터를 찾을 수 없습니다.',
        'invalid_data' => '잘못된 대회 ID 또는 이벤트 그룹입니다.'
    ],
    'en' => [
        'title' => 'Multi-Event Scoring System',
        'judge_info' => 'Judge',
        'event_info' => 'Event Information',
        'round' => 'Round',
        'dances' => 'Dances',
        'players' => 'Competitors',
        'submit' => 'Submit',
        'no_data' => 'Event data not found.',
        'invalid_data' => 'Invalid competition ID or event group.'
    ]
];

$t = $texts[$lang];

// Check if judge is logged in
if (!isset($_SESSION['scoring_logged_in']) || !$_SESSION['scoring_logged_in'] || $_SESSION['scoring_comp_id'] !== $comp_id) {
    header("Location: scoring_login.php?comp_id=" . urlencode($comp_id) . "&lang=" . urlencode($lang));
    exit;
}

if (!$comp_id || !$event_group) {
    echo "<h1>" . $t['invalid_data'] . "</h1>";
    exit;
}

// Load event data
$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$dance_name_file = "$data_dir/DanceName.txt";

$events = [];
$dance_mapping = [];

// Load dance name mapping
if (file_exists($dance_name_file)) {
    $lines = file($dance_name_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = preg_replace('/\x{FEFF}/u', '', $line);
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 3 && !empty($cols[1]) && is_numeric($cols[0])) {
            $dance_mapping[$cols[0]] = $cols[1];
        }
    }
}

// Load events from RunOrder_Tablet.txt
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = preg_replace('/\x{FEFF}/u', '', $line);
        $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
        $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
        
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        
        // Check if it's a competition event (has dances)
        $has_dance = false;
        for ($i = 6; $i <= 10; $i++) {
            if (isset($cols[$i]) && $cols[$i] !== '') {
                $has_dance = true;
                break;
            }
        }
        
        if ($has_dance) {
            $base_no_raw = $cols[0] ?? '';
            $detail_no = trim($cols[13] ?? '');
            $event_no = !empty($detail_no) ? $detail_no : $base_no_raw; // use detail_no when present
            $event_base = preg_replace('/\D+/', '', $base_no_raw); // numeric base number for grouping
            
            $event_data = [
                'no' => $event_no,
                'base_no' => $event_base,
                'name' => $cols[1],
                'round_type' => $cols[2],
                'round_num' => $cols[3],
                'recall_count' => intval($cols[4] ?? 0),
                'detail_no' => $detail_no,
                'dances' => []
            ];
            
            for ($i = 6; $i <= 10; $i++) {
                if (!empty($cols[$i])) $event_data['dances'][] = $cols[$i];
            }
            
            $events[] = $event_data;
        }
    }
}

// Filter events by group
$filtered_events = [];
if ($event_group) {
    // Find events that belong to the same group (same base event number)
    $base_event_no = $event_group; // event_group is already the base number like "1"
    foreach ($events as $event) {
        if ($event['base_no'] === $base_event_no) {
            $filtered_events[] = $event;
        }
    }
    // Debug: log filtered events
    error_log("Event group: $event_group, Filtered events: " . print_r(array_map(function($e) { return $e['no'] . ' (' . $e['name'] . ')'; }, $filtered_events), true));
} else {
    $filtered_events = $events;
}

$event_count = count($filtered_events);

// Load existing scores for current judge
$judge_id = $_SESSION['scoring_judge_id'] ?? '';
$existing_scores = [];

if ($judge_id) {
    foreach ($filtered_events as $event) {
        $event_no = $event['no'];
        foreach ($event['dances'] as $dance) {
            $adj_file = "$data_dir/{$event_no}_{$dance}_{$judge_id}.adj";
            error_log("Checking adj file: $adj_file (exists: " . (file_exists($adj_file) ? 'yes' : 'no') . ")");
            
            if (file_exists($adj_file)) {
                $lines = file($adj_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $rankings = [];
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (strpos($line, ',') !== false) {
                        // Final format: player,rank
                        list($player, $rank) = explode(',', $line, 2);
                        $player = trim($player, '"');
                        $rank = intval(trim($rank));
                        if (is_numeric($player) && $rank > 0) {
                            $rankings[$player] = $rank;
                        }
                    } else {
                        // Recall format: "player" - store as selected (no rank)
                        $player = trim($line, '"');
                        if (is_numeric($player)) {
                            $rankings[$player] = 'selected'; // Mark as selected for recall
                        }
                    }
                }
                
                if (!empty($rankings)) {
                    if (!isset($existing_scores[$event_no])) {
                        $existing_scores[$event_no] = [];
                    }
                    $existing_scores[$event_no][$dance] = $rankings;
                    error_log("Loaded rankings for {$event_no}_{$dance}: " . json_encode($rankings));
                }
            }
        }
    }
}

// Determine layout class based on event count
$layout_class = 'multievent-2'; // default
if ($event_count === 2) {
    $layout_class = 'multievent-2';
} elseif ($event_count === 3) {
    $layout_class = 'multievent-3';
} elseif ($event_count === 4) {
    $layout_class = 'multievent-4';
} elseif ($event_count === 5) {
    $layout_class = 'multievent-5';
} elseif ($event_count === 6) {
    $layout_class = 'multievent-6';
} elseif ($event_count > 6) {
    $layout_class = 'multievent-6'; // fallback to 6-event layout
}

function h($s) { return htmlspecialchars($s ?? ''); }

// Function to convert dance numbers to full names
function getDanceNames($dances, $dance_mapping) {
    $result = [];
    foreach ($dances as $dance) {
        $dance = trim($dance);
        if (isset($dance_mapping[$dance])) {
            $result[] = $dance_mapping[$dance];
        } else {
            $result[] = $dance;
        }
    }
    return $result;
}
?>
<!DOCTYPE html>
<html lang="<?=h($lang)?>">
<head>
    <meta charset="UTF-8">
    <title><?=h($t['title'])?> | <?=h($comp_id)?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { 
            background: #1a1a1a; 
            font-family: sans-serif; 
            margin: 0; 
            padding: 10px;
        }
        
        .multievent-container {
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #003399, #0066cc);
            color: white;
            border-radius: 8px;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: bold;
        }
        
        .judge-info {
            font-size: 16px;
            opacity: 0.9;
        }
        
        /* Multi-event Layouts */
        .multievent-2 {
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 20px;
            min-height: 80vh;
        }
        
        .multievent-3 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 20px;
            min-height: 80vh;
        }
        
        .multievent-3 .event-block:nth-child(3) {
            grid-column: 1 / -1;
        }
        
        .multievent-4 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 20px;
            min-height: 80vh;
        }
        
        .multievent-5 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr 1fr;
            gap: 20px;
            min-height: 80vh;
        }
        
        .multievent-5 .event-block:nth-child(5) {
            grid-column: 1 / -1;
        }
        
        .multievent-6 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr 1fr;
            gap: 20px;
            min-height: 80vh;
        }
        
        .event-block {
            background: #f8f9fa;
            border: 3px solid #003399;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.3s ease;
            min-height: 200px;
        }
        
        .event-block:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,51,153,0.2);
        }
        
        .event-header {
            background: #003399;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
        }
        .event-title-row { display:flex; align-items:center; gap:10px; justify-content:flex-start; }
        .event-number-badge {
            background: #ff6b35;
            color: white;
            min-width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }
        /* Dance tabs per event */
        .event-dances {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 8px 0 14px 0;
        }
        .dance-btn {
            background: #fff;
            color: #003399;
            border: 2px solid #003399;
            border-radius: 16px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .dance-btn.active {
            background: #003399;
            color: #fff;
        }
        
        .event-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        
        .event-details {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Removed top-right number; using left badge in title row */
        
        .scoring-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        /* Touch-based final ranking layout */
        .touch-scoring-container { display: flex; gap: 12px; }
        .players-column, .ranking-column {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            flex-direction: column;
        }
        .players-column { flex: 1; }
        .ranking-column { flex: 0 0 190px; max-width: 200px; min-width: 160px; }
        .players-column h4, .ranking-column h4 { margin: 0 0 8px 0; text-align: center; color:#003399; }
        .players-list, .ranking-list { display: flex; flex-direction: column; gap: 8px; overflow-y: auto; }
        .player-item {
            background: #003399;
            color: #fff;
            border-radius: 8px;
            text-align: center;
            padding: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            user-select: none;
        }
        .player-item.selected { background: #28a745; }
        .player-item.assigned { opacity: .35; background: #6c757d; cursor: not-allowed; }
        .ranking-slot {
            background: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px 12px 12px 40px; /* make room for rank label */
            text-align: center;
            min-height: 48px;
            position: relative;
        }
        .ranking-slot::before {
            content: attr(data-rank) "위";
            position: absolute; left: 8px; top: 50%; transform: translateY(-50%);
            background: #003399; color:#fff; padding: 2px 6px; font-size: 11px; border-radius: 10px;
        }
        .ranking-slot.assigned { border-color: #28a745; background: #f6fff6; }
        .player-assigned { font-size: 20px; font-weight: 900; color: #28a745; }

        /* Recall system styles */
        .players-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); 
            gap: 8px; 
            margin-bottom: 15px;
        }
        .player-card { 
            background: #fff; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            padding: 8px 4px; 
            text-align: center; 
            cursor: pointer; 
            transition: all 0.3s ease;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .player-card:hover { 
            border-color: #4CAF50; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .player-card.selected { 
            border-color: #4CAF50; 
            background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(76, 175, 80, 0.3);
        }
        .player-number { 
            font-size: 24px; 
            font-weight: 900; 
            color: #333;
            margin: 0;
            line-height: 1;
        }
        .player-card.selected .player-number {
            color: #2e7d32;
        }
        .recall-checkbox { 
            display: none;
        }

        .recall-status {
            text-align: center;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 8px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
            margin: 10px 0;
            border: 2px solid #ddd;
        }
        
        .recall-status.complete {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 3px solid #28a745;
        }
        
        .recall-status.over {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 3px solid #dc3545;
        }
        
        .recall-status .selected-count {
            font-size: 20px;
            font-weight: 900;
        }
        
        .submit-section {
            text-align: center;
            margin-top: auto;
        }
        
        .submit-btn {
            background: #4CAF50;
            color: #fff;
            border: none;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
        }
        
        .submit-btn:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .progress-overview {
            background: #e7f3ff;
            border: 2px solid #b3d9ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .progress-overview h3 {
            margin: 0 0 10px 0;
            color: #003399;
            font-size: 18px;
        }
        
        .progress-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .progress-item {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            font-size: 12px;
        }
        
        .progress-item.completed {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .progress-item.current {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-top: 2px solid #e0e0e0;
            padding: 10px 20px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-button {
            background: #f5f5f5;
            color: #333;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            min-width: 80px;
        }
        
        .nav-button:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .nav-button.primary {
            background: #2196F3;
            color: #fff;
        }
        
        .nav-button.primary:hover {
            background: #1976D2;
        }
        
        .nav-icon {
            font-size: 18px;
        }
        
        .nav-text {
            font-size: 12px;
        }
        
        /* Add bottom padding to body for navigation */
        body {
            padding-bottom: 80px;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .multievent-2,
            .multievent-3,
            .multievent-4,
            .multievent-5,
            .multievent-6 {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .multievent-3 .event-block:nth-child(3),
            .multievent-5 .event-block:nth-child(5) {
                grid-column: 1;
            }
            
            .event-block {
                min-height: 150px;
            }
            
            .players-grid {
                grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
                gap: 6px;
            }
            
            .player-card {
                min-height: 45px;
                padding: 6px 2px;
            }
            
            .player-number {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="multievent-container">
        <!-- Header -->
        <div class="header">
            <h1><?=h($t['title'])?></h1>
            <div class="judge-info">
                #<?=h($_SESSION['scoring_judge_id'])?> <?=h($_SESSION['scoring_judge_name'])?> (<?=h($_SESSION['scoring_judge_country'])?>)
            </div>
        </div>
        
        <!-- Progress Overview -->
        <div class="progress-overview">
            <h3>전체 진행 상황</h3>
            <div class="progress-grid" id="progressGrid">
                <!-- Progress items will be populated by JavaScript -->
            </div>
        </div>
        
        <!-- Multi-Event Layout -->
        <div class="<?=h($layout_class)?>" id="multieventContainer">
            <?php foreach ($filtered_events as $index => $event): ?>
                <?php 
                $dance_names = getDanceNames($event['dances'], $dance_mapping);
                // Use detail_no (which is already reflected in no) to load player list per sub-event
                $players_file = "$data_dir/players_{$event['no']}.txt";
                $players = [];
                
                // Debug: log player file path
                error_log("Loading players for event {$event['no']}: $players_file (exists: " . (file_exists($players_file) ? 'yes' : 'no') . ")");
                
                if (file_exists($players_file)) {
                    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $line = preg_replace('/\x{FEFF}/u', '', $line);
                        $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
                        $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
                        $line = trim($line);
                        if ($line && is_numeric($line)) {
                            $players[] = $line;
                        }
                    }
                }
                ?>
                <div class="event-block" data-event="<?=h($event['no'])?>" data-index="<?=$index?>">
                    <div class="event-header">
                        <div class="event-title-row">
                            <div class="event-number-badge">#<?=h($event['no'])?></div>
                            <div>
                                <div class="event-title"><?=h($event['name'])?></div>
                                <div class="event-details">
                                    <?php
                                    // RunOrder_Tablet.txt의 round_type을 직접 사용
                                    $round_display = $event['round_type'] ?: 'Final';
                                    
                                    // 디버그: 라운드 정보 확인
                                    error_log("Event {$event['no']}: round_type='{$event['round_type']}', round_display='{$round_display}'");
                                    ?>
                                    <?=h($round_display)?> | <?=h(implode(', ', $dance_names))?> | <?=count($players)?> couples
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="event-dances">
                        <?php foreach ($event['dances'] as $di => $dance_code): $dance_label = isset($dance_mapping[$dance_code]) ? $dance_mapping[$dance_code] : $dance_code; ?>
                            <button type="button" class="dance-btn<?= $di===0 ? ' active' : '' ?>" data-event="<?=h($event['no'])?>" data-dance="<?=h($dance_code)?>"><?=
                                h($dance_label)
                            ?></button>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="scoring-area">
                        <?php 
                        $totalPlayers = count($players);
                        $is_final = ($round_display === 'Final');
                        $recall_count = intval($event['recall_count'] ?? 0);
                        ?>
                        
                        <?php if ($is_final): ?>
                            <!-- Final Round: 등위 입력 방식 -->
                            <div class="touch-scoring-container">
                                <div class="players-column">
                                    <h4>선수</h4>
                                    <div class="players-list">
                                        <?php foreach ($players as $player_no): ?>
                                            <div class="player-item" data-event="<?=h($event['no'])?>" data-player="<?=h($player_no)?>"><?=h($player_no)?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="ranking-column">
                                    <h4>순위</h4>
                                    <div class="ranking-list">
                                        <?php for ($i=1; $i<= $totalPlayers; $i++): ?>
                                            <div class="ranking-slot" data-event="<?=h($event['no'])?>" data-rank="<?=$i?>">
                                                <span class="player-assigned"></span>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="recall-status">
                                <div>Final 등위 입력</div>
                                <div class="selected-count">0</div> / <?= $totalPlayers ?>명
                            </div>
                            
                            <div class="submit-section">
                                <button type="button" 
                                        class="submit-btn" 
                                        data-event="<?=h($event['no'])?>"
                                        data-total-count="<?= $totalPlayers ?>"
                                        data-type="final">
                                    <?=h($event['name'])?> 등위 제출
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Preliminary/Semi-Final Round: Recall 체크박스 방식 -->
                            <div class="players-grid">
                                <?php foreach ($players as $player_no): ?>
                                    <div class="player-card" data-event="<?=h($event['no'])?>" data-player="<?=h($player_no)?>" onclick="togglePlayerSelection(this)">
                                        <div class="player-number"><?=h($player_no)?></div>
                                        <input type="checkbox" 
                                               class="recall-checkbox"
                                               data-event="<?=h($event['no'])?>"
                                               data-player="<?=h($player_no)?>"
                                               style="display: none;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="recall-status">
                                <div style="font-size:20px; margin-bottom:5px;">📊 Recall 진행 상황</div>
                                <div style="font-size:28px; font-weight:900; color:#2e7d32;">
                                    <span class="selected-count">0</span> / <?= $recall_count ?>명
                                </div>
                                <div style="font-size:14px; margin-top:5px; opacity:0.8;">
                                    <?= $recall_count ?>명을 선택하면 전송할 수 있습니다
                                </div>
                            </div>
                            
                            <div class="submit-section">
                                <button type="button" 
                                        class="submit-btn" 
                                        data-event="<?=h($event['no'])?>"
                                        data-recall-count="<?= $recall_count ?>"
                                        data-type="recall">
                                    <?=h($event['name'])?> Recall 제출
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initializeMultiEventScoring();
    });
    
    const CURRENT_JUDGE_ID = <?= json_encode($_SESSION['scoring_judge_id'] ?? '') ?>;
    const ACTIVE_DANCE_BY_EVENT = {};
    const EXISTING_SCORES = <?= json_encode($existing_scores) ?>;

    const SELECTED_PLAYER_BY_EVENT = {};
    const RANKINGS_BY_EVENT_AND_DANCE = {}; // { eventNo: { dance: { playerNo: rank } } }

    function initializeMultiEventScoring() {
        // 입력 상호작용: 등위 입력 포커스/변경 시 스타일 업데이트
        document.querySelectorAll('.player-item').forEach(item => {
            item.addEventListener('click', function() {
                const ev = this.dataset.event;
                // 이미 배정된 선수면 선택 불가
                if (this.classList.contains('assigned')) return;
                // 토글 선택
                document.querySelectorAll(`.player-item[data-event="${ev}"]`).forEach(it=>it.classList.remove('selected'));
                this.classList.add('selected');
                SELECTED_PLAYER_BY_EVENT[ev] = this.dataset.player;
            });
        });
        document.querySelectorAll('.ranking-slot').forEach(slot => {
            slot.addEventListener('click', function() {
                const ev = this.dataset.event;
                const dance = ACTIVE_DANCE_BY_EVENT[ev];
                const selected = SELECTED_PLAYER_BY_EVENT[ev];
                if (!dance) { alert('종목을 먼저 선택해주세요.'); return; }
                if (!selected) {
                    // 선택된 선수가 없으면 기존 배정을 취소(토글)
                    if (this.classList.contains('assigned')) {
                        const current = this.querySelector('.player-assigned').textContent;
                        unassignPlayer(ev, dance, current, this);
                        updateEventStatus(ev);
                        updateProgressOverview();
                    }
                    return;
                }
                // 이미 이 랭크에 배정된 선수가 있으면 먼저 해제
                if (this.classList.contains('assigned')) {
                    const current = this.querySelector('.player-assigned').textContent;
                    if (current === selected) {
                        // 같은 선수 재터치시 해제
                        unassignPlayer(ev, dance, selected, this);
                        updateEventStatus(ev);
                        updateProgressOverview();
                        return;
                    } else {
                        unassignPlayer(ev, dance, current, this);
                    }
                }
                assignPlayerToRank(ev, dance, selected, this);
                updateEventStatus(ev);
                updateProgressOverview();
            });
        });
        // 댄스 탭 초기화 및 클릭 핸들러 (이벤트별 활성 댄스 설정)
        document.querySelectorAll('.dance-btn').forEach(btn => {
            const ev = btn.dataset.event;
            if (ACTIVE_DANCE_BY_EVENT[ev] === undefined) {
                ACTIVE_DANCE_BY_EVENT[ev] = btn.dataset.dance;
            }
            btn.addEventListener('click', function() {
                const eventNo = this.dataset.event;
                const parent = this.parentElement;
                parent.querySelectorAll('.dance-btn').forEach(b=>b.classList.remove('active'));
                this.classList.add('active');
                ACTIVE_DANCE_BY_EVENT[eventNo] = this.dataset.dance;
                // 선택된 종목 상태로 UI 반영
                renderRanksForEventDance(eventNo, this.dataset.dance);
                updateEventStatus(eventNo);
                updateProgressOverview();
            });
        });

        // 제출 버튼 핸들러
        document.querySelectorAll('.submit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                submitEvent(this.dataset.event);
            });
        });
        
        // Load existing scores
        loadExistingScores();
        
        updateProgressOverview();
    }
    
    function loadExistingScores() {
        console.log('Loading existing scores:', EXISTING_SCORES);
        
        Object.keys(EXISTING_SCORES).forEach(eventNo => {
            const eventScores = EXISTING_SCORES[eventNo];
            Object.keys(eventScores).forEach(dance => {
                const rankings = eventScores[dance]; // { playerNo: rank }
                
                // Initialize RANKINGS_BY_EVENT_AND_DANCE structure
                if (!RANKINGS_BY_EVENT_AND_DANCE[eventNo]) {
                    RANKINGS_BY_EVENT_AND_DANCE[eventNo] = {};
                }
                if (!RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance]) {
                    RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance] = {};
                }
                
                // Store the rankings
                Object.keys(rankings).forEach(playerNo => {
                    const rank = rankings[playerNo];
                    RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance][playerNo] = rank;
                });
                
                console.log(`Loaded scores for event ${eventNo}, dance ${dance}:`, rankings);
            });
        });
        
        // Update UI for all events with their first dance
        document.querySelectorAll('.event-block').forEach(block => {
            const eventNo = block.dataset.event;
            const firstDanceBtn = block.querySelector('.dance-btn');
            if (firstDanceBtn) {
                const firstDance = firstDanceBtn.dataset.dance;
                renderRanksForEventDance(eventNo, firstDance);
                updateEventStatus(eventNo);
            }
        });
    }
    
    function updateEventStatus(eventNo) {
        const eventBlock = document.querySelector(`[data-event="${eventNo}"]`);
        const submitBtn = eventBlock.querySelector('.submit-btn');
        const isRecall = submitBtn.dataset.type === 'recall';
        const statusDiv = eventBlock.querySelector('.recall-status');
        
        if (isRecall) {
            // Recall 방식: 체크박스 개수 확인
            const recallCount = parseInt(submitBtn.dataset.recallCount);
            const checkboxes = eventBlock.querySelectorAll('.recall-checkbox:checked');
            const selectedCount = checkboxes.length;
            
            statusDiv.querySelector('.selected-count').textContent = selectedCount;
            statusDiv.classList.remove('complete', 'over');
            
            if (selectedCount === recallCount) {
                statusDiv.classList.add('complete');
                submitBtn.style.display = 'inline-block';
                submitBtn.disabled = false;
            } else if (selectedCount > recallCount) {
                statusDiv.classList.add('over');
                submitBtn.style.display = 'none';
                submitBtn.disabled = true;
            } else {
                submitBtn.style.display = 'none';
                submitBtn.disabled = true;
            }
        } else {
            // Final 방식: 순위 개수 확인
            const totalCount = parseInt(submitBtn.dataset.totalCount);
            const dance = ACTIVE_DANCE_BY_EVENT[eventNo];
            const ranks = getCurrentRanks(eventNo, dance);
            const values = Object.values(ranks);
            const filled = values.length;
            const duplicate = (new Set(values)).size !== values.length;
            
            statusDiv.querySelector('.selected-count').textContent = filled;
            statusDiv.classList.remove('complete', 'over');
            
            if (filled === totalCount && !duplicate) {
                statusDiv.classList.add('complete');
                submitBtn.style.display = 'inline-block';
                submitBtn.disabled = false;
            } else {
                if (duplicate) statusDiv.classList.add('over');
                submitBtn.style.display = 'none';
                submitBtn.disabled = true;
            }
        }
    }
    
    function updateProgressOverview() {
        const progressGrid = document.getElementById('progressGrid');
        const eventBlocks = document.querySelectorAll('.event-block');
        progressGrid.innerHTML = '';
        eventBlocks.forEach(block => {
            const eventNo = block.dataset.event;
            const inputs = block.querySelectorAll('.rank-input');
            const totalCount = parseInt(block.querySelector('.submit-btn').dataset.totalCount);
            let filled = 0;
            inputs.forEach(inp => { if ((inp.value || '').trim()) filled++; });
            const item = document.createElement('div');
            item.className = 'progress-item';
            if (filled === totalCount) {
                item.classList.add('completed');
                item.textContent = `이벤트 ${eventNo} 완료`;
            } else if (filled > 0) {
                item.classList.add('current');
                item.textContent = `이벤트 ${eventNo} ${filled}/${totalCount}`;
            } else {
                item.textContent = `이벤트 ${eventNo} 대기`;
            }
            progressGrid.appendChild(item);
        });
    }
    
    function submitEvent(eventNo) {
        const eventBlock = document.querySelector(`[data-event="${eventNo}"]`);
        const submitBtn = eventBlock.querySelector('.submit-btn');
        const submitType = submitBtn.dataset.type;
        const dance = ACTIVE_DANCE_BY_EVENT[eventNo];
        
        if (!dance) { alert('종목을 선택해주세요.'); return; }
        
        const formData = new FormData();
        formData.append('comp_id', '<?=h($comp_id)?>');
        formData.append('event_no', eventNo);
        formData.append('dance', dance);
        
        if (submitType === 'final') {
            // Final Round: 등위 제출
            const totalCount = parseInt(submitBtn.dataset.totalCount);
            const ranks = getCurrentRanks(eventNo, dance);
            const values = Object.values(ranks);
            if (values.length !== totalCount) { alert('모든 순위를 배정해주세요.'); return; }
            const uniq = new Set(values);
            if (uniq.size !== values.length) { alert('중복된 순위가 있습니다.'); return; }
            
            formData.append('is_final', '1');
            const adjudicator_marks = {};
            adjudicator_marks[CURRENT_JUDGE_ID] = ranks;
            formData.append('adjudicator_marks', JSON.stringify(adjudicator_marks));
        } else {
            // Recall Round: 체크박스 제출
            const recallCount = parseInt(submitBtn.dataset.recallCount);
            const checkboxes = eventBlock.querySelectorAll('.recall-checkbox:checked');
            if (checkboxes.length !== recallCount) { 
                alert(`${recallCount}명을 정확히 선택해주세요.`); 
                return; 
            }
            
            const selectedPlayers = Array.from(checkboxes).map(cb => cb.dataset.player);
            const recallMarks = {};
            recallMarks[dance] = selectedPlayers;
            
            formData.append('type', 'recall');
            formData.append('recall_marks', JSON.stringify(recallMarks));
        }
        
        const originalText = submitBtn.textContent;
        submitBtn.textContent = '전송 중...';
        submitBtn.disabled = true;
        
        fetch('scoring/save_multievent_scores.php', { method: 'POST', body: formData })
        .then(r=>r.json())
        .then(result=>{
            if (result.success) {
                submitBtn.textContent = '완료';
                submitBtn.style.background = '#28a745';
                eventBlock.classList.add('completed');
                updateProgressOverview();
            } else {
                alert('전송 실패: ' + (result.error || '알 수 없는 오류'));
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(err=>{
            console.error(err);
            alert('전송 중 오류가 발생했습니다: ' + err);
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }

    function renderRanksForEventDance(eventNo, dance) {
        const eventBlock = document.querySelector(`[data-event="${eventNo}"]`);
        const submitBtn = eventBlock.querySelector('.submit-btn');
        const isRecall = submitBtn.dataset.type === 'recall';
        
        if (isRecall) {
            // Recall 방식: 체크박스 복원
            document.querySelectorAll(`.event-block[data-event="${eventNo}"] .player-card`).forEach(card => {
                card.classList.remove('selected');
                const checkbox = card.querySelector('.recall-checkbox');
                if (checkbox) checkbox.checked = false;
            });
            
            const ranks = getCurrentRanks(eventNo, dance);
            Object.keys(ranks).forEach(playerNo => {
                if (ranks[playerNo] === 'selected') {
                    const card = document.querySelector(`.event-block[data-event="${eventNo}"] .player-card[data-player="${playerNo}"]`);
                    if (card) {
                        card.classList.add('selected');
                        const checkbox = card.querySelector('.recall-checkbox');
                        if (checkbox) checkbox.checked = true;
                    }
                }
            });
            
            updateRecallStatus(eventNo);
        } else {
            // Final 방식: 순위 슬롯 복원
            document.querySelectorAll(`.event-block[data-event="${eventNo}"] .ranking-slot`).forEach(slot => {
                slot.classList.remove('assigned');
                const span = slot.querySelector('.player-assigned');
                if (span) span.textContent = '';
            });
            document.querySelectorAll(`.player-item[data-event="${eventNo}"]`).forEach(item => {
                item.classList.remove('assigned');
                item.classList.remove('selected');
            });
            
            const ranks = getCurrentRanks(eventNo, dance);
            Object.keys(ranks).forEach(playerNo => {
                const rank = ranks[playerNo];
                if (typeof rank === 'number') {
                    const slot = document.querySelector(`.event-block[data-event="${eventNo}"] .ranking-slot[data-rank="${rank}"]`);
                    if (slot) {
                        slot.classList.add('assigned');
                        const span = slot.querySelector('.player-assigned');
                        if (span) span.textContent = playerNo;
                    }
                    const playerEl = document.querySelector(`.player-item[data-event="${eventNo}"][data-player="${playerNo}"]`);
                    if (playerEl) playerEl.classList.add('assigned');
                }
            });
            
            updateEventStatus(eventNo);
        }
    }

    function assignPlayerToRank(eventNo, dance, playerNo, rankSlotEl) {
        // 랭크에 표시
        rankSlotEl.classList.add('assigned');
        rankSlotEl.querySelector('.player-assigned').textContent = playerNo;
        // 선수 배정 마크
        const playerEl = document.querySelector(`.player-item[data-event="${eventNo}"][data-player="${playerNo}"]`);
        if (playerEl) { playerEl.classList.add('assigned'); playerEl.classList.remove('selected'); }
        // 상태 저장
        if (!RANKINGS_BY_EVENT_AND_DANCE[eventNo]) RANKINGS_BY_EVENT_AND_DANCE[eventNo] = {};
        if (!RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance]) RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance] = {};
        RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance][playerNo] = parseInt(rankSlotEl.dataset.rank, 10);
        // 선택 해제
        SELECTED_PLAYER_BY_EVENT[eventNo] = null;
    }

    function unassignPlayer(eventNo, dance, playerNo, rankSlotEl) {
        rankSlotEl.classList.remove('assigned');
        rankSlotEl.querySelector('.player-assigned').textContent = '';
        const playerEl = document.querySelector(`.player-item[data-event="${eventNo}"][data-player="${playerNo}"]`);
        if (playerEl) { playerEl.classList.remove('assigned'); }
        if (RANKINGS_BY_EVENT_AND_DANCE[eventNo] && RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance]) {
            delete RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance][playerNo];
        }
    }

    function getCurrentRanks(eventNo, dance) {
        const obj = (RANKINGS_BY_EVENT_AND_DANCE[eventNo] && RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance]) ? RANKINGS_BY_EVENT_AND_DANCE[eventNo][dance] : {};
        return obj;
    }

    // Recall system functions
    function togglePlayerSelection(card) {
        const checkbox = card.querySelector('.recall-checkbox');
        checkbox.checked = !checkbox.checked;
        updatePlayerCardState(card);
        updateRecallStatus(card.dataset.event);
    }

    function updatePlayerCardState(card) {
        const checkbox = card.querySelector('.recall-checkbox');
        if (checkbox.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }

    function updateRecallStatus(eventNo) {
        const eventBlock = document.querySelector(`[data-event="${eventNo}"]`);
        const checkboxes = eventBlock.querySelectorAll('.recall-checkbox:checked');
        const submitBtn = eventBlock.querySelector('.submit-btn');
        const recallCount = parseInt(submitBtn.dataset.recallCount || '0');
        const selectedCount = checkboxes.length;
        
        const statusDiv = eventBlock.querySelector('.recall-status');
        const countSpan = statusDiv.querySelector('.selected-count');
        
        if (countSpan) {
            countSpan.textContent = selectedCount;
        }
        
        // Update status styling
        statusDiv.classList.remove('complete', 'over');
        if (selectedCount === recallCount) {
            statusDiv.classList.add('complete');
            submitBtn.style.display = 'inline-block';
            submitBtn.disabled = false;
        } else if (selectedCount > recallCount) {
            statusDiv.classList.add('over');
            submitBtn.style.display = 'none';
            submitBtn.disabled = true;
        } else {
            submitBtn.style.display = 'none';
            submitBtn.disabled = true;
        }
    }
    </script>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <button type="button" id="dashboardBtn" class="nav-button primary">
            <div class="nav-icon">🏠</div>
            <div class="nav-text">메인으로</div>
        </button>
        <button type="button" id="refreshBtn" class="nav-button">
            <div class="nav-icon">🔄</div>
            <div class="nav-text">새로고침</div>
        </button>
    </div>

    <script>
    // Setup navigation buttons
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('dashboardBtn').addEventListener('click', function() {
            const compId = '<?= h($comp_id) ?>';
            const lang = '<?= h($lang) ?>';
            window.location.href = `scoring_dashboard.php?comp_id=${compId}&lang=${lang}`;
        });
        
        document.getElementById('refreshBtn').addEventListener('click', function() {
            if (confirm('페이지를 새로고침하시겠습니까? 현재 작업 중인 내용이 저장되지 않을 수 있습니다.')) {
                window.location.reload();
            }
        });
    });
    </script>
</body>
</html>
