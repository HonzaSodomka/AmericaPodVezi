<?php
// BEZPEČNOSTNÍ OPRAVA: Bezpečné načtení dat sdíleným zámkem (čtení)
$dataFile = __DIR__ . '/data.json';
$data = [];

if (file_exists($dataFile)) {
    $fp = fopen($dataFile, 'r');
    if ($fp) {
        // Získáme sdílený zámek pro čtení (ochrana před tím, aby admin zrovna nepřepisoval soubor)
        if (flock($fp, LOCK_SH)) {
            $filesize = filesize($dataFile);
            if ($filesize > 0) {
                $jsonString = fread($fp, $filesize);
                $decoded = json_decode($jsonString, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data = $decoded;
                }
            }
            flock($fp, LOCK_UN); // Uvolnění zámku
        }
        fclose($fp);
    }
}

// Výchozí hodnoty z administrace
$phone = $data['contact']['phone'] ?? '326 322 007';
$address = $data['contact']['address'] ?? 'Komenského náměstí 61, Mladá Boleslav';

// CHYTRÉ ČIŠTĚNÍ TELEFONU (ochrana proti duplicitní předvolbě v odkazech)
$phoneClean = preg_replace('/[^\d+]/', '', $phone);
if (!str_starts_with($phoneClean, '+')) {
    if (str_starts_with($phoneClean, '420')) {
        $phoneClean = '+' . $phoneClean;
    } else {
        $phoneClean = '+420' . $phoneClean;
    }
}

// ODDĚLENÍ ULICE OD MĚSTA PRO SEO SCHÉMA
$streetOnly = trim(explode(',', $address)[0] ?? '');

// VERZOVÁNÍ PDF MENU (zabraňuje cachování starého menu)
$pdfVersion = file_exists(__DIR__ . '/menu.pdf') ? filemtime(__DIR__ . '/menu.pdf') : '1';
$menuPdfLink = 'menu.pdf?v=' . $pdfVersion;

// Zajištění datových typů a výchozích hodnot
$ratingValue = (float) ($data['rating']['value'] ?? 4.5);
$ratingCount = (int) ($data['rating']['count'] ?? 900);

// Helper pro bezpečné odkazy (ochrana proti javascript:alert() apod.)
function safeUrl($url) {
    if (empty($url)) return '';
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if (filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url)) {
        return htmlspecialchars($url);
    }
    return '';
}

// Logika pro delivery s enabled přepínačem
$delivery = $data['delivery'] ?? [];
$woltLink = '';
$foodoraLink = '';
$boltLink = '';

if (isset($delivery['wolt'])) {
    if (is_array($delivery['wolt']) && !empty($delivery['wolt']['enabled']) && !empty($delivery['wolt']['url'])) {
        $woltLink = safeUrl($delivery['wolt']['url']);
    } elseif (is_string($delivery['wolt'])) {
        $woltLink = safeUrl($delivery['wolt']);
    }
}

if (isset($delivery['foodora'])) {
    if (is_array($delivery['foodora']) && !empty($delivery['foodora']['enabled']) && !empty($delivery['foodora']['url'])) {
        $foodoraLink = safeUrl($delivery['foodora']['url']);
    } elseif (is_string($delivery['foodora'])) {
        $foodoraLink = safeUrl($delivery['foodora']);
    }
}

if (isset($delivery['bolt'])) {
    if (is_array($delivery['bolt']) && !empty($delivery['bolt']['enabled']) && !empty($delivery['bolt']['url'])) {
        $boltLink = safeUrl($delivery['bolt']['url']);
    } elseif (is_string($delivery['bolt'])) {
        $boltLink = safeUrl($delivery['bolt']);
    }
}

$dailyMenuUrl = safeUrl($data['daily_menu_url'] ?? 'https://www.menicka.cz/7509-america-pod-vezi.html');

$openingHours = $data['opening_hours'] ?? [
    'monday' => '11:00 - 14:00',
    'tuesday_friday' => '11:00 - 22:00',
    'saturday' => '11:30 - 22:00',
    'sunday' => 'ZAVŘENO'
];

$exceptions = $data['exceptions'] ?? [];

$dayTranslations = [
    'monday' => 'Pondělí',
    'tuesday' => 'Úterý',
    'wednesday' => 'Středa',
    'thursday' => 'Čtvrtek',
    'friday' => 'Pátek',
    'saturday' => 'Sobota',
    'sunday' => 'Neděle',
    'monday_friday' => 'Pondělí - Pátek',
    'tuesday_friday' => 'Úterý - Pátek',
    'monday_thursday' => 'Pondělí - Čtvrtek',
    'weekend' => 'Víkend'
];

$dayOrder = [
    'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
    'friday' => 5, 'saturday' => 6, 'sunday' => 7,
    'monday_friday' => 1, 'tuesday_friday' => 2, 'monday_thursday' => 1, 'weekend' => 6
];

function sortDaysByWeekOrder($openingHours, $dayOrder) {
    $sorted = [];
    foreach ($openingHours as $key => $value) {
        $order = $dayOrder[strtolower($key)] ?? 999;
        $sorted[$key] = ['value' => $value, 'order' => $order];
    }
    uasort($sorted, function($a, $b) {
        return $a['order'] <=> $b['order'];
    });
    $result = [];
    foreach ($sorted as $key => $data) {
        $result[$key] = $data['value'];
    }
    return $result;
}

$openingHours = sortDaysByWeekOrder($openingHours, $dayOrder);

function formatDayKey($key, $translations) {
    $kl = strtolower($key);
    if (isset($translations[$kl])) return $translations[$kl];
    $parts = explode('_', $kl);
    $res = [];
    foreach($parts as $p) {
        $res[] = $translations[$p] ?? $p;
    }
    return implode(' - ', $res);
}

function formatExceptionDate($dateStr) {
    $parts = explode('-', $dateStr);
    if (count($parts) === 3) {
        return intval($parts[2]) . '.' . intval($parts[1]) . '.';
    }
    return $dateStr;
}

