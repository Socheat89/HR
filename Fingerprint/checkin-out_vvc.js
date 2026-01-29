  /**
         * Show feedback star and comment popup after scan is completed.
         * Only show once per day per user.
         */
        // function showFeedbackPopup(onSubmit) {
        //     // Remove existing popup if any
        //     let old = document.getElementById('feedbackPopup');
        //     if (old) old.remove();

        //     const popup = document.createElement('div');
        //     popup.id = 'feedbackPopup';
        //     popup.style.position = 'fixed';
        //     popup.style.top = '0';
        //     popup.style.left = '0';
        //     popup.style.width = '100vw';
        //     popup.style.height = '100vh';
        //     popup.style.background = 'rgba(44,62,80,0.18)';
        //     popup.style.zIndex = '3000';
        //     popup.style.display = 'flex';
        //     popup.style.justifyContent = 'center';
        //     popup.style.alignItems = 'center';
        //     popup.innerHTML = `
        //   <div style="background:#fff;border-radius:1.2rem;box-shadow:0 8px 30px rgba(0,0,0,0.15);padding:2rem 1.2rem 1.5rem 1.2rem;max-width:95vw;width:350px;text-align:center;animation:popupFadeIn 0.3s;">
        //     <h3 style="font-size:1.25rem;color:#4A90E2;margin-bottom:1rem;">វាយតម្លៃបទពិសោធន៍ប្រើប្រាស់</h3>
        //     <div id="feedbackStars" style="font-size:2.2rem; margin-bottom:1rem; display:flex; justify-content:center; gap:0.3rem;">
        //       <span data-star="1" style="cursor:pointer;">☆</span>
        //       <span data-star="2" style="cursor:pointer;">☆</span>
        //       <span data-star="3" style="cursor:pointer;">☆</span>
        //       <span data-star="4" style="cursor:pointer;">☆</span>
        //       <span data-star="5" style="cursor:pointer;">☆</span>
        //     </div>
        //     <textarea id="feedbackComment" rows="3" style="width:100%;border-radius:0.7rem;border:1px solid #e0e0e0;padding:0.7rem;margin-bottom:1rem;font-size:1rem;" placeholder="សូមបញ្ចេញមតិ/សំណើរ..."></textarea>
        //     <div style="display:flex;gap:0.7rem;">
        //       <button id="feedbackSubmit" class="btn btn-primary" style="flex:1;">បញ្ជូន</button>
        //       <button id="feedbackSkip" class="btn btn-secondary" style="flex:1;">រំលង</button>
        //     </div>
        //   </div>
        // `;
        //     document.body.appendChild(popup);

        //     let selectedStar = 0;
        //     const stars = popup.querySelectorAll('#feedbackStars span');
        //     stars.forEach(star => {
        //     star.addEventListener('mouseenter', function () {
        //         const val = parseInt(this.dataset.star);
        //         stars.forEach((s, i) => s.textContent = i < val ? '★' : '☆');
        //     });
        //     star.addEventListener('mouseleave', function () {
        //         stars.forEach((s, i) => s.textContent = i < selectedStar ? '★' : '☆');
        //     });
        //     star.addEventListener('click', function () {
        //         selectedStar = parseInt(this.dataset.star);
        //         stars.forEach((s, i) => s.textContent = i < selectedStar ? '★' : '☆');
        //     });
        //     });

        //     popup.querySelector('#feedbackSubmit').onclick = function () {
        //     const comment = popup.querySelector('#feedbackComment').value.trim();
        //     if (selectedStar === 0) {
        //         stars.forEach(s => s.style.color = '#e74c3c');
        //         setTimeout(() => stars.forEach(s => s.style.color = ''), 600);
        //         return;
        //     }
        //     if (typeof onSubmit === 'function') onSubmit(selectedStar, comment);
        //     popup.remove();
        //     };
        //     popup.querySelector('#feedbackSkip').onclick = function () {
        //     popup.remove();
        //     };
        //     // Allow closing by clicking outside
        //     popup.onclick = function (e) {
        //     if (e.target === popup) popup.remove();
        //     };
        // }

        // // Save feedback to backend (async, non-blocking) and notify Telegram bot directly
        // async function submitFeedback(star, comment) {
        //     const user = await getFromDB('loggedInUser', 'user');
        //     try {
        //     await fetch('/worker/feedback.php', {
        //         method: 'POST',
        //         headers: { 'Content-Type': 'application/json' },
        //         body: JSON.stringify({
        //         user_id: user ? user.id : '',
        //         username: user ? user.username : '',
        //         star: star,
        //         comment: comment,
        //         time: new Date().toISOString()
        //         })
        //     });
        //     } catch (e) {
        //     // Ignore feedback errors
        //     }
        //     // Notify Telegram bot directly using bot token
        //     try {
        //     // Use your actual bot token and chat ID here
        //     const BOT_TOKEN = '8132165664:AAE5sE2HBg6P0IyIoM8xYhSFuBzHumUWK5o';
        //     const CHAT_ID = '-4757352988';
        //     const msg = `⭐️ [Feedback]\nឈ្មោះ: ${user ? user.username : ''}\nID: ${user ? user.id : ''}\nRating: ${star} / 5\nមតិ: ${comment || '(គ្មាន)'}\nTime: ${new Date().toLocaleString('en-US', { hour12: false })}`;
        //     await fetch(`https://api.telegram.org/bot${BOT_TOKEN}/sendMessage`, {
        //         method: 'POST',
        //         headers: { 'Content-Type': 'application/json' },
        //         body: JSON.stringify({
        //         chat_id: CHAT_ID,
        //         text: msg,
        //         parse_mode: 'Markdown'
        //         })
        //     });
        //     } catch (e) {
        //     // Ignore Telegram errors
        //     }
        //     // Save feedback marker to localStorage (per user, forever until clear browser)
        //     if (user && user.id) {
        //     const key = `feedback_submitted_${user.id}`;
        //     localStorage.setItem(key, '1');
        //     }
        // }

        // // Hook feedback popup after scan
        // const originalSubmitScan = submitScan;
        // submitScan = async function (formData, date, time, location, address, scanStatus, lateMinutes = 0) {
        //     await originalSubmitScan.apply(this, arguments);
        //     // Only show feedback popup if not submitted before for this user
        //     const user = await getFromDB('loggedInUser', 'user');
        //     if (user && user.id) {
        //     const key = `feedback_submitted_${user.id}`;
        //     if (!localStorage.getItem(key)) {
        //         setTimeout(() => {
        //         showFeedbackPopup(async (star, comment) => {
        //             await submitFeedback(star, comment);
        //             showStatus(elements.statusEl, 'អរគុណសម្រាប់មតិយោបល់របស់អ្នក!', 'success');
        //         });
        //         }, 500);
        //     }
        //     }
        // };




        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
            // Always show scan section instantly in PWA mode
            window.addEventListener('DOMContentLoaded', async () => {
                await openDB();
                const loggedInUser = await getFromDB('loggedInUser', 'user');
                if (loggedInUser && loggedInUser.token) {
                    scanSection.style.display = 'block';
                    loginSection.style.display = 'none';
                    showScanSection(loggedInUser);
                }
            });
        }
        window.addEventListener('DOMContentLoaded', async () => {
            // Always show scan section instantly in PWA mode, even if slow to load
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                await openDB();
                const loggedInUser = await getFromDB('loggedInUser', 'user');
                if (loggedInUser && loggedInUser.token) {
                    scanSection.style.display = 'block';
                    loginSection.style.display = 'none';
                    showScanSection(loggedInUser);
                }
            }
        });






        // Fallback for browsers that do not support meta[name=theme-color]
        if (!('theme-color' in document.createElement('meta'))) {
            document.body.style.backgroundColor = '#3b5998';
        }



        /**
         * Auto check for missed scans and send Telegram notification if any scan is missing for today.
         * This runs every 10 minutes and on page load.
         */
        async function checkAndNotifyMissedScans() {
            const user = await getFromDB('loggedInUser', 'user');
            if (!user || !user.id) return;

            // Get today's date in yyyy-mm-dd
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const todayStr = `${yyyy}-${mm}-${dd}`;

            // Fetch today's scan logs for this user from /worker/logs.php (expects JSON)
            let logs = [];
            try {
                const resp = await fetch(`/worker/logs.php?username=${encodeURIComponent(user.username)}&id=${encodeURIComponent(user.id)}&date=${todayStr}&json=1`);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.status === 'success' && Array.isArray(data.logs)) {
                        logs = data.logs;
                    }
                }
            } catch (e) {
                // If offline or error, skip
                return;
            }

            // Fetch user time settings (scan schedule)
            const timeSettings = await fetchUserTimeSettings(user.id);
            if (!timeSettings) return;

            // Build a map of scan type -> array of times
            const scanMap = {};
            logs.forEach(log => {
                if (!scanMap[log.scan_type]) scanMap[log.scan_type] = [];
                scanMap[log.scan_type].push(log.time);
            });

            // For each required scan (from timeSettings), check if missing
            const missedScans = [];
            function isScanDone(scanType, ranges) {
                if (!scanMap[scanType]) return false;
                // Check if any scan time falls within any allowed range
                return scanMap[scanType].some(scanTime => {
                    const [h, m] = scanTime.split(':').map(Number);
                    const scanMinutes = h * 60 + m;
                    return ranges.some(range => {
                        let start = typeof range.start === 'string' ? parseTimeToMinutes(range.start) : range.start;
                        let end = typeof range.end === 'string' ? parseTimeToMinutes(range.end) : range.end;
                        return scanMinutes >= start && scanMinutes <= end;
                    });
                });
            }

            // Check Check-In
            if (Array.isArray(timeSettings.check_in_ranges)) {
                timeSettings.check_in_ranges.forEach(range => {
                    if (!isScanDone('Check-In', [range])) {
                        missedScans.push({
                            type: 'Check-In',
                            time: typeof range.start === 'number' ? `${String(Math.floor(range.start / 60)).padStart(2, '0')}:${String(range.start % 60).padStart(2, '0')}` : range.start,
                        });
                    }
                });
            }
            // Check Check-Out
            if (Array.isArray(timeSettings.check_out_ranges)) {
                timeSettings.check_out_ranges.forEach(range => {
                    if (!isScanDone('Check-Out', [range])) {
                        missedScans.push({
                            type: 'Check-Out',
                            time: typeof range.start === 'number' ? `${String(Math.floor(range.start / 60)).padStart(2, '0')}:${String(range.start % 60).padStart(2, '0')}` : range.start,
                        });
                    }
                });
            }

            // Only notify if missed and not already notified today (avoid spam)
            if (missedScans.length > 0) {
                const notifiedKey = `missedScanNotified_${user.id}_${todayStr}`;
                if (localStorage.getItem(notifiedKey) === '1') return;
                localStorage.setItem(notifiedKey, '1');

                // Build Telegram message
                let msg = `⚠️ [Forget] ស្កេនបាត់បង់\nឈ្មោះ: ${user.username}\nថ្ងៃ: ${todayStr}\n`;
                missedScans.forEach(m => {
                    msg += `\n🕒 ${m.type} (${m.time}) [Forget]`;
                });
                msg += `\nID: ${user.id}\nDepartment: ${user.department}\nPosition: ${user.position}\nWorkplace: ${user.workplace}`;

                // Send Telegram
                sendToTelegram(msg).catch(() => { });
            }
        }

        // Run on page load and every 10 minutes
        window.addEventListener('load', checkAndNotifyMissedScans);
        setInterval(checkAndNotifyMissedScans, 10 * 60 * 1000);




        /**
         * Fix: Prevent duplicate "ការតភ្ជាប់យឺត..." status if scan is already completed.
         * Solution: Track scan-in-progress and clear timeout on scan completion.
         */
       
