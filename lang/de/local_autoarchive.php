<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * German language strings for local_autoarchive.
 *
 * @package    local_autoarchive
 * @category   string
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Aktionen';
$string['alldates'] = 'Alle Zeiträume';
$string['archivedate'] = 'Archiviert am';
$string['autoarchive:delete'] = 'Archivierte und inaktive Kurse endgültig löschen';
$string['autoarchive:download'] = 'Kurs-Backups herunterladen';
$string['autoarchive:manage'] = 'Archivierte Kurse verwalten';
$string['autoarchive:restore'] = 'Archivierte Kurse wiederherstellen';
$string['backupnotfound'] = 'Backup-Datei fehlt';
$string['bulkaction_apply'] = 'Auf Auswahl anwenden';
$string['bulkaction_confirm_deletearchives'] = 'Die folgenden {$a} Archiv(e) werden endgültig gelöscht: der Kurs (falls noch vorhanden), die Backup-Datei und der Archiveintrag. Das Löschen läuft im Hintergrund.';
$string['bulkaction_confirm_deletecourses'] = 'Für die folgenden {$a} Archiv(e) werden die Kurse gelöscht. Die Backups bleiben in der Liste verfügbar. Das Löschen läuft im Hintergrund.';
$string['bulkaction_deletearchives'] = 'Endgültig löschen (Kurs, Backup und Eintrag)';
$string['bulkaction_deletecourses'] = 'Nur Kurse löschen, Backups behalten';
$string['bulkaction_noselection'] = 'Keine Archive ausgewählt.';
$string['bulkaction_note_backuponly'] = 'nur Backup';
$string['bulkaction_note_course'] = 'Kurs vorhanden';
$string['bulkaction_note_reactivated'] = 'reaktiviert, wird übersprungen';
$string['bulkaction_reactivatedinfo'] = '{$a} Kurs(e) wurden nach der Archivierung wieder sichtbar geschaltet und werden nicht gelöscht.';
$string['bulkaction_selected'] = 'Mit ausgewählten Archiven';
$string['bulkdelete'] = 'Archive löschen';
$string['bulkdelete_button'] = 'Löschen';
$string['bulkdelete_confirm'] = '{$a->count} Archiv(e) wurden vor mehr als {$a->days} Tagen angelegt.';
$string['bulkdelete_days'] = 'Tage seit der Archivierung';
$string['bulkdelete_days_help'] = 'Archive, die vor mehr als dieser Anzahl an Tagen angelegt wurden, werden bearbeitet. Ihre Kurse werden gelöscht, sofern sie noch existieren.';
$string['bulkdelete_error'] = 'Bitte eine Anzahl an Tagen zwischen 1 und 7300 angeben.';
$string['bulkdelete_heading'] = 'Archivierte Kurse löschen';
$string['bulkdelete_includebackups'] = 'Auch Backup-Dateien löschen';
$string['bulkdelete_includebackups_help'] = 'Wenn aktiviert, werden auch Backup-Dateien und Archiveinträge entfernt. Andernfalls bleiben die Backups in dieser Liste verfügbar (Status „Nur Backup“).';
$string['bulkdelete_keepbackups'] = 'Die Kurse werden gelöscht. Die Backups bleiben für Download und Wiederherstellung verfügbar.';
$string['bulkdelete_nomatches'] = 'Keine passenden Archive gefunden.';
$string['bulkdelete_queued'] = 'Das Löschen von {$a} Archiv(en) wurde eingeplant. Sie erhalten eine Benachrichtigung mit dem Ergebnis.';
$string['bulkdelete_result'] = '{$a->count} von {$a->total} Archiv(en) bearbeitet.';
$string['bulkdelete_result_subject'] = 'Kursarchiv: Massenlöschung abgeschlossen';
$string['bulkdelete_withbackups'] = 'Kurse, Backup-Dateien und Archiveinträge werden gelöscht.';
$string['category'] = 'Kategorie';
$string['cleanup'] = 'Inaktive Kurse löschen';
$string['cleanup_alreadyarchived'] = 'Bereits archiviert, vorhandenes Backup wird verwendet';
$string['cleanup_confirm'] = '{$a} Kurs(e) löschen';
$string['cleanup_confirm_message'] = 'Wollen Sie wirklich {$a} Kurs(e) löschen? Das Löschen läuft im Hintergrund.';
$string['cleanup_continue'] = 'Weiter zur Bestätigung';
$string['cleanup_createbackups'] = 'Vor dem Löschen Backups erstellen';
$string['cleanup_createbackups_help'] = 'Wenn aktiviert, wird jeder Kurs zuerst gesichert und erst gelöscht, wenn sein Backup geschrieben und geprüft ist. Kurse, deren Backup fehlschlägt, bleiben erhalten.';
$string['cleanup_days'] = 'Tage ohne Zugriff';
$string['cleanup_days_help'] = 'Kurse ohne Zugriff seit dieser Anzahl von Tagen werden aufgelistet.';
$string['cleanup_description'] = 'Alle Kurse löschen, die seit einer bestimmten Anzahl von Tagen inaktiv sind. Vor dem Löschen wird eine Vorschau angezeigt. Ausgeschlossene Kategorien werden nicht aufgeführt.';
$string['cleanup_heading'] = 'Inaktive Kurse löschen';
$string['cleanup_nopreview'] = 'Keine inaktiven Kurse für den angegebenen Zeitraum gefunden.';
$string['cleanup_noselection'] = 'Keine Kurse ausgewählt.';
$string['cleanup_preview'] = 'Vorschau anzeigen';
$string['cleanup_preview_description'] = '{$a} inaktive(r) Kurs(e) gefunden:';
$string['cleanup_queued'] = 'Das Löschen von {$a} Kurs(en) wurde eingeplant. Sie erhalten eine Benachrichtigung mit dem Ergebnis.';
$string['cleanup_result'] = '{$a->count} von {$a->total} Kurs(en) gelöscht. Neue Backups: {$a->backedup}, vorhandene Backups verwendet: {$a->reused}.';
$string['cleanup_result_subject'] = 'Kursarchiv: Löschen inaktiver Kurse abgeschlossen';
$string['cleanup_warning'] = 'Achtung: Die ausgewählten Kurse werden endgültig gelöscht.';
$string['cleanup_withbackups'] = 'Jeder Kurs wird zuerst gesichert. Kurse, deren Backup fehlschlägt, werden nicht gelöscht.';
$string['cleanup_withoutbackups'] = 'Es werden keine Backups erstellt!';
$string['confirmdelete'] = 'Diese Aktion kann nicht rückgängig gemacht werden!';
$string['confirmdelete_backup'] = 'Das Backup von „{$a}“ endgültig löschen?';
$string['confirmdelete_course'] = 'Den Kurs „{$a}“ und sein Backup endgültig löschen?';
$string['confirmrestore_backup'] = '„{$a}“ aus dem Backup als neuen Kurs wiederherstellen? Nach erfolgreicher Wiederherstellung werden Archiveintrag und Backup-Datei entfernt.';
$string['confirmrestore_existing'] = '„{$a}“ mit der ursprünglichen Sichtbarkeit wieder bereitstellen? Der Kurs existiert noch, daher werden Archiveintrag und Backup-Datei anschließend entfernt.';
$string['coursedeleted'] = 'Das Archiv von „{$a}“ wurde endgültig gelöscht.';
$string['coursename'] = 'Kursname';
$string['courserestored'] = 'Der Kurs „{$a}“ wurde wiederhergestellt.';
$string['delete'] = 'Endgültig löschen';
$string['download'] = 'Herunterladen';
$string['enableautohide'] = 'Archivierte Kurse verbergen';
$string['enableautohide_desc'] = 'Kurse verbergen, nachdem die geplante Aufgabe sie gesichert hat.';
$string['enablenotifications'] = 'E-Mail-Benachrichtigungen aktivieren';
$string['enablenotifications_desc'] = 'Eine E-Mail an die unten genannten Adressen senden, wenn die geplante Aufgabe Kurse archiviert hat.';
$string['error_archive'] = 'Archiv {$a}';
$string['error_backupdelete'] = 'Die Backup-Datei {$a} konnte nicht gelöscht werden.';
$string['error_backupintegrity'] = 'Das Backup von „{$a}“ fehlt oder ist beschädigt (Integritätsprüfung fehlgeschlagen).';
$string['error_courseactive'] = 'Der Kurs wurde kürzlich aufgerufen und wurde übersprungen.';
$string['error_coursenotfound'] = 'Kurs {$a} nicht gefunden.';
$string['error_createbackup'] = 'Fehler beim Erstellen des Backups für Kurs {$a}';
$string['error_delete'] = 'Fehler beim Löschen des Kurses „{$a}“';
$string['error_hidecourse'] = 'Fehler beim Verbergen von Kurs {$a}';
$string['error_invalidid'] = 'Ungültige Archiv-ID';
$string['error_reactivated'] = 'Der Kurs „{$a}“ wurde seit der Archivierung wieder sichtbar geschaltet und wird deshalb nicht gelöscht. Stellen Sie das Archiv wieder her, um es aufzulösen.';
$string['error_restore'] = 'Fehler beim Wiederherstellen des Kurses „{$a}“';
$string['error_restoreinprogress'] = 'Dieses Archiv wird bereits wiederhergestellt (im Browser oder auf der Kommandozeile). Bitte warten Sie, bis diese Wiederherstellung abgeschlossen ist.';
$string['errors'] = 'Fehler';
$string['excludedcategories'] = 'Ausgeschlossene Kategorien';
$string['excludedcategories_desc'] = 'Kurse in diesen Kategorien (inklusive aller Unterkategorien) werden nie archiviert oder zum Löschen aufgeführt, z. B. Kursvorlagen.';
$string['filesize'] = 'Dateigröße';
$string['filterdate'] = 'Archiviert';
$string['filterteacher'] = 'Nach Trainer/in filtern';
$string['inactivityperiod'] = 'Inaktivitätszeitraum (Tage)';
$string['inactivityperiod_desc'] = 'Anzahl der Tage ohne Zugriff, bevor die geplante Aufgabe einen Kurs archiviert. Der Zugriff wird aus dem letzten Kurszugriff der Nutzer/innen und dem Standard-Log ermittelt (Cron- und Backup-Einträge werden ignoriert).';
$string['last3months'] = 'Letzte 3 Monate';
$string['lastaccess'] = 'Letzter Zugriff';
$string['lastmonth'] = 'Letzter Monat';
$string['lastweek'] = 'Letzte Woche';
$string['lastyear'] = 'Letztes Jahr';
$string['loglifetime_warning'] = 'Das Standard-Log bewahrt Einträge nur {$a->loglifetime} Tage auf, der Inaktivitätszeitraum beträgt aber {$a->inactivityperiod} Tage. Der Zugriff ist dann nur noch über den letzten Kurszugriff der Nutzer/innen bekannt, der beim Abmelden aus dem Kurs entfernt wird. Prüfen Sie die Ergebnisse sorgfältig.';
$string['managearchivedcourses'] = 'Archivierte Kurse verwalten';
$string['maxbackuptime'] = 'Aufbewahrungszeit für Backups (Tage)';
$string['maxbackuptime_desc'] = 'Backups von Kursen, die nicht mehr existieren, werden von der Aufgabe „Abgelaufene Backups löschen“ entfernt, sobald sie älter als diese Anzahl an Tagen sind. 0 bewahrt sie unbegrenzt auf. Die Aufgabe ist standardmäßig deaktiviert.';
$string['maxperrun'] = 'Kurse pro Durchlauf';
$string['maxperrun_desc'] = 'Maximale Anzahl an Kursen, die die geplante Aufgabe pro Durchlauf archiviert.';
$string['messageprovider:taskcompleted'] = 'Kursarchiv: Ergebnis von Löschvorgängen im Hintergrund';
$string['noarchives'] = 'Keine archivierten Kurse gefunden.';
$string['notification_body'] = 'Die folgenden Kurse wurden automatisch archiviert:

