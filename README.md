# Rekryteringsportalen.se – WordPress-tema och plugin

Jämförelse- och omdömessajt för svenska rekryteringsföretag.

| Del | Mapp | Innehåll |
|---|---|---|
| **Plugin** | `plugins/rekryteringsportalen-core/` | All data och funktionalitet: företag, jobb, jobbportaler, omdömen, paket, leads, import, REST, WP-CLI |
| **Tema** | `themes/rekryteringsportalen/` | Allt utseende: mallar, CSS, JS, `theme.json` |
| **Tillägg: XLS-import** | `plugins/rekryteringsportalen-xls-import/` | Import av företag från Excel (.xlsx, .xls). Valfritt, kräver pluginet ovan |

Funktionerna ligger i pluginet så att företag, omdömen och inställningar finns kvar om temat byts.

Krav: WordPress 6.6+ och PHP 8.1+. Inga betalda plugins behövs.

---

## Installation

1. Kopiera `plugins/rekryteringsportalen-core` till `wp-content/plugins/` och `themes/rekryteringsportalen` till `wp-content/themes/`.
2. Aktivera **pluginet** först (*Tillägg → Rekryteringsportalen Core*). Det skapar automatiskt:
   - tjänster, branscher och regioner (alla län och större orter),
   - sidorna *Topplistor*, *Hitta rekryterare*, *För rekryteringsföretag*, *Riktlinjer för omdömen* och *Så fungerar betygen*,
   - 11 jobbportaler (LinkedIn, Platsbanken, Jobbsafari, Indeed, StepStone med flera),
   - schemalagda jobb (cron) för paket, jobbimport och omdömen.
3. Aktivera **temat**. En huvudmeny skapas och kopplas automatiskt om ingen meny redan finns.
4. Gå till *Inställningar → Permalänkar* och klicka på **Spara** en gång.
5. Valfritt: välj en statisk startsida under *Inställningar → Läsning*. Temats `front-page.php` används oavsett.
6. Fyll i *Inställningar → Rekryteringsportalen*: priser, e-post för notiser och minsta antal omdömen för topplistorna.
7. **Konfigurera SMTP.** WordPress standardutskick hamnar ofta i skräppost. Använd t.ex. WP Mail SMTP eller FluentSMTP.

### Demodata

```bash
wp rp seed-demo
```

Kommandot skapar 20 fiktiva rekryteringsföretag (domäner på `.example`), 30 jobb och omkring 80 omdömen. Två av företagen får paket.

Ta bort demodatan igen med:

```bash
wp rp seed-demo --delete
```

---

## Sidor och adresser

| URL | Mall | Innehåll |
|---|---|---|
| `/` | `front-page.php` | Sök, populära tjänster, topp 10, nya företag, senaste jobben, omdömen och jobbportaler |
| `/foretag/` | `archive-foretag.php` | Alla företag med filter för tjänst, bransch, region och lägsta betyg, fritextsök och sortering |
| `/foretag/{namn}/` | `single-foretag.php` | Företagspresentationen. Layouten styrs av paketet |
| `/tjanst/{x}/`, `/bransch/{x}/`, `/region/{x}/` | `taxonomy-*.php` | Landningssidor per tjänst, bransch och region |
| `/topplistor/` | `page-topplistor.php` | Topp 10 |
| `/topplistor/{tjanst\|bransch\|region}/{x}/` | samma | Filtrerade topplistor med egna, indexerbara URL:er |
| `/jobb/` | `archive-jobb.php` | Lediga jobb med filter |
| `/jobbportaler/` | `archive-jobbportal.php` | Jobbportaler grupperade per kategori |
| `/hitta-rekryterare/` | `page-hitta-rekryterare.php` | Leadsformulär för företag som ska rekrytera |
| `/for-foretag/` | `page-for-foretag.php` | Säljsida med paketen, jämförelsetabell, FAQ och beställningsformulär |

