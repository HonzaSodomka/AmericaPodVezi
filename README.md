# America Pod Věží - Moderní Web

Tento repozitář obsahuje zdrojový kód pro moderní one-page web restaurace America Pod Věží.

## Jak web funguje
Web je postaven na moderních technologiích (Tailwind CSS) pro rychlé načítání a skvělý vzhled na mobilech. Nepotřebuje žádné složité instalace, stačí otevřít `index.html`.

## Jak upravit obsah

### 1. Denní Menu (PDF)
Sekce "Denní menu" očekává PDF soubor.
- Nahrajte svůj PDF soubor s denním menu do složky `assets/` (vytvořte ji, pokud neexistuje).
- Pojmenujte soubor `denni_menu.pdf`.
- Web ho automaticky zobrazí.

### 2. Hlavní Menu (Efekt listování)
Pro efekt "knihy" v sekci hlavního menu:
- Web používá "fiktivní" stránky v HTML kódu (`<div class="page">...</div>`).
- **Nejlepší řešení:** Vyexportujte vaše menu jako obrázky (JPG/PNG).
- Vložte obrázky do složky `assets/`.
- V souboru `index.html` najděte sekci `<div id="flipbook">`.
- Nahraďte obsah `div class="page"` vašimi obrázky:
  ```html
  <div class="page">
      <img src="assets/menu_strana_1.jpg" alt="Strana 1">
  </div>
  ```

### 3. Obrázky
Aktuálně jsou použity ilustrační obrázky z fotobanky Unsplash.
- Nahraďte je reálnými fotkami z restaurace.
- Upravte `src="..."` v `index.html` odkazy na vaše soubory.

## Spuštění
Pro náhled stačí otevřít soubor `index.html` ve vašem prohlížeči. Pro veřejné spuštění doporučuji nahrát na GitHub Pages (Settings -> Pages -> Source: main).
