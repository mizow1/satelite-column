<?php
// 直接アクセス防止
if (!defined('INCLUDED_FROM_API')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// 環境変数読み込み
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// データベース接続設定
class DatabaseConfig {
    private static $host = 'mysql80.mizy.sakura.ne.jp';
    private static $dbname = 'mizy_satelite-column1';
    private static $username = 'mizy';
    private static $password = '8rjcp4ck';
    
    public static function getConnection() {
        try {
            $pdo = new PDO(
                "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8",
                self::$username,
                self::$password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}

// AI APIキー設定
class AIConfig {
    public static function getApiKey($model) {
        switch ($model) {
            case 'gpt-4':
            case 'gpt-4o':
                $key = $_ENV['OPENAI_API_KEY'] ?? '';
                if (empty($key)) {
                    throw new Exception("OpenAI API key not configured. Please set OPENAI_API_KEY in .env file.");
                }
                return $key;
            case 'claude-4-sonnet':
                $key = $_ENV['CLAUDE_API_KEY'] ?? '';
                if (empty($key)) {
                    throw new Exception("Claude API key not configured. Please set CLAUDE_API_KEY in .env file.");
                }
                return $key;
            case 'gemini-2.0-flash':
                $key = $_ENV['GEMINI_API_KEY'] ?? '';
                if (empty($key)) {
                    throw new Exception("Gemini API key not configured. Please set GEMINI_API_KEY in .env file.");
                }
                return $key;
            default:
                throw new Exception("Unsupported AI model: " . $model);
        }
    }
}

// 一般設定
class AppConfig {
    public static $timeout_seconds = 300; // 5分
    public static $max_articles_per_batch = 100;
    public static $default_ai_model = 'gpt-4';
}
?>