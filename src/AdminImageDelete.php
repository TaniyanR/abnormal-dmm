<?php
/**
 * 管理画面：投稿の画像を削除する AJAX ハンドラ & メタボックス
 * 使い方:
 *  - プラグインの初期化時に AdminImageDelete::init() を呼ぶ
 */

class AdminImageDelete {
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        add_action('wp_ajax_plugin_delete_images', [__CLASS__, 'ajax_delete_images']);
    }

    public static function add_meta_box() {
        add_meta_box(
            'plugin-image-delete',
            '画像管理（プラグイン）',
            [__CLASS__, 'render_meta_box'],
            ['post', 'page'], // 必要に応じてカスタム投稿タイプを追加
            'side',
            'high'
        );
    }

    public static function render_meta_box($post) {
        // nonce フィールド（HTMLにも post_id を埋める）
        wp_nonce_field('plugin_image_delete_action', 'plugin_image_delete_nonce');

        $post_id = (int) $post->ID;

        // 管理画面用の UI を出力（JS がボタンをハンドル）
        echo '<p>この投稿のプラグイン関連画像を削除します。物理ファイルも削除する場合は下のチェックを入れてください。</p>';

        // 削除物理ファイルのオプション
        echo '<p>';
        echo '<label>';
        echo '<input type="checkbox" id="plugin-delete-physical" name="plugin-delete-physical" value="1" /> ';
        echo esc_html('画像ファイルもサーバから削除する（true = 実ファイル削除）');
        echo '</label>';
        echo '</p>';

        // ボタン類
        echo '<p>';
        echo '<button type="button" class="button button-secondary" id="plugin-delete-images-btn" data-post-id="' . esc_attr($post_id) . '">' . esc_html('画像を削除') . '</button> ';
        echo '<span id="plugin-delete-images-spinner" style="display:none;margin-left:8px;">' . esc_html('処理中...') . '</span>';
        echo '</p>';

        // 結果表示領域
        echo '<div id="plugin-delete-images-result" style="margin-top:8px; white-space:pre-wrap;"></div>';
    }

    public static function enqueue_admin_scripts($hook) {
        // 投稿編集画面以外では読み込まない
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        // 管理画面スクリプトを読み込む（assets/js/admin-image-delete.js を想定）
        wp_enqueue_script(
            'plugin-admin-image-delete',
            plugin_dir_url(__FILE__) . '../assets/js/admin-image-delete.js',
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('plugin-admin-image-delete', 'PluginImageDelete', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('plugin_image_delete_action'),
        ]);
        // スタイルは不要なら読み込まない
    }

    public static function ajax_delete_images() {
        // capability と nonce チェック
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません。']);
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'plugin_image_delete_action')) {
            wp_send_json_error(['message' => '不正なリクエストです（nonce）。']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $delete_physical = !empty($_POST['delete_physical']) ? true : false;

        if (!$post_id) {
            wp_send_json_error(['message' => 'post_id が指定されていません。']);
        }

        $deleted = [];
        $errors = [];

        // 1) 投稿サムネイル（featured image）
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            // 投稿のサムネイルを外す
            delete_post_thumbnail($post_id);
            if ($delete_physical) {
                if (!wp_delete_attachment($thumb_id, true)) {
                    $errors[] = "サムネイル（ID {$thumb_id}）の削除に失敗しました。";
                } else {
                    $deleted[] = "サムネイル（ID {$thumb_id}）を削除しました。";
                }
            } else {
                $deleted[] = "サムネイル（ID {$thumb_id}）の参照を削除しました。";
            }
        }

        // 2) プラグイン独自メタに保存している画像（例: image_list / sample_images / image_large など）
        $meta_keys = ['image_list', 'image_small', 'image_large', 'sample_images', 'sample_movies', 'affiliate_image']; // 必要に応じて追加
        foreach ($meta_keys as $mkey) {
            $val = get_post_meta($post_id, $mkey, true);
            if (empty($val)) continue;

            // JSON 配列か文字列かに対応
            $urls = [];
            if (is_string($val)) {
                // try JSON
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // may be array of URLs or array of objects
                    foreach ($decoded as $it) {
                        if (is_string($it)) $urls[] = $it;
                        elseif (is_array($it) && isset($it['url'])) $urls[] = $it['url'];
                    }
                } else {
                    // may be single URL
                    $urls[] = $val;
                }
            } elseif (is_array($val)) {
                foreach ($val as $it) {
                    if (is_string($it)) $urls[] = $it;
                    elseif (is_array($it) && isset($it['url'])) $urls[] = $it['url'];
                }
            }

            // Delete references and maybe attachments
            update_post_meta($post_id, $mkey, ''); // clear meta
            $deleted[] = "メタ {$mkey} の参照を削除しました。";

            if ($delete_physical) {
                foreach ($urls as $url) {
                    $att_id = attachment_url_to_postid($url);
                    if ($att_id) {
                        if (!wp_delete_attachment($att_id, true)) {
                            $errors[] = "添付ファイル {$att_id} の削除に失敗しました。";
                        } else {
                            $deleted[] = "添付ファイル {$att_id} を削除しました。";
                        }
                    }
                }
            }
        }

        if (class_exists('ImageDeleteLogger')) {
            try {
                ImageDeleteLogger::log($post_id, get_current_user_id(), $deleted, $delete_physical);
            } catch (Throwable $e) {
                $errors[] = 'ログ記録に失敗しました: ' . $e->getMessage();
            }
        }

        // 結果を返す
        $msg = implode("\n", $deleted);
        if (!empty($errors)) {
            $msg .= "\n\nエラー:\n" . implode("\n", $errors);
            wp_send_json_error(['message' => $msg]);
        }

        wp_send_json_success(['message' => $msg]);
    }
}
