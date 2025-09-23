<?php
// 간단한 테스트 페이지
echo "<h1>5가지 심사 방식 테스트</h1>";

$comp_id = '20250907-001';
$lang = 'ko';

$scoring_types = [
    '8' => '멀티이벤트 결승전 (기존)',
    '9' => '멀티이벤트 준결승 (기존)', 
    '10' => '프리스타일 심사',
    '11' => '포메이션 심사',
    '12' => '멀티이벤트 결승전 (준결승 없음)'
];

foreach ($scoring_types as $event_no => $name) {
    echo "<p>";
    echo "<strong>이벤트 #{$event_no}: {$name}</strong><br>";
    echo "<a href='judge_scoring_router.php?comp_id={$comp_id}&event_no={$event_no}&lang={$lang}' target='_blank'>테스트하기</a>";
    echo "</p><hr>";
}
?>