const APP_VERSION = document.querySelector('meta[name="app-version"]')?.getAttribute('content') || '1.0.0';
let scannedLocation = null;
let locationReady = false;
let lastScanType = null;
let deferredPrompt;
let isProcessing = false;
let qrScanner = null;
let qrScanCanvas = null;
let lastScannedQR = '';
let lastScannedAt = 0;
const QR_DUPLICATE_INTERVAL = 2000;
const ALLOWED_LOCATIONS_CACHE_MS = 2 * 60 * 1000;
const USER_TIME_SETTINGS_CACHE_MS = 2 * 60 * 1000;

// DOM Elements
const elements = {
    loginSection: document.getElementById('loginSection'),
    scanSection: document.getElementById('scanSection'),
    loginForm: document.getElementById('loginForm'),
    loginId: document.getElementById('loginId'),
    loginButton: document.getElementById('loginButton'),
    loginSpinner: document.querySelector('#loginButton .spinner'),
    loginStatus: document.getElementById('loginStatus'),
    userName: document.getElementById('userName'),
    branchSelect: document.getElementById('branchSelect'),
    actionSelect: document.getElementById('actionSelect'),
    scanButton: document.getElementById('scanButton'),
    scanSpinner: document.querySelector('#scanButton .spinner'),
    viewLogsButton: document.getElementById('viewLogsButton'),
    qrScannerContainer: document.getElementById('qrScannerContainer'),
    statusEl: document.getElementById('status'),
    actionEl: document.getElementById('action'),
    timestampEl: document.getElementById('timestamp'),
    locationEl: document.getElementById('location'),
    userIdEl: document.getElementById('userId'),
    userDepartmentEl: document.getElementById('userDepartment'),
    userPositionEl: document.getElementById('userPosition'),
    userBranchEl: document.getElementById('userBranch'),
    userFolderEl: document.getElementById('userFolder'),
    userWorkplaceEl: document.getElementById('userWorkplace'),
    logoutButton: document.getElementById('logoutButton'),
    scanPopup: document.getElementById('scanPopup'),
    popupMessage: document.getElementById('popupMessage'),
    earlyScanPopup: document.getElementById('earlyScanPopup'),
    earlyReason: document.getElementById('earlyReason'),
    submitEarlyScan: document.getElementById('submitEarlyScan'),
    cancelEarlyScan: document.getElementById('cancelEarlyScan'),
    loadingPopup: document.getElementById('loadingPopup')
};


// IndexedDB Setup
const DB_NAME = 'ScanSystemDB';
const DB_VERSION = 2; // Increment version to force upgrade and create missing stores
let db;

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onerror = () => reject(new Error('Failed to open IndexedDB'));
        request.onsuccess = () => {
            db = request.result;
            resolve(db);
        };
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            // Always create all required stores if missing
            if (!db.objectStoreNames.contains('scanQueue')) {
                db.createObjectStore('scanQueue', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('loggedInUser')) {
                db.createObjectStore('loggedInUser', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('lastState')) {
                db.createObjectStore('lastState', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('lastScanType')) {
                db.createObjectStore('lastScanType', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('addressCache')) {
                db.createObjectStore('addressCache', { keyPath: 'key' });
            }
        };
    });
}

async function getFromDB(storeName, key) {
    if (!db) await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readonly');
        const store = transaction.objectStore(storeName);
        const request = key ? store.get(key) : store.getAll();
        request.onerror = () => reject(new Error(`Failed to get from ${storeName}`));
        request.onsuccess = () => resolve(request.result);
    });
}

async function putToDB(storeName, data) {
    if (!db) await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.put(data);
        request.onerror = () => reject(new Error(`Failed to put to ${storeName}`));
        request.onsuccess = () => resolve();
    });
}

async function deleteFromDB(storeName, key) {
    if (!db) await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.delete(key);
        request.onerror = () => reject(new Error(`Failed to delete from ${storeName}`));
        request.onsuccess = () => resolve();
    });
}

async function clearStore(storeName) {
    if (!db) await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.clear();
        request.onerror = () => reject(new Error(`Failed to clear ${storeName}`));
        request.onsuccess = () => resolve();
    });
}

// Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(`service-worker.js?v=${APP_VERSION}`)
            .then(registration => {
                console.log('Service Worker registered:', registration.scope);
                setInterval(checkForUpdates, 5 * 60 * 1000);
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateNotification(registration);
                        }
                    });
                });
            })
            .catch(error => console.error('Service Worker registration failed:', error));
    });
}

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    console.log('Ready to prompt for installation');
});

function promptInstall() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') console.log('User accepted the A2HS prompt');
            deferredPrompt = null;
        });
    }
}

// Network Status Handlers
window.addEventListener('online', async () => {
    showStatus(elements.statusEl, 'ត្រឡប់មកអនឡាញ - កំពុងធ្វើសមកាលកម្ម...');
    await retryQueuedScans();
    setTimeout(() => window.location.reload(), 1000);
});

window.addEventListener('offline', () => {
    showStatus(elements.statusEl, 'បាត់បង់ការតភ្ជាប់អ៊ីនធឺណិត', 'error');
});

// Prevent gesture issues
['gesturestart', 'gesturechange', 'gestureend'].forEach(event => {
    document.addEventListener(event, e => e.preventDefault());
});

// Initialize on Load
window.addEventListener('load', async () => {
    try {
        await openDB();
        await restoreStateAfterUpdate();
        const loggedInUser = await getFromDB('loggedInUser', 'user');
        if (loggedInUser && loggedInUser.token) {
            const valid = await checkToken(loggedInUser.token);
            if (valid) {
                elements.loginSection.style.display = 'none';
                elements.scanSection.style.display = 'block';
                showScanSection(loggedInUser);
                setInterval(retryQueuedScans, 60000);
                // Prefetch allowedLocations and user time settings
                Promise.all([
                    fetchAllowedLocations(),
                    fetchUserTimeSettings(loggedInUser.id)
                ]).catch(() => {}); // Ignore prefetch errors
            } else {
                logout();
            }
        } else {
            elements.loginSection.style.display = 'block';
            elements.scanSection.style.display = 'none';
        }
        initializeQRScanner();
    } catch (error) {
        console.error('Initialization error:', error);
        logout();
    }
});

// PWA Camera Pre-warm
if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
    window.addEventListener('DOMContentLoaded', () => {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 320 },
                    height: { ideal: 320 }
                }
            }).then(stream => {
                stream.getTracks().forEach(track => track.stop());
            }).catch(() => {});
        }
    });
}

// Consolidated auto-stop camera handler
function autoStopCamera() {
    stopQRScanner();
    const btn = document.getElementById('quickCheckBtn');
    const qrContainer = document.getElementById('qrScannerContainer');
    const cameraOverlay = document.getElementById('fullscreenCameraOverlay');
    const scanSection = document.getElementById('scanSection');
    if (btn && qrContainer && cameraOverlay && scanSection) {
        if (!scanSection.querySelector('#qrScannerContainer')) {
            scanSection.querySelector('.card-body').insertBefore(qrContainer, scanSection.querySelector('#viewLogsButton'));
        }
        qrContainer.style.display = 'none';
        cameraOverlay.style.display = 'none';
        btn.style.display = '';
    }
}

['pagehide', 'beforeunload'].forEach(event => {
    window.addEventListener(event, autoStopCamera);
});
document.addEventListener('visibilitychange', () => {
    if (document.hidden) autoStopCamera();
});

