# Security

This document is a description of what the application actually does, not a wish list.
Every control below is implemented in the code referenced beside it, and the checks in
`tests/Cases/SecurityTest.php` and `tests/Cases/UploadTest.php` (73 assertions) fail if
one of them regresses.

## Reporting a vulnerability

Do not open a public issue. Email the address in **Admin → Settings → General →
Contact email** with a description and, if possible, a proof of concept. You will get
an acknowledgement, and a fix released through the normal update channel.

---

## Threat model

The application is multi-tenant: every user's invitations, photos, guest lists and
analytics are private to them, while a published invitation is public to anyone with
its link. The attackers it is designed against are:

1. **An anonymous visitor** who found a published invitation and wants other people's
   data, or wants to write to the database.
2. **A registered user** who wants to read or modify another user's invitations, or
   reach the admin panel.
3. **A compromised admin account** — which must not be able to plant JavaScript on
   every page, read secrets in plaintext, or deploy arbitrary code.
4. **A network attacker** between the guest and the server.
5. **A malicious update source** — a repository or archive that tries to escape the
   staging directory or overwrite protected files.

---

## Input handling and injection

**SQL.** Every query goes through PDO with bound parameters
(`app/Core/Database.php`); string interpolation of user input into SQL does not occur
anywhere in the codebase. Identifiers that must be interpolated (table and column
names in the generic repository) pass through `Database::wrap()`, which strips
everything outside `[A-Za-z0-9_.]` before quoting. `PDO::ATTR_EMULATE_PREPARES` is
off, so prepared statements are real server-side statements.

**Output.** Views escape on output rather than sanitising on input: `e()` for HTML,
`eattr()` for attributes, `ejs()` for JSON embedded in a script. `TemplateContext`
escapes by default — a template author has to go out of their way to get raw output,
and the raw accessors are only reachable for values the application produced.

**Admin-authored HTML.** Template `custom_html`/`custom_css` and CMS page bodies are
sanitised with an allow-list (`TemplateEngine::sanitiseHtml()` /
`sanitiseCss()`) when saved *and* when rendered. Removed: `<script>`, `<iframe>`,
`<object>`, `<embed>`, `<form>`, every `on*` attribute, `javascript:`/`data:` URLs in
attributes, `@import`, `expression()`, and `url(javascript:)`. This is the control
against threat 3: a stolen admin session cannot turn the site into a malware host.

**Placeholders.** `{{field_key}}` in a custom template resolves through the escaped
accessor, so a guest-visible value cannot introduce markup.

**Validation.** `app/Core/Validator.php` provides the rule set the controllers use
(`required`, `email`, `phone`, `url`, `date`, `time`, `hex_color`, `in`, `min`, `max`,
`unique`, `exists`, `confirmed`, `no_html`, `safe_text`, `password`, `locale`, …).
Template field validation is *derived from the field definition*, so a new field type
is validated without new code.

## Cross-site request forgery

`app/Core/Csrf.php` issues a 64-character synchroniser token per session, compared with
`hash_equals()`. The `csrf` middleware is applied per route, not globally, so a route
cannot be exempt by accident — `app/routes.php` lists it on every state-changing route.
A missing or wrong token returns **419** and the request is not executed. Cookies are
`SameSite=Lax`, which blocks the cross-site form post case as well.

## Sessions and authentication

- `password_hash()` with PHP's default algorithm; `password_verify()` on login;
  automatic rehash when the cost or algorithm changes.
- Login failures are throttled in **two** buckets — per email and per IP address — so
  neither a single account nor a single attacker can be brute forced, and an attacker
  spreading across accounts is still limited (`Auth::attempt()`).
- A failed login on an unknown address still performs a dummy `password_verify()`
  against a fixed hash, so response time does not reveal whether an account exists.
  The message is identical either way: "the email address or password is incorrect".
- Session cookies: `HttpOnly`, `Secure` when the request is HTTPS, `SameSite=Lax`,
  strict mode on, and the id is regenerated on login and on privilege change.
