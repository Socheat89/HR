import { showStatus, elements } from 'ui.js';

let qrScanner = null;
let qrScanCanvas = null;
let lastScannedQR = '';
let lastScannedAt = 0;
const QR_DUPLICATE_INTERVAL = 2000;

export function initializeQRScanner() {
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
                qrScanner = setInterval(() => scanQRCode(video), 100); // ប្តូរទៅ 100ms
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

    if (!window.jsQR) {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
        script.onload = tryNextConstraint;
        script.onerror = () => showStatus(elements.statusEl, 'មិនអាចផ្ទុក QR Scanner បាន!', 'error');
        document.head.appendChild(script);
    } else {
        tryNextConstraint();
    }
}

export function stopQRScanner() {
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

function scanQRCode(video) {
    if (!video || video.readyState !== video.HAVE_ENOUGH_DATA) return;
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
        clearInterval(qrScanner);
        try {
            if (code.data.startsWith('geo:')) {
                const [latitude, longitude] = code.data.replace('geo:', '').split(',').map(parseFloat);
                if (isNaN(latitude) || isNaN(longitude)) throw new Error('Invalid latitude or longitude');
                window.scannedLocation = { latitude, longitude };
                window.locationReady = true;
                // validateQRCodeLocation នឹងត្រូវហៅនៅ main.js
                showCameraUIAfterScan();
            } else {
                throw new Error('Invalid QR code format');
            }
        } catch (error) {
            showStatus(elements.statusEl, 'QR Code មិនត្រឹមត្រូវ!', 'error');
            window.locationReady = false;
            elements.scanButton.disabled = true;
        }
    }
}

function showCameraUIAfterScan() {
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
        stopQRScanner();
    }, 1200);
}

function addFlashlightToggle(video) {
    let existing = document.getElementById('flashlightToggleBtn');
    if (existing) existing.remove();
    const stream = video.srcObject;
    if (!stream) return;
    const track = stream.getVideoTracks()[0];
    if (!track || !track.getCapabilities || !track.getCapabilities().torch) return;

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
    elements.qrScannerContainer.appendChild(btn);
}