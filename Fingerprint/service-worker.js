// service-worker.js

/**
 * Service Worker នេះ​ដើរ​តួ​ជា​ខួរក្បាល​សម្រាប់ Progressive Web App (PWA) របស់​អ្នក។
 * មុខងារ​ចម្បង​របស់​វា​គឺ៖
 * 1. Caching: រក្សាទុក​ไฟล์​សំខាន់ៗ (HTML, CSS, JS, រូបភាព, សំឡេង) ក្នុង​ទូរស័ព្ទ ដើម្បី​ឲ្យ App បើក​បាន​លឿន និង​អាច​ដំណើរការ​ពេល​គ្មាន​អ៊ីនធឺណិត (Offline)។
 * 2. Offline Access: ចាប់​យក​រាល់​សំណើ (requests) ទៅ​កាន់​បណ្ដាញ ហើយ​ឆ្លើយតប​មក​វិញ​ជាមួយ​នឹង​ទិន្នន័យ​ពី Cache ប្រសិន​បើ​គ្មាន​អ៊ីនធឺណិត។
 * 3. Background Sync: នៅ​ពេល​អ្នក​ប្រើប្រាស់​ស្កេន​ពេល Offline, ទិន្នន័យ​នឹង​ត្រូវ​បាន​រក្សា​ទុក​ក្នុង IndexedDB។ នៅ​ពេល​មាន​អ៊ីនធឺណិត​វិញ Service Worker នឹង​ព្យាយាម​ផ្ញើ​ទិន្នន័យ​នោះ​ទៅ Server ដោយ​ស្វ័យប្រវត្តិ​នៅ​ Background។
 * 4. Cache Management: សម្អាត Cache ចាស់ៗ​ដោយ​ស្វ័យប្រវត្តិ​នៅ​ពេល​មាន​កំណែ​ថ្មី (version) នៃ App។
 */

// កំណត់​ឈ្មោះ និង​ជំនាន់​របស់ Cache។ ត្រូវ​ដំឡើង​លេខ​ជំនាន់​រាល់​ពេល​មាន​ការ​ផ្លាស់ប្ដូរ​ไฟล์​ក្នុង urlsToCache។
const CACHE_NAME = 'scan-system-v2.0.3'; // បាន​ធ្វើបច្ចុប្បន្នភាព​ជំនាន់​ដើម្បី​ឲ្យ​មាន​ការ​ដំឡើង​សារ​ជា​ថ្មី
const LOCATION_CACHE = 'location-cache-v1.0.5';

// បញ្ជី​ไฟล์​សំខាន់ៗ​ដែល​ត្រូវ​រក្សាទុក (cache) សម្រាប់​ការ​ប្រើប្រាស់​ពេល Offline
const urlsToCache = [
    '/',
    '/Fingerprint/checkin-out_vvc.html', // ត្រូវ​ប្រាកដ​ថា​ path នេះ​ត្រឹមត្រូវ
    '/Fingerprint/manifest.json',
    '/Fingerprint/global-fingerprint.js',
    'Check-In.mp3',
    'Check-Out.mp3',
    // អ្នក​អាច​បន្ថែម​ไฟล์ CSS, JS, ឬ​រូបភាព​សំខាន់ៗ​ផ្សេងទៀត​នៅ​ទីនេះ
    // ឧទាហរណ៍: 'js/main.js', 'css/style.css', 'images/logo.png'
];

// ---
// ## 1. Install Event: ការរក្សាទុក Asset សំខាន់ៗ (Caching)
// ---
self.addEventListener('install', event => {
    console.log(`Service Worker: កំពុង​ដំឡើង​ជំនាន់ ${CACHE_NAME}`);
    
    // self.skipWaiting() បង្ខំ​ឲ្យ Service Worker ថ្មី​ចូល​កាន់​កាប់ (activate) ភ្លាមៗ​ដោយ​មិន​ចាំបាច់​រង់ចាំ។
    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log(`បាន​បើក cache: ${CACHE_NAME}`);
                
                // ប្រើ fetch ជាមួយ cache: 'no-cache' ដើម្បី​ធានា​ថា​យើង​ទាញ​យក​ไฟล์​ថ្មី​បំផុត​ពី network មក​ដាក់​ក្នុង cache។
                const cachePromises = urlsToCache.map(url => {
                    return fetch(url, { cache: 'no-cache' })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`ล้มเหลวในการดึง ${url}: ${response.status}`);
                            }
                            console.log(`Caching สำเร็จ: ${url}`);
                            return cache.put(url, response);
                        })
                        .catch(error => {
                            console.error(`ล้มเหลวในการ cache ${url}:`, error);
                        });
                });
                return Promise.all(cachePromises);
            })
            .then(() => {
                console.log('Service Worker បាន​ដំឡើង និង​រក្សាទុក​ធនធាន​រួចរាល់។');
            })
            .catch(error => {
                console.error('ការ​ដំឡើង Cache បាន​បរាជ័យ:', error);
            })
    );
});