{$a->courses}
Sie können die archivierten Kurse hier verwalten: {$a->url}';
$string['notification_subject'] = 'Kurse wurden automatisch archiviert';
$string['notificationemails'] = 'E-Mail-Adressen für Benachrichtigungen';
$string['notificationemails_desc'] = 'E-Mail-Adressen, die Benachrichtigungen erhalten, getrennt durch Kommas oder Zeilenumbrüche.';
$string['perpage'] = 'Einträge pro Seite';
$string['pluginname'] = 'Kurs-Auto-Archivierung';
$string['privacy:metadata:core_backup'] = 'Die archivierten Kurs-Backups werden mit dem Moodle-Backup-System erstellt und enthalten die Nutzerdaten, die in einem Kurs-Backup enthalten sind.';
$string['privacy:metadata:local_autoarchive_teachers'] = 'Trainer/innen mit Bearbeitungsrecht eines Kurses zum Zeitpunkt der Archivierung.';
$string['privacy:metadata:local_autoarchive_teachers:archiveid'] = 'Der Archiveintrag.';
$string['privacy:metadata:local_autoarchive_teachers:firstname'] = 'Vorname der Trainerin/des Trainers.';
$string['privacy:metadata:local_autoarchive_teachers:lastname'] = 'Nachname der Trainerin/des Trainers.';
$string['privacy:metadata:local_autoarchive_teachers:userid'] = 'Die Nutzer-ID der Trainerin/des Trainers.';
$string['restore'] = 'Wiederherstellen';
$string['searchandfilter'] = 'Suchen und filtern';
$string['searchcoursename'] = 'Nach Kursname suchen';
$string['selectarchive'] = '{$a} auswählen';
$string['settings'] = 'Einstellungen';
$string['status'] = 'Status';
$string['status_backup_only'] = 'Nur Backup';
$string['status_course_hidden'] = 'Kurs verborgen';
$string['status_course_visible'] = 'Kurs sichtbar (nicht verborgen)';
$string['status_reactivated'] = 'Reaktiviert';
$string['task_archive_inactive_courses'] = 'Inaktive Kurse archivieren';
$string['task_delete_archives'] = 'Archive löschen (Hintergrund)';
$string['task_delete_courses'] = 'Inaktive Kurse löschen (Hintergrund)';
$string['task_purge_expired_backups'] = 'Abgelaufene Backups löschen';
$string['tasks_disabled_info'] = 'Die geplanten Aufgaben „Inaktive Kurse archivieren“ und „Abgelaufene Backups löschen“ sind nach der Installation deaktiviert. Aktivieren Sie sie unter <a href="{$a}">Geplante Aufgaben</a>, sobald die Einstellungen unten geprüft sind.';
$string['teachers'] = 'Trainer/innen';
