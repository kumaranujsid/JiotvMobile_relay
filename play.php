<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <title>JIOTV+ | Stream Player</title>

  <!-- External Frameworks -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Google Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

  <!-- Shaka Player CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.6.0/shaka-player.ui.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.6.0/controls.min.css" />

  <style>
    /* ============ FIX 1: No forced transforms / no !important positioning wars ============ */
    html, body {
      width: 100%;
      height: 100%;
      margin: 0;
      padding: 0;
      background-color: #000 !important;
      overflow: hidden;
    }

    /* Shaka container: fill the page, black background. NO position:absolute !important,
       NO translateZ(0) on the video — those break fullscreen compositing (black screen). */
    .shaka-video-container {
      width: 100%;
      height: 100%;
      background-color: #000;
    }

    /* Video fills the container; object-fit handles letterboxing. Shaka manages positioning. */
    .shaka-video-container video {
      width: 100%;
      height: 100%;
      object-fit: contain;
      position: relative;   /* Shaka's own default; don't force absolute */
      max-width: none;
      max-height: none;
      transform: none;      /* critical: kills the GPU-surface black-screen bug */
    }

    /* ============ FIX 2: Explicit fullscreen rules ============ */
    /* Shaka hard-codes the fullscreen element to .shaka-video-container (#controls).
       Each prefixed pseudo-class must be a separate rule (browsers drop unknown ones). */
    .shaka-video-container:fullscreen,
    .shaka-video-container:-webkit-full-screen,
    .shaka-video-container:-moz-full-screen,
    .shaka-video-container:-ms-fullscreen {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      max-width: 100vw !important;
      max-height: 100vh !important;
      background-color: #000 !important;
      transform: none !important;
      backface-visibility: visible !important;
      -webkit-backface-visibility: visible !important;
    }

    /* Force the video to re-render on top in the top-layer fullscreen context. */
    .shaka-video-container:fullscreen video,
    .shaka-video-container:-webkit-full-screen video,
    .shaka-video-container:-moz-full-screen video,
    .shaka-video-container:-ms-fullscreen video {
      width: 100% !important;
      height: 100% !important;
      max-width: none !important;
      max-height: none !important;
      object-fit: contain;
      transform: none !important;
      backface-visibility: visible !important;
      -webkit-backface-visibility: visible !important;
    }

    /* Native browser fullscreen background override */
    :-webkit-full-screen { background-color: #000 !important; }
    :fullscreen { background-color: #000 !important; }

    /* GPU-surface safety: only ever apply these OUTSIDE fullscreen, never inside it. */
    video {
      transform: translateZ(0);
      backface-visibility: hidden;
      -webkit-backface-visibility: hidden;
    }
    .shaka-video-container:fullscreen video,
    .shaka-video-container:-webkit-full-screen video,
    .shaka-video-container:-moz-full-screen video,
    .shaka-video-container:-ms-fullscreen video {
      transform: none !important; /* overrides the hack above once fullscreen */
      backface-visibility: visible !important;
      -webkit-backface-visibility: visible !important;
    }
  </style>
</head>
<body class="bg-black text-white m-0 p-0 w-screen h-screen font-sans">

<div id="video-container" class="relative w-full h-full flex items-center justify-center bg-black overflow-hidden">

  <!-- Loading Screen -->
  <div id="loader" class="absolute inset-0 bg-black/90 z-[2000] flex flex-col items-center justify-center transition-opacity duration-300">
    <div class="text-7xl animate-spin text-cyan-400 drop-shadow-[0_0_25px_#00d2ff]">❄️</div>
    <div class="loading-text mt-4 text-sm tracking-widest text-gray-300 uppercase animate-pulse">LOADING STREAM...</div>
  </div>

  <!-- Unmute Button -->
  <button id="unmute-overlay" class="hidden absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[1500] btn btn-danger rounded-pill px-4 py-2 font-bold shadow-lg animate-bounce">
    <span class="material-icons-round align-middle me-1">volume_off</span> TAP TO UNMUTE
  </button>

  <!-- ============ FIX 3: Video is now a DIRECT CHILD of the Shaka container ============
       Shaka's Overlay turns #controls into .shaka-video-container and uses IT as the
       fullscreen element. The video must live inside it. -->
  <div id="controls" class="absolute inset-0 z-10">
    <video id="video" class="w-full h-full object-contain" autoplay muted playsinline></video>
  </div>

  <!-- Channel Logo Panel -->
  <div id="channel-logo" class="hidden absolute top-4 right-4 z-[1000] bg-dark/70 backdrop-blur-md border border-white/10 rounded-3 px-3 py-2 flex items-center justify-center">
    <img id="logo-img" class="h-8 max-w-[120px] object-contain" src="" alt="Channel Logo">
  </div>

  <!-- Secondary Player Switch -->
  <button id="jw-button" class="absolute top-4 left-4 z-[1000] btn btn-outline-light btn-sm backdrop-blur-md border-white/20 d-flex align-items-center gap-2 shadow">
    <span class="material-icons-round text-base">open_in_new</span>
    <span>Switch Player</span>
  </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const loader = document.getElementById('loader');
  const unmuteBtn = document.getElementById('unmute-overlay');

  function toggleLoader(show, text = "LOADING STREAM...") {
    if (show) {
      document.querySelector('.loading-text').textContent = text;
      loader.classList.remove('opacity-0', 'pointer-events-none');
      loader.classList.add('opacity-100', 'pointer-events-auto');
    } else {
      loader.classList.remove('opacity-100', 'pointer-events-auto');
      loader.classList.add('opacity-0', 'pointer-events-none');
    }
  }

  function showUnmuteButton() {
    unmuteBtn.classList.remove('hidden');
    unmuteBtn.classList.add('block');
  }

  function hideUnmuteButton() {
    unmuteBtn.classList.remove('block');
    unmuteBtn.classList.add('hidden');
  }

  function getQueryParam(param) {
    const params = new URLSearchParams(window.location.search);
    return params.get(param);
  }

  // Primary API Lookup
  async function fetchPrimaryChannelDetails(channelId) {
    try {
      const primaryApiUrl = 'https://api.npoint.io/5f75ba3d2bf31a764128';
      const res = await fetch(primaryApiUrl);
      if (!res.ok) return null;
      const channels = await res.json();

      const matched = channels.find(c => String(c.id) === String(channelId));
      if (matched) {
        const keyId = matched.keyid || matched.keyId || '';
        const key = matched.key || '';

        let drmObj = null;
        if (keyId && key) {
          drmObj = {};
          drmObj[keyId.trim().toLowerCase()] = key.trim().toLowerCase();
        }

        return {
          url: matched.url || matched.mpd || '',
          keyId: keyId,
          key: key,
          drm: drmObj,
          logo: matched.logo || ''
        };
      }
    } catch (e) {
      console.error("Primary API fetch error:", e);
    }
    return null;
  }

  // Token Retrieval (Fixed Endpoint id=143)
  async function fetchTokenFromEndpoint() {
    try {
      const res = await fetch(`app/get_token.php?id=143`);
      if (!res.ok) return '';
      const text = await res.text();
      return text.trim();
    } catch (e) {
      console.error("Token endpoint fetch error:", e);
      return '';
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    toggleLoader(true, "FETCHING CHANNEL DETAILS...");

    const requestedId = getQueryParam("id") || "143";

    // 1. Load Stream Specs
    const primaryData = await fetchPrimaryChannelDetails(requestedId);
    if (!primaryData || !primaryData.url) {
      alert(`Channel ID ${requestedId} not found.`);
      toggleLoader(false);
      return;
    }

    if (primaryData.logo) {
      document.getElementById("logo-img").src = primaryData.logo;
      document.getElementById("channel-logo").classList.remove('hidden');
      document.getElementById("channel-logo").classList.add('flex');
    }

    // 2. Fetch Security Token
    toggleLoader(true, "FETCHING SECURITY TOKEN...");
    const token = await fetchTokenFromEndpoint();

    const videoData = {
      url: primaryData.url,
      token: token,
      drm: primaryData.drm,
      ua: navigator.userAgent,
      referer: "https://watch.tataplay.com/"
    };

    localStorage.setItem("__hdnea__", JSON.stringify(videoData));

    document.getElementById("jw-button").onclick = () => {
      const p = encodeURIComponent;
      const combinedDrmStr = (primaryData.keyId && primaryData.key) ? `${primaryData.keyId}:${primaryData.key}` : '';
      window.location.href = `jwplayer.php?url=${p(videoData.url)}&token=${p(videoData.token)}&drm=${p(combinedDrmStr)}`;
    };

    shaka.polyfill.installAll();

    if (!shaka.Player.isBrowserSupported()) {
      alert("Browser not supported");
      return;
    }

    // ============ FIX 4: Overlay gets the WRAPPER that contains the video ============
    const video = document.getElementById("video");
    const uiContainer = document.getElementById("controls"); // now the parent of <video>
    const player = new shaka.Player(video);
    const ui = new shaka.ui.Overlay(player, uiContainer, video);

    ui.configure({
      controlPanelElements: ["play_pause", "time_and_duration", "spacer", "mute", "volume", "quality", "fullscreen", "picture_in_picture"]
    });

    const playerConfig = {
      streaming: {
        bufferingGoal: 30,
        rebufferingGoal: 5,
        retryParameters: { timeout: 15000, maxAttempts: 3, baseDelay: 500, backoffFactor: 1.5 }
      }
    };

    if (videoData.drm) {
      // ============ FIX 5: ClearKey does NOT support SW_SECURE_DECODE ============
      // Setting it makes Chromium reject the license or render black frames.
      // Default robustness is correct for ClearKey; only Widevine/PlayReady use
      // SW_SECURE_DECODE. If you ever switch to Widevine, re-add the advanced block.
      playerConfig.drm = {
        clearKeys: videoData.drm
        /*
        advanced: {
          'audio/mp4': { robustness: 'SW_SECURE_CRYPTO' },
          'video/mp4': { robustness: 'SW_SECURE_CRYPTO' }
        }
        */
      };
    }

    player.configure(playerConfig);

    player.getNetworkingEngine().registerRequestFilter((type, request) => {
      if (type === shaka.net.NetworkingEngine.RequestType.SEGMENT || type === shaka.net.NetworkingEngine.RequestType.MANIFEST) {
        request.headers["Referer"] = videoData.referer;
        request.headers["User-Agent"] = videoData.ua;
        if (videoData.token) {
          request.headers["Cookie"] = videoData.token;
          if (!request.uris[0].includes(videoData.token)) {
            request.uris[0] += (request.uris[0].includes("?") ? "&" : "?") + videoData.token;
          }
        }
      }
    });

    player.addEventListener('buffering', (event) => {
      toggleLoader(event.buffering, "BUFFERING...");
    });

    // Force size refresh so the compositor re-lays out after any layout change
    const handleResize = () => {
      window.requestAnimationFrame(() => {
        video.style.width = '100%';
        video.style.height = '100%';
      });
    };
    window.addEventListener("resize", handleResize);
    window.addEventListener("orientationchange", handleResize);

    // ============ FIX 6: Re-render on fullscreen enter/exit ============
    // This is what actually clears the stale black compositor layer.
    ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(evt => {
      document.addEventListener(evt, () => {
        handleResize();
        setTimeout(handleResize, 50); // let the browser finish the top-layer swap first
        setTimeout(handleResize, 300);
      });
    });

    try {
      toggleLoader(true, "LOADING STREAM...");
      await player.load(videoData.url);
      toggleLoader(false);

      const playPromise = video.play();
      if (playPromise !== undefined) {
        playPromise.then(() => {
          if (video.muted) showUnmuteButton();
        }).catch(() => {
          showUnmuteButton();
        });
      }
    } catch (error) {
      console.error("Shaka error:", error);
      toggleLoader(false);
    }

    unmuteBtn.addEventListener('click', () => {
      video.muted = false;
      video.play();
      hideUnmuteButton();
    });

    video.addEventListener('volumechange', () => {
      if (!video.muted) hideUnmuteButton();
    });
  });
</script>

</body>
</html>