<?php
/**
 * Mutual RSS shortcode + renderer
 * ショートコード: [mutual_rss feed="https://example.com/feed" count="3"]
 */

class MutualRss {
    public static function init() {
        add_shortcode('mutual_rss', [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    public static function enqueue_styles() {
        wp_register_style('mutual-rss-style', plugin_dir_url(__FILE__) . '../assets/css/mutual-rss.css', [], '1.0');
        wp_enqueue_style('mutual-rss-style');
    }

    public static function shortcode($atts) {
        $atts = shortcode_atts([
            'feed' => '',
            'count' => 3,
        ], $atts, 'mutual_rss');

        $feed_url = esc_url_raw($atts['feed']);
        $count = intval($atts['count']) > 0 ? intval($atts['count']) : 3;
        if (empty($feed_url)) {
            return '';
        }

        if (!function_exists('fetch_feed')) {
            include_once ABSPATH . WPINC . '/feed.php';
        }

        $rss = @fetch_feed($feed_url);
        if (is_wp_error($rss) || !$rss) {
            return '';
        }

        $maxitems = $rss->get_item_quantity($count);
        $items = $rss->get_items(0, $maxitems);

        if (empty($items)) return '';

        ob_start();
        echo '';
        return ob_get_clean();
    }
}
