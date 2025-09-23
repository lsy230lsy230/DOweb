<?php
/**
 * 멀티이벤트 예선/준결승 심사 시스템
 * Instagram 스타일 댄스별 채점 시스템
 */

// 공통 파일들 포함
require_once __DIR__ . '/../shared/functions.php';
require_once __DIR__ . '/../shared/styles.php';
require_once __DIR__ . '/../shared/components.php';

// 변수들이 메인에서 전달되어야 함
// $comp_id, $event_no, $event_data, $players, $dance_mapping, $recall_count, $hits_data, $existing_selections, $saved_scores

// 변수 확인 및 기본값 설정
$recall_count = $recall_count ?? 0;
$hits_data = $hits_data ?? null;
$existing_selections = $existing_selections ?? [];
$dance_mapping = $dance_mapping ?? [];
?>

<!-- 멀티이벤트 예선/준결승 전용 스타일 -->
<style>
    /* Instagram Style Dance Container */
    .dance-container { 
        position: relative; 
        overflow: hidden; 
    }
    
    .dance-progress {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding: 0 20px;
    }
    
    .progress-bar {
        flex: 1;
        height: 6px;
        background: #e0e0e0;
        border-radius: 3px;
        margin-right: 15px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #4CAF50, #2e7d32);
        border-radius: 3px;
        transition: width 0.3s ease;
    }
    
    .progress-text {
        font-size: 14px;
        font-weight: bold;
        color: #666;
        white-space: nowrap;
    }
    
    .dance-slider {
        display: flex;
        transition: transform 0.3s ease;
    }
    
    .dance-page {
        min-width: 100%;
        padding: 0 20px;
        box-sizing: border-box;
    }
    
    .dance-header { 
        background:#4CAF50; 
        color:#fff; 
        padding:10px; 
        border-radius:6px; 
        margin-bottom:15px; 
        text-align:center;
    }
    
    .dance-header h3 { 
        margin:0 0 5px 0; 
        font-size:16px;
    }
    
    .dance-header p { 
        margin:0; 
        font-size:12px; 
        opacity:0.9;
    }
    
    /* Heat section styles */
    .heat-section { 
        margin-bottom:15px; 
        padding:10px; 
        background:#fff; 
        border-radius:6px; 
        border:1px solid #ddd;
    }
    
    .heat-section h4 { 
        margin:0 0 10px 0; 
        color:#333; 
        font-size:14px; 
        text-align:center;
    }
    
    /* Players grid styles - Mobile optimized */
    .players-grid { 
        display:grid; 
        grid-template-columns:repeat(auto-fill, minmax(60px, 1fr)); 
        gap:4px; 
        margin-bottom:15px;
    }
    
    .player-card { 
        background:#fff; 
        border:2px solid #ddd; 
        border-radius:6px; 
        padding:6px 2px; 
        text-align:center; 
        cursor:pointer; 
        transition:all 0.3s ease;
        position:relative;
        min-height:50px;
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }
    
    .player-card:hover { 
        border-color:#4CAF50; 
        transform:translateY(-2px); 
        box-shadow:0 4px 8px rgba(0,0,0,0.15);
    }
    
    .player-card.selected { 
        border-color:#4CAF50; 
        background:linear-gradient(135deg, #e8f5e8, #c8e6c9);
        transform:scale(1.05);
        box-shadow:0 6px 12px rgba(76, 175, 80, 0.3);
    }
    
    .player-number { 
        font-size:24px; 
        font-weight:900; 
        color:#333;
        margin:0;
        line-height:1;
    }
    
    .player-card.selected .player-number {
        color:#2e7d32;
    }
    
    .recall-checkbox { 
        display:none; /* Hide checkbox, use card click instead */
    }
    
    /* Recall status - Large and clear */
    .recall-status { 
        text-align:center; 
        padding:15px; 
        background:#f0f0f0; 
        border-radius:8px; 
        font-weight:bold; 
        color:#333;
        font-size:16px;
        margin:15px 0;
        border:2px solid #ddd;
    }
    
    .recall-status.complete { 
        background:linear-gradient(135deg, #d4edda, #c3e6cb); 
        color:#155724; 
        border:3px solid #28a745;
        animation:pulse 2s infinite;
    }
    
    .recall-status.over { 
        background:linear-gradient(135deg, #f8d7da, #f5c6cb); 
        color:#721c24; 
        border:3px solid #dc3545;
    }
    
    .recall-status .selected-count {
        font-size:24px;
        font-weight:900;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.02); }
        100% { transform: scale(1); }
    }
    
    /* Fixed Progress Bar for Mobile */
    .fixed-progress {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #ff9800, #ff5722);
        color: white;
        padding: 12px 20px;
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        transform: translateY(-100%);
        transition: transform 0.3s ease;
    }
    
    .fixed-progress.show {
        transform: translateY(0);
    }
    
    .fixed-progress.complete {
        background: linear-gradient(135deg, #4CAF50, #2e7d32);
    }
    
    .fixed-progress.over {
        background: linear-gradient(135deg, #f44336, #d32f2f);
    }
    
    /* Add top padding to body when progress bar is shown */
    body.progress-bar-visible {
        padding-top: 60px;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .players-grid { 
            grid-template-columns:repeat(auto-fill, minmax(50px, 1fr)); 
            gap:4px;
            margin-bottom:10px;
        }
        
        .player-card { 
            padding:4px 2px;
            min-height:45px;
            border-width:2px;
        }
        
        .player-number { 
            font-size:20px;
        }
        
        .dance-header h3 { 
            font-size:14px;
        }
        
        .dance-header p {
            font-size:12px;
        }
        
        .recall-status {
            padding:15px;
            font-size:16px;
            margin:15px 0;
        }
        
        .recall-status .selected-count {
            font-size:20px;
        }
    }
    
    @media (max-width: 480px) {
        .players-grid { 
            grid-template-columns:repeat(auto-fill, minmax(55px, 1fr)); 
            gap:5px;
        }
        
        .player-card { 
            padding:5px 2px;
            min-height:45px;
        }
        
        .player-number { 
            font-size:22px;
        }
        
        .recall-status {
            padding:12px;
            font-size:14px;
        }
        
        .recall-status .selected-count {
            font-size:18px;
        }
    }
</style>

<!-- 상단 헤더 -->
<div class="scoring-container">
    <div class="event-info" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding:15px; background:#f8f9fa; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
        <div style="flex:1;">
            <h1 style="margin:0 0 5px 0; color:#333; font-size:20px; font-weight:bold;"><?=h($event_data['name'])?></h1>
            <div style="display:flex; gap:20px; font-size:14px; color:#666;">
                <span><strong>이벤트:</strong> #<?=h($event_data['no'])?></span>
                <span><strong>라운드:</strong> <?=h($event_data['round'])?></span>
                <span><strong>종목:</strong> <?=h(implode(', ', array_map(function($d) use ($dance_mapping) { return getDanceName($d, $dance_mapping); }, $event_data['dances'])))?></span>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:16px; font-weight:bold; color:#003399; margin-bottom:5px;">
                심사위원: <?=h($_SESSION['scoring_judge_name'] ?? 'Unknown')?>
            </div>
            <div style="font-size:12px; color:#666;">
                ID: <?=h($_SESSION['scoring_judge_id'] ?? 'Unknown')?>
            </div>
        </div>
    </div>
    
    <!-- 고정 진행률 바 -->
    <?php renderFixedProgressBar($recall_count, $is_final, $event_no); ?>

    <!-- Instagram Style Dance by Dance System -->
<div class="dance-container">
    <div class="dance-progress">
        <div class="progress-bar">
            <div class="progress-fill" style="width: 0%"></div>
        </div>
        <div class="progress-text">
            <span class="current-dance">1</span> / <?= count($event_data['dances']) ?>
        </div>
    </div>
    
    <div class="dance-slider" id="danceSlider">
        <?php foreach ($event_data['dances'] as $dance_index => $dance): ?>
            <?php 
            $dance_display = isset($dance_mapping[$dance]) ? $dance_mapping[$dance] : $dance;
            ?>
            <div class="dance-page" data-dance="<?=h($dance)?>" data-dance-index="<?=$dance_index?>">
                <div class="dance-header">
                    <h3><?=h($dance_display)?></h3>
                    <p>총 <?=count($players)?>명 중 <?= $recall_count ?>명 선택</p>
                </div>
                
                <?php if ($hits_data && !empty($hits_data)): ?>
                    <!-- Heat-based display -->
                    <?php foreach ($hits_data as $heat_no => $heat_players): ?>
                        <div class="heat-section">
                            <h4>히트 <?= $heat_no ?></h4>
                            <div class="players-grid">
                        <?php foreach ($heat_players as $player_no): ?>
                            <div class="player-card" data-player="<?=h($player_no)?>" onclick="togglePlayerSelection(this)">
                                <div class="player-number"><?=h($player_no)?></div>
                                <input type="checkbox" 
                                       name="recall_marks[<?=h($dance)?>][<?=h($player_no)?>]" 
                                       class="recall-checkbox"
                                       data-dance="<?=h($dance)?>"
                                       data-player="<?=h($player_no)?>">
                            </div>
                        <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- No heats - all players together -->
                    <div class="players-grid">
                        <?php foreach ($players as $player_no): ?>
                            <div class="player-card" data-player="<?=h($player_no)?>" onclick="togglePlayerSelection(this)">
                                <div class="player-number"><?=h($player_no)?></div>
                                <input type="checkbox" 
                                       name="recall_marks[<?=h($dance)?>][<?=h($player_no)?>]" 
                                       class="recall-checkbox"
                                       data-dance="<?=h($dance)?>"
                                       data-player="<?=h($player_no)?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="recall-status">
                    <div style="font-size:20px; margin-bottom:5px;">📊 Recall 진행 상황</div>
                    <div style="font-size:28px; font-weight:900; color:#2e7d32;">
                        <span class="selected-count">0</span> / <?= $recall_count ?>명
                    </div>
                    <div style="font-size:14px; margin-top:5px; opacity:0.8;">
                        <?= $recall_count ?>명을 선택하면 전송할 수 있습니다
                    </div>
                </div>
                
                <div class="dance-submit-section" style="text-align:center; margin-top:20px; display:none;">
                    <button type="button" 
                            class="dance-submit-btn" 
                            data-dance="<?=h($dance)?>"
                            style="background:#4CAF50; color:#fff; border:none; padding:15px 30px; font-size:16px; font-weight:bold; border-radius:8px; cursor:pointer;">
                        <?=h($dance_display)?> 전송
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="dance-navigation" style="text-align:center; margin-top:20px;">
        <button type="button" id="prevDance" style="background:#666; color:#fff; border:none; padding:10px 20px; margin:0 10px; border-radius:5px; cursor:pointer; display:none;">이전</button>
        <button type="button" id="nextDance" style="background:#2196F3; color:#fff; border:none; padding:10px 20px; margin:0 10px; border-radius:5px; cursor:pointer; display:none;">다음</button>
    </div>
</div>

<!-- 하단 네비게이션 -->
<?php renderBottomNavigation($comp_id, $lang); ?>

<!-- 멀티이벤트 예선/준결승 전용 JavaScript -->
<script>
// Instagram Style Dance System
let currentDanceIndex = 0;
let totalDances = 0;
let completedDances = new Set();
let existingSelections = <?= json_encode($existing_selections) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('.place-input');
    if (firstInput) {
        firstInput.focus();
    }
    
    // Initialize dance system
    const danceSlider = document.getElementById('danceSlider');
    if (danceSlider) {
        initializeDanceSystem();
    }
    
    // Setup fixed action buttons
    setupFixedButtons();
});

function setupFixedButtons() {
    // Dashboard button
    const dashboardBtn = document.getElementById('dashboardBtn');
    if (dashboardBtn) {
        dashboardBtn.addEventListener('click', function() {
            const compId = '<?= h($comp_id) ?>';
            const lang = '<?= h($lang) ?>';
            window.location.href = `scoring_dashboard.php?comp_id=${compId}&lang=${lang}`;
        });
    }
    
    // Refresh button
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            if (confirm('페이지를 새로고침하시겠습니까? 현재 작업 중인 내용이 저장되지 않을 수 있습니다.')) {
                window.location.reload();
            }
        });
    }
}

