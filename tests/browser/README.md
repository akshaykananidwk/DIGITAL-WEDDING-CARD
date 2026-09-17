# Optional browser checks

`php tests/run.php` is the suite that matters: it has no dependencies and runs on the
same plain PHP hosting the application targets.

This directory holds an **optional** developer tool that drives a real browser to catch
what PHP cannot see — Content-Security-Policy violations, console errors, horizontal
scrolling on a phone, charts that fail to draw, form controls without labels.

It is **not needed to run, install, update or deploy the application**, and nothing in
`app/` refers to it. Node is a developer convenience here, never a runtime requirement.

## Running it

```bash
# 1. Serve the application somewhere the browser can reach.
php -S 127.0.0.1:8080 index.php

# 2. Install the driver (once) and run the checks.
cd tests/browser
npm install playwright-core
node check.mjs                     # public pages at four viewport widths
node check.mjs --auth              # signed-in and admin pages, phone width
```

Set `SK_BASE` to point at another origin, and `SK_EMAIL` / `SK_PASSWORD` to use a
different account for `--auth` (defaults: `http://127.0.0.1:8080` and the address you
installed with).

`CHROME_PATH` overrides the browser binary; otherwise the usual Playwright cache
locations are tried.
