<?php
session_start();
require_once 'auth.php';

// 오너 권한만 접근 가능
requirePermission('manage_users');

$user = $_SESSION['user'];
$message = '';
$error = '';

// 대회 ID 확인
$comp_id = $_GET['comp_id'] ?? '';
if (!$comp_id) {
    die("대회 ID가 필요합니다.");
}

// 대회 정보 로드
$data_dir = __DIR__ . '/data';
$comp_path = "$data_dir/$comp_id";
$info_file = "$comp_path/info.json";

if (!file_exists($info_file)) {
    die("대회 정보를 찾을 수 없습니다.");
}

$comp_info = json_decode(file_get_contents($info_file), true);
if (!$comp_info) {
    die("대회 정보를 읽을 수 없습니다.");
}

// 담당자 변경 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_admin'])) {
    $new_admin = trim($_POST['assigned_admin'] ?? '');
    
    if ($new_admin) {
        $comp_info['assigned_admin'] = $new_admin;
        file_put_contents($info_file, json_encode($comp_info, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $message = "담당자가 '{$new_admin}'로 변경되었습니다.";
    } else {
        $error = "담당자를 선택해주세요.";
    }
}

// 사용자 목록 로드
$users = loadUsers();
$senior_admins = array_filter($users, function($u) {
    return $u['role'] === 'senior_admin';
});

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>담당자 지정 | danceoffice.net</title>
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
            max-width: 600px;
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
        
        .comp-info {
            background: #181B20;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #31343a;
        }
        
        .comp-info h4 {
            color: #03C75A;
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .comp-info p {
            color: #F5F7FA;
            margin: 5px 0;
            font-size: 14px;
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
        
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #31343a;
            border-radius: 8px;
            background: #181B20;
            color: #F5F7FA;
            font-size: 13px;
            box-sizing: border-box;
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
        
        .btn-secondary {
            background: linear-gradient(90deg, #6c757d 70%, #5a6268 100%);
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(90deg, #5a6268 60%, #6c757d 100%);
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
        
        .current-assignment {
            background: rgba(3, 199, 90, 0.1);
            border: 1px solid #03C75A;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .current-assignment strong {
            color: #03C75A;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>
                <span class="material-symbols-rounded">person_add</span>
                대회 담당자 지정
            </h1>
            <p>대회의 담당 선임관리자를 변경합니다</p>
            <nav class="nav">
                <a href="index.php">
                    <span class="material-symbols-rounded">sports</span>
                    대회 관리
                </a>
                <a href="manage_users.php">
                    <span class="material-symbols-rounded">group</span>
                    사용자 관리
                </a>
                <a href="logout.php">
                    <span class="material-symbols-rounded">logout</span>
                    로그아웃
                </a>
            </nav>
        </header>
        
        <div class="card">
            <h3>
                <span class="material-symbols-rounded">info</span>
                대회 정보
            </h3>
            <div class="comp-info">
                <h4><?= h($comp_info['title']) ?></h4>
                <p><strong>일자:</strong> <?= h($comp_info['date']) ?></p>
                <p><strong>장소:</strong> <?= h($comp_info['place']) ?></p>
                <p><strong>주최:</strong> <?= h($comp_info['host']) ?></p>
            </div>
            
            <div class="current-assignment">
                <strong>현재 담당자:</strong> 
                <?= h($comp_info['assigned_admin'] ?? '미지정') ?>
            </div>
            
            <?php if ($message): ?>
                <div class="message"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?= h($error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="assigned_admin">새 담당 선임관리자</label>
                    <select id="assigned_admin" name="assigned_admin" required>
                        <option value="">선임관리자를 선택하세요</option>
                        <?php foreach ($senior_admins as $admin): ?>
                            <option value="<?= h($admin['username']) ?>" 
                                    <?= ($comp_info['assigned_admin'] ?? '') === $admin['username'] ? 'selected' : '' ?>>
                                <?= h($admin['username']) ?> (<?= getRoleDisplayName($admin['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="assign_admin" class="btn">
                        <span class="material-symbols-rounded">save</span>
                        담당자 변경
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <span class="material-symbols-rounded">arrow_back</span>
                        돌아가기
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>






