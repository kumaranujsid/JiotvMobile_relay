<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JioTV Premium | Stream</title>
    <link rel="icon" href="https://www.jiotv.com/favicon.ico" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb;
            --accent-color: #d946ef;
            --glass-bg: rgba(15, 23, 42, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        /* FULL SCREEN FIX: Remove all margins and scrollbars */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: #000;
            overflow: hidden; /* Critical for full screen */
            font-family: 'Inter', sans-serif;
        }

        /* PLAYER FIX: Force player to occupy exact viewport size */
        #player {
            position: fixed !important;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 10;
        }

        /* Ensure JW Player internals stretch */
        .jwplayer {
            width: 100% !important;
            height: 100% !important;
        }

        /* Ambient Background Glow (Visible if video has black bars) */
        .ambient-glow {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, #1e1b4b 0%, #000000 100%);
            z-index: -1;
        }

        /* Custom UI Overlay (Glassmorphism) */
        #ui-layer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1001;
            display: none; /* Hidden by default */
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #ui-layer.visible {
            display: flex;
            opacity: 1;
        }

        /* Error Message Box */
        .message-box {
            background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
            border: 1px solid var(--glass-border);
            padding: 2.5rem;
            border-radius: 24px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            color: #f8fafc;
            animation: float 6s ease-in-out infinite;
        }

        .icon-warn {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        h2 {
            margin: 0 0 0.5rem 0;
            font-weight: 700;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 2rem;
        }

        .btn-retry {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            padding: 12px 32px;
            color: white;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px var(--primary-color);
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>

    <div class="ambient-glow"></div>

    <div id="player"></div>

    <div id="ui-layer">
        <div class="message-box">
            <span class="icon-warn">⚠️</span>
            <h2 id="msg-title">Stream Interrupted</h2>
            <p id="msg-desc">We are unable to fetch the video stream.</p>
            <button class="btn-retry" onclick="window.location.reload()">Retry Stream</button>
        </div>
    </div>

    <script src="https://cdn.jwplayer.com/libraries/SAHhwvZq.js"></script>
    <script>
        /**
         * App Logic - Premium & Full Screen
         */
        const App = {
            config: {
                jwKey: 'XSuP4qMl+9tK17QNb+4+th2Pm9AWgMO/cYH8CI0HGGr7bdjo', // Replace with your key
                brandingLogo: 'https://upload.wikimedia.org/wikipedia/commons/1/11/Jio_Logo.png',
                defaultTitle: 'JioTV Exclusive',
            },

            init: function() {
                jwplayer.key = this.config.jwKey;
                const params = this.getParams();

                if (!params.url) {
                    this.showError("Missing Source", "No stream URL was provided in the parameters.");
                    return;
                }

                this.patchXHR(params.token);
                this.setupPlayer(params);
            },

            getParams: function() {
                const urlParams = new URLSearchParams(window.location.search);
                let streamUrl = decodeURIComponent(urlParams.get('url') || '');
                const token = urlParams.get('token') || '';
                const drm = urlParams.get('drm') || '';

                if (streamUrl && token && !streamUrl.includes('__hdnea__')) {
                    streamUrl += (streamUrl.includes('?') ? '&' : '?') + token;
                }

                return {
                    url: streamUrl,
                    token: token,
                    drm: this.parseDRM(drm)
                };
            },

            parseDRM: function(drmString) {
                if (!drmString || !drmString.includes(':')) return {};
                const [kid, key] = drmString.split(':');
                return {
                    clearkey: {
                        keyId: kid.trim().toLowerCase(),
                        key: key.trim().toLowerCase()
                    }
                };
            },

            patchXHR: function(token) {
                if (!token) return;
                const origOpen = XMLHttpRequest.prototype.open;
                XMLHttpRequest.prototype.open = function(method, url) {
                    if (typeof url === 'string' && url.startsWith('http') && !url.includes('__hdnea__')) {
                         const newUrl = new URL(url);
                         const tokenKey = token.split('=')[0];
                         if (!newUrl.searchParams.has(tokenKey)) {
                            url += (url.includes('?') ? '&' : '?') + token;
                         }
                    }
                    return origOpen.apply(this, arguments);
                };
            },

            setupPlayer: function(params) {
                const player = jwplayer("player");

                player.setup({
                    playlist: [{
                        title: this.config.defaultTitle,
                        file: params.url,
                        drm: params.drm,
                        image: "https://wallpaperaccess.com/full/825832.jpg"
                    }],
                    width: "100%",
                    height: "100%",
                    autostart: true,
                    mute: false,
                    
                    // STRETCHING SETTINGS:
                    // "uniform" = Best quality, fits screen, might have black bars
                    // "exactfit" = Stretches video to fill screen (distorts image)
                    // "fill" = Zooms to fill screen (crops edges)
                    stretching: "uniform", 
                    
                    logo: {
                        file: this.config.brandingLogo,
                        position: "top-right",
                        margin: "20"
                    }
                });

                player.on("error", (e) => {
                    console.error("JW Error:", e);
                    this.showError("Playback Error", "The stream connection was lost or refused.");
                });
            },

            showError: function(title, desc) {
                const layer = document.getElementById('ui-layer');
                document.getElementById('msg-title').innerText = title;
                document.getElementById('msg-desc').innerText = desc;
                layer.classList.add('visible');
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            App.init();
        });

        // Prevent Right Click for "App-like" feel
        document.addEventListener('contextmenu', event => event.preventDefault());

    </script>
</body>
</html>