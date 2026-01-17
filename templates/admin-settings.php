<?php
/**
 * Admin Settings Template
 * With Content Sync (Post Types) Configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Get current settings
$gemini_api_key = get_option('vac_gemini_api_key', '');
$ai_auto_reply = get_option('vac_ai_auto_reply', true);
$greeting_message = get_option('vac_greeting_message', 'Xin chào! Tôi có thể giúp gì cho bạn?');
$knowledge_base = get_option('vac_ai_knowledge_base', '');
$widget_position = get_option('vac_widget_position', 'bottom-right');
$primary_color = get_option('vac_primary_color', '#0073aa');
$business_hours = get_option('vac_business_hours', ['start' => '08:00', 'end' => '17:00']);
$offline_message = get_option('vac_offline_message', 'Hiện tại chúng tôi không online. Vui lòng để lại tin nhắn.');

// Content sync settings
$content_sync_enabled = get_option('vac_content_sync_enabled', false);
$content_sync_config = get_option('vac_content_sync_config', []);
?>

<div class="wrap vac-settings">
    <h1>
        <span class="dashicons dashicons-admin-generic"></span>
        Cài đặt AI Chat
    </h1>
    
    <nav class="nav-tab-wrapper vac-tabs">
        <a href="?page=vac-settings&tab=general" 
           class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span> Chung
        </a>
        <a href="?page=vac-settings&tab=ai" 
           class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-welcome-learn-more"></span> AI & Gemini
        </a>
        <a href="?page=vac-settings&tab=widget" 
           class="nav-tab <?php echo $active_tab === 'widget' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-format-chat"></span> Widget
        </a>
        <a href="?page=vac-settings&tab=knowledge" 
           class="nav-tab <?php echo $active_tab === 'knowledge' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-book"></span> Knowledge Base
        </a>
    </nav>
    
    <form method="post" action="options.php" id="vac-settings-form">
        <?php settings_fields('vac_settings'); ?>
        
        <!-- Tab: General -->
        <div id="vac-tab-general" class="vac-tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
            <h2>Cài đặt chung</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Giờ làm việc</th>
                    <td>
                        <div class="vac-time-range">
                            <input type="time" name="vac_business_hours[start]" 
                                   value="<?php echo esc_attr($business_hours['start']); ?>">
                            <span>đến</span>
                            <input type="time" name="vac_business_hours[end]" 
                                   value="<?php echo esc_attr($business_hours['end']); ?>">
                        </div>
                        <p class="description">Thời gian nhân viên online hỗ trợ</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tin nhắn ngoài giờ</th>
                    <td>
                        <textarea name="vac_offline_message" rows="3" class="large-text"><?php echo esc_textarea($offline_message); ?></textarea>
                        <p class="description">Hiển thị khi ngoài giờ làm việc</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Email thông báo</th>
                    <td>
                        <input type="email" name="vac_notification_email" 
                               value="<?php echo esc_attr(get_option('vac_notification_email', get_option('admin_email'))); ?>" 
                               class="regular-text">
                        <p class="description">Email nhận thông báo khi có tin nhắn mới</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Tab: AI & Gemini -->
        <div id="vac-tab-ai" class="vac-tab-content <?php echo $active_tab === 'ai' ? 'active' : ''; ?>">
            <h2>Cài đặt AI & Gemini</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Gemini API Key</th>
                    <td>
                        <input type="password" name="vac_gemini_api_key" id="vac_gemini_api_key"
                               value="<?php echo esc_attr($gemini_api_key); ?>" 
                               class="regular-text">
                        <button type="button" class="button" id="vac-toggle-api-key">👁</button>
                        <button type="button" class="button button-secondary" id="vac-test-ai-btn">
                            Kiểm tra kết nối
                        </button>
                        <div id="vac-test-result" class="vac-test-result"></div>
                        <p class="description">
                            Lấy API Key tại: 
                            <a href="https://makersuite.google.com/app/apikey" target="_blank">
                                Google AI Studio
                            </a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">AI tự động trả lời</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vac_ai_auto_reply" value="1" 
                                   <?php checked($ai_auto_reply, true); ?>>
                            Bật AI tự động trả lời khách hàng
                        </label>
                        <p class="description">Khi tắt, tin nhắn sẽ được chuyển cho nhân viên</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tin nhắn chào mừng</th>
                    <td>
                        <textarea name="vac_greeting_message" rows="3" class="large-text"><?php echo esc_textarea($greeting_message); ?></textarea>
                        <p class="description">Tin nhắn hiển thị khi khách hàng mở chat</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Tab: Content Sync -->
        <div id="vac-tab-content-sync" class="vac-tab-content <?php echo $active_tab === 'content-sync' ? 'active' : ''; ?>">
            <h2>Đồng bộ nội dung từ Website</h2>
            <p class="description">
                Tự động lấy dữ liệu từ các Post Types (sản phẩm, bài viết, dịch vụ...) để AI có thể trả lời chính xác hơn.
            </p>
            
            <div class="vac-content-sync-wrapper">
                <div class="vac-sync-toggle">
                    <label class="vac-switch">
                        <input type="checkbox" id="vac-sync-enabled" 
                               <?php checked($content_sync_enabled, true); ?>>
                        <span class="vac-slider"></span>
                    </label>
                    <span class="vac-switch-label">Bật đồng bộ tự động</span>
                </div>
                
                <div id="vac-sync-config" class="<?php echo $content_sync_enabled ? '' : 'hidden'; ?>">
                    
                    <!-- Post Types Selection -->
                    <div class="vac-config-section">
                        <h3>
                            <span class="dashicons dashicons-category"></span>
                            Chọn Post Types
                        </h3>
                        <p class="description">Chọn loại nội dung muốn đưa vào knowledge base cho AI</p>
                        
                        <div id="vac-post-types-list" class="vac-post-types-grid">
                            <div class="vac-loading">
                                <span class="spinner is-active"></span> Đang tải...
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fields Configuration -->
                    <div class="vac-config-section" id="vac-fields-config" style="display:none;">
                        <h3>
                            <span class="dashicons dashicons-list-view"></span>
                            Chọn trường dữ liệu
                        </h3>
                        <p class="description">Chọn các trường thông tin muốn AI sử dụng</p>
                        
                        <div id="vac-fields-list" class="vac-fields-container">
                            <!-- Fields will be loaded dynamically -->
                        </div>
                    </div>
                    
                    <!-- Sync Options -->
                    <div class="vac-config-section">
                        <h3>
                            <span class="dashicons dashicons-admin-settings"></span>
                            Tùy chọn đồng bộ
                        </h3>
                        
                        <table class="form-table vac-sync-options">
                            <tr>
                                <th>Số lượng mỗi loại</th>
                                <td>
                                    <input type="number" id="vac-limit-per-type" 
                                           value="<?php echo isset($content_sync_config['limit_per_type']) ? intval($content_sync_config['limit_per_type']) : 50; ?>" 
                                           min="1" max="500" class="small-text">
                                    <p class="description">Số bài viết tối đa mỗi post type</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Độ dài nội dung</th>
                                <td>
                                    <input type="number" id="vac-content-length" 
                                           value="<?php echo isset($content_sync_config['content_length']) ? intval($content_sync_config['content_length']) : 500; ?>" 
                                           min="100" max="2000" class="small-text"> ký tự
                                    <p class="description">Số ký tự tối đa trích xuất từ nội dung</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Bao gồm</th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="vac-include-excerpt" 
                                               <?php checked(!isset($content_sync_config['include_excerpt']) || $content_sync_config['include_excerpt']); ?>>
                                        Mô tả ngắn (Excerpt)
                                    </label><br>
                                    <label>
                                        <input type="checkbox" id="vac-include-content" 
                                               <?php checked(!isset($content_sync_config['include_content']) || $content_sync_config['include_content']); ?>>
                                        Nội dung bài viết
                                    </label><br>
                                    <label>
                                        <input type="checkbox" id="vac-include-taxonomies" 
                                               <?php checked(!isset($content_sync_config['include_taxonomies']) || $content_sync_config['include_taxonomies']); ?>>
                                        Danh mục & Tags
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>Định dạng</th>
                                <td>
                                    <select id="vac-format">
                                        <option value="detailed" <?php selected(isset($content_sync_config['format']) ? $content_sync_config['format'] : 'detailed', 'detailed'); ?>>
                                            Chi tiết (đầy đủ thông tin)
                                        </option>
                                        <option value="simple" <?php selected(isset($content_sync_config['format']) ? $content_sync_config['format'] : '', 'simple'); ?>>
                                            Đơn giản (chỉ tiêu đề + mô tả)
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Preview & Sync Buttons -->
                    <div class="vac-sync-actions">
                        <button type="button" class="button button-secondary" id="vac-preview-btn">
                            <span class="dashicons dashicons-visibility"></span> Xem trước
                        </button>
                        <button type="button" class="button button-primary" id="vac-sync-btn">
                            <span class="dashicons dashicons-update"></span> Đồng bộ ngay
                        </button>
                        <span class="vac-sync-status" id="vac-sync-status"></span>
                    </div>
                    
                    <!-- Preview Area -->
                    <div id="vac-preview-area" class="vac-preview-area" style="display:none;">
                        <h4>Xem trước Knowledge Base</h4>
                        <div class="vac-preview-stats">
                            <span id="vac-preview-posts">0 bài viết</span> | 
                            <span id="vac-preview-chars">0 ký tự</span>
                        </div>
                        <div id="vac-preview-content" class="vac-preview-content"></div>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <!-- Tab: Widget -->
        <div id="vac-tab-widget" class="vac-tab-content <?php echo $active_tab === 'widget' ? 'active' : ''; ?>">
            <h2>Tùy chỉnh Widget</h2>
            
            <div class="vac-widget-settings">
                <div class="vac-widget-options">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Vị trí</th>
                            <td>
                                <label>
                                    <input type="radio" name="vac_widget_position" value="bottom-right" 
                                           <?php checked($widget_position, 'bottom-right'); ?>>
                                    Góc dưới bên phải
                                </label><br>
                                <label>
                                    <input type="radio" name="vac_widget_position" value="bottom-left" 
                                           <?php checked($widget_position, 'bottom-left'); ?>>
                                    Góc dưới bên trái
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Màu chủ đạo</th>
                            <td>
                                <input type="text" name="vac_primary_color" id="vac_primary_color"
                                       value="<?php echo esc_attr($primary_color); ?>" 
                                       class="vac-color-picker">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tiêu đề widget</th>
                            <td>
                                <input type="text" name="vac_widget_title" 
                                       value="<?php echo esc_attr(get_option('vac_widget_title', 'Chat với chúng tôi')); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Subtitle</th>
                            <td>
                                <input type="text" name="vac_widget_subtitle" 
                                       value="<?php echo esc_attr(get_option('vac_widget_subtitle', 'Thường trả lời trong vài phút')); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="vac-widget-preview-container">
                    <h4>Xem trước</h4>
                    <div class="vac-widget-preview preview-<?php echo esc_attr($widget_position); ?>">
                        <div class="vac-preview-button" style="background-color: <?php echo esc_attr($primary_color); ?>">
                            <span class="dashicons dashicons-format-chat"></span>
                        </div>
                        <div class="vac-preview-window">
                            <div class="vac-preview-header" style="background-color: <?php echo esc_attr($primary_color); ?>">
                                <div class="vac-preview-title">Chat với chúng tôi</div>
                                <div class="vac-preview-subtitle">Thường trả lời trong vài phút</div>
                            </div>
                            <div class="vac-preview-body">
                                <div class="vac-preview-message ai">
                                    Xin chào! Tôi có thể giúp gì cho bạn?
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab: Knowledge Base -->
        <div id="vac-tab-knowledge" class="vac-tab-content <?php echo $active_tab === 'knowledge' ? 'active' : ''; ?>">
            <h2>Knowledge Base (Thủ công)</h2>
            <p class="description">
                Thông tin bổ sung cho AI. Nội dung này sẽ được kết hợp với dữ liệu tự động đồng bộ (nếu có).
            </p>
            
            <table class="form-table">
                <tr>
                    <td colspan="2">
                        <textarea name="vac_ai_knowledge_base" id="vac_knowledge_base" 
                                  rows="20" class="large-text code"><?php echo esc_textarea($knowledge_base); ?></textarea>
                        <p class="vac-char-count"><?php echo strlen($knowledge_base); ?> ký tự</p>
                    </td>
                </tr>
            </table>
            
            <div class="vac-knowledge-tips">
                <h4>💡 Gợi ý nội dung</h4>
                <ul>
                    <li><strong>Thông tin công ty:</strong> Tên, địa chỉ, số điện thoại, email, giờ làm việc</li>
                    <li><strong>Sản phẩm/Dịch vụ:</strong> Mô tả, giá cả, tính năng nổi bật</li>
                    <li><strong>FAQ:</strong> Các câu hỏi thường gặp và câu trả lời</li>
                    <li><strong>Chính sách:</strong> Đổi trả, bảo hành, vận chuyển, thanh toán</li>
                    <li><strong>Khuyến mãi:</strong> Các chương trình đang diễn ra</li>
                </ul>
            </div>
        </div>
        
        <p class="submit">
            <?php submit_button('Lưu cài đặt', 'primary', 'submit', false); ?>
        </p>
    </form>
</div>

<style>
/* Settings Page Styles */
.vac-settings .nav-tab .dashicons {
    margin-right: 5px;
    vertical-align: text-bottom;
}

