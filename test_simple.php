<?php
// シンプルなテストファイル
header('Content-Type: application/json; charset=utf-8');

try {
    // 環境変数の確認
    require_once 'config.php';
    
    echo json_encode([
        'success' => true,
        'message' => 'API is working',
        'env_check' => [
            'openai_key_set' => !empty($_ENV['OPENAI_API_KEY']),
            'claude_key_set' => !empty($_ENV['CLAUDE_API_KEY']),
            'gemini_key_set' => !empty($_ENV['GEMINI_API_KEY'])
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>