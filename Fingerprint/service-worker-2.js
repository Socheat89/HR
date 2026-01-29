// Versioned cache names - Increment version when making changes to cached assets
const CACHE_NAME = 'scan-system-v1.0.5'; // Updated version for new changes
const LOCATION_CACHE = 'location-cache-v1.0.5'; // Separate cache for location data (if needed for specific use cases)

// Resources to cache - Ensure these paths are correct relative to the service worker
const urlsToCache = [
    '/', // Root of your application
    'test-f-2.php', // Your main application HTML file
    'manifest-2.json',       // Web app manifest
    'Check-In.mp3',        // Audio file
    'Check-Out.mp3',       // Audio file
    // Add other critical assets your app needs to work offline:
    // For example, your main JavaScript file, CSS files, and any images.
    // 'js/main.js',
    // 'css/style.css',
    // 'images/logo.png',
    // 'face-api.min.js', // If this is used on checkin-out_vvc.html
    // 'models/face_detection_model.json', // If you load models dynamically
];

// Helper function to normalize URLs by removing query parameters
function normalizeUrl(url) {
    try {
        const urlObj = new URL(url, self.location.origin);
        return urlObj.pathname;
    } catch (e) {
        console.error('Invalid URL for normalization:', url, e);
        return url; // Return original if invalid to prevent blocking
    }
}


self.addEventListener('install', event => {
    console.log('Service Worker: Installing', CACHE_NAME);
    // `self.skipWaiting()` forces the new service worker to activate immediately,
    // bypassing the waiting phase, which is useful for rapid updates.
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache:', CACHE_NAME);
                // `Promise.all` ensures all cache operations complete.
                // `Workspace(url, { cache: 'no-cache' })` ensures we get the freshest copy from the network.
                return Promise.all(
                    urlsToCache.map(url => {
                        const normalizedUrl = normalizeUrl(url);
                        return fetch(url, { credentials: 'same-origin', cache: 'no-cache' })
                            .then(response => {
                                if (!response.ok) {
                                    // If a critical asset fails to fetch, log it but don't fail the entire install.
                                    // You might want to throw an error here if a core dependency is truly uncacheable.
                                    throw new Error(`Failed to fetch ${url}: ${response.status} ${response.statusText}`);
                                }
                                console.log(`Caching ${normalizedUrl} successfully`);
                                return cache.put(normalizedUrl, response);
                            })
                            .catch(error => {
                                console.error(`Failed to cache ${url}:`, error);
                                return null; // Allows other cache operations to proceed
                            });
                    })
                ).then(results => {
                    const failed = urlsToCache.filter((_, i) => results[i] === null);
                    if (failed.length > 0) {
                        console.warn('Some resources failed to cache during installation:', failed);
                    }
                    console.log('Service Worker installed and resources cached.');
                });
            })
            .catch(error => {
                console.error('Cache installation failed:', error);
                // Throwing the error here will cause the service worker installation to fail,
                // which might be desired if fundamental resources cannot be cached.
                throw error;
            })
    );
});

---
## **2. Activate Event: Cleaning Up Old Caches**
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating', CACHE_NAME);
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    // Delete any caches that are not the current main cache or location cache.
                    if (cacheName !== CACHE_NAME && cacheName !== LOCATION_CACHE) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
        .then(() => {
            console.log('Service Worker activated, old caches cleaned up.');
            // `self.clients.claim()` allows the active service worker to control
            // clients (pages) immediately upon activation, without requiring a page reload.
            return self.clients.claim();
        })
        .catch(error => console.error('Cache cleanup failed during activation:', error))
    );
});

