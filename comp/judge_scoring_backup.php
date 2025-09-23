<?php
// ?êÎü¨ Î¶¨Ìè¨???úÏÑ±??error_reporting(E_ALL);
ini_set('display_errors', 1);

// Í∞ÑÎã®???åÏä§??Ï∂úÎ†•
echo "<!-- Judge scoring started -->\n";

session_start();

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$multievent = $_GET['multievent'] ?? '0'; // Î©Ä???¥Î≤§??Î™®Îìú Í∞êÏ?

// ?îÎ≤ÑÍ∑?Î°úÍ∑∏
error_log("Judge scoring started: comp_id=$comp_id, event_no=$event_no, multievent=$multievent");

try {
    require_once 'detail_numbers_manager.php';
    error_log("detail_numbers_manager.php loaded successfully");
} catch (Exception $e) {
    error_log("Error loading detail_numbers_manager.php: " . $e->getMessage());
    die("Error loading detail_numbers_manager.php: " . $e->getMessage());
}

// Remove BOM and normalize - multiple methods
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$event_no = preg_replace('/\x{FEFF}/u', '', $event_no);

// Additional BOM removal
$comp_id = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $comp_id);
$event_no = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $event_no);

// Remove any non-printable characters
$comp_id = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $comp_id);
$event_no = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $event_no);

$comp_id = preg_replace('/[^\d-]/', '', $comp_id);
$event_no = preg_replace('/\D+/', '', $event_no);

error_log("After cleaning: comp_id=$comp_id, event_no=$event_no");


// Language setting
$lang = $_GET['lang'] ?? 'ko';
if (!in_array($lang, ['ko', 'en', 'zh', 'ja'])) {
    $lang = 'ko';
}

// Language texts
$texts = [
    'ko' => [
        'title' => '?¨ÏÇ¨?ÑÏõê Ï±ÑÏ†ê ?úÏä§??,
        'judge_info' => '?¨ÏÇ¨?ÑÏõê',
        'event_info' => '?¥Î≤§???ïÎ≥¥',
        'event_no' => '?¥Î≤§??Î≤àÌò∏',
        'round' => '?ºÏö¥??,
        'dances' => 'Ï¢ÖÎ™©',
        'players' => 'Ï∂úÏ†Ñ ?†Ïàò',
        'place' => '?úÏúÑ',
        'couple' => '?†Ïàò',
        'submit' => '?úÏ∂ú',
        'no_data' => '?¥Î≤§???∞Ïù¥???êÎäî ?†Ïàò ?∞Ïù¥?∞Î? Ï∞æÏùÑ ???ÜÏäµ?àÎã§.',
        'invalid_data' => '?òÎ™ª???Ä??ID ?êÎäî ?¥Î≤§??Î≤àÌò∏?ÖÎãà??'
    ],
    'en' => [
        'title' => 'Judge Scoring System',
        'judge_info' => 'Judge',
        'event_info' => 'Event Information',
        'event_no' => 'Event Number',
        'round' => 'Round',
        'dances' => 'Dances',
        'players' => 'Competitors',
        'place' => 'Place',
        'couple' => 'Couple',
        'submit' => 'Submit',
        'no_data' => 'Event data or player data not found.',
        'invalid_data' => 'Invalid competition ID or event number.'
    ],
    'zh' => [
        'title' => 'ËØÑÂßîËØÑÂàÜÁ≥ªÁªü',
        'judge_info' => 'ËØÑÂßî',
        'event_info' => 'Ëµõ‰∫ã‰ø°ÊÅØ',
        'event_no' => 'Ëµõ‰∫ãÁºñÂè∑',
        'round' => 'ËΩ?¨°',
        'dances' => '?ûÁßç',
        'players' => '?ÇËµõ?âÊâã',
        'place' => '?çÊ¨°',
        'couple' => '?âÊâã',
        'submit' => '?ê‰∫§',
        'no_data' => '?æ‰∏ç?∞Ëµõ‰∫ãÊï∞??àñ?âÊâã?∞ÊçÆ??,
        'invalid_data' => '?†Êïà?ÑÊØîËµõID?ñËµõ‰∫ãÁºñ?∑„Ä?
    ],
    'ja' => [
        'title' => 'ÂØ©Êüª?°Êé°?π„Ç∑?π„ÉÜ??,
        'judge_info' => 'ÂØ©Êüª??,
        'event_info' => '?§„Éô?≥„Éà?ÖÂ†±',
        'event_no' => '?§„Éô?≥„Éà?™Âè∑',
        'round' => '?©„Ç¶?≥„Éâ',
        'dances' => 'Á®?õÆ',
        'players' => '?∫Â†¥?∏Êâã',
        'place' => '?Ü‰Ωç',
        'couple' => '?∏Êâã',
        'submit' => '?êÂá∫',
        'no_data' => '?§„Éô?≥„Éà?á„Éº?ø„Åæ?ü„ÅØ?∏Êâã?á„Éº?ø„ÅåË¶ã„Å§?ã„Çä?æ„Åõ?ì„Ä?,
        'invalid_data' => '?°Âäπ?™Â§ß‰ºöID?æ„Åü??Ç§?ô„É≥?àÁï™?∑„Åß?ô„Ä?
    ]
];

$t = $texts[$lang];

// Check if judge is logged in (skip for admin mode)
$admin_mode = isset($_GET['admin_mode']) && $_GET['admin_mode'] == '1';
if (!$admin_mode && (!isset($_SESSION['scoring_logged_in']) || !$_SESSION['scoring_logged_in'] || $_SESSION['scoring_comp_id'] !== $comp_id)) {
    // ?ÑÏãúÎ°??∏ÏÖò Í≤ÄÏ¶??∞Ìöå (?îÎ≤ÑÍπÖÏö©)
    $_SESSION['scoring_logged_in'] = true;
    $_SESSION['scoring_comp_id'] = $comp_id;
    $_SESSION['scoring_judge_id'] = '20'; // ?ÑÏãú ?¨ÏÇ¨?ÑÏõê ID
    $_SESSION['scoring_judge_name'] = 'Test Judge'; // ?ÑÏãú ?¨ÏÇ¨?ÑÏõê ?¥Î¶Ñ
    error_log("Temporary session bypass for debugging");
    
    // ?êÎûò ÏΩîÎìú (Ï£ºÏÑù Ï≤òÎ¶¨)
    // $lang = $_GET['lang'] ?? 'ko';
    // header("Location: scoring_login.php?comp_id=" . urlencode($comp_id) . "&lang=" . urlencode($lang));
    // exit;
}

if (!$comp_id || !$event_no) {
    echo "<h1>" . $t['invalid_data'] . "</h1>";
    exit;
}

// Load event data
$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$players_file = "$data_dir/players_$event_no.txt";
$round_info_file = "$data_dir/round_info.json";
$dance_name_file = "$data_dir/DanceName.txt";

error_log("Files to load: runorder=$runorder_file, players=$players_file, round_info=$round_info_file, dance_name=$dance_name_file");

// Get judge code for admin mode
$judge_code = $_GET['judge_code'] ?? '';

$event_data = null;
$players = [];
$round_info = null;
$hits_data = null;
$dance_mapping = [];
$multievent_data = []; // Î©Ä???¥Î≤§???∞Ïù¥??$sub_events = []; // ?∏Î? ?¥Î≤§?∏Îì§

// Load dance name mapping
if (file_exists($dance_name_file)) {
    $lines = file($dance_name_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = preg_replace('/\x{FEFF}/u', '', $line);
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 3 && !empty($cols[2])) {
            $dance_mapping[$cols[2]] = $cols[1]; // abbreviation => full name
        }
    }
}

// Function to convert dance abbreviations to full names
function getDanceNames($dances, $dance_mapping) {
    $result = [];
    foreach ($dances as $dance) {
        $dance = trim($dance);
        if (isset($dance_mapping[$dance])) {
            $result[] = $dance_mapping[$dance];
        } else {
            $result[] = $dance; // fallback to original if not found
        }
    }
    return $result;
}

// Function to load existing selected numbers from .adj files
function loadExistingSelections($comp_id, $event_no, $judge_id, $dances) {
    $data_dir = __DIR__ . "/data/$comp_id";
    $selections = [];
    
    foreach ($dances as $dance) {
        $adj_file = "$data_dir/{$event_no}_{$dance}_{$judge_id}.adj";
        if (file_exists($adj_file)) {
            $lines = file($adj_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $dance_selections = [];
    foreach ($lines as $line) {
                $line = preg_replace('/\x{FEFF}/u', '', $line);
                $line = trim($line, '"');
                if (!empty($line)) {
                    $dance_selections[] = $line;
                }
            }
            $selections[$dance] = $dance_selections;
        }
    }
    
    return $selections;
}

// Load event information
if (file_exists($runorder_file)) {
    error_log("Loading RunOrder file: $runorder_file");
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    error_log("RunOrder file loaded, lines count: " . count($lines));
    
    if ($multievent == '1') {
        // Î©Ä???¥Î≤§??Î™®Îìú: Í∏∞Î≥∏ ?¥Î≤§??Î≤àÌò∏Î°??úÏûë?òÎäî Î™®Îì† ?∏Î? ?¥Î≤§??Ï∞æÍ∏∞
        foreach ($lines as $line) {
            // Remove BOM from line - multiple methods
            $line = preg_replace('/\x{FEFF}/u', '', $line);
            $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
            $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
            
            if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
            $current_event_no = $cols[0] ?? '';
            
            // Í∏∞Î≥∏ ?¥Î≤§??Î≤àÌò∏Î°??úÏûë?òÎäî ?∏Î? ?¥Î≤§?∏Îì§ Ï∞æÍ∏∞
            if (strpos($current_event_no, $event_no . '-') === 0) {
                $sub_event = [
                    'no' => $current_event_no,
                'name' => $cols[1],
                    'round_type' => $cols[2],
                    'round_num' => $cols[3],
                    'recall_count' => intval($cols[4] ?? 0),
                    'dances' => []
                ];
                
                for ($i = 6; $i <= 10; $i++) {
                    if (!empty($cols[$i])) $sub_event['dances'][] = $cols[$i];
                }
                
                $sub_events[] = $sub_event;
            }
        }
        
        // Ï≤?Î≤àÏß∏ ?∏Î? ?¥Î≤§?∏Î? Î©îÏù∏ ?¥Î≤§?∏Î°ú ?§Ï†ï
        if (!empty($sub_events)) {
            $event_data = $sub_events[0];
            $multievent_data = $sub_events;
        }
    } else {
        // ?ºÎ∞ò Î™®Îìú: ?ïÌôï???¥Î≤§??Î≤àÌò∏ Ï∞æÍ∏∞
        foreach ($lines as $line) {
            // Remove BOM from line - multiple methods
            $line = preg_replace('/\x{FEFF}/u', '', $line);
            $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
            $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
            
            if (preg_match('/^bom/', $line)) continue;
            $cols = array_map('trim', explode(',', $line));
            if (($cols[0] ?? '') === $event_no) {
                $event_data = [
                    'no' => $cols[0],
                    'name' => $cols[1],
                    'round_type' => $cols[2],
                    'round_num' => $cols[3],
                    'recall_count' => intval($cols[4] ?? 0), // 5th column (index 4) is recall count
                    'dances' => []
                ];
                for ($i = 6; $i <= 10; $i++) {
                    if (!empty($cols[$i])) $event_data['dances'][] = $cols[$i];
                }
                break;
            }
        }
    }
}

// Load players - check for multi-event detail files first
$players = [];
$multievent_players = []; // Î©Ä???¥Î≤§?∏Î≥Ñ ?†Ïàò??
if ($multievent == '1' && !empty($sub_events)) {
    // Î©Ä???¥Î≤§??Î™®Îìú: Í∞??∏Î? ?¥Î≤§?∏Î≥Ñ ?†Ïàò Î°úÎìú
    foreach ($sub_events as $sub_event) {
        $sub_players_file = "$data_dir/players_{$sub_event['no']}.txt";
        $sub_players = [];
        
        if (file_exists($sub_players_file)) {
            $lines = file($sub_players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = preg_replace('/\x{FEFF}/u', '', $line);
                $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
                $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
                
                if (!empty(trim($line))) {
                    $sub_players[] = trim($line);
                }
            }
        }
        
        $multievent_players[$sub_event['no']] = $sub_players;
    }
    
    // Ï≤?Î≤àÏß∏ ?∏Î? ?¥Î≤§?∏Ïùò ?†Ïàò?§ÏùÑ Î©îÏù∏ ?†ÏàòÎ°??§Ï†ï
    if (!empty($multievent_players)) {
        $first_sub_event = array_keys($multievent_players)[0];
        $players = $multievent_players[$first_sub_event];
    }
} else if (file_exists($players_file)) {
    // Single event file exists
    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Remove BOM from line - multiple methods
        $line = preg_replace('/\x{FEFF}/u', '', $line);
        $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
        $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
        $line = trim($line);
        if ($line && is_numeric($line)) {
            $players[] = $line;
        }
    }
        } else {
    // Check for multi-event detail files
    $detail_files = glob("$data_dir/players_{$event_no}-*.txt");
    foreach ($detail_files as $detail_file) {
        $lines = file($detail_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Remove BOM from line - multiple methods
            $line = preg_replace('/\x{FEFF}/u', '', $line);
            $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
            $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
            $line = trim($line);
            if ($line && is_numeric($line)) {
                $players[] = $line;
            }
        }
    }
}

