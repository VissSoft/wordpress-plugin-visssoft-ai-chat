<?php
/**
 * Widget Settings Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Save settings
if (isset($_POST['vac_save_widget_settings']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'vac_widget_settings')) {
    $settings = array(
        // Colors
        'primary_color' => sanitize_hex_color($_POST['primary_color']),
        'background_color' => sanitize_hex_color($_POST['background_color']),
        'text_color' => sanitize_hex_color($_POST['text_color']),
        'border_color' => sanitize_hex_color($_POST['border_color']),
        
        // Text
        'title' => sanitize_text_field($_POST['title']),
        'subtitle' => sanitize_text_field($_POST['subtitle']),
        'welcome_message' => sanitize_textarea_field($_POST['welcome_message']),
        'input_placeholder' => sanitize_text_field($_POST['input_placeholder']),
        'send_button_text' => sanitize_text_field($_POST['send_button_text']),
        
        // Quick Replies
        'quick_replies' => isset($_POST['quick_replies']) ? array_map(function($reply) {
            return array(
                'id' => sanitize_text_field($reply['id']),
                'text' => sanitize_text_field($reply['text']),
                'message' => sanitize_textarea_field($reply['message'])
            );
        }, $_POST['quick_replies']) : array(),
        
        // Branding
        'logo' => esc_url_raw($_POST['logo']),
        'position' => sanitize_text_field($_POST['position']),
        
        // Behavior
        'auto_open' => isset($_POST['auto_open']),
        'show_quick_replies' => isset($_POST['show_quick_replies']),
    );
    
    update_option('vac_widget_settings', $settings);
    echo '<div class="notice notice-success"><p>Cài đặt đã được lưu!</p></div>';
}

// Get current settings
$defaults = array(
    'primary_color' => '#FDB913',
    'background_color' => '#FFF8E7',
    'text_color' => '#5C4033',
    'border_color' => '#F5A623',
    'title' => 'Ban tang lễ hỗ trợ/ tư vấn',
    'subtitle' => '',
    'welcome_message' => "Ban lễ tang xin chào quý khách.\nQuý khách cần tư vấn gì a ?",
    'input_placeholder' => 'Nhập tin nhắn...',
    'send_button_text' => 'Gửi',
    'quick_replies' => array(
        array('id' => '1', 'text' => 'Tang lễ', 'message' => 'Tôi muốn tư vấn về dịch vụ tang lễ'),
        array('id' => '2', 'text' => 'Đặt ô trọ', 'message' => 'Tôi muốn đặt ô trọ'),
        array('id' => '3', 'text' => 'Gói mộ', 'message' => 'Tôi muốn tư vấn về gói mộ'),
        array('id' => '4', 'text' => 'Xem hướng mộ', 'message' => 'Tôi muốn xem hướng mộ'),
    ),
    'logo' => '',
    'position' => 'bottom-right',
    'auto_open' => false,
    'show_quick_replies' => true,
);

$settings = wp_parse_args(get_option('vac_widget_settings', array()), $defaults);
?>

<div class="wrap vac-widget-settings">
    <h1>
        <span class="dashicons dashicons-admin-appearance"></span>
        Cấu hình giao diện Widget
    </h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('vac_widget_settings'); ?>
        
        <div class="vac-settings-grid">
            <!-- Preview Panel -->
            <div class="vac-preview-panel">
                <h2>Xem trước</h2>
                <div class="vac-preview-container">
                    <div id="vac-widget-preview" class="vac-widget-mockup">
                        <div class="vac-widget-button" id="preview-button">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                            </svg>
                        </div>
                        
                        <div class="vac-widget-window" id="preview-window">
                            <div class="vac-widget-header" id="preview-header">
                                <div class="vac-widget-title">
                                    <strong id="preview-title"><?php echo esc_html($settings['title']); ?></strong>
                                    <small id="preview-subtitle"><?php echo esc_html($settings['subtitle']); ?></small>
                                </div>
                            </div>
                            
                            <div class="vac-widget-messages">
                                <div class="vac-widget-message vac-message-ai">
                                    <div class="vac-message-avatar">🤖</div>
                                    <div class="vac-message-bubble" id="preview-welcome">
                                        <?php echo nl2br(esc_html($settings['welcome_message'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="vac-widget-quick-replies" id="preview-quick-replies">
                                <?php foreach (array_slice($settings['quick_replies'], 0, 4) as $reply): ?>
                                <button class="vac-quick-reply-btn"><?php echo esc_html($reply['text']); ?></button>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="vac-widget-input">
                                <input type="text" id="preview-placeholder" placeholder="<?php echo esc_attr($settings['input_placeholder']); ?>" readonly>
                                <button class="vac-send-btn" id="preview-send-btn"><?php echo esc_html($settings['send_button_text']); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="vac-embed-code">
                    <h3>Mã nhúng cho Next.js / React</h3>
                    <pre><code>&lt;ChatWidget 
  apiUrl="<?php echo esc_url(rest_url('visssoft-ai-chat/v1')); ?>"
  visitorName=""
  visitorEmail=""
  visitorPhone=""
/&gt;</code></pre>
                    <button type="button" class="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent)">
                        📋 Copy code
                    </button>
                </div>
            </div>
            
            <!-- Settings Panel -->
            <div class="vac-settings-panel">
                <div class="vac-settings-tabs">
                    <button type="button" class="vac-tab active" data-tab="colors">🎨 Màu sắc</button>
                    <button type="button" class="vac-tab" data-tab="text">📝 Nội dung</button>
                    <button type="button" class="vac-tab" data-tab="quick-replies">⚡ Quick Replies</button>
                    <button type="button" class="vac-tab" data-tab="branding">🏷️ Branding</button>
                    <button type="button" class="vac-tab" data-tab="behavior">⚙️ Hành vi</button>
                </div>
                
                <!-- Colors Tab -->
                <div class="vac-tab-content active" data-tab="colors">
                    <h2>Màu sắc</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="primary_color">Màu chính</label></th>
                            <td>
                                <input type="color" id="primary_color" name="primary_color" 
                                       value="<?php echo esc_attr($settings['primary_color']); ?>" 
                                       class="vac-color-picker">
                                <input type="text" value="<?php echo esc_attr($settings['primary_color']); ?>" 
                                       class="regular-text vac-color-text" readonly>
                                <p class="description">Màu nền cho buttons và message bubbles</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="background_color">Màu nền</label></th>
                            <td>
                                <input type="color" id="background_color" name="background_color" 
                                       value="<?php echo esc_attr($settings['background_color']); ?>" 
                                       class="vac-color-picker">
                                <input type="text" value="<?php echo esc_attr($settings['background_color']); ?>" 
                                       class="regular-text vac-color-text" readonly>
                                <p class="description">Màu nền của widget</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="text_color">Màu chữ</label></th>
                            <td>
                                <input type="color" id="text_color" name="text_color" 
                                       value="<?php echo esc_attr($settings['text_color']); ?>" 
                                       class="vac-color-picker">
                                <input type="text" value="<?php echo esc_attr($settings['text_color']); ?>" 
                                       class="regular-text vac-color-text" readonly>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="border_color">Màu viền</label></th>
                            <td>
                                <input type="color" id="border_color" name="border_color" 
                                       value="<?php echo esc_attr($settings['border_color']); ?>" 
                                       class="vac-color-picker">
                                <input type="text" value="<?php echo esc_attr($settings['border_color']); ?>" 
                                       class="regular-text vac-color-text" readonly>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Text Tab -->
                <div class="vac-tab-content" data-tab="text">
                    <h2>Nội dung văn bản</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="title">Tiêu đề</label></th>
                            <td>
                                <input type="text" id="title" name="title" 
                                       value="<?php echo esc_attr($settings['title']); ?>" 
                                       class="regular-text">
                                <p class="description">Hiển thị ở header widget</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="subtitle">Phụ đề</label></th>
                            <td>
                                <input type="text" id="subtitle" name="subtitle" 
                                       value="<?php echo esc_attr($settings['subtitle']); ?>" 
                                       class="regular-text">
                                <p class="description">Hiển thị dưới tiêu đề (tùy chọn)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="welcome_message">Tin nhắn chào mừng</label></th>
                            <td>
                                <textarea id="welcome_message" name="welcome_message" 
                                          rows="3" class="large-text"><?php echo esc_textarea($settings['welcome_message']); ?></textarea>
                                <p class="description">Tin nhắn đầu tiên khi mở widget. Dùng \n để xuống dòng</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="input_placeholder">Placeholder input</label></th>
                            <td>
                                <input type="text" id="input_placeholder" name="input_placeholder" 
                                       value="<?php echo esc_attr($settings['input_placeholder']); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="send_button_text">Text nút gửi</label></th>
                            <td>
                                <input type="text" id="send_button_text" name="send_button_text" 
                                       value="<?php echo esc_attr($settings['send_button_text']); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Quick Replies Tab -->
                <div class="vac-tab-content" data-tab="quick-replies">
                    <h2>Quick Replies</h2>
                    <p class="description">Các câu trả lời nhanh giúp khách hàng chọn chủ đề</p>
                    
                    <div id="vac-quick-replies-container">
                        <?php foreach ($settings['quick_replies'] as $index => $reply): ?>
                        <div class="vac-quick-reply-item">
                            <input type="hidden" name="quick_replies[<?php echo $index; ?>][id]" value="<?php echo esc_attr($reply['id']); ?>">
                            <div class="vac-quick-reply-fields">
                                <input type="text" name="quick_replies[<?php echo $index; ?>][text]" 
                                       value="<?php echo esc_attr($reply['text']); ?>" 
                                       placeholder="Text hiển thị" class="regular-text">
                                <textarea name="quick_replies[<?php echo $index; ?>][message]" 
                                          placeholder="Tin nhắn sẽ gửi" rows="2" class="large-text"><?php echo esc_textarea($reply['message']); ?></textarea>
                            </div>
                            <button type="button" class="button vac-remove-reply">Xóa</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="button" id="vac-add-quick-reply">+ Thêm Quick Reply</button>
                </div>
                
                <!-- Branding Tab -->
                <div class="vac-tab-content" data-tab="branding">
                    <h2>Branding</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="logo">Logo URL</label></th>
                            <td>
                                <input type="url" id="logo" name="logo" 
                                       value="<?php echo esc_attr($settings['logo']); ?>" 
                                       class="large-text">
                                <p class="description">URL hình ảnh logo (tùy chọn)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="position">Vị trí widget</label></th>
                            <td>
                                <select id="position" name="position">
                                    <option value="bottom-right" <?php selected($settings['position'], 'bottom-right'); ?>>Góc phải dưới</option>
                                    <option value="bottom-left" <?php selected($settings['position'], 'bottom-left'); ?>>Góc trái dưới</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Behavior Tab -->
                <div class="vac-tab-content" data-tab="behavior">
                    <h2>Hành vi</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th>Tự động mở</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_open" value="1" 
                                           <?php checked($settings['auto_open']); ?>>
                                    Tự động mở widget khi load trang
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Hiển thị Quick Replies</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="show_quick_replies" value="1" 
                                           <?php checked($settings['show_quick_replies']); ?>>
                                    Hiển thị quick replies trong widget
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="vac_save_widget_settings" class="button button-primary button-large">
                        💾 Lưu cài đặt
                    </button>
                    <button type="button" class="button button-large" id="vac-reset-defaults">
                        🔄 Khôi phục mặc định
                    </button>
                </p>
            </div>
        </div>
    </form>
</div>

<style>
.vac-widget-settings h1 .dashicons { vertical-align: middle; margin-right: 10px; }

.vac-settings-grid {
    display: grid;
    grid-template-columns: 1fr 600px;
    gap: 30px;
    margin-top: 20px;
}

.vac-preview-panel,
.vac-settings-panel {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.vac-preview-panel h2,
.vac-settings-panel h2 {
    margin-top: 0;
}

.vac-preview-container {
    background: #f0f0f1;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    min-height: 600px;
    display: flex;
    align-items: flex-end;
    justify-content: flex-end;
}

/* Widget Mockup Styles */
.vac-widget-mockup {
    position: relative;
    width: 380px;
}

