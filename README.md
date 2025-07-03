
# 占い特化型記事自動生成ツール

本ツールは、指定した複数のWebサイト（主に占い関連）を分析し、SEOに強い特徴を抽出。そこから100本の記事を企画・生成し、CSV形式で出力できるWebアプリケーションです。  
さくらサーバー環境での運用を前提に構築されています。

---

## 🧠 主な機能

### 🔗 URL入力
- HTMLフォーム上で参照URL入力欄を1つ提供。
- 「追加」ボタンで入力欄を増やせます（空欄は無視）。

### 📊 サイト分析
- 実行ボタンで指定サイトを分析。
- 各サイトの特徴（特に占い好きなユーザーに響く要素）を抽出。
- SEOを意識したキーワード・語彙・コンテンツ傾向をMarkdown形式で表示。

### 📝 記事概要作成
- 「記事概要作成」ボタンを表示。
- 各サイトに基づいてSEOに適した以下の要素を自動生成（100件分）：
  - 記事タイトル
  - キーワード
  - 概要

### ✍️ 記事生成
- 各記事に個別の「記事作成」ボタンを設置。
- 全件一括で作成する「一括作成」ボタンも用意。
- 作成済み記事には、タイトルに全文リンクが付与され、クリックで全文表示。

### 📥 CSV出力
- 作成済み記事をまとめて1つのCSVファイルとしてダウンロード可能。

### 💾 データ保存（MySQL）
- 全ての生成データはMySQLに永続化。
- サイトごとのデータをまとめ、UIからの切り替えが可能。

---

## 🧩 使用AIモデル

以下のモデルから指定可能です（モデルは設定画面またはURLパラメータで選択）：

- **OpenAI GPT-4**
- **Claude 4 Sonnet**
- **Gemini 2.0 Flash**

---

## 🖥️ ユーザーインターフェース概要

- 参照URL入力フォーム（複数可）
- 「実行」→ サイト特徴分析表示（Markdown）
- 「記事概要作成」→ 100記事分の概要表を表示
- 「記事作成」「一括作成」→ 記事全文を生成・リンク表示
- 「CSV出力」→ 全記事の一括CSVダウンロード
- プルダウンによるサイト切替表示（過去データ参照用）

---

## 🧱 技術スタック

| 項目           | 使用技術                  |
|----------------|---------------------------|
| フロントエンド | HTML / JavaScript / CSS / Bootstrap または Vue.js（推奨） |
| バックエンド   | Python (Flask or FastAPI) |
| データベース   | MySQL                     |
| AI連携         | OpenAI API / Claude API / Gemini API |
| サーバー       | さくらのレンタルサーバー（CGI対応, Python動作可） |

---

## ⚠️ 注意点

- さくらサーバーでは長時間処理による**タイムアウト**に注意。
  - 記事生成など重い処理はバックエンドで非同期実行（例：Celery + Redis 等）を推奨。
- APIキーの安全な管理が必要です（環境変数またはMySQL側に暗号化保存）。

---

## 🚀 今後の機能拡張（予定）

- ジャンル別テンプレート適用（恋愛運・金運・仕事運など）
- カテゴリフィルタによる記事分類
- SNS投稿用タイトル・ハッシュタグ自動生成

---

## 📂 ディレクトリ構成（例）

```
project-root/
├── templates/
│   └── index.html
├── static/
│   └── js/
│       └── main.js
├── app/
│   ├── routes.py
│   ├── ai_engine.py
│   ├── article_generator.py
│   └── db.py
├── data/
│   └── output.csv
├── .env
├── requirements.txt
└── README.md
```

---

## 📦 セットアップ方法

```bash
git clone https://github.com/your-username/fortune-article-generator.git
cd fortune-article-generator

# 仮想環境を作成して起動
python3 -m venv venv
source venv/bin/activate

# 依存ライブラリをインストール
pip install -r requirements.txt

# MySQL設定（.envに記載）
# 起動
python app/routes.py
```

---

## 👤 ライセンス

MIT License

---

## 📬 お問い合わせ

不具合報告・機能要望は [Issues](https://github.com/your-username/fortune-article-generator/issues) からお願いします。
