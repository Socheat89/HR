const CACHE_NAME = 'hr-app-cache-v1';
const urlsToCache = [
    '/',
    'login.php',
    'manifest.json',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png',
    'https://png.pngtree.com/background/20230401/original/pngtree-khmer-new-year-frame-vector-picture-image_2253486.jpg',
    'https://i.ibb.co/mrtxdVKp/Khmer-flowe.png',
    'https://i.ibb.co/TDY55fP5/Khmer-flower2.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache);
            })
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                return response || fetch(event.request);
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