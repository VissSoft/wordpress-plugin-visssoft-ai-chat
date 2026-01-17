<?php
/**
 * Admin Conversations Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// If viewing single conversation
if ($conversation_id) {
    $database = new VAC_Database();
    $conversation = $database->get_conversation($conversation_id);
    $messages = $database->get_messages($conversation_id);

    if (!$conversation) {
        echo '<div class="wrap"><h1>Không tìm thấy hội thoại</h1></div>';
        return;
    }

    // Mark as read
    $database->mark_messages_read($conversation_id, 'visitor');
    ?>

    <style>
        /* Force load CSS with high specificity */
        .wrap.vac-conversation-detail .vac-message-row {
            display: flex !important;
            gap: 12px !important;
            margin-bottom: 20px !important;
            align-items: flex-end !important;
        }

        .wrap.vac-conversation-detail .vac-message-left {
            justify-content: flex-start !important;
        }

        .wrap.vac-conversation-detail .vac-message-right {
            justify-content: flex-end !important;
        }

        .wrap.vac-conversation-detail .vac-avatar-small {
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 18px !important;
            flex-shrink: 0 !important;
        }

        .wrap.vac-conversation-detail .vac-avatar-ai {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
        }

        .wrap.vac-conversation-detail .vac-avatar-visitor {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #fff !important;
            font-weight: 600 !important;
        }

        .wrap.vac-conversation-detail .vac-message-bubble {
            max-width: 70% !important;
            padding: 12px 16px !important;
            border-radius: 16px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
        }

        .wrap.vac-conversation-detail .vac-bubble-ai {
            background: #fff !important;
            color: #2c3338 !important;
            border: 1px solid #e3f2fd !important;
            border-bottom-left-radius: 4px !important;
        }

        .wrap.vac-conversation-detail .vac-bubble-visitor {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #fff !important;
            border-bottom-right-radius: 4px !important;
        }

        .wrap.vac-conversation-detail .vac-message-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 6px !important;
        }

        .wrap.vac-conversation-detail .vac-sender-name {
            font-weight: 600 !important;
            font-size: 13px !important;
        }

        .wrap.vac-conversation-detail .vac-message-time {
            font-size: 11px !important;
            opacity: 0.7 !important;
        }

        .wrap.vac-conversation-detail .vac-message-text {
            font-size: 14px !important;
            line-height: 1.5 !important;
        }

        .wrap.vac-conversation-detail .vac-messages-container {
            flex: 1 !important;
            overflow-y: auto !important;
            padding: 24px !important;
            background: #f8f9fa !important;
            /* Remove max/min height constraints to let flexbox handle it */
            height: 100% !important;
        }

        .wrap.vac-conversation-detail .vac-detail-grid {
            display: grid !important;
            grid-template-columns: 1fr 350px !important;
            gap: 24px !important;
            margin-top: 24px !important;
            height: calc(100vh - 140px) !important;
            /* Fixed height for grid */
        }

        .wrap.vac-conversation-detail .vac-chat-window {
            background: #fff !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1) !important;
            display: flex !important;
            flex-direction: column !important;
            height: 100% !important;
            /* Fill grid cell */
            overflow: hidden !important;
            /* Contain children */

            min-height: 650px !important;
            overflow: hidden !important;
        }

        .wrap.vac-conversation-detail .vac-chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #fff !important;
            padding: 20px 24px !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border-radius: 16px 16px 0 0 !important;
            flex-shrink: 0 !important;
        }

        .wrap.vac-conversation-detail .vac-chat-footer {
            padding: 20px 24px !important;
            background: #fff !important;
            border-top: 1px solid #e3f2fd !important;
            flex-shrink: 0 !important;
        }

        .wrap.vac-conversation-detail .vac-input-wrapper textarea {
            width: 100% !important;
            min-height: 80px !important;
            padding: 12px 16px !important;
            border: 2px solid #e3f2fd !important;
            border-radius: 12px !important;
            font-size: 14px !important;
            resize: vertical !important;
            font-family: inherit !important;
            box-sizing: border-box !important;
        }

        .wrap.vac-conversation-detail .vac-input-wrapper {
            width: 100% !important;
            display: block !important;
        }

        .wrap.vac-conversation-detail .vac-input-wrapper textarea:focus {
            outline: none !important;
            border-color: #667eea !important;
        }

        .wrap.vac-conversation-detail .vac-input-actions {
            display: flex !important;
            gap: 12px !important;
            margin-top: 12px !important;
            justify-content: flex-end !important;
        }

        .wrap.vac-conversation-detail .vac-btn-primary,
        .wrap.vac-conversation-detail .vac-btn-secondary {
            padding: 10px 20px !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .wrap.vac-conversation-detail .vac-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #fff !important;
            border: none !important;
        }

        .wrap.vac-conversation-detail .vac-btn-secondary {
            background: #f8f9fa !important;
            color: #667eea !important;
            border: 2px solid #e3f2fd !important;
        }

        .wrap.vac-conversation-detail .vac-btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
        }

        .wrap.vac-conversation-detail .vac-btn-secondary:hover {
            background: #e3f2fd !important;
        }
    </style>

    <div class="wrap vac-conversation-detail">
        <div class="vac-detail-header">
            <div class="vac-header-left">
                <a href="<?php echo admin_url('admin.php?page=vac-conversations'); ?>" class="vac-back-btn">
                    <span class="dashicons dashicons-arrow-left-alt2"></span> Quay lại
                </a>
                <div class="vac-header-info">
                    <h2>Hội thoại #<?php echo $conversation_id; ?></h2>
                    <span class="vac-status-badge vac-status-<?php echo esc_attr($conversation->status); ?>">
                        <?php
                        $statuses = ['open' => 'Đang mở', 'pending' => 'Chờ xử lý', 'resolved' => 'Đã giải quyết', 'closed' => 'Đã đóng'];
                        echo esc_html($statuses[$conversation->status] ?? $conversation->status);
                        ?>
                    </span>
                </div>
            </div>
            <div class="vac-header-actions">
                <select id="vac-change-status" data-id="<?php echo $conversation_id; ?>" class="vac-status-select">
                    <option value="">Đổi trạng thái...</option>
                    <option value="open">Mở lại</option>
                    <option value="pending">Chờ xử lý</option>
                    <option value="resolved">Đã giải quyết</option>
                    <option value="closed">Đóng</option>
                </select>
            </div>
        </div>

        <div class="vac-detail-grid">
            <!-- Chat Window -->
            <div class="vac-chat-window">
                <div class="vac-chat-header">
                    <div class="vac-chat-user">
                        <div class="vac-avatar-large vac-avatar-visitor">
                            <?php echo strtoupper(substr($conversation->visitor_name ?: 'K', 0, 1)); ?>
                        </div>
                        <div class="vac-user-info">
                            <strong><?php echo esc_html($conversation->visitor_name ?: 'Khách'); ?></strong>
                            <small><?php echo esc_html($conversation->visitor_email ?: 'Chưa có email'); ?></small>
                        </div>
                    </div>
                    <div class="vac-chat-meta">
                        <span class="vac-time">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo esc_html(date('H:i - d/m/Y', strtotime($conversation->created_at))); ?>
                        </span>
                    </div>
                </div>

                <div id="vac-messages-container" class="vac-messages-container"
                    data-conversation="<?php echo $conversation_id; ?>">
                    <?php foreach ($messages as $msg): ?>
                        <?php $is_visitor = $msg->sender_type === 'visitor'; ?>
                        <div class="vac-message-row <?php echo $is_visitor ? 'vac-message-right' : 'vac-message-left'; ?>"
                            data-id="<?php echo esc_attr($msg->id); ?>">
                            <?php if (!$is_visitor): ?>
                                <div class="vac-avatar-small vac-avatar-<?php echo esc_attr($msg->sender_type); ?>">
                                    <?php
                                    switch ($msg->sender_type) {
                                        case 'ai':
                                            echo '🤖';
                                            break;
                                        case 'staff':
                                            echo '👤';
                                            break;
                                        case 'system':
                                            echo '⚙️';
                                            break;
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>

                            <div class="vac-message-bubble vac-bubble-<?php echo esc_attr($msg->sender_type); ?>">
                                <div class="vac-message-header">
                                    <span class="vac-sender-name">
                                        <?php
                                        switch ($msg->sender_type) {
                                            case 'visitor':
                                                echo esc_html($conversation->visitor_name ?: 'Khách');
                                                break;
                                            case 'ai':
                                                echo 'AI Assistant';
                                                break;
                                            case 'staff':
                                                echo esc_html($msg->staff_name ?: 'Nhân viên');
                                                break;
                                            case 'system':
                                                echo 'Hệ thống';
                                                break;
                                        }
                                        ?>
                                    </span>
                                    <span class="vac-message-time">
                                        <?php echo esc_html(date('H:i', strtotime($msg->created_at))); ?>
                                    </span>
                                </div>
                                <div class="vac-message-text"><?php echo nl2br(esc_html($msg->message)); ?></div>
                            </div>

                            <?php if ($is_visitor): ?>
                                <div class="vac-avatar-small vac-avatar-visitor">
                                    <?php echo strtoupper(substr($conversation->visitor_name ?: 'K', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Form -->
                <div class="vac-chat-footer">
                    <form id="vac-staff-reply-form">
                        <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                        <div class="vac-input-wrapper">
                            <textarea name="message" placeholder="Nhập tin nhắn trả lời..." rows="2" required></textarea>
                            <div class="vac-input-actions">
                                <button type="button" class="vac-btn-secondary" id="vac-ai-suggest">
                                    <span class="dashicons dashicons-lightbulb"></span> Gợi ý AI
                                </button>
                                <button type="submit" class="vac-btn-primary">
                                    <span class="dashicons dashicons-yes"></span> Gửi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="vac-sidebar">
                <div class="vac-info-card">
                    <h3><span class="dashicons dashicons-id"></span> Thông tin khách hàng</h3>
                    <div class="vac-info-list">
                        <div class="vac-info-item">
                            <span class="vac-info-label">
                                <span class="dashicons dashicons-admin-users"></span> Tên
                            </span>
                            <span
                                class="vac-info-value"><?php echo esc_html($conversation->visitor_name ?: 'Chưa có'); ?></span>
                        </div>
                        <div class="vac-info-item">
                            <span class="vac-info-label">
                                <span class="dashicons dashicons-email"></span> Email
                            </span>
                            <span
                                class="vac-info-value"><?php echo esc_html($conversation->visitor_email ?: 'Chưa có'); ?></span>
                        </div>
                        <div class="vac-info-item">
                            <span class="vac-info-label">
                                <span class="dashicons dashicons-phone"></span> Số điện thoại
                            </span>
                            <span
                                class="vac-info-value"><?php echo esc_html($conversation->visitor_phone ?: 'Chưa có'); ?></span>
                        </div>
                        <div class="vac-info-item">
                            <span class="vac-info-label">
                                <span class="dashicons dashicons-location"></span> IP Address
                            </span>
                            <span class="vac-info-value"><?php echo esc_html($conversation->ip_address); ?></span>
                        </div>
                        <div class="vac-info-item">
                            <span class="vac-info-label">
                                <span class="dashicons dashicons-calendar-alt"></span> Thời gian
                            </span>
                            <span
                                class="vac-info-value"><?php echo esc_html(date('H:i d/m/Y', strtotime($conversation->created_at))); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($conversation->rating): ?>
                    <div class="vac-info-card vac-rating-card">
                        <h3><span class="dashicons dashicons-star-filled"></span> Đánh giá</h3>
                        <div class="vac-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="<?php echo $i <= $conversation->rating ? 'filled' : ''; ?>">★</span>
                            <?php endfor; ?>
                            <span class="vac-rating-number"><?php echo $conversation->rating; ?>/5</span>
                        </div>
                        <?php if ($conversation->rating_comment): ?>
                            <p class="vac-rating-comment"><?php echo esc_html($conversation->rating_comment); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return;
}

// List view
$database = new VAC_Database();
$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$result = $database->get_conversations([
    'status' => $status_filter,
    'search' => $search,
    'page' => $page,
]);
?>

<div class="wrap vac-conversations">
    <h1>
        <span class="dashicons dashicons-format-chat"></span>
        Quản lý hội thoại
    </h1>

    <!-- Filters -->
    <div class="vac-filters">
        <ul class="subsubsub">
            <li><a href="<?php echo admin_url('admin.php?page=vac-conversations'); ?>"
                    class="<?php echo empty($status_filter) ? 'current' : ''; ?>">Tất cả</a> |</li>
            <li><a href="<?php echo admin_url('admin.php?page=vac-conversations&status=open'); ?>"
                    class="<?php echo $status_filter === 'open' ? 'current' : ''; ?>">Mở</a> |</li>
            <li><a href="<?php echo admin_url('admin.php?page=vac-conversations&status=pending'); ?>"
                    class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">Chờ xử lý</a> |</li>
            <li><a href="<?php echo admin_url('admin.php?page=vac-conversations&status=resolved'); ?>"
                    class="<?php echo $status_filter === 'resolved' ? 'current' : ''; ?>">Đã giải quyết</a> |</li>
            <li><a href="<?php echo admin_url('admin.php?page=vac-conversations&status=closed'); ?>"
                    class="<?php echo $status_filter === 'closed' ? 'current' : ''; ?>">Đã đóng</a></li>
        </ul>

        <form method="get" class="search-form">
            <input type="hidden" name="page" value="vac-conversations">
            <?php if ($status_filter): ?>
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
            <?php endif; ?>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm theo tên, email...">
            <button type="submit" class="button">Tìm kiếm</button>
        </form>
    </div>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:40px;"><input type="checkbox" id="vac-select-all"></th>
                <th>Khách hàng</th>
                <th>Tin nhắn gần nhất</th>
                <th>Trạng thái</th>
                <th>Xử lý</th>
                <th>Cập nhật</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($result['conversations'])): ?>
                <tr>
                    <td colspan="7" style="text-align:center;">Không có hội thoại nào.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($result['conversations'] as $conv): ?>
                    <tr class="<?php echo $conv->unread_count > 0 ? 'vac-unread' : ''; ?>">
                        <td><input type="checkbox" name="conversation_ids[]" value="<?php echo $conv->id; ?>"></td>
                        <td>
                            <strong><?php echo esc_html($conv->visitor_name ?: 'Khách #' . $conv->id); ?></strong>
                            <?php if ($conv->unread_count > 0): ?>
                                <span class="vac-badge"><?php echo $conv->unread_count; ?></span>
                            <?php endif; ?>
                            <br><small><?php echo esc_html($conv->visitor_email); ?></small>
                        </td>
                        <td>
                            <div class="vac-preview"><?php echo esc_html(mb_substr($conv->last_message, 0, 60)); ?>...</div>
                        </td>
                        <td>
                            <span class="vac-status-badge vac-status-<?php echo esc_attr($conv->status); ?>">
                                <?php
                                $statuses = ['open' => 'Mở', 'pending' => 'Chờ', 'resolved' => 'Xong', 'closed' => 'Đóng'];
                                echo esc_html($statuses[$conv->status] ?? $conv->status);
                                ?>
                            </span>
                        </td>
                        <td><?php echo $conv->handled_by === 'ai' ? '🤖' : '👤'; ?></td>
                        <td><?php echo esc_html(human_time_diff(strtotime($conv->updated_at))); ?> trước</td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=vac-conversations&conversation_id=' . $conv->id); ?>"
                                class="button button-small">Xem</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($result['pagination']['total_pages'] > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $result['pagination']['total_pages'],
                    'current' => $page,
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* List View Styles */
    .vac-conversations h1 .dashicons {
        vertical-align: middle;
        margin-right: 10px;
    }

    .vac-filters {
        margin: 20px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .vac-filters .search-form {
        display: flex;
        gap: 5px;
    }

    .vac-unread {
        background: #fffbf0 !important;
    }

    .vac-badge {
        background: #d63638;
        color: #fff;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 11px;
    }

    .vac-status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .vac-status-open {
        background: #e0f0ff;
        color: #0073aa;
    }

    .vac-status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .vac-status-resolved {
        background: #d4edda;
        color: #155724;
    }

    .vac-status-closed {
        background: #e9ecef;
        color: #6c757d;
    }

    .vac-preview {
        color: #666;
        font-size: 13px;
    }

    /* Detail View - Modern Chat UI */
    .vac-conversation-detail {
        max-width: 1400px;
        margin: 20px auto;
    }

    .vac-detail-header {
        background: #fff;
        padding: 20px 30px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .vac-header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .vac-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 16px;
        background: #f0f0f1;
        border-radius: 8px;
        text-decoration: none;
        color: #2c3338;
        font-weight: 500;
        transition: all 0.2s;
    }

    .vac-back-btn:hover {
        background: #dcdcde;
        color: #000;
    }

    .vac-header-info h2 {
        margin: 0 0 5px 0;
        font-size: 20px;
        color: #1d2327;
    }

    .vac-status-select {
        padding: 8px 16px;
        border-radius: 8px;
        border: 1px solid #dcdcde;
        background: #fff;
        font-size: 14px;
    }

    .vac-detail-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 20px;
    }

    /* Chat Window - Modern Messaging App Style */
    .vac-chat-window {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        height: 700px;
        overflow: hidden;
    }

    .vac-chat-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 20px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 16px 16px 0 0;
    }

    .vac-chat-user {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .vac-avatar-large {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.3);
        color: #fff;
        border: 3px solid rgba(255, 255, 255, 0.5);
    }

    .vac-user-info strong {
        display: block;
        font-size: 16px;
        margin-bottom: 2px;
    }

    .vac-user-info small {
        opacity: 0.9;
        font-size: 13px;
    }

    .vac-chat-meta {
        opacity: 0.9;
        font-size: 13px;
    }

    .vac-chat-meta .dashicons {
        font-size: 16px;
        vertical-align: middle;
    }

    /* Messages Container */
    .vac-messages-container {
        flex: 1 !important;
        overflow-y: auto !important;
        padding: 24px !important;
        background: #f8f9fa !important;
    }

    /* Message Row */
    .vac-message-row {
        display: flex !important;
        gap: 12px !important;
        margin-bottom: 20px !important;
        align-items: flex-end !important;
    }

    .vac-message-left {
        justify-content: flex-start !important;
    }

    .vac-message-right {
        justify-content: flex-end !important;
    }

    /* Avatar Small */
    .vac-avatar-small {
        width: 36px !important;
        height: 36px !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 18px !important;
        flex-shrink: 0 !important;
    }

    .vac-avatar-visitor {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        color: #fff !important;
        font-weight: 600 !important;
    }

    .vac-avatar-ai {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
    }

    .vac-avatar-staff {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
    }

    .vac-avatar-system {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%) !important;
    }

    /* Message Bubble */
    .vac-message-bubble {
        max-width: 70% !important;
        padding: 12px 16px !important;
        border-radius: 16px !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
    }

    .vac-bubble-visitor {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        color: #fff !important;
        border-bottom-right-radius: 4px !important;
    }

    .vac-bubble-ai {
        background: #fff !important;
        color: #2c3338 !important;
        border: 1px solid #e3f2fd !important;
        border-bottom-left-radius: 4px !important;
    }

    .vac-bubble-staff {
        background: #e8f5e9 !important;
        color: #2c3338 !important;
        border-bottom-left-radius: 4px !important;
    }

    .vac-bubble-system {
        background: #fff3cd !important;
        color: #856404 !important;
        border-bottom-left-radius: 4px !important;
    }

    .vac-message-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        margin-bottom: 6px !important;
        gap: 12px !important;
    }

    .vac-sender-name {
        font-weight: 600 !important;
        font-size: 13px !important;
    }

    .vac-bubble-visitor .vac-sender-name {
        color: rgba(255, 255, 255, 0.95) !important;
    }

    .vac-message-time {
        font-size: 11px !important;
        opacity: 0.7 !important;
        white-space: nowrap !important;
    }

    .vac-message-text {
        font-size: 14px !important;
        line-height: 1.5 !important;
        word-wrap: break-word !important;
    }

    /* Chat Footer */
    .vac-chat-footer {
        padding: 20px 24px;
        background: #fff;
        border-top: 1px solid #e9ecef;
        border-radius: 0 0 16px 16px;
    }

    .vac-input-wrapper textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #dcdcde;
        border-radius: 12px;
        font-size: 14px;
        resize: none;
        transition: border-color 0.2s;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    .vac-input-wrapper textarea:focus {
        outline: none;
        border-color: #667eea;
    }

    .vac-input-actions {
        display: flex;
        gap: 10px;
        margin-top: 12px;
        justify-content: flex-end;
    }

    .vac-btn-primary,
    .vac-btn-secondary {
        padding: 10px 20px;
        border-radius: 10px;
        border: none;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }

    .vac-btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
    }

    .vac-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .vac-btn-secondary {
        background: #f0f0f1;
        color: #2c3338;
    }

    .vac-btn-secondary:hover {
        background: #dcdcde;
    }

    .vac-btn-primary .dashicons,
    .vac-btn-secondary .dashicons {
        font-size: 18px;
        width: 18px;
        height: 18px;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .vac-detail-grid {
            grid-template-columns: 1fr;
        }

        .vac-sidebar {
            flex-direction: row;
        }
    }

    @media (max-width: 782px) {
        .vac-detail-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .vac-chat-window {
            height: 600px;
        }

        .vac-message-bubble {
            max-width: 90%;
        }

        .vac-sidebar {
            flex-direction: column;
        }
    }
</style>