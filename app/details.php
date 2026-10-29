<?php
// --- 1. ERROR REPORTING & AUTH CHECK ---
error_reporting(0);

$creds_file = __DIR__ . '/assets/data/credskey.jtv';
if (!file_exists($creds_file)) {
    $creds_file = 'assets/data/credskey.jtv';
}
$file_exists = file_exists($creds_file);

if (file_exists("functions.php")) {
    include "functions.php";
}

// --- 2. cURL HELPER FUNCTION ---
function get_api_data($url) {
    if (!function_exists('curl_init')) return false;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($http_code == 200) ? $output : false;
}

// --- 3. SERVER ROUTING PATHS (FOR BACKUP / CATCHUP) ---
if (function_exists('isApache') && isApache()) {
    $url_host = "/play_";
    $cp_url_host = "/catchup/cp_";
} else {
    $url_host = "/play.php?data=";
    $cp_url_host = "/catchup/cp.php?data=";
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$local_ip = getHostByName(php_uname('n'));
$host_jio = ($_SERVER['SERVER_ADDR'] !== "127.0.0.1" && $_SERVER['SERVER_ADDR'] !== 'localhost')
  ? $_SERVER['HTTP_HOST']
  : $local_ip . ($_SERVER['SERVER_PORT'] ? ':' . $_SERVER['SERVER_PORT'] : '');

$jio_path = $protocol . $host_jio . str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']);
$jio_path = rtrim($jio_path, '/');

// --- 4. PARAMETER PROCESSING ---
$channel_id       = $_GET['id'] ?? null;
$catchup          = $_GET['catchup'] ?? '0';
$channel_name     = "Unknown Channel";
$channel_logo     = "";
$channel_category = "General";
$play_url         = "#";
$cid_raw          = "";

// Check for Backup Hex Data
$hex_data = $_GET['data'] ?? null;
if (!$hex_data) {
    $uri_parts = explode('_', $_SERVER['REQUEST_URI']);
    if (isset($uri_parts[1]) && !empty($uri_parts[1])) {
        $hex_data = explode('?', $uri_parts[1])[0];
    }
}

if (!empty($hex_data)) {
    // === BACKUP CHANNEL LOGIC (.m3u8 Streams) ===
    $decoded = hex2bin($hex_data);
    $data_parts = explode('=?=', $decoded);
    
    $cid_raw      = $data_parts[0] ?? '';
    $channel_id   = $data_parts[1] ?? '';
    $catchup      = $data_parts[2] ?? '0';
    $channel_name = strtoupper(str_replace('_', ' ', $cid_raw));
    $channel_logo = 'https://jiotv.catchup.cdn.jio.com/dare_images/images/' . $cid_raw . '.png';
    
    $cid_hex  = bin2hex($cid_raw . '=?=' . $channel_id);
    $play_url = $jio_path . $url_host . $cid_hex;

} elseif ($channel_id) {
    // === PRIMARY CHANNEL LOGIC (DASH DRM) ===
    $primary_api_url = 'https://api.npoint.io/5f75ba3d2bf31a764128';
    $raw_json = get_api_data($primary_api_url);

    if ($raw_json) {
        $decoded = json_decode($raw_json, true);
        if ($decoded && is_array($decoded)) {
            foreach ($decoded as $ch) {
                if (isset($ch['id']) && (string)$ch['id'] === (string)$channel_id) {
                    $channel_name     = $ch['name'] ?? 'Unnamed Channel';
                    $channel_logo     = $ch['logo'] ?? '';
                    $channel_category = $ch['category'] ?? 'General';
                    $cid_raw          = str_replace(' ', '_', strtolower($channel_name));
                    break;
                }
            }
        }
    }
    
    // REDIRECT OUTSIDE APP FOLDER TO play.php FOR DASH STREAM
    $play_url = "../play.php?id=" . urlencode($channel_id) . "&catchup=" . urlencode($catchup);
}

date_default_timezone_set('Asia/Kolkata');
$currentDate = date('d M, Y');
$currentTime = date('h:i A');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($channel_name) ?> | JioTV+ Exclusive</title>
    <link rel="icon" href="https://ik.imagekit.io/techiesneh/tv_logo/jtv-plus_TMaGGk6N0.png" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://code.iconify.design/2/2.1.2/iconify.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        body {
            background-color: #050505;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(76, 29, 149, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 85% 30%, rgba(236, 72, 153, 0.15) 0%, transparent 50%);
            min-height: 100vh;
            color: #ffffff;
            overflow-x: hidden;
        }
        .glass-panel {
            background: rgba(25, 25, 35, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }
        .glass-header {
            background: rgba(5, 5, 5, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .catchup-card {
            background: linear-gradient(145deg, rgba(30,30,40,0.6) 0%, rgba(20,20,25,0.8) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        .catchup-card:hover {
            transform: translateY(-8px);
            border-color: rgba(139, 92, 246, 0.5);
            box-shadow: 0 15px 30px -10px rgba(139, 92, 246, 0.3);
        }
        .text-gradient {
            background: linear-gradient(to right, #c084fc, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .channel-logo {
            filter: drop-shadow(0 0 15px rgba(192, 132, 252, 0.3));
        }
    </style>
</head>

<body class="antialiased selection:bg-purple-500 selection:text-white">

    <header class="fixed w-full top-0 z-50 glass-header transition-all duration-300">
        <div class="container mx-auto px-4 lg:px-8 h-20 flex justify-between items-center">
            <div data-aos="fade-right" class="flex items-center gap-3">
                <img src="https://ik.imagekit.io/techiesneh/tv_logo/jtv-plus_TMaGGk6N0.png" alt="JioTV+" class="h-10 hover:brightness-125 transition-all">
                <div class="hidden md:block h-6 w-[1px] bg-gray-700"></div>
                <span class="hidden md:block text-sm font-light text-gray-400 tracking-widest uppercase">Premium</span>
            </div>

            <div id="userButtons" class="flex gap-3" data-aos="fade-left">
                <button onclick="window.location.href='../index.php'" class="group relative p-2.5 rounded-xl bg-gray-800/50 hover:bg-gray-700/50 text-gray-300 hover:text-white border border-gray-700 transition-all overflow-hidden" title="Home">
                    <span class="iconify text-xl relative z-10" data-icon="solar:home-smile-bold-duotone"></span>
                </button>
                <button onclick="location.reload()" class="group relative p-2.5 rounded-xl bg-gray-800/50 hover:bg-gray-700/50 text-gray-300 hover:text-white border border-gray-700 transition-all" title="Refresh">
                    <span class="iconify text-xl group-hover:rotate-180 transition-transform duration-500" data-icon="solar:restart-circle-bold-duotone"></span>
                </button>
            </div>
        </div>
    </header>

    <main class="container mx-auto pt-28 pb-12 px-4 lg:px-8 relative z-10">
        
        <!-- HEADER DETAILS PANEL -->
        <div class="glass-panel rounded-3xl p-8 mb-12 relative overflow-hidden" data-aos="zoom-in-up">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-32 h-32 bg-purple-600 rounded-full blur-[100px] opacity-20 pointer-events-none"></div>

            <div class="relative z-10 flex flex-col items-center text-center">
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
                    <img src="<?= htmlspecialchars($channel_logo) ?>" alt="<?= htmlspecialchars($channel_name) ?>" class="channel-logo relative w-28 h-28 md:w-36 md:h-36 rounded-xl shadow-2xl object-contain bg-[#1a1a1a] p-2">
                </div>

                <h1 class="mt-6 text-3xl md:text-5xl font-bold text-white tracking-tight">
                    <?= htmlspecialchars($channel_name) ?>
                </h1>
                <p class="text-pink-400 mt-2 text-sm font-light tracking-widest uppercase font-semibold"><?= htmlspecialchars($channel_category) ?></p>

                <div class="mt-6 flex flex-wrap justify-center gap-4">
                    <div class="px-4 py-1.5 rounded-full bg-white/5 border border-white/10 flex items-center gap-2 text-sm text-gray-300">
                        <span class="iconify text-purple-400" data-icon="solar:calendar-bold"></span>
                        <?= $currentDate ?>
                    </div>
                    <div class="px-4 py-1.5 rounded-full bg-white/5 border border-white/10 flex items-center gap-2 text-sm text-gray-300">
                        <span class="iconify text-pink-400" data-icon="solar:clock-circle-bold"></span>
                        <span id="live-clock"><?= $currentTime ?></span>
                    </div>
                </div>

                <!-- WATCH LIVE BUTTON -->
                <a href="<?= htmlspecialchars($play_url) ?>"
                   class="mt-8 group relative inline-flex items-center justify-center px-8 py-3 font-semibold text-white transition-all duration-200 bg-gradient-to-r from-violet-600 to-fuchsia-600 rounded-full hover:from-violet-500 hover:to-fuchsia-500 focus:outline-none ring-offset-2 ring-purple-500 shadow-lg shadow-purple-900/50 hover:shadow-purple-700/50 hover:-translate-y-1">
                    <span class="mr-2 iconify animate-pulse" data-icon="solar:play-circle-bold"></span>
                    Watch Live
                </a>
            </div>
        </div>

        <!-- CATCHUP SECTION (BACKUP CATCHUP LOGIC) -->
        <div class="flex items-center gap-4 mb-8">
            <h2 class="text-2xl font-semibold text-white">Previous Catchup</h2>
            <div class="h-[1px] flex-1 bg-gradient-to-r from-gray-800 to-transparent"></div>
        </div>

        <?php if ($catchup == '1' || $catchup === true): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
                <?php
                $daysToShow = 7;
                $timezone = new DateTimeZone('Asia/Kolkata');
                $currentDateObj = new DateTime('now', $timezone);

                for ($i = 0; $i >= -$daysToShow; $i--):
                    $date = clone $currentDateObj;
                    $date->modify("$i day");
                    $dateComponents = [
                        'day'     => $date->format('d'),
                        'month'   => $date->format('M'),
                        'year'    => $date->format('Y'),
                        'weekday' => $date->format('l')
                    ];
                    
                    // Generate backup hex link structure for catchup
                    $dataToEncode = implode('=?=', [$cid_raw, $channel_id, $i]);
                    $cp_link = bin2hex($dataToEncode);
                    
                    $isToday = $i === 0;
                    $borderClass = $isToday ? 'border-purple-500/50' : 'border-white/5';
                ?>
                    <a href="<?= htmlspecialchars($jio_path) . $cp_url_host . htmlspecialchars($cp_link) ?>" 
                       class="block group" data-aos="fade-up" data-aos-delay="<?= abs($i) * 50 ?>">
                        <div class="catchup-card rounded-2xl p-5 h-full flex flex-col justify-between <?= $borderClass ?>">
                            <div>
                                <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">
                                    <?= htmlspecialchars($dateComponents['weekday']) ?>
                                </div>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-3xl font-bold <?= $isToday ? 'text-gradient' : 'text-white' ?>">
                                        <?= htmlspecialchars($dateComponents['day']) ?>
                                    </span>
                                    <span class="text-sm text-gray-400">
                                        <?= htmlspecialchars($dateComponents['month']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-4 flex items-center justify-between">
                                <span class="text-xs text-gray-500"><?= htmlspecialchars($dateComponents['year']) ?></span>
                                <div class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center group-hover:bg-purple-600 transition-colors">
                                    <span class="iconify text-gray-400 group-hover:text-white" data-icon="solar:play-bold" data-width="14"></span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endfor; ?>
            </div>
        <?php else: ?>
            <div class="glass-panel rounded-2xl p-12 text-center" data-aos="fade-up">
                <div class="inline-block p-4 bg-gray-800/50 rounded-full mb-4 text-gray-500">
                    <span class="iconify text-3xl" data-icon="solar:forbidden-circle-bold-duotone"></span>
                </div>
                <h3 class="text-xl font-medium text-gray-300">Catchup Unavailable</h3>
                <p class="text-gray-500 mt-2">This channel does not support previous broadcasts.</p>
            </div>
        <?php endif; ?>

    </main>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true });
        setInterval(() => {
            const el = document.getElementById('live-clock');
            if(el) el.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }, 1000);
    </script>
</body>
</html>