async function checkToken(token) {
    try {
        const response = await fetch(`api.php?action=check_token&token=${encodeURIComponent(token)}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
        const result = await response.json();
        if (result.status === 'error' && result.message === 'Token revoked') return false;
        return result.status === 'success';
    } catch (error) {
        console.error('Token check error:', error.message);
        if (!navigator.onLine) {
            showStatus(elements.statusEl, 'អ្នកនៅក្រៅបណ្តាញ - បន្តប្រើ Token ចាស់');
            return true;
        }
        showStatus(elements.statusEl, 'មានបញ្ហាក្នុងការតភ្ជាប់ Server - បន្តប្រើ Token ចាស់');
        return true;
    }
}

elements.loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (isProcessing) return;
    isProcessing = true;

    const id = elements.loginId.value.trim();
    if (!id) {
        showStatus(elements.loginStatus, 'សូមបញ្ចូល ID!');
        isProcessing = false;
        return;
    }

    elements.loginButton.disabled = true;
    elements.loginSpinner.style.display = 'inline-block';
    showStatus(elements.loginStatus, 'កំពុងផ្ទៀងផ្ទាត់...');

    try {
        const response = await fetch(`api.php?action=login&id=${encodeURIComponent(id)}`);
        if (!response.ok) throw new Error(`Login failed: ${response.status}`);
        const result = await response.json();
        if (result.status === 'success') {
            const user = {
                key: 'user',
                id: result.user.id,
                username: sanitizeInput(result.user.username),
                department: result.user.department || '',
                position: result.user.position || '',
                branch: result.user.branch || '',
                folder: result.user.folder || '',
                workplace: result.user.workplace || 'N/A',
                token: result.token
            };
            await putToDB('loggedInUser', user);
            showScanSection(user);
            elements.loginStatus.textContent = '';
        } else {
            showStatus(elements.loginStatus, result.message || 'ID មិនត្រឹមត្រូវ!');
        }
    } catch (error) {
        console.error('Login error:', error);
        showStatus(elements.loginStatus, 'មានបញ្ហាក្នុងការភ្ជាប់: ' + error.message);
    } finally {
        elements.loginButton.disabled = false;
        elements.loginSpinner.style.display = 'none';
        isProcessing = false;
    }
});

elements.logoutButton.addEventListener('click', () => {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <h3>បញ្ជាក់ការចាកចេញ</h3>
            <p>តើអ្នកប្រាកដទេថាចង់ចាកចេញ?</p>
            <div class="modal-buttons">
                <button id="confirmLogout" class="btn btn-primary">បាទ/ចាស</button>
                <button id="cancelLogout" class="btn btn-secondary">ទេ</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    document.getElementById('confirmLogout').addEventListener('click', () => {
        logout();
        document.body.removeChild(modal);
    });
    document.getElementById('cancelLogout').addEventListener('click', () => {
        document.body.removeChild(modal);
    });
});

async function logout() {
    try {
        const user = await getFromDB('loggedInUser', 'user');
        if (user && user.token) {
            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'logout', token: user.token })
            });
        }
        await Promise.all([
            clearStore('loggedInUser'),
            clearStore('lastScanType')
        ]);
        elements.scanSection.style.display = 'none';
        elements.loginSection.style.display = 'block';
        elements.loginId.value = '';
        elements.loginStatus.textContent = '';
        locationReady = false;
        lastScanType = null;
        scannedLocation = null;
        autoStopCamera();
    } catch (error) {
        console.error('Logout failed:', error);
    }
}

// Optimize scan button behavior
Object.defineProperty(window, 'locationReady', {
    set(val) {
        this._locationReady = val;
        elements.scanButton.disabled = !val;
        if (val) elements.scanButton.focus();
        else showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!');
    },
    get() {
        return this._locationReady;
    },
    configurable: true
});
window._locationReady = false;

Object.defineProperty(window, 'scannedLocation', {
    set(val) {
        this._scannedLocation = val;
        if (!val) {
            locationReady = false;
            showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!');
        }
    },
    get() {
        return this._scannedLocation;
    },
    configurable: true
});
window._scannedLocation = null;

elements.scanButton.style.transition = 'none';
elements.scanButton.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        handleScan();
    }
});

// Persist lastScanType in localStorage
function getLastScanTypeFromStorage(userId) {
    try {
        const map = JSON.parse(localStorage.getItem('lastScanTypeMap') || '{}');
        return map[userId] || null;
    } catch {
        return null;
    }
}

function setLastScanTypeToStorage(userId, scanType) {
    try {
        const map = JSON.parse(localStorage.getItem('lastScanTypeMap') || '{}');
        map[userId] = scanType;
        localStorage.setItem('lastScanTypeMap', JSON.stringify(map));
    } catch {}
}

async function showScanSection(user) {
    elements.loginSection.style.display = 'none';
    elements.scanSection.style.display = 'block';
    elements.userName.value = user.username;
    elements.userIdEl.value = user.id;
    elements.userDepartmentEl.value = user.department;
    elements.userPositionEl.value = user.position;
    elements.userFolderEl.value = user.folder;
    elements.userWorkplaceEl.value = user.workplace;

    const storedType = getLastScanTypeFromStorage(user.id);
    let lastScan = storedType || (await getFromDB('lastScanType', 'scanType')?.value) || await fetchLastScan(user.id);
    lastScanType = lastScan;
    await putToDB('lastScanType', { key: 'scanType', value: lastScanType });

    let nextAction = lastScanType === 'Check-In' ? 'Check-Out' : 'Check-In';
    elements.actionSelect.value = nextAction;
    showStatus(elements.statusEl, lastScanType ? `បានប្តូរទៅ ${nextAction} ដោយស្វ័យប្រវត្តិ` : 'បានជ្រើស ស្កេនចូល ជាលំនាំដើម');

    const queuedScans = await getFromDB('scanQueue');
    if (queuedScans?.length > 0) {
        showStatus(elements.statusEl, `មាន ${queuedScans.length} ស្កេនមិនទាន់ធ្វើសមកាលកម្ម កំពុងព្យាយាមធ្វើសមកាលកម្ម...`, 'error');
        retryQueuedScans();
    }

    elements.scanButton.disabled = true;
    elements.branchSelect.disabled = false;
    elements.scanButton.onmousedown = () => elements.scanButton.classList.add('active');
    elements.scanButton.onmouseup = elements.scanButton.onmouseleave = () => elements.scanButton.classList.remove('active');

    populateBranchOptions();
}

async function fetchLastScan(userId) {
    try {
        const cached = await getFromDB('lastScanType', 'scanType');
        if (cached?.value) return cached.value;

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 1500);
        const response = await fetch(`api.php?action=get_last_scan&id=${encodeURIComponent(userId)}`, { signal: controller.signal });
        clearTimeout(timeout);
        if (!response.ok) throw new Error('Failed to fetch last scan');
        const result = await response.json();
        if (result.status === 'success' && result.data?.scan_type) {
            lastScanType = result.data.scan_type;
            await putToDB('lastScanType', { key: 'scanType', value: lastScanType });
            return lastScanType;
        }
        return null;
    } catch {
        return null;
    }
}

let allowedLocationsCache = null;
let allowedLocationsCacheTime = 0;

async function populateBranchOptions() {
    try {
        const allowedLocations = await fetchAllowedLocations();
        elements.branchSelect.innerHTML = '<option value="">ជ្រើសសាខា</option>';
        const branchToleranceMap = {};
        allowedLocations.forEach(loc => {
            if (!branchToleranceMap[loc.branch]) {
                let maxTolerance = 0;
                if (Array.isArray(loc.users)) {
                    for (let user of loc.users) {
                        const tol = parseFloat(user.tolerance);
                        if (!isNaN(tol) && tol > maxTolerance) maxTolerance = tol;
                    }
                }
                branchToleranceMap[loc.branch] = maxTolerance;
            }
        });
        for (const [branch, tolerance] of Object.entries(branchToleranceMap)) {
            const option = document.createElement('option');
            option.value = branch;
            option.textContent = tolerance > 0 ? `${branch} (tolerance: ${tolerance})` : `${branch} (no tolerance)`;
            option.setAttribute('data-tolerance', tolerance);
            elements.branchSelect.appendChild(option);
        }
    } catch (error) {
        console.error('Error populating branches:', error);
        elements.branchSelect.innerHTML = '<option value="">មិនអាចផ្ទុកសាខា</option>';
    }
}

elements.actionSelect.addEventListener('change', () => {
    elements.scanButton.disabled = !locationReady;
    showStatus(elements.statusEl, `បានជ្រើស ${elements.actionSelect.value}`);
});

elements.viewLogsButton.addEventListener('click', async () => {
    const user = await getFromDB('loggedInUser', 'user');
    if (user) {
        window.location.href = `/worker/logs.php?username=${encodeURIComponent(user.username)}&id=${encodeURIComponent(user.id)}&branch=${encodeURIComponent(elements.branchSelect.value)}&folder=${encodeURIComponent(elements.userFolderEl.value)}`;
    } else {
        showStatus(elements.statusEl, 'សូមចូលប្រើប្រាស់ឡើងវិញ!');
    }
});

function validateFormData({ userName, action, userId, branch, folder, workplace, latitude, longitude }) {
    const errors = [];
    if (!userName) errors.push("សូមបញ្ចូលឈ្មោះ!");
    if (!action) errors.push("សូមជ្រើសរើសប្រភេទស្កេន!");
    if (!userId) errors.push("សូមជ្រើសរើស ID!");
    if (!branch) errors.push("សូមជ្រើសរើសសាខា!");
    if (!folder) errors.push("សូមជ្រើសរើស Folder!");
    if (!workplace) errors.push("សូមបញ្ចូលទីតាំងធ្វើការ!");
    if (!latitude || !longitude) errors.push("សូមស្កេន QR Code ទីតាំង!");
    return errors.length > 0 ? errors.join(" ") : null;
}

async function saveToBackend(formData, retries = 3, timeout = 5000) {
    if (!navigator.onLine) {
        await saveToIndexedDB(formData);
        showStatus(elements.statusEl, 'អ្នកនៅក្រៅបណ្តាញ - បានរក្សាទុកក្នុងមូលដ្ឋាន', 'error');
        return { success: false, error: new Error('Offline') };
    }

    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeout * attempt);
            let body = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                body.append(key, value);
            }
            const response = await fetch("/worker/save_log.php", {
                method: "POST",
                body: body,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            if (!response.ok) throw new Error(`Server error: ${response.status}`);
            const data = await response.json();
            if (data.status === "success") return { success: true, data };
            throw new Error(data.message || "Unknown error from server");
        } catch (error) {
            if (attempt === retries) {
                await saveToIndexedDB(formData);
                return { success: false, error };
            }
            await new Promise(resolve => setTimeout(resolve, 2000 * attempt));
        }
    }
}

// async function saveToIndexedDB(formData) {
//     if (!formData || !formData.get("ឈ្មោះ") || !formData.get("ID") || !formData.get("ប្រភេទស្កេន")) return;
//     const dataToSave = Object.fromEntries(formData);
//     dataToSave.id = Date.now();
//     await putToDB('scanQueue', dataToSave);
// }

async function retryQueuedScans() {
    if (!navigator.onLine) return;
    const localData = await getFromDB('scanQueue');
    if (!localData?.length) return;

    showStatus(elements.statusEl, `រកឃើញ ${localData.length} ស្កេនដែលមិនទាន់ធ្វើសមកាលកម្ម កំពុងសាកល្បង...`);
    const user = await getFromDB('loggedInUser', 'user');
    if (!user || !await checkToken(user.token)) {
        showStatus(elements.statusEl, 'Token មិនត្រឹមត្រូវ សូមចូលឡើងវិញ!', 'error');
        logout();
        return;
    }

    for (const scan of localData) {
        const formData = new FormData();
        Object.entries(scan).forEach(([key, value]) => {
            if (key !== 'id') formData.append(key, value);
        });
        formData.append('token', user.token);
        const result = await saveToBackend(formData);
        if (result.success) await deleteFromDB('scanQueue', scan.id);
    }
    const remainingScans = await getFromDB('scanQueue');
    showStatus(elements.statusEl,
        remainingScans.length === 0 ? 'បានធ្វើសមកាលកម្មស្កេនទាំងអស់!' : 'មានស្កេនមួយចំនួនមិនទាន់ធ្វើសមកាលកម្ម នឹងសាកល្បងម្តងទៀត',
        remainingScans.length === 0 ? 'success' : 'error'
    );
}

async function getAddress(lat, lon) {
    const key = `${lat.toFixed(5)},${lon.toFixed(5)}`;
    const cached = await getFromDB('addressCache', key);
    if (cached && Date.now() - cached.timestamp < 3600000) return cached.address;

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`, {
            headers: { "Accept-Language": "km" }
        });
        if (!response.ok) throw new Error('Nominatim API មិនឆ្លើយតប');
        const data = await response.json();
        const address = data.address ? (data.address.road || data.address.village || 'គ្មានឈ្មោះផ្លូវ ឬភូមិ') : 'មិនអាចរកអាសយដ្ឋានបាន';
        await putToDB('addressCache', { key, address, timestamp: Date.now() });
        return address;
    } catch {
        return 'មិនអាចរកអាសយដ្ឋានបាន';
    }
}

