<?php
/**
 * ImageDeleteLogger
 * - 画像削除の操作ログを専用テーブルに記録します。
 */

if (!defined('ABSPATH')) {
    exit;
}

class ImageDeleteLogger {
    const TABLE_SUFFIX = 'image_delete_log';

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function install_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            deleted_items LONGTEXT NOT NULL,
            delete_physical TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public static function log($post_id, $user_id, array $deleted_items, $delete_physical = false) {
        global $wpdb;
        $table = self::get_table_name();
        $deleted_json = wp_json_encode(array_values($deleted_items));
        if ($deleted_json === false) {
            error_log('ImageDeleteLogger: Failed to encode deleted_items to JSON');
            $deleted_json = '[]';
        }

        $result = $wpdb->insert(
            $table,
            [
                'post_id' => intval($post_id),
                'user_id' => intval($user_id),
                'deleted_items' => $deleted_json,
                'delete_physical' => $delete_physical ? 1 : 0,
                'created_at' => current_time('mysql', 1),
            ],
            ['%d', '%d', '%s', '%d', '%s']
        );

        return (bool) $result;
    }

    public static function get_recent($limit = 50) {
        global $wpdb;
        $table = esc_sql(self::get_table_name());
        $limit = intval($limit);
        $sql = $wpdb->prepare("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d", $limit);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!$rows) return [];
        return $rows;
    }
}
