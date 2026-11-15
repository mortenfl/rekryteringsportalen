# Prompt: WordPress-tema för Rekryteringsportalen.se

> Kopiera allt under strecket och klistra in i en AI-kodassistent (t.ex. Claude Code).
> Ändra gärna värden inom `[hakparenteser]` innan du kör.

---

## Roll och uppdrag

Du är en erfaren WordPress-utvecklare. Bygg ett komplett, produktionsklart WordPress-tema för **www.rekryteringsportalen.se** – en svensk jämförelse- och omdömessajt för rekryteringsföretag.

Sajten har två målgrupper:

1. **Besökare/arbetssökande och företag som ska rekrytera:** vill se vilka rekryteringsföretag som finns, vilka jobb de har ute och vilket betyg andra användare ger dem.
2. **Rekryteringsföretag:** vill synas, visa sina tjänster och köpa annonspaket för en utökad presentation eller för att få leads.

All text i gränssnittet ska vara på **svenska**. Koden, kommentarerna och funktionsnamnen ska vara på engelska.

## Teknisk arkitektur

Dela upp leveransen i två delar, så att innehållet finns kvar om temat byts:

- **Tema `rekryteringsportalen`** – all presentation: mallar, CSS, JS, `theme.json`.
- **Plugin `rekryteringsportalen-core`** – all funktionalitet: custom post types, taxonomier, metafält, betyg, import, leads, REST-endpoints, WP-CLI-kommandon, cron.

Krav:

- WordPress 6.6+ och PHP 8.1+. Inga betalda plugins krävs.
- Ett klassiskt PHP-tema med `theme.json` för färger, typografi och avstånd, och stöd för blockredigeraren på vanliga sidor.
- Ingen jQuery i frontend. Använd vanilla JS (ES-moduler), och håll varje sidas JS under 30 kB.
- Mobile-first, responsivt, WCAG 2.1 AA (tangentbordsnavigering, kontrast, ARIA på stjärnbetyget).
- Prestanda: Lighthouse 90+ på mobil, lazy-loadade logotyper, inga externa typsnitt som blockerar rendering.
- Säkerhet: nonces, `current_user_can`, sanitering vid input och escaping vid output, `$wpdb->prepare` överallt.
- Översättningsbart: text domain `rekryteringsportalen`, `.pot`-fil och svensk `.po/.mo`.
- Följ WordPress Coding Standards (PHPCS-konfiguration ska ingå).

## Datamodell

### CPT `foretag` (Rekryteringsföretag), slug `/foretag/{namn}/`

Metafält (registrera med `register_post_meta`, `show_in_rest => true`):

| Fält | Typ | Kommentar |
|---|---|---|
| `rp_orgnr` | string | Organisationsnummer, unik nyckel vid import |
| `rp_webbplats` | url | Används också som dubblettnyckel (domän) |
| `rp_logo` | attachment-ID | |
| `rp_kort_beskrivning` | text, max 300 tecken | Visas i listor |
| `rp_epost`, `rp_telefon` | string | |
| `rp_adress`, `rp_ort` | string | |
| `rp_antal_anstallda` | string | Intervall, t.ex. "10–49" |
| `rp_grundat` | int | År |
| `rp_linkedin` | url | |
| `rp_jobbfeed_url` | url | RSS/XML/JSON-feed med företagets lediga jobb (valfri) |
| `rp_karriarsida_url` | url | Länk till företagets egna jobblista |
| `rp_paket` | enum: `inget` / `paket1` / `paket2` | **Standard: `inget`** |
| `rp_paket_giltigt_till` | datum | Valfritt slutdatum. Paketet faller tillbaka till `inget` via cron |
| `rp_leads_epost` | e-post | Mottagare av leads (paket 2) |
| `rp_snittbetyg` | float | Cachat värde, uppdateras vid varje godkänt omdöme |
| `rp_antal_betyg` | int | Cachat värde |
| `rp_bayes_betyg` | float | Viktat betyg som används för topplistor (se nedan) |
| `rp_kalla` | string | Var företaget upptäcktes (import, manuellt, tips) |
| `rp_verifierad` | bool | Om företaget har gjort anspråk på sidan |

