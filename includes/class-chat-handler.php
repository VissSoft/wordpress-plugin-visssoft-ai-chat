<?php
/**
 * Chat Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class VAC_Chat_Handler {
    
    private $database;
    private $gemini;
    
    public function __construct() {
        $this->database = new VAC_Database();
        $this->gemini = new VAC_Gemini();
    }
    
    /**
     * Handle visitor message
     */
    public function handle_visitor_message($visitor_id, $message, $visitor_data = []) {
        // Get or create visitor
        $visitor = $this->database->get_or_create_visitor($visitor_id, $visitor_data);
        
        if (!$visitor) {
            return ['success' => false, 'error' => 'Could not create visitor'];
        }
        
        // Get or create conversation
        $conversation = $this->database->get_or_create_conversation($visitor->id);
        
        if (!$conversation) {
            return ['success' => false, 'error' => 'Could not create conversation'];
        }
        
        // Save visitor message
        $message_id = $this->database->add_message(
            $conversation->id,
            'visitor',
            sanitize_textarea_field($message)
        );
        
        // Check if AI auto-reply is enabled
        $ai_auto_reply = get_option('vac_ai_auto_reply', true);
        
        $response_message = null;
        
        if ($ai_auto_reply && $this->gemini->is_configured()) {
            // Get conversation history for context
            $messages = $this->database->get_messages($conversation->id);
            
            // Generate AI response
            $ai_response = $this->gemini->generate_response($message, [
                'visitor' => $visitor,
                'messages' => $messages,
            ]);
            
            if ($ai_response['success']) {
                // Save AI response
                $ai_message_id = $this->database->add_message(
                    $conversation->id,
                    'ai',
                    $ai_response['message']
                );
                
                $response_message = [
                    'id' => $ai_message_id,
                    'sender_type' => 'ai',
                    'message' => $ai_response['message'],
                    'created_at' => current_time('mysql'),
                ];
                
                // Check if needs human intervention
                if (!empty($ai_response['needs_human'])) {
                    $this->database->update_conversation($conversation->id, [
                        'status' => 'pending',
                        'handled_by' => 'staff',
                    ]);
                    
                    // Notify staff
                    $this->notify_staff($conversation, $visitor, $message);
                }
            }
        } else {
            // No AI, notify staff
            $this->database->update_conversation($conversation->id, [
                'status' => 'pending',
                'handled_by' => 'staff',
            ]);
            
            $this->notify_staff($conversation, $visitor, $message);
        }
        
        return [
            'success' => true,
            'conversation_id' => $conversation->id,
            'message_id' => $message_id,
            'ai_response' => $response_message,
        ];
    }
    
    /**
     * Handle staff reply
     */
    public function handle_staff_reply($conversation_id, $message, $staff_id) {
        $conversation = $this->database->get_conversation($conversation_id);
        
        if (!$conversation) {
            return ['success' => false, 'error' => 'Conversation not found'];
        }
        
        // Save staff message
        $message_id = $this->database->add_message(
            $conversation_id,
            'staff',
            sanitize_textarea_field($message),
            $staff_id
        );
        
        // Update conversation
        $this->database->update_conversation($conversation_id, [
            'handled_by' => 'staff',
            'staff_id' => $staff_id,
        ]);
        
        // Mark visitor messages as read
        $this->database->mark_messages_read($conversation_id, 'visitor');
        
        return [
            'success' => true,
            'message' => [
                'id' => $message_id,
                'sender_type' => 'staff',
                'staff_id' => $staff_id,
                'message' => $message,
                'created_at' => current_time('mysql'),
            ],
        ];
    }
    
    /**
     * Get messages for polling
     */
    public function get_messages($conversation_id, $after_id = 0) {
        return $this->database->get_messages($conversation_id, $after_id);
    }
    
    /**
     * Update conversation status
     */
    public function update_status($conversation_id, $status) {
        $valid_statuses = ['open', 'pending', 'resolved', 'closed'];
        
        if (!in_array($status, $valid_statuses)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }
        
        $result = $this->database->update_conversation($conversation_id, ['status' => $status]);
        
        return [
            'success' => $result !== false,
        ];
    }
    
    /**
     * Rate conversation
     */
    public function rate_conversation($conversation_id, $rating, $comment = '') {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'Invalid rating'];
        }
        
        $result = $this->database->update_conversation($conversation_id, [
            'rating' => $rating,
            'rating_comment' => sanitize_textarea_field($comment),
        ]);
        
        return [
            'success' => $result !== false,
        ];
    }
    
    /**
     * Update visitor info
     */
    public function update_visitor($visitor_id, $data) {
        return $this->database->update_visitor($visitor_id, $data);
    }
    
    /**
     * Notify staff about new message
     */
    private function notify_staff($conversation, $visitor, $message) {
        $notification_email = get_option('vac_notification_email', get_option('admin_email'));
        
        if (empty($notification_email)) {
            return;
        }
        
        $subject = sprintf('[%s] Tin nhắn mới từ khách hàng', get_bloginfo('name'));
        
        $visitor_name = !empty($visitor->name) ? $visitor->name : 'Khách';
        $visitor_email = !empty($visitor->email) ? $visitor->email : 'Chưa có';
        
        $body = "Bạn có tin nhắn mới cần xử lý:\n\n";
        $body .= "Khách hàng: {$visitor_name}\n";
        $body .= "Email: {$visitor_email}\n";
        $body .= "Tin nhắn: {$message}\n\n";
        $body .= "Xem chi tiết: " . admin_url('admin.php?page=vac-conversations&conversation_id=' . $conversation->id);
        
        wp_mail($notification_email, $subject, $body);
    }
    
    /**
     * Check if within business hours
     */
    public function is_within_business_hours() {
        $hours = get_option('vac_business_hours', ['start' => '08:00', 'end' => '17:00']);
        
        if (empty($hours['start']) || empty($hours['end'])) {
            return true;
        }
        
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        $current_time = $now->format('H:i');
        
        return $current_time >= $hours['start'] && $current_time <= $hours['end'];
    }
    
    /**
     * Get conversation status for visitor
     */
    public function get_conversation_status($conversation_id) {
        $conversation = $this->database->get_conversation($conversation_id);
        
        if (!$conversation) {
            return null;
        }
        
        return [
            'status' => $conversation->status,
            'handled_by' => $conversation->handled_by,
            'is_online' => $this->is_within_business_hours(),
        ];
    }
}
