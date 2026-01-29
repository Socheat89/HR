<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    body {
      background: linear-gradient(to right, #2c5364, #203a43, #0f2027);
      color: white;
      font-family: Khmer OS Battambang;
    }
    .navbar-custom {
      background-color: #050049;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
    }
    .card {
      background-color: rgba(255, 255, 255, 0.1);
      border: none;
      border-radius: 10px;
    }
    .form-control, .btn {
      border-radius: 25px;
    }
    .btn-primary {
      background-color: #4CAF50;
      border: none;
    }
    .btn-primary:hover {
      background-color: gold;
    }
    .section {
      margin-top: 80px;
      padding: 20px;
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-custom shadow-lg">
    <div class="container-fluid">
      <a class="navbar-brand text-white" href="#">Admin Panel</a>
      <a href="index.html" class="btn btn-danger"><i class="fa-solid fa-right-from-bracket"></i> ចាកចេញ</a>
    </div>
  </nav>

  <div class="container section">
    <h2 class="mb-4">គ្រប់គ្រង Swiper Slides</h2>
    <div class="card p-4 mb-4">
      <form id="slideForm">
        <div class="mb-3">
          <label for="slideImage" class="form-label">រូបភាព (URL)</label>
          <input type="text" class="form-control" id="slideImage" placeholder="បញ្ចូល URL រូបភាព">
        </div>
        <div class="mb-3">
          <label for="slideTitle" class="form-label">ចំណងជើង</label>
          <input type="text" class="form-control" id="slideTitle" placeholder="ឧ. បញ្ជីរាយនាមបុគ្គលិកឆ្នើម">
        </div>
        <div class="mb-3">
          <label for="slideSubtitle" class="form-label">អនុចំណងជើង</label>
          <input type="text" class="form-control" id="slideSubtitle" placeholder="ឧ. ត្រីមាសទី៣">
        </div>
        <button type="submit" class="btn btn-primary">បន្ថែម Slide</button>
      </form>
    </div>

    <h2 class="mb-4">គ្រប់គ្រង Menu Cards</h2>
    <div class="card p-4">
      <form id="menuForm">
        <div class="mb-3">
          <label for="menuIcon" class="form-label">Icon (Font Awesome Class)</label>
          <input type="text" class="form-control" id="menuIcon" placeholder="ឧ. fa-solid fa-file-pen">
        </div>
        <div class="mb-3">
          <label for="menuText" class="form-label">អត្ថបទ</label>
          <input type="text" class="form-control" id="menuText" placeholder="ឧ. ការស្នើរសុំផ្សេងៗ">
        </div>
        <div class="mb-3">
          <label for="menuLink" class="form-label">Link</label>
          <input type="text" class="form-control" id="menuLink" placeholder="ឧ. index24.html">
        </div>
        <button type="submit" class="btn btn-primary">បន្ថែម Menu Card</button>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Save slide data
    document.getElementById('slideForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const slide = {
        image: document.getElementById('slideImage').value,
        title: document.getElementById('slideTitle').value,
        subtitle: document.getElementById('slideSubtitle').value
      };
      console.log('New Slide:', slide);
      // Here you would send this data to the backend (e.g., via fetch API)
      this.reset();
    });

    // Save menu card data
    document.getElementById('menuForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const menu = {
        icon: document.getElementById('menuIcon').value,
        text: document.getElementById('menuText').value,
        link: document.getElementById('menuLink').value
      };
      console.log('New Menu Card:', menu);
      // Send this data to the backend
      this.reset();
    });
  </script>
</body>
</html>