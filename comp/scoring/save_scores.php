<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'skating_system.php';

// Check if it's FormData (POST) or JSON input
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    // FormData input
    $input = $_POST;
} else {
    // JSON input
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$comp_id = $input['comp_id'] ?? '';
$event_no = $input['event_no'] ?? '';
$type = $input['type'] ?? 'final'; // 'final' or 'recall'
$is_final = $input['is_final'] ?? '0';
$adjudicator_marks = $input['adjudicator_marks'] ?? [];
$recall_marks = $input['recall_marks'] ?? [];

// Get judge ID from session first
session_start();
$judge_id = $_SESSION['scoring_judge_id'] ?? 'unknown';

// Handle final round from FormData
if ($is_final === '1' || $is_final === 1) {
    $type = 'final';
    // Convert FormData to adjudicator_marks format
    if (isset($input['adjudicator_marks'])) {
        // Check if it's JSON string
        if (is_string($input['adjudicator_marks'])) {
            $adjudicator_marks = json_decode($input['adjudicator_marks'], true);
            error_log("Decoded adjudicator_marks: " . print_r($adjudicator_marks, true));
        } else {
            $adjudicator_marks = $input['adjudicator_marks'];
        }
    } else if (isset($input['final_ranking'])) {
        // Handle final_ranking format from touch-based system
        $adjudicator_marks = [];
        $adjudicator_marks[$judge_id] = [];
        foreach ($input['final_ranking'] as $rank => $player) {
            if (!empty($player)) {
                $adjudicator_marks[$judge_id][$player] = intval($rank);
            }
        }
    } else {
        // Handle direct place inputs
        $adjudicator_marks = [];
        foreach ($input as $key => $value) {
            if (strpos($key, 'adjudicator_marks[') === 0) {
                // Parse the key to extract adjudicator and couple info
                preg_match('/adjudicator_marks\[(\d+)\]\[(.+)\]/', $key, $matches);
                if (count($matches) === 3) {
                    $adjudicator_id = $matches[1];
                    $couple_id = $matches[2];
                    if (!isset($adjudicator_marks[$adjudicator_id])) {
                        $adjudicator_marks[$adjudicator_id] = [];
                    }
                    $adjudicator_marks[$adjudicator_id][$couple_id] = intval($value);
                }
            }
        }
    }
}

// Remove BOM and normalize
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$event_no = preg_replace('/\x{FEFF}/u', '', $event_no);
$comp_id = preg_replace('/[^\d-]/', '', $comp_id);
$event_no = preg_replace('/[^0-9\-]/', '', $event_no); // 하이픈 허용

if (!$comp_id || !$event_no) {
    echo json_encode(['success' => false, 'error' => '필수 데이터가 누락되었습니다']);
    exit;
}

$data_dir = __DIR__ . "/../data/$comp_id";
if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0777, true);
}

// Get judge ID from session (already started above)
$judge_id = $_SESSION['scoring_judge_id'] ?? 'unknown';

if ($type === 'final') {
    // Final round - save individual dance scores
    if (empty($adjudicator_marks)) {
        echo json_encode(['success' => false, 'error' => '심사위원 점수가 없습니다']);
        exit;
    }
    
    // Get dance name from input
    $dance = $input['dance'] ?? 'unknown';
    
    // Validate adjudicator marks format
    $validated_marks = [];
    foreach ($adjudicator_marks as $adjudicator_id => $marks) {
        if (is_array($marks)) {
            $validated_marks[$adjudicator_id] = [];
            foreach ($marks as $couple_id => $place) {
                if (is_numeric($place) && $place > 0) {
                    $validated_marks[$adjudicator_id][$couple_id] = intval($place);
                }
            }
        }
    }
    
    if (empty($validated_marks)) {
        echo json_encode(['success' => false, 'error' => '유효한 심사위원 점수가 없습니다']);
        exit;
    }
    
    // Save individual dance file: event_no_dance_judge_id.adj
    $dance_file = "{$event_no}_{$dance}_{$judge_id}.adj";
    $dance_filepath = "$data_dir/$dance_file";
    
    // Save as .adj file (one player per line with rank)
    $content = '';
    foreach ($validated_marks[$judge_id] as $player => $rank) {
        $content .= "$player,$rank\n";
    }
    
    if (file_put_contents($dance_filepath, $content) === false) {
        echo json_encode(['success' => false, 'error' => '파일 저장 실패']);
        exit;
    }
    
    // Check if all judges have submitted for this dance
    $all_judges_submitted = checkAllJudgesSubmitted($data_dir, $event_no, $dance);
    
    if ($all_judges_submitted) {
        // All judges submitted - calculate final results for this dance
        $final_results = calculateFinalResultsForDance($data_dir, $event_no, $dance);
        
        echo json_encode([
            'success' => true, 
            'message' => "$dance 댄스 채점이 완료되었습니다",
            'dance_completed' => true,
            'results' => $final_results
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => "$dance 댄스 점수가 저장되었습니다",
            'dance_completed' => false
        ]);
    }
    
} else if ($type === 'recall') {
    // Recall round - save dance by dance
    if (empty($recall_marks)) {
        echo json_encode(['success' => false, 'error' => 'Recall 점수가 없습니다']);
        exit;
    }
    
    $saved_files = [];
    $errors = [];
    
    foreach ($recall_marks as $dance => $selected_players) {
        if (empty($selected_players)) {
            $errors[] = "$dance: 선택된 선수가 없습니다";
            continue;
        }
        
        // Create filename: event_no_dance_judge_id.adj
        $filename = "{$event_no}_{$dance}_{$judge_id}.adj";
        $filepath = "$data_dir/$filename";
        
        // Save as .adj file (one player per line, quoted)
        $content = '';
        foreach ($selected_players as $player) {
            $content .= '"' . $player . '"' . "\n";
        }
        
        if (file_put_contents($filepath, $content) === false) {
            $errors[] = "$dance: 파일 저장 실패";
        } else {
            $saved_files[] = $filename;
        }
    }
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => false, 
            'error' => implode(', ', $errors)
        ]);
    } else {
        // Check if all dances are completed for this judge
        $all_completed = checkAllRecallDancesCompleted($data_dir, $event_no, $judge_id);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Recall 점수가 저장되었습니다',
            'saved_files' => $saved_files,
            'all_completed' => $all_completed
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => '잘못된 요청 타입입니다']);
}

