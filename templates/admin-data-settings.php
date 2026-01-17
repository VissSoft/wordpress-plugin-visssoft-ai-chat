<?php
/**
 * Admin Data Settings Template
 * Cấu hình nguồn dữ liệu cho AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$data_collector = VAC_Data_Collector::get_instance();
$settings = $data_collector->get_data_settings();
$available_post_types = $data_collector->get_available_post_types();
$stats = $data_collector->get_data_stats();

// Xử lý form submit
if (isset($_POST['vac_save_data_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'vac_data_settings')) {
    $new_settings = array(
        'post_types' => array(),
        'include_site_info' => !empty($_POST['include_site_info']),
        'woocommerce' => array(
            'enabled' => !empty($_POST['wc_enabled']),
            'include_shipping' => !empty($_POST['wc_shipping']),
            'include_payment' => !empty($_POST['wc_payment']),
            'include_policies' => !empty($_POST['wc_policies'])
        ),
        'custom_data' => sanitize_textarea_field($_POST['custom_data']),
        'excluded_ids' => array_filter(array_map('intval', explode(',', $_POST['excluded_ids']))),
        'max_content_length' => intval($_POST['max_content_length']),
        'frontend_url' => esc_url_raw($_POST['frontend_url'])
    );
    
    // Post types settings
    if (!empty($_POST['post_types'])) {
        foreach ($_POST['post_types'] as $type => $config) {
            $new_settings['post_types'][$type] = array(
                'enabled' => !empty($config['enabled']),
                'limit' => intval($config['limit']),
                'fields' => isset($config['fields']) ? array_map('sanitize_text_field', $config['fields']) : array(),
                'custom_fields' => isset($config['custom_fields']) ? array_map('sanitize_text_field', $config['custom_fields']) : array(),
                'label' => sanitize_text_field($config['label'])
            );
        }
    }
    
    $data_collector->save_settings($new_settings);
    update_option('vac_data_last_updated', current_time('mysql'));
    
    echo '<div class="notice notice-success"><p>Đã lưu cài đặt thành công!</p></div>';
    
    // Refresh settings
    $settings = $data_collector->get_data_settings();
    $stats = $data_collector->get_data_stats();
}

// Xử lý refresh cache
if (isset($_POST['vac_refresh_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'vac_refresh_cache')) {
    $data_collector->invalidate_cache();
    $data_collector->get_ai_knowledge_data(true);
    update_option('vac_data_last_updated', current_time('mysql'));
    
    echo '<div class="notice notice-success"><p>Đã cập nhật dữ liệu thành công!</p></div>';
    $stats = $data_collector->get_data_stats();
}
?>

<div class="wrap vac-data-settings">
    <h1>
        <span class="dashicons dashicons-database"></span>
        Cấu hình nguồn dữ liệu cho AI
    </h1>
    
    <p class="description">
        Chọn các nguồn dữ liệu từ website để AI có thể sử dụng khi trả lời khách hàng.
        Dữ liệu sẽ được tự động cập nhật khi có thay đổi.
    </p>
    
    <!-- Thống kê -->
    <div class="vac-stats-cards">
        <div class="vac-stat-card">
            <span class="dashicons dashicons-chart-bar"></span>
            <div class="stat-content">
                <strong><?php echo number_format($stats['total_items']); ?></strong>
                <span>Tổng items</span>
            </div>
        </div>
        <div class="vac-stat-card">
            <span class="dashicons dashicons-media-text"></span>
            <div class="stat-content">
                <strong><?php echo esc_html($stats['data_size_formatted']); ?></strong>
                <span>Kích thước dữ liệu</span>
            </div>
        </div>
        <div class="vac-stat-card">
            <span class="dashicons dashicons-clock"></span>
            <div class="stat-content">
                <strong><?php echo esc_html($stats['last_updated']); ?></strong>
                <span>Cập nhật lần cuối</span>
            </div>
        </div>
    </div>
    
    <form method="post" id="vac-data-settings-form">
        <?php wp_nonce_field('vac_data_settings'); ?>
        <input type="hidden" name="vac_save_data_settings" value="1">
        
        <!-- Post Types -->
        <div class="vac-settings-section">
            <h2>
                <span class="dashicons dashicons-admin-post"></span>
                Loại nội dung (Post Types)
            </h2>
            
            <div class="vac-post-types-grid">
                <?php foreach ($available_post_types as $type => $info): 
                    $type_settings = isset($settings['post_types'][$type]) ? $settings['post_types'][$type] : array(
                        'enabled' => false,
                        'limit' => 50,
                        'fields' => array('title', 'excerpt'),
                        'custom_fields' => array()
                    );
                    $custom_fields = $data_collector->get_available_custom_fields($type);
                ?>
                <div class="vac-post-type-card <?php echo !empty($type_settings['enabled']) ? 'enabled' : ''; ?>">
                    <div class="card-header">
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   name="post_types[<?php echo esc_attr($type); ?>][enabled]" 
                                   value="1"
                                   class="toggle-post-type"
                                   <?php checked(!empty($type_settings['enabled'])); ?>>
                            <span class="slider"></span>
                        </label>
                        <div class="card-title">
                            <strong><?php echo esc_html($info['label']); ?></strong>
                            <span class="badge"><?php echo number_format($info['count']); ?> items</span>
                        </div>
                        <input type="hidden" 
                               name="post_types[<?php echo esc_attr($type); ?>][label]" 
                               value="<?php echo esc_attr($info['label']); ?>">
                    </div>
                    
                    <div class="card-body">
                        <div class="field-group">
                            <label>Số lượng tối đa:</label>
                            <input type="number" 
                                   name="post_types[<?php echo esc_attr($type); ?>][limit]" 
                                   value="<?php echo esc_attr($type_settings['limit']); ?>"
                                   min="1" max="500" class="small-text">
                        </div>
                        
                        <div class="field-group">
                            <label>Dữ liệu tự động:</label>
                            <p class="description" style="margin: 10px 0; padding: 10px; background: #f0f6fc; border-left: 3px solid #0073aa;">
                                ✅ <strong>Plugin tự động load TẤT CẢ dữ liệu quan trọng:</strong><br>
                                • Tiêu đề, Nội dung, Mô tả<br>
                                • Danh mục, Tags<br>
                                • Giá, Tình trạng hàng (WooCommerce)<br>
                                • <strong>TẤT CẢ ACF Fields</strong> (nếu có)<br>
                                <em>Không cần chọn gì cả - hoàn toàn tự động!</em>
                            </p>
                        </div>
                        
                        <?php if (!empty($custom_fields)): ?>
                        <div class="field-group">
                            <label>Custom Fields:</label>
                            <div class="checkbox-list custom-fields-list">
                                <?php foreach ($custom_fields as $field_key => $field_label): 
                                    $checked = isset($type_settings['custom_fields']) && in_array($field_key, $type_settings['custom_fields']);
                                ?>
                                <label title="<?php echo esc_attr($field_key); ?>">
                                    <input type="checkbox" 
                                           name="post_types[<?php echo esc_attr($type); ?>][custom_fields][]" 
                                           value="<?php echo esc_attr($field_key); ?>"
                                           <?php checked($checked); ?>>
                                    <?php echo esc_html($field_label); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- WooCommerce -->
        <?php if (class_exists('WooCommerce')): ?>
        <div class="vac-settings-section">
            <h2>
                <span class="dashicons dashicons-cart"></span>
                WooCommerce
            </h2>
            
            <table class="form-table">
                <tr>
                    <th>Bật tích hợp WooCommerce</th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" name="wc_enabled" value="1" 
                                   <?php checked(!empty($settings['woocommerce']['enabled'])); ?>>
                            <span class="slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Bao gồm thông tin</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc_shipping" value="1" 
                                   <?php checked(!empty($settings['woocommerce']['include_shipping'])); ?>>
                            Phương thức vận chuyển
                        </label><br>
                        <label>
                            <input type="checkbox" name="wc_payment" value="1" 
                                   <?php checked(!empty($settings['woocommerce']['include_payment'])); ?>>
                            Phương thức thanh toán
                        </label><br>
                        <label>
                            <input type="checkbox" name="wc_policies" value="1" 
                                   <?php checked(!empty($settings['woocommerce']['include_policies'])); ?>>
                            Chính sách (đổi trả, bảo hành...)
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Cài đặt chung -->
        <div class="vac-settings-section">
            <h2>
                <span class="dashicons dashicons-admin-generic"></span>
                Cài đặt chung
            </h2>
            
            <table class="form-table">
                <tr>
                    <th>Thông tin website</th>
                    <td>
                        <label>
                            <input type="checkbox" name="include_site_info" value="1" 
                                   <?php checked(!empty($settings['include_site_info'])); ?>>
                            Bao gồm thông tin cơ bản của website (tên, mô tả, email...)
                        </label>
                    </td>
                </tr>
                    <tr>
                        <th scope="row">Độ dài nội dung tối đa</th>
                        <td>
                            <input type="number" name="max_content_length" 
                                   value="<?php echo esc_attr($settings['max_content_length']); ?>"
                                   min="100" max="1000" class="small-text">
                            <p class="description">Số ký tự tối đa cho mỗi nội dung (100-1000)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="frontend_url">URL Frontend</label>
                        </th>
                        <td>
                            <input type="url" id="frontend_url" name="frontend_url" 
                                   value="<?php echo esc_attr($settings['frontend_url']); ?>"
                                   class="regular-text" 
                                   placeholder="https://banphucvuletang.vn">
                            <p class="description">
                                <strong>Tùy chọn:</strong> Nếu website frontend có domain khác với admin site, nhập URL frontend ở đây.<br>
                                Ví dụ: Admin site là <code>https://api.live-stream.io.vn</code>, Frontend là <code>https://banphucvuletang.vn</code><br>
                                AI sẽ trả về links với domain frontend thay vì admin domain.
                            </p>
                        </td>
                    </tr>
                <tr>
                    <th>ID loại trừ</th>
                    <td>
                        <input type="text" name="excluded_ids" 
                               value="<?php echo esc_attr(implode(',', $settings['excluded_ids'])); ?>"
                               class="regular-text" placeholder="1,2,3">
                        <p class="description">ID các bài viết/trang/sản phẩm muốn loại trừ (cách nhau bằng dấu phẩy)</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Dữ liệu bổ sung -->
        <div class="vac-settings-section">
            <h2>
                <span class="dashicons dashicons-edit"></span>
                Thông tin bổ sung
            </h2>
            
            <p class="description">
                Thêm thông tin bổ sung mà AI cần biết (FAQ, chính sách đặc biệt, thông tin liên hệ chi tiết...)
            </p>
            
            <textarea name="custom_data" rows="10" class="large-text code"><?php echo esc_textarea($settings['custom_data']); ?></textarea>
            
            <div class="vac-example-data">
                <strong>Ví dụ:</strong>
                <pre>
=== THÔNG TIN LIÊN HỆ ===
Hotline: 1900 xxxx (8h-22h hàng ngày)
Email: support@company.com
Zalo: 0123 456 789

=== CÂU HỎI THƯỜNG GẶP ===
Q: Làm sao để đặt hàng?
A: Quý khách có thể đặt hàng qua website hoặc gọi hotline

Q: Thời gian giao hàng bao lâu?
A: Giao hàng trong 2-3 ngày với nội thành, 5-7 ngày với tỉnh xa

=== KHUYẾN MÃI HIỆN TẠI ===
- Giảm 10% cho đơn hàng đầu tiên
- Freeship cho đơn từ 500k
                </pre>
            </div>
        </div>
        
        <div class="submit-buttons">
            <input type="submit" class="button button-primary button-large" value="Lưu cài đặt">
        </div>
    </form>
    
    <!-- Nút refresh cache -->
    <div class="vac-refresh-section">
        <h3>Cập nhật dữ liệu</h3>
        <p>Dữ liệu được cache để tăng hiệu suất. Bấm nút bên dưới để cập nhật ngay.</p>
        
        <form method="post" style="display: inline;">
            <?php wp_nonce_field('vac_refresh_cache'); ?>
            <input type="hidden" name="vac_refresh_cache" value="1">
            <button type="submit" class="button">
                <span class="dashicons dashicons-update"></span>
                Cập nhật dữ liệu ngay
            </button>
        </form>
    </div>
    
    <!-- Preview dữ liệu -->
    <div class="vac-preview-section">
        <h3>
            Xem trước dữ liệu
            <button type="button" class="button button-small" id="toggle-preview">Hiện/Ẩn</button>
        </h3>
        <div id="data-preview" style="display: none;">
            <pre class="data-preview-content"><?php 
                $preview = $data_collector->get_ai_knowledge_data(true);
                echo esc_html(substr($preview, 0, 5000));
                if (strlen($preview) > 5000) {
                    echo "\n\n... (đã cắt bớt, tổng " . strlen($preview) . " ký tự)";
                }
            ?></pre>
        </div>
    </div>
</div>

<style>
.vac-data-settings h1 .dashicons {
    font-size: 30px;
    vertical-align: middle;
    margin-right: 10px;
}

.vac-stats-cards {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}

.vac-stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.vac-stat-card .dashicons {
    font-size: 36px;
    color: #0073aa;
}

.vac-stat-card .stat-content strong {
    display: block;
    font-size: 20px;
    color: #1d2327;
}

.vac-stat-card .stat-content span {
    color: #646970;
    font-size: 13px;
}

.vac-settings-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.vac-settings-section h2 {
    margin-top: 0;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}

.vac-settings-section h2 .dashicons {
    color: #0073aa;
}

/* Post Type Cards */
.vac-post-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.vac-post-type-card {
    border: 2px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.vac-post-type-card.enabled {
    border-color: #0073aa;
}

.vac-post-type-card .card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-bottom: 1px solid #eee;
}

