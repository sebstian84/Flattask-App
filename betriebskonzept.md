# Betriebskonzept: Flattask Application

## 1. Einleitung
Dieses Betriebskonzept beschreibt die technische Architektur, die Systemvoraussetzungen, Sicherheitsaspekte sowie die Bereitstellung und Wartung der **Flattask Application**. Es dient als Leitfaden für Administratoren und Entwickler, um einen sicheren und stabilen Betrieb der Anwendung zu gewährleisten.

## 2. Architekturübersicht
Die Anwendung folgt einer klassischen Client-Server-Architektur mit einer klaren Trennung von Frontend und Backend.

### 2.1 Frontend
- **Technologie-Stack:** Vue 3 (Composition API), Vite (Build-Tool), Axios (HTTP-Client).
- **Libraries:** `vuedraggable` für Drag & Drop, `lucide-vue-next` für Icons.
- **Features:** Clientseitiges State-Management für UI-Zustände (Dark Mode, Filter, Sortierung).
- **Bereitstellung:** Als statische HTML/CSS/JS-Dateien (Single-Page Application).

### 2.2 Backend
- **Technologie-Stack:** PHP 8.x.
- **API-Struktur:** Das Backend besteht primär aus einer zentralen Datei (`api/index.php`), die als Router fungiert und REST-ähnliche Endpunkte bereitstellt.
- **Datenbank:** Flat-File-Ansatz (JSON-Dateien) im Verzeichnis `api/data/`. Dies ermöglicht einen betriebsarmen ("Zero-Maintenance") Ansatz ohne Datenbankserver.

## 3. Datenhaltung & Persistenz
Alle persistierten Daten liegen verschlüsselt im Verzeichnis `api/data/`.

- `db.json`: Aktive Aufgaben (inkl. `pinned`-Status und `order`).
- `archive.json`: Archivierte Aufgaben.
- `users.json`: Benutzerprofile (Hashed Passwords).
- `settings.json`: UI-Einstellungen und Theme-Präferenzen (z.B. Dark Mode).
- `changelog.json`: Audit-Trail aller Änderungen für Undo-Operationen.

## 4. Sicherheit & Datenschutz

### 4.1 Verschlüsselung (Data at Rest)
Alle JSON-Dateien werden symmetrisch mit **AES-256-CBC** verschlüsselt, bevor sie auf das Filesystem geschrieben werden. Dies schützt die Inhalte bei unbefugtem Zugriff auf den Webspace.

### 4.2 Authentifizierung
- **Session-Handling:** Token-basierte Authentifizierung via HTTPOnly-Cookies.
- **Persistence:** Speicherung der Session-Präferenz im LocalStorage zur Vermeidung von FOUC (Flash of Unstyled Content) beim Dark Mode.

## 5. Systemvoraussetzungen & Deployment

### 5.1 Infrastruktur
- **Webserver:** Apache oder Nginx (PHP 8.0+ erforderlich).
- **SSL/TLS:** Eine HTTPS-Verbindung ist zwingend erforderlich, da Passwörter und Auth-Tokens übertragen werden.

### 5.2 Deployment
1. Build-Prozess via `npm run build`.
2. Transfer der `dist/` Inhalte und des `api/` Ordners auf den Webserver.
3. Setzen von Schreibrechten (chmod 775/777) auf den Ordner `api/data/`.

## 6. Wartung & Backup

### 6.1 Monitoring
Da das System keine externe Datenbank nutzt, beschränkt sich das Monitoring auf die Verfügbarkeit des Webservers und die Speicherkapazität für die JSON-Dateien.

### 6.2 Backup
- **Client-Side:** Der Nutzer kann jederzeit manuelle Backups exportieren.
- **Server-Side:** Tägliche Sicherung des `api/data/` Verzeichnisses wird empfohlen.

## 7. Skalierbarkeit
Das System ist für Einzelnutzer optimiert. Bei Lasttests mit über 500 Datensätzen (verschlüsselt) blieb die Antwortzeit unter 100ms. Bei einer Skalierung auf mehrere tausend gleichzeitige Nutzer oder sehr große Datenmengen (10.000+ Tasks) sollte ein Wechsel auf SQLite in Betracht gezogen werden.
