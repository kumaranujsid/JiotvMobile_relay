<?php
/**
 * Simple PHP Streaming Proxy
 * Supports forwarding GET, POST, PUT, DELETE, cookies, and large media streams (.m3u8, .mpd).
 */

// 1. Optional: Access Control by Client IP (Uncomment to enforce)
/*
$allowed_ips = [
    '123.45.67.89', // Add allowed client IPs here
];

if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    die('Forbidden: Access denied.');
}
*/

// Disable execution time limit for long video streams
set_time_limit(0);

// 2. Retrieve & Validate Target URL
$target_url = isset($_GET['url']) ? $_GET['url'] : null;

if (!$target_url || !filter_var($target_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die('Error: Missing or invalid target URL parameter (?url=https://example.com)');
}

// 3. Initialize cURL Session
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

// Forward HTTP Method (GET, POST, PUT, DELETE, etc.)
$request_method = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_method);

// Forward Request Payload (for POST/PUT/PATCH requests)
if (in_array($request_method, ['POST', 'PUT', 'PATCH'])) {
    $input_data = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input_data);
}

// Forward Request Headers (including User-Agent and incoming Cookies)
$request_headers = [];
foreach (getallheaders() as $key => $value) {
    // Exclude Host header to avoid routing conflicts on target server
    if (strcasecmp($key, 'Host') !== 0) {
        $request_headers[] = "$key: $value";
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

// 4. Handle Response Headers & Status Code
$headers_sent = false;

curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header_line) use (&$headers_sent) {
    // Send HTTP response status code once received
    if (!$headers_sent) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code) {
            http_response_code($http_code);
            $headers_sent = true;
        }
    }

    // Forward response headers back to client (skip encodings to avoid compression bugs)
    if (!preg_match('/^(Transfer-Encoding|Content-Encoding):/i', $header_line)) {
        header($header_line, false);
    }

    return strlen($header_line);
});

// 5. Stream Body Data in Chunks (Ideal for MPD, M3U8, and Video Segments)
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
    echo $chunk;
    // Flush output buffer immediately so client video player gets data without buffering delay
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
    return strlen($chunk);
});

// Execute the request
$success = curl_exec($ch);

if (!$success) {
    if (!headers_sent()) {
        http_response_code(502);
    }
    echo 'Proxy Error: ' . curl_error($ch);
}

curl_close($ch);