# 衛星コラム生成システム

占い好きな人向けのコラム記事を自動生成するWebアプリケーションです。

## 機能

- 複数の参照URLからサイトの特徴を分析
- AI（GPT-4、Claude 4 Sonnet、Gemini 2.0 Flash）による記事生成
- 100記事の一括生成
- SEOを意識した記事タイトル・キーワード生成
- CSV出力機能
- サイト別データ管理

## システム構成

### フロントエンド
- `index.html` - メインページ
- `styles.css` - スタイルシート
- `script.js` - JavaScript（フロントエンド制御）

### バックエンド
- `api.php` - API エンドポイント
- `config.php` - 設定ファイル
- `ai_service.php` - AI API連携サービス
- `database.sql` - データベース設計

### 設定・セットアップ
- `.env.example` - 環境変数テンプレート
- `setup.php` - セットアップスクリプト
- `.htaccess` - Apache設定（さくらレンタルサーバー対応）

## セットアップ手順

1. **データベース作成**
   ```bash
   mysql -u root -p
   CREATE DATABASE satellite_column;
   ```

2. **ファイルアップロード**
   - 全ファイルをWebサーバーにアップロード

3. **環境設定**
   - `.env.example`をコピーして`.env`を作成
   - API キーを設定

4. **セットアップ実行**
   ```bash
   php setup.php
   ```

5. **Webサーバー設定**
   - DocumentRootを適切に設定
   - PHPの実行時間制限を調整

## 使用方法

1. **URL入力**
   - 参照URLを入力（複数可）
   - AIモデルを選択

2. **サイト分析**
   - 「実行」ボタンでサイトの特徴を分析

3. **記事概要作成**
   - 「記事概要作成」ボタンで100記事の概要を生成

4. **記事生成**
   - 個別記事生成または一括生成を選択

5. **CSV出力**
   - 生成された記事をCSV形式でダウンロード

## データベース構造

### sites テーブル
- サイト情報と分析結果を保存

### articles テーブル
- 記事情報（タイトル、キーワード、内容等）

### site_analysis_history テーブル
- サイト分析履歴

### ai_generation_logs テーブル
- AI生成ログ

## API仕様

### エンドポイント
- `POST /api.php`

### アクション
- `get_sites` - サイト一覧取得
- `analyze_sites` - サイト分析
- `create_article_outline` - 記事概要作成
- `generate_article` - 記事生成
- `generate_all_articles` - 全記事生成
- `export_csv` - CSV出力

## 注意事項

### さくらレンタルサーバー
- PHP実行時間制限に注意
- `.htaccess`でタイムアウト設定を調整
- メモリ制限の設定が必要な場合がある

### AI API
- 各AIサービスのAPI制限に注意
- 一括生成時はレート制限を考慮
- APIキーの適切な管理が必要

### セキュリティ
- `.env`ファイルの公開防止
- 設定ファイルへのアクセス制限
- 入力値のサニタイゼーション

## トラブルシューティング

### タイムアウトエラー
- `.htaccess`の`max_execution_time`を延長
- `config.php`の`$timeout_seconds`を調整

### メモリ不足
- `memory_limit`を256M以上に設定

### AI API エラー
- APIキーの設定確認
- API制限の確認
- ネットワーク接続の確認

## 開発者向け情報

### 拡張方法
- 新しいAIモデルの追加は`ai_service.php`を編集
- 新しい機能の追加は`api.php`にアクション追加
- フロントエンドの拡張は`script.js`を編集

### ログ
- AI生成ログは`ai_generation_logs`テーブルに保存
- エラーログは適切に設定すること

## ライセンス
- 本システムは開発者が自由に使用・改変可能
- 商用利用も可能
- AIサービスの利用規約を遵守すること