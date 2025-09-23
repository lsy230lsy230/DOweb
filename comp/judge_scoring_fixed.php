<?php
// 에러 리포팅 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$multievent = $_GET['multievent'] ?? '0';
$lang = $_GET['lang'] ?? 'ko';

// 임시로 세션 검증 우회 (디버깅용)
if (!isset($_SESSION['scoring_logged_in']) || !$_SESSION['scoring_logged_in']) {
    $_SESSION['scoring_logged_in'] = true;
    $_SESSION['scoring_comp_id'] = $comp_id;
    $_SESSION['scoring_judge_id'] = '20';
    $_SESSION['scoring_judge_name'] = 'Test Judge';
}

// 기본 검증
if (!$comp_id || !$event_no) {
    echo "<h1>잘못된 대회 ID 또는 이벤트 번호입니다.</h1>";
    exit;
}

// 파일 로딩
$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$players_file = "$data_dir/players_$event_no.txt";

if (!file_exists($players_file)) {
    echo "<h1>선수 파일을 찾을 수 없습니다: " . $players_file . "</h1>";
    exit;
}

// 선수 데이터 로딩
$players = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// 이벤트 데이터 로딩
$event_data = null;
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = preg_replace('/\x{FEFF}/u', '', $line);
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 2 && $cols[0] == $event_no) {
            $event_data = [
                'no' => $cols[0],
                'name' => $cols[1],
                'round' => $cols[2] ?? 'Final',
                'dances' => array_filter(array_slice($cols, 6, 5), function($d) { return $d && $d != '0'; })
            ];
            break;
        }
    }
}

if (!$event_data) {
    echo "<h1>이벤트 데이터를 찾을 수 없습니다.</h1>";
    exit;
}

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>심사위원 채점 시스템</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .event-info { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .players { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .player { background: #e9e9e9; padding: 10px; border-radius: 5px; text-align: center; }
        .submit-btn { background: #007cba; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin-top: 20px; }
        .submit-btn:hover { background: #005a87; }
    </style>
</head>
<body>
    <div class="container">
        <h1>심사위원 채점 시스템</h1>
        
        <div class="event-info">
            <h2>이벤트 정보</h2>
            <p><strong>이벤트 번호:</strong> <?= htmlspecialchars($event_data['no']) ?></p>
            <p><strong>이벤트명:</strong> <?= htmlspecialchars($event_data['name']) ?></p>
            <p><strong>라운드:</strong> <?= htmlspecialchars($event_data['round']) ?></p>
            <p><strong>종목:</strong> <?= htmlspecialchars(implode(', ', $event_data['dances'])) ?></p>
            <p><strong>심사위원:</strong> <?= htmlspecialchars($_SESSION['scoring_judge_name']) ?> (ID: <?= htmlspecialchars($_SESSION['scoring_judge_id']) ?>)</p>
        </div>
        
        <div class="players">
            <h2>출전 선수</h2>
            <?php foreach ($players as $player): ?>
                <div class="player">
                    <strong>선수 번호: <?= htmlspecialchars($player) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        
        <button class="submit-btn" onclick="alert('채점 기능은 아직 구현되지 않았습니다.')">
            채점하기
        </button>
        
        <p><a href="scoring_dashboard.php?comp_id=<?= urlencode($comp_id) ?>&lang=<?= urlencode($lang) ?>">대시보드로 돌아가기</a></p>
    </div>
</body>
</html>






