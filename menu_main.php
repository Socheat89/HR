<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ទំព័រដើម</title>
  <script src="https://unpkg.com/scrollreveal"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="system/style1.css">
  <link rel="stylesheet" href="/node_modules/bootstrap-icons/icons/">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    .swiper {
      width: 90%;
      top: -2rem;
      position: relative;
      height: 70vh; /* Adjust height */
    }
    .swiper-slide {
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 1.5rem;
      color: #fff;
      background-size: cover;
      background-position: center;
    }
    @media (max-width: 768px) {
      .swiper {
        position: relative;
        top: -2.7rem;
        width: 94%;
        border-radius: 10px;
        height: 38vh; /* Adjust height for smaller devices */
      }
      .bottom-menu{
        left: 0;
        position: fixed;
        width: 50%;
      }
      .menu-card{
        position: relative;
        top: -5rem;
      }
      .main-menu{
        position: relative;
        top: -4rem;
      }
      .navbar-brand img{
        text-align: center;
        position: relative;
        left: 35%;
        justify-content: center;
        align-items: center;
      }
    }
    /* computer */
    .main-menu{
      top: -3rem;
      position: relative;
    }
    .menu-card{
      position: relative;
      top: -4rem;
    }
    .navbar-custom {
      background-color: #050049;
      z-index: 1000;
      top: 0;
      position: fixed;
    }

    .menu-icon {
      font-size: 1.5rem;
      color: white;
    }

    .list-group-item {
      display: flex;
      align-items: center;
      font-size: 1.2rem;
    }

    .list-group-item i {
      font-size: 1.5rem;
      margin-right: 10px;
    }
    .list-group-item i:hover{
      color: blue;
      transform: scale(1.1);
    }
    .list-group a:hover{
      color: blue;
    }

    .bottom-nav {
      position: fixed;
      /* background-color: #ffffff; */
      border: 1px solid #ddd;
      /* height: 80px; */
      bottom: 0;
      width: 100%;
      top:-2rem
    }

    .bottom-nav a {
      color: #6c757d;
      position: relative;
      font-size: 1.2rem;
      padding: 20px 30px 0 0;
      height: 20vh;
      top: -1rem;
      text-decoration: none;

    }

    .bottom-nav .active {
      color: #b08a29;
    }
    .navbar-brand img{
      text-decoration: none;
      width: 200px;
      text-align: center;
      align-items: center;
      position: relative;
    }
    .navbar-toggler{
      background: none;
    }
    .menu-card {
      border-radius: 15px;
      text-decoration: none;
      color: white;
      padding: 20px;
      text-align: center;
      font-weight: bold;
      height: 150px;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
    }
    .menu-card a{
      text-decoration: none;
      color: white;
    }
    .menu-card a:hover{
      transform: scale(1.1);
      transition: all 0.5s ease;
    }

    .menu-card i {
      font-size: 2rem;
      margin-bottom: 10px;
    }

    .bottom-menu {
      position: fixed;
      bottom: 0;
      width: 100%;
      background-color: #ffffff;
      background-image: url(https://img.lovepik.com/background/20211021/large/lovepik-blue-new-year-background-image_401667092.jpg);
      /* border-top: 1px solid #ddd; */
      display: flex;
      left: 0;
      justify-content: space-around;
      border-radius: 26px 26px 0 0 ;
      padding: 25px 0;
    }

    .bottom-menu a {
      color: #ffffff;
      text-decoration: none;
      font-size: 1.5rem;
    }

    .bottom-menu a:hover {
      color: #0056b3;
    }
    .swiper-slide{
      border-radius: 5px;
    }
    .main-header{
      position: relative;
      top: 9rem;
      font-family: Khmer OS Battambang;
    }
    .modal-body{
      font-family: Khmer OS Battambang;
    }
    .modal-footer{
      font-family: Khmer OS Battambang;
    }
    .modal-title{
      font-family: Khmer OS Battambang;
    }
.full-screen-alert {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.8);
      background-image: url(https://media4.giphy.com/media/v1.Y2lkPTc5MGI3NjExd25ud3poMWVsMXRsMXI5dHBqMGlhbnYxcHY1ZHVjb3VxcjJ3bW9vciZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/peAFQfg7Ol6IE/giphy.webp);
      display: flex;
      justify-content: center;
      background-repeat: no-repeat;
      background-size: cover;
      align-items: center;
      flex-direction: column;
      z-index: 9999;
      opacity: 0; /* Initially hidden for fade-in animation */
      animation: fadeIn 1s forwards; /* Fade-in effect */
    }
    @media (max-width:768px) {
      .full-screen-alert2 {
      position: fixed;
      top: -10rem;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: none;
      background-color: rgba(0, 0, 0, 0.8);
      display: flex;
      justify-content: center;
      background-repeat: no-repeat;
      background-size: cover;
      align-items: center;
      z-index: -10;
      opacity: 0; /* Initially hidden for fade-in animation */
      animation: fadeIn 1s forwards; /* Fade-in effect */
    }
      
    }
    /* Image styling */
    .full-screen-alert img {
      max-width: 100%;
      max-height: 60%; /* Let the image take up most of the screen */
      object-fit: contain; /* Ensure the image fits within the screen */
      margin-bottom: 20px; /* Space between image and text */
    }

    /* Alert text styling */
    .alert-text {
      color: white;
      font-size: 24px;
      text-align: center;
      max-width: 80%;
      margin: 0 auto;
      margin-bottom: 20px;
    }

    /* Input field container styling */
    .input-container {
      position: relative;
      margin-top: 20px;
      text-align: center;
      display: flex;
      align-items: center;
    }

    /* Stylish input field */
    .input-field {
      padding: 12px 20px;
      font-size: 18px;
      width: 70%;
      max-width: 300px;
      border: 2px solid #ddd;
      border-radius: 25px;
      background-color: transparent;
      color: #fff;
      outline: none;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
      transition: all 0.3s ease;
      margin-right: 10px; /* Space between input and button */
    }

    /* Focus effect */
    .input-field:focus {
      border-color: #00ff00; /* Green border on focus */
      box-shadow: 0 0 15px rgba(0, 255, 0, 0.8);
      background-color: rgba(0, 255, 0, 0.1);
    }

    /* Hover effect */
    .input-field:hover {
      border-color: #4CAF50;
      background-color: rgba(0, 255, 0, 0.05);
    }

    /* Placeholder text style */
    .input-field::placeholder {
      color: #bbb;
      font-style: italic;
    }

    /* Send button styling */
    .send-btn {
      padding: 12px 20px;
      font-size: 18px;
      background-color: #4CAF50;
      color: white;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .send-btn:hover {
      background-color: gold /* Darker green on hover */
    }

    /* Fireworks animation effect */
    .fireworks {
      position: absolute;
      top: -20px;
      left: -20px;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 10;
      opacity: 0;
      animation: fireworks 1s ease-out forwards;
    }

    .fireworks span {
      position: absolute;
      width: 10px;
      height: 10px;
      background-color: #fff;
      border-radius: 50%;
      animation: spark 1s linear infinite;
    }

    @keyframes fireworks {
      0% {
        opacity: 1;
      }
      100% {
        opacity: 0;
      }
    }

    @keyframes spark {
      0% {
        transform: scale(0) translate(0);
        opacity: 1;
      }
      50% {
        transform: scale(1) translate(10px, 10px);
        opacity: 0.7;
      }
      100% {
        transform: scale(0) translate(20px, -20px);
        opacity: 0;
      }
    }
    /* Close button styling */
    .close-btn {
      position: absolute;
      top: 20px;
      z-index: 999;
      right: 20px;
      border: none;
      font-size: 20px;
      padding: 10px;
      cursor: pointer;
    }

    /* Animation for fade-in effect */
    @keyframes fadeIn {
      0% {
        opacity: 0;
      }
      100% {
        opacity: 1;
      }
    }
    .img{
        position: absolute;
        top: 1.6rem;
    }

.loading-page {
  position: absolute;
  top: 0;
  z-index: 9999;
  left: 0;
  background: linear-gradient(to right, #2c5364, #203a43, #0f2027);
  height: 100%;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  align-items: center;
  justify-content: center;
  color: #191654;
}

#svg {
  height: 150px;
  width: 150px;
  stroke: white;
  fill-opacity: 0;
  stroke-width: 3px;
  stroke-dasharray: 4500;
  animation: draw 5s ease;
  -webkit-animation: draw 5s ease;
}

@keyframes draw {
  0% {
    stroke-dashoffset: 4500;
  }
  100% {
    stroke-dashoffset: 0;
  }
}

.name-container {
  height: 30px;
  overflow: hidden;
}

.logo-name {
  color: #fff;
  font-size: 20px;
  letter-spacing: 12px;
  text-transform: uppercase;
  margin-left: 20px;
  font-weight: bolder;
}

@keyframes fall {
      0% { transform: translateY(-100%); }
      100% { transform: translateY(100vh); }
    }

    .falling-flowers {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 9999;
    }

    .flower {
      position: absolute;
      width: 50px;
      height: 50px;
      background-image: url('https://i.ibb.co/mrtxdVKp/Khmer-flowe.png'); /* Purple flower */
      background-size: cover;
      animation: fall linear infinite;
    }
@keyframes fall1 {
      0% { transform: translateY(-100%); }
      100% { transform: translateY(100vh); }
    }

    .falling-flowers1 {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 9999;
    }

    .flower1 {
      position: absolute;
      width: 100px;
      height: 100px;
      background-image: url('https://i.ibb.co/TDY55fP5/Khmer-flower2.png'); /* Purple flower */
      background-size: cover;
      animation: fall1 linear infinite;
    }
  </style>
</head>
<body>
  <div class="falling-flowers text-align-center">
    <div class="flower" style="left: 10%; animation-duration: 5s;"></div>
    <div class="flower" style="left: 20%; animation-duration: 7s;"></div>
    <div class="flower" style="left: 30%; animation-duration: 6s;"></div>
    <div class="flower" style="left: 40%; animation-duration: 8s;"></div>
    <div class="flower" style="left: 50%; animation-duration: 5s;"></div>
    <div class="flower" style="left: 60%; animation-duration: 7s;"></div>
    <div class="flower" style="left: 70%; animation-duration: 6s;"></div>
    <div class="flower" style="left: 80%; animation-duration: 8s;"></div>
    <div class="flower" style="left: 90%; animation-duration: 5s;"></div>
  </div>
  <div class="falling-flowers1 text-align-center">
    <div class="flower1" style="left: 5%; animation-duration: 8s;"></div>
    <div class="flower1" style="left: 20%; animation-duration: 8s;"></div>
    <div class="flower1" style="left: 30%; animation-duration: 7s;"></div>
    <div class="flower1" style="left: 40%; animation-duration: 9s;"></div>
    <div class="flower1" style="left: 50%; animation-duration: 6s;"></div>
    <div class="flower1" style="left: 60%; animation-duration: 8s;"></div>
    <div class="flower1" style="left: 70%; animation-duration: 7s;"></div>
    <div class="flower1" style="left: 80%; animation-duration: 9s;"></div>
    <div class="flower1" style="left: 90%; animation-duration: 6s;"></div>
  </div>

  

  <div class="loading-page">
    <svg id="svg" xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 384 512">
        <path
            d="M19.7 34.5c16.3-6.8 35 .9 41.8 17.2L192 364.8 322.5 51.7c6.8-16.3 25.5-24 41.8-17.2s24 25.5 17.2 41.8l-160 384c-5 11.9-16.6 19.7-29.5 19.7s-24.6-7.8-29.5-19.7L2.5 76.3c-6.8-16.3 .9-35 17.2-41.8z" />
    </svg>


    <div class="name-container">
        <div class="logo-name">VVC</div>
    </div>
</div>
  <!-- Full-Screen Image Alert with Text, Input, and Send Button -->
<!-- <div id="imageAlert" class="full-screen-alert">
  <button class="close-btn" onclick="closeAlert()"><i class="fa-solid fa-xmark"></i></button>
  <div class="img">
  <img src="https://i.ibb.co/zh8RrC3/image.jpg" alt="Full Screen Image" width="600px"></div>
  <div class="input-container">
  </div>
  <div class="full-screen-alert2"><img src="https://media4.giphy.com/media/v1.Y2lkPTc5MGI3NjExd25ud3poMWVsMXRsMXI5dHBqMGlhbnYxcHY1ZHVjb3VxcjJ3bW9vciZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/peAFQfg7Ol6IE/giphy.webp" alt=""></div>
</div> -->
<!--   pop up  -->
<!--   <div class="modal fade" id="autoPopupModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Welcome!</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        បើសិនជាអ្នកមានបញ្ហាក្នុងការប្រើប្រាស់ HR_app សូមឆាតទាក់ទងជាមួយយើងដើម្បីដឹងពីបញ្ហានិងដោះស្រាយជូនអ្នក! សូមអរគុណ!
      </div>
      <div class="modal-footer">
        <a href="system/it_chatbot.php"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ទៅកាន់ ChatBot</button></a>
      </div>
    </div>
  </div>
</div> -->
  <!-- Navbar -->
  <style>
    .bg{
        /* background-image: url(https://t3.ftcdn.net/jpg/04/40/28/66/360_F_440286619_njGAX3dCgMisTrzvIyoDFnP7BZYuVMl8.jpg); */
        opacity: 100%;
        background-size: cover;
        background-repeat: no-repeat;
        position: absolute;
        top: 0;
        z-index: -9;
        width: 100%;
        height: 190vh;
    }
</style>
<div style="position: fixed; right: 0; z-index: 10; top: -25px;"><img src="https://i.ibb.co/7tjXBkxY/Angle.png" alt="" width="170px"></div>
<div style="position: fixed; left:  0; z-index: 10; top: -25px;"><img src="https://i.ibb.co/pjQLqKsz/Angle2.png" alt="" width="170px"></div>
<div class="bg"></div>
  <div class="main-header">
  <nav class="navbar navbar-expand-lg navbar-custom shadow-lg mb-5" style="z-index: 9;">
    <div class="container-fluid text-decoration-none">
      <a href="homes.php"><span class="navbar-brand text-white text-decoration-none "><img src="https://i.ibb.co/HTksMQd/Logo-Van-Van-2.png" alt="" style="z-index: 9999;"></span></a>
    </button>
    <a href="homes.php"><button id="logout"><i class="fa-solid fa-right-from-bracket"></i></button></a>
    </div>
  </nav>
  <!-- <div class="animation">
    <img src="https://media.tenor.com/0o-htOfmXWgAAAAi/chinese-new-year-lunar-new-year.gif" alt="" width="100px" style="width: 110px; height: 11.5%; position: fixed; top: 0; display: flex;  right: 0; z-index: 999;">
</div> -->
  <!-- Swiper Section -->
  <div class="swiper mySwiper mb-2 rounded main-swiper" style=" box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19); padding: 10px;">
    <div class="swiper-wrapper">
      <!-- Slide 1 -->
      <div class="swiper-slide" style="background-image: url('https://i.ibb.co/Rp8t9XwF/image.jpg');">
        <div class="showtext"><span style="font-family: kh Muol;  text-shadow: 1px 1px #000000;">❝បញ្ជីរាយនាមបុគ្គលិកឆ្នើមឆ្នាំ ២០២៤❞</span> <br><hr> <span style="color: rgb(255, 255, 255) ;text-shadow: 1px 1px #000000; font-family: Koulen;">ត្រីមាសទី៣</span></div>
      </div>
      <div class="swiper-slide" style="background-image: url('https://i.ibb.co/n9X6bsb/image.jpg');">
        <div class="showtext"><span style="font-family: kh Muol;  text-shadow: 1px 1px #000000;">❝បញ្ជីរាយនាមបុគ្គលិកឆ្នើមឆ្នាំ ២០២៤❞</span> <br><hr> <span style="color: rgb(255, 255, 255) ;text-shadow: 1px 1px #000000; font-family: Koulen;">ត្រីមាសទី៤</span></div>
      </div>
      <div class="swiper-slide" style="background-image: url('https://i.ibb.co/Rp8t9XwF/image.jpg');">
        <div class="showtext"><span style="font-family: kh Muol;  text-shadow: 1px 1px #000000;">❝បញ្ជីរាយនាមបុគ្គលិកឆ្នើមឆ្នាំ ២០២៤❞</span> <br><hr> <span style="color: rgb(255, 255, 255) ;text-shadow: 1px 1px #000000; font-family: Koulen;">ត្រីមាសទី៣</span></div>
      </div>
      <div class="swiper-slide" style="background-image: url('https://i.ibb.co/n9X6bsb/image.jpg');">
        <div class="showtext"><span style="font-family: kh Muol;  text-shadow: 1px 1px #000000;">❝បញ្ជីរាយនាមបុគ្គលិកឆ្នើមឆ្នាំ ២០២៤❞</span> <br><hr> <span style="color: rgb(255, 255, 255) ;text-shadow: 1px 1px #000000; font-family: Koulen;">ត្រីមាសទី៤</span></div>
      </div>
    </div>
    <style>
        
      .swiper-slide:hover .showtext {
        display: block;
        opacity: 1;
        transition: opacity 0.3s ease-in-out;
        font-size: 10px;
      }
      .showtext {
        display: none;
        transition: all 0.3s ease-in-out;
        opacity: 0;
        position: absolute;
        bottom: 20px;
        left: 20px;
        color: rgb(255, 255, 255);
        font-size: 1.5rem;
        background-color: rgba(77, 77, 77, 0.363);
        padding: 10px;
        border-radius: 5px;
      }
    </style>

    <!-- Swiper Pagination -->
    <!-- <div class="swiper-pagination"></div> -->
    <!-- Swiper Navigation -->
    <!-- <div class="swiper-button-prev"></div>
    <div class="swiper-button-next"></div> -->
  </div>
  <div class="container d-flex flex-column align-items-center py-4" style="overflow: hidden;">
    <div class="card w-100 max-w-md p-3 d-flex flex-column align-items-center" style="overflow: hidden;">
        <!-- Embed Looker Studio report inside the card -->
        <iframe class="w-100 d-none d-sm-block" height="490" src="https://lookerstudio.google.com/embed/reporting/43a413c3-76ce-4aef-96e4-b5c4032939c7/page/page_12345" frameborder="0" style="border:0" allowfullscreen sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
        
        <!-- Responsive iframe for small screens (no scroll, adjusted height) -->
        <iframe class="d-block d-sm-none" width="100%" height="130" style=" overflow: hidden; display: flex;" src="https://lookerstudio.google.com/embed/reporting/43a413c3-76ce-4aef-96e4-b5c4032939c7/page/page_12345" frameborder="0" allowfullscreen sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
    </div>
</div>
  <!-- Main Content -->
  <!-- <div class="container mt-3 sub-title" id="navbarNav"> -->
  <div class="container py-4" id="animation" style="position: relative; top: 3rem;">
    <div class="d-flex justify-content-between align-items-center mb-4 main-menu" style="background-color: rgb(1, 0, 59); padding: 10px; border-radius: 8px;">
      <h3 class="text-white" style="font-family: kh Muol;">Main-Menu</h3>
      <a href="#" class="text-white">See All</a>
    </div>
    <div class="row g-3">
      <div class="col-6">
        <div class="menu-card bg-info shadow p-2 mb-2 ani-1" >
          <a href="reports/daily_report_list.php"><i class="bi bi-calendar-check"></i></i></a>
          <a href="reports/daily_report_list.php">របាយការណ៍ប្រចាំថ្ងៃ</a>
        </div>
      </div>
      <div class="col-6">
        <div class="menu-card bg-warning shadow p-2 mb-2 ani-2">
          <a href="requests/request_home.php"><i class="fa-solid fa-file-pen"></i></a>
          <a href="requests/request_home.php">ការស្នើរសុំផ្សេងៗ</a>
        </div>
      </div>
      <div class="col-6 ">
        <div class="menu-card bg-danger shadow p-2 mb-2 ani-3">
          <a href="requests/material_request.php"><i class="fa-solid fa-file-pen"></i></a>
          <a href="requests/material_request.php">ស្នើរសុំទិញសម្ភារៈ</a>
        </div>
      </div>
      <div class="col-6">
        <div class="menu-card bg-success shadow p-2 mb-2 ani-4">
          <a href="meetings/meeting_register.php"><i class="fa-solid fa-file-pen"></i></a>
          <a href="meetings/meeting_register.php">ចុះឈ្មោះចូលរួមប្រជុំ</a>
        </div>
      </div>
      <div class="col-6">
        <div class="menu-card bg-warning shadow p-2 mb-2 ani-5">
          <a href="employee/staff_directory.php"><i class="bi bi-file-earmark-person"></i></a>
          <a href="employee/staff_directory.php">ព័ត៌មានបុគ្គលិក</a>
        </div>
      </div>
      <div class="col-6">
        <div class="menu-card bg-info shadow p-2 mb-2 ani-6">
          <a href="prints/print_docs.php"><i class="fa-solid fa-print"></i></a>
          <a href="prints/print_docs.php">ឯកសារសម្រាប់ Print</a>
        </div>
      </div>
      <div class="col-6">
        <div class="menu-card bg-success shadow p-2 mb-2 ani-6">
          <a href="missions/mission.php"><i class="bi bi-bullseye"></i></a>
          <a href="missions/mission.php">លិខិតបញ្ជាបេសកម្ម</a>
        </div>
      </div>
      <div class="col-6">
        <div class="menu-card bg-danger shadow p-2 mb-2 ani-6">
          <a href="posts/lessons_documents.php"><i class="fa-solid fa-print"></i></a>
          <a href="posts/lessons_documents.php">មេរៀនផ្សេងៗ</a>
        </div>
      </div>
      </div>
    </div>
  </div>
</div>

<!-- <style>
    .animation{
        z-index: 999;
        width: 100%;
        height: 100vh;
        position: absolute;
        top: 2rem;
        background-repeat: no-repeat;
        right: 0;
        left: -10rem;
    }
</style> -->
<!-- <div class="animation">
    <img src="https://media.tenor.com/DWQHpv9BkrgAAAAj/angpao-year-of-snake-2025.gif" alt="" width="100px" style="width: 110px; height: 10%; position: fixed; bottom: 0; z-index: 999; left: 15rem; align-items: center; justify-content: center;">
</div> -->
</div>
  <div class="bottom-menu">
    <a href="#"><i class="fa-solid fa-house"></i></a>
    <a href="system/it_chatbot.php"><i class="bi bi-chat-dots-fill"></i></a>
    <a href="meetings/meeting_dashboard.php"><i class="fa-solid fa-users"></i></a>
  </div>


  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.3/gsap.min.js"
    integrity="sha512-gmwBmiTVER57N3jYS3LinA9eb8aHrJua5iQD7yqYCKa5x6Jjc7VDVaEA0je0Lu0bP9j7tEjV3+1qUm6loO99Kw=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>
  <script>
    const swiper = new Swiper('.mySwiper', {
      loop: true,
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button -prev',
      },
      breakpoints: {
        320: { // Mobile screens
          slidesPerView: 1,
          spaceBetween: 10,
        },
        768: { // Tablet screens
          slidesPerView: 2,
          spaceBetween: 20,
        },
        1024: { // Desktop screens
          slidesPerView: 2,
          spaceBetween: 30,
        },
      },
    });
    const scrollRevealOption = {
  origin: "bottom",
  distance: "10px",
  duration: 1000,
};

ScrollReveal().reveal(".ani-1", {
  ...scrollRevealOption,
  origin: "bottom",
  distance:"200px",
});
ScrollReveal().reveal(".ani-2", {
  ...scrollRevealOption,
  delay: 100,
  distance:"200px",
});
ScrollReveal().reveal(".ani-3", {
  ...scrollRevealOption,
  delay: 200,
  distance:"200px",
});
ScrollReveal().reveal(".ani-4", {
  ...scrollRevealOption,
  delay: 300,
  distance:"200px",
});
ScrollReveal().reveal(".ani-5", {
  ...scrollRevealOption,
  delay: 400,
  distance:"200px",
});
ScrollReveal().reveal(".ani-6", {
  ...scrollRevealOption,
  delay: 500,
  distance:"200px",
});
      document.addEventListener("DOMContentLoaded", function () {
    const modalElement = document.getElementById('autoPopupModal');
    const modal = new bootstrap.Modal(modalElement);

    // Show the modal after 3 seconds
    setTimeout(function () {
      modal.show();

      // Auto close the modal after 5 seconds
      setTimeout(function () {
        modal.hide();
      }, 9000); // 5 seconds
    }, 100); // 3 seconds
  });
     function showPage(pageId) {
        document.getElementById('main').style.display = 'none'; // Hide main page
        document.getElementById('page-' + pageId).style.display = 'block'; // Show the selected page
    }

    // Function to go back to the main page
    function goBack() {
        const pages = document.querySelectorAll('.page');
        pages.forEach(page => page.style.display = 'none'); // Hide all pages
        document.getElementById('main').style.display = 'block'; // Show the main page
    }

    // Add event listeners to page links
    document.querySelectorAll('.main-page a').forEach(link => {
        link.addEventListener('click', function (event) {
            event.preventDefault(); // Prevent default link behavior
            const pageId = this.getAttribute('data-page'); // Get the page ID
            showPage(pageId); // Show the selected page
        });
    });
    // Function to close the image alert
  function closeAlert() {
    document.getElementById("imageAlert").style.display = "none";
  }

  // Automatically show the image alert when the page loads
  window.onload = function() {
    setTimeout(function() {
      document.getElementById("imageAlert").style.display = "flex";
    }, 1000); // Show the image alert after 1 second
  };

  // Trigger fireworks animation when input is focused
  function triggerFireworks() {
    const fireworksContainer = document.getElementById('fireworksContainer');
    fireworksContainer.innerHTML = ''; // Clear previous sparks

    // Generate multiple sparks for the fireworks effect
    for (let i = 0; i < 30; i++) {
      const spark = document.createElement('span');
      spark.style.left = `${Math.random() * 100}%`;
      spark.style.top = `${Math.random() * 100}%`;
      fireworksContainer.appendChild(spark);
    }

    fireworksContainer.style.animation = 'fireworks 1s ease-out forwards';
  }

  // Function to simulate sending the message
  function sendMessage() {
    const inputField = document.querySelector('.input-field');
    const message = inputField.value.trim();
    if (message) {
      alert("Message Sent: " + message);  // You can replace this with actual sending logic
      inputField.value = '';  // Clear the input field after sending
    } else {
      alert("Please enter a message before sending.");
    }
  }

  gsap.fromTo(
  ".loading-page",
  { opacity: 1 },
  {
    opacity: 0,
    display: "none",
    duration: 1.5,
    delay: 1.5,
  }
);

gsap.fromTo(
  ".logo-name",
  {
    y: 50,
    opacity: 0,
  },
  {
    y: 0,
    opacity: 1,
    duration: 2,
    delay: 0.1,
  }
);

  </script>
</body>
</html>