// ---
// ## 2. Activate Event: ការសម្អាត Cache ចាស់ៗ
// ---
self.addEventListener('activate', event => {
    console.log(`Service Worker: កំពុង​ដំណើរការ (Activating) ជំនាន់ ${CACHE_NAME}`);
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    // លុប Cache ណា​ដែល​មិនមែន​ជា​ជំនាន់​បច្ចុប្បន្ន
                    if (cacheName !== CACHE_NAME && cacheName !== LOCATION_CACHE) {
                        console.log('កំពុង​លុប cache ចាស់:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
        .then(() => {
            console.log('Service Worker បាន​ដំណើរការ និង​សម្អាត cache ចាស់ៗ​រួចរាល់។');
            // self.clients.claim() អនុញ្ញាត​ឲ្យ Service Worker គ្រប់គ្រង​หน้าเว็บ​ទាំងអស់​ភ្លាមៗ​ដោយ​មិន​បាច់ reload។
            return self.clients.claim();
        })
        .catch(error => console.error('ការសម្អាត Cache បាន​បរាជ័យ:', error))
    );
});


// ---
// ## 3. Fetch Event: ការគ្រប់គ្រង​សំណើ Network និង​យុទ្ធសាស្ត្រ Caching
// ---
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // រំលង​សំណើ​ដែល​មិនមែន​ជា GET (เช่น POST, PUT) ព្រោះ​យើង​មិន cache វា
    if (event.request.method !== 'GET') {
        // អនុញ្ញាត​ឲ្យ​សំណើ POST ទៅ​កាន់ API ឆ្លងកាត់​ទៅ network ដោយ​ផ្ទាល់
        if (url.pathname.includes('api.php') || url.pathname.includes('save_log.php')) {
            event.respondWith(fetch(event.request));
        }
        return;
    }

    // យុទ្ធសាស្ត្រទី១: "Network First" สำหรับ API Data (ទិន្នន័យ​ពី Server)
    // ព្យាយាម​ទៅ Network មុន, បើ​បរាជ័យ (Offline) ទើប​យក​ពី Cache។ នេះ​ធានា​ថា​អ្នក​ប្រើប្រាស់​បាន​ទិន្នន័យ​ថ្មី​ជានិច្ច។
    if (url.pathname.includes('api.php') || url.pathname.includes('logs.php') || url.hostname.includes('nominatim.openstreetmap.org')) {
        event.respondWith(
            fetch(event.request)
                .then(networkResponse => {
                    // បើ​ជោគជ័យ, រក្សាទុក​ចម្លើយ​ថ្មី​ទៅ​ក្នុង Cache
                    if (networkResponse && networkResponse.status === 200) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseToCache));
                    }
                    return networkResponse;
                })
                .catch(async () => {
                    // បើ​បរាជ័យ (Offline), ព្យាយាម​រក​មើល​ក្នុង Cache
                    console.warn(`API fetch ล้มเหลว, กำลังลองจาก cache: ${event.request.url}`);
                    const cachedResponse = await caches.match(event.request);
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // បើ​គ្មាន​ក្នុង Cache ដែរ, បង្ហាញ​សារ Offline
                    return new Response(JSON.stringify({ status: 'offline', message: 'អ្នក​កំពុង​នៅ​ក្រៅ​បណ្ដាញ ហើយ​ទិន្នន័យ​នេះ​មិន​មាន​ក្នុង cache ទេ។' }), {
                        headers: { 'Content-Type': 'application/json' },
                        status: 503
                    });
                })
        );
    } else {
        // យុទ្ធសាស្ត្រទី២: "Cache First" สำหรับ Static Assets (HTML, CSS, JS, រូបភាព, សំឡេង)
        // ពិនិត្យ​មើល​ក្នុង Cache មុន, បើ​មាន​គឺ​ប្រើ​ភ្លាមៗ (លឿន)។ បើ​មិន​មាន ទើប​ទៅ Network។
        event.respondWith(
            caches.match(event.request)
                .then(cachedResponse => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    
                    // បើ​មិន​មាន​ក្នុង Cache, ទៅ​យក​ពី Network ហើយ​រក្សាទុក​សម្រាប់​លើក​ក្រោយ
                    return fetch(event.request).then(networkResponse => {
                        if (networkResponse && networkResponse.status === 200) {
                             const responseToCache = networkResponse.clone();
                             caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseToCache));
                        }
                        return networkResponse;
                    });
                })
                .catch(async () => {
                     // បើ​បរាជ័យ​ទាំង​អស់, ព្យាយាម​បង្ហាញ​ទំព័រ Offline หลัก
                     const fallbackPage = await caches.match('/Fingerprint/checkin-out_vvc.html');
                     if(fallbackPage) return fallbackPage;
                     // បើ​សូម្បី​តែ​ទំព័រ Offline ក៏​រក​មិន​ឃើញ​ដែរ
                     return new Response('អ្នក​កំពុង​នៅ​ក្រៅ​បណ្ដាញ ហើយ​ធនធាន​នេះ​មិន​អាច​រក​ឃើញ​ទេ។', { status: 404, headers: { 'Content-Type': 'text/plain' } });
                })
        );
    }
});