.vac-tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #c3c4c7;
    border-top: none;
}

.vac-tab-content.active {
    display: block;
}

/* Content Sync Styles */
.vac-content-sync-wrapper {
    max-width: 900px;
}

.vac-sync-toggle {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.vac-switch {
    position: relative;
    width: 50px;
    height: 26px;
}

.vac-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.vac-slider {
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

.vac-slider:before {
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

.vac-switch input:checked + .vac-slider {
    background-color: #0073aa;
}

.vac-switch input:checked + .vac-slider:before {
    transform: translateX(24px);
}

.vac-switch-label {
    font-weight: 500;
    font-size: 14px;
}

.vac-config-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.vac-config-section h3 {
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.vac-config-section h3 .dashicons {
    color: #0073aa;
}

/* Post Types Grid */
.vac-post-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.vac-post-type-card {
    background: #fff;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s;
}

.vac-post-type-card:hover {
    border-color: #0073aa;
}

.vac-post-type-card.selected {
    border-color: #0073aa;
    background: #f0f6fc;
}

.vac-post-type-card .card-header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.vac-post-type-card .card-checkbox {
    width: 18px;
    height: 18px;
}

.vac-post-type-card .card-label {
    font-weight: 500;
    flex: 1;
}

.vac-post-type-card .card-count {
    background: #e0e0e0;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
}

.vac-post-type-card.selected .card-count {
    background: #0073aa;
    color: #fff;
}

.vac-post-type-card .card-details {
    margin-top: 10px;
    font-size: 12px;
    color: #666;
}

/* Fields Container */
.vac-fields-container {
    margin-top: 15px;
}

.vac-fields-group {
    margin-bottom: 20px;
    background: #fff;
    padding: 15px;
    border-radius: 6px;
}

.vac-fields-group h4 {
    margin: 0 0 10px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.vac-fields-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
}

.vac-field-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.vac-field-item label {
    font-size: 13px;
    cursor: pointer;
}

/* Sync Actions */
.vac-sync-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.vac-sync-actions .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.vac-sync-status {
    color: #666;
    font-style: italic;
}

.vac-sync-status.success {
    color: #00a32a;
}

.vac-sync-status.error {
    color: #d63638;
}

/* Preview Area */
.vac-preview-area {
    margin-top: 25px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.vac-preview-area h4 {
    margin-top: 0;
}

.vac-preview-stats {
    margin-bottom: 15px;
    color: #666;
    font-size: 13px;
}

.vac-preview-content {
    max-height: 400px;
    overflow-y: auto;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    font-family: monospace;
    font-size: 13px;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* Test Result */
.vac-test-result {
    margin-top: 10px;
}

.vac-test-result .vac-success {
    color: #00a32a;
}

.vac-test-result .vac-error {
    color: #d63638;
}

.vac-test-result .vac-loading {
    color: #666;
}

/* Widget Preview */
.vac-widget-settings {
    display: flex;
    gap: 40px;
}

.vac-widget-options {
    flex: 1;
}

.vac-widget-preview-container {
    width: 320px;
}

.vac-widget-preview {
    position: relative;
    height: 400px;
    background: #f0f0f0;
    border-radius: 8px;
    padding: 20px;
}

.vac-preview-button {
    position: absolute;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.vac-widget-preview.preview-bottom-left .vac-preview-button {
    right: auto;
    left: 20px;
}

.vac-preview-window {
    position: absolute;
    bottom: 80px;
    right: 20px;
    width: 280px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    overflow: hidden;
}

.vac-widget-preview.preview-bottom-left .vac-preview-window {
    right: auto;
    left: 20px;
}

.vac-preview-header {
    padding: 15px;
    color: #fff;
}

.vac-preview-title {
    font-weight: bold;
}

.vac-preview-subtitle {
    font-size: 12px;
    opacity: 0.9;
}

.vac-preview-body {
    padding: 15px;
    min-height: 100px;
}

.vac-preview-message {
    background: #f0f0f0;
    padding: 10px 15px;
    border-radius: 15px;
    font-size: 13px;
}

/* Knowledge Tips */
.vac-knowledge-tips {
    background: #fef8e7;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.vac-knowledge-tips h4 {
    margin-top: 0;
}

.vac-knowledge-tips ul {
    margin-bottom: 0;
}

.vac-knowledge-tips li {
    margin-bottom: 8px;
}

/* Loading */
.vac-loading {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #666;
}

.hidden {
    display: none !important;
}

/* Responsive */
@media (max-width: 782px) {
    .vac-widget-settings {
        flex-direction: column;
    }
    
    .vac-widget-preview-container {
        width: 100%;
    }
    
    .vac-post-types-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab navigation (if using JS tabs instead of page reload)
    $('.vac-tabs .nav-tab').on('click', function(e) {
        // Allow normal navigation for now
    });
    
    // Toggle sync configuration
    $('#vac-sync-enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#vac-sync-config').removeClass('hidden');
            loadPostTypes();
        } else {
            $('#vac-sync-config').addClass('hidden');
        }
    });
    
    // Load post types on page load if sync is enabled
    if ($('#vac-sync-enabled').is(':checked')) {
        loadPostTypes();
    }
    
    // Load post types via AJAX
    function loadPostTypes() {
        var $container = $('#vac-post-types-list');
        $container.html('<div class="vac-loading"><span class="spinner is-active"></span> Đang tải...</div>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'vac_get_post_types',
                nonce: vacAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderPostTypes(response.data);
                } else {
                    $container.html('<div class="vac-error">Không thể tải danh sách post types</div>');
                }
            },
            error: function() {
                $container.html('<div class="vac-error">Lỗi kết nối</div>');
            }
        });
    }
    
    // Render post types
    function renderPostTypes(postTypes) {
        var $container = $('#vac-post-types-list');
        $container.empty();
        
        var savedConfig = <?php echo json_encode($content_sync_config); ?> || {};
        var savedTypes = savedConfig.post_types || [];
        var savedTypeNames = savedTypes.map(function(t) {
            return typeof t === 'object' ? t.name : t;
        });
        
        postTypes.forEach(function(type) {
            var isSelected = savedTypeNames.indexOf(type.name) > -1;
            var card = $('<div class="vac-post-type-card ' + (isSelected ? 'selected' : '') + '" data-type="' + type.name + '">' +
                '<div class="card-header">' +
                    '<input type="checkbox" class="card-checkbox" ' + (isSelected ? 'checked' : '') + '>' +
                    '<span class="card-label">' + type.label + '</span>' +
                    '<span class="card-count">' + type.count + '</span>' +
                '</div>' +
                '<div class="card-details">' +
                    'Slug: ' + type.name + 
                    (type.fields.length > 0 ? '<br>' + type.fields.length + ' custom fields' : '') +
                '</div>' +
            '</div>');
            
            card.data('fields', type.fields);
            card.data('taxonomies', type.taxonomies);
            $container.append(card);
        });
        
        // Card click handler
        $('.vac-post-type-card').on('click', function(e) {
            if (!$(e.target).is('input')) {
                var $checkbox = $(this).find('.card-checkbox');
                $checkbox.prop('checked', !$checkbox.is(':checked'));
            }
            $(this).toggleClass('selected', $(this).find('.card-checkbox').is(':checked'));
            updateFieldsConfig();
        });
    }
    
    // Update fields configuration
    function updateFieldsConfig() {
        var $fieldsContainer = $('#vac-fields-list');
        var $fieldsSection = $('#vac-fields-config');
        $fieldsContainer.empty();
        
        var hasFields = false;
        
        $('.vac-post-type-card.selected').each(function() {
            var typeName = $(this).data('type');
            var typeLabel = $(this).find('.card-label').text();
            var fields = $(this).data('fields') || [];
            var taxonomies = $(this).data('taxonomies') || [];
            
            if (fields.length > 0 || taxonomies.length > 0) {
                hasFields = true;
                var $group = $('<div class="vac-fields-group">' +
                    '<h4>' + typeLabel + '</h4>' +
                    '<div class="vac-fields-list"></div>' +
                '</div>');
                
                var $list = $group.find('.vac-fields-list');
                
                // Add standard fields
                var standardFields = ['title', 'content', 'excerpt', 'featured_image', 'url'];
                standardFields.forEach(function(field) {
                    $list.append('<div class="vac-field-item">' +
                        '<input type="checkbox" id="field-' + typeName + '-' + field + '" checked>' +
                        '<label for="field-' + typeName + '-' + field + '">' + ucfirst(field) + '</label>' +
                    '</div>');
                });
                
                // Add custom fields
                fields.forEach(function(field) {
                    $list.append('<div class="vac-field-item">' +
                        '<input type="checkbox" id="field-' + typeName + '-' + field.key + '" data-field="' + field.key + '">' +
                        '<label for="field-' + typeName + '-' + field.key + '">' + field.label + '</label>' +
                    '</div>');
                });
                
                $fieldsContainer.append($group);
            }
        });
        
        if (hasFields) {
            $fieldsSection.show();
        } else {
            $fieldsSection.hide();
        }
    }
    
    // Preview content
    $('#vac-preview-btn').on('click', function() {
        var config = buildSyncConfig();
        var $btn = $(this);
        
        $btn.prop('disabled', true).html('<span class="spinner is-active"></span> Đang tải...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'vac_preview_content',
                nonce: vacAdmin.nonce,
                config: JSON.stringify(config)
            },
            success: function(response) {
                if (response.success) {
                    $('#vac-preview-posts').text(response.data.post_count + ' bài viết');
                    $('#vac-preview-chars').text(response.data.preview.length + ' ký tự');
                    $('#vac-preview-content').text(response.data.preview);
                    $('#vac-preview-area').show();
                } else {
                    alert('Không thể tạo preview: ' + (response.data.message || 'Lỗi'));
                }
            },
            error: function() {
                alert('Lỗi kết nối');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span> Xem trước');
            }
        });
    });
    
    // Sync content
    $('#vac-sync-btn').on('click', function() {
        var config = buildSyncConfig();
        var $btn = $(this);
        var $status = $('#vac-sync-status');
        
        $btn.prop('disabled', true).html('<span class="spinner is-active"></span> Đang đồng bộ...');
        $status.removeClass('success error').text('');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'vac_sync_content',
                nonce: vacAdmin.nonce,
                config: JSON.stringify(config)
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text('✓ ' + response.data.message + 
                        ' (' + response.data.stats.characters + ' ký tự)');
                    
                    // Show preview
                    $('#vac-preview-chars').text(response.data.stats.characters + ' ký tự');
                    $('#vac-preview-content').text(response.data.preview);
                    $('#vac-preview-area').show();
                } else {
                    $status.addClass('error').text('✗ ' + (response.data.message || 'Lỗi đồng bộ'));
                }
            },
            error: function() {
                $status.addClass('error').text('✗ Lỗi kết nối');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Đồng bộ ngay');
            }
        });
    });
    
    // Build sync configuration
    function buildSyncConfig() {
        var postTypes = [];
        
        $('.vac-post-type-card.selected').each(function() {
            var typeName = $(this).data('type');
            var fields = [];
            
            // Get selected custom fields
            $('#vac-fields-list').find('input[data-field]:checked').each(function() {
                if ($(this).closest('.vac-fields-group').find('h4').text().indexOf($(this).closest('.vac-post-type-card').find('.card-label').text()) > -1) {
                    fields.push($(this).data('field'));
                }
            });
            
            postTypes.push({
                name: typeName,
                fields: fields
            });
        });
        
        return {
            post_types: postTypes,
            limit_per_type: parseInt($('#vac-limit-per-type').val()) || 50,
            content_length: parseInt($('#vac-content-length').val()) || 500,
            include_excerpt: $('#vac-include-excerpt').is(':checked'),
            include_content: $('#vac-include-content').is(':checked'),
            include_taxonomies: $('#vac-include-taxonomies').is(':checked'),
            format: $('#vac-format').val()
        };
    }
    
    // Test AI connection
    $('#vac-test-ai-btn').on('click', function() {
        var $btn = $(this);
        var $result = $('#vac-test-result');
        var apiKey = $('#vac_gemini_api_key').val();
        
        if (!apiKey) {
            $result.html('<span class="vac-error">Vui lòng nhập API Key</span>');
            return;
        }
        
        $btn.prop('disabled', true).text('Đang kiểm tra...');
        $result.html('<span class="vac-loading">Đang kết nối...</span>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'vac_test_ai_connection',
                nonce: vacAdmin.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span class="vac-success">✓ Kết nối thành công!</span>');
                } else {
                    $result.html('<span class="vac-error">✗ ' + (response.data.message || 'Không thể kết nối') + '</span>');
                }
            },
            error: function() {
                $result.html('<span class="vac-error">✗ Lỗi kết nối</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Kiểm tra kết nối');
            }
        });
    });
    
    // Toggle API key visibility
    $('#vac-toggle-api-key').on('click', function() {
        var $input = $('#vac_gemini_api_key');
        var type = $input.attr('type') === 'password' ? 'text' : 'password';
        $input.attr('type', type);
        $(this).text(type === 'password' ? '👁' : '🔒');
    });
    
    // Color picker
    if ($.fn.wpColorPicker) {
        $('.vac-color-picker').wpColorPicker({
            change: function(event, ui) {
                updateWidgetPreview();
            }
        });
    }
    
    // Widget position change
    $('input[name="vac_widget_position"]').on('change', updateWidgetPreview);
    
    function updateWidgetPreview() {
        var position = $('input[name="vac_widget_position"]:checked').val() || 'bottom-right';
        var color = $('#vac_primary_color').val() || '#0073aa';
        
        var $preview = $('.vac-widget-preview');
        $preview.removeClass('preview-bottom-left preview-bottom-right')
                .addClass('preview-' + position);
        
        $preview.find('.vac-preview-button').css('background-color', color);
        $preview.find('.vac-preview-header').css('background-color', color);
    }
    
    // Knowledge base character count
    $('#vac_knowledge_base').on('input', function() {
        var count = $(this).val().length;
        $('.vac-char-count').text(count + ' ký tự');
    });
    
    // Helper function
    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
});
</script>
