<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Ticket Confirmation') }}</title>
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
            font-size: 24px;
            font-weight: bold;
            color: #6366f1;
            text-align: center;
            padding: 15px;
            background: #f0f0ff;
            border-radius: 8px;
            margin: 15px 0;
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table td {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        table td:first-child {
            font-weight: bold;
            width: 120px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('Ticket Received') }}</h1>
    </div>
    
    <div class="content">
        <p>{{ __('Hello') }} {{ $ticket->submitter_name }},</p>
        
        <p>{{ __('Thank you for contacting us. Your support ticket has been successfully created.') }}</p>
        
        <div class="ticket-code">
            {{ $ticket->ticket_code }}
        </div>
        
        <div class="ticket-info">
            <table>
                <tr>
                    <td>{{ __('Subject') }}:</td>
                    <td>{{ $ticket->subject }}</td>
                </tr>
                <tr>
                    <td>{{ __('Priority') }}:</td>
                    <td>{{ \App\Models\Support::$priority[$ticket->priority] ?? 'Unknown' }}</td>
                </tr>
                <tr>
                    <td>{{ __('Status') }}:</td>
                    <td>{{ $ticket->status }}</td>
                </tr>
                <tr>
                    <td>{{ __('Created') }}:</td>
                    <td>{{ $ticket->created_at->format('Y-m-d H:i') }}</td>
                </tr>
            </table>
        </div>
        
        <p>{{ __('You can track your ticket status and add additional information using the link below:') }}</p>
        
        <p style="text-align: center;">
            <a href="{{ $ticket->tracking_url }}" class="btn">{{ __('Track Your Ticket') }}</a>
        </p>
        
        <p style="text-align: center;">
            <small>{{ __('Or copy this link') }}: <br>{{ $ticket->tracking_url }}</small>
        </p>
        
        <p>{{ __('Our support team will review your ticket and respond as soon as possible.') }}</p>
    </div>
    
    <div class="footer">
        <p>{{ __('This is an automated message. Please do not reply directly to this email.') }}</p>
        <p>&copy; {{ date('Y') }} {{ $settings->localized_title ?? config('app.name') }}</p>
    </div>
</body>
</html>
