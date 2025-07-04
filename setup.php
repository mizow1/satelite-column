<?php
// セットアップスクリプト
echo "衛星コラム生成システム セットアップ\n";
echo "==================================\n\n";

// データベース接続テスト
echo "1. データベース接続テスト...\n";
try {
    require_once 'config.php';
    $pdo = DatabaseConfig::getConnection();
    echo "✓ データベース接続成功\n\n";
} catch (Exception $e) {
    echo "✗ データベース接続失敗: " . $e->getMessage() . "\n";
    echo "config.phpのデータベース設定を確認してください。\n\n";
    exit(1);
}

// テーブル作成
echo "2. テーブル作成...\n";
try {
    $sql = file_get_contents('database.sql');
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    echo "✓ テーブル作成成功\n\n";
} catch (Exception $e) {
    echo "✗ テーブル作成失敗: " . $e->getMessage() . "\n";
    echo "database.sqlを確認してください。\n\n";
    exit(1);
}

// 必要なディレクトリ作成
echo "3. 必要なディレクトリの作成...\n";
$directories = ['logs', 'uploads', 'exports'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✓ ディレクトリ作成: $dir\n";
    } else {
        echo "✓ ディレクトリ存在: $dir\n";
    }
}

// 権限チェック
echo "\n4. 権限チェック...\n";
$writeableDirectories = ['logs', 'uploads', 'exports'];
foreach ($writeableDirectories as $dir) {
    if (is_writable($dir)) {
        echo "✓ 書き込み権限OK: $dir\n";
    } else {
        echo "✗ 書き込み権限なし: $dir\n";
        echo "chmod 755 $dir を実行してください。\n";
    }
}

// 環境設定ファイルチェック
echo "\n5. 環境設定ファイルチェック...\n";
if (file_exists('.env')) {
    echo "✓ .envファイル存在\n";
} else {
    echo "! .envファイルが存在しません\n";
    echo "  .env.exampleを参考に.envファイルを作成してください。\n";
}

// PHP拡張モジュールチェック
echo "\n6. PHP拡張モジュールチェック...\n";
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext 拡張モジュールOK\n";
    } else {
        echo "✗ $ext 拡張モジュールが必要です\n";
    }
}

echo "\n==================================\n";
echo "セットアップ完了\n";
echo "==================================\n\n";

echo "次のステップ:\n";
echo "1. .envファイルを作成してAPI キーを設定\n";
echo "2. Webサーバーを起動\n";
echo "3. index.htmlにアクセス\n";
echo "\n注意事項:\n";
echo "- さくらレンタルサーバーでは.htaccessでphp.iniの設定が必要な場合があります\n";
echo "- 長時間処理でタイムアウトが発生する場合は、設定を調整してください\n";
?>