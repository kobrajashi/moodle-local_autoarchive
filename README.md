# Moodle Course Auto-Archive Plugin

Lokales Plugin für **Moodle 5.1**, das inaktive Kurse erkennt, als Backup sichert, verbirgt und bei Bedarf in großer Zahl löscht.

## Funktionen

- **Automatische Archivierung** (geplante Aufgabe): sichert Kurse ohne Zugriff seit X Tagen und verbirgt sie.
- **Inaktive Kurse löschen**: Vorschau, Auswahl, Bestätigung und Löschen im Hintergrund (Ad-hoc-Task), optional mit Backup.
  Ein Kurs wird nur gelöscht, wenn sein Backup geschrieben und per SHA1 geprüft wurde.
- **Archivverwaltung**: Suchen, Filtern, Wiederherstellen, Herunterladen, Löschen; Mehrfachauswahl mit
  „Alle auswählen“ für Sammelaktionen; Massenlöschung nach Archivalter.
- **Ausgeschlossene Kategorien** (inkl. Unterkategorien), z. B. für Kursvorlagen.
- **Aufbewahrungszeit** für Backups gelöschter Kurse (eigene, standardmäßig deaktivierte Aufgabe).
- **Benachrichtigungen**: E-Mail nach automatischer Archivierung, Moodle-Mitteilung nach Hintergrund-Löschungen.
- **Datenschutz**: Privacy-Provider für die gespeicherten Trainer/innen-Namen.

## Installation

Ab Moodle 5.1 liegen Plugins im Verzeichnis `public`:

```bash
cp -r local_autoarchive /pfad/zu/moodle/public/local/autoarchive
php admin/cli/upgrade.php
```

Oder als Administrator/in über *Website-Administration → Mitteilungen*.

Bei einem Update von Version 1.x werden die Daten automatisch übernommen. Archiveinträge, die durch einen Fehler
der alten Version aus der Liste verschwunden waren (Massenlöschung mit „Backups behalten“), sind danach wieder sichtbar.

## Wie Inaktivität ermittelt wird

Letzter Zugriff ist der neueste Wert aus

1. `user_lastaccess` (letzter Kursaufruf je Nutzer/in, unabhängig von der Log-Aufbewahrung) und
2. dem Standard-Log, ohne Einträge aus Cron/CLI, aus Wiederherstellungen und ohne Backup-Ereignisse.

Gibt es keinen Zugriff, zählt das Erstellungsdatum des Kurses. Werden alle Nutzer/innen aus einem Kurs abgemeldet,
entfernt Moodle deren `user_lastaccess`-Einträge. Ist die Log-Aufbewahrung kürzer als der Inaktivitätszeitraum,
zeigen Einstellungsseite und Aufgabe deshalb eine Warnung.

## Konfiguration

*Website-Administration → Plugins → Lokale Plugins → Kurs-Auto-Archivierung → Einstellungen*

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Inaktivitätszeitraum | 365 | Tage ohne Zugriff bis zur automatischen Archivierung |
| Ausgeschlossene Kategorien | – | werden nie archiviert oder zum Löschen angeboten |
| Kurse pro Durchlauf | 50 | Obergrenze je Lauf der Archivierungsaufgabe |
| Archivierte Kurse verbergen | an | Kurs nach dem Backup verbergen |
| E-Mail-Benachrichtigungen / Adressen | an / leer | Empfänger/innen der Archivierungsmeldung |
| Aufbewahrungszeit für Backups | 730 | nur für Kurse, die nicht mehr existieren; 0 = unbegrenzt |

### Geplante Aufgaben

Beide Aufgaben sind **nach der Installation deaktiviert** und müssen unter
*Website-Administration → Server → Geplante Aufgaben* bewusst aktiviert werden:

- **Inaktive Kurse archivieren**: sonntags 03:00
- **Abgelaufene Backups löschen**: sonntags 04:00

Manuell testen:

```bash
php admin/cli/scheduled_task.php --execute='\local_autoarchive\task\archive_inactive_courses'
```

Löschaufträge aus der Oberfläche laufen als Ad-hoc-Tasks über den normalen Cron.

## Verwendung

- **Archivierte Kurse verwalten**: `/local/autoarchive/manage.php`
- **Inaktive Kurse löschen**: `/local/autoarchive/cleanup.php`

### Mehrere Archive auf einmal bearbeiten

In der Archivliste hat jede Zeile ein Auswahlkästchen, das Kästchen im Tabellenkopf wählt alle Einträge der
aktuellen Seite an oder ab. Mit „Einträge pro Seite“ (bis 500) passen mehr Einträge auf eine Seite.
Unter der Tabelle stehen zwei Aktionen zur Wahl:

- **Endgültig löschen (Kurs, Backup und Eintrag)**
- **Nur Kurse löschen, Backups behalten**: die Einträge bleiben als „Nur Backup“ erhalten

Vor dem Ausführen zeigt eine Bestätigungsseite alle ausgewählten Kurse. Reaktivierte Kurse werden übersprungen.
Das Löschen läuft im Hintergrund (Ad-hoc-Task), das Ergebnis kommt als Mitteilung.

### Status in der Archivliste

