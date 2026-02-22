<?php
$dataFile = __DIR__ . '/data.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentData = [];
    if (file_exists($dataFile)) {
        $currentData = json_decode(file_get_contents($dataFile), true) ?: [];
    }

    $currentData['contact']['phone'] = $_POST['contact_phone'] ?? '';
    $currentData['contact']['phone_alt'] = $_POST['contact_phone_alt'] ?? '';
    $currentData['contact']['email'] = $_POST['contact_email'] ?? '';
    $currentData['contact']['email_reservation'] = $_POST['contact_email_reservation'] ?? '';
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

    $openingHoursJson = $_POST['opening_hours_json'] ?? '{}';
    $openingHoursDecoded = json_decode($openingHoursJson, true);
    $currentData['opening_hours'] = (is_array($openingHoursDecoded) && !empty($openingHoursDecoded)) ? $openingHoursDecoded : ($currentData['opening_hours'] ?? []);

    $exceptionsJson = $_POST['exceptions_json'] ?? '';
    if (!empty($exceptionsJson)) {
        $exceptionsDecoded = json_decode($exceptionsJson, true);
        if (is_array($exceptionsDecoded)) {
            $currentData['exceptions'] = $exceptionsDecoded;
        }
    } else {
        if (!isset($currentData['exceptions'])) {
            $currentData['exceptions'] = [];
        }
    }

    $jsonString = json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents($dataFile, $jsonString) !== false) {
        header('Location: admin.php?saved=1');
        exit;
    } else {
        header('Location: admin.php?error=1');
        exit;
    }
}

$successMessage = '';
$errorMessage = '';

if (isset($_GET['saved'])) {
    $successMessage = 'Změny byly úspěšně uloženy!';
}
if (isset($_GET['error'])) {
    $errorMessage = 'Chyba při zápisu do souboru data.json.';
}

$data = [];
if (file_exists($dataFile)) {
    $data = json_decode(file_get_contents($dataFile), true) ?: [];
}

function val($array, $key1, $key2 = null, $key3 = null) {
    if ($key3 !== null) {
        return htmlspecialchars($array[$key1][$key2][$key3] ?? '');
    }
    if ($key2 !== null) {
        return htmlspecialchars($array[$key1][$key2] ?? '');
    }
    return htmlspecialchars($array[$key1] ?? '');
}

function isChecked($array, $key1, $key2, $key3) {
    return !empty($array[$key1][$key2][$key3]) ? 'checked' : '';
}

