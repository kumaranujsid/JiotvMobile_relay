<?php

// Copyright 2021-2025 SnehTV, Inc.
// Licensed under MIT (https://github.com/mitthu786/TS-JioTV/blob/main/LICENSE)
// Created By : TechieSneh

error_reporting(0);
include "functions.php";

$data = null;
$hex = '';

// Determine data source
if (isset($_GET['data'])) {
    $hex = $_GET['data'];
} else {
    $parts = explode('_', $_SERVER['REQUEST_URI']);
    if (isset($parts[1])) {
        $hex = $parts[1];
    }
}

// Decode and parse data
if (!empty($hex)) {
    $decoded = hex2bin($hex);
    $data = explode('=?=', $decoded);
}

$cid = $data[0] ?? '';
$id = $data[1] ?? '';
$name = str_replace('_', ' ', $cid);

// Set live URL
$live_url = (isApache())
    ? "ts_live_$id.m3u8"
    : "live.php?id=$id&e=.m3u8";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $name; ?> | JioTV +</title>
    
    <meta name="theme-color" content="#000000">
    <script type="text/javascript" src="https://content.jwplatform.com/libraries/IDzF9Zmk.js"></script>

    <style>
        :root {
            --brand-color: #E50914;
        }

        /* --- 1. GLOBAL RESET (Fixes Spacing Issues) --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            width: 100%;
            height: 100%; /* Fallback for old browsers */
            background-color: #000;
            overflow: hidden; /* Prevents scrolling */
        }

        /* --- 2. CONTAINER FIX (The "Width & High" Fix) --- */
        #player-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;  /* 100% of Viewport Width */
            height: 100vh; /* 100% of Viewport Height */
            height: 100dvh; /* Dynamic Height: Fixes mobile address bar issues */
            display: flex;
            justify-content: center;
            align-items: center;
            background: #000;
            z-index: 1;
        }

        /* Ensure the player div inside takes full space */
        #jwplayerDiv {
            width: 100% !important;
            height: 100% !important;
        }

        /* --- 3. LOADING ANIMATION (Kept your requested features) --- */
        #smart-loader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 999;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        #smart-loader.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .loader-ring {
            width: 55px;
            height: 55px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid var(--brand-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.4);
        }

        .loader-text {
            margin-top: 15px;
            color: #fff;
            font-family: sans-serif;
            font-size: 12px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            opacity: 0.8;
            animation: pulse 1.5s infinite;
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }
        @keyframes pulse { 50% { opacity: 0.4; } }

        /* --- 4. HEADER OVERLAY --- */
        .premium-header {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            padding: 20px 25px;
            background: linear-gradient(to bottom, rgba(0,0,0,0.9), transparent);
            z-index: 100;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }

        #player-container:hover .premium-header { opacity: 1; }

        .channel-title {
            color: white;
            font-family: sans-serif;
            font-size: 16px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
        }

        .jw-icon-buffer { display: none !important; }
    </style>
</head>
<body oncontextmenu="return false;">

    <div id="player-container">
        
        <div class="premium-header">
            <div class="channel-title"><?= $name ?></div>
        </div>

        <div id="smart-loader">
            <div class="loader-ring"></div>
            <div class="loader-text" id="status-text">CONNECTING</div>
        </div>

        <div id="jwplayerDiv"></div>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', () => {
            const loader = document.getElementById('smart-loader');
            const statusText = document.getElementById('status-text');

            function hideLoader() { loader.classList.add('hidden'); }
            function showLoader(txt) { 
                statusText.innerText = txt; 
                loader.classList.remove('hidden'); 
            }

            const player = jwplayer("jwplayerDiv").setup({
                file: "<?= $live_url ?>",
                type: "hls",
                title: "<?= $name ?>",
                image: "https://jiotv.catchup.cdn.jio.com/dare_images/images/<?= $cid ?>.png",
                
                // --- WIDTH & HEIGHT CONFIG ---
                width: "100%",
                height: "100%",
                stretching: "uniform", // Options: 'uniform' (fit), 'exactfit' (stretch), 'fill' (zoom)
                
                autostart: true,
                mute: false,
                controls: true
            });

            // Logic
            player.on('ready', () => showLoader("READY"));
            player.on('play', () => hideLoader());
            player.on('buffer', () => showLoader("BUFFERING"));
            player.on('pause', () => hideLoader()); // Keep video visible on pause
            player.on('error', () => {
                showLoader("RETRYING...");
                setTimeout(() => {
                    player.load({ file: "https://snehtv.vercel.app/video/tsjiotv.m3u8" });
                }, 1000);
            });
        });
    </script>
</body>
</html>