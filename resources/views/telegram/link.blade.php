@extends('layouts.admin')

@section('page-title')
    {{ __('Link Telegram Account') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Link Telegram Account') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-brand-telegram text-info me-1"></i>{{ __('Link Telegram Account') }}
                    </h5>
                    <small class="text-muted">
                        {{ __('Task notifications (assignments, stage changes, reminders) will arrive in your Telegram.') }}
                    </small>
                </div>
                <div class="card-body">
                    @if (!$configured)
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle me-1"></i>
                            {{ __('Telegram is not configured yet. Ask your administrator to set the bot token and username first.') }}
                        </div>
                    @elseif ($linkUser->telegram_chat_id)
                        <div class="alert alert-success">
                            <i class="ti ti-circle-check me-1"></i>
                            {{ __('Your account is linked.') }}
                            <span class="text-muted small" dir="ltr">(chat id: {{ $linkUser->telegram_chat_id }})</span>
                        </div>
                        <form method="post" action="{{ route('telegram.link.unlink') }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="ti ti-unlink me-1"></i>{{ __('Unlink this account') }}
                            </button>
                        </form>
                    @else
                        @if ($deepLink)
                            <ol class="mb-3">
                                <li>{!! __('Open this link (on your phone or desktop):') !!}</li>
                            </ol>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control text-primary" dir="ltr" readonly
                                    value="{{ $deepLink }}" onclick="this.select()" id="tg-deep-link">
                                <a href="{{ $deepLink }}" target="_blank" rel="noopener"
                                    class="btn btn-primary">
                                    <i class="ti ti-brand-telegram me-1"></i>{{ __('Open Telegram') }}
                                </a>
                            </div>
                            <ol start="2">
                                <li>{{ __('Press START in the chat with the bot.') }}</li>
                                <li>{{ __('Linking completes automatically on the next run (within a minute) — no need to wait on this page.') }}</li>
                            </ol>
                            <div class="alert alert-light border small">
                                <i class="ti ti-info-circle me-1"></i>
                                {{ __('This link is one-time and expires at:') }}
                                <strong>{{ $expiresAt }}</strong>.
                                {{ __('If it expires, generate a fresh one below.') }}
                            </div>
                            <form method="post" action="{{ route('telegram.link.regenerate') }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-light">
                                    <i class="ti ti-refresh me-1"></i>{{ __('Generate a fresh link') }}
                                </button>
                            </form>
                        @else
                            <div class="alert alert-warning">
                                {{ __('No active link. Generate one to start.') }}
                            </div>
                            <form method="post" action="{{ route('telegram.link.regenerate') }}" class="d-inline">
                                @csrf
                                <button class="btn btn-primary">
                                    <i class="ti ti-link me-1"></i>{{ __('Generate my link') }}
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
