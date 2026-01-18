(function ($) {
    'use strict';
    var VAC = {
        conversationId: null,
        visitorId: null,
        lastMessageId: 0,
        isOpen: false,
        polling: null,

        init: function () {
            this.visitorId = this.getVisitorId();
            this.bindEvents();
            this.loadState();
        },

        getVisitorId: function () {
            var id = localStorage.getItem('vac_visitor_id');
            if (!id) {
                // Generate format: v_timestamp_randomstring (9 lowercase alphanumeric chars)
                var randomStr = Math.random().toString(36).substring(2, 11).toLowerCase();
                // Ensure exactly 9 characters by padding if needed
                while (randomStr.length < 9) {
                    randomStr += Math.random().toString(36).substring(2, 3).toLowerCase();
                }
                id = 'v_' + Date.now() + '_' + randomStr.substring(0, 9);
                localStorage.setItem('vac_visitor_id', id);
                console.log('Generated new visitor_id:', id);
            }
            return id;
        },

        bindEvents: function () {
            var self = this;
            $('#vac-chat-button').on('click', function () { self.toggleChat(); });
            $('.vac-header-close').on('click', function () { self.closeChat(); });
            $('#vac-visitor-form').on('submit', function (e) { e.preventDefault(); self.submitVisitorInfo(); });
            $('#vac-message-form').on('submit', function (e) { e.preventDefault(); self.sendMessage(); });
            $('#vac-message-input').on('input', function () {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
            $('#vac-message-input').on('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); self.sendMessage(); }
            });
            $('.vac-star').on('click', function () { self.submitRating($(this).data('rating')); });
        },

        toggleChat: function () {
            var $widget = $('#vac-chat-widget');
            $widget.toggleClass('vac-open');
            this.isOpen = $widget.hasClass('vac-open');
            if (this.isOpen) this.onChatOpen();
            else this.onChatClose();
        },

        closeChat: function () {
            $('#vac-chat-widget').removeClass('vac-open');
            this.isOpen = false;
            this.onChatClose();
        },

        onChatOpen: function () {
            var visitorInfo = localStorage.getItem('vac_visitor_info');
            if (!visitorInfo) {
                $('#vac-prechat-form').show();
                $('#vac-messages').hide();
                $('#vac-input-area').hide();
            } else {
                this.showChat();
            }
            $('#vac-unread-badge').hide().text('0');
        },

        onChatClose: function () {
            if (this.polling) { clearInterval(this.polling); this.polling = null; }
        },

        submitVisitorInfo: function () {
            var $form = $('#vac-visitor-form');
            var data = {
                name: $form.find('[name="name"]').val(),
                email: $form.find('[name="email"]').val(),
                phone: $form.find('[name="phone"]').val()
            };
            localStorage.setItem('vac_visitor_info', JSON.stringify(data));
            $.post(vacChat.restUrl + 'chat/visitor', { visitor_id: this.visitorId, name: data.name, email: data.email, phone: data.phone });
            this.showChat();
        },

        showChat: function () {
            $('#vac-prechat-form').hide();
            $('#vac-messages').show();
            $('#vac-input-area').show();
            if ($('#vac-messages').children().length === 0) this.addMessage('ai', vacChat.settings.greeting);
            this.startPolling();
            $('#vac-message-input').focus();
        },

        sendMessage: function () {
            var self = this;
            var $input = $('#vac-message-input');
            var message = $input.val().trim();
            if (!message) return;

            // Debug: Log visitor ID
            console.log('Sending message with visitor_id:', this.visitorId);

            $input.val('').css('height', 'auto');
            this.addMessage('visitor', message);
            $('#vac-typing').show();
            this.scrollToBottom();

            var visitorInfo = JSON.parse(localStorage.getItem('vac_visitor_info') || '{}');

            $.ajax({
                url: vacChat.restUrl + 'chat/send',
                method: 'POST',
                data: {
                    visitor_id: this.visitorId,
                    message: message,
                    name: visitorInfo.name || '',
                    email: visitorInfo.email || '',
                    phone: visitorInfo.phone || '',
                    page_url: window.location.href
                },
                success: function (response) {
                    $('#vac-typing').hide();
                    console.log('Chat response:', response);
                    if (response.success) {
                        self.conversationId = response.conversation_id;
                        localStorage.setItem('vac_conversation_id', self.conversationId);
                        if (response.ai_response) {
                            self.addMessage('ai', response.ai_response.message);
                            self.lastMessageId = response.ai_response.id;
                        }
                    } else {
                        self.addMessage('system', response.message || 'Có lỗi xảy ra.');
                    }
                },
                error: function (xhr) {
                    $('#vac-typing').hide();
                    console.error('Chat error:', xhr.status, xhr.responseText);
                    var errorMsg = 'Không thể gửi tin nhắn.';
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        if (errorData.message) {
                            errorMsg = errorData.message;
                        }
                    } catch (e) { }
                    self.addMessage('system', errorMsg);
                }
            });
        },

        addMessage: function (type, content) {
            var time = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            var html = '<div class="vac-message vac-' + type + '"><div class="vac-message-bubble">' + this.escapeHtml(content).replace(/\n/g, '<br>') + '</div><div class="vac-message-time">' + time + '</div></div>';
            $('#vac-messages').append(html);
            this.scrollToBottom();
        },

        startPolling: function () {
            var self = this;
            if (this.polling) return;
            this.polling = setInterval(function () { self.fetchMessages(); }, 3000);
        },

        fetchMessages: function () {
            var self = this;
            if (!this.conversationId) { this.conversationId = localStorage.getItem('vac_conversation_id'); if (!this.conversationId) return; }
            $.get(vacChat.restUrl + 'chat/messages', { conversation_id: this.conversationId, after_id: this.lastMessageId }, function (response) {
                if (response.success && response.messages.length > 0) {
                    response.messages.forEach(function (msg) {
                        if (msg.sender_type !== 'visitor') self.addMessage(msg.sender_type, msg.message);
                        self.lastMessageId = Math.max(self.lastMessageId, msg.id);
                    });
                    if (!self.isOpen) {
                        var count = parseInt($('#vac-unread-badge').text()) || 0;
                        $('#vac-unread-badge').text(count + response.messages.length).show();
                    }
                }
            });
        },

        submitRating: function (rating) {
            $('.vac-star').each(function () { $(this).toggleClass('active', $(this).data('rating') <= rating); });
            $.post(vacChat.restUrl + 'chat/rate', { conversation_id: this.conversationId, rating: rating }, function () {
                setTimeout(function () { $('#vac-rating').html('<p>Cảm ơn!</p>'); }, 500);
            });
        },

        scrollToBottom: function () { var $m = $('#vac-messages'); $m.scrollTop($m[0].scrollHeight); },
        escapeHtml: function (text) { var d = document.createElement('div'); d.textContent = text; return d.innerHTML; },
        loadState: function () { this.conversationId = localStorage.getItem('vac_conversation_id'); }
    };
    $(document).ready(function () { VAC.init(); });
})(jQuery);
