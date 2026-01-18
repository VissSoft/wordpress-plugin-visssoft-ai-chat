<?php
/**
 * Plugin Name: Visssoft AI Chat Support
 * Plugin URI: https://visssoft.com/plugins/ai-chat
 * Description: Plugin chat hỗ trợ khách hàng với AI (Gemini) - Tự động thu thập dữ liệu từ Post Types
 * Version: 2.0.0
 * Author: Visssoft
 * Author URI: https://visssoft.com
 * Text Domain: visssoft-ai-chat
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('VAC_VERSION', '2.0.1');
define('VAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VAC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Configuration constants
define('VAC_RATE_LIMIT_REQUESTS', 30);           // Max requests per window
define('VAC_RATE_LIMIT_WINDOW', 60);             // Time window in seconds
define('VAC_RATE_LIMIT_IP_REQUESTS', 50);        // Max requests per IP
define('VAC_POLLING_INTERVAL_MS', 10000);        // Polling interval in milliseconds (10s)
define('VAC_DEFAULT_POST_LIMIT', 50);            // Default posts to fetch per type
define('VAC_CACHE_EXPIRATION', 3600);            // Cache expiration in seconds (1 hour)
define('VAC_MAX_MESSAGE_LENGTH', 5000);          // Max message length in characters
define('VAC_MAX_CACHE_SIZE', 1048576);           // Max cache size in bytes (1MB)
define('VAC_API_TIMEOUT', 30);                   // API timeout in seconds

/**
 * Main Plugin Class
 */
