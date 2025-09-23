<?php
/**
 * 인증 시스템
 * 다국어 지원 관리자 로그인
 */

class AuthManager {
    private $users;
    private $session_timeout = 3600; // 1시간
    
    public function __construct() {
        $this->loadUsers();
    }
    
    private function loadUsers() {
        // 기본 관리자 계정들
        $this->users = [
            'admin' => [
                'password' => password_hash('admin123!', PASSWORD_DEFAULT),
                'role' => 'super_admin',
                'name' => 'Super Administrator',
                'email' => 'admin@danceoffice.net',
                'last_login' => null
            ],
            'manager' => [
                'password' => password_hash('manager123!', PASSWORD_DEFAULT),
                'role' => 'admin',
                'name' => 'Manager',
                'email' => 'manager@danceoffice.net',
                'last_login' => null
            ],
            'judge' => [
                'password' => password_hash('judge123!', PASSWORD_DEFAULT),
                'role' => 'judge',
                'name' => 'Judge',
                'email' => 'judge@danceoffice.net',
                'last_login' => null
            ]
        ];
    }
    
    public function login($username, $password) {
        if (!isset($this->users[$username])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        $user = $this->users[$username];
        
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // 세션 설정
        $_SESSION['user'] = [
            'username' => $username,
            'role' => $user['role'],
            'name' => $user['name'],
            'email' => $user['email'],
            'login_time' => time()
        ];
        
        // 마지막 로그인 시간 업데이트
        $this->users[$username]['last_login'] = time();
        
        return ['success' => true, 'message' => 'Login successful', 'user' => $_SESSION['user']];
    }
    
    public function logout() {
        unset($_SESSION['user']);
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user'])) {
            return false;
        }
        
        // 세션 타임아웃 확인
        if (time() - $_SESSION['user']['login_time'] > $this->session_timeout) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $user_role = $_SESSION['user']['role'];
        
        // 역할 계층 구조
        $role_hierarchy = [
            'judge' => 1,
            'admin' => 2,
            'super_admin' => 3
        ];
        
        return isset($role_hierarchy[$user_role]) && 
               $role_hierarchy[$user_role] >= $role_hierarchy[$role];
    }
    
    public function getCurrentUser() {
        return $this->isLoggedIn() ? $_SESSION['user'] : null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /manage/login.php');
            exit;
        }
    }
    
    public function requireRole($role) {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            header('Location: /manage/access_denied.php');
            exit;
        }
    }
    
    public function getRoleDisplayName($role) {
        $role_names = [
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'judge' => 'Judge'
        ];
        return $role_names[$role] ?? $role;
    }
}

// 전역 인증 매니저
$auth = new AuthManager();
?>
