const CACHE_NAME = 'barcode-scanner-cache-v1.2'; // ដំឡើង Version ថ្មី ដើម្បី​ឱ្យ SW Update
const CORE_ASSETS = [
    '/',
    'index.php',
    'manifest.json',
    'https://cdn-icons-png.flaticon.com/512/5393/5393325.png',
    'https://cdn-icons-png.flaticon.com/512/5393/5393325.png',
    'https://cdn.tailwindcss.com',
    'https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap',
    'https://unpkg.com/@zxing/library@latest/umd/index.min.js'
    // សំឡេង Beep ក៏​គួរ​តែ​ត្រូវ​បាន Cache ដែរ
    // 'data:audio/wav;base64,...' មិន​អាច​ cache បាន​ដោយ​ផ្ទាល់, ប៉ុន្តែ​ដោយ​សារ​វា​ជា inline data, វា​ត្រូវ​បាន​រួម​បញ្ចូល​ក្នុង index.html រួច​ហើយ
];

// ជំហាន Install: បើក Cache និង​ដាក់​ Core Assets ចូល
self.addEventListener('install', event => {
    console.log('[Service Worker] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[Service Worker] Caching core assets');
                return cache.addAll(CORE_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

// ជំហាន Activate: លុប Cache ចាស់ៗ​ដែល​លែង​ប្រើ​ចោល
self.addEventListener('activate', event => {
    console.log('[Service Worker] Activating...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// ជំហាន Fetch: ស្ទាក់​ចាប់​រាល់ Request ហើយ​អនុវត្ត​យុទ្ធសាស្ត្រ "Cache First, Falling Back to Network"
self.addEventListener('fetch', event => {
    // យើង​មិន​ Cache Request ដែល​មិន​មែន​ជា GET
    if (event.request.method !== 'GET') {
        return;
    }

    // យុទ្ធសាស្ត្រ​សម្រាប់​ការ​ស្នើ​សុំ​ទាំង​អស់ (រួម​ទាំង cross-origin)
    event.respondWith(
        caches.open(CACHE_NAME).then(cache => {
            return cache.match(event.request).then(cachedResponse => {
                // បង្កើត Promise សម្រាប់​ការ​ទៅ​យក​ពី Network
                const networkFetch = fetch(event.request).then(networkResponse => {
                    // ពិនិត្យ​មើល​ថា​តើ response ត្រឹមត្រូវ​ឬ​អត់ មុន​នឹង​ cache
                    if (networkResponse.ok) {
                        cache.put(event.request, networkResponse.clone());
                    }
                    return networkResponse;
                }).catch(err => {
                    console.error('[Service Worker] Network fetch failed:', err);
                });

                // បើ​មាន​ក្នុង Cache, យក​ពី Cache មក​ប្រើ​ភ្លាម។ បើ​មិន​មាន, រង់ចាំ​លទ្ធផល​ពី Network។
                return cachedResponse || networkFetch;
            });
        })
    );
});