<?php
/**
 * Gemini AI Integration
 * Tích hợp với Data Collector để sử dụng dữ liệu động từ website
 */

if (!defined('ABSPATH')) {
    exit;
}

class VAC_Gemini
{

    private $api_key;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    private $model = 'gemini-2.0-flash';
    private $data_collector;

    public function __construct()
    {
        $this->api_key = get_option('vac_gemini_api_key', '');

        // Khởi tạo Data Collector
        if (class_exists('VAC_Data_Collector')) {
            $this->data_collector = VAC_Data_Collector::get_instance();
        }
    }

    /**
     * Kiểm tra API đã cấu hình chưa
     */
    public function is_configured()
    {
        return !empty($this->api_key);
    }

    /**
     * Tạo response từ AI
     */
    public function generate_response($message, $context = array())
    {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'Gemini API key not configured'
            );
        }

        // Build system prompt với dữ liệu động
        $system_prompt = $this->build_system_prompt($context);
        $conversation_history = $this->build_conversation_history($context);

        $contents = array();

        // Thêm lịch sử hội thoại
        if (!empty($conversation_history)) {
            foreach ($conversation_history as $msg) {
                $contents[] = array(
                    'role' => $msg['role'],
                    'parts' => array(array('text' => $msg['content']))
                );
            }
        }

        // Thêm tin nhắn hiện tại
        $contents[] = array(
            'role' => 'user',
            'parts' => array(array('text' => $message))
        );

        $request_body = array(
            'contents' => $contents,
            'systemInstruction' => array(
                'parts' => array(array('text' => $system_prompt))
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024
            ),
            'safetySettings' => array(
                array(
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                )
            )
        );

        $response = $this->make_request($request_body);

        if (!$response['success']) {
            return $response;
        }

        return $this->parse_response($response['data']);
    }

    /**
     * Build system prompt với dữ liệu động từ website
     */
    private function build_system_prompt($context = array())
    {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');

        // Lấy knowledge base tĩnh
        $manual_knowledge = get_option('vac_ai_knowledge_base', '');

        // Lấy dữ liệu động từ Data Collector
        $dynamic_knowledge = '';
        if ($this->data_collector) {
            $dynamic_knowledge = $this->data_collector->get_ai_knowledge_data();
        }

        // Kết hợp cả hai
        $full_knowledge = '';
        if (!empty($manual_knowledge)) {
            $full_knowledge .= "=== THÔNG TIN QUAN TRỌNG ===\n" . $manual_knowledge . "\n\n";
        }
        if (!empty($dynamic_knowledge)) {
            $full_knowledge .= $dynamic_knowledge;
        }

        $prompt = "Bạn là trợ lý AI hỗ trợ khách hàng cho website \"{$site_name}\".
Mô tả website: {$site_description}

HƯỚNG DẪN QUAN TRỌNG:
1. Trả lời bằng tiếng Việt, thân thiện và chuyên nghiệp
2. Giữ câu trả lời ngắn gọn, dễ hiểu (tối đa 2-3 đoạn văn)
3. Nếu không biết câu trả lời chính xác, hãy thông báo sẽ chuyển cho nhân viên hỗ trợ
4. Không đưa ra thông tin không chính xác hoặc suy đoán
5. Luôn lịch sự và kiên nhẫn với khách hàng
6. Nếu khách hàng yêu cầu nói chuyện với nhân viên, hãy thông báo sẽ chuyển ngay
7. Khi trả lời về sản phẩm/dịch vụ, hãy đưa ra link chi tiết nếu có
8. Khi được hỏi về giá, hãy cung cấp giá chính xác từ dữ liệu
9. Khi được hỏi về tình trạng hàng, hãy trả lời dựa trên thông tin có sẵn
10. Nếu khách muốn biết chi tiết hơn, hướng dẫn họ truy cập link sản phẩm/bài viết

DỮ LIỆU VỀ SẢN PHẨM/DỊCH VỤ/NỘI DUNG WEBSITE:
{$full_knowledge}

";

        // Thêm context của visitor
        if (!empty($context['visitor'])) {
            $visitor = $context['visitor'];
            $prompt .= "\nTHÔNG TIN KHÁCH HÀNG ĐANG CHAT:\n";
            if (!empty($visitor->name)) {
                $prompt .= "- Tên: {$visitor->name}\n";
            }
            if (!empty($visitor->email)) {
                $prompt .= "- Email: {$visitor->email}\n";
            }
            if (!empty($visitor->phone)) {
                $prompt .= "- Số điện thoại: {$visitor->phone}\n";
            }
        }

        return $prompt;
    }

    /**
     * Build conversation history
     */
    private function build_conversation_history($context = array())
    {
        $history = array();

        if (empty($context['messages'])) {
            return $history;
        }

        // Lấy 10 tin nhắn gần nhất
        $messages = array_slice($context['messages'], -10);

        foreach ($messages as $msg) {
            $role = ($msg->sender_type === 'visitor') ? 'user' : 'model';
            $history[] = array(
                'role' => $role,
                'content' => $msg->message
            );
        }

        return $history;
    }

    /**
     * Make API request
     */
    private function make_request($body)
    {
        $url = $this->api_url . '?key=' . $this->api_key;

        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body)
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $error_message = isset($data['error']['message'])
                ? $data['error']['message']
                : 'Unknown API error';

            return array(
                'success' => false,
                'error' => $error_message,
                'code' => $response_code
            );
        }

        return array(
            'success' => true,
            'data' => $data
        );
    }

    /**
     * Parse Gemini response
     */
    private function parse_response($data)
    {
        if (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            // Kiểm tra blocked content
            if (
                !empty($data['candidates'][0]['finishReason']) &&
                $data['candidates'][0]['finishReason'] === 'SAFETY'
            ) {
                return array(
                    'success' => true,
                    'message' => 'Xin lỗi, tôi không thể trả lời câu hỏi này. Vui lòng liên hệ nhân viên hỗ trợ để được giúp đỡ.',
                    'needs_human' => true
                );
            }

            return array(
                'success' => false,
                'error' => 'Empty response from AI'
            );
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];

        // Kiểm tra nếu cần chuyển cho nhân viên
        $needs_human = $this->check_needs_human($text);

        return array(
            'success' => true,
            'message' => $text,
            'needs_human' => $needs_human,
            'usage' => isset($data['usageMetadata']) ? $data['usageMetadata'] : null
        );
    }

    /**
     * Kiểm tra cần chuyển cho nhân viên không
     */
    private function check_needs_human($text)
    {
        $keywords = array(
            'chuyển cho nhân viên',
            'liên hệ nhân viên',
            'không thể trả lời',
            'không biết',
            'cần hỗ trợ thêm',
            'nhân viên sẽ',
            'chờ nhân viên',
            'không có thông tin',
            'không tìm thấy'
        );

        $text_lower = mb_strtolower($text);
        foreach ($keywords as $keyword) {
            if (mb_strpos($text_lower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test API connection
     */
    public function test_connection()
    {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'API key not configured'
            );
        }

        // Simple test request without data_collector dependency
        $url = $this->api_url . '?key=' . $this->api_key;

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => 'Hello, this is a test message. Please respond with "OK".')
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'maxOutputTokens' => 100
            )
        );

        $args = array(
            'method' => 'POST',
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body)
        );

        try {
            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => 'Connection failed: ' . $response->get_error_message()
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                $data = json_decode($response_body, true);
                $error_message = isset($data['error']['message'])
                    ? $data['error']['message']
                    : 'API returned error code: ' . $response_code;

                return array(
                    'success' => false,
                    'error' => $error_message
                );
            }

            // Success!
            return array(
                'success' => true,
                'message' => 'Connection successful!'
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Phân tích sentiment
     */
    public function analyze_sentiment($message)
    {
        $prompt = "Phân tích cảm xúc của tin nhắn sau và trả về một trong các giá trị: positive, negative, neutral.
Chỉ trả về một từ duy nhất.

Tin nhắn: \"{$message}\"";

        $response = $this->generate_response($prompt, array());

        if (!$response['success']) {
            return 'neutral';
        }

        $sentiment = strtolower(trim($response['message']));
        if (in_array($sentiment, array('positive', 'negative', 'neutral'))) {
            return $sentiment;
        }

        return 'neutral';
    }

    /**
     * Tạo gợi ý trả lời cho staff
     */
    public function generate_suggestions($conversation_context)
    {
        $prompt = "Dựa trên cuộc hội thoại sau, đề xuất 3 câu trả lời ngắn gọn mà nhân viên có thể sử dụng.
Trả về dạng danh sách, mỗi câu một dòng.

Cuộc hội thoại:
" . $conversation_context;

        $response = $this->generate_response($prompt, array());

        if (!$response['success']) {
            return array();
        }

        $suggestions = explode("\n", trim($response['message']));
        $suggestions = array_filter($suggestions, function ($s) {
            return strlen(trim($s)) > 0;
        });

        return array_slice($suggestions, 0, 3);
    }

    /**
     * Tìm kiếm trong knowledge base
     */
    public function search_knowledge($query)
    {
        if (!$this->data_collector) {
            return array();
        }

        $knowledge = $this->data_collector->get_ai_knowledge_data();

        $prompt = "Dựa trên dữ liệu sau, tìm và trả về thông tin liên quan đến: \"{$query}\"
Trả về kết quả ngắn gọn, mỗi item một dòng.

Dữ liệu:
{$knowledge}";

        $response = $this->generate_response($prompt, array());

        if (!$response['success']) {
            return array();
        }

        return array(
            'query' => $query,
            'results' => $response['message']
        );
    }

    /**
     * Lấy thông tin sản phẩm cụ thể
     */
    public function get_product_info($product_name)
    {
        if (!$this->data_collector) {
            return null;
        }

        $knowledge = $this->data_collector->get_ai_knowledge_data();

        $prompt = "Từ dữ liệu sản phẩm sau, tìm và trả về đầy đủ thông tin về sản phẩm \"{$product_name}\" bao gồm: tên, giá, mô tả, tình trạng hàng, link.
Nếu không tìm thấy, trả lời 'Không tìm thấy sản phẩm'.

Dữ liệu:
{$knowledge}";

        $response = $this->generate_response($prompt, array());

        if (!$response['success']) {
            return null;
        }

        return $response['message'];
    }
}
