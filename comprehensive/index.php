<?php
// 종합결과 페이지 - 댄스스코어와 자체 시스템 통합
require_once __DIR__ . '/../data/scheduler.php';

$scheduler = new CompetitionScheduler();

// 현재 주간 대회들
$currentWeek = date('Y-m-d', strtotime('monday this week'));
$nextWeek = date('Y-m-d', strtotime('monday next week'));
$lastWeek = date('Y-m-d', strtotime('monday last week'));

$lastWeekCompetitions = $scheduler->getCompetitionsByDateRange($lastWeek, date('Y-m-d', strtotime('sunday last week')));
$currentWeekCompetitions = $scheduler->getCompetitionsByDateRange($currentWeek, date('Y-m-d', strtotime('sunday this week')));
$nextWeekCompetitions = $scheduler->getCompetitionsByDateRange($nextWeek, date('Y-m-d', strtotime('sunday next week')));

// 연도별 대회 목록
$currentYear = date('Y');
$availableYears = [];
$allCompetitions = $scheduler->getAllCompetitions();
foreach ($allCompetitions as $comp) {
    $year = date('Y', strtotime($comp['date']));
    if (!in_array($year, $availableYears)) {
        $availableYears[] = $year;
    }
}
rsort($availableYears); // 최신 연도부터

// 특정 연도 선택
$selectedYear = $_GET['year'] ?? $currentYear;
$yearCompetitions = $scheduler->getCompetitionsByYear($selectedYear);

