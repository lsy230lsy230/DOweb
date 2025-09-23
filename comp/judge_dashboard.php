<?php
session_start();

// Check if judge is logged in (기존 프리스타일 시스템 세션 확인)
if (!isset($_SESSION['judge_id']) || !isset($_SESSION['judge_name'])) {
    header("Location: judge_login.php");
    exit;
}

// Load competition info from current data structure
$data_dir = __DIR__ . "/data";
$comp_info_file = "$data_dir/competition_info.txt";

if (!file_exists($comp_info_file)) {
    echo "<h1>대회 정보를 찾을 수 없습니다.</h1>";
    exit;
}

// Load competition info
$comp_info = file($comp_info_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$info = [
    'title' => $comp_info[0] ?? '대회',
    'date' => $comp_info[1] ?? '',
    'place' => $comp_info[2] ?? ''
];

// Load events from current data structure
$events_file = "$data_dir/events.txt";
$events = [];

if (file_exists($events_file)) {
    $lines = file($events_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 2) {
            $events[] = [
                'code' => $cols[0],
                'name' => $cols[1],
                'type' => 'freestyle' // 프리스타일 이벤트로 설정
            ];
        }
    }
}

// Check which events have scores
$events_with_scores = [];
foreach ($events as $event) {
    $scores_file = "$data_dir/scores/score_{$event['code']}_J{$_SESSION['judge_id']}.txt";
    $events_with_scores[$event['code']] = file_exists($scores_file);
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>심사위원 대시보드 | <?=h($info['title'])?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { 
            background: #f5f7fa; 
            font-family: sans-serif; 
            margin: 0; 
            padding: 20px;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: #fff; 
            border-radius: 12px; 
            padding: 30px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        h1 { 
            color: #333; 
            margin: 0; 
            font-size: 1.8em;
        }
        .judge-info {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 8px;
            color: #1976d2;
            font-weight: 600;
        }
        .logout-btn {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .event-card {
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .event-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
        }
        .event-card.scored {
            border-color: #28a745;
            background: #f8fff9;
        }
        .event-card.scored:hover {
            border-color: #1e7e34;
        }
        .event-title {
            font-size: 1.2em;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }
        .event-details {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        .event-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: #fff;
        }
        .btn-primary:hover {
            background: #5a6fd8;
        }
        .btn-success {
            background: #28a745;
            color: #fff;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        .btn-info {
            background: #17a2b8;
            color: #fff;
        }
        .btn-info:hover {
            background: #138496;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>심사위원 대시보드</h1>
            <div class="judge-info">
                <?=h($_SESSION['judge_name'])?> - 심사위원 #<?=h($_SESSION['judge_id'])?>
            </div>
        </div>
        <a href="judge_login.php" class="logout-btn">로그아웃</a>
    </div>
    
    <div class="comp-info" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
        <strong><?=h($info['title'])?></strong><br>
        <?=h($info['date'])?> | <?=h($info['place'])?>
    </div>
    
    <?php if (empty($events)): ?>
        <div class="empty-state">
            <h3>등록된 이벤트가 없습니다</h3>
            <p>대회 관리자에게 문의하세요.</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($events as $event): ?>
            <div class="event-card <?=$events_with_scores[$event['code']] ? 'scored' : ''?>">
                <div class="status-badge <?=$events_with_scores[$event['code']] ? 'status-completed' : 'status-pending'?>">
                    <?=$events_with_scores[$event['code']] ? '채점 완료' : '채점 대기'?>
                </div>
                
                <div class="event-title"><?=h($event['name'])?></div>
                <div class="event-details">
                    <strong>종목 코드: <?=h($event['code'])?></strong><br>
                    <strong>타입: 프리스타일 채점</strong>
                </div>
                
                <div class="event-actions">
                    <a href="judge_scoring_free.php?event=<?=h($event['code'])?>" 
                       class="btn btn-primary" 
                       target="_blank">
                        🎭 프리스타일 채점
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>