---
## **3. Fetch Event: Handling Network Requests and Caching Strategy**
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    const normalizedUrl = normalizeUrl(event.request.url);
    // console.log('Service Worker: Fetching:', event.request.url); // Use with caution, can be very verbose

    // Skip non-GET requests unless they are specific API POSTs
    if (event.request.method !== 'GET') {
        // Allow POST requests for API calls to go through the network directly.
        // It's generally not advisable to cache POST requests.
        if (url.pathname.includes('/api/') || url.pathname.includes('/worker/save_log.php')) {
            event.respondWith(fetch(event.request)); // Always try network for POST
        } else {
            // For other non-GET requests (PUT, DELETE, etc.), just pass them through.
            event.respondWith(fetch(event.request));
        }
        return;
    }

    // Network-first strategy for API requests (e.g., your PHP scripts)
    // This ensures you always try to get the latest data from the server.
    if (url.pathname.includes('/api/') || url.pathname.includes('/worker/save_log.php') || url.hostname.includes('nominatim.openstreetmap.org')) {
        event.respondWith(
            fetch(event.request)
                .then(networkResponse => {
                    // Check if the response is valid before caching it (e.g., HTTP 200 OK)
                    // `response.type === 'basic'` ensures it's a same-origin request, safe to cache.
                    if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME) // Could use a separate cache for API data if preferred
                            .then(cache => cache.put(event.request, responseToCache))
                            .catch(error => console.error('API Cache put failed:', error));
                    }
                    return networkResponse;
                })
                .catch(async () => {
                    console.warn('API fetch failed, attempting to serve from cache for:', event.request.url);
                    const cachedResponse = await caches.match(event.request);
                    if (cachedResponse) {
                        console.log('Serving cached API response for:', event.request.url);
                        return cachedResponse;
                    }
                    // Fallback to a cached offline page if no cached API response is available
                    const fallbackPage = await caches.match('test-f-2.php'); // Ensure this page is always cached
                    if (fallbackPage) {
                        return fallbackPage;
                    }
                    // Generic offline response if nothing else works
                    return new Response('Offline: Unable to fetch API data.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
                })
        );
    } else {
        // Cache-first strategy for static assets (HTML, CSS, JS, images, audio)
        // This makes your app load quickly offline.
        event.respondWith(
            caches.match(normalizedUrl)
                .then(cachedResponse => {
                    if (cachedResponse) {
                        // console.log('Serving from cache:', normalizedUrl);
                        return cachedResponse;
                    }
                    // If not in cache, fetch from network and then cache it for future use.
                    return fetch(event.request)
                        .then(networkResponse => {
                            if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                                const responseToCache = networkResponse.clone();
                                caches.open(CACHE_NAME)
                                    .then(cache => cache.put(normalizedUrl, responseToCache))
                                    .catch(error => console.error('Asset Cache put failed:', error));
                            }
                            return networkResponse;
                        })
                        .catch(async () => {
                            console.error('Fetch failed for asset:', event.request.url);
                            // Serve a cached fallback page if an asset can't be fetched or isn't cached.
                            const cachedFallback = await caches.match('test-f-2.php');
                            if (cachedFallback) {
                                return cachedFallback;
                            }
                            return new Response('Offline: Resource not cached.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
                        });
                })
        );
    }
});

---
## **4. Sync Event: Background Data Synchronization**
self.addEventListener('sync', event => {
    // This 'sync-scans' tag is registered by your frontend when data needs to be sent offline.
    if (event.tag === 'sync-scans') {
        console.log('Background sync triggered:', event.tag);
        // `event.waitUntil` keeps the service worker alive until the promise resolves.
        event.waitUntil(syncScans());
    }
});

