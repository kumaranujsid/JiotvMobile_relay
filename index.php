<?php
// --- 1. ERROR REPORTING & AUTH REDIRECTION ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$file_path = __DIR__ . '/app/assets/data/credskey.jtv';
if (!file_exists($file_path)) {
    header("Location: app/login");
    exit;
}

if (file_exists("functions.php")) {
    include "functions.php";
}

// --- 2. cURL HELPER FUNCTION ---
function get_url_contents($url) {
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

// --- 3. DYNAMIC PLAYLIST URL DETECTION ---
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$basePlaylistURL = $scheme . '://' . $host . $current_dir . '/app/playlist.php';

// --- 4. USER DATA ---
$name = $mobile = $exp_date_time = null;
if (function_exists('getUserData')) {
    $user_data = getUserData();
    $name = $user_data['name'] ?? null;
    $mobile = $user_data['mobile'] ?? null;
    $exp_date_time = $user_data['exp_date_time'] ?? null;
}

// --- 5. DATA FETCHING & SOURCE SELECTION ---
$use_source = $_GET['source'] ?? 'PRIMARY'; // 'PRIMARY' or 'BACKUP'

$primary_api_url = 'https://api.npoint.io/5f75ba3d2bf31a764128';
$backup_json_url = 'https://gist.githubusercontent.com/mitthu786/6719418610aa81997e72d6c27a6a2e39/raw/tsjiotvnew.json';

$channels = [];
$seen_channels = [];

if ($use_source === 'PRIMARY') {
    // --- 5a. PRIMARY SOURCE ---
    $json_data = get_url_contents($primary_api_url);
    if ($json_data) {
        $decoded = json_decode($json_data, true);
        if ($decoded && is_array($decoded)) {
            foreach ($decoded as $ch) {
                $c_name = $ch['name'] ?? 'Unnamed';
                if (!isset($seen_channels[$c_name])) {
                    $ch_id    = $ch['id'] ?? '';
                    $logo     = $ch['logo'] ?? '';
                    $category = $ch['category'] ?? 'General';
                    
                    // FORCED TO ALWAYS BE 1
                    $catchup  = '1';

                    $detail_url = "app/details.php?id=" . urlencode($ch_id) . "&catchup=" . $catchup;

                    $channels[] = [
                        'link2'     => $c_name,
                        'img'       => $logo,
                        'genre'     => $category,
                        'lang'      => $category,
                        'catchup'   => $catchup,
                        'final_url' => $detail_url
                    ];
                    $seen_channels[$c_name] = true;
                }
            }
        }
    }
} else {
    // --- 5b. BACKUP SOURCE ---
    $json_data = get_url_contents($backup_json_url);
    if ($json_data) {
        $decoded = json_decode($json_data, true);
        if ($decoded && is_array($decoded)) {
            foreach ($decoded as $ch) {
                $c_name = $ch['link2'] ?? 'Unnamed';
                if (stripos($c_name, 'Sony') === false && !isset($seen_channels[$c_name])) {
                    
                    // FORCED TO ALWAYS BE 1
                    $catchup = '1';
                    
                    $hex = '';
                    $str = ($ch['name'] ?? $c_name) . "=?=" . ($ch['id'] ?? '') . "=?=" . $catchup;
                    for ($i = 0; $i < strlen($str); $i++) {
                        $hex .= str_pad(dechex(ord($str[$i])), 2, '0', STR_PAD_LEFT);
                    }
                    
                    $ch['catchup']   = $catchup;
                    $ch['final_url'] = 'app/details_' . $hex;
                    $channels[]      = $ch;
                    $seen_channels[$c_name] = true;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>JioTV+ ReBorn Edition</title>
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ec4899' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='2' y='7' width='20' height='13' rx='2' ry='2'></rect><polyline points='17 2 12 7 7 2'></polyline></svg>" type="image/svg+xml">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                screens: {
                    'xs': '380px',
                    'sm': '640px',
                    'md': '768px',
                    'lg': '1024px',
                    'xl': '1280px',
                    '2xl': '1536px',
                    '3xl': '1920px', // Full HD / TV Viewport
                    '4k': '2560px',  // 4K Smart TVs & Ultra-Wide
                },
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        heading: ['Space Grotesk', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            500: '#ec4899',
                            600: '#db2777',
                            accent: '#8b5cf6',
                        }
                    },
                    animation: {
                        'spin-slow': 'spin 8s linear infinite',
                    }
                }
            }
        }
    </script>
    
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet" />
    
    <style>
        /* Modern Light Mode (Default) */
        body {
            background-color: #f1f5f9;
            background-image: 
                radial-gradient(at 0% 0%, rgba(139, 92, 246, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(236, 72, 153, 0.12) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(226, 232, 240, 0.6) 0px, transparent 50%);
            background-attachment: fixed;
            color: #0f172a;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }

        .glass-card:hover {
            border-color: rgba(236, 72, 153, 0.4);
            box-shadow: 0 10px 25px -5px rgba(236, 72, 153, 0.15);
        }

        .glass-input {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(203, 213, 225, 0.8);
            color: #0f172a;
        }

        .glass-input:focus-within {
            border-color: #ec4899;
            box-shadow: 0 0 20px -5px rgba(236, 72, 153, 0.25);
        }

        .theme-text-muted { color: #64748b; }
        .theme-text-main { color: #0f172a; }
        .theme-card-sub { background: rgba(241, 245, 249, 0.8); }

        /* Dark Mode Override */
        html.dark body {
            background-color: #08090d;
            background-image: 
                radial-gradient(at 0% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(236, 72, 153, 0.15) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(15, 23, 42, 0.5) 0px, transparent 50%);
            color: #f1f5f9;
        }

        html.dark .glass-card {
            background: rgba(20, 24, 36, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: none;
        }

        html.dark .glass-card:hover {
            border-color: rgba(236, 72, 153, 0.3);
            box-shadow: none;
        }

        html.dark .glass-input {
            background: rgba(10, 12, 18, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        html.dark .theme-text-muted { color: #94a3b8; }
        html.dark .theme-text-main { color: #ffffff; }
        html.dark .theme-card-sub { background: rgba(15, 23, 42, 0.5); }

        .mic-active {
            color: #ec4899 !important;
            animation: pulse 1.2s infinite;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        html.dark ::-webkit-scrollbar-thumb { background: #1e293b; }

        /* ========================================================= */
        /* RESPONSIVE MEDIA QUERIES (TV, TABLETS, MOBILES, LAPTOPS) */
        /* ========================================================= */

        /* Smart TVs & Ultra-Wide Monitors (1920px+) */
        @media screen and (min-width: 1920px) {
            body { font-size: 18px; }
            .tv-large-text { font-size: 1.5rem !important; }
            .glass-card { border-radius: 1.25rem; }
            .channel-grid {
                grid-template-columns: repeat(8, minmax(0, 1fr)) !important;
                gap: 1.75rem !important;
            }
        }

        @media screen and (min-width: 2560px) {
            .channel-grid {
                grid-template-columns: repeat(10, minmax(0, 1fr)) !important;
                gap: 2rem !important;
            }
        }

        /* Tablets & iPads (768px to 1023px) */
        @media screen and (min-width: 768px) and (max-width: 1023px) {
            .channel-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 1rem !important;
            }
        }

        /* Compact Mobile Phones (Below 480px) */
        @media screen and (max-width: 480px) {
            .channel-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.625rem !important;
            }
            .header-container {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            .brand-title { font-size: 1.125rem !important; }
            .mobile-p-small { padding: 0.5rem !important; }
        }
    </style>

    <!-- Initial Theme Script to Prevent Flash -->
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="min-h-screen flex flex-col antialiased selection:bg-pink-500 selection:text-white">

    <!-- PRELOADER -->
    <div id="preloader" class="fixed inset-0 bg-slate-100 dark:bg-[#050608] z-[9999] flex flex-col items-center justify-center transition-all duration-700">
        <div class="relative w-28 h-28 sm:w-36 sm:h-36 flex items-center justify-center">
            <div class="absolute inset-0 border-4 border-transparent border-t-pink-500 border-b-blue-500 rounded-full animate-spin"></div>
            <div class="absolute inset-2 border-2 border-transparent border-l-purple-500 border-r-indigo-500 rounded-full animate-spin-slow"></div>
            <div class="relative z-10 w-20 h-20 sm:w-24 sm:h-24 bg-white dark:bg-slate-950 border border-slate-200 dark:border-white/10 rounded-2xl flex items-center justify-center shadow-2xl shadow-pink-500/20">
                <span class="font-heading font-bold text-xs sm:text-sm tracking-tighter text-slate-900 dark:text-white">JIOTV<span class="text-pink-500">+</span></span>
            </div>
        </div>
        <div class="w-48 sm:w-64 h-1.5 bg-slate-200 dark:bg-slate-800/80 rounded-full overflow-hidden mt-8 relative border border-black/5 dark:border-white/5">
            <div id="progressFill" class="h-full w-0 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 rounded-full transition-all duration-100 ease-out"></div>
        </div>
        <div id="loadingStatus" class="font-heading font-semibold text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 tracking-widest uppercase mt-4">Initializing Engine...</div>
    </div>

    <!-- AUDIO EFFECT -->
    <audio id="bootSound" src="https://assets.mixkit.co/active_storage/sfx/2874/2874-preview.mp3" preload="auto"></audio>

    <!-- HEADER NAVBAR -->
    <header class="glass-card sticky top-0 z-50 border-b">
        <div class="max-w-[1920px] mx-auto px-3 sm:px-6 lg:px-8 py-2.5 sm:py-3.5 flex justify-between items-center gap-2 sm:gap-4 header-container">
            
            <!-- Brand Logo -->
            <div data-aos="fade-right" class="flex items-center gap-2 sm:gap-3.5 cursor-pointer group">
                <div class="relative w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0">
                    <div class="absolute inset-0 bg-gradient-to-tr from-blue-600 to-pink-600 rounded-xl blur opacity-70 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative w-full h-full bg-white dark:bg-slate-950 rounded-xl flex items-center justify-center border border-slate-200 dark:border-white/10 text-pink-500">
                        <iconify-icon icon="lucide:tv" class="text-lg sm:text-xl"></iconify-icon>
                    </div>
                </div>
                <div>
                    <h1 class="font-heading font-bold text-base sm:text-xl tracking-tight theme-text-main flex items-center gap-1 brand-title">
                        JIOTV<span class="text-pink-500">+</span>
                    </h1>
                    <span class="text-[9px] sm:text-[10px] uppercase tracking-widest font-bold text-pink-500 dark:text-pink-400/80 block -mt-1">ReBorn Edition</span>
                </div>
            </div>

            <!-- Header Actions & Controls -->
            <div class="flex items-center gap-1.5 sm:gap-3">
                <!-- Source Selection Dropdown -->
                <select onchange="location = this.value;" class="glass-input theme-text-main text-[11px] sm:text-xs font-semibold rounded-lg sm:rounded-xl px-2 sm:px-3 py-1.5 sm:py-2 focus:outline-none cursor-pointer">
                    <option value="?source=PRIMARY" <?= $use_source === 'PRIMARY' ? 'selected' : '' ?> class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white">Primary</option>
                    <option value="?source=BACKUP" <?= $use_source === 'BACKUP' ? 'selected' : '' ?> class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white">Backup</option>
                </select>

                <!-- Theme Toggle Button -->
                <button id="themeToggleBtn" title="Toggle Light/Dark Theme" class="p-2 sm:p-2.5 rounded-lg sm:rounded-xl glass-input theme-text-muted hover:text-pink-500 transition-all">
                    <iconify-icon id="themeIcon" icon="lucide:moon" class="text-base sm:text-lg block"></iconify-icon>
                </button>

                <?php if ($name) : ?>
                    <button id="loginButton" class="flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 py-1.5 sm:py-2 rounded-lg sm:rounded-xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-600 dark:text-purple-300 transition-all text-[11px] sm:text-xs font-semibold">
                        <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="hidden sm:inline"><?= htmlspecialchars(substr($name, 0, 10)) ?>..</span>
                    </button>
                    <button title="Logout" onclick="window.location.href='app/login/logout.php'" class="p-2 sm:p-2.5 rounded-lg sm:rounded-xl glass-input theme-text-muted hover:text-red-500 transition-all">
                        <iconify-icon icon="lucide:power" class="text-base sm:text-lg block"></iconify-icon>
                    </button>
                <?php endif; ?>
                
                <button title="Refresh Session" onclick="window.location.href='app/login/refreshLogin.php'" class="p-2 sm:p-2.5 rounded-lg sm:rounded-xl glass-input theme-text-muted hover:text-emerald-500 transition-all">
                    <iconify-icon icon="lucide:refresh-cw" class="text-base sm:text-lg block"></iconify-icon>
                </button>
                
                <button id="PlayListButton" title="M3U Playlist URL" class="p-2 sm:p-2.5 rounded-lg sm:rounded-xl glass-input theme-text-muted hover:text-amber-500 transition-all">
                    <iconify-icon icon="lucide:list-video" class="text-base sm:text-lg block"></iconify-icon>
                </button>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-[1920px] mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8 flex-grow w-full">
        
        <!-- SEARCH & CONTROLS -->
        <div class="mb-6 sm:mb-8 space-y-3 sm:space-y-4" data-aos="fade-up">
            <!-- Search Bar -->
            <div class="relative glass-input rounded-xl sm:rounded-2xl flex items-center px-3 sm:px-4 py-0.5">
                <iconify-icon icon="lucide:search" class="text-lg sm:text-xl theme-text-muted mr-2 sm:mr-3 flex-shrink-0"></iconify-icon>
                <input type="text" id="searchBar" placeholder="Search channels, languages, or genres..." class="w-full py-2.5 sm:py-3.5 bg-transparent border-none theme-text-main placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none text-sm sm:text-base font-medium">
                <button id="micBtn" title="Voice Search" class="p-1.5 sm:p-2 theme-text-muted hover:text-pink-500 transition-colors rounded-lg">
                    <iconify-icon icon="lucide:mic" class="text-lg sm:text-xl block"></iconify-icon>
                </button>
            </div>

            <!-- Filters Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 2xl:grid-cols-5 gap-2 sm:gap-3">
                <select id="genreFilter" class="glass-input theme-text-main text-xs sm:text-sm font-medium rounded-lg sm:rounded-xl p-2.5 sm:p-3 focus:outline-none cursor-pointer">
                    <option value="" class="bg-white dark:bg-slate-900 theme-text-main">All Genres</option>
                </select>
                
                <select id="langFilter" class="glass-input theme-text-main text-xs sm:text-sm font-medium rounded-lg sm:rounded-xl p-2.5 sm:p-3 focus:outline-none cursor-pointer">
                    <option value="" class="bg-white dark:bg-slate-900 theme-text-main">All Languages</option>
                </select>

                <label class="glass-input flex items-center justify-center gap-2 p-2.5 sm:p-3 rounded-lg sm:rounded-xl cursor-pointer hover:bg-black/5 dark:hover:bg-white/5 select-none transition-colors col-span-2 sm:col-span-1">
                    <input type="checkbox" id="favoritesFilter" class="sr-only peer">
                    <div class="w-4 h-4 rounded border border-slate-400 dark:border-slate-500 peer-checked:bg-pink-500 peer-checked:border-pink-500 transition-all flex items-center justify-center">
                        <iconify-icon icon="lucide:check" class="text-white text-xs opacity-0 peer-checked:opacity-100"></iconify-icon>
                    </div>
                    <span class="text-xs sm:text-sm font-medium theme-text-main">Favorites</span>
                </label>

                <!-- Dynamic Live Stats -->
                <div class="glass-input hidden md:flex items-center justify-around p-2.5 sm:p-3 rounded-lg sm:rounded-xl text-xs font-semibold theme-text-muted col-span-1 2xl:col-span-2">
                    <span>Total: <strong id="totalCount" class="text-pink-500">0</strong></span>
                    <span class="opacity-30">|</span>
                    <span>Catchup: <strong id="catchupCount" class="text-purple-500">0</strong></span>
                </div>
            </div>
        </div>

        <!-- CHANNEL GRID -->
        <div id="grid-wrapper" class="relative min-h-[300px] sm:min-h-[400px]">
            <div id="charactersList" class="channel-grid grid grid-cols-2 xs:grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 3xl:grid-cols-8 gap-3 sm:gap-4">
                <!-- Cards appended dynamically via JavaScript -->
            </div>

            <!-- Empty State Fallback -->
            <div id="emptyState" class="hidden flex-col items-center justify-center py-16 sm:py-20 text-center">
                <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl glass-card flex items-center justify-center theme-text-muted text-xl sm:text-2xl mb-3 sm:mb-4">
                    🔍
                </div>
                <h3 class="text-base sm:text-lg font-heading font-bold theme-text-main">No Channels Found</h3>
                <p class="text-xs theme-text-muted max-w-sm mt-1">Try tweaking your search parameters or disabling active filters.</p>
            </div>
        </div>
    </main>

    <!-- M3U MODAL WITH PLAYLIST TYPE DROPDOWN -->
    <div id="playlistModal" class="hidden fixed inset-0 bg-slate-900/40 dark:bg-black/80 backdrop-blur-md flex items-center justify-center p-3 sm:p-4 z-[100]">
        <div class="glass-card w-full max-w-md sm:max-w-lg rounded-2xl p-5 sm:p-6 relative border shadow-2xl">
            <button onclick="document.getElementById('playlistModal').classList.add('hidden')" class="absolute top-3.5 right-3.5 sm:top-4 sm:right-4 theme-text-muted hover:text-pink-500 p-1 rounded-lg transition-colors">
                <iconify-icon icon="lucide:x" class="text-lg sm:text-xl block"></iconify-icon>
            </button>
            
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-amber-500/10 border border-amber-500/20 text-amber-500 rounded-xl mb-3 sm:mb-4 flex items-center justify-center text-xl sm:text-2xl">
                📡
            </div>
            
            <h2 class="text-lg sm:text-xl font-heading font-bold theme-text-main mb-1">M3U Playlist Endpoint</h2>
            <p class="text-xs theme-text-muted mb-4">Connected Host: <span class="font-mono text-pink-500 font-semibold break-all"><?= htmlspecialchars($host) ?></span></p>
            
            <!-- Source Selector Dropdown -->
            <div class="mb-4">
                <label for="playlistTypeSelect" class="block text-xs font-semibold theme-text-muted mb-1.5">Select Playlist Source:</label>
                <select id="playlistTypeSelect" class="w-full glass-input theme-text-main text-xs font-semibold rounded-xl p-2.5 sm:p-3 focus:outline-none focus:border-pink-500 cursor-pointer">
                    <option value="?id=Jiotv+" selected class="bg-white dark:bg-slate-900">JioTV+</option>
                    <option value="?id=Jiotv" class="bg-white dark:bg-slate-900">JioTV</option>
                </select>
            </div>

            <!-- Generated Playlist URL Input -->
            <div class="relative mb-5 sm:mb-6">
                <input id="playlistUrlInput" type="text" value="<?= htmlspecialchars($basePlaylistURL) ?>?id=Jiotv+" class="w-full glass-input rounded-xl p-2.5 sm:p-3 pr-10 sm:pr-12 text-[11px] sm:text-xs font-mono text-pink-500 font-semibold focus:outline-none" readonly />
                <button onclick="copyPlaylistUrl()" title="Copy Link" class="absolute right-1.5 sm:right-2 top-1/2 -translate-y-1/2 theme-text-muted hover:text-pink-500 p-1.5 sm:p-2 rounded-lg transition-colors">
                    <iconify-icon id="copyIcon" icon="lucide:copy" class="text-sm sm:text-base block"></iconify-icon>
                </button>
            </div>
            
            <button onclick="document.getElementById('playlistModal').classList.add('hidden')" class="w-full py-2 sm:py-2.5 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white text-xs font-semibold transition-colors">
                Close
            </button>
        </div>
    </div>

    <!-- USER MODAL -->
    <div id="loginModal" class="hidden fixed inset-0 bg-slate-900/40 dark:bg-black/80 backdrop-blur-md flex items-center justify-center p-3 sm:p-4 z-[100]">
        <div class="glass-card w-full max-w-xs sm:max-w-sm rounded-2xl p-5 sm:p-6 relative text-center border shadow-2xl">
            <button onclick="document.getElementById('loginModal').classList.add('hidden')" class="absolute top-3.5 right-3.5 sm:top-4 sm:right-4 theme-text-muted hover:text-pink-500 p-1 rounded-lg transition-colors">
                <iconify-icon icon="lucide:x" class="text-lg sm:text-xl block"></iconify-icon>
            </button>
            
            <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-tr from-purple-500 to-pink-500 rounded-full mx-auto mb-3 sm:mb-4 p-0.5">
                <div class="w-full h-full bg-white dark:bg-slate-950 rounded-full flex items-center justify-center text-xl sm:text-2xl">
                    👤
                </div>
            </div>
            
            <h2 class="text-base sm:text-lg font-heading font-bold theme-text-main"><?= htmlspecialchars($name) ?></h2>
            <p class="text-xs theme-text-muted mb-4"><?= htmlspecialchars($mobile) ?></p>
            
            <div class="theme-card-sub border border-black/5 dark:border-white/5 rounded-xl p-2.5 sm:p-3 mb-5 sm:mb-6">
                <span class="text-[9px] sm:text-[10px] uppercase font-bold theme-text-muted block mb-0.5">Subscription Expiry</span>
                <span class="text-xs font-mono text-pink-500 font-semibold"><?= htmlspecialchars($exp_date_time) ?></span>
            </div>
            
            <button onclick="window.location.href='app/login/refreshLogin.php'" class="w-full py-2.5 rounded-xl bg-pink-600 hover:bg-pink-700 text-white text-xs font-semibold transition-colors shadow-lg shadow-pink-600/30">
                Refresh Credentials
            </button>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js" async></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    
    <script>
        const basePlaylistURL = <?= json_encode($basePlaylistURL) ?>;
        const allChannels = <?= json_encode($channels) ?>;
        const els = {
            grid: document.getElementById('charactersList'),
            search: document.getElementById('searchBar'),
            genre: document.getElementById('genreFilter'),
            lang: document.getElementById('langFilter'),
            fav: document.getElementById('favoritesFilter'),
            empty: document.getElementById('emptyState'),
            totalCount: document.getElementById('totalCount'),
            catchupCount: document.getElementById('catchupCount')
        };
        
        let favorites = JSON.parse(localStorage.getItem('userFavorites') || '[]');

        // Theme Toggle Logic
        const themeBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeIcon');

        function updateThemeIcon() {
            if (document.documentElement.classList.contains('dark')) {
                themeIcon.setAttribute('icon', 'lucide:sun');
            } else {
                themeIcon.setAttribute('icon', 'lucide:moon');
            }
        }
        updateThemeIcon();

        themeBtn?.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeIcon();
        });

        // Preloader Logic
        window.addEventListener('load', () => {
            const fill = document.getElementById('progressFill');
            const status = document.getElementById('loadingStatus');
            const loader = document.getElementById('preloader');
            const audio = document.getElementById('bootSound');

            let progress = 0;
            const steps = [
                { p: 25, t: "Connecting Feed..." },
                { p: 60, t: "Parsing Streams..." },
                { p: 90, t: "Applying Filters..." },
                { p: 100, t: "Engine Ready" }
            ];

            audio.play().catch(() => {});

            const timer = setInterval(() => {
                progress += 5;
                if(fill) fill.style.width = `${progress}%`;

                const matched = steps.find(s => s.p === progress);
                if (matched && status) status.textContent = matched.t;

                if (progress >= 100) {
                    clearInterval(timer);
                    setTimeout(() => {
                        loader.style.opacity = '0';
                        setTimeout(() => loader.style.display = 'none', 700);
                    }, 200);
                }
            }, 30);
        });

        // Speech Recognition
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const Speech = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new Speech();
            const micBtn = document.getElementById('micBtn');

            micBtn.onclick = () => { 
                recognition.start(); 
                micBtn.classList.add('mic-active'); 
            };
            recognition.onresult = (e) => { 
                els.search.value = e.results[0][0].transcript; 
                applyFilters(); 
            };
            recognition.onend = () => micBtn.classList.remove('mic-active');
        }

        function toggleFavorite(name) {
            const idx = favorites.indexOf(name);
            if (idx > -1) {
                favorites.splice(idx, 1);
            } else {
                favorites.push(name);
            }
            localStorage.setItem('userFavorites', JSON.stringify(favorites));
            applyFilters();
        }

        function copyPlaylistUrl() {
            const input = document.getElementById("playlistUrlInput");
            input.select();
            navigator.clipboard.writeText(input.value);
            
            const icon = document.getElementById("copyIcon");
            icon.setAttribute("icon", "lucide:check");
            setTimeout(() => icon.setAttribute("icon", "lucide:copy"), 2000);
        }

        function renderChannels(data) {
            els.grid.innerHTML = '';
            
            if (data.length === 0) {
                els.empty.classList.remove('hidden');
                els.empty.classList.add('flex');
            } else {
                els.empty.classList.add('hidden');
                els.empty.classList.remove('flex');
            }

            data.forEach(ch => {
                const isFav = favorites.includes(ch.link2);
                
                const card = document.createElement('div');
                card.className = 'glass-card group relative rounded-xl sm:rounded-2xl overflow-hidden cursor-pointer transition-all duration-300 hover:-translate-y-1 sm:hover:-translate-y-1.5 flex flex-col justify-between';
                
                card.innerHTML = `
                    <div class="relative w-full aspect-video bg-slate-100 dark:bg-slate-950/80 p-2.5 sm:p-4 flex items-center justify-center overflow-hidden" onclick="window.location.href='${ch.final_url}'">
                        
                        <!-- Favorite Button -->
                        <button onclick="event.stopPropagation(); toggleFavorite('${ch.link2}')" class="absolute top-1.5 left-1.5 sm:top-2 sm:left-2 z-20 p-1 sm:p-1.5 rounded-lg bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-black/5 dark:border-white/10 theme-text-muted hover:text-amber-500 transition-colors shadow-sm">
                            <iconify-icon icon="${isFav ? 'lucide:star' : 'lucide:star'}" class="text-xs sm:text-sm block ${isFav ? 'text-amber-500 fill-amber-500' : ''}"></iconify-icon>
                        </button>

                        <!-- Catchup Badge -->
                        <span class="absolute top-1.5 right-1.5 sm:top-2 sm:right-2 z-20 px-1.5 sm:px-2 py-0.5 rounded-md bg-pink-500/10 dark:bg-pink-500/20 border border-pink-500/30 text-[8px] sm:text-[9px] font-bold uppercase tracking-wider text-pink-600 dark:text-pink-300 backdrop-blur-md">
                            Catchup
                        </span>

                        <!-- Channel Logo -->
                        <img data-src="${ch.img}" src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'></svg>" class="lazyload w-full h-full object-contain group-hover:scale-105 transition-transform duration-300" alt="${ch.link2}">
                    </div>

                    <!-- Meta details -->
                    <div class="p-2 sm:p-3 border-t border-black/5 dark:border-white/5 theme-card-sub">
                        <h3 class="text-[11px] sm:text-xs font-semibold theme-text-main truncate group-hover:text-pink-500 transition-colors">${ch.link2}</h3>
                        <p class="text-[9px] sm:text-[10px] font-medium theme-text-muted truncate mt-0.5">${ch.genre || 'Live TV'}</p>
                    </div>
                `;
                els.grid.appendChild(card);
            });

            // Update Dynamic Counters
            els.totalCount.textContent = data.length;
            els.catchupCount.textContent = data.length;
        }

        function applyFilters() {
            const term = els.search.value.toLowerCase().trim();
            const filtered = allChannels.filter(c => {
                const matchesSearch = c.link2.toLowerCase().includes(term);
                const matchesGenre  = !els.genre.value || c.genre === els.genre.value;
                const matchesLang   = !els.lang.value || c.lang === els.lang.value;
                const matchesFav    = !els.fav.checked || favorites.includes(c.link2);

                return matchesSearch && matchesGenre && matchesLang && matchesFav;
            });
            renderChannels(filtered);
        }

        document.addEventListener('DOMContentLoaded', () => {
            AOS.init({ duration: 600, once: true });

            // Populate Dropdown Options dynamically
            const genres = [...new Set(allChannels.map(c => c.genre))].filter(Boolean).sort();
            const langs  = [...new Set(allChannels.map(c => c.lang))].filter(Boolean).sort();

            genres.forEach(g => els.genre.innerHTML += `<option value="${g}" class="bg-white dark:bg-slate-900 theme-text-main">${g}</option>`);
            langs.forEach(l => els.lang.innerHTML += `<option value="${l}" class="bg-white dark:bg-slate-900 theme-text-main">${l}</option>`);

            renderChannels(allChannels);

            // Playlist URL Dropdown Switcher Event
            const typeSelect = document.getElementById('playlistTypeSelect');
            const urlInput = document.getElementById('playlistUrlInput');

            typeSelect?.addEventListener('change', (e) => {
                urlInput.value = basePlaylistURL + e.target.value;
            });

            // Bind Event Listeners
            [els.search, els.genre, els.lang, els.fav].forEach(el => el.addEventListener('input', applyFilters));
            
            document.getElementById('loginButton')?.addEventListener('click', () => document.getElementById('loginModal').classList.remove('hidden'));
            document.getElementById('PlayListButton')?.addEventListener('click', () => document.getElementById('playlistModal').classList.remove('hidden'));
        });
    </script>
</body>
</html>