- Idle timeout and absolute lifetime are enforced server side, not by cookie expiry.
- "Remember me" uses a selector/validator pair: the selector identifies the row, the
  validator is stored hashed. A stolen database cannot be replayed as a login.
- Password reset tokens are single-use, expire in 60 minutes, and are stored hashed.
  Requesting a reset for an unknown address returns the same response as a known one.

## Authorisation

- Role-based, with 51 named permissions across four seeded roles. Checks are
  `Auth::can('permission.slug')`, enforced by the `can:` middleware on the route
  *and* re-checked in the controller for anything destructive.
- The super-admin role holds every permission implicitly, so a permission introduced
  by a future update is never silently withheld.
- **IDOR:** every owner-scoped read goes through `findOwned($id, $userId)` or
  `InvitationService::findOwnedOrFail()`, which raises a **404, not a 403** — probing
  ids cannot confirm that a record exists.
- An admin can unpublish or delete an invitation but the admin UI never offers to
  rewrite someone's wording.
- Route parameters take precedence over body and query in `Request::input()`, so a
  posted `id` field cannot redirect a write away from the resource named in the URL.

## Uploads

`app/Services/MediaService.php`:

- The type is decided by **content** (`finfo`), never by the extension or the
  browser-supplied MIME type. The extension is then derived from the detected type.
- Images are **re-encoded** through GD rather than moved, which normalises the file and
  destroys anything appended to it (a PNG with PHP after `IEND` becomes a plain PNG).
- A decompression-bomb guard rejects anything above 80 megapixels; size limits are
  configurable and checked before the file is read.
- Stored names are random (`bin2hex(random_bytes())` plus a timestamp) inside
  `uploads/<folder>/<year>/<month>/`. The original name is kept only as a label, after
  sanitising.
- SVG is not accepted as a photo — it can carry script.
- `uploads/.htaccess` sets `php_flag engine off`, removes every PHP handler and denies
  `.php`, `.phtml`, `.phar`, `.cgi`, `.pl`, `.py`, `.sh`, `.htaccess` by pattern, so a
  file that somehow lands there cannot be executed. Deletes are bounded by
  `realpath()` inside the uploads directory.
- Deleting a library asset that a template or invitation still uses is refused unless
  the operator explicitly confirms it, and the refusal says where it is used.

## Secrets

- Database credentials, the application key, the Gemini API key, the SMTP password and
  the GitHub token live in a file **outside the web root**:
  `../.invitation-secrets/app.php` (0640), with a deny-all `.htaccess` beside it as a
  second line of defence. If the parent directory is not writable, the installer falls
  back to `storage/config/app.php`, which `.htaccess` and the front controller both
  block.
- API keys and tokens are encrypted at rest with AES-256-GCM
  (`app/Core/Crypto.php`); the key comes from the secret file, so a database dump on
  its own does not disclose them.
- Secrets are **never** rendered in full: the admin UI shows `ghp_•••••••wxyz`.
  `AiService::safeSettings()` unsets the key entirely before the data reaches a view.
- Secrets are never logged. `Logger` redacts keys named like `password`, `token`,
  `secret`, `key`, `authorization` and `api_key` anywhere in its context array.
- `.gitignore` excludes `.env`, `config/app.php`, `storage/*` and `uploads/*`.

## Transport and headers

Sent on every response (`Application::applySecurityHeaders()`):

