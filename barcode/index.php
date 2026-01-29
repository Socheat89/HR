<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>កម្មវិធី​ស្កេន Barcode រក​ប្រទេស​ផលិត</title>

    <!-- PWA: Link to Manifest -->
    <link rel="manifest" href="manifest.json">
    <!-- PWA: Theme Color for browser UI -->
    <meta name="theme-color" content="#0c66ee">
    <!-- PWA: Apple Touch Icon for iOS -->
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="text/javascript" src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
    <style>
        body {
            font-family: 'Kantumruy Pro', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-color: #f0f2f5;
        }
        .card { transition: all 0.3s ease-in-out; }
        .card-enter { opacity: 0; transform: translateY(20px); }
        .card-enter-active { opacity: 1; transform: translateY(0); }
        .card-exit { opacity: 1; transform: translateY(0); }
        .card-exit-active { opacity: 0; transform: translateY(-20px); position: absolute; }
        .viewfinder-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; border-radius: 1rem; box-shadow: 0 0 0 4000px rgba(0, 0, 0, 0.6); z-index: 10; }
        .viewfinder-box { position: absolute; top: 50%; left: 50%; width: 90%; height: 40%; max-height: 220px; transform: translate(-50%, -50%); border-radius: 1rem; }
        .viewfinder-line { position: absolute; top: 50%; left: 5%; right: 5%; height: 2px; background: linear-gradient(to right, transparent, rgba(255, 75, 75, 0.9), transparent); box-shadow: 0 0 10px rgba(255, 0, 0, 0.8); border-radius: 1px; animation: scan-line 2s infinite ease-in-out; }
        .viewfinder-corner { position: absolute; width: 25px; height: 25px; border: 4px solid white; }
        .top-left { top: -4px; left: -4px; border-right: none; border-bottom: none; border-top-left-radius: 14px; }
        .top-right { top: -4px; right: -4px; border-left: none; border-bottom: none; border-top-right-radius: 14px; }
        .bottom-left { bottom: -4px; left: -4px; border-right: none; border-top: none; border-bottom-left-radius: 14px; }
        .bottom-right { bottom: -4px; right: -4px; border-left: none; border-top: none; border-bottom-right-radius: 14px; }
        @keyframes scan-line { 0% { transform: translateY(-45%); } 50% { transform: translateY(45%); } 100% { transform: translateY(-45%); } }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <!-- UI Code from previous step remains the same here... -->
    <div class="relative max-w-md w-full">
        <!-- Scanner UI -->
        <div id="scanner-ui" class="bg-white p-6 md:p-8 rounded-2xl shadow-xl w-full card">
            <h1 class="text-2xl md:text-3xl font-bold text-center mb-2 text-gray-800">
                កម្មវិធី​ស្កេន Barcode
            </h1>
            <p class="text-center text-gray-500 mb-6">ស្វែងរក​ប្រទេស​ផលិត​របស់​ផលិតផល</p>

            <div class="relative w-full mb-4 rounded-2xl overflow-hidden bg-gray-900 aspect-video">
                <video id="video" class="w-full h-full object-cover block" playsinline></video>
                <div id="viewfinder-container" class="hidden">
                    <div class="viewfinder-overlay"></div>
                    <div class="viewfinder-box">
                        <div class="viewfinder-line"></div>
                        <div class="viewfinder-corner top-left"></div>
                        <div class="viewfinder-corner top-right"></div>
                        <div class="viewfinder-corner bottom-left"></div>
                        <div class="viewfinder-corner bottom-right"></div>
                    </div>
                </div>
            </div>
            
            <p id="status-text" class="text-center text-gray-600 my-4 h-6 font-medium"></p>

            <div class="flex items-center space-x-3">
                <button id="startButton" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 shadow-md hover:shadow-lg">
                    ចាប់​ផ្តើម​ស្កេន
                </button>
                <button id="torchButton" class="hidden flex-shrink-0 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold p-3 rounded-lg transition-colors">
                    <svg id="torch-icon-off" class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.243 4.243a3 3 0 01-4.243-4.243" /></svg>
                    <svg id="torch-icon-on" class="w-6 h-6 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.311a14.994 14.994 0 01-4.5 0M9.75 15.75A3 3 0 0112 12.75a3 3 0 012.25 3M12 12.75v-1.5m0 1.5a6.01 6.01 0 00-1.5-.189m1.5.189a6.01 6.01 0 011.5-.189m-1.5 5.25a6.01 6.01 0 00-1.5.189m1.5-.189a6.01 6.01 0 011.5.189M12 6.75a2.25 2.25 0 110 4.5 2.25 2.25 0 010-4.5z" /></svg>
                </button>
            </div>
        </div>
        <!-- Result Card UI -->
        <div id="result-ui" class="bg-white p-6 md:p-8 rounded-2xl shadow-xl w-full card hidden">
             <div class="text-center">
                <div class="mx-auto bg-green-100 rounded-full h-16 w-16 flex items-center justify-center mb-4 ring-4 ring-green-50">
                    <svg class="h-10 w-10 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4">ស្កេន​បាន​ជោគជ័យ!</h2>
            </div>
            <div class="space-y-4 bg-gray-50 p-4 rounded-lg border">
                <div class="flex items-center space-x-3">
                    <div class="bg-gray-200 p-2 rounded-full"><svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 4h16v2H1zM1 9h16v2H1zM1 14h16v2H1z"/></svg></div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">លេខកូដ Barcode:</p>
                        <p id="barcode-result-text" class="text-lg font-bold text-gray-900 font-mono"></p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="bg-blue-100 p-2 rounded-full"><svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A11.953 11.953 0 0012 13.5c2.998 0 5.74 1.1 7.843 2.918m-15.686 0A8.959 8.959 0 003 12c0-.778.099 1.533.284-2.253m0 0" /></svg></div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">ផលិតផល​ពី​ប្រទេស:</p>
                        <p id="country-result-text" class="text-2xl font-bold text-blue-600"></p>
                    </div>
                </div>
            </div>
            <button id="scanAgainButton" class="mt-6 w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                ស្កេន​ម្ដង​ទៀត
            </button>
        </div>
    </div>
    
    <script>
        // All the previous JavaScript code remains the same here...
        const scannerUI = document.getElementById('scanner-ui');
        const resultUI = document.getElementById('result-ui');
        const videoElement = document.getElementById('video');
        const startButton = document.getElementById('startButton');
        const scanAgainButton = document.getElementById('scanAgainButton');
        const statusText = document.getElementById('status-text');
        const barcodeResultText = document.getElementById('barcode-result-text');
        const countryResultText = document.getElementById('country-result-text');
        const viewfinderContainer = document.getElementById('viewfinder-container');
        const torchButton = document.getElementById('torchButton');
        const torchIconOn = document.getElementById('torch-icon-on');
        const torchIconOff = document.getElementById('torch-icon-off');
        const codeReader = new ZXing.BrowserMultiFormatReader();
        let stream = null;
        let torchOn = false;
        let videoTrack = null;
        const gs1Prefixes = {'000-019': '🇺🇸 សហរដ្ឋអាមេរិក & 🇨🇦 កាណាដា', '020-029': 'ប្រើប្រាស់​ក្នុង​ហាង', '030-039': '🇺🇸 សហរដ្ឋអាមេរិក & 🇨🇦 កាណាដា', '060-139': '🇺🇸 សហរដ្ឋអាមេរិក & 🇨🇦 កាណាដា', '300-379': '🇫🇷 បារាំង & 🇲🇨 ម៉ូណាកូ', '400-440': '🇩🇪 អាល្លឺម៉ង់', '450-459': '🇯🇵 ជប៉ុន', '460-469': '🇷🇺 រុស្ស៊ី', '471': '🇹🇼 តៃវ៉ាន់', '480': '🇵🇭 ហ្វីលីពីន', '482': '🇺🇦 អ៊ុយក្រែន', '489': '🇭🇰 ហុងកុង', '490-499': '🇯🇵 ជប៉ុន', '500-509': '🇬🇧 ចក្រភពអង់គ្លេស', '520-521': '🇬🇷 ក្រិក', '539': '🇮🇪 អៀរឡង់', '540-549': '🇧🇪 បែលហ្ស៊ិក & 🇱🇺 លុចសំបួរ', '560': '🇵🇹 ព័រទុយហ្គាល់', '570-579': '🇩🇰 ដាណឺម៉ាក', '590': '🇵🇱 ប៉ូឡូញ', '594': '🇷🇴 រូម៉ានី', '600-601': '🇿🇦 អាហ្វ្រិកខាងត្បូង', '611': '🇲🇦 ម៉ារ៉ុក', '613': '🇩🇿 អាល់ហ្សេរី', '626': '🇮🇷 អ៊ីរ៉ង់', '628': '🇸🇦 អារ៉ាប៊ីសាអូឌីត', '629': '🇦🇪 អេមីរ៉ាតអារ៉ាប់រួម', '640-649': '🇫🇮 ហ្វាំងឡង់', '690-699': '🇨🇳 ចិន', '700-709': '🇳🇴 ន័រវែស', '729': '🇮🇱 អ៊ីស្រាអែល', '730-739': '🇸🇪 ស៊ុយអែត', '759': '🇻🇪 វ៉េណេស៊ុយអេឡា', '760-769': '🇨🇭 ស្វីស & 🇱🇮 លិកតិនស្តាញ', '770-771': '🇨🇴 កូឡុំប៊ី', '773': '🇺🇾 អ៊ុយរូហ្គាយ', '779': '🇦🇷 អាហ្សង់ទីន', '786': '🇪🇨 អេក្វាឌ័រ', '789-790': '🇧🇷 ប្រេស៊ីល', '800-839': '🇮🇹 អ៊ីតាលី', '840-849': '🇪🇸 អេស្ប៉ាញ', '850': '🇨🇺 គុយបា', '858': '🇸🇰 ស្លូវ៉ាគី', '859': '🇨🇿 សាធារណរដ្ឋឆែក', '860': '🇷🇸 ស៊ែប៊ី', '867': '🇰🇵 កូរ៉េខាងជើង', '869': '🇹🇷 តួកគី', '870-879': '🇳🇱 ហូឡង់', '880': '🇰🇷 កូរ៉េខាងត្បូង', '884': '🇰🇭 កម្ពុជា', '885': '🇹🇭 ថៃ', '888': '🇸🇬 សិង្ហបុរី', '890': '🇮🇳 ឥណ្ឌា', '893': '🇻🇳 វៀតណាម', '899': '🇮🇩 ឥណ្ឌូណេស៊ី', '900-919': '🇦🇹 អូទ្រីស', '930-939': '🇦🇺 អូស្ត្រាលី', '940-949': '🇳🇿 នូវែលសេឡង់', '955': '🇲🇾 ម៉ាឡេស៊ី', '958': '🇲🇴 ម៉ាកាវ'};
        function getCountryFromBarcode(barcode) { const prefix = barcode.substring(0, 3); for (const range in gs1Prefixes) { if (range.includes('-')) { const [start, end] = range.split('-').map(Number); if (Number(prefix) >= start && Number(prefix) <= end) return gs1Prefixes[range]; } else if (prefix.startsWith(range)) return gs1Prefixes[range]; } return 'រក​មិន​ឃើញ​ព័ត៌មាន​ប្រទេស'; }
        function switchUI(showScanner) { scannerUI.classList.toggle('card-exit', !showScanner); scannerUI.classList.toggle('card-exit-active', !showScanner); resultUI.classList.toggle('card-enter', showScanner); setTimeout(() => { scannerUI.classList.toggle('hidden', !showScanner); resultUI.classList.toggle('hidden', showScanner); scannerUI.classList.toggle('card-enter-active', showScanner); resultUI.classList.remove('card-enter'); resultUI.classList.add('card-enter-active'); }, 50); }
        async function stopScan() { if (videoTrack && torchOn) { await videoTrack.applyConstraints({ advanced: [{ torch: false }] }); torchOn = false; updateTorchIcon(); } codeReader.reset(); if (stream) { stream.getTracks().forEach(track => track.stop()); stream = null; } videoElement.srcObject = null; viewfinderContainer.classList.add('hidden'); torchButton.classList.add('hidden'); }
        async function startScan() { try { switchUI(true); startButton.classList.add('hidden'); statusText.textContent = '⏳ កំពុង​ស្នើ​សុំ​បើក​កាមេរ៉ា...'; const constraints = { video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 }, advanced: [{torch: false}, {focusMode: 'continuous'}] } }; stream = await navigator.mediaDevices.getUserMedia(constraints); videoElement.srcObject = stream; videoTrack = stream.getVideoTracks()[0]; const capabilities = videoTrack.getCapabilities(); if (capabilities.torch) { torchButton.classList.remove('hidden'); } await videoElement.play(); viewfinderContainer.classList.remove('hidden'); statusText.textContent = '🔎 សូម​ដាក់ Barcode នៅ​ក្នុង​ប្រអប់'; codeReader.decodeFromStream(stream, videoElement, (result, error) => { if (result) { stopScan(); new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU'+Array(1e3).join('12121313')).play(); barcodeResultText.textContent = result.getText(); countryResultText.textContent = getCountryFromBarcode(result.getText()); switchUI(false); } if (error && !(error instanceof ZXing.NotFoundException)) { console.error('ZXing Decode Error:', error); } }); } catch (err) { console.error('Camera Access Error:', err); if (err.name === "NotAllowedError") { statusText.textContent = '🚫 អ្នក​មិន​បាន​អនុញ្ញាត​ឱ្យ​បើក​កាមេរ៉ា'; } else { statusText.textContent = '❌ បរាជ័យ​ក្នុង​ការ​បើក​កាមេរ៉ា'; } startButton.classList.remove('hidden'); } }
        function updateTorchIcon() { torchIconOn.classList.toggle('hidden', !torchOn); torchIconOff.classList.toggle('hidden', torchOn); }
        torchButton.addEventListener('click', async () => { if (videoTrack) { torchOn = !torchOn; await videoTrack.applyConstraints({ advanced: [{ torch: torchOn }] }); updateTorchIcon(); } });
        startButton.addEventListener('click', startScan);
        scanAgainButton.addEventListener('click', startScan);

        // --- PWA: Register Service Worker ---
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').then(registration => {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, err => {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>