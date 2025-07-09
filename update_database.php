<?php
// データベース更新スクリプト
define('INCLUDED_FROM_API', true);
require_once 'config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Database Update</title></head><body>";
echo "<h1>データベース更新スクリプト</h1>";

try {
    $pdo = DatabaseConfig::getConnection();
    echo "<p>✓ データベースに接続しました。</p>";
    
    // sitesテーブルにai_modelカラムを追加
    $sql = "ALTER TABLE sites ADD COLUMN ai_model VARCHAR(50) DEFAULT NULL";
    
    try {
        $pdo->exec($sql);
        echo "<p>✓ sitesテーブルにai_modelカラムを追加しました。</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>ⓘ ai_modelカラムは既に存在します。</p>";
        } else {
            throw $e;
        }
    }
    
    // 既存のデータにデフォルト値を設定
    $sql = "UPDATE sites SET ai_model = 'gemini-2.0-flash' WHERE ai_model IS NULL";
    $result = $pdo->exec($sql);
    echo "<p>✓ {$result}件のレコードにデフォルト値を設定しました。</p>";
    
    // site_analysis_historyテーブルにも ai_model カラムを追加
    $sql = "ALTER TABLE site_analysis_history ADD COLUMN ai_model VARCHAR(50) DEFAULT NULL";
    try {
        $pdo->exec($sql);
        echo "<p>✓ site_analysis_historyテーブルにai_modelカラムを追加しました。</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>ⓘ site_analysis_historyテーブルのai_modelカラムは既に存在します。</p>";
        } else {
            echo "<p style='color: orange;'>⚠ site_analysis_history更新エラー: " . $e->getMessage() . "</p>";
        }
    }
    
    // テーブル構造を確認
    $sql = "DESCRIBE sites";
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>sitesテーブルの現在の構造</h2>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column['Field']}: {$column['Type']}</li>";
    }
    echo "</ul>";
    
    // site_analysis_historyテーブルの構造も確認
    $sql = "DESCRIBE site_analysis_history";
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>site_analysis_historyテーブルの現在の構造</h2>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column['Field']}: {$column['Type']}</li>";
    }
    echo "</ul>";
    
    echo "<p><strong>✓ データベースの更新が完了しました。</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ エラーが発生しました: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>