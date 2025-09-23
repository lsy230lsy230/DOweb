<?php
/**
 * 다국어 지원 시스템
 * 한국어, 일본어, 중국어, 러시아어 지원
 */

class LanguageManager {
    private $current_language;
    private $translations;
    private $supported_languages = [
        'ko' => '한국어',
        'ja' => '日本語',
        'zh' => '中文',
        'ru' => 'Русский'
    ];
    
    public function __construct() {
        $this->current_language = $this->detectLanguage();
        $this->loadTranslations();
    }
    
    private function detectLanguage() {
        // 1. URL 파라미터 확인
        if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $this->supported_languages)) {
            $_SESSION['language'] = $_GET['lang'];
            return $_GET['lang'];
        }
        
        // 2. 세션에서 확인
        if (isset($_SESSION['language']) && array_key_exists($_SESSION['language'], $this->supported_languages)) {
            return $_SESSION['language'];
        }
        
        // 3. 브라우저 언어 확인
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (array_key_exists($browser_lang, $this->supported_languages)) {
                return $browser_lang;
            }
        }
        
        // 4. 기본값 (한국어)
        return 'ko';
    }
    
    private function loadTranslations() {
        $lang_file = __DIR__ . "/../languages/{$this->current_language}.php";
        if (file_exists($lang_file)) {
            $this->translations = include $lang_file;
        } else {
            $this->translations = include __DIR__ . "/../languages/ko.php";
        }
    }
    
    public function get($key, $params = []) {
        $translation = $this->translations[$key] ?? $key;
        
        // 파라미터 치환
        if (!empty($params)) {
            foreach ($params as $param_key => $param_value) {
                $translation = str_replace("{{$param_key}}", $param_value, $translation);
            }
        }
        
        return $translation;
    }
    
    public function getCurrentLanguage() {
        return $this->current_language;
    }
    
    public function getSupportedLanguages() {
        return $this->supported_languages;
    }
    
    public function getLanguageName($code) {
        return $this->supported_languages[$code] ?? $code;
    }
    
    public function setLanguage($lang_code) {
        if (array_key_exists($lang_code, $this->supported_languages)) {
            $_SESSION['language'] = $lang_code;
            $this->current_language = $lang_code;
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    public function getLanguageSelector() {
        $html = '<div class="language-selector">';
        $html .= '<select onchange="changeLanguage(this.value)">';
        
        foreach ($this->supported_languages as $code => $name) {
            $selected = ($code === $this->current_language) ? 'selected' : '';
            $html .= "<option value='{$code}' {$selected}>{$name}</option>";
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        return $html;
    }
}

// 전역 함수
function __($key, $params = []) {
    global $lang;
    return $lang->get($key, $params);
}

function getLanguageSelector() {
    global $lang;
    return $lang->getLanguageSelector();
}

// 언어 매니저 초기화
$lang = new LanguageManager();
?>
