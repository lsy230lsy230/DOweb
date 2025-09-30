<?php
// final_aggregation_api.php 직접 테스트
$url = 'https://www.danceoffice.net/comp/final_aggregation_api.php?comp_id=20250913-001&event_no=52';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 30
    ]
]);

$result = file_get_contents($url, false, $context);

echo "Response: " . $result . "\n";
echo "HTTP Response Headers: " . print_r($http_response_header, true) . "\n";
?>
