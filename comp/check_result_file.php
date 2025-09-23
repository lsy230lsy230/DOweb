<?php
/**
 * 결과 파일 존재 여부 확인 API
 */

$eventId = isset($_GET['event']) ? $_GET['event'] : '';

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => '이벤트 ID가 필요합니다.']);
    exit;
}

// combined_result 파일 경로 설정
$resultsPath = __DIR__ . "/../results/results";
$combinedFile = $resultsPath . "/combined_result_" . $eventId . ".html";

// 파일 존재 여부 확인
$exists = file_exists($combinedFile) && is_readable($combinedFile);

// 추가 정보
$fileInfo = [];
if ($exists) {
    $fileInfo = [
        'path' => $combinedFile,
        'size' => filesize($combinedFile),
        'modified' => date('Y-m-d H:i:s', filemtime($combinedFile))
    ];
}

echo json_encode([
    'success' => true,
    'event_id' => $eventId,
    'exists' => $exists,
    'file_info' => $fileInfo,
    'message' => $exists ? '결과 파일이 존재합니다.' : '결과 파일이 없습니다.'
]);
?>
