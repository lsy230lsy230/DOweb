<?php
/**
 * 멀티이벤트 결승전 심사 시스템
 * 터치 기반 순위 시스템
 */

// 공통 파일들은 이미 메인에서 포함됨
// require_once __DIR__ . '/../shared/functions.php';
// require_once __DIR__ . '/../shared/styles.php';
// require_once __DIR__ . '/../shared/components.php';

// 변수들이 메인에서 전달되어야 함
// $comp_id, $event_no, $event_data, $players, $dance_mapping, $saved_rankings, $is_final

// 멀티 이벤트 개수 감지
$event_count = count($event_data['dances'] ?? []);
$layout_class = '';

// 이벤트 개수에 따른 레이아웃 클래스 결정
switch($event_count) {
    case 2:
        $layout_class = 'multievent-2';
        break;
    case 3:
        $layout_class = 'multievent-3';
        break;
    case 4:
        $layout_class = 'multievent-4';
        break;
    case 5:
        $layout_class = 'multievent-5';
        break;
    case 6:
        $layout_class = 'multievent-6';
        break;
    default:
        $layout_class = 'multievent-default';
        break;
}

echo "<!-- DEBUG: multievent_final.php loaded successfully -->";
echo "<div style='background: yellow; padding: 10px; margin: 10px; border: 2px solid red;'>";
echo "<h3>DEBUG: multievent_final.php is working!</h3>";
echo "<p>Event: " . htmlspecialchars($event_data['name'] ?? 'No event name') . "</p>";
echo "<p>Players: " . count($players) . "</p>";
echo "<p>Dances: " . implode(', ', $event_data['dances'] ?? []) . "</p>";
echo "<p>Event Count: " . $event_count . " (Layout: " . $layout_class . ")</p>";
echo "</div>";
?>