| Header | Value |
|---|---|
| `Content-Security-Policy` | `default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'`, `form-action 'self'`, and a **per-request nonce** for scripts |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` (`ALLOWALL` only on `/invite/*`, which is meant to be embeddable) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=(), payment=()` |
| `Strict-Transport-Security` | when the request is HTTPS and HTTPS is enforced |

`X-Powered-By` is removed in PHP as well as in `.htaccess`, so the version is not
advertised on hosts without `mod_headers`. With **Force HTTPS** on, every plain-HTTP
request is redirected, and proxy headers are only trusted from configured proxies.

No third-party script, font or stylesheet is loaded: Bootstrap, Bootstrap Icons,
Chart.js, SortableJS and all six font families are served from the application itself.
Nothing about a guest leaves the server.

## Rate limiting

`throttle:bucket,max,window` is declared per route:

| Route | Limit |
|---|---|
| `POST /login` | 20 / 10 min |
| `POST /register` | 10 / hour |
| `POST /password/forgot` | 6 / hour |
| `POST /invite/{slug}/rsvp` | 20 / hour |
| `POST /invite/{slug}/unlock` | 20 / 10 min |
| `GET /invite/{slug}/pdf` | 30 / 10 min |
| uploads | 60 / 10 min |
| `/api/v1/*` | 120 / min (configurable) |
| AI endpoints | per-user and per-IP, plus a configurable daily cap |

Exceeding a limit returns **429** with a `Retry-After` header.

## The update system

Treated as security-critical, because it deploys code. Full detail in
[UPDATE_SYSTEM.md](UPDATE_SYSTEM.md); the controls are:

- The GitHub token is encrypted at rest, never displayed in full, never sent to the
  browser and never logged. It needs read access to repository contents and nothing else.
- Repository names are validated against `owner/repo` before they are ever placed in a
  URL. Outbound requests are restricted to `api.github.com`/`codeload.github.com`, and
  the HTTP client refuses redirects to another host, private address ranges and
  non-HTTPS URLs (SSRF guard).
- The archive is extracted with a **Zip Slip** guard (`ArchiveExtractor`): every entry
  is resolved and rejected if it escapes the destination, along with absolute paths,
  symlinks, entry counts above 20,000, a total above 300 MB or a compression ratio
  above 120:1 (zip-bomb guard).
- Staging is validated before anything is deployed: required files present, a readable
  `version.json`, a minimum PHP check, and a syntax check of every changed PHP file
  performed in-process with `token_get_all()` — never by executing it.
- Protected paths are preserved and restored untouched: `storage/`, `uploads/`,
  `.env`, `config/app.php`, the secret file, the backup directory and
  `.htaccess`/`web.config`. **A deployment never extracts an archive over the public
  directory**; files are copied into place individually and renamed atomically.
- SQL is never executed from the downloaded archive. Only migration classes shipped in
  `database/migrations/` run, through the migrator.
- Failure at any step after the backup triggers an automatic rollback of both files and
  database, and the attempt is recorded with every step and its message.

## Privacy

- No IP address is stored anywhere. Where an address is needed to distinguish
  visitors or rate-limit, a salted HMAC is stored instead (`ip_hash`, `visitor_hash`),
  scoped per invitation so the same visitor is not correlatable across cards.
- No cookie is set on a public invitation page unless the guest submits an RSVP or
  unlocks a passphrase-protected card.
- AI logs record the action, status, token count and duration. Prompts and responses
  are not stored.
- A user can export everything held about them as JSON, and delete their account,
  which purges their invitations, photos and RSVP responses.
- The service worker keeps a "never cache" list so `/admin`, `/dashboard`, `/api/`,
  `/builder` and `/invite/` responses are not written to the browser cache.

## Error handling

In production, `display_errors` is off and a visitor sees **"Something went wrong.
Please try again."** with a reference code. The detail — class, message, file, line and
a compacted stack trace — goes to `storage/logs/`, outside the web root, under the
reference shown to the user. Expected outcomes (404, 403, a rejected form) are recorded
at info level without a trace, so a real fault is not lost in noise. PHP deprecations
are logged, never displayed, and never fatal.

## What is deliberately *not* claimed

- Complex Indic conjuncts in generated PDFs render as consonant + visible virama
  rather than a true ligature, because the PDF writer performs no OpenType shaping.
  The browser view (and therefore the screenshot a user shares) is unaffected. See
  `docs/PDF.md`.
- There is no built-in web application firewall, bot detection or CAPTCHA. Rate
  limiting and CSRF are the controls; put Cloudflare or equivalent in front if you
  expect abuse.
- Payment handling is not implemented, so no cardholder data is processed anywhere.
