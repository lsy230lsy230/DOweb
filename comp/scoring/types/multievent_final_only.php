<?php
/**
 * 멀티이벤트 결승전 시스템 (준결승 없음)
 * 바로 결승전으로 진행되는 멀티이벤트
 */

// 공통 파일들 포함
require_once __DIR__ . '/../shared/functions.php';
require_once __DIR__ . '/../shared/styles.php';
require_once __DIR__ . '/../shared/components.php';
?>

<!-- 멀티이벤트 결승전 전용 스타일 -->
<style>
    .multievent-final-container {
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 20px;
        margin-top: 10px;
    }
    
    .multievent-header {
        text-align: center;
        margin-bottom: 20px;
        padding: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 8px;
    }
    
    .multievent-header h2 {
        margin: 0 0 10px 0;
        font-size: 24px;
    }
    
    .multievent-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 14px;
    }
    
    .dance-progress-header {
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 20px;
        text-align: center;
    }
    
    .dance-progress {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .dance-step {
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 16px;
        font-weight: bold;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .dance-step.completed {
        background: #4CAF50;
        color: #fff;
    }
    
    .dance-step.current {
        background: #2196F3;
        color: #fff;
        transform: scale(1.1);
        box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
    }
    
    .dance-step.pending {
        background: #e0e0e0;
        color: #666;
    }
    
    .current-dance-display {
        font-size: 20px;
        font-weight: bold;
        color: #333;
        margin-bottom: 10px;
    }
    
    .scoring-area {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }
    
    .players-column, .ranking-column {
        flex: 1;
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
    }
    
    .players-column h3, .ranking-column h3 {
        margin: 0 0 15px 0;
        text-align: center;
        color: #333;
        font-size: 18px;
        font-weight: bold;
        padding: 10px;
        background: #e9ecef;
        border-radius: 6px;
    }
    
    .player-item, .ranking-slot {
        background: #fff;
        border: 2px solid #ddd;
        border-radius: 8px;
        padding: 12px;
        margin: 8px 0;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        font-weight: bold;
        font-size: 18px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .player-item:hover {
        border-color: #667eea;
        background: #f1f8e9;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .player-item.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
        transform: scale(1.05);
        box-shadow: 0 6px 12px rgba(102, 126, 234, 0.3);
    }
    
    .ranking-slot {
        background: #f8f9fa;
        border: 2px dashed #ccc;
        color: #666;
        font-style: italic;
    }
    
    .ranking-slot.assigned {
        background: #fff;
        border: 2px solid #667eea;
        color: #333;
        font-style: normal;
    }
    
    .ranking-slot:hover {
        border-color: #667eea;
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
    
    /* 순위 라벨 */
    .ranking-slot::before {
        content: attr(data-rank) "위";
        position: absolute;
        left: -50px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 14px;
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
        left: -25px;
        top: 0;
        bottom: 0;
        width: 1px;
        background: #ddd;
    }
    
    .navigation-buttons {
        text-align: center;
        margin-top: 20px;
        display: flex;
        justify-content: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .nav-btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        transition: all 0.3s ease;
    }
    
    .nav-btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .nav-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .nav-btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .nav-btn-secondary:hover {
        background: #5a6268;
    }
    
    .nav-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    /* 모바일 반응형 */
    @media (max-width: 768px) {
        .scoring-area {
            flex-direction: column;
            gap: 15px;
        }
        
        .dance-progress {
            gap: 10px;
        }
        
        .dance-step {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .player-item, .ranking-slot {
            height: 50px;
            font-size: 16px;
            padding: 10px;
        }
        
        .ranking-slot::before {
            left: -40px;
            font-size: 12px;
        }
        
        .ranking-column::before {
            left: -20px;
        }
        
        .navigation-buttons {
            flex-direction: column;
            align-items: center;
        }
        
        .nav-btn {
            width: 100%;
            max-width: 200px;
        }
    }
</style>

<!-- 멀티이벤트 결승전 인터페이스 -->
<div class="multievent-final-container">
    <div class="multievent-header">
        <h2>🏆 멀티이벤트 결승전</h2>
        <p>여러 댄스의 순위를 종합하여 최종 결과를 결정합니다</p>
    </div>
    
    <!-- 댄스 진행 상황 -->
    <div class="dance-progress-header">
        <div class="dance-progress" id="danceProgress">
            <!-- 댄스 단계들이 JavaScript로 생성됩니다 -->
        </div>
        <div class="current-dance-display" id="currentDanceDisplay">
            현재 종목: <?= h($event_data['dances'][0] ?? '') ?>
        </div>
    </div>
    
    <!-- 채점 영역 -->
    <div class="scoring-area">
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
    
    <!-- 네비게이션 버튼 -->
    <div class="navigation-buttons">
        <button type="button" id="nextDance" class="nav-btn nav-btn-primary" style="display: none;">
            다음 댄스
        </button>
        <button type="button" id="submitDance" class="nav-btn nav-btn-primary">
            <?= h($event_data['dances'][0] ?? '') ?> 순위 제출
        </button>
        <button type="button" id="clearScores" class="nav-btn nav-btn-secondary">
            초기화
        </button>
    </div>
</div>

<!-- 멀티이벤트 결승전 전용 JavaScript -->
<script>
// 멀티이벤트 결승전 변수들
let selectedPlayer = null;
let playerRankings = {};
let currentDanceIndex = 0;
let totalDances = <?= count($event_data['dances']) ?>;
let danceNames = <?= json_encode($event_data['dances']) ?>;
let completedDances = new Set();
let savedRankings = <?= json_encode($saved_rankings ?? []) ?>;
let currentJudgeId = '<?= h($judge_id) ?>';
let isDragging = false;
let dragStartTime = 0;
let draggedSlot = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeMultieventFinalSystem();
    setupDragAndDrop();
});

function initializeMultieventFinalSystem() {
    // 댄스 진행 상황 업데이트
    updateDanceProgress();
    
    // 첫 번째 댄스 표시
    showDance(0);
    
    // 이벤트 리스너 설정
    setupEventListeners();
}

function updateDanceProgress() {
    const progressContainer = document.getElementById('danceProgress');
    if (!progressContainer) return;
    
    progressContainer.innerHTML = '';
    
    danceNames.forEach((dance, index) => {
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
        
        // 클릭 이벤트 추가
        step.addEventListener('click', () => {
            if (index <= currentDanceIndex || completedDances.has(dance)) {
                showDance(index);
            }
        });
        
        progressContainer.appendChild(step);
    });
}

function showDance(danceIndex) {
    if (danceIndex < 0 || danceIndex >= totalDances) return;
    
    currentDanceIndex = danceIndex;
    const currentDance = danceNames[danceIndex];
    
    // 진행 상황 업데이트
    updateDanceProgress();
    
    // 현재 댄스 표시 업데이트
    updateCurrentDanceDisplay();
    
    // 점수 초기화
    clearScores();
    
    // 저장된 순위 복원
    setTimeout(() => {
        restoreSavedRankings();
    }, 100);
    
    // 네비게이션 버튼 업데이트
    updateNavigationButtons();
}

function updateCurrentDanceDisplay() {
    const display = document.getElementById('currentDanceDisplay');
    if (display) {
        const currentDance = danceNames[currentDanceIndex];
        display.textContent = `현재 종목: ${currentDance}`;
    }
}

function setupEventListeners() {
    // 다음 댄스 버튼
    document.getElementById('nextDance').addEventListener('click', () => {
        if (currentDanceIndex < totalDances - 1) {
            showDance(currentDanceIndex + 1);
        }
    });
    
    // 댄스 제출 버튼
    document.getElementById('submitDance').addEventListener('click', submitDance);
    
    // 초기화 버튼
    document.getElementById('clearScores').addEventListener('click', clearScoresWithConfirm);
}

function setupDragAndDrop() {
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.addEventListener('dragstart', dragStart);
        slot.addEventListener('dragover', dragOver);
        slot.addEventListener('drop', drop);
        slot.addEventListener('dragend', dragEnd);
        slot.addEventListener('mousedown', handleMouseDown);
        slot.addEventListener('mouseup', handleMouseUp);
    });
}

function selectPlayer(playerNo) {
    if (isDragging) return;
    
    selectedPlayer = playerNo;
    
    // UI 업데이트
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
    
    // 기존 할당이 있다면 해제
    if (rankSlot.classList.contains('assigned')) {
        const currentPlayer = rankSlot.getAttribute('data-player');
        if (currentPlayer) {
            delete playerRankings[currentPlayer];
            const playerItem = document.querySelector(`[data-player="${currentPlayer}"]`);
            if (playerItem) {
                playerItem.classList.remove('selected');
            }
        }
    }
    
    // 새 선수 할당
    playerRankings[selectedPlayer] = rank;
    
    // UI 업데이트
    rankSlot.classList.add('assigned');
    rankSlot.textContent = selectedPlayer;
    rankSlot.setAttribute('data-player', selectedPlayer);
    
    // 선수 목록에서 숨기기
    const playerItem = document.querySelector(`[data-player="${selectedPlayer}"]`);
    if (playerItem) {
        playerItem.style.display = 'none';
    }
    
    // 선택 해제
    selectedPlayer = null;
}

function clearScores() {
    selectedPlayer = null;
    playerRankings = {};
    
    // UI 초기화
    document.querySelectorAll('.player-item').forEach(item => {
        item.classList.remove('selected');
        item.style.display = 'flex';
    });
    
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.classList.remove('assigned');
        slot.textContent = '';
        slot.setAttribute('data-player', '');
    });
}

