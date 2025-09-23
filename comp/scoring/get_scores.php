<?php
header('Content-Type: application/json; charset=utf-8');

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$dance = $_GET['dance'] ?? ''; // Optional: specific dance
$judge_id = $_GET['judge_id'] ?? ''; // Optional: specific judge

// Remove BOM and normalize
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$event_no = preg_replace('/\x{FEFF}/u', '', $event_no);
$comp_id = preg_replace('/[^\d-]/', '', $comp_id);
$event_no = preg_replace('/\D+/', '', $event_no);

if (!$comp_id || !$event_no) {
    echo json_encode(['success' => false, 'error' => 'comp_id 또는 event_no 누락']);
    exit;
}

$data_dir = __DIR__ . "/../data/$comp_id";

// Check for JSON scores file first (final round)
$scores_file = "$data_dir/scores_$event_no.json";
if (file_exists($scores_file)) {
    $content = file_get_contents($scores_file);
    $scores_data = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode(['success' => true, 'scores' => $scores_data, 'type' => 'final']);
        exit;
    }
}

// Look for .adj files (recall round)
$adj_files = glob("$data_dir/{$event_no}_*.adj");
$recall_data = [];

foreach ($adj_files as $file) {
    $filename = basename($file);
    
    // Parse filename: event_no_dance_judge_id.adj
    if (preg_match('/^(\d+)_(.+)_(\d+)\.adj$/', $filename, $matches)) {
        $file_event_no = $matches[1];
        $file_dance = $matches[2];
        $file_judge_id = $matches[3];
        
        // Filter by dance and judge if specified
        if ($dance && $file_dance !== $dance) continue;
        if ($judge_id && $file_judge_id !== $judge_id) continue;
        
        // Read file content
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $players = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Remove quotes if present
            $line = trim($line, '"');
            if ($line && is_numeric($line)) {
                $players[] = $line;
            }
        }
        
        if (!isset($recall_data[$file_dance])) {
            $recall_data[$file_dance] = [];
        }
        
        $recall_data[$file_dance][$file_judge_id] = $players;
    }
}

if (!empty($recall_data)) {
    echo json_encode([
        'success' => true, 
        'scores' => $recall_data, 
        'type' => 'recall',
        'files_found' => count($adj_files)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => '점수 파일을 찾을 수 없습니다']);
}
?>
