# Deployment guide

The application is plain PHP. It needs Apache (or Nginx, or LiteSpeed), PHP 8.1 or
newer and MySQL/MariaDB. **It does not need Node.js, Composer, a build step, a queue
worker or shell access.** Everything — the PDF writer, the QR encoder, the SMTP
client, the ZIP safety checks — is implemented in PHP that ships with the application.

---

## Requirements

| | Minimum | Recommended |
|---|---|---|
| PHP | 8.1 | 8.2 or 8.3 |
| MySQL | 5.7 | MariaDB 10.5+ |
| Disk | 300 MB | 2 GB (uploads and backups grow) |
| Memory limit | 64 MB | 128 MB |
| `upload_max_filesize` | 2 MB | 8–16 MB |
| `post_max_size` | ≥ `upload_max_filesize` | 16–32 MB |
| `max_execution_time` | 30 s | 120 s (updates and full backups) |

**Required extensions:** `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`.

**Recommended:** `gd` (image resizing, thumbnails, WebP — without it photo upload is
unavailable), `zip` (backup archives and updates), `curl` (GitHub updates),
`intl`, `exif`, `zlib`.

The installer checks all of this and refuses to continue if something required is
missing, naming what to ask your host for.

---

## Install on cPanel

1. **Create the database.** MySQL® Databases → create a database and a user, and grant
   the user **All Privileges** on it. Note the four values; the installer needs them.
2. **Upload the files.** File Manager or FTP. Put the contents of the repository in the
   directory your domain serves — usually `public_html` for the main domain, or
   `public_html/kankotri` for a subfolder. Include the dotfiles: `.htaccess` matters.
3. **Recommended, one extra step for security:** create a directory
   `.invitation-secrets` **one level above** the web root (so `/home/user/`, next to
   `public_html`) and make it writable. The installer will put your credentials there
   instead of inside the site. If you skip this, it falls back to
   `storage/config/app.php`, which is blocked from the web — but outside is better.
4. **Set the PHP version** to 8.1 or newer in MultiPHP Manager, and raise
   `upload_max_filesize`, `post_max_size` and `max_execution_time` in MultiPHP INI
   Editor.
5. **Open `https://your-domain.com/install`** and follow three screens:
   requirements → database → site and administrator. It creates the database if the
   user has permission, runs the migrations, seeds roles, permissions, categories,
   fonts, pages and the template catalogue, creates your administrator account, writes
   the configuration outside the web root, locks itself and runs a health check.
6. **Delete nothing.** The installer locks itself; `/install` afterwards shows
   "Application already installed."
7. **Sign in** at `/login` and go to **Admin → System → Health**. Green is the goal;
   amber items are things to finish (email sender, first backup, cron).

### aaPanel / Plesk / a plain VPS

The same steps. Point the document root at the application directory, make sure
`storage/` and `uploads/` are writable by the web user, and visit `/install`.

### From the command line

```bash
php bin/console install \
  --db-host=127.0.0.1 --db-name=kankotri --db-user=kankotri --db-pass='…' \
  --site-name="Shubh Kankotri" --site-url=https://your-domain.com --locale=gu \
  --admin-name="Your Name" --admin-email=you@example.com --admin-pass='…' \
  --demo          # optional: a demo invitation with analytics and RSVP responses
```

---

## Directory permissions

```
storage/            755, writable by the web user (logs, cache, sessions, tmp, update)
storage/backups/    755, writable  (or point it outside the web root — see below)
uploads/            755, writable  (photos, music, QR, fonts, media library)
everything else     644 files / 755 directories, not writable by the web user
```

`storage/` and `uploads/` each carry an `.htaccess` that switches the PHP engine off
and denies executable extensions. If your host ignores `.htaccess` (Nginx, LiteSpeed
with certain configs), add the equivalent to the server config — see **Nginx** below.

## Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/kankotri;
    index index.php;

    client_max_body_size 16M;

    # Everything goes through the front controller.
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Never serve the application's internals.
    location ~ ^/(app|database|storage|tests|bin|docs)/ { deny all; }
    location ~ /\.(env|git|htaccess) { deny all; }

    # Uploads are data, never code.
    location ^~ /uploads/ {
        location ~ \.(php|phtml|phar|cgi|pl|py|sh)$ { deny all; }
    }

    # Long cache for fingerprinted assets.
    location ^~ /assets/ { expires 30d; add_header Cache-Control "public, immutable"; }
}
```

---

## HTTPS

Install a certificate (Let's Encrypt through AutoSSL, certbot, or your host's panel),
then turn on **Admin → Settings → Security → Force HTTPS**. Every plain-HTTP request
is then redirected and `Strict-Transport-Security` is sent. Do this before you share
your first invitation link: turning it on later leaves the earlier links on HTTP.

## Email

**Admin → Settings → Email & SMTP.** The `mail` driver works on most shared hosts.
SMTP is more reliable: host, port (587 with STARTTLS, or 465 with SSL), username,
password — the password is stored encrypted. Set a **From address on your own domain**,
or providers will reject the mail. Send a test from the same screen; with the `log`
driver the message is written to `storage/logs/mail-*.log` instead of being sent, which
is useful while you are setting things up.

## Cron

**Admin → System → Scheduled tasks** shows the exact line to paste. On cPanel, Cron
Jobs → once an hour:

```
0 * * * * /usr/local/bin/php /home/user/public_html/bin/console cron:run >/dev/null 2>&1
```

No shell access? The same screen shows a tokenised URL you can call from an external
cron service. Keep the token private — anyone holding it can trigger the tasks.

The tasks: `cleanup` (expired tokens, stale sessions, temp files), `analytics`
(aggregate daily stats and prune raw events), `reminders` (notify owners about upcoming
and finished events), `backup` (scheduled database backup), `health` (record a
snapshot), `cache` (prune expired entries), `update-check` (notice a new release).

## Backups

**Admin → System → Backups.** Database, files, or everything. Backups are written
outside the web root when the installer could place your configuration there. Verify
the checksum from the same screen, download, or restore — a restore takes its own
snapshot first, and needs `RESTORE` typed out.

Turn on scheduled backups in **Settings → Backups** once cron is running, and keep an
off-server copy of anything you would miss. A backup on the same disk is not a backup.

## Updates

Enter your repository once in **Admin → System → Updates** and every future release is
one click, with a real rollback. See [UPDATE_SYSTEM.md](UPDATE_SYSTEM.md).

---

## Performance

Out of the box: page caching for the catalogue, `Cache-Control`/`Expires` on assets,
gzip and brotli where the server offers them, lazy-loaded images, WebP copies
generated on upload, deferred scripts, and pagination everywhere — the gallery never
loads more than one page of rows, whether the catalogue holds 50 templates or 50,000.

To go further:

- **OPcache** on, with `opcache.validate_timestamps=1` (the updater resets OPcache
  after a deploy).
- **Longer asset caching** at the CDN or in `.htaccess`; `/assets/` is safe to cache
  hard, `/uploads/` for a day.
- **A CDN** in front of `/assets/` and `/uploads/`, which is why nothing there needs a
  cookie.
- **Larger `analytics_retention`** only if you need it; the aggregate table is what the
  charts read, so pruning raw rows costs nothing visible.
- **Database:** the schema carries the indexes the queries need; on a large catalogue
  give InnoDB a buffer pool of at least 256 MB.

Measured on the reference install (PHP 8.4, MariaDB 10.11, one shared CPU):
1,000 generated templates in ~2 s, page 7 of a 1,051-template catalogue in 3 ms,
a full-text search across it in 4 ms, an A4 PDF with Gujarati text in ~45 ms, a QR PNG in ~11 ms.

---

## After going live: a checklist

- [ ] `/install` shows "Application already installed."
- [ ] **Admin → System → Health** is green, or every amber item is understood.
- [ ] Force HTTPS is on and the certificate is valid.
- [ ] A test email arrives.
- [ ] Cron has run at least once (the screen shows the last run).
- [ ] A backup exists, has been verified, and a copy is off-server.
- [ ] The update source is configured and **Verify access** succeeds.
- [ ] Your own account has a strong password; extra staff have `editor`, not `admin`.
- [ ] `storage/` and `uploads/` are not browsable: `https://your-domain.com/storage/logs/`
      must return 403 or 404.
- [ ] Privacy policy and terms pages say what you actually do
      (**Admin → Pages**).

## Reading an error reference

Every 500 shows a reference such as `5254EDEE`. That is the key to the actual
cause, which is never shown to a visitor:

```bash
grep -r 5254EDEE storage/logs/          # over SSH
```

Without SSH, open `storage/logs/app-<today>.log` in the panel's file manager
and search for the reference. The line names the exception, the file and the
line. When the application cannot write that file at all, the same message goes
to the host's own PHP error log (cPanel: **Metrics → Errors**; aaPanel: the
site's error log).

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| 500 on every page | `storage/` not writable, or PHP below 8.1 (8.0 and older cannot parse the code and say so in plain text before anything loads). Read `storage/logs/app-<date>.log` and search for the reference printed on the page; if that file is missing or unwritable, the same line is in the host's PHP error log instead (cPanel: **Metrics → Errors**). |
| 500 before the installer opens | Usually `open_basedir` narrowed to the document root. The application detects it and keeps its secrets in `storage/config/` instead; the installer's requirements screen lists the restriction when one is in force. |
| "Application already installed" during a first install | A stale `storage/installed.lock` or secret file from an earlier attempt. Remove both and retry. |
| Blank page after upload | Dotfiles missing — `.htaccess` did not come across. Re-upload including hidden files. |
| `/templates` works, `/invite/x` 404s | `mod_rewrite` off, or `AllowOverride None`. Enable both. |
| Photos fail to upload | `gd` missing, or `upload_max_filesize` too small. Health reports which. |
| PDF has boxes instead of Gujarati | The bundled fonts did not upload. Check `assets/fonts/*.ttf`, then **System → Health → Fonts**. |
| Login says "too many attempts" | The throttle is doing its job. Wait, or clear `storage/cache/`. |
| Update says the lock is held | A previous run crashed. **System → Updates → Clear the lock**, then read the last history entry. |
