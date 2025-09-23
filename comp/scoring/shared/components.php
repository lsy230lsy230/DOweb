<?php
// 공통 컴포넌트들

function renderBottomNavigation($comp_id, $lang) {
    echo '<div class="bottom-nav" style="position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #ddd; padding: 15px; display: flex; justify-content: space-around; z-index: 1000;">';
    echo '<button type="button" id="dashboardBtn" class="btn btn-primary">🏠 대시보드</button>';
    echo '<button type="button" id="refreshBtn" class="btn btn-success">🔄 새로고침</button>';
    echo '</div>';
}
?>