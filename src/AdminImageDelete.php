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
        // nonce フィールド
        wp_nonce_field('plugin_image_delete_action', 'plugin_image_delete_nonce');

        // 表示ボタン（JS で Ajax 呼び出し）
        echo '<p>この投稿のプラグイン関連画像を削除します。</p>';
        echo '<p><label><input type="checkbox" id="plugin_delete_physical" /> 添付ファイル自体も削除する（完全削除）</label></p>';
        echo '<p><button type="button" class="button button-danger" id="plugin-delete-images-btn" data-postid="'.esc_attr($post->ID).'">画像を削除</button></p>';
        echo '<div id="plugin-delete-images-result" style="margin-top:8px;" ></div>';
    }

    public static function enqueue_admin_scripts($hook) {
        // 管理画面のみ読み込む
        wp_enqueue_script('plugin-admin-image-delete', plugin_dir_url(__FILE__) . '../assets/js/admin-image-delete.js', ['jquery'], '1.0', true);
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

        // 結果を返す
        $msg = implode("\n", $deleted);
        if (!empty($errors)) {
            $msg .= "\n\nエラー:\n" . implode("\n", $errors);
            wp_send_json_error(['message' => $msg]);
        }

        wp_send_json_success(['message' => $msg]);
    }
}
