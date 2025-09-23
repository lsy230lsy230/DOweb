<?php
// 전체 결과 목록 파일 경로
$all_results_file = __DIR__ . '/index_results.html';
header('Content-Type: text/html; charset=UTF-8');
$results_html = "<div class='result-error'><span class='material-symbols-rounded'>error</span> 전체 결과 파일을 찾을 수 없습니다.</div>";
if (file_exists($all_results_file)) {
    $raw = file_get_contents($all_results_file);
    if ($raw !== false) {
        $results_html = $raw;
    } else {
        $results_html = "<div class='result-error'><span class='material-symbols-rounded'>error</span> 파일을 읽을 수 없습니다.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>경기 결과 - danceoffice.net</title>
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
            
            .results-section {
                background: #222;
                border-radius: 15px;
                padding: 25px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .results-section h2 {
                color: #03C75A;
                font-size: 20px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
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
            
            .results-section {
                background: rgba(34, 34, 34, 0.95);
                border-radius: 20px;
                padding: 25px;
                box-shadow: 0 10px 30px rgba(3, 199, 90, 0.1);
                backdrop-filter: blur(20px);
                border: 1px solid #31343a;
            }
            
            .results-section h2 {
                color: #03C75A;
                font-size: 18px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 8px;
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
                    <a href="/results/" class="active">경기 결과</a>
                    <a href="/comprehensive/">종합결과</a>
                    <a href="/notice/">공지사항</a>
                    <a href="/manage/">관리자</a>
                </nav>
            </div>
        </aside>
        
        <header class="main-header">
            <h1><span class="material-symbols-rounded">sports_score</span> 경기 결과</h1>
        </header>
        
        <aside class="ad-side">
            <!-- 광고 영역 -->
        </aside>
        
        <main>
            <section class="results-section">
                <h2><span class="material-symbols-rounded">sports_score</span> 전체 경기 결과</h2>
                <div class="result-area">
                    <?php echo $results_html; ?>
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
        <a href="/results/" class="active">
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