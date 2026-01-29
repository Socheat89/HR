const CACHE_NAME = 'chat-app-v1';
const urlsToCache = [
    '/',
    'messenger.php',
    '/login.php',
    '/logout.php',
    '/css/tailwind.min.css',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Caching files');
                return cache.addAll(urlsToCache);
            })
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    console.log('Serving from cache:', event.request.url);
                    return response;
                }
                return fetch(event.request).catch(() => {
                    console.log('Fetch failed, offline mode');
                    return caches.match('/index.php');
                });
            })
    );
});

self.addEventListener('activate', event => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (!cacheWhitelist.includes(cacheName)) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});