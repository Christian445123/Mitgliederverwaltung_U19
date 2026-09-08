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
   `.env.example` nach `.env` kopieren (Projekt-Wurzelverzeichnis) und dort
   alle Werte eintragen: `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`, `SMTP_*` für
   den E-Mail-Versand (siehe Abschnitt „E-Mail-Versand“ unten), `BASE_URL`
   (die öffentlich erreichbare URL des Projekts, ohne abschließenden Slash),
   sowie `FORCE_HTTPS_COOKIE`/`DEBUG`.

   Es gibt keine separate `config.php` mehr – `includes/env.php` liest die
   `.env` beim ersten Include automatisch ein und definiert daraus alle
   Konstanten. `.env` ist per `.gitignore` von Git ausgeschlossen und darf
   **niemals** eingecheckt werden, da sie Passwörter enthält.

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
- Zugangsdaten (Datenbank, SMTP) liegen ausschließlich in der nicht
  versionierten `.env` (siehe oben), nicht im Repository.
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

## E-Mail-Versand

Über `admin/member_link.php` kann der Verifizierungs-Link direkt per E-Mail an
das Mitglied verschickt werden (Button „Link per E-Mail an Mitglied senden“).
Der Versand nutzt einen schlanken, selbst geschriebenen SMTP-Client
(`includes/mailer.php`, kein Composer/PHPMailer nötig) und liest die
Zugangsdaten aus der `.env`:

```
SMTP_HOST=mail.eurehostingfirma.at
SMTP_PORT=587
SMTP_ENCRYPTION=STARTTLS   # STARTTLS/TLS oder SSL
SMTP_USERNAME=noreply@euerverein.at
SMTP_PASSWORD=...
SMTP_FROM=noreply@euerverein.at
```

Ist `SMTP_HOST` leer, ist der Mail-Versand deaktiviert und der Button zeigt
eine Fehlermeldung statt zu versenden. Aus Sicherheitsgründen wird **nur der
Link** per E-Mail verschickt, niemals der Zugangscode (siehe „getrennte
Kanäle“ oben).

⚠️ Falls euer `SMTP_PASSWORD` mit einem Präfix wie `ENC:` beginnt: das ist
kein Format, das `includes/mailer.php` versteht – dort wird der Wert 1:1 als
Passwort für die SMTP-Anmeldung verwendet. Ein `ENC:`-Wert stammt vermutlich
aus einem anderen Tool/einer anderen Verschlüsselung und muss durch das
**echte Klartext-Passwort** des Mail-Postfachs ersetzt werden, sonst schlägt
die Anmeldung beim Mailserver fehl.

## Mitglieds-Datenfelder (Team, Ausrüstung, Reisedokumente)

Neben den Basisdaten erfasst die Anwendung noch:
- **Team & Spielbetrieb** (nur Admin): Spielernummer, Position, Bezirk,
  Herkunftsverein, Körpergröße, Gewicht.
- **Zertifikate** (nur Admin): NADA-Zertifikat/-Erlaubnis gültig bis.
- **Reisedokumente** (Mitglied + Admin): Geburtsland/-ort, Reisepassnummer,
  ausgestellt am/gültig bis, Ausstellungsbehörde, Sozialversicherungsnummer,
  Passfoto-Upload.
- **Ausrüstung** (Mitglied + Admin): Größen für Jersey, Hose, Mesh Shorts,
  Helm, T-Shirt/Polo (MACRON), Hoodie (MACRON), ob ein eigener Helm vorhanden
  ist, sowie Ernährungshinweise („Essen“).
- **Einwilligungen** (mit Zeitstempel als Nachweis): Akzeptanz der Rechte &
  Pflichten (Pflicht beim Speichern über den Mitglieder-Link) sowie optionale
  Einwilligung zur Bildnutzung.

**Besonders sensible Felder – zusätzliche Sorgfaltspflichten:**
- **Sozialversicherungsnummer**: ein staatlicher Personenidentifikator. Wird
  im Admin-Bereich nur maskiert angezeigt (`maskSvnr()` in
  `includes/functions.php`, z. B. `••••••1234`) und kann dort nur durch
  Neueingabe geändert, nicht eingesehen werden. Im eigenen
  Selbstauskunfts-Formular (`verify.php`) sieht das Mitglied naturgemäß seine
  eigene volle Nummer.
- **Passfotos**: werden in `uploads/members/` gespeichert, das per
  `.htaccess` gegen direkten Web-Zugriff gesperrt ist. Ausgeliefert werden sie
  ausschließlich über die authentifizierten Endpunkte
  `admin/member_photo.php` (Admin-Login erforderlich) bzw. `verify_photo.php`
  (nur nach erfolgreicher Freischaltung des jeweiligen Mitglieds).
