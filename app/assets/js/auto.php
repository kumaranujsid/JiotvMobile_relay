<?php
header('Content-Type: application/json');

// Step 1: Decrypt the M3U source URL
$encrypted_url_b64 = 'dUFCVWdLY3NwTm1DWTNNcjAwclIzbnhOTXNsN1pzbWJtU0tWV2oyQW9GRjYyeS9tUUhlbkJMemRFeGdSM0dVb2U0RlcwM2dLaEQ4MTBGY081WG0wQjhnNDRMUnpMMHNEallMNHlNYUkvWjQ9';
$key_b64 = 'imtz42EsRU07M8x5gYpy2PFaydeY+78/vtGrdmh2oKM=';
$iv_b64 = 'hM5tMvUPnj2EqYGIxInnEA==';

// Decode from Base64
$ciphertext = base64_decode($encrypted_url_b64);
$key = base64_decode($key_b64);
$iv = base64_decode($iv_b64);

// Decrypt using AES-256-CBC to get the source URL
// The decrypted URL, if successful, would be the M3U source.
$url = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);

if ($url === false) {
    echo json_encode(['error' => 'Failed to decrypt the source URL']);
    exit;
}

$result = [];
// Attempt to fetch M3U content
$data = @file_get_contents($url);

if ($data === false) {
    // Note: In a real-world scenario, you might see "Failed to open stream: HTTP request failed!" 
    // if the decrypted URL is invalid or inaccessible.
    echo json_encode(['error' => 'Failed to fetch M3U from the decrypted URL']);
    exit;
}

$lines = explode("\n", $data);

// IMPROVEMENT: Validate that this is actually an M3U file.
if (empty($lines) || trim($lines[0]) !== '#EXTM3U') {
    echo json_encode(['error' => 'Invalid M3U file: Missing #EXTM3U header.']);
    exit;
}

$current = [];

foreach ($lines as $line) {
    $line = trim($line);

    if (empty($line)) {
        continue; // Skip empty lines
    }

    if (strpos($line, '#EXTINF:') === 0) {
        // FIX: Before starting a new entry, save the previous one if it's complete.
        if (!empty($current) && isset($current['url'])) {
            $result[] = $current;
        }

        // Start a new channel entry
        $current = [];
        $current['title'] = explode(',', $line, 2)[1] ?? 'Unknown';
        
        // Use regex to extract attributes
        preg_match('/tvg-logo="([^"]+)"/', $line, $logo);
        preg_match('/group-title="([^"]+)"/', $line, $group);
        preg_match('/tvg-id="([^"]+)"/', $line, $id);
        
        // Assign attributes, defaulting to empty string if not found
        $current['logo'] = $logo[1] ?? '';
        $current['group'] = $group[1] ?? '';
        $current['id'] = $id[1] ?? '';
        
    } elseif (strpos($line, '#KODIPROP:inputstream.adaptive.license_key=') === 0) {
        $current['license_key'] = trim(explode('=', $line, 2)[1] ?? '');
    } elseif (strpos($line, '#KODIPROP:inputstream.adaptive.license_type=') === 0) {
        $current['license_type'] = trim(explode('=', $line, 2)[1] ?? '');
    } elseif (strpos($line, '#EXTVLCOPT:http-user-agent=') === 0) {
        $current['user_agent'] = trim(explode('=', $line, 2)[1] ?? '');
    } elseif (strpos($line, '#EXTHTTP:') === 0) {
        $json = json_decode(substr($line, 9), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $current['headers'] = $json;
        }
    } elseif (filter_var($line, FILTER_VALIDATE_URL)) {
        // Only add the URL to the current entry, don't save the whole entry yet.
        $current['url'] = $line;
    }
}

// FIX: After the loop, save the very last channel entry if it's complete.
if (!empty($current) && isset($current['url'])) {
    $result[] = $current;
}

// === START OF REQUIRED FILTERING LOGIC ===
$filtered_result = array_filter($result, function($channel) {
    // Filter to only keep channels where 'group' is exactly 'JIOSTAR'
    return isset($channel['group']) && $channel['group'] === 'JIOSTAR';
});

// Use array_values to reset numeric keys after filtering
$final_output = array_values($filtered_result);
// === END OF REQUIRED FILTERING LOGIC ===


// Step 2: Output final JSON
echo json_encode($final_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);