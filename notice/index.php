<?php
$notice_file = "/volume1/web/data/notice.txt";
$schedule_file = "/volume1/web/data/schedule.txt";

$notice = file_exists($notice_file) ? nl2br(htmlspecialchars(file_get_contents($notice_file))) : "등록된 공지가 없습니다.";
$schedule = file_exists($schedule_file) ? nl2br(htmlspecialchars(file_get_contents($schedule_file))) : "등록된 대회일정이 없습니다.";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>공지/현장 안내</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header>
        <h1>공지/현장 안내</h1>
        <nav>
            <a href="/">메인으로</a>
            <a href="/manage/">관리자</a>
        </nav>
    </header>
    <main>
        <section>
            <h2>공지사항</h2>
            <div style="background:#f2f2f2; padding:1em; border-radius:5px;">
                <?= $notice ?>
            </div>
        </section>
        <section>
            <h2>대회 일정</h2>
            <div style="background:#f2f2f2; padding:1em; border-radius:5px;">
                <?= $schedule ?>
            </div>
        </section>
    </main>
    <footer>
        &copy; 2025 danceoffice.net
    </footer>
</body>
</html>