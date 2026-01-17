'use client';

import { useState, useEffect, useRef } from 'react';
import './ChatWidget-Configurable.css';

interface Message {
    id: number;
    sender_type: 'visitor' | 'ai' | 'staff' | 'system';
    message: string;
    created_at: string;
}

interface QuickReply {
    id: string;
    text: string;
    message: string;
}

interface WidgetConfig {
    // Colors
    primaryColor: string;
    backgroundColor: string;
    textColor: string;
    borderColor: string;

    // Text
    title: string;
    subtitle: string;
    welcomeMessage: string;
    inputPlaceholder: string;
    sendButtonText: string;

    // Quick Replies
    quickReplies: QuickReply[];

    // Branding
    logo?: string;
    position: 'bottom-right' | 'bottom-left';

    // Behavior
    autoOpen: boolean;
    showQuickReplies: boolean;
}

interface ChatWidgetProps {
    apiUrl: string;
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
    const [config, setConfig] = useState<WidgetConfig | null>(null);
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>([]);
    const [inputMessage, setInputMessage] = useState('');
    const [conversationId, setConversationId] = useState<number | null>(null);
    const [visitorId, setVisitorId] = useState<string>('');
    const [lastMessageId, setLastMessageId] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);
    const [showQuickReplies, setShowQuickReplies] = useState(true);

    // Visitor info form state
    const [showInfoForm, setShowInfoForm] = useState(false);
    const [formData, setFormData] = useState({
        name: visitorName || '',
        email: visitorEmail || '',
        phone: visitorPhone || ''
    });
    const [storedVisitorInfo, setStoredVisitorInfo] = useState({
        name: '',
        email: '',
        phone: ''
    });

    const messagesEndRef = useRef<HTMLDivElement>(null);
    const pollingInterval = useRef<NodeJS.Timeout | null>(null);

    // Fetch widget configuration
    useEffect(() => {
        const fetchConfig = async () => {
            try {
                const response = await fetch(`${apiUrl}/widget/config`);
                const data = await response.json();

                if (data.success) {
                    setConfig(data.config);
                    if (data.config.autoOpen) {
                        setIsOpen(true);
                    }
                } else {
                    // Fallback to default config
                    setConfig(getDefaultConfig());
                }
            } catch (error) {
                console.error('Error fetching config:', error);
                setConfig(getDefaultConfig());
            }
        };

        fetchConfig();
    }, [apiUrl]);

    // Default configuration based on design
    const getDefaultConfig = (): WidgetConfig => ({
        primaryColor: '#FDB913',
        backgroundColor: '#FFF8E7',
        textColor: '#5C4033',
        borderColor: '#F5A623',
        title: 'Ban tang lễ hỗ trợ/ tư vấn',
        subtitle: '',
        welcomeMessage: 'Ban lễ tang xin chào quý khách.\nQuý khách cần tư vấn gì a ?',
        inputPlaceholder: 'Nhập tin nhắn...',
        sendButtonText: 'Gửi',
        quickReplies: [
            { id: '1', text: 'Tang lễ', message: 'Tôi muốn tư vấn về dịch vụ tang lễ' },
            { id: '2', text: 'Đặt ô trọ', message: 'Tôi muốn đặt ô trọ' },
            { id: '3', text: 'Gói mộ', message: 'Tôi muốn tư vấn về gói mộ' },
            { id: '4', text: 'Xem hướng mộ', message: 'Tôi muốn xem hướng mộ' }
        ],
        position: 'bottom-right',
        autoOpen: false,
        showQuickReplies: true
    });

    // Generate visitor ID and check for stored info
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

        // Check for stored visitor info
        const storedInfo = localStorage.getItem('vac_visitor_info');
        if (storedInfo) {
            try {
                const parsedInfo = JSON.parse(storedInfo);
                setStoredVisitorInfo(parsedInfo);
                setFormData(parsedInfo);
            } catch (e) {
                console.error('Error parsing stored visitor info:', e);
            }
        }

        // Show form if no visitor info is available (neither from props nor localStorage)
        if (!visitorName && !storedInfo) {
            setShowInfoForm(true);
        }
    }, [visitorName]);

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

                        if (!isOpen) {
                            setUnreadCount(prev => prev + newMessages.length);
                        }
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
    const sendMessage = async (messageText?: string) => {
        const textToSend = messageText || inputMessage.trim();

        if (!textToSend || isLoading) return;

        setInputMessage('');
        setIsLoading(true);
        setShowQuickReplies(false);

        // Add user message to UI immediately
        const tempMessage: Message = {
            id: Date.now(),
            sender_type: 'visitor',
            message: textToSend,
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
                    message: textToSend,
                    name: storedVisitorInfo.name || visitorName,
                    email: storedVisitorInfo.email || visitorEmail,
                    phone: storedVisitorInfo.phone || visitorPhone,
                    page_url: typeof window !== 'undefined' ? window.location.href : ''
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

    // Handle quick reply click
    const handleQuickReply = (reply: QuickReply) => {
        sendMessage(reply.message);
    };

    // Handle visitor info form submit
    const handleInfoFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Validate required fields
        if (!formData.name.trim()) {
            alert('Vui lòng nhập tên của bạn');
            return;
        }

        // Save to localStorage
        localStorage.setItem('vac_visitor_info', JSON.stringify(formData));
        setStoredVisitorInfo(formData);
        setShowInfoForm(false);
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

    if (!config) return null;

    // Apply CSS variables
    const cssVars = {
        '--primary-color': config.primaryColor,
        '--bg-color': config.backgroundColor,
        '--text-color': config.textColor,
        '--border-color': config.borderColor,
    } as React.CSSProperties;

    return (
        <>
            {/* Chat Button */}
            <button
                onClick={toggleChat}
                className={`vac-chat-button vac-position-${config.position}`}
                style={cssVars}
                aria-label="Open chat"
            >
                {isOpen ? (
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M18 6L6 18M6 6l12 12" strokeWidth="2" strokeLinecap="round" />
                    </svg>
                ) : (
                    <>
                        {config.logo ? (
                            <img src={config.logo} alt="Chat" className="vac-button-logo" />
                        ) : (
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" strokeWidth="2" />
                            </svg>
                        )}
                        {unreadCount > 0 && (
                            <span className="vac-unread-badge">{unreadCount}</span>
                        )}
                    </>
                )}
            </button>

            {/* Chat Window */}
            {isOpen && (
                <div className={`vac-chat-window vac-position-${config.position}`} style={cssVars}>
                    {/* Header */}
                    <div className="vac-chat-header">
                        <div className="vac-header-content">
                            {config.logo && (
                                <img src={config.logo} alt="Logo" className="vac-header-logo" />
                            )}
                            <div className="vac-header-text">
                                <h3>{config.title}</h3>
                                {config.subtitle && <p>{config.subtitle}</p>}
                            </div>
                        </div>
                        <button onClick={toggleChat} className="vac-close-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M18 6L6 18M6 6l12 12" strokeWidth="2" strokeLinecap="round" />
                            </svg>
                        </button>
                    </div>

                    {/* Visitor Info Form */}
                    {showInfoForm ? (
                        <div className="vac-info-form-container">
                            <div className="vac-info-form-header">
                                <h4>👋 Xin chào!</h4>
                                <p>Vui lòng cho chúng tôi biết thông tin của bạn để bắt đầu trò chuyện</p>
                            </div>

                            <form onSubmit={handleInfoFormSubmit} className="vac-info-form">
                                <div className="vac-form-group">
                                    <label htmlFor="visitor-name">Tên của bạn *</label>
                                    <input
                                        type="text"
                                        id="visitor-name"
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                        placeholder="Nhập tên của bạn"
                                        required
                                        className="vac-form-input"
                                    />
                                </div>

                                <div className="vac-form-group">
                                    <label htmlFor="visitor-email">Email (tùy chọn)</label>
                                    <input
                                        type="email"
                                        id="visitor-email"
                                        value={formData.email}
                                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                        placeholder="email@example.com"
                                        className="vac-form-input"
                                    />
                                </div>

                                <div className="vac-form-group">
                                    <label htmlFor="visitor-phone">Số điện thoại (tùy chọn)</label>
                                    <input
                                        type="tel"
                                        id="visitor-phone"
                                        value={formData.phone}
                                        onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                                        placeholder="0123456789"
                                        className="vac-form-input"
                                    />
                                </div>

                                <button type="submit" className="vac-form-submit-btn">
                                    Bắt đầu trò chuyện
                                </button>
                            </form>
                        </div>
                    ) : (
                        <>
                            {/* Messages */}
                            <div className="vac-messages-container">
                                {/* Welcome Message */}
                                {messages.length === 0 && config.welcomeMessage && (
                                    <div className="vac-message vac-message-ai">
                                        <div className="vac-message-bubble">
                                            {config.welcomeMessage.split('\n').map((line, i) => (
                                                <span key={i}>
                                                    {line}
                                                    {i < config.welcomeMessage.split('\n').length - 1 && <br />}
                                                </span>
                                            ))}
                                        </div>
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

                                {/* Quick Replies */}
                                {showQuickReplies && config.showQuickReplies && config.quickReplies.length > 0 && (
                                    <div className="vac-quick-replies">
                                        <p className="vac-quick-replies-label">
                                            Ban có thể chọn các chủ đề dưới đây để có sự tư vấn rõ ràng nhất:
                                        </p>
                                        <div className="vac-quick-replies-grid">
                                            {config.quickReplies.map((reply) => (
                                                <button
                                                    key={reply.id}
                                                    onClick={() => handleQuickReply(reply)}
                                                    className="vac-quick-reply-btn"
                                                    disabled={isLoading}
                                                >
                                                    {reply.text}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div ref={messagesEndRef} />
                            </div>

                            {/* Input */}
                            <form onSubmit={(e) => { e.preventDefault(); sendMessage(); }} className="vac-input-form">
                                <input
                                    type="text"
                                    value={inputMessage}
                                    onChange={(e) => setInputMessage(e.target.value)}
                                    placeholder={config.inputPlaceholder}
                                    disabled={isLoading}
                                    className="vac-input"
                                />
                                <button
                                    type="submit"
                                    disabled={!inputMessage.trim() || isLoading}
                                    className="vac-send-btn"
                                >
                                    {config.sendButtonText}
                                </button>
                            </form>
                        </>
                    )}
                </div>
            )}
        </>
    );
}