function clearScoresWithConfirm() {
    if (confirm('현재 댄스의 모든 순위를 초기화하시겠습니까?')) {
        clearScores();
    }
}

function restoreSavedRankings() {
    const currentDance = danceNames[currentDanceIndex];
    
    if (savedRankings[currentDance]) {
        const rankings = savedRankings[currentDance];
        
        rankings.forEach(([player, rank]) => {
            playerRankings[player] = rank;
            
            const rankSlot = document.querySelector(`[data-rank="${rank}"]`);
            if (rankSlot) {
                rankSlot.classList.add('assigned');
                rankSlot.textContent = player;
                rankSlot.setAttribute('data-player', player);
            }
            
            const playerItem = document.querySelector(`[data-player="${player}"]`);
            if (playerItem) {
                playerItem.style.display = 'none';
            }
        });
    }
}

function submitDance() {
    const currentDance = danceNames[currentDanceIndex];
    const rankings = Object.entries(playerRankings).map(([player, rank]) => [player, rank]);
    
    if (rankings.length === 0) {
        alert('순위를 지정해주세요.');
        return;
    }
    
    // 데이터 준비
    const adjudicator_marks = {};
    adjudicator_marks[currentJudgeId] = rankings;
    
    const formData = new FormData();
    formData.append('comp_id', '<?= h($comp_id) ?>');
    formData.append('event_no', '<?= h($event_no) ?>');
    formData.append('type', 'final');
    formData.append('is_final', '1');
    formData.append('dance', currentDance);
    formData.append('adjudicator_marks', JSON.stringify(adjudicator_marks));
    
    // 제출 버튼 비활성화
    const submitBtn = document.getElementById('submitDance');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '전송 중...';
    submitBtn.disabled = true;
    
    // 서버로 전송
    fetch('scoring/save_scores.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // 완료된 댄스로 표시
            completedDances.add(currentDance);
            
            // 저장된 순위 업데이트
            savedRankings[currentDance] = rankings;
            
            alert(`${currentDance} 댄스 순위가 저장되었습니다.`);
            
            // 네비게이션 업데이트
            updateNavigationButtons();
            
            // 다음 댄스로 이동
            if (currentDanceIndex < totalDances - 1) {
                showDance(currentDanceIndex + 1);
            } else {
                alert('모든 댄스의 순위가 완료되었습니다!');
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

function updateNavigationButtons() {
    const nextBtn = document.getElementById('nextDance');
    const submitBtn = document.getElementById('submitDance');
    
    // 다음 댄스 버튼 표시/숨김
    if (currentDanceIndex < totalDances - 1) {
        nextBtn.style.display = 'inline-block';
    } else {
        nextBtn.style.display = 'none';
    }
    
    // 제출 버튼 텍스트 업데이트
    const currentDance = danceNames[currentDanceIndex];
    if (currentDanceIndex === totalDances - 1) {
        submitBtn.textContent = '최종 저장';
    } else {
        submitBtn.textContent = `${currentDance} 순위 제출`;
    }
}

// 드래그 앤 드롭 함수들
function handleMouseDown(event) {
    dragStartTime = Date.now();
    isDragging = false;
}

function handleMouseUp(event) {
    const dragDuration = Date.now() - dragStartTime;
    if (dragDuration < 200) {
        isDragging = false;
    }
}

function dragStart(event) {
    isDragging = true;
    draggedSlot = event.target;
    draggedSlot.classList.add('dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/html', draggedSlot.outerHTML);
}

function dragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    
    if (event.target.classList.contains('ranking-slot')) {
        event.target.classList.add('drag-over');
    }
}

function drop(event) {
    event.preventDefault();
    
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.classList.remove('drag-over');
    });
    
    if (event.target.classList.contains('ranking-slot') && draggedSlot !== event.target) {
        swapRankings(draggedSlot, event.target);
    }
}