// ---
// ## 4. Sync Event: ការធ្វើ​សមកាលកម្ម​ទិន្នន័យ​ពេល Offline (Background Data Synchronization)
// ---
self.addEventListener('sync', event => {
    // ពិនិត្យ​មើល "tag" ដែល​បាន​ចុះ​ឈ្មោះ​ពី​ frontend
    if (event.tag === 'sync-scans') {
        console.log('Background sync បាន​កេះ (triggered):', event.tag);
        // event.waitUntil រក្សា​ឲ្យ Service Worker បន្ត​ដំណើរការ​រហូត​ដល់​ការ sync ចប់
        event.waitUntil(syncScans());
    }
});

async function syncScans() {
    try {
        const db = await openDB();
        const tx = db.transaction('scanQueue', 'readonly');
        const store = tx.objectStore('scanQueue');
        const scans = await store.getAll();

        if (scans.length === 0) {
            console.log('គ្មាន​ទិន្នន័យ​រង់ចាំ​ការ sync ទេ។');
            return;
        }

        console.log(`កំពុង sync ទិន្នន័យ​ដែល​មិន​ទាន់​បាន​ផ្ញើចំនួន ${scans.length} កំណត់ត្រា...`);
        await notifyClients('sync-progress', `កំពុង​ផ្ញើ​ទិន្នន័យ ${scans.length} កំណត់ត្រា...`);

        // ប្រើ Promise.all ដើម្បី​ដំណើរការ​ការ​ផ្ញើ​ស្រប​គ្នា
        const syncPromises = scans.map(async (scan) => {
            try {
                // បំប្លែង object ពី IndexedDB ទៅជា FormData
                const formData = new FormData();
                Object.entries(scan).forEach(([key, value]) => {
                    if (key !== 'id') { // មិន​បាច់​ផ្ញើ ID របស់ IndexedDB
                         formData.append(key, value);
                    }
                });
                formData.append('is_offline_sync', 'true'); // បន្ថែម​ flag ដើម្បី​ឲ្យ Server ដឹង

                const response = await fetch('/worker/save_log.php', {
                    method: 'POST',
                    body: formData,
                    signal: AbortSignal.timeout(15000) // កំណត់ timeout ១៥ វិនាទី
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.status === 'success') {
                        // បើ​ផ្ញើ​ជោគជ័យ, លុប​ចេញ​ពី queue
                        const deleteTx = db.transaction('scanQueue', 'readwrite');
                        await deleteTx.objectStore('scanQueue').delete(scan.id);
                        await deleteTx.done;
                        console.log(`បាន Sync និង​លុប​កំណត់ត្រា ID: ${scan.id}`);
                    } else {
                        throw new Error(`Server បដិសេធ: ${result.message}`);
                    }
                } else {
                    // បើ​មាន​បញ្ហា HTTP (404, 500)
                    throw new Error(`បញ្ហា Network: ${response.status} ${response.statusText}`);
                }
            } catch (error) {
                console.error(`Sync សម្រាប់ ID ${scan.id} បាន​បរាជ័យ:`, error);
                // **សំខាន់:** បោះ error នេះ​បន្ត​ទៀត ដើម្បី​ប្រាប់​ browser ថា sync លើក​នេះ​មិន​ជោគជ័យ
                // browser នឹង​ព្យាយាម​កេះ (trigger) sync event នេះ​ម្ដង​ទៀត​នៅ​ពេល​ក្រោយ​ដោយ​ស្វ័យប្រវត្តិ។
                throw error;
            }
        });

        await Promise.all(syncPromises);

        // ក្រោយ​ពី​ដំណើរការ​រួច, ពិនិត្យ​មើល​ថា​តើ​នៅ​សល់​អ្វី​ក្នុង queue ដែរ​ឬ​ទេ
        const remainingScans = await db.transaction('scanQueue', 'readonly').objectStore('scanQueue').getAll();
        if (remainingScans.length === 0) {
            console.log('ការ Sync បាន​បញ្ចប់​ដោយ​ជោគជ័យ! ទិន្នន័យ​ទាំងអស់​ត្រូវ​បាន​ផ្ញើ។');
            await notifyClients('sync-complete', 'ការ​សមកាលកម្ម​បាន​បញ្ចប់​ដោយ​ជោគជ័យ!');
            self.registration.showNotification('ការ​សមកាលកម្ម​រួចរាល់', {
                body: 'ទិន្នន័យ​វត្តមាន​របស់​អ្នក​ត្រូវ​បាន​ផ្ញើ​ទៅ​កាន់ server ដោយ​ជោគជ័យ។',
                icon: '/icons/icon-192x192.png', // ត្រូវ​ប្រាកដ​ថា​មាន​រូប​ icon
            });
        }
        
    } catch (error) {
        console.error('ដំណើរការ syncScans ទាំងមូល​បាន​បរាជ័យ:', error);
        await notifyClients('sync-error', `ការ​សមកាលកម្ម​បាន​បរាជ័យ: ${error.message}`);
        self.registration.showNotification('ការ​សមកាលកម្ម​បាន​បរាជ័យ', {
            body: `មាន​បញ្ហា​ក្នុង​ការ​ផ្ញើ​ទិន្នន័យ។ ប្រព័ន្ធ​នឹង​ព្យាយាម​ម្ដង​ទៀត​នៅ​ពេល​ក្រោយ។`,
            icon: '/icons/icon-192x192.png',
        });
        // បោះ error នេះ​បន្ត​ទៀត ដើម្បី​ធានា​ថា browser នឹង​ retry
        throw error;
    }
}


