const CACHE_VERSION = 'v1.0.1'; // ត្រូវប្តូររាល់ពេល Update
const CACHE_NAME = 'attendance-cache-' + CACHE_VERSION;
const URLS_TO_CACHE = [
  '/',
  '/scan.php', // ទំព័រមេ
  '/view_logs.php', // ប្រសិនបើមាន
  '/manifest.json',
  // CSS & Fonts
  'https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700&display=swap',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://unpkg.com/html5-qrcode/html5-qrcode.min.js',
  // Icons (ត្រូវប្រាកដថាបានបង្កើត)
  '/icons/icon-72x72.png',
  '/icons/icon-96x96.png',
  '/icons/icon-128x128.png',
  '/icons/icon-152x152.png',
  '/icons/icon-192x192.png',
  '/icons/icon-384x384.png',
  '/icons/icon-512x512.png'
];

// Installation: Caching App Shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Caching App Shell');
        return cache.addAll(URLS_TO_CACHE);
      })
  );
});

// Activation: Cleaning up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// Fetching: Serving from cache first, then network (Cache-First strategy)
self.addEventListener('fetch', (event) => {
  // Only cache GET requests and non-AJAX POST (like form submissions)
  const isPostRequest = event.request.method === 'POST';
  const isSignatureUpload = event.request.url.includes('upload_signature');

  // Do not cache or intercept POST/AJAX requests for dynamic data/actions
  if (isPostRequest && !isSignatureUpload) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Cache-First Strategy for static files and the main page
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Cache hit - return the cached response
        if (response) {
          return response;
        }
        // No cache match, fetch from network
        return fetch(event.request)
          .then((networkResponse) => {
            // Check if we received a valid response
            if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
              return networkResponse;
            }
            // IMPORTANT: Clone the response. A response is a stream
            const responseToCache = networkResponse.clone();
            
            caches.open(CACHE_NAME)
              .then((cache) => {
                cache.put(event.request, responseToCache);
              });
            
            return networkResponse;
          })
          .catch(() => {
            // Handle offline case for main page if network failed
            if (event.request.mode === 'navigate') {
              // Can serve a simple offline page if needed
            }
          });
      })
  );
});