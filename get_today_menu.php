<?php
/**
 * API endpoint pro získání dnešního menu
 * Vrací JSON s menu pro dnešní den z daily_menu.json
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: max-age=300'); // Cache na 5 minut

$menuFile = __DIR__ . '/daily_menu.json';

if (!file_exists($menuFile)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Menu file not found'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$menuData = json_decode(file_get_contents($menuFile), true);

if (!$menuData || empty($menuData['days'])) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'No menu data available'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Najdi dnešní den
$today = date('j.n.Y'); // např. "23.2.2026"
$todayMenu = null;

// Česká jména dnů
$czechDays = [
    1 => 'Pondělí',
    2 => 'Úterý',
    3 => 'Středa',
    4 => 'Čtvrtek',
    5 => 'Pátek',
    6 => 'Sobota',
    0 => 'Neděle'
];

$todayDayName = $czechDays[date('w')];

foreach ($menuData['days'] as $day) {
    // Zkus najít podle datumu v textu (např. "Pondělí 23.2.2026")
    if (stripos($day['date'], $today) !== false || 
        stripos($day['date'], $todayDayName) !== false && 
        stripos($day['date'], date('j.')) !== false) {
        $todayMenu = $day;
        break;
    }
}

if (!$todayMenu) {
    echo json_encode([
        'success' => false,
        'error' => 'No menu for today',
        'scraped_at' => $menuData['scraped_at'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'date' => $todayMenu['date'],
    'soup' => $todayMenu['soup'],
    'meals' => $todayMenu['meals'],
    'scraped_at' => $menuData['scraped_at'] ?? null
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
