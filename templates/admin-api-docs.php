<?php
/**
 * Admin API Documentation Template
 * Hiển thị hướng dẫn sử dụng REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

$base_url = rest_url('visssoft-ai-chat/v1/');
$site_url = home_url();
?>

<div class="wrap vac-api-docs">
    <h1>
        <span class="dashicons dashicons-rest-api"></span>
        API Documentation
    </h1>

    <div class="vac-api-intro">
        <p class="description">
            Plugin cung cấp REST API để tích hợp chat AI vào website hoặc ứng dụng của bạn.
            Sử dụng các endpoints dưới đây để gửi/nhận tin nhắn, quản lý hội thoại và lấy thống kê.
        </p>
    </div>

    <!-- Base URL -->
    <div class="vac-api-section">
        <h2>🌐 Base URL</h2>
        <div class="vac-code-block">
            <code><?php echo esc_html($base_url); ?></code>
            <button class="button button-small copy-btn" data-copy="<?php echo esc_attr($base_url); ?>">
                📋 Copy
            </button>
        </div>
    </div>

    <!-- Authentication -->
    <div class="vac-api-section">
        <h2>🔐 Authentication</h2>
        <p><strong>Public APIs:</strong> Không cần authentication (dành cho chat widget)</p>
        <p><strong>Admin APIs:</strong> Cần đăng nhập WordPress và header <code>X-WP-Nonce</code></p>

        <div class="vac-example">
            <h4>Lấy Nonce trong JavaScript:</h4>
            <pre><code>const nonce = wpApiSettings.nonce;</code></pre>
        </div>
    </div>

    <!-- Public APIs -->
    <div class="vac-api-section">
        <h2>💬 Public APIs</h2>

        <!-- Send Message -->
        <div class="vac-endpoint">
            <div class="endpoint-header">
                <span class="method post">POST</span>
                <span class="path">/chat/send</span>
                <span class="badge">Gửi tin nhắn</span>
            </div>

            <div class="endpoint-body">
                <h4>Request Body:</h4>
                <pre><code>{
    "visitor_id": "v_1234567890_abc123",
    "message": "Xin chào, tôi cần hỗ trợ",
    "name": "Nguyễn Văn A",
    "email": "nguyenvana@example.com",
    "phone": "0123456789",
    "page_url": "<?php echo esc_js($site_url); ?>/product/abc"
}</code></pre>

                <h4>Response Success:</h4>
                <pre><code>{
    "success": true,
    "conversation_id": 123,
    "message_id": 456,
    "ai_response": {
        "id": 457,
        "sender_type": "ai",
        "message": "Xin chào! Tôi có thể giúp gì cho bạn?",
        "created_at": "2026-01-16 18:00:00"
    }
}</code></pre>

                <h4>JavaScript Example:</h4>
                <pre><code>const response = await fetch('<?php echo esc_js($base_url); ?>chat/send', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        visitor_id: 'v_123',
        message: 'Xin chào',
        name: 'Nguyễn Văn A',
        email: 'test@example.com'
    })
});

const data = await response.json();
console.log(data.ai_response.message);</code></pre>

                <button class="button try-it-btn" data-endpoint="chat/send" data-method="POST">
                    🚀 Try it
                </button>
            </div>
        </div>

        <!-- Get Messages -->
        <div class="vac-endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="path">/chat/messages</span>
                <span class="badge">Lấy tin nhắn</span>
            </div>

            <div class="endpoint-body">
                <h4>Query Parameters:</h4>
                <ul>
                    <li><code>conversation_id</code> (required) - ID cuộc hội thoại</li>
                    <li><code>after_id</code> (optional) - Chỉ lấy tin nhắn sau ID này</li>
                </ul>

                <h4>Example URL:</h4>
                <pre><code><?php echo esc_html($base_url); ?>chat/messages?conversation_id=123&after_id=450</code></pre>

                <h4>Response:</h4>
                <pre><code>{
    "success": true,
    "messages": [
        {
            "id": 451,
            "sender_type": "ai",
            "message": "Bạn cần hỗ trợ gì?",
            "created_at": "2026-01-16 18:01:00"
        }
    ]
}</code></pre>
            </div>
        </div>

        <!-- Rate Conversation -->
        <div class="vac-endpoint">
            <div class="endpoint-header">
                <span class="method post">POST</span>
                <span class="path">/chat/rate</span>
                <span class="badge">Đánh giá</span>
            </div>

            <div class="endpoint-body">
                <h4>Request Body:</h4>
                <pre><code>{
    "conversation_id": 123,
    "rating": 5,
    "comment": "Hỗ trợ rất tốt!"
}</code></pre>
            </div>
        </div>

        <!-- Get Status -->
        <div class="vac-endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="path">/chat/status</span>
                <span class="badge">Trạng thái</span>
            </div>

            <div class="endpoint-body">
                <p>Lấy trạng thái online/offline và thông tin cuộc hội thoại</p>
            </div>
        </div>
    </div>

    <!-- Admin APIs -->
    <div class="vac-api-section">
        <h2>🔧 Admin APIs</h2>
        <p class="notice notice-warning inline">
            <strong>⚠️ Lưu ý:</strong> Các API này yêu cầu đăng nhập WordPress với quyền <code>manage_options</code>
        </p>

        <!-- Get Conversations -->
        <div class="vac-endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="path">/admin/conversations</span>
                <span class="badge">Danh sách hội thoại</span>
            </div>

            <div class="endpoint-body">
                <h4>Query Parameters:</h4>
                <ul>
                    <li><code>status</code> - open, pending, resolved, closed</li>
                    <li><code>search</code> - Tìm kiếm theo tên/email</li>
                    <li><code>page</code> - Trang hiện tại (default: 1)</li>
                    <li><code>per_page</code> - Số items/trang (default: 20)</li>
                </ul>

                <h4>JavaScript Example:</h4>
                <pre><code>const response = await fetch('<?php echo esc_js($base_url); ?>admin/conversations?status=pending', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
});

const data = await response.json();
console.log(data.data.conversations);</code></pre>
            </div>
        </div>

        <!-- Reply to Conversation -->
        <div class="vac-endpoint">
            <div class="endpoint-header">
                <span class="method post">POST</span>
                <span class="path">/admin/conversations/{id}/reply</span>
                <span class="badge">Trả lời</span>
            </div>

            <div class="endpoint-body">
                <h4>Request Body:</h4>
                <pre><code>{
    "message": "Cảm ơn bạn đã liên hệ!"
}</code></pre>
            </div>
        </div>

        <!-- Get Stats -->
        <div class="vac-endpoint">
            <div class="endpoint-header">
                <span class="method get">GET</span>
                <span class="path">/admin/stats</span>
                <span class="badge">Thống kê</span>
            </div>

            <div class="endpoint-body">
                <h4>Response:</h4>
                <pre><code>{
    "success": true,
    "data": {
        "total_conversations": 150,
        "pending_conversations": 5,
        "resolved_today": 12,
        "avg_response_time": 120,
        "unread_count": 8
    }
}</code></pre>
            </div>
        </div>
    </div>

    <!-- Rate Limiting -->
    <div class="vac-api-section">
        <h2>⏱️ Rate Limiting</h2>
        <p>API có giới hạn requests để tránh spam:</p>
        <ul>
            <li><strong>Public APIs:</strong> 30 requests/phút mỗi visitor_id</li>
            <li><strong>Admin APIs:</strong> Không giới hạn</li>
        </ul>
        <p>Khi vượt quá limit, API trả về HTTP 429 với message: <code>Rate limit exceeded</code></p>
    </div>

    <!-- Complete Example -->
    <div class="vac-api-section">
        <h2>💻 Complete Chat Widget Example</h2>
        <pre><code>class ChatWidget {
    constructor() {
        this.baseUrl = '<?php echo esc_js($base_url); ?>';
        this.visitorId = this.getVisitorId();
        this.conversationId = null;
    }
    
    getVisitorId() {
        let id = localStorage.getItem('chat_visitor_id');
        if (!id) {
            id = 'v_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('chat_visitor_id', id);
        }
        return id;
    }
    
    async sendMessage(message, visitorInfo = {}) {
        const response = await fetch(this.baseUrl + 'chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                visitor_id: this.visitorId,
                message: message,
                ...visitorInfo
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.conversationId = data.conversation_id;
            return data.ai_response;
        }
        
        throw new Error(data.message);
    }
}

// Sử dụng
const chat = new ChatWidget();
const aiResponse = await chat.sendMessage('Xin chào', {
    name: 'Nguyễn Văn A',
    email: 'test@example.com'
});

console.log('AI:', aiResponse.message);</code></pre>
    </div>

    <!-- API Tester -->
    <div class="vac-api-section">
        <h2>🧪 API Tester</h2>
        <div class="vac-api-tester">
            <div class="tester-form">
                <label>
                    <strong>Endpoint:</strong>
                    <select id="test-endpoint">
                        <option value="chat/status">GET /chat/status</option>
                        <option value="admin/stats">GET /admin/stats (Admin)</option>
                        <option value="admin/unread-count">GET /admin/unread-count (Admin)</option>
                    </select>
                </label>

                <button class="button button-primary" id="test-api-btn">
                    🚀 Test API
                </button>
            </div>

            <div id="test-result" class="test-result"></div>
        </div>
    </div>

    <!-- Support -->
    <div class="vac-api-section">
        <h2>📞 Support</h2>
        <p>Nếu gặp vấn đề khi sử dụng API:</p>
        <ul>
            <li>Kiểm tra WordPress debug log</li>
            <li>Kiểm tra browser console</li>
            <li>Verify API key và permissions</li>
            <li>Liên hệ: <a href="mailto:support@visssoft.com">support@visssoft.com</a></li>
        </ul>
    </div>
</div>

<style>
    .vac-api-docs h1 .dashicons {
        font-size: 30px;
        vertical-align: middle;
        margin-right: 10px;
    }

    .vac-api-intro {
        background: #f0f6fc;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #0073aa;
        margin: 20px 0;
    }

    .vac-api-section {
        background: #fff;
        padding: 25px;
        margin: 20px 0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .vac-api-section h2 {
        margin-top: 0;
        padding-bottom: 15px;
        border-bottom: 2px solid #eee;
    }

    .vac-code-block {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 15px 0;
    }

    .vac-code-block code {
        flex: 1;
        font-size: 14px;
        color: #d63638;
    }

    .copy-btn {
        flex-shrink: 0;
    }

    .vac-endpoint {
        border: 1px solid #ddd;
        border-radius: 8px;
        margin: 20px 0;
        overflow: hidden;
    }

    .endpoint-header {
        background: #f9f9f9;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        border-bottom: 1px solid #ddd;
    }

    .method {
        padding: 5px 12px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 12px;
        text-transform: uppercase;
    }

    .method.get {
        background: #61affe;
        color: #fff;
    }

    .method.post {
        background: #49cc90;
        color: #fff;
    }

    .method.put {
        background: #fca130;
        color: #fff;
    }

    .method.delete {
        background: #f93e3e;
        color: #fff;
    }

    .endpoint-header .path {
        font-family: monospace;
        font-size: 14px;
        font-weight: 600;
    }

    .endpoint-header .badge {
        background: #0073aa;
        color: #fff;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
    }

    .endpoint-body {
        padding: 20px;
    }

    .endpoint-body h4 {
        margin: 20px 0 10px 0;
        color: #1d2327;
    }

    .endpoint-body pre {
        background: #282c34;
        color: #abb2bf;
        padding: 15px;
        border-radius: 6px;
        overflow-x: auto;
        font-size: 13px;
        line-height: 1.6;
    }

    .endpoint-body ul {
        margin: 10px 0;
        padding-left: 25px;
    }

    .endpoint-body ul li {
        margin: 8px 0;
    }

    .endpoint-body code {
        background: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 13px;
        color: #d63638;
    }

    .endpoint-body pre code {
        background: transparent;
        padding: 0;
        color: #abb2bf;
    }

    .try-it-btn {
        margin-top: 15px;
    }

    .vac-example {
        background: #fef8e7;
        padding: 15px;
        border-radius: 6px;
        margin: 15px 0;
    }

    .vac-example h4 {
        margin-top: 0;
    }

    .notice.inline {
        display: inline-block;
        padding: 10px 15px;
        margin: 10px 0;
    }

    .vac-api-tester {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
    }

    .tester-form {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        margin-bottom: 20px;
    }

    .tester-form label {
        flex: 1;
    }

    .tester-form select {
        width: 100%;
        margin-top: 5px;
    }

    .test-result {
        background: #282c34;
        color: #abb2bf;
        padding: 15px;
        border-radius: 6px;
        min-height: 100px;
        font-family: monospace;
        font-size: 13px;
        white-space: pre-wrap;
        word-wrap: break-word;
        display: none;
    }

    .test-result.show {
        display: block;
    }

    .test-result.success {
        border-left: 4px solid #49cc90;
    }

    .test-result.error {
        border-left: 4px solid #f93e3e;
    }
</style>

<script>
    jQuery(document).ready(function ($) {
        // Copy button
        $('.copy-btn').on('click', function () {
            const text = $(this).data('copy');
            navigator.clipboard.writeText(text).then(() => {
                const $btn = $(this);
                const originalText = $btn.text();
                $btn.text('✓ Copied!');
                setTimeout(() => $btn.text(originalText), 2000);
            });
        });

        // API Tester
        $('#test-api-btn').on('click', function () {
            const endpoint = $('#test-endpoint').val();
            const $result = $('#test-result');
            const $btn = $(this);

            $btn.prop('disabled', true).text('Testing...');
            $result.removeClass('success error').addClass('show').text('Loading...');

            const isAdmin = endpoint.startsWith('admin/');
            const headers = {
                'Content-Type': 'application/json'
            };

            if (isAdmin) {
                headers['X-WP-Nonce'] = vacAdmin.nonce;
            }

            fetch('<?php echo esc_js($base_url); ?>' + endpoint, {
                headers: headers
            })
                .then(response => response.json())
                .then(data => {
                    $result.addClass('success').text(JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    $result.addClass('error').text('Error: ' + error.message);
                })
                .finally(() => {
                    $btn.prop('disabled', false).text('🚀 Test API');
                });
        });
    });
</script>