let allowedLocationsPromise = null;
async function fetchAllowedLocations() {
    const now = Date.now();
    if (allowedLocationsCache && now - allowedLocationsCacheTime < ALLOWED_LOCATIONS_CACHE_MS) return allowedLocationsCache;
    if (allowedLocationsPromise) return allowedLocationsPromise;

    allowedLocationsPromise = (async () => {
        try {
            const response = await fetchWithRetry('api.php?action=get_data', 3, 1000);
            const result = await response.json();
            if (result.status !== 'success' || !result.data.allowedLocations) throw new Error('Invalid data from API');
            allowedLocationsCache = result.data.allowedLocations;
            allowedLocationsCacheTime = now;
            return allowedLocationsCache;
        } catch (error) {
            showStatus(elements.statusEl, 'មានបញ្ហាក្នុងការទាញទីតាំងអនុញ្ញាត', 'error');
            elements.scanButton.disabled = true;
            return [];
        } finally {
            allowedLocationsPromise = null;
        }
    })();
    return allowedLocationsPromise;
}

const userTimeSettingsCache = {};
const userTimeSettingsCacheTime = {};
const userTimeSettingsFetchPromise = {};

async function fetchUserTimeSettings(userId) {
    const now = Date.now();
    if (userTimeSettingsCache[userId] && userTimeSettingsCacheTime[userId] && now - userTimeSettingsCacheTime[userId] < USER_TIME_SETTINGS_CACHE_MS) {
        return userTimeSettingsCache[userId];
    }
    if (userTimeSettingsFetchPromise[userId]) return userTimeSettingsFetchPromise[userId];

    userTimeSettingsFetchPromise[userId] = (async () => {
        try {
            const response = await fetch('api.php?action=get_data');
            if (!response.ok) throw new Error(`Failed to fetch time settings: ${response.status}`);
            const result = await response.json();
            if (result.status === 'success') {
                const user = result.data.users.find(u => u.id === userId);
                if (user && user.timeSettings) {
                    if (user.timeSettings.check_in_ranges) {
                        user.timeSettings.check_in_ranges.forEach(range => {
                            if (typeof range.start === 'string') range.start = parseTimeToMinutes(range.start);
                            if (typeof range.end === 'string') range.end = parseTimeToMinutes(range.end);
                        });
                    }
                    if (user.timeSettings.check_out_ranges) {
                        user.timeSettings.check_out_ranges.forEach(range => {
                            if (typeof range.start === 'string') range.start = parseTimeToMinutes(range.start);
                            if (typeof range.end === 'string') range.end = parseTimeToMinutes(range.end);
                        });
                    }
                    userTimeSettingsCache[userId] = user.timeSettings;
                    userTimeSettingsCacheTime[userId] = now;
                    return user.timeSettings;
                }
                throw new Error('No time settings found for user');
            }
            throw new Error(result.message || 'Failed to fetch time settings');
        } catch {
            return null;
        } finally {
            delete userTimeSettingsFetchPromise[userId];
        }
    })();
    return userTimeSettingsFetchPromise[userId];
}

function parseTimeToMinutes(timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
}

async function getServerTime() {
    try {
        const resp = await fetch('api.php?action=get_server_time');
        if (!resp.ok) throw new Error('Failed to fetch server time');
        const data = await resp.json();
        if (data.status === 'success' && data.server_time) return new Date(data.server_time);
        throw new Error('Invalid server time response');
    } catch {
        return null;
    }
}

function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = x => x * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}



async function validateQRCodeLocation({ latitude, longitude }) {
    try {
        const user = await getFromDB('loggedInUser', 'user');
        if (!user?.id) throw new Error('User not logged in');

        const allowedLocations = await fetchAllowedLocations();
        const matchedLocation = allowedLocations.find(loc => {
            const allowedUser = loc.users?.find(u => u.user_id === user.id);
            if (!allowedUser) return false;
            const tolerance = parseFloat(allowedUser.tolerance);
            if (isNaN(tolerance) || tolerance <= 0) return false;
            return Math.abs(latitude - loc.latitude) <= tolerance && Math.abs(longitude - loc.longitude) <= tolerance;
        });

        if (!matchedLocation) {
            showStatus(elements.statusEl, 'ទីតាំង QR Code មិនត្រូវគ្នានឹងទីតាំងអនុញ្ញាត!', 'error');
            elements.scanButton.disabled = true;
            elements.branchSelect.disabled = false;
            return;
        }

        let realPosition;
        try {
            // Reduce timeout for faster response, especially on Android
            realPosition = await getCurrentPosition({ enableHighAccuracy: true, timeout: 5000, maximumAge: 10000 });
        } catch {
            // Fallback: try low accuracy or cached position for speed
            try {
                realPosition = await getCurrentPosition({ enableHighAccuracy: false, timeout: 2000, maximumAge: 60000 });
            } catch {
                showStatus(elements.statusEl, 'សូមអនុញ្ញាតឲ្យប្រើ GPS ដើម្បីបញ្ជាក់ទីតាំង!', 'error');
                elements.scanButton.disabled = true;
                return;
            }
        }
        const realLat = realPosition.coords.latitude;
        const realLon = realPosition.coords.longitude;

        const allowedUser = matchedLocation.users.find(u => u.user_id === user.id);
        const tolerance = parseFloat(allowedUser.tolerance);
        const distance = haversineDistance(realLat, realLon, matchedLocation.latitude, matchedLocation.longitude);
        const maxDistance = Math.max(tolerance * 111320, 100);

        if (distance > maxDistance) {
            // Show both meters and kilometers
            const distanceKm = (distance / 1000).toFixed(2);
            const maxDistanceKm = (maxDistance / 1000).toFixed(2);
            showStatus(
                elements.statusEl,
                `អ្នកមិនស្ថិតនៅក្នុងតំបនដែនអាចស្កេនបាន! (ចម្ងាយពី GPS: ${distance.toFixed(1)}m (${distanceKm}km), អនុញ្ញាត: ${maxDistance.toFixed(0)}m (${maxDistanceKm}km))`,
                'error'
            );
            elements.scanButton.disabled = true;
            elements.branchSelect.disabled = false;
            return;
        }

        scannedLocation = { ...scannedLocation, name: matchedLocation.name, branch: matchedLocation.branch };
        elements.branchSelect.value = matchedLocation.branch;
        elements.userBranchEl.value = matchedLocation.branch;
        elements.branchSelect.disabled = true;
        elements.locationEl.textContent = `ទីតាំង: ${matchedLocation.name} (${matchedLocation.branch})`;
        showStatus(elements.statusEl, `ទីតាំងត្រឹមត្រូវ (${matchedLocation.name} - ${matchedLocation.branch})`, 'success');
        elements.scanButton.disabled = false;
        handleScan();
    } catch (error) {
        showStatus(elements.statusEl, 'មិនអាចផ្ទៀងផ្ទាត់ទីតាំងបាន: ' + error.message, 'error');
        elements.scanButton.disabled = true;
    }
}

