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
■ Two-step sign-in                            35 checks  6 544 ms
■ Authentication and authorisation            29 checks  1 065 ms
■ Templates and the engine                    90 checks  2 979 ms
■ Invitations, slugs and RSVP                 57 checks    313 ms
■ PDF, QR, calendar and sharing               60 checks    170 ms
■ Uploads and the media library               33 checks    173 ms
■ AI generator and recommender                38 checks     13 ms
■ System, installer, backups and updates     129 checks  4 616 ms
──────────────────────────────────────────────────────────────────
All 515 checks passed in 17.8 s across 9 cases
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
| Migrations | 8 files, 40 tables |
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
| Admin panel (42 screens: users, roles, taxonomy, templates, fields, components, generator, invitations, media, fonts, pages, analytics, audit, all 11 settings groups, flags, AI, AI log, system, health, logs, cron, backups, updates, update history) | 42 | 200 |
| **Total** | **95** | **95 as expected, 0 unexpected, 0 errors logged** |

Static assets, the manifest and the service worker were fetched and returned with the
right content types (`text/css`, `application/javascript`, `font/ttf`,
`application/manifest+json`, `image/png`). `/service-worker.js` has its own route, so
it works on hosts that send unknown paths to the front controller, and the built-in
PHP server now serves static files too — `php -S localhost:8000 index.php` is a usable
development setup.

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
| Two-step sign-in | Enabled on the profile screen; a correct password redirected to `/login/verify` and `/dashboard` still bounced to login; the emailed code signed in; a wrong code was refused with the attempts left; the code could not be reused |
| Template browsing | Gallery, filters (category, language, colour, type, tag), search, detail, full-screen preview |
| Invitation creation | Created from a template as a draft with a unique slug and short code, default sections seeded |
| Content saving | Values persisted per field; unknown keys ignored; markup and over-long values rejected |
| Live preview | Unsaved values POSTed to the same server-side renderer the public page uses |
| Publishing | Refused while required fields are empty, with the missing fields named; succeeds once filled; `published_at` set |
| Slug change | `/invite/suite-renamed-card` served immediately; a traversal attempt sanitised to a safe slug; reserved and too-short slugs refused |
| Short link | `/i/PWZLQU` → **301** to the full URL, QR scan counted |
| Short link reissued | `POST /builder/1/short-code` replaced that code with `/i/VWE6HH` (**301**), after which the earlier `/i/PWZLQU` returned **404** — a short link that has travelled too far can be revoked |
| Secondary roles | A role added in `user_roles` granted its permissions on the next sign-in without conferring super admin, and removing the row took them away again |
| Template components | Two components added from `/admin/templates/1/components` took over the rendering: placeholders resolved, styles applied, `<script>` and `onclick` stripped on save *and* on render, a hidden component left out, two pages rendered as a page-turning card; deleting them handed rendering back to the layout |
| GitHub token removal | Saving a token showed it masked (`ghp_••••••••en00`) with a **Remove stored token** control; removing it cleared the setting, wrote an audit entry, and put no token text in any log |
| RSVP | Guest submission stored, summary counts and guest total correct, a repeat from the same visitor updates rather than duplicates, CSV export contains the guest and no IP address |
| Share tracking | `POST /invite/{slug}/share` recorded per channel; counters incremented |
| Analytics | Views, unique views, shares and downloads recorded; 30-day series; device, browser, referrer and channel breakdowns; CSV export |
| PDF | A4 and mobile generated; `qpdf --check` reports a valid 1-page PDF; `pdftotext` extracts the Gujarati text (`॥ શુભ લગ્ન ॥`, `સંગ`, `પુત્ર`) and the Latin names |
| QR | PNG and SVG; `zbarimg` decodes the PNG to `http://localhost:8080/i/PWZLQU`; print size larger than screen size; the venue code decodes to the Google Maps link and the RSVP code to the form |
| Calendar | `.ics` valid, `Asia/Kolkata` VTIMEZONE, CRLF line endings, no line over 75 octets |
| Duplicate | New row, own slug and short code, content copied, status draft |
| Delete / purge | Soft delete hides it from the owner's list and keeps the row; purge removes the row and its content |
| Account deletion | Invitations purged, avatar removed, email released, audit entry kept |
| Data export | `GET /profile/export` returns the account, invitations and responses as JSON |
| Admin CRUD | Users, roles and permissions, categories and subcategories, templates, template fields, pages, media, fonts, settings, feature flags |
| Template generator | 1,000 templates generated in **2.0 s**; catalogue reached 1,051; every slug unique; removal restored the original 51 |
| Media library | Deleting an in-use asset refused with the usage listed; deleting with explicit confirmation removed the row and the file; choosing a shared library track raised its use count, while another user's audio id was refused **422** |
| Cron | `cleanup` and `analytics` ran and recorded their runs; an unknown task is refused |
| Backups | Database (518 KB) and full (1.8 MB) created; checksum verified; restore put a tampered setting and a deleted RSVP row back |
| Email | `log` driver writes to `storage/logs/mail-*.log`; SMTP path exercised through the test-send screen |
| JSON API | Token issued by `POST /auth/login`; `GET /auth/me`, `/templates`, `/templates/{slug}` (with field definitions), `/categories`, `/invitations`, `/analytics`; `POST /invitations` created a draft, `PUT` updated it, `POST …/publish` published it, `DELETE` removed it; `POST /rsvp/{slug}` recorded a guest response; a missing or unknown token returns 401 |

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
| Database dump (40 tables, demo data) | ~1 s, 520 KB |
| Full backup (database + files) | ~3 s, 1.8 MB |
| Complete update cycle | ~13 s |

