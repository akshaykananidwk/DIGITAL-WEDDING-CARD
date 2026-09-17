# The update system

One-click updates from GitHub, with a real rollback. After the repository is entered
once in **Admin → System → Updates**, no file is ever uploaded to the server again.

Everything below is implemented in `app/Services/UpdateService.php`,
`app/Services/GitHubService.php` and `app/Services/ArchiveExtractor.php`, and is
exercised by `tests/Cases/SystemTest.php`.

---

## Setting it up

1. **Admin → System → Updates → Update source.**
2. **Repository** — `owner/repository`. Validated against that shape before it is
   used; a URL, a shell payload or a bare owner name is rejected.
3. **Branch** — `main`, or whichever branch you release from.
4. **Personal access token** — only needed for a private repository. A fine-grained
   token with **Contents: read-only** on that one repository is enough. Nothing else is
   required, and nothing else should be granted.
5. **Save and verify.** The connection is tested before the settings are accepted, so a
   wrong token is reported immediately rather than at the worst moment.

The token is encrypted with AES-256-GCM using the application key, which lives outside
the web root. It is displayed as `ghp_•••••••wxyz`, never sent to the browser in full,
and redacted from logs. **Remove stored token** on the same screen clears it, with an
audit entry; the repository and branch stay as they are, so a repository that has since
become public keeps working.

---

## Checking for an update

**Check for update** calls the GitHub API and reports:

- the version in the remote `version.json` and the version you are running
- the latest commit: short SHA, message, author and date
- how many commits you are behind
- the list of changed files, with their status

Two things can make an update available: a **newer semantic version** in
`version.json`, or **new commits** on the tracked branch when the versions match. The
result is cached for 15 minutes so opening the page repeatedly does not spend API
quota, and administrators are notified once per commit rather than on every page load.

---

## Applying an update

**Dry run** does everything except the deploy — download, extract, validate, syntax
check — so you can confirm the package is sound before anything is written. It is
recorded as `testing`, never as a success, so it cannot be mistaken for a deployment.

**Update now** runs these steps in order:

| # | Step | What happens | On failure |
|---|---|---|---|
| 1 | **Lock** | An exclusive lock file (`O_CREAT\|O_EXCL`) is taken. A second update cannot start, and a stale lock is visible in the UI with a button to clear it. | Refuse, change nothing |
| 2 | **Maintenance mode** | Visitors get the maintenance page; administrators keep access through a bypass token. | Release lock |
| 3 | **Backup** | A full database dump **and** a file archive are written to the backup directory outside the web root. Both paths are recorded on the update row. | Abort before anything is touched |
| 4 | **Download** | The branch tarball is fetched from `codeload.github.com` to `storage/update/`, with the token sent only to GitHub. | Abort, restore nothing (nothing changed yet) |
| 5 | **Extract** | Into `storage/update/staging/`, with the Zip Slip, symlink, entry-count, total-size and compression-ratio guards. | Abort |
| 6 | **Validate** | Required files present; `version.json` readable; `min_php` satisfied; every changed PHP file syntax-checked in-process with `token_get_all()` (never executed). | Abort |
| 7 | **Deploy** | Each file is compared by hash, written to a temporary name and **renamed atomically**. Unchanged files are skipped. Protected paths are never touched. | Roll back |
| 8 | **Migrate** | `Migrator::run()` applies new migrations from the deployed code. | Roll back |
| 9 | **Clear caches** | Application cache, compiled views, OPcache. | Roll back |
| 10 | **Health gate** | The critical subset of the health checks must pass: database, schema, migrations, writable paths, configuration, routes. | Roll back |
| 11 | **Finish** | Version recorded, lock released, maintenance lifted, administrators notified, every step stored. | — |

There is no step that extracts an archive over the public directory. That pattern —
"download ZIP, unzip over the site" — is what this design exists to avoid.

---

## Rollback

**Automatic.** If any step from 7 onwards fails, the same run restores the file
archive and the database dump taken in step 3, clears caches, lifts maintenance mode
and records the attempt as `rolled_back` with the failing step and its message. The
site is back on the previous version before the request finishes.

