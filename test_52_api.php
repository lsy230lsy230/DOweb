<?php
// 52번 이벤트 API 테스트
$_GET['comp_id'] = '20250913-001';
$_GET['event_no'] = '52';

echo "=== 52번 이벤트 API 테스트 시작 ===\n";

// final_aggregation_api.php 실행
ob_start();
include 'comp/final_aggregation_api.php';
$output = ob_get_clean();

echo "API 출력:\n";
echo $output . "\n";

echo "=== 테스트 완료 ===\n";
?>