<!-- 멀티이벤트 결승전 전용 스타일 -->
<style>
    /* 멀티 이벤트 레이아웃 컨테이너 */
    .multievent-container {
        display: grid;
        gap: 10px;
        margin-top: 10px;
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 15px;
        min-height: 400px;
    }

    /* 2개 이벤트: 상하 2분할 */
    .multievent-2 .multievent-container {
        grid-template-rows: 1fr 1fr;
        grid-template-columns: 1fr;
    }

    /* 3개 이벤트: 상단 2개 + 하단 1개 */
    .multievent-3 .multievent-container {
        grid-template-rows: 1fr 1fr;
        grid-template-columns: 1fr 1fr;
    }
    .multievent-3 .multievent-container .event-3 {
        grid-column: 1 / -1;
    }

    /* 4개 이벤트: 2x2 그리드 */
    .multievent-4 .multievent-container {
        grid-template-rows: 1fr 1fr;
        grid-template-columns: 1fr 1fr;
    }

    /* 5개 이벤트: 2x2 + 하단 1개 */
    .multievent-5 .multievent-container {
        grid-template-rows: 1fr 1fr 1fr;
        grid-template-columns: 1fr 1fr;
    }
    .multievent-5 .multievent-container .event-5 {
        grid-column: 1 / -1;
    }

    /* 6개 이벤트: 3x2 그리드 */
    .multievent-6 .multievent-container {
        grid-template-rows: 1fr 1fr 1fr;
        grid-template-columns: 1fr 1fr;
    }

    /* 개별 이벤트 블록 */
    .event-block {
        background: #f8f9fa;
        border: 2px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 150px;
        position: relative;
    }

    .event-block h3 {
        margin: 0 0 10px 0;
        color: #333;
        font-size: 18px;
        font-weight: bold;
        text-align: center;
    }

    .event-block .event-number {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #007bff;
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: bold;
    }

    /* Final Round Styles */
    .final-scoring-container {
        display: flex;
        gap: 15px;
        margin-top: 10px;
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 15px;
    }
    
    .players-column, .ranking-column {
        flex: 1;
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 10px;
    }
    
    .players-column h3, .ranking-column h3 {
        margin: 0 0 15px 0;
        text-align: center;
        color: #333;
        font-size: 16px;
        font-weight: bold;
        padding: 8px;
        background: #e9ecef;
        border-radius: 4px;
    }
    
    .player-item, .ranking-slot {
        background: #fff;
        border: 2px solid #ddd;
        border-radius: 6px;
        padding: 8px 12px;
        margin: 5px 0;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        font-weight: bold;
        font-size: 16px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .player-item:hover {
        border-color: #4CAF50;
        background: #f1f8e9;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .player-item.selected {
        border-color: #4CAF50;
        background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
        transform: scale(1.05);
        box-shadow: 0 6px 12px rgba(76, 175, 80, 0.3);
    }
    
    .ranking-slot {
        background: #f8f9fa;
        border: 2px dashed #ccc;
        color: #666;
        font-style: italic;
    }
    
    .ranking-slot.assigned {
        background: #fff;
        border: 2px solid #4CAF50;
        color: #333;
        font-style: normal;
    }
    
    .ranking-slot:hover {
        border-color: #4CAF50;
        background: #f1f8e9;
    }
    
    .ranking-slot.dragging {
        opacity: 0.5;
        transform: rotate(5deg);
    }
    
    .ranking-slot.drag-over {
        border-color: #ff9800;
        background: #fff3e0;
    }
    
    /* Rank labels */
    .ranking-slot::before {
        content: attr(data-rank) "위";
        position: absolute;
        left: -40px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        color: #666;
        font-weight: bold;
        white-space: nowrap;
    }
    
    .ranking-column {
        position: relative;
    }
    
    .ranking-column::before {
        content: "";
        position: absolute;
        left: -20px;
        top: 0;
        bottom: 0;
        width: 1px;
        background: #ddd;
    }
    
    /* Dance Progress Header */
    .dance-progress-header {
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 10px;
        margin-bottom: 15px;
        text-align: center;
    }
    
    .dance-progress {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .dance-step {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        transition: all 0.3s ease;
    }
    
    .dance-step.completed {
        background: #4CAF50;
        color: #fff;
    }
    
    .dance-step.current {
        background: #2196F3;
        color: #fff;
        transform: scale(1.1);
    }
    
    .dance-step.pending {
        background: #e0e0e0;
        color: #666;
    }
    
    .current-dance-display {
        font-size: 18px;
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .final-scoring-container {
            flex-direction: row;
            gap: 10px;
            padding: 10px;
        }
        
        .players-column, .ranking-column {
            padding: 8px;
        }
        
        .player-item, .ranking-slot {
            height: 45px;
            font-size: 14px;
            padding: 6px 8px;
            margin: 3px 0;
        }
        
        .ranking-slot::before {
            left: -35px;
            font-size: 10px;
        }
        
        .ranking-column::before {
            left: -15px;
        }
        
        .dance-step {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .current-dance-display {
            font-size: 16px;
        }
    }
    
    /* Tablet optimization */
    @media (min-width: 769px) and (max-width: 1024px) {
        .final-scoring-container {
            padding: 12px;
            gap: 12px;
        }
        
        .players-column, .ranking-column {
            padding: 8px;
        }
        
        .player-item, .ranking-slot {
            height: 50px;
            font-size: 15px;
            padding: 6px 10px;
            margin: 4px 0;
        }
        
        .ranking-slot::before {
            left: -35px;
            font-size: 11px;
        }
        
        .ranking-column::before {
            left: -18px;
        }
        
        .dance-step {
            padding: 7px 14px;
            font-size: 13px;
        }
        
        .current-dance-display {
            font-size: 17px;
        }
    }
</style>

<!-- Dance Progress Header -->
<div class="dance-progress-header">
    <div class="dance-progress" id="danceProgress">
        <!-- Dance steps will be populated by JavaScript -->
    </div>
    <div class="current-dance-display" id="currentDanceDisplay">
        현재 종목: <?= h($event_data['dances'][0] ?? '') ?>
    </div>
</div>

<!-- Final Round Touch-based Ranking System -->
<div class="final-scoring-container">
    <div class="players-column">
        <h3>선수 목록</h3>
        <div id="playersList">
            <?php foreach ($players as $player_no): ?>
                <div class="player-item" 
                     data-player="<?=h($player_no)?>" 
                     onclick="selectPlayer(<?=h($player_no)?>)">
                    <?=h($player_no)?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="ranking-column">
        <h3>순위</h3>
        <div id="rankingList">
            <?php for ($i = 1; $i <= count($players); $i++): ?>
                <div class="ranking-slot" 
                     data-rank="<?=$i?>" 
                     onclick="assignRank(<?=$i?>)"
                     onmousedown="handleMouseDown(event)"
                     onmouseup="handleMouseUp(event)"
                     ondblclick="swapWithNext(this)"
                     draggable="true"
                     ondragstart="dragStart(event)"
                     ondragover="dragOver(event)"
                     ondrop="drop(event)"
                     ondragend="dragEnd(event)">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Final Round Navigation -->
<div style="text-align: center; margin-top: 20px;">
    <button type="button" id="nextFinalDance" class="btn btn-primary" style="display: none;">
        다음 댄스
    </button>
    <button type="button" id="submitFinalDance" class="btn btn-primary">
        <?= h($event_data['dances'][0] ?? '') ?> 순위 제출
    </button>
    <button type="button" id="clearFinalScores" class="btn btn-secondary">
        초기화
    </button>
</div>

<!-- 멀티이벤트 결승전 전용 JavaScript -->
<script>
// Final Round Variables
let selectedPlayer = null;
let playerRankings = {};
let currentDanceIndex = 0;
let totalDances = <?= count($event_data['dances']) ?>;
let finalDanceNames = <?= json_encode($event_data['dances']) ?>;
let finalCompletedDances = new Set();
let savedRankings = <?= json_encode($saved_rankings) ?>;
let currentJudgeId = '<?= h($_SESSION['scoring_judge_id'] ?? '') ?>';
let isDragging = false;
let dragStartTime = 0;

document.addEventListener('DOMContentLoaded', function() {
    initializeFinalDanceSystem();
    initializeTouchSystem();
    setupFinalDanceNavigation();
});

function initializeFinalDanceSystem() {
    // Set total dances
    totalDances = finalDanceNames.length;
    
    // Populate dance progress
    updateFinalDanceProgress();
    
    // Show first dance
    showFinalDance(0);
}

function updateFinalDanceProgress() {
    const progressContainer = document.getElementById('danceProgress');
    if (!progressContainer) return;
    
    progressContainer.innerHTML = '';
    
    finalDanceNames.forEach((dance, index) => {
        const step = document.createElement('div');
        step.className = 'dance-step';
        step.textContent = dance;
        
        if (index < currentDanceIndex) {
            step.classList.add('completed');
        } else if (index === currentDanceIndex) {
            step.classList.add('current');
        } else {
            step.classList.add('pending');
        }
        
        progressContainer.appendChild(step);
    });
}

function showFinalDance(danceIndex) {
    if (danceIndex < 0 || danceIndex >= totalDances) return;
    
    currentDanceIndex = danceIndex;
    const currentDance = finalDanceNames[danceIndex];
    
    // Update progress
    updateFinalDanceProgress();
    
    // Update current dance display
    updateCurrentDanceDisplay();
    
    // Clear current scores
    clearFinalScores();
    
    // Update navigation after DOM is ready
    setTimeout(() => {
        updateFinalDanceNav();
    }, 50);
    
    // Restore saved rankings for current dance (after clearing)
    setTimeout(() => {
        restoreSavedRankings();
    }, 100);
}

function updateCurrentDanceDisplay() {
    const display = document.getElementById('currentDanceDisplay');
    if (display) {
        const currentDance = finalDanceNames[currentDanceIndex];
        display.textContent = `현재 종목: ${currentDance}`;
    }
}

function setupFinalDanceNavigation() {
    // Submit button
    const submitBtn = document.getElementById('submitFinalDance');
    if (submitBtn) {
        submitBtn.addEventListener('click', submitFinalDance);
    }
    
    // Next dance button
    const nextBtn = document.getElementById('nextFinalDance');
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentDanceIndex < totalDances - 1) {
                showFinalDance(currentDanceIndex + 1);
            }
        });
    }
}

