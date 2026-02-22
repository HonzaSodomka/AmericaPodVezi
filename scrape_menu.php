<?php
/**
 * Menu scraper pro menicka.cz
 * Stáhne denní menu a uloží do daily_menu.json
 * 
 * Použití: php scrape_menu.php nebo jako cron job
 */

define('MENU_URL', 'https://www.menicka.cz/7509-america-pod-vezi.html');
define('OUTPUT_FILE', __DIR__ . '/daily_menu.json');

function scrapeMenu() {
    // Fetch HTML using file_get_contents with proper headers
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                       "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                       "Accept-Language: cs,en;q=0.9\r\n",
            'timeout' => 10
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $html = @file_get_contents(MENU_URL, false, $context);
    
    if ($html === false) {
        $error = error_get_last();
        error_log('Failed to fetch menu from ' . MENU_URL . ': ' . ($error['message'] ?? 'Unknown error'));
        return false;
    }
    
    // Load into DOMDocument
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    $menuData = [
        'scraped_at' => date('Y-m-d H:i:s'),
        'days' => []
    ];
    
    // Find all menu sections by day
    // Struktura: <div class="menicka"> obsahuje dny
    $menuDivs = $xpath->query("//div[contains(@class, 'menicka')]");
    
    foreach ($menuDivs as $menuDiv) {
        // Najdi nadpis dne (např. "Čtvrtek 7.8.2025")
        $dateNodes = $xpath->query(".//h2 | .//h3", $menuDiv);
        if ($dateNodes->length === 0) continue;
        
        $dayText = trim($dateNodes->item(0)->textContent);
        
        // Skip if closed or no menu
        if (stripos($dayText, 'zavřeno') !== false || stripos($dayText, 'nebylo zadáno') !== false) {
            continue;
        }
        
        $dayData = [
            'date' => $dayText,
            'soup' => null,
            'meals' => []
        ];
        
        // Najdi všechny položky menu (soup + meals)
        $menuItems = $xpath->query(".//li[contains(@class, 'polevka')] | .//li[contains(@class, 'jidlo')]", $menuDiv);
        
        foreach ($menuItems as $item) {
            $text = trim($item->textContent);
            if (empty($text)) continue;
            
            // Rozčlenění na text a cenu
            // Formát: "Název jídla 199 Kč" nebo "Polévka 45 Kč"
            if (preg_match('/^(.+?)\s+(\d+)\s*Kč\s*$/u', $text, $matches)) {
                $name = trim($matches[1]);
                $price = intval($matches[2]);
                
                // Je to polévka?
                if (strpos($item->getAttribute('class'), 'polevka') !== false) {
                    $dayData['soup'] = [
                        'name' => $name,
                        'price' => $price
                    ];
                } else {
                    // Hlavní jídlo - vyřeš číslo na začátku
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
        }
        
        // Přidej den jen pokud má nějaká jídla
        if ($dayData['soup'] || !empty($dayData['meals'])) {
            $menuData['days'][] = $dayData;
        }
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

// Main execution
if (php_sapi_name() === 'cli' || !empty($_GET['run'])) {
    echo "Scraping menu from menicka.cz...\n";
    
    $menuData = scrapeMenu();
    
    if ($menuData === false) {
        echo "ERROR: Failed to scrape menu\n";
        exit(1);
    }
    
    if (empty($menuData['days'])) {
        echo "WARNING: No menu data found\n";
    } else {
        echo "Found menu for " . count($menuData['days']) . " day(s)\n";
    }
    
    if (saveMenu($menuData)) {
        echo "Menu saved to daily_menu.json\n";
        echo "Scraped at: " . $menuData['scraped_at'] . "\n";
        
        // Print preview
        foreach ($menuData['days'] as $day) {
            echo "\n" . $day['date'] . ":\n";
            if ($day['soup']) {
                echo "  Polévka: " . $day['soup']['name'] . " (" . $day['soup']['price'] . " Kč)\n";
            }
            foreach ($day['meals'] as $meal) {
                echo "  " . ($meal['number'] ? $meal['number'] . ". " : "") . $meal['name'] . " (" . $meal['price'] . " Kč)\n";
            }
        }
    } else {
        echo "ERROR: Failed to save menu\n";
        exit(1);
    }
} else {
    // Web interface
    header('Content-Type: application/json');
    
    $menuData = scrapeMenu();
    if ($menuData !== false) {
        saveMenu($menuData);
        echo json_encode(['success' => true, 'data' => $menuData], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to scrape menu']);
    }
}
