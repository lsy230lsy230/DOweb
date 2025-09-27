<?php
/**
 * 이벤트 결과 HTML 파일을 불러오는 API
 */

// CORS 헤더 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 컨텐츠 타입을 HTML로 설정
header('Content-Type: text/html; charset=utf-8');

// 파라미터 확인
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

if (empty($comp_id) || empty($event_no)) {
    echo '<div style="text-align: center; padding: 40px; color: #ef4444;">
            <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px;">error</span>
            <h3>잘못된 요청</h3>
            <p>대회 ID 또는 이벤트 번호가 없습니다.</p>
          </div>';
    exit;
}

// 파일 경로 생성
$comp_data_dir = __DIR__ . '/data/' . $comp_id;
$result_file = $comp_data_dir . '/Results/Event_' . $event_no . '/Event_' . $event_no . '_result.html';

// 파일 존재 확인
if (!file_exists($result_file)) {
    echo '<div style="text-align: center; padding: 40px; color: #6b7280;">
            <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">description</span>
            <h3>결과를 찾을 수 없습니다</h3>
            <p>이벤트 ' . htmlspecialchars($event_no) . '의 결과 파일이 아직 생성되지 않았습니다.</p>
            <p style="font-size: 14px; color: #9ca3af; margin-top: 12px;">파일 경로: ' . htmlspecialchars($result_file) . '</p>
          </div>';
    exit;
}

// 파일 내용 읽기
$html_content = file_get_contents($result_file);

if ($html_content === false) {
    echo '<div style="text-align: center; padding: 40px; color: #ef4444;">
            <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px;">error</span>
            <h3>파일 읽기 오류</h3>
            <p>결과 파일을 읽을 수 없습니다.</p>
          </div>';
    exit;
}

// HTML 내용이 비어있는지 확인
if (trim($html_content) === '') {
    echo '<div style="text-align: center; padding: 40px; color: #6b7280;">
            <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">description</span>
            <h3>빈 결과 파일</h3>
            <p>이벤트 ' . htmlspecialchars($event_no) . '의 결과 파일이 비어있습니다.</p>
          </div>';
    exit;
}

// 모달에서 보기 좋게 스타일 적용
$styled_content = '
<style>
    /* 모달 내 테이블 스타일 */
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 16px 0;
        font-size: 14px;
    }
    
    th, td {
        padding: 8px 12px;
        text-align: left;
        border: 1px solid #e5e7eb;
    }
    
    th {
        background-color: #f8fafc;
        font-weight: 600;
        color: #374151;
    }
    
    tr:nth-child(even) {
        background-color: #f9fafb;
    }
    
    tr:hover {
        background-color: #f3f4f6;
    }
    
    /* 텍스트 스타일 */
    h1, h2, h3, h4, h5, h6 {
        color: #1f2937;
        margin: 16px 0 8px 0;
    }
    
    h1 { font-size: 24px; }
    h2 { font-size: 20px; }
    h3 { font-size: 18px; }
    h4 { font-size: 16px; }
    
    p {
        margin: 8px 0;
        line-height: 1.5;
        color: #374151;
    }
    
    /* 순위 강조 */
    .rank-1, .rank-2, .rank-3 {
        font-weight: bold;
    }
    
    .rank-1 { color: #dc2626; } /* 1위 - 빨간색 */
    .rank-2 { color: #ea580c; } /* 2위 - 주황색 */
    .rank-3 { color: #ca8a04; } /* 3위 - 황금색 */
    
    /* 중앙 정렬 */
    .center {
        text-align: center;
    }
    
    /* 결과 파일의 기본 스타일 재정의 */
    body {
        font-family: "Noto Sans KR", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        line-height: 1.6;
        color: #374151;
        background: white;
        margin: 0;
        padding: 0;
    }
    
    /* 모든 텍스트를 보기 좋게 */
    * {
        max-width: 100%;
        box-sizing: border-box;
    }
    
    /* 이미지나 큰 요소 제한 */
    img {
        max-width: 100%;
        height: auto;
    }
</style>

<div style="padding: 0;">
' . $html_content . '
</div>';

// 결과 출력
echo $styled_content;
?>