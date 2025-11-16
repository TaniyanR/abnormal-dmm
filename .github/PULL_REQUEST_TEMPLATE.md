# Pull Request Template / プルリクエストテンプレート
このテンプレートは、abnormal-dmm リポジトリへの PR を整理するための雛形です。日本語を基本に、レビュワーが再現・検証しやすい情報を簡潔に記載してください。

## タイトル（Title）
- 短く分かりやすい要約を記載してください（例: 「API取得処理のページング対応を追加」）

## 概要（Description / Summary）
- この PR が何をするのかを 1〜3 行で簡潔に書いてください。

## 関連 Issue（Related issue(s)）
- 関連する Issue 番号を必ず書いてください（例: Fixes #1, Related: #12）

## 変更タイプ（Type of change）
- 該当するものにチェックを入れてください。
  - [ ] バグ修正 (Bugfix)
  - [ ] 新機能 (New feature)
  - [ ] ドキュメント (Documentation)
  - [ ] チョア（リファクタ・依存更新等）(Chore)

## 変更ファイル（Files changed）
- 主な修正・追加ファイルを列挙してください（例: src/Services/DmmApiService.php, db/migrate_add_product_id.sql）

## 動作確認方法（How to test / Reproduction steps）
レビュワーがローカルで再現できる手順を具体的に書いてください。例:
1. ブランチをチェックアウト:
   - git checkout -b chore/add-pr-template
2. 依存があればインストール:
   - composer install
3. 開発サーバ起動:
   - php -S localhost:8000 -t public
4. DB マイグレーション（必要な場合）:
   - mysql -u root -p your_db < db/schema.sql
5. 管理 API 実行（該当する場合）:
   - curl -X POST "http://localhost:8000/public/api/admin/fetch.php?token=YOUR_ADMIN_TOKEN"

（具体的なコマンドは PR 内容に応じて編集してください）

## マイグレーション / DB変更（Migration）
- DB スキーマ変更がある場合は必ず記載し、適用コマンドを明示してください。
  - 例: mysql -u root -p abnormal_dmm < db/migrate_add_product_id.sql
- ロールバック手順も簡潔に書いてください（もしあれば）。

## セキュリティ / プライバシー考慮（Security / Privacy）
- シークレット（APIキー / トークン等）を含めていないことを明記してください。
- XSS/SQL インジェクションのリスクがある変更はその対策を記載してください。
- 例: Prepared statements を使用、出力は htmlspecialchars でエスケープ済み 等

## パフォーマンス / スケーラビリティ（Performance）
- 大量データ処理や頻繁な API 呼び出しがある場合、考慮事項や改善案を記載してください。

## スクリーンショット / GIF（UI 変更がある場合）
- 変更前後のスクショや GIF を添付してください。

## チェックリスト（Checklist）
- PR 作成者は最低限以下を確認してチェックしてください。
  - [ ] PR の説明が完結に書かれている
  - [ ] 変更に対応するテストを追加・更新した（該当する場合）
  - [ ] lint / static analysis を実行して問題ない
  - [ ] ドキュメントを更新した（README / schema / 設定）
  - [ ] DB マイグレーション手順を記載した（該当する場合）
  - [ ] シークレットや個人情報を含めていない

## レビュワーへの指示（Reviewer guidance）
- 推奨レビュワーやラベル（例: backend, api, security）を指定してください。
- 例: @TaniyanR （オーナー）、ラベル: backend

---

小さな補足:
- テストコマンドの例: composer test, vendor/bin/phpunit (プロジェクトに合わせて)
- 開発サーバ: php -S localhost:8000 -t public
- DB インポート例: mysql -u root -p abnormal_dmm < db/schema.sql
