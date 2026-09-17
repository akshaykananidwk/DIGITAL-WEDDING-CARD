# Testing report

Everything below was run against a real installation on the reference host, not
inferred from reading the code. Where a number appears, it was measured.

**Reference host:** PHP 8.4.19 (CLI + built-in server), MariaDB 10.11.14, Linux,
one shared CPU. **Application version:** 1.0.0, schema 2.

Re-run it yourself:

```bash
php tests/run.php            # the automated suite
php tests/run.php --json     # machine readable
php bin/console health       # the 23 runtime checks
```

---

## 1. Automated suite

```
■ Security                                    44 checks    939 ms
■ Authentication and authorisation            25 checks  1 065 ms
■ Templates and the engine                    79 checks  2 810 ms
■ Invitations, slugs and RSVP                 53 checks    313 ms
■ PDF, QR, calendar and sharing               53 checks    170 ms
■ Uploads and the media library               29 checks    115 ms
■ AI generator and recommender                38 checks     13 ms
■ System, installer, backups and updates     129 checks  4 616 ms
──────────────────────────────────────────────────────────────────
All 450 checks passed in 10.0 s across 8 cases
```

The runner has no dependencies — no Composer, no PHPUnit, no Node — so it runs on the
same plain PHP hosting the application targets. Cases that write data clean up after
themselves; the suite leaves the database as it found it.

---

## 2. Installation

`php bin/console install` and the three-screen web installer were both run from an
empty database.

| Step | Result |
|---|---|
| Requirements | 23 checks passed; 2 advisory warnings (upload size 2 MB, no certificate on localhost) |
| Database connection | MariaDB 10.11.14, database created by the installer |
| Configuration written | `/home/user/.invitation-secrets/app.php`, mode 0640, **outside the web root** |
| Migrations | 7 files, 39 tables |
| Roles and permissions | 51 permissions, 4 roles, 52 grants, 2 plans |
| Settings | 50 settings, 12 feature flags |
| Fonts | 8 registered (5 embeddable in PDFs, including Gujarati and Devanagari) |
| Taxonomy | 5 categories, 68 subcategories |
| Pages | 4 (about, privacy, terms, help) |
| Administrator | created, email verified, super-admin |
| Templates | 51 curated |
| Demo data | 1 invitation, 3 RSVP responses, 14 days of analytics |
| Lock | `storage/installed.lock` written |
| Health check | warning (email sender, first backup and cron not yet configured — expected on a fresh install) |

Re-running the installer returns **"Application already installed."** and changes
nothing. `GET /install` after installation returns **403** with the same message.

---

## 3. HTTP surface

95 requests across every public, signed-in and admin route, checked for the expected
status:

| Group | Requests | Result |
|---|---|---|
| Public pages, SEO, PWA, exports | 24 | all as expected (200, plus 301 for `/i/CODE` and 302 for language switching) |
| Guest hitting protected routes | 9 | 302 to login, none served |
| Guest hitting `/admin` | 1 | 302 |
| Unknown path | 1 | 404 |
| `/install` after install | 1 | 403 |
| Signed-in user area (dashboard, builder steps 3–8, RSVP, analytics, exports) | 19 | 200 |
| Admin panel (41 screens: users, roles, taxonomy, templates, fields, generator, invitations, media, fonts, pages, analytics, audit, all 11 settings groups, flags, AI, AI log, system, health, logs, cron, backups, updates, update history) | 41 | 200 |
| **Total** | **95** | **95 as expected, 0 unexpected, 0 errors logged** |

Static assets, the manifest and the service worker were fetched and returned with the
right content types (`text/css`, `application/javascript`, `font/ttf`,
`application/manifest+json`, `image/png`).

Application internals stay unreachable: `app/*`, `database/*`, `storage/*`, `tests/*`,
`bin/*`, dotfiles, `version.json`, `README.md` and a traversal to the secret file all
return 404 with no content leaked.

---

## 4. Functional verification

Each of these was performed end to end and the result checked in the database or the
produced file, not just by a 200 response.

