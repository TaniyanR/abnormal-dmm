<?php
// .env.php - ローカル環境用サンプル（必ずローカルで編集してから使ってください）
// IMPORTANT: このファイルは機密情報を含みます。公開リポジトリにはコミットしないでください.
return [
    // 管理用トークン（任意の長めのランダム文字列）
    'ADMIN_TOKEN' => 'change_this_to_a_strong_token',

    // DB 接続情報（XAMPP のデフォルト例）
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 3306,
    'DB_NAME' => 'abnormal_dmm',   // 先に作成したデータベース名を指定
    'DB_USER' => 'root',
    'DB_PASS' => '',               // パスワードが無ければ空文字 ''

    // DMM API（fetch 実行する場合に必要）
    'DMM_API_ID' => '',
    'DMM_AFFILIATE_ID' => '',
];
