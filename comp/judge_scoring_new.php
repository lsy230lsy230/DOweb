<?php
/**
 * 심사위원 채점 시스템 - 메인 라우터
 * 이벤트 타입에 따라 적절한 심사 시스템을 로드
 */

session_start();

// 공통 함수들 포함
require_once __DIR__ . '/scoring/shared/functions.php';

// 기본 설정
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$lang = $_GET['lang'] ?? 'ko';

// 세션 검증
if (!validateJudgeSession($comp_id)) {
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

// 심사위원 정보
$judge_id = $_SESSION['scoring_judge_id'] ?? '';
$judge_name = $_SESSION['scoring_judge_name'] ?? '';
$judge_country = $_SESSION['scoring_judge_country'] ?? '';

// 이벤트별 데이터 준비
$recall_count = intval($event_data['recall_count'] ?? 0);
$round_display = $event_data['round_type'] ?? '';

// 히트 데이터 로드 (예선/준결승용)
$hits_data = null;
if (!$is_final && $event_no !== '8') {
    $data_dir = __DIR__ . "/data/$comp_id";
    $hits_file = "$data_dir/players_hits_$event_no.json";
    if (file_exists($hits_file)) {
        $hits_data = json_decode(file_get_contents($hits_file), true);
    }
}

// 기존 선택된 선수들 로드 (예선/준결승용)
$existing_selections = [];
if (!$is_final && $event_no !== '8') {
    $existing_selections = loadExistingSelections($comp_id, $event_no, $judge_id, $event_data['dances']);
}

// 저장된 순위 로드 (결승전용)
$saved_rankings = [];
if ($is_final || $event_no === '8') {
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
if ($is_final || $event_no === '8') {
    $saved_scores = array_keys($saved_rankings);
} else {
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
        <?php if ($is_final || $event_no === '8'): ?>
            <!-- 결승전 시스템 -->
            <?php include __DIR__ . '/scoring/types/multievent_final.php'; ?>
        <?php else: ?>
            <!-- 예선/준결승 시스템 -->
            <?php include __DIR__ . '/scoring/types/multievent_preliminary.php'; ?>
        <?php endif; ?>
        
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






