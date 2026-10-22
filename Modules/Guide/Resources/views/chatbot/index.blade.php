@extends('layouts.admin')

@section('page-title')
    {{ __('AI Chatbot') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.index') }}">{{ __('System Guide') }}</a></li>
    <li class="breadcrumb-item active">{{ __('AI Chatbot') }}</li>
@endsection

@push('css-page')
    <style>
        .chatbot-container {
            height: calc(100vh - 200px);
            min-height: 600px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .chatbot-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .chatbot-header {
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }

        .chatbot-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .chatbot-header p {
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
        }

        .feature-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 10px;
            margin-top: 15px;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .loading-overlay.hidden {
            display: none;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #6c5ce7;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="chatbot-header">
                <h1><i class="ti ti-message-chatbot me-3"></i>{{ __('AI Chatbot Assistant') }}</h1>
                <p>{{ __('Get instant help and answers to your questions about the ERP system. Our AI-powered chatbot is here to assist you 24/7.') }}</p>
                <div class="mt-3">
                    <span class="feature-badge"><i class="ti ti-bolt me-1"></i>{{ __('Instant Responses') }}</span>
                    <span class="feature-badge"><i class="ti ti-brain me-1"></i>{{ __('AI-Powered') }}</span>
                    <span class="feature-badge"><i class="ti ti-clock-24 me-1"></i>{{ __('24/7 Available') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="chatbot-container position-relative">
                        <div class="loading-overlay" id="chatbotLoading">
                            <div class="text-center">
                                <div class="spinner"></div>
                                <p class="mt-3 text-muted">{{ __('Loading Chatbot...') }}</p>
                            </div>
                        </div>
                        <iframe 
                            id="chatbotFrame"
                            src="http://localhost:3001" 
                            class="chatbot-iframe"
                            onload="hideLoading()"
                        ></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="ti ti-help-circle" style="font-size: 40px; color: #6c5ce7;"></i>
                    <h5 class="mt-3">{{ __('How to Use') }}</h5>
                    <p class="text-muted small">{{ __('Simply type your question in the chat box and our AI will provide instant answers about the ERP system.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="ti ti-bulb" style="font-size: 40px; color: #6c5ce7;"></i>
                    <h5 class="mt-3">{{ __('Smart Suggestions') }}</h5>
                    <p class="text-muted small">{{ __('The chatbot provides quick reply buttons to guide you through common questions and tasks.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="ti ti-history" style="font-size: 40px; color: #6c5ce7;"></i>
                    <h5 class="mt-3">{{ __('Conversation History') }}</h5>
                    <p class="text-muted small">{{ __('Your chat history is saved so you can always refer back to previous conversations.') }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        function hideLoading() {
            document.getElementById('chatbotLoading').classList.add('hidden');
        }

        // Handle iframe errors
        document.getElementById('chatbotFrame').onerror = function() {
            document.getElementById('chatbotLoading').innerHTML = `
                <div class="text-center">
                    <i class="ti ti-alert-circle" style="font-size: 50px; color: #e74c3c;"></i>
                    <h5 class="mt-3">{{ __('Chatbot Service Unavailable') }}</h5>
                    <p class="text-muted">{{ __('Please make sure the chatbot server is running on port 3001.') }}</p>
                    <p class="text-muted small">{{ __('Run: cd ai-chatbot/frontend && npm run dev') }}</p>
                </div>
            `;
        };
    </script>
@endpush