| Status | Bedeutung |
|---|---|
| Kurs verborgen | Kurs existiert, ist verborgen, Backup vorhanden |
| Reaktiviert | Kurs wurde nach der Archivierung wieder sichtbar geschaltet; wird nicht gelöscht |
| Kurs sichtbar (nicht verborgen) | archiviert ohne Verbergen |
| Nur Backup | Kurs gelöscht, Backup kann heruntergeladen oder wiederhergestellt werden |

### Wiederherstellen

- Existiert der Kurs noch, erhält er seine ursprüngliche Sichtbarkeit zurück.
- Sonst wird er aus dem Backup als neuer Kurs angelegt (gleicher Kurzname, falls frei, sonst `_restored`).

Nach erfolgreicher Wiederherstellung werden Archiveintrag und Backup entfernt. Schlägt sie fehl, bleibt beides erhalten.
Ein Archiv kann nicht gleichzeitig zweimal wiederhergestellt werden (Sperre zwischen Browser und Kommandozeile).

### Wiederherstellen über die Kommandozeile

Große Backups (mehrere GB) scheitern im Browser am Zeitlimit des Webservers. Dafür gibt es ein CLI-Skript mit
derselben Logik wie der Button. Aufruf aus dem Moodle-Hauptverzeichnis als Webserver-Benutzer:

```bash
# Archive auflisten (ID, Status, Größe, Archivierungsdatum, Kursname)
sudo -u www-data php public/local/autoarchive/cli/restore.php --list --search=informatik

# Archiv mit ID 42 wiederherstellen
sudo -u www-data php public/local/autoarchive/cli/restore.php --archiveid=42
```

Vor einer Wiederherstellung aus der Datei prüft das Skript grob den freien Speicher: etwa die doppelte
Backup-Größe im Temp-Verzeichnis und die einfache im Dateispeicher. Mit `--force` lässt sich die Prüfung übergehen.
Für sehr lange Läufe empfiehlt sich `screen`, `tmux` oder `nohup`, damit ein Verbindungsabbruch der SSH-Sitzung
den Vorgang nicht beendet.

## Berechtigungen

| Capability | Risiko | Zweck |
|---|---|---|
| `local/autoarchive:manage` | Personal | Archivliste sehen |
| `local/autoarchive:restore` | Personal, Config | Kurse wiederherstellen |
| `local/autoarchive:delete` | Datenverlust | Kurse und Archive löschen |
| `local/autoarchive:download` | Personal | Backups (mit Nutzerdaten) herunterladen |

Standardmäßig hat nur die Rolle **Manager** diese Rechte.

## Backup-Speicherort

`$CFG->dataroot/local_autoarchive/backups/` (außerhalb des Webroots). Backups enthalten Nutzerdaten, Rollen,
Aktivitäten und Dateien, aber keine Logs. Einstellungen, die die Website-Konfiguration sperrt, werden respektiert.

## Dateistruktur

```
local_autoarchive/
├── classes/
│   ├── archive_manager.php                 # Kern-Logik
│   ├── privacy/provider.php                # Datenschutz-API
│   ├── table/archived_courses_table.php    # Archivliste
│   └── task/
│       ├── archive_inactive_courses.php    # geplant: archivieren
│       ├── purge_expired_backups.php       # geplant: Aufbewahrung
│       ├── delete_courses.php              # ad hoc: inaktive Kurse löschen
│       └── delete_archives.php             # ad hoc: Massenlöschung
├── db/ (access, install.xml, messages, tasks, upgrade)
├── lang/ (de, en)
├── cli/restore.php                     # Wiederherstellen über die Kommandozeile
├── bulk_action.php, bulk_delete.php, cleanup.php, delete.php, download.php, manage.php, restore.php
├── settings.php
└── version.php
```

## Changelog

### Version 2.0.0 (2026-10-26)
- Anpassung an Moodle 5.1 (Namespaces, Bootstrap 5, Admin-Navigation, `sql_table`)
- Kurse werden nur gelöscht, wenn ihr Backup geprüft vorhanden ist
- Löschvorgänge laufen im Hintergrund mit Ergebnis-Mitteilung
- Inaktivität über `user_lastaccess` + Log (ohne Cron- und Backup-Einträge)
- „Backups behalten“ bei der Massenlöschung lässt Backups in der Liste
- Ausgeschlossene Kategorien, Aufbewahrungszeit-Aufgabe, Kurse pro Durchlauf
- Geplante Aufgaben standardmäßig deaktiviert
- E-Mail-Benachrichtigungen repariert
- Privacy-Provider, Risk-Bitmasks, Trainer/innen ohne E-Mail-Adressen gespeichert
- Sortierung und Filter der Archivliste repariert
- Mehrfachauswahl und Sammelaktionen in der Archivliste, Einträge pro Seite wählbar
- CLI-Skript zum Auflisten und Wiederherstellen großer Archive, Sperre gegen doppelte Wiederherstellung
- Einstellung „Kompressionsstufe“ entfernt (hatte keine Wirkung)

### Version 1.0.0 (2026-01-27)
- Erste Veröffentlichung

## Lizenz

GNU GPL v3 oder später – siehe [LICENSE](LICENSE).
