<?php
// --- ZABEZPEČENÍ: Parametry session cookies (musí být před session_start) ---
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'samesite' => 'Strict'
]);
session_start();

// --- ZABEZPEČENÍ: CSRF Token pro login i admin ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dataFile = __DIR__ . '/data.json';
$uploadsDir = __DIR__ . '/uploads/';

// Load data including password hash
function loadData() {
    global $dataFile;
    if (file_exists($dataFile)) {
        return json_decode(file_get_contents($dataFile), true) ?: [];
    }
    return [];
}

// Get admin password hash
function getPasswordHash() {
    $data = loadData();
    return $data['admin_password_hash'] ?? null;
}

// Check if setup is needed
function needsSetup() {
    return getPasswordHash() === null;
}

// --- ZMENŠENÍ FOTEK GALERIE ---
function processAndSaveImage($tmpName, $ext, $destination, $maxDim = 1200) {
    if (!extension_loaded('gd')) return move_uploaded_file($tmpName, $destination);
    
    if ($ext === 'jpg' || $ext === 'jpeg') $img = @imagecreatefromjpeg($tmpName);
    elseif ($ext === 'png') $img = @imagecreatefrompng($tmpName);
    elseif ($ext === 'webp') $img = @imagecreatefromwebp($tmpName);
    else return false;
    
    if (!$img) return move_uploaded_file($tmpName, $destination);
    
    $width = imagesx($img); $height = imagesy($img);
    if ($width > $maxDim || $height > $maxDim) {
        $newWidth = $width > $height ? $maxDim : (int)($width * ($maxDim / $height));
        $newHeight = $width > $height ? (int)($height * ($maxDim / $width)) : $maxDim;
        
        $newImg = imagecreatetruecolor($newWidth, $newHeight);
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($newImg, false); imagesavealpha($newImg, true);
            imagefilledrectangle($newImg, 0, 0, $newWidth, $newHeight, imagecolorallocatealpha($newImg, 255, 255, 255, 127));
        }
        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($img); $img = $newImg;
    }
    
    $success = false;
    if ($ext === 'jpg' || $ext === 'jpeg') $success = imagejpeg($img, $destination, 85);
    elseif ($ext === 'png') $success = imagepng($img, $destination, 8);
    elseif ($ext === 'webp') $success = imagewebp($img, $destination, 85);
    imagedestroy($img); return $success;
}

// --- MAZÁNÍ Z GALERIE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_gallery') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Neplatný CSRF token.');
    }
    $index = (int)($_POST['image_index'] ?? -1);
    $currentData = loadData();
    if ($index >= 0 && isset($currentData['gallery'][$index])) {
        $file = __DIR__ . '/' . $currentData['gallery'][$index];
        if (file_exists($file)) unlink($file);
        array_splice($currentData['gallery'], $index, 1);
        
        copy($dataFile, $dataFile . '.bak');
        file_put_contents($dataFile, json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT), LOCK_EX);
    }
    header('Location: admin.php?saved=1'); exit;
}

// --- SETUP: Počáteční nastavení hesla ---
if (isset($_POST['action']) && $_POST['action'] === 'setup') {
    if (!needsSetup()) {
        header('Location: admin.php');
        exit;
    }
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $setupError = 'Platnost formuláře vypršela. Zkuste to prosím znovu.';
    } else {
        $newPassword = $_POST['setup_password'] ?? '';
        $newPasswordConfirm = $_POST['setup_password_confirm'] ?? '';
        
        if (empty($newPassword) || empty($newPasswordConfirm)) {
            $setupError = 'Obě pole musí být vyplněna.';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $setupError = 'Hesla se neshodují.';
        } elseif (strlen($newPassword) < 8) {
            $setupError = 'Heslo musí mít alespoň 8 znaků.';
        } else {
            // Create data.json with password hash
            $data = loadData();
            $data['admin_password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
            
            if (file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT), LOCK_EX) !== false) {
                // Auto-login after setup
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                header('Location: admin.php?setup_complete=1');
                exit;
            } else {
                $setupError = 'Chyba při ukládání hesla. Zkontrolujte oprávnění k zápisu.';
            }
        }
    }
}

// --- SETUP SCREEN ---
if (needsSetup()) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex">
        <title>Setup | Admin</title>
        <link rel="stylesheet" href="output.css">
    </head>
    <body class="bg-[#050505] text-white font-sans min-h-screen flex items-center justify-center p-4">
        <form method="POST" class="w-full max-w-[400px] bg-[#0A0A0A] border border-white/10 p-8 shadow-2xl">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="setup">
            
            <h1 class="text-xl font-heading text-brand-gold mb-8 uppercase tracking-[0.3em] border-b border-white/10 pb-4">Initialize Admin</h1>

            <?php if (!empty($setupError)): ?>
                <div class="bg-red-500/10 border-l-2 border-red-500 text-red-200 px-4 py-3 mb-6 text-sm italic"><?= htmlspecialchars($setupError) ?></div>
            <?php endif; ?>

            <div class="space-y-6">
                <div>
                    <label class="text-[10px] text-gray-500 uppercase tracking-widest mb-2 block font-bold">New Password</label>
                    <input type="password" name="setup_password" minlength="8" 
                           class="w-full bg-black border border-white/10 px-4 py-3 text-white focus:border-brand-gold outline-none transition-all" autofocus required>
                </div>

                <div>
                    <label class="text-[10px] text-gray-500 uppercase tracking-widest mb-2 block font-bold">Confirm Password</label>
                    <input type="password" name="setup_password_confirm" minlength="8" 
                           class="w-full bg-black border border-white/10 px-4 py-3 text-white focus:border-brand-gold outline-none transition-all" required>
                </div>

                <button type="submit" class="w-full bg-brand-gold text-black font-bold uppercase tracking-widest py-4 mt-4 hover:bg-white transition-colors duration-300">
                    Set Credentials
                </button>
            </div>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// --- ZABEZPEČENÍ: Změna hesla ---
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!isset($_SESSION['admin_logged_in'])) {
        header('Location: admin.php');
        exit;
    }
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Location: admin.php?error=csrf');
        exit;
    }
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($newPasswordConfirm)) {
        header('Location: admin.php?error=password_empty');
        exit;
    }
    
    // Verify current password
    if (!password_verify($currentPassword, getPasswordHash())) {
        sleep(1); // Brute-force protection
        header('Location: admin.php?error=password_wrong');
        exit;
    }
    
    // Check new passwords match
    if ($newPassword !== $newPasswordConfirm) {
        header('Location: admin.php?error=password_mismatch');
        exit;
    }
    
    // Check password strength (min 8 chars)
    if (strlen($newPassword) < 8) {
        header('Location: admin.php?error=password_weak');
        exit;
    }
    
    // Save new password hash
    $data = loadData();
    $data['admin_password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
    
    if (file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT), LOCK_EX) !== false) {
        header('Location: admin.php?password_changed=1');
        exit;
    } else {
        header('Location: admin.php?error=password_save_failed');
        exit;
    }
}

