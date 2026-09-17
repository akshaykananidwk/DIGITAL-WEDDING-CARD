/* =========================================================================
   Service worker.
   Caches the application shell and static assets so the app opens instantly
   and shows a useful page when the network is gone.

   Deliberately conservative about what it stores: only GET requests for
   static assets and the offline shell. Anything under /dashboard, /builder,
   /admin, /api or /invite is always fetched from the network, so private
   content is never written to the cache.
   ========================================================================= */

const VERSION = 'sk-v1';
const SHELL_CACHE = VERSION + '-shell';
const ASSET_CACHE = VERSION + '-assets';

/* Populated at install time; the scope tells us the base path. */
const SHELL_URLS = [
    'offline',
    'assets/css/app.css',
    'assets/js/app.js',
    'assets/vendor/bootstrap.min.css',
    'assets/vendor/bootstrap-icons.css',
];

/** Paths that must never be cached. */
const NEVER_CACHE = [
    '/admin', '/api/', '/dashboard', '/builder', '/create', '/invitations',
    '/profile', '/login', '/register', '/logout', '/install', '/cron/',
    '/password', '/notifications', '/invite/',
];

function base() {
    return self.registration.scope.replace(/\/$/, '');
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(SHELL_CACHE).then(function (cache) {
            return cache.addAll(SHELL_URLS.map(function (path) { return base() + '/' + path; }))
                .catch(function () { /* a missing asset must not block install */ });
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys
                .filter(function (key) { return key.indexOf(VERSION) !== 0; })
                .map(function (key) { return caches.delete(key); }));
        }).then(function () { return self.clients.claim(); })
    );
});

function isPrivate(url) {
    const path = url.pathname;
    return NEVER_CACHE.some(function (prefix) {
        return path.indexOf(prefix) !== -1;
    });
}

function isStaticAsset(url) {
    return /\/assets\/.+\.(css|js|woff2?|ttf|png|jpe?g|svg|webp|ico)$/.test(url.pathname);
}

self.addEventListener('fetch', function (event) {
    const request = event.request;

    if (request.method !== 'GET') { return; }

    let url;
    try {
        url = new URL(request.url);
    } catch (error) {
        return;
    }

    // Only handle our own origin.
    if (url.origin !== self.location.origin) { return; }
    if (isPrivate(url)) { return; }

    // Static assets: cache first, they are content-hashed by query string.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then(function (cached) {
                if (cached) { return cached; }
                return fetch(request).then(function (response) {
                    if (response && response.ok) {
                        const copy = response.clone();
                        caches.open(ASSET_CACHE).then(function (cache) { cache.put(request, copy); });
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Navigations: network first, fall back to the offline page.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return caches.match(base() + '/offline').then(function (cached) {
                    return cached || new Response(
                        '<h1>Offline</h1><p>Please reconnect and try again.</p>',
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
                    );
                });
            })
        );
    }
});

/* Let the page ask the worker to activate a new version immediately. */
self.addEventListener('message', function (event) {
    if (event.data === 'skipWaiting') { self.skipWaiting(); }
});
