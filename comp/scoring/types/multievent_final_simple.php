<?php
/**
 * 간단한 멀티이벤트 결승전 테스트
 */

echo "<!-- DEBUG: multievent_final_simple.php loaded successfully -->";
echo "<div style='background: yellow; padding: 10px; margin: 10px; border: 2px solid red;'>";
echo "<h3>DEBUG: multievent_final_simple.php is working!</h3>";
echo "<p>Event: " . htmlspecialchars($event_data['name'] ?? 'No event name') . "</p>";
echo "<p>Players: " . count($players) . "</p>";
echo "<p>Dances: " . implode(', ', $event_data['dances'] ?? []) . "</p>";
echo "<p>Scoring Type: " . htmlspecialchars($scoring_type ?? 'No scoring type') . "</p>";
echo "</div>";

// 간단한 심사 시스템 UI
echo "<div style='background: #f0f0f0; padding: 20px; margin: 10px; border: 2px solid #333;'>";
echo "<h2>심사 시스템 (간단 버전)</h2>";
echo "<p>이벤트: " . htmlspecialchars($event_data['name'] ?? 'Unknown') . "</p>";
echo "<p>선수 수: " . count($players) . "명</p>";
echo "<p>댄스: " . implode(', ', $event_data['dances'] ?? []) . "</p>";

// 선수 목록 표시
echo "<h3>선수 목록:</h3>";
echo "<ul>";
foreach ($players as $player) {
    echo "<li>선수 번호: " . htmlspecialchars($player) . "</li>";
}
echo "</ul>";

echo "</div>";
?>