// --- ZABEZPEČENÍ: Přihlášení ---
if (isset($_POST['login_password'])) {
    // Ověření CSRF u loginu
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $loginError = 'Platnost formuláře vypršela. Zkuste to prosím znovu.';
    } elseif (password_verify($_POST['login_password'], getPasswordHash())) {
        // ZABEZPEČENÍ: Prevence Session Fixation
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        // PŘESMĚROVÁNÍ PO ÚSPĚŠNÉM PŘIHLÁŠENÍ
        header('Location: admin.php');
        exit;
    } else {
        // ZABEZPEČENÍ: Zpomalení proti Brute-force útokům
        sleep(1);
        $loginError = 'Nesprávné heslo.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
// --- LOGIN SCREEN ---
// --- LOGIN SCREEN ---
if (empty($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex">
        <title>Login | Admin</title>
        <link rel="stylesheet" href="output.css">
    </head>
    <body class="bg-[#050505] text-white font-sans min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-[380px]">
            <form method="POST" class="bg-[#0A0A0A] border border-white/10 p-8 shadow-2xl">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="mb-10 text-center">
                    <h1 class="text-lg font-heading text-white uppercase tracking-[0.4em]">Admin Access</h1>
                    <div class="h-[1px] w-12 bg-brand-gold mx-auto mt-4"></div>
                </div>

                <?php if (!empty($loginError)): ?>
                    <div class="text-red-500 text-xs text-center mb-6 italic"><?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>

                <div class="space-y-8">
                    <div class="relative">
                        <label class="text-[9px] text-gray-500 uppercase tracking-[0.2em] mb-2 block">System Password</label>
                        <input type="password" name="login_password" 
                               class="w-full bg-transparent border-b border-white/20 py-2 text-xl text-white focus:border-brand-gold outline-none transition-all" autofocus>
                    </div>

                    <button type="submit" class="w-full border border-brand-gold text-brand-gold hover:bg-brand-gold hover:text-black font-bold uppercase tracking-[0.2em] py-4 transition-all duration-300">
                        Authorize
                    </button>
                </div>
            </form>
            <p class="text-center mt-8">
                <a href="https://americapodvezi.cz" class="text-[10px] text-gray-600 hover:text-white uppercase tracking-widest transition-colors">Return to Terminal</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Create uploads directory if it doesn't exist
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// ZABEZPEČENÍ: Zamezení spuštění PHP ve složce uploads
if (!file_exists($uploadsDir . '.htaccess')) {
    file_put_contents($uploadsDir . '.htaccess', "php_flag engine off\n<FilesMatch \"\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|htm|shtml|sh|cgi)$\">\nDeny from all\n</FilesMatch>");
}

// -- MANUÁLNÍ SPUŠTĚNÍ SCRAPERU --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_scraper') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Location: admin.php?error=csrf');
        exit;
    }
    
    // Povolit skriptu, aby poznal, že je volán oprávněně z adminu
    define('ALLOW_SCRAPER_RUN', true);
    
    // Zavoláme scraper napřímo
    ob_start();
    require_once __DIR__ . '/scrape_menu.php';
    $scrape_output = ob_get_clean();
    
    // Požadavek prošel (ve scraperu se nastavuje flag)
    if (isset($scrape_success) && $scrape_success === true) {
        header('Location: admin.php?scrape=success');
    } else {
        header('Location: admin.php?scrape=error');
    }
    exit;
}

// Mapování dnů na jejich pořadí v týdnu (1 = pondělí, 7 = neděle)
$dayOrder = [
    'monday' => 1,
    'tuesday' => 2,
    'wednesday' => 3,
    'thursday' => 4,
    'friday' => 5,
    'saturday' => 6,
    'sunday' => 7
];

// Funkce pro seřazení dnů podle správného pořadí v týdnu
function sortDaysByWeekOrder($openingHours, $dayOrder) {
    $sorted = [];
    foreach ($openingHours as $key => $value) {
        $keyLower = strtolower($key);
        // Pro rozsahy dnů (např. monday_friday) použijeme první den
        $firstDay = explode('_', $keyLower)[0];
        $order = $dayOrder[$firstDay] ?? 999;
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

// Podmínka action=save zaručuje, že data ukládáme jen tehdy, když se odešle z admin formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    // ZABEZPEČENÍ: Ověření CSRF tokenu v administraci
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Neplatný CSRF token. Zkuste stránku obnovit a odeslat formulář znovu.');
    }

    // --- Zpracování PDF Menu ---
    if (isset($_FILES['menu_pdf']) && $_FILES['menu_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['menu_pdf']['error'] === UPLOAD_ERR_OK) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['menu_pdf']['tmp_name']);
            finfo_close($finfo);

            $ext = strtolower(pathinfo($_FILES['menu_pdf']['name'], PATHINFO_EXTENSION));

            if ($mime === 'application/pdf' && $ext === 'pdf') {
                move_uploaded_file($_FILES['menu_pdf']['tmp_name'], __DIR__ . '/menu.pdf');
            } else {
                header('Location: admin.php?error=invalid_pdf');
                exit;
            }
        } else {
            header('Location: admin.php?error=upload_failed');
            exit;
        }
    }

    $currentData = loadData();

    $currentData['contact']['phone'] = $_POST['contact_phone'] ?? '';
    $currentData['contact']['address'] = $_POST['contact_address'] ?? '';

    $currentData['rating']['value'] = (float)($_POST['rating_value'] ?? 4.5);
    $currentData['rating']['count'] = (int)($_POST['rating_count'] ?? 900);

    $currentData['delivery']['wolt'] = [
        'url' => $_POST['delivery_wolt_url'] ?? '',
        'enabled' => isset($_POST['delivery_wolt_enabled'])
    ];
    $currentData['delivery']['foodora'] = [
        'url' => $_POST['delivery_foodora_url'] ?? '',
        'enabled' => isset($_POST['delivery_foodora_enabled'])
    ];
    $currentData['delivery']['bolt'] = [
        'url' => $_POST['delivery_bolt_url'] ?? '',
        'enabled' => isset($_POST['delivery_bolt_enabled'])
    ];

    $currentData['daily_menu_url'] = $_POST['daily_menu_url'] ?? '';

    $openingHoursRaw = $_POST['opening_hours_json'] ?? null;
    if ($openingHoursRaw !== null && $openingHoursRaw !== '') {
        $openingHoursParsed = json_decode($openingHoursRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($openingHoursParsed)) {
            // SEŘADIT otevírací dobu před uložením
            $currentData['opening_hours'] = sortDaysByWeekOrder($openingHoursParsed, $dayOrder);
        }
    } else {
        if (!isset($currentData['opening_hours'])) {
            $currentData['opening_hours'] = new stdClass();
        }
    }

    $exceptionsRaw = $_POST['exceptions_json'] ?? null;
    if ($exceptionsRaw !== null && $exceptionsRaw !== '') {
        $exceptionsParsed = json_decode($exceptionsRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($exceptionsParsed)) {
            $currentData['exceptions'] = $exceptionsParsed;
        }
    } else {
        if (!isset($currentData['exceptions'])) {
            $currentData['exceptions'] = new stdClass();
        }
    }

    // Handle Events/Akce with FILE-BASED storage + AUTO CLEANUP
    $eventActive = isset($_POST['event_active']);
    $eventDateFrom = $_POST['event_date_from'] ?? '';
    $eventDateTo = $_POST['event_date_to'] ?? '';
    $eventImageData = $_POST['event_image_data'] ?? '';
    
    // Validate dates if event is active
    if ($eventActive && $eventDateFrom && $eventDateTo) {
        if (strtotime($eventDateFrom) > strtotime($eventDateTo)) {
            header('Location: admin.php?error=date_invalid');
            exit;
        }
    }
    
    // Get current image file
    $eventImageFile = $currentData['event']['image_file'] ?? '';
    
    // AUTOMATIC CLEANUP: If event is deactivated, delete the file
    if (!$eventActive && !empty($eventImageFile)) {
        if (file_exists(__DIR__ . '/' . $eventImageFile)) {
            unlink(__DIR__ . '/' . $eventImageFile);
        }
        $eventImageFile = '';
    }
    // Handle image upload/update (only if event is active)
    elseif ($eventActive) {
        if (!empty($eventImageData) && strpos($eventImageData, 'data:image/') === 0) {
            // New image uploaded - save as file
            
            // Extract format and data
            preg_match('/data:image\/(\w+);base64,(.+)/', $eventImageData, $matches);
            $extension = strtolower($matches[1] ?? 'jpg');
            if ($extension === 'jpeg') $extension = 'jpg';
            $base64Data = $matches[2] ?? '';
            
            // ZABEZPEČENÍ: Whitelist povolených přípon obrázků
            $allowedExtensions = ['jpg', 'png', 'webp', 'gif'];
            if (!in_array($extension, $allowedExtensions, true)) {
                header('Location: admin.php?error=invalid_image');
                exit;
            }
            
            if (!empty($base64Data)) {
                $imageData = base64_decode($base64Data);
                
                // ZABEZPEČENÍ: Kontrola skutečného MIME typu dat, nejen přípony
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_buffer($finfo, $imageData);
                finfo_close($finfo);
                
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($mimeType, $allowedMimes, true)) {
                    header('Location: admin.php?error=invalid_image_content');
                    exit;
                }

                // Delete old file if exists
                if (!empty($eventImageFile) && file_exists(__DIR__ . '/' . $eventImageFile)) {
                    unlink(__DIR__ . '/' . $eventImageFile);
                }

                $filename = 'event-' . time() . '.' . $extension;
                $filepath = $uploadsDir . $filename;
                
                // ZABEZPEČENÍ: LOCK_EX proti souběžnému zápisu
                if (file_put_contents($filepath, $imageData, LOCK_EX)) {
                    $eventImageFile = 'uploads/' . $filename;
                }
            }
        } elseif ($eventImageData === '' && !empty($eventImageFile)) {
            // Image manually removed - delete file
            if (file_exists(__DIR__ . '/' . $eventImageFile)) {
                unlink(__DIR__ . '/' . $eventImageFile);
            }
            $eventImageFile = '';
        }
    }
    
    $currentData['event'] = [
        'active' => $eventActive,
        'date_from' => $eventDateFrom,
        'date_to' => $eventDateTo,
        'image_file' => $eventImageFile
    ];

    // --- ULOŽENÍ FOTOGALERIE ---
    $gallery = $currentData['gallery'] ?? [];
    if (isset($_FILES['gallery_images'])) {
        $fileCount = is_array($_FILES['gallery_images']['name']) ? count($_FILES['gallery_images']['name']) : 0;
        for ($i = 0; $i < $fileCount; $i++) {
            if (count($gallery) >= 10) break; // Limit 10 fotek
            
            if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $filename = 'gallery-' . time() . '-' . $i . '.' . $ext;
                    if (processAndSaveImage($_FILES['gallery_images']['tmp_name'][$i], $ext, $uploadsDir . $filename, 1200)) {
                        $gallery[] = 'uploads/' . $filename;
                    }
                }
            }
        }
    }
    $currentData['gallery'] = $gallery;

    $jsonString = json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
    
    // PŘIDEJ TENTO ŘÁDEK: Vytvoření zálohy před přepisem
    copy($dataFile, $dataFile . '.bak');
    
    // ZABEZPEČENÍ: LOCK_EX proti souběžnému přepisu/ztrátě dat
    if (file_put_contents($dataFile, $jsonString, LOCK_EX) !== false) {
        header('Location: admin.php?saved=1');
        exit;
    } else {
        header('Location: admin.php?error=1');
        exit;
    }
}

