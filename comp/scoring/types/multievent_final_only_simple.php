<?php
/**
 * 간단한 멀티이벤트 결승전(준결승 없음) 심사 테스트
 */

echo "<!-- DEBUG: multievent_final_only_simple.php loaded successfully -->";
echo "<div style='background: yellow; padding: 10px; margin: 10px; border: 2px solid red;'>";
echo "<h3>DEBUG: multievent_final_only_simple.php is working!</h3>";
echo "<p>Event: " . htmlspecialchars($event_data['name'] ?? 'No event name') . "</p>";
echo "<p>Players: " . count($players) . "</p>";
echo "<p>Dances: " . implode(', ', $event_data['dances'] ?? []) . "</p>";
echo "<p>Scoring Type: " . htmlspecialchars($scoring_type ?? 'No scoring type') . "</p>";
echo "</div>";

// 간단한 멀티이벤트 결승전 심사 시스템 UI
echo "<div style='background: #f0f0f0; padding: 20px; margin: 10px; border: 2px solid #333;'>";
echo "<h2>멀티이벤트 결승전 심사 시스템 (간단 버전)</h2>";
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

// 멀티이벤트 결승전 특별 안내
echo "<div style='background: #fff3e0; padding: 15px; margin: 10px; border: 2px solid #FF9800; border-radius: 5px;'>";
echo "<h3>🏆 멀티이벤트 결승전 안내</h3>";
echo "<p>• 준결승 없이 바로 결승전으로 진행됩니다</p>";
echo "<p>• 모든 댄스를 한 번에 평가합니다</p>";
echo "<p>• 종합적인 실력과 일관성을 평가합니다</p>";
echo "<p>• 최종 순위를 직접 결정합니다</p>";
echo "</div>";

echo "</div>";
?>






