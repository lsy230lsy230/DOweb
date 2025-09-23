<?php
/**
 * 디버그용 라우터 - 단계별 테스트 (수정된 버전)
 */

echo "<h1>Debug Router Test</h1>";

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$lang = $_GET['lang'] ?? 'ko';

echo "<p>Comp ID: " . htmlspecialchars($comp_id) . "</p>";
echo "<p>Event No: " . htmlspecialchars($event_no) . "</p>";
echo "<p>Language: " . htmlspecialchars($lang) . "</p>";

// Step 1: functions.php 포함
echo "<h2>Step 1: Including functions.php</h2>";
require_once __DIR__ . '/scoring/shared/functions.php';
echo "<p style='color: green;'>✓ functions.php included successfully!</p>";

// Step 2: 이벤트 데이터 로드
echo "<h2>Step 2: Loading event data</h2>";
$event_data = loadEventData($comp_id, $event_no);
if ($event_data) {
    echo "<p style='color: green;'>✓ Event Data Loaded: " . htmlspecialchars($event_data['name']) . "</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to load event data.</p>";
    die();
}

// Step 3: 선수 데이터 로드
echo "<h2>Step 3: Loading players data</h2>";
$players = loadPlayersData($comp_id, $event_no);
if ($players) {
    echo "<p style='color: green;'>✓ Players Data Loaded: " . count($players) . " players</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to load players data.</p>";
    die();
}

// Step 4: 댄스 매핑 로드
echo "<h2>Step 4: Loading dance mapping</h2>";
$dance_mapping = loadDanceMapping($comp_id);
echo "<p style='color: green;'>✓ Dance mapping loaded: " . count($dance_mapping) . " dances</p>";

// Step 5: 라운드 정보 로드
echo "<h2>Step 5: Loading round info</h2>";
$round_info = loadRoundInfo($comp_id);
echo "<p style='color: green;'>✓ Round info loaded</p>";

// Step 6: 결승전 여부 확인
echo "<h2>Step 6: Checking if final round</h2>";
$is_final = isFinalRound($event_no, $round_info, $event_data);
echo "<p style='color: green;'>✓ Is final round: " . ($is_final ? 'TRUE' : 'FALSE') . "</p>";

// Step 7: 언어 텍스트 로드
echo "<h2>Step 7: Loading language texts</h2>";
$t = getLanguageTexts($lang);
echo "<p style='color: green;'>✓ Language texts loaded</p>";

// Step 8: 심사 방식 결정
echo "<h2>Step 8: Determining scoring type</h2>";
$scoring_type = 'multievent_final'; // 기본값

$scoring_type_mapping = [
    '8' => 'multievent_final',
    '9' => 'multievent_preliminary',
    '10' => 'freestyle',
    '11' => 'formation',
    '12' => 'multievent_final_only'
];

if (isset($scoring_type_mapping[$event_no])) {
    $scoring_type = $scoring_type_mapping[$event_no];
} elseif ($is_final || $event_no === '8') {
    $scoring_type = 'multievent_final';
}

echo "<p style='color: green;'>✓ Scoring type determined: " . htmlspecialchars($scoring_type) . "</p>";

// Step 9: 심사 파일 존재 확인
echo "<h2>Step 9: Checking scoring file</h2>";
$scoring_file = __DIR__ . "/scoring/types/{$scoring_type}.php";
echo "<p>File path: " . htmlspecialchars($scoring_file) . "</p>";
if (file_exists($scoring_file)) {
    echo "<p style='color: green;'>✓ Scoring file exists</p>";
} else {
    echo "<p style='color: red;'>✗ Scoring file does not exist!</p>";
    die();
}

echo "<h2 style='color: green;'>All steps completed successfully!</h2>";
echo "<p>Now you can try the full router: <a href='judge_scoring_router.php?comp_id=" . htmlspecialchars($comp_id) . "&event_no=" . htmlspecialchars($event_no) . "&lang=" . htmlspecialchars($lang) . "'>judge_scoring_router.php</a></p>";
?>






