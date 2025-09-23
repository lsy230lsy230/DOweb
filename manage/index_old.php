<?php
session_start();
if (!isset($_SESSION['admin'])) {
    if ($_POST['pw'] ?? '' === 'adminpw') {
        $_SESSION['admin'] = true;
    } else {
        ?>
        <form method="post">
            <label>관리자 비밀번호: <input type="password" name="pw"></label>
            <button type="submit">로그인</button>
        </form>
        <?php
        exit;
    }
}

// 광고 위치별 안내/권장 사이즈 정보
$positions = [
    'top'    => ['label'=>'상단',   'size'=>'728x90px',    'desc'=>'상단 가로 배너 (PC/모바일 모두 노출)',         'img'=>'top.jpg',   'link'=>'top.link'],
    'bottom' => ['label'=>'하단',   'size'=>'728x90px',    'desc'=>'하단 가로 배너 (PC/모바일 모두 노출)',         'img'=>'bottom.jpg','link'=>'bottom.link'],
    'left'   => ['label'=>'좌측',   'size'=>'160x600px',   'desc'=>'좌측 세로 배너 (PC 화면만 노출)',              'img'=>'left.jpg',  'link'=>'left.link'],
    'right'  => ['label'=>'우측',   'size'=>'160x600px',   'desc'=>'우측 세로 배너 (PC 화면만 노출)',              'img'=>'right.jpg', 'link'=>'right.link'],
    'main'   => ['label'=>'메인',   'size'=>'300x250px 또는 728x90px', 'desc'=>'메인/이벤트 영역 (크기 가변)',    'img'=>'main.jpg',  'link'=>'main.link'],
];

// NAS 실경로
$base_ads_dir = "/volume1/web/ads/";

// 공지/일정 파일 경로
$notice_file = "/volume1/web/data/notice.txt";
$schedule_file = "/volume1/web/data/schedule.txt";

// 공지/일정 삭제 처리
if (isset($_GET['delete_notice'])) {
    if (file_exists($notice_file)) unlink($notice_file);
    header("Location: /manage/");
    exit;
}
if (isset($_GET['delete_schedule'])) {
    if (file_exists($schedule_file)) unlink($schedule_file);
    header("Location: /manage/");
    exit;
}

// 광고 삭제 처리
if (isset($_GET['delete']) && isset($_GET['pos']) && isset($positions[$_GET['pos']])) {
    $pos = $_GET['pos'];
    $type = $_GET['delete']; // 'img' or 'link'
    if ($type === 'img') {
        $file = $base_ads_dir . $positions[$pos]['img'];
        if (file_exists($file)) unlink($file);
    } elseif ($type === 'link') {
        $file = $base_ads_dir . $positions[$pos]['link'];
        if (file_exists($file)) unlink($file);
    }
    header("Location: /manage/");
    exit;
}

// 광고 업로드/링크 등록
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos'])) {
    $pos = $_POST['pos'];
    if (isset($_FILES['banner']) && $_FILES['banner']['size'] > 0) {
        $target = $base_ads_dir . $positions[$pos]['img'];
        move_uploaded_file($_FILES['banner']['tmp_name'], $target);
    }
    if (isset($_POST['banner_link'])) {
        $linkfile = $base_ads_dir . $positions[$pos]['link'];
        file_put_contents($linkfile, trim($_POST['banner_link']));
    }
    echo "<script>location.href='/manage/';</script>";
    exit;
}

// 공지사항 등록/수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notice'])) {
    file_put_contents($notice_file, trim($_POST['notice']));
    echo "<script>location.href='/manage/';</script>";
    exit;
}

// 대회일정 등록/수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule'])) {
    file_put_contents($schedule_file, trim($_POST['schedule']));
    echo "<script>location.href='/manage/';</script>";
    exit;
}

