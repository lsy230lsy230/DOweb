<?php
session_start();

// 다국어 시스템 로드
require_once __DIR__ . '/../lang/Language.php';
$lang = Language::getInstance();

// NoticeManager 로드
require_once __DIR__ . '/../data/NoticeManager.php';

// 관리자 인증 확인
if (!isset($_SESSION['admin'])) {
    if ($_POST['pw'] ?? '' === 'adminpw') {
        $_SESSION['admin'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="<?= $lang->getCurrentLang() ?>">
        <head>
            <meta charset="UTF-8">
            <title><?= t('admin_login') ?> | DanceOffice</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
                    color: #e2e8f0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .login-container {
                    background: rgba(30, 41, 59, 0.8);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(59, 130, 246, 0.2);
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 400px;
                    width: 90%;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }

                .login-container::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: radial-gradient(circle at 30% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
                    pointer-events: none;
                }

                .login-content {
                    position: relative;
                    z-index: 2;
                }

                .login-title {
                    font-size: 28px;
                    font-weight: 700;
                    color: #f1f5f9;
                    margin-bottom: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 12px;
                }

                .login-title .material-symbols-rounded {
                    color: #3b82f6;
                    font-size: 32px;
                }

                .login-subtitle {
                    color: #94a3b8;
                    font-size: 16px;
                    margin-bottom: 32px;
                }

                .form-group {
                    margin-bottom: 24px;
                    text-align: left;
                }

                .form-group label {
                    display: block;
                    color: #f1f5f9;
                    font-size: 14px;
                    font-weight: 500;
                    margin-bottom: 8px;
                }

                .form-group input {
                    width: 100%;
                    padding: 14px 16px;
                    border: 1px solid rgba(59, 130, 246, 0.2);
                    border-radius: 12px;
                    background: rgba(15, 23, 42, 0.6);
                    color: #f1f5f9;
                    font-size: 14px;
                    transition: all 0.3s ease;
                }

                .form-group input:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }

                .btn {
                    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                    color: white;
                    border: none;
                    padding: 14px 32px;
                    border-radius: 12px;
                    font-weight: 600;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                }

                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
                }

                .btn .material-symbols-rounded {
                    font-size: 18px;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-content">
                    <h1 class="login-title">
                        <span class="material-symbols-rounded">admin_panel_settings</span>
                        <?= t('admin_login') ?>
                    </h1>
                    <p class="login-subtitle"><?= t('admin_subtitle') ?></p>
                    
                    <form method="post">
                        <div class="form-group">
                            <label for="pw"><?= t('admin_password') ?></label>
                            <input type="password" id="pw" name="pw" placeholder="<?= t('admin_password_placeholder') ?>" required>
                        </div>
                        <button type="submit" class="btn">
                            <span class="material-symbols-rounded">login</span>
                            <?= t('admin_login_btn') ?>
                        </button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// 광고 위치별 정보
$positions = [
    'top'    => ['label' => t('banner_top'),    'size' => '728x90px',    'desc' => t('banner_top_desc'),    'img' => 'top.jpg',    'link' => 'top.link'],
    'bottom' => ['label' => t('banner_bottom'), 'size' => '728x90px',    'desc' => t('banner_bottom_desc'), 'img' => 'bottom.jpg', 'link' => 'bottom.link'],
    'left'   => ['label' => t('banner_left'),   'size' => '160x600px',   'desc' => t('banner_left_desc'),   'img' => 'left.jpg',   'link' => 'left.link'],
    'right'  => ['label' => t('banner_right'),  'size' => '160x600px',   'desc' => t('banner_right_desc'),  'img' => 'right.jpg',  'link' => 'right.link'],
    'main'   => ['label' => t('banner_main'),   'size' => '300x250px ' . t('or') . ' 728x90px', 'desc' => t('banner_main_desc'),   'img' => 'main.jpg',   'link' => 'main.link'],
];

# 디렉토리 경로
$base_ads_dir = __DIR__ . "/../data/ads/";
$schedule_file = __DIR__ . "/../data/schedule.txt";

// NoticeManager 초기화
try {
    $noticeManager = new NoticeManager();
} catch (Exception $e) {
    error_log("NoticeManager 초기화 실패: " . $e->getMessage());
}

// 공지사항 처리
if (isset($_POST['notice_action']) && isset($noticeManager)) {
    try {
        switch ($_POST['notice_action']) {
            case 'create':
                $imagePath = null;
                if (isset($_FILES['notice_image']) && $_FILES['notice_image']['size'] > 0) {
                    $imagePath = $noticeManager->uploadImage($_FILES['notice_image']);
                }
                $noticeManager->createNotice(
                    $_POST['notice_title'],
                    $_POST['notice_content'],
                    $imagePath,
                    isset($_POST['is_pinned'])
                );
                break;
            case 'edit':
                $imagePath = $_POST['existing_image'] ?? null;
                if (isset($_FILES['notice_image']) && $_FILES['notice_image']['size'] > 0) {
                    $imagePath = $noticeManager->uploadImage($_FILES['notice_image']);
                }
                $noticeManager->updateNotice(
                    $_POST['notice_id'],
                    $_POST['notice_title'],
                    $_POST['notice_content'],
                    $imagePath,
                    isset($_POST['is_pinned'])
                );
                break;
            case 'delete':
                $noticeManager->deleteNotice($_POST['notice_id']);
                break;
            case 'toggle_pin':
                $noticeManager->togglePin($_POST['notice_id']);
                break;
        }
    } catch (Exception $e) {
        $notice_error = $e->getMessage();
    }
    if (!isset($notice_error)) {
        header("Location: /manage/");
        exit;
    }
}

// 일정 삭제 처리
if (isset($_GET['delete_schedule'])) {
    if (file_exists($schedule_file)) unlink($schedule_file);
    header("Location: /manage/");
    exit;
}

// 광고 삭제 처리
if (isset($_GET['delete']) && isset($_GET['pos']) && isset($positions[$_GET['pos']])) {
    $pos = $_GET['pos'];
    $type = $_GET['delete'];
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

// 기존 파일 기반 공지사항 시스템 제거됨 - 데이터베이스 기반으로 교체

// 대회일정 등록/수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule'])) {
    file_put_contents($schedule_file, trim($_POST['schedule']));
    echo "<script>location.href='/manage/';</script>";
    exit;
}

// 공지사항 및 통계 로드
try {
    $notices = isset($noticeManager) ? $noticeManager->getAllNotices() : [];
    $notice_stats = isset($noticeManager) ? $noticeManager->getNoticeStats() : null;
} catch (Exception $e) {
    $notices = [];
    $notice_stats = null;
    error_log("공지사항 로드 실패: " . $e->getMessage());
}

$current_schedule = file_exists($schedule_file) ? file_get_contents($schedule_file) : "";
?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('admin_title') ?> | DanceOffice</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* 상단 헤더 */
        .admin-header {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }

        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .admin-header-content {
            position: relative;
            z-index: 2;
        }

        .admin-title {
            font-size: 32px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-title .material-symbols-rounded {
            color: #3b82f6;
            font-size: 36px;
        }

        .admin-subtitle {
            color: #94a3b8;
            font-size: 16px;
            margin-bottom: 24px;
        }

        .admin-nav {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .admin-nav a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .admin-nav a:hover {
            background: rgba(59, 130, 246, 0.2);
            transform: translateY(-2px);
        }

        .admin-nav .material-symbols-rounded {
            font-size: 18px;
        }

        /* 언어 선택기 */
        .language-selector {
            position: absolute;
            top: 32px;
            right: 32px;
            z-index: 3;
        }

        .language-dropdown {
            position: relative;
            display: inline-block;
        }

        .language-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .language-btn:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        .language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 8px;
            min-width: 160px;
            display: none;
            z-index: 1000;
        }

        .language-menu.show {
            display: block;
        }

        .language-menu a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            color: #e2e8f0;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .language-menu a:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .language-menu a.active {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        /* 카드 그리드 */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .admin-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 24px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title .material-symbols-rounded {
            color: #3b82f6;
            font-size: 24px;
        }

        .preview-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            min-height: 80px;
        }

        .preview-box strong {
            color: #3b82f6;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            color: #f1f5f9;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.6);
            color: #f1f5f9;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .btn-danger:hover {
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn .material-symbols-rounded {
            font-size: 18px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        /* 배너 그리드 */
        .banner-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .banner-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 24px;
        }

        .banner-info {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #94a3b8;
        }

        .banner-thumb {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 12px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .file-info {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 12px 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .file-info strong {
            color: #3b82f6;
        }

        .file-info code {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }

        /* 모바일 대응 */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .admin-header {
                padding: 24px 20px;
            }

            .admin-title {
                font-size: 24px;
            }

            .admin-nav {
                flex-direction: column;
                gap: 12px;
            }

            .cards-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .banner-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .language-selector {
                position: relative;
                top: auto;
                right: auto;
                margin-top: 16px;
                text-align: center;
            }

            .language-menu {
                right: 50%;
                transform: translateX(50%);
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="admin-header">
            <div class="admin-header-content">
                <h1 class="admin-title">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                    <?= t('admin_title') ?>
                </h1>
                <p class="admin-subtitle"><?= t('admin_subtitle') ?></p>
                
                <nav class="admin-nav">
                    <a href="/">
                        <span class="material-symbols-rounded">home</span>
                        <?= t('admin_nav_home') ?>
                    </a>
                    <a href="/results.php">
                        <span class="material-symbols-rounded">military_tech</span>
                        <?= t('admin_nav_results') ?>
                    </a>
                    <a href="/comp/">
                        <span class="material-symbols-rounded">sports</span>
                        <?= t('admin_nav_competitions') ?>
                    </a>
                    <a href="/comp/admin_event.php">
                        <span class="material-symbols-rounded">score</span>
                        <?= t('admin_nav_judging') ?>
                    </a>
                </nav>
            </div>

            <!-- 언어 선택기 -->
            <div class="language-selector">
                <div class="language-dropdown">
                    <div class="language-btn" onclick="toggleLanguageMenu()">
                        <span><?= $lang->getLangFlag() ?></span>
                        <span><?= $lang->getLangName() ?></span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    <div class="language-menu" id="languageMenu">
                        <?php foreach ($lang->getAvailableLanguages() as $code => $info): ?>
                            <a href="<?= $lang->getLanguageUrl($code) ?>" class="<?= $lang->getCurrentLang() === $code ? 'active' : '' ?>">
                                <span><?= $info['flag'] ?></span>
                                <span><?= $info['name'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </header>

        <main>
            <!-- 공지사항 관리 -->
            <div class="admin-card" style="margin-bottom: 32px;">
                <h2 class="card-title">
                    <span class="material-symbols-rounded">campaign</span>
                    <?= t('notice_management') ?>
                    
                    <?php if ($notice_stats): ?>
                        <span style="font-size: 14px; color: #94a3b8; font-weight: 400;">
                            (<?= t('total') ?>: <?= $notice_stats['total'] ?>, <?= t('pinned') ?>: <?= $notice_stats['pinned'] ?>)
                        </span>
                    <?php endif; ?>
                </h2>
                
                <?php if (isset($notice_error)): ?>
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                        <strong><?= t('error') ?>:</strong> <?= htmlspecialchars($notice_error) ?>
                    </div>
                <?php endif; ?>
                
                <!-- 새 공지사항 작성 -->
                <div class="preview-box" style="margin-bottom: 24px;">
                    <strong><?= t('create_notice') ?></strong>
                    <form method="post" enctype="multipart/form-data" style="margin-top: 16px;">
                        <input type="hidden" name="notice_action" value="create">
                        
                        <div class="form-group">
                            <label for="notice_title"><?= t('notice_title') ?></label>
                            <input type="text" id="notice_title" name="notice_title" placeholder="<?= t('notice_title_placeholder') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="notice_content"><?= t('notice_content') ?></label>
                            <textarea id="notice_content" name="notice_content" placeholder="<?= t('notice_content_placeholder') ?>" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="notice_image"><?= t('notice_image') ?> (<?= t('optional') ?>)</label>
                            <input type="file" id="notice_image" name="notice_image" accept="image/*">
                            <small style="color: #94a3b8; display: block; margin-top: 4px;">
                                <?= t('notice_image_help') ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="is_pinned" style="width: auto;">
                                <span><?= t('notice_pin') ?></span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn">
                            <span class="material-symbols-rounded">add</span>
                            <?= t('notice_create') ?>
                        </button>
                    </form>
                </div>
                
                <!-- 기존 공지사항 목록 -->
                <div style="max-height: 600px; overflow-y: auto;">
                    <strong style="color: #f1f5f9; margin-bottom: 16px; display: block;"><?= t('existing_notices') ?></strong>
                    
                    <?php if (empty($notices)): ?>
                        <div class="preview-box" style="text-align: center; color: #94a3b8;">
                            <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 12px; display: block; opacity: 0.5;">campaign</span>
                            <?= t('no_notices') ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notices as $notice): ?>
                            <div class="notice-item" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(59, 130, 246, 0.1); border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                                <div style="display: flex; justify-content: between; align-items: flex-start; margin-bottom: 12px;">
                                    <div style="flex: 1;">
                                        <h4 style="color: #f1f5f9; font-size: 16px; font-weight: 600; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px;">
                                            <?php if ($notice['is_pinned']): ?>
                                                <span class="material-symbols-rounded" style="color: #f59e0b; font-size: 18px;">push_pin</span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($notice['title']) ?>
                                        </h4>
                                        <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                                            <?= date('Y-m-d H:i', strtotime($notice['created_at'])) ?>
                                            <?php if ($notice['updated_at'] !== $notice['created_at']): ?>
                                                (<?= t('updated') ?>: <?= date('Y-m-d H:i', strtotime($notice['updated_at'])) ?>)
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div style="color: #e2e8f0; margin-bottom: 12px; line-height: 1.5;">
                                    <?= nl2br(htmlspecialchars($notice['content'])) ?>
                                </div>
                                
                                <?php if ($notice['image_path']): ?>
                                    <div style="margin-bottom: 12px;">
                                        <img src="/<?= $notice['image_path'] ?>" alt="<?= htmlspecialchars($notice['title']) ?>" 
                                             style="max-width: 100%; height: auto; border-radius: 8px; max-height: 200px;">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="notice_action" value="toggle_pin">
                                        <input type="hidden" name="notice_id" value="<?= $notice['id'] ?>">
                                        <button type="submit" class="btn btn-small" style="background: <?= $notice['is_pinned'] ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' : 'rgba(59, 130, 246, 0.1)' ?>; color: <?= $notice['is_pinned'] ? 'white' : '#3b82f6' ?>;">
                                            <span class="material-symbols-rounded">push_pin</span>
                                            <?= $notice['is_pinned'] ? t('notice_unpin') : t('notice_pin') ?>
                                        </button>
                                    </form>
                                    
                                    <button onclick="editNotice(<?= $notice['id'] ?>, '<?= addslashes($notice['title']) ?>', '<?= addslashes($notice['content']) ?>', '<?= $notice['image_path'] ?>', <?= $notice['is_pinned'] ? 'true' : 'false' ?>)" class="btn btn-small">
                                        <span class="material-symbols-rounded">edit</span>
                                        <?= t('edit') ?>
                                    </button>
                                    
                                    <form method="post" style="display: inline;" onsubmit="return confirm('<?= t('notice_delete_confirm') ?>')">
                                        <input type="hidden" name="notice_action" value="delete">
                                        <input type="hidden" name="notice_id" value="<?= $notice['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-small">
                                            <span class="material-symbols-rounded">delete</span>
                                            <?= t('delete') ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 일정 관리 -->
            <div class="cards-grid">
                <div class="admin-card">

                <!-- 일정 관리 -->
                <div class="admin-card">
                    <h2 class="card-title">
                        <span class="material-symbols-rounded">event</span>
                        <?= t('schedule_management') ?>
                    </h2>
                    
                    <div class="preview-box">
                        <strong><?= t('current_schedule') ?></strong><br>
                        <?= $current_schedule ? nl2br(htmlspecialchars($current_schedule)) : t('no_schedule') ?>
                        
                        <?php if ($current_schedule): ?>
                            <div class="action-buttons">
                                <a href="/manage/?delete_schedule=1" class="btn btn-danger btn-small"
                                   onclick="return confirm('<?= t('schedule_delete_confirm') ?>')">
                                    <span class="material-symbols-rounded">delete</span>
                                    <?= t('schedule_delete') ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label for="schedule"><?= t('schedule_content') ?></label>
                            <textarea id="schedule" name="schedule" placeholder="<?= t('schedule_placeholder') ?>"><?= htmlspecialchars($current_schedule) ?></textarea>
                        </div>
                        <button type="submit" class="btn">
                            <span class="material-symbols-rounded">save</span>
                            <?= t('schedule_save') ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- 배너 관리 -->
            <div class="admin-card" style="margin-bottom: 32px;">
                <h2 class="card-title">
                    <span class="material-symbols-rounded">image</span>
                    <?= t('banner_management') ?>
                </h2>
                
                <div class="banner-grid">
                    <?php foreach($positions as $pos => $data): ?>
                        <div class="banner-card">
                            <h3 class="card-title" style="font-size: 18px; margin-bottom: 12px;">
                                <span class="material-symbols-rounded">ad_units</span>
                                <?= $data['label'] ?> <?= t('banner_management') ?>
                                <span style="font-size: 12px; color: #94a3b8; font-weight: 400;">
                                    (<?= t('banner_recommended_size') ?>: <?= $data['size'] ?>)
                                </span>
                            </h3>
                            
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
                                    <img src="<?= $img_file_web ?>" class="banner-thumb" alt="<?= $data['label'] ?> 배너">
                                    <div class="action-buttons">
                                        <a href="/manage/?delete=img&pos=<?= $pos ?>" class="btn btn-danger btn-small"
                                           onclick="return confirm('<?= t('banner_delete_image_confirm') ?>')">
                                            <span class="material-symbols-rounded">delete</span>
                                            <?= t('banner_image_delete') ?>
                                        </a>
                                        <?php if ($link): ?>
                                            <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="btn btn-small">
                                                <span class="material-symbols-rounded">open_in_new</span>
                                                <?= t('banner_view_link') ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (file_exists($link_file)): ?>
                                <div class="file-info">
                                    <strong><?= t('banner_current_link') ?></strong> <code><?= htmlspecialchars($link) ?></code>
                                    <div class="action-buttons">
                                        <a href="/manage/?delete=link&pos=<?= $pos ?>" class="btn btn-danger btn-small"
                                           onclick="return confirm('<?= t('banner_delete_link_confirm') ?>')">
                                            <span class="material-symbols-rounded">delete</span>
                                            <?= t('banner_link_delete') ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="pos" value="<?= $pos ?>">
                                <div class="form-group">
                                    <label for="banner_<?= $pos ?>"><?= t('banner_upload') ?></label>
                                    <input type="file" id="banner_<?= $pos ?>" name="banner" accept="image/*">
                                </div>
                                <div class="form-group">
                                    <label for="link_<?= $pos ?>"><?= t('banner_link') ?></label>
                                    <input type="text" id="link_<?= $pos ?>" name="banner_link" 
                                           value="<?= htmlspecialchars($link) ?>" 
                                           placeholder="<?= t('banner_link_placeholder') ?>">
                                </div>
                                <button type="submit" class="btn">
                                    <span class="material-symbols-rounded">upload</span>
                                    <?= t('banner_upload_save') ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- 편집 모달 -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; padding: 20px;">
        <div style="max-width: 600px; margin: 50px auto; background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 16px; padding: 24px; max-height: 80vh; overflow-y: auto;">
            <h3 style="color: #f1f5f9; margin: 0 0 20px 0; display: flex; align-items: center; gap: 12px;">
                <span class="material-symbols-rounded" style="color: #3b82f6;">edit</span>
                <?= t('edit_notice') ?>
            </h3>
            
            <form method="post" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="notice_action" value="edit">
                <input type="hidden" name="notice_id" id="edit_notice_id">
                <input type="hidden" name="existing_image" id="edit_existing_image">
                
                <div class="form-group">
                    <label for="edit_notice_title"><?= t('notice_title') ?></label>
                    <input type="text" id="edit_notice_title" name="notice_title" placeholder="<?= t('notice_title_placeholder') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_notice_content"><?= t('notice_content') ?></label>
                    <textarea id="edit_notice_content" name="notice_content" placeholder="<?= t('notice_content_placeholder') ?>" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_notice_image"><?= t('notice_image') ?> (<?= t('optional') ?>)</label>
                    <input type="file" id="edit_notice_image" name="notice_image" accept="image/*">
                    <div id="current_image_preview" style="margin-top: 8px;"></div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_pinned" id="edit_is_pinned" style="width: auto;">
                        <span><?= t('notice_pin') ?></span>
                    </label>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn">
                        <span class="material-symbols-rounded">save</span>
                        <?= t('save_changes') ?>
                    </button>
                    <button type="button" onclick="closeEditModal()" class="btn" style="background: rgba(148, 163, 184, 0.1); color: #94a3b8;">
                        <span class="material-symbols-rounded">close</span>
                        <?= t('cancel') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleLanguageMenu() {
            const menu = document.getElementById('languageMenu');
            menu.classList.toggle('show');
        }

        // 언어 메뉴 외부 클릭 시 닫기
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.language-dropdown');
            const menu = document.getElementById('languageMenu');
            
            if (!dropdown.contains(event.target)) {
                menu.classList.remove('show');
            }
        });

        // 공지사항 편집 함수
        function editNotice(id, title, content, imagePath, isPinned) {
            document.getElementById('edit_notice_id').value = id;
            document.getElementById('edit_notice_title').value = title;
            document.getElementById('edit_notice_content').value = content;
            document.getElementById('edit_existing_image').value = imagePath || '';
            document.getElementById('edit_is_pinned').checked = isPinned;
            
            // 현재 이미지 미리보기
            const preview = document.getElementById('current_image_preview');
            if (imagePath) {
                preview.innerHTML = '<img src="/' + imagePath + '" style="max-width: 200px; height: auto; border-radius: 8px; display: block; margin-top: 8px;"><small style="color: #94a3b8; margin-top: 4px; display: block;"><?= t('current_image') ?></small>';
            } else {
                preview.innerHTML = '<small style="color: #94a3b8;"><?= t('no_current_image') ?></small>';
            }
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // ESC 키로 모달 닫기
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });

        // 모달 배경 클릭 시 닫기
        document.getElementById('editModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>