- **Reisepassdaten**: nur für Meisterschafts-/Turnier-Meldungen bzw.
  internationale Reisen erheben – im Zweifel mit dem Verband/Datenschutz-
  berater abstimmen, ob und wie lange diese Daten nach der jeweiligen
  Veranstaltung noch benötigt werden.

## Anwendung aktualisieren (Deployment)

Läuft die Anwendung auf dem Server als Git-Checkout, gibt es zwei Wege, den
neuesten Code zu ziehen - beide nutzen dieselbe Logik (`includes/updater.php`):

- **Button im Admin-Bereich** (nur für Administratoren sichtbar): „Update“ in
  der Navigation → `admin/update.php` → „Jetzt aktualisieren (git pull)“.
  Zeigt den Ablauf inkl. Git-Ausgabe direkt im Browser an. Voraussetzung:
  `proc_open()` darf auf dem Server für PHP-Webanfragen nicht deaktiviert sein
  (bei manchen Hostern ist das aus Sicherheitsgründen nur für die
  Kommandozeile erlaubt - dann bricht der Button mit einer entsprechenden
  Meldung ab, und man nutzt stattdessen die Kommandozeile).
- **Kommandozeile** (funktioniert immer, auch wenn `proc_open()` im Web
  gesperrt ist):
  ```
  php bin/update.php
  ```

Beide Wege ziehen den Code per `git pull --ff-only` und stoßen danach sofort
die Datenbank-Migration an. Es wird **abgebrochen, ohne etwas zu verändern**,
falls es auf dem Server nicht committete lokale Änderungen gibt (Schutz vor
versehentlichem Datenverlust) oder falls `git pull` wegen divergierter
Historie nicht als reines Fast-Forward möglich ist.

## Notfall-Zugang ohne Datenbank

Für den seltenen Fall, dass die `admins`-Tabelle leer/beschädigt ist oder die
Datenbank nicht erreichbar ist, gibt es einen optionalen, standardmäßig
**deaktivierten** Notfall-Login (`EMERGENCY_ADMIN_USERNAME` /
`EMERGENCY_ADMIN_PASSWORD_HASH` in der `.env`), der ohne Datenbankabfrage
funktioniert:

1. Passwort-Hash erzeugen: `php bin/hash_password.php <ein_langes_passwort>`
2. In der `.env` eintragen:
   ```
   EMERGENCY_ADMIN_USERNAME=notfall
   EMERGENCY_ADMIN_PASSWORD_HASH=<hier der erzeugte Hash>
   ```
3. Damit kann man sich in `admin/login.php` unabhängig von der `admins`-Tabelle
   anmelden und z. B. über `admin/users.php` einen regulären Administrator
   wiederherstellen.

**Wichtig:**
- Standardmäßig leer = deaktiviert. Nur aktivieren, wenn wirklich benötigt.
- Es zeigt einen deutlichen Warn-Banner im Admin-Bereich, solange man darüber
  angemeldet ist.
- Fehlversuche werden dateibasiert gesperrt (`data/emergency_login.json`,
  5 Versuche/15 Minuten), da hierfür keine Datenbank zur Verfügung steht.
- Diese Zugangsdaten sollten nach der eigentlichen Notfall-Nutzung wieder aus
  der `.env` entfernt bzw. das Passwort geändert werden.
- Funktioniert nur, wenn die Datenbank selbst erreichbar ist ODER die
  `admins`-Tabelle das Problem ist – bei einem echten DB-Verbindungsausfall
  kommt man zwar in den Admin-Bereich, die eigentliche Mitgliederverwaltung
  benötigt aber weiterhin eine funktionierende Datenbank.

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

- Für den produktiven Einsatz **unbedingt HTTPS** verwenden – `FORCE_HTTPS_COOKIE`
  in der `.env` sollte auf `true` stehen, damit Session-Cookies nur über
  HTTPS übertragen werden.
- `DEBUG` in der `.env` im Produktivbetrieb auf `false` belassen.
- Die Ordner `includes/`, `sql/`, `bin/` und `uploads/` sind per
  `.htaccess` gegen direkten Web-Zugriff abgesichert, die `.env` zusätzlich
  über eine Regel in der Root-`.htaccess` (funktioniert nur unter Apache mit
  aktiviertem `.htaccess`-Support – bei anderen Webservern z. B. Nginx müssen
  diese Ordner/Dateien analog in der Server-Konfiguration gesperrt oder
  komplett außerhalb des Web-Roots abgelegt werden).