function dragEnd(event) {
    document.querySelectorAll('.ranking-slot').forEach(slot => {
        slot.classList.remove('dragging', 'drag-over');
    });
    draggedSlot = null;
    isDragging = false;
}

function swapRankings(slot1, slot2) {
    const player1 = slot1.getAttribute('data-player');
    const player2 = slot2.getAttribute('data-player');
    const rank1 = slot1.dataset.rank;
    const rank2 = slot2.dataset.rank;
    
    // UI 업데이트
    slot1.textContent = player2 || '';
    slot2.textContent = player1 || '';
    slot1.setAttribute('data-player', player2 || '');
    slot2.setAttribute('data-player', player1 || '');
    
    // 클래스 업데이트
    slot1.classList.toggle('assigned', !!player2);
    slot2.classList.toggle('assigned', !!player1);
    
    // 데이터 업데이트
    if (player1) {
        playerRankings[player1] = rank2;
    } else {
        delete playerRankings[player1];
    }
    
    if (player2) {
        playerRankings[player2] = rank1;
    } else {
        delete playerRankings[player2];
    }
}

function swapWithNext(element) {
    const currentRank = parseInt(element.dataset.rank);
    const nextRank = currentRank + 1;
    const nextElement = document.querySelector(`[data-rank="${nextRank}"]`);
    
    if (nextElement) {
        swapRankings(element, nextElement);
    }
}
</script>






