<?php
require_once 'config.php';

echo "API Key Test\n";
echo "============\n\n";

// 環境変数の確認
echo "Environment Variables:\n";
var_dump($_ENV);

echo "\n\nAPI Keys:\n";
echo "OpenAI: " . substr(AIConfig::getApiKey('gpt-4o'), 0, 20) . "...\n";
echo "Claude: " . substr(AIConfig::getApiKey('claude-4-sonnet'), 0, 20) . "...\n";
echo "Gemini: " . substr(AIConfig::getApiKey('gemini-2.0-flash'), 0, 20) . "...\n";

// 簡単なJSON テスト
$testData = [
    'model' => 'gpt-4o',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello']
    ]
];

echo "\n\nJSON Test:\n";
$json = json_encode($testData, JSON_UNESCAPED_UNICODE);
echo "JSON: " . $json . "\n";
echo "JSON Error: " . json_last_error_msg() . "\n";
?>