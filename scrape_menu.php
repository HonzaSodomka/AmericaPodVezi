<?php
/**
 * Menu scraper pro menicka.cz
 * Stáhne denní menu pro celý týden včetně alergenů a uloží do daily_menu.json
 * 
 * Použití: php scrape_menu.php
 */

define('MENU_URL', 'https://www.menicka.cz/7509-america-pod-vezi.html');
define('OUTPUT_FILE', __DIR__ . '/daily_menu.json');

function fetchWithCurl($url) {
    if (!function_exists('curl_init')) {
        // Fallback - try with stream context
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'timeout' => 10
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        return @file_get_contents($url, false, $context);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
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

/**
 * Parse allergens from text
 * Example: "Kuřecí řízek (1,3,7)" -> [1, 3, 7]
 */
function parseAllergens($text) {
    $allergens = [];
    // Hledej čísla v závorkách na konci textu
    if (preg_match('/\(([0-9,\s]+)\)\s*$/u', $text, $matches)) {
        $numbers = preg_split('/[,\s]+/', trim($matches[1]));
        foreach ($numbers as $num) {
            if (is_numeric($num)) {
                $allergens[] = intval($num);
            }
        }
    }
    return $allergens;
}

/**
 * Remove allergen numbers from meal name
 * Example: "Kuřecí řízek (1,3,7)" -> "Kuřecí řízek"
 */
function cleanMealName($text) {
    return trim(preg_replace('/\([0-9,\s]+\)\s*$/u', '', $text));
}

function scrapeMenu() {
    $html = fetchWithCurl(MENU_URL);
    
    if ($html === false) {
        error_log('Failed to fetch menu from ' . MENU_URL);
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
    $menuDivs = $xpath->query("//div[@class='menicka']");
    
    foreach ($menuDivs as $menuDiv) {
        // Najdi nadpis dne
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
        
        // Zkontroluj jestli není zavřeno
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
        
        // Najdi všechny položky menu (polévka + jídla)
        $menuItems = $xpath->query(".//li[@class='polevka'] | .//li[@class='jidlo']", $menuDiv);
        
        foreach ($menuItems as $item) {
            // Najdi div.polozka a div.cena
            $polozkaNodes = $xpath->query(".//div[@class='polozka']", $item);
            $cenaNodes = $xpath->query(".//div[@class='cena']", $item);
            
            if ($polozkaNodes->length === 0 || $cenaNodes->length === 0) continue;
            
            $rawName = trim($polozkaNodes->item(0)->textContent);
            $priceText = trim($cenaNodes->item(0)->textContent);
            
            // Parsuj alergeny
            $allergens = parseAllergens($rawName);
            $name = cleanMealName($rawName);
            
            // Vytáhni číslo z ceny
            if (preg_match('/(\d+)\s*Kč/u', $priceText, $matches)) {
                $price = intval($matches[1]);
            } else {
                continue; // Přeskoč položky bez ceny
            }
            
            // Je to polévka?
            if ($item->getAttribute('class') === 'polevka') {
                $dayData['soup'] = [
                    'name' => $name,
                    'price' => $price,
                    'allergens' => $allergens
                ];
            } else {
                // Hlavní jídlo - zkus najít číslo
                $mealNumber = null;
                if (preg_match('/^(\d+)\.\s*(.+)$/u', $name, $mealMatches)) {
                    $mealNumber = intval($mealMatches[1]);
                    $name = trim($mealMatches[2]);
                }
                
                $dayData['meals'][] = [
                    'number' => $mealNumber,
                    'name' => $name,
                    'price' => $price,
                    'allergens' => $allergens
                ];
            }
        }
        
        // Přidej vždy všechny dny (i prázdné)
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
                $allergenStr = !empty($day['soup']['allergens']) ? ' [' . implode(',', $day['soup']['allergens']) . ']' : '';
                echo "  Polévka: " . $day['soup']['name'] . $allergenStr . " (" . $day['soup']['price'] . " Kč)\n";
            }
            foreach ($day['meals'] as $meal) {
                $allergenStr = !empty($meal['allergens']) ? ' [' . implode(',', $meal['allergens']) . ']' : '';
                echo "  " . ($meal['number'] ? $meal['number'] . ". " : "") . $meal['name'] . $allergenStr . " (" . $meal['price'] . " Kč)\n";
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