Filtren på `/foretag/` och `/jobb/` är vanliga GET-formulär och fungerar utan JavaScript. Med JavaScript uppdateras resultaten direkt och URL:en ändras, så att filtrerade vyer går att dela.

---

## Paket – så aktiverar du

Varje företag har ett av tre paket:

| Paket | Vad som visas |
|---|---|
| **Inget** (standard) | Grunduppgifter, kort beskrivning, tjänster, jobb, omdömen, "Liknande företag" och en ruta "Är detta ditt företag?" |
| **Paket 1 – Utökad presentation** | Allt ovan plus lång beskrivning (blockredigeraren), omslagsbild, galleri, video, team, kundcase, företagets svar på omdömen och kontaktformulär. Inga konkurrenter visas på sidan och företaget märks *Utvald partner · Annons* |
| **Paket 2 – Leads** | Allt i Paket 1 plus förfrågningar från företag som ska rekrytera |

> Paket 2 innehåller allt i Paket 1. Om paketen ska vara helt separata, lägg till `add_filter( 'rp_package_includes_extended', '__return_false' );`.

Det finns flera sätt att byta paket:

- **Ett företag i taget, snabbt:** gå till *Rekryteringsföretag*, håll muspekaren över ett företag och klicka på **Snabbredigera**. Välj sedan *Paket* och eventuellt *Giltigt till*.
- **Många företag samtidigt:** bocka för flera företag, välj *Redigera* under *Massåtgärder*, välj ett paket och klicka på *Uppdatera*.
- **I redigeringsvyn:** använd rutan *Paket* i sidopanelen.
- **Via en beställning:** öppna *Rekryteringsföretag → Paketbeställningar* och sätt status till **Aktiv**. Då aktiveras paketet på det kopplade företaget. Status **Avslutad** återställer till inget paket.
- **WP-CLI:** `wp rp set-paket 123 paket1` eller `wp rp set-paket 556677-8899 paket2 --till=2027-01-31`
- **REST:** `POST /wp-json/rp/v1/foretag/123/paket` med `{"paket":"paket1","till":"2027-01-31"}`
- **Vid import:** använd kolumnerna `paket` och `paket_giltigt_till`.

Om du anger *Giltigt till* återgår paketet automatiskt till *Inget* efter det datumet. Admin får ett mejl 7 dagar innan ett paket går ut.

Företagslistan i admin visar paketet som en färgad badge och kan filtreras på paket och källa. Den kan också sorteras på betyg, antal omdömen och antal jobb.

---

## Importera nya företag

Alla tre importvägar använder samma kärnfunktion, `RP_Importer::upsert_company()`, och samma dubblettkontroll. Dubbletter letas i den här ordningen:

1. organisationsnummer (`556677-8899`, `5566778899` och `16556677-8899` räknas som samma nummer),
2. webbplatsens domän (`https://www.exempel.se/om` blir `exempel.se`),
3. normaliserat namn (`Exempel AB` och `exempel aktiebolag` räknas som samma namn).

Befintliga företag uppdateras. Som standard fylls bara tomma fält i, men du kan välja att skriva över. Nya företag kan sparas som **utkast** för granskning (standard) eller publiceras direkt. Nyimporterade utkast syns i panelen *Rekryteringsportalen* på adminstartsidan.

### 1. Adminsidan

Gå till *Rekryteringsföretag → Importera företag*.

1. Ladda upp en CSV- eller JSON-fil, ange en URL till en fil, eller klistra in rader.
2. Koppla kolumnerna till fält. Vanliga kolumnnamn som "Företagsnamn", "Org.nr", "Hemsida" och "Län" känns igen automatiskt. Du kan spara mappningen som en mall.
3. Förhandsgranska och se vilka företag som blir nya, vilka som uppdateras och vilka som hoppas över.
4. Importera. Resultatet sparas i importloggen (de 20 senaste importerna).

CSV-filen får använda semikolon, komma eller tab som avgränsare, och Excel-filer i Windows-1252 konverteras automatiskt. Det finns en mall med alla kolumner och två exempelrader i [`data/import-mall.csv`](plugins/rekryteringsportalen-core/data/import-mall.csv).

