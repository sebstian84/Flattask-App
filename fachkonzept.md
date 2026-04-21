# Fachkonzept: Todo Application

## 1. Einleitung und Zielsetzung
Die vorliegende Anwendung ist eine webbasierte, Single-Page Application (SPA) zur Verwaltung von Aufgaben (Todos). Ziel ist es, dem Nutzer ein intuitives, schnelles und hochgradig anpassbares Werkzeug für das persönliche oder berufliche Task-Management zur Verfügung zu stellen. Die Applikation zeichnet sich durch flexible Gruppierungs- und Filterfunktionen, Drag-and-Drop-Unterstützung sowie ein integriertes Tag-System aus.

## 2. Zielgruppe & Anwendungsbereich
Die Anwendung richtet sich in ihrer aktuellen Ausprägung primär an Einzelnutzer (Single-User-Betrieb), die eine datenschutzfreundliche und leichtgewichtige Lösung zur Aufgabenverwaltung suchen. Der Anwendungsbereich erstreckt sich von einfachen Einkaufslisten bis hin zur Organisation komplexer, terminierter Projekte mittels Kalenderwochen- oder Monatsgruppierungen.

## 3. Funktionsübersicht

### 3.1 Todo-Verwaltung (CRUD)
- **Erstellen:** Neue Aufgaben können mit einem Titel (max. 150 Zeichen), einem optionalen Zieldatum, einer Rich-Text-Beschreibung, Tags und einem Status angelegt werden.
- **Lesen & Anzeigen:** Aufgaben werden übersichtlich in einer Liste oder gruppiert dargestellt. Jeder Task hat einen Status (offen oder erledigt).
- **Aktualisieren:** Inline-Bearbeitung von Titel, Zieldatum, Status und Beschreibung. Die Rich-Text-Beschreibung kann per Klick im ausgeklappten Bereich direkt angepasst werden.
- **Löschen & Archivieren:** Aufgaben werden nicht sofort physisch gelöscht, sondern in ein Archiv verschoben. Aus dem Archiv können sie dauerhaft gelöscht oder wiederhergestellt werden.

### 3.2 Strukturierung & Organisation
- **Tag-System:** Todos können mit mehreren Tags versehen werden. Eine Autovervollständigung (Suggested Tags) erleichtert die Zuweisung.
- **Filterung & Suche:** Die Ansicht kann nach einem oder mehreren Tags gefiltert werden (inklusive oder exklusive Logik). Zudem können Aufgaben mit dem Status "erledigt" über einen zentralen Toggle-Button ein- oder ausgeblendet werden. Eine intelligente Freitextsuche scannt Titel und Beschreibung, um bestimmte Todos in Echtzeit zu finden.
- **Gruppierung:** Aufgaben können dynamisch gruppiert werden nach:
  - **Zeitlich:** Täglich, Wöchentlich (Kalenderwochen), Monatlich. Bei aktiver Zeit-Gruppierung existiert eine "Quick-Jump"-Funktion, um direkt zum aktuellen Zeitraum zu springen.
  - **Thematisch:** Nach Tags.
- **Sortierung:** Eine manuelle Sortierung per Drag-and-Drop ist möglich (sofern keine speziellen Filter/Gruppierungen aktiv sind), alternativ kann nach Zieldatum sortiert werden.

### 3.3 Benutzeroberfläche & Interaktion (UI/UX)
- **Responsive Design:** Die Oberfläche passt sich dynamisch an Desktop- und Mobilgeräte an. Für mobile Endgeräte (Smartphones) wird ein platzsparendes Hamburger-Menü (Drawer) verwendet, welches alle Filter, Tags und Einstellungen beinhaltet, um die Übersichtlichkeit zu wahren.
- **Floating Search:** Das Suchfeld ist platzsparend als ausfahrbares Floating-Element implementiert.
- **Drag & Drop:** Aufgaben können per Maus oder Touch-Gesten frei verschoben oder zwischen verschiedenen Gruppierungen (z. B. von einer Kalenderwoche in die nächste) umverteilt werden, wobei sich das Zieldatum automatisch anpasst.
- **Rich-Text Editor:** Für detailreiche Beschreibungen der Aufgaben.

### 3.4 Authentifizierung & Profilverwaltung
- Ein Login-System schützt die Daten vor unbefugtem Zugriff.
- Der Benutzername und das Passwort können über die Profilverwaltung innerhalb der App geändert werden.

### 3.5 Backup, Wiederherstellung & Historie
- Die Anwendung bietet eine integrierte Exportfunktion für Todos, Archiv und Einstellungen.
- Ein Import-Dialog ermöglicht die Wiederherstellung, wobei Duplikate übersprungen werden.
- **Historie (Changelog):** Jede Änderung (Anlage, Bearbeitung, Löschung) an einem Task wird protokolliert. In der Historien-Ansicht können die Änderungen (Alte vs. Neue Werte) detailliert eingesehen werden. Einträge lassen sich gezielt rückgängig machen oder löschen.

## 4. Datenmodell (Logische Struktur)

- **Todo-Objekt:**
  - `id`: Eindeutiger Identifier (Timestamp).
  - `name`: Kurztitel der Aufgabe (max. 150 Zeichen).
  - `description`: Detaillierte Beschreibung (HTML/Rich-Text).
  - `targetDate`: Optionales Fälligkeits- oder Zieldatum (YYYY-MM-DD).
  - `tags`: Array von Strings.
  - `status`: Zustand des Tasks ('offen' oder 'erledigt').
  - `order`: Integer für die manuelle Sortierreihenfolge.
- **Settings-Objekt:**
  - Speichert UI-Zustände (aktive Tags, Gruppierungsart, Sortierung), um diese sitzungsübergreifend beizubehalten.
- **Changelog-Objekt:**
  - Protokolliert Änderungen an Todos (Erstellung, Aktualisierung, Löschung) für eine spätere Nachvollziehbarkeit oder Undo-Funktionen.

## 5. Abnahmekriterien & Qualitätssicherung
- Alle Änderungen (Erstellen, Verschieben, Löschen) müssen sofort (optimistisch) im UI reflektiert und asynchron im Backend gesichert werden.
- Die mobile Ansicht muss die volle Funktionalität der Desktop-Ansicht bieten (insbesondere Drag & Drop).
