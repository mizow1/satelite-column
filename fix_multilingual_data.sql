-- 既存の不整合データをクリーンアップするSQLスクリプト

-- 1. 空のコンテンツを持つレコードを削除
DELETE FROM multilingual_articles 
WHERE (title IS NULL OR title = '') 
   AND (content IS NULL OR content = '');

-- 2. 重複レコードを削除（より新しいものを保持）
DELETE t1 FROM multilingual_articles t1
INNER JOIN multilingual_articles t2 
WHERE t1.original_article_id = t2.original_article_id 
  AND t1.language_code = t2.language_code 
  AND t1.id < t2.id;

-- 3. UNIQUE制約を追加（既に存在する場合はエラーを無視）
ALTER TABLE multilingual_articles 
ADD CONSTRAINT unique_article_language 
UNIQUE (original_article_id, language_code);

-- 4. 修正後のデータ確認
SELECT 
    original_article_id,
    language_code,
    CASE 
        WHEN title IS NULL OR title = '' THEN 'EMPTY_TITLE'
        ELSE 'OK'
    END as title_status,
    CASE 
        WHEN content IS NULL OR content = '' THEN 'EMPTY_CONTENT'
        ELSE 'OK'
    END as content_status,
    created_at,
    updated_at
FROM multilingual_articles 
WHERE original_article_id = 346 
  AND language_code = 'zh-TW'
ORDER BY created_at DESC;