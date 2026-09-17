# Shubh Kankotri · digital invitation card platform

A complete invitation-card SaaS for Indian occasions — Gujarati weddings and the
functions around them, poojas and kathas, shop openings, birthdays, anniversaries and
parties. A host picks a design, fills in a guided form, and shares a living invitation
card on WhatsApp: countdown, map, photo gallery, RSVP, printable PDF and QR code, in
Gujarati, Hindi or English.

**It runs on ordinary PHP hosting.** Apache or Nginx, PHP 8.0+, MySQL or MariaDB.
No Node.js, no Composer, no build step, no queue worker, no shell access required.
The PDF writer, the QR encoder, the SMTP client and the archive-safety code are all
implemented in the PHP that ships here.

---

## What it does

**For the host.** Browse or search a catalogue of designs by occasion, language,
colour and style. An eight-step builder with a live preview rendered by the same
engine as the public page. Photos with drag-to-reorder. Optional music that never
autoplays without consent. A memorable link (`/invite/rahul-weds-priya`) plus a short
one for QR codes (`/i/8F3K9A`). One-tap WhatsApp sharing with the message already
written. RSVP with guest counts and messages. Private analytics. An A4 or
mobile-shaped PDF for printing or forwarding.

**For the guest.** A card that opens fast on a phone, in the host's language, with the
date in their calendar, the venue on a map and one tap to reply. No account, no
tracking, no third-party scripts.

**For the operator.** An admin panel over users and RBAC, the template catalogue and
its field definitions, invitations, the media library, fonts, CMS pages, analytics, an
audit log, 11 groups of settings, feature flags, AI settings and usage, health
monitoring, backups with restore, scheduled tasks, and one-click updates from GitHub
with automatic rollback.

## The idea that makes it scale

A template is **a row, not a file**. Each one is a `layout_key` (one of ten PHP
renderers) crossed with a `theme` (design tokens as JSON) and a set of
`template_fields` rows that decide what the builder asks for and how each answer is
validated. Adding a design is inserting a row; adding a *field* to a design is
inserting a row. Nothing about an individual invitation is hardcoded anywhere.

A bespoke card that needs more than its layout offers can instead be built from
`template_components` in the admin panel: blocks with `{{field_key}}` placeholders,
grouped into pages, which take over the rendering while any of them is visible and
hand it back to the layout when they are gone.

That is why the built-in generator can produce thousands of distinct, sensible
templates — layouts × 16 palettes × font pairings × 68 occasions — and why the
catalogue stays fast at that size: **1,000 templates generate in 2 seconds, and page 7
of a 1,051-template catalogue renders in 3 ms.**

## Getting started

```bash
# Upload the files, then open /install in a browser - three screens, no SQL, no config
# editing. Or install from a shell:
php bin/console install \
  --db-host=127.0.0.1 --db-name=kankotri --db-user=kankotri --db-pass='…' \
  --site-name="Shubh Kankotri" --site-url=https://your-domain.com --locale=gu \
  --admin-name="Your Name" --admin-email=you@example.com --admin-pass='…' --demo
```

Full instructions, including cPanel, Nginx, cron and HTTPS:
**[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)**.

For local development, the built-in server is enough:

```bash
php -S localhost:8000 index.php
```

## Command line

```bash
php bin/console help               # every command
php bin/console migrate            # apply pending migrations
php bin/console seed --demo        # roles, settings, catalogue, demo content
php bin/console health             # 23 runtime checks
php bin/console backup --type=full
php bin/console cron:run           # what the scheduler calls
php bin/console templates:generate --count=250
php bin/console pdf:test
```

## Tests

```bash
php tests/run.php                  # 515 checks, 9 cases, no dependencies
php tests/run.php Security         # one case
php tests/run.php --json           # machine readable
```

The suite runs on the same plain hosting the application targets — no PHPUnit, no
Composer, no Node. `tests/browser/` holds an optional Playwright script for things
only a browser can see (CSP violations, console errors, phone-width layout); it is a
developer tool and is never needed to run or deploy anything.

What was actually tested, measured and fixed: **[TESTING_REPORT.md](TESTING_REPORT.md)**.

## Documentation

| | |
|---|---|
| [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) | Install, configure, harden, tune, troubleshoot |
| [UPDATE_SYSTEM.md](UPDATE_SYSTEM.md) | One-click GitHub updates, and how rollback works |
| [SECURITY.md](SECURITY.md) | Threat model and every control, with where it lives |
| [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) | All 40 tables, their columns and their indexes |
| [API_DOCUMENTATION.md](API_DOCUMENTATION.md) | The JSON API |
| [TESTING_REPORT.md](TESTING_REPORT.md) | Results, measurements, defects found, limitations |
| [docs/PDF.md](docs/PDF.md) | The PDF writer, and honestly what it cannot do |

## Layout

```
index.php              front controller (also a static-file router under php -S)
version.json           version, schema, minimum PHP - read by the updater
bin/console            CLI
app/
  Core/                framework: router, request, response, database, auth, view,
                       validator, CSRF, crypto, cache, logger, migrator, PDF, QR
  Controllers/         Web (18), Api (7), Admin (19), Install
  Services/            template engine, invitations, PDF, QR, share, RSVP, analytics,
                       AI, mail, health, backup, update, GitHub, cron, SEO
  Repositories/        one per table, PDO with bound parameters throughout
  Views/               99 templates: layouts, invitation layouts, builder, dashboard,
                       admin, emails, errors, installer
  Seeds/               roles, permissions, categories, field presets, palettes,
                       templates, settings, fonts, pages, demo data
  Lang/                en, gu, hi - full parity, verified by the suite
database/migrations/   7 migrations, MySQL and SQLite from one definition
assets/                CSS, JS, self-hosted fonts and vendor libraries
storage/  uploads/     runtime state and user content (never in the repository)
tests/                 the suite, plus the optional browser checks
```

## Notable engineering

- **A pure-PHP QR encoder** — ISO/IEC 18004 byte mode, Reed–Solomon over GF(256),
  all eight masks scored against the four penalty rules. Output decodes in `zbarimg`.
- **A pure-PHP PDF writer** — CIDFontType2 with Identity-H, TrueType subsetting with
  composite-glyph resolution, a ToUnicode CMap so Gujarati text extracts correctly,
  Flate compression, gradients and JPEG pass-through. Limitations are documented
  rather than glossed over: see [docs/PDF.md](docs/PDF.md).
- **Indic text handling without HarfBuzz** — pre-base matra reordering and
  zero-advance mark positioning computed from the font's own glyph metrics.
- **One schema, two dialects** — the migrations emit MySQL and SQLite from a single
  definition, which is how the schema is verified without a database server.
- **Privacy-first analytics** — a salted HMAC per invitation instead of an IP address,
  raw events aggregated nightly and pruned, no third-party script anywhere.
- **An update engine treated as security-critical** — backup, stage, validate,
  syntax-check, preserve protected paths, deploy atomically, migrate, health-gate,
  and roll both files and database back if anything fails.

## Licence and credits

The application code carries no licence file yet — that is the repository owner's
call to make, and adding one without being asked would be presumptuous. Bundled fonts (Noto Sans, Noto Sans Gujarati, Noto
Sans Devanagari, Great Vibes, Playfair Display, Cormorant Garamond) are under the SIL
Open Font License — `assets/fonts/OFL.txt`. Bootstrap and Bootstrap Icons are MIT;
Chart.js is MIT; SortableJS is MIT. All are vendored, so nothing about a visitor
reaches a third party.
