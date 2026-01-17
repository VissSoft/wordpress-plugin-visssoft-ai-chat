<?php
/**
 * Chat Widget Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$widget_position = get_option('vac_widget_position', 'bottom-right');
$primary_color = get_option('vac_primary_color', '#0073aa');
?>

<div id="vac-chat-widget" class="vac-widget vac-<?php echo esc_attr($widget_position); ?>" style="--vac-primary: <?php echo esc_attr($primary_color); ?>">
    
    <!-- Chat Button -->
    <button id="vac-chat-button" class="vac-chat-button" aria-label="Mở chat">
        <span class="vac-icon-chat">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3c-4.97 0-9 3.185-9 7.115 0 2.557 1.522 4.82 3.889 6.115l-.78 2.77 3.116-1.65c.885.21 1.815.325 2.775.325 4.97 0 9-3.186 9-7.115C21 6.185 16.97 3 12 3z"/></svg>
        </span>
        <span class="vac-icon-close">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </span>
        <span id="vac-unread-badge" class="vac-unread-badge" style="display:none;">0</span>
    </button>
    
    <!-- Chat Window -->
    <div id="vac-chat-window" class="vac-chat-window">
        
        <!-- Header -->
        <div class="vac-header">
            <div class="vac-header-info">
                <div class="vac-header-avatar">
                    <span class="vac-avatar-icon">🤖</span>
                    <span id="vac-online-indicator" class="vac-online-dot"></span>
                </div>
                <div class="vac-header-text">
                    <div class="vac-header-title"><?php echo esc_html(get_option('vac_widget_title', 'Chat với chúng tôi')); ?></div>
                    <div class="vac-header-subtitle" id="vac-status-text"><?php echo esc_html(get_option('vac_widget_subtitle', 'Thường trả lời trong vài phút')); ?></div>
                </div>
            </div>
            <button class="vac-header-close" aria-label="Đóng chat">&times;</button>
        </div>
        
        <!-- Body -->
        <div id="vac-chat-body" class="vac-body">
            <!-- Pre-chat form -->
            <div id="vac-prechat-form" class="vac-prechat" style="display:none;">
                <div class="vac-prechat-greeting">
                    <p>Chào bạn! Vui lòng cho biết thông tin để chúng tôi hỗ trợ tốt hơn.</p>
                </div>
                <form id="vac-visitor-form">
                    <div class="vac-form-group">
                        <input type="text" name="name" placeholder="Tên của bạn *" required>
                    </div>
                    <div class="vac-form-group">
                        <input type="email" name="email" placeholder="Email *" required>
                    </div>
                    <div class="vac-form-group">
                        <input type="tel" name="phone" placeholder="Số điện thoại (tùy chọn)">
                    </div>
                    <button type="submit" class="vac-btn-start">Bắt đầu chat</button>
                </form>
            </div>
            
            <!-- Messages container -->
            <div id="vac-messages" class="vac-messages">
                <!-- Messages will be loaded here -->
            </div>
            
            <!-- Typing indicator -->
            <div id="vac-typing" class="vac-typing" style="display:none;">
                <span class="vac-typing-dot"></span>
                <span class="vac-typing-dot"></span>
                <span class="vac-typing-dot"></span>
            </div>
        </div>
        
        <!-- Input area -->
        <div id="vac-input-area" class="vac-input-area">
            <form id="vac-message-form">
                <div class="vac-input-wrapper">
                    <textarea id="vac-message-input" placeholder="Nhập tin nhắn..." rows="1"></textarea>
                    <button type="submit" class="vac-send-btn" aria-label="Gửi">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Rating (shown after conversation) -->
        <div id="vac-rating" class="vac-rating" style="display:none;">
            <p>Đánh giá cuộc hội thoại</p>
            <div class="vac-stars">
                <span class="vac-star" data-rating="1">★</span>
                <span class="vac-star" data-rating="2">★</span>
                <span class="vac-star" data-rating="3">★</span>
                <span class="vac-star" data-rating="4">★</span>
                <span class="vac-star" data-rating="5">★</span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="vac-footer">
            <span>Powered by Visssoft AI</span>
        </div>
    </div>
</div>
