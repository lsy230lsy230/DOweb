<?php
session_start();
require_once 'auth.php';

// 권한 확인
requirePermission('view_all_comps');

$user = $_SESSION['user'];

// 대회 목록 로드
$data_dir = __DIR__ . '/data';
function load_competitions($data_dir) {
    $comps = [];
    if (!is_dir($data_dir)) return $comps;
    foreach (glob($data_dir . "/*/info.json") as $info_file) {
        $comp_id = basename(dirname($info_file));
        $info = json_decode(file_get_contents($info_file), true);
        if ($info) {
            $info['id'] = $comp_id;
            $comps[] = $info;
        }
    }
    usort($comps, function($a, $b) {
        return ($b['created'] ?? 0) <=> ($a['created'] ?? 0);
    });
    return $comps;
}

$all_comps = load_competitions($data_dir);

// 현재 사용자가 담당하는 대회만 필터링
$comps = array_filter($all_comps, function($comp) use ($user) {
    return isset($comp['assigned_admin']) && $comp['assigned_admin'] === $user['username'];
});

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>선임관리자 대시보드 | danceoffice.net</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
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
            
            .dashboard-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .dashboard-header {
                background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
                border-radius: 15px;
                padding: 25px;
                margin-bottom: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
                border: 2px solid #03C75A;
                position: relative;
                overflow: hidden;
                text-align: center;
            }
            
            .dashboard-header::before {
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
            
            .dashboard-title {
                color: #03C75A;
                font-size: 24px;
                margin: 0 0 10px 0;
                position: relative;
                z-index: 2;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            
            .dashboard-subtitle {
                color: #00BFAE;
                font-size: 16px;
                margin: 0;
                position: relative;
                z-index: 2;
            }
            
            .user-info {
                background: rgba(3, 199, 90, 0.1);
                border-radius: 10px;
                padding: 15px;
                margin: 20px 0;
                position: relative;
                z-index: 2;
            }
            
            .user-info strong {
                color: #03C75A;
            }
            
            .dashboard-nav {
                display: flex;
                gap: 15px;
                margin-top: 20px;
                position: relative;
                z-index: 2;
                justify-content: center;
            }
            
            .dashboard-nav a {
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
            
            .dashboard-nav a:hover {
                background: #03C75A;
                color: #222;
                transform: translateY(-2px);
            }
            
            .comps-list {
                background: #222;
                border-radius: 15px;
                padding: 25px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .comps-list h3 {
                color: #03C75A;
                font-size: 18px;
                margin: 0 0 20px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .comps-table {
                width: 100%;
                border-collapse: collapse;
                background: #181B20;
                border-radius: 8px;
                overflow: hidden;
            }
            
            .comps-table th {
                background: #1E2126;
                color: #03C75A;
                font-weight: 600;
                font-size: 13px;
                padding: 15px 10px;
                text-align: center;
                border-bottom: 1px solid #31343a;
            }
            
            .comps-table td {
                color: #F5F7FA;
                font-size: 13px;
                padding: 15px 10px;
                text-align: center;
                border-bottom: 1px solid #31343a;
            }
            
            .comps-table tr:last-child td {
                border-bottom: none;
            }
            
            .dash-btn {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                text-decoration: none;
                padding: 8px 16px;
                border-radius: 16px;
                font-size: 12px;
                font-weight: 600;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            
            .dash-btn:hover {
                background: linear-gradient(90deg, #00BFAE 60%, #03C75A 100%);
                color: white;
                transform: translateY(-2px);
            }
            
            .logout-btn {
                background: linear-gradient(90deg, #ff4444 70%, #cc0000 100%);
                color: white;
                text-decoration: none;
                padding: 8px 16px;
                border-radius: 16px;
                font-size: 12px;
                font-weight: 600;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            
            .logout-btn:hover {
                background: linear-gradient(90deg, #cc0000 60%, #ff4444 100%);
                transform: translateY(-2px);
            }
            
            .none-msg {
                text-align: center;
                color: #8A8D93;
                font-size: 14px;
                padding: 40px 20px;
            }
            
            .dashboard-footer {
                text-align: center;
                color: #03C75A;
                padding: 20px;
                font-size: 12px;
                margin-top: 20px;
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
            
            .dashboard-container {
                padding: 10px;
            }
            
            .dashboard-header {
                background: rgba(26, 29, 33, 0.9);
                backdrop-filter: blur(20px);
                border-radius: 15px;
                padding: 20px;
                margin-bottom: 15px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
                border: 2px solid #03C75A;
                text-align: center;
            }
            
            .dashboard-title {
                color: #03C75A;
                font-size: 20px;
                margin: 0 0 8px 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            
            .dashboard-subtitle {
                color: #00BFAE;
                font-size: 14px;
                margin: 0;
            }
            
            .user-info {
                background: rgba(3, 199, 90, 0.1);
                border-radius: 8px;
                padding: 12px;
                margin: 15px 0;
                font-size: 12px;
            }
            
            .dashboard-nav {
                display: flex;
                gap: 10px;
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .dashboard-nav a {
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
            
            .dashboard-nav a:hover {
                background: #03C75A;
                color: #222;
            }
            
            .comps-list {
                background: rgba(34, 34, 34, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .comps-list h3 {
                color: #03C75A;
                font-size: 16px;
                margin: 0 0 15px 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .comps-table {
                width: 100%;
                border-collapse: collapse;
                background: #181B20;
                border-radius: 8px;
                overflow: hidden;
                font-size: 12px;
            }
            
            .comps-table th {
                background: #1E2126;
                color: #03C75A;
                font-weight: 600;
                font-size: 11px;
                padding: 12px 8px;
                text-align: center;
                border-bottom: 1px solid #31343a;
            }
            
            .comps-table td {
                color: #F5F7FA;
                font-size: 11px;
                padding: 12px 8px;
                text-align: center;
                border-bottom: 1px solid #31343a;
            }
            
            .dash-btn {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                text-decoration: none;
                padding: 6px 12px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 600;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            
            .logout-btn {
                background: linear-gradient(90deg, #ff4444 70%, #cc0000 100%);
                color: white;
                text-decoration: none;
                padding: 6px 12px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 600;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            
            .none-msg {
                text-align: center;
                color: #8A8D93;
                font-size: 13px;
                padding: 30px 15px;
            }
            
            .dashboard-footer {
                text-align: center;
                color: #03C75A;
                padding: 15px;
                font-size: 11px;
                margin-top: 15px;
            }
        }
        
        /* 공통 스타일 */
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1 class="dashboard-title">
                <span class="material-symbols-rounded">admin_panel_settings</span>
                선임관리자 대시보드
            </h1>
            <p class="dashboard-subtitle">danceoffice.net 대회 관리 시스템</p>
            
            <div class="user-info">
                <strong><?= h($user['username']) ?></strong> - <?= getRoleDisplayName($user['role']) ?>
            </div>
            
            <nav class="dashboard-nav">
                <a href="/">
                    <span class="material-symbols-rounded">home</span>
                    메인으로
                </a>
                <a href="/manage/">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                    관리자 페이지
                </a>
                <a href="/results/">
                    <span class="material-symbols-rounded">sports_score</span>
                    경기 결과
                </a>
                <a href="logout.php" class="logout-btn">
                    <span class="material-symbols-rounded">logout</span>
                    로그아웃
                </a>
            </nav>
        </header>
        
        <div class="comps-list">
            <h3>
                <span class="material-symbols-rounded">list</span>
                담당 대회 목록
            </h3>
            <?php if (empty($comps)): ?>
                <div class="none-msg">등록된 대회가 없습니다.</div>
            <?php else: ?>
                <table class="comps-table">
                    <thead>
                        <tr>
                            <th>대회명</th>
                            <th>일자</th>
                            <th>장소</th>
                            <th>주최</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comps as $comp): ?>
                            <tr>
                                <td><?= h($comp['title']) ?></td>
                                <td><?= h($comp['date']) ?></td>
                                <td><?= h($comp['place']) ?></td>
                                <td><?= h($comp['host']) ?></td>
                                <td>
                                    <a class="dash-btn" href="dashboard.php?comp_id=<?= urlencode($comp['id']) ?>">
                                        <span class="material-symbols-rounded">dashboard</span>
                                        대시보드
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <footer class="dashboard-footer">
            &copy; 2025 danceoffice.net | Powered by Seyoung Lee
        </footer>
    </div>
</body>
</html>
