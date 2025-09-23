<?php
session_start();

// 다국어 지원 시스템 로드
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';

// 로그아웃 처리
$auth->logout();

// 로그인 페이지로 리다이렉트
header('Location: /manage/login.php');
exit;
?>