$current_notice = file_exists($notice_file) ? file_get_contents($notice_file) : "";
$current_schedule = file_exists($schedule_file) ? file_get_contents($schedule_file) : "";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>관리자 페이지 - 광고/공지/일정 관리</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .banner-thumb {max-width:200px; margin:0.5em;}
        .admin-section {margin-bottom:2em;}
        .banner-info {background:#f9f9f9; border-radius:5px; padding:0.8em; margin-bottom:0.5em; font-size:0.98em;}
        .file-tip {font-size:0.93em; color:#555; margin-bottom:0.4em;}
        .del-btn {color:#c00; font-size:0.92em; margin-left:0.7em;}
        .del-btn:hover {text-decoration:underline;}
        textarea {width:98%; height:6em;}
        .notice-preview, .schedule-preview {
            background: #fcfcfc;
            border-radius: 5px;
            padding: 0.7em;
            margin-bottom: 0.6em;
            font-size: 1em;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <h1 class="admin-title">
            <span class="material-symbols-rounded">admin_panel_settings</span>
            관리자 페이지
        </h1>
        <p class="admin-subtitle">danceoffice.net 관리 시스템</p>
        <nav class="admin-nav">
            <a href="/">
                <span class="material-symbols-rounded">home</span>
                메인으로
            </a>
            <a href="/results/">
                <span class="material-symbols-rounded">sports_score</span>
                경기 결과
            </a>
            <a href="/comp/admin_event.php">
                <span class="material-symbols-rounded">event</span>
                이벤트 관리
            </a>
        </nav>
    </header>
    <main>
        <section class="admin-section">
            <h2>공지사항 관리</h2>
            <form method="post" style="border:1px solid #ddd; padding:1em; margin-bottom:1em;">
                <h3>공지사항 <span style="font-size:0.9em;color:#777;">(텍스트 입력, 줄바꿈 가능)</span></h3>
                <div class="banner-info">
                    <span class="file-tip">
                        <b>파일명:</b> <code>notice.txt</code><br>
                        <b>수동 경로:</b> <code>/volume1/web/data/notice.txt</code>
                    </span><br>
                    <b>수동 설정법:</b><br>
                    - <b>notice.txt</b> 파일을 직접 편집해도 적용됩니다.<br>
                    - 내용이 없으면 공지사항이 표시되지 않습니다.<br>
                </div>
                <div class="notice-preview">
                    <strong>현재 등록된 공지사항:</strong><br>
                    <?= nl2br(htmlspecialchars($current_notice)) ?>
                    <?php if ($current_notice): ?>
                        <a href="/manage/?delete_notice=1" class="del-btn" onclick="return confirm('공지사항을 삭제할까요?')">[공지사항 삭제]</a>
                    <?php endif; ?>
                </div>
                <textarea name="notice" placeholder="공지 내용을 입력하세요"><?= htmlspecialchars($current_notice) ?></textarea>
                <button type="submit">공지 저장</button>
            </form>
        </section>
        <section class="admin-section">
            <h2>대회 일정 관리</h2>
            <form method="post" style="border:1px solid #ddd; padding:1em; margin-bottom:1em;">
                <h3>대회 일정 <span style="font-size:0.9em;color:#777;">(텍스트 입력, 줄바꿈 가능)</span></h3>
                <div class="banner-info">
                    <span class="file-tip">
                        <b>파일명:</b> <code>schedule.txt</code><br>
                        <b>수동 경로:</b> <code>/volume1/web/data/schedule.txt</code>
                    </span><br>
                    <b>수동 설정법:</b><br>
                    - <b>schedule.txt</b> 파일을 직접 편집해도 적용됩니다.<br>
                    - 내용이 없으면 대회일정이 표시되지 않습니다.<br>
                </div>
                <div class="schedule-preview">
                    <strong>현재 등록된 대회 일정:</strong><br>
                    <?= nl2br(htmlspecialchars($current_schedule)) ?>
                    <?php if ($current_schedule): ?>
                        <a href="/manage/?delete_schedule=1" class="del-btn" onclick="return confirm('대회일정을 삭제할까요?')">[대회일정 삭제]</a>
                    <?php endif; ?>
                </div>
                <textarea name="schedule" placeholder="대회 일정을 입력하세요"><?= htmlspecialchars($current_schedule) ?></textarea>
                <button type="submit">일정 저장</button>
            </form>
        </section>
        <section class="admin-section">
            <h2>광고 배너 관리</h2>
            <?php foreach($positions as $pos=>$data): ?>
            <form method="post" enctype="multipart/form-data" style="border:1px solid #ddd; padding:1em; margin-bottom:1em;">
                <h3>
                    <?= $data['label'] ?> 배너
                    <span style="font-size:0.9em;color:#777;">(권장: <?= $data['size'] ?>)</span>
                </h3>
                <div class="banner-info">
                    <?= $data['desc'] ?><br>
                    <span class="file-tip">
                        <b>이미지 파일명:</b> <code><?= $data['img'] ?></code> <br>
                        <b>링크 파일명:</b> <code><?= $data['link'] ?></code> <br>
                        <b>수동 설정 경로:</b> <code>/volume1/web/ads/<?= $data['img'] ?></code>, <code>/volume1/web/ads/<?= $data['link'] ?></code>
                    </span><br>
                    <b>수동 설정법:</b> <br>
                    - 이미지: 해당 위치에 맞는 사이즈로 <b><?= $data['img'] ?></b> 파일을 직접 올리세요.<br>
                    - 링크: <b><?= $data['link'] ?></b> 파일에 한 줄로 URL을 입력하면 됩니다.<br>
                    - 이미지가 없으면 해당 위치에 배너가 보이지 않습니다.<br>
                </div>
                <?php
                $img_file_web = "/ads/" . $data['img'];
                $img_file_real = $base_ads_dir . $data['img'];
                $link_file = $base_ads_dir . $data['link'];
                $link = file_exists($link_file) ? file_get_contents($link_file) : "";
                ?>
                <?php if (file_exists($img_file_real)): ?>
                    <div>
                        <img src="<?= $img_file_web ?>" class="banner-thumb">
                        <a href="/manage/?delete=img&amp;pos=<?= $pos ?>" class="del-btn" onclick="return confirm('이미지를 삭제할까요?')">[이미지 삭제]</a>
                        <?php if ($link): ?>
                            <a href="<?= htmlspecialchars($link) ?>" target="_blank">[링크로 이동]</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (file_exists($link_file)): ?>
                    <div>
                        <span>현재 링크: <code><?= htmlspecialchars($link) ?></code></span>
                        <a href="/manage/?delete=link&amp;pos=<?= $pos ?>" class="del-btn" onclick="return confirm('링크 파일을 삭제할까요?')">[링크 삭제]</a>
                    </div>
                <?php endif; ?>
                <input type="hidden" name="pos" value="<?= $pos ?>">
                <label>이미지 업로드: <input type="file" name="banner"></label>
                <input type="text" name="banner_link" value="<?= htmlspecialchars($link) ?>" placeholder="배너 링크(URL)">
                <button type="submit">업로드/링크 저장</button>
            </form>
            <?php endforeach; ?>
        </section>
    </main>
    <footer>
        &copy; 2025 danceoffice.net | 관리자 페이지
    </footer>
</body>
</html>