// 특정 대회 선택
$selectedComp = null;
if (isset($_GET['comp'])) {
    $selectedComp = $scheduler->getCompetitionById($_GET['comp']);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>종합결과 - danceoffice.net</title>
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
            
            .sidebar-left .main-nav a:hover,
            .sidebar-left .main-nav a.active {
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
            
            .main-header {
                grid-area: header;
                background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
                border: 2px solid #03C75A;
                text-align: center;
            }
            
            .main-header h1 {
                color: #03C75A;
                font-size: 24px;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
            }
            
            main {
                grid-area: main;
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .results-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
            }
            
            .result-card {
                background: #222;
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
                transition: transform 0.3s ease;
            }
            
            .result-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 12px 30px rgba(3, 199, 90, 0.15);
            }
            
            .result-card h3 {
                color: #03C75A;
                font-size: 18px;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .result-links {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .result-links a {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                padding: 8px 16px;
                border-radius: 15px;
                text-decoration: none;
                font-weight: 600;
                font-size: 12px;
                transition: all 0.3s ease;
            }
            
            .result-links a:hover {
                background: linear-gradient(90deg, #00BFAE 60%, #03C75A 100%);
                color: white;
                transform: translateY(-2px);
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
                padding-bottom: 80px;
            }
            
            .main-container {
                display: flex;
                flex-direction: column;
                min-height: 100vh;
            }
            
            .sidebar-left {
                display: none;
            }
            
            .ad-side {
                display: none;
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
            
            .main-header h1 {
                color: #03C75A;
                font-size: 20px;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            
            main {
                flex: 1;
                padding: 20px;
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .results-grid {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .result-card {
                background: rgba(34, 34, 34, 0.95);
                border-radius: 20px;
                padding: 20px;
                box-shadow: 0 10px 30px rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(20px);
                border: 1px solid #31343a;
            }
            
            .result-card h3 {
                color: #03C75A;
                font-size: 16px;
                margin-bottom: 15px;
            }
            
            .result-links {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .result-links a {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                padding: 10px 16px;
                border-radius: 15px;
                text-decoration: none;
                font-weight: 600;
                font-size: 12px;
                transition: all 0.3s ease;
            }
            
            .result-links a:hover {
                background: linear-gradient(90deg, #00BFAE 60%, #03C75A 100%);
                color: white;
            }
            
            .main-footer {
                text-align: center;
                color: #03C75A;
                padding: 20px;
                background: rgba(34, 34, 34, 0.9);
                backdrop-filter: blur(20px);
                border-top: 2px solid #03C75A;
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
        }
        
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
                    <a href="/comprehensive/" class="active">종합결과</a>
                    <a href="/notice/">공지사항</a>
                    <a href="/manage/">관리자</a>
                </nav>
            </div>
        </aside>
        
        <header class="main-header">
            <h1><span class="material-symbols-rounded">analytics</span> 종합결과</h1>
        </header>
        
        <aside class="ad-side">
            <!-- 광고 영역 -->
        </aside>
        
        <main>
            <!-- 주간 대회 현황 -->
            <section class="weekly-section">
                <h2><span class="material-symbols-rounded">calendar_view_week</span> 주간 대회 현황</h2>
                
                <!-- 지난주 대회 -->
                <?php if (!empty($lastWeekCompetitions)): ?>
                <div class="week-group">
                    <h3><span class="material-symbols-rounded">history</span> 지난주 대회</h3>
                    <div class="competitions-grid">
                        <?php foreach ($lastWeekCompetitions as $comp): ?>
                        <div class="competition-card completed">
                            <div class="comp-header">
                                <h4><?= htmlspecialchars($comp['title']) ?></h4>
                                <div class="comp-date"><?= date('m.d', strtotime($comp['date'])) ?></div>
                            </div>
                            <div class="comp-details">
                                <p><span class="material-symbols-rounded">location_on</span> <?= htmlspecialchars($comp['place']) ?></p>
                            </div>
                            <div class="comp-actions">
                                <a href="/results/?comp=<?= $comp['id'] ?>" class="button">결과 보기</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 이번주 대회 -->
                <?php if (!empty($currentWeekCompetitions)): ?>
                <div class="week-group">
                    <h3><span class="material-symbols-rounded">event</span> 이번주 대회</h3>
                    <div class="competitions-grid">
                        <?php foreach ($currentWeekCompetitions as $comp): ?>
                        <div class="competition-card <?= $comp['status'] === 'ongoing' ? 'ongoing' : 'upcoming' ?>">
                            <div class="comp-header">
                                <h4><?= htmlspecialchars($comp['title']) ?></h4>
                                <div class="comp-date"><?= date('m.d', strtotime($comp['date'])) ?></div>
                            </div>
                            <div class="comp-details">
                                <p><span class="material-symbols-rounded">location_on</span> <?= htmlspecialchars($comp['place']) ?></p>
                                <p><span class="material-symbols-rounded">info</span> <?= ucfirst($comp['status']) ?></p>
                            </div>
                            <div class="comp-actions">
                                <?php if ($comp['status'] === 'completed'): ?>
                                    <a href="/results/?comp=<?= $comp['id'] ?>" class="button">결과 보기</a>
                                <?php else: ?>
                                    <a href="/comprehensive/?comp=<?= $comp['id'] ?>" class="button">대회 정보</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 다음주 대회 -->
                <?php if (!empty($nextWeekCompetitions)): ?>
                <div class="week-group">
                    <h3><span class="material-symbols-rounded">schedule</span> 다음주 대회</h3>
                    <div class="competitions-grid">
                        <?php foreach ($nextWeekCompetitions as $comp): ?>
                        <div class="competition-card upcoming">
                            <div class="comp-header">
                                <h4><?= htmlspecialchars($comp['title']) ?></h4>
                                <div class="comp-date"><?= date('m.d', strtotime($comp['date'])) ?></div>
                            </div>
                            <div class="comp-details">
                                <p><span class="material-symbols-rounded">location_on</span> <?= htmlspecialchars($comp['place']) ?></p>
                            </div>
                            <div class="comp-actions">
                                <a href="/comprehensive/?comp=<?= $comp['id'] ?>" class="button">대회 정보</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
            
            <!-- 연도별 대회 목록 -->
            <section class="yearly-section">
                <h2><span class="material-symbols-rounded">calendar_month</span> 연도별 대회 목록</h2>
                
                <!-- 연도 선택 -->
                <div class="year-selector">
                    <?php foreach ($availableYears as $year): ?>
                        <a href="?year=<?= $year ?>" class="year-button <?= $selectedYear == $year ? 'active' : '' ?>">
                            <?= $year ?>년
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- 선택된 연도의 대회들 -->
                <div class="year-competitions">
                    <?php if (empty($yearCompetitions)): ?>
                        <div class="no-competitions">
                            <span class="material-symbols-rounded">info</span>
                            <p><?= $selectedYear ?>년에는 등록된 대회가 없습니다.</p>
                        </div>
                    <?php else: ?>
                        <div class="competitions-timeline">
                            <?php foreach ($yearCompetitions as $comp): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?= date('m.d', strtotime($comp['date'])) ?></div>
                                <div class="timeline-content">
                                    <h4><?= htmlspecialchars($comp['title']) ?></h4>
                                    <p><?= htmlspecialchars($comp['place']) ?></p>
                                    <div class="timeline-actions">
                                        <?php if ($comp['status'] === 'completed'): ?>
                                            <a href="/results/?comp=<?= $comp['id'] ?>" class="button small">결과 보기</a>
                                        <?php else: ?>
                                            <a href="/comprehensive/?comp=<?= $comp['id'] ?>" class="button small">정보 보기</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
        
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
        <a href="/comprehensive/" class="active">
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
