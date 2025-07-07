<?php
class ContentProcessor {
    
    public function fetchWebContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("CURL Error for URL $url: $error");
            return null;
        }
        
        if ($httpCode === 200 && $content !== false) {
            $textContent = $this->extractTextFromHtml($content);
            return $this->sanitizeTextContent($textContent);
        }
        
        error_log("HTTP Error for URL $url: HTTP $httpCode");
        return null;
    }
    
    public function extractTextFromHtml($html) {
        if (empty($html)) {
            return '';
        }
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $this->removeUnwantedElements($dom);
        
        $textContent = $dom->textContent;
        return $this->cleanExtractedText($textContent);
    }
    
    public function sanitizeTextContent($text) {
        if (empty($text)) {
            return '';
        }
        
        $encoding = mb_detect_encoding($text, ['UTF-8', 'Shift_JIS', 'EUC-JP', 'ISO-8859-1', 'Windows-1252'], true);
        
        if ($encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding ?: 'auto');
        }
        
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        $text = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
        
        $text = preg_replace('/\s+/', ' ', $text);
        
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = preg_replace('/[^\x20-\x7E\x{3000}-\x{9FFF}\x{FF00}-\x{FFEF}]/u', '', $text);
        }
        
        return trim($text);
    }
    
    private function removeUnwantedElements($dom) {
        $unwantedTags = ['script', 'style', 'nav', 'footer', 'aside', 'header', 'advertisement'];
        
        foreach ($unwantedTags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $element = $elements->item($i);
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }
        
        $xpath = new DOMXPath($dom);
        $unwantedClasses = ['advertisement', 'ads', 'sidebar', 'menu', 'navigation'];
        
        foreach ($unwantedClasses as $class) {
            $elements = $xpath->query("//*[contains(@class, '$class')]");
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $element = $elements->item($i);
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }
    }
    
    private function cleanExtractedText($text) {
        $text = preg_replace('/\s+/', ' ', $text);
        
        $text = preg_replace('/\b(Cookie|クッキー|個人情報|プライバシー|利用規約|免責事項|Copyright|©)\b.{0,100}/ui', '', $text);
        
        $text = preg_replace('/\b(JavaScript|js|css|href|src|onclick)\b.{0,50}/i', '', $text);
        
        $text = preg_replace('/\b(メニュー|ナビゲーション|サイドバー|フッター|ヘッダー|広告|AD|PR)\b.{0,30}/ui', '', $text);
        
        return trim($text);
    }
    
    public function extractMainContent($html) {
        if (empty($html)) {
            return '';
        }
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        $mainSelectors = [
            'main',
            'article',
            '[role="main"]',
            '.main-content',
            '.content',
            '.post-content',
            '.entry-content',
            '.article-content'
        ];
        
        foreach ($mainSelectors as $selector) {
            $elements = $xpath->query("//*[contains(@class, '" . str_replace('.', '', $selector) . "')]");
            if ($elements->length > 0) {
                $element = $elements->item(0);
                return $this->extractTextFromHtml($element->ownerDocument->saveHTML($element));
            }
        }
        
        return $this->extractTextFromHtml($html);
    }
    
    public function limitTextLength($text, $maxLength = 5000) {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        
        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');
        
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }
}