Taxonomier kopplade till `foretag`:

- `tjanst` (hierarkisk): t.ex. Rekrytering, Bemanning, Executive search, Interimslösningar, Headhunting, RPO, Tester & assessment.
- `bransch`: t.ex. IT, Ekonomi, Teknik, Vård, Bygg, Försäljning.
- `region`: Sveriges län och de större orterna.

### CPT `jobb` (Lediga jobb), slug `/jobb/{titel}/`

Fälten är `rp_foretag_id` (relation), `rp_ort`, `rp_anstallningsform`, `rp_sista_ansokningsdag`, `rp_ansok_url` och `rp_extern_id` (för dubblettkontroll vid feed-import). Jobbet delar taxonomierna `bransch` och `region`.

- Jobb vars sista ansökningsdag har passerat sätts automatiskt till status `utgangen` via dagligt cron-jobb och visas inte längre.

### CPT `jobbportal` (Jobbportaler)

Fälten är `rp_url`, `rp_logo`, `rp_beskrivning` och `rp_kategori` (Allmän, Tech, Chef, Student, Offentlig sektor, Frilans). Seeda med: LinkedIn Jobs, Jobbsafari, Arbetsförmedlingen Platsbanken, Indeed, StepStone, Monster, Academic Work, Jobbland, Ingenjörsjobb, Offentliga Jobb och Glassdoor.

### CPT `lead` (endast admin, `public => false`)

Inkommande rekryteringsbehov från företag (se avsnittet om paket 2).

### CPT `paketorder` (endast admin)

Beställningar och intresseanmälningar för annonspaket.

## Funktioner

### 1. Företagslista (`/foretag/`)

- En lista med alla publicerade rekryteringsföretag som kort (logotyp, namn, ort, tjänster, stjärnor, antal omdömen och antal lediga jobb).
- **Filter:** tjänst, bransch, region och lägsta betyg. **Sök:** fritext på namn. **Sortering:** bäst betyg, flest omdömen, A–Ö, nyast tillagda och flest jobb.
- Filtreringen sker utan omladdning via REST (`/wp-json/rp/v1/foretag`), men URL:en uppdateras (`?tjanst=it&region=stockholm`) så att filtrerade vyer går att dela och indexera. Lösningen ska fungera även utan JS (progressive enhancement).
- Företag med paket 1 eller 2 får en diskret "Utvald"-markering och kan lyftas högst upp i standardsorteringen. Det ska tydligt märkas som annons/sponsrat.
- En rad som visar "X nya rekryteringsföretag senaste 30 dagarna" och länkar till sortering på nyast.

### 2. Företagspresentation (`single-foretag.php`)

Layouten styrs av `rp_paket`:

| Del | Inget paket | Paket 1 – Utökad presentation | Paket 2 – Leads |
|---|---|---|---|
| Namn, logotyp, kontaktuppgifter, tjänster, betyg | ✅ | ✅ | ✅ |
| Kort beskrivning | ✅ | ✅ | ✅ |
| Lediga jobb (lista) | ✅ | ✅ | ✅ |
| Omdömen + betygsformulär | ✅ | ✅ | ✅ |
| Lång beskrivning med rich content (blockredigerare) | – | ✅ | ✅ |
| Omslagsbild/hero och bildgalleri | – | ✅ | ✅ |
| Video (YouTube/Vimeo-embed) | – | ✅ | ✅ |
| Team/kontaktpersoner med foto | – | ✅ | ✅ |
| Kundcase / referenser | – | ✅ | ✅ |
| Företagets svar på omdömen | – | ✅ | ✅ |
| Kontaktformulär direkt till företaget | – | ✅ | ✅ |
| Ingen konkurrentreklam ("Liknande företag" döljs) | – | ✅ | ✅ |
| Mottar leads från rekryterande företag | – | – | ✅ |