| Kolumn | Exempel |
|---|---|
| `namn` * | Exempel Rekrytering AB |
| `orgnr` | 559999-0001 |
| `webbplats` | exempel.se |
| `logo_url` | https://…/logo.png (laddas ner till mediabiblioteket) |
| `kort_beskrivning` | max 300 tecken |
| `beskrivning` | HTML tillåten |
| `epost`, `telefon`, `adress`, `ort`, `linkedin` | |
| `antal_anstallda`, `grundat` | 10–49, 2010 |
| `tjanster`, `branscher`, `regioner` | kommaseparerade. Nya termer skapas automatiskt |
| `karriarsida_url`, `jobbfeed_url` | |
| `paket`, `paket_giltigt_till`, `leads_epost` | paket1, 2027-12-31 |
| `kalla` | var företaget upptäcktes, t.ex. `crawler` |

### Excel (.xlsx och .xls)

Aktivera tillägget **Rekryteringsportalen XLS-import**. Då tar importsidan och `wp rp import` även emot Excel-filer:

- **Format:** både `.xlsx`/`.xlsm` (Excel 2007 och senare) och `.xls` (Excel 97–2003) fungerar, utan externa bibliotek.
- **Flik:** välj flik med namn eller nummer. Lämnar du fältet tomt används första fliken som innehåller data.
- **Rubriker och tomma celler:** första raden med innehåll blir kolumnrubriker. Tomma rader och kolumner hoppas över.
- **Datum och tal:** datumceller blir `ÅÅÅÅ-MM-DD`, och långa tal som organisationsnummer behåller alla siffror.
- **Mall:** det finns en färdig Excel-mall i [`data/import-mall.xlsx`](plugins/rekryteringsportalen-xls-import/data/import-mall.xlsx).

```bash
wp rp import foretag.xlsx --sheet="Företag" --status=draft --dry-run
```

### 2. WP-CLI

```bash
wp rp import foretag.csv --status=draft --dry-run
```

```bash
wp rp import foretag.json --status=publish --overwrite
```

```bash
wp rp list --paket=paket2
```

### 3. REST API (för en crawler som hittar nya företag automatiskt)

Skapa ett *Application Password* för en administratör under *Användare → Profil*. Sedan:

```bash
curl -u admin:"xxxx xxxx xxxx xxxx" -H "Content-Type: application/json" -X POST "https://www.rekryteringsportalen.se/wp-json/rp/v1/import?status=draft" -d '[{"namn":"Nytt Rekryteringsbolag AB","orgnr":"559999-1234","webbplats":"nytt.se","kalla":"crawler"}]'
```

Parametrarna `dry_run=true` och `overwrite=true` stöds.

---

## Betyg och omdömen

- **1–5 stjärnor, där 1 är sämst.** Kommentaren är obligatorisk och måste vara minst 20 tecken.
- Omdömen sparas som WordPress-kommentarer (`comment_type = rp_omdome`) med betyget i meta-fältet `rp_betyg`. Därför fungerar vanlig moderering, Akismet, export och radering.
- Flödet ser ut så här:
  1. Besökaren skickar formuläret.
  2. Hen får ett mejl med en bekräftelselänk (kan stängas av i inställningarna).
  3. Omdömet väntar på granskning under *Kommentarer*.
  4. När det godkänns publiceras det och snittbetyget räknas om.
- Ett omdöme per e-postadress och företag. Formuläret har honeypot, nonce och en gräns för antal inskick per IP-adress och timme. IP-adresser sparas aldrig, bara en saltad hash som tillfälligt används för gränsen.
- Varje omdöme har knappen *Rapportera*. Rapporter mejlas till admin och syns på omdömet i admin.
- **Företagets svar:** öppna omdömet i admin och fyll i *Svar från företaget*. Svaret visas bara för företag med Paket 1 eller 2.
- Obekräftade omdömen raderas efter 7 dagar.

