# 🚀 Deployment Guide - America Pod Věží

## 📦 Před Nasazením

### 1. Smaž Nepotřebné Soubory z Gitu

Tyto soubory **SMAZAT** z repozitáře (již jsou v .gitignore):

```bash
# Dokumentace (pouze pro dev)
git rm DAILY_MENU_INSTRUCTIONS.md
git rm PATCH_daily_menu_section.html
git rm tailwind-navod.txt

# Build skripty (pouze local)
git rm build.sh
git rm setup-fa.sh

# Commitni
git commit -m "🧹 Remove dev-only files"
git push
```

## 💻 Nasazení na Server

### Předpoklady
- PHP 7.4+ (doporučeno 8.1+)
- Apache/Nginx s mod_rewrite
- přístup k cronu
- SSL certifikát

### 1. Nahrání Souborů

**Metoda A: Git Clone (doporučeno)**
```bash
cd /var/www/html
git clone https://github.com/HonzaSodomka/AmericaPodVezi.git .
```

**Metoda B: FTP Upload**
- Nahraj všechny soubory kromě těch v .gitignore
- Přeskoč: `build.sh`, `setup-fa.sh`, `tailwind-navod.txt`, `DAILY_MENU_INSTRUCTIONS.md`, `PATCH_daily_menu_section.html`

### 2. Nastavení Oprávnění

```bash
# Vlastník souborů
chown -R www-data:www-data /var/www/html/

# Základní práva
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Executable skripty
chmod 755 scrape_menu.php

# Zápis pro JSON
chmod 666 daily_menu.json 2>/dev/null || touch daily_menu.json && chmod 666 daily_menu.json
```

### 3. Apache Konfigurace

**Vytvoř `.htaccess`:**
```apache
# Security Headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"

# PHP Settings
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 30
php_value max_input_time 30

# Cache Control
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 month"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/json "access plus 5 minutes"
</IfModule>

# Gzip Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Block access to sensitive files
<FilesMatch "^\.(htaccess|htpasswd|env|git|gitignore)$">
    Require all denied
</FilesMatch>

<Files "data.json">
    Require all denied
</Files>

<Files "daily_menu.json">
    Require all granted
</Files>

# Redirect to HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 4. Nastavení Cronu

**Edituj crontab:**
```bash
crontab -e
```

**Přidej řádky:**
```cron
# Scrape menu každé ráno v 6:00
0 6 * * * cd /var/www/html && /usr/bin/php scrape_menu.php >> scrape.log 2>&1

# Vymaz staré logy každý týden
0 3 * * 0 find /var/www/html -name "*.log" -mtime +30 -delete
```

**Ověř cron:**
```bash
crontab -l
```

### 5. První Spuštění Menu Scraperu

```bash
cd /var/www/html
php scrape_menu.php
```

**Očekávaný output:**
```
Scraping menu from menicka.cz...
Found menu for 7 day(s)
Menu saved to daily_menu.json
Scraped at: 2026-02-22 18:00:00
```

### 6. Test Funkcionality

**Test webu:**
```bash
curl https://americapodvezi.cz/
```

**Test API:**
```bash
curl https://americapodvezi.cz/get_today_menu.php
```

**Test manuálního scrape:**
```bash
curl "https://americapodvezi.cz/scrape_menu.php?run=1"
```

## 🔒 Bezpečnost

### Zajištění admin.php

**Vytvoř `.htpasswd`:**
```bash
htpasswd -c /var/www/.htpasswd admin
# Zadej silné heslo
```

**Přidej do `.htaccess` před `admin.php`:**
```apache
<Files "admin.php">
    AuthType Basic
    AuthName "Restricted Area"
    AuthUserFile /var/www/.htpasswd
    Require valid-user
</Files>
```

### Nebo smaž admin.php pokud se nepoužívá:
```bash
rm admin.php
git rm admin.php
git commit -m "Remove unused admin panel"
git push
```

## 📈 Monitoring

### Kontrola Logů
```bash
# Posledních 50 řádků scrape logu
tail -n 50 /var/www/html/scrape.log

# Sledování v reálném čase
tail -f /var/www/html/scrape.log
```

### Test Cronu Manuálně
```bash
/usr/bin/php /var/www/html/scrape_menu.php
```

### Kontrola JSON
```bash
cat daily_menu.json | jq .
```

## 🔄 Update

Při aktualizaci kódu:

```bash
cd /var/www/html
git pull origin main

# Opět nastav práva
chown -R www-data:www-data .
chmod 666 daily_menu.json

# Vymaz cache prohlížeče
# Ctrl+Shift+R u klientů
```

## ⚠️ Troubleshooting

### Menu se nenačítá
```bash
# Zkontroluj PHP errors
tail -f /var/log/apache2/error.log

# Test scrape
php scrape_menu.php

# Zkontroluj práva
ls -la daily_menu.json
```

### Cron neběží
```bash
# Zkontroluj cron log
grep CRON /var/log/syslog

# Test přímo
/usr/bin/php /var/www/html/scrape_menu.php
```

### SSL Problémy
```bash
# Let's Encrypt certbot
sudo certbot --apache -d americapodvezi.cz -d www.americapodvezi.cz
```

## 📝 Checklist Před Spuštěním

- [ ] Všechny soubory nahrány
- [ ] Práva nastavená (644/755)
- [ ] `.htaccess` vytvořen
- [ ] SSL certifikát aktivní
- [ ] Cron nastaven
- [ ] `daily_menu.json` poprvné vygenerován
- [ ] Test API endpointu
- [ ] Test načítání menu na webu
- [ ] `admin.php` zabezpečen nebo smazán
- [ ] Google Maps fungují (GDPR consent)
- [ ] Mobilní verze otestována
- [ ] Kontaktní údaje aktuální v `data.json`

## 📞 Kontakt & Podpora

Pokud něco nefunguje:
1. Zkontroluj logy: `scrape.log` a Apache error log
2. Over práva souborů
3. Test manuální scrape
4. Kontaktuj developera

---

**Server je připraven!** 🎉
