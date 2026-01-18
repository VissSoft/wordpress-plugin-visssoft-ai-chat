<?php
/**
 * Data Collector Class
 * Thu thập dữ liệu từ WordPress post types để AI sử dụng
 */

if (!defined('ABSPATH')) {
    exit;
}

class VAC_Data_Collector
{

    private static $instance = null;
    private $cache_key = 'vac_ai_knowledge_data';
    private $cache_expiration = 3600; // 1 hour

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // Hook để tự động cập nhật cache khi có thay đổi
        add_action('save_post', array($this, 'invalidate_cache'), 10, 1);
        add_action('delete_post', array($this, 'invalidate_cache'), 10, 1);
        add_action('woocommerce_update_product', array($this, 'invalidate_cache'), 10, 1);
    }

    /**
     * Lấy tất cả dữ liệu đã được format cho AI
     */
    public function get_ai_knowledge_data($force_refresh = false)
    {
        // Check cache
        if (!$force_refresh) {
            $cached = get_transient($this->cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $data = array();
        $settings = $this->get_data_settings();

        // Thu thập từ các post types được chọn
        foreach ($settings['post_types'] as $post_type => $config) {
            if (!empty($config['enabled'])) {
                $data[$post_type] = $this->collect_post_type_data($post_type, $config);
            }
        }

        // Thu thập thông tin site
        if (!empty($settings['include_site_info'])) {
            $data['site_info'] = $this->collect_site_info();
        }

        // Thu thập từ WooCommerce nếu có
        if (!empty($settings['woocommerce']['enabled']) && class_exists('WooCommerce')) {
            $data['woocommerce'] = $this->collect_woocommerce_data($settings['woocommerce']);
        }

        // Thu thập custom data
        if (!empty($settings['custom_data'])) {
            $data['custom'] = $settings['custom_data'];
        }

        // Format thành text cho AI
        $formatted_data = $this->format_for_ai($data);

        // Optimize với Gemini nếu data quá lớn (> 100KB)
        $data_size = strlen($formatted_data);
        if ($data_size > 100000) {
            error_log("VAC: Data size {$data_size} bytes, optimizing with Gemini...");
            $formatted_data = $this->optimize_with_gemini($formatted_data);
        }

        // Final size check và truncate nếu vẫn quá lớn
        $final_size = strlen($formatted_data);
        if ($final_size > VAC_MAX_CACHE_SIZE) {
            error_log("VAC: Data still too large ({$final_size} bytes), truncating to " . VAC_MAX_CACHE_SIZE);
            $formatted_data = substr($formatted_data, 0, VAC_MAX_CACHE_SIZE);
            $formatted_data .= "\n\n[LƯU Ý: Dữ liệu đã được tối ưu và rút gọn. Nếu không tìm thấy thông tin, hãy yêu cầu khách hàng liên hệ trực tiếp.]";
        }

        // Cache kết quả
        set_transient($this->cache_key, $formatted_data, VAC_CACHE_EXPIRATION);

        return $formatted_data;
    }

    /**
     * Get frontend URL from admin URL
     * Replaces admin domain with frontend domain and converts URL pattern
     */
    private function get_frontend_url($admin_url, $post_id = null, $post_type = null)
    {
        $settings = $this->get_data_settings();

        // Check if frontend URL is configured
        if (empty($settings['frontend_url'])) {
            return $admin_url;
        }

        $frontend_base = rtrim($settings['frontend_url'], '/');

        // Special handling for products
        if ($post_type === 'product' && $post_id) {
            // Get product slug
            $post = get_post($post_id);
            if (!$post) {
                return $admin_url;
            }

            $product_slug = $post->post_name;

            // Get primary category
            $categories = wp_get_object_terms($post_id, 'product_cat');
            if (!is_wp_error($categories) && !empty($categories)) {
                $primary_cat = $categories[0];
                $category_id = $primary_cat->term_id;
                $category_slug = $primary_cat->slug;

                // Build frontend URL: /products/{slug}-{category_id}?category={category_slug}
                return $frontend_base . '/products/' . $product_slug . '-' . $category_id . '?category=' . $category_slug;
            }

            // Fallback if no category
            return $frontend_base . '/products/' . $product_slug;
        }

        // For other post types, just replace domain
        $admin_domain = parse_url(home_url(), PHP_URL_HOST);
        $frontend_domain = parse_url($frontend_base, PHP_URL_HOST);

        if ($admin_domain && $frontend_domain && $admin_domain !== $frontend_domain) {
            $admin_url = str_replace($admin_domain, $frontend_domain, $admin_url);
        }

        return $admin_url;
    }

    /**
     * Lấy cài đặt data sources
     */
    public function get_data_settings()
    {
        $defaults = array(
            'post_types' => array(
                'post' => array(
                    'enabled' => true,
                    'limit' => 15,  // Giảm số lượng để tránh quá tải
                    'fields' => array('title', 'excerpt', 'content', 'categories', 'tags'),  // ✅ Load tất cả fields
                    'label' => 'Bài viết'
                ),
                'page' => array(
                    'enabled' => true,
                    'limit' => 10,
                    'fields' => array('title', 'content'),  // ✅ Đầy đủ
                    'label' => 'Trang'
                ),
                'product' => array(
                    'enabled' => true,
                    'limit' => 25,  // Giảm từ 100 xuống 25
                    'fields' => array('title', 'description', 'price', 'categories', 'attributes', 'stock', 'image'),  // ✅ Thêm image
                    'label' => 'Sản phẩm'
                )
            ),
            'include_site_info' => true,
            'woocommerce' => array(
                'enabled' => true,
                'include_shipping' => true,
                'include_payment' => true,
                'include_policies' => false  // Tắt vì quá dài
            ),
            'custom_fields' => array(),
            'custom_data' => '',
            'excluded_ids' => array(),
            'max_content_length' => 300,  // Vừa đủ
            'frontend_url' => ''  // URL frontend (nếu khác admin URL)
        );

        $saved = get_option('vac_data_settings', array());
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Thu thập dữ liệu từ một post type
     */
    private function collect_post_type_data($post_type, $config)
    {
        $items = array();
        $settings = $this->get_data_settings();

        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => isset($config['limit']) ? $config['limit'] : 50,
            'orderby' => 'modified',
            'order' => 'DESC',
            'post__not_in' => $settings['excluded_ids']
        );

        $posts = get_posts($args);

        // Pre-fetch all terms to avoid N+1 queries
        $post_ids = wp_list_pluck($posts, 'ID');

        // Determine taxonomies
        $taxonomies = get_object_taxonomies($post_type, 'names');
        $cat_taxonomy = in_array('category', $taxonomies) ? 'category' : $post_type . '_cat';
        $tag_taxonomy = $post_type === 'product' ? 'product_tag' : 'post_tag';
        if ($post_type === 'product') {
            $cat_taxonomy = 'product_cat';
        }

        // Pre-fetch categories
        $all_categories = [];
        if (in_array('categories', isset($config['fields']) ? $config['fields'] : [])) {
            $terms = wp_get_object_terms($post_ids, $cat_taxonomy, array('fields' => 'all'));
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (!isset($all_categories[$term->object_id])) {
                        $all_categories[$term->object_id] = [];
                    }
                    $all_categories[$term->object_id][] = $term->name;
                }
            }
        }

        // Pre-fetch tags
        $all_tags = [];
        if (in_array('tags', isset($config['fields']) ? $config['fields'] : [])) {
            $terms = wp_get_object_terms($post_ids, $tag_taxonomy, array('fields' => 'all'));
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (!isset($all_tags[$term->object_id])) {
                        $all_tags[$term->object_id] = [];
                    }
                    $all_tags[$term->object_id][] = $term->name;
                }
            }
        }

        $items = array();
        foreach ($posts as $post) {
            $item = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => $this->get_frontend_url(get_permalink($post->ID), $post->ID, $post_type)
            );

            $fields = isset($config['fields']) ? $config['fields'] : array('title', 'excerpt');

            // Content/Excerpt
            if (in_array('content', $fields)) {
                $content = wp_strip_all_tags($post->post_content);
                $item['content'] = $this->truncate_content($content, $settings['max_content_length']);
            }

            if (in_array('excerpt', $fields)) {
                $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($post->post_content, 50);
                $item['excerpt'] = wp_strip_all_tags($excerpt);
            }

            if (in_array('description', $fields)) {
                // WooCommerce short description
                $short_desc = get_post_meta($post->ID, '_short_description', true);
                if (empty($short_desc)) {
                    $short_desc = $post->post_excerpt;
                }
                $item['description'] = wp_strip_all_tags($short_desc);
            }

            // Categories - Use pre-fetched data
            if (in_array('categories', $fields)) {
                $item['categories'] = $all_categories[$post->ID] ?? [];
            }

            // Tags - Use pre-fetched data
            if (in_array('tags', $fields)) {
                $item['tags'] = $all_tags[$post->ID] ?? [];
            }

            // Price (WooCommerce)
            if (in_array('price', $fields) && $post_type === 'product') {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $item['price'] = $product->get_price_html();
                    $item['regular_price'] = $product->get_regular_price();
                    $item['sale_price'] = $product->get_sale_price();
                    $item['in_stock'] = $product->is_in_stock();
                    $item['stock_status'] = $product->get_stock_status();
                }
            }

            // Attributes (WooCommerce)
            if (in_array('attributes', $fields) && $post_type === 'product') {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $attributes = $product->get_attributes();
                    $attr_data = array();
                    foreach ($attributes as $attr) {
                        if (is_object($attr)) {
                            $attr_data[$attr->get_name()] = $attr->get_options();
                        }
                    }
                    $item['attributes'] = $attr_data;
                }
            }

            // ✅ AUTO-LOAD ALL ACF FIELDS (if ACF is active)
            if (function_exists('get_field_objects')) {
                try {
                    $acf_fields = get_field_objects($post->ID);

                    // Debug log
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('VAC: Post ID ' . $post->ID . ' (' . $post->post_title . ') - ACF fields: ' . (is_array($acf_fields) ? count($acf_fields) : 'none'));
                    }

                    if ($acf_fields && is_array($acf_fields)) {
                        foreach ($acf_fields as $field_key => $field) {
                            // Skip if field data is invalid
                            if (!is_array($field) || !isset($field['value'])) {
                                continue;
                            }

                            // Get value
                            $value = $field['value'];

                            // Skip truly empty values (but keep 0, false, '0')
                            if ($value === '' || $value === null || $value === array()) {
                                continue;
                            }

                            // Format field name
                            $field_name = !empty($field['label']) ? $field['label'] : $field_key;
                            $field_type = isset($field['type']) ? $field['type'] : 'text';

                            // Format value based on field type
                            $formatted_value = $this->format_acf_value($value, $field_type);

                            // Add to item (keep 0 and false values)
                            if ($formatted_value !== '' && $formatted_value !== null) {
                                $item['acf_' . $field_key] = array(
                                    'label' => $field_name,
                                    'value' => $formatted_value
                                );

                                // Debug log
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    $preview = is_string($formatted_value) ? substr($formatted_value, 0, 50) : print_r($formatted_value, true);
                                    error_log('VAC: Added ACF field: ' . $field_key . ' (' . $field_type . ') = ' . $preview);
                                }
                            }
                        }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('VAC: No ACF fields found for post ID ' . $post->ID);
                        }
                    }
                } catch (Exception $e) {
                    error_log('VAC: Error loading ACF fields for post ' . $post->ID . ': ' . $e->getMessage());
                }
            } else {
                // Log once that ACF is not available
                static $acf_warning_logged = false;
                if (!$acf_warning_logged && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('VAC: ACF plugin not detected (get_field_objects function not found)');
                    $acf_warning_logged = true;
                }
            }

            // Legacy: Manual custom fields (fallback if ACF not available)
            if (!function_exists('get_field_objects') && !empty($config['custom_fields'])) {
                foreach ($config['custom_fields'] as $field_key) {
                    $value = get_post_meta($post->ID, $field_key, true);
                    if (!empty($value)) {
                        $item['custom_' . $field_key] = $this->format_meta_value($value);
                    }
                }
            }

            // Featured image and gallery - ALWAYS LOAD FOR ALL POST TYPES
            // Always try to get featured image first
            $image_url = get_the_post_thumbnail_url($post->ID, 'medium');
            if ($image_url) {
                $item['image'] = $image_url;
            }

            // Gallery images for WooCommerce products
            if ($post_type === 'product' && class_exists('WooCommerce')) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $gallery_ids = $product->get_gallery_image_ids();
                    if (!empty($gallery_ids)) {
                        $gallery_urls = array();
                        foreach ($gallery_ids as $attachment_id) {
                            $gallery_url = wp_get_attachment_url($attachment_id);
                            if ($gallery_url) {
                                $gallery_urls[] = $gallery_url;
                            }
                        }
                        if (!empty($gallery_urls)) {
                            $item['gallery_images'] = $gallery_urls;
                        }
                    }

                    // Variation images (for variable products)
                    if ($product->is_type('variable')) {
                        $variations = $product->get_available_variations();
                        $variation_images = array();

                        foreach ($variations as $variation) {
                            if (!empty($variation['image']['url'])) {
                                $variation_key = '';
                                // Build variation key from attributes
                                if (!empty($variation['attributes'])) {
                                    $attrs = array();
                                    foreach ($variation['attributes'] as $attr_name => $attr_value) {
                                        $attrs[] = $attr_value;
                                    }
                                    $variation_key = implode(' - ', $attrs);
                                }

                                $variation_images[] = array(
                                    'variation' => $variation_key,
                                    'image' => $variation['image']['url']
                                );
                            }
                        }

                        if (!empty($variation_images)) {
                            $item['variation_images'] = $variation_images;
                        }
                    }
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Thu thập thông tin site
     */
    private function collect_site_info()
    {
        return array(
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url(),
            'email' => get_option('admin_email'),
            'timezone' => wp_timezone_string(),
            'language' => get_locale()
        );
    }

    /**
     * Thu thập dữ liệu WooCommerce
     */
    private function collect_woocommerce_data($config)
    {
        if (!class_exists('WooCommerce')) {
            return array();
        }

        $data = array();

        // Thông tin cửa hàng
        $data['store_info'] = array(
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'store_address' => WC()->countries->get_base_address(),
            'store_city' => WC()->countries->get_base_city(),
            'store_country' => WC()->countries->get_base_country()
        );

        // Danh mục sản phẩm
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'number' => 50
        ));

        if (!is_wp_error($categories)) {
            $data['categories'] = array_map(function ($cat) {
                return array(
                    'name' => $cat->name,
                    'count' => $cat->count,
                    'url' => get_term_link($cat)
                );
            }, $categories);
        }

        // Phương thức vận chuyển
        if (!empty($config['include_shipping'])) {
            $shipping_zones = WC_Shipping_Zones::get_zones();
            $shipping_methods = array();

            foreach ($shipping_zones as $zone) {
                foreach ($zone['shipping_methods'] as $method) {
                    $shipping_methods[] = array(
                        'zone' => $zone['zone_name'],
                        'method' => $method->get_title(),
                        'cost' => $method->get_option('cost', 'N/A')
                    );
                }
            }
            $data['shipping_methods'] = $shipping_methods;
        }

        // Phương thức thanh toán
        if (!empty($config['include_payment'])) {
            $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $data['payment_methods'] = array_map(function ($gateway) {
                return array(
                    'id' => $gateway->id,
                    'title' => $gateway->get_title(),
                    'description' => $gateway->get_description()
                );
            }, $payment_gateways);
        }

        // Chính sách
        if (!empty($config['include_policies'])) {
            $data['policies'] = array(
                'terms_page' => $this->get_page_content(wc_get_page_id('terms')),
                'privacy_page' => $this->get_page_content(get_option('wp_page_for_privacy_policy')),
                'refund_page' => $this->get_page_content(wc_get_page_id('refund_returns'))
            );
        }

        return $data;
    }

    /**
     * Lấy nội dung trang
     */
    private function get_page_content($page_id)
    {
        if (!$page_id)
            return '';

        $page = get_post($page_id);
        if (!$page)
            return '';

        $settings = $this->get_data_settings();
        return array(
            'title' => $page->post_title,
            'content' => $this->truncate_content(
                wp_strip_all_tags($page->post_content),
                $settings['max_content_length']
            ),
            'url' => get_permalink($page_id)
        );
    }

    /**
     * Format dữ liệu thành text cho AI
     */
    private function format_for_ai($data)
    {
        $output = "";

        // Site info
        if (!empty($data['site_info'])) {
            $site = $data['site_info'];
            $output .= "=== THÔNG TIN WEBSITE ===\n";
            $output .= "Tên: {$site['name']}\n";
            $output .= "Mô tả: {$site['description']}\n";
            $output .= "Website: {$site['url']}\n";
            $output .= "Email liên hệ: {$site['email']}\n\n";
        }

        // WooCommerce info
        if (!empty($data['woocommerce'])) {
            $wc = $data['woocommerce'];

            if (!empty($wc['store_info'])) {
                $output .= "=== THÔNG TIN CỬA HÀNG ===\n";
                $output .= "Đơn vị tiền tệ: {$wc['store_info']['currency_symbol']}\n";
                if (!empty($wc['store_info']['store_address'])) {
                    $output .= "Địa chỉ: {$wc['store_info']['store_address']}, {$wc['store_info']['store_city']}\n";
                }
                $output .= "\n";
            }

            if (!empty($wc['categories'])) {
                $output .= "=== DANH MỤC SẢN PHẨM ===\n";
                foreach ($wc['categories'] as $cat) {
                    $output .= "- {$cat['name']} ({$cat['count']} sản phẩm)\n";
                }
                $output .= "\n";
            }

            if (!empty($wc['shipping_methods'])) {
                $output .= "=== PHƯƠNG THỨC VẬN CHUYỂN ===\n";
                foreach ($wc['shipping_methods'] as $method) {
                    $output .= "- {$method['zone']}: {$method['method']}";
                    if ($method['cost'] !== 'N/A') {
                        $output .= " - {$method['cost']}";
                    }
                    $output .= "\n";
                }
                $output .= "\n";
            }

            if (!empty($wc['payment_methods'])) {
                $output .= "=== PHƯƠNG THỨC THANH TOÁN ===\n";
                foreach ($wc['payment_methods'] as $method) {
                    $output .= "- {$method['title']}";
                    if (!empty($method['description'])) {
                        $output .= ": " . wp_strip_all_tags($method['description']);
                    }
                    $output .= "\n";
                }
                $output .= "\n";
            }

            if (!empty($wc['policies'])) {
                $output .= "=== CHÍNH SÁCH ===\n";
                foreach ($wc['policies'] as $key => $policy) {
                    if (!empty($policy['title'])) {
                        $output .= "--- {$policy['title']} ---\n";
                        $output .= "{$policy['content']}\n";
                        $output .= "Xem chi tiết: {$policy['url']}\n\n";
                    }
                }
            }
        }

        // Products
        if (!empty($data['product'])) {
            $output .= "=== DANH SÁCH SẢN PHẨM ===\n";
            foreach ($data['product'] as $product) {
                $output .= "\n[{$product['title']}]\n";

                if (!empty($product['description'])) {
                    $output .= "Mô tả: {$product['description']}\n";
                }

                if (!empty($product['price'])) {
                    $output .= "Giá: " . wp_strip_all_tags($product['price']) . "\n";
                }

                if (isset($product['in_stock'])) {
                    $stock_text = $product['in_stock'] ? 'Còn hàng' : 'Hết hàng';
                    $output .= "Tình trạng: {$stock_text}\n";
                }

                if (!empty($product['categories'])) {
                    $output .= "Danh mục: " . implode(', ', $product['categories']) . "\n";
                }

                if (!empty($product['attributes'])) {
                    foreach ($product['attributes'] as $attr_name => $attr_values) {
                        if (is_array($attr_values)) {
                            $output .= "{$attr_name}: " . implode(', ', $attr_values) . "\n";
                        }
                    }
                }

                // ✅ SHOW ACF FIELDS
                $has_acf = false;
                foreach ($product as $key => $value) {
                    if (strpos($key, 'acf_') === 0 && is_array($value)) {
                        if (!$has_acf) {
                            $output .= "Thông tin chi tiết:\n";
                            $has_acf = true;
                        }
                        $label = isset($value['label']) ? $value['label'] : str_replace('acf_', '', $key);
                        $field_value = isset($value['value']) ? $value['value'] : '';
                        if (!empty($field_value) || $field_value === '0' || $field_value === 0) {
                            $output .= "  - {$label}: {$field_value}\n";
                        }
                    }
                }

                // ✅ SHOW IMAGES
                if (!empty($product['image'])) {
                    $output .= "Ảnh sản phẩm: ![Ảnh]({$product['image']})\n";
                }

                if (!empty($product['gallery_images'])) {
                    $output .= "Thư viện ảnh:\n";
                    foreach ($product['gallery_images'] as $idx => $img_url) {
                        $output .= "  - ![Ảnh " . ($idx + 1) . "]({$img_url})\n";
                    }
                }

                if (!empty($product['variation_images'])) {
                    $output .= "Ảnh theo biến thể:\n";
                    foreach ($product['variation_images'] as $var) {
                        $variation_name = !empty($var['variation']) ? $var['variation'] : 'Mặc định';
                        $output .= "  - {$variation_name}: ![Ảnh]({$var['image']})\n";
                    }
                }

                $output .= "Link: {$product['url']}\n";
            }
            $output .= "\n";
        }

        // Posts/Articles
        if (!empty($data['post'])) {
            $output .= "=== BÀI VIẾT/TIN TỨC ===\n";
            foreach ($data['post'] as $post) {
                $output .= "\n[{$post['title']}]\n";

                if (!empty($post['excerpt'])) {
                    $output .= "{$post['excerpt']}\n";
                }

                if (!empty($post['categories'])) {
                    $output .= "Chuyên mục: " . implode(', ', $post['categories']) . "\n";
                }

                // ✅ SHOW ACF FIELDS FOR POSTS
                $has_acf = false;
                foreach ($post as $key => $value) {
                    if (strpos($key, 'acf_') === 0 && is_array($value)) {
                        if (!$has_acf) {
                            $output .= "Thông tin chi tiết:\n";
                            $has_acf = true;
                        }
                        $label = isset($value['label']) ? $value['label'] : str_replace('acf_', '', $key);
                        $field_value = isset($value['value']) ? $value['value'] : '';
                        if (!empty($field_value) || $field_value === '0' || $field_value === 0) {
                            $output .= "  - {$label}: {$field_value}\n";
                        }
                    }
                }

                $output .= "Link: {$post['url']}\n";
            }
            $output .= "\n";
        }

        // Pages
        if (!empty($data['page'])) {
            $output .= "=== CÁC TRANG THÔNG TIN ===\n";
            foreach ($data['page'] as $page) {
                $output .= "\n[{$page['title']}]\n";

                if (!empty($page['content'])) {
                    $output .= "{$page['content']}\n";
                }

                // ✅ SHOW ACF FIELDS FOR PAGES
                $has_acf = false;
                foreach ($page as $key => $value) {
                    if (strpos($key, 'acf_') === 0 && is_array($value)) {
                        if (!$has_acf) {
                            $output .= "Thông tin chi tiết:\n";
                            $has_acf = true;
                        }
                        $label = isset($value['label']) ? $value['label'] : str_replace('acf_', '', $key);
                        $field_value = isset($value['value']) ? $value['value'] : '';
                        if (!empty($field_value) || $field_value === '0' || $field_value === 0) {
                            $output .= "  - {$label}: {$field_value}\n";
                        }
                    }
                }

                $output .= "Link: {$page['url']}\n";
            }
            $output .= "\n";
        }

        // Custom post types
        $standard_types = array('post', 'page', 'product', 'site_info', 'woocommerce', 'custom');
        foreach ($data as $type => $items) {
            if (in_array($type, $standard_types))
                continue;
            if (empty($items))
                continue;

            $type_obj = get_post_type_object($type);
            $type_label = $type_obj ? $type_obj->labels->name : ucfirst($type);

            $output .= "=== {$type_label} ===\n";
            foreach ($items as $item) {
                $output .= "\n[{$item['title']}]\n";

                if (!empty($item['content'])) {
                    $output .= "{$item['content']}\n";
                } elseif (!empty($item['excerpt'])) {
                    $output .= "{$item['excerpt']}\n";
                }

                // Custom fields
                foreach ($item as $key => $value) {
                    if (strpos($key, 'custom_') === 0) {
                        $field_name = str_replace('custom_', '', $key);
                        $output .= "{$field_name}: {$value}\n";
                    }
                }

                $output .= "Link: {$item['url']}\n";
            }
            $output .= "\n";
        }

        // Custom data từ settings
        if (!empty($data['custom'])) {
            $output .= "=== THÔNG TIN BỔ SUNG ===\n";
            $output .= $data['custom'] . "\n\n";
        }

        return $output;
    }

    /**
     * Sử dụng Gemini API để optimize/summarize data
     */
    private function optimize_with_gemini($raw_data)
    {
        // Check if optimization is enabled
        if (!get_option('vac_auto_optimize', true)) {
            return $raw_data;
        }

        // Check if Gemini is available
        if (!class_exists('VAC_Gemini')) {
            return $raw_data;
        }

        $gemini = new VAC_Gemini();
        if (!$gemini->is_configured()) {
            error_log('VAC: Gemini not configured, skipping optimization');
            return $raw_data;
        }

        // Build optimization prompt
        $prompt = "Bạn là AI chuyên tóm tắt dữ liệu. Rút gọn dữ liệu sau xuống 20-30% kích thước, NHƯNG PHẢI GIỮ:

1. TẤT CẢ tên sản phẩm/dịch vụ
2. TẤT CẢ giá cả chính xác
3. Danh mục sản phẩm
4. Thông tin liên hệ
5. Tình trạng hàng (còn/hết)

Loại bỏ:
- Mô tả dài (chỉ giữ 1 câu ngắn gọn)
- Thông tin trùng lặp
- Chi tiết không quan trọng

Format: Giữ cấu trúc sections (===), nhưng nội dung ngắn gọn.

DỮ LIỆU:
---
{$raw_data}
---

TÓM TẮT:";

        try {
            // Call Gemini API
            $response = $gemini->generate_response($prompt, array());

            if ($response['success']) {
                $optimized = $response['message'];

                // Log compression ratio
                $original_size = strlen($raw_data);
                $optimized_size = strlen($optimized);
                $ratio = round((1 - $optimized_size / $original_size) * 100);

                error_log("VAC: Data optimized by Gemini - {$original_size} bytes → {$optimized_size} bytes ({$ratio}% reduction)");

                return $optimized;
            } else {
                error_log('VAC: Gemini optimization failed - ' . ($response['error'] ?? 'Unknown error'));
                return $raw_data;
            }
        } catch (Exception $e) {
            error_log('VAC: Exception in Gemini optimization - ' . $e->getMessage());
            return $raw_data;
        }
    }

    /**
     * Truncate content
     */
    private function truncate_content($content, $max_length)
    {
        $content = trim($content);
        if (strlen($content) <= $max_length) {
            return $content;
        }

        return substr($content, 0, $max_length) . '...';
    }

    /**
     * Format meta value
     */
    private function format_meta_value($value)
    {
        if (is_array($value)) {
            return implode(', ', array_filter($value));
        }

        if (is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Format ACF field value based on field type
     */
    private function format_acf_value($value, $type)
    {
        if (empty($value)) {
            return '';
        }

        switch ($type) {
            case 'text':
            case 'textarea':
            case 'email':
            case 'url':
            case 'number':
                return (string) $value;

            case 'wysiwyg':
                // Strip HTML tags from WYSIWYG
                return wp_strip_all_tags($value);

            case 'image':
                // Return image URL
                if (is_array($value) && isset($value['url'])) {
                    return $value['url'];
                } elseif (is_numeric($value)) {
                    return wp_get_attachment_url($value);
                }
                return '';

            case 'file':
                // Return file URL
                if (is_array($value) && isset($value['url'])) {
                    return $value['url'];
                }
                return '';

            case 'select':
            case 'radio':
            case 'button_group':
                return (string) $value;

            case 'checkbox':
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return (string) $value;

            case 'true_false':
                return $value ? 'Yes' : 'No';

            case 'link':
                if (is_array($value) && isset($value['url'])) {
                    return $value['url'];
                }
                return '';

            case 'post_object':
            case 'page_link':
                if (is_object($value) && isset($value->post_title)) {
                    return $value->post_title;
                } elseif (is_numeric($value)) {
                    $post = get_post($value);
                    return $post ? $post->post_title : '';
                }
                return '';

            case 'taxonomy':
                if (is_array($value)) {
                    $names = array();
                    foreach ($value as $term) {
                        if (is_object($term) && isset($term->name)) {
                            $names[] = $term->name;
                        }
                    }
                    return implode(', ', $names);
                }
                return '';

            case 'user':
                if (is_object($value) && isset($value->display_name)) {
                    return $value->display_name;
                }
                return '';

            case 'date_picker':
            case 'date_time_picker':
            case 'time_picker':
                return (string) $value;

            case 'color_picker':
                return (string) $value;

            case 'repeater':
            case 'group':
            case 'flexible_content':
                // For complex fields, return count or simplified representation
                if (is_array($value)) {
                    return count($value) . ' items';
                }
                return '';

            default:
                // Default: try to convert to string
                if (is_array($value)) {
                    return implode(', ', array_filter($value));
                }
                return (string) $value;
        }
    }

    /**
     * Invalidate cache khi có thay đổi
     */
    public function invalidate_cache($post_id = null)
    {
        delete_transient($this->cache_key);
    }

    /**
     * Lấy danh sách post types có sẵn
     */
    public function get_available_post_types()
    {
        $post_types = get_post_types(array('public' => true), 'objects');
        $available = array();

        foreach ($post_types as $type) {
            if ($type->name === 'attachment')
                continue;

            $available[$type->name] = array(
                'label' => $type->labels->name,
                'count' => wp_count_posts($type->name)->publish
            );
        }

        return $available;
    }

    /**
     * Lấy custom fields có sẵn
     */
    public function get_available_custom_fields($post_type = 'post')
    {
        global $wpdb;

        $fields = array();

        // Lấy từ postmeta
        $meta_keys = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.meta_key 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\_%'
            LIMIT 100
        ", $post_type));

        foreach ($meta_keys as $key) {
            $fields[$key] = $key;
        }

        // Lấy từ ACF nếu có
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(array(
                'post_type' => $post_type
            ));

            foreach ($field_groups as $group) {
                $acf_fields = acf_get_fields($group['key']);
                if ($acf_fields) {
                    foreach ($acf_fields as $field) {
                        $fields[$field['name']] = $field['label'] . ' (ACF)';
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Lưu cài đặt
     */
    public function save_settings($settings)
    {
        update_option('vac_data_settings', $settings);
        $this->invalidate_cache();
    }

    /**
     * Thống kê dữ liệu
     */
    public function get_data_stats()
    {
        $settings = $this->get_data_settings();
        $stats = array(
            'total_items' => 0,
            'by_type' => array(),
            'data_size' => 0,
            'last_updated' => get_option('vac_data_last_updated', 'Chưa cập nhật')
        );

        foreach ($settings['post_types'] as $post_type => $config) {
            if (!empty($config['enabled'])) {
                $count = wp_count_posts($post_type);
                $published = isset($count->publish) ? $count->publish : 0;
                $stats['by_type'][$post_type] = min($published, $config['limit']);
                $stats['total_items'] += $stats['by_type'][$post_type];
            }
        }

        // Ước tính kích thước dữ liệu
        $data = $this->get_ai_knowledge_data();
        $stats['data_size'] = strlen($data);
        $stats['data_size_formatted'] = size_format($stats['data_size']);

        return $stats;
    }
}
