<?php
$dataFile = __DIR__ . '/data.json';

// 1. ZPRACOVÁNÍ FORMULÁŘE (ULOŽENÍ)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentData = [];
    if (file_exists($dataFile)) {
        $currentData = json_decode(file_get_contents($dataFile), true) ?: [];
    }

    $currentData['contact']['phone'] = $_POST['contact_phone'] ?? '';
    $currentData['contact']['phone_alt'] = $_POST['contact_phone_alt'] ?? '';
    $currentData['contact']['email'] = $_POST['contact_email'] ?? '';
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

    // Otevírací doba
    $openingHoursJson = $_POST['opening_hours_json'] ?? '{}';
    $currentData['opening_hours'] = json_decode($openingHoursJson, true) ?: [];

    // Výjimky (speciální dny)
    $exceptionsJson = $_POST['exceptions_json'] ?? '{}';
    $currentData['exceptions'] = json_decode($exceptionsJson, true) ?: [];

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
</head>
<body class="bg-[#050505] text-white font-sans min-h-screen p-4 md:p-8">

    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-8 border-b border-white/10 pb-4">
            <h1 class="text-3xl font-heading font-bold tracking-widest uppercase text-brand-gold">
                <i class="fas fa-cog mr-2"></i> Administrace Webu
            </h1>
            <a href="index.php" target="_blank" class="text-gray-400 hover:text-white transition text-sm">
                <i class="fas fa-external-link-alt"></i> Zobrazit web
            </a>
        </div>

        <?php if ($successMessage): ?>
            <div class="bg-green-900/50 border border-green-500 text-green-200 px-4 py-3 rounded mb-6 flex items-center gap-3">
                <i class="fas fa-check-circle"></i> <?= $successMessage ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded mb-6 flex items-center gap-3">
                <i class="fas fa-exclamation-triangle"></i> <?= $errorMessage ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin.php" class="space-y-8" id="adminForm">
            
            <!-- KONTAKTY -->
            <div class="bg-white/5 border border-white/10 p-6 rounded-sm shadow-xl">
                <h2 class="text-xl font-heading text-white tracking-wider uppercase mb-4 border-b border-white/10 pb-2">Kontakty</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">Hlavní Telefon</label>
                        <input type="text" name="contact_phone" value="<?= val($data, 'contact', 'phone') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">Alternativní Telefon</label>
                        <input type="text" name="contact_phone_alt" value="<?= val($data, 'contact', 'phone_alt') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">E-mail</label>
                        <input type="email" name="contact_email" value="<?= val($data, 'contact', 'email') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">Adresa</label>
                        <input type="text" name="contact_address" value="<?= val($data, 'contact', 'address') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- ODKAZY -->
            <div class="bg-white/5 border border-white/10 p-6 rounded-sm shadow-xl">
                <h2 class="text-xl font-heading text-white tracking-wider uppercase mb-4 border-b border-white/10 pb-2">Rozvoz & Menu</h2>
                <div class="space-y-4">
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">Denní Menu</label>
                        <input type="url" name="daily_menu_url" value="<?= val($data, 'daily_menu_url') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none placeholder-gray-600" placeholder="https://...">
                    </div>
                    
                    <div class="bg-black/30 p-4 rounded border border-white/5">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-brand-gold text-sm uppercase tracking-widest font-bold">Wolt</label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="delivery_wolt_enabled" <?= isChecked($data, 'delivery', 'wolt', 'enabled') ?> class="w-5 h-5 text-brand-gold bg-black/50 border-white/20 rounded focus:ring-brand-gold focus:ring-2">
                                <span class="text-xs text-gray-400">Zobrazovat</span>
                            </label>
                        </div>
                        <input type="url" name="delivery_wolt_url" value="<?= val($data, 'delivery', 'wolt', 'url') ?>" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none" placeholder="https://wolt.com/...">
                    </div>

                    <div class="bg-black/30 p-4 rounded border border-white/5">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-brand-gold text-sm uppercase tracking-widest font-bold">Foodora</label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="delivery_foodora_enabled" <?= isChecked($data, 'delivery', 'foodora', 'enabled') ?> class="w-5 h-5 text-brand-gold bg-black/50 border-white/20 rounded focus:ring-brand-gold focus:ring-2">
                                <span class="text-xs text-gray-400">Zobrazovat</span>
                            </label>
                        </div>
                        <input type="url" name="delivery_foodora_url" value="<?= val($data, 'delivery', 'foodora', 'url') ?>" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none" placeholder="https://www.foodora.cz/...">
                    </div>

                    <div class="bg-black/30 p-4 rounded border border-white/5">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-brand-gold text-sm uppercase tracking-widest font-bold">Bolt Food</label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="delivery_bolt_enabled" <?= isChecked($data, 'delivery', 'bolt', 'enabled') ?> class="w-5 h-5 text-brand-gold bg-black/50 border-white/20 rounded focus:ring-brand-gold focus:ring-2">
                                <span class="text-xs text-gray-400">Zobrazovat</span>
                            </label>
                        </div>
                        <input type="url" name="delivery_bolt_url" value="<?= val($data, 'delivery', 'bolt', 'url') ?>" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none" placeholder="https://food.bolt.eu/...">
                    </div>
                </div>
            </div>

            <!-- OTEVÍRACÍ DOBA -->
            <div class="bg-white/5 border border-white/10 p-6 rounded-sm shadow-xl">
                <h2 class="text-xl font-heading text-white tracking-wider uppercase mb-4 border-b border-white/10 pb-2">Otevírací Doba</h2>
                <div class="mb-4">
                    <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-2 block">Vyberte dny</label>
                    <div id="daySelector" class="flex flex-wrap gap-2"></div>
                </div>
                <div class="mb-4">
                    <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1 block">Otevírací doba</label>
                    <input type="text" id="timeInput" placeholder="11:00 - 22:00 nebo ZAVŘENO" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none placeholder-gray-600">
                </div>
                <button type="button" id="addHoursBtn" class="bg-brand-gold/20 hover:bg-brand-gold/30 border border-brand-gold text-brand-gold px-4 py-2 rounded-sm text-sm uppercase tracking-widest transition">
                    <i class="fas fa-plus mr-2"></i> Přidat
                </button>
                <div id="hoursPreview" class="mt-6 space-y-2"></div>
                <input type="hidden" name="opening_hours_json" id="openingHoursJson" value="">
            </div>

            <!-- VÝJIMKY (SPECIÁLNÍ DNY) -->
            <div class="bg-white/5 border border-white/10 p-6 rounded-sm shadow-xl">
                <h2 class="text-xl font-heading text-white tracking-wider uppercase mb-4 border-b border-white/10 pb-2">
                    <i class="fas fa-calendar-alt mr-2"></i> Výjimky (Svátky, Speciální Dny)
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">Datum Od</label>
                        <input type="date" id="exceptionDateFrom" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">Datum Do</label>
                        <input type="date" id="exceptionDateTo" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1 block">Otevírací doba</label>
                    <input type="text" id="exceptionTimeInput" placeholder="ZAVŘENO nebo 10:00 - 16:00" class="w-full bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none placeholder-gray-600">
                </div>

                <button type="button" id="addExceptionBtn" class="bg-brand-gold/20 hover:bg-brand-gold/30 border border-brand-gold text-brand-gold px-4 py-2 rounded-sm text-sm uppercase tracking-widest transition">
                    <i class="fas fa-plus mr-2"></i> Přidat Výjimku
                </button>

                <div id="exceptionsPreview" class="mt-6 space-y-2"></div>
                <input type="hidden" name="exceptions_json" id="exceptionsJson" value="">
            </div>

            <!-- HODNOCENÍ GOOGLE -->
            <div class="bg-white/5 border border-white/10 p-6 rounded-sm shadow-xl">
                <h2 class="text-xl font-heading text-white tracking-wider uppercase mb-4 border-b border-white/10 pb-2">Hodnocení (Google)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">Průměr</label>
                        <input type="number" step="0.1" min="1" max="5" name="rating_value" value="<?= val($data, 'rating', 'value') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-brand-gold text-[10px] uppercase tracking-widest mb-1">Počet</label>
                        <input type="number" name="rating_count" value="<?= val($data, 'rating', 'count') ?>" class="bg-black/50 border border-white/20 text-white px-3 py-2 rounded-sm focus:border-brand-gold focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="text-right pb-10">
                <button type="submit" class="bg-brand-gold text-black font-bold font-heading py-3 px-8 text-lg rounded-sm hover:bg-white transition uppercase tracking-widest shadow-[0_0_15px_rgba(212,163,115,0.4)]">
                    <i class="fas fa-save mr-2"></i> Uložit Změny
                </button>
            </div>

        </form>
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
                ? 'px-3 py-2 bg-brand-gold text-black rounded-sm text-sm font-bold cursor-pointer transition'
                : 'px-3 py-2 bg-black/50 border border-white/20 text-white rounded-sm text-sm hover:border-brand-gold cursor-pointer transition';
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
            item.className = 'flex items-center justify-between bg-black/30 p-3 rounded border border-white/5';
            item.innerHTML = `<div><span class="text-brand-gold font-bold text-sm uppercase">${days}</span><span class="text-white ml-3">${value}</span></div><button type="button" onclick="removeHours('${key}')" class="text-red-400 hover:text-red-300 transition"><i class="fas fa-times"></i></button>`;
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

    // VÝJIMKY
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
            item.className = 'flex items-center justify-between bg-black/30 p-3 rounded border border-white/5';
            item.innerHTML = `<div><span class="text-brand-gold font-bold text-sm">${from} – ${to}</span><span class="text-white ml-3">${value}</span></div><button type="button" onclick="removeException('${key}')" class="text-red-400 hover:text-red-300 transition"><i class="fas fa-times"></i></button>`;
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