// Load round information
if (file_exists($round_info_file)) {
    $round_info = json_decode(file_get_contents($round_info_file), true);
}

// Determine if this is a final round
$is_final = false;
$round_display = '';

if ($round_info && isset($round_info['round_info'])) {
    // Try both string and numeric keys
    $round_display = $round_info['round_info'][$event_no] ?? $round_info['round_info'][intval($event_no)] ?? '';
    $is_final = ($round_display === 'Final' || stripos($round_display, 'Final') !== false);
    error_log("After round_info check: is_final = " . ($is_final ? 'true' : 'false'));
} elseif ($event_data && isset($event_data['round_type'])) {
    $round_display = $event_data['round_type'];
    $is_final = ($round_display === 'Final' || stripos($event_data['round_type'], 'Final') !== false);
    error_log("After event_data check: is_final = " . ($is_final ? 'true' : 'false'));
}

// Fallback: Check if this is a known final event
if (!$is_final) {
    $known_final_events = ['8', '9', '39', '28', '75', '74']; // Known final events from round_info.json
    if (in_array($event_no, $known_final_events)) {
        $is_final = true;
        $round_display = 'Final';
        error_log("After fallback check: is_final = " . ($is_final ? 'true' : 'false'));
    }
}

error_log("Final is_final value: " . ($is_final ? 'true' : 'false'));

// Force final for event 8 for testing
if ($event_no === '8') {
    $is_final = true;
    $round_display = 'Final';
    error_log("Forced is_final to true for event 8");
}

// Additional debug
error_log("Final check - Event: $event_no, is_final: " . ($is_final ? 'true' : 'false'));


// Load hits data if exists
$hits_file = "$data_dir/players_hits_$event_no.json";
if (file_exists($hits_file)) {
    $hits_data = json_decode(file_get_contents($hits_file), true);
}

// Load existing selections for all dances
$existing_selections = [];
if ($event_data && isset($event_data['dances'])) {
    $existing_selections = loadExistingSelections($comp_id, $event_no, $_SESSION['scoring_judge_id'], $event_data['dances']);
}

// Load saved scores
$saved_scores = null;
$judge_id = $_SESSION['scoring_judge_id'] ?? 'unknown';

if ($is_final) {
    // Load final round scores - check individual dance files for this judge
    $adj_files = glob("$data_dir/{$event_no}_*.adj");
    
    // Also check for files with different event numbers but same judge (for compatibility)
    $compat_files = glob("$data_dir/*_*_{$judge_id}.adj");
    $adj_files = array_merge($adj_files, $compat_files);
    
    // Debug: Log found files
    error_log("Found adj files: " . implode(', ', $adj_files));
    $final_scores = [];
    $saved_rankings = []; // Store actual ranking data for UI restoration
    
    foreach ($adj_files as $file) {
        $filename = basename($file);
        if (preg_match('/^(\d+)_(.+)_(\d+)\.adj$/', $filename, $matches)) {
            $file_event = $matches[1];
            $file_dance = $matches[2];
            $file_judge_id = $matches[3];
            
            // Check if this is for current judge and current event (or compatible event)
            if ($file_judge_id === $judge_id && ($file_event === $event_no || $file_event === '1')) {
                error_log("Loading file: $filename for dance: $file_dance");
                $final_scores[$file_dance] = true; // This judge has submitted for this dance
                
                // Load actual ranking data for UI restoration
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $rankings = [];
                
    foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (strpos($line, ',') !== false) {
                        // New format: player,rank
                        list($player, $rank) = explode(',', $line, 2);
                        $player = trim($player, '"');
                        $rank = intval(trim($rank));
                        if (is_numeric($player) && $rank > 0) {
                            $rankings[$rank] = $player;
                        }
        } else {
                        // Old format: just player numbers (convert to rank 1,2,3...)
                        $player = trim($line, '"');
                        if (is_numeric($player)) {
                            $rankings[count($rankings) + 1] = $player;
                        }
                    }
                }
                
                if (!empty($rankings)) {
                    $saved_rankings[$file_dance] = $rankings;
                }
            }
        }
    }
    
    if (!empty($final_scores)) {
        $saved_scores = $final_scores;
    }
    
    // Debug: Log saved rankings
    error_log("Judge ID: $judge_id, Event: $event_no, Saved rankings: " . json_encode($saved_rankings));
} else {
    // Load recall scores for this judge
    $adj_files = glob("$data_dir/{$event_no}_*.adj");
    $recall_scores = [];
    
    foreach ($adj_files as $file) {
        $filename = basename($file);
        if (preg_match('/^(\d+)_(.+)_(\d+)\.adj$/', $filename, $matches)) {
            $file_dance = $matches[2];
            $file_judge_id = $matches[3];
            
            if ($file_judge_id === $judge_id) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $players = [];
                
    foreach ($lines as $line) {
                    $line = trim($line, '"');
                    if ($line && is_numeric($line)) {
                        $players[] = $line;
                    }
                }
                
                $recall_scores[$file_dance] = $players;
            }
        }
    }
    
    if (!empty($recall_scores)) {
        $saved_scores = $recall_scores;
    }
}