function loadExistingSelections() {
    // Load existing selections for all dances
    Object.keys(existingSelections).forEach(dance => {
        const dancePage = document.querySelector(`[data-dance="${dance}"]`);
        if (!dancePage) return;
        
        const selectedPlayers = existingSelections[dance];
        selectedPlayers.forEach(playerNo => {
            const checkbox = dancePage.querySelector(`input[data-player="${playerNo}"]`);
            if (checkbox) {
                checkbox.checked = true;
                const card = checkbox.closest('.player-card');
                if (card) {
                    card.classList.add('selected');
                }
            }
        });
        
        // Mark as completed if selections exist
        if (selectedPlayers.length > 0) {
            completedDances.add(dance);
        }
    });
}

function initializeDanceSystem() {
    console.log('Initializing dance system');
    
    const dancePages = document.querySelectorAll('.dance-page');
    totalDances = dancePages.length;
    
    console.log('Found dance pages:', totalDances);
    
    if (totalDances === 0) {
        console.log('No dance pages found');
        return;
    }
    
    // Load existing selections for all dances
    loadExistingSelections();
    
    // Show first dance page
    showDancePage(0);
    
    // Setup event listeners
    setupDanceEventListeners();
    // setupPlayerCardEvents(); // 제거 - onclick 속성 사용
    updateProgress();
    
    // Initialize top recall status
    updateTopRecallStatus();
}

