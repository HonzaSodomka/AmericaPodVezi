<?php
/**
 * API endpoint pro získání denního menu
 * Vrací JSON s menu pro všechny dny z daily_menu.json
 * 
 * Parametry:
 *   ?day=0  - vrací dnešek (výchozí)
 *   ?day=1  - vrací zítra
 *   ?day=-1 - vrací včera
 *   ?all=1  - vrací všechny dny
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: max-age=300');

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

if (!empty($_GET['all'])) {
    echo json_encode([
        'success' => true,
        'days' => $menuData['days'],
        'scraped_at' => $menuData['scraped_at'] ?? null
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$dayOffset = isset($_GET['day']) ? intval($_GET['day']) : 0;

$czechDays = [
    1 => 'Pondělí',
    2 => 'Úterý',
    3 => 'Středa',
    4 => 'Čtvrtek',
    5 => 'Pátek',
    6 => 'Sobota',
    0 => 'Neděle'
];

$targetDate = date('j.n.Y', strtotime("+{$dayOffset} days"));
$targetDayName = $czechDays[date('w', strtotime("+{$dayOffset} days"))];

$foundDay = null;
$dayIndex = -1;

foreach ($menuData['days'] as $index => $day) {
    if (stripos($day['date'], $targetDate) !== false || 
        (stripos($day['date'], $targetDayName) !== false && 
         stripos($day['date'], date('j.', strtotime("+{$dayOffset} days"))) !== false)) {
        $foundDay = $day;
        $dayIndex = $index;
        break;
    }
}

if (!$foundDay) {
    echo json_encode([
        'success' => false,
        'error' => 'No menu for requested day',
        'requested_offset' => $dayOffset,
        'scraped_at' => $menuData['scraped_at'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$hasPrev = $dayIndex > 0;
$hasNext = $dayIndex < count($menuData['days']) - 1;

echo json_encode([
    'success' => true,
    'date' => $foundDay['date'],
    'soup' => $foundDay['soup'],
    'meals' => $foundDay['meals'],
    'is_closed' => $foundDay['is_closed'] ?? false,
    'is_empty' => $foundDay['is_empty'] ?? false,
    'navigation' => [
        'current_index' => $dayIndex,
        'total_days' => count($menuData['days']),
        'has_prev' => $hasPrev,
        'has_next' => $hasNext
    ],
    'scraped_at' => $menuData['scraped_at'] ?? null
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