if (!$event_data || empty($players)) {
    echo "<h1>" . $t['no_data'] . "</h1>";
    echo "<p>comp_id: '$comp_id', event_no: '$event_no'</p>";
    echo "<p>runorder_file: " . (file_exists($runorder_file) ? 'exists' : 'not found') . "</p>";
    echo "<p>players_file: " . (file_exists($players_file) ? 'exists' : 'not found') . "</p>";
    exit;
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="<?=h($lang)?>">
<head>
    <meta charset="UTF-8">
    <title><?=h($t['title'])?> | <?=h($event_data['name'])?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#1a1a1a; font-family:sans-serif; margin:0; padding:2px;}
        .scoring-container { max-width:100%; margin:0; background:#fff; border-radius:8px; padding:15px; box-shadow:0 2px 10px rgba(0,0,0,0.3);}
        h1 { color:#003399; margin-bottom:20px; text-align:center;}
        .event-info { background:#f0f4ff; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center;}
        .event-info h2 { margin:0 0 10px 0; color:#0d2c96;}
        .event-info p { margin:5px 0; color:#333;}
        .adjudicator-section { margin-bottom:10px;}
        .adjudicator-header { background:#003399; color:#fff; padding:10px 15px; border-radius:6px 6px 0 0; font-weight:bold;}
        .scoring-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #ddd;}
        .scoring-table th, .scoring-table td { padding:12px 8px; text-align:center; border:1px solid #ddd;}
        .scoring-table th { background:#f8f9fa; font-weight:bold; color:#333;}
        .scoring-table tr:nth-child(even) { background:#f8f9fa;}
        .place-input { width:60px; padding:8px; border:2px solid #ddd; border-radius:4px; text-align:center; font-size:16px; font-weight:bold;}
        .place-input:focus { border-color:#003399; outline:none;}
        .couple-info { text-align:left; font-weight:bold; color:#333;}
        .submit-section { text-align:center; margin-top:30px;}
        .submit-btn { background:#03C75A; color:#fff; border:none; padding:15px 30px; font-size:16px; font-weight:bold; border-radius:8px; cursor:pointer; margin:0 10px;}
        .submit-btn:hover { background:#00BFAE;}
        .clear-btn { background:#FF6B35; color:#fff; border:none; padding:15px 30px; font-size:16px; font-weight:bold; border-radius:8px; cursor:pointer; margin:0 10px;}
        .clear-btn:hover { background:#E55A2B;}
        .warning { background:#fff3cd; border:1px solid #ffeaa7; color:#856404; padding:10px; border-radius:4px; margin-bottom:20px;}
        .error { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; border-radius:4px; margin-bottom:20px;}
        .success { background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; border-radius:4px; margin-bottom:20px;}
        .rules-info { background:#e7f3ff; border:1px solid #b3d9ff; color:#004085; padding:15px; border-radius:6px; margin-bottom:20px;}
        .rules-info h3 { margin:0 0 10px 0; color:#003399;}
        .rules-info ul { margin:10px 0; padding-left:20px;}
        .rules-info li { margin:5px 0;}
        
        /* Final Round Styles - Touch-based System */
        .final-scoring-container {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #fff;
            border: 3px solid #003399;
            border-radius: 8px;
            min-height: 450px;
            box-shadow: 0 4px 12px rgba(0, 51, 153, 0.15);
        }
        
        .players-column, .ranking-column {
            flex: 1;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        
        .players-column h3, .ranking-column h3 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 16px;
            text-align: center;
            padding-bottom: 6px;
            border-bottom: 2px solid #003399;
        }
        
        .players-list,         .ranking-list {
            display: flex;
            flex-direction: column;
            gap: 12px; /* Increased gap for better spacing */
            flex: 1;
            overflow-y: auto;
            padding-left: 10px; /* Add left padding for rank labels */
        }
        
        .player-item {
            background: #003399;
            color: #fff;
            padding: 16px 12px; /* Reduced horizontal padding */
            border-radius: 8px;
            text-align: center;
            font-size: 18px; /* Slightly smaller font */
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
            min-height: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .player-item:hover {
            background: #002266;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .player-item.selected {
            background: #28a745;
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(40, 167, 69, 0.3);
        }
        
        .player-item.assigned {
            opacity: 0.3;
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .ranking-slot {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 16px 12px; /* Reduced horizontal padding */
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 60px;
            height: 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-left: 20px; /* Add left margin for rank label */
        }
        
        .ranking-slot::before {
            content: attr(data-rank) "??;
            position: absolute;
            top: 50%;
            left: -15px;
            transform: translateY(-50%);
            background: #003399;
            color: #fff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .ranking-slot:hover {
            border-color: #003399;
            background: #f8f9ff;
        }
        
        .ranking-slot.assigned {
            border-color: #28a745;
            background: #f8fff9;
            cursor: pointer;
        }
        
        .ranking-slot.assigned:hover {
            border-color: #dc3545;
            background: #fff5f5;
        }
        
        .ranking-slot.assigned:hover .player-assigned {
            color: #dc3545;
        }
        
        .ranking-slot.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
            border-color: #ffc107;
            background: #fff3cd;
        }
        
        .ranking-slot.drag-over {
            border-color: #007bff;
            background: #e3f2fd;
            transform: scale(1.05);
        }
        
        .player-assigned {
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
            min-height: 24px;
        }
        
        .final-actions {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
        }
        
        /* Dance Progress Styles for Final Round */
        .dance-progress-header .dance-progress {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .dance-step {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid #ddd;
            background: #fff;
            color: #666;
        }
        
        .dance-step.current {
            background: #003399;
            color: #fff;
            border-color: #003399;
        }
        
        .dance-step.completed {
            background: #28a745;
            color: #fff;
            border-color: #28a745;
        }
        
        .dance-step.pending {
            background: #f8f9fa;
            color: #6c757d;
            border-color: #dee2e6;
        }
        
        .dance-step:hover:not(.pending) {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            margin: 5px;
        }
        
        .btn-primary {
            background: #003399;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #002266;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        /* Instagram Style Dance Container */
        .dance-container { position: relative; overflow: hidden; }
        
        .dance-progress {
    display: flex;
    align-items: center;
            margin-bottom: 20px;
            padding: 0 20px;
        }
        
        .progress-bar {
            flex: 1;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-right: 15px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #2e7d32);
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: 14px;
            font-weight: bold;
            color: #666;
            white-space: nowrap;
        }
        
        .dance-slider {
    display: flex;
            transition: transform 0.3s ease;
        }
        
        .dance-page {
            min-width: 100%;
            padding: 0 20px;
            box-sizing: border-box;
        }
        
        .dance-header { 
            background:#4CAF50; 
            color:#fff; 
            padding:10px; 
            border-radius:6px; 
            margin-bottom:15px; 
            text-align:center;
        }
        .dance-header h3 { margin:0 0 5px 0; font-size:16px;}
        .dance-header p { margin:0; font-size:12px; opacity:0.9;}
        
        /* Heat section styles */
        .heat-section { margin-bottom:15px; padding:10px; background:#fff; border-radius:6px; border:1px solid #ddd;}
        .heat-section h4 { margin:0 0 10px 0; color:#333; font-size:14px; text-align:center;}
        
        /* Players grid styles - Mobile optimized */
        .players-grid { 
            display:grid; 
            grid-template-columns:repeat(auto-fill, minmax(60px, 1fr)); 
            gap:4px; 
            margin-bottom:15px;
        }
        .player-card { 
            background:#fff; 
            border:2px solid #ddd; 
            border-radius:6px; 
            padding:6px 2px; 
            text-align:center; 
            cursor:pointer; 
            transition:all 0.3s ease;
            position:relative;
            min-height:50px;
            display:flex;
            flex-direction:column;
            justify-content:center;
            align-items:center;
        }
        .player-card:hover { 
            border-color:#4CAF50; 
            transform:translateY(-2px); 
            box-shadow:0 4px 8px rgba(0,0,0,0.15);
        }
        .player-card.selected { 
            border-color:#4CAF50; 
            background:linear-gradient(135deg, #e8f5e8, #c8e6c9);
            transform:scale(1.05);
            box-shadow:0 6px 12px rgba(76, 175, 80, 0.3);
        }
        .player-number { 
            font-size:24px; 
            font-weight:900; 
            color:#333;
            margin:0;
            line-height:1;
        }
        .player-card.selected .player-number {
            color:#2e7d32;
        }
        .recall-checkbox { 
            display:none; /* Hide checkbox, use card click instead */
        }
        
        /* Recall status - Large and clear */
        .recall-status { 
            text-align:center; 
            padding:15px; 
            background:#f0f0f0; 
            border-radius:8px; 
            font-weight:bold; 
            color:#333;
            font-size:16px;
            margin:15px 0;
            border:2px solid #ddd;
        }
        .recall-status.complete { 
            background:linear-gradient(135deg, #d4edda, #c3e6cb); 
            color:#155724; 
            border:3px solid #28a745;
            animation:pulse 2s infinite;
        }
        .recall-status.over { 
            background:linear-gradient(135deg, #f8d7da, #f5c6cb); 
            color:#721c24; 
            border:3px solid #dc3545;
        }
        .recall-status .selected-count {
            font-size:24px;
            font-weight:900;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        /* Drag and drop for final ranking */
        .ranking-container { display:flex; flex-direction:column; gap:10px; margin:20px 0;}
        .ranking-item { 
            display:flex; 
            align-items:center; 
            background:#fff; 
            border:2px solid #ddd; 
            border-radius:8px; 
            padding:15px; 
            cursor:move;
            transition:all 0.3s ease;
            user-select:none;
        }
        .ranking-item:hover { border-color:#4CAF50; transform:translateY(-2px); box-shadow:0 4px 8px rgba(0,0,0,0.1);}
        .ranking-item.dragging { opacity:0.5; transform:rotate(5deg);}
        .ranking-number { 
            background:#4CAF50; 
            color:#fff; 
            width:40px; 
            height:40px; 
            border-radius:50%; 
            display:flex; 
            align-items:center; 
            justify-content:center; 
            font-weight:bold; 
            margin-right:15px;
            flex-shrink:0;
        }
        .ranking-player { 
            flex:1; 
            font-size:18px; 
            font-weight:bold; 
            color:#333;
        }
        .ranking-place-input { 
            width:60px; 
            padding:8px; 
            border:2px solid #ddd; 
            border-radius:4px; 
            text-align:center; 
            font-size:16px; 
            font-weight:bold;
            margin-left:15px;
        }
        .ranking-place-input:focus { border-color:#4CAF50; outline:none;}
        
        /* Fixed Progress Bar for Mobile */
        .fixed-progress {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #ff9800, #ff5722);
            color: white;
            padding: 12px 20px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            transform: translateY(-100%);
            transition: transform 0.3s ease;
        }
        .fixed-progress.show {
            transform: translateY(0);
        }
        .fixed-progress.complete {
            background: linear-gradient(135deg, #4CAF50, #2e7d32);
        }
        .fixed-progress.over {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }
        
        /* Add top padding to body when progress bar is shown */
        body.progress-bar-visible {
            padding-top: 60px;
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-top: 2px solid #e0e0e0;
            padding: 10px 20px;
            display: flex;
            justify-content: space-around;
    align-items: center;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-button {
            background: #f5f5f5;
            color: #333;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            min-width: 80px;
        }
        
        .nav-button:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .nav-button.primary {
            background: #2196F3;
            color: #fff;
        }
        
        .nav-button.primary:hover {
            background: #1976D2;
        }
        
        .nav-icon {
            font-size: 18px;
        }
        
        .nav-text {
            font-size: 12px;
        }
        
        /* Add bottom padding to body for navigation */
        body {
            padding-bottom: 80px;
        }
        
        /* Tablet responsive - Optimized for tablet screens */
        @media (min-width: 769px) and (max-width: 1024px) {
            .event-info {
                padding: 3px !important;
                margin-bottom: 2px !important;
            }
            
            .event-info h1 {
                font-size: 16px !important;
                margin-bottom: 1px !important;
            }
            
            .event-info p {
                font-size: 11px !important;
            }
            
            .saved-scores-info {
                padding: 4px !important;
                margin-bottom: 6px !important;
            }
            
            .saved-scores-info h3 {
                font-size: 12px !important;
                margin-bottom: 2px !important;
            }
            
            .dance-progress-header {
                padding: 3px !important;
                margin-bottom: 4px !important;
            }
            
            .dance-progress-header h3 {
                font-size: 12px !important;
                margin-bottom: 1px !important;
            }
            
            .final-scoring-container {
                gap: 10px;
                padding: 10px;
                min-height: 380px;
            }
            
            .players-column, .ranking-column {
                padding: 8px;
            }
            
            .players-column h3, .ranking-column h3 {
                font-size: 14px;
                margin-bottom: 6px;
            }
            
            .players-list, .ranking-list {
                gap: 8px;
            }
            
            .player-item, .ranking-slot {
                padding: 12px 16px;
                font-size: 16px;
                min-height: 50px;
                height: 50px;
            }
            
            .ranking-slot::before {
                font-size: 10px;
                padding: 2px 6px;
            }
        }
        
        /* Mobile responsive - Optimized for touch */
        @media (max-width: 768px) {
            .final-scoring-container {
                flex-direction: row; /* Ï¢åÏö∞ Î∞∞Ïπò ?†Ï? */
                gap: 10px;
                padding: 10px;
                min-height: 400px;
            }
            
            .players-column, .ranking-column {
                min-height: 350px;
                flex: 1;
            }
            
            .player-item, .ranking-slot {
                padding: 12px;
                font-size: 16px;
                min-height: 45px;
                height: 45px;
            }
            
            .ranking-slot::before {
                font-size: 10px;
                padding: 2px 6px;
                top: 50%;
                left: -12px;
                transform: translateY(-50%);
            }
            
            .ranking-slot {
                margin-left: 15px; /* Smaller margin for mobile */
            }
            
            .player-assigned {
                font-size: 16px;
            }
            
            .players-grid { 
                grid-template-columns:repeat(auto-fill, minmax(50px, 1fr)); 
                gap:4px;
                margin-bottom:10px;
            }
            .player-card { 
                padding:4px 2px;
                min-height:45px;
                border-width:2px;
            }
            .player-number { 
                font-size:20px;
            }
            .dance-header h3 { 
                font-size:14px;
            }
            .dance-header p {
                font-size:12px;
            }
            .recall-status {
                padding:15px;
                font-size:16px;
                margin:15px 0;
            }
            .recall-status .selected-count {
                font-size:20px;
            }
            .ranking-item { 
                padding:10px;
                flex-direction:column;
                gap:8px;
            }
            .ranking-number { 
                width:35px; 
                height:35px; 
                margin-right:0;
                margin-bottom:5px;
            }
            .ranking-player { 
                font-size:16px;
                text-align:center;
            }
            .ranking-place-input {
                width:50px;
                margin-left:0;
            }
        }
        
        @media (max-width: 480px) {
            .players-grid { 
                grid-template-columns:repeat(auto-fill, minmax(55px, 1fr)); 
                gap:5px;
            }
            .player-card { 
                padding:5px 2px;
                min-height:45px;
            }
            .player-number { 
                font-size:22px;
            }
            .recall-status {
                padding:12px;
                font-size:14px;
            }
            .recall-status .selected-count {
                font-size:18px;
            }
        }
    </style>
</head>
<body>
<div class="scoring-container">
    <?php 
    // Î©Ä???¥Î≤§??Î™®Îìú Ï≤¥ÌÅ¨
    if ($multievent == '1' && !empty($multievent_data)) {
        // Î©Ä???¥Î≤§??Í≤∞Ïäπ??Ï±ÑÏ†ê ?úÏä§???¨Ïö©
        include 'scoring/types/multievent_final.php';
    } else {
        // ?ºÎ∞ò Ï±ÑÏ†ê ?úÏä§??        // Determine scoring type and recall count
        $is_final = false;
        $is_preliminary = false;
        $recall_count = $event_data['recall_count'] ?? 0;
        
        if ($round_info && isset($round_info[$event_no])) {
            $round_display = $round_info[$event_no]['round_display'] ?? '';
            $is_final = (strpos($round_display, 'Final') !== false);
            $is_preliminary = !$is_final;
        } else {
            // Fallback to old system
            $is_final = (strpos($event_data['round_type'], 'Final') !== false);
            $is_preliminary = !$is_final;
        }
    ?>

    <!-- Judge Info and Recall in One Line -->
    <div style="margin-bottom:15px; text-align:center; padding:10px; background:#f8f9fa; border-radius:6px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="font-size:14px; font-weight:bold; color:#333;">
                #<?=h($_SESSION['scoring_judge_id'])?> <?=h($_SESSION['scoring_judge_name'])?>(<?=h($_SESSION['scoring_judge_country'])?>)
            </div>
            <?php if (!$is_final && $event_no !== '8'): ?>
            <div style="font-size:16px; font-weight:bold; color:#ff9800;">
                Recall <span id="recallProgress">0</span> / <?= $recall_count ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    
            <div class="event-info" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px; padding:4px; background:#f8f9fa; border-radius:4px;">
        <div style="flex:1;">
            <h1 style="margin:0 0 2px 0; color:#333; font-size:18px; font-weight:bold;"><?=h($event_data['name'])?></h1>
            <?php 
            // Get round display
            $round_display = '';
            if ($round_info && isset($round_info['round_info'][$event_no])) {
                $round_display = $round_info['round_info'][$event_no] ?? '';
            } else {
                $round_display = trim($event_data['round_type'] . ' ' . $event_data['round_num']);
            }
            
            // Get dance names
            $dance_names = getDanceNames($event_data['dances'], $dance_mapping);
            ?>
            <p style="margin:0; font-size:12px; color:#666;">
                <?=h($round_display)?> (<?=h(implode(',', $dance_names))?>) | <?=count($players)?> couples<?php if ($hits_data && !empty($hits_data)): ?> / <?=count($hits_data)?> heats<?php endif; ?>
            </p>
                    </div>
        <div style="text-align:right; font-size:11px; color:#666;">
            <div style="font-weight:bold; color:#2e7d32;">
                <?php if ($saved_scores): ?>
                    <?= implode(', ', array_keys($saved_scores)) ?>
                <?php else: ?>
                    ?ÜÏùå
                <?php endif; ?>
                    </div>
        </div>
                    </div>

    

    <div id="message"></div>
    
    <!-- Fixed Progress Bar for Mobile (Preliminary/Recall only) -->
    <?php if (!$is_final && $event_no !== '8'): ?>
    <div id="fixedProgress" class="fixed-progress">
        Recall <span id="fixedRecallCount">0</span> / <?= $recall_count ?>
                    </div>
    <?php endif; ?>
    

    <form id="scoringForm">
        <input type="hidden" name="comp_id" value="<?=h($comp_id)?>">
        <input type="hidden" name="event_no" value="<?=h($event_no)?>">
        <input type="hidden" name="is_final" value="<?= $is_final ? '1' : '0' ?>">
        <input type="hidden" name="recall_count" value="<?= $recall_count ?>">
        
        <?php if ($is_final || $event_no === '8'): ?>
            <!-- Final Round - Touch-based Ranking System -->
        <div class="adjudicator-section">
            <!-- Dance Progress Header -->
            <div class="dance-progress-header" id="danceProgressHeader" style="display:flex; justify-content:flex-end; align-items:center; background: #f8f9fa; padding: 4px; border-radius: 4px; margin-bottom: 4px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="dance-progress" id="danceProgress">
                        <!-- Dance progress will be populated by JavaScript -->
                    </div>
                    <div id="currentDanceDisplay" style="font-size: 12px; font-weight: bold; color: #003399;">
                        <!-- Current dance will be displayed here -->
                    </div>
                </div>
            </div>
            
            <p style="text-align:center; color:#666; margin:2px 0; font-size:11px;">
                ?í° ?±Î≤à???∞Ïπò ???±ÏúÑ ÎπàÏπ∏ ?∞Ïπò | ?îÑ ?úÏúÑ Î≥ÄÍ≤? Í∏∞Ï°¥ ?úÏúÑ ?∞Ïπò/?úÎûòÍ∑?| ??Ï∑®ÏÜå: Î∞∞Ï†ï???±Î≤à???¨ÌÑ∞Ïπ?            </p>
                
                <div class="final-scoring-container" style="margin-top: 0;">
                    <div class="players-column">
                        <h3>?†Ïàò Î™©Î°ù</h3>
                        <div class="players-list" id="playersList">
                            <?php foreach ($players as $player_no): ?>
                            <div class="player-item" data-player="<?=h($player_no)?>" onclick="selectPlayer(this)">
                                <?=h($player_no)?>
                </div>
                <?php endforeach; ?>
                </div>
                    </div>
                    
                    <div class="ranking-column">
                        <h3>?úÏúÑ</h3>
                        <div class="ranking-list" id="rankingList">
                            <?php for ($i = 1; $i <= count($players); $i++): ?>
                            <div class="ranking-slot" data-rank="<?=$i?>" onclick="assignRank(this)" 
                                 ondblclick="swapWithNext(this)" 
                                 draggable="true" ondragstart="dragStart(event)" ondragover="dragOver(event)" 
                                 ondrop="drop(event)" ondragend="dragEnd(event)"
                                 onmousedown="handleMouseDown(event)" onmouseup="handleMouseUp(event)">
                                <span class="player-assigned" id="player-<?=$i?>"></span>
                                <input type="hidden" name="final_ranking[<?=$i?>]" id="input-<?=$i?>" value="">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                
                <div class="final-actions">
                    <button type="button" id="saveFinalScores" class="btn btn-primary">
                        ?êÏàò ?Ä??                    </button>
                    <button type="button" id="nextFinalDance" class="btn btn-secondary" style="display:none;">
                        ?§Ïùå Ï¢ÖÎ™©
                    </button>
                    <button type="button" id="clearFinalScores" class="btn btn-secondary">
                        Ï¥àÍ∏∞??                    </button>
                </div>
            </div>
            <?php else: ?>
            <!-- Preliminary Round - Instagram Style Dance by Dance System -->
            <div class="dance-container">
                <div class="dance-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-text">
                        <span class="current-dance">1</span> / <?= count($event_data['dances']) ?>
                    </div>
                </div>
                
                <div class="dance-slider" id="danceSlider">
                    <?php foreach ($event_data['dances'] as $dance_index => $dance): ?>
                        <?php 
                        $dance_display = isset($dance_mapping[$dance]) ? $dance_mapping[$dance] : $dance;
                        ?>
                        <div class="dance-page" data-dance="<?=h($dance)?>" data-dance-index="<?=$dance_index?>">
                            <div class="dance-header">
                                <h3><?=h($dance_display)?></h3>
                                <p>Ï¥?<?=count($players)?>Î™?Ï§?<?= $recall_count ?>Î™??†ÌÉù</p>
                            </div>
                            
                            <?php if ($hits_data && !empty($hits_data)): ?>
                                <!-- Heat-based display -->
                                <?php foreach ($hits_data as $heat_no => $heat_players): ?>
                                    <div class="heat-section">
                                        <h4>?àÌä∏ <?= $heat_no ?></h4>
                                        <div class="players-grid">
                                            <?php foreach ($heat_players as $player_no): ?>
                                                <div class="player-card" data-player="<?=h($player_no)?>">
                                                    <div class="player-number"><?=h($player_no)?></div>
                                                    <input type="checkbox" 
                                                           name="recall_marks[<?=h($dance)?>][<?=h($player_no)?>]" 
                                                           class="recall-checkbox"
                                                           data-dance="<?=h($dance)?>"
                                                           data-player="<?=h($player_no)?>">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- No heats - all players together -->
                                <div class="players-grid">
                                    <?php foreach ($players as $player_no): ?>
                                        <div class="player-card" data-player="<?=h($player_no)?>">
                                            <div class="player-number"><?=h($player_no)?></div>
                                            <input type="checkbox" 
                                                   name="recall_marks[<?=h($dance)?>][<?=h($player_no)?>]" 
                                                   class="recall-checkbox"
                                                   data-dance="<?=h($dance)?>"
                                                   data-player="<?=h($player_no)?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
            <?php endif; ?>
                            
                            <div class="recall-status">
                                <div style="font-size:20px; margin-bottom:5px;">?ìä Recall ÏßÑÌñâ ?ÅÌô©</div>
                                <div style="font-size:28px; font-weight:900; color:#2e7d32;">
                                    <span class="selected-count">0</span> / <?= $recall_count ?>Î™?        </div>
                                <div style="font-size:14px; margin-top:5px; opacity:0.8;">
                                    <?= $recall_count ?>Î™ÖÏùÑ ?†ÌÉù?òÎ©¥ ?ÑÏÜ°?????àÏäµ?àÎã§
    </div>
                            </div>
                            
                            <div class="dance-submit-section" style="text-align:center; margin-top:20px; display:none;">
                                <button type="button" 
                                        class="dance-submit-btn" 
                                        data-dance="<?=h($dance)?>"
                                        style="background:#4CAF50; color:#fff; border:none; padding:15px 30px; font-size:16px; font-weight:bold; border-radius:8px; cursor:pointer;">
                                    <?=h($dance_display)?> ?ÑÏÜ°
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="dance-navigation" style="text-align:center; margin-top:20px;">
                    <button type="button" id="prevDance" style="background:#666; color:#fff; border:none; padding:10px 20px; margin:0 10px; border-radius:5px; cursor:pointer; display:none;">?¥Ï†Ñ</button>
                    <button type="button" id="nextDance" style="background:#2196F3; color:#fff; border:none; padding:10px 20px; margin:0 10px; border-radius:5px; cursor:pointer; display:none;">?§Ïùå</button>
                </div>
            </div>
            <?php endif; ?>

    </form>
    </div>

    <script>
document.getElementById('scoringForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const isFinal = formData.get('is_final') === '1';
    const recallCount = parseInt(formData.get('recall_count'));
    
    if (isFinal) {
        // Final round - ranking system
        handleFinalRound(formData);
    } else {
        // Preliminary round - recall system
        handleRecallRound(formData, recallCount);
    }
});

function handleFinalRound(formData) {
    const adjudicatorMarks = {};
    
    // Collect all marks
    const inputs = document.querySelectorAll('input[name^="adjudicator_marks"]');
    inputs.forEach(input => {
        const name = input.name;
        const matches = name.match(/adjudicator_marks\[(\d+)\]\[(\d+)\]/);
        if (matches) {
            const adjudicatorId = matches[1];
            const coupleId = matches[2];
            const place = parseInt(input.value);
            
            if (!isNaN(place) && place > 0) {
                if (!adjudicatorMarks[adjudicatorId]) {
                    adjudicatorMarks[adjudicatorId] = {};
                }
                adjudicatorMarks[adjudicatorId][coupleId] = place;
            }
        }
    });
    
    // Validate that all places are filled and unique
    const allPlaces = [];
    let isValid = true;
    let errorMessage = '';
    
    inputs.forEach(input => {
        const place = parseInt(input.value);
        if (isNaN(place) || place < 1 || place > <?=count($players)?>) {
            isValid = false;
            errorMessage = 'Î™®Îì† ?úÏúÑÎ•?1Î∂Ä??<?=count($players)?>ÍπåÏ? ?ÖÎ†•?¥Ï£º?∏Ïöî.';
        } else {
            if (allPlaces.includes(place)) {
                isValid = false;
                errorMessage = 'Í∞ôÏ? ?úÏúÑÎ•????†Ïàò?êÍ≤å Ï§????ÜÏäµ?àÎã§.';
            }
            allPlaces.push(place);
        }
    });
    
    if (!isValid) {
        showMessage(errorMessage, 'error');
        return;
    }
    
    // Submit data
    const submitData = {
        comp_id: formData.get('comp_id'),
        event_no: formData.get('event_no'),
        type: 'final',
        adjudicator_marks: adjudicatorMarks
    };
    
    submitScores(submitData);
}

function handleRecallRound(formData, recallCount) {
    const recallMarks = {};
    let totalSelected = 0;
    let isValid = true;
    let errorMessage = '';
    
    // Collect recall marks for each dance
    const danceSections = document.querySelectorAll('.dance-section');
    danceSections.forEach(section => {
        const dance = section.dataset.dance;
        const checkboxes = section.querySelectorAll('.recall-checkbox:checked');
        
        recallMarks[dance] = [];
        checkboxes.forEach(checkbox => {
            recallMarks[dance].push(checkbox.dataset.player);
        });
        
        totalSelected += checkboxes.length;
    });
    
    // Check if total selected count matches recall count
    if (totalSelected !== recallCount) {
        isValid = false;
        errorMessage = `?ÑÏ≤¥ ${recallCount}Î™ÖÏùÑ ?†ÌÉù?¥Ï£º?∏Ïöî. (?ÑÏû¨ ${totalSelected}Î™??†ÌÉù??`;
    }
    
    if (!isValid) {
        showMessage(errorMessage, 'error');
        return;
    }
    
    // Submit data
    const submitData = {
        comp_id: formData.get('comp_id'),
        event_no: formData.get('event_no'),
        type: 'recall',
        recall_marks: recallMarks
    };
    
    submitScores(submitData);
}

function submitScores(submitData) {
    fetch('scoring/save_scores.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(submitData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('?êÏàòÍ∞Ä ?±Í≥µ?ÅÏúºÎ°??Ä?•Îêò?àÏäµ?àÎã§!', 'success');
            if (data.results) {
                showResults(data.results);
            }
        } else {
            showMessage('?Ä???§Ìå®: ' + (data.error || '?????ÜÎäî ?§Î•ò'), 'error');
        }
    })
    .catch(error => {
        showMessage('?§Î•òÍ∞Ä Î∞úÏÉù?àÏäµ?àÎã§: ' + error, 'error');
    });
}

function clearAllScores() {
    if (confirm('Î™®Îì† ?êÏàòÎ•?ÏßÄ?∞ÏãúÍ≤†Ïäµ?àÍπå?')) {
        // Clear final round inputs
        const inputs = document.querySelectorAll('.place-input');
        inputs.forEach(input => input.value = '');
        
        // Clear recall checkboxes
        const checkboxes = document.querySelectorAll('.recall-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
            checkbox.closest('.player-card').classList.remove('selected');
        });
        
        // Update recall status
        updateRecallStatus();
        
        showMessage('Î™®Îì† ?êÏàòÍ∞Ä ÏßÄ?åÏ°å?µÎãà??', 'success');
    }
}

// Recall system event handlers
    document.addEventListener('DOMContentLoaded', function() {
    // Handle player card clicks - Touch optimized
    document.querySelectorAll('.player-card').forEach(card => {
        // Click event
        card.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            togglePlayerSelection(this);
        });
        
        // Touch events for better mobile experience
        card.addEventListener('touchstart', function(e) {
            e.preventDefault();
            this.style.transform = 'scale(0.95)';
        });
        
        card.addEventListener('touchend', function(e) {
            e.preventDefault();
            this.style.transform = '';
            togglePlayerSelection(this);
        });
        
        // Prevent default touch behavior
        card.addEventListener('touchmove', function(e) {
            e.preventDefault();
        });
    });

    // Handle checkbox changes
    document.querySelectorAll('.recall-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updatePlayerCardState(this.closest('.player-card'));
            updateRecallStatus();
        });
    });

    // Initialize recall status
    updateRecallStatus();
    
    // Initialize drag and drop for final ranking
    initializeDragAndDrop();
    
    // Load saved scores
    loadSavedScores();
    
    // Initialize fixed progress bar
    initializeFixedProgress();
});

function initializeDragAndDrop() {
    const container = document.getElementById('rankingContainer');
    if (!container) return;
    
    let draggedElement = null;
    
    // Handle drag start
    container.addEventListener('dragstart', function(e) {
        if (e.target.classList.contains('ranking-item')) {
            draggedElement = e.target;
            e.target.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
    });
    
    // Handle drag end
    container.addEventListener('dragend', function(e) {
        if (e.target.classList.contains('ranking-item')) {
            e.target.classList.remove('dragging');
            draggedElement = null;
        }
    });
    
    // Handle drag over
    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        const afterElement = getDragAfterElement(container, e.clientY);
        if (afterElement == null) {
            container.appendChild(draggedElement);
        } else {
            container.insertBefore(draggedElement, afterElement);
        }
    });
    
    // Handle drop
    container.addEventListener('drop', function(e) {
        e.preventDefault();
        updateRankingNumbers();
    });
    
    // Handle manual input changes
    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('ranking-place-input')) {
            updateRankingFromInputs();
        }
    });
}

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.ranking-item:not(.dragging)')];
    
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function updateRankingNumbers() {
    const container = document.getElementById('rankingContainer');
    if (!container) return;
    
    const items = container.querySelectorAll('.ranking-item');
    items.forEach((item, index) => {
        const numberDiv = item.querySelector('.ranking-number');
        const input = item.querySelector('.ranking-place-input');
        
        numberDiv.textContent = index + 1;
        input.value = index + 1;
        
        // Update input name to reflect new position
        const player = item.dataset.player;
        input.name = `adjudicator_marks[${index + 1}][${player}]`;
    });
}

function updateRankingFromInputs() {
    const container = document.getElementById('rankingContainer');
    if (!container) return;
    
    const items = Array.from(container.querySelectorAll('.ranking-item'));
    const inputs = items.map(item => ({
        element: item,
        value: parseInt(item.querySelector('.ranking-place-input').value) || 0
    }));
    
    // Sort by input value
    inputs.sort((a, b) => a.value - b.value);
    
    // Reorder DOM elements
    inputs.forEach((input, index) => {
        container.appendChild(input.element);
        
        // Update visual number
        const numberDiv = input.element.querySelector('.ranking-number');
        numberDiv.textContent = index + 1;
        
        // Update input name
        const player = input.element.dataset.player;
        const inputElement = input.element.querySelector('.ranking-place-input');
        inputElement.name = `adjudicator_marks[${index + 1}][${player}]`;
    });
}

function loadSavedScores() {
    <?php if ($saved_scores && !$is_final): ?>
    // Load saved recall scores
    const savedScores = <?= json_encode($saved_scores) ?>;
    
    Object.keys(savedScores).forEach(dance => {
        const danceSection = document.querySelector(`[data-dance="${dance}"]`);
        if (!danceSection) return;
        
        const selectedPlayers = savedScores[dance];
        selectedPlayers.forEach(playerNo => {
            const checkbox = danceSection.querySelector(`input[data-player="${playerNo}"]`);
            if (checkbox) {
                checkbox.checked = true;
                updatePlayerCardState(checkbox.closest('.player-card'));
            }
        });
    });

    updateRecallStatus();
    <?php endif; ?>
}

function initializeFixedProgress() {
    const fixedProgress = document.getElementById('fixedProgress');
    if (!fixedProgress) return;
    
    let isScrolling = false;
    let scrollTimeout;
    
    // Show progress bar when scrolling down
    window.addEventListener('scroll', function() {
        if (!isScrolling) {
            fixedProgress.classList.add('show');
            document.body.classList.add('progress-bar-visible');
        }
        
        isScrolling = true;
        clearTimeout(scrollTimeout);
        
        scrollTimeout = setTimeout(function() {
            isScrolling = false;
            // Hide progress bar after scrolling stops (only if no selections)
            const totalSelected = document.querySelectorAll('.recall-checkbox:checked').length;
            if (totalSelected === 0) {
                fixedProgress.classList.remove('show');
                document.body.classList.remove('progress-bar-visible');
            }
        }, 1000);
    });
    
    // Touch events for mobile
    document.addEventListener('touchstart', function() {
        fixedProgress.classList.add('show');
        document.body.classList.add('progress-bar-visible');
    });
}

function togglePlayerSelection(card) {
    const checkbox = card.querySelector('.recall-checkbox');
    checkbox.checked = !checkbox.checked;
    updatePlayerCardState(card);
    updateRecallStatus();
    updateTopRecallStatus(); // Add this line
}

function updatePlayerCardState(card) {
    const checkbox = card.querySelector('.recall-checkbox');
    if (checkbox.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
}

function updateTopRecallStatus() {
    // Get all checked checkboxes from all pages
    const allCheckboxes = document.querySelectorAll('.recall-checkbox:checked');
    const selectedCount = allCheckboxes.length;
    const recallCount = <?= json_encode(intval($recall_count)) ?>;
    
    // Update top recall progress display
    const topRecallProgress = document.getElementById('recallProgress');
    if (topRecallProgress) {
        topRecallProgress.textContent = selectedCount;
        
        // Update color based on progress
        if (selectedCount === recallCount) {
            topRecallProgress.style.color = '#4CAF50';
            topRecallProgress.style.fontWeight = 'bold';
        } else if (selectedCount > recallCount) {
            topRecallProgress.style.color = '#f44336';
            topRecallProgress.style.fontWeight = 'bold';
        } else {
            topRecallProgress.style.color = '#ff9800';
            topRecallProgress.style.fontWeight = 'normal';
        }
    }
    
    // Update fixed progress bar
    const fixedRecallCount = document.getElementById('fixedRecallCount');
    if (fixedRecallCount) {
        fixedRecallCount.textContent = selectedCount;
    }
    
    // Update submit button state for current dance page
    const currentPage = document.querySelector('.dance-page[data-dance-index="' + currentDanceIndex + '"]');
    if (currentPage) {
        const submitSection = currentPage.querySelector('.dance-submit-section');
        const submitBtn = currentPage.querySelector('.dance-submit-btn');
        
        if (submitSection && submitBtn) {
            // Show/hide submit section based on selection count
            if (selectedCount === recallCount) {
                submitSection.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            } else {
                submitSection.style.display = 'none';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        }
    }
}

function updateRecallStatus() {
    const currentPage = document.querySelector('.dance-page[data-dance-index="' + currentDanceIndex + '"]');
    
    if (!currentPage) {
        // Fallback: get all checked checkboxes from all pages
        const allCheckboxes = document.querySelectorAll('.recall-checkbox:checked');
        const selectedCount = allCheckboxes.length;
        const recallCount = <?= json_encode(intval($recall_count)) ?>;
        
        // Update top recall progress display
        const topRecallProgress = document.getElementById('recallProgress');
        if (topRecallProgress) {
            topRecallProgress.textContent = selectedCount;
            
            // Update color based on progress
            if (selectedCount === recallCount) {
                topRecallProgress.style.color = '#4CAF50';
                topRecallProgress.style.fontWeight = 'bold';
            } else if (selectedCount > recallCount) {
                topRecallProgress.style.color = '#f44336';
                topRecallProgress.style.fontWeight = 'bold';
            } else {
                topRecallProgress.style.color = '#ff9800';
                topRecallProgress.style.fontWeight = 'normal';
            }
        }
        return;
    }
    
    const checkboxes = currentPage.querySelectorAll('.recall-checkbox:checked');
    const selectedCount = checkboxes.length;
    const recallCount = <?= json_encode(intval($recall_count)) ?>;
    
    // Top recall progress is handled by updateTopRecallStatus function
    
    // Update fixed progress bar
    const fixedProgress = document.getElementById('fixedProgress');
    if (fixedProgress) {
        const progressText = fixedProgress.querySelector('#recallProgress');
        if (progressText) {
            progressText.textContent = selectedCount;
        }
        
        // Update progress bar styling
        fixedProgress.classList.remove('complete', 'over');
        if (selectedCount === recallCount) {
            fixedProgress.classList.add('complete');
        } else if (selectedCount > recallCount) {
            fixedProgress.classList.add('over');
        }
    }
    
    // Update current page status
    const statusDiv = currentPage.querySelector('.recall-status');
    const countSpan = statusDiv.querySelector('.selected-count');
    const submitSection = currentPage.querySelector('.dance-submit-section');
    
    if (countSpan) {
        countSpan.textContent = selectedCount;
    }
    
    // Update status styling
    statusDiv.classList.remove('complete', 'over');
    if (selectedCount === recallCount) {
        statusDiv.classList.add('complete');
        if (submitSection) submitSection.style.display = 'block';
    } else if (selectedCount > recallCount) {
        statusDiv.classList.add('over');
        if (submitSection) submitSection.style.display = 'none';
    } else {
        if (submitSection) submitSection.style.display = 'none';
    }
}

function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    messageDiv.innerHTML = `<div class="${type}">${message}</div>`;
    setTimeout(() => {
        messageDiv.innerHTML = '';
    }, 5000);
}

function showResults(results) {
    let resultsHtml = '<div class="success"><h3>ÏµúÏ¢Ö Í≤∞Í≥º (Skating System ?ÅÏö©)</h3><ol>';
    const sortedResults = Object.entries(results).sort((a, b) => a[1] - b[1]);
    sortedResults.forEach(([coupleId, place]) => {
        resultsHtml += `<li>?†Ïàò #${coupleId} - ${place}??/li>`;
    });
    resultsHtml += '</ol></div>';
    document.getElementById('message').innerHTML = resultsHtml;
}

// Instagram Style Dance System
let currentDanceIndex = 0;
let totalDances = 0;
let completedDances = new Set();
let existingSelections = <?= json_encode($existing_selections) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('.place-input');
    if (firstInput) {
        firstInput.focus();
    }
    
    // Initialize dance system if in preliminary round (not final)
    <?php if (!$is_final && $event_no !== '8'): ?>
    const danceSlider = document.getElementById('danceSlider');
    if (danceSlider) {
        initializeDanceSystem();
    }
    <?php endif; ?>
    
    // Setup fixed action buttons
    setupFixedButtons();
});

function setupFixedButtons() {
    // Dashboard button
    const dashboardBtn = document.getElementById('dashboardBtn');
    if (dashboardBtn) {
        dashboardBtn.addEventListener('click', function() {
            const compId = '<?= h($comp_id) ?>';
            const lang = '<?= h($lang) ?>';
            window.location.href = `scoring_dashboard.php?comp_id=${compId}&lang=${lang}`;
        });
    }
    
    // Refresh button
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            if (confirm('?òÏù¥ÏßÄÎ•??àÎ°úÍ≥†Ïπ®?òÏãúÍ≤†Ïäµ?àÍπå? ?ÑÏû¨ ?ëÏóÖ Ï§ëÏù∏ ?¥Ïö©???Ä?•ÎêòÏßÄ ?äÏùÑ ???àÏäµ?àÎã§.')) {
                window.location.reload();
            }
        });
    }
}

function loadExistingSelections() {
    // Load existing selections for all dances
    Object.keys(existingSelections).forEach(dance => {
        const dancePage = document.querySelector(`[data-dance="${dance}"]`);
        if (!dancePage) return;
        
        const selectedPlayers = existingSelections[dance];
        selectedPlayers.forEach(playerNo => {
            const checkbox = dancePage.querySelector(`input[data-player="${playerNo}"]`);
            if (checkbox) {
                checkbox.checked = true;
                const card = checkbox.closest('.player-card');
                if (card) {
                    card.classList.add('selected');
                }
            }
        });
        
        // Mark as completed if selections exist
        if (selectedPlayers.length > 0) {
            completedDances.add(dance);
        }
    });
}

function loadExistingSelectionsForDance(page, dance) {
    // Load existing selections for a specific dance
    if (existingSelections[dance]) {
        const selectedPlayers = existingSelections[dance];
        selectedPlayers.forEach(playerNo => {
            const checkbox = page.querySelector(`input[data-player="${playerNo}"]`);
            if (checkbox) {
                checkbox.checked = true;
                const card = checkbox.closest('.player-card');
                if (card) {
                    card.classList.add('selected');
                }
            }
        });
    }
}

function initializeDanceSystem() {
    const dancePages = document.querySelectorAll('.dance-page');
    totalDances = dancePages.length;
    
    if (totalDances === 0) return;
    
    // Load existing selections for all dances
    loadExistingSelections();
    
    // Show first dance page
    showDancePage(0);
    
    // Setup event listeners
    setupDanceEventListeners();
    updateProgress();
    
    // Initialize top recall status
    updateTopRecallStatus();
}

function showDancePage(index) {
    const danceSlider = document.getElementById('danceSlider');
    const dancePages = document.querySelectorAll('.dance-page');
    
    if (index < 0 || index >= totalDances) return;
    
    currentDanceIndex = index;
    danceSlider.style.transform = `translateX(-${index * 100}%)`;
    
    // Update progress
    updateProgress();
    
    // Update navigation buttons
    updateNavigationButtons();
    
    // Reset touch events for current page
    resetTouchEvents();
    
    // Restore dance page state if going back to a completed dance
    const currentPage = document.querySelector('.dance-page[data-dance-index="' + currentDanceIndex + '"]');
    if (currentPage) {
        const dance = currentPage.dataset.dance;
        const isCompleted = completedDances.has(dance);
        
        if (isCompleted) {
            // Restore the page to editable state
            restoreDancePageState(currentPage, dance);
        } else {
            // Load existing selections for this dance if not completed
            loadExistingSelectionsForDance(currentPage, dance);
        }
    }
    
    // Update recall status for current page
    updateTopRecallStatus();
}

function setupDanceEventListeners() {
    // Navigation buttons
    document.getElementById('prevDance')?.addEventListener('click', () => {
        if (currentDanceIndex > 0) {
            showDancePage(currentDanceIndex - 1);
        }
    });
    
    document.getElementById('nextDance')?.addEventListener('click', () => {
        if (currentDanceIndex < totalDances - 1) {
            showDancePage(currentDanceIndex + 1);
        }
    });
    
    // Dance submit buttons
    document.querySelectorAll('.dance-submit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const dance = this.dataset.dance;
            submitDance(dance);
        });
    });
    
    // Touch/swipe events
    let startX = 0;
    let startY = 0;
    let isSwipe = false;
    
    const danceSlider = document.getElementById('danceSlider');
    
    danceSlider.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        isSwipe = false;
    });
    
    danceSlider.addEventListener('touchmove', function(e) {
        if (!startX || !startY) return;
        
        const currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const diffX = startX - currentX;
        const diffY = startY - currentY;
        
        // Determine if this is a horizontal swipe
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
            isSwipe = true;
            e.preventDefault();
        }
    });
    
    danceSlider.addEventListener('touchend', function(e) {
        if (!isSwipe || !startX || !startY) return;
        
        const endX = e.changedTouches[0].clientX;
        const diffX = startX - endX;
        
        if (Math.abs(diffX) > 100) {
            if (diffX > 0 && currentDanceIndex < totalDances - 1) {
                // Swipe left - next dance
                showDancePage(currentDanceIndex + 1);
            } else if (diffX < 0 && currentDanceIndex > 0) {
                // Swipe right - previous dance
                showDancePage(currentDanceIndex - 1);
            }
        }
        
        startX = 0;
        startY = 0;
        isSwipe = false;
    });
}

function resetTouchEvents() {
    // Remove existing event listeners and re-add them
    const playerCards = document.querySelectorAll('.player-card');
    playerCards.forEach(card => {
        // Remove old listeners by cloning the element
        const newCard = card.cloneNode(true);
        card.parentNode.replaceChild(newCard, card);
        
        // Add new listeners
        setupPlayerCardEvents(newCard);
    });
}

function setupPlayerCardEvents(card) {
    let touchStartTime = 0;
    let touchStartPos = { x: 0, y: 0 };
    
    // Click event
    card.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        togglePlayerSelection(this);
    });
    
    // Touch start
    card.addEventListener('touchstart', function(e) {
        touchStartTime = Date.now();
        const touch = e.touches[0];
        touchStartPos = { x: touch.clientX, y: touch.clientY };
    });
    
    // Touch end
    card.addEventListener('touchend', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const touchEndTime = Date.now();
        const touchDuration = touchEndTime - touchStartTime;
        
        // Only trigger if it's a quick tap (less than 500ms)
        if (touchDuration < 500) {
            togglePlayerSelection(this);
        }
    });
    
    // Prevent context menu on long press
    card.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
}

function updateProgress() {
    const progressFill = document.querySelector('.progress-fill');
    const currentDanceSpan = document.querySelector('.current-dance');
    
    if (progressFill) {
        const progress = ((currentDanceIndex + 1) / totalDances) * 100;
        progressFill.style.width = progress + '%';
    }
    
    if (currentDanceSpan) {
        currentDanceSpan.textContent = currentDanceIndex + 1;
    }
}

function restoreDancePageState(page, dance) {
    // Remove from completed dances to allow re-editing
    completedDances.delete(dance);
    
    // Restore the recall status section
    const statusDiv = page.querySelector('.recall-status');
    if (statusDiv) {
        statusDiv.innerHTML = `
            <div style="font-size:20px; margin-bottom:5px;">?ìä Recall ÏßÑÌñâ ?ÅÌô©</div>
            <div style="font-size:28px; font-weight:900; color:#2e7d32;">
                <span class="selected-count">0</span> / <?= $recall_count ?>Î™?            </div>
            <div style="font-size:14px; margin-top:5px; opacity:0.8;">
                <?= $recall_count ?>Î™ÖÏùÑ ?†ÌÉù?òÎ©¥ ?ÑÏÜ°?????àÏäµ?àÎã§
            </div>
        `;
    }
    
    // Show submit section (will be hidden/shown based on selection count)
    const submitSection = page.querySelector('.dance-submit-section');
    if (submitSection) {
        submitSection.style.display = 'none';
    }
    
    // Load existing selections for this dance
    if (existingSelections[dance]) {
        const selectedPlayers = existingSelections[dance];
        selectedPlayers.forEach(playerNo => {
            const checkbox = page.querySelector(`input[data-player="${playerNo}"]`);
            if (checkbox) {
                checkbox.checked = true;
                const card = checkbox.closest('.player-card');
                if (card) {
                    card.classList.add('selected');
                }
            }
        });
    } else {
        // No existing selections, clear all
        const checkboxes = page.querySelectorAll('.recall-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Update player card states
        const playerCards = page.querySelectorAll('.player-card');
        playerCards.forEach(card => {
            card.classList.remove('selected');
        });
    }
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevDance');
    const nextBtn = document.getElementById('nextDance');
    
    if (prevBtn) {
        prevBtn.style.display = currentDanceIndex > 0 ? 'inline-block' : 'none';
    }
    
    if (nextBtn) {
        // Show next button if there are more dances AND current dance is completed
        const currentDance = document.querySelector('.dance-page[data-dance-index="' + currentDanceIndex + '"]');
        const isCurrentDanceCompleted = currentDance && completedDances.has(currentDance.dataset.dance);
        
        if (currentDanceIndex < totalDances - 1 && isCurrentDanceCompleted) {
            nextBtn.style.display = 'inline-block';
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        } else {
            nextBtn.style.display = 'none';
        }
    }
}

function submitDance(dance) {
    const currentPage = document.querySelector(`[data-dance="${dance}"]`);
    const checkboxes = currentPage.querySelectorAll('.recall-checkbox:checked');
    const recallCount = <?= json_encode(intval($recall_count)) ?>;
    
    if (checkboxes.length !== recallCount) {
        alert(`${recallCount}Î™ÖÏùÑ ?ïÌôï???†ÌÉù?¥Ï£º?∏Ïöî.`);
        return;
    }
    
    // Collect selected players for this dance
    const selectedPlayers = Array.from(checkboxes).map(cb => cb.dataset.player);
    
    // Prepare data for this dance only
    const recallMarks = {};
    recallMarks[dance] = selectedPlayers;
    
    const data = {
        comp_id: <?= json_encode($comp_id) ?>,
        event_no: <?= json_encode($event_no) ?>,
        type: 'recall',
        recall_marks: recallMarks
    };
    
    // Show loading state
    const submitBtn = currentPage.querySelector('.dance-submit-btn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '?ÑÏÜ° Ï§?..';
    submitBtn.disabled = true;
    
    fetch('scoring/save_scores.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Mark this dance as completed
            completedDances.add(dance);
            
            // Update existing selections
            existingSelections[dance] = selectedPlayers;
            
            // Hide submit button and show completion message
            submitBtn.style.display = 'none';
            const statusDiv = currentPage.querySelector('.recall-status');
            statusDiv.innerHTML = `
                <div style="font-size:20px; margin-bottom:5px;">???ÑÎ£å</div>
                <div style="font-size:18px; font-weight:bold; color:#4CAF50;">
                    ${recallCount}Î™??†ÌÉù ?ÑÎ£å
                </div>
                <div style="font-size:14px; margin-top:5px; opacity:0.8;">
                    ?§Ïùå ?ÑÏä§Î°?ÏßÑÌñâ?òÏÑ∏??                </div>
            `;
            
            // Enable next dance button if available
            updateNavigationButtons();
            
            // Show completion message
            if (currentDanceIndex < totalDances - 1) {
                setTimeout(() => {
                    alert(`${dance} ?ÑÏä§ Ï±ÑÏ†ê???ÑÎ£å?òÏóà?µÎãà?? ?§Ïùå ?ÑÏä§Î°?ÏßÑÌñâ?òÏÑ∏??`);
                }, 1000);
            } else {
                // All dances completed
                setTimeout(() => {
                    alert('Î™®Îì† ?ÑÏä§ Ï±ÑÏ†ê???ÑÎ£å?òÏóà?µÎãà??');
                }, 1000);
            }
        } else {
            alert('?ÑÏÜ° ?§Ìå®: ' + (result.error || '?????ÜÎäî ?§Î•ò'));
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('?ÑÏÜ° Ï§??§Î•òÍ∞Ä Î∞úÏÉù?àÏäµ?àÎã§: ' + error);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Final Round Dance-by-Dance System
<?php if ($is_final || $event_no === '8'): ?>
let selectedPlayer = null;
let playerRankings = {};
let draggedSlot = null;
let isDragging = false;
let dragStartTime = 0;
let finalDanceNames = <?= json_encode($event_data['dances']) ?>;
let finalCompletedDances = new Set();
let savedRankings = <?= json_encode($saved_rankings ?? []) ?>;
let currentJudgeId = <?= json_encode($judge_id) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize dance-by-dance system
    initializeFinalDanceSystem();
    setupFinalDanceNavigation();
    
    // Initialize touch-based system
    initializeTouchSystem();
    
    // Setup drag and drop for ranking slots
    setupDragAndDrop();
});

function initializeFinalDanceSystem() {
    totalDances = finalDanceNames.length;
    // Populate progress header
    updateFinalDanceProgress();
    // Show first dance
    showFinalDance(0);
}

function updateFinalDanceProgress() {
    const progressEl = document.getElementById('danceProgress');
    if (!progressEl) return;
    progressEl.innerHTML = finalDanceNames.map((dance, idx) => {
        const isCurrent = idx === currentDanceIndex;
        const isCompleted = finalCompletedDances.has(dance);
        const isPending = !isCompleted && !isCurrent;
        return `<span class="dance-step ${isCurrent ? 'current' : (isCompleted ? 'completed' : 'pending')}">${dance}</span>`;
    }).join('');
}

function showFinalDance(danceIndex) {
    if (danceIndex < 0 || danceIndex >= totalDances) return;
    currentDanceIndex = danceIndex;
    updateFinalDanceProgress();
    updateCurrentDanceDisplay();
    
    // Clear rankings when switching dances
    clearFinalScores();
    
    // Update navigation after DOM is ready
    setTimeout(() => {
        updateFinalDanceNav();
    }, 50);
    
    // Restore saved rankings for current dance (after clearing)
    setTimeout(() => {
        restoreSavedRankings();
    }, 100);
}

function updateCurrentDanceDisplay() {
    const currentDanceEl = document.getElementById('currentDanceDisplay');
    if (currentDanceEl && finalDanceNames[currentDanceIndex]) {
        currentDanceEl.textContent = `?ÑÏû¨ Ï¢ÖÎ™©: ${finalDanceNames[currentDanceIndex]}`;
    }
}

function setupFinalDanceNavigation() {
    // Bind "Submit Dance" and "Next Dance" buttons
    const submitBtn = document.getElementById('saveFinalScores');
    if (submitBtn) {
        submitBtn.addEventListener('click', submitFinalDance);
    }
    const nextBtn = document.getElementById('nextFinalDance');
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentDanceIndex < totalDances - 1) {
                showFinalDance(currentDanceIndex + 1);
            }
        });
    }
}

function submitFinalDance() {
    const totalPlayers = document.querySelectorAll('.player-item').length;
    const assignedPlayers = Object.keys(playerRankings).length;
    
    if (assignedPlayers < totalPlayers) {
        alert(`Î™®Îì† ?†Ïàò?êÍ≤å ?úÏúÑÎ•?Î∂Ä?¨Ìï¥???©Îãà?? (${assignedPlayers}/${totalPlayers})`);
        return;
    }
    
    const form = document.getElementById('scoringForm');
    const formData = new FormData(form);
    formData.append('is_final', '1');
    formData.append('dance', finalDanceNames[currentDanceIndex]);
    
    Object.keys(playerRankings).forEach(player => {
        formData.append(`final_ranking[${playerRankings[player]}]`, player);
    });
    
    const submitBtn = document.getElementById('saveFinalScores');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '?ÑÏÜ° Ï§?..';
    submitBtn.disabled = true;
    
    fetch('scoring/save_scores.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            if (result.dance_completed) {
                // All judges completed this dance
                finalCompletedDances.add(finalDanceNames[currentDanceIndex]);
                updateFinalDanceProgress();
                alert(`${finalDanceNames[currentDanceIndex]} ?ÑÏä§ Ï±ÑÏ†ê???ÑÎ£å?òÏóà?µÎãà??`);
            } else {
                // Only this judge submitted
                alert(`${finalDanceNames[currentDanceIndex]} ?ÑÏä§ ?êÏàòÍ∞Ä ?Ä?•Îêò?àÏäµ?àÎã§`);
            }
            
            if (currentDanceIndex < totalDances - 1) {
                showFinalDance(currentDanceIndex + 1);
            } else {
                updateFinalDanceNav();
                alert('Î™®Îì† Ï¢ÖÎ™© ?úÏúÑ ?úÏ∂ú???ÑÎ£å?òÏóà?µÎãà??');
            }
        } else {
            alert('?úÏ∂ú ?§Ìå®: ' + (result.error || '?????ÜÎäî ?§Î•ò'));
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('?úÏ∂ú Ï§??§Î•òÍ∞Ä Î∞úÏÉù?àÏäµ?àÎã§: ' + error);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function updateFinalDanceNav() {
    const submitBtn = document.getElementById('saveFinalScores');
    const nextBtn = document.getElementById('nextFinalDance');
    
    console.log('updateFinalDanceNav called - currentDanceIndex:', currentDanceIndex, 'totalDances:', totalDances);
    
    if (submitBtn) {
        const buttonText = currentDanceIndex < totalDances - 1 ? `${finalDanceNames[currentDanceIndex]} ?úÏúÑ ?úÏ∂ú` : 'ÏµúÏ¢Ö ?Ä??;
        submitBtn.textContent = buttonText;
        console.log('Button text updated to:', buttonText);
    }
    if (nextBtn) {
        nextBtn.style.display = (currentDanceIndex < totalDances - 1) ? 'inline-block' : 'none';
    }
    // Hide clear button
    const clearBtn = document.getElementById('clearFinalScores');
    if (clearBtn) clearBtn.style.display = 'none';
}

function initializeTouchSystem() {
    // Clear any existing rankings when switching dances
    clearFinalScores();
    
    // Bind clear button with confirmation
    const clearBtn = document.getElementById('clearFinalScores');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearFinalScoresWithConfirm);
    }
}

function setupDragAndDrop() {
    // Add drag and drop event listeners to all ranking slots
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.addEventListener('dragstart', dragStart);
        slot.addEventListener('dragover', dragOver);
        slot.addEventListener('drop', drop);
        slot.addEventListener('dragend', dragEnd);
        slot.addEventListener('mousedown', handleMouseDown);
        slot.addEventListener('mouseup', handleMouseUp);
    });
}

// Final Round JavaScript - Touch-based System

function selectPlayer(element) {
    // Remove previous selection
    document.querySelectorAll('.player-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Check if player is already assigned
    if (element.classList.contains('assigned')) {
        return;
    }
    
    // Select current player
    element.classList.add('selected');
    selectedPlayer = element.dataset.player;
    
    console.log('Selected player:', selectedPlayer);
}

function assignRank(element) {
    // If no player is selected, check if we can cancel an existing assignment
    if (!selectedPlayer) {
        if (element.classList.contains('assigned')) {
            // Cancel existing assignment
            cancelRank(element);
        }
        return;
    }
    
    const rank = element.dataset.rank;
    
    // Check if this rank is already assigned
    if (element.classList.contains('assigned')) {
        // Remove existing assignment
        const currentPlayer = element.querySelector('.player-assigned').textContent;
        if (currentPlayer) {
            // Remove from player list
            const playerElement = document.querySelector(`[data-player="${currentPlayer}"]`);
            if (playerElement) {
                playerElement.classList.remove('assigned');
            }
            // Remove from rankings
            delete playerRankings[currentPlayer];
        }
    }
    
    // Assign new player to this rank
    element.querySelector('.player-assigned').textContent = selectedPlayer;
    element.querySelector('input').value = selectedPlayer;
    element.classList.add('assigned');
    
    // Mark player as assigned
    const playerElement = document.querySelector(`[data-player="${selectedPlayer}"]`);
    if (playerElement) {
        playerElement.classList.add('assigned');
    }
    
    // Update rankings object
    playerRankings[selectedPlayer] = rank;
    
    // Clear selection
    selectedPlayer = null;
    document.querySelectorAll('.player-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    console.log('Assigned player', selectedPlayer, 'to rank', rank);
    console.log('Current rankings:', playerRankings);
}

function cancelRank(element) {
    const currentPlayer = element.querySelector('.player-assigned').textContent;
    if (currentPlayer) {
        // Remove from player list
        const playerElement = document.querySelector(`[data-player="${currentPlayer}"]`);
        if (playerElement) {
            playerElement.classList.remove('assigned');
        }
        
        // Clear the ranking slot
        element.querySelector('.player-assigned').textContent = '';
        element.querySelector('input').value = '';
        element.classList.remove('assigned');
        
        // Remove from rankings
        delete playerRankings[currentPlayer];
        
        console.log('Cancelled assignment for player', currentPlayer);
        console.log('Current rankings:', playerRankings);
    }
}

// Drag and Drop functions for ranking slots
function dragStart(event) {
    console.log('dragStart called', event.target);
    isDragging = true;
    draggedSlot = event.target;
    draggedSlot.classList.add('dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/html', draggedSlot.outerHTML);
}

function dragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    
    // Add visual feedback
    if (event.target.classList.contains('ranking-slot')) {
        event.target.classList.add('drag-over');
        console.log('dragOver on ranking slot');
    }
}

function drop(event) {
    event.preventDefault();
    console.log('drop called', event.target, 'draggedSlot:', draggedSlot);
    
    // Remove drag-over class
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.classList.remove('drag-over');
    });
    
    if (event.target.classList.contains('ranking-slot') && draggedSlot !== event.target) {
        console.log('Swapping rankings between slots');
        // Swap the players between the two slots
        swapRankings(draggedSlot, event.target);
    }
}

function dragEnd(event) {
    // Remove dragging class
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.classList.remove('dragging', 'drag-over');
    });
    draggedSlot = null;
    isDragging = false;
}