function formatExceptionRange($dateRange) {
    $dates = explode('_', $dateRange);
    if (count($dates) === 2) {
        $fromDisplay = formatExceptionDate($dates[0]);
        $toDisplay = formatExceptionDate($dates[1]);
        if ($fromDisplay === $toDisplay) {
            return $fromDisplay;
        }
        return $fromDisplay . ' - ' . $toDisplay;
    }
    return $dateRange;
}

// ===== EVENT POPUP LOGIC =====
$showEventPopup = false;
$eventImagePath = '';

if (isset($data['event'])) {
    $event = $data['event'];
    $eventActive = !empty($event['active']);
    $eventDateFrom = $event['date_from'] ?? '';
    $eventDateTo = $event['date_to'] ?? '';
    $eventImageFile = $event['image_file'] ?? '';
    
    if ($eventActive && $eventDateFrom && $eventDateTo && $eventImageFile) {
        if (file_exists(__DIR__ . '/' . $eventImageFile)) {
            $today = date('Y-m-d');
            if ($today >= $eventDateFrom && $today <= $eventDateTo) {
                $showEventPopup = true;
                $eventImagePath = htmlspecialchars($eventImageFile);
            }
        }
    }
}

// Generování JSON-LD pro Google
$schema = [
    "@context" => "https://schema.org",
    "@type" => "Restaurant",
    "name" => "America Pod Věží",
    "url" => "https://americapodvezi.cz",
    "image" => "https://americapodvezi.cz/prostory.jpg",
    "description" => "Autentická americká restaurace v srdci Mladé Boleslavi. Burgery z čerstvého masa, BBQ žebra, steaky a skvělá atmosféra přímo pod věží.",
    "address" => [
        "@type" => "PostalAddress",
        "streetAddress" => $streetOnly,
        "addressLocality" => "Mladá Boleslav",
        "postalCode" => "293 01",
        "addressCountry" => "CZ"
    ],
    "geo" => [
        "@type" => "GeoCoordinates",
        "latitude" => 50.4149,
        "longitude" => 14.9120
    ],
    "telephone" => $phoneClean,
    "servesCuisine" => ["American", "BBQ", "Burgers", "Steaks"],
    "priceRange" => "$$",
    "hasMenu" => "https://americapodvezi.cz/" . $menuPdfLink,
    "acceptsReservations" => true,
    "aggregateRating" => [
        "@type" => "AggregateRating",
        "ratingValue" => $ratingValue,
        "ratingCount" => $ratingCount,
        "bestRating" => 5,
        "worstRating" => 1
    ]
];

$sameAs = ["https://www.facebook.com/profile.php?id=100063543104526"];
if (!empty($woltLink)) $sameAs[] = $woltLink;
if (!empty($foodoraLink)) $sameAs[] = $foodoraLink;
if (!empty($boltLink)) $sameAs[] = $boltLink;
$schema['sameAs'] = $sameAs;