function initializeQRScanner() {
    if (!elements.qrScannerContainer) {
        showStatus(elements.statusEl, 'មិនអាចចាប់ផ្តើម QR Scanner បាន!', 'error');
        return;
    }

    while (elements.qrScannerContainer.firstChild) {
        elements.qrScannerContainer.removeChild(elements.qrScannerContainer.firstChild);
    }

    const video = document.createElement('video');
    video.setAttribute('playsinline', 'true');
    video.style.width = '100%';
    video.style.height = 'auto';
    elements.qrScannerContainer.appendChild(video);

    const cameraOverlay = document.createElement('div');
    cameraOverlay.style.position = 'absolute';
    cameraOverlay.style.top = '0';
    cameraOverlay.style.left = '0';
    cameraOverlay.style.width = '100%';
    cameraOverlay.style.height = '100%';
    cameraOverlay.style.display = 'flex';
    cameraOverlay.style.alignItems = 'center';
    cameraOverlay.style.justifyContent = 'center';
    cameraOverlay.style.pointerEvents = 'none';
    cameraOverlay.style.zIndex = '10';
    cameraOverlay.innerHTML = `
        <div style="background:rgba(0,0,0,0.18);border-radius:1.2rem;width:100%;height:100%;position:absolute;top:0;left:0;"></div>
    `;
    elements.qrScannerContainer.appendChild(cameraOverlay);

    function startScanner() {
        const constraintsList = [
            { video: { facingMode: { ideal: 'environment' }, width: { ideal: 320 }, height: { ideal: 320 } } },
            { video: { facingMode: { ideal: 'environment' }, width: { ideal: 480 }, height: { ideal: 480 } } },
            { video: { facingMode: { ideal: 'environment' } } }
        ];

        let tried = 0;
        function tryNextConstraint() {
            if (tried >= constraintsList.length) {
                showStatus(elements.statusEl, 'មិនអាចបើកកាមេរ៉ា!', 'error');
                cameraOverlay.innerHTML = `<span style="color:#e74c3c;font-size:1.1rem;font-weight:600;background:rgba(255,255,255,0.9);border-radius:0.7rem;padding:0.18rem 0.6rem;">មិនអាចបើកកាមេរ៉ា</span>`;
                return;
            }
            navigator.mediaDevices.getUserMedia(constraintsList[tried])
                .then(stream => {
                    video.srcObject = stream;
                    video.play();
                    qrScanner = setInterval(() => scanQRCode(video), 100);
                    showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!');
                    video.onplaying = () => {
                        cameraOverlay.style.display = 'none';
                        addFlashlightToggle(video);
                    };
                })
                .catch(() => {
                    tried++;
                    tryNextConstraint();
                });
        }
        tryNextConstraint();
    }

    if (!window.jsQR) {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
        script.onload = startScanner;
        script.onerror = () => showStatus(elements.statusEl, 'មិនអាចផ្ទុក QR Scanner បាន!', 'error');
        document.head.appendChild(script);
    } else {
        startScanner();
    }
}

function addFlashlightToggle(video) {
    let existing = document.getElementById('flashlightToggleBtn');
    if (existing) existing.remove();

    const stream = video.srcObject;
    if (!stream) return;
    const track = stream.getVideoTracks()[0];
    if (!track) return;

    const capabilities = track.getCapabilities && track.getCapabilities();
    if (!capabilities || !capabilities.torch) return;

    const btn = document.createElement('button');
    btn.id = 'flashlightToggleBtn';
    btn.type = 'button';
    btn.style.position = 'absolute';
    btn.style.bottom = '18px';
    btn.style.right = '18px';
    btn.style.zIndex = '30';
    btn.style.background = 'rgba(255,255,255,0.92)';
    btn.style.border = 'none';
    btn.style.borderRadius = '50%';
    btn.style.width = '48px';
    btn.style.height = '48px';
    btn.style.display = 'flex';
    btn.style.alignItems = 'center';
    btn.style.justifyContent = 'center';
    btn.style.boxShadow = '0 2px 8px #0002';
    btn.style.fontSize = '2rem';
    btn.style.cursor = 'pointer';
    btn.style.transition = 'background 0.2s';
    btn.innerHTML = '<img src="https://cdn-icons-png.flaticon.com/512/6422/6422016.png" alt="flashlight" style="width:28px;height:28px;">';

    let torchOn = false;
    btn.onclick = async () => {
        try {
            torchOn = !torchOn;
            await track.applyConstraints({ advanced: [{ torch: torchOn }] });
            btn.style.background = torchOn ? '#ffe066' : 'rgba(255,255,255,0.92)';
            btn.innerHTML = torchOn
                ? '<img src="https://cdn-icons-png.flaticon.com/512/6422/6422016.png" alt="flashlight" style="width:28px;height:28px;filter:drop-shadow(0 0 8px #ffc107);">' 
                : '<img src="https://cdn-icons-png.flaticon.com/512/6422/6422016.png" alt="flashlight" style="width:28px;height:28px;">';
        } catch {
            btn.disabled = true;
            btn.title = 'មិនគាំទ្រពិល';
        }
    };
    if (elements.qrScannerContainer) elements.qrScannerContainer.appendChild(btn);
}

(function addCheckInOutBtn() {
    if (document.getElementById('quickCheckBtn')) return;
    const btn = document.createElement('button');
    btn.id = 'quickCheckBtn';
    btn.className = 'btn btn-primary mb-3';
    btn.style.width = '100%';
    btn.innerHTML = '<i class="fa-solid fa-qrcode"></i> ចាប់ផ្តើមស្កេន Check-In/Out';

    let cameraOverlay = document.getElementById('fullscreenCameraOverlay');
    if (!cameraOverlay) {
        cameraOverlay = document.createElement('div');
        cameraOverlay.id = 'fullscreenCameraOverlay';
        cameraOverlay.style.position = 'fixed';
        cameraOverlay.style.top = '0';
        cameraOverlay.style.left = '0';
        cameraOverlay.style.width = '100vw';
        cameraOverlay.style.height = '100vh';
        cameraOverlay.style.background = 'rgba(0,0,0,0.97)';
        cameraOverlay.style.zIndex = '3000';
        cameraOverlay.style.display = 'none';
        cameraOverlay.style.justifyContent = 'center';
        cameraOverlay.style.alignItems = 'center';
        cameraOverlay.style.flexDirection = 'column';
        cameraOverlay.innerHTML = `
            <div style="position:relative;width:90vw;max-width:400px;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;">
                <div id="fullscreenQRScannerContainer" style="width:100%;height:100%;"></div>
                <button id="closeFullscreenCamera" style="position:absolute;top:10px;right:10px;z-index:10;background:#fff;border:none;border-radius:50%;width:38px;height:38px;font-size:1.5rem;box-shadow:0 2px 8px #0002;cursor:pointer;">×</button>
            </div>
        `;
        document.body.appendChild(cameraOverlay);
    }

    const scanSection = document.getElementById('scanSection');
    const qrContainer = document.getElementById('qrScannerContainer');
    if (scanSection && qrContainer) {
        qrContainer.style.display = 'none';
        qrContainer.parentNode.insertBefore(btn, qrContainer);

        btn.addEventListener('click', () => {
            const overlayQR = document.getElementById('fullscreenQRScannerContainer');
            if (overlayQR && qrContainer) {
                qrContainer.style.display = '';
                overlayQR.appendChild(qrContainer);
            }
            cameraOverlay.style.display = 'flex';
            initializeQRScanner();
            btn.style.display = 'none';
        });

        cameraOverlay.querySelector('#closeFullscreenCamera').onclick = autoStopCamera;

        const originalShowStatus = showStatus;
        window.showStatus = function(element, message, type = 'default', action = null) {
            originalShowStatus(element, message, type, action);
            if (typeof message === 'string' && (message.includes('ទីតាំងត្រឹមត្រូវ') || message.includes('អ្នកមិនស្ថិតនៅក្នុងតំបនដែនអាចស្កេនបាន!')) && cameraOverlay.style.display !== 'none') {
                autoStopCamera();
            }
        };
    }
})();



function showCameraUIAfterScan() {
    if (elements.qrScannerContainer.querySelector('.camera-ui-after-scan')) {
        elements.qrScannerContainer.removeChild(elements.qrScannerContainer.querySelector('.camera-ui-after-scan'));
    }
    const doneOverlay = document.createElement('div');
    doneOverlay.className = 'camera-ui-after-scan';
    doneOverlay.style.position = 'absolute';
    doneOverlay.style.top = '0';
    doneOverlay.style.left = '0';
    doneOverlay.style.width = '100%';
    doneOverlay.style.height = '100%';
    doneOverlay.style.background = 'rgba(44, 203, 112, 0.18)';
    doneOverlay.style.display = 'flex';
    doneOverlay.style.alignItems = 'center';
    doneOverlay.style.justifyContent = 'center';
    doneOverlay.style.zIndex = '20';
    doneOverlay.innerHTML = `
        <div style="background:rgba(255,255,255,0.95);border-radius:1.2rem;padding:1.2rem 1.5rem;box-shadow:0 2px 8px rgba(44,62,80,0.07);display:flex;flex-direction:column;align-items:center;">
            <i class="fa-solid fa-circle-check" style="color:#27ae60;font-size:2.5rem;margin-bottom:0.5rem;"></i>
            <span style="color:#27ae60;font-size:1.1rem;font-weight:600;">ស្កេនរួចរាល់</span>
        </div>
    `;
    elements.qrScannerContainer.appendChild(doneOverlay);
    setTimeout(() => {
        if (doneOverlay.parentNode) doneOverlay.parentNode.removeChild(doneOverlay);
        autoStopCamera(); // Close camera after showing "ស្កេនរួចរាល់"
    }, 1200);
}

function scanQRCode(video) {
    if (!video || video.readyState < 2) return;
    if (!qrScanCanvas) qrScanCanvas = document.createElement('canvas');
    qrScanCanvas.width = video.videoWidth;
    qrScanCanvas.height = video.videoHeight;
    const ctx = qrScanCanvas.getContext('2d');
    ctx.drawImage(video, 0, 0, qrScanCanvas.width, qrScanCanvas.height);
    const imageData = ctx.getImageData(0, 0, qrScanCanvas.width, qrScanCanvas.height);
    if (!window.jsQR) return;
    const code = jsQR(imageData.data, imageData.width, imageData.height);
    if (code && code.data) {
        const now = Date.now();
        if (code.data === lastScannedQR && now - lastScannedAt < QR_DUPLICATE_INTERVAL) return;
        lastScannedQR = code.data;
        lastScannedAt = now;
        try {
            if (code.data.startsWith('geo:')) {
                const [latitude, longitude] = code.data.replace('geo:', '').split(',').map(parseFloat);
                if (isNaN(latitude) || isNaN(longitude)) throw new Error('Invalid latitude or longitude');
                scannedLocation = { latitude, longitude };
                locationReady = true;
                validateQRCodeLocation({ latitude, longitude });
                showCameraUIAfterScan();
            } else {
                throw new Error('Invalid QR code format');
            }
        } catch (error) {
            showStatus(elements.statusEl, 'QR Code មិនត្រឹមត្រូវ!', 'error');
            locationReady = false;
            elements.scanButton.disabled = true;
        }
    }
}

