<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ប្រព័ន្ធស្កេនចូល/ចេញសម្រាប់តាមដានវត្តមាន" />
    <meta name="app-version" content="1.0.3" />
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#3b5998">
    <style>
        /* Fallback for browsers that do not support theme-color */
        body {
            background-color: #3b5998;
        }
    </style>
    <link rel="apple-touch-icon" href="https://cdn-icons-png.flaticon.com/512/16461/16461372.png">
    <meta name="msapplication-TileColor" content="#3b5998">
    <title>ប្រព័ន្ធស្កេនចូល/ចេញ</title>
    <link rel="manifest" href="manifest-2.json?v=1.0.3">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css?v=1.0.1"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Khmer&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css?v=1.0.1" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&family=Noto+Sans+Khmer:wght@100..900&display=swap');
    </style>
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/16461/16461372.png" type="image/png">
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link rel="stylesheet" href="global-fingerprint.css">
     <script defer src="global-fingerprint.js"></script>
</head>

<body>



    <div class="container">
        
        <div class="card" id="loginSection">
            <div class="card-body">
                <h1>ចូលប្រើប្រព័ន្ធ</h1>
                <form id="loginForm" class="mb-3">
                    <div class="mb-3">
                        <label for="loginId" class="form-label">ID</label>
                        <input type="text" class="form-control" id="loginId" placeholder="បញ្ចូល ID របស់អ្នក" required
                            autocomplete="off">
                    </div>
                    <button type="submit" id="loginButton" class="btn btn-primary" autocomplete="off">
                        <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true" style="display:none;"></span>
                        ចូល
                    </button>
                </form>
                <p id="loginStatus" class="status-message"></p>
            </div>
        </div>

   <header class="custom-header">
      <div class="header-content">
        <img src="https://i.ibb.co/qLg5cVXS/Logo-Van-Van-3.png" alt="Logo">
        <div style="flex:1;">
          <span class="header-title">ប្រព័ន្ធស្កេនចូល/ចេញ</span>
          <div class="header-desc">សម្រាប់តាមដានវត្តមាន</div>
        </div>
        <!-- Clock Display -->
        <div id="headerClock" style="color:#fff;font-size:1.08rem;font-weight:600;min-width:90px;text-align:right;letter-spacing:0.01em;display:flex;align-items:center;gap:0.4em;flex-direction:column;align-items:flex-end;">
          <div style="display:flex;align-items:center;gap:0.4em;">
            <i class="fa-regular fa-clock" style="font-size:1.15em;opacity:0.85;"></i>
            <span id="clockTime" style="font-family:'Kantumruy Pro', 'Noto Sans Khmer', 'Khmer', Arial,sans-serif;letter-spacing:0.02em;background:rgba(255,255,255,0.13);padding:0.18em 0.7em;border-radius:0.7em;box-shadow:0 1px 4px #0001;font-size:1.08em;"></span>
          </div>
          <span id="clockDate" style="font-size:0.92em;color:#eaf6fb;opacity:0.85;margin-top:0.1em;font-weight:500;letter-spacing:0.01em;"></span>
        </div>
        <style>
          #headerClock {
            user-select: none;
            transition: background 0.18s;
          }
          #headerClock #clockTime {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 1px 4px #0002;
            letter-spacing: 0.03em;
            transition: background 0.18s, color 0.18s;
          }
          #headerClock #clockDate {
            font-size: 0.92em;
            color: #eaf6fb;
            opacity: 0.85;
            margin-top: 0.1em;
            font-weight: 500;
            letter-spacing: 0.01em;
          }
          @media (max-width: 600px) {
            #headerClock {
              font-size: 0.98rem;
              min-width: 70px;
            }
            #headerClock #clockTime {
              font-size: 0.98em;
              padding: 0.13em 0.5em;
            }
            #headerClock #clockDate {
              font-size: 0.85em;
            }
          }
        </style>
        <script>
          function updateHeaderClock() {
            const el = document.getElementById('clockTime');
            const dateEl = document.getElementById('clockDate');
            if (el) {
              const now = new Date();
              let h = now.getHours();
              let m = now.getMinutes();
              let s = now.getSeconds();
              const ampm = h >= 12 ? 'PM' : 'AM';
              h = h % 12;
              if (h === 0) h = 12;
              el.textContent = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')} ${ampm}`;
            }
            if (dateEl) {
              const now = new Date();
              // Khmer date format: ថ្ងៃទី dd ខែ mm ឆ្នាំ yyyy
              const days = ['អាទិត្យ','ច័ន្ទ','អង្គារ','ពុធ','ព្រហស្បតិ៍','សុក្រ','សៅរ៍'];
              const months = ['មករា','កម្ភៈ','មិនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
              const day = days[now.getDay()];
              const d = now.getDate().toString().padStart(2, '0');
              const m = months[now.getMonth()];
              const y = now.getFullYear();
              dateEl.textContent = `ថ្ងៃ${day} ទី${d} ខែ${m} ឆ្នាំ${y}`;
            }
          }
          document.addEventListener('DOMContentLoaded', function () {
            updateHeaderClock();
            setInterval(updateHeaderClock, 1000);

            // Hide header on login page
            const loginSection = document.getElementById('loginSection');
            const header = document.querySelector('header.custom-header');
            function updateHeaderVisibility() {
              if (loginSection && header) {
          if (loginSection.style.display === 'none') {
            header.style.display = '';
          } else {
            header.style.display = 'none';
          }
              }
            }
            updateHeaderVisibility();
            const observer = new MutationObserver(updateHeaderVisibility);
            observer.observe(loginSection, { attributes: true, attributeFilter: ['style', 'class'] });
            window.addEventListener('resize', updateHeaderVisibility);
          });
        </script>

      </div>
    </header>
    <style>
      .custom-header {
        width: 100%;
        max-width: 100vw;
        position: relative;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.2rem;
      }

      .header-content {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 1.1rem 1.2rem;
        width: 100%;
        max-width: 480px;
        background: linear-gradient(90deg, #4A90E2 60%, #226694 100%);
        border-radius: 1.2rem;
        box-shadow: 0 2px 8px rgba(44, 62, 80, 0.07);
      }

      .header-content img {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 4px #0001;
      }

      .header-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
        letter-spacing: -0.01em;
        display: block;
      }

      .header-desc {
        font-size: 0.95rem;
        color: #eaf6fb;
        font-weight: 500;
      }

      .header-version {
        font-size: 0.85rem;
        color: #eaf6fb;
        font-weight: 500;
        margin-left: auto;
        text-align: right;
        opacity: 0.85;
        line-height: 1.1;
      }

      @media (max-width: 600px) {
        .header-content {
          padding: 0.8rem 0.7rem;
          max-width: 100vw;
        }

        .header-content img {
          width: 34px;
          height: 34px;
        }

        .header-title {
          font-size: 1.05rem;
        }
      }
    </style>
    

        <div class="card" id="scanSection" style="margin-top: 1rem;">
            <div class="card-body">
                <!-- <h1>ប្រព័ន្ធស្កេនចូល/ចេញ</h1> -->
                <hr style="border: none; border-top: 2px solid #4A90E2; margin: 1rem 0 1.5rem 0;">
                <button id="logoutButton" class="btn btn-danger mb-3 d-none">ចាកចេញ</button>
                <div class="mb-3 d-none">
                    <label class="form-label">ឈ្មោះ</label>
                    <input type="text" id="userName" class="form-control" readonly>
                </div>
                <div class="mb-3 d-none">
                    <label for="branchSelect" class="form-label">សាខា</label>
                    <select id="branchSelect" class="form-select">
                        <option value="">កំពុងរកសាខា...</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="actionSelect" class="form-label">ប្រភេទស្កេន</label>
                    <select id="actionSelect" class="form-select" required>
                        <option value="Check-In" selected>ស្កេនចូល</option>
                        <option value="Check-Out">ស្កេនចេញ</option>
                    </select>
                </div>
                <input type="hidden" id="userId">
                <input type="hidden" id="userDepartment">
                <input type="hidden" id="userPosition">
                <input type="hidden" id="userBranch">
                <input type="hidden" id="userFolder">
                <input type="hidden" id="userWorkplace">
                <button id="scanButton" class="btn btn-primary d-none" disabled>
                    <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true"></span>
                    <i class="fa-solid fa-fingerprint"></i> ចាប់ផ្តើមស្កេន
                </button>

                
                <div id="qrScannerContainer" style="
                    position: relative;
                    width: 100%;
                    max-width: 380px;
                    aspect-ratio: 1/1;
                    margin: 0 auto 1.2rem auto;
                    border-radius: 1.5rem;
                    box-shadow: 0 4px 24px rgba(44,62,80,0.10), 0 1.5px 6px rgba(74,144,226,0.07);
                    background: linear-gradient(135deg, #eaf6fb 60%, #d6eaff 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    border: 2.5px solid #4A90E2;
                    z-index: 10;
                    transition: box-shadow 0.2s;
                ">
                    <!-- QR Scanner video will be injected here -->
                    <div style="
                        position: absolute;
                        top: 4.5%;
                        left: 4.5%;
                        right: 4.5%;
                        bottom: 4.5%;
                        border: 2.5px dashed #4A90E2;
                        border-radius: 1.2rem;
                        pointer-events: none;
                        z-index: 2;
                        box-shadow: 0 2px 8px rgba(74,144,226,0.07);
                        background: rgba(255,255,255,0.04);
                    "></div>
                    <span style="
                        position: absolute;
                        bottom: 5.5%;
                        left: 0; right: 0;
                        text-align: center;
                        color: #2980b9;
                        font-size: 1.08rem;
                        font-weight: 600;
                        z-index: 3;
                        text-shadow: 0 2px 8px #fff, 0 1px 0 #eaf6fb;
                        pointer-events: none;
                        letter-spacing: 0.01em;
                        background: rgba(255,255,255,0.82);
                        border-radius: 0.8rem;
                        padding: 0.32rem 1.1rem 0.25rem 1.1rem;
                        margin: 0 8vw;
                        box-shadow: 0 1px 4px rgba(44,62,80,0.06);
                        backdrop-filter: blur(2px);
                    "></span>
                    <div style="
                        position: absolute;
                        left: 8.5%;
                        right: 8.5%;
                        top: 13%;
                        height: 4px;
                        background: linear-gradient(90deg, #4A90E2 0%, #6EC6FF 100%);
                        border-radius: 2px;
                        opacity: 0.85;
                        animation: scanLineAnim 1.7s linear infinite alternate;
                        z-index: 4;
                        pointer-events: none;
                    "></div>
           
                    <style>
                        @keyframes scanLineAnim {
                            0% {
                                top: 12vw;
                                opacity: 0.7;
                            }
                            100% {
                                top: calc(100% - 12vw - 4px);
                                opacity: 1;
                            }
                        }
                    </style>
                </div>
              
                <button id="viewLogsButton" class="btn btn-warning mt-2">
                    <i class="fa-solid fa-history"></i> មើលប្រវត្តិស្កេន
                </button>
                <button id="retryLocation" class="btn btn-secondary mt-2"
                    style="display:none;">សាកល្បងទីតាំងឡើងវិញ</button>
                <p id="status" class="status-message">សូមជ្រើសរើសប្រភេទស្កេន</p>
                <div class="action-info d-none" style="background: #f8fbff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(44,62,80,0.06); padding: 1.1rem 1.2rem; margin-bottom: 1.2rem; font-size: 1.07rem;">
                    <div style="display: flex; align-items: center; margin-bottom: 0.7rem;">
                        <i class="fa-solid fa-fingerprint" style="color: #4A90E2; font-size: 1.3rem; margin-right: 0.7rem;"></i>
                        <span style="font-weight: 600; color: #34495E; min-width: 110px;"></span>
                        <span id="action" style="margin-left: auto; color: #4A90E2; font-weight: 600;">N/A</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 0.7rem;">
                        <i class="fa-regular fa-calendar-check" style="color: #4A90E2; font-size: 1.2rem; margin-right: 0.7rem;"></i>
                        <span style="font-weight: 600; color: #34495E; min-width: 110px;"></span>
                        <span id="timestamp" style="margin-left: auto; color: #222;">N/A</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 0.7rem;">
                        <i class="fa-solid fa-location-dot" style="color: #4A90E2; font-size: 1.2rem; margin-right: 0.7rem;"></i>
                        <span style="font-weight: 600; color: #34495E; min-width: 110px;"></span>
                        <span id="location" style="margin-left: auto; color: #222;">កំពុងស្កេនទីតាំង</span>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <i class="fa-solid fa-map-location-dot" style="color: #4A90E2; font-size: 1.2rem; margin-right: 0.7rem;"></i>
                        <span style="font-weight: 600; color: #34495E; min-width: 110px;"></span>
                        <a id="mapLink" href="#" target="_blank" rel="noopener noreferrer" style="margin-left: auto; color: #4A90E2; font-weight: 600; text-decoration: underline;">View on Map</a>
                    </div>
                </div>
            </div>
        </div>



        <!-- Animated Sticker Up-Down -->
        <div id="stickerAnim"
            style="position:fixed;left:1.2rem;bottom:110px;z-index:1200;pointer-events:none;display:none;">
            <img id="stickerImgUpDown" src="https://media.tenor.com/WjlNymHcH3oAAAAi/love-bear-lovedina.gif"
                alt="Sticker"
                style="width:70px;height:70px;animation:stickerUpDown 2.2s ease-in-out infinite alternate;">
        </div>
        <!-- Animated Sticker Left-Right -->
        <div id="stickerAnimLR"
            style="position:fixed;right:1.2rem;bottom:110px;z-index:1200;pointer-events:none;display:none;">
            <img id="stickerImgLR" src="https://media.tenor.com/mo6Te6bSxEcAAAAi/quby-run.gif" alt="Sticker"
                style="width:70px;height:70px;animation:stickerLeftRight 2.5s ease-in-out infinite alternate;">
        </div>
        <script>
            // Sticker image arrays
            const stickerUpDownList = [
                "https://media.tenor.com/WjlNymHcH3oAAAAi/love-bear-lovedina.gif",
                "https://media.tenor.com/sTKDievU6fwAAAAi/love.gif",
                "https://media.tenor.com/kGFoKMN5ItkAAAAj/love.gif",
                "https://media.tenor.com/GPSeRW1WccoAAAAi/i-love-you-iloveyou.gif"
            ];
            const stickerLeftRightList = [
                "https://media.tenor.com/mo6Te6bSxEcAAAAi/quby-run.gif",
                "https://media.tenor.com/t72D550id6MAAAAi/hearts-hugs.gif",
                "https://media.tenor.com/aCWyBMhIQ14AAAAi/hug-love.gif",
                "https://media.tenor.com/QUFOQXsCouEAAAAi/pengu-pudgy.gif"
            ];
            let stickerUpDownIdx = 0;
            let stickerLeftRightIdx = 0;

            function rotateStickers() {
                const upDown = document.getElementById('stickerImgUpDown');
                const lr = document.getElementById('stickerImgLR');
                if (upDown) {
                    stickerUpDownIdx = (stickerUpDownIdx + 1) % stickerUpDownList.length;
                    upDown.src = stickerUpDownList[stickerUpDownIdx];
                }
                if (lr) {
                    stickerLeftRightIdx = (stickerLeftRightIdx + 1) % stickerLeftRightList.length;
                    lr.src = stickerLeftRightList[stickerLeftRightIdx];
                }
            }
            setInterval(rotateStickers, 3000);
        </script>
        <script>
            // Show/hide sticker by user ID or username
            async function controlStickersForUser() {
                await openDB();
                const user = await getFromDB('loggedInUser', 'user');
                // Example: show sticker only for user ID "12345" and hide for others
                // You can use username or any property you want
                const showStickerIds = ['0163', '0172', '0244', '0250', '0150', '0062', '0224', '0238', '0066', '0183']; // IDs to show sticker
                const hideStickerIds = ['123', '22222']; // IDs to always hide sticker

                if (user && showStickerIds.includes(user.id)) {
                    document.getElementById('stickerAnim').style.display = 'block';
                    document.getElementById('stickerAnimLR').style.display = 'block';
                } else if (user && hideStickerIds.includes(user.id)) {
                    document.getElementById('stickerAnim').style.display = 'none';
                    document.getElementById('stickerAnimLR').style.display = 'none';
                } else {
                    // Default: hide or show as you wish
                    document.getElementById('stickerAnim').style.display = 'none';
                    document.getElementById('stickerAnimLR').style.display = 'none';
                }
            }
            // Call after login or on page load
            window.addEventListener('DOMContentLoaded', controlStickersForUser);
        </script>

        <!-- Slide Content Section -->
        <div id="slideContainer"
            style="position:relative; width:100%; max-width:480px; margin:0 auto 1.5rem auto; min-height:90px; top: -0.5rem;">
            <div id="slideContent"
                style="font-size:18px; color:#222;  border-radius:1rem; box-shadow:0 2px 8px rgba(44,62,80,0.07); padding:1.2rem 1.5rem; min-height:70px; display:flex; align-items:center; justify-content:center; text-align:center; transition:opacity 0.5s;">
                <!-- Slide text will appear here -->
            </div>
        </div>
        <script>
            const slides = [
                "✅ខ្ញុំជាមនុស្សរីកចម្រើន និងញញឹមរីករាយ",
                "✳️ខ្ញុំជាមនុស្សមានគុណភាព",
                "❇️ខ្ញុំជាមនុស្សស្អាតបាត និងមានសណ្តាប់ធ្នាប់",
                "✳️ខ្ញុំជាមនុស្សមានផែនការច្បាស់លាស់",
                "❇️ខ្ញុំជាមនុស្សគោរពម៉ោងការងារ និងមានសកម្មភាពរហ័ស",
                "✅ខ្ញុំជាមនុស្សបន្តអភិវឌ្ឍ",
                "❇️ខ្ញុំជាមនុស្សមានសក្តានុពល",
                "✳️ខ្ញុំជាមនុស្សមិនខ្លាចនឿយហត់",
                "✅ខ្ញុំជាមនុស្សហ្មត់ចត់ការងារ",
                "❇️ខ្ញុំជាមនុស្សទទួលខុសត្រូវការងារ",
                "✳️ខ្ញុំជាមនុស្សផ្តោតលើលទ្ឋផលការងារ",
                "❇️ខ្ញុំជាមនុស្សទទួលយកការពិត",
                "✅ខ្ញុំជាមនុស្សវិជ្ជមាន",
                "✳️ខ្ញុំជាមនុស្សមិនត្អូញត្អែរ",
                "❇️ខ្ញុំជាមនុស្សមិននិយាយដើមគេ",
                "✅ខ្ញុំជាមនុស្សមានដំណោះស្រាយ",
                "❇️ខ្ញុំជាមនុស្សហ៊ានប្រឈមនឹងបញ្ហា",
                "✳️ខ្ញុំជាមនុស្សមិនបោះបង់",
                "❇️ខ្ញុំជាមនុស្សក្លាហាន",
                "✅ខ្ញុំជាមនុស្សជួយមនុស្ស",
                "✳️ខ្ញុំជាមនុស្សដឹងគុណ",
                "❇️ខ្ញុំជាមនុស្សស្មោះត្រង់",
                "✅ខ្ញុំជាមនុស្សជោគជ័យ",
                "❇️ខ្ញុំជាមនុស្សលូតលាស់ឆាប់រហ័ស",
                "✅ខ្ញុំជាម្ចាស់ជីវិតរបស់ខ្ញុំ/សូមអរគុណ!",
            ];
            let currentSlide = 0;
            let autoSlideTimer = null;
            const AUTO_SLIDE_INTERVAL = 4000;

            function showSlide(idx) {
                const slideContent = document.getElementById('slideContent');
                slideContent.style.opacity = 0;
                setTimeout(() => {
                    slideContent.textContent = slides[idx];
                    slideContent.style.opacity = 1;
                }, 200);
                resetAutoSlide();
            }

            function nextSlide() {
                if (currentSlide < slides.length - 1) {
                    currentSlide++;
                    showSlide(currentSlide);
                } else {
                    currentSlide = 0;
                    showSlide(currentSlide);
                }
            }

            function resetAutoSlide() {
                if (autoSlideTimer) clearTimeout(autoSlideTimer);
                autoSlideTimer = setTimeout(nextSlide, AUTO_SLIDE_INTERVAL);
            }

            showSlide(currentSlide);
        </script>


        <div id="scanPopup" class="popup">
            <p id="popupMessage"></p>
        </div>

        <div id="earlyScanPopup" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>ស្កេនចេញមុន</h3>
                <label for="earlyReason">មូលហេតុ:</label>
                <textarea id="earlyReason" class="form-control" rows="3" placeholder="សរសេរមូលហេតុនៅទីនេះ"></textarea>
                <div class="modal-buttons">
                    <button id="submitEarlyScan" class="btn btn-primary">បញ្ជូន</button>
                    <button id="cancelEarlyScan" class="btn btn-secondary">បោះបង់</button>
                </div>
            </div>
        </div>
    </div>
    <div id="loadingPopup" class="fullscreen-loading">
        <div class="spinner-border text-light" role="status"></div>
        <p>កំពុងបញ្ជូន...</p>
    </div>
    <style>
        #loadingPopup.fullscreen-loading {
            transition: opacity 0.18s cubic-bezier(.4,0,.2,1);
            opacity: 0;
            pointer-events: none;
        }
        #loadingPopup.fullscreen-loading.show {
            display: flex;
            opacity: 1;
            pointer-events: auto;
            transition: opacity 0.18s cubic-bezier(.4,0,.2,1);
        }
    </style>
    <script>
        // Instantly show/hide loadingPopup for faster UX
        (function() {
            const loadingPopup = document.getElementById('loadingPopup');
            if (!loadingPopup) return;
            const origAdd = loadingPopup.classList.add.bind(loadingPopup.classList);
            const origRemove = loadingPopup.classList.remove.bind(loadingPopup.classList);
            loadingPopup.classList.add = function(...args) {
                if (args.includes('show')) {
                    loadingPopup.style.display = 'flex';
                    setTimeout(() => loadingPopup.style.opacity = '1', 0);
                }
                return origAdd(...args);
            };
            loadingPopup.classList.remove = function(...args) {
                if (args.includes('show')) {
                    loadingPopup.style.opacity = '0';
                    setTimeout(() => loadingPopup.style.display = 'none', 180);
                }
                return origRemove(...args);
            };
            // Ensure initial state is hidden
            loadingPopup.style.opacity = '0';
            loadingPopup.style.display = 'none';
        })();
    </script>
    <footer class="custom-footer">
        <div class="footer-content">
            <button id="footerProfileBtn" class="footer-btn">
                <i class="fa-solid fa-user"></i>
                <span>Profile</span>
            </button>
            <button id="footerScanLogsBtn" class="footer-btn">
                <i class="fa-solid fa-list"></i>
                <span>ទិន្នន័យស្កេន</span>
            </button>
            <button id="footerAllowedLocationsBtn" class="footer-btn">
                <i class="fa-solid fa-map-marker-alt"></i>
                <span>ទីតាំងអាចស្កេន</span>
            </button>
        </div>
    </footer>
    <!-- Allowed Locations Popup -->
    <div id="allowedLocationsPopup" class="popup" style="z-index:2200;display:none;max-width:95vw;">
        <p style="font-weight:600;font-size:1.1rem;color:#2980b9;margin-bottom:0.7rem;">
            <i class="fa-solid fa-map-marker-alt"></i> បញ្ជីទីតាំងអាចស្កេនបាន
        </p>
        <div id="allowedLocationsList" style="max-height:45vh;overflow-y:auto;font-size:1rem;"></div>
        <button id="closeAllowedLocationsPopup" class="btn btn-primary mt-2">បិទ</button>
    </div>
    <script>
        document.getElementById('footerAllowedLocationsBtn').onclick = async function () {
            const popup = document.getElementById('allowedLocationsPopup');
            const list = document.getElementById('allowedLocationsList');
            list.innerHTML = '<div style="color:#888;">កំពុងផ្ទុក...</div>';
            popup.style.display = 'block';
            try {
                // Get current user
                await openDB();
                const user = await getFromDB('loggedInUser', 'user');
                if (!user || !user.id) {
                    list.innerHTML = '<div style="color:#e74c3c;">សូមចូលប្រើប្រាស់ឡើងវិញ!</div>';
                    return;
                }
                let allowedLocations = window.allowedLocationsCache;
                if (!allowedLocations || Date.now() - (window.allowedLocationsCacheTime || 0) > 2 * 60 * 1000) {
                    const resp = await fetch('api.php?action=get_data');
                    const data = await resp.json();
                    allowedLocations = data.data.allowedLocations || [];
                }
                // Filter only locations that allow this user
                const userLocations = allowedLocations.filter(loc =>
                    Array.isArray(loc.users) && loc.users.some(u => u.user_id === user.id)
                );
                if (!userLocations.length) {
                    list.innerHTML = '<div style="color:#e74c3c;">អ្នកមិនមានទីតាំងអាចស្កេនបាន!</div>';
                } else {
                    list.innerHTML = userLocations.map(loc =>
                        `<div style="margin-bottom:0.7rem;padding:0.7rem 0.5rem;border-bottom:1px solid #e0e0e0;">
                            <b style="color:#2980b9;">${loc.name || ''}</b>
                            <div style="font-size:0.97em;color:#555;">
                                សាខា: <span>${loc.branch || '-'}</span><br>
                                Lat: <span>${loc.latitude}</span>, Long: <span>${loc.longitude}</span>
                                <a href="https://www.google.com/maps?q=${loc.latitude},${loc.longitude}" target="_blank" style="color:#4A90E2;text-decoration:underline;margin-left:0.5em;">មើលផែនទី</a>
                            </div>
                        </div>`
                    ).join('');
                }
            } catch (e) {
                list.innerHTML = '<div style="color:#e74c3c;">បរាជ័យក្នុងការទាញទិន្នន័យ!</div>';
            }
        };
        document.getElementById('closeAllowedLocationsPopup').onclick = function () {
            document.getElementById('allowedLocationsPopup').style.display = 'none';
        };
        document.getElementById('allowedLocationsPopup').onclick = function (e) {
            if (e.target === this) this.style.display = 'none';
        };
    </script>
    <div id="profilePopup" class="profile-popup">
        <div class="profile-popup-content">
            <button class="profile-popup-close" id="profilePopupClose" aria-label="Close">&times;</button>
            <div class="profile-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="profile-info">
                <div><span>Name:</span> <b id="profileName"></b></div>
                <div><span>ID:</span> <b id="profileId"></b></div>
                <div><span>Department:</span> <b id="profileDepartment"></b></div>
                <div><span>Position:</span> <b id="profilePosition"></b></div>
                <div><span>Workplace:</span> <b id="profileWorkplace"></b></div>
            </div>
        </div>
    </div>
    <!-- Scan Logs Popup -->
    <div id="scanLogsPopup" class="scan-logs-popup">
        <div class="scan-logs-popup-content">
            <button class="scan-logs-popup-close" id="scanLogsPopupClose" style="font-size: 18px;"
                aria-label="Close">&times;</button>
            <div id="scanLogsList" class="scan-logs-list">
                <div style="text-align:center;color:#888;">កំពុងផ្ទុក...</div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('footerProfileBtn').onclick = async function () {
            const user = await getFromDB('loggedInUser', 'user');
            if (user) {
                document.getElementById('profileName').textContent = user.username || '';
                document.getElementById('profileId').textContent = user.id || '';
                document.getElementById('profileDepartment').textContent = user.department || '';
                document.getElementById('profilePosition').textContent = user.position || '';
                document.getElementById('profileWorkplace').textContent = user.workplace || '';
                document.getElementById('profilePopup').classList.add('show');
            } else {
                alert('សូមចូលប្រើប្រាស់ឡើងវិញ!');
            }
        };

        document.getElementById('footerScanLogsBtn').onclick = async function () {
            const user = await getFromDB('loggedInUser', 'user');
            if (user) {
                // Show popup and load logs from /worker/logs.php in an iframe
                document.getElementById('scanLogsPopup').classList.add('show');
                const logsList = document.getElementById('scanLogsList');
                logsList.innerHTML = `
                <iframe src="/worker/logs.php?username=${encodeURIComponent(user.username)}&id=${encodeURIComponent(user.id)}&branch=${encodeURIComponent(user.branch || '')}&folder=${encodeURIComponent(user.folder || '')}" 
                    style="width:100%;height:60vh;border:none;background:#fff;"></iframe>
            `;
            } else {
                alert('សូមចូលប្រើប្រាស់ឡើងវិញ!');
            }
        };

        document.getElementById('profilePopupClose').onclick = function () {
            document.getElementById('profilePopup').classList.remove('show');
        };
        // Close popup when clicking outside content
        document.getElementById('profilePopup').onclick = function (e) {
            if (e.target === this) this.classList.remove('show');
        };
        // Scan logs popup close
        document.getElementById('scanLogsPopupClose').onclick = function () {
            document.getElementById('scanLogsPopup').classList.remove('show');
        };
        document.getElementById('scanLogsPopup').onclick = function (e) {
            // Hide popup if click is outside the content area
            if (e.target === this) {
                this.classList.remove('show');
            }
        };
    </script>




    <!-- PWA Install Guide Popup -->
    <div id="pwaInstallPopup" class="popup" style="z-index:2100;">
        <p>
            <strong>បន្ថែមកម្មវិធីទៅកាន់ទំព័រដើម (Install PWA)</strong><br>
            <span style="font-size:1.1em;">
                សម្រាប់ Chrome/Edge/Samsung Browser៖<br>
                <b>ចុច <i class="fa-solid fa-ellipsis-vertical"></i> ឬ <i class="fa-solid fa-bars"></i> នៅខាងលើ &gt;
                    ជ្រើស "Add to Home screen" ឬ "Install app"</b>
            </span>
            <br><br>
            <span style="font-size:1.1em;">
                សម្រាប់ iPhone/iPad (Safari)៖<br>
                <b>ចុច <i class="fa-solid fa-share-from-square"></i> &gt; "Add to Home Screen"</b>
            </span>
        </p>
        <button id="closePwaInstallPopup" class="btn btn-primary mt-2">យល់ព្រម</button>
        <button id="hidePwaInstallPopup" class="btn btn-secondary mt-2">មិនបង្ហាញម្ដងទៀត</button>
    </div>
    <script>
        (function () {
            // Show popup only if not installed and not dismissed before
            function isStandalone() {
                return (window.matchMedia('(display-mode: standalone)').matches) ||
                    (window.navigator.standalone === true);
            }
            function showPwaPopupIfNeeded() {
                if (isStandalone()) return;
                if (localStorage.getItem('hidePwaInstallPopup') === '1') return;
                // Show only once per session (not every reload)
                if (sessionStorage.getItem('pwaInstallPopupShown') === '1') return;
                setTimeout(() => {
                    document.getElementById('pwaInstallPopup').style.display = 'block';
                    sessionStorage.setItem('pwaInstallPopupShown', '1');
                }, 1200);
            }
            document.getElementById('closePwaInstallPopup').onclick = function () {
                document.getElementById('pwaInstallPopup').style.display = 'none';
            };
            document.getElementById('hidePwaInstallPopup').onclick = function () {
                document.getElementById('pwaInstallPopup').style.display = 'none';
                localStorage.setItem('hidePwaInstallPopup', '1');
            };
            showPwaPopupIfNeeded();
        })();
    </script>
    <style>
        #pwaInstallPopup.popup {
            display: none;
            z-index: 2100;
            background: #fff;
            border: 2px solid #2980b9;
            color: #222;
            font-size: 1rem;
            padding: 1.5rem 1rem;
        }

        #pwaInstallPopup strong {
            color: #2980b9;
        }

        #pwaInstallPopup i {
            color: #2980b9;
            margin: 0 2px;
        }

        #pwaInstallPopup .btn-secondary {
            margin-left: 0.5rem;
        }
    </style>





   
</body>

</html>