| Area | Verified |
|---|---|
| Registration | Account created, password stored hashed, redirected into the builder |
| Login / logout | Session established, cookie flags correct, logout clears it |
| Login throttling | 20 failures allowed, the 21st returns **429** |
| Template browsing | Gallery, filters (category, language, colour, type, tag), search, detail, full-screen preview |
| Invitation creation | Created from a template as a draft with a unique slug and short code, default sections seeded |
| Content saving | Values persisted per field; unknown keys ignored; markup and over-long values rejected |
| Live preview | Unsaved values POSTed to the same server-side renderer the public page uses |
| Publishing | Refused while required fields are empty, with the missing fields named; succeeds once filled; `published_at` set |
| Slug change | `/invite/suite-renamed-card` served immediately; a traversal attempt sanitised to a safe slug; reserved and too-short slugs refused |
| Short link | `/i/PWZLQU` → **301** to the full URL, QR scan counted |
| RSVP | Guest submission stored, summary counts and guest total correct, a repeat from the same visitor updates rather than duplicates, CSV export contains the guest and no IP address |
| Share tracking | `POST /invite/{slug}/share` recorded per channel; counters incremented |
| Analytics | Views, unique views, shares and downloads recorded; 30-day series; device, browser, referrer and channel breakdowns; CSV export |
| PDF | A4 and mobile generated; `qpdf --check` reports a valid 1-page PDF; `pdftotext` extracts the Gujarati text (`॥ શુભ લગ્ન ॥`, `સંગ`, `પુત્ર`) and the Latin names |
| QR | PNG and SVG; `zbarimg` decodes the PNG to `http://localhost:8080/i/PWZLQU`; print size larger than screen size |
| Calendar | `.ics` valid, `Asia/Kolkata` VTIMEZONE, CRLF line endings, no line over 75 octets |
| Duplicate | New row, own slug and short code, content copied, status draft |
| Delete / purge | Soft delete hides it from the owner's list and keeps the row; purge removes the row and its content |
| Account deletion | Invitations purged, avatar removed, email released, audit entry kept |
| Data export | `GET /profile/export` returns the account, invitations and responses as JSON |
| Admin CRUD | Users, roles and permissions, categories and subcategories, templates, template fields, pages, media, fonts, settings, feature flags |
| Template generator | 1,000 templates generated in **2.0 s**; catalogue reached 1,051; every slug unique; removal restored the original 51 |
| Media library | Deleting an in-use asset refused with the usage listed; deleting with explicit confirmation removed the row and the file |
| Cron | `cleanup` and `analytics` ran and recorded their runs; an unknown task is refused |
| Backups | Database (518 KB) and full (1.8 MB) created; checksum verified; restore put a tampered setting and a deleted RSVP row back |
| Email | `log` driver writes to `storage/logs/mail-*.log`; SMTP path exercised through the test-send screen |

---

## 5. Security testing

44 automated assertions plus the manual probes below. No finding was left open.

| Attack | Attempt | Result |
|---|---|---|
| SQL injection | `' OR 1=1--`, `1; DROP TABLE users--`, `' UNION SELECT password FROM users--` through search, login and filters | Treated as data; `users` intact; no password column in any result |
| Stored XSS | `<script>alert(1)</script>` into an invitation field | Rejected at validation (**422**); nothing stored |
| Reflected XSS | Payload through query parameters | Escaped on output; no execution |
| Admin-authored XSS | `<script>`, `<iframe>`, `onclick=`, `javascript:` in template HTML/CSS | Stripped on save and on render |
| CSRF | State-changing POST with no token, a forged token and an empty token | **419** in every case; nothing executed |
| CSRF (API) | Cookie-authenticated `POST /api/v1/invitations` without a token | **419**; the same call with a bearer token succeeds, which is correct — a bearer token is not sent automatically by a browser |
| Auth bypass | Protected routes and `/admin` as a guest | 302 to login; `/admin` as a plain signed-in user **403** |
| IDOR | Another user's invitation by id, through the builder, the API, RSVP and analytics | **404**, not 403 — an id cannot be confirmed by probing |
| Path traversal | `../../etc/passwd` through the log viewer, page slugs, invitation slugs and upload names | Refused or sanitised; `/etc/passwd` never read |
| Zip Slip | Archive containing `../../escaped.txt` and `nested/../../escaped2.txt` | Safe entry extracted, both escaping entries refused, nothing written outside the destination |
| Malicious upload | PHP as `.png`, `shell.php.png`, HTML as `.jpg`, SVG with script, fake MP3, fake font | All rejected on content, not extension |
| Upload polyglot | Valid PNG with PHP appended | Re-encoded; the PHP is gone from the stored file |
| Upload execution | `.php` inside `uploads/` | Engine off, handlers removed, extension denied |
| Session | Cookie flags, fixation, idle timeout | `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, id regenerated on login |
| User enumeration | Login and password reset with an unknown address | Identical message and comparable timing (a dummy hash is always verified) |
| Secret exposure | Admin UI, API responses, logs, HTTP | Masked in the UI, absent from the API, redacted in logs, secret file unreachable |
| Rate limiting | Repeated login, RSVP, PDF and API requests | **429** with `Retry-After` at the configured thresholds |
| Headers | Every response | CSP with a per-request nonce, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`; `X-Powered-By` removed |

---

## 6. The update system

Exercised against this repository over the network — not simulated.