function stopQRScanner() {
    if (qrScanner) {
        clearInterval(qrScanner);
        qrScanner = null;
    }
    if (elements.qrScannerContainer && elements.qrScannerContainer.firstChild) {
        const video = elements.qrScannerContainer.firstChild;
        const stream = video.srcObject;
        if (stream) stream.getTracks().forEach(track => track.stop());
        elements.qrScannerContainer.removeChild(video);
    }
}



async function handleScan() {
    if (isProcessing) return;
    isProcessing = true;
    elements.scanButton.disabled = true;
    elements.scanSpinner.style.display = 'inline-block';
    elements.scanButton.style.display = 'none';

    let spinnerTimeout = setTimeout(() => {
        elements.scanSpinner.style.display = 'none';
        elements.scanButton.style.display = 'block';
        elements.scanButton.disabled = !locationReady;
    }, 1200);

    try {
        // Try to get server time with retries and longer timeout, fallback to device time if failed
        let serverTime = null;
        for (let i = 0; i < 3; i++) {
            serverTime = await Promise.race([
                getServerTime(),
                new Promise(resolve => setTimeout(() => resolve(null), 3500))
            ]);
            if (serverTime) break;
        }
        if (!serverTime) {
            // Fallback: use device time and show a warning, but allow scan to proceed
            showStatus(elements.statusEl, 'មិនអាចផ្ទៀងផ្ទាត់ម៉ោងតាមម៉ាស៊ីនមេបាន - ប្រើម៉ោងក្នុងទូរស័ព្ទ', 'error');
            serverTime = new Date();
        }
        const deviceTime = new Date();
        const diffMinutes = Math.abs(deviceTime.getTime() - serverTime.getTime()) / 60000;
        if (diffMinutes > 2) {
            showStatus(elements.statusEl, `ម៉ោងក្នុងទូរស័ព្ទ (${deviceTime.toLocaleTimeString()}) ខុសពីម៉ោងតំបន់ (${serverTime.toLocaleTimeString()}) ។ សូមកែម៉ោងឲ្យត្រឹមត្រូវ!`, 'error');
            return;
        }

        const user = await getFromDB('loggedInUser', 'user');
        if (!user?.token || !await checkToken(user.token)) {
            showStatus(elements.statusEl, 'Token មិនត្រឹមត្រូវ សូមចូលឡើងវិញ!', 'error');
            logout();
            return;
        }

        if (!locationReady || !scannedLocation) {
            showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!', 'error');
            return;
        }

        const allowedLocations = await fetchAllowedLocations();
        if (!allowedLocations.length) {
            showStatus(elements.statusEl, 'មិនទាន់មានទីតាំងអនុញ្ញាត មិនអាចស្កេនបាន', 'error');
            return;
        }

        const matchedLocation = allowedLocations.find(loc => {
            const allowedUser = loc.users.find(u => u.user_id === user.id);
            if (!allowedUser) return false;
            const tolerance = parseFloat(allowedUser.tolerance);
            return Math.abs(scannedLocation.latitude - loc.latitude) <= tolerance && Math.abs(scannedLocation.longitude - loc.longitude) <= tolerance;
        });

        if (!matchedLocation) {
            showStatus(elements.statusEl, 'ទីតាំង QR Code មិនត្រូវគ្នានឹងទីតាំងអនុញ្ញាត!', 'error');
            return;
        }

        const validationError = validateFormData({
            userName: elements.userName.value,
            action: elements.actionSelect.value,
            userId: elements.userIdEl.value,
            branch: elements.branchSelect.value,
            folder: elements.userFolderEl.value,
            workplace: elements.userWorkplaceEl.value,
            latitude: scannedLocation.latitude,
            longitude: scannedLocation.longitude
        });
        if (validationError) {
            showStatus(elements.statusEl, validationError, 'error');
            return;
        }

        const now = new Date();
        const date = now.toLocaleDateString("km-KH", { day: "2-digit", month: "2-digit", year: "numeric" });
        const time = now.toLocaleTimeString("km-KH", { hour12: true, hour: "2-digit", minute: "2-digit", second: "2-digit" });
        const location = `Lat: ${scannedLocation.latitude}, Long: ${scannedLocation.longitude}`;
        const addressPromise = getAddress(scannedLocation.latitude, scannedLocation.longitude);

        let scanStatus = 'Good';
        let lateMinutes = 0;
        const timeSettings = await Promise.race([
            fetchUserTimeSettings(user.id),
            new Promise(resolve => setTimeout(() => resolve(null), 1200))
        ]);
        if (timeSettings) {
            const ranges = elements.actionSelect.value === 'Check-In' ? timeSettings.check_in_ranges : timeSettings.check_out_ranges;
            if (ranges?.length) {
                const { status, lateMinutes: calculatedLateMinutes } = calculateScanStatus(now, ranges);
                scanStatus = status;
                lateMinutes = calculatedLateMinutes;
                if (elements.actionSelect.value === 'Check-Out' && !scanStatus.includes('Good')) {
                    showEarlyScanPopup();
                    return;
                }
            }
        }

        const address = await Promise.race([
            addressPromise,
            new Promise(resolve => setTimeout(() => resolve('កំពុងទាញអាសយដ្ឋាន...'), 1500))
        ]);

        const formData = new FormData();
        formData.append("ឈ្មោះ", elements.userName.value);
        formData.append("ID", elements.userIdEl.value);
        formData.append("ប្រភេទស្កេន", elements.actionSelect.value);
        formData.append("ថ្ងៃ", date);
        formData.append("ម៉ោង", time);
        formData.append("location", location);
        formData.append("address", address);
        formData.append("សាខា", elements.branchSelect.value);
        formData.append("folder", elements.userFolderEl.value);
        formData.append("Department", elements.userDepartmentEl.value);
        formData.append("Position", elements.userPositionEl.value);
        formData.append("workplace", elements.userWorkplaceEl.value);
        formData.append("token", user.token);
        formData.append("location_name", matchedLocation.name);
        formData.append("status", scanStatus || '');
        formData.append("early_reason", elements.earlyReason?.value?.trim() || '');

        await submitScan(formData, date, time, location, address, scanStatus, lateMinutes);
    } catch (error) {
        showStatus(elements.statusEl, 'កំហុសក្នុងការស្កេន: ' + error.message, 'error');
    } finally {
        clearTimeout(spinnerTimeout);
        isProcessing = false;
        elements.scanSpinner.style.display = 'none';
        elements.scanButton.style.display = 'block';
        elements.scanButton.disabled = !locationReady;
        autoStopCamera();
    }
}

function calculateScanStatus(scanTime, ranges, isEarlyWithReason = false) {
    if (isEarlyWithReason) return { status: 'Good', lateMinutes: 0 };
    const scanMinutes = scanTime.getHours() * 60 + scanTime.getMinutes();
    for (const range of ranges) {
        if (scanMinutes >= range.start && scanMinutes <= range.end) {
            return { status: range.status, lateMinutes: 0 };
        }
    }
    let minLate = null;
    let status = '🔴 Late';
    for (const range of ranges) {
        if (range.status !== 'Good') continue;
        if (scanMinutes < range.start) {
            const late = range.start - scanMinutes;
            if (minLate === null || late < minLate) minLate = late;
        } else if (scanMinutes > range.end) {
            const late = scanMinutes - range.end;
            if (minLate === null || late < minLate) minLate = late;
        }
    }
    return { status, lateMinutes: minLate || 0 };
}

async function submitScan(formData, date, time, location, address, scanStatus, lateMinutes = 0) {
    const userId = elements.userIdEl.value;
    const scanType = elements.actionSelect.value;
    if (userId && scanType) setLastScanTypeToStorage(userId, scanType);

    elements.loadingPopup.classList.add('show');
    try {
        const result = await saveToBackend(formData);
        elements.loadingPopup.classList.remove('show'); // Hide popup immediately after backend attempt

        if (result.success) {
            lastScanType = scanType;
            await putToDB('lastScanType', { key: 'scanType', value: lastScanType });
            elements.actionEl.textContent = scanType;
            elements.timestampEl.textContent = `${date} ${time}`;
            const mapUrl = `https://www.google.com/maps?q=${scannedLocation.latitude},${scannedLocation.longitude}`;
            elements.locationEl.innerHTML = `${location}\n${address} (<a href="${mapUrl}" target="_blank" rel="noopener noreferrer" style="color: #3498db; text-decoration: underline;">មើលទីតាំង</a>)`;

            const formattedStatus = scanStatus.includes('🔵') || scanStatus.includes('🔴') ? scanStatus : (scanStatus === 'Good' ? '🔵 Good' : '🔴 Late');
            const earlyReasonText = formData.get("early_reason") ? `\nមូលហេតុចេញមុន: ${formData.get("early_reason")}` : '';
            const lateText = lateMinutes > 0 ? `\nចំនួននាទីយឺត: ${lateMinutes} នាទី` : '';
            const telegramMessage = `Name: ${elements.userName.value}\nStatus: ${scanType} (${formattedStatus})${earlyReasonText}${lateText}\n\nID: ${userId}\nDepartment: ${elements.userDepartmentEl.value}\nPosition: ${elements.userPositionEl.value}\nWorkplace: ${elements.userWorkplaceEl.value}\nDate/Time: ${date} ${time}\nArea: ${elements.branchSelect.value}\nLocation: ${address}\n\n[📍 មើលទីតាំង](https://www.google.com/maps?q=${scannedLocation.latitude},${scannedLocation.longitude})`;

            // Run Telegram send in background, don't await
            sendToTelegram(telegramMessage).catch(error => {
                console.error('Failed to send to Telegram:', error);
                showStatus(elements.statusEl, `បានរក្សាទុកទិន្នន័យ ប៉ុន្តែមិនអាចផ្ញើទៅ Telegram: ${error.message}`, 'error');
            });

            showStatus(elements.statusEl, `បានបញ្ជូនរួចរាល់ - ${elements.userName.value}`, 'success');
            showPopup(`បានបញ្ជូនរួចរាល់ - ${elements.userName.value}`, 'success');

            const nextAction = scanType === 'Check-In' ? 'Check-Out' : 'Check-In';
            elements.actionSelect.value = nextAction;
        } else {
            await saveToIndexedDB(formData);
            console.error("Failed to save data, stored in IndexedDB:", { formData: Object.fromEntries(formData), error: result.error });
            showStatus(elements.statusEl, `មានបញ្ហាក្នុងការបញ្ជូនទិន្នន័យ ប៉ុន្តែបានរក្សាទុកជាបណ្តោះអាសន្ន`, 'error');
            showPopup('បញ្ជូនបរាជ័យ បានរក្សាទុកជាបណ្តោះអាសន្ន!', 'error');
        }
    } catch (error) {
        await saveToIndexedDB(formData);
        showStatus(elements.statusEl, `កំហុសបណ្តាញ: ${error.message}`, 'error');
        showPopup('បញ្ជូនបរាជ័យ!', 'error');
    } finally {
        elements.loadingPopup.classList.remove('show');
        showStatus(elements.statusEl, `សូមបញ្ជាក់ ${elements.actionSelect.value} បន្ទាប់`);
    }
}

