<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>daily_report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://unpkg.com/scrollreveal"></script>
  <link rel="stylesheet" href="../system/style1.css">
  <link href="https://fonts.googleapis.com/css2?family=Khmer&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    .swiper { width: 90%; top: -2rem; position: relative; height: 70vh; }
    .swiper-slide { display: flex; justify-content: center; align-items: center; font-size: 1.5rem; color: #fff; background-size: cover; background-position: center; border-radius: 5px; }
    .main-menu { top: 3rem; position: relative; }
    .menu-card { position: relative; top: -4rem; }
    .navbar-custom { background-color: #050049; position: fixed; width: 100%; z-index: 1000; }
    .menu-icon { font-size: 1.5rem; color: white; }
    .list-group-item { display: flex; align-items: center; font-size: 1.2rem; }
    .list-group-item i { font-size: 1.5rem; margin-right: 10px; }
    .list-group-item i:hover { color: blue; transform: scale(1.1); }
    .list-group a:hover { color: blue; }
    .bottom-nav { position: fixed; background-color: #ffffff; border: 1px solid #ddd; height: 80px; bottom: 0; width: 100%; }
    .bottom-nav a { color: #6c757d; font-size: 1.2rem; padding: 20px 30px 0 0; height: 20vh; top: -1rem; text-decoration: none; }
    .bottom-nav .active { color: #b08a29; }
    .navbar-brand img { width: 200px; text-align: center; }
    .navbar-toggler { background: none; }
    .menu-card { border-radius: 15px; color: white; padding: 20px; text-align: center; font-weight: bold; height: 150px; display: flex; justify-content: center; align-items: center; flex-direction: column; }
    .menu-card a { text-decoration: none; color: white; }
    .menu-card a:hover { transform: scale(1.1); transition: all 0.5s ease; }
    .menu-card i { font-size: 2rem; margin-bottom: 10px; }
    .bottom-menu { position: fixed; bottom: 0; width: 100%; background-color: #fff; border-top: 1px solid #ddd; display: flex; justify-content: space-around; padding: 25px 0; }
    .bottom-menu a { color: #007bff; text-decoration: none; font-size: 1.5rem; }
    .bottom-menu a:hover { color: #0056b3; }
    textarea { width: 100%; min-height: 100px; overflow: hidden; resize: none; font-family: 'Khmer', 'Khmer OS Battambang', sans-serif; }
    #form { width: 90%; }
    .btn { color: white; }
    .btn:hover { color: rgb(253, 232, 36); }
    .main-header { position: relative; top: 9rem; }
    .label { font-family: 'Khmer', 'Khmer OS Battambang', sans-serif; }
    #submit-button { font-family: 'Khmer', 'Khmer OS Battambang', sans-serif; }
    @media (max-width: 768px) {
      .swiper { top: -2.7rem; width: 94%; border-radius: 10px; height: 35vh; }
      .bottom-menu { left: 0; }
      .menu-card { top: -5rem; }
      .main-menu { top: -4rem; }
      .navbar-brand img { left: 0; margin: 0 auto; display: block; }
      .btn { left: 0; width: 100%; text-align: center; }
      .mes { width: 100%; top: -3.3rem; left: 0; }
    }
    @media (max-width: 576px) {
      .btn { padding: 10px; font-size: 1rem; }
      .navbar-brand img { width: 150px; }
    }
  </style>
</head>
<body>
  <div class="main-header">
    <nav class="navbar navbar-expand-lg navbar-custom shadow-lg mb-5">
      <div class="container-fluid text-decoration-none">
        <a href="../homes.php"><span class="navbar-brand text-white text-decoration-none"><img src="https://i.ibb.co/HTksMQd/Logo-Van-Van-2.png" alt=""></span></a>
        <a href="../menu_main.php"><button id="logout"><i class="fa-solid fa-right-from-bracket"></i></button></a>
      </div>
    </nav>
    <section class="hero is-bold container"></section>
    <form id="form" class="container pl-4 main-form text-white shadow p-2 mb-2" method="POST" action="../reports/submit-report.php">
      <div class="field ani-1">
        <label class="label">Email</label>
        <div class="control">
          <input class="input" type="email" placeholder="Your Email" name="Email" required />
        </div>
      </div>
      <div class="field ani-2">
        <label class="label">ឈ្មោះ</label>
        <div class="control">
          <input class="input" type="text" placeholder="Name" name="Name" required />
        </div>
      </div>
      <div class="field ani-3">
        <label class="label">បុគ្គលិកផ្នែក</label>
        <div class="control">
          <select class="label mt-0 drop" name="Position" id="Positions" required>
            <option value="">សូមជ្រើសរើស</option>
            <option value="ព័ត៌មានវិទ្យា">ព័ត៌មានវិទ្យា</option>
            <option value="គិតលុយ">គិតលុយ</option>
            <option value="រដ្ឋបាលទូទៅ">រដ្ឋបាលទូទៅ</option>
            <option value="បុគ្គលិកផ្នែកលក់">បុគ្គលិកផ្នែកលក់</option>
            <option value="បុគ្គលិកផ្នែកស្តុក318">បុគ្គលិកផ្នែកស្តុក318</option>
            <option value="ប្រធានផ្នែកគ្រប់គ្រងស្តកទំនិញទូទៅ">ប្រធានផ្នែកគ្រប់គ្រងស្តកទំនិញទូទៅ</option>
            <option value="ប្រធានឃ្លាំង៣១៨និងហាងទំនិញ">ប្រធានឃ្លាំង៣១៨និងហាងទំនិញ</option>
            <option value="បុគ្គលិកផ្នែកគណនេយ្យ">បុគ្គលិកផ្នែកគណនេយ្យ</option>
            <option value="ប្រមូលសាច់ប្រាក់">ប្រមូលសាច់ប្រាក់</option>
            <option value="ប្រធានឃ្លាំង CH1">ប្រធានឃ្លាំង CH1</option>
            <option value="រដ្ឋបាលឃ្លាំង CH1">រដ្ឋបាលឃ្លាំង CH1</option>
            <option value="ជំនួយការប្រធានឃ្លាំង CH1">ជំនួយការប្រធានឃ្លាំង CH1</option>
            <option value="ប្រធានឃ្លាំង CKD">ប្រធានឃ្លាំង CKD</option>
            <option value="ជំនួយការប្រធានឃ្លាំង CKD">ជំនួយការប្រធានឃ្លាំង CKD</option>
            <option value="ប្រធានរដ្ឋបាលឃ្លាំង CKD">រដ្ឋបាលឃ្លាំង CKD</option>
            <option value="ប្រធានឃ្លាំង ST1">ប្រធានឃ្លាំង ST1</option>
            <option value="ប្រធានឃ្លាំង PSP">ប្រធានឃ្លាំង PSP</option>
          </select>
        </div>
      </div>
      <div class="field ani-4">
        <label class="label">ថ្ងៃខែឆ្នាំ</label>
        <div class="control">
          <input class="input" type="datetime-local" placeholder="Your Date of Birth" name="Date" required />
        </div>
      </div>
      <div class="field ani-5">
        <div class="control main-font">
          <label class="label main-font">របាយការពេលល្ងាច</label>
          <textarea oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px';" placeholder="សរសេររបាយការណ៍របស់អ្នក" name="Content" required></textarea>
        </div>
      </div>
      <div class="field is-grouped">
        <div class="control ani-6">
          <button class="button bg-primary btn" type="submit" id="submit-button">បញ្ជូន</button>
        </div>
      </div>
      <div class="mes" id="message" style="display: none; position: relative; width: 100%; left: 0; font-weight: bold; color: green; padding: 8px; background-color: beige; border-radius: 4px; border: 1px solid aquamarine;"></div>
    </form>
  </div>

  <script>
    const scrollRevealOption = {
      origin: "bottom",
      distance: "10px",
      duration: 1000,
    };
    ScrollReveal().reveal(".ani-1", { ...scrollRevealOption, distance: "200px" });
    ScrollReveal().reveal(".ani-2", { ...scrollRevealOption, delay: 100, distance: "200px" });
    ScrollReveal().reveal(".ani-3", { ...scrollRevealOption, delay: 200, distance: "200px" });
    ScrollReveal().reveal(".ani-4", { ...scrollRevealOption, delay: 300, distance: "200px" });
    ScrollReveal().reveal(".ani-5", { ...scrollRevealOption, delay: 400, distance: "200px" });
    ScrollReveal().reveal(".ani-6", { ...scrollRevealOption, delay: 500, distance: "200px" });

    const textareas = document.querySelectorAll('textarea');
    textareas.forEach((textarea) => {
      textarea.addEventListener('input', () => {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
      });
    });

    document.getElementById("form").addEventListener("submit", async function (e) {
      e.preventDefault();

      const messageEl = document.getElementById("message");
      const submitBtn = document.getElementById("submit-button");
      messageEl.style.display = "block";
      messageEl.textContent = "កំពុងបញ្ជូន";
      submitBtn.disabled = true;

      const formData = new FormData(this);
      const email = formData.get("Email");
      const content = formData.get("Content").trim();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      // Client-side validation
      if (!emailRegex.test(email)) {
        messageEl.textContent = "សូមបញ្ចូលអ៊ីមែលឲ្យត្រឹមត្រូវ";
        messageEl.style.backgroundColor = "red";
        messageEl.style.color = "white";
        submitBtn.disabled = false;
        return;
      }
      if (content.length < 1) {
        messageEl.textContent = "របាយការណ៍ត្រូវមានយ៉ាងតិច ១០ តួអក្សរ";
        messageEl.style.backgroundColor = "red";
        messageEl.style.color = "white";
        submitBtn.disabled = false;
        return;
      }

      try {
        // Submit to PHP backend (which handles Telegram and database)
        const phpResponse = await fetch("../reports/submit-report.php", {
          method: "POST",
          body: formData,
        });

        const phpResult = await phpResponse.json();

        if (!phpResponse.ok || !phpResult.success) {
          throw new Error(phpResult.message || "ការបញ្ជូនទៅ Telegram ឬមូលដ្ឋានទិន្នន័យបរាជ័យ");
        }

        // Submit to Google Apps Script
        const formDataString = new URLSearchParams(formData).toString();
        const googleResponse = await fetch("https://script.google.com/macros/s/AKfycbxebdv3IG9m2k_fTvAOwcuvGX4Rn_crBTaWNSC9Jq1H2AFhyZh-0S3AuH-RcDThcT1A/exec", {
          method: "POST",
          body: formDataString,
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          redirect: "follow",
        });
        if (!googleResponse.ok) {
          throw new Error("ការបញ្ជូនទៅ Google Apps Script បរាជ័យ");
        }

        messageEl.textContent = "បានបញ្ជូនរួចរាល់";
        messageEl.style.backgroundColor = "green";
        messageEl.style.color = "beige";
        this.reset();
        setTimeout(() => { messageEl.style.display = "none"; }, 2600);
      } catch (error) {
        console.error("Error:", error);
        messageEl.textContent = error.message;
        messageEl.style.backgroundColor = "red";
        messageEl.style.color = "white";
      } finally {
        submitBtn.disabled = false;
      }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>