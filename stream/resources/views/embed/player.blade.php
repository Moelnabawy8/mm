<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $video->title }} — SecureStream</title>
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; overflow: hidden; background: #000; }
        .video-container { width: 100%; height: 100%; position: relative; }
        .video-js { width: 100% !important; height: 100% !important; }
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

        /* Watermark */
        .watermark-overlay {
            position: absolute;
            z-index: 10;
            pointer-events: none;
            font-family: monospace;
            font-size: 14px;
            color: rgba(255, 255, 255, var(--watermark-opacity, 0.3));
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            padding: 8px 12px;
            transition: all 2s ease-in-out;
            user-select: none;
            -webkit-user-select: none;
        }

        /* Disable right-click visual cue */
        .no-context { -webkit-touch-callout: none; user-select: none; }
    </style>
</head>
<body class="no-context" oncontextmenu="return false;">
    <div class="video-container" id="video-container">
        @if($watermark)
            <div class="watermark-overlay" id="watermark"
                 style="--watermark-opacity: {{ $watermark['opacity'] }};">
                {{ $watermark['text'] }}
            </div>
        @endif

        <video id="secure-player" class="video-js vjs-big-play-centered vjs-fluid"
               controls preload="auto" playsinline
               data-setup='{}'>
            <p class="vjs-no-js">JavaScript is required to view this video.</p>
        </video>
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

        // Initialize Video.js
        const player = videojs('secure-player', {
            controls: true,
            autoplay: false,
            preload: 'auto',
            fluid: true,
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

        // Disable right-click on video element
        player.on('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // Remove download button if present
        player.ready(function() {
            const controlBar = player.controlBar;
            if (controlBar && controlBar.getChild('downloadButton')) {
                controlBar.removeChild('downloadButton');
            }
        });

        // Disable keyboard shortcuts for source viewing
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 's' || e.key === 'U' || e.key === 'S')) {
                e.preventDefault();
                return false;
            }
            if (e.key === 'F12') {
                e.preventDefault();
                return false;
            }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'i' || e.key === 'I' || e.key === 'j' || e.key === 'J')) {
                e.preventDefault();
                return false;
            }
        });

        // Watermark repositioning
        @if($watermark)
        const watermarkEl = document.getElementById('watermark');
        const positions = [
            { top: '10%', left: '10%' },
            { top: '10%', right: '10%', left: 'auto' },
            { bottom: '15%', left: '10%', top: 'auto' },
            { bottom: '15%', right: '10%', top: 'auto', left: 'auto' },
            { top: '40%', left: '30%' },
            { top: '20%', left: '50%' },
        ];
        let posIndex = 0;

        function repositionWatermark() {
            watermarkEl.style.top = '';
            watermarkEl.style.bottom = '';
            watermarkEl.style.left = '';
            watermarkEl.style.right = '';

            const pos = positions[posIndex % positions.length];
            Object.keys(pos).forEach(key => {
                watermarkEl.style[key] = pos[key];
            });
            posIndex++;
        }

        repositionWatermark();
        setInterval(repositionWatermark, {{ $watermark['reposition_interval'] }} * 1000);

        // Update watermark with timestamp
        function updateTimestamp() {
            const now = new Date();
            const timeStr = now.toISOString().slice(0, 19).replace('T', ' ');
            watermarkEl.textContent = config.watermark.text + ' | ' + timeStr;
        }
        updateTimestamp();
        setInterval(updateTimestamp, 60000);
        @endif

        // Token auto-refresh
        let refreshTimer;
        function scheduleTokenRefresh() {
            const refreshIn = (config.tokenExpiry - 120) * 1000;
            if (refreshIn > 0) {
                refreshTimer = setTimeout(refreshToken, refreshIn);
            }
        }

        async function refreshToken() {
            try {
                const response = await fetch('/api/embed/refresh-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ video_hash: config.videoHash }),
                });

                if (response.ok) {
                    const data = await response.json();
                    config.streamUrl = data.stream_url;
                    config.tokenExpiry = data.expires_in;

                    player.src({
                        src: data.stream_url,
                        type: 'application/x-mpegURL',
                    });

                    scheduleTokenRefresh();
                }
            } catch (e) {
                console.warn('Token refresh failed');
            }
        }

        scheduleTokenRefresh();
    })();
    </script>
</body>
</html>
