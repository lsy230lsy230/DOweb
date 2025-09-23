<?php
/**
 * 프리스타일 심사 시스템
 */

echo "<!-- DEBUG: freestyle.php loaded successfully -->";
echo "<div style='background: yellow; padding: 10px; margin: 10px; border: 2px solid red;'>";
echo "<h3>DEBUG: freestyle.php is working!</h3>";
echo "<p>Event: " . htmlspecialchars($event_data['name'] ?? 'No event name') . "</p>";
echo "<p>Players: " . count($players) . "</p>";
echo "<p>Dances: " . implode(', ', $event_data['dances'] ?? []) . "</p>";
echo "<p>Scoring Type: " . htmlspecialchars($scoring_type ?? 'No scoring type') . "</p>";
echo "</div>";

// 간단한 프리스타일 심사 시스템 UI
echo "<div style='background: #f0f0f0; padding: 20px; margin: 10px; border: 2px solid #333;'>";
echo "<h2>🎭 프리스타일 심사 시스템</h2>";
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

// 프리스타일 특별 안내
echo "<div style='background: #e8f4fd; padding: 15px; margin: 10px; border: 2px solid #2196F3; border-radius: 5px;'>";
echo "<h3>🎭 프리스타일 심사 안내</h3>";
echo "<p>• 프리스타일은 창의적이고 자유로운 표현을 평가합니다</p>";
echo "<p>• 음악에 맞춰 즉흥적으로 춤을 추는 능력을 평가합니다</p>";
echo "<p>• 기술적 완성도와 예술적 표현력을 종합적으로 평가합니다</p>";
echo "</div>";

echo "</div>";
?>