$schemaHours = [];
foreach ($openingHours as $key => $val) {
    if (strtoupper($val) === 'ZAVŘENO') continue;
    $parts = explode('-', $val);
    if (count($parts) === 2) {
        $opens = trim($parts[0]);
        $closes = trim($parts[1]);
        $days = [];
        $kl = strtolower($key);
        if ($kl === 'monday') $days = ['Monday'];
        elseif ($kl === 'tuesday') $days = ['Tuesday'];
        elseif ($kl === 'wednesday') $days = ['Wednesday'];
        elseif ($kl === 'thursday') $days = ['Thursday'];
        elseif ($kl === 'friday') $days = ['Friday'];
        elseif ($kl === 'saturday') $days = ['Saturday'];
        elseif ($kl === 'sunday') $days = ['Sunday'];
        elseif ($kl === 'monday_friday') $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        elseif ($kl === 'tuesday_friday') $days = ['Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        elseif ($kl === 'weekend') $days = ['Saturday', 'Sunday'];
        
        if (!empty($days)) {
            $schemaHours[] = [
                "@type" => "OpeningHoursSpecification",
                "dayOfWeek" => $days,
                "opens" => $opens,
                "closes" => $closes
            ];
        }
    }
}
if (!empty($schemaHours)) {
    $schema['openingHoursSpecification'] = $schemaHours;
}

$schemaJson = json_encode(
    $schema, 
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

// Delivery HTML logic
$activeDeliveries = [];
if (!empty($woltLink)) {
    $activeDeliveries[] = '<a href="'.$woltLink.'" target="_blank" rel="noopener noreferrer" class="font-bold text-sm sm:text-base text-white hover:text-wolt-blue transition flex items-center gap-1.5"><i class="fas fa-bicycle"></i> Wolt</a>';
}
if (!empty($foodoraLink)) {
    $activeDeliveries[] = '<a href="'.$foodoraLink.'" target="_blank" rel="noopener noreferrer" class="font-bold text-sm sm:text-base text-white hover:text-foodora-pink transition flex items-center gap-1.5"><i class="fas fa-shopping-bag"></i> Foodora</a>';
}
if (!empty($boltLink)) {
    $activeDeliveries[] = '<a href="'.$boltLink.'" target="_blank" rel="noopener noreferrer" class="font-bold text-sm sm:text-base text-white hover:text-bolt-green transition flex items-center gap-1.5"><i class="fas fa-car"></i> Bolt</a>';
}
?>
<!DOCTYPE html>
<html lang="cs" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#d4a373">
    
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="canonical" href="https://americapodvezi.cz/">
    
    <title>America Pod Věží | Burger & BBQ Restaurant Mladá Boleslav</title>
    <meta name="description" content="Autentická americká restaurace v srdci Mladé Boleslavi. Burgery z čerstvého masa, BBQ žebra, steaky a skvělá atmosféra přímo pod věží.">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="cs_CZ">
    <meta property="og:site_name" content="America Pod Věží">
    <meta property="og:url" content="https://americapodvezi.cz/">
    <meta property="og:title" content="America Pod Věží | Burger & BBQ Restaurant">
    <meta property="og:description" content="Přijďte ochutnat nejlepší burgery a BBQ v Mladé Boleslavi. Těšíme se na vás!">
    <meta property="og:image" content="https://americapodvezi.cz/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://americapodvezi.cz/">
    <meta name="twitter:title" content="America Pod Věží | Burger & BBQ Restaurant">
    <meta name="twitter:description" content="Přijďte ochutnat nejlepší burgery a BBQ v Mladé Boleslavi. Těšíme se na vás!">
    <meta name="twitter:image" content="https://americapodvezi.cz/og-image.jpg">

    <script type="application/ld+json">
    <?= $schemaJson ?>
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Oswald:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="fa/css/fontawesome.min.css">
    <link rel="stylesheet" href="fa/css/solid.min.css">
    <link rel="stylesheet" href="fa/css/brands.min.css">

    <?php $cssVersion = file_exists(__DIR__ . '/output.css') ? filemtime(__DIR__ . '/output.css') : '1'; ?>
    <link rel="stylesheet" href="output.css?v=<?= $cssVersion ?>">

    <style>
        .nav-backdrop {
            position: absolute;
            inset: 0;
            background-color: transparent;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            opacity: 0;
            transition: opacity 0s;
            z-index: -1;
            pointer-events: none;
        }
        /* Skrytí scrollbaru pro galerii */
        .hide-scrollbar {
            -ms-overflow-style: none; 
            scrollbar-width: none; 
        }
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>

    <noscript>
        <style>
            .scroll-wait { opacity: 1 !important; }
            #preloader { display: none !important; }
        </style>
    </noscript>
</head>
<body class="bg-black text-white overflow-x-hidden">

    <div id="preloader" class="fixed inset-0 bg-black z-[100] flex flex-col items-center justify-center transition-opacity duration-1000">
        <div class="text-white font-heading font-bold text-5xl md:text-7xl anim-text-reveal mb-6 select-none">
            AMERICA
        </div>
        <div class="h-1 bg-brand-gold rounded-full anim-line-expand w-0 shadow-[0_0_10px_#d4a373]"></div>
        <div class="mt-4 text-brand-gold font-sans text-xs md:text-sm tracking-[0.5em] uppercase opacity-0 anim-sub-reveal font-light select-none">
            POD VĚŽÍ
        </div>
    </div>

    <?php if ($showEventPopup): ?>
    <div id="event-popup" class="fixed inset-0 z-[120] hidden" style="background: rgba(0, 0, 0, 0.92); backdrop-filter: blur(8px);">
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="relative max-w-2xl w-full">
                <button id="event-close" aria-label="Zavřít" class="absolute top-3 right-3 z-10 w-12 h-12 bg-brand-gold hover:bg-white text-black rounded-full flex items-center justify-center transition shadow-2xl group">
                    <i class="fas fa-times text-xl group-hover:rotate-90 transition-transform duration-300"></i>
                </button>
                <img src="<?= $eventImagePath ?>" alt="Aktuální akce" class="w-full h-auto max-h-[90vh] object-contain rounded-sm shadow-2xl" loading="eager">
            </div>
        </div>
    </div>
    <script>
    (function() {
        const popup = document.getElementById('event-popup');
        const closeBtn = document.getElementById('event-close');
        const sessionKey = 'event_popup_shown';
        
        function closePopup() {
            popup.classList.add('hidden');
            sessionStorage.setItem(sessionKey, 'true');
        }
        
        if (!sessionStorage.getItem(sessionKey)) {
            setTimeout(function() {
                popup.classList.remove('hidden');
            }, 2500);
        }
        
        closeBtn.addEventListener('click', closePopup);
        
        popup.addEventListener('click', function(e) {
            if (e.target === popup) closePopup();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !popup.classList.contains('hidden')) closePopup();
        });
    })();
    </script>
    <?php endif; ?>

    <nav class="fixed w-full z-50 top-0 left-0 p-6 md:px-12 transition-all duration-300 border-b border-transparent" id="navbar">
        <div class="nav-backdrop"></div>
        <div class="flex justify-between items-center max-w-7xl mx-auto relative z-10">
            <div class="flex items-center animate-enter">
                <a href="#" aria-label="Návrat na začátek" class="block focus:outline-none group">
                    <img src="logo.png" alt="America Pod Věží Logo" class="h-10 sm:h-12 md:h-14 w-auto drop-shadow-lg transition-transform duration-300 group-hover:scale-105">
                </a>
            </div>
            <div class="hidden md:flex gap-10 lg:gap-12 items-center animate-enter delay-100">
                <a href="#" class="nav-link font-heading">DOMŮ</a>
                <a href="#denni-menu" class="nav-link font-heading">DENNÍ MENU</a>
                <a href="<?= $menuPdfLink ?>" target="_blank" class="nav-link font-heading">STÁLÉ MENU</a>
                <a href="#about" class="nav-link font-heading">O NÁS</a>
                <?php if (!empty($data['gallery'])): ?>
                <a href="#galerie" class="nav-link font-heading">GALERIE</a>
                <?php endif; ?>
                <a href="#contact" class="nav-link font-heading">KONTAKT</a>
            </div>
            <button id="menu-btn" aria-label="Otevřít menu" aria-expanded="false" aria-controls="mobile-menu" class="text-white text-3xl focus:outline-none z-50 relative md:hidden animate-enter">
                <i class="fas fa-bars pointer-events-none"></i>
            </button>
        </div>
    </nav>

    <div id="mobile-menu" class="fixed inset-0 bg-black/95 z-40 flex flex-col items-center justify-center space-y-6 menu-closed backdrop-blur-xl md:hidden" role="dialog" aria-modal="true" aria-label="Navigační menu">
        <a href="#" class="text-3xl font-heading font-bold tracking-widest hover:text-brand-gold transition focus:outline-none">DOMŮ</a>
        <a href="#denni-menu" class="text-3xl font-heading font-bold tracking-widest hover:text-brand-gold transition focus:outline-none">DENNÍ MENU</a>
        <a href="<?= $menuPdfLink ?>" target="_blank" class="text-3xl font-heading font-bold tracking-widest hover:text-brand-gold transition focus:outline-none">STÁLÉ MENU</a>
        <a href="#about" class="text-3xl font-heading font-bold tracking-widest hover:text-brand-gold transition focus:outline-none">O NÁS</a>
        <?php if (!empty($data['gallery'])): ?>
        <a href="#galerie" class="text-3xl font-heading font-bold tracking-widest hover:text-brand-gold transition focus:outline-none">GALERIE</a>
        <?php endif; ?>
        <a href="#contact" class="text-3xl font-heading font-bold tracking-widest hover:text-brand-gold transition focus:outline-none">KONTAKT</a>
    </div>

    <section class="hero-section flex flex-col pt-[110px] pb-12 sm:pt-[130px] md:pt-[100px] relative overflow-hidden min-h-screen">
        <picture class="absolute inset-0 z-0">
            <source srcset="hero.webp" type="image/webp">
            <img src="hero.jpg" alt="America Pod Věží restaurace" fetchpriority="high" class="w-full h-full object-cover">
        </picture>
        <div class="absolute inset-0 hero-overlay z-0"></div>
        
        <div class="relative z-10 w-full max-w-7xl mx-auto px-8 md:px-12 text-center md:text-left my-auto">
            <div class="animate-enter delay-100">
                <h1 class="mb-6">
                    <span class="block text-6xl md:text-7xl lg:text-8xl xl:text-9xl font-bold font-heading mb-2 leading-none drop-shadow-2xl tracking-tight text-white">
                        AMERICA
                    </span>
                    <span class="block text-4xl md:text-4xl lg:text-5xl xl:text-6xl font-bold font-heading text-brand-gold tracking-widest drop-shadow-lg">
                        POD VĚŽÍ
                    </span>
                </h1>
                <div class="h-1 w-24 bg-brand-gold mb-6 mx-auto md:mx-1 shadow-lg"></div>
            </div>
            <div class="max-w-4xl md:ml-1 animate-enter delay-200">
                <p class="text-sm md:text-base font-light text-gray-200 mb-10 leading-relaxed drop-shadow-md">
                    <span class="font-bold text-white block mb-1 text-base md:text-lg">Naší pýchou jsou burgery z čerstvě mletého hovězího masa.</span>
                    Nabízíme BBQ speciality připravované rovnou z grilu, steaky, saláty a dezerty.
                </p>
                <div class="flex flex-wrap gap-4 items-center justify-center md:justify-start animate-enter delay-300">
                    <div class="flex flex-row gap-3 w-full md:w-auto">
                        <a href="#denni-menu" class="min-h-[64px] flex-1 md:flex-none bg-brand-gold text-black font-bold font-heading px-4 sm:px-6 rounded hover:bg-white transition shadow-lg shadow-amber-900/40 uppercase tracking-widest flex flex-row items-center justify-center transform hover:scale-105 duration-200 text-center leading-none gap-2 whitespace-nowrap min-w-[140px]">
                            <i class="fas fa-utensils text-sm sm:text-base"></i>
                            <span class="text-base sm:text-lg">DENNÍ MENU</span>
                        </a>
                        <a href="tel:<?= htmlspecialchars($phoneClean) ?>" class="min-h-[64px] flex-1 md:flex-none border-2 border-white/80 text-white font-bold font-heading px-2 sm:px-6 rounded hover:bg-white hover:text-black hover:border-white transition uppercase tracking-widest flex flex-col items-center justify-center transform hover:scale-105 duration-200 leading-none gap-1 whitespace-nowrap min-w-[140px]">
                            <span class="text-base sm:text-lg mt-1">REZERVACE</span>
                            <span class="text-[10px] sm:text-xs font-sans font-normal opacity-90"><i class="fas fa-phone-alt text-xs mr-1"></i> <?= htmlspecialchars($phone) ?></span>
                        </a>
                    </div>

                    <?php if (!empty($activeDeliveries)): ?>
                    <div class="min-h-[64px] bg-black/50 backdrop-blur-md border border-white/20 rounded flex flex-col sm:flex-row items-center justify-center px-4 md:px-6 py-2 gap-1 md:gap-4 shadow-xl w-full md:w-auto overflow-hidden">
                        <span class="text-[10px] md:text-xs uppercase tracking-widest text-gray-300 font-heading font-bold whitespace-nowrap md:mt-0 mt-1">Rozvoz domů:</span>
                        <div class="flex items-center justify-center gap-3 sm:gap-4 whitespace-nowrap">
                            <?= implode(' <span class="text-gray-500 text-xs sm:text-sm">|</span> ', $activeDeliveries) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="h-px w-full bg-gradient-to-r from-transparent via-brand-gold/80 to-transparent shadow-[0_0_15px_rgba(212,163,115,0.4)]"></div>

    <section id="denni-menu" class="bg-black py-20 px-8 md:px-12 relative">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-10 scroll-wait">
                <h2 class="text-4xl md:text-5xl font-heading font-bold text-white tracking-widest uppercase mb-2">
                    Denní <span class="text-brand-gold">Menu</span>
                </h2>
                <div class="h-1 w-24 bg-brand-gold mx-auto shadow-lg"></div>
            </div>

            <div id="menu-loading" class="bg-white/5 border border-white/10 p-8 md:p-12 rounded-sm shadow-xl max-w-5xl mx-auto scroll-wait delay-100 text-center relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-black/40 to-transparent pointer-events-none"></div>
                <div class="relative z-10">
                    <div class="text-brand-gold text-4xl md:text-5xl mb-6 animate-pulse">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <p class="text-gray-300">Načítám menu...</p>
                </div>
            </div>

            <div id="menu-display" class="bg-white/5 border border-white/10 p-8 md:p-12 rounded-sm shadow-xl max-w-5xl mx-auto scroll-wait delay-100 relative overflow-hidden hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-black/40 to-transparent pointer-events-none"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-6">
                        <button id="menu-prev-day" class="w-10 h-10 rounded-full bg-white/10 hover:bg-brand-gold hover:text-black border border-white/20 hover:border-brand-gold flex items-center justify-center transition disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-white/10 disabled:hover:text-white" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="flex-1 text-center">
                            <div class="text-brand-gold text-4xl md:text-5xl mb-4">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <h3 id="menu-date" class="text-2xl md:text-3xl font-heading font-bold text-white tracking-wider uppercase"></h3>
                        </div>
                        <button id="menu-next-day" class="w-10 h-10 rounded-full bg-white/10 hover:bg-brand-gold hover:text-black border border-white/20 hover:border-brand-gold flex items-center justify-center transition disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-white/10 disabled:hover:text-white" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <div class="flex justify-center mb-6 pb-6 border-b border-white/10">
                        <a href="tel:<?= htmlspecialchars($phoneClean) ?>" class="inline-flex items-center justify-center gap-2 bg-brand-gold hover:bg-white text-black px-8 py-4 rounded-sm transition duration-300 text-base font-bold font-heading tracking-wider uppercase shadow-lg">
                            <i class="fas fa-phone-alt text-lg"></i> 
                            <span>Objednat s sebou: <?= htmlspecialchars($phone) ?></span>
                        </a>
                    </div>
                    
                    <div id="menu-content">
                        <div id="soup-section" class="mb-8 text-left max-w-3xl mx-auto hidden">
                            <h4 class="text-brand-gold font-heading text-lg uppercase tracking-widest mb-3 border-b border-white/20 pb-2">
                                <i class="fas fa-bowl-hot mr-2"></i> Polévka
                            </h4>
                            <div id="soup-content" class="flex justify-between items-center bg-black/30 p-4 rounded-sm hover:bg-black/40 transition">
                                <span id="soup-name" class="text-white text-base"></span>
                                <span id="soup-price" class="text-brand-gold font-bold text-lg ml-4"></span>
                            </div>
                        </div>

                        <div id="meals-section" class="text-left max-w-3xl mx-auto">
                            <h4 class="text-brand-gold font-heading text-lg uppercase tracking-widest mb-3 border-b border-white/20 pb-2">
                                <i class="fas fa-hamburger mr-2"></i> Hlavní jídla
                            </h4>
                            <div id="meals-content" class="space-y-3"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="menu-closed" class="bg-white/5 border border-white/10 p-8 md:p-12 rounded-sm shadow-xl max-w-3xl mx-auto scroll-wait delay-100 text-center relative overflow-hidden hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-black/40 to-transparent pointer-events-none"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-6">
                        <button id="menu-prev-day-closed" class="w-10 h-10 rounded-full bg-white/10 hover:bg-brand-gold hover:text-black border border-white/20 hover:border-brand-gold flex items-center justify-center transition disabled:opacity-30 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="flex-1 text-center">
                            <div class="text-gray-600 text-4xl md:text-5xl mb-4">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3 id="menu-date-closed" class="text-2xl md:text-3xl font-heading font-bold text-white mb-2 tracking-wider uppercase"></h3>
                        </div>
                        <button id="menu-next-day-closed" class="w-10 h-10 rounded-full bg-white/10 hover:bg-brand-gold hover:text-black border border-white/20 hover:border-brand-gold flex items-center justify-center transition disabled:opacity-30 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <p id="menu-closed-message" class="text-gray-300 font-light text-sm md:text-base mb-8 max-w-xl mx-auto"></p>
                    <div class="flex flex-col sm:flex-row justify-center gap-4">
                        <a href="tel:<?= htmlspecialchars($phoneClean) ?>" class="inline-flex items-center justify-center gap-2 bg-brand-gold hover:bg-white text-black px-6 py-3 rounded-sm transition duration-300 text-sm font-bold font-heading tracking-wider uppercase">
                            <i class="fas fa-phone-alt"></i> Informace: <?= htmlspecialchars($phone) ?>
                        </a>
                        <?php if ($dailyMenuUrl): ?>
                        <a href="<?= $dailyMenuUrl ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/40 text-white px-6 py-3 rounded-sm transition duration-300 text-sm font-bold font-heading uppercase tracking-widest">
                            <i class="fas fa-external-link-alt"></i> Menicka.cz
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="menu-error" class="bg-white/5 border border-white/10 p-8 md:p-12 rounded-sm shadow-xl max-w-3xl mx-auto scroll-wait delay-100 text-center relative overflow-hidden hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-black/40 to-transparent pointer-events-none"></div>
                <div class="relative z-10">
                    <div class="text-gray-600 text-4xl md:text-5xl mb-6">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="text-2xl md:text-3xl font-heading font-bold text-white mb-4 tracking-wider uppercase">Menu není k dispozici</h3>
                    <p class="text-gray-300 font-light text-sm md:text-base mb-8 max-w-xl mx-auto">Omlouváme se, nepodařilo se načíst denní menu. Kontaktujte nás prosím telefonicky.</p>
                    <div class="flex flex-col sm:flex-row justify-center gap-4">
                        <a href="tel:<?= htmlspecialchars($phoneClean) ?>" class="inline-flex items-center justify-center gap-2 bg-brand-gold hover:bg-white text-black px-6 py-3 rounded-sm transition duration-300 text-sm font-bold font-heading tracking-wider uppercase">
                            <i class="fas fa-phone-alt"></i> Zavolat: <?= htmlspecialchars($phone) ?>
                        </a>
                        <?php if ($dailyMenuUrl): ?>
                        <a href="<?= $dailyMenuUrl ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/40 text-white px-6 py-3 rounded-sm transition duration-300 text-sm font-bold font-heading uppercase tracking-widest">
                            <i class="fas fa-external-link-alt"></i> Menicka.cz
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="h-px w-full bg-gradient-to-r from-transparent via-brand-gold/80 to-transparent shadow-[0_0_15px_rgba(212,163,115,0.4)]"></div>

    <section id="about" class="bg-black py-20 px-8 md:px-12 overflow-hidden relative">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16 scroll-wait">
                 <h2 class="text-3xl md:text-5xl font-heading font-bold text-white tracking-widest uppercase mb-2">
                    Náš <span class="text-brand-gold">Příběh</span>
                 </h2>
                 <div class="h-1 w-24 bg-brand-gold mx-auto shadow-lg"></div>
            </div>
            <div class="flex flex-col lg:flex-row gap-12 items-center">
                <div class="w-full lg:w-1/2 grid grid-cols-1 sm:grid-cols-2 gap-4 scroll-wait delay-100 order-2 lg:order-1">
                    
                    <div class="relative rounded-sm overflow-hidden h-[300px] sm:col-span-2 shadow-2xl border border-white/10 group">
                        <picture>
                            <source srcset="prostory.webp" type="image/webp">
                            <img src="prostory.jpg" alt="Stylový interiér restaurace America Pod Věží s barem" loading="lazy" class="w-full h-full object-cover filter brightness-90 contrast-110 group-hover:scale-105 transition duration-500">
                        </picture>
                        <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/90 to-transparent p-4">
                            <h3 class="text-brand-gold font-heading text-lg tracking-widest uppercase">Restaurace</h3>
                            <p class="text-gray-300 text-xs">Příjemné prostředí pro 40 hostů</p>
                        </div>
                    </div>

                    <div class="relative rounded-sm overflow-hidden h-[250px] shadow-2xl border border-white/10 group">
                        <picture>
                            <source srcset="salonek.webp" type="image/webp">
                            <img src="salonek.jpg" alt="Velký soukromý salonek pro 32 osob" loading="lazy" class="w-full h-full object-cover filter brightness-90 contrast-110 group-hover:scale-105 transition duration-500">
                        </picture>
                        <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/90 to-transparent p-4">
                            <h3 class="text-brand-gold font-heading text-base tracking-widest uppercase">Velký Salonek</h3>
                            <p class="text-gray-300 text-[10px] sm:text-xs">Kapacita 32 míst</p>
                        </div>
                    </div>

                    <div class="relative rounded-sm overflow-hidden h-[250px] shadow-2xl border border-white/10 group">
                        <picture>
                            <source srcset="salonek2.webp" type="image/webp">
                            <img src="salonek2.jpg" alt="Malý soukromý salonek pro 15 osob" loading="lazy" class="w-full h-full object-cover filter brightness-90 contrast-110 group-hover:scale-105 transition duration-500">
                        </picture>
                        <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/90 to-transparent p-4">
                            <h3 class="text-brand-gold font-heading text-base tracking-widest uppercase">Malý Salonek</h3>
                            <p class="text-gray-300 text-[10px] sm:text-xs">Kapacita 15 míst</p>
                        </div>
                    </div>

                </div>
                <div class="w-full lg:w-1/2 text-center lg:text-left scroll-wait delay-200 order-1 lg:order-2">
                    <h3 class="text-3xl md:text-4xl font-heading font-bold text-white tracking-wider uppercase mb-6 leading-tight">
                        Autentická chuť Ameriky <br>
                        <span class="text-brand-gold">pod Boleslavskou věží</span>
                    </h3>
                    <p class="text-gray-300 text-lg mb-6 leading-relaxed font-light">
                        Jsme <strong>Family Style Restaurant</strong> s dlouholetou tradicí, přímo v historickém srdci Mladé Boleslavi. Naším cílem není být jen další restaurací, ale místem, kde se budete cítit jako doma.
                    </p>
                    <p class="text-gray-300 mb-6 leading-relaxed">
                        Zakládáme si na poctivé kuchyni. Naše vyhlášené <strong class="text-white">burgery připravujeme denně z čerstvě mletého masa</strong> a pečeme si vlastní housky. Ať už dostanete chuť na pořádný steak, BBQ žebra přímo z grilu, nebo jen "na jedno" (čepujeme Krušovice Bohém), u nás si přijdete na své.
                    </p>
                    
                    <div id="reservation" class="bg-white/5 p-6 rounded-sm border border-white/5 mb-8 text-left scroll-mt-24">
                        <h3 class="text-brand-gold font-heading text-xl mb-3 border-b border-white/10 pb-2">
                            Rezervace stolů & Plánování akcí
                        </h3>
                        <p class="text-gray-300 text-sm mb-4">
                            Chcete si rezervovat stůl na večer? Nebo plánujete svatbu, firemní večírek či rodinnou oslavu? Nabízíme restauraci a <strong>dva soukromé salonky</strong> (celková kapacita až 100 hostů).
                        </p>
                        <ul class="text-gray-400 text-sm space-y-2 mb-6">
                            <li class="flex items-center gap-2"><i class="fas fa-check text-brand-gold text-xs"></i> Rauty na míru (teplá i studená kuchyně)</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-brand-gold text-xs"></i> Grilovací & BBQ speciality</li>
                        </ul>
                        
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="tel:<?= htmlspecialchars($phoneClean) ?>" class="inline-flex items-center justify-center gap-2 bg-brand-gold hover:bg-white text-black px-6 py-3 rounded-sm transition duration-300 text-sm font-bold font-heading tracking-wider uppercase shadow-lg transform hover:scale-105">
                                <i class="fas fa-phone-alt"></i> Zavolejte nám: <?= htmlspecialchars($phone) ?>
                            </a>
                        </div>
                    </div>

                    <a href="https://www.google.com/search?q=America+Pod+V%C4%9B%C5%BE%C3%AD+recenze" target="_blank" rel="noopener noreferrer" class="bg-white/5 border border-white/10 rounded-sm p-4 inline-flex items-center gap-4 mb-6 hover:bg-white/10 transition cursor-pointer group">
                        <div class="flex flex-col">
                            <span class="text-xs text-gray-400 uppercase tracking-wider mb-1">Hodnocení Google</span>
                            <div class="flex items-center gap-2">
                                <span class="text-2xl font-bold text-white"><?= htmlspecialchars($ratingValue) ?></span>
                                <div class="flex text-brand-gold text-sm">
                                    <?php
                                    $fullStars = floor($ratingValue);
                                    $halfStar = ($ratingValue - $fullStars) >= 0.5 ? 1 : 0;
                                    $emptyStars = 5 - $fullStars - $halfStar;
                                    for ($i = 0; $i < $fullStars; $i++) echo '<i class="fas fa-star"></i>';
                                    if ($halfStar) echo '<i class="fas fa-star-half-alt"></i>';
                                    for ($i = 0; $i < $emptyStars; $i++) echo '<i class="fas fa-star opacity-30"></i>';
                                    ?>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400 group-hover:text-white transition"><?= htmlspecialchars($ratingCount) ?>+ recenzí</span>
                        </div>
                        <div class="h-8 w-px bg-white/10 mx-2"></div>
                        <i class="fab fa-google text-2xl text-gray-400 group-hover:text-brand-gold transition"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($data['gallery'])): ?>
    <div class="h-px w-full bg-gradient-to-r from-transparent via-brand-gold/80 to-transparent shadow-[0_0_15px_rgba(212,163,115,0.4)]"></div>

    <section id="galerie" class="bg-[#050505] py-20 px-4 md:px-12 relative overflow-hidden">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12 scroll-wait">
                 <h2 class="text-3xl md:text-5xl font-heading font-bold text-white tracking-widest uppercase mb-2">
                    Naše <span class="text-brand-gold">Galerie</span>
                 </h2>
                 <div class="h-1 w-24 bg-brand-gold mx-auto shadow-lg"></div>
            </div>
            
            <div class="relative max-w-6xl mx-auto scroll-wait delay-100 group">
                <div id="gallery-carousel" class="flex gap-4 md:gap-6 overflow-x-auto snap-x snap-mandatory hide-scrollbar pb-6">
                    <?php foreach ($data['gallery'] as $index => $img): ?>
                        <div class="snap-start shrink-0 w-full sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.3333%-1rem)] h-[300px] md:h-[400px] relative rounded-sm overflow-hidden border border-white/10 shadow-2xl">
                            <img src="<?= htmlspecialchars($img) ?>?v=<?= filemtime(__DIR__.'/'.$img) ?>" alt="Fotogalerie America Pod Věží" class="w-full h-full object-cover hover:scale-105 transition duration-700">
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button id="gallery-prev" aria-label="Předchozí fotka" class="absolute left-2 md:-left-6 top-[calc(50%-1.5rem)] bg-black/80 hover:bg-brand-gold text-brand-gold hover:text-black border border-brand-gold/30 hover:border-brand-gold w-12 h-12 rounded-full flex items-center justify-center backdrop-blur-md transition-all opacity-100 md:opacity-0 md:group-hover:opacity-100 disabled:opacity-0 shadow-[0_0_15px_rgba(0,0,0,0.8)] z-10">
                    <i class="fas fa-chevron-left text-xl"></i>
                </button>
                <button id="gallery-next" class="absolute right-2 md:-right-6 top-[calc(50%-1.5rem)] bg-black/80 hover:bg-brand-gold text-brand-gold hover:text-black border border-brand-gold/30 hover:border-brand-gold w-12 h-12 rounded-full flex items-center justify-center backdrop-blur-md transition-all opacity-100 md:opacity-0 md:group-hover:opacity-100 disabled:opacity-0 shadow-[0_0_15px_rgba(0,0,0,0.8)] z-10">
                    <i class="fas fa-chevron-right text-xl"></i>
                </button>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <div class="h-px w-full bg-gradient-to-r from-transparent via-brand-gold/80 to-transparent shadow-[0_0_15px_rgba(212,163,115,0.4)]"></div>

    <section id="contact" class="bg-black py-16 px-8 md:px-12">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-10 scroll-wait">
                 <h2 class="text-3xl md:text-5xl font-heading font-bold text-white tracking-widest uppercase mb-2">
                    Kde nás <span class="text-brand-gold">Najdete</span>
                 </h2>
                 <div class="h-1 w-24 bg-brand-gold mx-auto shadow-lg"></div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <div class="flex flex-col space-y-8 scroll-wait delay-100 bg-white/5 p-6 rounded-sm border border-white/5 h-full">
                    <div class="flex items-start gap-4 group">
                        <div class="w-10 h-10 rounded-full border border-brand-gold/30 bg-brand-gold/5 flex items-center justify-center text-brand-gold text-lg shrink-0 group-hover:bg-brand-gold group-hover:text-black transition">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-heading text-lg tracking-widest uppercase">Adresa</h3>
                            <p class="text-gray-300"><?= htmlspecialchars(explode(',', $address)[0] ?? '') ?><br><?= htmlspecialchars(explode(',', $address)[1] ?? '') ?></p>
                            <p class="text-gray-500 text-xs mt-1">Přímo pod věží</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 group">
                        <div class="w-10 h-10 rounded-full border border-brand-gold/30 bg-brand-gold/5 flex items-center justify-center text-brand-gold text-lg shrink-0 group-hover:bg-brand-gold group-hover:text-black transition">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-heading text-lg tracking-widest uppercase">Rezervace & Akce</h3>
                            <a href="tel:<?= htmlspecialchars($phoneClean) ?>" class="text-gray-300 text-lg font-bold hover:text-white transition block mt-1"><?= htmlspecialchars($phone) ?></a>
                        </div>
                    </div>
                    <a href="https://www.facebook.com/profile.php?id=100063543104526" target="_blank" rel="noopener noreferrer" class="flex items-start gap-4 group cursor-pointer">
                        <div class="w-10 h-10 rounded-full border border-brand-gold/30 bg-brand-gold/5 flex items-center justify-center text-brand-gold text-lg shrink-0 group-hover:bg-brand-gold group-hover:text-black transition">
                            <i class="fab fa-facebook-f"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-heading text-lg tracking-widest uppercase">Sledujte Nás</h3>
                            <p class="text-gray-400 text-sm">Novinky & Akce na Facebooku</p>
                        </div>
                    </a>
                </div>
                <div class="flex flex-col space-y-4 scroll-wait delay-200 bg-white/5 p-6 rounded-sm border border-white/5 h-full">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-10 h-10 rounded-full border border-brand-gold/30 bg-brand-gold/5 flex items-center justify-center text-brand-gold text-lg shrink-0">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="text-white font-heading text-lg tracking-widest uppercase">Otevírací Doba</h3>
                    </div>
                    <ul class="text-gray-300 text-sm space-y-3 w-full">
                        <?php
                        $keys = array_keys($openingHours);
                        $count = count($keys);
                        foreach ($keys as $index => $key):
                            $label = formatDayKey($key, $dayTranslations);
                            $value = $openingHours[$key];
                            $isClosed = (strpos(strtoupper($value), 'ZAVŘENO') !== false);
                            $valClass = $isClosed ? 'text-brand-gold' : '';
                            $borderClass = ($index < $count - 1) ? 'border-b border-white/10 pb-3' : '';
                        ?>
                            <li class="flex justify-between <?= $borderClass ?>">
                                <span class="font-bold text-white"><?= htmlspecialchars($label) ?></span>
                                <span class="<?= $valClass ?>"><?= htmlspecialchars($value) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($exceptions)): ?>
                    <div class="pt-4 border-t-2 border-brand-gold/30">
                        <h4 class="text-brand-gold font-heading text-xs uppercase tracking-widest mb-3">Výjimečná Otevírací Doba</h4>
                        <ul class="text-gray-300 text-sm space-y-3 w-full">
                            <?php foreach ($exceptions as $dateRange => $note): 
                                $displayRange = formatExceptionRange($dateRange);
                                $isClosed = (strpos(strtoupper($note), 'ZAVŘENO') !== false);
                                $valClass = $isClosed ? 'text-brand-gold' : '';
                            ?>
                            <li class="flex justify-between">
                                <span class="font-bold text-white"><?= htmlspecialchars($displayRange) ?></span>
                                <span class="<?= $valClass ?>"><?= htmlspecialchars($note) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="w-full h-auto lg:h-full min-h-[200px] flex flex-col scroll-wait delay-300">
                    <div class="border border-white/10 bg-white/5 p-3 rounded-sm shadow-xl h-full flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-2 px-1">
                             <span class="text-xs uppercase tracking-widest text-brand-gold font-heading">Mapa</span>
                             <i class="fas fa-map text-gray-500 text-xs"></i>
                        </div>
                        <div class="relative w-full h-64 lg:h-auto lg:flex-grow border border-white/10 rounded-sm overflow-hidden bg-[#242424]" id="map-wrapper">
                            <div id="map-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-center p-6 gap-3">
                                <div class="text-brand-gold text-2xl"><i class="fas fa-map-marked-alt"></i></div>
                                <p class="text-gray-400 text-xs leading-relaxed">
                                    Pro zobrazení mapy načteme obsah od Google,<br>který může používat cookies.
                                </p>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <button id="map-consent-accept" class="bg-brand-gold text-black font-bold font-heading px-4 py-2 rounded-sm uppercase tracking-widest text-xs hover:bg-white transition">
                                        Povolit mapu
                                    </button>
                                    <a href="https://maps.google.com/?q=America+Pod+V%C4%9B%C5%BE%C3%AD" target="_blank" rel="noopener noreferrer"
                                       class="border border-white/20 text-white font-bold font-heading px-4 py-2 rounded-sm uppercase tracking-widest text-xs hover:bg-white hover:text-black transition text-center">
                                        Otevřít v aplikaci
                                    </a>
                                </div>
                            </div>
                            <div id="map-iframe-host" class="absolute inset-0 hidden"></div>
                        </div>
                        <a href="https://maps.google.com/?q=America+Pod+Věží" target="_blank" rel="noopener noreferrer" class="text-[10px] text-center text-gray-500 hover:text-white mt-2 uppercase tracking-wider transition flex items-center justify-center gap-1 py-1">
                            <i class="fas fa-external-link-alt"></i> Otevřít v aplikaci
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-black text-gray-500 py-6 text-center text-xs tracking-widest uppercase border-t border-white/10">
        <p>
            © <span id="current-year"><?= date('Y') ?></span> America Pod Věží | <?= htmlspecialchars(str_replace(', ', ' | ', $address)) ?>
        </p>
        <p class="mt-4 text-[10px] text-gray-600 font-sans tracking-normal normal-case opacity-80">
            Provozovatel: Zdeněk Vimi | IČO: 66773971 <br>
            <span class="mt-1 block uppercase tracking-widest opacity-60">Realizováno <a href="https://webresent.cz" target="_blank" rel="noopener noreferrer" class="text-brand-gold hover:underline">Webresent</a></span>
            &nbsp;|&nbsp;
            <a href="#" id="cookie-settings" class="hover:text-brand-gold transition normal-case">Změnit nastavení cookies</a>
        </p>
    </footer>

    <div id="consent-banner" class="fixed bottom-0 left-0 right-0 z-[110] hidden">
        <div class="h-[2px] w-full bg-gradient-to-r from-transparent via-brand-gold/50 to-transparent"></div>
        <div class="bg-[#0a0a0a]/95 backdrop-blur-xl border-t border-white/5">
            <div class="max-w-5xl mx-auto px-6 md:px-12 py-5 flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="flex-1">
                    <h3 class="font-heading text-white text-lg tracking-wider uppercase mb-1">
                        Nastavení <span class="text-brand-gold">soukromí</span>
                    </h3>
                    <p class="text-gray-400 text-sm font-light leading-relaxed max-w-2xl">
                        Tento web využívá služby třetích stran (Google Mapy), které mohou ukládat soubory cookies do vašeho zařízení za účelem zobrazení interaktivní mapy přímo na stránce.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                    <button id="consent-reject" class="font-heading uppercase tracking-widest text-xs text-gray-400 border border-white/20 px-6 py-3 hover:text-white hover:border-white/40 transition duration-200 w-full sm:w-auto">
                        Pouze nezbytné
                    </button>
                    <button id="consent-accept" class="font-heading uppercase tracking-widest text-xs font-bold text-white border border-brand-gold px-6 py-3 hover:bg-brand-gold hover:text-black transition duration-200 w-full sm:w-auto">
                        Povolit vše
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php $jsVersion = file_exists(__DIR__ . '/script.js') ? filemtime(__DIR__ . '/script.js') : '1'; ?>
    <script src="script.js?v=<?= $jsVersion ?>"></script>

</body>
</html>