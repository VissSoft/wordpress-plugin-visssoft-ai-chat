<?php
/**
 * Admin Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$database = new VAC_Database();
$stats = $database->get_stats();
$recent = $database->get_conversations(['per_page' => 10]);
?>

<div class="wrap vac-dashboard">
    <h1>
        <span class="dashicons dashicons-format-chat"></span>
        AI Chat Dashboard
    </h1>

    <!-- Stats Cards -->
    <div class="vac-stats-grid">
        <div class="vac-stat-card vac-stat-total">
            <div class="vac-stat-icon">💬</div>
            <div class="vac-stat-content">
                <div class="vac-stat-value"><?php echo intval($stats['total_conversations']); ?></div>
                <div class="vac-stat-label">Tổng hội thoại</div>
            </div>
        </div>

        <div class="vac-stat-card vac-stat-pending">
            <div class="vac-stat-icon">⏳</div>
            <div class="vac-stat-content">
                <div class="vac-stat-value"><?php echo intval($stats['pending_conversations']); ?></div>
                <div class="vac-stat-label">Chờ xử lý</div>
            </div>
        </div>

        <div class="vac-stat-card vac-stat-resolved">
            <div class="vac-stat-icon">✅</div>
            <div class="vac-stat-content">
                <div class="vac-stat-value"><?php echo intval($stats['resolved_today']); ?></div>
                <div class="vac-stat-label">Đã giải quyết hôm nay</div>
            </div>
        </div>

        <div class="vac-stat-card vac-stat-response">
            <div class="vac-stat-icon">⚡</div>
            <div class="vac-stat-content">
                <?php
                $response_time = intval($stats['avg_response_time']);
                $response_display = $response_time > 0 ? round($response_time / 60) . ' phút' : 'N/A';
                ?>
                <div class="vac-stat-value"><?php echo esc_html($response_display); ?></div>
                <div class="vac-stat-label">Thời gian phản hồi TB</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="vac-quick-actions">
        <h2>Thao tác nhanh</h2>
        <div class="vac-action-buttons">
            <a href="<?php echo admin_url('admin.php?page=vac-conversations&status=pending'); ?>"
                class="button button-primary">
                <span class="dashicons dashicons-clock"></span>
                Xem chờ xử lý (<?php echo intval($stats['pending_conversations']); ?>)
            </a>
            <a href="<?php echo admin_url('admin.php?page=vac-conversations'); ?>" class="button">
                <span class="dashicons dashicons-list-view"></span>
                Tất cả hội thoại
            </a>
            <a href="<?php echo admin_url('admin.php?page=vac-settings'); ?>" class="button">
                <span class="dashicons dashicons-admin-settings"></span>
                Cài đặt
            </a>
            <a href="<?php echo admin_url('admin.php?page=vac-settings&tab=content-sync'); ?>" class="button">
                <span class="dashicons dashicons-database"></span>
                Đồng bộ nội dung
            </a>
        </div>
    </div>

    <!-- Recent Conversations -->
    <div class="vac-recent-conversations">
        <h2>Hội thoại gần đây</h2>

        <?php if (empty($recent['conversations'])): ?>
            <p class="vac-no-data">Chưa có hội thoại nào.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Tin nhắn gần nhất</th>
                        <th>Trạng thái</th>
                        <th>Xử lý bởi</th>
                        <th>Thời gian</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent['conversations'] as $conv): ?>
                        <tr class="<?php echo $conv->unread_count > 0 ? 'vac-unread' : ''; ?>">
                            <td>
                                <strong><?php echo esc_html($conv->visitor_name ?: 'Khách'); ?></strong>
                                <?php if ($conv->unread_count > 0): ?>
                                    <span class="vac-badge"><?php echo intval($conv->unread_count); ?></span>
                                <?php endif; ?>
                                <br>
                                <small><?php echo esc_html($conv->visitor_email); ?></small>
                            </td>
                            <td>
                                <div class="vac-message-preview">
                                    <?php echo esc_html(mb_substr($conv->last_message, 0, 50)); ?>
                                    <?php if (strlen($conv->last_message) > 50)
                                        echo '...'; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $status_labels = [
                                    'open' => ['Mở', 'vac-status-open'],
                                    'pending' => ['Chờ xử lý', 'vac-status-pending'],
                                    'resolved' => ['Đã giải quyết', 'vac-status-resolved'],
                                    'closed' => ['Đã đóng', 'vac-status-closed'],
                                ];
                                $status = $status_labels[$conv->status] ?? ['Unknown', ''];
                                ?>
                                <span class="vac-status-badge <?php echo esc_attr($status[1]); ?>">
                                    <?php echo esc_html($status[0]); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($conv->handled_by === 'ai'): ?>
                                    <span title="AI đang xử lý">🤖</span>
                                <?php else: ?>
                                    <span title="Nhân viên đang xử lý">👤</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html(human_time_diff(strtotime($conv->updated_at)) . ' trước'); ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=vac-conversations&conversation_id=' . $conv->id); ?>"
                                    class="button button-small">
                                    Xem
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- AI Status -->
    <div class="vac-ai-status">
        <h2>Trạng thái AI</h2>
        <?php
        $gemini = new VAC_Gemini();
        $is_configured = $gemini->is_configured();
        $auto_reply = get_option('vac_ai_auto_reply', true);
        $last_sync = get_option('vac_last_sync', 0);
        $has_data = !empty($last_sync);
        ?>
        <div class="vac-status-items">
            <div class="vac-status-item <?php echo $is_configured ? 'active' : 'inactive'; ?>">
                <span class="status-icon"><?php echo $is_configured ? '✓' : '✗'; ?></span>
                <span>Gemini API: <?php echo $is_configured ? 'Đã cấu hình' : 'Chưa cấu hình'; ?></span>
            </div>
            <div class="vac-status-item <?php echo $auto_reply ? 'active' : 'inactive'; ?>">
                <span class="status-icon"><?php echo $auto_reply ? '✓' : '✗'; ?></span>
                <span>Tự động trả lời: <?php echo $auto_reply ? 'Bật' : 'Tắt'; ?></span>
            </div>
            <div class="vac-status-item <?php echo $has_data ? 'active' : 'inactive'; ?>">
                <span class="status-icon"><?php echo $has_data ? '✓' : '✗'; ?></span>
                <span>Dữ liệu AI: <?php echo $has_data ? 'Đã đồng bộ' : 'Chưa đồng bộ'; ?></span>
            </div>
        </div>
    </div>
</div>

<style>
    .vac-dashboard h1 .dashicons {
        font-size: 30px;
        vertical-align: middle;
        margin-right: 10px;
    }

    .vac-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .vac-stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .vac-stat-icon {
        font-size: 32px;
    }

    .vac-stat-value {
        font-size: 28px;
        font-weight: bold;
        color: #1d2327;
    }

    .vac-stat-label {
        color: #646970;
        font-size: 13px;
    }

    .vac-quick-actions {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .vac-quick-actions h2 {
        margin-top: 0;
    }

    .vac-action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .vac-action-buttons .button .dashicons {
        vertical-align: text-bottom;
        margin-right: 5px;
    }

    .vac-recent-conversations {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .vac-recent-conversations h2 {
        margin-top: 0;
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
        margin-left: 5px;
    }

    .vac-status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
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

    .vac-message-preview {
        color: #646970;
        font-size: 13px;
    }

    .vac-ai-status {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .vac-ai-status h2 {
        margin-top: 0;
    }

    .vac-status-items {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .vac-status-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 15px;
        border-radius: 6px;
        background: #f8f9fa;
    }

    .vac-status-item.active {
        background: #d4edda;
        color: #155724;
    }

    .vac-status-item.inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .vac-status-item .status-icon {
        font-weight: bold;
    }

    .vac-no-data {
        color: #646970;
        font-style: italic;
    }
</style>