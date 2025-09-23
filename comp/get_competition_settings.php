<?php
/**
 * 대회 설정 조회 API
 */

header('Content-Type: application/json');

$settingsFile = __DIR__ . '/uploads/competition_settings.json';

// 기본 설정
$defaultSettings = [
    'competition_title' => '2025 경기도지사배 전국장애인댄스스포츠선수권대회',
    'competition_subtitle' => 'DanceOffice 댄스스포츠 관리 시스템'
];

// 설정 로드
$settings = $defaultSettings;
if (file_exists($settingsFile)) {
    $loadedSettings = json_decode(file_get_contents($settingsFile), true);
    if ($loadedSettings) {
        $settings = array_merge($defaultSettings, $loadedSettings);
    }
}

echo json_encode([
    'success' => true,
    'settings' => $settings
]);
?>