function showStatus(element, message, type = 'default', action = null) {
    element.textContent = '';
    element.classList.remove('show');
    element.style.transition = 'background 0.18s, color 0.18s, border-color 0.18s';
    let color, bg, border;
    switch (type) {
        case 'success':
            color = '#27ae60';
            bg = 'rgba(231,255,231,0.97)';
            border = '#27ae60';
            break;
        case 'error':
            color = '#e74c3c';
            bg = 'rgba(255,245,245,0.97)';
            border = '#e74c3c';
            break;
        case 'warning':
            color = '#f39c12';
            bg = 'rgba(255,250,230,0.97)';
            border = '#f39c12';
            break;
        default:
            color = '#2980b9';
            bg = 'rgba(240,248,255,0.97)';
            border = '#4A90E2';
    }
    element.style.color = color;
    element.style.background = bg;
    element.style.borderColor = border;
    element.style.borderWidth = '2px';
    element.style.borderStyle = 'solid';
    element.style.borderRadius = '0.7rem';
    element.style.padding = '0.7rem 1.1rem';
    element.style.marginBottom = '0.7rem';
    element.style.fontWeight = '600';
    element.style.fontSize = '1.08rem';
    element.style.boxShadow = '0 2px 8px rgba(44,62,80,0.07)';
    element.style.display = 'block';

    // Remove old buttons
    Array.from(element.querySelectorAll('button')).forEach(btn => btn.remove());

    // Add action button if needed
    if (action) {
        const actionBtn = document.createElement('button');
        actionBtn.textContent = action.text;
        actionBtn.className = 'btn btn-sm btn-secondary mt-2';
        actionBtn.onclick = action.callback;
        element.appendChild(actionBtn);
    }

    // Add icon
    let icon = '';
    if (type === 'success') icon = '<i class="fa-solid fa-circle-check" style="color:#27ae60;margin-right:0.5em;"></i>';
    else if (type === 'error') icon = '<i class="fa-solid fa-circle-xmark" style="color:#e74c3c;margin-right:0.5em;"></i>';
    else if (type === 'warning') icon = '<i class="fa-solid fa-triangle-exclamation" style="color:#f39c12;margin-right:0.5em;"></i>';
    else icon = '<i class="fa-solid fa-info-circle" style="color:#2980b9;margin-right:0.5em;"></i>';

    element.innerHTML = icon + message;
    if (action) element.appendChild(actionBtn);

    element.classList.add('show');
    element.style.opacity = '1';
}

function showPopup(message, type = 'success') {
    elements.popupMessage.textContent = message;
    elements.scanPopup.className = 'popup ' + type;
    elements.scanPopup.style.display = 'block';

    const stayBtn = document.createElement('button');
    stayBtn.innerHTML = '<span style="display:inline-block;animation:iconBounce 1s infinite alternate;">🙏</span>';
    stayBtn.className = 'btn btn-secondary mt-2';
    elements.scanPopup.appendChild(stayBtn);

    let audioUrl = elements.actionSelect.value === 'Check-In' ? 'CheckIn.mp3' : 'CheckOut.mp3';
    const audio = new Audio(audioUrl);
    audio.play().catch(err => console.log('Audio error:', err));

    const popupTimeout = setTimeout(() => {
        elements.scanPopup.style.opacity = '0';
        setTimeout(() => {
            elements.scanPopup.style.display = 'none';
            elements.scanPopup.style.opacity = '1';
            elements.scanPopup.removeChild(stayBtn);
        }, 200);
    }, 3000);

    const redirectTimeout = setTimeout(() => {
        window.location.href = `/worker/logs.php?username=${encodeURIComponent(elements.userName.value)}&id=${encodeURIComponent(elements.userIdEl.value)}&branch=${encodeURIComponent(elements.branchSelect.value)}&folder=${encodeURIComponent(elements.userFolderEl.value)}`;
    }, 60000);

    stayBtn.onclick = () => {
        clearTimeout(popupTimeout);
        clearTimeout(redirectTimeout);
        elements.scanPopup.style.display = 'none';
        elements.scanPopup.removeChild(stayBtn);
        showStatus(elements.statusEl, 'អ្នកនៅតែស្ថិតក្នុងទំព័រនេះ');
        elements.scanButton.style.display = 'block';
    };
}

function showEarlyScanPopup() {
    elements.earlyScanPopup.style.display = 'flex';
    elements.earlyReason.value = '';
    setTimeout(() => elements.earlyReason.focus(), 10);
}

elements.earlyScanPopup.addEventListener('transitionend', () => {
    if (elements.earlyScanPopup.style.display === 'flex') {
        elements.earlyReason.focus();
    }
});

elements.earlyReason.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        elements.submitEarlyScan.click();
    } else if (e.key === 'Escape') {
        elements.cancelEarlyScan.click();
    }
});

elements.submitEarlyScan.addEventListener('click', async () => {
    const reason = elements.earlyReason.value.trim();
    if (!reason) {
        showPopup('សូមបញ្ចូលមូលហេតុ!', 'error');
        return;
    }

    const now = new Date();
    const date = now.toLocaleDateString("km-KH", { day: "2-digit", month: "2-digit", year: "numeric" });
    const time = now.toLocaleTimeString("km-KH", { hour12: true, hour: "2-digit", minute: "2-digit", second: "2-digit" });
    const location = `Lat: ${scannedLocation.latitude}, Long: ${scannedLocation.longitude}`;
    const address = await getAddress(scannedLocation.latitude, scannedLocation.longitude);

    const formData = new FormData();
    formData.append("ឈ្មោះ", elements.userName.value);
    formData.append("ID", elements.userIdEl.value);
    formData.append("ប្រភេទស្កេន", elements.actionSelect.value);
    formData.append("ថ្ងៃ", date);
    formData.append("ម៉ោង", time);
    formData.append("location", location);
    formData.append("address", address);
    formData.append("សាខា", elements.branchSelect.value);
    formData.append("folder", elements.userFolderEl.value);
    formData.append("Department", elements.userDepartmentEl.value);
    formData.append("Position", elements.userPositionEl.value);
    formData.append("workplace", elements.userWorkplaceEl.value);
    formData.append("token", (await getFromDB('loggedInUser', 'user')).token);
    formData.append("status", "Good");
    formData.append("early_reason", reason);
    formData.append("location_name", scannedLocation.name);
    await submitScan(formData, date, time, location, address, 'Good', 0);
    elements.earlyScanPopup.style.display = 'none';
    autoStopCamera();
});

elements.cancelEarlyScan.addEventListener('click', () => {
    elements.earlyScanPopup.style.display = 'none';
    elements.scanButton.style.display = 'block';
    elements.scanButton.disabled = !locationReady;
    elements.scanSpinner.style.display = 'none';
    showStatus(elements.statusEl, `បានជ្រើស ${elements.actionSelect.value}`);
    autoStopCamera();
});

async function sendToTelegram(message) {
    const user = await getFromDB('loggedInUser', 'user');
    try {
        const response = await fetch('api.php?action=send_telegram', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message,
                department: elements.userDepartmentEl.value || 'specialist',
                token: user.token,
                parse_mode: 'Markdown'
            })
        });
        const result = await response.json();
        if (result.status !== 'success') throw new Error(result.message || 'Telegram send failed');
        console.log('Telegram message sent successfully');
        return true;
    } catch (error) {
        console.error('Telegram send error:', error.message);
        throw error;
    }
}

async function getCurrentPosition(options = { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) reject(new Error('មិនគាំទ្រការទទួល GPS!'));
        else navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });
}

async function fetchWithRetry(url, retries = 3, delay = 1000) {
    for (let i = 0; i < retries; i++) {
        try {
            const response = await fetch(url);
            if (response.ok) return response;
            console.warn(`Fetch attempt ${i + 1} failed: ${response.status}`);
        } catch (e) {
            console.error(`Fetch attempt ${i + 1} error:`, e);
        }
        if (i < retries - 1) await new Promise(resolve => setTimeout(resolve, delay));
    }
    throw new Error('Failed to fetch after retries');
}

