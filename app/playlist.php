<?php
// CRITICAL: Ensure there are NO blank lines or characters before this tag.

// ==================================================================
// == 1. HEADERS, GEOLOCATION & PARAMETER CHECK
// ==================================================================
if (headers_sent()) {
    die('Error: Headers already sent.');
}

if (ob_get_length()) ob_end_clean();

// Check if 'id' parameter is completely missing or empty
if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Error: Missing required URL parameter 'id'. Example: playlist.php?id=Jiotv+ or playlist.php?id=Jiotv";
    exit;
}

// Set content headers for playlist output
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * IP Geolocation Helper
 * Detects if the requesting client IP is outside India.
 */
function is_outside_india() {
    // Get real client IP address
    $client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] 
              ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
              ?? $_SERVER['REMOTE_ADDR'] 
              ?? '';

    // Extract first IP if multiple exist in X-Forwarded-For
    if (strpos($client_ip, ',') !== false) {
        $ip_list = explode(',', $client_ip);
        $client_ip = trim($ip_list[0]);
    }

    // Ignore local/internal IPs
    if (in_array($client_ip, ['127.0.0.1', '::1']) || filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }

    // Free geolocation API to check Country Code
    $geo_url = "http://ip-api.com/json/" . $client_ip . "?fields=countryCode";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $geo_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['countryCode']) && $data['countryCode'] !== 'IN') {
            return true; // Request originates OUTSIDE India
        }
    }

    return false; // Within India or fallback default
}

// Check if proxy needs to be enabled
$use_proxy = is_outside_india();

// Read raw query string to catch unencoded '+' before PHP converts it to a space
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$rawMode = $_GET['id'];

// Custom User Agent
$customUA = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36';

// --- Detect User-Agent and set specific Header/Catchup strings ---
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isPC = (stripos($userAgent, 'Windows') !== false || stripos($userAgent, 'Macintosh') !== false || stripos($userAgent, 'vlc') !== false);

if (stripos($userAgent, 'tivimate') !== false) { 
    // Android App Format (TiviMate/Televizo)
    $headers = '|User-Agent="' . $customUA . '"&Origin="https://watch.tataplay.com"&Referer="https://watch.tataplay.com/"';
    $ctag = 'catchup-type="append" catchup-days="8" catchup-source="&begin={utc}&end={utcend}"';
} elseif ($isPC) { 
    // PC / VLC Format
    $headers = '&http-user-agent=' . urlencode($customUA) . '&http-referrer=' . urlencode('https://watch.tataplay.com/');
    $ctag = '';
} else { 
    // Default Generic Format
    $headers = '|User-Agent=' . $customUA . '&Origin=https://watch.tataplay.com&Referer=https://watch.tataplay.com/';
    $ctag = 'catchup-type="append" catchup-days="8" catchup-source="&begin={utc}&end={utcend}"';
}

/**
 * Robust fetch helper with cURL and stream fallback
 */
