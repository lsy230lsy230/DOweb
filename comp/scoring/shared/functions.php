<?php
// 공통 함수들

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function renderFixedProgressBar($recall_count, $is_final, $event_no) {
    if ($is_final) return;
    
    echo '<div id="fixedProgress" class="fixed-progress">';
    echo '<div style="display: flex; align-items: center; justify-content: center;">';
    echo '<span style="margin-right: 10px;">📊 Recall 진행: </span>';
    echo '<span id="fixedRecallCount" style="font-size: 24px; font-weight: bold;">0</span>';
    echo '<span style="margin-left: 5px;">/ ' . $recall_count . '명</span>';
    echo '</div>';
    echo '</div>';
}
?>