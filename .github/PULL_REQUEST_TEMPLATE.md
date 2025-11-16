# Pull Request Template / プルリクエストテンプレート

このテンプレートは、プルリクエストの説明を標準化し、レビュアーが変更内容を理解しやすくするためのものです。  
This template standardizes PR descriptions to help reviewers understand changes efficiently.

各セクションを埋めてください。該当しない項目は削除または「N/A」と記入してください。  
Please fill in each section. Delete or mark "N/A" for sections that don't apply.

---

## タイトル / Title
<!-- 短く説明的なタイトルをつけてください -->
<!-- Provide a short, descriptive title -->

## 概要 / Description
<!-- このPRが何をするのか、簡潔に説明してください -->
<!-- Briefly summarize what this PR does -->



## 関連Issue / Related Issue(s)
<!-- 関連するIssueをリンクしてください（例: #1, #2） -->
<!-- Link related issues (e.g., #1, #2) -->

- Closes #
- Related to #

## 変更の種類 / Type of Change
<!-- 該当するものにチェックを入れてください -->
<!-- Check all that apply -->

- [ ] バグ修正 / Bugfix
- [ ] 新機能 / New feature
- [ ] ドキュメント更新 / Documentation
- [ ] その他（雑務、リファクタリングなど） / Chore (refactoring, cleanup, etc.)

## 変更されたファイル / Files Changed
<!-- 主要な変更ファイルを簡潔にリストしてください -->
<!-- List main files modified -->

- `path/to/file1.php` - 変更内容の説明
- `path/to/file2.php` - 変更内容の説明

## テスト方法 / How to Test
<!-- レビュアーがローカルで変更を再現・テストする手順を記載してください -->
<!-- Provide steps for reviewers to reproduce/test changes locally -->

### 環境セットアップ / Environment Setup
```bash
# リポジトリをクローンしてブランチをチェックアウト
git clone https://github.com/TaniyanR/abnormal-dmm.git
cd abnormal-dmm
git checkout <branch-name>

# 環境変数を設定
cp .env.example .env
# .envファイルを編集してAPIキーとDB設定を追加

# データベースを起動
docker-compose up -d

# PHPサーバーを起動
php -S localhost:8000 -t public
```

### テストコマンド / Test Commands
```bash
# テストを実行（該当する場合）
# composer test  # If composer is configured
# php tests/run_tests.php  # If test file exists

# 手動テスト例
curl http://localhost:8000/api/items
curl -X POST http://localhost:8000/api/admin/fetch \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{"hits": 10, "offset": 1}'
```

### 期待される結果 / Expected Results
<!-- テスト実行後の期待される動作を記載してください -->
<!-- Describe expected behavior after testing -->



## マイグレーション / DB Changes
<!-- データベースマイグレーションが必要かどうかを示してください -->
<!-- Indicate whether database migrations are required -->

- [ ] マイグレーション不要 / No migration required
- [ ] マイグレーションが必要 / Migration required

### マイグレーション実行方法 / How to Run Migration
<!-- マイグレーションが必要な場合、実行方法を記載してください -->
<!-- If migration is required, provide commands to run it -->

```bash
# データベーススキーマを適用
mysql -h 127.0.0.1 -P 3306 -u video_user -p video_store < db/schema.sql

# または docker-composeで実行
docker-compose exec db mysql -u video_user -p video_store < db/schema.sql
```

## セキュリティ / プライバシー考慮事項 / Security & Privacy Considerations
<!-- セキュリティやプライバシーに関する懸念事項を記載してください -->
<!-- Note any concerns about secrets, tokens, XSS/SQL injection risks, etc. -->

- [ ] シークレットやトークンが含まれていない / No secrets or tokens included
- [ ] XSS対策を実装済み / XSS prevention implemented
- [ ] SQLインジェクション対策済み / SQL injection prevention applied
- [ ] 該当なし / N/A

### 詳細 / Details
<!-- セキュリティ考慮事項の詳細を記載してください -->
<!-- Provide details on security considerations -->



## パフォーマンス / スケーラビリティ / Performance & Scalability
<!-- パフォーマンスやスケーラビリティに影響がある場合は記載してください -->
<!-- Note any performance or scalability impacts if relevant -->

- [ ] パフォーマンスへの影響なし / No performance impact
- [ ] パフォーマンス改善 / Performance improvement
- [ ] パフォーマンスへの影響あり（詳細は下記） / Performance impact (see details below)

### 詳細 / Details
<!-- パフォーマンス関連の詳細を記載してください -->
<!-- Provide details on performance considerations -->



## スクリーンショット / GIF / Screenshots or GIFs
<!-- UI変更がある場合、スクリーンショットやGIFを添付してください -->
<!-- If UI changes are included, attach screenshots or GIFs -->

### 変更前 / Before


### 変更後 / After


## チェックリスト / Checklist
<!-- PRを提出する前に、以下の項目を確認してください -->
<!-- Verify the following before submitting the PR -->

- [ ] テストを追加・更新した / Tests added or updated
- [ ] コードがリントを通過する / Code passes linting
- [ ] ドキュメントを更新した（該当する場合） / Documentation updated (if applicable)
- [ ] CHANGELOGを更新した（該当する場合） / CHANGELOG updated (if applicable)
- [ ] PR説明が完全である / PR description is complete
- [ ] コードレビューを依頼した / Code review requested
- [ ] セキュリティリスクを確認した / Security risks reviewed
- [ ] ローカルでテストして動作確認した / Tested locally and verified functionality

## レビュアー / 推奨ラベル / Reviewer Guidance
<!-- 推奨レビュアーやラベルを追加してください -->
<!-- Recommend reviewers or labels to add -->

### 推奨レビュアー / Recommended Reviewers
<!-- @username の形式でレビュアーをメンションしてください -->
<!-- Mention reviewers using @username format -->

- @

### 推奨ラベル / Suggested Labels
<!-- 該当するラベルを追加してください -->
<!-- Add relevant labels -->

- `bugfix` / `enhancement` / `documentation` / `chore`
- `needs-review` / `work-in-progress`
- `high-priority` / `low-priority`

---

## 追加情報 / Additional Notes
<!-- その他、レビュアーが知っておくべき情報を記載してください -->
<!-- Any additional information reviewers should know -->