// Helper function to check if all recall dances are completed for a judge
function checkAllRecallDancesCompleted($data_dir, $event_no, $judge_id) {
    // Get event data to find all dances
    $runorder_file = "$data_dir/RunOrder_Tablet.txt";
    if (!file_exists($runorder_file)) {
        error_log("checkAllRecallDancesCompleted: RunOrder file not found");
        return false;
    }
    
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $event_dances = [];
    
    foreach ($lines as $line) {
        $line = preg_replace('/\x{FEFF}/u', '', $line);
        $cols = array_map('trim', explode(',', $line));
        
        if (preg_match('/^bom/', $line)) continue;
        
        $file_event_no = preg_replace('/\x{FEFF}/u', '', $cols[0]);
        $detail_no = trim($cols[13] ?? '');
        $match_event_no = !empty($detail_no) ? $detail_no : preg_replace('/\D+/', '', $file_event_no);
        
        if ($match_event_no === $event_no) {
            // Get dances from columns 6-10
            for ($i = 6; $i <= 10; $i++) {
                if (!empty($cols[$i])) {
                    $event_dances[] = $cols[$i];
                }
            }
            break;
        }
    }
    
    error_log("checkAllRecallDancesCompleted: Event dances for $event_no: " . json_encode($event_dances));
    
    if (empty($event_dances)) {
        error_log("checkAllRecallDancesCompleted: No dances found for event $event_no");
        return false;
    }
    
    // Check if all dance files exist for this judge
    foreach ($event_dances as $dance) {
        $adj_file = "$data_dir/{$event_no}_{$dance}_{$judge_id}.adj";
        error_log("checkAllRecallDancesCompleted: Checking file: $adj_file");
        if (!file_exists($adj_file)) {
            error_log("checkAllRecallDancesCompleted: File not found: $adj_file");
            return false;
        }
    }
    
    error_log("checkAllRecallDancesCompleted: All files found for event $event_no, judge $judge_id");
    return true;
}

// Helper function to check if all judges have submitted for a dance
function checkAllJudgesSubmitted($data_dir, $event_no, $dance) {
    // Get list of judges from adjudicators.txt
    $adjudicators_file = "$data_dir/adjudicators.txt";
    if (!file_exists($adjudicators_file)) {
        return false;
    }
    
    $judges = [];
    $lines = file($adjudicators_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = explode(',', $line);
        if (count($cols) >= 2) {
            $judges[] = trim($cols[1]); // Judge ID is in second column
        }
    }
    
    // Check if all judges have submitted
    foreach ($judges as $judge_id) {
        $dance_file = "{$event_no}_{$dance}_{$judge_id}.adj";
        $dance_filepath = "$data_dir/$dance_file";
        if (!file_exists($dance_filepath)) {
            return false;
        }
    }
    
    return true;
}

// Helper function to calculate final results for a dance
function calculateFinalResultsForDance($data_dir, $event_no, $dance) {
    // Get all judge scores for this dance
    $adjudicator_marks = [];
    
    $adjudicators_file = "$data_dir/adjudicators.txt";
    if (file_exists($adjudicators_file)) {
        $lines = file($adjudicators_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $cols = explode(',', $line);
            if (count($cols) >= 2) {
                $judge_id = trim($cols[1]);
                $dance_file = "{$event_no}_{$dance}_{$judge_id}.adj";
                $dance_filepath = "$data_dir/$dance_file";
                
                if (file_exists($dance_filepath)) {
                    $adjudicator_marks[$judge_id] = [];
                    $content = file_get_contents($dance_filepath);
                    $lines = explode("\n", trim($content));
                    foreach ($lines as $line) {
                        if (strpos($line, ',') !== false) {
                            list($player, $rank) = explode(',', $line, 2);
                            $adjudicator_marks[$judge_id][trim($player)] = intval(trim($rank));
                        }
                    }
                }
            }
        }
    }
    
    if (empty($adjudicator_marks)) {
        return [];
    }
    
    // Calculate results using Skating System
    require_once 'skating_system.php';
    $results = SkatingSystem::calculateSingleDance($adjudicator_marks);
    
    // Save final results for this dance
    $final_file = "{$event_no}_{$dance}_final.json";
    $final_filepath = "$data_dir/$final_file";
    $final_data = [
        'event_no' => $event_no,
        'dance' => $dance,
        'adjudicator_marks' => $adjudicator_marks,
        'results' => $results,
        'calculated_at' => date('Y-m-d H:i:s'),
        'num_adjudicators' => count($adjudicator_marks),
        'num_couples' => count($results['results'])
    ];
    
    file_put_contents($final_filepath, json_encode($final_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    return $results['results'];
}
?>
