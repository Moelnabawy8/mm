<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $video->title }} — SecureStream</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; padding: 40px; max-width: 480px; }
        .icon { width: 80px; height: 80px; background: #1e293b; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .spinner { width: 40px; height: 40px; border: 3px solid #334155; border-top-color: #6366f1; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { color: #f1f5f9; font-size: 20px; font-weight: 600; margin-bottom: 12px; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin-bottom: 8px; }
        .status { display: inline-block; margin-top: 16px; padding: 6px 14px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
            background: {{ $video->status === 'failed' ? '#450a0a' : '#1e1b4b' }};
            color: {{ $video->status === 'failed' ? '#fca5a5' : '#a5b4fc' }};
        }
        .refresh-note { color: #475569; font-size: 12px; margin-top: 24px; }
        .back-link { display: inline-block; margin-top: 20px; color: #6366f1; font-size: 13px; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            @if($video->status === 'failed')
                <svg width="32" height="32" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            @else
                <div class="spinner"></div>
            @endif
        </div>

        <h1>{{ $video->title }}</h1>

        @if($video->status === 'failed')
            <p>This video failed to process. Please contact the administrator.</p>
        @elseif($video->status === 'processing')
            <p>Your video is currently being processed.</p>
            <p>This usually takes a few minutes depending on file size.</p>
        @else
            <p>This video is pending processing and will be available shortly.</p>
        @endif

        <div><span class="status">{{ ucfirst($video->status) }}</span></div>

        @if($video->status !== 'failed')
            <p class="refresh-note">This page will automatically refresh every 15 seconds.</p>
            <script>setTimeout(() => location.reload(), 15000);</script>
        @endif
    </div>
</body>
</html>