**Manual.** Every successful update keeps its pre-update backups, so
**Admin → System → Updates → Update history** offers **Roll back to this point** for
each one. It restores files and database, re-runs the critical health checks, and marks
that entry `rolled_back` so the next check no longer treats its commit as deployed.

A rollback never touches a protected path: uploads, storage, secrets and the backup
directory are left exactly as they are, so guest photos and RSVP responses collected
after the update survive a rollback of the code.

---

## What is never overwritten

`UpdateService::protectedPaths()`:

```
storage/            uploads/            .env
config/app.php      ../.invitation-secrets/     storage/config/
.htaccess           web.config          storage/installed.lock
storage/backups/    storage/logs/       storage/update/
uploads/.htaccess
```

If a release needs to change one of these — a new `.htaccess` rule, say — the release
notes have to say so, and the operator applies it by hand. That is the deliberate
trade: a file the operator owns is never silently replaced.

---

## Update history and statuses

| Status | Meaning |
|---|---|
| `checking` | The run has started; nothing has been written |
| `downloading` | Fetching the archive |
| `backing_up` | Taking the pre-update database and file backups |
| `staging` | Extracting and validating |
| `updating` | Deploying files |
| `migrating` | Running migrations |
| `testing` | A dry run, or the post-update health gate |
| `success` | Deployed, migrated and healthy |
| `failed` | Failed before anything was deployed |
| `rolled_back` | Failed after deployment and was restored, or was rolled back by hand |

Each row records the commit, the author, the changed files, the backup paths, the
migration result, the health result, the duration and the full step log. The step log
is what the admin screen shows, so an operator can see exactly where a failure happened
without reading a log file.

---

## Security controls

| Risk | Control |
|---|---|
| Token theft | AES-256-GCM at rest, key outside the web root, masked in the UI, absent from the API, redacted from logs |
| Unauthorised update | `updates.apply` permission, CSRF token, admin-only route, audit log entry |
| Path traversal / Zip Slip | Every archive entry resolved and rejected if it escapes the destination; absolute paths and symlinks refused |
| Zip bomb | Entry count, total size and per-entry compression ratio limits |
| Arbitrary file overwrite | Protected path list, checked on deploy and on rollback |
| Remote code injection | Staging is syntax-checked with `token_get_all()`, not executed; only migrations shipped in `database/migrations/` run; no SQL from the archive is executed |
| SSRF | Outbound requests limited to GitHub hosts; redirects to another host, private address ranges and non-HTTPS URLs refused |
| Concurrent updates | Exclusive lock file, with a visible way to clear a stale one |
| Broken deploy | Health gate, then automatic rollback |
| Silent failure | Every step recorded with its message; administrators notified |

---

## Publishing a release

1. Bump `version` in `version.json` (semantic versioning), and `schema` if you added a
   migration.
2. Put the operator-facing summary in the `notes` field — the admin screen shows it.
3. Commit, push to the tracked branch, and optionally tag a GitHub release.
4. Servers see it at the next **Check for update**, or at the next
   `update-check` cron run, which notifies the administrators once.

A release that changes a protected file, needs a manual step, or requires a newer PHP
should say so in `notes`. `min_php` is enforced by the validator, so a server that is
too old refuses the update instead of breaking on it.

---

## If an update goes wrong

1. **The site is in maintenance mode and the lock is held.** The run crashed (a PHP
   fatal or the process was killed). Open **Admin → System → Updates**, clear the lock,
   and check the last entry in the update history for the step it reached.
2. **The site is broken after a successful update.** Use **Roll back to this point** on
   that entry.
3. **You cannot reach the admin panel.** From a shell:
   ```bash
   php bin/console health          # what is actually wrong
   rm storage/maintenance.json     # lift maintenance mode
   rm storage/update.lock          # clear the lock
   php bin/console migrate         # finish an interrupted migration
   ```
   The backups are in the directory shown on the updates screen; a database dump
   restores with `mysql < dump.sql`, and a file archive unzips over the application
   root.
4. **Health reports a critical check.** Fix that first — the update gate will keep
   refusing while it fails, which is the point.
