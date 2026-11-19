# Ändringslogg

## Rekryteringsportalen Core

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

### 1.1.0
- Mall i `.xls`-format (Excel 97–2003) utöver `.xlsx`.

### 1.0.0
- Första versionen: import från `.xlsx`, `.xlsm` och `.xls`.

## Tema Rekryteringsportalen

### 1.1.0
- Topp 10 i två kolumner. Bottenlistan är borttagen.
- Rättat: punktlistan i Paket 2 och samtyckesrutans radbrytning.
- Stil för fältet "E-post (valfritt)".

### 1.0.0
- Första versionen.
