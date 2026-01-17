(function ($) {
    'use strict';

    var VACAdmin = {
        init: function () {
            this.bindEvents();
            this.initColorPicker();
        },

        bindEvents: function () {
            var self = this;

            // Staff reply form
            $('#vac-staff-reply-form').on('submit', function (e) {
                e.preventDefault();
                self.sendStaffReply();
            });

            // Change status
            $('#vac-change-status').on('change', function () {
                var status = $(this).val();
                var id = $(this).data('id');
                if (status) self.updateStatus(id, status);
            });

            // AI suggestion
            $('#vac-ai-suggest').on('click', function () {
                self.getAISuggestion();
            });

            // Test AI
            $('#vac-test-ai-btn').on('click', function () {
                self.testAIConnection();
            });

            // Polling for new messages
            if ($('#vac-messages-container').length) {
                setInterval(function () { self.pollMessages(); }, 5000);
            }
        },

        initColorPicker: function () {
            if ($.fn.wpColorPicker) {
                $('.vac-color-picker').wpColorPicker();
            }
        },

        sendStaffReply: function () {
            var $form = $('#vac-staff-reply-form');
            var id = $form.find('[name="conversation_id"]').val();
            var message = $form.find('[name="message"]').val().trim();

            if (!message) return;

            $form.find('button[type="submit"]').prop('disabled', true).text('Đang gửi...');

            $.ajax({
                url: vacAdmin.restUrl + 'admin/conversations/' + id + '/reply',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', vacAdmin.nonce);
                },
                data: { message: message },
                success: function (response) {
                    if (response.success) {
                        var html = '<div class="vac-message-row vac-message-left">' +
                            '<div class="vac-avatar-small vac-avatar-staff">💼</div>' +
                            '<div class="vac-message-bubble vac-bubble-staff">' +
                            '<div class="vac-message-header">' +
                            '<span class="vac-sender-name">Bạn</span>' +
                            '<span class="vac-message-time">' + new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) + '</span>' +
                            '</div>' +
                            '<div class="vac-message-text">' + VACAdmin.escapeHtml(message) + '</div>' +
                            '</div>' +
                            '</div>';
                        $('#vac-messages-container').append(html);
                        $form.find('[name="message"]').val('');
                        VACAdmin.scrollMessages();
                    }
                },
                complete: function () {
                    $form.find('button[type="submit"]').prop('disabled', false).text('Gửi');
                }
            });
        },

        updateStatus: function (id, status) {
            $.ajax({
                url: vacAdmin.restUrl + 'admin/conversations/' + id + '/status',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', vacAdmin.nonce);
                },
                data: { status: status },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },

        getAISuggestion: function () {
            var $btn = $('#vac-ai-suggest');
            $btn.prop('disabled', true).text('Đang tạo...');

            // Get conversation context
            var context = '';
            $('#vac-messages-container .vac-message-content').each(function () {
                context += $(this).text() + '\n';
            });

            // For now, just show a placeholder
            setTimeout(function () {
                var suggestions = ['Cảm ơn bạn đã liên hệ. Để hỗ trợ tốt hơn, bạn có thể cho biết thêm chi tiết?',
                    'Chúng tôi sẽ kiểm tra và phản hồi trong thời gian sớm nhất.',
                    'Bạn có thể liên hệ hotline để được hỗ trợ nhanh hơn.'];
                var suggestion = suggestions[Math.floor(Math.random() * suggestions.length)];
                $('#vac-staff-reply-form [name="message"]').val(suggestion);
                $btn.prop('disabled', false).text('💡 Gợi ý AI');
            }, 1000);
        },

        testAIConnection: function () {
            var $btn = $('#vac-test-ai-btn');
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
                success: function (response) {
                    if (response.success) {
                        $result.html('<span class="vac-success">✓ Kết nối thành công!</span>');
                    } else {
                        $result.html('<span class="vac-error">✗ ' + (response.data.message || 'Lỗi') + '</span>');
                    }
                },
                error: function () {
                    $result.html('<span class="vac-error">✗ Lỗi kết nối</span>');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Kiểm tra kết nối');
                }
            });
        },

        pollMessages: function () {
            var $container = $('#vac-messages-container');
            var conversationId = $container.data('conversation');
            // FIX: Use .vac-message-row to find last ID
            var lastId = $container.find('.vac-message-row:last').data('id') || 0;

            $.ajax({
                url: vacAdmin.restUrl + 'admin/conversations/' + conversationId + '/messages',
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', vacAdmin.nonce);
                },
                data: { after_id: lastId },
                success: function (response) {
                    if (response.success && response.data.messages.length > 0) {
                        var hasNewMessages = false;
                        response.data.messages.forEach(function (msg) {
                            // Check for duplicate ID
                            if ($container.find('.vac-message-row[data-id="' + msg.id + '"]').length === 0) {
                                hasNewMessages = true;
                                var html = '';
                                var time = new Date(msg.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

                                if (msg.sender_type === 'visitor') {
                                    var shortName = (msg.visitor_name || 'K').charAt(0).toUpperCase();
                                    var visitorName = msg.visitor_name || 'Khách';

                                    html = '<div class="vac-message-row vac-message-right" data-id="' + msg.id + '">' +
                                        '<div class="vac-message-bubble vac-bubble-visitor">' +
                                        '<div class="vac-message-header">' +
                                        '<span class="vac-sender-name">' + VACAdmin.escapeHtml(visitorName) + '</span>' +
                                        '<span class="vac-message-time">' + time + '</span>' +
                                        '</div>' +
                                        '<div class="vac-message-text">' + VACAdmin.escapeHtml(msg.message) + '</div>' +
                                        '</div>' +
                                        '<div class="vac-avatar-small vac-avatar-visitor">' + shortName + '</div>' +
                                        '</div>';
                                } else {
                                    // Staff / AI / System
                                    var type = msg.sender_type; // staff, ai, system
                                    var avatarChar = type === 'ai' ? '🤖' : (type === 'system' ? '⚙️' : '👤');
                                    var name = type === 'ai' ? 'AI Assistant' : (type === 'system' ? 'Hệ thống' : (msg.staff_name || 'Nhân viên'));
                                    var bubbleClass = 'vac-bubble-' + type; // vac-bubble-staff, etc.

                                    html = '<div class="vac-message-row vac-message-left" data-id="' + msg.id + '">' +
                                        '<div class="vac-avatar-small vac-avatar-' + type + '">' + avatarChar + '</div>' +
                                        '<div class="vac-message-bubble ' + bubbleClass + '">' +
                                        '<div class="vac-message-header">' +
                                        '<span class="vac-sender-name">' + VACAdmin.escapeHtml(name) + '</span>' +
                                        '<span class="vac-message-time">' + time + '</span>' +
                                        '</div>' +
                                        '<div class="vac-message-text">' + VACAdmin.escapeHtml(msg.message) + '</div>' +
                                        '</div>' +
                                        '</div>';
                                }
                                $container.append(html);
                            }
                        });

                        if (hasNewMessages) {
                            VACAdmin.scrollMessages();
                        }
                    }
                }
            });
        },

        scrollMessages: function () {
            var $container = $('#vac-messages-container');
            $container.scrollTop($container[0].scrollHeight);
        },

        escapeHtml: function (text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function () {
        VACAdmin.init();
    });

})(jQuery);