$successMessage = '';
$errorMessage = '';

if (isset($_GET['setup_complete'])) {
    $successMessage = 'Heslo bylo úspěšně nastaveno! Vítejte v administraci.';
}
if (isset($_GET['saved'])) {
    $successMessage = 'Změny byly úspěšně uloženy!';
}
if (isset($_GET['password_changed'])) {
    $successMessage = 'Heslo bylo úspěšně změněno!';
}
if (isset($_GET['scrape'])) {
    if ($_GET['scrape'] === 'success') {
        $successMessage = 'Menu z menicka.cz bylo úspěšně staženo!';
    } else {
        $errorMessage = 'Nepodařilo se stáhnout menu. Zkontrolujte připojení.';
    }
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'date_invalid':
            $errorMessage = 'Datum "Od" musí být před datem "Do".';
            break;
        case 'invalid_image':
            $errorMessage = 'Nepovolený formát obrázku. Povolené jsou: JPG, PNG, WebP, GIF.';
            break;
        case 'invalid_image_content':
            $errorMessage = 'Soubor neodpovídá svému formátu. Nahrajte prosím platný obrázek.';
            break;
        case 'invalid_pdf':
            $errorMessage = 'Nahraný soubor není platné PDF.';
            break;
        case 'upload_failed':
            $errorMessage = 'Chyba při nahrávání souboru. Není PDF příliš velké?';
            break;
        case 'password_empty':
            $errorMessage = 'Všechna pole pro změnu hesla musí být vyplněna.';
            break;
        case 'password_wrong':
            $errorMessage = 'Současné heslo je nesprávné.';
            break;
        case 'password_mismatch':
            $errorMessage = 'Nová hesla se neshodují.';
            break;
        case 'password_weak':
            $errorMessage = 'Nové heslo musí mít alespoň 8 znaků.';
            break;
        case 'password_save_failed':
            $errorMessage = 'Chyba při ukládání nového hesla.';
            break;
        case 'csrf':
            $errorMessage = 'Neplatný CSRF token. Zkuste to prosím znovu.';
            break;
        default:
            $errorMessage = 'Chyba při zápisu do souboru data.json.';
    }
}

