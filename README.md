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
   `config/config.example.php` nach `config/config.php` kopieren und dort
   `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` sowie `BASE_URL` (die öffentlich
   erreichbare URL des Projekts, ohne abschließenden Slash) eintragen.
   `config/config.php` ist per `.gitignore` von Git ausgeschlossen und darf
   **niemals** eingecheckt werden, da sie das Datenbank-Passwort enthält.

3. **Ersten Admin-Account anlegen**
   ```
   php bin/create_admin.php <benutzername> <passwort>
   ```
   Das Passwort muss mindestens 8 Zeichen haben. Das Script kann später
   erneut ausgeführt werden, um das Passwort desselben Benutzers zu ändern.

4. **Aufrufen**
   - `admin/login.php` – Admin-Login
   - `admin/index.php` – Mitgliederliste (nach Login), Anlegen/Bearbeiten/Löschen
   - `admin/users.php` – Benutzerverwaltung (nur für Administratoren, siehe unten)
   - `verify.php?token=...` – öffentliche Selbstauskunftsseite, der Link wird
     beim Anlegen eines Mitglieds automatisch erzeugt und ist über
     „Link“ in der Mitgliederliste jederzeit abrufbar bzw. neu generierbar.

## Benutzerverwaltung & Rollen

Es gibt zwei Rollen:
- **Administrator**: alles, inkl. Benutzerverwaltung (Benutzer anlegen,
  bearbeiten, löschen, Rollen vergeben).
- **Bearbeiter**: kann Mitglieder anlegen/bearbeiten/löschen und
  Links/Zugangscodes verwalten, sieht/nutzt die Benutzerverwaltung aber nicht
  (auch nicht per direktem Aufruf der URL – serverseitig abgesichert).

Der über `bin/create_admin.php` angelegte erste Account wird automatisch
Administrator. Weitere Benutzer legt man danach bequem über
`admin/users.php` → „+ Neuer Benutzer“ an. Schutzmechanismen: man kann sich
nicht selbst löschen, und der letzte verbleibende Administrator kann weder
gelöscht noch auf „Bearbeiter“ herabgestuft werden (damit niemand sich
versehentlich aussperrt).

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
  (Brute-Force-Schutz) – sowohl für den öffentlichen Verifizierungs-Link als
  auch für den Admin-Login.
- Link und/oder Zugangscode können im Admin-Bereich (`admin/member_link.php`)
  jederzeit unabhängig voneinander neu generiert werden (der jeweils alte
  Wert wird damit ungültig).
- Es gibt **kein fest eingebautes Standard-/Default-Passwort**: Admin-Accounts
  werden ausschließlich explizit über `bin/create_admin.php <benutzer>
  <passwort>` (mind. 8 Zeichen) bzw. über `admin/user_form.php` angelegt, und
  jedes Mitglied erhält einen individuellen, zufällig erzeugten 10-stelligen
  Zugangscode (siehe `generateAccessPassword()` in `includes/functions.php`).
- Passwort-Hashes (`password_hash()`/bcrypt) für Admins und Mitglieds-Zugangscodes,
  nie Klartext-Speicherung.
- Sicherheits-Header (`Content-Security-Policy`, `X-Frame-Options`,
  `X-Content-Type-Options`, `Referrer-Policy`) werden auf allen Seiten gesetzt.
- Zugangsdaten zur Datenbank liegen ausschließlich in der nicht versionierten
  `config/config.php` (siehe oben), nicht im Repository.
- **Gesundheitsdaten:** Das Feld „Allergien“ ist eine besondere Kategorie
  personenbezogener Daten (Art. 9 DSGVO). Die Erhebung ist nur zulässig, wenn
  eine gültige Rechtsgrundlage vorliegt (i. d. R. ausdrückliche Einwilligung,
  z. B. aus Sicherheitsgründen bei Sportveranstaltungen). Das muss der Verein
  über die Beitritts-/Einwilligungserklärung sicherstellen – das Feld sollte
  nur ausgefüllt werden, wenn eine solche Einwilligung vorliegt, und ist im
  Formular optional.
- Auf der öffentlichen Seite wird ein kurzer Datenschutzhinweis angezeigt.
  **Der Platzhaltertext ist noch durch eure echte Datenschutzerklärung /
  einen Link darauf zu ersetzen** – das ist eine inhaltliche/rechtliche
  Aufgabe, die der Verein (ggf. mit Datenschutzberater) festlegen muss und
  die nicht durch Code allein "DSGVO-konform" gemacht werden kann.

**Noch offene, organisatorische DSGVO-Punkte (nicht durch Code lösbar):**
- Löschkonzept/Aufbewahrungsfristen für inaktive Mitglieder festlegen (Prinzip
  der Speicherbegrenzung, Art. 5 Abs. 1 lit. e DSGVO) – die Anwendung löscht
  nichts automatisch.
- Auftragsverarbeitungsvertrag (AVV) mit dem Hosting-Provider abschließen,
  falls nicht bereits vorhanden.
- Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO) führen.
- Echte Datenschutzerklärung verlinken (siehe oben).

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