### Topplistorna

Topplistorna rangordnas på ett **Bayesianskt viktat betyg**:

```
viktat = (v / (v + m)) × R  +  (m / (v + m)) × C
```

där

- `R` är företagets snittbetyg,
- `v` är antal omdömen,
- `C` är snittet för alla företag på sajten,
- `m` är minsta antal omdömen (standard 5, ändras under *Inställningar*).

Bara företag med minst `m` godkända omdömen kommer med. **Paket påverkar aldrig placeringen.**

Shortcode för att visa en lista var som helst:

```
[rp_topplista antal="10"]
[rp_topplista antal="10" bransch="it-tech"]
[rp_topplista antal="5" region="stockholm" kompakt="1"]
```

## Lediga jobb

Jobb kan läggas in på tre sätt:

- manuellt under *Lediga jobb*,
- via företagets **jobbfeed** (`rp_jobbfeed_url`), som läses in varannan timme,
- för hand med knappen *Importera jobb nu* på företaget, eller med `wp rp import-jobb`.

Feeden kan vara RSS, Atom eller JSON. JSON-formatet ser ut så här:

```json
[{ "id": "123", "title": "Controller", "url": "https://…", "description": "…", "location": "Stockholm", "employment_type": "Tillsvidare", "deadline": "2027-02-01" }]
```

Jobb vars sista ansökningsdag har passerat får automatiskt statusen *Utgången* och visas inte längre.

## Leads (Paket 2)

1. Ett företag fyller i formuläret på `/hitta-rekryterare/` (shortcode `[rp_lead_formular]`).
2. Förfrågan sparas under *Rekryteringsföretag → Leads*.
3. Förfrågan mejlas till max 3 företag (går att ändra) som har aktivt Paket 2 och matchar bransch och/eller region.
   - En exakt matchning väger tyngst.
   - Ett företag utan angivna branscher eller regioner räknas som "täcker allt".
   - Vid lika poäng går förfrågan till det företag som fick ett lead längst tillbaka (round-robin).
4. I admin syns en logg över vilka företag som fick leadet och när. Där kan du också skicka om leadet till valfria företag.

Leads raderas automatiskt efter 24 månader (filtret `rp_lead_retention_months`).

## Beställning av paket

Beställningsformuläret på `/for-foretag/` (shortcode `[rp_paketformular]`) skapar en *Paketbeställning*, mejlar admin och skickar en bekräftelse till kunden. Ingen kortbetalning byggs in. Koppla på WooCommerce eller Stripe via action-hooken:

```php
add_action( 'rp_paketorder_created', function ( int $order_id ) { /* skapa faktura/betalning */ } );
```

---

## Shortcodes

| Shortcode | Beskrivning |
|---|---|
| `[rp_topplista antal="10" tjanst="" bransch="" region="" kompakt="0"]` | Topplista |
| `[rp_jobbportaler kategori="" antal="-1"]` | Jobbportaler |
| `[rp_paket]` | De två paketkorten |
| `[rp_paketformular paket="paket1"]` | Beställningsformulär |
| `[rp_lead_formular]` | Leadsformulär |

## REST API (`/wp-json/rp/v1/`)

| Metod | Route | Behörighet |
|---|---|---|
| GET | `foretag?tjanst=&bransch=&region=&min_betyg=&q=&sortera=&page=` | Publik |
| GET | `topplista?antal=10&taxonomi=bransch&term=it-tech` | Publik |
| POST | `import?status=draft&overwrite=false&dry_run=false` | `manage_options` |
| POST | `foretag/{id}/paket` | `edit_post` |
| POST | `klick/{id}` | Publik (räknar klick på jobbportaler) |

## WP-CLI

```bash
wp rp import <fil> [--status=draft|publish|pending] [--overwrite] [--dry-run]
```

```bash
wp rp set-paket <id|orgnr> <inget|paket1|paket2> [--till=ÅÅÅÅ-MM-DD]
```

```bash
wp rp list [--paket=paket1] [--format=table|csv|json]
```

