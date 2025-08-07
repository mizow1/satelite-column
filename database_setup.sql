-- ユーザーテーブル作成
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- 既存テーブルにuser_idカラムを追加（存在しない場合のみ）
-- sites テーブル
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'sites' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE sites ADD COLUMN user_id INT DEFAULT NULL', 'SELECT "user_id already exists in sites table"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- articles テーブル
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'articles' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE articles ADD COLUMN user_id INT DEFAULT NULL', 'SELECT "user_id already exists in articles table"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ai_usage_logs テーブル
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'ai_usage_logs' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE ai_usage_logs ADD COLUMN user_id INT DEFAULT NULL', 'SELECT "user_id already exists in ai_usage_logs table"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ai_generation_logs テーブル
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'ai_generation_logs' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE ai_generation_logs ADD COLUMN user_id INT DEFAULT NULL', 'SELECT "user_id already exists in ai_generation_logs table"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- multilingual_articles テーブル
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'multilingual_articles' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE multilingual_articles ADD COLUMN user_id INT DEFAULT NULL', 'SELECT "user_id already exists in multilingual_articles table"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- multilingual_settings テーブル
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'multilingual_settings' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE multilingual_settings ADD COLUMN user_id INT DEFAULT NULL', 'SELECT "user_id already exists in multilingual_settings table"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- reference_urls テーブル
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'reference_urls' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE reference_urls ADD COLUMN user_id INT DEFAULT NULL', 'SELECT "user_id already exists in reference_urls table"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- site_analysis_history テーブル
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'site_analysis_history' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE site_analysis_history ADD COLUMN user_id INT DEFAULT NULL', 'SELECT "user_id already exists in site_analysis_history table"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 外部キー制約追加（存在しない場合のみ）
-- sites テーブルの外部キー
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'sites' AND CONSTRAINT_NAME = 'fk_sites_user_id');
SET @sql = IF(@constraint_exists = 0, 'ALTER TABLE sites ADD CONSTRAINT fk_sites_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "Foreign key fk_sites_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- articles テーブルの外部キー
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'articles' AND CONSTRAINT_NAME = 'fk_articles_user_id');
SET @sql = IF(@constraint_exists = 0, 'ALTER TABLE articles ADD CONSTRAINT fk_articles_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "Foreign key fk_articles_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ai_usage_logs テーブルの外部キー
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'ai_usage_logs' AND CONSTRAINT_NAME = 'fk_ai_usage_logs_user_id');
SET @sql = IF(@constraint_exists = 0, 'ALTER TABLE ai_usage_logs ADD CONSTRAINT fk_ai_usage_logs_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "Foreign key fk_ai_usage_logs_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ai_generation_logs テーブルの外部キー
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'ai_generation_logs' AND CONSTRAINT_NAME = 'fk_ai_generation_logs_user_id');
SET @sql = IF(@constraint_exists = 0, 'ALTER TABLE ai_generation_logs ADD CONSTRAINT fk_ai_generation_logs_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "Foreign key fk_ai_generation_logs_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- multilingual_articles テーブルの外部キー
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'multilingual_articles' AND CONSTRAINT_NAME = 'fk_multilingual_articles_user_id');
SET @sql = IF(@constraint_exists = 0, 'ALTER TABLE multilingual_articles ADD CONSTRAINT fk_multilingual_articles_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "Foreign key fk_multilingual_articles_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- multilingual_settings テーブルの外部キー
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'multilingual_settings' AND CONSTRAINT_NAME = 'fk_multilingual_settings_user_id');
SET @sql = IF(@constraint_exists = 0, 'ALTER TABLE multilingual_settings ADD CONSTRAINT fk_multilingual_settings_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "Foreign key fk_multilingual_settings_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- reference_urls テーブルの外部キー
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'reference_urls' AND CONSTRAINT_NAME = 'fk_reference_urls_user_id');
SET @sql = IF(@constraint_exists = 0, 'ALTER TABLE reference_urls ADD CONSTRAINT fk_reference_urls_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "Foreign key fk_reference_urls_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- site_analysis_history テーブルの外部キー
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'site_analysis_history' AND CONSTRAINT_NAME = 'fk_site_analysis_history_user_id');
SET @sql = IF(@constraint_exists = 0, 'ALTER TABLE site_analysis_history ADD CONSTRAINT fk_site_analysis_history_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "Foreign key fk_site_analysis_history_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- インデックス追加（存在しない場合のみ）
-- sites テーブルのインデックス
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'sites' AND INDEX_NAME = 'idx_sites_user_id');
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_sites_user_id ON sites(user_id)', 'SELECT "Index idx_sites_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- articles テーブルのインデックス
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'articles' AND INDEX_NAME = 'idx_articles_user_id');
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_articles_user_id ON articles(user_id)', 'SELECT "Index idx_articles_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ai_usage_logs テーブルのインデックス
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'ai_usage_logs' AND INDEX_NAME = 'idx_ai_usage_logs_user_id');
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_ai_usage_logs_user_id ON ai_usage_logs(user_id)', 'SELECT "Index idx_ai_usage_logs_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ai_generation_logs テーブルのインデックス
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'ai_generation_logs' AND INDEX_NAME = 'idx_ai_generation_logs_user_id');
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_ai_generation_logs_user_id ON ai_generation_logs(user_id)', 'SELECT "Index idx_ai_generation_logs_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- multilingual_articles テーブルのインデックス
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'multilingual_articles' AND INDEX_NAME = 'idx_multilingual_articles_user_id');
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_multilingual_articles_user_id ON multilingual_articles(user_id)', 'SELECT "Index idx_multilingual_articles_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- multilingual_settings テーブルのインデックス
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'multilingual_settings' AND INDEX_NAME = 'idx_multilingual_settings_user_id');
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_multilingual_settings_user_id ON multilingual_settings(user_id)', 'SELECT "Index idx_multilingual_settings_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- reference_urls テーブルのインデックス
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'reference_urls' AND INDEX_NAME = 'idx_reference_urls_user_id');
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_reference_urls_user_id ON reference_urls(user_id)', 'SELECT "Index idx_reference_urls_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- site_analysis_history テーブルのインデックス
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'mizy_satelite-column1' AND TABLE_NAME = 'site_analysis_history' AND INDEX_NAME = 'idx_site_analysis_history_user_id');
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_site_analysis_history_user_id ON site_analysis_history(user_id)', 'SELECT "Index idx_site_analysis_history_user_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;