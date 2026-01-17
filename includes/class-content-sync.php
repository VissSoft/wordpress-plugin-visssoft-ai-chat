<?php
/**
 * Content Sync - Fetch WordPress Post Types Data for AI Knowledge Base
 * 
 * This class handles:
 * - Getting all available post types
 * - Fetching posts data with custom fields
 * - Building AI knowledge base from content
 * - Caching for performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class VAC_Content_Sync {
    
    private static $instance = null;
    private $cache_key = 'vac_content_knowledge_base';
    private $cache_expiration = 3600; // 1 hour
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook to clear cache when posts are updated
        add_action('save_post', [$this, 'clear_cache']);
        add_action('delete_post', [$this, 'clear_cache']);
        add_action('trash_post', [$this, 'clear_cache']);
        
        // AJAX handlers for admin
        add_action('wp_ajax_vac_get_post_types', [$this, 'ajax_get_post_types']);
        add_action('wp_ajax_vac_sync_content', [$this, 'ajax_sync_content']);
        add_action('wp_ajax_vac_preview_content', [$this, 'ajax_preview_content']);
    }
    
    /**
     * Get all available post types
     */
    public function get_available_post_types() {
        $post_types = get_post_types([
            'public' => true,
        ], 'objects');
        
        $result = [];
        
        foreach ($post_types as $post_type) {
            // Skip attachments
            if ($post_type->name === 'attachment') {
                continue;
            }
            
            $count = wp_count_posts($post_type->name);
            $published_count = isset($count->publish) ? $count->publish : 0;
            
            $result[] = [
                'name' => $post_type->name,
                'label' => $post_type->labels->name,
                'singular' => $post_type->labels->singular_name,
                'count' => $published_count,
                'has_archive' => $post_type->has_archive,
                'hierarchical' => $post_type->hierarchical,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get custom fields for a post type
     */
    public function get_post_type_fields($post_type) {
        global $wpdb;
        
        $fields = [];
        
        // Get meta keys used by this post type
        $meta_keys = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.meta_key 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\_%'
            ORDER BY pm.meta_key
            LIMIT 50
        ", $post_type));
        
        foreach ($meta_keys as $key) {
            $fields[] = [
                'key' => $key,
                'label' => ucwords(str_replace(['_', '-'], ' ', $key)),
            ];
        }
        
        // Check for ACF fields
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups([
                'post_type' => $post_type,
            ]);
            
            foreach ($field_groups as $group) {
                $acf_fields = acf_get_fields($group['key']);
                if ($acf_fields) {
                    foreach ($acf_fields as $field) {
                        $fields[] = [
                            'key' => $field['name'],
                            'label' => $field['label'],
                            'type' => $field['type'],
                            'acf' => true,
                        ];
                    }
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Get taxonomies for a post type
     */
    public function get_post_type_taxonomies($post_type) {
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        
        $result = [];
        foreach ($taxonomies as $taxonomy) {
            $result[] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->labels->name,
                'singular' => $taxonomy->labels->singular_name,
            ];
        }
        
        return $result;
    }
    
    /**
     * Fetch posts data from selected post types
     */
    public function fetch_posts_data($config = []) {
        $defaults = [
            'post_types' => [],
            'fields' => [],
            'taxonomies' => [],
            'limit_per_type' => 100,
            'include_content' => true,
            'include_excerpt' => true,
            'include_meta' => true,
            'include_taxonomies' => true,
            'content_length' => 500, // Characters to include from content
        ];
        
        $config = wp_parse_args($config, $defaults);
        
        if (empty($config['post_types'])) {
            return [];
        }
        
        $all_data = [];
        
        foreach ($config['post_types'] as $post_type_config) {
            $post_type = is_array($post_type_config) ? $post_type_config['name'] : $post_type_config;
            $type_fields = is_array($post_type_config) && isset($post_type_config['fields']) 
                ? $post_type_config['fields'] 
                : [];
            
            $posts = $this->get_posts_for_type($post_type, $config, $type_fields);
            
            if (!empty($posts)) {
                $all_data[$post_type] = $posts;
            }
        }
        
        return $all_data;
    }
    
    /**
     * Get posts for a specific post type
     */
    private function get_posts_for_type($post_type, $config, $fields = []) {
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $config['limit_per_type'],
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $posts = get_posts($args);
        $result = [];
        
        foreach ($posts as $post) {
            $post_data = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'date' => $post->post_date,
            ];
            
            // Include excerpt
            if ($config['include_excerpt'] && !empty($post->post_excerpt)) {
                $post_data['excerpt'] = wp_strip_all_tags($post->post_excerpt);
            }
            
            // Include content (truncated)
            if ($config['include_content']) {
                $content = wp_strip_all_tags($post->post_content);
                if (strlen($content) > $config['content_length']) {
                    $content = mb_substr($content, 0, $config['content_length']) . '...';
                }
                $post_data['content'] = $content;
            }
            
            // Include featured image
            if (has_post_thumbnail($post->ID)) {
                $post_data['image'] = get_the_post_thumbnail_url($post->ID, 'medium');
            }
            
            // Include meta fields
            if ($config['include_meta'] && !empty($fields)) {
                $meta = [];
                foreach ($fields as $field) {
                    $field_key = is_array($field) ? $field['key'] : $field;
                    $value = get_post_meta($post->ID, $field_key, true);
                    
                    if (!empty($value) && !is_array($value)) {
                        $meta[$field_key] = $value;
                    } elseif (is_array($value)) {
                        $meta[$field_key] = implode(', ', array_filter($value));
                    }
                }
                if (!empty($meta)) {
                    $post_data['meta'] = $meta;
                }
            }
            
            // Include taxonomies
            if ($config['include_taxonomies']) {
                $taxonomies = get_object_taxonomies($post_type);
                foreach ($taxonomies as $taxonomy) {
                    $terms = get_the_terms($post->ID, $taxonomy);
                    if ($terms && !is_wp_error($terms)) {
                        $term_names = wp_list_pluck($terms, 'name');
                        $post_data['taxonomies'][$taxonomy] = implode(', ', $term_names);
                    }
                }
            }
            
            $result[] = $post_data;
        }
        
        return $result;
    }
    
    /**
     * Build knowledge base text from posts data
     */
    public function build_knowledge_base($posts_data, $format = 'detailed') {
        if (empty($posts_data)) {
            return '';
        }
        
        $knowledge = [];
        
        foreach ($posts_data as $post_type => $posts) {
            $post_type_obj = get_post_type_object($post_type);
            $type_label = $post_type_obj ? $post_type_obj->labels->name : $post_type;
            
            $knowledge[] = "\n=== {$type_label} ===\n";
            
            foreach ($posts as $post) {
                $knowledge[] = $this->format_post_for_knowledge($post, $format);
            }
        }
        
        return implode("\n", $knowledge);
    }
    
    /**
     * Format single post for knowledge base
     */
    private function format_post_for_knowledge($post, $format = 'detailed') {
        $lines = [];
        
        $lines[] = "【{$post['title']}】";
        
        if ($format === 'detailed') {
            if (!empty($post['excerpt'])) {
                $lines[] = "Mô tả: {$post['excerpt']}";
            } elseif (!empty($post['content'])) {
                $lines[] = "Nội dung: {$post['content']}";
            }
            
            // Add meta fields
            if (!empty($post['meta'])) {
                foreach ($post['meta'] as $key => $value) {
                    $label = ucwords(str_replace(['_', '-'], ' ', $key));
                    $lines[] = "{$label}: {$value}";
                }
            }
            
            // Add taxonomies
            if (!empty($post['taxonomies'])) {
                foreach ($post['taxonomies'] as $tax => $terms) {
                    $tax_obj = get_taxonomy($tax);
                    $tax_label = $tax_obj ? $tax_obj->labels->singular_name : $tax;
                    $lines[] = "{$tax_label}: {$terms}";
                }
            }
            
            $lines[] = "Link: {$post['url']}";
        } else {
            // Simple format
            if (!empty($post['excerpt'])) {
                $lines[] = $post['excerpt'];
            }
            $lines[] = "Link: {$post['url']}";
        }
        
        $lines[] = ""; // Empty line between posts
        
        return implode("\n", $lines);
    }
    
    /**
     * Get cached knowledge base or rebuild
     */
    public function get_knowledge_base($force_rebuild = false) {
        if (!$force_rebuild) {
            $cached = get_transient($this->cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Get sync configuration
        $config = get_option('vac_content_sync_config', []);
        
        if (empty($config) || empty($config['post_types'])) {
            return '';
        }
        
        // Fetch data
        $posts_data = $this->fetch_posts_data($config);
        
        // Build knowledge base
        $format = isset($config['format']) ? $config['format'] : 'detailed';
        $knowledge = $this->build_knowledge_base($posts_data, $format);
        
        // Cache the result
        set_transient($this->cache_key, $knowledge, $this->cache_expiration);
        
        return $knowledge;
    }
    
    /**
     * Get full AI knowledge base (manual + auto-synced)
     */
    public function get_full_knowledge_base() {
        $parts = [];
        
        // Manual knowledge base
        $manual = get_option('vac_ai_knowledge_base', '');
        if (!empty($manual)) {
            $parts[] = "=== THÔNG TIN DOANH NGHIỆP ===\n" . $manual;
        }
        
        // Auto-synced content
        $auto_sync_enabled = get_option('vac_content_sync_enabled', false);
        if ($auto_sync_enabled) {
            $synced = $this->get_knowledge_base();
            if (!empty($synced)) {
                $parts[] = "\n=== DỮ LIỆU TỪ WEBSITE ===\n" . $synced;
            }
        }
        
        return implode("\n\n", $parts);
    }
    
    /**
     * Clear cache
     */
    public function clear_cache($post_id = null) {
        delete_transient($this->cache_key);
    }
    
    /**
     * AJAX: Get post types
     */
    public function ajax_get_post_types() {
        check_ajax_referer('vac_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $post_types = $this->get_available_post_types();
        
        // Get fields and taxonomies for each
        foreach ($post_types as &$type) {
            $type['fields'] = $this->get_post_type_fields($type['name']);
            $type['taxonomies'] = $this->get_post_type_taxonomies($type['name']);
        }
        
        wp_send_json_success($post_types);
    }
    
    /**
     * AJAX: Sync content
     */
    public function ajax_sync_content() {
        check_ajax_referer('vac_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        // Save configuration
        $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : [];
        
        if (empty($config)) {
            wp_send_json_error(['message' => 'Invalid configuration']);
        }
        
        update_option('vac_content_sync_config', $config);
        update_option('vac_content_sync_enabled', true);
        
        // Clear cache and rebuild
        $this->clear_cache();
        $knowledge = $this->get_knowledge_base(true);
        
        $char_count = strlen($knowledge);
        $word_count = str_word_count($knowledge);
        
        wp_send_json_success([
            'message' => 'Đồng bộ thành công',
            'stats' => [
                'characters' => $char_count,
                'words' => $word_count,
            ],
            'preview' => mb_substr($knowledge, 0, 1000) . (strlen($knowledge) > 1000 ? '...' : ''),
        ]);
    }
    
    /**
     * AJAX: Preview content
     */
    public function ajax_preview_content() {
        check_ajax_referer('vac_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : [];
        
        if (empty($config)) {
            wp_send_json_error(['message' => 'Invalid configuration']);
        }
        
        // Fetch sample data (limit to 5 per type for preview)
        $config['limit_per_type'] = 5;
        $posts_data = $this->fetch_posts_data($config);
        
        $format = isset($config['format']) ? $config['format'] : 'detailed';
        $preview = $this->build_knowledge_base($posts_data, $format);
        
        wp_send_json_success([
            'preview' => $preview,
            'post_count' => array_sum(array_map('count', $posts_data)),
        ]);
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        $config = get_option('vac_content_sync_config', []);
        $enabled = get_option('vac_content_sync_enabled', false);
        $last_sync = get_option('vac_content_last_sync', 0);
        
        return [
            'enabled' => $enabled,
            'config' => $config,
            'last_sync' => $last_sync,
            'cache_exists' => get_transient($this->cache_key) !== false,
        ];
    }
    
    /**
     * Manual sync trigger
     */
    public function trigger_sync() {
        $this->clear_cache();
        $knowledge = $this->get_knowledge_base(true);
        update_option('vac_content_last_sync', time());
        
        return [
            'success' => true,
            'characters' => strlen($knowledge),
        ];
    }
}

// Initialize
VAC_Content_Sync::get_instance();
