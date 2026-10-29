<?php
// * Copyright 2021-2025 SnehTV, Inc.
// * Licensed under MIT (https://github.com/mitthu786/TS-JioTV/blob/main/LICENSE)
// * Created By : TechieSneh

// Load configuration - Path adjusted for better local/host compatibility
$config = @parse_ini_file(__DIR__ . '/../config.ini', true);
$PROXY = $config['settings']['proxy'] ?? null;

// --- FIX 1: ABSOLUTE DATA PATHS ---
// Using __DIR__ ensures assets are found whether called from root or /app folder
define('DATA_FOLDER', __DIR__ . '/assets/data');
define('TOKEN_EXPIRY_TIME', 7000);
define('COOKIE_EXPIRY_TIME', 40000);

// Determine protocol
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';

// --- FIX 2: SIMPLIFIED HOST DETECTION ---
// $_SERVER['HTTP_HOST'] is the most reliable way to get 'localhost' or '127.0.0.1'
$host_jio = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Build Jio path
$jio_path = $protocol . $host_jio . str_replace(' ', '%20', str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']));

// Check if server is Apache compatible
function isApache(): bool
{
  $software = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
  // Added common Windows/XAMPP strings to compatibility list
  $compatibleServers = ['apache', 'litespeed', 'openlitespeed', 'win32', 'win64'];

  foreach ($compatibleServers as $server) {
    if (strpos($software, strtolower($server)) !== false) {
      return true;
    }
  }
  return false;
}

// Refresh token logic (Kept Same)
function refresh_token()
{
  $filePath = DATA_FOLDER . '/creds.jtv';
  $TokenNeedsRefresh = !file_exists($filePath) || (time() - filemtime($filePath) > TOKEN_EXPIRY_TIME);

  if ($TokenNeedsRefresh) {
    return cUrlGetData($GLOBALS['jio_path'] . '/login/refreshLogin.php');
  }
  return null;
}

// Cookie Refresh logic (Kept Same)
function get_and_refresh_cookie($url, $headers)
{
  $filePath = DATA_FOLDER . '/cookie.jtv';
  $cookieNeedsRefresh = !file_exists($filePath) || (time() - filemtime($filePath) > COOKIE_EXPIRY_TIME);

  if ($cookieNeedsRefresh) {
    $cookies = getCookiesFromUrl($url, $headers);
    if (isset($cookies['__hdnea__'])) {
      $cooKee = bin2hex('__hdnea__=' . $cookies['__hdnea__']);
      file_put_contents($filePath, $cooKee);
    } else {
      throw new Exception("Cookie '__hdnea__' not found in response.");
    }
  } else {
    $cooKee = @file_get_contents($filePath);
  }
  return $cooKee;
}

// Generate Jio headers (Kept Same)
function jio_headers($cookies, $access_token, $crm, $device_id, $ssoToken, $uniqueId)
{
  return [
    "Cookie: $cookies",
    "accesstoken: $access_token",
    "appkey: NzNiMDhlYcQyNjJm",
    "channel_id: 144",
    "crmid: $crm",
    "deviceId: $device_id",
    "devicetype: phone",
    "isott: true",
    "languageId: 6",
    "lbcookie: 1",
    "os: android",
    "osVersion: 14",
    "srno: 240707144000",
    "ssotoken: $ssoToken",
    "subscriberId: $crm",
    "uniqueId: $uniqueId",
    "User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7",
    "usergroup: tvYR7NSNn7rymo3F",
    "versionCode: 353",
    "Origin: https://www.jiocinema.com",
    "Referer: https://www.jiocinema.com/",
  ];
}

// --- FIX 3: LOCALHOST cURL SUPPORT ---
function cUrlGetData($url, $headers = null, $post_fields = null)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Higher timeout for local
  
  // BYPASS SSL VERIFICATION (CRITICAL FOR LOCALHOST)
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

  if (!empty($post_fields)) {
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
  }

  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }

  $data = curl_exec($ch);

  if (curl_errno($ch)) {
    // Hidden on production (0), visible on localhost error_reporting
    error_log('Error:' . curl_error($ch));
  }

  curl_close($ch);
  return $data;
}

