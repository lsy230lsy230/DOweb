<?php
/**
 * 심사위원 채점 시스템 - 메인 라우터
 * 5가지 심사 방식을 지원하는 통합 라우터
 */

session_start();

// 공통 함수들 포함
require_once __DIR__ . '/scoring/shared/functions.php';

// 기본 설정
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$lang = $_GET['lang'] ?? 'ko';

// 세션 검증 (임시로 우회)
echo "<!-- SESSION DEBUG -->";
echo "<!-- scoring_logged_in: " . (isset($_SESSION['scoring_logged_in']) ? ($_SESSION['scoring_logged_in'] ? 'TRUE' : 'FALSE') : 'NOT SET') . " -->";
echo "<!-- scoring_comp_id: " . ($_SESSION['scoring_comp_id'] ?? 'NOT SET') . " -->";
echo "<!-- comp_id: " . $comp_id . " -->";
echo "<!-- END SESSION DEBUG -->";

// 임시로 세션 검증 우회 (테스트용)
if (false && !validateJudgeSession($comp_id)) {
    header("Location: scoring_login.php?comp_id=" . urlencode($comp_id));
    exit;
}

// 이벤트 데이터 로드
$event_data = loadEventData($comp_id, $event_no);
if (!$event_data) {
    die('<div class="error">이벤트 데이터를 찾을 수 없습니다.</div>');
}

// 선수 데이터 로드
$players = loadPlayersData($comp_id, $event_no);
if (empty($players)) {
    die('<div class="error">선수 데이터를 찾을 수 없습니다.</div>');
}

// 댄스 매핑 로드
$dance_mapping = loadDanceMapping($comp_id);

// 라운드 정보 로드
$round_info = loadRoundInfo($comp_id);

// 결승전 여부 확인
$is_final = isFinalRound($event_no, $round_info, $event_data);

// 언어 텍스트 로드
$t = getLanguageTexts($lang);

// 심사위원 정보 (임시로 설정)
$judge_id = $_SESSION['scoring_judge_id'] ?? '8';
$judge_name = $_SESSION['scoring_judge_name'] ?? 'Test Judge';
$judge_country = $_SESSION['scoring_judge_country'] ?? 'Korea';

// 이벤트별 데이터 준비
$recall_count = intval($event_data['recall_count'] ?? 0);
$round_display = $event_data['round_type'] ?? '';

// 심사 방식 결정
$scoring_type = 'multievent_preliminary'; // 기본값

// 이벤트별 심사 방식 매핑
$scoring_type_mapping = [
    '8' => 'multievent_final',           // 기존 결승전
    '9' => 'multievent_preliminary',     // 기존 준결승
    '10' => 'freestyle',                 // 프리스타일
    '11' => 'formation',                 // 포메이션
    '12' => 'multievent_final_only'      // 멀티이벤트 결승전(준결승 없음)
];

// 이벤트 번호에 따른 심사 방식 결정
if (isset($scoring_type_mapping[$event_no])) {
    $scoring_type = $scoring_type_mapping[$event_no];
} elseif ($is_final || $event_no === '8') {
    $scoring_type = 'multievent_final';
}

// 심사 방식별 데이터 준비
$hits_data = null;
$existing_selections = [];
$saved_rankings = [];

if ($scoring_type === 'multievent_preliminary') {
    // 예선/준결승용 데이터
    $data_dir = __DIR__ . "/data/$comp_id";
    $hits_file = "$data_dir/players_hits_$event_no.json";
    if (file_exists($hits_file)) {
        $hits_data = json_decode(file_get_contents($hits_file), true);
    }
    $existing_selections = loadExistingSelections($comp_id, $event_no, $judge_id, $event_data['dances']);
} elseif (in_array($scoring_type, ['multievent_final', 'multievent_final_only'])) {
    // 결승전용 데이터
    $data_dir = __DIR__ . "/data/$comp_id";
    foreach ($event_data['dances'] as $dance) {
        $adj_file = "$data_dir/{$event_no}_{$dance}_{$judge_id}.adj";
        if (file_exists($adj_file)) {
            $lines = file($adj_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $dance_rankings = [];
            foreach ($lines as $line) {
                $line = cleanInput($line);
                $parts = explode(',', $line);
                if (count($parts) >= 2) {
                    $dance_rankings[] = [trim($parts[0]), trim($parts[1])];
                }
            }
            if (!empty($dance_rankings)) {
                $saved_rankings[$dance] = $dance_rankings;
            }
        }
    }
}

// 저장된 점수 표시용
$saved_scores = [];
if (in_array($scoring_type, ['multievent_final', 'multievent_final_only'])) {
    $saved_scores = array_keys($saved_rankings);
} elseif ($scoring_type === 'multievent_preliminary') {
    $saved_scores = array_keys($existing_selections);
}

// 댄스 이름 변환
$dance_names = getDanceNames($event_data['dances'], $dance_mapping);
?>

<!DOCTYPE html>
<html lang="<?=h($lang)?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=h($t['title'])?></title>
    
    <!-- 공통 스타일 포함 -->
    <?php include __DIR__ . '/scoring/shared/styles.php'; ?>
</head>
<body>
    <div class="scoring-container">
        <!-- 심사위원 정보 헤더 -->
        <?php renderJudgeInfoHeader($judge_id, $judge_name, $judge_country, $recall_count, $is_final, $event_no); ?>
        
        <!-- 이벤트 정보 헤더 -->
        <?php renderEventInfoHeader($event_data, $round_display, $dance_names, count($players), $hits_data, $saved_scores); ?>
        
        <!-- 메시지 컨테이너 -->
        <?php renderMessageContainer(); ?>
        
        <!-- 폼 시작 -->
        <?php renderFormStart($comp_id, $event_no, $is_final, $recall_count); ?>
        
        <!-- 심사 시스템 로드 -->
        <?php 
        echo "<!-- DEBUG: scoring_type = " . $scoring_type . " -->";
        echo "<!-- DEBUG: event_no = " . $event_no . " -->";
        echo "<!-- DEBUG: is_final = " . ($is_final ? 'TRUE' : 'FALSE') . " -->";
        
        $scoring_file = __DIR__ . "/scoring/types/{$scoring_type}.php";
        echo "<!-- DEBUG: scoring_file = " . $scoring_file . " -->";
        echo "<!-- DEBUG: file_exists = " . (file_exists($scoring_file) ? 'TRUE' : 'FALSE') . " -->";
        
        if (file_exists($scoring_file)) {
            include $scoring_file;
        } else {
            echo '<div class="error">지원하지 않는 심사 방식입니다: ' . h($scoring_type) . '</div>';
        }
        ?>
        
        <!-- 폼 종료 -->
        <?php renderFormEnd(); ?>
        
        <!-- 하단 네비게이션 -->
        <?php renderBottomNavigation($comp_id, $lang); ?>
        
        <!-- 푸터 -->
        <?php renderFooter(); ?>
    </div>
    
    <!-- 공통 JavaScript 포함 -->
    <?php renderCommonJavaScript($comp_id, $lang); ?>
</body>
</html>
