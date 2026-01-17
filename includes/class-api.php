<?php
/**
 * REST API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class VAC_API
{

    private $namespace = 'visssoft-ai-chat/v1';
    private $chat_handler;
    private $database;

    public function __construct()
    {
        $this->chat_handler = new VAC_Chat_Handler();
        $this->database = new VAC_Database();

        // Enable cookie authentication for REST API
        add_filter('rest_authentication_errors', array($this, 'allow_cookie_auth'));
    }

    /**
     * Allow cookie authentication for REST API requests from admin
     */
    public function allow_cookie_auth($result)
    {
        // If already authenticated or error, return as is
        if (true === $result || is_wp_error($result)) {
            return $result;
        }

        // Check if user is logged in via cookie
        if (is_user_logged_in()) {
            return true;
        }

        return $result;
    }

    /**
     * Register REST routes
     */
    public function register_routes()
    {
        // Public routes (for chat widget)
        register_rest_route($this->namespace, '/chat/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send_message'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/chat/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_messages'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/chat/visitor', [
            'methods' => 'POST',
            'callback' => [$this, 'update_visitor'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/chat/rate', [
            'methods' => 'POST',
            'callback' => [$this, 'rate_conversation'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/chat/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_chat_status'],
            'permission_callback' => '__return_true',
        ]);

        // Widget configuration
        register_rest_route($this->namespace, '/widget/config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_widget_config'],
            'permission_callback' => '__return_true',
        ]);

        // Admin routes
        register_rest_route($this->namespace, '/admin/conversations', [
            'methods' => 'GET',
            'callback' => [$this, 'admin_get_conversations'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route($this->namespace, '/admin/conversations/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'admin_get_conversation'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route($this->namespace, '/admin/conversations/(?P<id>\d+)/reply', [
            'methods' => 'POST',
            'callback' => [$this, 'admin_reply'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/admin/conversations/(?P<id>\d+)/status', [
            'methods' => 'POST',
            'callback' => [$this, 'admin_update_status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/admin/conversations/(?P<id>\d+)/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'admin_get_messages'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/admin/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'admin_get_stats'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route($this->namespace, '/admin/test-ai', [
            'methods' => 'POST',
            'callback' => [$this, 'admin_test_ai'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route($this->namespace, '/admin/unread-count', [
            'methods' => 'GET',
            'callback' => [$this, 'admin_unread_count'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    /**
     * Check admin permission
     */
    public function check_admin_permission()
    {
        // For logged-in requests, check manage_options capability
        if (is_user_logged_in()) {
            return current_user_can('manage_options');
        }

        // If not logged in, deny access
        return false;
    }

    /**
     * Validate visitor ID format and timestamp
     */
    private function validate_visitor_id($visitor_id)
    {
        // Check format: v_timestamp_randomstring
        if (!preg_match('/^v_\d+_[a-z0-9]{9}$/', $visitor_id)) {
            return false;
        }

        // Extract and validate timestamp
        $parts = explode('_', $visitor_id);
        $timestamp = intval($parts[1]);
        $now = time();

        // Timestamp should be within last 30 days and not in future
        if ($timestamp > $now || $timestamp < ($now - 2592000)) {
            return false;
        }

        return true;
    }

    /**
     * Rate limit check with IP tracking
     */
    private function check_rate_limit($visitor_id)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Check if IP is banned
        $ban_key = 'vac_banned_' . md5($ip);
        if (get_transient($ban_key)) {
            error_log('VAC: Banned IP attempted access - ' . $ip);
            return false;
        }

        // Check visitor_id rate limit
        $visitor_key = 'vac_rate_limit_' . md5($visitor_id);
        $visitor_count = get_transient($visitor_key);

        if ($visitor_count === false) {
            set_transient($visitor_key, 1, VAC_RATE_LIMIT_WINDOW);
            $visitor_count = 1;
        } else {
            $visitor_count++;
            set_transient($visitor_key, $visitor_count, VAC_RATE_LIMIT_WINDOW);
        }

        // Check IP rate limit (stricter)
        $ip_key = 'vac_rate_limit_ip_' . md5($ip);
        $ip_count = get_transient($ip_key);

        if ($ip_count === false) {
            set_transient($ip_key, 1, VAC_RATE_LIMIT_WINDOW);
            $ip_count = 1;
        } else {
            $ip_count++;
            set_transient($ip_key, $ip_count, VAC_RATE_LIMIT_WINDOW);
        }

        // Check if limits exceeded
        if ($visitor_count > VAC_RATE_LIMIT_REQUESTS || $ip_count > VAC_RATE_LIMIT_IP_REQUESTS) {
            error_log('VAC: Rate limit exceeded - Visitor: ' . $visitor_id . ', IP: ' . $ip . ', Counts: ' . $visitor_count . '/' . $ip_count);

            // Track abuse
            $abuse_key = 'vac_abuse_' . md5($ip);
            $abuse_count = get_transient($abuse_key) ?: 0;
            $abuse_count++;
            set_transient($abuse_key, $abuse_count, 3600); // 1 hour

            // Ban after 3 violations
            if ($abuse_count >= 3) {
                set_transient($ban_key, true, 86400); // Ban for 24 hours
                error_log('VAC: IP banned for 24h - ' . $ip);
            }

            return false;
        }

        return true;
    }

    /**
     * Public: Send message
     */
    public function send_message($request)
    {
        $visitor_id = sanitize_text_field($request->get_param('visitor_id'));
        $message = sanitize_textarea_field($request->get_param('message'));

        // Validate visitor ID format
        if (!$this->validate_visitor_id($visitor_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid visitor ID format',
            ], 400);
        }

        // Validate message
        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Message cannot be empty',
            ], 400);
        }

        if (strlen($message) > VAC_MAX_MESSAGE_LENGTH) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Message exceeds maximum length',
            ], 400);
        }

        // Rate limit check
        if (!$this->check_rate_limit($visitor_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
            ], 429);
        }

        $visitor_data = [
            'name' => sanitize_text_field($request->get_param('name')),
            'email' => sanitize_email($request->get_param('email')),
            'phone' => sanitize_text_field($request->get_param('phone')),
            'page_url' => esc_url_raw($request->get_param('page_url')),
        ];

        $result = $this->chat_handler->handle_visitor_message($visitor_id, $message, $visitor_data);

        return new WP_REST_Response($result);
    }

    /**
     * Public: Get messages
     */
    public function get_messages($request)
    {
        $conversation_id = intval($request->get_param('conversation_id'));
        $after_id = intval($request->get_param('after_id'));

        if (!$conversation_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Conversation ID required',
            ], 400);
        }

        $messages = $this->chat_handler->get_messages($conversation_id, $after_id);

        return new WP_REST_Response([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Public: Update visitor
     */
    public function update_visitor($request)
    {
        $visitor_id = sanitize_text_field($request->get_param('visitor_id'));

        if (empty($visitor_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Visitor ID required',
            ], 400);
        }

        $data = [
            'name' => sanitize_text_field($request->get_param('name')),
            'email' => sanitize_email($request->get_param('email')),
            'phone' => sanitize_text_field($request->get_param('phone')),
        ];

        $visitor = $this->chat_handler->update_visitor($visitor_id, $data);

        return new WP_REST_Response([
            'success' => true,
            'visitor' => $visitor,
        ]);
    }

    /**
     * Public: Rate conversation
     */
    public function rate_conversation($request)
    {
        $conversation_id = intval($request->get_param('conversation_id'));
        $rating = intval($request->get_param('rating'));
        $comment = sanitize_textarea_field($request->get_param('comment'));

        if (!$conversation_id || !$rating) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Conversation ID and rating required',
            ], 400);
        }

        $result = $this->chat_handler->rate_conversation($conversation_id, $rating, $comment);

        return new WP_REST_Response($result);
    }

    /**
     * Public: Get chat status
     */
    public function get_chat_status($request)
    {
        $conversation_id = intval($request->get_param('conversation_id'));

        $response = [
            'success' => true,
            'is_online' => $this->chat_handler->is_within_business_hours(),
            'offline_message' => get_option('vac_offline_message', ''),
        ];

        if ($conversation_id) {
            $status = $this->chat_handler->get_conversation_status($conversation_id);
            if ($status) {
                $response['conversation'] = $status;
            }
        }

        return new WP_REST_Response($response);
    }

    /**
     * Admin: Get conversations list
     */
    public function admin_get_conversations($request)
    {
        $args = [
            'status' => sanitize_text_field($request->get_param('status')),
            'search' => sanitize_text_field($request->get_param('search')),
            'page' => intval($request->get_param('page')) ?: 1,
            'per_page' => intval($request->get_param('per_page')) ?: 20,
        ];

        $result = $this->database->get_conversations($args);

        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Admin: Get single conversation
     */
    public function admin_get_conversation($request)
    {
        $id = intval($request->get_param('id'));

        $conversation = $this->database->get_conversation($id);

        if (!$conversation) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        }

        $messages = $this->database->get_messages($id);

        // Mark as read
        $this->database->mark_messages_read($id, 'visitor');

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $messages,
                'visitor' => [
                    'name' => $conversation->visitor_name,
                    'email' => $conversation->visitor_email,
                    'phone' => $conversation->visitor_phone,
                    'ip_address' => $conversation->ip_address,
                    'user_agent' => $conversation->user_agent,
                ],
            ],
        ]);
    }

    /**
     * Admin: Reply to conversation
     */
    public function admin_reply($request)
    {
        $id = intval($request->get_param('id'));
        $message = sanitize_textarea_field($request->get_param('message'));

        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Message is required',
            ], 400);
        }

        $result = $this->chat_handler->handle_staff_reply($id, $message, get_current_user_id());

        return new WP_REST_Response($result);
    }

    /**
     * Admin: Update conversation status
     */
    public function admin_update_status($request)
    {
        $id = intval($request->get_param('id'));
        $status = sanitize_text_field($request->get_param('status'));

        $result = $this->chat_handler->update_status($id, $status);

        return new WP_REST_Response($result);
    }

    /**
     * Admin: Get messages (for polling)
     */
    public function admin_get_messages($request)
    {
        $id = intval($request->get_param('id'));
        $after_id = intval($request->get_param('after_id'));

        $messages = $this->database->get_messages($id, $after_id);

        return new WP_REST_Response([
            'success' => true,
            'data' => ['messages' => $messages],
        ]);
    }

    /**
     * Admin: Get statistics
     */
    public function admin_get_stats($request)
    {
        $stats = $this->database->get_stats();

        return new WP_REST_Response([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Admin: Test AI connection
     */
    public function admin_test_ai($request)
    {
        $api_key = sanitize_text_field($request->get_param('api_key'));

        if (!empty($api_key)) {
            update_option('vac_gemini_api_key', $api_key);
        }

        $gemini = new VAC_Gemini();
        $result = $gemini->test_connection();

        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'data' => ['model' => 'Gemini 2.0 Flash'],
            ]);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $result['error'] ?? 'Connection failed',
        ], 400);
    }


    /**
     * Admin: Get unread count
     */
    public function admin_unread_count($request)
    {
        $stats = $this->database->get_stats();

        return new WP_REST_Response([
            'success' => true,
            'data' => ['count' => $stats['unread_count']],
        ]);
    }

    /**
     * Public: Get widget configuration
     */
    public function get_widget_config($request)
    {
        $defaults = array(
            'primaryColor' => '#FDB913',
            'backgroundColor' => '#FFF8E7',
            'textColor' => '#5C4033',
            'borderColor' => '#F5A623',
            'title' => 'Ban tang lễ hỗ trợ/ tư vấn',
            'subtitle' => '',
            'welcomeMessage' => "Ban lễ tang xin chào quý khách.\nQuý khách cần tư vấn gì a ?",
            'inputPlaceholder' => 'Nhập tin nhắn...',
            'sendButtonText' => 'Gửi',
            'quickReplies' => array(
                array('id' => '1', 'text' => 'Tang lễ', 'message' => 'Tôi muốn tư vấn về dịch vụ tang lễ'),
                array('id' => '2', 'text' => 'Đặt ô trọ', 'message' => 'Tôi muốn đặt ô trọ'),
                array('id' => '3', 'text' => 'Gói mộ', 'message' => 'Tôi muốn tư vấn về gói mộ'),
                array('id' => '4', 'text' => 'Xem hướng mộ', 'message' => 'Tôi muốn xem hướng mộ'),
            ),
            'logo' => '',
            'position' => 'bottom-right',
            'autoOpen' => false,
            'showQuickReplies' => true,
        );

        $settings = get_option('vac_widget_settings', array());

        // Convert snake_case to camelCase for JavaScript
        $config = array(
            'primaryColor' => $settings['primary_color'] ?? $defaults['primaryColor'],
            'backgroundColor' => $settings['background_color'] ?? $defaults['backgroundColor'],
            'textColor' => $settings['text_color'] ?? $defaults['textColor'],
            'borderColor' => $settings['border_color'] ?? $defaults['borderColor'],
            'title' => $settings['title'] ?? $defaults['title'],
            'subtitle' => $settings['subtitle'] ?? $defaults['subtitle'],
            'welcomeMessage' => $settings['welcome_message'] ?? $defaults['welcomeMessage'],
            'inputPlaceholder' => $settings['input_placeholder'] ?? $defaults['inputPlaceholder'],
            'sendButtonText' => $settings['send_button_text'] ?? $defaults['sendButtonText'],
            'quickReplies' => $settings['quick_replies'] ?? $defaults['quickReplies'],
            'logo' => $settings['logo'] ?? $defaults['logo'],
            'position' => $settings['position'] ?? $defaults['position'],
            'autoOpen' => $settings['auto_open'] ?? $defaults['autoOpen'],
            'showQuickReplies' => $settings['show_quick_replies'] ?? $defaults['showQuickReplies'],
        );

        return new WP_REST_Response([
            'success' => true,
            'config' => $config,
        ]);
    }
}
