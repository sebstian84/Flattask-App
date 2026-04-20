# Fachkonzept: Todo Application

## 1. Einleitung und Zielsetzung
Die vorliegende Anwendung ist eine webbasierte, Single-Page Application (SPA) zur Verwaltung von Aufgaben (Todos). Ziel ist es, dem Nutzer ein intuitives, schnelles und hochgradig anpassbares Werkzeug für das persönliche oder berufliche Task-Management zur Verfügung zu stellen. Die Applikation zeichnet sich durch flexible Gruppierungs- und Filterfunktionen, Drag-and-Drop-Unterstützung sowie ein integriertes Tag-System aus.

## 2. Zielgruppe & Anwendungsbereich
Die Anwendung richtet sich in ihrer aktuellen Ausprägung primär an Einzelnutzer (Single-User-Betrieb), die eine datenschutzfreundliche und leichtgewichtige Lösung zur Aufgabenverwaltung suchen. Der Anwendungsbereich erstreckt sich von einfachen Einkaufslisten bis hin zur Organisation komplexer, terminierter Projekte mittels Kalenderwochen- oder Monatsgruppierungen.

## 3. Funktionsübersicht

### 3.1 Todo-Verwaltung (CRUD)
- **Erstellen:** Neue Aufgaben können mit einem Titel, einem optionalen Zieldatum, einer Rich-Text-Beschreibung und beliebigen Tags angelegt werden.
- **Lesen & Anzeigen:** Aufgaben werden übersichtlich in einer Liste oder gruppiert dargestellt.
- **Aktualisieren:** Inline-Bearbeitung von Titel und Zieldatum. Die Rich-Text-Beschreibung kann über einen dedizierten Editor (inkl. Formatierungen) angepasst werden.
- **Löschen & Archivieren:** Aufgaben werden nicht sofort physisch gelöscht, sondern in ein Archiv verschoben. Aus dem Archiv können sie dauerhaft gelöscht oder wiederhergestellt werden.

### 3.2 Strukturierung & Organisation
- **Tag-System:** Todos können mit mehreren Tags versehen werden. Eine Autovervollständigung (Suggested Tags) erleichtert die Zuweisung.
- **Filterung:** Die Ansicht kann nach einem oder mehreren Tags gefiltert werden. Dabei wird zwischen inklusiver (ODER-Logik) und exklusiver (UND-Logik) Filterung unterschieden.
- **Gruppierung:** Aufgaben können dynamisch gruppiert werden nach:
  - **Zeitlich:** Täglich, Wöchentlich (Kalenderwochen), Monatlich.
  - **Thematisch:** Nach Tags.
- **Sortierung:** Eine manuelle Sortierung per Drag-and-Drop ist möglich (sofern keine speziellen Filter/Gruppierungen aktiv sind), alternativ kann nach Zieldatum sortiert werden.

### 3.3 Benutzeroberfläche & Interaktion (UI/UX)
- **Responsive Design:** Die Oberfläche passt sich dynamisch an Desktop- und Mobilgeräte an.
- **Drag & Drop:** Aufgaben können per Maus oder Touch-Gesten frei verschoben oder zwischen verschiedenen Gruppierungen (z. B. von einer Kalenderwoche in die nächste) umverteilt werden, wobei sich das Zieldatum automatisch anpasst.
- **Rich-Text Editor:** Für detailreiche Beschreibungen der Aufgaben.

### 3.4 Authentifizierung & Profilverwaltung
- Ein Login-System schützt die Daten vor unbefugtem Zugriff.
- Der Benutzername und das Passwort können über die Profilverwaltung innerhalb der App geändert werden.

### 3.5 Backup & Wiederherstellung
- Die Anwendung bietet eine integrierte Exportfunktion, mit der alle aktuellen Todos, das Archiv und die Einstellungen als JSON-Datei heruntergeladen werden können.
- Ein Import-Dialog ermöglicht die Wiederherstellung oder Zusammenführung aus bestehenden Backups, wobei Duplikate automatisch erkannt und übersprungen werden.

## 4. Datenmodell (Logische Struktur)

- **Todo-Objekt:**
  - `id`: Eindeutiger Identifier (Timestamp).
  - `name`: Kurztitel der Aufgabe.
  - `description`: Detaillierte Beschreibung (HTML/Rich-Text).
  - `targetDate`: Optionales Fälligkeits- oder Zieldatum (YYYY-MM-DD).
  - `tags`: Array von Strings.
  - `order`: Integer für die manuelle Sortierreihenfolge.
- **Settings-Objekt:**
  - Speichert UI-Zustände (aktive Tags, Gruppierungsart, Sortierung), um diese sitzungsübergreifend beizubehalten.
- **Changelog-Objekt:**
  - Protokolliert Änderungen an Todos (Erstellung, Aktualisierung, Löschung) für eine spätere Nachvollziehbarkeit oder Undo-Funktionen.

## 5. Abnahmekriterien & Qualitätssicherung
- Alle Änderungen (Erstellen, Verschieben, Löschen) müssen sofort (optimistisch) im UI reflektiert und asynchron im Backend gesichert werden.
- Die mobile Ansicht muss die volle Funktionalität der Desktop-Ansicht bieten (insbesondere Drag & Drop).
