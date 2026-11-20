# Ändringslogg

## Rekryteringsportalen Core

### 1.4.0
- **Lägg till ditt företag gratis.** Ny sida `/lagg-till-foretag/` (shortcode `[rp_anmal_foretag]`) där rekryteringsföretag som saknas kan anmäla sig själva:
  - Företaget sparas som *Väntar på granskning* med källan "anmälan". Admin får ett mejl och kontaktpersonen en bekräftelse.
  - Dubbletter fångas på org.nr, domän och namn. Finns företaget redan visas en länk till det.
  - När admin publicerar företaget får kontaktpersonen automatiskt ett mejl med länk till sidan och till annonspaketen. Därefter raderas kontaktuppgifterna. Opublicerade anmälningar rensas efter 90 dagar.
  - Rutan *Anmälan* i företagets redigeringsvy visar vem som anmälde.
- Rättat: termnamn med `&amp;` (t.ex. "Teknik &amp; Ingenjör") delades vid `;` och blev två felaktiga termer.

### 1.3.1
- Rättat: knappen "Uppgradera" på företagssidor gav 404. Länken använde parametern `?foretag=`, som krockar med företagstypens query var. Nu används `?uppgradera=`, och gamla länkar skickas vidare automatiskt (301).

### 1.3.0
- **Ångra import.** Varje import får ett import-ID. Under *Importera företag → Importlogg → Ångra…* (eller med `wp rp undo-import <id>`) kan en hel import ångras:
  - Företag som importen skapade flyttas till papperskorgen tillsammans med sina jobb.
  - Företag som importen uppdaterade återställs till sina tidigare värden (namn, beskrivning, fält, logotyp, tjänster/branscher/regioner och paket).
  - Företag som har ändrats efter importen hoppas över och listas, liksom skapade företag som har fått omdömen.
- Förhandsgranskning innan något ändras. Fungerar för importer gjorda från version 1.3.0.
- Nya kommandon: `wp rp undo-import` och `wp rp import-log`. Ny REST-route: `POST rp/v1/import/{id}/undo`. Ny hook: `rp_import_undone`.
- Integrationstest mot riktig WordPress: `tests/integration/import-undo-check.php`.

### 1.2.1
- Tömmer sidcachen (LiteSpeed, WP Rocket, W3TC, WP Super Cache) automatiskt efter uppdatering och när inställningarna sparas, så att ändrade formulär syns direkt.

### 1.2.0
- E-post är valfritt när man skriver ett omdöme, och ingen bekräftelselänk skickas som standard. Kan slås på igen under *Inställningar → Rekryteringsportalen*.
- Samma besökare kan inte betygsätta samma företag igen inom 90 dagar. Kontrollen görs på en saltad IP-hash som raderas efter 90 dagar.
- Nytt filter: `rp_client_ip`.

### 1.1.0
- Importen kan byggas ut med fler filformat: `rp_import_extensions`, `rp_import_parse_file` och `rp_import_upload_fields`. Används av XLS-importen.
- `wp rp import` har fått flaggan `--sheet`.
- Excel-datum (serienummer) tolkas som datum. Ett ensamt årtal som "2010" tolkas inte längre som dagens datum.
- Bottenlistan (botten 10) är borttagen. Bara topp 10 finns kvar.
- Rättat: samtyckestexten bröts fel runt länken "integritetspolicy".

### 1.0.0
- Första versionen.

## Rekryteringsportalen XLS-import

### 1.2.0
- Tog bort `Requires Plugins: rekryteringsportalen-core`. Det låste kärnpluginet så att det inte gick att inaktivera så länge XLS-importen var aktiv. Utan Core visar XLS-importen i stället ett meddelande i admin och gör ingenting.

### 1.1.0
- Mall i `.xls`-format (Excel 97–2003) utöver `.xlsx`.

### 1.0.0
- Första versionen: import från `.xlsx`, `.xlsm` och `.xls`.

## Tema Rekryteringsportalen

### 1.2.0
- Rättat: sidnumreringen under företagslistan låg på höjden. Nu står den på en rad.
- Länkar till "Lägg till ditt företag gratis" på företagslistan, säljsidan, i sidfoten och vid sökningar utan träffar.
- Stil för kryssrutor i formulär. Menylänkar bryts inte längre över två rader.

### 1.1.2
- Knappen "Uppgradera" länkar till `/for-foretag/?uppgradera=<id>#bestall`, så att beställningsformuläret öppnas med företaget förifyllt.

### 1.1.1
- Rättat: i rutan "Liknande företag" på företagssidan stod stjärnorna under varandra. Nu ligger de på en rad, med betyg och antal omdömen (eller "Inga omdömen ännu").

### 1.1.0
- Topp 10 i två kolumner. Bottenlistan är borttagen.
- Rättat: punktlistan i Paket 2 och samtyckesrutans radbrytning.
- Stil för fältet "E-post (valfritt)".

### 1.0.0
- Första versionen.
