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
            return '<!-- mutual_rss: feed URL 未指定 -->';
        }

        if (!function_exists('fetch_feed')) {
            include_once ABSPATH . WPINC . '/feed.php';
        }

        $rss = @fetch_feed($feed_url);
        if (is_wp_error($rss) || !$rss) {
            return '<!-- mutual_rss: feed 取得失敗 -->';
        }

        $maxitems = $rss->get_item_quantity($count);
        $items = $rss->get_items(0, $maxitems);

        if (empty($items)) return '';

        ob_start();
        echo '<div class="mutual-rss-widget">';
        foreach ($items as $item) {
            $image = '';
            $enclosure = $item->get_enclosure();
            if ($enclosure && $enclosure->get_link()) {
                $image = esc_url($enclosure->get_link());
            } else {
                $ns = $item->get_item_tags('http://search.yahoo.com/mrss/', 'content');
                if (!empty($ns) && isset($ns[0]['attribs']['']['url'])) {
                    $image = esc_url($ns[0]['attribs']['']['url']);
                }
                if (!$image) {
                    $desc = $item->get_description();
                    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $desc, $m)) {
                        $image = esc_url($m[1]);
                    }
                }
            }

            $title = esc_html($item->get_title());
            $link = esc_url($item->get_permalink());
            $description = wp_strip_all_tags($item->get_description());
            $description = mb_strimwidth($description, 0, 120, '...');

            echo '<a class="mutual-rss-item" href="'. $link .'" target="_blank" rel="noopener noreferrer">';
            if ($image) {
                echo '<div class="mutual-rss-image-wrap"><img class="mutual-rss-image" src="'. $image .'" alt="'. $title .'" /></div>';
            } else {
                echo '<div class="mutual-rss-image-wrap mutual-rss-noimage"></div>';
            }
            echo '<div class="mutual-rss-title">'. $title .'</div>';
            echo '<div class="mutual-rss-text">'. esc_html($description) .'</div>';
            echo '</a>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
