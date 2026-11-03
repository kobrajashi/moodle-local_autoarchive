# Course Auto-Archive (local_autoarchive)

Local plugin that detects inactive courses, backs them up, hides them and, if required, deletes them in bulk.

## Requirements

- **Moodle 5.1 or later** (`$plugin->requires = 2025100600`). The plugin uses Moodle 5.1 APIs and does not
  install on earlier versions.

## Features

- **Automatic archiving** (scheduled task): backs up courses that have not been accessed for X days and hides them.
- **Delete inactive courses**: preview, selection, confirmation and deletion in the background (ad hoc task),
  optionally with a backup. A course is only deleted once its backup has been written and verified by SHA1.
- **Archive management**: search, filter, restore, download, delete; multi-select with "select all" for bulk
  actions; bulk deletion by archive age.
- **Excluded categories** (including subcategories), e.g. for course templates.
- **Retention time** for backups of deleted courses (separate task, disabled by default).
- **Notifications**: email after automatic archiving, Moodle notification after background deletions.
- **Privacy**: privacy provider for the stored teacher names.

## Installation

From Moodle 5.1, plugins live in the `public` directory:

```bash
cp -r local_autoarchive /path/to/moodle/public/local/autoarchive
php admin/cli/upgrade.php
```

Or as an administrator via *Site administration → Notifications*.

When upgrading from version 1.x, existing data is migrated automatically. Archive entries that had disappeared
from the list because of a bug in the old version (bulk deletion with "keep backups") become visible again.

## How inactivity is determined

The last access is the most recent value of

1. `user_lastaccess` (last course access per user, independent of log retention) and
2. the standard log, excluding entries from cron/CLI, from restores and from backup events.

If there is no access at all, the course creation date counts. When all users are unenrolled from a course,
Moodle removes their `user_lastaccess` records. If log retention is shorter than the inactivity period, the
settings page and the task therefore show a warning.

## Configuration

*Site administration → Plugins → Local plugins → Course auto-archive → Settings*

| Setting | Default | Meaning |
|---|---|---|
| Inactivity period (days) | 365 | Days without access before automatic archiving |
| Excluded categories | – | Never archived or offered for deletion |
| Courses per run | 50 | Upper limit per run of the archiving task |
| Hide archived courses | on | Hide the course after the backup |
| Enable email notifications / Notification email addresses | on / empty | Recipients of the archiving report |
| Backup retention time (days) | 730 | Only for courses that no longer exist; 0 = unlimited |

### Scheduled tasks

Both tasks are **disabled after installation** and have to be enabled deliberately under
*Site administration → Server → Scheduled tasks*:

- **Archive inactive courses**: Sundays 03:00
- **Delete expired backups**: Sundays 04:00

Manual test run:

```bash
php admin/cli/scheduled_task.php --execute='\local_autoarchive\task\archive_inactive_courses'
```

Deletions started from the user interface run as ad hoc tasks via the regular cron.

## Usage

- **Manage archived courses**: `/local/autoarchive/manage.php`
- **Delete inactive courses**: `/local/autoarchive/cleanup.php`

### Processing several archives at once

Each row of the archive list has a checkbox; the checkbox in the table header selects or deselects all entries on
the current page. "Entries per page" (up to 500) fits more entries on one page. Two actions are available below
the table:

- **Delete permanently (course, backup and entry)**
- **Delete courses only, keep backups**: the entries remain as "Backup only"

Before anything runs, a confirmation page lists all selected courses. Reactivated courses are skipped. Deletion
runs in the background (ad hoc task); the result arrives as a notification.

### Status in the archive list

| Status | Meaning |
|---|---|
| Course hidden | Course exists, is hidden, backup available |
| Reactivated | Course was made visible again after archiving; it will not be deleted |
| Course visible (not hidden) | Archived without hiding |
| Backup only | Course deleted; the backup can be downloaded or restored |

### Restoring

- If the course still exists, it gets its original visibility back.
- Otherwise it is created from the backup as a new course (same short name if available, otherwise `_restored`).

After a successful restore, the archive entry and the backup are removed. If the restore fails, both are kept.
An archive cannot be restored twice at the same time (lock shared between browser and command line).

### Restoring from the command line

Large backups (several GB) hit the web server's time limit in the browser. For those there is a CLI script with
the same logic as the button. Run it from the Moodle root directory as the web server user:

```bash
# List archives (ID, status, size, archive date, course name)
sudo -u www-data php public/local/autoarchive/cli/restore.php --list --search=informatik

# Restore the archive with ID 42
sudo -u www-data php public/local/autoarchive/cli/restore.php --archiveid=42
```

Before restoring from the file, the script roughly checks free disk space: about twice the backup size in the
temp directory and once in the file storage. `--force` skips this check. For very long runs, use `screen`, `tmux`
or `nohup` so that a dropped SSH connection does not stop the process.

## Capabilities

| Capability | Risk | Purpose |
|---|---|---|
| `local/autoarchive:manage` | Personal | View the archive list |
| `local/autoarchive:restore` | Personal, config | Restore courses |
| `local/autoarchive:delete` | Data loss | Delete courses and archives |
| `local/autoarchive:download` | Personal | Download backups (including user data) |

By default only the **Manager** role has these capabilities.

## Backup location

`$CFG->dataroot/local_autoarchive/backups/` (outside the web root). Backups contain user data, roles, activities
and files, but no logs. Settings locked by the site configuration are respected.

## File structure

```
local_autoarchive/
├── classes/
│   ├── archive_manager.php                 # Core logic
│   ├── privacy/provider.php                # Privacy API
│   ├── table/archived_courses_table.php    # Archive list
│   └── task/
│       ├── archive_inactive_courses.php    # scheduled: archive
│       ├── purge_expired_backups.php       # scheduled: retention
│       ├── delete_courses.php              # ad hoc: delete inactive courses
│       └── delete_archives.php             # ad hoc: bulk deletion
├── db/ (access, install.xml, messages, tasks, upgrade)
├── lang/ (de, en)
├── cli/restore.php                     # Restore from the command line
├── bulk_action.php, bulk_delete.php, cleanup.php, delete.php, download.php, manage.php, restore.php
├── settings.php
└── version.php
```

## Changelog

### Version 2.0.1 (2026-11-03)
- Coding style aligned with the Moodle coding standard (moodle-cs)
- German language pack removed from the plugin; translations are provided via AMOS
- Plugin name in sentence case
- GitHub Actions workflow with moodle-plugin-ci (PostgreSQL and MariaDB)

### Version 2.0.0 (2026-10-26)
- Adapted to Moodle 5.1 (namespaces, Bootstrap 5, admin navigation, `sql_table`)
- Courses are only deleted once their backup has been verified
- Deletions run in the background with a result notification
- Inactivity from `user_lastaccess` + log (without cron and backup entries)
- "Keep backups" in bulk deletion keeps the backups in the list
- Excluded categories, retention task, courses per run
- Scheduled tasks disabled by default
- Email notifications fixed
- Privacy provider, risk bitmasks, teachers stored without email addresses
- Sorting and filtering of the archive list fixed
- Multi-select and bulk actions in the archive list, selectable entries per page
- CLI script to list and restore large archives, lock against concurrent restores
- "Compression level" setting removed (it had no effect)

### Version 1.0.0 (2026-01-27)
- First release

## License

GNU GPL v3 or later – see [LICENSE](LICENSE).
