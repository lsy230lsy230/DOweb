<?php
// 파일 경로 설정
$data_dir = __DIR__ . '/data';
$event_file = $data_dir . '/events.txt';
$event_players_dir = $data_dir . '/event_players';
$competitor_file = $data_dir . '/Competitor_Tablet.txt';

// 폴더 없으면 생성
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0777, true);
}
if (!is_dir($event_players_dir)) {
    mkdir($event_players_dir, 0777, true);
}

// 이벤트 목록 불러오기
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

// 선택된 이벤트의 선수목록 불러오기
$selected_event = isset($_GET['event']) ? $_GET['event'] : (isset($events[0]) ? $events[0]['code'] : '');
$event_players_file = $event_players_dir . "/{$selected_event}_players.txt";
$event_players = [];
if ($selected_event && file_exists($event_players_file)) {
    $lines = file($event_players_file);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 2) {
            $event_players[] = [
                'number' => $cols[0],
                'name' => $cols[1],
                'partner' => isset($cols[2]) ? $cols[2] : ''
            ];
        }
    }
}

// 선수명단 전체 파일에서 데이터 불러오기(자동 입력용)
$all_players = [];
if (file_exists($competitor_file)) {
    $lines = file($competitor_file);
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 2) {
            $number = $cols[0];
            $name = $cols[1];
            $partner = isset($cols[2]) ? $cols[2] : '';
            $all_players[$number] = ['name'=>$name, 'partner'=>$partner];
        }
    }
}

// 선수 추가 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_player']) && $selected_event) {
    $number = trim($_POST['player_number']);
    $name = trim($_POST['player_name']);
    $partner = trim($_POST['player_partner']);
    if ($number && $name) {
        // 중복 등번호 체크
        $is_duplicate = false;
        foreach ($event_players as $ep) {
            if ($ep['number'] === $number) {
                $is_duplicate = true;
                break;
            }
        }
        if ($is_duplicate) {
            $message = "<div class='event-msg error'>이미 등록된 등번호입니다.</div>";
        } else {
            file_put_contents($event_players_file, "$number,$name,$partner\n", FILE_APPEND);
            header("Location: admin_players.php?event=$selected_event");
            exit;
        }
    } else {
        $message = "<div class='event-msg error'>번호와 이름을 모두 입력하세요.</div>";
    }
}