> **Antagande att verifiera:** Paket 2 inkluderar allt i paket 1. Om paketen ska vara helt separata, ändra så att paket 2 bara lägger till leads.

- Utan paket visas en ruta "Är detta ditt företag? Gör anspråk på sidan / Uppgradera" som länkar till paketsidan.
- Schema.org: `Organization` (eller `EmploymentAgency`) med `AggregateRating` och `Review`, samt `JobPosting` på jobbsidorna.

### 3. Betyg och omdömen

- **Stjärnor 1–5, där 1 är sämst och 5 bäst.** Klickbara stjärnor som även går att välja med tangentbordet (radio-knappar som stylas som stjärnor).
- **Kommentar är obligatorisk** (minst 20 tecken) och förklarar varför betyget gavs.
- Valfria fält: roll ("Kandidat", "Kund/uppdragsgivare", "Anställd") och namn eller signatur. Dessutom krävs e-post (visas inte publikt) och ett samtycke till publicering och integritetspolicy (GDPR).
- Implementation: använd WordPress-kommentarer med `comment_type = 'rp_omdome'` och betyget i comment meta `rp_betyg`. Använd inte ett eget tabellsystem, så att moderering, spam och export fungerar som vanligt.
- Ett omdöme per e-postadress och företag. Bekräfta via e-postlänk (double opt-in) innan omdömet går till moderering.
- Spamskydd: honeypot, nonce, rate limit per IP (t.ex. 3 omdömen per timme) och stöd för Akismet om det är aktivt. Ingen tredjeparts-captcha som standard.
- **Moderering:** omdömen är `pending` tills en admin godkänner dem. Lägg till knappen "Rapportera omdöme" på publika omdömen.
- Vid godkänt/borttaget omdöme räknas `rp_snittbetyg`, `rp_antal_betyg` och `rp_bayes_betyg` om.
- Visa betygsfördelningen (staplar för 5★…1★) överst bland omdömena.
- Lägg upp en sida med riktlinjer för omdömen (sakliga omdömen, inga personangrepp och inga namngivna privatpersoner).

### 4. Topp 10 och botten 10 (`/topplistor/`)

- Två listor: **Topp 10** (högst betyg) och **Botten 10** (lägst betyg).
- Rangordna på **Bayesianskt viktat betyg**, så att ett företag med ett enda 5-stjärnigt omdöme inte toppar listan:
  `bayes = (v / (v + m)) * R + (m / (v + m)) * C`, där `R` är företagets snitt, `v` antal omdömen, `C` sajtens totala snitt och `m` minsta antal omdömen (inställbart, standard 5).
- Endast företag med minst `m` godkända omdömen kommer med i listorna.
- Möjlighet att filtrera listorna per tjänst/bransch/region (t.ex. "Topp 10 IT-rekryterare i Stockholm"). Dessa vyer ska ha egna indexerbara URL:er.
- Topp 10 och botten 10 ska även finnas som block/shortcode (`[rp_topplista typ="topp" antal="10"]`) så att de går att visa på startsidan.
- Sponsrade företag får **inte** påverka placeringen i topplistorna.

### 5. Lediga jobb (`/jobb/`)

- Lista över jobb från alla rekryteringsföretag med filter för bransch, region, anställningsform och företag.
- Varje jobb länkar till företagets presentation och till ansökan (extern länk).
- Import sker automatiskt från `rp_jobbfeed_url` via WP-Cron (varannan timme), med dubblettkontroll på `rp_extern_id`. Manuell inmatning ska också fungera.

### 6. Jobbportaler (`/jobbportaler/`)

- Grupperade kort per kategori med logotyp, beskrivning och en knapp "Gå till {namn}".
- Utgående länkar får `target="_blank" rel="noopener nofollow"` och en UTM-parameter (`utm_source=rekryteringsportalen`). Räkna klick via REST-endpoint (enkel räknare i post meta).
- Admin kan lägga till och ändra ordning på portaler (menu_order).

### 7. För rekryteringsföretag – annonspaket (`/for-foretag/`)

