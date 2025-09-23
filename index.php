<?php
session_start();

// 다국어 시스템 로드
require_once __DIR__ . '/lang/Language.php';
$lang = Language::getInstance();

// NoticeManager 로드
require_once __DIR__ . '/data/NoticeManager.php';

$base_ads_dir = __DIR__ . "/data/";
function read_banner($pos) {
    $img_web = "/data/$pos.jpg";
    $img_file = __DIR__ . "/data/$pos.jpg";
    $link_file = __DIR__ . "/data/$pos.link";
    $link = file_exists($link_file) ? trim(file_get_contents($link_file)) : "";
    if (file_exists($img_file)) {
        if ($link) return "<a href='$link' target='_blank'><img src='$img_web' alt='{$pos} 광고'></a>";
        else return "<img src='$img_web' alt='{$pos} 광고'>";
    }
    return "";
}

// 공지사항 로드 (데이터베이스 기반)
try {
    $noticeManager = new NoticeManager();
    $recent_notices = $noticeManager->getRecentNotices(3);
} catch (Exception $e) {
    $recent_notices = [];
    error_log("공지사항 로드 실패: " . $e->getMessage());
}

// 일정 미리보기 (기존 파일 기반)
$schedule_file = __DIR__ . "/data/schedule.txt";
$schedule_preview = file_exists($schedule_file) ? nl2br(htmlspecialchars(file_get_contents($schedule_file))) : t('no_schedule');

// 대회 스케줄러 로드
require_once __DIR__ . '/data/scheduler.php';
$scheduler = new CompetitionScheduler();

// 다가오는 대회 (최대 3개)
$upcoming_competitions = $scheduler->getUpcomingCompetitions(3);

// 최근 결과 대회 (대회 전날부터 7일간)
$recent_results_competitions = $scheduler->getRecentResultsCompetitions();

