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
    content TEXT,
    ai_model VARCHAR(50),
    status ENUM('draft', 'generated', 'published') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

-- サイト分析履歴テーブル
CREATE TABLE site_analysis_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
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

-- インデックス作成
CREATE INDEX idx_articles_site_id ON articles(site_id);
CREATE INDEX idx_articles_status ON articles(status);
CREATE INDEX idx_site_analysis_site_id ON site_analysis_history(site_id);
CREATE INDEX idx_ai_logs_article_id ON ai_generation_logs(article_id);