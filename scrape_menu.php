<?php
/**
 * Menu scraper pro menicka.cz
 * Stáhne denní menu pro celý týden a uloží do daily_menu.json
 * 
 * Použití: php scrape_menu.php
 */

define('MENU_URL', 'https://www.menicka.cz/7509-america-pod-vezi.html');
define('OUTPUT_FILE', __DIR__ . '/daily_menu.json');

// ZABEZPEČENÍ: Blokovat spuštění přes web prohlížeč
// Povolujeme pouze CLI nebo pokud je skript inkludován z adminu (konstanta definována)
if (php_sapi_name() !== 'cli' && !defined('ALLOW_SCRAPER_RUN')) {
    http_response_code(403);
    die('Přístup odepřen.');
}

function fetchWithCurl($url) {
    if (!function_exists('curl_init')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'timeout' => 10
            ],
            'ssl' => [
                'verify_peer' => true, // SECURITY FIX
                'verify_peer_name' => true // SECURITY FIX
            ]
        ]);
        return @file_get_contents($url, false, $context);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true, // SECURITY FIX
        CURLOPT_SSL_VERIFYHOST => 2, // SECURITY FIX (2 = verify full name)
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_TIMEOUT => 10
    ]);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($result === false) {
        error_log('cURL error: ' . $error);
        return false;
    }
    
    return $result;
}

function scrapeMenu() {
    $html = fetchWithCurl(MENU_URL);
    
    if ($html === false) {
        error_log('Failed to fetch menu from ' . MENU_URL);
        return false;
    }
    
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    $menuData = [
        'scraped_at' => date('Y-m-d H:i:s'),
        'days' => []
    ];
    
    $menuDivs = $xpath->query("//div[@class='menicka']");
    
    foreach ($menuDivs as $menuDiv) {
        $dateNodes = $xpath->query(".//div[@class='nadpis']", $menuDiv);
        if ($dateNodes->length === 0) continue;
        
        $dayText = trim($dateNodes->item(0)->textContent);
        
        $dayData = [
            'date' => $dayText,
            'soup' => null,
            'meals' => [],
            'is_closed' => false,
            'is_empty' => false
        ];
        
        $fullDayText = trim($menuDiv->textContent);
        if (stripos($fullDayText, 'zavřeno') !== false) {
            $dayData['is_closed'] = true;
            $menuData['days'][] = $dayData;
            continue;
        }
        
        if (stripos($fullDayText, 'nebylo zadáno') !== false) {
            $dayData['is_empty'] = true;
            $menuData['days'][] = $dayData;
            continue;
        }
        
        $menuItems = $xpath->query(".//li[@class='polevka'] | .//li[@class='jidlo']", $menuDiv);
        
        foreach ($menuItems as $item) {
            $polozkaNodes = $xpath->query(".//div[@class='polozka']", $item);
            $cenaNodes = $xpath->query(".//div[@class='cena']", $item);
            
            if ($polozkaNodes->length === 0 || $cenaNodes->length === 0) continue;
            
            $name = trim($polozkaNodes->item(0)->textContent);
            $priceText = trim($cenaNodes->item(0)->textContent);
            
            if (preg_match('/(\d+)\s*Kč/u', $priceText, $matches)) {
                $price = intval($matches[1]);
            } else {
                continue;
            }
            
            if ($item->getAttribute('class') === 'polevka') {
                $dayData['soup'] = [
                    'name' => $name,
                    'price' => $price
                ];
            } else {
                $mealNumber = null;
                if (preg_match('/^(\d+)\.\s*(.+)$/u', $name, $mealMatches)) {
                    $mealNumber = intval($mealMatches[1]);
                    $name = trim($mealMatches[2]);
                }
                
                $dayData['meals'][] = [
                    'number' => $mealNumber,
                    'name' => $name,
                    'price' => $price
                ];
            }
        }
        
        $menuData['days'][] = $dayData;
    }
    
    return $menuData;
}

function saveMenu($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('Failed to encode menu data to JSON');
        return false;
    }
    
    if (file_put_contents(OUTPUT_FILE, $json) === false) {
        error_log('Failed to write menu to ' . OUTPUT_FILE);
        return false;
    }
    
    return true;
}

// Vlastní logika skriptu
echo "Scraping menu from menicka.cz...\n";

$menuData = scrapeMenu();

if ($menuData === false) {
    echo "ERROR: Failed to scrape menu\n";
    // Zpřístupníme chybový status pro admin.php
    $scrape_success = false;
    if (php_sapi_name() === 'cli') exit(1);
} else {
    if (empty($menuData['days'])) {
        echo "WARNING: No menu data found\n";
    } else {
        echo "Found menu for " . count($menuData['days']) . " day(s)\n";
    }
    
    if (saveMenu($menuData)) {
        echo "Menu saved to daily_menu.json\n";
        echo "Scraped at: " . $menuData['scraped_at'] . "\n";
        $scrape_success = true;
        
        // V CLI vypíšeme menu, v adminu ne (není potřeba spamovat buffer)
        if (php_sapi_name() === 'cli') {
            foreach ($menuData['days'] as $day) {
                echo "\n" . $day['date'] . ":";
                
                if ($day['is_closed']) {
                    echo " ZAVŘENO\n";
                    continue;
                }
                
                if ($day['is_empty']) {
                    echo " Nebylo zadáno menu\n";
                    continue;
                }
                
                echo "\n";
                
                if ($day['soup']) {
                    echo "  Polévka: " . $day['soup']['name'] . " (" . $day['soup']['price'] . " Kč)\n";
                }
                foreach ($day['meals'] as $meal) {
                    echo "  " . ($meal['number'] ? $meal['number'] . ". " : "") . $meal['name'] . " (" . $meal['price'] . " Kč)\n";
                }
            }
        }
    } else {
        echo "ERROR: Failed to save menu\n";
        $scrape_success = false;
        if (php_sapi_name() === 'cli') exit(1);
    }
}
