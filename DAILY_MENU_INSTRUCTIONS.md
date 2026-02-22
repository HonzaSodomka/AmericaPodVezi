# 🍔 Denní Menu - Návod k použití

## ✅ Co je hotové

1. ✅ **Scraper** - `scrape_menu.php` stáhne celý týden menu + alergeny z menicka.cz
2. ✅ **API** - `get_today_menu.php` vrací menu pro konkrétní den s navigací
3. ✅ **Frontend** - JavaScript v `script.js` zobrazuje menu s navigací mezi dny a alergeny
4. ✅ **HTML patch** - `PATCH_daily_menu_section.html` obsahuje novou strukturu sekce

## 🛠️ Co ještě musíš udělat TY

### 1. Aktualizuj `index.php`

**Najdi sekci DENNÍ MENU** (kolem řádku 669):
```html
<!-- DENNÍ MENU SECTION -->
<section id="denni-menu" class="bg-black py-20 px-8 md:px-12 relative">
    ...
</section>
```

**Smaž celou tuto sekci** a nahraď ji obsahem ze souboru:
[PATCH_daily_menu_section.html](https://github.com/HonzaSodomka/AmericaPodVezi/blob/main/PATCH_daily_menu_section.html)

### 2. Nastav CRON pro pravidelné scrapování

Přidej do cronu (každé ráno v 6:00):
```bash
crontab -e
```

Přidej řádek:
```
0 6 * * * cd /path/to/AmericaPodVezi && php scrape_menu.php >> scrape.log 2>&1
```

### 3. První spuštění scraperu

```bash
php scrape_menu.php
```

Mužeš také spustit přes prohlížeč:
```
https://americapodvezi.cz/scrape_menu.php?run=1
```

### 4. Commitni `index.php`

```bash
git add index.php
git commit -m "✨ Update daily menu section with navigation and allergens"
git push
```

## 🎉 Funkce

### 👉 Navigace mezi dny
- **Šipky vlevo/vpravo** - procházej všechny dny týdne
- **Automatické zakázání** - šipky se zakážou na začátku/konci

### 🥜 Alergeny
- **Zobrazení** - Kužďíky se čísly alergenů pod každým jídlem
- **Tooltip** - Při najetí myší se zobrazí název alergenu
- **Legenda** - Kompletní seznam alergenů dole (pouze pokud jsou nějaké)

### 📅 Stavy
- **Zavřeno** - Zobrazí se hezká hláška s možností jít na další den
- **Nebylo zadáno** - Menu ještě není v systému menicka.cz
- **Normální** - Zobrazí polévku + hlavní jídla s cenami a alergeny

## 📁 Datová struktura JSON

```json
{
  "scraped_at": "2026-02-22 17:30:00",
  "days": [
    {
      "date": "Pondělí 23.2.2026",
      "soup": {
        "name": "Drobečková polévka",
        "price": 45,
        "allergens": [1, 3, 7]
      },
      "meals": [
        {
          "number": 1,
          "name": "Kuřecí řízek s bramborovou kaší",
          "price": 135,
          "allergens": [1, 3, 7]
        }
      ],
      "is_closed": false,
      "is_empty": false
    }
  ]
}
```

## ⚙️ API Endpointy

### `get_today_menu.php`

**Parametry:**
- `?day=0` - Dnešek (výchozí)
- `?day=1` - Zítra
- `?day=-1` - Včera
- `?all=1` - Všechny dny

**Příklad:**
```bash
curl https://americapodvezi.cz/get_today_menu.php?day=1
```

## 🐛 Troubleshooting

### Menu se nenačítá
1. Zkontroluj, že existuje `daily_menu.json`
2. Spusť `php scrape_menu.php` manuálně
3. Zkontroluj práva: `chmod 644 daily_menu.json`

### Alergeny se nezobrazují
1. Zkontroluj formát v menicka.cz - musí být `Název jídla (1,3,7)`
2. Spusť scraper znovu pro update

### Navigace nefunguje
1. Zkontroluj konzoli prohlížeče (F12)
2. Over, že `script.js` je aktuální
3. Vymaz cache: Ctrl+Shift+R

## 📞 Kontakt

Pokud něco nefunguje, napiš!