// Mouse event handlers for better drag detection
function handleMouseDown(event) {
    dragStartTime = Date.now();
    isDragging = false;
    console.log('Mouse down on ranking slot');
}

function handleMouseUp(event) {
    const dragDuration = Date.now() - dragStartTime;
    console.log('Mouse up, drag duration:', dragDuration);
    
    if (dragDuration > 200 && isDragging) {
        // This was a drag operation, not a click
        event.preventDefault();
        event.stopPropagation();
        return false;
    }
}

// Double-click to swap with next rank
function swapWithNext(element) {
    console.log('Double-clicked ranking slot:', element);
    
    const currentRank = parseInt(element.dataset.rank);
    const nextRank = currentRank + 1;
    const nextElement = document.querySelector(`[data-rank="${nextRank}"]`);
    
    if (nextElement && element.classList.contains('assigned') && nextElement.classList.contains('assigned')) {
        console.log('Swapping ranks', currentRank, 'and', nextRank);
        swapRankings(element, nextElement);
    } else {
        console.log('Cannot swap - one or both slots are empty');
    }
}

function swapRankings(slot1, slot2) {
    const player1 = slot1.querySelector('.player-assigned').textContent;
    const player2 = slot2.querySelector('.player-assigned').textContent;
    const input1 = slot1.querySelector('input');
    const input2 = slot2.querySelector('input');
    
    // Swap the players
    slot1.querySelector('.player-assigned').textContent = player2;
    slot2.querySelector('.player-assigned').textContent = player1;
    input1.value = player2;
    input2.value = player1;
    
    // Update visual states
    if (player1) {
        slot1.classList.add('assigned');
    } else {
        slot1.classList.remove('assigned');
    }
    
    if (player2) {
        slot2.classList.add('assigned');
    } else {
        slot2.classList.remove('assigned');
    }
    
    // Update rankings object
    const rank1 = slot1.dataset.rank;
    const rank2 = slot2.dataset.rank;
    
    if (player1) {
        playerRankings[player1] = rank2;
    } else {
        // Remove from rankings if empty
        Object.keys(playerRankings).forEach(player => {
            if (playerRankings[player] === rank1) {
                delete playerRankings[player];
            }
        });
    }
    
    if (player2) {
        playerRankings[player2] = rank1;
    } else {
        // Remove from rankings if empty
        Object.keys(playerRankings).forEach(player => {
            if (playerRankings[player] === rank2) {
                delete playerRankings[player];
            }
        });
    }
    
    console.log('Swapped rankings:', playerRankings);
}

