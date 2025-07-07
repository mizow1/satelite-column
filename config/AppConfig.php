<?php
class AppConfig {
    const AI_MODELS = [
        'gpt-4o' => [
            'name' => 'GPT-4o',
            'max_tokens' => 16000,
            'temperature' => 0.7,
            'cost_per_token' => 0.00003
        ],
        'claude-4-sonnet' => [
            'name' => 'Claude 4 Sonnet',
            'max_tokens' => 16000,
            'temperature' => 0.7,
            'cost_per_token' => 0.00003
        ],
        'gemini-2.0-flash' => [
            'name' => 'Gemini 2.0 Flash',
            'max_tokens' => 16000,
            'temperature' => 0.7,
            'cost_per_token' => 0.00002
        ]
    ];
    
    const CONTENT_SETTINGS = [
        'max_content_length' => 50000,
        'max_url_count' => 10,
        'article_min_length' => 10000,
        'outline_articles_count' => 100,
        'additional_articles_count' => 10,
        'curl_timeout' => 30,
        'generation_delay' => 100000
    ];
    
    const SECURITY_SETTINGS = [
        'max_input_length' => 10000,
        'allowed_domains' => [],
        'blocked_domains' => [],
        'rate_limit_per_hour' => 1000,
        'enable_csrf_protection' => true
    ];
    
    const ERROR_MESSAGES = [
        'site_not_found' => 'Site not found',
        'article_not_found' => 'Article not found',
        'invalid_json' => 'Invalid JSON input',
        'invalid_action' => 'Invalid action',
        'ai_service_empty' => 'AI service returned empty content',
        'no_valid_content' => 'No valid content found',
        'database_error' => 'Database error',
        'curl_error' => 'Failed to fetch content',
        'parse_error' => 'Failed to parse article outline'
    ];
    
    const PROMPTS = [
        'analysis_intro' => '以下のサイトの内容を分析して、占い好きな人に向けたコラム記事を作成するための特徴とキーワードを分析してください。',
        'outline_intro' => '以下のサイト分析結果を基に、占い好きな人向けのコラム記事を作成してください。',
        'article_intro' => '以下の記事概要を基に、占い好きな人向けの詳細なコラム記事を作成してください。'
    ];
    
    public static function getAiModel($modelKey) {
        return self::AI_MODELS[$modelKey] ?? null;
    }
    
    public static function getContentSetting($key) {
        return self::CONTENT_SETTINGS[$key] ?? null;
    }
    
    public static function getSecuritySetting($key) {
        return self::SECURITY_SETTINGS[$key] ?? null;
    }
    
    public static function getErrorMessage($key) {
        return self::ERROR_MESSAGES[$key] ?? 'Unknown error';
    }
    
    public static function getPrompt($key) {
        return self::PROMPTS[$key] ?? '';
    }
    
    public static function validateAiModel($modelKey) {
        return isset(self::AI_MODELS[$modelKey]);
    }
    
    public static function getAllAiModels() {
        return self::AI_MODELS;
    }
    
    public static function getEnvironmentConfig() {
        return [
            'debug' => $_ENV['DEBUG'] ?? false,
            'log_level' => $_ENV['LOG_LEVEL'] ?? 'INFO',
            'max_execution_time' => $_ENV['MAX_EXECUTION_TIME'] ?? 300,
            'memory_limit' => $_ENV['MEMORY_LIMIT'] ?? '256M'
        ];
    }
}