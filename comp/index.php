<?php
session_start();
require_once 'auth.php';

// 오너 권한 확인
requirePermission('create_comp');

$user = $_SESSION['user'];

// 대회 목록 및 신규 대회 생성
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comp'])) {
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $place = trim($_POST['place'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $assigned_admin = trim($_POST['assigned_admin'] ?? '');
    
    if ($title && $date && $place && $host && $country && $assigned_admin) {
        $today = date('Ymd');
        $seq = 1;
        do {
            $comp_id = sprintf("%s-%03d", $today, $seq++);
            $comp_path = "$data_dir/$comp_id";
        } while (file_exists($comp_path));
        mkdir($comp_path, 0777, true);
        $info = [
            'title' => $title,
            'date' => $date,
            'place' => $place,
            'host' => $host,
            'country' => $country,
            'assigned_admin' => $assigned_admin,
            'created' => time()
        ];
        file_put_contents("$comp_path/info.json", json_encode($info, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        header("Location: dashboard.php?comp_id=$comp_id");
        exit;
    } else {
        $error = "모든 항목을 입력해주세요.";
    }
}
$comps = load_competitions($data_dir);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>대회 현황판 | danceoffice.net COMP</title>
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
            
            .comp-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .comp-header {
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
            
            .comp-header::before {
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
            
            .comp-title {
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
            
            .comp-subtitle {
                color: #00BFAE;
                font-size: 16px;
                margin: 0;
                position: relative;
                z-index: 2;
            }
            
            .comp-nav {
                display: flex;
                gap: 15px;
                margin-top: 20px;
                position: relative;
                z-index: 2;
                justify-content: center;
            }
            
            .comp-nav a {
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
            
            .comp-nav a:hover {
                background: #03C75A;
                color: #222;
                transform: translateY(-2px);
            }
            
            .newcomp-card {
                background: #222;
                border-radius: 15px;
                padding: 25px;
                margin-bottom: 20px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
                max-width: 500px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .newcomp-card h3 {
                color: #03C75A;
                font-size: 18px;
                margin: 0 0 20px 0;
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
            .form-group select {
                width: 100%;
                padding: 10px;
                border: 1px solid #31343a;
                border-radius: 8px;
                background: #181B20;
                color: #F5F7FA;
                font-size: 13px;
                box-sizing: border-box;
            }
            
            .form-group select {
                cursor: pointer;
            }
            
            .form-group select option {
                background: #181B20;
                color: #F5F7FA;
            }
            
            .btn {
                background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
                color: #222;
                border: none;
                padding: 12px 24px;
                border-radius: 20px;
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
            
            .error {
                color: #ff4444;
                background: rgba(255, 68, 68, 0.1);
                padding: 10px;
                border-radius: 8px;
                margin-bottom: 15px;
                font-size: 13px;
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
            
            .none-msg {
                text-align: center;
                color: #8A8D93;
                font-size: 14px;
                padding: 40px 20px;
            }
            
            .comp-footer {
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
            
            .comp-container {
                padding: 10px;
            }
            
            .comp-header {
                background: rgba(26, 29, 33, 0.9);
                backdrop-filter: blur(20px);
                border-radius: 15px;
                padding: 20px;
                margin-bottom: 15px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
                border: 2px solid #03C75A;
                text-align: center;
            }
            
            .comp-title {
                color: #03C75A;
                font-size: 20px;
                margin: 0 0 8px 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            
            .comp-subtitle {
                color: #00BFAE;
                font-size: 14px;
                margin: 0;
            }
            
            .comp-nav {
                display: flex;
                gap: 10px;
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .comp-nav a {
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
            
            .comp-nav a:hover {
                background: #03C75A;
                color: #222;
            }
            
            .newcomp-card {
                background: rgba(34, 34, 34, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 15px;
                padding: 20px;
                margin-bottom: 15px;
                box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
                border: 1px solid #31343a;
            }
            
            .newcomp-card h3 {
                color: #03C75A;
                font-size: 16px;
                margin: 0 0 15px 0;
                display: flex;
                align-items: center;
                gap: 8px;
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
            
            .none-msg {
                text-align: center;
                color: #8A8D93;
                font-size: 13px;
                padding: 30px 15px;
            }
            
            .comp-footer {
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
    <div class="comp-container">
        <header class="comp-header">
            <h1 class="comp-title">
                <span class="material-symbols-rounded">sports</span>
                오너 대회 관리 시스템
            </h1>
            <p class="comp-subtitle">danceoffice.net - 대회 생성 및 선임관리자 관리</p>
            
            <div style="background: rgba(3, 199, 90, 0.1); border-radius: 10px; padding: 15px; margin: 20px 0; position: relative; z-index: 2;">
                <strong style="color: #03C75A;"><?= htmlspecialchars($user['username']) ?></strong> - <?= getRoleDisplayName($user['role']) ?>
            </div>
            
            <nav class="comp-nav">
                <a href="/">
                    <span class="material-symbols-rounded">home</span>
                    메인으로
                </a>
                <a href="senior_dashboard.php">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                    선임관리자 대시보드
                </a>
                <a href="manage_users.php">
                    <span class="material-symbols-rounded">group</span>
                    선임관리자 관리
                </a>
                <a href="manage_judges.php">
                    <span class="material-symbols-rounded">gavel</span>
                    심사위원 목록
                </a>
                <a href="/manage/">
                    <span class="material-symbols-rounded">settings</span>
                    관리자 페이지
                </a>
                <a href="/results/">
                    <span class="material-symbols-rounded">sports_score</span>
                    경기 결과
                </a>
                <a href="logout.php" style="background: rgba(255, 68, 68, 0.2);">
                    <span class="material-symbols-rounded">logout</span>
                    로그아웃
                </a>
            </nav>
        </header>
        
        <div class="newcomp-card">
            <h3>
                <span class="material-symbols-rounded">add_circle</span>
                새 대회 만들기
            </h3>
            <form method="post">
                <?php if (!empty($error)): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="title">대회명</label>
                    <input type="text" id="title" name="title" placeholder="대회명을 입력하세요" required>
                </div>
                <div class="form-group">
                    <label for="date">일자</label>
                    <input type="date" id="date" name="date" required>
                </div>
                <div class="form-group">
                    <label for="place">장소</label>
                    <input type="text" id="place" name="place" placeholder="장소를 입력하세요" required>
                </div>
                <div class="form-group">
                    <label for="host">주최/주관</label>
                    <input type="text" id="host" name="host" placeholder="주최/주관을 입력하세요" required>
                </div>
                <div class="form-group">
                    <label for="country">개최국가</label>
                    <select id="country" name="country" required>
                        <option value="">국가를 선택하세요</option>
                        <option value="KR">대한민국 (South Korea)</option>
                        <option value="CN">중국 (China)</option>
                        <option value="JP">일본 (Japan)</option>
                        <option value="US">미국 (United States)</option>
                        <option value="DE">독일 (Germany)</option>
                        <option value="UK">영국 (United Kingdom)</option>
                        <option value="FR">프랑스 (France)</option>
                        <option value="IT">이탈리아 (Italy)</option>
                        <option value="RU">러시아 (Russia)</option>
                        <option value="AU">호주 (Australia)</option>
                        <option value="CA">캐나다 (Canada)</option>
                        <option value="SG">싱가포르 (Singapore)</option>
                        <option value="HK">홍콩 (Hong Kong)</option>
                        <option value="TW">대만 (Taiwan)</option>
                        <option value="TH">태국 (Thailand)</option>
                        <option value="MY">말레이시아 (Malaysia)</option>
                        <option value="VN">베트남 (Vietnam)</option>
                        <option value="ID">인도네시아 (Indonesia)</option>
                        <option value="PH">필리핀 (Philippines)</option>
                        <option value="IN">인도 (India)</option>
                        <option value="BR">브라질 (Brazil)</option>
                        <option value="AR">아르헨티나 (Argentina)</option>
                        <option value="MX">멕시코 (Mexico)</option>
                        <option value="ES">스페인 (Spain)</option>
                        <option value="NL">네덜란드 (Netherlands)</option>
                        <option value="BE">벨기에 (Belgium)</option>
                        <option value="CH">스위스 (Switzerland)</option>
                        <option value="AT">오스트리아 (Austria)</option>
                        <option value="SE">스웨덴 (Sweden)</option>
                        <option value="NO">노르웨이 (Norway)</option>
                        <option value="DK">덴마크 (Denmark)</option>
                        <option value="FI">핀란드 (Finland)</option>
                        <option value="PL">폴란드 (Poland)</option>
                        <option value="CZ">체코 (Czech Republic)</option>
                        <option value="OTHER">기타 (Other)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assigned_admin">담당 선임관리자</label>
                    <select id="assigned_admin" name="assigned_admin" required>
                        <option value="">선임관리자를 선택하세요</option>
                        <?php
                        $users = loadUsers();
                        $senior_admins = array_filter($users, function($u) {
                            return $u['role'] === 'senior_admin';
                        });
                        foreach ($senior_admins as $admin):
                        ?>
                            <option value="<?= htmlspecialchars($admin['username']) ?>">
                                <?= htmlspecialchars($admin['username']) ?> (<?= getRoleDisplayName($admin['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="add_comp" value="1" class="btn">
                    <span class="material-symbols-rounded">add</span>
                    대회 생성
                </button>
            </form>
        </div>
        
        <div class="comps-list">
            <h3>
                <span class="material-symbols-rounded">list</span>
                등록된 대회 목록
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
                            <th>담당자</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comps as $comp): ?>
                            <tr>
                                <td><?= htmlspecialchars($comp['title']) ?></td>
                                <td><?= htmlspecialchars($comp['date']) ?></td>
                                <td><?= htmlspecialchars($comp['place']) ?></td>
                                <td><?= htmlspecialchars($comp['host']) ?></td>
                                <td>
                                    <span style="color: #03C75A; font-weight: 600;">
                                        <?= htmlspecialchars($comp['assigned_admin'] ?? '미지정') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                        <a class="dash-btn" href="dashboard.php?comp_id=<?= urlencode($comp['id']) ?>" style="font-size: 11px; padding: 6px 12px;">
                                            <span class="material-symbols-rounded">dashboard</span>
                                            대시보드
                                        </a>
                                        <a class="dash-btn" href="assign_admin.php?comp_id=<?= urlencode($comp['id']) ?>" style="font-size: 11px; padding: 6px 12px; background: linear-gradient(90deg, #17a2b8 70%, #138496 100%);">
                                            <span class="material-symbols-rounded">person_add</span>
                                            담당자
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <footer class="comp-footer">
            &copy; 2025 danceoffice.net | Powered by Seyoung Lee
        </footer>
    </div>
</body>
</html>
