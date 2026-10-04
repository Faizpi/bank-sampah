const CACHE_PREFIX = 'bank-sampah-public-';
const CACHE_VERSION = 'v2';
const CACHE_NAME = `${CACHE_PREFIX}${CACHE_VERSION}`;

const PUBLIC_NAVIGATION_ALLOWLIST = new Set([
    '/',
    '/katalog-sampah',
    '/harga-sampah',
    '/pengumuman',
    '/jadwal-keliling',
    '/target-dan-statistik',
    '/ketentuan-dan-privasi',
]);

const PUBLIC_STATIC_ASSET_ALLOWLIST = new Set([
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
]);

const VERSIONED_BUILD_ASSET_PATTERN = /-[A-Za-z0-9_-]{8,}\.[A-Za-z0-9]+$/;

self.addEventListener('install', (event) => {
    event.waitUntil(
        precachePublicNavigations()
            .then(() => precachePublicStaticAssets())
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames
                .filter((cacheName) => cacheName.startsWith(CACHE_PREFIX) && cacheName !== CACHE_NAME)
                .map((cacheName) => caches.delete(cacheName)),
        )).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        event.respondWith(networkOnly(request));
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        event.respondWith(networkOnly(request));
        return;
    }

    if (request.mode === 'navigate') {
        if (PUBLIC_NAVIGATION_ALLOWLIST.has(url.pathname) && url.search === '') {
            event.respondWith(networkFirstPublicNavigation(request, url));
            return;
        }

        // Non-allowlisted navigation, including public QR verification and private routes, is network-only.
        event.respondWith(networkOnly(request));
        return;
    }

    const path = url.pathname;

    if (url.search === '' && path.startsWith('/build/') && VERSIONED_BUILD_ASSET_PATTERN.test(path)) {
        event.respondWith(cacheFirstVersionedBuildAsset(request));
        return;
    }

    if (url.search === '' && PUBLIC_STATIC_ASSET_ALLOWLIST.has(path)) {
        event.respondWith(cacheFirstPublicStaticAsset(request));
        return;
    }

    // Livewire, authentication, private, financial, media, signed, and export requests are network-only.
    event.respondWith(networkOnly(request));
});

function networkOnly(request) {
    return fetch(request);
}

async function precachePublicNavigations() {
    const cache = await caches.open(CACHE_NAME);

    await Promise.all([...PUBLIC_NAVIGATION_ALLOWLIST].map(async (path) => {
        try {
            const response = await fetch(new Request(path, { credentials: 'omit' }));

            if (response.ok && response.type === 'basic') {
                await cache.put(new Request(path), response);
            }
        } catch {
            // Installation remains available when an allowlisted public page is temporarily unreachable.
        }
    }));
}

async function precachePublicStaticAssets() {
    const cache = await caches.open(CACHE_NAME);

    await Promise.all([...PUBLIC_STATIC_ASSET_ALLOWLIST].map(async (path) => {
        try {
            const response = await fetch(new Request(path, { credentials: 'omit' }));

            if (response.ok && response.type === 'basic') {
                await cache.put(new Request(path), response);
            }
        } catch {
            // Installation remains available when a public icon is temporarily unreachable.
        }
    }));
}

async function networkFirstPublicNavigation(request, url) {
    const cache = await caches.open(CACHE_NAME);
    const cacheKey = new Request(url.pathname);

    try {
        return await fetch(request);
    } catch {
        const cachedResponse = await cache.match(cacheKey);

        if (cachedResponse) {
            return cachedResponse;
        }

        return Response.error();
    }
}

async function cacheFirstVersionedBuildAsset(request) {
    return cacheFirstPublicAsset(request);
}

async function cacheFirstPublicStaticAsset(request) {
    return cacheFirstPublicAsset(request);
}

async function cacheFirstPublicAsset(request) {
    const cache = await caches.open(CACHE_NAME);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        return cachedResponse;
    }

    const response = await fetch(request);

    if (response.ok && response.type === 'basic') {
        const cache = await caches.open(CACHE_NAME);
        await cache.put(request, response.clone());
    }

    return response;
}