Listing queries are index-backed and paginated, and the catalogue never loads more
than one page of rows: the 3 ms figure is the same at 51 templates and at 1,051.

## 9. Browser rendering, responsive layout and accessibility

Real pages were rendered in Chromium (`tests/browser/check.mjs`, optional) and
inspected programmatically — not eyeballed.

**49 renders, 0 findings:**

| Sweep | Pages | Viewports |
|---|---|---|
| Public | home, gallery, categories, login, register, a CMS page | 360, 768, 1024, 1440 px |
| Signed in and admin | 25 pages including every builder step, RSVP, both analytics screens, the field builder, system, health, updates and cron | 390 px (phone) |

Checked on every render: HTTP status, Content-Security-Policy violations, console and
page errors, horizontal scrolling (document scroll width against client width, with the
offending element named), form controls without an accessible name, icon-only controls
without one, images without `alt`, whether the application's JavaScript ran, and
whether every declared chart actually drew.

This is what found the most serious defect in the whole build: `'strict-dynamic'` in
`script-src` disables host allow-listing by design, including `'self'`, so **every
external script tag was blocked** — a production deployment would have run no
JavaScript at all. It also found asset URLs breaking on a hostname that differs from
the configured site URL, two pages scrolling sideways at 390 px, and three sets of
icon-only controls without an `aria-label`. All fixed, all re-verified.

Also confirmed in the browser:

- The builder reflows as specified: form left / preview right on desktop, preview on
  top and controls beneath on a phone.
- Charts render on the dashboard, both analytics screens and the admin dashboard.
- Tables scroll inside their container rather than widening the page.
- A skip link is present, focus rings are visible, and headings are ordered.
- Animations respect `prefers-reduced-motion`, and every animated card can be skipped.

## 10. Defects found and fixed during testing

Testing that finds nothing has usually not been done. These were found by running the
application, and each one is now covered by a check that fails if it comes back.

| # | Defect | Found by |
|---|---|---|
| 1 | `script-src 'strict-dynamic'` blocked every external script, so production would have run no JavaScript | Browser render |
| 2 | Asset URLs came from the configured site URL, so any other hostname broke CSS, images and fonts | Browser render |
| 3 | The router dropped a placeholder pattern at its first `}`, so `/i/{code:…{4,12}}` and `/lang/{locale:[a-z]{2}}` never matched | HTTP sweep |
| 4 | `Request::input()` ignored route parameters, so every `/{id}/` route read id 0 and returned 404 | HTTP sweep |
| 5 | Bearer-token API writes were rejected with 419 by the CSRF middleware | API exercise |
| 6 | The AI fact guard fell back to unfiltered text when it had dropped every sentence | Automated suite |
| 7 | The fact guard deleted correctly translated dates, because Indic and Western digits were compared literally | Automated suite |
| 8 | A rollback restored the files but not the database — the pre-update dump path was never stored | Update exercise |
| 9 | A dry run was recorded as a successful update, so the next check reported "up to date" over unchanged files | Update exercise |
| 10 | `Cache::put()` treated a zero or negative TTL as "never expires" | Automated suite |
| 11 | A completed dry run looked like a stuck update to the health check | Automated suite |
| 12 | `php -S` served no static assets; `/service-worker.js` 404'd behind a front controller | HTTP sweep |
| 13 | Ordinary 404s were logged as CRITICAL with a stack trace | Log review |
| 14 | Two session INI settings deprecated in PHP 8.4 were still being set | Log review |
| 15 | Inline code chips did not wrap, so long paths scrolled the page sideways on a phone | Browser render |
| 16 | Icon-only controls in three places had a `title` but no `aria-label` | Browser render |
| 17 | Column mismatches in new views (template previews, health timestamps, RSVP read flag, notification links) | HTTP sweep |
| 18 | `X-Powered-By` was only removed by `.htaccess`, so hosts without `mod_headers` advertised the PHP version | Header review |
| 19 | A long card silently lost its programme and family names, and the QR footer could be drawn past the paper edge | PDF review |

## 11. Known limitations

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