function checkForUpdates() {
    fetch(`/version.json?v=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
            if (data.version !== APP_VERSION) {
                navigator.serviceWorker.getRegistration().then(registration => {
                    registration.update().then(() => showUpdateNotification(registration));
                });
            }
        })
        .catch(error => console.error('Update check failed:', error));
}

async function showUpdateNotification(registration) {
    await retryQueuedScans();
    showPopup('មានកំណែថ្មី! កំពុងធ្វើបច្ចុប្បន្នភាព...', 'success');
    setTimeout(() => {
        registration.waiting?.postMessage({ type: 'CHECK_UPDATE' });
        window.location.reload();
    }, 3000);
}

async function restoreStateAfterUpdate() {
    const lastState = await getFromDB('lastState', 'state');
    if (lastState) {
        await Promise.all([
            putToDB('loggedInUser', lastState.user),
            putToDB('lastScanType', { key: 'scanType', value: lastState.lastScanType }),
            ...(lastState.queuedScans?.map(scan => putToDB('scanQueue', scan)) || []),
            deleteFromDB('lastState', 'state')
        ]);
        lastScanType = lastState.lastScanType;
        locationReady = lastState.locationReady;
        showScanSection(lastState.user);
        retryQueuedScans();
    }
}

function sanitizeInput(input) {
    const div = document.createElement('div');
    div.textContent = input;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

setInterval(async () => {
    try {
        const now = Date.now();
        const scans = await getFromDB('scanQueue');
        const sevenDays = 7 * 24 * 60 * 60 * 1000;
        for (const scan of scans) {
            if (scan.id && now - scan.id > sevenDays) await deleteFromDB('scanQueue', scan.id);
        }

        const addressCache = await getFromDB('addressCache');
        const twoDays = 2 * 24 * 60 * 60 * 1000;
        for (const entry of addressCache) {
            if (entry.timestamp && now - entry.timestamp > twoDays) await deleteFromDB('addressCache', entry.key);
        }

        const lastState = await getFromDB('lastState', 'state');
        if (lastState && lastState.user && lastState.user.token && lastState.user.id && lastState.timestamp && now - lastState.timestamp > sevenDays) {
            await deleteFromDB('lastState', 'state');
        }
    } catch {}
}, 10000);

/**
 * Speed up PWA startup: show scan section instantly if user is logged in,
 * even before all async data is loaded.
 * Also hide the splash screen/logo ASAP on Android.
 */
(function fastPWAStartup() {
    if (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true
    ) {
        document.addEventListener('DOMContentLoaded', async () => {
            // Hide Android splash screen ASAP (if supported)
            if (navigator.splashscreen && navigator.splashscreen.hide) {
                try { navigator.splashscreen.hide(); } catch {}
            }
            // For Chrome PWA, try to force paint ASAP
            document.body.style.display = '';
            try {
                await openDB();
                const user = await getFromDB('loggedInUser', 'user');
                if (user && user.token) {
                    elements.loginSection.style.display = 'none';
                    elements.scanSection.style.display = 'block';
                    // Fill minimal user info instantly
                    elements.userName.value = user.username || '';
                    elements.userIdEl.value = user.id || '';
                    elements.userDepartmentEl.value = user.department || '';
                    elements.userPositionEl.value = user.position || '';
                    elements.userFolderEl.value = user.folder || '';
                    elements.userWorkplaceEl.value = user.workplace || '';
                    // Don't wait for all async data to finish
                    setTimeout(() => {
                        showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!');
                        elements.scanButton.disabled = true;
                    }, 0);
                }
            } catch {}
        });
        // Extra: force repaint to hide splash on some Android PWAs
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 10);
        });
    }
})();

/**
 * Disable scan button for 2 minutes after a successful scan.
 */
let scanCooldownTimer = null;
let scanCooldownEnd = null;

function setScanCooldown(minutes = 2) {
    const now = Date.now();
    scanCooldownEnd = now + minutes * 60 * 1000;
    localStorage.setItem('scanCooldownEnd', scanCooldownEnd.toString());
    updateScanCooldownUI();
    if (scanCooldownTimer) clearInterval(scanCooldownTimer);
    scanCooldownTimer = setInterval(updateScanCooldownUI, 1000);
}

function clearScanCooldown() {
    scanCooldownEnd = null;
    localStorage.removeItem('scanCooldownEnd');
    if (scanCooldownTimer) clearInterval(scanCooldownTimer);
    updateScanCooldownUI();
}

function updateScanCooldownUI() {
    const now = Date.now();
    const btn = document.getElementById('quickCheckBtn');
    if (scanCooldownEnd && now < scanCooldownEnd) {
        const remaining = Math.max(0, scanCooldownEnd - now);
        const min = Math.floor(remaining / 60000);
        const sec = Math.floor((remaining % 60000) / 1000);
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-qrcode"></i> ចាប់ផ្តើមស្កេន Check-In/Out (${min}:${sec.toString().padStart(2, '0')})`;
        }
    } else {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-qrcode"></i> ចាប់ផ្តើមស្កេន Check-In/Out';
        }
        if (scanCooldownTimer) clearInterval(scanCooldownTimer);
    }
}

// Hook into submitScan to set cooldown after scan
const _originalSubmitScan = submitScan;
submitScan = async function (...args) {
    await _originalSubmitScan.apply(this, args);
    setScanCooldown(2);
};

// Restore cooldown on page load
window.addEventListener('DOMContentLoaded', () => {
    const cooldown = parseInt(localStorage.getItem('scanCooldownEnd'), 10);
    if (cooldown && Date.now() < cooldown) {
        scanCooldownEnd = cooldown;
        updateScanCooldownUI();
        scanCooldownTimer = setInterval(updateScanCooldownUI, 1000);
    }
});


/**
 * Disable QR scanning for 09 minutes after a successful scan.
 * Prevent camera from scanning and show countdown until time is up.
 */
// let scanCooldownTimer = null;
// let scanCooldownEnd = null;

// function setScanCooldown(minutes = 3) {
//     const now = Date.now();
//     scanCooldownEnd = now + minutes * 60 * 1000;
//     localStorage.setItem('scanCooldownEnd', scanCooldownEnd.toString());
//     updateScanCooldownUI();
//     if (scanCooldownTimer) clearInterval(scanCooldownTimer);
//     scanCooldownTimer = setInterval(updateScanCooldownUI, 1000);
// }

// function clearScanCooldown() {
//     scanCooldownEnd = null;
//     localStorage.removeItem('scanCooldownEnd');
//     if (scanCooldownTimer) clearInterval(scanCooldownTimer);
//     updateScanCooldownUI();
// }

//   function updateScanCooldownUI() {
//     const now = Date.now();
//     if (scanCooldownEnd && now < scanCooldownEnd) {
//         const remaining = Math.max(0, scanCooldownEnd - now);
//         const min = Math.floor(remaining / 60000);
//         const sec = Math.floor((remaining % 60000) / 1000);
//         const progress = (remaining / (10 * 60 * 1000)) * 100;

//         elements.scanButton.disabled = true;
//         elements.scanButton.innerHTML = `
//             <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true"></span>
//             <i class="fa-solid fa-fingerprint"></i> ចាប់ផ្តើមស្កេន (${min}:${sec.toString().padStart(2, '0')})
//         `;

//         if (elements.qrScannerContainer) {
//             elements.qrScannerContainer.style.pointerEvents = 'none';
//             const video = elements.qrScannerContainer.querySelector('video');
//             if (video) video.style.display = 'none';

//             let overlay = elements.qrScannerContainer.querySelector('.cooldown-overlay');
//             if (!overlay) {
//                 overlay = document.createElement('div');
//                 overlay.className = 'cooldown-overlay';
//                 overlay.style.position = 'absolute';
//                 overlay.style.top = '0';
//                 overlay.style.left = '0';
//                 overlay.style.width = '100%';
//                 overlay.style.height = '100%';
//                 overlay.style.background = 'rgba(255,255,255,0.85)';
//                 overlay.style.zIndex = '99';
//                 overlay.style.display = 'flex';
//                 overlay.style.alignItems = 'center';
//                 overlay.style.justifyContent = 'center';
//                 overlay.style.flexDirection = 'column';
//                 overlay.innerHTML = `
//                     <div style="width: 100px; height: 100px; position: relative;">
//                         <svg width="100" height="100">
//                             <circle cx="50" cy="50" r="45" stroke="#e74c3c" stroke-width="8" fill="none"/>
//                             <circle cx="50" cy="50" r="45" stroke="#27ae60" stroke-width="8" fill="none" 
//                                     stroke-dasharray="283" stroke-dashoffset="${283 * (1 - (100 - progress) / 100)}"
//                                     style="transition: stroke-dashoffset 1s linear;"/>
//                         </svg>
//                         <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 1.2rem; color: #e74c3c;">
//                             ${min}:${sec.toString().padStart(2, '0')}
//                         </div>
//                     </div>
//                     <span style="margin-top: 10px; font-size: 1.1rem; color: #e74c3c;">សូមរង់ចាំមុនស្កេនបន្ទាប់</span>
//                 `;
//                 elements.qrScannerContainer.appendChild(overlay);
//             } else {
//                 overlay.querySelector('circle:nth-child(2)').setAttribute('stroke-dashoffset', 283 * (1 - (100 - progress) / 100));
//                 overlay.querySelector('div:nth-child(2)').textContent = `${min}:${sec.toString().padStart(2, '0')}`;
//             }
//         }
//         showStatus(elements.statusEl, `សូមរង់ចាំ ${min} នាទី ${sec} វិនាទី មុនស្កេនបន្ទាប់`, 'error');
//     } else {
//         elements.scanButton.disabled = !locationReady;
//         elements.scanButton.innerHTML = `
//             <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true"></span>
//             <i class="fa-solid fa-fingerprint"></i> ចាប់ផ្តើមស្កេន
//         `;
//         if (elements.qrScannerContainer) {
//             elements.qrScannerContainer.style.pointerEvents = '';
//             const video = elements.qrScannerContainer.querySelector('video');
//             if (video) video.style.display = '';
//             const overlay = elements.qrScannerContainer.querySelector('.cooldown-overlay');
//             if (overlay) overlay.remove();
//         }
//         if (scanCooldownTimer) clearInterval(scanCooldownTimer);
//         showStatus(elements.statusEl, `សូមជ្រើស ${elements.actionSelect.value}`);
//     }
// }

// // // Hook into submitScan to set cooldown after scan
// const _originalSubmitScan = submitScan;
// submitScan = async function (...args) {
//     await _originalSubmitScan.apply(this, args);
//     setScanCooldown(3);
// };

// // // On page load, restore cooldown if present
// window.addEventListener('DOMContentLoaded', () => {
//     const cooldown = parseInt(localStorage.getItem('scanCooldownEnd'), 3);
//     if (cooldown && Date.now() < cooldown) {
//         scanCooldownEnd = cooldown;
//         updateScanCooldownUI();
//         scanCooldownTimer = setInterval(updateScanCooldownUI, 1000);
//     }
// });
