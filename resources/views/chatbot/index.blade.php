@extends('layouts.admin')

@section('page-title')
    {{ __('AI Assistant') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('AI Assistant') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <button type="button" class="btn btn-sm btn-light-secondary me-2" onclick="window.location.reload()">
            <i class="ti ti-refresh"></i> {{ __('Refresh') }}
        </button>
        <button type="button" class="btn btn-sm btn-danger" id="clearChatBtn">
            <i class="ti ti-trash"></i> {{ __('Clear Chat') }}
        </button>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card chat-card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex align-items-center">
                        <div class="chat-avatar me-3">
                            <div class="avatar-circle">
                                <i class="ti ti-robot"></i>
                            </div>
                        </div>
                        <div>
                            <h5 class="mb-0 text-white">{{ __('ERP AI Assistant') }}</h5>
                            <small class="opacity-75">
                                <span class="status-dot bg-success"></span>
                                {{ __('Online - Ready to help') }}
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="chat-container" id="chatContainer">
                        @if ($messages->isEmpty())
                            <div class="welcome-screen" id="welcomeScreen">
                                <div class="text-center py-5">
                                    <div class="welcome-icon mb-4">
                                        <i class="ti ti-message-chatbot"></i>
                                    </div>
                                    <h4 class="mb-2">{{ __('Welcome to ERP AI Assistant') }}</h4>
                                    <p class="text-muted mb-4">
                                        {{ __('I can help you navigate the platform, find features, and answer your questions about the ERP system.') }}
                                    </p>

                                    <div class="suggested-questions" id="suggestedQuestions">
                                        <p class="text-muted mb-3"><i class="ti ti-bulb me-1"></i> {{ __('Try asking:') }}
                                        </p>
                                        <div class="row g-2 justify-content-center">
                                            @foreach ($suggestedQuestions as $sq)
                                                <div class="col-md-6">
                                                    <button class="suggestion-btn w-100"
                                                        onclick="askQuestion('{{ $sq['question'] }}')">
                                                        <i class="{{ $sq['icon'] }} me-2"></i>
                                                        <span>{{ $sq['title'] }}</span>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            @foreach ($messages as $message)
                                <div class="message-wrapper {{ $message->role === 'user' ? 'user-side' : 'bot-side' }}">
                                    <div class="message-avatar">
                                        @if ($message->role === 'user')
                                            <div class="avatar-user"><i class="ti ti-user"></i></div>
                                        @else
                                            <div class="avatar-bot"><i class="ti ti-robot"></i></div>
                                        @endif
                                    </div>
                                    <div class="message-content">
                                        <div
                                            class="message-bubble {{ $message->role === 'user' ? 'user-bubble' : 'bot-bubble' }}">
                                            <div class="message-text">{!! nl2br(e($message->message)) !!}</div>
                                        </div>
                                        <div class="message-time">
                                            {{ $message->created_at->format('h:i A') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        <div class="typing-indicator d-none" id="typingIndicator">
                            <div class="message-wrapper bot-side">
                                <div class="message-avatar">
                                    <div class="avatar-bot"><i class="ti ti-robot"></i></div>
                                </div>
                                <div class="message-content">
                                    <div class="message-bubble bot-bubble typing-bubble">
                                        <div class="typing-dots">
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </div>
                                        <span class="typing-text">{{ __('AI is thinking...') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light">
                    <form id="chatForm" class="chat-input-form">
                        <div class="input-group">
                            <input type="text" class="form-control chat-input" id="messageInput"
                                placeholder="{{ __('Type your message here...') }}" autocomplete="off" maxlength="2000">
                            <button class="btn btn-primary send-btn" type="submit" id="sendBtn">
                                <i class="ti ti-send"></i>
                                <span class="btn-text">{{ __('Send') }}</span>
                            </button>
                        </div>
                        <div class="input-hint mt-2">
                            <small class="text-muted">
                                <i class="ti ti-info-circle me-1"></i>
                                {{ __('Press Enter to send, Shift+Enter for new line') }}
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-help me-2"></i>{{ __('Quick Help') }}</h5>
                </div>
                <div class="card-body">
                    <div class="quick-links">
                        <p class="text-muted mb-3">{{ __('Popular Questions:') }}</p>

                        @foreach ($suggestedQuestions as $sq)
                            <div class="quick-link-item" onclick="askQuestion('{{ $sq['question'] }}')">
                                <div class="quick-link-icon">
                                    <i class="{{ $sq['icon'] }}"></i>
                                </div>
                                <div class="quick-link-content">
                                    <strong>{{ $sq['title'] }}</strong>
                                    <small class="d-block text-muted">{{ Str::limit($sq['question'], 40) }}</small>
                                </div>
                                <i class="ti ti-chevron-right"></i>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-keyboard me-2"></i>{{ __('Shortcuts') }}</h5>
                </div>
                <div class="card-body">
                    <div class="shortcut-list">
                        <div class="shortcut-item">
                            <kbd>Enter</kbd>
                            <span>{{ __('Send message') }}</span>
                        </div>
                        <div class="shortcut-item">
                            <kbd>Shift + Enter</kbd>
                            <span>{{ __('New line') }}</span>
                        </div>
                        <div class="shortcut-item">
                            <kbd>Esc</kbd>
                            <span>{{ __('Clear input') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3 bg-light-primary">
                <div class="card-body text-center">
                    <i class="ti ti-bulb text-primary" style="font-size: 32px;"></i>
                    <h6 class="mt-2 mb-1">{{ __('Pro Tip') }}</h6>
                    <p class="text-muted small mb-0">
                        {{ __('Ask me anything about the ERP system - employees, invoices, reports, projects, and more!') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css-page')
    <style>
        .chat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .chat-card .card-header {
            border-radius: 12px 12px 0 0;
            padding: 15px 20px;
        }

        .avatar-circle {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-circle i {
            font-size: 24px;
            color: white;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .chat-container {
            height: 500px;
            overflow-y: auto;
            padding: 20px;
            background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
            scroll-behavior: smooth;
        }

        .welcome-screen {
            padding: 40px 20px;
        }

        .welcome-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .welcome-icon i {
            font-size: 40px;
            color: white;
        }

        .suggestion-btn {
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 16px;
            text-align: left;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .suggestion-btn:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateY(-2px);
        }

        .suggestion-btn i {
            color: #667eea;
            font-size: 18px;
        }

        .message-wrapper {
            display: flex;
            margin-bottom: 16px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-wrapper.user-side {
            flex-direction: row-reverse;
        }

        .message-avatar {
            flex-shrink: 0;
            margin: 0 10px;
        }

        .avatar-user,
        .avatar-bot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-user {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .avatar-bot {
            background: #e9ecef;
            color: #6c757d;
        }

        .message-content {
            max-width: 70%;
        }

        .user-side .message-content {
            text-align: right;
        }

        .message-bubble {
            padding: 12px 16px;
            border-radius: 18px;
            display: inline-block;
            max-width: 100%;
            word-wrap: break-word;
        }

        .user-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .bot-bubble {
            background: white;
            color: #333;
            border: 1px solid #e9ecef;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .message-text {
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .message-time {
            font-size: 11px;
            color: #9e9e9e;
            margin-top: 4px;
        }

        .typing-bubble {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .typing-dots span:nth-child(1) {
            animation-delay: -0.32s;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes bounce {

            0%,
            80%,
            100% {
                transform: scale(0);
            }

            40% {
                transform: scale(1);
            }
        }

        .typing-text {
            color: #9e9e9e;
            font-size: 13px;
        }

        .chat-input-form .input-group {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-radius: 25px;
            overflow: hidden;
        }

        .chat-input {
            border: none;
            padding: 15px 20px;
            font-size: 15px;
        }

        .chat-input:focus {
            box-shadow: none;
            border: none;
        }

        .send-btn {
            padding: 10px 25px;
            border-radius: 0 25px 25px 0 !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .send-btn i {
            font-size: 18px;
        }

        .quick-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .quick-link-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-link-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .quick-link-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .quick-link-icon i {
            color: white;
            font-size: 18px;
        }

        .quick-link-content {
            flex: 1;
        }

        .shortcut-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .shortcut-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .shortcut-item kbd {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .bg-light-primary {
            background: rgba(102, 126, 234, 0.1) !important;
        }

        @media (max-width: 992px) {
            .chat-container {
                height: 400px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatContainer = document.getElementById('chatContainer');
            const messageInput = document.getElementById('messageInput');
            const chatForm = document.getElementById('chatForm');
            const clearChatBtn = document.getElementById('clearChatBtn');
            const typingIndicator = document.getElementById('typingIndicator');
            const welcomeScreen = document.getElementById('welcomeScreen');
            const suggestedQuestions = document.getElementById('suggestedQuestions');

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const csrf = csrfToken ? csrfToken.getAttribute('content') : '';

            function scrollToBottom() {
                if (chatContainer) {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            }

            function formatMessage(text) {
                return text.replace(/\n/g, '<br>');
            }

            function addMessage(message, role) {
                if (welcomeScreen) {
                    welcomeScreen.style.display = 'none';
                }

                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });

                const messageHtml = `
            <div class="message-wrapper ${role === 'user' ? 'user-side' : 'bot-side'}">
                <div class="message-avatar">
                    ${role === 'user' 
                        ? '<div class="avatar-user"><i class="ti ti-user"></i></div>'
                        : '<div class="avatar-bot"><i class="ti ti-robot"></i></div>'
                    }
                </div>
                <div class="message-content">
                    <div class="message-bubble ${role === 'user' ? 'user-bubble' : 'bot-bubble'}">
                        <div class="message-text">${formatMessage(message)}</div>
                    </div>
                    <div class="message-time">${timeString}</div>
                </div>
            </div>
        `;

                if (typingIndicator) {
                    typingIndicator.insertAdjacentHTML('beforebegin', messageHtml);
                } else {
                    chatContainer.insertAdjacentHTML('beforeend', messageHtml);
                }

                scrollToBottom();
            }

            function showTyping() {
                if (typingIndicator) {
                    typingIndicator.classList.remove('d-none');
                    scrollToBottom();
                }
            }

            function hideTyping() {
                if (typingIndicator) {
                    typingIndicator.classList.add('d-none');
                }
            }

            function setLoading(isLoading) {
                const sendBtn = document.getElementById('sendBtn');
                if (isLoading) {
                    showTyping();
                    sendBtn.disabled = true;
                    messageInput.disabled = true;
                } else {
                    hideTyping();
                    sendBtn.disabled = false;
                    messageInput.disabled = false;
                    messageInput.focus();
                }
            }

            function showNotification(message, type = 'success') {
                const toastEl = document.getElementById('liveToast');
                if (toastEl) {
                    const toastBody = toastEl.querySelector('.toast-body');
                    if (toastBody) {
                        toastBody.textContent = message;
                    }
                    toastEl.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info');
                    toastEl.classList.add('bg-' + type);
                    const toast = new bootstrap.Toast(toastEl);
                    toast.show();
                } else {
                    alert(message);
                }
            }

            async function sendMessage(messageText) {
                const message = messageText || messageInput.value.trim();

                if (!message) {
                    showNotification('{{ __('Please enter a message') }}', 'warning');
                    return;
                }

                addMessage(message, 'user');
                messageInput.value = '';
                setLoading(true);

                try {
                    const response = await fetch('{{ route('chatbot.send') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            message: message
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        addMessage(data.message, 'assistant');
                    } else {
                        showNotification(data.message || '{{ __('An error occurred') }}', 'danger');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('{{ __('Failed to send message. Please check your connection.') }}',
                        'danger');
                } finally {
                    setLoading(false);
                }
            }

            async function clearChat() {
                if (!confirm('{{ __('Are you sure you want to clear all chat history?') }}')) {
                    return;
                }

                try {
                    const response = await fetch('{{ route('chatbot.clear') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        showNotification('{{ __('Chat history cleared successfully') }}', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        showNotification(data.message || '{{ __('Failed to clear chat history') }}', 'danger');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('{{ __('An error occurred') }}', 'danger');
                }
            }

            if (chatForm) {
                chatForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    sendMessage();
                });
            }

            if (messageInput) {
                messageInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                    if (e.key === 'Escape') {
                        messageInput.value = '';
                    }
                });
            }

            if (clearChatBtn) {
                clearChatBtn.addEventListener('click', clearChat);
            }

            window.askQuestion = function(question) {
                messageInput.value = question;
                sendMessage(question);
            };

            scrollToBottom();
            if (messageInput) {
                messageInput.focus();
            }
        });
    </script>
@endpush
