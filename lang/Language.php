<?php
class Language {
    private static $instance = null;
    private $currentLang = 'ko';
    private $translations = [];
    private $availableLanguages = [
        'ko' => ['name' => '한국어', 'flag' => '🇰🇷'],
        'en' => ['name' => 'English', 'flag' => '🇺🇸'],
        'ja' => ['name' => '日本語', 'flag' => '🇯🇵'],
        'zh' => ['name' => '中文', 'flag' => '🇨🇳'],
        'ru' => ['name' => 'Русский', 'flag' => '🇷🇺']
    ];

    private function __construct() {
        $this->detectLanguage();
        $this->loadTranslations();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function detectLanguage() {
        // URL 파라미터에서 언어 확인
        if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $this->availableLanguages)) {
            $this->currentLang = $_GET['lang'];
            $_SESSION['lang'] = $this->currentLang;
            return;
        }

        // 세션에서 언어 확인
        if (isset($_SESSION['lang']) && array_key_exists($_SESSION['lang'], $this->availableLanguages)) {
            $this->currentLang = $_SESSION['lang'];
            return;
        }

        // 브라우저 언어 감지
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (array_key_exists($browserLang, $this->availableLanguages)) {
                $this->currentLang = $browserLang;
                $_SESSION['lang'] = $this->currentLang;
                return;
            }
        }

        // 기본값: 한국어
        $this->currentLang = 'ko';
        $_SESSION['lang'] = $this->currentLang;
    }

    private function loadTranslations() {
        $langFile = __DIR__ . '/' . $this->currentLang . '.php';
        if (file_exists($langFile)) {
            $this->translations = require $langFile;
        } else {
            // 폴백으로 한국어 로드
            $this->translations = require __DIR__ . '/ko.php';
        }
    }

    public function get($key, $default = null) {
        return $this->translations[$key] ?? $default ?? $key;
    }

    public function getCurrentLang() {
        return $this->currentLang;
    }

    public function getAvailableLanguages() {
        return $this->availableLanguages;
    }

    public function getLangName($code = null) {
        $code = $code ?? $this->currentLang;
        return $this->availableLanguages[$code]['name'] ?? $code;
    }

    public function getLangFlag($code = null) {
        $code = $code ?? $this->currentLang;
        return $this->availableLanguages[$code]['flag'] ?? '';
    }

    public function getLanguageUrl($langCode) {
        $currentUrl = $_SERVER['REQUEST_URI'];
        $urlParts = parse_url($currentUrl);
        $params = [];
        
        // 기존 lang 파라미터 제거
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $params);
        }
        
        $params['lang'] = $langCode;
        $queryString = http_build_query($params);

        $path = $urlParts['path'] ?? '/';
        return $path . '?' . $queryString;
    }

    public function formatDate($timestamp, $format = null) {
        $format = $format ?? $this->get('date_format', 'Y-m-d');
        return date($format, is_string($timestamp) ? strtotime($timestamp) : $timestamp);
    }
}

// 헬퍼 함수
function t($key, $default = null) {
    return Language::getInstance()->get($key, $default);
}

function lang() {
    return Language::getInstance();
}
?>