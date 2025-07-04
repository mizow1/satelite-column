<?php
class AIService {
    private $timeout = 300; // 5分
    
    public function generateText($prompt, $model) {
        switch ($model) {
            case 'gpt-4':
            case 'gpt-4o':
                return $this->callOpenAI($prompt);
            case 'claude-4-sonnet':
                return $this->callClaude($prompt);
            case 'gemini-2.0-flash':
                return $this->callGemini($prompt);
            default:
                throw new Exception("Unsupported AI model: " . $model);
        }
    }
    
    private function callOpenAI($prompt) {
        $apiKey = AIConfig::getApiKey('gpt-4o');
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        // UTF-8文字列のサニタイズ
        $cleanPrompt = $this->sanitizeUtf8($prompt);
        
        $data = [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'あなたは占い好きな人向けのコラム記事を作成する専門家です。SEOを意識し、読みやすく興味深い記事を作成してください。'
                ],
                [
                    'role' => 'user',
                    'content' => $cleanPrompt
                ]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.7
        ];
        
        // JSONエンコードを試行（まずは通常のエンコード）
        $jsonData = json_encode($data);
        
        // JSONエンコードに失敗した場合の処理
        if (json_last_error() !== JSON_ERROR_NONE) {
            // データ内の全文字列をさらにサニタイズ
            $data = $this->deepSanitizeArray($data);
            $jsonData = json_encode($data);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON encoding error: " . json_last_error_msg() . " - Data: " . print_r($data, true));
            }
        }
        
        // デバッグ用ログ
        error_log("OpenAI Request JSON: " . substr($jsonData, 0, 500) . "...");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'Content-Length: ' . strlen($jsonData)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("cURL error: " . $curlError);
        }
        
        // デバッグ用ログ
        error_log("OpenAI Response: " . $response);
        
        if ($httpCode !== 200) {
            $errorInfo = json_decode($response, true);
            $errorMessage = $errorInfo['error']['message'] ?? 'Unknown error';
            throw new Exception("OpenAI API error: HTTP " . $httpCode . " - " . $errorMessage);
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI API response");
        }
        
        return $result['choices'][0]['message']['content'];
    }
    
    private function callClaude($prompt) {
        $apiKey = AIConfig::getApiKey('claude-4-sonnet');
        if (empty($apiKey)) {
            throw new Exception("Claude API key not configured");
        }
        
        $data = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'system' => 'あなたは占い好きな人向けのコラム記事を作成する専門家です。SEOを意識し、読みやすく興味深い記事を作成してください。',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Claude API error: HTTP " . $httpCode);
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['content'][0]['text'])) {
            throw new Exception("Invalid Claude API response");
        }
        
        return $result['content'][0]['text'];
    }
    
    private function callGemini($prompt) {
        $apiKey = AIConfig::getApiKey('gemini-2.0-flash');
        if (empty($apiKey)) {
            throw new Exception("Gemini API key not configured");
        }
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => 'あなたは占い好きな人向けのコラム記事を作成する専門家です。SEOを意識し、読みやすく興味深い記事を作成してください。\n\n' . $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 4000,
                'temperature' => 0.7
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . $apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Gemini API error: HTTP " . $httpCode);
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Gemini API response");
        }
        
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
    
    private function sanitizeUtf8($text) {
        // 文字列がnullまたは空の場合の処理
        if (empty($text)) {
            return '';
        }
        
        // 元の文字エンコーディングを検出
        $encoding = mb_detect_encoding($text, ['UTF-8', 'Shift_JIS', 'EUC-JP', 'ISO-8859-1'], true);
        
        // UTF-8に強制変換
        if ($encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding ?: 'auto');
        }
        
        // 不正なUTF-8文字を除去
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // 制御文字を除去（改行、タブ、スペースは保持）
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // 4バイト以上のUTF-8文字（絵文字など）を除去
        $text = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
        
        // 最終的に有効なUTF-8かチェック
        if (!mb_check_encoding($text, 'UTF-8')) {
            // 無効な場合は安全な文字のみを抽出
            $text = preg_replace('/[^\x20-\x7E\x{3000}-\x{9FFF}\x{FF00}-\x{FFEF}]/u', '', $text);
        }
        
        return trim($text);
    }
    
    private function deepSanitizeArray($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->deepSanitizeArray($value);
            }
        } elseif (is_string($data)) {
            $data = $this->sanitizeUtf8($data);
        }
        return $data;
    }
}
?>