$data = loadData();

function val($array, $key1, $key2 = null, $key3 = null) {
    if ($key3 !== null) {
        return htmlspecialchars($array[$key1][$key2][$key3] ?? '', ENT_QUOTES, 'UTF-8');
    }
    if ($key2 !== null) {
        return htmlspecialchars($array[$key1][$key2] ?? '', ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($array[$key1] ?? '', ENT_QUOTES, 'UTF-8');
}

function isChecked($array, $key1, $key2, $key3) {
    return !empty($array[$key1][$key2][$key3]) ? 'checked' : '';
}

$openingHoursData = $data['opening_hours'] ?? [];
if (empty($openingHoursData)) {
    $openingHoursData = new stdClass();
}
// ZABEZPEČENÍ: Ochrana proti XSS přes vnořené HTML/script tagy v JSON datech
$openingHoursJson = json_encode($openingHoursData, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$exceptionsData = $data['exceptions'] ?? [];
if (empty($exceptionsData)) {
    $exceptionsData = new stdClass();
}
$exceptionsJson = json_encode($exceptionsData, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// Event data
$eventData = $data['event'] ?? ['active' => false, 'date_from' => '', 'date_to' => '', 'image_file' => ''];
$eventActive = !empty($eventData['active']);
$eventDateFrom = $eventData['date_from'] ?? '';
$eventDateTo = $eventData['date_to'] ?? '';
$eventImageFile = $eventData['image_file'] ?? '';

// Convert file path to data URL for preview in admin
$eventImagePreview = '';
if (!empty($eventImageFile) && file_exists(__DIR__ . '/' . $eventImageFile)) {
    $eventImagePreview = $eventImageFile . '?v=' . filemtime(__DIR__ . '/' . $eventImageFile);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Administrace | America Pod Věží</title>
    <link rel="stylesheet" href="output.css">
    <link rel="stylesheet" href="fa/css/fontawesome.min.css">
    <link rel="stylesheet" href="fa/css/solid.min.css">
    <style>
        input[type="date"], input[type="time"] {
            color-scheme: dark;
        }
        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            filter: none;
            opacity: 1;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white"><path d="M11 2V1h-1v1H6V1H5v1H1v14h14V2h-4zM2 15V5h12v10H2zm2-8h2v2H4V7zm3 0h2v2H7V7zm3 0h2v2h-2V7zM4 10h2v2H4v-2zm3 0h2v2H7v-2zm3 0h2v2h-2v-2z"/></svg>');
            background-size: 16px 16px;
            background-repeat: no-repeat;
            background-position: center;
            width: 20px;
            height: 20px;
        }
        input[type="time"]::-webkit-calendar-picker-indicator {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white"><path d="M8 1a7 7 0 110 14A7 7 0 018 1zm0 1a6 6 0 100 12A6 6 0 008 2zm-.5 2v4.414l3.043 3.043.707-.707L8.5 7.586V4h-1z"/></svg>');
            background-size: 16px 16px;
            background-repeat: no-repeat;
            background-position: center;
            width: 20px;
            height: 20px;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeInUp 0.4s ease-out;
        }
    </style>
</head>
<body class="bg-[#050505] text-white font-sans min-h-screen">

    <div class="w-full max-w-5xl mx-auto px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-12 pb-32">
        
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6 sm:mb-8 pb-4 sm:pb-6 border-b border-white/10">
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-heading font-bold tracking-widest uppercase text-brand-gold">
                <i class="fas fa-cog mr-2"></i> Administrace
            </h1>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <form method="POST" action="admin.php" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="run_scraper">
                    <button type="submit" class="inline-flex items-center gap-2 text-sm bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded transition text-white">
                        <i class="fas fa-sync-alt"></i> Načíst menu nyní
                    </button>
                </form>
                <a href="https://americapodvezi.cz" target="_blank" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-white transition">
                    <i class="fas fa-external-link-alt"></i> Zobrazit web
                </a>
                <a href="admin.php?logout=1" class="inline-flex items-center gap-2 text-sm text-red-400 hover:text-red-300 transition">
                    <i class="fas fa-sign-out-alt"></i> Odhlásit se
                </a>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="bg-green-900/50 border border-green-500 text-green-200 px-4 py-3 rounded-sm mb-6 flex items-center gap-3 animate-fade-in">
                <i class="fas fa-check-circle text-lg"></i>
                <span class="text-sm sm:text-base"><?= htmlspecialchars($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-sm mb-6 flex items-center gap-3 animate-fade-in">
                <i class="fas fa-exclamation-triangle text-lg"></i>
                <span class="text-sm sm:text-base"><?= htmlspecialchars($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden mb-6 sm:mb-8">
            <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                    <i class="fas fa-key text-brand-gold"></i> Změna hesla
                </h2>
            </div>
            <div class="p-4 sm:p-6">
                <form method="POST" action="admin.php" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Současné heslo</label>
                            <input type="password" name="current_password" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base" required>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Nové heslo</label>
                            <input type="password" name="new_password" minlength="8" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base" required>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Nové heslo znovu</label>
                            <input type="password" name="new_password_confirm" minlength="8" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full sm:w-auto bg-brand-gold text-black font-bold uppercase tracking-widest px-6 py-2.5 rounded-sm hover:bg-white transition text-sm">
                        <i class="fas fa-lock mr-2"></i> Změnit heslo
                    </button>
                    
                    <p class="text-xs text-gray-500 mt-2">Heslo musí mít minimálně 8 znaků. Použijte kombinaci písmen, čísel a speciálních znaků.</p>
                </form>
            </div>
        </section>

        <form method="POST" action="admin.php" class="space-y-6 sm:space-y-8" id="adminForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="save">
            
            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-camera text-brand-gold"></i> Fotogalerie
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <p class="text-gray-400 text-sm mb-4">Nahoře nahrajte fotky na web (max. 10 celkem). Fotky z mobilu se automaticky zmenší a zrychlí.</p>
                    
                    <?php 
                    $currentGalleryCount = count($data['gallery'] ?? []); 
                    $remaining = 10 - $currentGalleryCount;
                    ?>
                    
                    <?php if ($remaining > 0): ?>
                    <div class="mb-6">
                        <input type="file" name="gallery_images[]" accept="image/png, image/jpeg, image/jpg, image/webp" multiple class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-sm file:border-0 file:text-sm file:font-bold file:bg-brand-gold file:text-black hover:file:bg-white file:transition file:cursor-pointer bg-black/50 border border-white/20 rounded-sm cursor-pointer">
                        <p class="text-xs text-gray-500 mt-2">Můžete nahrát ještě <?= $remaining ?> fotek. (Můžete vybrat více souborů najednou).</p>
                    </div>
                    <?php else: ?>
                    <div class="mb-6 bg-yellow-900/30 border border-yellow-600/50 p-3 rounded-sm text-yellow-500 text-sm">
                        Dosažen maximální počet 10 fotek. Pro nahrání nových musíte nějaké smazat.
                    </div>
                    <?php endif; ?>

                    <?php if ($currentGalleryCount > 0): ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mt-4">
    <?php foreach (($data['gallery'] ?? []) as $index => $img): ?>
        <div class="relative aspect-square rounded-sm overflow-hidden border border-white/20 bg-black">
            <img src="<?= htmlspecialchars($img) ?>?v=<?= time() ?>" class="w-full h-full object-cover">
            <button type="button" onclick="deleteGalleryImage(<?= $index ?>)" class="absolute top-1 right-1 bg-red-600 hover:bg-red-700 text-white w-7 h-7 rounded-sm flex items-center justify-center transition shadow-xl z-20">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    <?php endforeach; ?>
</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-address-book text-brand-gold"></i> Kontakty
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 gap-4">
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 flex items-center gap-1">
                                <i class="fas fa-phone text-xs"></i> Telefon pro rezervace
                            </label>
                            <input type="text" name="contact_phone" value="<?= val($data, 'contact', 'phone') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 flex items-center gap-1">
                                <i class="fas fa-map-marker-alt text-xs"></i> Adresa
                            </label>
                            <input type="text" name="contact_address" value="<?= val($data, 'contact', 'address') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base">
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-utensils text-brand-gold"></i> Rozvoz & Denní Menu
                    </h2>
                </div>
                <div class="p-4 sm:p-6 space-y-4">
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 flex items-center gap-1">
                            <i class="fas fa-clipboard-list text-xs"></i> Denní Menu (URL)
                        </label>
                        <input type="url" name="daily_menu_url" value="<?= val($data, 'daily_menu_url') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none placeholder-gray-500 transition text-sm sm:text-base" placeholder="https://menicka.cz/...">
                    </div>
                    
                    <div class="bg-black/30 p-4 rounded-sm border border-white/5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <label class="text-brand-gold text-xs sm:text-sm uppercase tracking-widest font-bold flex items-center gap-2">
                                <i class="fas fa-motorcycle"></i> Wolt
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="delivery_wolt_enabled" <?= isChecked($data, 'delivery', 'wolt', 'enabled') ?> class="w-5 h-5 text-brand-gold bg-black/50 border-white/20 rounded focus:ring-brand-gold focus:ring-2">
                                <span class="text-xs text-gray-400">Zobrazovat na webu</span>
                            </label>
                        </div>
                        <input type="url" name="delivery_wolt_url" value="<?= val($data, 'delivery', 'wolt', 'url') ?>" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm" placeholder="https://wolt.com/...">
                    </div>

                    <div class="bg-black/30 p-4 rounded-sm border border-white/5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <label class="text-brand-gold text-xs sm:text-sm uppercase tracking-widest font-bold flex items-center gap-2">
                                <i class="fas fa-bicycle"></i> Foodora
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="delivery_foodora_enabled" <?= isChecked($data, 'delivery', 'foodora', 'enabled') ?> class="w-5 h-5 text-brand-gold bg-black/50 border-white/20 rounded focus:ring-brand-gold focus:ring-2">
                                <span class="text-xs text-gray-400">Zobrazovat na webu</span>
                            </label>
                        </div>
                        <input type="url" name="delivery_foodora_url" value="<?= val($data, 'delivery', 'foodora', 'url') ?>" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm" placeholder="https://www.foodora.cz/...">
                    </div>

                    <div class="bg-black/30 p-4 rounded-sm border border-white/5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <label class="text-brand-gold text-xs sm:text-sm uppercase tracking-widest font-bold flex items-center gap-2">
                                <i class="fas fa-bolt"></i> Bolt Food
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="delivery_bolt_enabled" <?= isChecked($data, 'delivery', 'bolt', 'enabled') ?> class="w-5 h-5 text-brand-gold bg-black/50 border-white/20 rounded focus:ring-brand-gold focus:ring-2">
                                <span class="text-xs text-gray-400">Zobrazovat na webu</span>
                            </label>
                        </div>
                        <input type="url" name="delivery_bolt_url" value="<?= val($data, 'delivery', 'bolt', 'url') ?>" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm" placeholder="https://food.bolt.eu/...">
                    </div>
                </div>
            </section>

            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-file-pdf text-brand-gold"></i> Stálé Menu (PDF)
                    </h2>
                </div>
                <div class="p-4 sm:p-6 space-y-4">
                    <p class="text-gray-400 text-sm">Zde můžete nahrát aktuální verzi stálého jídelního lístku ve formátu PDF.</p>
                    <div>
                        <input type="file" name="menu_pdf" accept="application/pdf" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-sm file:border-0 file:text-sm file:font-bold file:bg-brand-gold file:text-black hover:file:bg-white file:transition file:cursor-pointer bg-black/50 border border-white/20 rounded-sm cursor-pointer">
                    </div>
                    <?php if (file_exists(__DIR__ . '/menu.pdf')): ?>
                        <div class="mt-4 flex items-center gap-3 bg-black/30 p-3 rounded-sm border border-white/10">
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span class="text-sm text-gray-300">Aktuální menu nahráno: <?= date('d.m.Y H:i', filemtime(__DIR__ . '/menu.pdf')) ?></span>
                            <a href="menu.pdf?v=<?= time() ?>" target="_blank" class="text-brand-gold hover:text-white transition text-sm ml-auto font-bold uppercase tracking-widest">Zobrazit</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-clock text-brand-gold"></i> Otevírací Doba
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="mb-4">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-3 block">Vyberte dny</label>
                        <div id="daySelector" class="flex flex-wrap gap-2"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center gap-2 mb-3 cursor-pointer">
                            <input type="checkbox" id="closedCheckbox" class="w-5 h-5 text-brand-gold bg-black/50 border-white/20 rounded focus:ring-brand-gold focus:ring-2">
                            <span class="text-white font-bold">ZAVŘENO</span>
                        </label>
                    </div>
                    
                    <div id="timeInputs" class="grid grid-cols-2 gap-4 mb-4">
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Od</label>
                            <input type="time" id="timeFrom" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Do</label>
                            <input type="time" id="timeTo" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm">
                        </div>
                    </div>
                    
                    <button type="button" id="addHoursBtn" class="w-full sm:w-auto bg-brand-gold/20 hover:bg-brand-gold/30 border border-brand-gold text-brand-gold px-6 py-2.5 rounded-sm text-sm uppercase tracking-widest transition font-bold">
                        <i class="fas fa-plus mr-2"></i> Přidat
                    </button>
                    <div id="hoursPreview" class="mt-6 space-y-2"></div>
                    <input type="hidden" name="opening_hours_json" id="openingHoursJson" value="">
                </div>
            </section>

            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-brand-gold"></i> Výjimky
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Začátek</label>
                            <input type="date" id="exceptionDateFrom" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm cursor-pointer">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Konec</label>
                            <input type="date" id="exceptionDateTo" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm cursor-pointer">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center gap-2 mb-3 cursor-pointer">
                            <input type="checkbox" id="exceptionClosedCheckbox" class="w-5 h-5 text-brand-gold bg-black/50 border-white/20 rounded focus:ring-brand-gold focus:ring-2">
                            <span class="text-white font-bold">ZAVŘENO</span>
                        </label>
                    </div>
                    
                    <div id="exceptionTimeInputs" class="grid grid-cols-2 gap-4 mb-4">
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Od</label>
                            <input type="time" id="exceptionTimeFrom" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Do</label>
                            <input type="time" id="exceptionTimeTo" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm">
                        </div>
                    </div>
                    
                    <button type="button" id="addExceptionBtn" class="w-full sm:w-auto bg-brand-gold/20 hover:bg-brand-gold/30 border border-brand-gold text-brand-gold px-6 py-2.5 rounded-sm text-sm uppercase tracking-widest transition font-bold">
                        <i class="fas fa-plus mr-2"></i> Přidat Výjimku
                    </button>
                    <div id="exceptionsPreview" class="mt-6 space-y-2"></div>
                    <input type="hidden" name="exceptions_json" id="exceptionsJson" value="">
                </div>
            </section>

            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-bullhorn text-brand-gold"></i> Akce / Popup
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <p class="text-gray-400 text-sm mb-6 leading-relaxed">
                        Nahrajte leták akce (např. Vánoční menu, Silvestr apod.) a nastavte datum platnosti. Popup se zobrazí návštěvníkům automaticky <strong class="text-white">jen v nastaveném období</strong> a pouze jednou za návštěvu. <span class="text-brand-gold font-bold">Po deaktivaci akce se soubor automaticky smaže.</span>
                    </p>

                    <div class="space-y-6">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="event_active" id="eventActive" class="sr-only peer" <?= $eventActive ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-gold"></div>
                            <span class="ml-3 text-sm font-heading tracking-widest uppercase text-white">Zobrazit akci na webu</span>
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col">
                                <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Zobrazovat OD</label>
                                <input type="date" name="event_date_from" id="eventDateFrom" value="<?= $eventDateFrom ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm cursor-pointer">
                            </div>
                            <div class="flex flex-col">
                                <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Zobrazovat DO (včetně)</label>
                                <input type="date" name="event_date_to" id="eventDateTo" value="<?= $eventDateTo ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm cursor-pointer">
                            </div>
                        </div>

                        <div>
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 block">Obrázek letáku</label>
                            <input type="file" id="eventImageInput" accept="image/png, image/jpeg, image/jpg, image/webp" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-sm file:border-0 file:text-sm file:font-bold file:bg-brand-gold file:text-black hover:file:bg-white file:transition file:cursor-pointer bg-black/50 border border-white/20 rounded-sm cursor-pointer">
                            <p class="text-xs text-gray-500 mt-2">Podporované formáty: PNG, JPEG, WebP. Doporučený formát: na výšku nebo čtverec.</p>
                            <p class="text-xs text-gray-500">Velké obrázky budou automaticky zmenšeny na max. 1200px pro rychlé načítání.</p>
                            
                            <div class="mt-4 relative <?= $eventImagePreview ? '' : 'hidden' ?> w-full max-w-sm border border-white/20 rounded-sm overflow-hidden bg-black/30" id="eventPreviewContainer">
                                <img id="eventPreview" src="<?= $eventImagePreview ?>" alt="Náhled akce" class="w-full h-auto">
                                <div class="absolute top-2 right-2 flex gap-2">
                                    <button type="button" id="eventRemoveBtn" class="bg-red-500 text-white w-8 h-8 rounded-full flex items-center justify-center hover:bg-red-600 transition shadow-lg">
                                        <i class="fas fa-times text-sm"></i>
                                    </button>
                                </div>
                                <div class="absolute bottom-0 left-0 right-0 bg-black/80 p-2 text-xs text-gray-300" id="eventImageInfo"></div>
                            </div>

                            <input type="hidden" name="event_image_data" id="eventImageData" value="">
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-star text-brand-gold"></i> Hodnocení Google
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Průměrné hodnocení</label>
                            <input type="number" step="0.1" min="1" max="5" name="rating_value" value="<?= val($data, 'rating', 'value') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm sm:text-base">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Počet recenzí</label>
                            <input type="number" name="rating_count" value="<?= val($data, 'rating', 'count') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm sm:text-base">
                        </div>
                    </div>
                </div>
            </section>

        </form>
    </div>

    <div class="fixed bottom-8 right-8 z-50">
        <button type="submit" form="adminForm" class="group bg-brand-gold hover:bg-white text-black font-bold font-heading py-4 px-8 rounded-full uppercase tracking-widest transition-all duration-300 shadow-[0_8px_30px_rgba(212,163,115,0.5)] hover:shadow-[0_12px_40px_rgba(212,163,115,0.7)] hover:scale-105 flex items-center gap-3">
            <i class="fas fa-save text-lg group-hover:rotate-12 transition-transform duration-300"></i>
            <span class="hidden sm:inline">Uložit</span>
        </button>
    </div>

    <script>
    const dayNames = {'monday': 'Pondělí', 'tuesday': 'Úterý', 'wednesday': 'Středa', 'thursday': 'Čtvrtek', 'friday': 'Pátek', 'saturday': 'Sobota', 'sunday': 'Neděle'};
    const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    let availableDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    let selectedDays = [];
    
    let openingHoursData = <?= $openingHoursJson ?>;
    if (Array.isArray(openingHoursData)) openingHoursData = {};
    
    let exceptionsData = <?= $exceptionsJson ?>;
    if (Array.isArray(exceptionsData)) exceptionsData = {};

    const closedCheckbox = document.getElementById('closedCheckbox');
    const timeInputs = document.getElementById('timeInputs');
    const timeFrom = document.getElementById('timeFrom');
    const timeTo = document.getElementById('timeTo');

    closedCheckbox.addEventListener('change', function() {
        timeInputs.style.display = this.checked ? 'none' : 'grid';
        if (this.checked) {
            timeFrom.value = '';
            timeTo.value = '';
        }
    });

    const exceptionClosedCheckbox = document.getElementById('exceptionClosedCheckbox');
    const exceptionTimeInputs = document.getElementById('exceptionTimeInputs');
    const exceptionTimeFrom = document.getElementById('exceptionTimeFrom');
    const exceptionTimeTo = document.getElementById('exceptionTimeTo');

    exceptionClosedCheckbox.addEventListener('change', function() {
        exceptionTimeInputs.style.display = this.checked ? 'none' : 'grid';
        if (this.checked) {
            exceptionTimeFrom.value = '';
            exceptionTimeTo.value = '';
        }
    });

    function expandDayRange(key) {
        const parts = key.split('_');
        if (parts.length === 1) return parts;
        const startIdx = dayOrder.indexOf(parts[0]);
        const endIdx = dayOrder.indexOf(parts[parts.length - 1]);
        if (startIdx === -1 || endIdx === -1 || startIdx > endIdx) return parts;
        return dayOrder.slice(startIdx, endIdx + 1);
    }

    function fillGaps() {
        if (selectedDays.length < 2) return;
        selectedDays.sort((a, b) => dayOrder.indexOf(a) - dayOrder.indexOf(b));
        const firstIdx = dayOrder.indexOf(selectedDays[0]);
        const lastIdx = dayOrder.indexOf(selectedDays[selectedDays.length - 1]);
        const fullRange = dayOrder.slice(firstIdx, lastIdx + 1);
        fullRange.forEach(day => {
            if (availableDays.includes(day) && !selectedDays.includes(day)) {
                selectedDays.push(day);
            }
        });
        selectedDays.sort((a, b) => dayOrder.indexOf(a) - dayOrder.indexOf(b));
    }

    function initializeEditor() {
        const usedDays = new Set();
        Object.keys(openingHoursData).forEach(key => {
            const days = expandDayRange(key);
            days.forEach(day => usedDays.add(day));
        });
        availableDays = availableDays.filter(day => !usedDays.has(day));
        renderDaySelector();
        renderPreview();
        syncJsonInput();
        renderExceptionsPreview();
        syncExceptionsJson();
    }

    function renderDaySelector() {
        const selector = document.getElementById('daySelector');
        selector.innerHTML = '';
        if (availableDays.length === 0) {
            selector.innerHTML = '<span class="text-gray-500 text-sm">Všechny dny nastaveny</span>';
            return;
        }
        availableDays.forEach(day => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = dayNames[day];
            btn.className = selectedDays.includes(day) 
                ? 'px-3 py-2 bg-brand-gold text-black rounded-sm text-xs sm:text-sm font-bold cursor-pointer transition'
                : 'px-3 py-2 bg-black/50 border border-white/20 text-white rounded-sm text-xs sm:text-sm hover:border-brand-gold cursor-pointer transition';
            btn.onclick = () => toggleDay(day);
            selector.appendChild(btn);
        });
    }

    function toggleDay(day) {
        if (selectedDays.includes(day)) {
            const clickedIdx = dayOrder.indexOf(day);
            selectedDays = selectedDays.filter(d => dayOrder.indexOf(d) < clickedIdx);
        } else {
            selectedDays.push(day);
            fillGaps();
        }
        renderDaySelector();
    }

    function renderPreview() {
        const preview = document.getElementById('hoursPreview');
        preview.innerHTML = '';
        if (Object.keys(openingHoursData).length === 0) {
            preview.innerHTML = '<p class="text-gray-500 text-sm">Zatím nejsou nastaveny hodiny</p>';
            return;
        }
        Object.entries(openingHoursData).forEach(([key, value]) => {
            const days = key.split('_').map(d => dayNames[d]).join(' - ');
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between bg-black/30 p-3 rounded-sm border border-white/5';
            item.innerHTML = `<div class="flex-1 min-w-0"><span class="text-brand-gold font-bold text-xs sm:text-sm uppercase block sm:inline">${days}</span><span class="text-white text-sm sm:text-base sm:ml-3 block sm:inline mt-1 sm:mt-0">${value}</span></div><button type="button" onclick="removeHours('${key}')" class="text-red-400 hover:text-red-300 transition ml-3 flex-shrink-0"><i class="fas fa-times"></i></button>`;
            preview.appendChild(item);
        });
    }

    window.removeHours = function(key) {
        const days = expandDayRange(key);
        availableDays.push(...days);
        availableDays.sort((a, b) => dayOrder.indexOf(a) - dayOrder.indexOf(b));
        delete openingHoursData[key];
        renderDaySelector();
        renderPreview();
        syncJsonInput();
    };

    document.getElementById('addHoursBtn').onclick = function() {
        if (selectedDays.length === 0) { 
            alert('Vyberte den'); 
            return; 
        }
        
        let timeString;
        if (closedCheckbox.checked) {
            timeString = 'ZAVŘENO';
        } else {
            const from = timeFrom.value;
            const to = timeTo.value;
            if (!from || !to) { 
                alert('Vyplňte čas'); 
                return; 
            }
            timeString = `${from} - ${to}`;
        }
        
        selectedDays.sort((a, b) => dayOrder.indexOf(a) - dayOrder.indexOf(b));
        const key = selectedDays.length > 1 ? `${selectedDays[0]}_${selectedDays[selectedDays.length - 1]}` : selectedDays[0];
        openingHoursData[key] = timeString;
        
        availableDays = availableDays.filter(day => !selectedDays.includes(day));
        selectedDays = [];
        timeFrom.value = '';
        timeTo.value = '';
        closedCheckbox.checked = false;
        timeInputs.style.display = 'grid';
        renderDaySelector();
        renderPreview();
        syncJsonInput();
    };

    function syncJsonInput() {
        document.getElementById('openingHoursJson').value = JSON.stringify(openingHoursData);
    }

    function formatDateForDisplay(dateStr) {
        const parts = dateStr.split('-');
        const day = parseInt(parts[2], 10);
        const month = parseInt(parts[1], 10);
        return `${day}.${month}.`;
    }

    function renderExceptionsPreview() {
        const preview = document.getElementById('exceptionsPreview');
        preview.innerHTML = '';
        if (Object.keys(exceptionsData).length === 0) {
            preview.innerHTML = '<p class="text-gray-500 text-sm">Zatím nejsou žádné výjimky</p>';
            return;
        }
        Object.entries(exceptionsData).forEach(([key, value]) => {
            const [from, to] = key.split('_');
            const fromDisplay = formatDateForDisplay(from);
            const toDisplay = formatDateForDisplay(to);
            // Pokud jsou data stejná, zobraz jen jednou
            const dateDisplay = fromDisplay === toDisplay ? fromDisplay : `${fromDisplay} – ${toDisplay}`;
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between bg-black/30 p-3 rounded-sm border border-white/5';
            item.innerHTML = `<div class="flex-1 min-w-0"><span class="text-brand-gold font-bold text-xs sm:text-sm block sm:inline">${dateDisplay}</span><span class="text-white text-sm sm:text-base sm:ml-3 block sm:inline mt-1 sm:mt-0">${value}</span></div><button type="button" onclick="removeException('${key}')" class="text-red-400 hover:text-red-300 transition ml-3 flex-shrink-0"><i class="fas fa-times"></i></button>`;
            preview.appendChild(item);
        });
    }

    window.removeException = function(key) {
        delete exceptionsData[key];
        renderExceptionsPreview();
        syncExceptionsJson();
    };

    document.getElementById('addExceptionBtn').onclick = function() {
        const from = document.getElementById('exceptionDateFrom').value;
        const to = document.getElementById('exceptionDateTo').value;
        
        if (!from || !to) { 
            alert('Vyberte oba datumy'); 
            return; 
        }
        if (from > to) { 
            alert('Datum "Od" musí být před "Do"'); 
            return; 
        }
        
        let timeString;
        if (exceptionClosedCheckbox.checked) {
            timeString = 'ZAVŘENO';
        } else {
            const timeFromVal = exceptionTimeFrom.value;
            const timeToVal = exceptionTimeTo.value;
            if (!timeFromVal || !timeToVal) { 
                alert('Vyplňte čas'); 
                return; 
            }
            timeString = `${timeFromVal} - ${timeToVal}`;
        }
        
        const key = `${from}_${to}`;
        exceptionsData[key] = timeString;
        
        document.getElementById('exceptionDateFrom').value = '';
        document.getElementById('exceptionDateTo').value = '';
        exceptionTimeFrom.value = '';
        exceptionTimeTo.value = '';
        exceptionClosedCheckbox.checked = false;
        exceptionTimeInputs.style.display = 'grid';
        
        renderExceptionsPreview();
        syncExceptionsJson();
    };

    function syncExceptionsJson() {
        document.getElementById('exceptionsJson').value = JSON.stringify(exceptionsData);
    }

    // FOTOGALERIE MAZÁNÍ
    window.deleteGalleryImage = function(index) {
        if(!confirm('Opravdu smazat tuto fotku z galerie?')) return;
        const form = document.getElementById('adminForm');
        form.querySelector('input[name="action"]').value = 'delete_gallery';
        const idxInput = document.createElement('input');
        idxInput.type = 'hidden';
        idxInput.name = 'image_index';
        idxInput.value = index;
        form.appendChild(idxInput);
        form.submit();
    };

    // ===== EVENT IMAGE HANDLER (FILE-BASED) =====
    const eventImageInput = document.getElementById('eventImageInput');
    const eventPreviewContainer = document.getElementById('eventPreviewContainer');
    const eventPreview = document.getElementById('eventPreview');
    const eventImageData = document.getElementById('eventImageData');
    const eventRemoveBtn = document.getElementById('eventRemoveBtn');
    const eventImageInfo = document.getElementById('eventImageInfo');

    eventImageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Check file type
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Neplatný formát obrázku. Použijte PNG, JPEG nebo WebP.');
            eventImageInput.value = '';
            return;
        }

        // Check file size (max 10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('Obrázek je příliš velký (max 10MB).');
            eventImageInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(event) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                const maxDim = 1200;

                // Calculate new dimensions
                if (width > height) {
                    if (width > maxDim) {
                        height = Math.round((height * maxDim) / width);
                        width = maxDim;
                    }
                } else {
                    if (height > maxDim) {
                        width = Math.round((width * maxDim) / height);
                        height = maxDim;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                // Determine output format and quality
                let outputFormat = 'image/jpeg';
                let quality = 0.85;
                
                if (file.type === 'image/png') {
                    outputFormat = 'image/png';
                    quality = 0.9;
                } else if (file.type === 'image/webp') {
                    outputFormat = 'image/webp';
                    quality = 0.85;
                }

                const base64 = canvas.toDataURL(outputFormat, quality);
                
                // Calculate final size
                const sizeKB = Math.round((base64.length * 3) / 4 / 1024);
                
                // Store base64 temporarily for form submission
                eventImageData.value = base64;
                eventPreview.src = base64;
                eventPreviewContainer.classList.remove('hidden');
                eventImageInfo.textContent = `${width}×${height}px • ${sizeKB} KB • Bude uloženo jako soubor`;
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });

    eventRemoveBtn.addEventListener('click', function() {
        if (confirm('Opravdu chcete odstranit obrázek akce?')) {
            eventImageData.value = '';
            eventPreview.src = '';
            eventPreviewContainer.classList.add('hidden');
            eventImageInput.value = '';
            eventImageInfo.textContent = '';
        }
    });

    // Date validation for events
    const eventDateFrom = document.getElementById('eventDateFrom');
    const eventDateTo = document.getElementById('eventDateTo');

    eventDateFrom.addEventListener('change', function() {
        if (eventDateTo.value && this.value > eventDateTo.value) {
            alert('Datum "Od" musí být před datem "Do".');
            this.value = '';
        }
    });

    eventDateTo.addEventListener('change', function() {
        if (eventDateFrom.value && this.value < eventDateFrom.value) {
            alert('Datum "Do" musí být po datu "Od".');
            this.value = '';
        }
    });

    if (window.location.search.includes('saved=') || window.location.search.includes('error=') || window.location.search.includes('password_changed=') || window.location.search.includes('setup_complete=') || window.location.search.includes('scrape=')) {
        setTimeout(function() {
            const url = new URL(window.location);
            url.searchParams.delete('saved');
            url.searchParams.delete('error');
            url.searchParams.delete('password_changed');
            url.searchParams.delete('setup_complete');
            url.searchParams.delete('scrape');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }, 2500);
    }

    initializeEditor();
    </script>

</body>
</html>