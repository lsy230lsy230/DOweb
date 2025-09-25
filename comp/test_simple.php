<?php
echo "PHP 서버가 정상적으로 작동합니다!";
echo "<br>";
echo "현재 시간: " . date('Y-m-d H:i:s');
echo "<br>";
echo "GET 파라미터: " . json_encode($_GET);
?>