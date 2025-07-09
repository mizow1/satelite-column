-- sitesテーブルにai_modelカラムを追加
ALTER TABLE sites ADD COLUMN ai_model VARCHAR(50) DEFAULT NULL;

-- 既存のデータにデフォルト値を設定（必要に応じて）
UPDATE sites SET ai_model = 'gemini-2.0-flash' WHERE ai_model IS NULL;