.vac-post-type-card.enabled .card-header {
    background: #e7f3ff;
}

.vac-post-type-card .card-title {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
}

.vac-post-type-card .badge {
    background: #0073aa;
    color: #fff;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.vac-post-type-card .card-body {
    padding: 15px;
}

.vac-post-type-card .field-group {
    margin-bottom: 15px;
}

.vac-post-type-card .field-group > label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.checkbox-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.checkbox-list label {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: #f5f5f5;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}

.checkbox-list label:hover {
    background: #e9e9e9;
}

.custom-fields-list {
    max-height: 150px;
    overflow-y: auto;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-switch .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 26px;
}

.toggle-switch .slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .slider {
    background-color: #0073aa;
}

.toggle-switch input:checked + .slider:before {
    transform: translateX(24px);
}

/* Submit */
.submit-buttons {
    margin: 30px 0;
}

/* Refresh Section */
.vac-refresh-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.vac-refresh-section h3 {
    margin-top: 0;
}

.vac-refresh-section .button .dashicons {
    vertical-align: text-bottom;
    margin-right: 5px;
}

/* Preview Section */
.vac-preview-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.vac-preview-section h3 {
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.data-preview-content {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 4px;
    max-height: 500px;
    overflow-y: auto;
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* Example Data */
.vac-example-data {
    margin-top: 15px;
    padding: 15px;
    background: #f0f6fc;
    border-radius: 6px;
    border-left: 4px solid #0073aa;
}

.vac-example-data pre {
    margin: 10px 0 0 0;
    background: #fff;
    padding: 15px;
    border-radius: 4px;
    font-size: 12px;
    overflow-x: auto;
}

/* Responsive */
@media (max-width: 782px) {
    .vac-stats-cards {
        flex-direction: column;
    }
    
    .vac-post-types-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle post type card enabled state
    $('.toggle-post-type').on('change', function() {
        $(this).closest('.vac-post-type-card').toggleClass('enabled', $(this).is(':checked'));
    });
    
    // Toggle preview
    $('#toggle-preview').on('click', function() {
        $('#data-preview').slideToggle();
    });
});
</script>
