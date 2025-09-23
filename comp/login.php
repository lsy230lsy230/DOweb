<?php
session_start();
require_once 'auth.php';

// 이미 로그인된 경우 리다이렉트
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    if (hasPermission($user, 'create_comp')) {
        header("Location: index.php");
    } else {
        header("Location: senior_dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username && $password) {
        $user = authenticateUser($username, $password);
        if ($user) {
            $_SESSION['user'] = $user;
            if (hasPermission($user, 'create_comp')) {
                header("Location: index.php");
            } else {
                header("Location: senior_dashboard.php");
            }
            exit;
        } else {
            $error = "사용자명 또는 비밀번호가 올바르지 않습니다.";
        }
    } else {
        $error = "사용자명과 비밀번호를 입력해주세요.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>로그인 - danceoffice.net</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <style>
        body {
            background: #181B20;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
            font-family: 'Noto Sans KR', sans-serif;
        }
        .login-container {
            background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(3, 199, 90, 0.2);
            border: 2px solid #03C75A;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .login-title {
            color: #03C75A;
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .login-subtitle {
            color: #00BFAE;
            font-size: 16px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            color: #F5F7FA;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #31343a;
            border-radius: 10px;
            background: #222;
            color: #F5F7FA;
            font-size: 14px;
            box-sizing: border-box;
        }
        .btn {
            background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
            color: #222;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
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
        .role-info {
            background: rgba(3, 199, 90, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #B0B3B8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">
            <span class="material-symbols-rounded">login</span>
            로그인
        </h1>
        <p class="login-subtitle">danceoffice.net 관리 시스템</p>
        <form method="post">
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="form-group">
                <label for="username">사용자명</label>
                <input type="text" id="username" name="username" placeholder="사용자명을 입력하세요" required>
            </div>
            <div class="form-group">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" placeholder="비밀번호를 입력하세요" required>
            </div>
            <button type="submit" class="btn">로그인</button>
        </form>
        <div class="role-info">
            <strong>권한 안내:</strong><br>
            • <strong>오너</strong>: 대회 생성 및 모든 관리<br>
            • <strong>선임관리자</strong>: 등록된 대회 관리<br>
            • <strong>관리자</strong>: 콘텐츠 관리
        </div>
    </div>
</body>
</html>






