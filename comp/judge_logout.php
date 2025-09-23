<?php
session_start();
$_SESSION = [];
session_destroy();
header("Location: judge_login.php");
exit;