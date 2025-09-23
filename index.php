<?php
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

// 공지 및 일정 미리보기
$notice_file = __DIR__ . "/data/notice.txt";
$schedule_file = __DIR__ . "/data/schedule.txt";
$notice_preview = file_exists($notice_file) ? nl2br(htmlspecialchars(file_get_contents($notice_file))) : "등록된 공지가 없습니다.";
$schedule_preview = file_exists($schedule_file) ? nl2br(htmlspecialchars(file_get_contents($schedule_file))) : "등록된 대회일정이 없습니다.";

// 대회 스케줄러 로드
require_once __DIR__ . '/data/scheduler.php';
$scheduler = new CompetitionScheduler();

// 다가오는 대회 (최대 3개)
$upcoming_competitions = $scheduler->getUpcomingCompetitions(3);

// 최근 완료된 대회 (최대 2개)
$recent_competitions = $scheduler->getRecentCompetitions(2);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>DanceOffice - International DanceSport Competition Management System</title>
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
                    Dashboard
                </a></li>
                <li><a href="/comp/">
                    <span class="material-symbols-rounded">event</span>
                    Competitions
                </a></li>
                <li><a href="/results/">
                    <span class="material-symbols-rounded">trophy</span>
                    Results
                </a></li>
                <li><a href="/manage/">
                    <span class="material-symbols-rounded">settings</span>
                    Management
                </a></li>
                <li><a href="/notice/">
                    <span class="material-symbols-rounded">notifications</span>
                    Notices
                </a></li>
            </ul>
        </nav>

        <!-- 메인 콘텐츠 -->
        <main class="main-content">
            <!-- 히어로 섹션 -->
            <section class="hero-section">
                <div class="hero-content">
                    <h1 class="hero-title">DanceOffice</h1>
                    <p class="hero-subtitle">
                        International DanceSport Competition Management System
                        <br>Professional judging platform for global dance competitions
                    </p>
                    
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?= count($upcoming_competitions) + count($recent_competitions) ?></span>
                            <span class="stat-label">Total Competitions</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= count($upcoming_competitions) ?></span>
                            <span class="stat-label">Upcoming Events</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= count($recent_competitions) ?></span>
                            <span class="stat-label">Completed</span>
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
                            Upcoming Competitions
                        </h2>
                        <a href="/comp/" class="widget-action">View All</a>
                    </div>
                    
                    <div class="widget-content">
                        <?php if (!empty($upcoming_competitions)): ?>
                            <?php foreach ($upcoming_competitions as $comp): ?>
                                <div class="competition-item">
                                    <div class="comp-header">
                                        <h3 class="comp-title"><?= htmlspecialchars($comp['name']) ?></h3>
                                        <span class="comp-status status-upcoming">Upcoming</span>
                                    </div>
                                    <div class="comp-details">
                                        <?= htmlspecialchars($comp['description']) ?>
                                    </div>
                                    <div class="comp-meta">
                                        <div class="meta-item">
                                            <span class="material-symbols-rounded">schedule</span>
                                            <?= date('M d, Y', strtotime($comp['date'])) ?>
                                        </div>
                                        <div class="meta-item">
                                            <span class="material-symbols-rounded">location_on</span>
                                            <?= htmlspecialchars($comp['location']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #64748b; text-align: center; padding: 20px;">
                                No upcoming competitions scheduled
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 최근 결과 위젯 -->
                <div class="widget-card">
                    <div class="widget-header">
                        <h2 class="widget-title">
                            <span class="material-symbols-rounded widget-icon">history</span>
                            Recent Results
                        </h2>
                        <a href="/results/" class="widget-action">View All</a>
                    </div>
                    
                    <div class="widget-content">
                        <?php if (!empty($recent_competitions)): ?>
                            <?php foreach ($recent_competitions as $comp): ?>
                                <div class="competition-item">
                                    <div class="comp-header">
                                        <h3 class="comp-title"><?= htmlspecialchars($comp['name']) ?></h3>
                                        <span class="comp-status status-completed">Completed</span>
                                    </div>
                                    <div class="comp-details">
                                        <?= htmlspecialchars($comp['description']) ?>
                                    </div>
                                    <div class="comp-meta">
                                        <div class="meta-item">
                                            <span class="material-symbols-rounded">schedule</span>
                                            <?= date('M d, Y', strtotime($comp['date'])) ?>
                                        </div>
                                        <div class="meta-item">
                                            <span class="material-symbols-rounded">location_on</span>
                                            <?= htmlspecialchars($comp['location']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #64748b; text-align: center; padding: 20px;">
                                No recent competitions
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 공지사항 위젯 -->
                <div class="widget-card">
                    <div class="widget-header">
                        <h2 class="widget-title">
                            <span class="material-symbols-rounded widget-icon">campaign</span>
                            Announcements
                        </h2>
                        <a href="/notice/" class="widget-action">Manage</a>
                    </div>
                    
                    <div class="widget-content">
                        <div style="color: #94a3b8; line-height: 1.6; font-size: 14px;">
                            <?= $notice_preview ?>
                        </div>
                    </div>
                </div>

                <!-- 일정 위젯 -->
                <div class="widget-card">
                    <div class="widget-header">
                        <h2 class="widget-title">
                            <span class="material-symbols-rounded widget-icon">calendar_month</span>
                            Schedule
                        </h2>
                        <a href="/manage/" class="widget-action">Edit</a>
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
                    New Competition
                </a>
                <a href="/results/" class="btn btn-secondary">
                    <span class="material-symbols-rounded">visibility</span>
                    View Results
                </a>
                <a href="/manage/" class="btn btn-secondary">
                    <span class="material-symbols-rounded">tune</span>
                    Settings
                </a>
            </div>
        </main>
    </div>
</body>
</html>