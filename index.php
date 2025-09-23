<?php
$base_ads_dir = "/volume1/web/data/";
function read_banner($pos) {
    $img_web = "/data/$pos.jpg";
    $img_file = "/volume1/web/data/$pos.jpg";
    $link_file = "/volume1/web/data/$pos.link";
    $link = file_exists($link_file) ? trim(file_get_contents($link_file)) : "";
    if (file_exists($img_file)) {
        if ($link) return "<a href='$link' target='_blank'><img src='$img_web' alt='{$pos} 광고'></a>";
        else return "<img src='$img_web' alt='{$pos} 광고'>";
    }
    return "";
}

// 공지 및 일정 미리보기
$notice_file = "/volume1/web/data/notice.txt";
$schedule_file = "/volume1/web/data/schedule.txt";
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
    <title>댄스스포츠 대회 실시간 정보 - danceoffice.net</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        /* PC 버전 스타일 */
        @media (min-width: 1024px) {
            body {
                background: #181B20;
                min-height: 100vh;
                font-size: 14px;
                line-height: 1.5;
            }
            
            .main-container {
                display: grid;
                grid-template-columns: 200px 1fr 180px;
                grid-template-rows: auto 1fr auto;
                grid-template-areas: 
                    "sidebar-left header sidebar-right"
                    "sidebar-left main sidebar-right"
                    "footer footer footer";
                min-height: 100vh;
                gap: 15px;
                padding: 15px;
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .main-header {
                grid-area: header;
                background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
                position: relative;
                overflow: hidden;
                border: 2px solid #03C75A;
            }
            
            .main-header::before {
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
            
            .logo-nav {
                position: relative;
                z-index: 2;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .main-logo {
                height: 40px;
                filter: drop-shadow(0 3px 6px rgba(0,0,0,0.2));
            }
            
            .main-nav {
                display: flex;
                gap: 20px;
            }
            
            .main-nav a {
                color: white;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                padding: 10px 20px;
                border-radius: 20px;
                transition: all 0.3s ease;
                background: rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(10px);
            }
            
            .main-nav a:hover {
                background: #03C75A;
                color: #222;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(3, 199, 90, 0.3);
            }
            
            .sidebar-left {
                grid-area: sidebar-left;
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .sidebar-left .logo-nav {
                background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
                border: 2px solid #03C75A;
                text-align: center;
            }
            
            .sidebar-left .main-logo {
                height: 50px;
                margin-bottom: 20px;
                filter: drop-shadow(0 3px 6px rgba(0,0,0,0.2));
            }
            
            .sidebar-left .main-nav {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .sidebar-left .main-nav a {
                color: white;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                padding: 12px 20px;
                border-radius: 20px;
                transition: all 0.3s ease;
                background: rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(10px);
                text-align: center;
            }
            
            .sidebar-left .main-nav a:hover {
                background: #03C75A;
                color: #222;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(3, 199, 90, 0.3);
            }
            
            .ad-side {
                grid-area: sidebar-right;
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .ad-side img {
                width: 100%;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                transition: transform 0.3s ease;
            }
            
            .ad-side img:hover {
                transform: scale(1.03);
            }
            
            main {
                grid-area: main;
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .info-section {
                background: #222;
                border-radius: 15px;
                padding: 25px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .info-cards {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            
            .info-card {
                background: linear-gradient(135deg, #181B20 0%, #1E2126 100%);
                color: white;
                padding: 20px;
                border-radius: 15px;
                position: relative;
                overflow: hidden;
                transition: transform 0.3s ease;
                border: 2px solid #03C75A;
            }
            
            .info-card:hover {
                transform: translateY(-3px);
            }
            
            .info-card::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(3, 199, 90, 0.08) 0%, transparent 70%);
                animation: float 8s ease-in-out infinite;
            }
            
            .card-icon {
                font-size: 36px;
                margin-bottom: 15px;
                position: relative;
                z-index: 2;
                color: #03C75A;
            }
            
            .info-card h2 {
                font-size: 18px;
                margin-bottom: 15px;
                position: relative;
                z-index: 2;
                color: #03C75A;
            }
            
            .notice-preview, .schedule-preview {
                background: rgba(3, 199, 90, 0.08);
                padding: 15px;
                border-radius: 12px;
                margin-bottom: 15px;
                backdrop-filter: blur(10px);
                position: relative;
                z-index: 2;
                min-height: 80px;
                border: 1px solid rgba(3, 199, 90, 0.2);
                font-size: 13px;
                line-height: 1.4;
            }
            
            .button {
                display: inline-block;
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                padding: 12px 24px;
                border-radius: 20px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
                position: relative;
                z-index: 2;
                backdrop-filter: blur(10px);
                font-size: 13px;
            }
            
            .button:hover {
                background: linear-gradient(90deg, #00BFAE 60%, #03C75A 100%);
                color: white;
                transform: translateY(-2px);
            }
            
            .results-section {
                background: #1E2126;
                border-radius: 15px;
                padding: 25px;
                text-align: center;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .results-section h2 {
                color: #03C75A;
                font-size: 20px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
            }
            
            .results-cta .button {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                padding: 15px 30px;
                font-size: 14px;
            }
            
            .ad-section {
                background: #1E2126;
                border-radius: 15px;
                padding: 25px;
                text-align: center;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .competitions-section {
                background: #1E2126;
                border-radius: 15px;
                padding: 25px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .competitions-section h2 {
                color: #03C75A;
                font-size: 20px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .competitions-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
            
            .competition-card {
                background: linear-gradient(135deg, #181B20 0%, #1E2126 100%);
                border-radius: 15px;
                padding: 20px;
                border: 2px solid #03C75A;
                transition: transform 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .competition-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.2);
            }
            
            .competition-card.upcoming {
                border-color: #00BFAE;
            }
            
            .competition-card.completed {
                border-color: #FFD700;
            }
            
            .comp-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 15px;
            }
            
            .comp-header h3 {
                color: #03C75A;
                font-size: 16px;
                margin: 0;
                flex: 1;
            }
            
            .comp-date {
                background: rgba(3, 199, 90, 0.2);
                color: #03C75A;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
            }
            
            .comp-details p {
                color: #ccc;
                font-size: 14px;
                margin: 8px 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .comp-details .material-symbols-rounded {
                font-size: 16px;
                color: #03C75A;
            }
            
            .comp-actions {
                display: flex;
                gap: 10px;
                margin-top: 15px;
            }
            
            .comp-actions .button {
                flex: 1;
                text-align: center;
                font-size: 12px;
                padding: 8px 16px;
            }
            
            .comp-actions .button.secondary {
                background: rgba(3, 199, 90, 0.1);
                color: #03C75A;
            }
            
            .comp-actions .button.secondary:hover {
                background: rgba(3, 199, 90, 0.2);
            }
            
            .ad-section h2 {
                color: #00BFAE;
                font-size: 18px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
            }
            
            .ad-main img {
                max-width: 100%;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            
            .main-footer {
                grid-area: footer;
                text-align: center;
                color: #03C75A;
                padding: 15px;
                background: #222;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
                font-size: 13px;
            }
        }
        
        /* 모바일 버전 스타일 */
        @media (max-width: 1023px) {
            body {
                background: #181B20;
                min-height: 100vh;
                margin: 0;
                padding: 0;
                padding-bottom: 80px; /* 하단 네비게이션 공간 */
            }
            
            .main-container {
                display: flex;
                flex-direction: column;
                min-height: 100vh;
            }
            
            .main-header {
                background: rgba(26, 29, 33, 0.9);
                backdrop-filter: blur(20px);
                padding: 20px;
                position: sticky;
                top: 0;
                z-index: 100;
                border-bottom: 2px solid #03C75A;
            }
            
            .logo-nav {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .main-logo {
                height: 40px;
                filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            }
            
            .main-nav {
                display: none; /* 상단 네비게이션 숨김 */
            }
            
            .ad-side {
                display: none;
            }
            
            /* 하단 네비게이션 */
            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(26, 29, 33, 0.95);
                backdrop-filter: blur(20px);
                border-top: 2px solid #03C75A;
                padding: 10px 0;
                z-index: 1000;
                display: flex;
                justify-content: space-around;
                align-items: center;
            }
            
            .bottom-nav a {
                color: white;
                text-decoration: none;
                font-weight: 600;
                font-size: 12px;
                padding: 8px 12px;
                border-radius: 15px;
                background: rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(10px);
                transition: all 0.3s ease;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                min-width: 60px;
            }
            
            .bottom-nav a:hover,
            .bottom-nav a.active {
                background: #03C75A;
                color: #222;
                transform: translateY(-2px);
            }
            
            .bottom-nav .nav-icon {
                font-size: 20px;
            }
            
            main {
                flex: 1;
                padding: 20px;
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .info-section {
                background: rgba(34, 34, 34, 0.95);
                border-radius: 20px;
                padding: 25px;
                box-shadow: 0 10px 30px rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(20px);
                border: 1px solid #31343a;
            }
            
            .info-cards {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .info-card {
                background: linear-gradient(135deg, #181B20 0%, #1E2126 100%);
                color: white;
                padding: 25px;
                border-radius: 20px;
                position: relative;
                overflow: hidden;
                border: 2px solid #03C75A;
            }
            
            .card-icon {
                font-size: 36px;
                margin-bottom: 15px;
                color: #03C75A;
            }
            
            .info-card h2 {
                font-size: 20px;
                margin-bottom: 15px;
                color: #03C75A;
            }
            
            .notice-preview, .schedule-preview {
                background: rgba(3, 199, 90, 0.1);
                padding: 15px;
                border-radius: 15px;
                margin-bottom: 15px;
                backdrop-filter: blur(10px);
                min-height: 80px;
                font-size: 14px;
                border: 1px solid rgba(3, 199, 90, 0.2);
            }
            
            .button {
                display: inline-block;
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                padding: 12px 24px;
                border-radius: 20px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
                font-size: 14px;
            }
            
            .button:hover {
                background: linear-gradient(90deg, #00BFAE 60%, #03C75A 100%);
                color: white;
            }
            
            .results-section {
                background: rgba(30, 33, 38, 0.95);
                border-radius: 20px;
                padding: 25px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(20px);
                border: 1px solid #31343a;
            }
            
            .results-section h2 {
                color: #03C75A;
                font-size: 22px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            
            .results-cta .button {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                padding: 15px 30px;
                font-size: 16px;
            }
            
            .ad-section {
                background: rgba(30, 33, 38, 0.95);
                border-radius: 20px;
                padding: 25px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(20px);
                border: 1px solid #31343a;
            }
            
            .ad-section h2 {
                color: #00BFAE;
                font-size: 20px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            
            .ad-main img {
                max-width: 100%;
                border-radius: 15px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            
            .main-footer {
                text-align: center;
                color: #03C75A;
                padding: 20px;
                background: rgba(34, 34, 34, 0.9);
                backdrop-filter: blur(20px);
                border-top: 2px solid #03C75A;
            }
            
            .ad-top, .ad-bottom {
                padding: 10px 20px;
                text-align: center;
            }
            
            .ad-top img, .ad-bottom img {
                max-width: 100%;
                border-radius: 10px;
            }
        }
        
        /* 공통 스타일 */
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <aside class="sidebar-left">
            <div class="logo-nav">
                <img src="/assets/danceoffice-logo.png" alt="Danceoffice Logo" class="main-logo">
                <nav class="main-nav">
                    <a href="/">홈</a>
                    <a href="/results/">경기 결과</a>
                    <a href="/comprehensive/">종합결과</a>
                    <a href="/notice/">공지사항</a>
                    <a href="/manage/">관리자</a>
                </nav>
            </div>
        </aside>
        
        <header class="main-header">
            <div class="ad-top">
                <?= read_banner("top") ?>
            </div>
        </header>
        
        <aside class="ad-side">
            <?= read_banner("right") ?>
        </aside>
        
        <main>
            <section class="info-section">
                <div class="info-cards">
                    <div class="info-card notice-card">
                        <div class="card-icon"><span class="material-symbols-rounded">campaign</span></div>
                        <h2>공지사항</h2>
                        <div class="notice-preview">
                            <?= $notice_preview ?>
                        </div>
                        <a class="button" href="/notice/">공지/현장 안내 바로가기</a>
                    </div>
                    <div class="info-card schedule-card">
                        <div class="card-icon"><span class="material-symbols-rounded">calendar_month</span></div>
                        <h2>대회 일정</h2>
                        <div class="schedule-preview">
                            <?= $schedule_preview ?>
                        </div>
                        <a class="button" href="/notice/">전체 일정 보기</a>
                    </div>
                </div>
            </section>
            
            <!-- 다가오는 대회 카드들 -->
            <?php if (!empty($upcoming_competitions)): ?>
            <section class="competitions-section">
                <h2><span class="material-symbols-rounded">event</span> 다가오는 대회</h2>
                <div class="competitions-grid">
                    <?php foreach ($upcoming_competitions as $comp): ?>
                    <div class="competition-card upcoming">
                        <div class="comp-header">
                            <h3><?= htmlspecialchars($comp['title']) ?></h3>
                            <div class="comp-date"><?= date('Y.m.d', strtotime($comp['date'])) ?></div>
                        </div>
                        <div class="comp-details">
                            <p><span class="material-symbols-rounded">location_on</span> <?= htmlspecialchars($comp['place']) ?></p>
                            <?php if ($comp['subtitle']): ?>
                            <p><span class="material-symbols-rounded">info</span> <?= htmlspecialchars($comp['subtitle']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="comp-actions">
                            <a href="/comprehensive/?comp=<?= $comp['id'] ?>" class="button">대회 정보</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- 최근 완료된 대회 카드들 -->
            <?php if (!empty($recent_competitions)): ?>
            <section class="competitions-section">
                <h2><span class="material-symbols-rounded">history</span> 최근 대회 결과</h2>
                <div class="competitions-grid">
                    <?php foreach ($recent_competitions as $comp): ?>
                    <div class="competition-card completed">
                        <div class="comp-header">
                            <h3><?= htmlspecialchars($comp['title']) ?></h3>
                            <div class="comp-date"><?= date('Y.m.d', strtotime($comp['date'])) ?></div>
                        </div>
                        <div class="comp-details">
                            <p><span class="material-symbols-rounded">location_on</span> <?= htmlspecialchars($comp['place']) ?></p>
                        </div>
                        <div class="comp-actions">
                            <a href="/results/?comp=<?= $comp['id'] ?>" class="button">결과 보기</a>
                            <a href="/comprehensive/?comp=<?= $comp['id'] ?>" class="button secondary">상세 정보</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <section class="results-section">
                <h2><span class="material-symbols-rounded">sports_score</span> 실시간 경기 결과</h2>
                <div class="results-cta">
                    <a class="button" href="/results/">전체 결과 보기</a>
                    <a class="button" href="/comprehensive/">종합 결과 보기</a>
                </div>
            </section>
            
            <section class="ad-section">
                <h2><span class="material-symbols-rounded">star</span> 광고 및 이벤트</h2>
                <div class="ad-main">
                    <?= read_banner("main") ?>
                </div>
            </section>
        </main>
        
        <div class="ad-bottom">
            <?= read_banner("bottom") ?>
        </div>
        
        <footer class="main-footer">
            &copy; 2025 danceoffice.net | Powered by Seyoung Lee
        </footer>
    </div>
    
    <!-- 모바일 하단 네비게이션 -->
    <nav class="bottom-nav">
        <a href="/">
            <span class="nav-icon material-symbols-rounded">home</span>
            <span>홈</span>
        </a>
        <a href="/results/">
            <span class="nav-icon material-symbols-rounded">sports_score</span>
            <span>경기결과</span>
        </a>
        <a href="/comprehensive/">
            <span class="nav-icon material-symbols-rounded">analytics</span>
            <span>종합결과</span>
        </a>
        <a href="/notice/">
            <span class="nav-icon material-symbols-rounded">campaign</span>
            <span>공지사항</span>
        </a>
        <a href="/manage/">
            <span class="nav-icon material-symbols-rounded">settings</span>
            <span>관리자</span>
        </a>
    </nav>
    
    <!-- 구글 머티리얼 아이콘 로드 -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
</body>
</html>