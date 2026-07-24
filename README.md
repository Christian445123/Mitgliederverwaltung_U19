# Mitgliederverwaltung

Einfache PHP/MySQL-Anwendung zur Mitgliederverwaltung mit Admin-Login und
öffentlichem Selbstauskunfts-Link je Mitglied.

## Setup

1. **Datenbank anlegen**
   Die Tabellen `admins` und `members` werden automatisch beim ersten
   Seitenaufruf angelegt (siehe "Auto-Migration" unten) – ein manueller
   SQL-Import ist nicht mehr zwingend nötig. `sql/schema.sql` dient nur noch
   als Referenz/Dokumentation des Schemas.

2. **Zugangsdaten eintragen**
   In `config/config.php` `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` sowie
   `BASE_URL` (die öffentlich erreichbare URL des Projekts, ohne
   abschließenden Slash) eintragen.

3. **Ersten Admin-Account anlegen**
   ```
   php bin/create_admin.php <benutzername> <passwort>
   ```
   Das Passwort muss mindestens 8 Zeichen haben. Das Script kann später
   erneut ausgeführt werden, um das Passwort desselben Benutzers zu ändern.

4. **Aufrufen**
   - `admin/login.php` – Admin-Login
   - `admin/index.php` – Mitgliederliste (nach Login), Anlegen/Bearbeiten/Löschen
   - `verify.php?token=...` – öffentliche Selbstauskunftsseite, der Link wird
     beim Anlegen eines Mitglieds automatisch erzeugt und ist über
     „Link“ in der Mitgliederliste jederzeit abrufbar bzw. neu generierbar.

## Ablauf der Datenprüfung durch Mitglieder

Beim Anlegen eines Mitglieds werden automatisch ein zufälliger, nicht
erratbarer Link-Token sowie ein separater Zugangscode (Zufallspasswort)
erzeugt. Der Link (`verify.php?token=...`) allein zeigt **keine** Daten an –
das Mitglied muss zusätzlich seine hinterlegte E-Mail-Adresse und den
Zugangscode eingeben. Erst danach wird das mit den hinterlegten Daten
vorausgefüllte Formular angezeigt. Stimmen die Daten nicht (mehr), kann das
Mitglied sie direkt dort korrigieren und speichern – der Zeitpunkt der
Bestätigung wird in der Mitgliederliste als „Daten geprüft“ angezeigt.
Persönliche Felder wie Mitgliedsnummer und Status sind über diesen Link nicht
änderbar, das bleibt dem Admin-Bereich vorbehalten.

**Sicherheits-/Datenschutzmaßnahmen (DSGVO):**
- Zugangscode wird nur als Hash gespeichert und dem Admin nur einmalig direkt
  nach dem Anlegen bzw. Neu-Generieren angezeigt – Link und Zugangscode
  sollten über getrennte Kanäle (z. B. E-Mail für den Link, Telefon/SMS für
  den Code) an das Mitglied übermittelt werden.
- Nach 5 falschen Zugangsversuchen wird der Zugang für 15 Minuten gesperrt
  (Brute-Force-Schutz).
- Link und/oder Zugangscode können im Admin-Bereich (`admin/member_link.php`)
  jederzeit unabhängig voneinander neu generiert werden (der jeweils alte
  Wert wird damit ungültig).
- Auf der öffentlichen Seite wird ein kurzer Datenschutzhinweis angezeigt.
  **Der Platzhaltertext ist noch durch eure echte Datenschutzerklärung /
  einen Link darauf zu ersetzen** – das ist eine inhaltliche/rechtliche
  Aufgabe, die der Verein (ggf. mit Datenschutzberater) festlegen muss und
  die nicht durch Code allein "DSGVO-konform" gemacht werden kann.

Bereits vor dieser Änderung angelegte Mitglieder haben noch keinen
Zugangscode – für sie muss einmalig über „Neuen Zugangscode generieren“ in
`member_link.php` ein Code erzeugt werden, bevor ihr Link nutzbar ist.

## Auto-Migration

`includes/migrate.php` prüft bei jedem Datenbank-Verbindungsaufbau
(`includes/db.php`), ob alle benötigten Tabellen und Spalten existieren, und
legt fehlende automatisch an (`CREATE TABLE IF NOT EXISTS` / `ALTER TABLE ...
ADD COLUMN`). Bestehende Spalten/Daten werden dabei nie verändert oder
gelöscht. Soll künftig ein neues Mitglieds-Feld hinzukommen, reicht es, es in
`membersColumnDefinitions()` in `includes/migrate.php` zu ergänzen (plus im
Formular in `admin/member_form.php` und `verify.php`) – die Spalte wird beim
nächsten Seitenaufruf automatisch in der Datenbank angelegt, ganz ohne
manuellen SQL-Import.

## Hinweise zum Betrieb

- Für den produktiven Einsatz **unbedingt HTTPS** verwenden – in
  `config/config.php` ist `FORCE_HTTPS_COOKIE` auf `true` gesetzt, damit
  Session-Cookies nur über HTTPS übertragen werden.
- `DEBUG` in `config/config.php` im Produktivbetrieb auf `false` belassen.
- Die Ordner `config/`, `includes/`, `sql/` und `bin/` sind per `.htaccess`
  gegen direkten Web-Zugriff abgesichert (funktioniert nur unter Apache mit
  aktiviertem `.htaccess`-Support – bei anderen Webservern z. B. Nginx müssen
  diese Ordner analog in der Server-Konfiguration gesperrt oder komplett
  außerhalb des Web-Roots abgelegt werden).
