<?php
/**
 * Uninstall Script
 * Chạy khi plugin bị XÓA (không phải deactivate)
 * Cleanup toàn bộ data để tránh rác trong database
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. XÓA TẤT CẢ OPTIONS
$options_to_delete = array(
    // API & Settings
    'vac_gemini_api_key',
    'vac_ai_auto_reply',
    'vac_greeting_message',
    'vac_offline_message',
    'vac_widget_position',
    'vac_primary_color',
    'vac_widget_title',
    'vac_show_on_mobile',
    'vac_business_hours',

    // Data settings
    'vac_data_settings',
    'vac_auto_optimize',
    'vac_last_sync',

    // Other settings
    'vac_plugin_version',
    'vac_db_version'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// 2. XÓA TẤT CẢ TRANSIENTS
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_vac_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_vac_%'");

// Specific transients
delete_transient('vac_ai_knowledge_data');
delete_transient('vac_rate_limit_ip_*');
delete_transient('vac_rate_limit_*');
delete_transient('vac_banned_*');
delete_transient('vac_abuse_*');

// 3. XÓA DATABASE TABLES
$table_visitors = $wpdb->prefix . 'vac_visitors';
$table_conversations = $wpdb->prefix . 'vac_conversations';
$table_messages = $wpdb->prefix . 'vac_messages';

$wpdb->query("DROP TABLE IF EXISTS {$table_messages}");
$wpdb->query("DROP TABLE IF EXISTS {$table_conversations}");
$wpdb->query("DROP TABLE IF EXISTS {$table_visitors}");

// 4. CLEAR CRON JOBS (nếu có)
wp_clear_scheduled_hook('vac_cleanup_old_data');
wp_clear_scheduled_hook('vac_refresh_knowledge_data');

// 5. LOG CLEANUP
error_log('VAC Plugin: All data cleaned up successfully on uninstall');

// Optional: Xóa uploaded files (nếu plugin có upload files)
// $upload_dir = wp_upload_dir();
// $vac_upload_dir = $upload_dir['basedir'] . '/vac-chat/';
// if (is_dir($vac_upload_dir)) {
//     // Recursive delete directory
//     array_map('unlink', glob("$vac_upload_dir/*.*"));
//     rmdir($vac_upload_dir);
// }