// `syncScans` function to process and send pending scans from IndexedDB.
async function syncScans() {
    const MAX_RETRIES = 3; // Maximum attempts for each scan
    const BASE_RETRY_DELAY = 2000; // 2 seconds initial delay for exponential backoff

    try {
        const db = await openDB();
        const tx = db.transaction('scanQueue', 'readwrite');
        const store = tx.objectStore('scanQueue');
        const scans = await store.getAll(); // Get all pending scans

        if (scans.length === 0) {
            console.log('No pending scans to sync.');
            await notifyClients('sync-complete', 'ការសមកាលកម្មរួចរាល់: គ្មានទិន្នន័យត្រូវផ្ញើ។');
            return;
        }

        console.log('Syncing', scans.length, 'pending scans...');
        await notifyClients('sync-progress', `កំពុងផ្ញើទិន្នន័យដែលមិនទាន់បានផ្ញើ ${scans.length} កំណត់ត្រា...`);

        const failedScans = [];

        for (const scan of scans) {
            let attempt = 1;
            let success = false;

            while (attempt <= MAX_RETRIES && !success) {
                try {
                    const formData = new FormData();
                    // Append all scan properties to FormData, excluding the IndexedDB `id`.
                    Object.entries(scan).forEach(([key, value]) => {
                        if (key !== 'id') {
                            // Convert objects/arrays to JSON strings if your PHP expects them this way
                            formData.append(key, typeof value === 'object' ? JSON.stringify(value) : value);
                        } else {
                            // You might want to send the original IndexedDB 'id' for server-side logging/tracking
                            formData.append('indexedDbId', value);
                        }
                    });
                    // Add a flag to indicate this is an offline sync, useful for server-side logic.
                    formData.append('is_offline_sync', true);

                    const response = await fetch('/worker/save_log.php', {
                        method: 'POST',
                        body: formData,
                        // Set a timeout for the fetch request to prevent hanging.
                        signal: AbortSignal.timeout(15000) // 15 seconds timeout
                    });

                    if (response.ok) { // Check for HTTP status 200-299
                        const result = await response.json(); // Parse the JSON response from your PHP script
                        if (result.status === 'success') {
                            // If successfully sent to server, delete from IndexedDB queue.
                            const deleteTx = db.transaction('scanQueue', 'readwrite');
                            const deleteStore = deleteTx.objectStore('scanQueue');
                            await deleteStore.delete(scan.id);
                            await deleteTx.done; // Ensure the transaction completes
                            console.log('Synced and deleted scan:', scan.id);
                            success = true; // Mark as successful to exit retry loop
                        } else {
                            // If server responds with an error status (e.g., validation failure), throw it.
                            throw new Error(`Server response error for scan ${scan.id}: ${result.message || JSON.stringify(result)}`);
                        }
                    } else {
                        // If HTTP response itself indicates an error (e.g., 404, 500)
                        throw new Error(`Network response error for scan ${scan.id}: ${response.status} ${response.statusText}`);
                    }
                } catch (error) {
                    console.error(`Sync attempt ${attempt} failed for scan ${scan.id}:`, error);

                    // Determine if the error is transient (network issues) or permanent (server rejection).
                    // Transient errors (network down, timeout) should trigger a retry by the browser.
                    if (error.name === 'AbortError' || (error instanceof TypeError && error.message === 'Failed to fetch')) {
                        // Re-throw the error to indicate to the browser that the sync event failed.
                        // The browser will then schedule a retry for the 'sync-scans' event later.
                        console.warn('Re-throwing error to trigger browser retry for sync event:', error.message);
                        throw error;
                    } else {
                        // For permanent errors (e.g., server validation error 4xx/5xx that's not a network issue),
                        // add to failed list and stop retrying this specific scan.
                        failedScans.push({ id: scan.id, error: error.message });
                        break; // Exit retry loop for this specific scan
                    }
                }
            }
        }

        // After processing all scans, check remaining items in IndexedDB.
        const remainingScans = await store.getAll();
        if (remainingScans.length === 0) {
            await notifyClients('sync-complete', 'ការសមកាលកម្មរួចរាល់: ទិន្នន័យទាំងអស់បានផ្ញើ។');
            // Show a native push notification to the user
            self.registration.showNotification('ការសមកាលកម្មរួចរាល់', {
                body: 'ទិន្នន័យវត្តមានរបស់អ្នកទាំងអស់ត្រូវបានផ្ញើទៅ server ។',
                icon: '/icons/icon-192x192.png', // Ensure you have an icon at this path
                badge: '/icons/badge.png' // Optional badge icon
            });
        } else {
            const message = `ការសមកាលកម្មមិនពេញលេញ: នៅសល់ ${remainingScans.length} កំណត់ត្រា។`;
            await notifyClients('sync-partial', message);
            self.registration.showNotification('ការសមកាលកម្មមិនពេញលេញ', {
                body: message,
                icon: '/icons/icon-192x192.png'
            });
            // If there are remaining items due to transient errors (that were re-thrown),
            // the browser will automatically reschedule the sync event.
            // If they are due to permanent errors, they will stay in the queue.
        }
    } catch (error) {
        console.error('Overall syncScans function error:', error);
        await notifyClients('sync-error', `កំហុសការសមកាលកម្ម: ${error.message}`);
        self.registration.showNotification('កំហុសការសមកាលកម្ម', {
            body: `មានបញ្ហាក្នុងការផ្ញើទិន្នន័យ: ${error.message}. សូមព្យាយាមម្តងទៀត។`,
            icon: '/icons/icon-192x192.png'
        });
        // Important: Re-throw the error to ensure the browser schedules a retry for the sync event.
        throw error;
    }
}

