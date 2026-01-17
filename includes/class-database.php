<?php
/**
 * Database Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class VAC_Database {
    
    private $visitors_table;
    private $conversations_table;
    private $messages_table;
    
    public function __construct() {
        global $wpdb;
        $this->visitors_table = $wpdb->prefix . 'vac_visitors';
        $this->conversations_table = $wpdb->prefix . 'vac_conversations';
        $this->messages_table = $wpdb->prefix . 'vac_messages';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Visitors table
        $sql_visitors = "CREATE TABLE IF NOT EXISTS {$this->visitors_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visitor_id varchar(64) NOT NULL,
            name varchar(100) DEFAULT '',
            email varchar(100) DEFAULT '',
            phone varchar(20) DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            user_agent text,
            page_url text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY visitor_id (visitor_id),
            KEY email (email)
        ) $charset_collate;";
        
        // Conversations table
        $sql_conversations = "CREATE TABLE IF NOT EXISTS {$this->conversations_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visitor_id bigint(20) UNSIGNED NOT NULL,
            status enum('open','pending','resolved','closed') DEFAULT 'open',
            handled_by enum('ai','staff') DEFAULT 'ai',
            staff_id bigint(20) UNSIGNED DEFAULT NULL,
            rating tinyint(1) DEFAULT NULL,
            rating_comment text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY visitor_id (visitor_id),
            KEY status (status),
            KEY staff_id (staff_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Messages table
        $sql_messages = "CREATE TABLE IF NOT EXISTS {$this->messages_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) UNSIGNED NOT NULL,
            sender_type enum('visitor','ai','staff','system') NOT NULL,
            staff_id bigint(20) UNSIGNED DEFAULT NULL,
            message longtext NOT NULL,
            attachments longtext,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY sender_type (sender_type),
            KEY created_at (created_at),
            KEY is_read (is_read)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_visitors);
        dbDelta($sql_conversations);
        dbDelta($sql_messages);
    }
    
    /**
     * Get or create visitor
     */
    public function get_or_create_visitor($visitor_id, $data = []) {
        global $wpdb;
        
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->visitors_table} WHERE visitor_id = %s",
            $visitor_id
        ));
        
        if (!$visitor) {
            $wpdb->insert($this->visitors_table, [
                'visitor_id' => $visitor_id,
                'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
                'email' => isset($data['email']) ? sanitize_email($data['email']) : '',
                'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'page_url' => isset($data['page_url']) ? esc_url_raw($data['page_url']) : '',
            ]);
            
            $visitor = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->visitors_table} WHERE id = %d",
                $wpdb->insert_id
            ));
        }
        
        return $visitor;
    }
    
    /**
     * Update visitor
     */
    public function update_visitor($visitor_id, $data) {
        global $wpdb;
        
        $update_data = [];
        if (isset($data['name'])) $update_data['name'] = sanitize_text_field($data['name']);
        if (isset($data['email'])) $update_data['email'] = sanitize_email($data['email']);
        if (isset($data['phone'])) $update_data['phone'] = sanitize_text_field($data['phone']);
        
        if (!empty($update_data)) {
            $wpdb->update(
                $this->visitors_table,
                $update_data,
                ['visitor_id' => $visitor_id]
            );
        }
        
        return $this->get_or_create_visitor($visitor_id);
    }
    
    /**
     * Get or create conversation
     */
    public function get_or_create_conversation($visitor_db_id) {
        global $wpdb;
        
        // Check for existing open conversation
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->conversations_table} 
             WHERE visitor_id = %d AND status IN ('open', 'pending')
             ORDER BY created_at DESC LIMIT 1",
            $visitor_db_id
        ));
        
        if (!$conversation) {
            $wpdb->insert($this->conversations_table, [
                'visitor_id' => $visitor_db_id,
                'status' => 'open',
                'handled_by' => 'ai',
            ]);
            
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->conversations_table} WHERE id = %d",
                $wpdb->insert_id
            ));
        }
        
        return $conversation;
    }
    
    /**
     * Get conversation by ID
     */
    public function get_conversation($conversation_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, v.name as visitor_name, v.email as visitor_email, v.phone as visitor_phone,
                    v.ip_address, v.user_agent, v.visitor_id as visitor_uid
             FROM {$this->conversations_table} c
             LEFT JOIN {$this->visitors_table} v ON c.visitor_id = v.id
             WHERE c.id = %d",
            $conversation_id
        ));
    }
    
    /**
     * Get conversations list
     */
    public function get_conversations($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => '',
            'search' => '',
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'updated_at',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "1=1";
        $values = [];
        
        if (!empty($args['status'])) {
            $where .= " AND c.status = %s";
            $values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where .= " AND (v.name LIKE %s OR v.email LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
        }
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$this->conversations_table} c
                      LEFT JOIN {$this->visitors_table} v ON c.visitor_id = v.id
                      WHERE $where";
        
        if (!empty($values)) {
            $total = $wpdb->get_var($wpdb->prepare($count_sql, $values));
        } else {
            $total = $wpdb->get_var($count_sql);
        }
        
        // Get results
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $sql = "SELECT c.*, v.name as visitor_name, v.email as visitor_email,
                       (SELECT message FROM {$this->messages_table} WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT COUNT(*) FROM {$this->messages_table} WHERE conversation_id = c.id AND sender_type = 'visitor' AND is_read = 0) as unread_count
                FROM {$this->conversations_table} c
                LEFT JOIN {$this->visitors_table} v ON c.visitor_id = v.id
                WHERE $where
                ORDER BY c.{$orderby}
                LIMIT %d OFFSET %d";
        
        $values[] = $args['per_page'];
        $values[] = $offset;
        
        $conversations = $wpdb->get_results($wpdb->prepare($sql, $values));
        
        return [
            'conversations' => $conversations,
            'pagination' => [
                'total' => intval($total),
                'total_pages' => ceil($total / $args['per_page']),
                'current_page' => $args['page'],
                'per_page' => $args['per_page'],
            ],
        ];
    }
    
    /**
     * Update conversation status
     */
    public function update_conversation($conversation_id, $data) {
        global $wpdb;
        
        $update = [];
        if (isset($data['status'])) $update['status'] = $data['status'];
        if (isset($data['handled_by'])) $update['handled_by'] = $data['handled_by'];
        if (isset($data['staff_id'])) $update['staff_id'] = $data['staff_id'];
        if (isset($data['rating'])) $update['rating'] = $data['rating'];
        if (isset($data['rating_comment'])) $update['rating_comment'] = $data['rating_comment'];
        
        if (!empty($update)) {
            return $wpdb->update($this->conversations_table, $update, ['id' => $conversation_id]);
        }
        
        return false;
    }
    
    /**
     * Add message
     */
    public function add_message($conversation_id, $sender_type, $message, $staff_id = null) {
        global $wpdb;
        
        $wpdb->insert($this->messages_table, [
            'conversation_id' => $conversation_id,
            'sender_type' => $sender_type,
            'staff_id' => $staff_id,
            'message' => $message,
            'is_read' => ($sender_type !== 'visitor') ? 1 : 0,
        ]);
        
        // Update conversation timestamp
        $wpdb->update(
            $this->conversations_table,
            ['updated_at' => current_time('mysql')],
            ['id' => $conversation_id]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get messages for conversation
     */
    public function get_messages($conversation_id, $after_id = 0) {
        global $wpdb;
        
        $sql = "SELECT m.*, u.display_name as staff_name
                FROM {$this->messages_table} m
                LEFT JOIN {$wpdb->users} u ON m.staff_id = u.ID
                WHERE m.conversation_id = %d";
        
        $values = [$conversation_id];
        
        if ($after_id > 0) {
            $sql .= " AND m.id > %d";
            $values[] = $after_id;
        }
        
        $sql .= " ORDER BY m.created_at ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    /**
     * Mark messages as read
     */
    public function mark_messages_read($conversation_id, $sender_type = 'visitor') {
        global $wpdb;
        
        return $wpdb->update(
            $this->messages_table,
            ['is_read' => 1],
            [
                'conversation_id' => $conversation_id,
                'sender_type' => $sender_type,
                'is_read' => 0,
            ]
        );
    }
    
    /**
     * Get statistics
     */
    public function get_stats() {
        global $wpdb;
        
        $today = date('Y-m-d 00:00:00');
        
        return [
            'total_conversations' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->conversations_table}"),
            'pending_conversations' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->conversations_table} WHERE status = %s",
                'pending'
            )),
            'resolved_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->conversations_table} WHERE status = %s AND updated_at >= %s",
                'resolved', $today
            )),
            'total_messages_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->messages_table} WHERE created_at >= %s",
                $today
            )),
            'avg_response_time' => $this->calculate_avg_response_time(),
            'unread_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->messages_table} WHERE sender_type = 'visitor' AND is_read = 0"
            ),
        ];
    }
    
    /**
     * Calculate average response time
     */
    private function calculate_avg_response_time() {
        global $wpdb;
        
        $result = $wpdb->get_var("
            SELECT AVG(TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at))
            FROM {$this->messages_table} m1
            JOIN {$this->messages_table} m2 ON m1.conversation_id = m2.conversation_id
            WHERE m1.sender_type = 'visitor'
            AND m2.sender_type IN ('ai', 'staff')
            AND m2.created_at > m1.created_at
            AND m2.id = (
                SELECT MIN(id) FROM {$this->messages_table}
                WHERE conversation_id = m1.conversation_id
                AND sender_type IN ('ai', 'staff')
                AND created_at > m1.created_at
            )
        ");
        
        return $result ? intval($result) : 0;
    }
    
    /**
     * Get client IP
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}
