<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $video->title }} — My Communication Stream</title>
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; flex-direction: column; align-items: center; }
        .player-wrapper { width: 100%; max-width: 960px; margin: 40px auto 0; padding: 0 16px; }
        .video-container { position: relative; width: 100%; background: #000; border-radius: 12px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .video-js { width: 100% !important; }
        .video-js .vjs-big-play-button {
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            width: 80px; height: 80px;
            line-height: 80px;
            border: none;
            background: rgba(99, 102, 241, 0.9);
        }
        .video-js .vjs-big-play-button:hover { background: rgba(99, 102, 241, 1); }
        .video-js .vjs-control-bar { background: rgba(0, 0, 0, 0.7); }
        .video-js .vjs-play-progress { background: #6366f1; }
        .video-js .vjs-volume-level { background: #6366f1; }
        .video-info { max-width: 960px; width: 100%; padding: 24px 16px; }
        .video-info h1 { color: #f1f5f9; font-size: 22px; font-weight: 600; margin-bottom: 8px; }
        .video-info p { color: #94a3b8; font-size: 14px; line-height: 1.6; }
        .watermark-overlay {
            position: absolute; z-index: 10; pointer-events: none;
            font-family: monospace; font-size: 14px;
            color: rgba(255, 255, 255, var(--watermark-opacity, 0.3));
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            padding: 8px 12px; transition: all 2s ease-in-out;
            user-select: none; -webkit-user-select: none;
        }
        .branding { color: #475569; font-size: 12px; text-align: center; padding: 40px 16px; }
        .branding a { color: #6366f1; text-decoration: none; }
        .no-context { -webkit-touch-callout: none; user-select: none; }
    </style>
</head>
<body class="no-context" oncontextmenu="return false;">
    <div class="player-wrapper">
        <div class="video-container">
            @if($watermark)
                <div class="watermark-overlay" id="watermark"
                     style="--watermark-opacity: {{ $watermark['opacity'] }};">
                    {{ $watermark['text'] }}
                </div>
            @endif
            <video id="secure-player" class="video-js vjs-big-play-centered vjs-16-9"
                   controls preload="auto" playsinline data-setup='{}'>
                <p class="vjs-no-js">JavaScript is required to view this video.</p>
            </video>
        </div>
    </div>

    <div class="video-info">
        <h1>{{ $video->title }}</h1>
        @if($video->description)
            <p>{{ $video->description }}</p>
        @endif
    </div>

    <div class="branding">
        Powered by <a href="#">My Communication Stream</a>
    </div>

    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script>
    (function() {
        'use strict';

        const config = {
            streamUrl: @json($streamUrl),
            videoHash: @json($hash),
            tokenExpiry: {{ $tokenExpiry }},
            watermark: @json($watermark),
        };

        const player = videojs('secure-player', {
            controls: true,
            autoplay: false,
            preload: 'auto',
            responsive: true,
            controlBar: {
                children: [
                    'playToggle',
                    'volumePanel',
                    'currentTimeDisplay',
                    'timeDivider',
                    'durationDisplay',
                    'progressControl',
                    'remainingTimeDisplay',
                    'fullscreenToggle',
                ],
            },
            html5: {
                vhs: {
                    overrideNative: true,
                    enableLowInitialPlaylist: true,
                },
            },
            sources: [{
                src: config.streamUrl,
                type: 'application/x-mpegURL',
            }],
        });

        player.on('contextmenu', function(e) { e.preventDefault(); return false; });

        player.ready(function() {
            const controlBar = player.controlBar;
            if (controlBar && controlBar.getChild('downloadButton')) {
                controlBar.removeChild('downloadButton');
            }
        });

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 's' || e.key === 'U' || e.key === 'S')) { e.preventDefault(); return false; }
            if (e.key === 'F12') { e.preventDefault(); return false; }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'i' || e.key === 'I' || e.key === 'j' || e.key === 'J')) { e.preventDefault(); return false; }
        });

        @if($watermark)
        const watermarkEl = document.getElementById('watermark');
        const positions = [
            { top: '10%', left: '10%' },
            { top: '10%', right: '10%', left: 'auto' },
            { bottom: '15%', left: '10%', top: 'auto' },
            { bottom: '15%', right: '10%', top: 'auto', left: 'auto' },
            { top: '40%', left: '30%' },
        ];
        let posIndex = 0;
        function repositionWatermark() {
            watermarkEl.style.top = ''; watermarkEl.style.bottom = ''; watermarkEl.style.left = ''; watermarkEl.style.right = '';
            const pos = positions[posIndex % positions.length];
            Object.keys(pos).forEach(key => { watermarkEl.style[key] = pos[key]; });
            posIndex++;
        }
        repositionWatermark();
        setInterval(repositionWatermark, {{ $watermark['reposition_interval'] }} * 1000);
        function updateTimestamp() {
            const now = new Date();
            watermarkEl.textContent = config.watermark.text + ' | ' + now.toISOString().slice(0, 19).replace('T', ' ');
        }
        updateTimestamp();
        setInterval(updateTimestamp, 60000);
        @endif

        let refreshTimer;
        function scheduleTokenRefresh() {
            const refreshIn = (config.tokenExpiry - 120) * 1000;
            if (refreshIn > 0) { refreshTimer = setTimeout(refreshToken, refreshIn); }
        }
        async function refreshToken() {
            try {
                const response = await fetch('/api/embed/refresh-token', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ video_hash: config.videoHash }),
                });
                if (response.ok) {
                    const data = await response.json();
                    config.streamUrl = data.stream_url;
                    config.tokenExpiry = data.expires_in;
                    player.src({ src: data.stream_url, type: 'application/x-mpegURL' });
                    scheduleTokenRefresh();
                }
            } catch (e) { console.warn('Token refresh failed'); }
        }
        scheduleTokenRefresh();
    })();
    </script>
</body>
</html>
