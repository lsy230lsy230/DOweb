<?php
session_start();

$comp_id = $_GET['comp_id'] ?? '';
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$comp_id = preg_replace('/[^\d-]/', '', $comp_id);

// Clear scoring session
unset($_SESSION['scoring_logged_in']);
unset($_SESSION['scoring_judge_id']);
unset($_SESSION['scoring_judge_name']);
unset($_SESSION['scoring_judge_country']);
unset($_SESSION['scoring_comp_id']);

// Redirect to login page
header("Location: scoring_login.php?comp_id=" . urlencode($comp_id));
exit;
?>
