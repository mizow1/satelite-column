-- 衛星コラムシステム データベース設計

-- サイト情報テーブル
CREATE TABLE sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    features TEXT,
    keywords TEXT,
    analysis_result TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 記事テーブル
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    title VARCHAR(500) NOT NULL,
    seo_keywords TEXT,
    summary TEXT,
    content LONGTEXT,
    ai_model VARCHAR(50),
    status ENUM('draft', 'generated', 'published') DEFAULT 'draft',
    publish_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

-- サイト分析履歴テーブル
CREATE TABLE site_analysis_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    ai_model VARCHAR(50),
    analysis_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    analysis_result TEXT,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

-- AI生成ログテーブル
CREATE TABLE ai_generation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT,
    ai_model VARCHAR(50),
    prompt TEXT,
    response TEXT,
    generation_time DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);

-- 参照URLテーブル
CREATE TABLE reference_urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    url VARCHAR(1000) NOT NULL,
    is_selected BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

-- AI使用ログテーブル（総合的なログ）
CREATE TABLE ai_usage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    article_id INT NULL,
    ai_model VARCHAR(50) NOT NULL,
    usage_type ENUM('site_analysis', 'article_outline', 'article_generation', 'additional_outline') NOT NULL,
    prompt_text TEXT,
    response_text TEXT,
    tokens_used INT DEFAULT 0,
    processing_time DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);

-- インデックス作成
CREATE INDEX idx_articles_site_id ON articles(site_id);
CREATE INDEX idx_articles_status ON articles(status);
CREATE INDEX idx_site_analysis_site_id ON site_analysis_history(site_id);
CREATE INDEX idx_ai_logs_article_id ON ai_generation_logs(article_id);
CREATE INDEX idx_reference_urls_site_id ON reference_urls(site_id);
CREATE INDEX idx_ai_usage_logs_site_id ON ai_usage_logs(site_id);
CREATE INDEX idx_ai_usage_logs_article_id ON ai_usage_logs(article_id);
CREATE INDEX idx_ai_usage_logs_type ON ai_usage_logs(usage_type);
CREATE INDEX idx_ai_usage_logs_model ON ai_usage_logs(ai_model);