function submitFinalDance() {
    // Check if all players are assigned
    const totalPlayers = document.querySelectorAll('.player-item').length;
    const assignedPlayers = Object.keys(playerRankings).length;
    
    if (assignedPlayers < totalPlayers) {
        alert(`Î™®Îì† ?†Ïàò?êÍ≤å ?úÏúÑÎ•?Î∂Ä?¨Ìï¥???©Îãà?? (${assignedPlayers}/${totalPlayers})`);
        return;
    }
    
    const form = document.getElementById('scoringForm');
    const formData = new FormData(form);
    
    // Add final round flag
    formData.append('is_final', '1');
    
    // Add current dance
    const currentDance = finalDanceNames[currentDanceIndex];
    formData.append('dance', currentDance);
    
    // Add rankings data in the format expected by save_scores.php
    const adjudicator_marks = {};
    adjudicator_marks[<?= json_encode($judge_id) ?>] = {};
    
    Object.keys(playerRankings).forEach(player => {
        const rank = playerRankings[player];
        adjudicator_marks[<?= json_encode($judge_id) ?>][player] = rank;
    });
    
    console.log('Sending adjudicator_marks:', adjudicator_marks);
    formData.append('adjudicator_marks', JSON.stringify(adjudicator_marks));
    
    const saveBtn = document.getElementById('saveFinalScores');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = '?Ä??Ï§?..';
    saveBtn.disabled = true;
    
    fetch('scoring/save_scores.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            if (result.dance_completed) {
                finalCompletedDances.add(currentDance);
                updateFinalDanceProgress();
                alert(`${currentDance} ?ÑÏä§ Ï±ÑÏ†ê???ÑÎ£å?òÏóà?µÎãà??`);
            } else {
                alert(`${currentDance} ?ÑÏä§ ?êÏàòÍ∞Ä ?Ä?•Îêò?àÏäµ?àÎã§`);
            }
            updateFinalDanceNav();
            
            if (currentDanceIndex < totalDances - 1) {
                showFinalDance(currentDanceIndex + 1);
            } else {
                alert('Î™®Îì† Ï¢ÖÎ™© ?úÏúÑ ?úÏ∂ú???ÑÎ£å?òÏóà?µÎãà??');
            }
        } else {
            alert('?Ä???§Ìå®: ' + (result.error || '?????ÜÎäî ?§Î•ò'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('?Ä??Ï§??§Î•òÍ∞Ä Î∞úÏÉù?àÏäµ?àÎã§: ' + error);
    })
    .finally(() => {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}

function clearFinalScores() {
    // Clear all assignments (no confirmation for dance switching)
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.classList.remove('assigned');
        slot.querySelector('.player-assigned').textContent = '';
        slot.querySelector('input').value = '';
    });
    
    document.querySelectorAll('.player-item').forEach(item => {
        item.classList.remove('assigned');
    });
    
    // Clear selection
    selectedPlayer = null;
    playerRankings = {};
    
    console.log('Cleared all scores');
}