function submitFinalDance() {
    const currentDance = finalDanceNames[currentDanceIndex];
    const rankings = Object.entries(playerRankings).map(([player, rank]) => [player, rank]);
    
    if (rankings.length === 0) {
        alert('순위를 지정해주세요.');
        return;
    }
    
    // Prepare data
    const adjudicator_marks = {};
    adjudicator_marks[currentJudgeId] = rankings;
    
    const formData = new FormData();
    formData.append('comp_id', '<?= h($comp_id) ?>');
    formData.append('event_no', '<?= h($event_no) ?>');
    formData.append('type', 'final');
    formData.append('is_final', '1');
    formData.append('dance', currentDance);
    formData.append('adjudicator_marks', JSON.stringify(adjudicator_marks));
    
    console.log('Sending adjudicator_marks:', adjudicator_marks);
    
    // Show loading state
    const submitBtn = document.getElementById('submitFinalDance');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '전송 중...';
    submitBtn.disabled = true;
    
    fetch('scoring/save_scores.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Mark this dance as completed
            finalCompletedDances.add(currentDance);
            
            // Update saved rankings
            savedRankings[currentDance] = rankings;
            
            // Show success message
            alert(`${currentDance} 댄스 순위가 저장되었습니다.`);
            
            // Update navigation
            updateFinalDanceNav();
            
            // Check if all dances are completed
            if (finalCompletedDances.size === totalDances) {
                alert('모든 댄스의 순위가 완료되었습니다!');
                // Redirect to dashboard or show completion message
                setTimeout(() => {
                    const compId = '<?= h($comp_id) ?>';
                    const lang = '<?= h($lang) ?>';
                    window.location.href = `scoring_dashboard.php?comp_id=${compId}&lang=${lang}`;
                }, 2000);
            } else {
                // Move to next dance
                if (currentDanceIndex < totalDances - 1) {
                    showFinalDance(currentDanceIndex + 1);
                }
            }
        } else {
            alert('저장 실패: ' + (result.error || '알 수 없는 오류'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('전송 중 오류가 발생했습니다: ' + error);
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function updateFinalDanceNav() {
    const submitBtn = document.getElementById('submitFinalDance');
    const nextBtn = document.getElementById('nextFinalDance');
    
    if (submitBtn) {
        const currentDance = finalDanceNames[currentDanceIndex];
        if (currentDanceIndex === totalDances - 1) {
            submitBtn.textContent = '최종 저장';
        } else {
            submitBtn.textContent = `${currentDance} 순위 제출`;
        }
    }
    
    if (nextBtn) {
        nextBtn.style.display = currentDanceIndex < totalDances - 1 ? 'inline-block' : 'none';
    }
}

function initializeTouchSystem() {
    // Clear button
    const clearBtn = document.getElementById('clearFinalScores');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearFinalScoresWithConfirm);
    }
}

function clearFinalScores() {
    // Clear player selections
    selectedPlayer = null;
    playerRankings = {};
    
    // Reset UI
    document.querySelectorAll('.player-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.classList.remove('assigned');
        slot.textContent = '';
        slot.setAttribute('data-player', '');
    });
}

function clearFinalScoresWithConfirm() {
    if (confirm('현재 댄스의 모든 순위를 초기화하시겠습니까?')) {
        clearFinalScores();
    }
}

function selectPlayer(playerNo) {
    if (isDragging) return;
    
    selectedPlayer = playerNo;
    
    // Update UI
    document.querySelectorAll('.player-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    const playerItem = document.querySelector(`[data-player="${playerNo}"]`);
    if (playerItem) {
        playerItem.classList.add('selected');
    }
}

function assignRank(rank) {
    if (isDragging) return;
    
    if (!selectedPlayer) {
        alert('먼저 선수를 선택해주세요.');
        return;
    }
    
    const rankSlot = document.querySelector(`[data-rank="${rank}"]`);
    if (!rankSlot) return;
    
    // If slot is already occupied, move that player back to players list
    if (rankSlot.classList.contains('assigned')) {
        const currentPlayer = rankSlot.getAttribute('data-player');
        if (currentPlayer) {
            // Remove from rankings
            delete playerRankings[currentPlayer];
            
            // Update UI
            const playerItem = document.querySelector(`[data-player="${currentPlayer}"]`);
            if (playerItem) {
                playerItem.classList.remove('selected');
            }
        }
    }
    
    // Assign new player to rank
    playerRankings[selectedPlayer] = rank;
    
    // Update UI
    rankSlot.classList.add('assigned');
    rankSlot.textContent = selectedPlayer;
    rankSlot.setAttribute('data-player', selectedPlayer);
    
    // Remove player from players list
    const playerItem = document.querySelector(`[data-player="${selectedPlayer}"]`);
    if (playerItem) {
        playerItem.style.display = 'none';
    }
    
    // Clear selection
    selectedPlayer = null;
}

function cancelRank(rank) {
    const rankSlot = document.querySelector(`[data-rank="${rank}"]`);
    if (!rankSlot || !rankSlot.classList.contains('assigned')) return;
    
    const playerNo = rankSlot.getAttribute('data-player');
    if (playerNo) {
        // Remove from rankings
        delete playerRankings[playerNo];
        
        // Update UI
        rankSlot.classList.remove('assigned');
        rankSlot.textContent = '';
        rankSlot.setAttribute('data-player', '');
        
        // Show player in players list
        const playerItem = document.querySelector(`[data-player="${playerNo}"]`);
        if (playerItem) {
            playerItem.style.display = 'flex';
        }
    }
}

// Drag and Drop Functions
function handleMouseDown(event) {
    dragStartTime = Date.now();
    isDragging = false;
}

function handleMouseUp(event) {
    const dragDuration = Date.now() - dragStartTime;
    if (dragDuration < 200) { // Short click, not drag
        isDragging = false;
    }
}

function dragStart(event) {
    isDragging = true;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/html', event.target.outerHTML);
    event.target.classList.add('dragging');
}

function dragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    event.target.classList.add('drag-over');
}

function drop(event) {
    event.preventDefault();
    event.target.classList.remove('drag-over');
    
    if (!isDragging) return;
    
    const draggedElement = event.target;
    const draggedPlayer = draggedElement.getAttribute('data-player');
    
    if (draggedPlayer) {
        // This is a ranking slot with a player
        const targetRank = draggedElement.getAttribute('data-rank');
        const sourceRank = Object.keys(playerRankings).find(player => playerRankings[player] == targetRank);
        
        if (sourceRank) {
            // Swap the players
            const tempRank = playerRankings[sourceRank];
            playerRankings[sourceRank] = playerRankings[draggedPlayer];
            playerRankings[draggedPlayer] = tempRank;
            
            // Update UI
            updateRankingDisplay();
        }
    }
}

function dragEnd(event) {
    event.target.classList.remove('dragging');
    isDragging = false;
}

function updateRankingDisplay() {
    // Clear all ranking slots
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.classList.remove('assigned');
        slot.textContent = '';
        slot.setAttribute('data-player', '');
    });
    
    // Update with current rankings
    Object.entries(playerRankings).forEach(([player, rank]) => {
        const rankSlot = document.querySelector(`[data-rank="${rank}"]`);
        if (rankSlot) {
            rankSlot.classList.add('assigned');
            rankSlot.textContent = player;
            rankSlot.setAttribute('data-player', player);
        }
    });
}

