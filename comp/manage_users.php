<?php
session_start();
require_once 'auth.php';

// 오너 권한만 접근 가능
requirePermission('manage_users');

$user = $_SESSION['user'];
$message = '';
$error = '';

// 선임관리자 추가 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_senior'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username && $password) {
        $users = loadUsers();
        
        // 중복 확인
        $exists = false;
        foreach ($users as $u) {
            if ($u['username'] === $username) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $users[] = [
                'username' => $username,
                'password' => $password,
                'role' => 'senior_admin',
                'created_by' => $user['username'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            saveUsers($users);
            $message = "선임관리자 '{$username}'가 추가되었습니다.";
        } else {
            $error = "이미 존재하는 사용자명입니다.";
        }
    } else {
        $error = "사용자명과 비밀번호를 입력해주세요.";
    }
}

// 사용자 삭제 처리
if (isset($_GET['delete']) && $_GET['delete']) {
    $delete_username = $_GET['delete'];
    $users = loadUsers();
    $users = array_filter($users, function($u) use ($delete_username) {
        return $u['username'] !== $delete_username;
    });
    saveUsers($users);
    $message = "사용자 '{$delete_username}'가 삭제되었습니다.";
}

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
    <title>사용자 관리 | danceoffice.net</title>
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
            max-width: 1000px;
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
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #31343a;
            border-radius: 8px;
            background: #181B20;
            color: #F5F7FA;
            font-size: 13px;
            box-sizing: border-box;
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
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: #181B20;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .users-table th {
            background: #1E2126;
            color: #03C75A;
            font-weight: 600;
            font-size: 13px;
            padding: 15px 10px;
            text-align: center;
            border-bottom: 1px solid #31343a;
        }
        
        .users-table td {
            color: #F5F7FA;
            font-size: 13px;
            padding: 15px 10px;
            text-align: center;
            border-bottom: 1px solid #31343a;
        }
        
        .users-table tr:last-child td {
            border-bottom: none;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .role-owner {
            background: #ffd700;
            color: #000;
        }
        
        .role-senior_admin {
            background: #03C75A;
            color: #222;
        }
        
        .role-admin {
            background: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>
                <span class="material-symbols-rounded">group</span>
                사용자 관리
            </h1>
            <p>선임관리자 추가 및 관리</p>
            <nav class="nav">
                <a href="index.php">
                    <span class="material-symbols-rounded">sports</span>
                    대회 관리
                </a>
                <a href="senior_dashboard.php">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                    선임관리자 대시보드
                </a>
                <a href="logout.php">
                    <span class="material-symbols-rounded">logout</span>
                    로그아웃
                </a>
            </nav>
        </header>
        
        <div class="card">
            <h3>
                <span class="material-symbols-rounded">person_add</span>
                선임관리자 추가
            </h3>
            <?php if ($message): ?>
                <div class="message"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="username">사용자명</label>
                    <input type="text" id="username" name="username" placeholder="사용자명을 입력하세요" required>
                </div>
                <div class="form-group">
                    <label for="password">비밀번호</label>
                    <input type="password" id="password" name="password" placeholder="비밀번호를 입력하세요" required>
                </div>
                <button type="submit" name="add_senior" class="btn">
                    <span class="material-symbols-rounded">add</span>
                    선임관리자 추가
                </button>
            </form>
        </div>
        
        <div class="card">
            <h3>
                <span class="material-symbols-rounded">group</span>
                등록된 사용자 목록
            </h3>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>사용자명</th>
                        <th>역할</th>
                        <th>생성자</th>
                        <th>생성일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= h($u['username']) ?></td>
                            <td>
                                <span class="role-badge role-<?= $u['role'] ?>">
                                    <?= getRoleDisplayName($u['role']) ?>
                                </span>
                            </td>
                            <td><?= h($u['created_by']) ?></td>
                            <td><?= h($u['created_at']) ?></td>
                            <td>
                                <?php if ($u['role'] !== 'owner' && $u['username'] !== $user['username']): ?>
                                    <a href="?delete=<?= urlencode($u['username']) ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('정말 삭제하시겠습니까?')">
                                        <span class="material-symbols-rounded">delete</span>
                                        삭제
                                    </a>
                                <?php else: ?>
                                    <span style="color: #8A8D93; font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>