class Visssoft_AI_Chat
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies()
    {
        require_once VAC_PLUGIN_DIR . 'includes/class-database.php';
        require_once VAC_PLUGIN_DIR . 'includes/class-data-collector.php';
        require_once VAC_PLUGIN_DIR . 'includes/class-gemini.php';
        require_once VAC_PLUGIN_DIR . 'includes/class-chat-handler.php';
        require_once VAC_PLUGIN_DIR . 'includes/class-api.php';
    }

    private function init_hooks()
    {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_footer', array($this, 'render_chat_widget'));

        // AJAX handlers
        add_action('wp_ajax_vac_test_ai_connection', array($this, 'ajax_test_ai'));
        add_action('wp_ajax_vac_refresh_data', array($this, 'ajax_refresh_data'));
        add_action('wp_ajax_vac_get_custom_fields', array($this, 'ajax_get_custom_fields'));
        add_action('wp_ajax_vac_preview_data', array($this, 'ajax_preview_data'));
        add_action('wp_ajax_vac_sync_content', array($this, 'ajax_sync_content'));
        add_action('wp_ajax_vac_get_post_types', array($this, 'ajax_get_post_types'));
        add_action('wp_ajax_vac_preview_content', array($this, 'ajax_preview_content'));

        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Plugin links
        add_filter('plugin_action_links_' . VAC_PLUGIN_BASENAME, array($this, 'add_settings_link'));

        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    public function activate()
    {
        // Create database tables
        $database = new VAC_Database();
        $database->create_tables();

        // Set default options
        $this->set_default_options();

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        // Clear transients
        delete_transient('vac_ai_knowledge_data');

        flush_rewrite_rules();
    }

    private function set_default_options()
    {
        $defaults = array(
            'vac_gemini_api_key' => '',
            'vac_ai_auto_reply' => true,
            'vac_greeting_message' => 'Xin chào! Tôi là trợ lý AI. Tôi có thể giúp gì cho bạn?',
            'vac_widget_position' => 'bottom-right',
            'vac_primary_color' => '#0073aa',
            'vac_widget_title' => 'Chat với chúng tôi',
            'vac_widget_subtitle' => 'Thường trả lời trong vài phút',
            'vac_business_hours' => array('start' => '08:00', 'end' => '22:00'),
            'vac_offline_message' => 'Hiện tại chúng tôi không online. Vui lòng để lại tin nhắn.',
            'vac_notification_email' => get_option('admin_email'),
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    public function add_admin_menu()
    {
        // Main menu
        add_menu_page(
            'AI Chat Support',
            'AI Chat',
            'manage_options',
            'visssoft-ai-chat',
            array($this, 'render_dashboard'),
            'dashicons-format-chat',
            30
        );

        // Dashboard
        add_submenu_page(
            'visssoft-ai-chat',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'visssoft-ai-chat',
            array($this, 'render_dashboard')
        );

        // Conversations
        add_submenu_page(
            'visssoft-ai-chat',
            'Hội thoại',
            'Hội thoại',
            'manage_options',
            'vac-conversations',
            array($this, 'render_conversations')
        );

        // Data Sources - QUAN TRỌNG
        add_submenu_page(
            'visssoft-ai-chat',
            'Nguồn dữ liệu',
            '📊 Nguồn dữ liệu',
            'manage_options',
            'vac-data-settings',
            array($this, 'render_data_settings')
        );

        // Widget Settings
        add_submenu_page(
            'visssoft-ai-chat',
            'Cấu hình Widget',
            '🎨 Cấu hình Widget',
            'manage_options',
            'vac-widget-settings',
            array($this, 'render_widget_settings')
        );

        // Settings
        add_submenu_page(
            'visssoft-ai-chat',
            'Cài đặt',
            'Cài đặt',
            'manage_options',
            'vac-settings',
            array($this, 'render_settings')
        );

        // API Documentation
        add_submenu_page(
            'visssoft-ai-chat',
            'API Documentation',
            '📚 API Docs',
            'manage_options',
            'vac-api-docs',
            array($this, 'render_api_docs')
        );
    }

    public function register_settings()
    {
        // General settings
        register_setting('vac_settings', 'vac_gemini_api_key');
        register_setting('vac_settings', 'vac_ai_auto_reply');
        register_setting('vac_settings', 'vac_greeting_message');
        register_setting('vac_settings', 'vac_ai_knowledge_base');
        register_setting('vac_settings', 'vac_widget_position');
        register_setting('vac_settings', 'vac_primary_color');
        register_setting('vac_settings', 'vac_widget_title');
        register_setting('vac_settings', 'vac_show_on_mobile');
        register_setting('vac_settings', 'vac_business_hours');
        register_setting('vac_settings', 'vac_offline_message');

        // Content sync settings
        register_setting('vac_settings', 'vac_content_sync_enabled');
        register_setting('vac_settings', 'vac_content_sync_config');

        // Data settings
        register_setting('vac_settings', 'vac_data_settings');
    }

    public function enqueue_admin_scripts($hook)
    {
        // Allow scripts on all admin pages to ensure conversations page works
        // Original check was too restrictive

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_style(
            'vac-admin-css',
            VAC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VAC_VERSION
        );

        wp_enqueue_script(
            'vac-admin-js',
            VAC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            VAC_VERSION,
            true
        );

        wp_localize_script('vac-admin-js', 'vacAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('visssoft-ai-chat/v1/'),
            'nonce' => wp_create_nonce('vac_admin_nonce'),
        ));

    }

    public function enqueue_frontend_scripts()
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'vac-widget-css',
            VAC_PLUGIN_URL . 'assets/css/chat-widget.css',
            array(),
            VAC_VERSION
        );

        wp_enqueue_script(
            'vac-widget-js',
            VAC_PLUGIN_URL . 'assets/js/chat-widget.js',
            array('jquery'),
            VAC_VERSION,
            true
        );

        wp_localize_script('vac-widget-js', 'vacChat', array(
            'restUrl' => rest_url('visssoft-ai-chat/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => array(
                'position' => get_option('vac_widget_position', 'bottom-right'),
                'primaryColor' => get_option('vac_primary_color', '#0073aa'),
                'title' => get_option('vac_widget_title', 'Chat với chúng tôi'),
                'subtitle' => get_option('vac_widget_subtitle', 'Thường trả lời trong vài phút'),
                'greeting' => get_option('vac_greeting_message', 'Xin chào! Tôi có thể giúp gì cho bạn?'),
            ),
        ));
    }

    public function render_dashboard()
    {
        include VAC_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    public function render_conversations()
    {
        include VAC_PLUGIN_DIR . 'templates/admin-conversations.php';
    }

    public function render_data_settings()
    {
        include VAC_PLUGIN_DIR . 'templates/admin-data-settings.php';
    }

    public function render_widget_settings()
    {
        include VAC_PLUGIN_DIR . 'templates/admin-widget-settings.php';
    }

    public function render_settings()
    {
        include VAC_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    public function render_api_docs()
    {
        include VAC_PLUGIN_DIR . 'templates/admin-api-docs.php';
    }

    public function render_chat_widget()
    {
        if (is_admin()) {
            return;
        }
        include VAC_PLUGIN_DIR . 'templates/chat-widget.php';
    }

    public function register_rest_routes()
    {
        $api = new VAC_API();
        $api->register_routes();
    }

    // AJAX: Test AI Connection
    public function ajax_test_ai()
    {
        check_ajax_referer('vac_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (!empty($api_key)) {
            update_option('vac_gemini_api_key', $api_key);
        }

        $gemini = new VAC_Gemini();
        $result = $gemini->test_connection();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Kết nối thành công!',
                'response' => $result['message']
            ));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }

    // AJAX: Refresh Data
    public function ajax_refresh_data()
    {
        check_ajax_referer('vac_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $data_collector = VAC_Data_Collector::get_instance();
        $data_collector->invalidate_cache();
        $data = $data_collector->get_ai_knowledge_data(true);

        update_option('vac_data_last_updated', current_time('mysql'));

        wp_send_json_success(array(
            'message' => 'Đã cập nhật dữ liệu!',
            'size' => strlen($data),
            'preview' => substr($data, 0, 1000)
        ));
    }

    // AJAX: Get Custom Fields
    public function ajax_get_custom_fields()
    {
        check_ajax_referer('vac_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';

        $data_collector = VAC_Data_Collector::get_instance();
        $fields = $data_collector->get_available_custom_fields($post_type);

        wp_send_json_success(array('fields' => $fields));
    }

    // AJAX: Preview Data
    public function ajax_preview_data()
    {
        check_ajax_referer('vac_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $data_collector = VAC_Data_Collector::get_instance();
        $data = $data_collector->get_ai_knowledge_data(true);

        wp_send_json_success(array(
            'data' => $data,
            'size' => strlen($data)
        ));
    }

    // AJAX: Sync Content
    public function ajax_sync_content()
    {
        check_ajax_referer('vac_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        // Lấy config từ request (nếu có)
        $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : array();

        // Refresh data
        $data_collector = VAC_Data_Collector::get_instance();
        $data_collector->invalidate_cache();
        $data = $data_collector->get_ai_knowledge_data(true);

        update_option('vac_data_last_updated', current_time('mysql'));

        wp_send_json_success(array(
            'message' => 'Đã đồng bộ dữ liệu thành công!',
            'stats' => array(
                'characters' => strlen($data)
            ),
            'preview' => substr($data, 0, 2000)
        ));
    }

    // AJAX: Get Post Types
    public function ajax_get_post_types()
    {
        check_ajax_referer('vac_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $data_collector = VAC_Data_Collector::get_instance();
        $post_types = $data_collector->get_available_post_types();

        $result = array();
        foreach ($post_types as $name => $info) {
            $custom_fields = $data_collector->get_available_custom_fields($name);
            $taxonomies = get_object_taxonomies($name, 'names');

            $result[] = array(
                'name' => $name,
                'label' => $info['label'],
                'count' => $info['count'],
                'fields' => array_map(function ($key, $label) {
                    return array('key' => $key, 'label' => $label);
                }, array_keys($custom_fields), array_values($custom_fields)),
                'taxonomies' => $taxonomies
            );
        }

        wp_send_json_success($result);
    }

    // AJAX: Preview Content
    public function ajax_preview_content()
    {
        check_ajax_referer('vac_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $data_collector = VAC_Data_Collector::get_instance();
        $data = $data_collector->get_ai_knowledge_data(true);

        wp_send_json_success(array(
            'post_count' => substr_count($data, '['),
            'preview' => substr($data, 0, 5000)
        ));
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=vac-settings') . '">Cài đặt</a>';
        $data_link = '<a href="' . admin_url('admin.php?page=vac-data-settings') . '">Nguồn dữ liệu</a>';
        array_unshift($links, $settings_link, $data_link);
        return $links;
    }

    public function admin_notices()
    {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'visssoft-ai-chat') === false) {
            return;
        }

        // Check API key
        $api_key = get_option('vac_gemini_api_key', '');
        if (empty($api_key)) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Visssoft AI Chat:</strong> Vui lòng cấu hình Gemini API Key tại ';
            echo '<a href="' . admin_url('admin.php?page=vac-settings') . '">Cài đặt</a>';
            echo '</p></div>';
        }

        // Check data sources
        $data_settings = get_option('vac_data_settings', array());
        $has_enabled = false;
        if (!empty($data_settings['post_types'])) {
            foreach ($data_settings['post_types'] as $config) {
                if (!empty($config['enabled'])) {
                    $has_enabled = true;
                    break;
                }
            }
        }

        if (!$has_enabled && !empty($api_key)) {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>💡 Gợi ý:</strong> Cấu hình nguồn dữ liệu để AI có thể trả lời dựa trên nội dung website của bạn. ';
            echo '<a href="' . admin_url('admin.php?page=vac-data-settings') . '">Cấu hình ngay</a>';
            echo '</p></div>';
        }
    }
}

// Initialize
function visssoft_ai_chat()
{
    return Visssoft_AI_Chat::get_instance();
}

visssoft_ai_chat();
