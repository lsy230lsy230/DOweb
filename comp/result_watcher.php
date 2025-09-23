<?php
/**
 * 댄스스코어 결과 파일 실시간 모니터링 스크립트
 * 파일 시스템을 모니터링하고 새로운 결과 파일이 생성되면 자동으로 처리
 */

class ResultWatcher {
    private $resultsPath;
    private $lastCheckTime;
    private $logFile;
    private $isRunning;
    
    public function __construct($resultsPath = __DIR__ . "/../results/results") {
        $this->resultsPath = $resultsPath;
        $this->lastCheckTime = time();
        $this->logFile = __DIR__ . "/watcher.log";
        $this->isRunning = false;
    }
    
    /**
     * 로그 메시지 기록
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 파일 변경 감지
     */
    public function watch() {
        $this->isRunning = true;
        $this->log("결과 파일 모니터링을 시작합니다.");
        
        while ($this->isRunning) {
            try {
                $this->checkForChanges();
                sleep(5); // 5초마다 확인
            } catch (Exception $e) {
                $this->log("모니터링 중 오류 발생: " . $e->getMessage(), 'ERROR');
                sleep(10);
            }
        }
        
        $this->log("모니터링이 중지되었습니다.");
    }
    
    /**
     * 파일 변경사항 확인
     */
    private function checkForChanges() {
        $files = glob($this->resultsPath . "/*.html");
        $newFiles = [];
        
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime > $this->lastCheckTime) {
                $newFiles[] = $file;
            }
        }
        
        if (!empty($newFiles)) {
            $this->log(count($newFiles) . "개의 새 파일이 발견되었습니다.");
            $this->processNewFiles($newFiles);
        }
        
        $this->lastCheckTime = time();
    }
    
    /**
     * 새 파일 처리
     */
    private function processNewFiles($files) {
        require_once 'result_monitor.php';
        
        $monitor = new ResultMonitor($this->resultsPath);
        
        // 결과 쌍 찾기
        $pairs = $monitor->findResultPairs();
        
        foreach ($pairs as $pair) {
            try {
                $this->log("결과 처리 시작: {$pair['event_id']}");
                
                $summaryData = $monitor->parseResultFile($pair['summary']);
                $detailedData = $monitor->parseDetailedFile($pair['detailed']);
                $combinedHtml = $monitor->generateCombinedResult($summaryData, $detailedData, $pair['event_id']);
                $savedFile = $monitor->saveCombinedResult($combinedHtml, $pair['event_id']);
                
                $this->log("결과 처리 완료: {$pair['event_id']} -> {$savedFile}");
                
                // 웹소켓을 통한 실시간 알림 (선택사항)
                $this->notifyWebSocket($pair['event_id'], $savedFile);
                
            } catch (Exception $e) {
                $this->log("결과 처리 실패: {$pair['event_id']} - " . $e->getMessage(), 'ERROR');
            }
        }
    }
    
    /**
     * 웹소켓 알림 (선택사항)
     */
    private function notifyWebSocket($eventId, $filePath) {
        // 웹소켓 서버가 있다면 여기서 알림 전송
        // 예: WebSocket::send("new_result", ['event_id' => $eventId, 'file' => $filePath]);
    }
    
    /**
     * 모니터링 중지
     */
    public function stop() {
        $this->isRunning = false;
    }
    
    /**
     * 데몬 모드로 실행
     */
    public function runAsDaemon() {
        // Windows에서는 데몬 모드가 제한적이므로 일반 실행
        $this->watch();
    }
}

// CLI에서 실행될 때
if (php_sapi_name() === 'cli') {
    $watcher = new ResultWatcher();
    
    // 시그널 핸들러 (Windows에서는 제한적)
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function() use ($watcher) {
            $watcher->stop();
        });
        pcntl_signal(SIGINT, function() use ($watcher) {
            $watcher->stop();
        });
    }
    
    echo "결과 파일 모니터링을 시작합니다...\n";
    echo "중지하려면 Ctrl+C를 누르세요.\n";
    
    $watcher->runAsDaemon();
} else {
    // 웹에서 실행될 때
    if (isset($_GET['action'])) {
        $watcher = new ResultWatcher();
        
        switch ($_GET['action']) {
            case 'start':
                // 백그라운드에서 실행
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    // Windows
                    $command = "php " . __FILE__ . " > nul 2>&1 &";
                    pclose(popen("start /B " . $command, "r"));
                } else {
                    // Linux/Unix
                    $command = "php " . __FILE__ . " > /dev/null 2>&1 &";
                    exec($command);
                }
                echo json_encode(['success' => true, 'message' => '모니터링이 시작되었습니다.']);
                break;
                
            case 'stop':
                // 프로세스 종료 (Windows에서는 제한적)
                echo json_encode(['success' => true, 'message' => '모니터링을 중지하려면 프로세스를 종료하세요.']);
                break;
                
            case 'status':
                $logFile = __DIR__ . "/watcher.log";
                if (file_exists($logFile)) {
                    $logs = file_get_contents($logFile);
                    $lines = explode("\n", $logs);
                    $recentLogs = array_slice($lines, -20); // 최근 20줄
                    echo json_encode(['success' => true, 'logs' => $recentLogs]);
                } else {
                    echo json_encode(['success' => false, 'message' => '로그 파일이 없습니다.']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => '잘못된 액션입니다.']);
        }
    }
}
?>
