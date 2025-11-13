# abnormal-dmm
A-01【abnormal-dmm(アブノーマル-DMM)】DMMでサンプル動画表示

**DMMアフィリエイト動画紹介サイト構築システム（PHP8 + MySQL8 / フレームワーク不使用）**
黒・白・赤を基調にしたクールなデザインと、充実した管理機能・SEO最適化を備えた動画紹介CMSです。

---

## 🚀 Quick Start / セットアップ

This project uses Docker Compose for the database and plain PHP for the application.

### Prerequisites / 必要なもの
- Docker & Docker Compose
- PHP 8.0 or higher
- DMM Affiliate API credentials (get from: https://affiliate.dmm.com/)

### Setup Instructions / セットアップ手順

1. **Clone the repository / リポジトリをクローン**
   ```bash
   git clone https://github.com/TaniyanR/abnormal-dmm.git
   cd abnormal-dmm
   ```

2. **Configure environment variables / 環境変数を設定**
   ```bash
   cp .env.example .env
   ```
   
   Edit `.env` and add your DMM API credentials:
   ```
   DMM_API_ID=your_api_id_here
   DMM_AFFILIATE_ID=your_affiliate_id_here
   ADMIN_TOKEN=your_secure_random_token_here
   ```

3. **Start the database / データベースを起動**
   ```bash
   docker-compose up -d
   ```
   
   This will:
   - Start a MariaDB container
   - Automatically create the `video_store` database
   - Initialize all required tables

4. **Start the PHP development server / PHP開発サーバーを起動**
   ```bash
   php -S localhost:8000 -t public
   ```

5. **Access the API / APIにアクセス**
   
   Open your browser or API client to: http://localhost:8000

### API Endpoints / APIエンドポイント

#### Get Items List / アイテム一覧を取得
```bash
GET http://localhost:8000/api/items
```

Optional query parameters:
- `keyword`: Search by title or description
- `limit`: Number of items to return (default: 20, max: 100)
- `offset`: Pagination offset (default: 0)

Example:
```bash
curl "http://localhost:8000/api/items?limit=10&offset=0"
```

#### Get Specific Item / 特定のアイテムを取得
```bash
GET http://localhost:8000/api/items/{content_id}
```

Example:
```bash
curl "http://localhost:8000/api/items/example_content_id"
```

#### Fetch Items from DMM API / DMM APIからアイテムを取得 (Admin Only)
```bash
POST http://localhost:8000/admin/fetch-items
Authorization: Bearer YOUR_ADMIN_TOKEN
Content-Type: application/json

{
  "hits": 20,
  "offset": 1
}
```

Example with curl:
```bash
curl -X POST http://localhost:8000/admin/fetch-items \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"hits": 20, "offset": 1}'
```

### Database Management / データベース管理

**Stop the database:**
```bash
docker-compose down
```

**Stop and remove all data:**
```bash
docker-compose down -v
```

**View database logs:**
```bash
docker-compose logs db
```

**Connect to the database directly:**
```bash
docker-compose exec db mysql -u dbuser -p video_store
```

### Development Notes / 開発メモ

- The database is automatically initialized on first run using `db/init.sql`
- Database data persists in a Docker volume
- PHP files are loaded from the repository, so changes are immediately visible
- For production deployment, consider using a proper web server (Apache/Nginx) instead of PHP's built-in server

---

## 🚀 概要

abnormal-dmm は **DMMアフィリエイトAPI** を利用して動画を自動取得し、
サンプル画像・動画・出演者・ジャンル情報などを整理して表示する
**完全自動型の動画紹介サイト** です。

* フレームワーク不使用（**純粋な PHP8 + MySQL8 + PDO**）
* Cron不要（**内部タイマーによる自動API取得**）
* レスポンシブ対応（PC / スマホ）
* SEO最適化 / 構造化データ / 高速化対応
* ユーザー向け表示と管理画面をフル搭載

---

# 🧩 機能一覧

---

## 📺 **1. 表示機能（ユーザー側）**

### ● レイアウト

* 黒・白・赤を基調（管理画面で色変更可能）
* PC：**ヘッダー / 左サイド / メイン / フッター**
* スマホ：1カラム、左サイドはドロワー化
* トップに
  **「当サイトはアフィリエイト広告を使用しています。」**

---

## ● トップページ

* 記事カードのグリッド表示
* 新着順（デフォルト）
* 各カード内容：

  * アイキャッチ画像
  * サンプル動画（DMM埋め込み）
  * 「サンプル画像を見る」ボタン（別ウィンドウ横スクロール）
  * タイトル / 出演者 / 発売日 / ジャンル / シリーズ

---

## ● サイドコンテンツ

* 新着
* 人気
* おすすめ
* 相互リンク
* 表示/非表示 切り替え可能
  （PCはサイド、スマホは下部）

---

## ● 記事詳細ページ

* API＋管理画面編集の本文
* 関連記事（本文下）
* サンプル動画
* サムネイル画像
* 構造化データ（JSON-LD）
* metaタグ自動生成
* canonical URL

---

# 🖼️ **画像仕様（最終版）**

* 最大幅：**800px**
* 最大高さ：**600px**
* **比率は必ず維持**
* 縦または横が最大値に達するまで自然拡大
  （それ以上は拡大しない）
* 大きすぎる画像は比率を維持して縮小
* 画像なし時の代替画像あり
* サンプル画像は別ウィンドウで横スクロール

---

# 🔌 **2. DMMアフィリエイトAPI 自動取得**

### ● 更新間隔（選択式）

* 1時間
* 3時間
* 6時間
* 12時間
* 24時間

### ● 取得件数（選択式）

* 10件
* 100件
* 500件
* 1000件

### ● 取得内容

* 商品ID
* タイトル
* サンプル画像
* サンプル動画
* 出演者
* ジャンル
* シリーズ
* 発売日
* 説明文
* 価格
* 自動タグ
* 商品URL（アフィ付き）

### ● 内部タイマー仕様

* Cron不要
* アクセス時のみ実行可否を判定
* `api_last_run_at` で間隔を判断
* 多重実行防止：`api_lock_until`

### ● API失敗時の対策

* 失敗ログ保存
* 過去成功データのキャッシュ保持（72時間）
* 5回失敗で管理画面に警告表示

---

# 🔖 **3. 自動タグ生成**

* 出演者 / ジャンル / シリーズ / メーカーからタグ生成
* 正規化（表記ゆれ予防）
* スラッグ化
* 1記事 最大10タグ
* 関連記事抽出に利用

---

# 🔗 **4. 相互リンク機能**

* 登録項目：
  **サイト名 / URL / RSS**
* 表示選択：

  * トップページリンク
  * リンク集への掲載
  * RSSリンク
* 組み合わせ自由
* RSS表示：画像付き
* IN / OUT アクセス自動記録
* 表示／非表示切り替え可能
* RSS画像の制御も可能
* PCではサイド、スマホはトップに表示

---

# 🛠 **5. 管理画面（Admin）**

### ● ログイン

* メールアドレス + パスワード
* 初期値：**admin / password**
* パスワードリセット機能あり
* ログインURLは推測されにくい構造

### ● 管理機能一覧

* 記事管理
* カテゴリー管理（女優 / ジャンル / シリーズ）
* 相互リンク管理
* 固定ページ編集（追加・削除可）
* 広告設定（PC/スマホ別）
* 色設定（背景/文字/ボタン）
* API設定
* アクセス解析
* 人気記事・逆アクセスランキング
* メール送受信・通知先設定
* バックアップ（ダウンロード・エクスポート）
* Google Analytics（GA4）設定
* Google Search Console 設定

---

# 📊 **6. アクセス解析**

* PV
* UU
* 参照元
* クリック先
* 検索流入
* 人気ランキング
* **逆アクセスランキング（ON/OFF）**
* 時間 / 日 / 月のグラフ表示

---

# 🔍 **7. 検索機能**

* サイト内検索
* タイトル / 出演者 / 説明文

---

# 🧭 **8. SEO / セキュリティ**

### ■ SEO

* title / description 自動生成
* URL最適化
* canonical URL
* alt属性自動付与
* JSON-LD（WebSite / Article / Breadcrumb）
* sitemap.xml / robots.txt 自動生成

### ■ セキュリティ

* HTTPS強制
* HSTS
* CSRF
* XSS対策
* SQLプリペアド
* cookie セキュア設定
* Clickjacking防止

---

# 📨 **9. お問い合わせ**

* 管理画面で受信・返信可能
* 通知メール設定あり

---

# 📦 **10. バックアップ**

* DBエクスポート
* 設定ファイルのダウンロード
* 手動復元可能

---

# 🔄 **11. RSS機能**

* ランダムRSS配信
* 通常RSS配信
* キャッシュ対応

---

# 📝 **12. クレジット表記**

```
<a href="https://affiliate.dmm.com/api/">
  <img src="https://pics.dmm.com/af/web_service/com_135_17.gif" 
       width="135" height="17" 
       alt="WEB SERVICE BY DMM.com" />
</a>
```

---

# ⚙ インストール手順

1. `.env.php` を編集（DB・APIキー）
2. `/install/installer.php` を実行
3. `/admin/login` へアクセス
4. 設定 → API・色設定・広告設定を調整
5. サイト公開

---