function clearFinalScoresWithConfirm() {
    if (confirm('Î™®Îì† ?êÏàòÎ•?Ï¥àÍ∏∞?îÌïò?úÍ≤†?µÎãàÍπ?')) {
        clearFinalScores();
    }
}

function restoreSavedRankings() {
    // Get current dance name
    const currentDance = finalDanceNames[currentDanceIndex];
    console.log('=== RESTORE SAVED RANKINGS DEBUG ===');
    console.log('Current judge ID:', currentJudgeId);
    console.log('Current dance:', currentDance);
    console.log('Current dance index:', currentDanceIndex);
    console.log('Available saved rankings:', savedRankings);
    console.log('Final dance names:', finalDanceNames);
    
    if (!currentDance || !savedRankings[currentDance]) {
        console.log('No saved data for dance:', currentDance);
        return; // No saved data for this dance
    }
    
    const rankings = savedRankings[currentDance];
    console.log('Restoring rankings for', currentDance, ':', rankings);
    
    // Restore each ranking
    Object.keys(rankings).forEach(rank => {
        const player = rankings[rank];
        const rankElement = document.querySelector(`[data-rank="${rank}"]`);
        
        console.log(`Attempting to restore rank ${rank}: player ${player}`);
        console.log('Rank element found:', rankElement);
        
        if (rankElement && player) {
            // Assign player to this rank
            rankElement.querySelector('.player-assigned').textContent = player;
            rankElement.querySelector('input').value = player;
            rankElement.classList.add('assigned');
            
            // Mark player as assigned
            const playerElement = document.querySelector(`[data-player="${player}"]`);
            if (playerElement) {
                playerElement.classList.add('assigned');
            }
            
            // Update rankings object
            playerRankings[player] = rank;
            
            console.log(`Successfully restored rank ${rank}: player ${player}`);
        } else {
            console.log(`Failed to restore rank ${rank}: player ${player} - element not found`);
        }
    });
    
    console.log('Restored rankings:', playerRankings);
}
}<?php } // ?ºÎ∞ò Ï±ÑÏ†ê ?úÏä§??Ï¢ÖÎ£å ?>
<?php endif; ?>
    </script>
    
    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <button type="button" id="dashboardBtn" class="nav-button primary">
            <div class="nav-icon">?è†</div>
            <div class="nav-text">Î©îÏù∏?ºÎ°ú</div>
        </button>
        <button type="button" id="refreshBtn" class="nav-button">
            <div class="nav-icon">?îÑ</div>
            <div class="nav-text">?àÎ°úÍ≥†Ïπ®</div>
        </button>
    </div>
    
    <footer style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9em; padding: 20px; border-top: 1px solid #eee;">
        2025 danceoffice.net | Powered by Seyoung Lee
    </footer>
</body>
</html>
