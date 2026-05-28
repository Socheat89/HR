const CACHE_NAME = 'hr-app-v1';
const urlsToCache = [
  '/',
  'https://app.vvc.asia/stock-control/dashboard.php',
  '/manifest.json',
  'https://i.ibb.co/4ntDCgkg/Logo-Van-Van-1.jpg'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return response || fetch(event.request).catch(error => {
          console.warn('[Service Worker] Fetch failed:', event.request.url, error);
        });
      })
  );
});