// Function to send messages back to open client pages
async function notifyClients(type, message, payload = {}) {
    const clients = await self.clients.matchAll({ includeUncontrolled: true });
    if (clients.length === 0) {
        console.warn('No clients available to notify:', type, message);
        return;
    }
    for (const client of clients) {
        client.postMessage({ type, message, payload });
    }
}

---
## **5. IndexedDB Helper Function**
// Open IndexedDB - Enhanced with proper Promise handling and versioning
function openDB() {
    return new Promise((resolve, reject) => {
        // Increment the version number whenever you change the database schema (e.g., add/remove object stores).
        const request = indexedDB.open('ScanSystemDB', 2); // Version 2

        request.onupgradeneeded = event => {
            const db = event.target.result;
            const oldVersion = event.oldVersion;

            console.log(`IndexedDB upgrade needed from version ${oldVersion} to ${db.version}`);

            // Create 'scanQueue' store if it doesn't exist (for version 1 or less)
            if (!db.objectStoreNames.contains('scanQueue')) {
                db.createObjectStore('scanQueue', { keyPath: 'id', autoIncrement: true });
                console.log('Created object store: scanQueue');
            }

            // Create new stores for version 2 (or if upgrading from 1)
            if (oldVersion < 2) {
                if (!db.objectStoreNames.contains('loggedInUser')) {
                    db.createObjectStore('loggedInUser', { keyPath: 'key' });
                    console.log('Created object store: loggedInUser');
                }
                if (!db.objectStoreNames.contains('lastState')) {
                    db.createObjectStore('lastState', { keyPath: 'key' });
                    console.log('Created object store: lastState');
                }
                if (!db.objectStoreNames.contains('lastScanType')) {
                    db.createObjectStore('lastScanType', { keyPath: 'key' });
                    console.log('Created object store: lastScanType');
                }
                if (!db.objectStoreNames.contains('addressCache')) {
                    db.createObjectStore('addressCache', { keyPath: 'key' });
                    console.log('Created object store: addressCache');
                }
                if (!db.objectStoreNames.contains('lastLocation')) {
                    db.createObjectStore('lastLocation', { keyPath: 'key' });
                    console.log('Created object store: lastLocation');
                }
            }
        };

        request.onsuccess = event => resolve(event.target.result);
        request.onerror = event => reject(event.target.error);
    });
}

---
## **6. Message Event: Communication Between Client and Service Worker**
self.addEventListener('message', async event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        console.log('Received SKIP_WAITING message from client, skipping waiting.');
        self.skipWaiting(); // Force activation
    } else if (event.data && event.data.type === 'REQUEST_LOCATION') {
        // Service Worker cannot directly request geolocation from the user due to security/privacy.
        // The client-side (main script) should handle obtaining location and then send it to the SW.
        console.warn('Service Worker received REQUEST_LOCATION. Geolocation must be obtained by the client script.');
    } else if (event.data && event.data.type === 'LOCATION_DATA') {
        // This is the expected way to receive location data from the client.
        console.log('Received LOCATION_DATA from client:', event.data.payload);
        const { latitude, longitude, timestamp, address } = event.data.payload;
        const locationData = { latitude, longitude, timestamp, address };

        // Store the latest location in IndexedDB for persistence.
        try {
            const db = await openDB();
            const tx = db.transaction('lastLocation', 'readwrite');
            const store = tx.objectStore('lastLocation');
            await store.put({ key: 'currentLocation', value: locationData }); // Use a fixed key to store the single latest location
            await tx.done;
            console.log('Stored latest location in IndexedDB:', locationData);
        } catch (error) {
            console.error('Failed to store location in IndexedDB:', error);
        }

        // Notify all open client pages about the updated location (e.g., to update UI).
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'LOCATION_UPDATED_IN_SW', // Custom type to indicate SW processed the location
                payload: locationData
            });
        });
    }
});

// Polyfill for `AbortSignal.timeout` for broader browser compatibility
if (!AbortSignal.timeout) {
    AbortSignal.timeout = function (milliseconds) {
        const controller = new AbortController();
        setTimeout(() => controller.abort(new DOMException('TimeoutError', 'TimeoutError')), milliseconds);
        return controller.signal;
    };
}