<?php
session_start();
if (!isset($_SESSION['judge_id']) || !isset($_SESSION['judge_name'])) {
    header("Location: judge_login.php");
    exit;
}

// 로그아웃 처리
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: judge_login.php");
    exit;
}

// 심사위원 정보
$judge_name = $_SESSION['judge_name'];
$judge_id = $_SESSION['judge_id'];

// 대회 정보 및 채점 페이지 등 추가 안내 (확장 가능)
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>심사위원 패널 | danceoffice.net</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .panel-section {background:#23262b; border-radius:14px; padding:2em 2em 2.5em 2em; max-width:500px; margin:3em auto;}
        .panel-title {font-size:1.3em; font-weight:700; color:#03C75A; margin-bottom:1.1em;}
        .panel-info {color:#F5F7FA; margin-bottom:2em;}
        .panel-nav {margin-top:2em;}
        .panel-nav a {color:#03C75A; font-weight:700; text-decoration:none; padding:0.5em 1.2em; border-radius:18px; background:#181B20; margin-right:1em;}
        .panel-nav a:hover {background:#00BFAE; color:#fff;}
        .logout-btn {background:#ff5e5e; color:#fff; border:none; border-radius:20px; padding:0.5em 1.4em; font-weight:700; margin-top:2.5em;}
        .logout-btn:hover {background:#c93;}
    </style>
</head>
<body>
    <main>
        <section class="panel-section">
            <div class="panel-title">심사위원 패널</div>
            <div class="panel-info">
                <b><?= htmlspecialchars($judge_name) ?></b> 심사위원님 안녕하세요.<br>
                (ID: <?= htmlspecialchars($judge_id) ?>)
            </div>
            <div>
                <ul>
                    <li>심사 채점은 아래 "채점 페이지로 이동" 버튼을 눌러 진행하세요.</li>
                    <li>대회 중에는 채점 결과가 실시간으로 저장됩니다.</li>
                    <li>채점이 끝난 후 반드시 <b>로그아웃</b> 해주세요.</li>
                </ul>
            </div>
            <div class="panel-nav">
                <a href="judge_scoring.php">채점 페이지로 이동</a>
            </div>
            <form method="get" action="judge_panel.php">
                <button type="submit" name="logout" value="1" class="logout-btn">로그아웃</button>
            </form>
        </section>
    </main>
</body>
</html>