// setupBasicPlayerCardEvents 함수 제거 - onclick 속성 사용

// setupPlayerCardEvents 함수 제거 - onclick 속성 사용

function showDancePage(index) {
    const danceSlider = document.getElementById('danceSlider');
    const dancePages = document.querySelectorAll('.dance-page');
    
    if (index < 0 || index >= totalDances) return;
    
    currentDanceIndex = index;
    danceSlider.style.transform = `translateX(-${index * 100}%)`;
    
    // Update progress
    updateProgress();
    
    // Update navigation buttons
    updateNavigationButtons();
    
    // Reset touch events for current page
    resetTouchEvents();
    
    // Restore dance page state if going back to a completed dance
    const currentPage = document.querySelector('.dance-page[data-dance-index="' + currentDanceIndex + '"]');
    if (currentPage) {
        const dance = currentPage.dataset.dance;
        const isCompleted = completedDances.has(dance);
        
        if (isCompleted) {
            // Restore the page to editable state
            restoreDancePageState(currentPage, dance);
        } else {
            // Load existing selections for this dance if not completed
            loadExistingSelectionsForDance(currentPage, dance);
        }
    }
    
    // Update recall status for current page
    updateTopRecallStatus();
}

function setupDanceEventListeners() {
    // Navigation buttons
    document.getElementById('prevDance')?.addEventListener('click', () => {
        if (currentDanceIndex > 0) {
            showDancePage(currentDanceIndex - 1);
        }
    });
    
    document.getElementById('nextDance')?.addEventListener('click', () => {
        if (currentDanceIndex < totalDances - 1) {
            showDancePage(currentDanceIndex + 1);
        }
    });
    
    // Dance submit buttons
    document.querySelectorAll('.dance-submit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const dance = this.dataset.dance;
            submitDance(dance);
        });
    });
    
    // Touch/swipe events
    let startX = 0;
    let startY = 0;
    let isSwipe = false;
    
    const danceSlider = document.getElementById('danceSlider');
    
    danceSlider.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        isSwipe = false;
    });
    
    danceSlider.addEventListener('touchmove', function(e) {
        if (!startX || !startY) return;
        
        const currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const diffX = startX - currentX;
        const diffY = startY - currentY;
        
        // Determine if this is a horizontal swipe
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
            isSwipe = true;
            e.preventDefault();
        }
    });
    
    danceSlider.addEventListener('touchend', function(e) {
        if (!isSwipe || !startX || !startY) return;
        
        const endX = e.changedTouches[0].clientX;
        const diffX = startX - endX;
        
        if (Math.abs(diffX) > 100) {
            if (diffX > 0 && currentDanceIndex < totalDances - 1) {
                // Swipe left - next dance
                showDancePage(currentDanceIndex + 1);
            } else if (diffX < 0 && currentDanceIndex > 0) {
                // Swipe right - previous dance
                showDancePage(currentDanceIndex - 1);
            }
        }
        
        startX = 0;
        startY = 0;
        isSwipe = false;
    });
}

