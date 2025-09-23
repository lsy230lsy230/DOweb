<?php
// 인증 및 권한 관리 시스템

function loadUsers() {
    $users_file = __DIR__ . '/users.txt';
    $users = [];
    
    if (file_exists($users_file)) {
        $lines = file($users_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue; // 주석 무시
            $parts = explode(',', $line);
            if (count($parts) >= 5) {
                $users[] = [
                    'username' => trim($parts[0]),
                    'password' => trim($parts[1]),
                    'role' => trim($parts[2]),
                    'created_by' => trim($parts[3]),
                    'created_at' => trim($parts[4])
                ];
            }
        }
    }
    return $users;
}

function saveUsers($users) {
    $users_file = __DIR__ . '/users.txt';
    $content = "# 사용자 관리 파일\n";
    $content .= "# 형식: username,password,role,created_by,created_at\n";
    $content .= "# role: owner, senior_admin, admin\n";
    $content .= "# created_by: 이 사용자를 생성한 사용자 (owner는 'system')\n\n";
    
    foreach ($users as $user) {
        $content .= implode(',', $user) . "\n";
    }
    
    file_put_contents($users_file, $content);
}

function authenticateUser($username, $password) {
    $users = loadUsers();
    foreach ($users as $user) {
        if ($user['username'] === $username && $user['password'] === $password) {
            return $user;
        }
    }
    return false;
}

function hasPermission($user, $permission) {
    if (!$user) return false;
    
    $role_permissions = [
        'owner' => ['create_comp', 'manage_users', 'view_all_comps', 'manage_comp'],
        'senior_admin' => ['view_all_comps', 'manage_comp'],
        'admin' => ['manage_content']
    ];
    
    return in_array($permission, $role_permissions[$user['role']] ?? []);
}

function requirePermission($permission) {
    if (!isset($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
    
    if (!hasPermission($_SESSION['user'], $permission)) {
        die("권한이 없습니다.");
    }
}

function getRoleDisplayName($role) {
    $role_names = [
        'owner' => '오너',
        'senior_admin' => '선임관리자',
        'admin' => '관리자'
    ];
    return $role_names[$role] ?? $role;
}
?>


