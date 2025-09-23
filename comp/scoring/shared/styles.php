<?php
// 공통 스타일들
?>
<style>
/* 기본 스타일 */
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    margin: 0;
    padding: 0;
    background: #f5f5f5;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    min-height: 100vh;
}

.header {
    background: #2c3e50;
    color: white;
    padding: 20px;
    text-align: center;
}

.event-info {
    background: #ecf0f1;
    padding: 20px;
    border-bottom: 1px solid #bdc3c7;
}

/* 버튼 스타일 */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: all 0.3s;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-success {
    background: #27ae60;
    color: white;
}

.btn-success:hover {
    background: #229954;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
}
</style>