// ---
// ## 5. Message Event & Helpers: ការ​ប្រាស្រ័យ​ទាក់ទង និង​មុខងារ​ជំនួយ
// ---

// មុខងារ​ជំនួយ​សម្រាប់​ផ្ញើ​សារ​ទៅ​កាន់ client (หน้าเว็บ​ដែល​កំពុង​បើក)
async function notifyClients(type, message) {
    const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
    clients.forEach(client => {
        client.postMessage({ type, message });
    });
}

// មុខងារ​ជំនួយ​សម្រាប់​បើក IndexedDB
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('ScanSystemDB', 2); // ត្រូវ​ប្រាកដ​ថា version នេះ​ដូច​គ្នា​នឹង​ main script
        request.onupgradeneeded = event => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('scanQueue')) {
                db.createObjectStore('scanQueue', { keyPath: 'id', autoIncrement: true });
            }
            // បន្ថែម​ការ​បង្កើត stores ផ្សេងទៀត​បើ​ចាំបាច់
        };
        request.onsuccess = event => resolve(event.target.result);
        request.onerror = event => reject(event.target.error);
    });
}

// ទទួល​សារ​ពី client
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// Polyfill សម្រាប់ AbortSignal.timeout
if (!AbortSignal.timeout) {
    AbortSignal.timeout = function (milliseconds) {
        const controller = new AbortController();
        setTimeout(() => controller.abort(new DOMException('TimeoutError', 'TimeoutError')), milliseconds);
        return controller.signal;
    };
}