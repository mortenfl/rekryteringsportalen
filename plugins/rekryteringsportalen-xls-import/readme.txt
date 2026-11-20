=== Rekryteringsportalen XLS-import ===
Requires at least: 6.6
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPLv2 or later

Importera rekryteringsföretag från Excel-filer (.xlsx, .xlsm, .xls).

== Beskrivning ==

Tillägg till Rekryteringsportalen Core (måste vara aktivt för att importen ska fungera, men låser inte Core – Core kan inaktiveras när som helst). Gör att importen under Rekryteringsföretag → Importera företag
(och `wp rp import`) även tar emot Excel-filer.

* .xlsx/.xlsm (Excel 2007 och senare) och .xls (Excel 97–2003).
* Inga externa bibliotek. Kräver PHP-tilläggen zip och xmlreader (finns hos nästan alla webbhotell) för .xlsx.
* Första raden med innehåll används som kolumnrubriker. Tomma rader och tomma kolumner hoppas över.
* Datumceller blir ÅÅÅÅ-MM-DD. Långa tal som organisationsnummer behåller alla siffror.
* Välj flik med namn eller nummer. Tomt = första fliken som innehåller data.
* Samma kolumnkoppling, förhandsgranskning och dubblettkontroll som CSV-importen.

Färdiga mallar finns i data/import-mall.xlsx och data/import-mall.xls (länkas även från importsidan).

== Installation ==

1. Installera och aktivera Rekryteringsportalen Core.
2. Ladda upp och aktivera det här pluginet.
3. Gå till Rekryteringsföretag → Importera företag och ladda upp en Excel-fil.

WP-CLI: wp rp import foretag.xlsx --sheet="Blad1" --status=draft --dry-run

== Begränsningar ==

* Formler importeras med det värde Excel senast sparade. Filer som skapats av program som inte räknar ut formler (vissa exportverktyg) kan sakna värden i formelceller.
* Lösenordsskyddade/krypterade filer kan inte läsas. Ta bort skyddet först.
* "Excel-filer" som egentligen är HTML-tabeller (vanligt vid export från webbsystem) känns igen och ger ett tydligt felmeddelande. Öppna och spara dem som .xlsx.
