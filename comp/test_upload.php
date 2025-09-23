<?php
echo "File upload test - " . date('Y-m-d H:i:s');
echo "<br>Current directory: " . __DIR__;
echo "<br>File exists: " . (file_exists(__DIR__ . '/judge_scoring.php') ? 'YES' : 'NO');
echo "<br>File size: " . filesize(__DIR__ . '/judge_scoring.php') . " bytes";
?>



