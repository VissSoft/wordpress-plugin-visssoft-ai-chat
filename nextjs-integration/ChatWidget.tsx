'use client';

import { useState, useEffect, useRef } from 'react';
import './ChatWidget.css';

interface Message {
    id: number;
    sender_type: 'visitor' | 'ai' | 'staff' | 'system';
    message: string;
    created_at: string;
}

interface ChatWidgetProps {
    apiUrl: string; // e.g., 'https://api.live-stream.io.vn/wp-json/visssoft-ai-chat/v1'
    visitorName?: string;
    visitorEmail?: string;
    visitorPhone?: string;
}

export default function ChatWidget({
    apiUrl,
    visitorName = '',
    visitorEmail = '',
    visitorPhone = ''
}: ChatWidgetProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>([]);
    const [inputMessage, setInputMessage] = useState('');
    const [conversationId, setConversationId] = useState<number | null>(null);
    const [visitorId, setVisitorId] = useState<string>('');
    const [lastMessageId, setLastMessageId] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);

    const messagesEndRef = useRef<HTMLDivElement>(null);
    const pollingInterval = useRef<NodeJS.Timeout | null>(null);

    // Generate visitor ID
    useEffect(() => {
        let storedVisitorId = localStorage.getItem('vac_visitor_id');

        if (!storedVisitorId) {
            const timestamp = Math.floor(Date.now() / 1000);
            const randomStr = Array.from({ length: 9 }, () =>
                'abcdefghijklmnopqrstuvwxyz0123456789'[Math.floor(Math.random() * 36)]
            ).join('');
            storedVisitorId = `v_${timestamp}_${randomStr}`;
            localStorage.setItem('vac_visitor_id', storedVisitorId);
        }

        setVisitorId(storedVisitorId);

        const storedConvId = localStorage.getItem('vac_conversation_id');
        if (storedConvId) {
            setConversationId(parseInt(storedConvId));
        }
    }, []);

    // Scroll to bottom
    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    // Polling for new messages
    useEffect(() => {
        if (!isOpen || !conversationId) return;

        const fetchMessages = async () => {
            try {
                const response = await fetch(
                    `${apiUrl}/chat/messages?conversation_id=${conversationId}&after_id=${lastMessageId}`
                );
                const data = await response.json();

                if (data.success && data.messages.length > 0) {
                    const newMessages = data.messages.filter(
                        (msg: Message) => msg.sender_type !== 'visitor'
                    );

                    if (newMessages.length > 0) {
                        setMessages(prev => [...prev, ...newMessages]);
                        setLastMessageId(Math.max(...data.messages.map((m: Message) => m.id)));
                    }
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
            }
        };

        pollingInterval.current = setInterval(fetchMessages, 3000);

        return () => {
            if (pollingInterval.current) {
                clearInterval(pollingInterval.current);
            }
        };
    }, [isOpen, conversationId, lastMessageId, apiUrl]);

    // Send message
    const sendMessage = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!inputMessage.trim() || isLoading) return;

        const userMessage = inputMessage.trim();
        setInputMessage('');
        setIsLoading(true);

        // Add user message to UI immediately
        const tempMessage: Message = {
            id: Date.now(),
            sender_type: 'visitor',
            message: userMessage,
            created_at: new Date().toISOString()
        };
        setMessages(prev => [...prev, tempMessage]);

        try {
            const response = await fetch(`${apiUrl}/chat/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    visitor_id: visitorId,
                    message: userMessage,
                    name: visitorName,
                    email: visitorEmail,
                    phone: visitorPhone,
                    page_url: window.location.href
                })
            });

            const data = await response.json();

            if (data.success) {
                if (data.conversation_id && !conversationId) {
                    setConversationId(data.conversation_id);
                    localStorage.setItem('vac_conversation_id', data.conversation_id.toString());
                }

                // Add AI response if available
                if (data.ai_response) {
                    setMessages(prev => [...prev, {
                        id: data.ai_response.id,
                        sender_type: data.ai_response.sender_type,
                        message: data.ai_response.message,
                        created_at: data.ai_response.created_at
                    }]);
                    setLastMessageId(data.ai_response.id);
                }
            }
        } catch (error) {
            console.error('Error sending message:', error);
        } finally {
            setIsLoading(false);
        }
    };

    // Toggle chat
    const toggleChat = () => {
        setIsOpen(!isOpen);
        if (!isOpen) {
            setUnreadCount(0);
        }
    };

    // Format time
    const formatTime = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <>
            {/* Chat Button */}
            <button
                onClick={toggleChat}
                className="vac-chat-button"
                aria-label="Open chat"
            >
                {isOpen ? (
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M18 6L6 18M6 6l12 12" strokeWidth="2" strokeLinecap="round" />
                    </svg>
                ) : (
                    <>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" strokeWidth="2" />
                        </svg>
                        {unreadCount > 0 && (
                            <span className="vac-unread-badge">{unreadCount}</span>
                        )}
                    </>
                )}
            </button>

            {/* Chat Window */}
            {isOpen && (
                <div className="vac-chat-window">
                    {/* Header */}
                    <div className="vac-chat-header">
                        <div className="vac-header-info">
                            <h3>Hỗ trợ trực tuyến</h3>
                            <p>Chúng tôi luôn sẵn sàng hỗ trợ bạn</p>
                        </div>
                        <button onClick={toggleChat} className="vac-close-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M18 6L6 18M6 6l12 12" strokeWidth="2" strokeLinecap="round" />
                            </svg>
                        </button>
                    </div>

                    {/* Messages */}
                    <div className="vac-messages-container">
                        {messages.length === 0 && (
                            <div className="vac-welcome-message">
                                <p>👋 Xin chào! Chúng tôi có thể giúp gì cho bạn?</p>
                            </div>
                        )}

                        {messages.map((msg) => (
                            <div
                                key={msg.id}
                                className={`vac-message vac-message-${msg.sender_type}`}
                            >
                                <div className="vac-message-bubble">
                                    {msg.message.split('\n').map((line, i) => (
                                        <span key={i}>
                                            {line}
                                            {i < msg.message.split('\n').length - 1 && <br />}
                                        </span>
                                    ))}
                                </div>
                                <div className="vac-message-time">
                                    {formatTime(msg.created_at)}
                                </div>
                            </div>
                        ))}

                        {isLoading && (
                            <div className="vac-message vac-message-ai">
                                <div className="vac-message-bubble vac-typing">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        )}

                        <div ref={messagesEndRef} />
                    </div>

                    {/* Input */}
                    <form onSubmit={sendMessage} className="vac-input-form">
                        <input
                            type="text"
                            value={inputMessage}
                            onChange={(e) => setInputMessage(e.target.value)}
                            placeholder="Nhập tin nhắn..."
                            disabled={isLoading}
                            className="vac-input"
                        />
                        <button
                            type="submit"
                            disabled={!inputMessage.trim() || isLoading}
                            className="vac-send-btn"
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>
                    </form>
                </div>
            )}
        </>
    );
}