// Double-click swap function
function swapWithNext(element) {
    const currentRank = parseInt(element.getAttribute('data-rank'));
    const nextRank = currentRank + 1;
    const nextElement = document.querySelector(`[data-rank="${nextRank}"]`);
    
    if (!nextElement) return;
    
    const currentPlayer = element.getAttribute('data-player');
    const nextPlayer = nextElement.getAttribute('data-player');
    
    if (currentPlayer && nextPlayer) {
        // Swap players
        const tempRank = playerRankings[currentPlayer];
        playerRankings[currentPlayer] = playerRankings[nextPlayer];
        playerRankings[nextPlayer] = tempRank;
        
        // Update UI
        updateRankingDisplay();
    }
}

function restoreSavedRankings() {
    const currentDance = finalDanceNames[currentDanceIndex];
    
    console.log('=== RESTORE SAVED RANKINGS DEBUG ===');
    console.log('Current judge ID:', currentJudgeId);
    console.log('Current dance:', currentDance);
    console.log('Current dance index:', currentDanceIndex);
    console.log('Available saved rankings:', savedRankings);
    console.log('Final dance names:', finalDanceNames);
    
    if (savedRankings[currentDance]) {
        const rankings = savedRankings[currentDance];
        console.log('Found saved rankings for dance:', currentDance, rankings);
        
        // Restore rankings
        rankings.forEach(([player, rank]) => {
            playerRankings[player] = rank;
            
            // Update UI
            const rankSlot = document.querySelector(`[data-rank="${rank}"]`);
            if (rankSlot) {
                rankSlot.classList.add('assigned');
                rankSlot.textContent = player;
                rankSlot.setAttribute('data-player', player);
            }
            
            // Hide player from players list
            const playerItem = document.querySelector(`[data-player="${player}"]`);
            if (playerItem) {
                playerItem.style.display = 'none';
            }
        });
        
        console.log('Restored rankings:', playerRankings);
    } else {
        console.log('No saved data for dance:', currentDance);
    }
}
</script>

<!-- 멀티 이벤트 동적 레이아웃 HTML -->
<div class="<?= $layout_class ?>">
    <div class="multievent-container">
        <?php
        $dances = $event_data['dances'] ?? [];
        foreach($dances as $index => $dance) {
            $event_number = $index + 1;
            $event_class = ($event_count == 3 && $event_number == 3) ? 'event-3' : 
                          (($event_count == 5 && $event_number == 5) ? 'event-5' : '');
            ?>
            <div class="event-block <?= $event_class ?>" data-event="<?= $event_number ?>">
                <div class="event-number">#1-<?= $event_number ?></div>
                <h3><?= htmlspecialchars($dance) ?></h3>
                <div class="event-content">
                    <p>이벤트 <?= $event_number ?> 내용</p>
                    <div class="scoring-area">
                        <!-- 여기에 각 이벤트별 채점 UI가 들어갑니다 -->
                        <div class="players-list">
                            <?php foreach($players as $player): ?>
                                <div class="player-item" data-player="<?= htmlspecialchars($player) ?>">
                                    <?= htmlspecialchars($player) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</div>