En säljsida med två paketkort sida vid sida:

- **Paket 1 – Utökad företagspresentation** – [pris] kr/mån. Lista innehållet enligt tabellen ovan.
- **Paket 2 – Leads** – [pris] kr/mån. Ni får förfrågningar från företag som ska rekrytera inom er bransch och region.

Därefter följer en jämförelsetabell, FAQ och ett beställningsformulär (företag, org.nr, kontaktperson, e-post, telefon, valt paket och meddelande). Formuläret skapar en `paketorder` och skickar e-post till admin.

- Förbered för betalning: gör `paketorder` utbyggbar med en hook (`do_action('rp_paketorder_created', $order_id)`) så att WooCommerce/Stripe kan kopplas på senare. Bygg ingen egen kortbetalning.
- När admin markerar en order som "Aktiv" sätts `rp_paket` på det kopplade företaget automatiskt.

### 8. Leadsflöde (paket 2)

- En publik sida **"Ska ni rekrytera? Få offerter från rekryteringsföretag"** (`/hitta-rekryterare/`) med ett formulär: företag, kontaktperson, e-post, telefon, roll som ska rekryteras, bransch, region, antal tjänster, önskad start och samtycke.
- En inskickad förfrågan sparas som `lead`. Den skickas automatiskt till de företag som har **`rp_paket = paket2`**, aktivt paket och matchande bransch och/eller region. Max antal mottagare per lead är inställbart (standard 3) och fördelas rättvist (round-robin).
- E-post till mottagaren skickas via `wp_mail` med HTML-mall. En logg på leadet visar vilka företag som fått det och när.
- Admin kan skicka om ett lead manuellt eller välja mottagare för hand.

## Admin: enkel import och paketstyrning

### Import av nya företag

Sajten ska löpande fyllas på med nya rekryteringsföretag. Bygg tre importvägar som delar samma kärnfunktion `RP_Importer::upsert_company(array $data): int`:

1. **Adminsida "Importera företag"** (under Rekryteringsföretag-menyn):
   - Ladda upp CSV eller JSON, eller klistra in en URL till en CSV-fil.
   - **Kolumnmappning** i gränssnittet (dra kolumn → fält), med sparbara mappningsmallar.
   - **Förhandsgranskning** innan import, som visar vilka företag som blir nya, vilka som uppdateras och vilka som hoppas över.
   - **Dubblettkontroll:** först `rp_orgnr`, sedan domänen i `rp_webbplats`, sist normaliserat namn.
   - Välj om befintliga fält ska skrivas över eller bara fyllas i om de är tomma.
   - Logotyp-URL laddas ner till mediabiblioteket (`media_sideload_image`).
   - Valbar status för nya företag: `draft` (granska först) eller `publish`.
   - Importlogg med resultat per rad.
2. **WP-CLI:** `wp rp import foretag.csv --status=draft --dry-run`, samt `wp rp set-paket <id|orgnr> <inget|paket1|paket2> [--till=2027-01-31]`.
3. **REST-endpoint** `POST /wp-json/rp/v1/import` (Application Passwords-autentisering, kräver `manage_options`), så att en extern crawler/scraper kan skicka in nyupptäckta företag automatiskt.

Leverera även en **CSV-mall** (`import-mall.csv`) med alla kolumner och två exempelrader.

Nyimporterade företag som har status `draft` visas i en adminwidget "Nya företag att granska (X)".

### Aktivera paket – så enkelt som möjligt

- En **kolumn "Paket"** i företagslistan i admin med färgad badge (Inget / Paket 1 / Paket 2).
- **Quick Edit** och **Bulk Edit** med en dropdown för paket och fält för slutdatum, så att man kan byta paket för ett eller flera företag på några sekunder.
- Filter i adminlistan per paket, och en sorterbar kolumn för betyg och antal omdömen.
- En metabox "Paket" i sidopanelen i redigeringsvyn (radioknappar och slutdatum).
- Dagligt cron-jobb som återställer utgångna paket till `inget` och mejlar admin 7 dagar innan ett paket går ut.

