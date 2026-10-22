<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $video->title }}</title>
    
    <!-- HLS.js -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    
    <!-- Plyr CSS -->
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #000;
        }
        
        .video-container {
            width: 100%;
            height: 100%;
            position: relative;
        }
        
        video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
            font-family: Arial, sans-serif;
            pointer-events: none;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            z-index: 100;
            user-select: none;
        }
        
        .watermark.top-right { top: 10px; right: 10px; }
        .watermark.top-left { top: 10px; left: 10px; }
        .watermark.bottom-right { bottom: 50px; right: 10px; }
        .watermark.bottom-left { bottom: 50px; left: 10px; }
        .watermark.center { top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 24px; }

        /* Password Screen */
        .password-screen {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        
        .password-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .password-box .lock-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .password-box h3 {
            color: #fff;
            margin-bottom: 10px;
            font-family: Arial, sans-serif;
        }
        
        .password-box p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
        
        .password-box input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 16px;
            margin-bottom: 16px;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .password-box input:focus {
            border-color: var(--plyr-color-main);
        }
        
        .password-box input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .password-box button {
            width: 100%;
            padding: 12px 24px;
            background: var(--plyr-color-main);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .password-box button:hover {
            opacity: 0.9;
        }
        
        .password-box .error-message {
            color: #ff6b6b;
            font-size: 14px;
            margin-top: 10px;
            display: none;
        }

        /* Expired/Unavailable Screen */
        .unavailable-screen {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-family: Arial, sans-serif;
            z-index: 2000;
        }
        
        .unavailable-screen .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .unavailable-screen h3 {
            margin-bottom: 10px;
        }
        
        .unavailable-screen p {
            color: rgba(255, 255, 255, 0.7);
        }
        
        /* Custom Plyr theme */
        :root {
            --plyr-color-main: {{ $config['color'] ?? '#2A1274' }};
        }
        
        .plyr--video .plyr__control--overlaid {
            background: var(--plyr-color-main);
        }
        
        /* Anti-selection */
        .video-container,
        .video-container * {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        /* Loading spinner */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--plyr-color-main);
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Error message */
        .error-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            z-index: 1000;
        }
        
        .error-overlay h3 {
            margin-bottom: 10px;
        }
        
        .error-overlay p {
            color: #999;
            font-size: 14px;
        }
    </style>
</head>
<body>
    @php
        $isExpired = !empty($video->expires_at) && \Carbon\Carbon::parse($video->expires_at)->isPast();
        $reachedMaxViews = !empty($video->max_views) && ($video->views_count ?? 0) >= $video->max_views;
        $requiresPassword = !empty($video->password_protected) && !empty($video->password);
        $isUnlocked = session('video_unlocked_' . $video->uuid);
    @endphp
    
    {{-- Check if video is expired or max views reached --}}
    @if($isExpired)
    <div class="unavailable-screen">
        <div class="icon">⏰</div>
        <h3>{{ __('Video Expired') }}</h3>
        <p>{{ __('This video is no longer available.') }}</p>
    </div>
    @elseif($reachedMaxViews)
    <div class="unavailable-screen">
        <div class="icon">🚫</div>
        <h3>{{ __('View Limit Reached') }}</h3>
        <p>{{ __('This video has reached its maximum number of views.') }}</p>
    </div>
    @elseif($requiresPassword && !$isUnlocked)
    {{-- Password Screen --}}
    <div class="password-screen" id="passwordScreen">
        <div class="password-box">
            <div class="lock-icon">🔒</div>
            <h3>{{ __('Password Protected') }}</h3>
            <p>{{ __('This video requires a password to watch.') }}</p>
            <form id="passwordForm">
                <input type="password" id="videoPassword" placeholder="{{ __('Enter password') }}" required>
                <button type="submit">{{ __('Unlock Video') }}</button>
                <div class="error-message" id="passwordError">{{ __('Incorrect password. Please try again.') }}</div>
            </form>
        </div>
    </div>
    @else
    <div class="video-container" id="videoContainer">
        <!-- Loading -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
        </div>
        
        <!-- Error -->
        <div class="error-overlay" id="errorOverlay" style="display: none;">
            <h3>{{ __('Unable to play video') }}</h3>
            <p id="errorMessage">{{ __('An error occurred while loading the video.') }}</p>
        </div>
        
        <!-- Video Player -->
        <video id="player" playsinline>
            <source src="" type="application/x-mpegURL">
        </video>
        
        <!-- Watermark -->
        @if($video->watermark_enabled && $video->watermark_text)
        <div class="watermark {{ $video->watermark_position ?? 'top-right' }}" id="watermark">{{ $video->watermark_text }}</div>
        @endif
    </div>
    @endif

    <!-- Plyr JS -->
    <script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
    
    <script>
        (function() {
            'use strict';
            
            const video = document.getElementById('player');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const errorOverlay = document.getElementById('errorOverlay');
            const errorMessage = document.getElementById('errorMessage');
            const token = '{{ $token }}';
            const masterPlaylistUrl = '{{ route("video.stream.master", $video->uuid) }}?token=' + token;
            const sessionId = 'vs_' + Math.random().toString(36).substr(2, 9);
            
            // Prevent right-click
            document.addEventListener('contextmenu', e => e.preventDefault());
            
            // Prevent keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Block Ctrl+S, Ctrl+U, F12
                if ((e.ctrlKey && (e.key === 's' || e.key === 'u')) || e.key === 'F12') {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Detect DevTools
            (function detectDevTools() {
                const threshold = 160;
                const check = function() {
                    const widthThreshold = window.outerWidth - window.innerWidth > threshold;
                    const heightThreshold = window.outerHeight - window.innerHeight > threshold;
                    
                    if (widthThreshold || heightThreshold) {
                        document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#000;color:#fff;"><h2>{{ __("Developer tools detected. Video playback disabled.") }}</h2></div>';
                    }
                };
                setInterval(check, 1000);
            })();
            
            // Initialize player
            function initPlayer() {
                if (Hls.isSupported()) {
                    const hls = new Hls({
                        xhrSetup: function(xhr, url) {
                            // Add token to all requests
                            if (!url.includes('token=')) {
                                const separator = url.includes('?') ? '&' : '?';
                                xhr.open('GET', url + separator + 'token=' + token, true);
                            }
                        },
                        maxBufferLength: 30,
                        maxMaxBufferLength: 60,
                        startLevel: -1, // Auto quality
                    });
                    
                    hls.loadSource(masterPlaylistUrl);
                    hls.attachMedia(video);
                    
                    hls.on(Hls.Events.MANIFEST_PARSED, function(event, data) {
                        loadingOverlay.style.display = 'none';
                        initPlyr(hls);
                    });
                    
                    hls.on(Hls.Events.ERROR, function(event, data) {
                        if (data.fatal) {
                            loadingOverlay.style.display = 'none';
                            errorOverlay.style.display = 'flex';
                            
                            switch(data.type) {
                                case Hls.ErrorTypes.NETWORK_ERROR:
                                    errorMessage.textContent = '{{ __("Network error. Please check your connection.") }}';
                                    break;
                                case Hls.ErrorTypes.MEDIA_ERROR:
                                    errorMessage.textContent = '{{ __("Media error. The video format may not be supported.") }}';
                                    break;
                                default:
                                    errorMessage.textContent = '{{ __("An error occurred while playing the video.") }}';
                            }
                            
                            console.error('HLS error:', data);
                        }
                    });
                    
                } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    // Safari native HLS support
                    video.src = masterPlaylistUrl;
                    video.addEventListener('loadedmetadata', function() {
                        loadingOverlay.style.display = 'none';
                        initPlyr(null);
                    });
                    
                    video.addEventListener('error', function() {
                        loadingOverlay.style.display = 'none';
                        errorOverlay.style.display = 'flex';
                    });
                } else {
                    loadingOverlay.style.display = 'none';
                    errorOverlay.style.display = 'flex';
                    errorMessage.textContent = '{{ __("Your browser does not support HLS video playback.") }}';
                }
            }
            
            function initPlyr(hls) {
                const qualityOptions = hls ? hls.levels.map((l, i) => l.height) : [];
                
                const playerConfig = {
                    controls: {{ $config['controls'] ?? true ? 'true' : 'false' }} ? [
                        'play-large',
                        'play',
                        'progress',
                        'current-time',
                        'mute',
                        'volume',
                        'captions',
                        'settings',
                        'pip',
                        'airplay',
                        'fullscreen'
                    ] : [],
                    settings: ['quality', 'speed'],
                    speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                    quality: {
                        default: qualityOptions[qualityOptions.length - 1] || 720,
                        options: qualityOptions,
                        forced: true,
                        onChange: function(quality) {
                            if (hls) {
                                hls.levels.forEach((level, index) => {
                                    if (level.height === quality) {
                                        hls.currentLevel = index;
                                    }
                                });
                            }
                            recordAnalytics('quality_change', { quality: quality });
                        }
                    },
                    autoplay: {{ $config['autoplay'] ?? false ? 'true' : 'false' }},
                    loop: { active: {{ $config['loop'] ?? false ? 'true' : 'false' }} },
                    muted: {{ $config['muted'] ?? false ? 'true' : 'false' }},
                    fullscreen: { enabled: {{ $config['fullscreen'] ?? true ? 'true' : 'false' }} },
                    disableContextMenu: true,
                    storage: { enabled: true, key: 'vs_player' },
                    keyboard: { focused: true, global: false },
                    tooltips: { controls: true, seek: true },
                };
                
                const player = new Plyr(video, playerConfig);
                
                // Event tracking
                player.on('play', () => recordAnalytics('play'));
                player.on('pause', () => recordAnalytics('pause'));
                player.on('seeked', () => recordAnalytics('seek', { position: Math.floor(player.currentTime) }));
                player.on('ended', () => recordAnalytics('complete'));
                
                // Progress tracking (every 30 seconds)
                let lastProgressReport = 0;
                player.on('timeupdate', () => {
                    const currentTime = Math.floor(player.currentTime);
                    if (currentTime - lastProgressReport >= 30) {
                        lastProgressReport = currentTime;
                        recordAnalytics('progress', { position: currentTime });
                    }
                });
                
                // Handle quality selector with HLS
                if (hls) {
                    player.on('qualitychange', event => {
                        const quality = event.detail.quality;
                        hls.levels.forEach((level, index) => {
                            if (level.height === quality) {
                                hls.currentLevel = index;
                            }
                        });
                    });
                }
            }
            
            // Analytics
            function recordAnalytics(event, data = {}) {
                const payload = {
                    session_id: sessionId,
                    event: event,
                    ...data
                };
                
                fetch('{{ route("video.analytics", $video->uuid) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                }).catch(() => {}); // Silent fail
            }
            
            // Block download attempts
            document.addEventListener('beforeunload', function(e) {
                // Clean up
            });
            
            // Disable drag
            document.addEventListener('dragstart', e => e.preventDefault());
            
            // Start player
            initPlayer();
        })();
        
        // Password form handler
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const password = document.getElementById('videoPassword').value;
                const errorEl = document.getElementById('passwordError');
                
                fetch('{{ route("video.verify.password", $video->uuid) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ password: password })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        errorEl.style.display = 'block';
                    }
                })
                .catch(() => {
                    errorEl.style.display = 'block';
                });
            });
        }
    </script>
</body>
</html>
