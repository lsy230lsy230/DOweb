<?php
session_start();
if (!isset($_SESSION['admin'])) {
    if ($_POST['pw'] ?? '' === 'adminpw') {
        $_SESSION['admin'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="ko">
        <head>
            <meta charset="UTF-8">
            <title>관리자 로그인 - danceoffice.net</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
            <style>
                body {
                    background: #181B20;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0;
                    padding: 0;
                    font-family: 'Noto Sans KR', sans-serif;
                }
                .login-container {
                    background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
                    border-radius: 20px;
                    padding: 40px;
                    box-shadow: 0 20px 40px rgba(3, 199, 90, 0.2);
                    border: 2px solid #03C75A;
                    text-align: center;
                    max-width: 400px;
                    width: 90%;
                }
                .login-title {
                    color: #03C75A;
                    font-size: 24px;
                    margin-bottom: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }
                .login-subtitle {
                    color: #00BFAE;
                    font-size: 16px;
                    margin-bottom: 30px;
                }
                .form-group {
                    margin-bottom: 20px;
                    text-align: left;
                }
                .form-group label {
                    display: block;
                    color: #F5F7FA;
                    font-size: 14px;
                    margin-bottom: 8px;
                    font-weight: 600;
                }
                .form-group input {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #31343a;
                    border-radius: 10px;
                    background: #222;
                    color: #F5F7FA;
                    font-size: 14px;
                    box-sizing: border-box;
                }
                .btn {
                    background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                    color: #222;
                    border: none;
                    padding: 12px 30px;
                    border-radius: 25px;
                    font-weight: 600;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    width: 100%;
                }
                .btn:hover {
                    background: linear-gradient(90deg, #00BFAE 60%, #03C75A 100%);
                    color: white;
                    transform: translateY(-2px);
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <h1 class="login-title">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                    관리자 로그인
                </h1>
                <p class="login-subtitle">danceoffice.net 관리 시스템</p>
        <form method="post">
                    <div class="form-group">
                        <label for="pw">관리자 비밀번호</label>
                        <input type="password" id="pw" name="pw" placeholder="비밀번호를 입력하세요" required>
                    </div>
                    <button type="submit" class="btn">로그인</button>
        </form>
            </div>
        </body>
        </html>
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
$base_ads_dir = __DIR__ . "/../data/ads/";

// 공지/일정 파일 경로
$notice_file = __DIR__ . "/../data/notice.txt";
$schedule_file = __DIR__ . "/../data/schedule.txt";

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
    <title>관리자 페이지 - danceoffice.net</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <style>
        /* PC 버전 스타일 */
        @media (min-width: 1024px) {
            body {
                background: #181B20;
                min-height: 100vh;
                font-size: 14px;
                line-height: 1.5;
                margin: 0;
                padding: 0;
                font-family: 'Noto Sans KR', sans-serif;
            }
            
            .admin-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .admin-header {
                background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
                border-radius: 15px;
                padding: 25px;
                margin-bottom: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
                border: 2px solid #03C75A;
                position: relative;
                overflow: hidden;
            }
            
            .admin-header::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(3, 199, 90, 0.08) 0%, transparent 70%);
                animation: float 6s ease-in-out infinite;
            }
            
            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-15px) rotate(180deg); }
            }
            
            .admin-title {
                color: #03C75A;
                font-size: 24px;
                margin: 0 0 10px 0;
                position: relative;
                z-index: 2;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .admin-subtitle {
                color: #00BFAE;
                font-size: 16px;
                margin: 0;
                position: relative;
                z-index: 2;
            }
            
            .admin-nav {
                display: flex;
                gap: 15px;
                margin-top: 20px;
                position: relative;
                z-index: 2;
            }
            
            .admin-nav a {
                color: white;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                padding: 10px 20px;
                border-radius: 20px;
                background: rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(10px);
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .admin-nav a:hover {
                background: #03C75A;
                color: #222;
                transform: translateY(-2px);
            }
            
            .admin-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .admin-card {
                background: #222;
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .admin-card h3 {
                color: #03C75A;
                font-size: 18px;
                margin: 0 0 15px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-group label {
                display: block;
                color: #F5F7FA;
                font-size: 13px;
                margin-bottom: 5px;
                font-weight: 600;
            }
            
            .form-group input,
            .form-group textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #31343a;
                border-radius: 8px;
                background: #181B20;
                color: #F5F7FA;
                font-size: 13px;
                box-sizing: border-box;
            }
            
            .form-group textarea {
                height: 80px;
                resize: vertical;
            }
            
            .btn {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                border: none;
                padding: 10px 20px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .btn:hover {
                background: linear-gradient(90deg, #00BFAE 60%, #03C75A 100%);
                color: white;
                transform: translateY(-2px);
            }
            
            .btn-danger {
                background: linear-gradient(90deg, #ff4444 70%, #cc0000 100%);
                color: white;
            }
            
            .btn-danger:hover {
                background: linear-gradient(90deg, #cc0000 60%, #ff4444 100%);
            }
            
            .preview-box {
                background: #181B20;
                border: 1px solid #31343a;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                font-size: 13px;
                line-height: 1.4;
            }
            
            .preview-box strong {
                color: #03C75A;
            }
            
            .banner-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
            
            .banner-card {
                background: #222;
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .banner-card h4 {
                color: #03C75A;
                font-size: 16px;
                margin: 0 0 10px 0;
            }
            
            .banner-info {
                background: #181B20;
                border-radius: 8px;
                padding: 12px;
                margin-bottom: 15px;
                font-size: 12px;
                color: #B0B3B8;
            }
            
            .banner-thumb {
                max-width: 100%;
                height: auto;
                border-radius: 8px;
                margin: 10px 0;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            }
            
            .file-info {
                background: #1E2126;
                border-radius: 6px;
                padding: 8px;
                margin: 8px 0;
                font-size: 11px;
                color: #8A8D93;
            }
            
            .action-buttons {
                display: flex;
                gap: 10px;
                margin-top: 15px;
                flex-wrap: wrap;
            }
            
            .btn-small {
                padding: 6px 12px;
                font-size: 11px;
            }
        }
        
        /* 모바일 버전 스타일 */
        @media (max-width: 1023px) {
            body {
                background: #181B20;
                min-height: 100vh;
                font-size: 14px;
                line-height: 1.5;
                margin: 0;
                padding: 0;
                font-family: 'Noto Sans KR', sans-serif;
            }
            
            .admin-container {
                padding: 10px;
            }
            
            .admin-header {
                background: rgba(26, 29, 33, 0.9);
                backdrop-filter: blur(20px);
                border-radius: 15px;
                padding: 20px;
                margin-bottom: 15px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
                border: 2px solid #03C75A;
            }
            
            .admin-title {
                color: #03C75A;
                font-size: 20px;
                margin: 0 0 8px 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .admin-subtitle {
                color: #00BFAE;
                font-size: 14px;
                margin: 0;
            }
            
            .admin-nav {
                display: flex;
                gap: 10px;
                margin-top: 15px;
                flex-wrap: wrap;
            }
            
            .admin-nav a {
                color: white;
                text-decoration: none;
                font-weight: 600;
                font-size: 13px;
                padding: 8px 16px;
                border-radius: 16px;
                background: rgba(3, 199, 90, 0.2);
                backdrop-filter: blur(10px);
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .admin-nav a:hover {
                background: #03C75A;
                color: #222;
            }
            
            .admin-grid {
                display: flex;
                flex-direction: column;
                gap: 15px;
                margin-bottom: 15px;
            }
            
            .admin-card {
                background: rgba(34, 34, 34, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 15px;
                padding: 15px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .admin-card h3 {
                color: #03C75A;
                font-size: 16px;
                margin: 0 0 12px 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .banner-grid {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .banner-card {
                background: rgba(34, 34, 34, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 15px;
                padding: 15px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .banner-card h4 {
                color: #03C75A;
                font-size: 14px;
                margin: 0 0 8px 0;
            }
            
            .banner-info {
                background: #1E2126;
                border-radius: 8px;
                padding: 10px;
                margin-bottom: 12px;
                font-size: 11px;
                color: #B0B3B8;
            }
            
            .file-info {
                background: #181B20;
                border-radius: 6px;
                padding: 6px;
                margin: 6px 0;
                font-size: 10px;
                color: #8A8D93;
            }
            
            .action-buttons {
                display: flex;
                gap: 8px;
                margin-top: 12px;
                flex-wrap: wrap;
            }
            
            .btn {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                border: none;
                padding: 8px 16px;
                border-radius: 16px;
                font-weight: 600;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .btn-small {
                padding: 5px 10px;
                font-size: 10px;
            }
        }
        
        /* 공통 스타일 */
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-active {
            background: #03C75A;
        }
        
        .status-inactive {
            background: #666;
        }
    </style>
</head>
<body>
    <div class="admin-container">
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
                 <a href="/comp/">
                     <span class="material-symbols-rounded">sports</span>
                     대회 관리
                 </a>
                 <a href="/comp/admin_event.php">
                     <span class="material-symbols-rounded">event</span>
                     프리스타일 대회 채점
                 </a>
        </nav>
    </header>
        
    <main>
            <div class="admin-grid">
                <div class="admin-card">
                    <h3>
                        <span class="material-symbols-rounded">campaign</span>
                        공지사항 관리
                    </h3>
                    <div class="preview-box">
                    <strong>현재 등록된 공지사항:</strong><br>
                    <?= nl2br(htmlspecialchars($current_notice)) ?>
                    <?php if ($current_notice): ?>
                            <div class="action-buttons">
                                <a href="/manage/?delete_notice=1" class="btn btn-danger btn-small" onclick="return confirm('공지사항을 삭제할까요?')">
                                    <span class="material-symbols-rounded">delete</span>
                                    공지사항 삭제
                                </a>
                            </div>
                    <?php endif; ?>
                </div>
                    <form method="post">
                        <div class="form-group">
                            <label for="notice">공지사항 내용</label>
                            <textarea id="notice" name="notice" placeholder="공지 내용을 입력하세요"><?= htmlspecialchars($current_notice) ?></textarea>
                        </div>
                        <button type="submit" class="btn">
                            <span class="material-symbols-rounded">save</span>
                            공지 저장
                        </button>
            </form>
                </div>
                
                <div class="admin-card">
                    <h3>
                        <span class="material-symbols-rounded">calendar_month</span>
                        대회 일정 관리
                    </h3>
                    <div class="preview-box">
                    <strong>현재 등록된 대회 일정:</strong><br>
                    <?= nl2br(htmlspecialchars($current_schedule)) ?>
                    <?php if ($current_schedule): ?>
                            <div class="action-buttons">
                                <a href="/manage/?delete_schedule=1" class="btn btn-danger btn-small" onclick="return confirm('대회일정을 삭제할까요?')">
                                    <span class="material-symbols-rounded">delete</span>
                                    대회일정 삭제
                                </a>
                            </div>
                    <?php endif; ?>
                </div>
                    <form method="post">
                        <div class="form-group">
                            <label for="schedule">대회 일정 내용</label>
                            <textarea id="schedule" name="schedule" placeholder="대회 일정을 입력하세요"><?= htmlspecialchars($current_schedule) ?></textarea>
                        </div>
                        <button type="submit" class="btn">
                            <span class="material-symbols-rounded">save</span>
                            일정 저장
                        </button>
            </form>
                </div>
            </div>
            
            <div class="banner-grid">
            <?php foreach($positions as $pos=>$data): ?>
                <div class="banner-card">
                    <h4>
                        <span class="material-symbols-rounded">image</span>
                    <?= $data['label'] ?> 배너
                        <span style="font-size:12px; color:#8A8D93;">(권장: <?= $data['size'] ?>)</span>
                    </h4>
                <div class="banner-info">
                        <?= $data['desc'] ?>
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
                            <div class="action-buttons">
                                <a href="/manage/?delete=img&amp;pos=<?= $pos ?>" class="btn btn-danger btn-small" onclick="return confirm('이미지를 삭제할까요?')">
                                    <span class="material-symbols-rounded">delete</span>
                                    이미지 삭제
                                </a>
                        <?php if ($link): ?>
                                    <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="btn btn-small">
                                        <span class="material-symbols-rounded">open_in_new</span>
                                        링크로 이동
                                    </a>
                        <?php endif; ?>
                            </div>
                    </div>
                <?php endif; ?>
                <?php if (file_exists($link_file)): ?>
                        <div class="file-info">
                            <strong>현재 링크:</strong> <code><?= htmlspecialchars($link) ?></code>
                            <a href="/manage/?delete=link&amp;pos=<?= $pos ?>" class="btn btn-danger btn-small" onclick="return confirm('링크 파일을 삭제할까요?')">
                                <span class="material-symbols-rounded">delete</span>
                                링크 삭제
                            </a>
                    </div>
                <?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="pos" value="<?= $pos ?>">
                        <div class="form-group">
                            <label for="banner_<?= $pos ?>">이미지 업로드</label>
                            <input type="file" id="banner_<?= $pos ?>" name="banner" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label for="link_<?= $pos ?>">배너 링크 (URL)</label>
                            <input type="text" id="link_<?= $pos ?>" name="banner_link" value="<?= htmlspecialchars($link) ?>" placeholder="배너 링크를 입력하세요">
                        </div>
                        <button type="submit" class="btn">
                            <span class="material-symbols-rounded">upload</span>
                            업로드/링크 저장
                        </button>
            </form>
                </div>
            <?php endforeach; ?>
            </div>
    </main>
        
        <footer style="text-align: center; color: #03C75A; padding: 20px; font-size: 12px;">
        &copy; 2025 danceoffice.net | 관리자 페이지
    </footer>
    </div>
</body>
</html>
