<?php
// 파일 경로 설정 (comp/data 폴더 기준)
$data_dir        = __DIR__ . '/data';
$adjudicator_file = $data_dir . '/Adjudicator.txt';
$adjpanel_file    = $data_dir . '/AdjPanel.txt';
$event_file       = $data_dir . '/events.txt';
$compinfo_file    = $data_dir . '/competition_info.txt';

// data 폴더가 없으면 생성
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0777, true);
}

// 대회명/날짜 저장 및 불러오기
$comp_title = "";
$comp_date = "";
if (file_exists($compinfo_file)) {
    $compinfo = file($compinfo_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $comp_title = isset($compinfo[0]) ? trim($compinfo[0]) : "";
    $comp_date  = isset($compinfo[1]) ? trim($compinfo[1]) : "";
}

// 대회명/날짜 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_compinfo'])) {
    $comp_title = trim($_POST['comp_title']);
    $comp_date  = trim($_POST['comp_date']);
    file_put_contents($compinfo_file, $comp_title . "\n" . $comp_date . "\n");
    $compinfo_msg = "<div class='event-msg success'>대회명/날짜가 저장되었습니다.</div>";
}

// 종목 목록 불러오기
$events = [];
if (file_exists($event_file)) {
    $lines = file($event_file);
    foreach ($lines as $line) {
        $cols = explode(',', trim($line));
        if (count($cols) >= 2) {
            $events[] = ['code' => $cols[0], 'name' => $cols[1]];
        }
    }
}

// 종목 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $delete_code = trim($_POST['delete_code']);
    if ($delete_code) {
        // 해당 종목을 제외한 나머지 종목들로 파일 재작성
        $remaining_events = [];
        foreach ($events as $ev) {
            if ($ev['code'] !== $delete_code) {
                $remaining_events[] = $ev['code'] . ',' . $ev['name'];
            }
        }
        file_put_contents($event_file, implode("\n", $remaining_events) . "\n");
        $message = "<div class='event-msg success'>종목이 삭제되었습니다.</div>";
        
        // 페이지 새로고침을 위해 리다이렉트
        header("Location: admin_event.php");
        exit;
    }
}

// 종목 추가 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $code = trim($_POST['event_code']);
    $name = trim($_POST['event_name']);
    if ($code && $name) {
        // 중복 방지: 이미 존재하는 코드/이름 체크
        $is_duplicate = false;
        foreach ($events as $ev) {
            if ($ev['code'] === $code || $ev['name'] === $name) {
                $is_duplicate = true;
                break;
            }
        }
        if ($is_duplicate) {
            $message = "<div class='event-msg error'>이미 등록된 종목 코드 혹은 이름입니다.</div>";
        } else {
            file_put_contents($event_file, "$code,$name\n", FILE_APPEND);
            header("Location: admin_event.php");
            exit;
        }
    } else {
        $message = "<div class='event-msg error'>종목 코드와 이름을 모두 입력하세요.</div>";
    }
}

