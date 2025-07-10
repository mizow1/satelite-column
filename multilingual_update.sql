-- 多言語対応テーブル追加

-- 多言語記事テーブル
CREATE TABLE multilingual_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_article_id INT NOT NULL,
    language_code VARCHAR(10) NOT NULL,
    title VARCHAR(500) NOT NULL,
    seo_keywords TEXT,
    summary TEXT,
    content LONGTEXT,
    ai_model VARCHAR(50),
    status ENUM('draft', 'generated', 'published') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (original_article_id) REFERENCES articles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_article_language (original_article_id, language_code),
    INDEX idx_multilingual_articles_original_id (original_article_id),
    INDEX idx_multilingual_articles_language (language_code),
    INDEX idx_multilingual_articles_status (status)
);

-- 多言語生成設定テーブル
CREATE TABLE multilingual_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    language_code VARCHAR(10) NOT NULL,
    language_name VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_language (site_id, language_code),
    INDEX idx_multilingual_settings_site_id (site_id),
    INDEX idx_multilingual_settings_enabled (is_enabled)
);

-- デフォルトの多言語設定を挿入
INSERT INTO multilingual_settings (site_id, language_code, language_name, is_enabled)
SELECT 
    s.id,
    lang.code,
    lang.name,
    FALSE
FROM sites s
CROSS JOIN (
    SELECT 'en' as code, 'English' as name
    UNION ALL SELECT 'zh-CN', '中文（简体）'
    UNION ALL SELECT 'zh-TW', '中文（繁體）'
    UNION ALL SELECT 'ko', '한국어'
    UNION ALL SELECT 'es', 'Español'
    UNION ALL SELECT 'ar', 'العربية'
    UNION ALL SELECT 'pt', 'Português'
    UNION ALL SELECT 'fr', 'Français'
    UNION ALL SELECT 'de', 'Deutsch'
    UNION ALL SELECT 'ru', 'Русский'
    UNION ALL SELECT 'it', 'Italiano'
) lang
WHERE NOT EXISTS (
    SELECT 1 FROM multilingual_settings 
    WHERE site_id = s.id AND language_code = lang.code
);