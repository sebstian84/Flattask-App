# Betriebskonzept: Todo Application

## 1. Einleitung
Dieses Betriebskonzept beschreibt die technische Architektur, die Systemvoraussetzungen, Sicherheitsaspekte sowie die Bereitstellung und Wartung der Todo Application. Es dient als Leitfaden für Administratoren und Entwickler, um einen sicheren und stabilen Betrieb der Anwendung zu gewährleisten.

## 2. Architekturübersicht
Die Anwendung folgt einer klassischen Client-Server-Architektur mit einer klaren Trennung von Frontend und Backend.

### 2.1 Frontend
- **Technologie-Stack:** Vue 3 (Composition API), Vite (Build-Tool), Axios (HTTP-Client).
- **Libraries:** `vuedraggable` für Drag & Drop, `lucide-vue-next` für Icons.
- **Bereitstellung:** Als statische HTML/CSS/JS-Dateien (Single-Page Application).

### 2.2 Backend
- **Technologie-Stack:** PHP 8.x.
- **API-Struktur:** Das Backend besteht primär aus einer zentralen Datei (`api/index.php`), die als Router fungiert und REST-ähnliche Endpunkte (GET/POST) bereitstellt.
- **Datenbank:** Es wird kein relationales oder NoSQL-Datenbankmanagementsystem (wie MySQL oder MongoDB) verwendet. Stattdessen basiert die Datenhaltung auf einem Flat-File-Ansatz (JSON-Dateien), was den Betrieb und das Backup stark vereinfacht.

## 3. Datenhaltung & Persistenz
Alle persistierten Daten liegen im Verzeichnis `api/data/`. Der Webserver benötigt Schreib- und Leserechte für dieses Verzeichnis.

- `db.json`: Speichert alle aktuell aktiven Todos (inklusive Status 'offen'/'erledigt').
- `archive.json`: Speichert alle gelöschten/archivierten Todos.
- `users.json`: Speichert die Zugangsdaten (Benutzername und Passwort).
- `settings.json`: Speichert applikationsweite UI-Einstellungen (Sortierung, Gruppierung, Filterung nach Erledigten, etc.).
- `changelog.json`: Speichert eine Historie der Änderungen für jedes Todo. Jeder Eintrag enthält detaillierte Vorher- (`oldData`) und Nachher-Werte (`newData`), um genaue Vergleiche in der Historienansicht zu ermöglichen.

## 4. Sicherheit & Datenschutz

### 4.1 Verschlüsselung auf Dateiebene (Data at Rest)
Um die sensiblen Daten in den JSON-Dateien zu schützen, implementiert das Backend eine symmetrische Verschlüsselung:
- **Algorithmus:** AES-256-CBC.
- **Vorgang:** Vor dem Schreiben auf die Festplatte wird der JSON-String mit `openssl_encrypt` verschlüsselt. Beim Einlesen erfolgt die Entschlüsselung.
- **Schlüssel:** Der kryptografische Schlüssel ist statisch im PHP-Code (`$encryption_key`) hinterlegt. Für produktive Umgebungen sollte dieser Key über Umgebungsvariablen (Environment Variables) in den Server injiziert werden.

### 4.2 Authentifizierung & Autorisierung
- **Mechanismus:** Token-basierte Authentifizierung.
- **Ablauf:** Bei erfolgreichem Login wird ein Token generiert (`todo_token_secure_` + Username) und als HTTPOnly, Lax, SameSite Cookie (`todo_auth_token`) an den Client gesendet. Zusätzlich erhält der Client das Token im Body für den lokalen Storage.
- **Sicherheitsvorteil:** Das Cookie schützt weitestgehend vor XSS-Angriffen beim API-Zugriff, während der LocalStorage für die initiale UI-Zustandsprüfung genutzt wird.

### 4.3 CORS (Cross-Origin Resource Sharing)
- Das Backend erlaubt Zugriffe vom via `$_SERVER['HTTP_ORIGIN']` ermittelten Origin und lässt explizit Credentials (`Access-Control-Allow-Credentials: true`) zu. Preflight-Anfragen (OPTIONS) werden korrekt gehandhabt.

## 5. Systemvoraussetzungen & Deployment

### 5.1 Infrastruktur
- **Webserver:** Apache oder Nginx.
- **PHP:** Version 8.0 oder höher mit aktivierter `openssl` und `json` Extension.
- **Node.js:** Nur für die Entwicklung und den Build-Prozess (`npm install && npm run build`).

### 5.2 Deployment-Prozess
1. **Frontend bauen:** Im Stammverzeichnis `npm run build` ausführen. Dies generiert die statischen Assets im `dist/` Ordner.
2. **Dateien kopieren:** Den Inhalt des `dist/` Ordners auf den Webserver kopieren.
3. **Backend bereitstellen:** Den Ordner `api/` auf den Webserver in das Root-Verzeichnis der App kopieren.
4. **Berechtigungen:** Sicherstellen, dass der PHP-Prozess (z. B. `www-data`) Lese- und Schreibrechte für das Verzeichnis `api/data/` besitzt.
5. **Webserver-Konfiguration:**
   - **Apache:** Eine `.htaccess` ist ggf. erforderlich, um Zugriffe auf `api/data/` direkt über den Browser zu blockieren (z. B. `Deny from all` im `api/data/` Verzeichnis).
   - Umleitung aller API-Requests auf `api/index.php`.

## 6. Monitoring, Logging & Wartung

### 6.1 Application Logging
Änderungen an Todos (Erstellen, Updaten, Löschen, Wiederherstellen) werden programmatisch in der `changelog.json` protokolliert. Dies dient dem fachlichen Audit-Trail. Die Datenstruktur speichert exakt, welche Felder sich wie verändert haben, und erlaubt ein gezieltes Rückgängigmachen oder Löschen einzelner Historien-Einträge über die UI.

### 6.2 Backup-Strategie
- **Manuell über UI:** Der Benutzer kann über die Oberfläche ein vollständiges Backup im JSON-Format herunterladen (und wieder importieren).
- **Serverseitig:** Da alle Daten in `api/data/` liegen, genügt ein einfaches, regelmäßiges Datei-Backup dieses Ordners mittels Cronjob (z. B. rsync oder tar). Datenbank-Dumps entfallen komplett.

## 7. Skalierbarkeit & Limitierungen
- Das System ist in der aktuellen Flat-File-Architektur auf einen (oder sehr wenige gleichzeitige) Nutzer ausgelegt (Single-User bzw. Small-Team).
- **Concurrency:** Da PHP direkt in die Dateien schreibt (`file_put_contents`), kann es bei gleichzeitigen Schreibvorgängen ohne Dateisperren (File-Locks) theoretisch zu Datenverlust kommen. Für eine breitere Nutzung müssten `flock()` oder eine relationale Datenbank (z.B. SQLite/MySQL) eingeführt werden.
- **Performance:** Das komplette Einlesen und Entschlüsseln der JSON-Dateien bei jedem API-Aufruf ist für Listen im Bereich von tausenden Todos performant, skaliert aber nicht für Big Data. Für den angedachten Anwendungsfall ist dies jedoch absolut ausreichend.