// 선수 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_player']) && $selected_event) {
    $delete_number = $_POST['delete_number'];
    $new_players = [];
    foreach ($event_players as $ep) {
        if ($ep['number'] !== $delete_number) {
            $new_players[] = $ep;
        }
    }
    $fp = fopen($event_players_file, 'w');
    foreach ($new_players as $ep) {
        fwrite($fp, "{$ep['number']},{$ep['name']},{$ep['partner']}\n");
    }
    fclose($fp);
    header("Location: admin_players.php?event=$selected_event");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>이벤트별 선수 관리 - 관리자 | danceoffice.net</title>
    <link rel="stylesheet" href="/assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <style>
        html, body {
            background: #23262b !important;
            margin: 0;
            font-family: 'Pretendard','Apple SD Gothic Neo',sans-serif;
            font-size: 15px;
        }
        body {
            min-height: 100vh;
            padding: 0;
        }
        .admin-section {
            background: #23262b;
            border-radius: 14px;
            padding: 1.2em 1em 1.7em 1em;
            max-width: 630px;
            width: 96vw;
            margin: 3vh auto 0 auto;
            box-shadow: 0 4px 24px rgba(0,0,0,0.11);
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        .admin-title {
            font-size: 1.17em;
            font-weight: 700;
            color: #03C75A;
            margin-bottom: 0.7em;
            letter-spacing: -1px;
            text-align: center;
        }
        .event-select-form {
            margin-bottom: 1.3em;
            text-align: center;
        }
        .event-select-form label {
            color: #03C75A;
            font-weight: 600;
            font-size: 0.98em;
        }
        .event-select-form select {
            padding: 0.32em 0.8em;
            border-radius: 8px;
            background: #181B20;
            color: #03C75A;
            border: 1px solid #31343a;
            font-size: 0.99em;
            margin-left: 0.5em;
        }
        .event-table {
            width: 100%;
            background: #222;
            border-radius: 8px;
            border-collapse: separate;
            border-spacing: 0 0.3em;
            margin-bottom: 1.1em;
        }
        .event-table th, .event-table td {
            padding: 0.37em 0.2em;
            border-bottom: 1px solid #31343a;
        }
        .event-table th {
            background: #181B20;
            color: #03C75A;
            font-size: 0.98em;
            font-weight: 700;
        }
        .event-table td {
            color: #F5F7FA;
            font-size: 0.98em;
        }
        .event-table tr:last-child td {border-bottom:none;}
        .add-player-form {
            margin-top: 0.6em;
            display: flex;
            gap: 0.3em;
            flex-wrap: wrap;
            justify-content: center;
        }
        .add-player-form input[type="text"] {
            padding: 0.25em 0.7em;
            border-radius: 6px;
            border: 1px solid #31343a;
            background: #1E2126;
            color: #fff;
            font-size: 0.97em;
        }
        .add-player-form button {
            background: linear-gradient(90deg,#03C75A 70%,#00BFAE 100%);
            color: #222;
            border: none;
            border-radius: 20px;
            padding: 0.34em 1em;
            font-weight: 700;
            font-size: 0.97em;
        }
        .add-player-form button:hover {
            background: linear-gradient(90deg,#00BFAE 60%,#03C75A 100%);
            color: #fff;
        }
        .event-msg {
            margin: 0.7em 0;
            padding: 0.6em 0.7em;
            border-radius: 8px;
            font-size: 0.98em;
        }
        .event-msg.success {background:#232; color:#03C75A;}
        .event-msg.error {background:#311; color:#ff5e5e;}
        .delete-btn {
            background: #ff5e5e;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 0.21em 0.8em;
            font-weight: 600;
            font-size: 0.97em;
            cursor: pointer;
        }
        .delete-btn:hover {
            background: #d32f2f;
        }
        .main-header, .main-footer {
            max-width: 630px;
            margin: 0 auto;
            color: #aaa;
            text-align: center;
            letter-spacing: 0.02em;
        }
        .main-header {
            margin-top: 1.1em;
            margin-bottom: 1.1em;
        }
        .main-logo {
            height: 32px;
            vertical-align: middle;
            margin-right: 0.4em;
        }
        .main-nav {
            display: inline-block;
            margin-left: 0.6em;
            vertical-align: middle;
        }
        .main-nav a {
            color: #bbb;
            text-decoration: none;
            margin: 0 0.41em;
            font-weight: 600;
            font-size: 0.98em;
            padding-bottom: 0.07em;
            border-bottom: 2px solid transparent;
            transition: color 0.12s, border-color 0.12s;
        }
        .main-nav a.active, .main-nav a:hover {
            color: #03C75A;
            border-bottom: 2px solid #03C75A;
        }
        .main-footer {
            margin-top: 2em;
            padding-bottom: 0.6em;
            color: #888;
            font-size: 0.92em;
        }
        @media (max-width: 900px) {
            .admin-section { max-width: 99vw; padding: 0.6em 0.1em 0.6em 0.1em;}
            .admin-title { font-size: 1em; }
            .event-table th, .event-table td {font-size: 0.91em; padding: 0.26em 0.08em;}
            .add-player-form {gap:0.19em;}
            .add-player-form button, .delete-btn {font-size: 0.89em; padding: 0.19em 0.5em;}
        }
        @media (max-width: 600px) {
            .admin-section { padding: 0.28em 0.03em;}
            .admin-title { font-size: 0.93em;}
            .add-player-form { flex-direction: column; align-items: stretch;}
            .event-table th, .event-table td { font-size: 0.89em; padding: 0.11em 0.07em;}
            .add-player-form button, .delete-btn {font-size: 0.89em; padding: 0.14em 0.4em;}
        }
    </style>
    <script>
        // 선수명 자동 입력 기능
        const playerData = <?php echo json_encode($all_players); ?>;
        function fillPlayerName() {
            const number = document.getElementById('player_number').value.trim();
            if (playerData[number]) {
                document.getElementById('player_name').value = playerData[number].name;
                document.getElementById('player_partner').value = playerData[number].partner;
            } else {
                document.getElementById('player_name').value = '';
                document.getElementById('player_partner').value = '';
            }
        }
    </script>
</head>
<body>
    <header class="main-header">
        <div class="logo-nav">
            <img src="/assets/danceoffice-logo.png" alt="Danceoffice Logo" class="main-logo">
            <nav class="main-nav">
                <a href="/">홈</a>
                <a href="/comp/admin_event.php">대회/종목 관리</a>
                <a href="/comp/admin_players.php" class="active">선수 전체 관리</a>
                <a href="/comp/result/">채점/결과 관리</a>
            </nav>
        </div>
    </header>
    <main>
        <section class="admin-section">
            <div class="admin-title">
                <span class="material-symbols-rounded">groups</span> 선수 관리
            </div>
            <form class="event-select-form" method="get">
                <label for="event_select">종목 선택:</label>
                <select name="event" id="event_select" onchange="this.form.submit()">
                    <?php foreach($events as $ev): ?>
                        <option value="<?= htmlspecialchars($ev['code']) ?>" <?= $selected_event === $ev['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ev['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if (isset($message)) echo $message; ?>
            <table class="event-table">
                <thead>
                    <tr>
                        <th>번호</th>
                        <th>이름</th>
                        <th>소속</th>
                        <th>삭제</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($event_players as $ep): ?>
                    <tr>
                        <td><?= htmlspecialchars($ep['number']) ?></td>
                        <td><?= htmlspecialchars($ep['name']) ?></td>
                        <td><?= htmlspecialchars($ep['partner']) ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="delete_number" value="<?= htmlspecialchars($ep['number']) ?>">
                                <button type="submit" name="delete_player" class="delete-btn">삭제</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($selected_event): ?>
            <form class="add-player-form" method="post">
                <input type="text" name="player_number" id="player_number" placeholder="번호 (예: 43)" required oninput="fillPlayerName()">
                <input type="text" name="player_name" id="player_name" placeholder="이름 (예: 문진호)" required>
                <input type="text" name="player_partner" id="player_partner" placeholder="파트너 (예: 노명주 또는 공란)">
                <button type="submit" name="add_player"><span class="material-symbols-rounded">add</span> 선수 추가</button>
            </form>
            <div style="margin-top:1em; color:#888; font-size:0.93em;">
                ※ 등번호를 입력하면 <b>Competitor_Tablet.txt</b>에 등록된 선수명/파트너가 자동으로 채워집니다.
            </div>
            <?php endif; ?>
        </section>
    </main>
    <footer class="main-footer">
        &copy; 2025 danceoffice.net | Powered by Seyoung Lee
    </footer>
</body>
</html>