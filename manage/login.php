<?php
session_start();

// 다국어 지원 시스템 로드
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';

// 이미 로그인된 경우 리다이렉트
if ($auth->isLoggedIn()) {
    header('Location: /manage/');
    exit;
}

$error_message = '';

// 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        header('Location: /manage/');
        exit;
    } else {
        $error_message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('admin_login') ?> - danceoffice.net</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e2e8f0;
        }
        
        .login-container {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-title {
            color: #3b82f6;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .login-subtitle {
            color: #94a3b8;
            font-size: 16px;
        }
        
        .language-selector {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .language-selector select {
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #e2e8f0;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            color: #f1f5f9;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.8);
            color: #e2e8f0;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .demo-accounts {
            margin-top: 30px;
            padding: 20px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 10px;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .demo-accounts h3 {
            color: #3b82f6;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .demo-account:last-child {
            border-bottom: none;
        }
        
        .demo-account .role {
            color: #94a3b8;
            font-size: 12px;
        }
        
        .back-link {
            margin-top: 20px;
        }
        
        .back-link a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-link a:hover {
            color: #60a5fa;
        }
    </style>
    <script>
        function changeLanguage(langCode) {
            const url = new URL(window.location);
            url.searchParams.set('lang', langCode);
            window.location.href = url.toString();
        }
    </script>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1 class="login-title">
                <span class="material-symbols-rounded">admin_panel_settings</span>
                <?= __('admin_login') ?>
            </h1>
            <p class="login-subtitle"><?= __('admin_login_subtitle') ?></p>
            
            <div class="language-selector">
                <select onchange="changeLanguage(this.value)">
                    <option value="ko" <?= $lang->getCurrentLanguage() === 'ko' ? 'selected' : '' ?>>한국어</option>
                    <option value="ja" <?= $lang->getCurrentLanguage() === 'ja' ? 'selected' : '' ?>>日本語</option>
                    <option value="zh" <?= $lang->getCurrentLanguage() === 'zh' ? 'selected' : '' ?>>中文</option>
                    <option value="ru" <?= $lang->getCurrentLanguage() === 'ru' ? 'selected' : '' ?>>Русский</option>
                </select>
            </div>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <span class="material-symbols-rounded" style="font-size: 16px; vertical-align: middle;">error</span>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username"><?= __('username') ?></label>
                <input type="text" id="username" name="username" required 
                       placeholder="<?= __('username_placeholder') ?>" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password"><?= __('password') ?></label>
                <input type="password" id="password" name="password" required 
                       placeholder="<?= __('password_placeholder') ?>">
            </div>
            
            <button type="submit" class="btn">
                <span class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle;">login</span>
                <?= __('login_button') ?>
            </button>
        </form>
        
        <div class="demo-accounts">
            <h3><?= __('demo_accounts') ?></h3>
            <div class="demo-account">
                <span><strong>admin</strong> / admin123!</span>
                <span class="role"><?= __('super_admin') ?></span>
            </div>
            <div class="demo-account">
                <span><strong>manager</strong> / manager123!</span>
                <span class="role"><?= __('admin') ?></span>
            </div>
            <div class="demo-account">
                <span><strong>judge</strong> / judge123!</span>
                <span class="role"><?= __('judge') ?></span>
            </div>
        </div>
        
        <div class="back-link">
            <a href="/">
                <span class="material-symbols-rounded">arrow_back</span>
                <?= __('back_to_home') ?>
            </a>
        </div>
    </div>
</body>
</html>