// Helper functions (Kept Same)
function getCRED()
{
  $filePath = DATA_FOLDER . '/creds.jtv';
  $keyPath = DATA_FOLDER . '/credskey.jtv';
  if(!file_exists($filePath) || !file_exists($keyPath)) return false;
  
  $key_data = file_get_contents($keyPath);
  return decrypt_data(file_get_contents($filePath), $key_data);
}

function encrypt_data($data, $key)
{
  $key = (int) $key;
  $encrypted = array_map(fn($char) => chr(ord($char) + $key), str_split($data));
  return base64_encode(implode('', $encrypted));
}

function decrypt_data($e_data, $key)
{
  $key = (int) $key;
  $encrypted = base64_decode($e_data);
  $decrypted = array_map(fn($char) => chr(ord($char) - $key), str_split($encrypted));
  return implode('', $decrypted);
}

function getCookiesFromUrl($url, $headers = [], $post_fields = null)
{
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false, // Fix for local cookie fetch
    CURLOPT_SSL_VERIFYHOST => false
  ]);

  if ($post_fields !== null) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
  }

  $response = curl_exec($ch);
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $header = substr($response, 0, $header_size);
  curl_close($ch);

  return extractCookies($header);
}

function extractCookies($header)
{
  $cookies = [];
  foreach (explode("\r\n", $header) as $line) {
    if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $line, $matches)) {
      parse_str($matches[1], $cookie);
      $cookies = array_merge($cookies, $cookie);
    }
  }
  return $cookies;
}

function getUserData()
{
  $filePath = DATA_FOLDER . "/creds.jtv";
  $keyPath = DATA_FOLDER . "/credskey.jtv";
  
  if(!file_exists($filePath)) return null;
  
  $key_data = file_get_contents($keyPath);
  $cred = decrypt_data(file_get_contents($filePath), $key_data);
  $jio_cred = json_decode($cred, true) ?? [];

  if(!isset($jio_cred['sessionAttributes']['user'])) return null;

  $name = $jio_cred['sessionAttributes']['user']['commonName'];
  $mobile = $jio_cred['sessionAttributes']['user']['mobile'];
  $jwt = $jio_cred['authToken'];
  $parts = explode('.', $jwt);
  $payload = base64_decode($parts[1]);
  $expiry_time = json_decode($payload, true)['exp'];

  $date = new DateTime();
  $date->setTimestamp($expiry_time);
  $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
  $exp_date_time = $date->format('d-M-Y h:i:s A');

  return [
    'name' => $name,
    'mobile' => $mobile,
    'exp_date_time' => $exp_date_time
  ];
}

function getJioTvData($id)
{
  $cred = getCRED();
  if(!$cred) return null;
  
  $jio_cred = json_decode($cred, true) ?? [];
  extract($jio_cred['sessionAttributes']['user'] ?? []);

  $access_token = $jio_cred['authToken'] ?? '';
  $crm = $subscriberId ?? '';
  $uniqueId = $unique ?? '';
  $device_id = $jio_cred['deviceId'] ?? '';

  $post_data = http_build_query(['stream_type' => 'Seek', 'channel_id' => $id]);

  $headers = [
    "Host: jiotvapi.media.jio.com",
    "Content-Type: application/x-www-form-urlencoded",
    "appkey: NzNiMDhlYzQyNjJm",
    "channel_id: $id",
    "userid: $crm",
    "crmid: $crm",
    "deviceId: $device_id",
    "devicetype: phone",
    "isott: true",
    "languageId: 6",
    "lbcookie: 1",
    "os: android",
    "dm: Xiaomi 22101316UP",
    "osversion: 14",
    "srno: 240303144000",
    "accesstoken: $access_token",
    "subscriberid: $crm",
    "uniqueId: $uniqueId",
    "content-length: " . strlen($post_data),
    "usergroup: tvYR7NSNn7rymo3F",
    "User-Agent: okhttp/4.9.3",
    "versionCode: 353",
  ];

  $response = cUrlGetData("https://jiotvapi.media.jio.com/playback/apis/v1/geturl?langId=6", $headers, $post_data);
  return json_decode($response);
}