### Inställningar (Inställningar → Rekryteringsportalen)

Inställningarna gäller minsta antal omdömen för topplistor (`m`), max mottagare per lead, priser och paketbeskrivningar (som visas på paketsidan), e-postadresser för notiser, om omdömen kräver e-postbekräftelse, och om sponsrade företag ska lyftas i standardsorteringen.

## Sidor och mallar

Följande mallar ska skapas:

- `front-page.php`: hero med sökfält ("Sök rekryteringsföretag eller jobb"), snabbfilter på tjänster, Topp 10 i kompakt form, senast tillagda företag, senaste jobben, de senaste omdömena, en jobbportal-sektion och en CTA till både "Hitta rekryterare" och "För rekryteringsföretag".
- `archive-foretag.php` och `single-foretag.php`.
- `archive-jobb.php` och `single-jobb.php`.
- `archive-jobbportal.php`.
- `taxonomy-tjanst.php`, `taxonomy-bransch.php` och `taxonomy-region.php`, som är landningssidor med intro och filtrerad lista.
- `page-topplistor.php`, `page-for-foretag.php` och `page-hitta-rekryterare.php`.
- `search.php`, som söker i både företag och jobb, i separata grupper.
- `404.php`, `header.php`, `footer.php` och `comments.php` (anpassad för omdömen).

Vid tema-/pluginaktivering skapas sidorna automatiskt och läggs i en standardmeny: Företag, Lediga jobb, Topplistor, Jobbportaler, Hitta rekryterare och För företag.

## Design

- Stilen ska vara förtroendeingivande, ren och modern, som en jämförelsetjänst (tänk Trustpilot möter Platsbanken), men med egen identitet.
- Färger definieras i `theme.json` och kan ändras i Site Editor/Customizer: primär [#1E4E8C], accent [#F5A623] för stjärnor, och neutrala grå. Stjärnor ska vara SVG, inte typsnittsikoner.
- Typsnittet är systemfontstack eller ett lokalt hostat variabelt typsnitt (t.ex. Inter).
- Kortkomponenter, tydliga betygsbadges och stor klickyta på mobil.
- Sponsrat innehåll ska vara visuellt särskiljt och märkt "Annons" eller "Utvald partner".

## SEO och juridik

- Rena URL:er, brödsmulor (`BreadcrumbList`-schema), automatiska meta-beskrivningar om inget SEO-plugin finns, och kompatibilitet med Yoast/Rank Math (ingen dubbel schema-utskrift när de är aktiva).
- XML-sitemaps för CPT och taxonomier via WordPress kärn-sitemaps.
- GDPR: omdömesförfattarens e-post och IP lagras bara så länge det behövs. Koppla in WordPress verktyg för export och radering av personuppgifter (`wp_privacy_personal_data_exporters/erasers`) för omdömen och leads.
- Lägg till sidmallar för integritetspolicy, riktlinjer för omdömen och "Om oss / Så fungerar betygen", med text som förklarar att placering i topplistor inte kan köpas.

## Leverans

1. Fullständig filstruktur för tema och plugin, med alla filer kompletta. Inga "TODO" eller "...".
2. `README.md` med installation, sidinställningar, importexempel, WP-CLI-kommandon och hur man byter paket.
3. `import-mall.csv` och en demodatafil (20 fiktiva rekryteringsföretag, 50 omdömen, 30 jobb och jobbportalerna) som kan importeras med `wp rp seed-demo`.
4. PHPCS-konfiguration och grundläggande PHPUnit-tester för betygsberäkningen (snitt och Bayes), dubblettkontrollen vid import och leads-matchningen.
5. En kort lista över antaganden du gjort och vad som behöver konfigureras manuellt (SMTP, priser, juridisk text).

Arbeta i steg: börja med plugin-datamodellen och importen, sedan betygssystemet, sedan mallarna och sist paket- och leadsflödet. Visa filstrukturen innan du skriver koden.
