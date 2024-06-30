<?php
/*
Plugin Name: Simple Social Follow Buttons
Description: Adds social media follow buttons with follower counts and allows for horizontal or vertical display.
Version: 1.3
Author: Your Name
*/

// Prevent direct access to the plugin file
if (!defined('ABSPATH')) {
    exit;
}

class SimpleSocialFollowButtons {
    private $social_platforms = array('twitter', 'facebook', 'instagram', 'youtube');

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_head', array($this, 'add_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_dashicons'));
        register_deactivation_hook(__FILE__, array($this, 'clear_follower_count_transients'));
    }

    public function add_admin_menu() {
        add_options_page('Social Follow Buttons', 'Social Follow Buttons', 'manage_options', 'simple-social-follow-buttons', array($this, 'admin_page'));
    }

    public function register_settings() {
        register_setting('simple-social-follow-buttons', 'simple_social_follow_buttons_options');
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Social Follow Buttons Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('simple-social-follow-buttons');
                do_settings_sections('simple-social-follow-buttons');
                ?>
                <table class="form-table">
                    <?php
                    $options = get_option('simple_social_follow_buttons_options');
                    foreach ($this->social_platforms as $platform) {
                        $value = isset($options[$platform]) ? $options[$platform] : '';
                        ?>
                        <tr valign="top">
                            <th scope="row"><?php echo ucfirst($platform); ?> <?php echo $platform === 'youtube' ? 'Channel ID or URL' : 'Username'; ?></th>
                            <td>
                                <input type="text" name="simple_social_follow_buttons_options[<?php echo $platform; ?>]" value="<?php echo esc_attr($value); ?>" />
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    <tr valign="top">
                        <th scope="row">Display Style</th>
                        <td>
                            <select name="simple_social_follow_buttons_options[display_style]">
                                <option value="horizontal" <?php selected($options['display_style'], 'horizontal'); ?>>Horizontal</option>
                                <option value="vertical" <?php selected($options['display_style'], 'vertical'); ?>>Vertical</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function social_buttons_shortcode($atts) {
        $options = get_option('simple_social_follow_buttons_options');
        $display_style = isset($options['display_style']) ? $options['display_style'] : 'horizontal';
        $html = '<div class="social-follow-buttons ' . esc_attr($display_style) . '">';

        if (empty($options) || !is_array($options)) {
            return '<p>No social media accounts configured.</p>';
        }

        $dashicons = [
            'twitter' => 'dashicons-twitter-custom',
            'facebook' => 'dashicons-facebook',
            'instagram' => 'dashicons-instagram',
            'youtube' => 'dashicons-video-alt3'
        ];

        foreach ($options as $platform => $username) {
            if (in_array($platform, $this->social_platforms) && !empty($username)) {
                $count = $this->get_follower_count($username, $platform);
                $icon_class = $dashicons[$platform] ?? 'dashicons-share';
                $platform_url = $platform === 'youtube' ? get_option('simple_social_follow_buttons_options_' . $platform, "https://www.youtube.com/channel/{$username}") : "https://{$platform}.com/{$username}";
                $html .= "<a href='{$platform_url}' target='_blank' class='social-button {$platform}'>";
                $html .= "<span class='dashicons {$icon_class}'></span>";
                $html .= "<span class='follower-count'>{$count}</span>&nbsp;followers";
                $html .= "</a>";
            }
        }

        $html .= '</div>';
        return $html;
    }

    public function get_follower_count($username, $platform) {
        $transient_key = 'follower_count_' . $platform . '_' . $username;
        $follower_count = get_transient($transient_key);

        if ($follower_count === false) {
            $search_query = urlencode($username . ' ' . $platform);
            $url = "https://www.google.com/search?q={$search_query}";

            $args = array(
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            );

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                return 'N/A';
            }

            $body = wp_remote_retrieve_body($response);

            if ($platform === 'youtube') {
                preg_match('/<a href="(https:\/\/www\.youtube\.com\/channel\/[^"]+)"[^>]*>/i', $body, $matches);
                if (isset($matches[1])) {
                    $channel_url = $matches[1];
                    update_option('simple_social_follow_buttons_options_' . $platform, $channel_url);
                }
            }

            preg_match('/<cite[^>]*>(\d+(?:\.\d+)?[KMB]?\+?)\s*followers<\/cite>/i', $body, $matches);

            if (isset($matches[1])) {
                $follower_count = $matches[1];
                set_transient($transient_key, $follower_count, DAY_IN_SECONDS);
            } else {
                return 'N/A';
            }
        }

        return $follower_count;
    }

    public function add_styles() {
        echo '
        <style>
            .social-follow-buttons {
                display: flex;
                gap: 10px;
                font-family: Arial, sans-serif;
                color: white;
            }
            .social-follow-buttons.vertical {
                flex-direction: column;
                max-width:160px;
            }
            .social-button {
                display: inline-flex;
                align-items: center;
                padding: 10px 15px;
                border-radius: 5px;
                text-decoration: none;
                color: #fff !important;
                font-size: 14px;
                transition: opacity 0.3s;
            }
            .social-button:hover {
                opacity: 0.9;
            }
            .platform-name {
                color: #fff !important;
            }
         
            .social-button .platform-name {
                font-weight: bold;
                margin-right: 10px;
            }
            .social-follow-buttons a {
                font-size: 15px;
            }
            .social-button .follower-count {
                font-size: 15px;
                color: #fff;
            }
            .dashicons-twitter-custom {
                background-image:  url(https://upload.wikimedia.org/wikipedia/commons/thumb/5/57/X_logo_2023_%28white%29.png/240px-X_logo_2023_%28white%29.png);
                background-size: contain;
                background-repeat: no-repeat;
            }
            .dashicons {
                margin-right:10px;
              
            }
            .twitter { background-color: #000; }
            .facebook { background-color: #4267B2; }
            .instagram { background-color: #E1306C; }
            .youtube { background-color: #FF0000; }
        </style>
        ';
    }

    public function enqueue_dashicons() {
        wp_enqueue_style('dashicons');
    }

    public function clear_follower_count_transients() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_follower_count_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_follower_count_%'");
    }
}

function initialize_simple_social_follow_buttons() {
    $plugin = new SimpleSocialFollowButtons();
    add_shortcode('social_follow_buttons', array($plugin, 'social_buttons_shortcode'));
}
add_action('init', 'initialize_simple_social_follow_buttons');
?>
