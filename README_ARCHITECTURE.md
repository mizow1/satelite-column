# 衛星コラム生成システム - アーキテクチャ詳細

## システム概要

このシステムは占い好きな読者向けのコラム記事を自動生成するWebアプリケーションです。複数のAI（GPT-4、Claude 4 Sonnet、Gemini 2.0 Flash）を使用して、参照サイトの特徴を分析し、SEOを考慮した記事を100件まで一括生成できます。

## ファイル構成

### フロントエンド
- **index.html** - メインUI（URL入力、サイト分析、記事生成）
- **styles.css** - スタイルシート
- **script.js** - フロントエンド制御（AutoGenerationManager、API通信）
- **service-worker.js** - バックグラウンド処理（Web Worker）

### バックエンドAPI
- **api.php** - メインAPIエンドポイント（全機能統合）
- **api_new.php** - 新しいMVC構造のAPIエンドポイント
- **ai_service.php** - AI API連携サービス

### 設定・データベース
- **config.php** - データベース接続設定
- **database.sql** - データベース設計
- **multilingual_update.sql** - 多言語機能のスキーマ更新

### MVC構造（新システム）
```
config/
├── AppConfig.php         # アプリケーション設定
controllers/
├── ApiController.php     # API制御
services/
├── ArticleService.php    # 記事関連サービス
├── ContentProcessor.php  # コンテンツ処理
├── PromptGenerator.php   # AIプロンプト生成
└── SiteService.php      # サイト分析サービス
js/modules/
└── ApiClient.js         # API通信クライアント
```

### セットアップ・メンテナンス
- **setup.php** - 初期セットアップ
- **update_database.php** - データベース更新
- **fix_database.sql** - データベース修正
- **handler.php** - 汎用ハンドラー

## 主要機能

### 1. サイト分析
- **入力**: 参照URLの複数入力
- **処理**: サイトの特徴を分析、占い・SEOキーワードを抽出
- **出力**: Markdown形式の分析結果

### 2. 記事生成
- **概要生成**: 100記事の概要（タイトル、キーワード、要約）を一括生成
- **記事生成**: 個別または一括で記事本文を生成
- **多言語対応**: 11言語での記事生成

### 3. データ管理
- **サイト別管理**: プルダウンでサイト切り替え
- **履歴管理**: 分析結果と生成記事の履歴保存
- **CSV出力**: 全記事のCSV形式エクスポート

### 4. URL取得機能
- **関連URL取得**: ベースURLから関連URLを自動取得
- **手動URL追加**: 手動でURLを追加・削除
- **URL管理**: チェックボックスで選択管理

## データベース設計

### テーブル構成
```sql
sites                    # サイト情報
├── id (PRIMARY KEY)
├── name                 # サイト名
├── urls                 # 参照URL（JSON配列）
├── analysis_result      # 分析結果
├── ai_model            # 使用AIモデル
├── created_at          # 作成日時
└── updated_at          # 更新日時

articles                 # 記事情報
├── id (PRIMARY KEY)
├── site_id (FOREIGN KEY)
├── title               # 記事タイトル
├── keywords            # SEOキーワード
├── summary             # 記事要約
├── content             # 記事本文
├── language            # 言語（ja, en, etc）
├── created_at          # 作成日時
└── updated_at          # 更新日時

site_analysis_history   # 分析履歴
├── id (PRIMARY KEY)
├── site_id (FOREIGN KEY)
├── analysis_result     # 分析結果
├── ai_model           # 使用AIモデル
└── created_at         # 作成日時

ai_generation_logs      # AI使用ログ
├── id (PRIMARY KEY)
├── site_id (FOREIGN KEY)
├── ai_model           # 使用AIモデル
├── action             # 実行アクション
├── tokens_used        # 使用トークン数
├── execution_time     # 実行時間
└── created_at         # 作成日時
```

## API仕様

### エンドポイント
- **POST /api.php** - 全機能統合API
- **POST /api_new.php** - 新MVC構造API

### アクション（api.php）
```php
get_sites               # サイト一覧取得
analyze_sites           # サイト分析実行
crawl_urls             # URL取得
create_article_outline  # 記事概要生成
generate_article        # 記事生成
generate_all_articles   # 全記事一括生成
generate_multilingual_articles  # 多言語記事生成
export_csv             # CSV出力
get_ai_logs            # AI使用ログ取得
update_multilingual_settings  # 多言語設定更新
```

### レスポンス形式
```json
{
    "success": true,
    "data": { ... },
    "message": "処理が完了しました"
}
```

## AI統合

### 対応モデル
- **GPT-4o** - OpenAI API
- **Claude 4 Sonnet** - Anthropic API
- **Gemini 2.0 Flash** - Google AI API

### AI使用パターン
1. **サイト分析**: 複数URLの特徴分析
2. **記事概要生成**: 100記事の概要生成
3. **記事生成**: 個別記事の本文生成
4. **多言語翻訳**: 11言語への翻訳

## セキュリティ対策

### 基本設定
- **HTTPSリダイレクト**: さくらレンタルサーバー対応
- **CORS設定**: 適切なオリジン制限
- **入力サニタイゼーション**: XSS/SQLインジェクション対策

### ファイル保護
- **設定ファイル**: .htaccessでアクセス制限
- **APIキー**: 環境変数での管理
- **ログファイル**: 適切なアクセス権限

## パフォーマンス最適化

### タイムアウト対策
- **PHP実行時間**: 300秒に設定
- **メモリ制限**: 256MBに設定
- **バッファリング**: 出力バッファリング使用

### 非同期処理
- **Web Worker**: 大量記事生成時の非同期処理
- **プログレスバー**: 処理進捗の表示
- **中断機能**: 処理中断の実装

## 運用環境

### さくらレンタルサーバー対応
- **PHP設定**: 実行時間、メモリ制限の動的設定
- **MySQL**: データベース接続設定
- **ファイル権限**: 適切な権限設定

### 必要要件
- **PHP**: 7.4以上
- **MySQL**: 5.7以上
- **モジュール**: curl, json, mbstring

## 今後の拡張予定

### 機能拡張
- **新AIモデル**: 新しいAIモデルの追加
- **出力形式**: HTML、XML形式の出力
- **スケジューリング**: 定期実行機能

### パフォーマンス向上
- **キャッシュ**: Redis/Memcached統合
- **CDN**: 静的ファイル配信の最適化
- **データベース**: インデックス最適化

## 整理・削除済みファイル

### 削除済み
- **test_*.php** - 全てのテストファイル
- **debug.html** - デバッグ用HTMLファイル
- **api_simple.php** - 簡易APIファイル

### 整理済み
- **script.js** - alert()関数をUI通知に変更
- **api.php** - 不要なerror_log()を削除
- **index.html** - デバッグ用テストボタンを削除

## 開発者向け情報

### 新機能追加方法
1. **AIモデル追加**: ai_service.phpに新モデルの処理を追加
2. **API機能追加**: api.phpに新しいactionを追加
3. **UI機能追加**: script.jsにイベントハンドラーを追加

### デバッグ方法
- **ログ確認**: error_log()でのログ出力
- **APIテスト**: PostmanでのAPIテスト
- **データベース**: phpMyAdminでのデータ確認

### コード規約
- **PHP**: PSR-4準拠
- **JavaScript**: ES6+モジュール構造
- **CSS**: BEM命名規則
- **SQL**: 適切なインデックス設計