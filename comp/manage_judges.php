<?php
session_start();
require_once 'auth.php';
require_once 'judge_manager.php';

// 오너 권한만 접근 가능
requirePermission('manage_users');

$user = $_SESSION['user'];
$message = '';
$error = '';

// 심사위원 추가 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_judge'])) {
    $name = trim($_POST['name'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    
    if ($name && $organization && $region && $phone && $email && $specialty) {
        // 사진 업로드 처리
        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['size'] > 0) {
            $upload_dir = __DIR__ . '/judges_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = generateJudgeId() . '.' . $file_extension;
            $upload_path = $upload_dir . $photo;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // 사진 업로드 성공
            } else {
                $error = "사진 업로드에 실패했습니다.";
            }
        }
        
        if (!$error) {
            $judge_data = [
                'name' => $name,
                'organization' => $organization,
                'region' => $region,
                'phone' => $phone,
                'email' => $email,
                'photo' => $photo,
                'specialty' => $specialty
            ];
            
            addJudge($judge_data);
            $message = "심사위원 '{$name}'이 추가되었습니다.";
        }
    } else {
        $error = "모든 필수 항목을 입력해주세요.";
    }
}

// 심사위원 삭제 처리
if (isset($_GET['delete']) && $_GET['delete']) {
    $judge_id = $_GET['delete'];
    $judge = getJudgeById($judge_id);
    if ($judge) {
        deleteJudge($judge_id);
        $message = "심사위원 '{$judge['name']}'이 삭제되었습니다.";
    }
}

$judges = loadJudges();
$regions = getRegions();
$specialties = getSpecialties();

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>심사위원 관리 | danceoffice.net</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <style>
        body {
            background: #181B20;
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            font-family: 'Noto Sans KR', sans-serif;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
            border: 2px solid #03C75A;
            text-align: center;
        }
        
        .header h1 {
            color: #03C75A;
            font-size: 24px;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .header p {
            color: #00BFAE;
            font-size: 16px;
            margin: 0;
        }
        
        .nav {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .nav a {
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
        
        .nav a:hover {
            background: #03C75A;
            color: #222;
            transform: translateY(-2px);
        }
        
        .card {
            background: #222;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(3, 199, 90, 0.08);
            border: 1px solid #31343a;
        }
        
        .card h3 {
            color: #03C75A;
            font-size: 18px;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
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
        
        .btn-small {
            padding: 6px 12px;
            font-size: 11px;
        }
        
        .message {
            color: #03C75A;
            background: rgba(3, 199, 90, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .error {
            color: #ff4444;
            background: rgba(255, 68, 68, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .judges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .judge-card {
            background: #181B20;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #31343a;
            transition: all 0.3s ease;
        }
        
        .judge-card:hover {
            border-color: #03C75A;
            box-shadow: 0 4px 15px rgba(3, 199, 90, 0.1);
        }
        
        .judge-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px auto;
            display: block;
            border: 2px solid #03C75A;
        }
        
        .judge-name {
            color: #03C75A;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .judge-info {
            color: #F5F7FA;
            font-size: 12px;
            margin-bottom: 8px;
        }
        
        .judge-info strong {
            color: #00BFAE;
        }
        
        .judge-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            justify-content: center;
        }
        
        .empty-state {
            text-align: center;
            color: #8A8D93;
            font-size: 14px;
            padding: 40px 20px;
            grid-column: 1 / -1;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #181B20;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            border: 1px solid #31343a;
        }
        
        .stat-number {
            color: #03C75A;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #8A8D93;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>
                <span class="material-symbols-rounded">group</span>
                심사위원 인명사전
            </h1>
            <p>국내 심사위원 정보 관리 시스템</p>
            <nav class="nav">
                <a href="index.php">
                    <span class="material-symbols-rounded">sports</span>
                    대회 관리
                </a>
                <a href="manage_users.php">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                    사용자 관리
                </a>
                <a href="logout.php">
                    <span class="material-symbols-rounded">logout</span>
                    로그아웃
                </a>
            </nav>
        </header>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= count($judges) ?></div>
                <div class="stat-label">총 심사위원</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_unique(array_column($judges, 'region'))) ?></div>
                <div class="stat-label">지역 수</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_unique(array_column($judges, 'specialty'))) ?></div>
                <div class="stat-label">전문 분야</div>
            </div>
        </div>
        
        <div class="card">
            <h3>
                <span class="material-symbols-rounded">person_add</span>
                심사위원 추가
            </h3>
            <?php if ($message): ?>
                <div class="message"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">이름 *</label>
                        <input type="text" id="name" name="name" placeholder="이름을 입력하세요" required>
                    </div>
                    <div class="form-group">
                        <label for="organization">소속 *</label>
                        <input type="text" id="organization" name="organization" placeholder="소속을 입력하세요" required>
                    </div>
                    <div class="form-group">
                        <label for="region">지역 *</label>
                        <select id="region" name="region" required>
                            <option value="">지역을 선택하세요</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?= h($region) ?>"><?= h($region) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone">연락처 *</label>
                        <input type="tel" id="phone" name="phone" placeholder="010-1234-5678" required>
                    </div>
                    <div class="form-group">
                        <label for="email">이메일 *</label>
                        <input type="email" id="email" name="email" placeholder="email@example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="specialty">전문 분야 *</label>
                        <select id="specialty" name="specialty" required>
                            <option value="">전문 분야를 선택하세요</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?= h($specialty) ?>"><?= h($specialty) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="photo">증명사진</label>
                        <input type="file" id="photo" name="photo" accept="image/*">
                    </div>
                </div>
                <button type="submit" name="add_judge" class="btn">
                    <span class="material-symbols-rounded">add</span>
                    심사위원 추가
                </button>
            </form>
        </div>
        
        <div class="card">
            <h3>
                <span class="material-symbols-rounded">list</span>
                심사위원 목록
            </h3>
            <?php if (empty($judges)): ?>
                <div class="empty-state">등록된 심사위원이 없습니다.</div>
            <?php else: ?>
                <div class="judges-grid">
                    <?php foreach ($judges as $judge): ?>
                        <div class="judge-card">
                            <?php if ($judge['photo'] && file_exists(__DIR__ . '/judges_photos/' . $judge['photo'])): ?>
                                <img src="judges_photos/<?= h($judge['photo']) ?>" class="judge-photo" alt="<?= h($judge['name']) ?>">
                            <?php else: ?>
                                <div class="judge-photo" style="background: #31343a; display: flex; align-items: center; justify-content: center; color: #8A8D93;">
                                    <span class="material-symbols-rounded">person</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="judge-name"><?= h($judge['name']) ?></div>
                            
                            <div class="judge-info">
                                <strong>소속:</strong> <?= h($judge['organization']) ?>
                            </div>
                            <div class="judge-info">
                                <strong>지역:</strong> <?= h($judge['region']) ?>
                            </div>
                            <div class="judge-info">
                                <strong>연락처:</strong> <?= h($judge['phone']) ?>
                            </div>
                            <div class="judge-info">
                                <strong>이메일:</strong> <?= h($judge['email']) ?>
                            </div>
                            <div class="judge-info">
                                <strong>전문분야:</strong> <?= h($judge['specialty']) ?>
                            </div>
                            
                            <div class="judge-actions">
                                <a href="?delete=<?= urlencode($judge['id']) ?>" 
                                   class="btn btn-danger btn-small" 
                                   onclick="return confirm('정말 삭제하시겠습니까?')">
                                    <span class="material-symbols-rounded">delete</span>
                                    삭제
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>