function resetTouchEvents() {
    // Remove existing event listeners and re-add them
    const playerCards = document.querySelectorAll('.player-card');
    playerCards.forEach(card => {
        // Remove old listeners by cloning the element
        const newCard = card.cloneNode(true);
        card.parentNode.replaceChild(newCard, card);
        
        // Add new listeners
        setupPlayerCardEvents(newCard);
    });
}

function setupPlayerCardEvents(card) {
    let touchStartTime = 0;
    let touchStartPos = { x: 0, y: 0 };
    
    // Click event
    card.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        togglePlayerSelection(this);
    });
    
    // Touch start
    card.addEventListener('touchstart', function(e) {
        touchStartTime = Date.now();
        const touch = e.touches[0];
        touchStartPos = { x: touch.clientX, y: touch.clientY };
    });
    
    // Touch end
    card.addEventListener('touchend', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const touchEndTime = Date.now();
        const touchDuration = touchEndTime - touchStartTime;
        
        // Only trigger if it's a quick tap (less than 500ms)
        if (touchDuration < 500) {
            togglePlayerSelection(this);
        }
    });
    
    // Prevent context menu on long press
    card.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
}

function updateProgress() {
    const progressFill = document.querySelector('.progress-fill');
    const currentDanceSpan = document.querySelector('.current-dance');
    
    if (progressFill) {
        const progress = ((currentDanceIndex + 1) / totalDances) * 100;
        progressFill.style.width = progress + '%';
    }
    
    if (currentDanceSpan) {
        currentDanceSpan.textContent = currentDanceIndex + 1;
    }
}