// 파일 업로드 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 심사위원 파일
    if (isset($_POST['upload_adj']) && isset($_FILES['adj_upload']) && $_FILES['adj_upload']['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($_FILES['adj_upload']['tmp_name'], $adjudicator_file);
        $message = "<div class='event-msg success'>심사위원 파일을 성공적으로 업로드했습니다.</div>";
    }
    // 패널 파일
    if (isset($_POST['upload_panel']) && isset($_FILES['panel_upload']) && $_FILES['panel_upload']['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($_FILES['panel_upload']['tmp_name'], $adjpanel_file);
        $message = "<div class='event-msg success'>패널 파일을 성공적으로 업로드했습니다.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>대회/종목 관리 - 관리자 | danceoffice.net</title>
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
        .compinfo-form {
            display: flex;
            gap: 0.6em;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1em;
            justify-content: center;
        }
        .compinfo-form label {
            color: #03C75A;
            font-weight: 600;
            margin-bottom: 0.1em;
            font-size: 0.97em;
        }
        .compinfo-form input[type="text"],
        .compinfo-form input[type="date"] {
            padding: 0.28em 0.8em;
            border-radius: 6px;
            border: 1px solid #31343a;
            background: #1E2126;
            color: #fff;
            font-size: 0.98em;
            min-width: 140px;
        }
        .compinfo-form button {
            background: linear-gradient(90deg,#03C75A 70%,#00BFAE 100%);
            color: #222;
            border: none;
            border-radius: 20px;
            padding: 0.39em 1em;
            font-weight: 700;
            font-size: 0.99em;
            margin-left: 0.2em;
        }
        .compinfo-form button:hover {
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
        .add-event-form {
            margin-top: 0.7em;
            display: flex;
            gap: 0.3em;
            flex-wrap: wrap;
            justify-content: center;
        }
        .add-event-form input[type="text"] {
            padding: 0.25em 0.7em;
            border-radius: 6px;
            border: 1px solid #31343a;
            background: #1E2126;
            color: #fff;
            font-size: 0.97em;
        }
        .add-event-form button {
            background: linear-gradient(90deg,#03C75A 70%,#00BFAE 100%);
            color: #222;
            border: none;
            border-radius: 20px;
            padding: 0.34em 1em;
            font-weight: 700;
            font-size: 0.97em;
        }
        .add-event-form button:hover {
            background: linear-gradient(90deg,#00BFAE 60%,#03C75A 100%);
            color: #fff;
        }
        .file-manage {
            margin-top: 1.2em;
        }
        .file-manage label {
            color: #00BFAE;
            font-weight: 600;
            font-size: 0.98em;
        }
        .file-manage input[type="file"] {
            margin-left: 0.4em;
            font-size: 0.97em;
        }
        .file-manage button {
            margin-left: 0.3em;
            padding: 0.25em 0.7em;
            font-size: 0.97em;
            border-radius: 10px;
            border: none;
            background: linear-gradient(90deg,#03C75A 70%,#00BFAE 100%);
            color: #222;
            font-weight: 700;
        }
        .file-manage button:hover {
            background: linear-gradient(90deg,#00BFAE 60%,#03C75A 100%);
            color: #fff;
        }
        .btn-judge {
            background: #0057b7;
            color: #fff;
            font-weight: 700;
            padding: 0.25em 0.7em;
            border-radius: 12px;
            text-decoration: none;
            margin: 0 0.09em;
            display: inline-block;
            font-size: 0.97em;
            transition: background 0.14s;
            border: none;
        }
        .btn-judge:hover {
            background: #003399;
        }
        .btn-result {
            background: #03C75A;
            color: #222;
            font-weight: 700;
            padding: 0.25em 0.7em;
            border-radius: 12px;
            text-decoration: none;
            margin: 0 0.09em;
            display: inline-block;
            font-size: 0.97em;
            border: none;
            transition: background 0.14s;
        }
        .btn-result:hover {
            background: #00BFAE;
            color: #fff;
        }
        .btn-delete {
            background: #dc3545;
            color: #fff;
            font-weight: 700;
            padding: 0.25em 0.7em;
            border-radius: 12px;
            text-decoration: none;
            margin: 0 0.09em;
            display: inline-block;
            font-size: 0.97em;
            border: none;
            cursor: pointer;
            transition: background 0.14s;
        }
        .btn-delete:hover {
            background: #c82333;
        }
        .info-tip {
            color: #8af;
            font-size: 0.93em;
            margin-top: 1.1em;
            line-height: 1.55;
        }
        @media (max-width: 900px) {
            .admin-section { max-width: 99vw; padding: 0.6em 0.1em 0.6em 0.1em;}
            .admin-title { font-size: 1em; }
            .event-table th, .event-table td {font-size: 0.91em; padding: 0.26em 0.08em;}
            .compinfo-form, .add-event-form {gap:0.19em;}
            .btn-judge, .btn-result, .btn-delete, .add-event-form button, .compinfo-form button, .file-manage button {
                font-size: 0.89em; padding: 0.19em 0.5em;
            }
        }
        @media (max-width: 600px) {
            .admin-section { padding: 0.28em 0.03em;}
            .admin-title { font-size: 0.93em;}
            .compinfo-form, .add-event-form { flex-direction: column; align-items: stretch;}
            .event-table th, .event-table td { font-size: 0.89em; padding: 0.11em 0.07em;}
            .btn-judge, .btn-result, .btn-delete, .add-event-form button, .compinfo-form button, .file-manage button {
                font-size: 0.89em; padding: 0.14em 0.4em;
            }
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
    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo-nav">
            <img src="/assets/danceoffice-logo.png" alt="Danceoffice Logo" class="main-logo">
            <nav class="main-nav">
                <a href="/">홈</a>
                <a href="/comp/admin_event.php" class="active">대회/종목 관리</a>
                <a href="/comp/admin_players.php">선수 관리</a>
                <a href="/comp/result/result.php?event=<?= urlencode($ev['code'] ?? '') ?>" class="btn-result" target="_blank">채점 결과</a>
            </nav>
        </div>
    </header>
    <main>
        <section class="admin-section">
            <div class="admin-title">
                <span class="material-symbols-rounded">event</span> 대회/종목 관리
            </div>
            <?php if (isset($compinfo_msg)) echo $compinfo_msg; ?>
            <form class="compinfo-form" method="post">
                <label for="comp_title">대회명</label>
                <input type="text" name="comp_title" id="comp_title" value="<?= htmlspecialchars($comp_title) ?>" placeholder="예: 2025 전국댄스경연대회" required>
                <label for="comp_date">대회일</label>
                <input type="date" name="comp_date" id="comp_date" value="<?= htmlspecialchars($comp_date) ?>" required>
                <button type="submit" name="save_compinfo"><span class="material-symbols-rounded">save</span> 저장</button>
            </form>
            <?php if (isset($message)) echo $message; ?>
            <table class="event-table">
                <thead>
                    <tr>
                        <th>종목 코드</th>
                        <th>종목 이름</th>
                        <th>프리스타일 채점</th>
                        <th>채점 결과</th>
                        <th>삭제</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($events as $ev): ?>
                    <tr>
                        <td><?= htmlspecialchars($ev['code']) ?></td>
                        <td><?= htmlspecialchars($ev['name']) ?></td>
                        <td>
                            <a href="judge_login.php?event=<?= urlencode($ev['code']) ?>"
                               class="btn-judge" target="_blank">🎭 프리스타일 채점</a>
                        </td>
                        <td>
                            <a href="/comp/result/result.php?event=<?= urlencode($ev['code']) ?>"
                               class="btn-result" target="_blank">채점 결과</a>
                        </td>
                        <td>
                            <button type="button" class="btn-delete" onclick="deleteEvent('<?= htmlspecialchars($ev['code']) ?>', '<?= htmlspecialchars($ev['name']) ?>')">
                                <span class="material-symbols-rounded">delete</span> 삭제
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <form class="add-event-form" method="post">
                <input type="text" name="event_code" placeholder="종목 코드 (예: DUO2)" required>
                <input type="text" name="event_name" placeholder="종목 이름 (예: 듀오 프리스타일 Class 2)" required>
                <button type="submit" name="add_event"><span class="material-symbols-rounded">add</span> 종목 추가</button>
            </form>
            <div class="file-manage">
                <form method="post" enctype="multipart/form-data">
                    <label for="adj_upload">심사위원 파일(Adjudicator.txt) 업로드:</label>
                    <input type="file" name="adj_upload" id="adj_upload" accept=".txt">
                    <button type="submit" name="upload_adj">업로드</button>
                </form>
                <form method="post" enctype="multipart/form-data" style="margin-top:1em;">
                    <label for="panel_upload">패널 파일(AdjPanel.txt) 업로드:</label>
                    <input type="file" name="panel_upload" id="panel_upload" accept=".txt">
                    <button type="submit" name="upload_panel">업로드</button>
                </form>
            </div>
            <div class="info-tip">
                ※ <b>대회명/일</b>은 반드시 저장 후 심사위원/종목 채점에 반영됩니다.<br>
                ※ <b>심사위원 채점</b> 버튼을 누르면 해당 종목의 심사위원 채점 입력 페이지로 이동합니다.<br>
                ※ <b>채점 결과</b> 버튼은 종목별 점수 집계 결과(출력/인쇄 가능 표)를 보여줍니다.<br>
                ※ 심사위원별 개별 진입이 필요하면 judge_score.php?event=이벤트코드&judge=J01 등으로 추가 구현할 수 있습니다.
            </div>
        </section>
    </main>
    <footer class="main-footer">
        &copy; 2025 danceoffice.net | Powered by Seyoung Lee
    </footer>

    <!-- 종목 삭제용 숨겨진 폼 -->
    <form id="deleteEventForm" method="post" style="display: none;">
        <input type="hidden" name="delete_event" value="1">
        <input type="hidden" name="delete_code" id="deleteCode" value="">
    </form>

    <script>
    function deleteEvent(eventCode, eventName) {
        if (confirm(`종목 "${eventName}" (${eventCode})을(를) 정말 삭제하시겠습니까?\n\n삭제된 종목은 복구할 수 없습니다.`)) {
            document.getElementById('deleteCode').value = eventCode;
            document.getElementById('deleteEventForm').submit();
        }
    }
    </script>
</body>
</html>