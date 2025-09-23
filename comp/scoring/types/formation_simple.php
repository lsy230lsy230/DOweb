<?php
/**
 * 간단한 포메이션 심사 테스트
 */

echo "<!-- DEBUG: formation_simple.php loaded successfully -->";
echo "<div style='background: yellow; padding: 10px; margin: 10px; border: 2px solid red;'>";
echo "<h3>DEBUG: formation_simple.php is working!</h3>";
echo "<p>Event: " . htmlspecialchars($event_data['name'] ?? 'No event name') . "</p>";
echo "<p>Players: " . count($players) . "</p>";
echo "<p>Dances: " . implode(', ', $event_data['dances'] ?? []) . "</p>";
echo "<p>Scoring Type: " . htmlspecialchars($scoring_type ?? 'No scoring type') . "</p>";
echo "</div>";

// 간단한 포메이션 심사 시스템 UI
echo "<div style='background: #f0f0f0; padding: 20px; margin: 10px; border: 2px solid #333;'>";
echo "<h2>포메이션 심사 시스템 (간단 버전)</h2>";
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

// 포메이션 특별 안내
echo "<div style='background: #e8f5e8; padding: 15px; margin: 10px; border: 2px solid #4CAF50; border-radius: 5px;'>";
echo "<h3>👥 포메이션 심사 안내</h3>";
echo "<p>• 포메이션은 팀의 일체감과 조화를 평가합니다</p>";
echo "<p>• 선들의 정확성과 동기화를 평가합니다</p>";
echo "<p>• 전체적인 시각적 효과와 예술성을 평가합니다</p>";
echo "<p>• 팀워크와 리더십을 종합적으로 평가합니다</p>";
echo "</div>";

echo "</div>";
?>






