<?php
session_start();

// Check if judge is logged in
if (!isset($_SESSION['scoring_logged_in']) || !$_SESSION['scoring_logged_in']) {
    echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.']);
    exit;
}

$comp_id = $_POST['comp_id'] ?? '';
$event_no = $_POST['event_no'] ?? '';
$dance = $_POST['dance'] ?? '';
$judge_id = $_SESSION['scoring_judge_id'] ?? '';
$type = $_POST['type'] ?? 'final';

if (!$comp_id || !$event_no || !$dance || !$judge_id) {
    echo json_encode(['success' => false, 'error' => '필수 매개변수가 누락되었습니다.']);
    exit;
}

$data_dir = __DIR__ . "/../data/$comp_id";
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0777, true);
}

// File format: {event_no}_{dance}_{judge_id}.adj
$adj_file = "$data_dir/{$event_no}_{$dance}_{$judge_id}.adj";

if ($type === 'recall') {
    // Recall system: save selected players
    $recall_marks_json = $_POST['recall_marks'] ?? '';
    $recall_marks = json_decode($recall_marks_json, true);
    
    if (!$recall_marks || !isset($recall_marks[$dance])) {
        echo json_encode(['success' => false, 'error' => 'Recall 데이터가 없습니다.']);
        exit;
    }
    
    $selected_players = $recall_marks[$dance];
    $file_content = [];
    
    // Save in format: "player" (judge_scoring.php 호환)
    foreach ($selected_players as $player) {
        $file_content[] = "\"$player\"";
    }
    
    $success = file_put_contents($adj_file, implode("\n", $file_content));
} else {
    // Final system: save rankings
    $adjudicator_marks_json = $_POST['adjudicator_marks'] ?? '';
    $adjudicator_marks = json_decode($adjudicator_marks_json, true);
    
    if (!$adjudicator_marks || !isset($adjudicator_marks[$judge_id])) {
        echo json_encode(['success' => false, 'error' => '채점 데이터가 없습니다.']);
        exit;
    }
    
    $rankings = $adjudicator_marks[$judge_id];
    $file_content = [];
    
    // Save in format: player,rank
    foreach ($rankings as $player => $rank) {
        $file_content[] = "$player,$rank";
    }
    
    $success = file_put_contents($adj_file, implode("\n", $file_content));
}

if ($success !== false) {
    echo json_encode([
        'success' => true,
        'message' => '점수가 저장되었습니다.',
        'file' => basename($adj_file),
        'type' => $type
    ]);
} else {
    echo json_encode(['success' => false, 'error' => '파일 저장에 실패했습니다.']);
}
?>
