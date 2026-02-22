# 🍔 America Pod Věží - Oficiální Web

Moderní responzivní web pro restauraci America Pod Věží v Mladé Boleslavi.

## ✨ Hlavní Funkce

- 📱 **Plně responzivní** - optimalizováno pro všechna zařízení
- 🍽️ **Interaktivní menu** - procházení jídelního lístku s animacemi
- 📅 **Automatické denní menu** - scrapování z menicka.cz s navigací mezi dny
- 🗺️ **Google Maps integrace** - s GDPR consent
- 🎨 **Moderní design** - Tailwind CSS, animace, paralax efekty
- ⚡ **Výkonná optimalizace** - WebP obrázky, lazy loading, GPU akcelerace

## 📁 Struktura Projektu

```
AmericaPodVezi/
├── index.php              # Hlavní stránka
├── script.js              # JavaScript logika
├── input.css              # Tailwind source
├── output.css             # Kompilované CSS (gitignored)
├── data.json              # Kontaktní informace
├───
├── scrape_menu.php       # Scraper pro denní menu
├── get_today_menu.php     # API endpoint pro menu
├── daily_menu.json        # Vygenerovaná data (gitignored)
├───
├── admin.php              # Admin panel (zabezpečit!)
├── .htaccess              # Apache konfigurace
├── .gitignore             # Git ignore rules
├───
├── hero.jpg/webp          # Hero obrázky
├── akce.jpg/webp          # Akce fotky
├── prostory.jpg/webp      # Interiér
├── salonek.jpg/webp       # Saloněk
├── zebra.jpg/webp         # Zebra dekorace
├── menu-page-*.svg        # Jídelní lístek stránky
├── favicon.svg/png        # Favikony
└── fa/                    # Font Awesome ikony
```

## 🚀 Quick Start (Dev)

### Předpoklady
- PHP 7.4+
- Node.js (pro Tailwind)
- Git

### Instalace

```bash
# 1. Klonování
git clone https://github.com/HonzaSodomka/AmericaPodVezi.git
cd AmericaPodVezi

# 2. Tailwind setup (optional - pro úpravy CSS)
npm install -D tailwindcss
npx tailwindcss -i ./input.css -o ./output.css --watch

# 3. Spuštění lokálního serveru
php -S localhost:8000

# 4. První scrape menu
php scrape_menu.php
```

Otevři `http://localhost:8000`

## 💻 Production Deployment

**Kompletní návod:** [DEPLOYMENT.md](DEPLOYMENT.md)

**Rychlý start:**
```bash
# Na serveru
cd /var/www/html
git clone https://github.com/HonzaSodomka/AmericaPodVezi.git .

# Práva
chown -R www-data:www-data .
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 666 daily_menu.json

# První scrape
php scrape_menu.php

# Nastav cron (6:00 každé ráno)
crontab -e
# Přidej: 0 6 * * * cd /var/www/html && php scrape_menu.php >> scrape.log 2>&1
```

## 🛠️ Vývoj & Customizace

### Editace Kontaktních Údajů

Uprav `data.json`:
```json
{
  "name": "America Pod Věží",
  "phone": "326 722 111",
  "email": "info@americapodvezi.cz",
  "address": {
    "street": "Komenského nám. 61",
    "city": "Mladá Boleslav",
    "zip": "293 01"
  },
  "hours": { ... },
  "social": { ... }
}
```

### Změna Barev (Tailwind)

`input.css`:
```css
@layer base {
  :root {
    --brand-gold: #d4a373;  /* Změň zde */
  }
}
```

Pak:
```bash
npx tailwindcss -i ./input.css -o ./output.css --minify
```

### Přidání Nové Stránky Menu

1. Přidej `menu-page-5.svg`
2. Uprav `script.js` v `CONFIG.menuImages`:
```javascript
menuImages: [
    { src: 'menu-page-1.svg', alt: '...' },
    // ... přidej další
]
```

## 🔧 API Endpoints

### `GET /get_today_menu.php`

Parametry:
- `?day=0` - dnešek (default)
- `?day=1` - zítra
- `?day=-1` - včera
- `?all=1` - všechny dny

Příklad:
```bash
curl https://americapodvezi.cz/get_today_menu.php?day=1
```

Response:
```json
{
  "success": true,
  "date": "Pondělí 23.2.2026",
  "soup": { "name": "...", "price": 45 },
  "meals": [...],
  "navigation": {
    "has_prev": true,
    "has_next": true
  }
}
```

## 📊 Monitoring

```bash
# Kontrola scrape logu
tail -f scrape.log

# Test API
curl https://americapodvezi.cz/get_today_menu.php

# Kontrola cronu
grep CRON /var/log/syslog
```

## 🔒 Bezpečnost

- ✅ **HTTPS only** - automatický redirect v .htaccess
- ✅ **Security headers** - XSS, Clickjacking protection
- ✅ **GDPR compliant** - Google Maps consent
- ⚠️ **Zabezpeč admin.php** - použij .htpasswd nebo smaž

## ⚙️ Technologie

- **Frontend:** HTML5, Tailwind CSS, Vanilla JS
- **Backend:** PHP 8.1
- **Icons:** Font Awesome 6
- **Images:** WebP + JPG fallback
- **Maps:** Google Maps Embed API
- **Scraping:** DOMDocument, XPath
- **Server:** Apache 2.4, mod_rewrite

## 📝 Changelog

Viz [Git commits](https://github.com/HonzaSodomka/AmericaPodVezi/commits/main)

## 👥 Autor

**Jan Sodomka**  
GitHub: [@HonzaSodomka](https://github.com/HonzaSodomka)

## 📝 Licence

Proprietary - Všechna práva vyhražena

---

**Web:** [americapodvezi.cz](https://americapodvezi.cz)  
**Menu:** [menicka.cz/7509-america-pod-vezi](https://www.menicka.cz/7509-america-pod-vezi.html)
