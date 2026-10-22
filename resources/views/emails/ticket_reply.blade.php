<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('New Reply to Your Ticket') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8fafc;
            padding: 30px;
            border: 1px solid #e2e8f0;
        }
        .ticket-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #e2e8f0;
        }
        .ticket-code {
            font-size: 18px;
            font-weight: bold;
            color: #6366f1;
        }
        .reply-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #6366f1;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('New Reply to Your Ticket') }}</h1>
    </div>
    
    <div class="content">
        <p>{{ __('Hello') }} {{ $ticket->submitter_name }},</p>
        
        <p>{{ __('Our support team has replied to your ticket.') }}</p>
        
        <div class="ticket-info">
            <p><span class="ticket-code">{{ $ticket->ticket_code }}</span></p>
            <p><strong>{{ __('Subject') }}:</strong> {{ $ticket->subject }}</p>
            <p><strong>{{ __('Status') }}:</strong> {{ $ticket->status }}</p>
        </div>
        
        <h3>{{ __('Reply') }}:</h3>
        <div class="reply-content">
            {!! nl2br(e($reply->description)) !!}
        </div>
        
        <p>{{ __('To view the full conversation and respond, please click the button below:') }}</p>
        
        <p style="text-align: center;">
            <a href="{{ $ticket->tracking_url }}" class="btn">{{ __('View Ticket') }}</a>
        </p>
        
        <p style="text-align: center;">
            <small>{{ __('Or copy this link') }}: <br>{{ $ticket->tracking_url }}</small>
        </p>
    </div>
    
    <div class="footer">
        <p>{{ __('This is an automated message. Please do not reply directly to this email.') }}</p>
        <p>&copy; {{ date('Y') }} {{ $settings->localized_title ?? config('app.name') }}</p>
    </div>
</body>
</html>