// 최근 완료된 대회 (최대 2개) - 기존 통계용
$recent_competitions = $scheduler->getRecentCompetitions(2);
?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('site_title') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/style.css">
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
            overflow-x: hidden;
        }

        /* 새로운 대시보드 레이아웃 */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* 사이드바 */
        .sidebar {
            width: 280px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(59, 130, 246, 0.2);
            padding: 24px 20px;
            position: fixed;
            height: 100vh;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
            padding: 16px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 16px;
            color: white;
        }

        .sidebar-logo img {
            width: 32px;
            height: 32px;
        }

        .sidebar-logo h1 {
            font-size: 18px;
            font-weight: 700;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 8px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            transform: translateX(4px);
        }

        .sidebar-nav .material-symbols-rounded {
            font-size: 20px;
        }

        /* 언어 선택 */
        .language-selector {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(59, 130, 246, 0.2);
        }

        .language-dropdown {
            position: relative;
        }

        .language-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-align: left;
        }

        .language-toggle:hover {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .language-menu {
            position: absolute;
            bottom: 100%;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            margin-bottom: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .language-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .language-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 4px;
        }

        .language-option:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .language-option.current {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        /* 메인 콘텐츠 */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
        }

        /* 히어로 섹션 */
        .hero-section {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 24px;
            padding: 48px;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #ffffff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 20px;
            color: #64748b;
            margin-bottom: 32px;
            max-width: 600px;
        }

        .hero-stats {
            display: flex;
            gap: 32px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #3b82f6;
            display: block;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        /* 대시보드 그리드 */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        /* 위젯 카드 */
        .widget-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 20px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .widget-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.4);
        }

        .widget-header {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: 20px;
        }

        .widget-title {
            font-size: 18px;
            font-weight: 600;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .widget-icon {
            color: #3b82f6;
            font-size: 24px;
        }

        .widget-action {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .widget-action:hover {
            color: #60a5fa;
        }

        /* 대회 카드 */
        .competition-item {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .competition-item:hover {
            background: rgba(59, 130, 246, 0.05);
            border-color: rgba(59, 130, 246, 0.3);
        }

        /* Recent Results 스타일 */
        .recent-result-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .result-info h3.result-title {
            color: #f1f5f9;
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 8px 0;
        }

        .result-meta {
            display: flex;
            gap: 12px;
            font-size: 13px;
            color: #94a3b8;
        }

        .result-date {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 3px 8px;
            border-radius: 8px;
        }

        .result-location {
            background: rgba(100, 116, 139, 0.1);
            color: #64748b;
            padding: 3px 8px;
            border-radius: 8px;
        }

        .result-actions {
            display: flex;
            gap: 8px;
        }

        .result-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .result-btn .material-symbols-rounded {
            font-size: 16px;
        }

        .btn-results {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .btn-results:hover {
            background: rgba(34, 197, 94, 0.2);
            border-color: rgba(34, 197, 94, 0.3);
        }

        .btn-live {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-live:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .comp-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 12px;
        }

        .comp-title {
            font-size: 16px;
            font-weight: 600;
            color: #f1f5f9;
        }

        .comp-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-upcoming {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .status-completed {
            background: rgba(100, 116, 139, 0.1);
            color: #64748b;
            border: 1px solid rgba(100, 116, 139, 0.2);
        }

        .comp-details {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.5;
        }

        .comp-meta {
            display: flex;
            gap: 16px;
            margin-top: 12px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: #64748b;
        }

        .meta-item .material-symbols-rounded {
            font-size: 16px;
        }

        /* 액션 버튼 */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        /* 모바일 대응 */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 16px;
            }

            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .dashboard-container {
                flex-direction: column;
            }

            .hero-title {
                font-size: 32px;
            }

            .hero-section {
                padding: 32px 24px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .hero-stats {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- 사이드바 -->
        <nav class="sidebar">
            <div class="sidebar-logo">
                <img src="/assets/danceoffice-logo.png" alt="DanceOffice">
                <h1>DanceOffice</h1>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="/" class="active">
                    <span class="material-symbols-rounded">dashboard</span>
                    <?= t('nav_dashboard') ?>
                </a></li>
                <li><a href="/competitions.php">
                    <span class="material-symbols-rounded">event</span>
                    <?= t('nav_competitions') ?>
                </a></li>
                <li><a href="/results.php">
                    <span class="material-symbols-rounded">trophy</span>
                    <?= t('nav_results') ?>
                </a></li>
                <li><a href="/manage/">
                    <span class="material-symbols-rounded">settings</span>
                    <?= t('nav_management') ?>
                </a></li>
                <li><a href="/notice/">
                    <span class="material-symbols-rounded">notifications</span>
                    <?= t('nav_notices') ?>
                </a></li>
            </ul>

            <!-- 언어 선택기 -->
            <div class="language-selector">
                <div class="language-dropdown">
                    <div class="language-toggle" onclick="toggleLanguageMenu()">
                        <span><?= $lang->getLangFlag() ?></span>
                        <span><?= $lang->getLangName() ?></span>
                        <span class="material-symbols-rounded" style="margin-left: auto;">expand_more</span>
                    </div>
                    <div class="language-menu" id="languageMenu">
                        <?php foreach ($lang->getAvailableLanguages() as $code => $info): ?>
                            <a href="<?= $lang->getLanguageUrl($code) ?>" 
                               class="language-option <?= $code === $lang->getCurrentLang() ? 'current' : '' ?>">
                                <span><?= $info['flag'] ?></span>
                                <span><?= $info['name'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- 메인 콘텐츠 -->
        <main class="main-content">
            <!-- 히어로 섹션 -->
            <section class="hero-section">
                <div class="hero-content">
                    <h1 class="hero-title"><?= t('hero_title') ?></h1>
                    <p class="hero-subtitle">
                        <?= t('hero_subtitle') ?>
                        <br><?= t('hero_description') ?>
                    </p>
                    
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?= count($upcoming_competitions) + count($recent_competitions) ?></span>
                            <span class="stat-label"><?= t('stat_total_competitions') ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= count($upcoming_competitions) ?></span>
                            <span class="stat-label"><?= t('stat_upcoming_events') ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= count($recent_competitions) ?></span>
                            <span class="stat-label"><?= t('stat_completed') ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 대시보드 그리드 -->
            <div class="dashboard-grid">
                <!-- 다가오는 대회 위젯 -->
                <div class="widget-card">
                    <div class="widget-header">
                        <h2 class="widget-title">
                            <span class="material-symbols-rounded widget-icon">upcoming</span>
                            <?= t('widget_upcoming_competitions') ?>
                        </h2>
                        <a href="/comp/" class="widget-action"><?= t('action_view_all') ?></a>
                    </div>
                    
                    <div class="widget-content">
                        <?php if (!empty($upcoming_competitions)): ?>
                            <?php foreach ($upcoming_competitions as $comp): ?>
                                <a href="/competition.php?id=<?= urlencode($comp['id']) ?>" style="text-decoration: none; color: inherit;">
                                    <div class="competition-item">
                                        <div class="comp-header">
                                            <h3 class="comp-title"><?= htmlspecialchars($comp['name']) ?></h3>
                                            <span class="comp-status status-upcoming"><?= t('status_upcoming') ?></span>
                                        </div>
                                        <div class="comp-details">
                                            <?= htmlspecialchars($comp['description']) ?>
                                        </div>
                                        <div class="comp-meta">
                                            <div class="meta-item">
                                                <span class="material-symbols-rounded">schedule</span>
                                                <?= $lang->formatDate($comp['date']) ?>
                                            </div>
                                            <div class="meta-item">
                                                <span class="material-symbols-rounded">location_on</span>
                                                <?= htmlspecialchars($comp['location']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #64748b; text-align: center; padding: 20px;">
                                <?= t('no_upcoming_competitions') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 최근 결과 위젯 -->
                <div class="widget-card">
                    <div class="widget-header">
                        <h2 class="widget-title">
                            <span class="material-symbols-rounded widget-icon">history</span>
                            <?= t('widget_recent_results') ?>
                        </h2>
                        <a href="/results.php" class="widget-action"><?= t('action_view_all') ?></a>
                    </div>
                    
                    <div class="widget-content">
                        <?php if (!empty($recent_results_competitions)): ?>
                            <?php foreach ($recent_results_competitions as $comp): ?>
                                <div class="recent-result-item">
                                    <div class="result-header">
                                        <div class="result-info">
                                            <h3 class="result-title"><?= htmlspecialchars($comp['name'] ?? $comp['title']) ?></h3>
                                            <div class="result-meta">
                                                <span class="result-date"><?= $lang->formatDate($comp['date']) ?></span>
                                                <span class="result-location"><?= htmlspecialchars($comp['location'] ?? $comp['place']) ?></span>
                                            </div>
                                        </div>
                                        <span class="comp-status status-<?= $comp['status'] ?>"><?= t('status_' . $comp['status']) ?></span>
                                    </div>
                                    
                                    <div class="result-actions">
                                        <a href="/competition.php?id=<?= urlencode($comp['id']) ?>&page=results" class="result-btn btn-results">
                                            <span class="material-symbols-rounded">trophy</span>
                                            종합결과
                                        </a>
                                        <a href="/competition.php?id=<?= urlencode($comp['id']) ?>&page=live" class="result-btn btn-live">
                                            <span class="material-symbols-rounded">live_tv</span>
                                            실시간 결과
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #64748b; text-align: center; padding: 20px;">
                                최근 7일간 완료된 대회가 없습니다
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 공지사항 위젯 -->
                <div class="widget-card">
                    <div class="widget-header">
                        <h2 class="widget-title">
                            <span class="material-symbols-rounded widget-icon">campaign</span>
                            <?= t('widget_announcements') ?>
                        </h2>
                        <a href="/manage/" class="widget-action"><?= t('action_manage') ?></a>
                    </div>
                    
                    <div class="widget-content">
                        <?php if (!empty($recent_notices)): ?>
                            <?php foreach ($recent_notices as $notice): ?>
                                <div class="notice-item" style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid rgba(59, 130, 246, 0.1);">
                                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                                        <?php if ($notice['is_pinned']): ?>
                                            <span class="material-symbols-rounded" style="color: #f59e0b; font-size: 16px; margin-top: 2px;">push_pin</span>
                                        <?php endif; ?>
                                        <div style="flex: 1;">
                                            <h4 style="color: #f1f5f9; font-size: 14px; font-weight: 600; margin: 0 0 4px 0; line-height: 1.4;">
                                                <?= htmlspecialchars($notice['title']) ?>
                                            </h4>
                                            <p style="color: #94a3b8; font-size: 12px; margin: 0 0 8px 0;">
                                                <?= date('Y-m-d', strtotime($notice['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div style="color: #e2e8f0; font-size: 13px; line-height: 1.5;">
                                        <?php 
                                        $content = htmlspecialchars($notice['content']);
                                        if (strlen($content) > 100) {
                                            echo substr($content, 0, 100) . '...';
                                        } else {
                                            echo nl2br($content);
                                        }
                                        ?>
                                    </div>
                                    
                                    <?php if ($notice['image_path']): ?>
                                        <div style="margin-top: 8px;">
                                            <img src="/<?= $notice['image_path'] ?>" alt="<?= htmlspecialchars($notice['title']) ?>" 
                                                 style="max-width: 100%; height: auto; border-radius: 6px; max-height: 120px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div style="text-align: center; margin-top: 16px;">
                                <a href="/manage/" style="color: #3b82f6; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                    <span class="material-symbols-rounded" style="font-size: 14px;">visibility</span>
                                    <?= t('view_all_notices') ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; color: #64748b; padding: 20px;">
                                <span class="material-symbols-rounded" style="font-size: 32px; margin-bottom: 8px; display: block; opacity: 0.5;">campaign</span>
                                <?= t('no_notices') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 일정 위젯 -->
                <div class="widget-card">
                    <div class="widget-header">
                        <h2 class="widget-title">
                            <span class="material-symbols-rounded widget-icon">calendar_month</span>
                            <?= t('widget_schedule') ?>
                        </h2>
                        <a href="/manage/" class="widget-action"><?= t('action_edit') ?></a>
                    </div>
                    
                    <div class="widget-content">
                        <div style="color: #94a3b8; line-height: 1.6; font-size: 14px;">
                            <?= $schedule_preview ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 액션 버튼 -->
            <div class="action-buttons">
                <a href="/comp/" class="btn btn-primary">
                    <span class="material-symbols-rounded">add</span>
                    <?= t('action_new_competition') ?>
                </a>
                <a href="/results/" class="btn btn-secondary">
                    <span class="material-symbols-rounded">visibility</span>
                    <?= t('action_view_results') ?>
                </a>
                <a href="/manage/" class="btn btn-secondary">
                    <span class="material-symbols-rounded">tune</span>
                    <?= t('action_settings') ?>
                </a>
            </div>
        </main>
    </div>

    <script>
        function toggleLanguageMenu() {
            const menu = document.getElementById('languageMenu');
            menu.classList.toggle('active');
        }

        // 언어 메뉴 외부 클릭시 닫기
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.language-dropdown');
            const menu = document.getElementById('languageMenu');
            
            if (!dropdown.contains(event.target)) {
                menu.classList.remove('active');
            }
        });
    </script>
</body>
</html>