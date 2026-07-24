# Mitgliederverwaltung

Einfache PHP/MySQL-Anwendung zur Mitgliederverwaltung mit Admin-Login und
öffentlichem Selbstauskunfts-Link je Mitglied.

## Setup

1. **Datenbank anlegen**
   Das Schema in `sql/schema.sql` importieren (z. B. via phpMyAdmin oder
   `mysql -u root -p < sql/schema.sql`). Es legt die Datenbank
   `mitgliederverwaltung` sowie die Tabellen `admins` und `members` an.

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

Beim Anlegen eines Mitglieds wird automatisch ein zufälliger, nicht erratbarer
Token erzeugt. Der daraus gebildete Link (`verify.php?token=...`) zeigt dem
Mitglied ein mit den hinterlegten Daten vorausgefülltes Formular. Stimmen die
Daten nicht (mehr), kann das Mitglied sie direkt dort korrigieren und
speichern – der Zeitpunkt der Bestätigung wird in der Mitgliederliste als
„Daten geprüft“ angezeigt. Persönliche Felder wie Mitgliedsnummer und Status
sind über diesen Link nicht änderbar, das bleibt dem Admin-Bereich
vorbehalten. Bei Verdacht auf Missbrauch kann der Link im Admin-Bereich
jederzeit neu generiert werden (der alte wird damit ungültig).

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