.vac-widget-button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: <?php echo esc_attr($settings['primary_color']); ?>;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-left: auto;
    margin-bottom: 10px;
}

.vac-widget-window {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    overflow: hidden;
    width: 100%;
}

.vac-widget-header {
    background: <?php echo esc_attr($settings['primary_color']); ?>;
    color: white;
    padding: 20px;
}

.vac-widget-title strong {
    display: block;
    font-size: 16px;
    margin-bottom: 4px;
}

.vac-widget-title small {
    opacity: 0.9;
    font-size: 13px;
}

.vac-widget-messages {
    padding: 20px;
    background: <?php echo esc_attr($settings['background_color']); ?>;
    min-height: 300px;
    max-height: 400px;
    overflow-y: auto;
}

.vac-widget-message {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    align-items: flex-start;
}

.vac-message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.vac-message-bubble {
    background: white;
    padding: 12px 16px;
    border-radius: 12px;
    color: <?php echo esc_attr($settings['text_color']); ?>;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    max-width: 280px;
    line-height: 1.5;
}

.vac-widget-quick-replies {
    padding: 15px 20px;
    background: <?php echo esc_attr($settings['background_color']); ?>;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.vac-quick-reply-btn {
    background: white;
    border: 2px solid <?php echo esc_attr($settings['border_color']); ?>;
    color: <?php echo esc_attr($settings['text_color']); ?>;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.vac-quick-reply-btn:hover {
    background: <?php echo esc_attr($settings['primary_color']); ?>;
    color: white;
    border-color: <?php echo esc_attr($settings['primary_color']); ?>;
}

.vac-widget-input {
    padding: 15px 20px;
    background: white;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
}

.vac-widget-input input {
    flex: 1;
    padding: 10px 15px;
    border: 2px solid <?php echo esc_attr($settings['border_color']); ?>;
    border-radius: 24px;
    font-size: 14px;
}

.vac-send-btn {
    background: <?php echo esc_attr($settings['primary_color']); ?>;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 24px;
    font-weight: 500;
    cursor: pointer;
}

.vac-embed-code {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #2271b1;
}

.vac-embed-code h3 {
    margin-top: 0;
    font-size: 14px;
}

.vac-embed-code pre {
    background: #2c3338;
    color: #f0f0f1;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    margin: 10px 0;
}

.vac-settings-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    border-bottom: 2px solid #dcdcde;
}

.vac-tab {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #50575e;
    transition: all 0.2s;
}

.vac-tab:hover {
    color: #2271b1;
}

.vac-tab.active {
    color: #2271b1;
    border-bottom-color: #2271b1;
}

.vac-tab-content {
    display: none;
}

.vac-tab-content.active {
    display: block;
}

.vac-color-picker {
    width: 60px;
    height: 40px;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    cursor: pointer;
}

.vac-color-text {
    margin-left: 10px;
}

.vac-quick-reply-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.vac-quick-reply-fields {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.vac-remove-reply {
    background: #d63638;
    color: white;
    border-color: #d63638;
}

.vac-remove-reply:hover {
    background: #b32d2e;
    border-color: #b32d2e;
}

@media (max-width: 1400px) {
    .vac-settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.vac-tab').on('click', function() {
        const tab = $(this).data('tab');
        $('.vac-tab').removeClass('active');
        $(this).addClass('active');
        $('.vac-tab-content').removeClass('active');
        $(`.vac-tab-content[data-tab="${tab}"]`).addClass('active');
    });
    
    // Color picker sync
    $('.vac-color-picker').on('change', function() {
        $(this).next('.vac-color-text').val($(this).val());
        updatePreview();
    });
    
    // Add quick reply
    let replyIndex = <?php echo count($settings['quick_replies']); ?>;
    $('#vac-add-quick-reply').on('click', function() {
        const html = `
            <div class="vac-quick-reply-item">
                <input type="hidden" name="quick_replies[${replyIndex}][id]" value="${replyIndex + 1}">
                <div class="vac-quick-reply-fields">
                    <input type="text" name="quick_replies[${replyIndex}][text]" 
                           placeholder="Text hiển thị" class="regular-text">
                    <textarea name="quick_replies[${replyIndex}][message]" 
                              placeholder="Tin nhắn sẽ gửi" rows="2" class="large-text"></textarea>
                </div>
                <button type="button" class="button vac-remove-reply">Xóa</button>
            </div>
        `;
        $('#vac-quick-replies-container').append(html);
        replyIndex++;
    });
    
    // Remove quick reply
    $(document).on('click', '.vac-remove-reply', function() {
        $(this).closest('.vac-quick-reply-item').remove();
    });
    
    // Reset defaults
    $('#vac-reset-defaults').on('click', function() {
        if (confirm('Bạn có chắc muốn khôi phục cài đặt mặc định?')) {
            $('#primary_color').val('#FDB913').trigger('change');
            $('#background_color').val('#FFF8E7').trigger('change');
            $('#text_color').val('#5C4033').trigger('change');
            $('#border_color').val('#F5A623').trigger('change');
        }
    });
    
    // Live preview (simplified - would need full implementation)
    function updatePreview() {
        // This would update the iframe with new settings
        console.log('Preview updated');
    }
});
</script>