$openingHoursJson = json_encode($data['opening_hours'] ?? [], JSON_UNESCAPED_UNICODE);
$exceptionsJson = json_encode($data['exceptions'] ?? [], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrace | America Pod Věží</title>
    <link rel="stylesheet" href="output.css">
    <link rel="stylesheet" href="fa/css/fontawesome.min.css">
    <link rel="stylesheet" href="fa/css/solid.min.css">
    <style>
        input[type="date"] {
            color-scheme: dark;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            filter: invert(1);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in {
            animation: fadeInUp 0.4s ease-out;
        }
    </style>
</head>
<body class="bg-[#050505] text-white font-sans min-h-screen">

    <div class="w-full max-w-5xl mx-auto px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-12 pb-32">
        
        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6 sm:mb-8 pb-4 sm:pb-6 border-b border-white/10">
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-heading font-bold tracking-widest uppercase text-brand-gold">
                <i class="fas fa-cog mr-2"></i> Administrace
            </h1>
            <a href="index.php" target="_blank" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-white transition">
                <i class="fas fa-external-link-alt"></i> Zobrazit web
            </a>
        </div>

        <!-- SUCCESS/ERROR MESSAGES -->
        <?php if ($successMessage): ?>
            <div class="bg-green-900/50 border border-green-500 text-green-200 px-4 py-3 rounded-sm mb-6 flex items-center gap-3 animate-fade-in">
                <i class="fas fa-check-circle text-lg"></i>
                <span class="text-sm sm:text-base"><?= $successMessage ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-sm mb-6 flex items-center gap-3 animate-fade-in">
                <i class="fas fa-exclamation-triangle text-lg"></i>
                <span class="text-sm sm:text-base"><?= $errorMessage ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin.php" class="space-y-6 sm:space-y-8" id="adminForm">
            
            <!-- KONTAKTY -->
            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-address-book text-brand-gold"></i> Kontakty
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 flex items-center gap-1">
                                <i class="fas fa-phone text-xs"></i> Hlavní Telefon
                            </label>
                            <input type="text" name="contact_phone" value="<?= val($data, 'contact', 'phone') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 flex items-center gap-1">
                                <i class="fas fa-phone text-xs"></i> Alternativní Telefon
                            </label>
                            <input type="text" name="contact_phone_alt" value="<?= val($data, 'contact', 'phone_alt') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 flex items-center gap-1">
                                <i class="fas fa-envelope text-xs"></i> E-mail
                            </label>
                            <input type="email" name="contact_email" value="<?= val($data, 'contact', 'email') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 flex items-center gap-1">
                                <i class="fas fa-calendar-check text-xs"></i> E-mail na Rezervace
                            </label>
                            <input type="email" name="contact_email_reservation" value="<?= val($data, 'contact', 'email_reservation') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base" placeholder="rezervace@...">
                        </div>
                        <div class="flex flex-col sm:col-span-2">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 flex items-center gap-1">
                                <i class="fas fa-map-marker-alt text-xs"></i> Adresa
                            </label>
                            <input type="text" name="contact_address" value="<?= val($data, 'contact', 'address') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none transition text-sm sm:text-base">
                        </div>
                    </div>
                </div>
            </section>

            <!-- ROZVOZ & MENU -->
            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-utensils text-brand-gold"></i> Rozvoz & Menu
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

            <!-- OTEVÍRACÍ DOBA -->
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
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 block">Otevírací doba</label>
                        <input type="text" id="timeInput" placeholder="11:00 - 22:00 nebo ZAVŘENO" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none placeholder-gray-500 transition text-sm sm:text-base">
                    </div>
                    <button type="button" id="addHoursBtn" class="w-full sm:w-auto bg-brand-gold/20 hover:bg-brand-gold/30 border border-brand-gold text-brand-gold px-6 py-2.5 rounded-sm text-sm uppercase tracking-widest transition font-bold">
                        <i class="fas fa-plus mr-2"></i> Přidat
                    </button>
                    <div id="hoursPreview" class="mt-6 space-y-2"></div>
                    <input type="hidden" name="opening_hours_json" id="openingHoursJson" value="">
                </div>
            </section>

            <!-- VÝJIMKY -->
            <section class="bg-white/5 border border-white/10 rounded-sm shadow-2xl overflow-hidden">
                <div class="bg-white/5 px-4 sm:px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg sm:text-xl font-heading text-white tracking-wider uppercase flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-brand-gold"></i> Výjimky (Svátky)
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Datum Od</label>
                            <input type="date" id="exceptionDateFrom" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm sm:text-base">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2">Datum Do</label>
                            <input type="date" id="exceptionDateTo" class="bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none text-sm sm:text-base">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 block">Otevírací doba</label>
                        <input type="text" id="exceptionTimeInput" placeholder="ZAVŘENO nebo 10:00 - 16:00" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2.5 rounded-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none placeholder-gray-500 transition text-sm sm:text-base">
                    </div>
                    <button type="button" id="addExceptionBtn" class="w-full sm:w-auto bg-brand-gold/20 hover:bg-brand-gold/30 border border-brand-gold text-brand-gold px-6 py-2.5 rounded-sm text-sm uppercase tracking-widest transition font-bold">
                        <i class="fas fa-plus mr-2"></i> Přidat Výjimku
                    </button>
                    <div id="exceptionsPreview" class="mt-6 space-y-2"></div>
                    <input type="hidden" name="exceptions_json" id="exceptionsJson" value="">
                </div>
            </section>

            <!-- HODNOCENÍ -->
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

    <!-- FIXED FLOATING SAVE BUTTON (vždy viditelný) -->
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
    let exceptionsData = <?= $exceptionsJson ?>;

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
        if (selectedDays.length === 0) { alert('Vyberte den'); return; }
        const time = document.getElementById('timeInput').value.trim();
        if (!time) { alert('Vyplňte čas'); return; }
        selectedDays.sort((a, b) => dayOrder.indexOf(a) - dayOrder.indexOf(b));
        const key = selectedDays.join('_');
        openingHoursData[key] = time;
        availableDays = availableDays.filter(day => !selectedDays.includes(day));
        selectedDays = [];
        document.getElementById('timeInput').value = '';
        renderDaySelector();
        renderPreview();
        syncJsonInput();
    };

    function syncJsonInput() {
        document.getElementById('openingHoursJson').value = JSON.stringify(openingHoursData);
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
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between bg-black/30 p-3 rounded-sm border border-white/5';
            item.innerHTML = `<div class="flex-1 min-w-0"><span class="text-brand-gold font-bold text-xs sm:text-sm block sm:inline">${from} – ${to}</span><span class="text-white text-sm sm:text-base sm:ml-3 block sm:inline mt-1 sm:mt-0">${value}</span></div><button type="button" onclick="removeException('${key}')" class="text-red-400 hover:text-red-300 transition ml-3 flex-shrink-0"><i class="fas fa-times"></i></button>`;
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
        const time = document.getElementById('exceptionTimeInput').value.trim();
        
        if (!from || !to) { alert('Vyberte oba datumy'); return; }
        if (!time) { alert('Vyplňte otevírací dobu'); return; }
        if (from > to) { alert('Datum "Od" musí být před "Do"'); return; }
        
        const key = `${from}_${to}`;
        exceptionsData[key] = time;
        
        document.getElementById('exceptionDateFrom').value = '';
        document.getElementById('exceptionDateTo').value = '';
        document.getElementById('exceptionTimeInput').value = '';
        
        renderExceptionsPreview();
        syncExceptionsJson();
    };

    function syncExceptionsJson() {
        document.getElementById('exceptionsJson').value = JSON.stringify(exceptionsData);
    }

    if (window.location.search.includes('saved=') || window.location.search.includes('error=')) {
        setTimeout(function() {
            const url = new URL(window.location);
            url.searchParams.delete('saved');
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }, 2500);
    }

    initializeEditor();
    </script>

</body>
</html>