function restoreDancePageState(page, dance) {
    // Remove from completed dances to allow re-editing
    completedDances.delete(dance);
    
    // Restore the recall status section
    const statusDiv = page.querySelector('.recall-status');
    if (statusDiv) {
        statusDiv.innerHTML = `
            <div style="font-size:20px; margin-bottom:5px;">📊 Recall 진행 상황</div>
            <div style="font-size:28px; font-weight:900; color:#2e7d32;">
                <span class="selected-count">0</span> / <?= $recall_count ?>명
            </div>
            <div style="font-size:14px; margin-top:5px; opacity:0.8;">
                <?= $recall_count ?>명을 선택하면 전송할 수 있습니다
            </div>
        `;
    }
    
    // Show submit section (will be hidden/shown based on selection count)
    const submitSection = page.querySelector('.dance-submit-section');
    if (submitSection) {
        submitSection.style.display = 'none';
    }
    
    // Load existing selections for this dance
    if (existingSelections[dance]) {
        const selectedPlayers = existingSelections[dance];
        selectedPlayers.forEach(playerNo => {
            const checkbox = page.querySelector(`input[data-player="${playerNo}"]`);
            if (checkbox) {
                checkbox.checked = true;
                const card = checkbox.closest('.player-card');
                if (card) {
                    card.classList.add('selected');
                }
            }
        });
    } else {
        // No existing selections, clear all
        const checkboxes = page.querySelectorAll('.recall-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Update player card states
        const playerCards = page.querySelectorAll('.player-card');
        playerCards.forEach(card => {
            card.classList.remove('selected');
        });
    }
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevDance');
    const nextBtn = document.getElementById('nextDance');
    
    if (prevBtn) {
        prevBtn.style.display = currentDanceIndex > 0 ? 'inline-block' : 'none';
    }
    
    if (nextBtn) {
        // Show next button if there are more dances AND current dance is completed
        const currentDance = document.querySelector('.dance-page[data-dance-index="' + currentDanceIndex + '"]');
        const isCurrentDanceCompleted = currentDance && completedDances.has(currentDance.dataset.dance);
        
        if (currentDanceIndex < totalDances - 1 && isCurrentDanceCompleted) {
            nextBtn.style.display = 'inline-block';
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        } else {
            nextBtn.style.display = 'none';
        }
    }
}

function submitDance(dance) {
    const currentPage = document.querySelector(`[data-dance="${dance}"]`);
    const checkboxes = currentPage.querySelectorAll('.recall-checkbox:checked');
    const recallCount = <?= json_encode(intval($recall_count)) ?>;
    
    if (checkboxes.length !== recallCount) {
        alert(`${recallCount}명을 정확히 선택해주세요.`);
        return;
    }
    
    // Collect selected players for this dance
    const selectedPlayers = Array.from(checkboxes).map(cb => cb.dataset.player);
    
    // Prepare data for this dance only
    const recallMarks = {};
    recallMarks[dance] = selectedPlayers;
    
    const data = {
        comp_id: <?= json_encode($comp_id) ?>,
        event_no: <?= json_encode($event_no) ?>,
        type: 'recall',
        recall_marks: recallMarks
    };
    
    // Show loading state
    const submitBtn = currentPage.querySelector('.dance-submit-btn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '전송 중...';
    submitBtn.disabled = true;
    
    fetch('scoring/save_scores.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Mark this dance as completed
            completedDances.add(dance);
            
            // Update existing selections
            existingSelections[dance] = selectedPlayers;
            
            // Hide submit button and show completion message
            submitBtn.style.display = 'none';
            const statusDiv = currentPage.querySelector('.recall-status');
            statusDiv.innerHTML = `
                <div style="font-size:20px; margin-bottom:5px;">✅ 완료</div>
                <div style="font-size:18px; font-weight:bold; color:#4CAF50;">
                    ${recallCount}명 선택 완료
                </div>
                <div style="font-size:14px; margin-top:5px; opacity:0.8;">
                    다음 댄스로 진행하세요
                </div>
            `;
            
            // Enable next dance button if available
            updateNavigationButtons();
            
            // Show completion message
            if (currentDanceIndex < totalDances - 1) {
                setTimeout(() => {
                    alert(`${dance} 댄스 채점이 완료되었습니다. 다음 댄스로 진행하세요.`);
                }, 1000);
            } else {
                // All dances completed
                setTimeout(() => {
                    alert('모든 댄스 채점이 완료되었습니다!');
                }, 1000);
            }
        } else {
            alert('전송 실패: ' + (result.error || '알 수 없는 오류'));
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('전송 중 오류가 발생했습니다: ' + error);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Recall system event handlers
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up event handlers');
    
    // Initialize dance system first
    const danceSlider = document.getElementById('danceSlider');
    if (danceSlider) {
        initializeDanceSystem();
    }
    
    // Setup fixed action buttons
    setupFixedButtons();
    
    console.log('Event handlers setup complete');
});

function togglePlayerSelection(card) {
    console.log('togglePlayerSelection called for card:', card);
    const checkbox = card.querySelector('.recall-checkbox');
    if (!checkbox) {
        console.error('No checkbox found in card:', card);
        return;
    }
    
    console.log('Checkbox before toggle:', checkbox.checked);
    checkbox.checked = !checkbox.checked;
    console.log('Checkbox after toggle:', checkbox.checked);
    
    updatePlayerCardState(card);
    updateRecallStatus();
    updateTopRecallStatus();
    
    // 디버그: 현재 선택된 선수 수 확인
    const selectedCount = document.querySelectorAll('.recall-checkbox:checked').length;
    console.log('Total selected players:', selectedCount);
}

function updatePlayerCardState(card) {
    console.log('updatePlayerCardState called for card:', card);
    const checkbox = card.querySelector('.recall-checkbox');
    if (!checkbox) {
        console.error('No checkbox found in card for updatePlayerCardState:', card);
        return;
    }
    
    if (checkbox.checked) {
        card.classList.add('selected');
        console.log('Card marked as selected');
    } else {
        card.classList.remove('selected');
        console.log('Card marked as unselected');
    }
}

function updateTopRecallStatus() {
    // Get all checked checkboxes from all pages
    const allCheckboxes = document.querySelectorAll('.recall-checkbox:checked');
    const selectedCount = allCheckboxes.length;
    const recallCount = <?= json_encode(intval($recall_count)) ?>;
    
    // Update top recall progress display
    const topRecallProgress = document.getElementById('recallProgress');
    if (topRecallProgress) {
        topRecallProgress.textContent = selectedCount;
        
        // Update color based on progress
        if (selectedCount === recallCount) {
            topRecallProgress.style.color = '#4CAF50';
            topRecallProgress.style.fontWeight = 'bold';
        } else if (selectedCount > recallCount) {
            topRecallProgress.style.color = '#f44336';
            topRecallProgress.style.fontWeight = 'bold';
        } else {
            topRecallProgress.style.color = '#ff9800';
            topRecallProgress.style.fontWeight = 'normal';
        }
    }
    
    // Update fixed progress bar
    const fixedRecallCount = document.getElementById('fixedRecallCount');
    if (fixedRecallCount) {
        fixedRecallCount.textContent = selectedCount;
    }
    
    // Update submit button state for current dance page
    const currentPage = document.querySelector('.dance-page[data-dance-index="' + currentDanceIndex + '"]');
    if (currentPage) {
        const submitSection = currentPage.querySelector('.dance-submit-section');
        const submitBtn = currentPage.querySelector('.dance-submit-btn');
        
        if (submitSection && submitBtn) {
            // Show/hide submit section based on selection count
            if (selectedCount === recallCount) {
                submitSection.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            } else {
                submitSection.style.display = 'none';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        }
    }
}

function updateRecallStatus() {
    console.log('updateRecallStatus called, currentDanceIndex:', currentDanceIndex);
    const currentPage = document.querySelector('.dance-page[data-dance-index="' + currentDanceIndex + '"]');
    
    console.log('Current page found:', currentPage);
    
    if (!currentPage) {
        console.log('No current page found, using fallback');
        // Fallback: get all checked checkboxes from all pages
        const allCheckboxes = document.querySelectorAll('.recall-checkbox:checked');
        const selectedCount = allCheckboxes.length;
        const recallCount = <?= json_encode(intval($recall_count)) ?>;
        
        console.log('Fallback - selectedCount:', selectedCount, 'recallCount:', recallCount);
        
        // Update top recall progress display
        const topRecallProgress = document.getElementById('recallProgress');
        if (topRecallProgress) {
            topRecallProgress.textContent = selectedCount;
            
            // Update color based on progress
            if (selectedCount === recallCount) {
                topRecallProgress.style.color = '#4CAF50';
                topRecallProgress.style.fontWeight = 'bold';
            } else if (selectedCount > recallCount) {
                topRecallProgress.style.color = '#f44336';
                topRecallProgress.style.fontWeight = 'bold';
            } else {
                topRecallProgress.style.color = '#ff9800';
                topRecallProgress.style.fontWeight = 'normal';
            }
        }
        return;
    }
    
    const checkboxes = currentPage.querySelectorAll('.recall-checkbox:checked');
    const selectedCount = checkboxes.length;
    const recallCount = <?= json_encode(intval($recall_count)) ?>;
    
    console.log('Current page - selectedCount:', selectedCount, 'recallCount:', recallCount);
    
    // Top recall progress is handled by updateTopRecallStatus function
    
    // Update fixed progress bar
    const fixedProgress = document.getElementById('fixedProgress');
    console.log('Fixed progress bar found:', fixedProgress);
    if (fixedProgress) {
        const progressText = fixedProgress.querySelector('#fixedRecallCount');
        console.log('Progress text element found:', progressText);
        if (progressText) {
            progressText.textContent = selectedCount;
            console.log('Updated progress text to:', selectedCount);
        }
        
        // Update progress bar styling
        fixedProgress.classList.remove('complete', 'over');
        if (selectedCount === recallCount) {
            fixedProgress.classList.add('complete');
        } else if (selectedCount > recallCount) {
            fixedProgress.classList.add('over');
        }
    }
    
    // Update current page status
    const statusDiv = currentPage.querySelector('.recall-status');
    console.log('Status div found:', statusDiv);
    const countSpan = statusDiv ? statusDiv.querySelector('.selected-count') : null;
    console.log('Count span found:', countSpan);
    const submitSection = currentPage.querySelector('.dance-submit-section');
    console.log('Submit section found:', submitSection);
    
    if (countSpan) {
        countSpan.textContent = selectedCount;
        console.log('Updated count span to:', selectedCount);
    }
    
    // Update status styling
    statusDiv.classList.remove('complete', 'over');
    if (selectedCount === recallCount) {
        statusDiv.classList.add('complete');
        if (submitSection) submitSection.style.display = 'block';
    } else if (selectedCount > recallCount) {
        statusDiv.classList.add('over');
        if (submitSection) submitSection.style.display = 'none';
    } else {
        if (submitSection) submitSection.style.display = 'none';
    }
}

function initializeFixedProgress() {
    const fixedProgress = document.getElementById('fixedProgress');
    if (!fixedProgress) return;
    
    let isScrolling = false;
    let scrollTimeout;
    
    // Show progress bar when scrolling down
    window.addEventListener('scroll', function() {
        if (!isScrolling) {
            fixedProgress.classList.add('show');
            document.body.classList.add('progress-bar-visible');
        }
        
        isScrolling = true;
        clearTimeout(scrollTimeout);
        
        scrollTimeout = setTimeout(function() {
            isScrolling = false;
            // Hide progress bar after scrolling stops (only if no selections)
            const totalSelected = document.querySelectorAll('.recall-checkbox:checked').length;
            if (totalSelected === 0) {
                fixedProgress.classList.remove('show');
                document.body.classList.remove('progress-bar-visible');
            }
        }, 1000);
    });
    
    // Touch events for mobile
    document.addEventListener('touchstart', function() {
        fixedProgress.classList.add('show');
        document.body.classList.add('progress-bar-visible');
    });
}

function loadExistingSelectionsForDance(page, dance) {
    // Load existing selections for a specific dance
    if (existingSelections[dance]) {
        const selectedPlayers = existingSelections[dance];
        selectedPlayers.forEach(playerNo => {
            const checkbox = page.querySelector(`input[data-player="${playerNo}"]`);
            if (checkbox) {
                checkbox.checked = true;
                const card = checkbox.closest('.player-card');
                if (card) {
                    card.classList.add('selected');
                }
            }
        });
    }
}
</script>