| Scenario | Result |
|---|---|
| Configure source | Repository validated, connection verified before the settings were accepted |
| Check for update | Latest commit SHA, message, author and date, plus the changed-file list |
| Dry run | lock → maintenance → database backup (520 KB) → file backup (1.8 MB) → download (1.8 MB) → extract (325 files) → validate → **nothing deployed**; recorded as `testing`, lock released, maintenance lifted |
| Apply, identical content | 11 steps passed; deploy reported `0 files updated, 21 protected files preserved` (unchanged files are skipped by hash) |
| Apply, one file behind | Deploy reported `1 file updated`; the modified file was restored to the published content byte for byte |
| Protected paths | `storage/`, `uploads/` and the secret file untouched across every run — a marker file in each survived |
| Migrations | Ran after deploy; `No new migrations` when there were none; the new schema-2 migration applied cleanly when there was |
| Health gate | Critical subset passed before the update was declared successful |
| Rollback (manual) | Files restored (305 files) **and** database restored (112 statements): a setting changed after the update reverted and an RSVP row inserted after it disappeared; entry marked `rolled_back` |
| Lock exclusivity | A second updater is refused while the lock is held; a stale lock is clearable from the admin screen |
| Repository validation | `owner/repo; rm -rf /`, `../../etc/passwd`, a full URL and a bare owner name all rejected |

Two defects were found and fixed during this testing: a dry run was being recorded as
a successful update (so the next check reported "up to date" over unchanged files), and
the pre-update **database** backup path was not stored, so a rollback restored the
files but left the database alone. Both are covered by the suite now.

---

## 7. Compatibility

| Target | Status |
|---|---|
| PHP 8.4 | Reference host. No deprecations emitted (two session INI settings are now applied only below 8.4) |
| PHP 8.0–8.3 | Supported: no 8.1+ only syntax is used; `min_php` in `version.json` is enforced by the installer and the updater |
| MariaDB 10.11 | Reference database |
| MySQL 5.7+ / MariaDB 10.3+ | Supported; `utf8mb4` throughout, `FULLTEXT` used where available |
| SQLite | The same migrations run, which is how the schema is verified without a server |
| Apache + `.htaccess` | Target deployment; rewrite, deny rules, compression and caching all in the shipped file |
| Nginx | Supported with the configuration in the deployment guide |
| PHP built-in server | `php -S localhost:8000 index.php` works, including static assets |
| No Composer, no Node | Verified: `vendor/` absent, nothing to build |

## 8. Performance

| Operation | Measured |
|---|---|
| Generate 1,000 templates | 2.0 s |
| List page 7 of a 1,051-template catalogue | 3 ms |
| Full-text search across that catalogue | 4 ms |
| Render an invitation (server side) | 5 ms |
| Generate an A4 PDF with Gujarati text | 45 ms |
| Generate a QR PNG | 11 ms |
| Database dump (39 tables, demo data) | ~1 s, 518 KB |
| Full backup (database + files) | ~3 s, 1.8 MB |
| Complete update cycle | ~13 s |

Listing queries are index-backed and paginated, and the catalogue never loads more
than one page of rows: the 3 ms figure is the same at 51 templates and at 1,051.

## 9. Accessibility and responsive behaviour

Checked by hand at 360 px, 768 px, 1024 px and 1440 px.

- Every form control has a label; icon-only buttons carry `aria-label`.
- A skip link, visible focus rings on every interactive element, and a logical tab order.
- The builder reflows as specified: form left / preview right on desktop, preview on
  top and controls beneath on a phone.
- Tables scroll horizontally inside a container rather than breaking the layout.
- Animations respect `prefers-reduced-motion`, and every animated card can be skipped.
- Colour contrast meets WCAG AA for body text against the cream background.

## 10. Known limitations

Stated plainly rather than left for someone to discover.

1. **PDF conjuncts.** The built-in PDF writer performs no OpenType shaping, so a
   Gujarati or Hindi conjunct renders as consonant + visible virama instead of a
   ligature, and a pre-base matra sits before the whole cluster. Text is correct,
   selectable and searchable; only the joining is simplified. The browser view is
   unaffected. Installing mPDF or Dompdf (both optional and auto-detected) uses their
   shaping instead. See `docs/PDF.md`.
2. **AI is untested against the live API** in this report — no Gemini key was
   configured on the reference host. The unavailable path, the fact guard, the quota
   and the deterministic recommender fallback are all covered; the HTTP call itself is
   not.
3. **Email delivery** was verified through the `log` and `mail` drivers. SMTP was
   exercised against the code path, not against a third-party provider.
4. **No load test.** The scalability figures above are single-request timings at a
   catalogue of 1,051 templates. Concurrency was not measured.
5. **iOS Safari and Android Chrome** were not driven directly; responsive behaviour was
   verified at their viewport sizes in a desktop browser.