```bash
wp rp recalc
```

```bash
wp rp import-jobb
```

```bash
wp rp seed-demo [--delete]
```

## Hooks

| Hook | Typ | Användning |
|---|---|---|
| `rp_package_changed( $id, $paket, $old )` | action | När ett företag byter paket |
| `rp_paketorder_created( $order_id )` | action | Koppla på betalning |
| `rp_lead_created( $lead_id, $recipients )` | action | Efter att ett lead skickats |
| `rp_company_imported( $id, $action, $data )` | action | Efter import av ett företag |
| `rp_package_includes_extended` | filter | Om Paket 2 även ska innehålla Paket 1 (standard `true`) |
| `rp_output_schema` | filter | Stäng av JSON-LD |
| `rp_import_extensions` | filter | Filändelser som importen tar emot |
| `rp_import_parse_file( null, $path, $ext, $options )` | filter | Läs in ett nytt filformat. Returnera `{headers, rows}` eller `WP_Error` |
| `rp_import_upload_fields` | action | Extra fält på importsidans första steg |
| `rp_lead_retention_months` | filter | Hur länge leads sparas (standard 24) |

---

## SEO

- JSON-LD för `EmploymentAgency` med `AggregateRating` och de 5 senaste omdömena, `JobPosting` på jobbsidor och `BreadcrumbList`.
- Automatiska meta-beskrivningar när inget SEO-plugin (Yoast, Rank Math, SEOPress eller AIOSEO) är aktivt.
- Sitemaps via WordPress kärn-sitemaps. Leads och beställningar är exkluderade.

## Juridik och GDPR

- Pluginet lägger till ett förslag på text för integritetspolicyn under *Inställningar → Integritet*.
- Exporter och raderare för leads och beställningar finns under *Verktyg → Exportera/Radera personuppgifter*. Omdömen täcks av WordPress egna verktyg för kommentarer.
- Sidorna *Riktlinjer för omdömen* och *Så fungerar betygen* skapas automatiskt. Läs igenom texterna och anpassa dem.

## Utveckling

```bash
composer install
```

```bash
composer test
```

```bash
composer lint
```

Enhetstesterna täcker betygsberäkningen (snitt, Bayes, fördelning), dubblettkontrollen vid import, leads-matchningen och Excel-läsarna. Excel-läsarna testas mot riktiga .xlsx- och .xls-filer i `plugins/rekryteringsportalen-xls-import/tests/fixtures/`. Dessa klasser saknar WordPress-beroenden och testas utan WordPress-installation.

## Antaganden och sådant som måste konfigureras manuellt

- **SMTP** för e-post (se Installation).
- **Priser och paketbeskrivningar** under *Inställningar → Rekryteringsportalen*. Standardvärdena 2 495 och 4 995 kr/mån är platshållare.
- **Juridiska texter:** integritetspolicy, riktlinjer och villkor bör granskas av jurist.
- **Paket 2 innehåller Paket 1.** Kan ändras med filter (se ovan).
- **Företagens svar på omdömen** läggs in av admin. Det finns ingen inloggning för företag ännu.
- **Betalning** är inte inbyggd. Använd `rp_paketorder_created`.
- **Gränssnittstexterna är skrivna direkt på svenska** i källkoden, eftersom sajten är svenskspråkig. Text domains (`rekryteringsportalen`, `rekryteringsportalen-core`) är satta, så en översättning till andra språk kan göras med en `.pot`-fil (`wp i18n make-pot`).
- **Sidcache:** formulären skyddas med WordPress-nonces som gäller i 12–24 timmar. Om sajten använder helsidescache (WP Rocket, LiteSpeed, Cloudflare APO) ska cachetiden för sidor med formulär (företagssidor, *Hitta rekryterare*, *För företag*) vara under 10 timmar, annars kan formulären ge felet "Formuläret kunde inte skickas".
- Logotyper för jobbportalerna läggs till som *utvald bild* på respektive portal. Utan bild visas en färgad bokstav.
