# スクレイピング方針（DMM/FANZA）と安全対策

## 要旨
- 第一選択肢：DMM API を利用する（ItemList / ActressSearch / FloorList など）。
- 第二選択肢：APIで得られない情報を取得するためにどうしてもスクレイピングする場合は、下記の安全対策・技術的制約を必ず守る。

---

## 1) API 優先の実装例（推奨）
DmmClient に「作品詳細を API で取得するメソッド」を実装して使う。

PHP（DmmClient）例:

```php
// DmmClient::getItemByContentId は既に用意済みの想定。
// もしItemListで詳細が取れない場合、ActressSearch等の別APIを呼ぶ例。
$item = $dmm-&gt;getItemByContentId('ABC-123');
// $item が null のときのみスクレイピングを検討する（最小化）
```

メリット：
- 利用規約に素直に従えることが多い
- データ形式が安定（JSON）で正規化が容易
- 速度・信頼性が高い（APIレートはあるが管理しやすい）

---

## 2) スクレイピングを行う場合の必須対策
1. 事前確認（必須）
   - DMM/FANZA の利用規約、API 利用規約、著作権・スクレイピングに関する法的制約を確認する（法務レビュー）。
2. 最小化と限定
   - スクレイピングは「APIで取得できないフィールドのみ」に限定する。
   - 頻度は低く（例: 1週間に1回）、かつページ毎の間隔を入れる（例: 1〜5秒のランダム遅延）。
3. 技術的制約
   - TLS 検証を有効にする（CURLOPT_SSL_VERIFYPEER = true）
   - 適切な User-Agent を使い、サイトの利用規約に記載があれば従う。
   - robots.txt の尊重（ただし robots.txt は法的強制力を持たないが、運用上のマナーとして従う）。
4. 認証や age-check の迂回はしない
   - 「age_check_done=1」等の cookie を流用して年齢判定を回避するのは避ける（利用規約違反のリスク）。
5. 安全な解析
   - HTML パーサは `DOMDocument` / `DOMXPath` や `Symfony DomCrawler` を使う（phpQuery は古く脆弱性や互換性の問題がある）。
   - libxml のエラーハンドリングを設定し、外部実体参照 (XXE) を無効化する。
6. ファイル（画像）保存の安全
   - ダウンロードする画像は Content-Type を確認し、許可された MIME（image/jpeg, image/png, image/webp）だけ受け入れる。
   - ファイルサイズ上限を設定（例: 5MB）。
   - 保存先は公開ディレクトリ直下にしない（アップロードディレクトリでもアクセス権を適切に設定）。
   - ファイル名はランダム化（UUID等）、パスは検証済み。
7. レート制御・キュー
   - 同時実行は限定し、ジョブキュー（Redis/RabbitMQ / worker）で処理する。
   - 失敗時は指数バックオフでリトライ、ログ保存。
8. ログ・監査
   - いつ・どのURLを取得したかログに残す（ログは個人情報を含まないよう注意）。
   - エラーや異常アクセスは通知（Slack/email）する。
9. セキュリティとサニタイズ
   - 取得した HTML を直接保存・表示しない。HTMLは `strip_tags` や `wp_kses` のようにホワイトリストでサニタイズ。
   - 外部由来の HTML を DB に入れる場合は XSS 対策を徹底。
10. 法務
   - スクレイピングを行う際は必ず利用規約の観点で問題がないか確認。重大なリスクがある場合は実装しない。

---

## 3) スクレイピングの安全な PHP 実装例（DOMDocument）
```php
function safeFetchHtml(string $url, int $timeout = 30): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER =&gt; true,
        CURLOPT_FOLLOWLOCATION =&gt; true,
        CURLOPT_MAXREDIRS =&gt; 5,
        CURLOPT_TIMEOUT =&gt; $timeout,
        CURLOPT_USERAGENT =&gt; 'VideoStoreBot/1.0 (+https://yourdomain.example/)',
        CURLOPT_SSL_VERIFYPEER =&gt; true,
        CURLOPT_SSL_VERIFYHOST =&gt; 2,
    ]);
    $html = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($http !== 200) return null;
    if ($ct &amp;&amp; !preg_match('#text/html|application/xhtml\+xml#i', $ct)) return null;
    return $html;
}

function parseDescriptionFromHtml(string $html, string $floor = null, string $service = null): string {
    $previousEntityLoader = libxml_disable_entity_loader(true);
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    @$dom-&gt;loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

    $xpath = new DOMXPath($dom);

    $candidates = [
        "//meta[@name='description']/@content",
        "//p[contains(@class,'mg-b20')]",
        "//div[contains(@class,'summary__txt')]",
        "//div[contains(@class,'tx-productComment')]",
    ];

    $desc = '';
    foreach ($candidates as $expr) {
        $nodes = $xpath-&gt;query($expr);
        if ($nodes &amp;&amp; $nodes-&gt;length) {
            if ($nodes-&gt;item(0)-&gt;nodeType === XML_ATTRIBUTE_NODE) {
                $desc = trim($nodes-&gt;item(0)-&gt;nodeValue);
            } else {
                $desc = trim($nodes-&gt;item(0)-&gt;textContent);
            }
            if ($desc) break;
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors(false);
    libxml_disable_entity_loader($previousEntityLoader);

    return $desc ?: '';
}
```

---

## 4) 代替案（推奨）
- "APIのみ" モードを実装する（設定フラグ: SCRAPE_FALLBACK=false）。運用中にスクレイピングが原因で問題が発生したらすぐ切れるようにする。
- スクレイピングが必要なら「バッチ処理（夜間）」で実行し、リアルタイムで行わない（トラフィック負荷を抑える）。

---

## 5) 短い実装手順
1. DmmClient に詳細取得メソッドを強化（ItemList の cid パラメータで可能ならそれで詳細を獲得）。
2. アプリ設定に `SCRAPE_FALLBACK`（bool）を追加。デフォルト false。
3. スクレイピングは別モジュール（scraper）に分離し、独立したキューで処理する。
4. スクレイピング実装は上記 `safeFetchHtml` / `parseDescriptionFromHtml` のように安全設定で行う。
5. 監査ログとアラートを整備する（エラー率が上がったら通知）。
