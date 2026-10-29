<?php
// Copyright 2021-2026 SnehTV, Inc.
// Licensed under MIT (https://github.com/mitthu786/TS-JioTV/blob/main/LICENSE)
// Created By: TechieSneh

error_reporting(0);
include "functions.php";

// Set response header to plain text
header("Content-Type: text/plain; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// 1. Get channel ID parameter
$id = htmlspecialchars($_REQUEST['id'] ?? '');

if (empty($id)) {
    http_response_code(400);
    exit("Missing required 'id' parameter.");
}

// 2. Fetch stream data from Jio TV API
$haystack = getJioTvData($id);

if (!isset($haystack->code) || $haystack->code !== 200) {
    refresh_token();
    header("Location: {$_SERVER['REQUEST_URI']}");
    exit;
}

// 3. Process URL & Cookie extraction
[$baseUrl, $query] = explode('?', $haystack->result) + ['', ''];
$cookies_y = str_contains($query, "minrate=") ? explode("&", $query)[2] : $query;
$cook = bin2hex($cookies_y);
$chs = explode('/', $baseUrl);

$headers_1 = ["User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7"];

if (str_contains($query, "bpk-tv")) {

    // Fetch master playlist
    $masterContent = cUrlGetData($haystack->result, $headers_1);

    // Extract sub-playlist file path
    $lines = explode("\n", $masterContent);
    $targetVariant = "";

    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && $line[0] !== '#' && str_contains($line, '.m3u8')) {
            $targetVariant = $line;
            break;
        }
    }

    $channelFolder = $chs[4] ?? $chs[0];
    if (!empty($targetVariant)) {
        if (str_starts_with($targetVariant, 'http')) {
            $innerUrl = $targetVariant;
        } else {
            $innerUrl = "https://jiotvmblive.cdn.jio.com/bpk-tv/$channelFolder/Fallback/$targetVariant";
        }
    } else {
        $innerUrl = "https://jiotvmblive.cdn.jio.com/bpk-tv/$channelFolder/Fallback/$channelFolder-video=148000.m3u8";
    }

    $cookieDecoded = hex2bin($cook);
    $streamHeaders = [
        'Cookie: ' . $cookieDecoded,
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7'
    ];

    // Fetch refreshed cookie string from CDN endpoint
    $cuk = get_and_refresh_cookie($innerUrl, $streamHeaders);
    if (empty($cuk)) {
        $cuk = $cook;
    }

    // Output ONLY the raw token string (e.g., __hdnea__=st=...~exp=...~hmac=...)
    $tokenString = hex2bin($cuk);
    echo trim($tokenString);

} else {
    // Fallback: decode and print the initial token query
    echo trim(hex2bin($cook));
}