function fetch_url_with_curl($url) {
    $customUA = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, $customUA);
    $data = curl_exec($ch);
    $err  = curl_errno($ch);
    curl_close($ch);

    if ($err || empty($data)) {
        $opts = [
            'http' => [
                'method' => "GET",
                'header' => "User-Agent: " . $customUA . "\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];
        $context = stream_context_create($opts);
        $data = @file_get_contents($url, false, $context);
    }
    return $data;
}

// Global unique tracker
$seen_channels = [];

echo "#EXTM3U\r\n";

// Base URL detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$current_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$base_url = (basename($current_path) === 'app') ? $protocol . $host . $current_path : $protocol . $host . $current_path . '/app';
$proxy_prefix = $base_url . '/proxy.php?url=';

// --- ROUTING LOGIC ---
$hasPlusSignal = (
    strpos($queryString, '+') !== false || 
    strpos($queryString, '%2B') !== false || 
    strpos($queryString, '%2b') !== false || 
    strpos($rawMode, ' ') !== false
);

$runPrimary = ($rawMode === 'all' || $hasPlusSignal);
$runBackup  = ($rawMode === 'all' || (!$hasPlusSignal && stripos($rawMode, 'jiotv') !== false) || stripos($rawMode, 'backup') !== false);

// ==================================================================
// == 2. PRIMARY SOURCE (Triggered when id contains '+' or space)
// ==================================================================
if ($runPrimary) {
    // Fetch dynamic cookie token using fixed ID 143
    $token_url = $base_url . '/get_token.php?id=143';
    $cookie_data = fetch_url_with_curl($token_url);
    $cookie_string = trim($cookie_data ?: '');

    $json_url = 'https://api.npoint.io/5f75ba3d2bf31a764128';
    $json_data = fetch_url_with_curl($json_url);

    if ($json_data) {
        $channels = json_decode($json_data, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($channels)) {
            foreach ($channels as $channel) {
                $name       = $channel['name'] ?? $channel['link2'] ?? 'Unknown';
                $id         = $channel['id'] ?? '';
                $logo       = $channel['logo'] ?? $channel['img'] ?? '';
                $genre      = $channel['category'] ?? $channel['genre'] ?? 'News';
                
                // Fetch Stream URL directly from JSON
                $streamUrl  = $channel['url'] ?? '';

                // If user is outside India, route the stream URL through the proxy
                if ($use_proxy && !empty($streamUrl)) {
                    $streamUrl = $proxy_prefix . urlencode($streamUrl);
                }

                // Extract Key ID and Key from JSON
                $keyId = $channel['keyId'] ?? $channel['key_id'] ?? $channel['kid'] ?? '';
                $key   = $channel['key'] ?? $channel['license_key'] ?? $channel['license'] ?? '';

                // Combine keyId and key in ClearKey format (keyId:key)
                if (!empty($keyId) && !empty($key) && strpos($key, ':') === false) {
                    $licenseUrl = $keyId . ':' . $key;
                } else {
                    $licenseUrl = $key;
                }

                if (!empty($id) && stripos($name, 'Sony') === false && !isset($seen_channels[$name])) {
                    $seen_channels[$name] = true;

                    // #EXTINF Line
                    echo sprintf(
                        '#EXTINF:-1 tvg-id="%s" tvg-name="%s" tvg-logo="%s" group-title="Jiotv+ ♨️%s", %s',
                        htmlspecialchars($id),
                        htmlspecialchars($name),
                        htmlspecialchars($logo),
                        htmlspecialchars($genre),
                        htmlspecialchars($name)
                    ) . "\r\n";

                    // Kodi / DRM Properties dynamically pulled from JSON
                    echo "#KODIPROP:inputstream.adaptive.manifest_type=mpd\r\n";
                    echo "#KODIPROP:inputstream.adaptive.license_type=clearkey\r\n";
                    if (!empty($licenseUrl)) {
                        echo "#KODIPROP:inputstream.adaptive.license_key=" . $licenseUrl . "\r\n";
                    }
                    echo "#EXTVLCOPT:http-user-agent=" . $customUA . "\r\n";

                    // Include Cookie Tag if cookie fetched successfully
                    if (!empty($cookie_string)) {
                        echo '#EXTHTTP:{"cookie":"' . addslashes($cookie_string) . '"}' . "\r\n";
                    }

                    // Final Stream URL Output (Proxied if outside India)
                    echo $streamUrl . "\r\n";
                }
            }
        }
    }
}

// ==================================================================
// == 3. BACKUP SOURCE (Triggered when id is 'Jiotv' without '+')
// ==================================================================
if ($runBackup) {
    $backup_json_url = 'https://gist.githubusercontent.com/mitthu786/6719418610aa81997e72d6c27a6a2e39/raw/tsjiotvnew.json';
    $backup_data = fetch_url_with_curl($backup_json_url);

    if ($backup_data) {
        $backup_channels = json_decode($backup_data, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($backup_channels)) {
            foreach ($backup_channels as $item) {
                $id       = $item['id'] ?? '';
                $name     = $item['name'] ?? $item['link2'] ?? 'Unknown';
                $logo     = $item['img'] ?? $item['logo'] ?? '';
                $genre    = $item['genre'] ?? $item['category'] ?? 'General';
                $catchup  = $item['catchup'] ?? '';

                if (!empty($id) && !isset($seen_channels[$name])) {
                    $seen_channels[$name] = true;

                    // Append catchup tag if channel supports catchup
                    $catchup_str = (!empty($catchup) && !empty($ctag)) ? ' ' . $ctag : '';

                    // #EXTINF line output for backup channel
                    echo sprintf(
                        '#EXTINF:-1 tvg-id="%s" tvg-name="%s" tvg-logo="%s" group-title="%s"%s, %s',
                        htmlspecialchars($id),
                        htmlspecialchars($name),
                        htmlspecialchars($logo),
                        htmlspecialchars($genre),
                        $catchup_str,
                        htmlspecialchars($name)
                    ) . "\r\n";

                    // Construct live URL
                    $live_stream = $base_url . "/live.php?id=" . $id . $headers;

                    // If request is outside India, route live.php link through proxy.php
                    if ($use_proxy) {
                        $finalUrl = $proxy_prefix . urlencode($live_stream);
                    } else {
                        $finalUrl = $live_stream;
                    }

                    echo $finalUrl . "\r\n";
                }
            }
        }
    }
}
?>