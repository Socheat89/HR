<?php
session_start();
include 'admin/includes/auth.php'; // Include auth functions

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Check if user is logged in; if not, redirect to login.php

?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
     <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>Requests Menu - HR App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSB7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        body {
            margin: 0;
            background: url(https://png.pngtree.com/background/20230401/original/pngtree-khmer-new-year-frame-vector-picture-image_2253486.jpg) no-repeat center center/cover;
            min-height: 100vh;
            position: relative;
            left: 0;
            right: 0;
            font-family: 'Arial', sans-serif;
        }
        /* New Card Design */
        .card {
            position: relative;
            text-align: center;
            padding: 30px 20px;
            border-radius: 20px;
            background: #f9f9f9;
            box-shadow: 10px 10px 20px rgba(0, 0, 0, 0.1), -10px -10px 20px rgba(255, 255, 255, 0.8);
            transition: all 0.4s ease;
            overflow: hidden;
            border: 1px solid rgba(255, 215, 0, 0.2); /* Subtle golden border */
            cursor: pointer;
        }
        .card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 215, 0, 0.3) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 0;
        }
        .card:hover::before {
            opacity: 1;
        }
        .card:hover {
            transform: translateY(-10px) scale(1.03);
            box-shadow: 15px 15px 30px rgba(0, 0, 0, 0.15), -15px -15px 30px rgba(255, 255, 255, 0.9);
            background: linear-gradient(145deg, #fff8e1, #ffebee);
        }
        .icon {
            font-size: 52px;
            margin-bottom: 15px;
            color: #ff8f00; /* Vibrant orange for Khmer vibrancy */
            opacity: 0;
            transform: translateY(30px);
            animation: slideUp 0.7s ease-out forwards;
            transition: transform 0.3s ease, color 0.3s ease;
            z-index: 1;
            position: relative;
        }
        .card:hover .icon {
            transform: translateY(0) scale(1.15);
            color: #e91e63; /* Pink on hover */
        }
        @keyframes slideUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .card p {
            margin: 0;
            font-size: 1.15rem;
            color: #424242;
            font-weight: 600;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
            transition: color 0.3s ease;
        }
        .card:hover p {
            color: #e91e63;
        }
        .card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255, 143, 0, 0.2), rgba(233, 30, 99, 0.2));
            clip-path: polygon(0 0, 100% 0, 85% 100%, 15% 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .card:hover::after {
            opacity: 0.3;
        }

        /* New Button Design */
        .btn-custom {
            position: relative;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            background: linear-gradient(135deg, #ff8f00, #e91e63);
            color: #fff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .btn-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: all 0.5s ease;
        }
        .btn-custom:hover::before {
            left: 100%;
        }
        .btn-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
            background: linear-gradient(135deg, #e91e63, #ff8f00);
        }
        .btn-custom i {
            margin-right: 8px;
        }
        .btn-danger {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #b71c1c, #d32f2f);
        }

        /* Other Styles */
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
            background-image: url('https://i.ibb.co/mrtxdVKp/Khmer-flowe.png');
            background-size: cover;
            animation: fall linear infinite;
        }
        @keyframes fall {
            0% { transform: translateY(-100px) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
        .container {
            padding-top: 4rem;
            padding-bottom: 4rem;
        }
        a {
            text-decoration: none;
            color: inherit;
        }
        .menu-title {
            color: #fff;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.5), 0 0 12px rgba(255, 143, 0, 0.8);
            margin-bottom: 2rem;
            font-size: 2.5rem;
            background: linear-gradient(90deg, #ff8f00, #e91e63);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .bottom-menu {
            position: fixed;
            bottom: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            left: 0;
            justify-content: space-around;
            border-radius: 26px 26px 0 0;
            padding: 10px 0;
        }
        .bottom-menu a {
            color: #424242;
            text-decoration: none;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }
        .bottom-menu a:hover {
            color: #e91e63;
        }
        @media (max-width:12000px) {
            .bottom-menu {
                display: none;
            }
        }
        @media (max-width:768px) {
            .bottom-menu {
                display: flex;
            }
            .menu-title {
                font-size: 1.8rem;
            }
            .card {
                padding: 20px;
            }
            .icon {
                font-size: 42px;
            }
            .card p {
                font-size: 1rem;
            }
            .btn-custom {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<div class="falling-flowers">
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

<div class="container d-flex flex-column align-items-center">
    <h1 class="menu-title">ម៉ឺនុយស្នើសុំ</h1>
    
    <div class="row g-4 w-100" style="max-width: 600px;">
        <div class="col-12 col-md-4">
            <a href="admin/submit_request.php">
                <div class="card">
                    <i class="fa-solid fa-plus icon" style="animation-delay: 0.1s;"></i>
                    <p>ដាក់ស្នើសុំថ្មី</p>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-4">
            <a href="admin/table_report.php">
                <div class="card">
                    <i class="fa-solid fa-table icon" style="animation-delay: 0.2s;"></i>
                    <p>តារាងស្នើសុំ</p>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-4">
            <a href="admin/analyze_requests.php">
                <div class="card">
                    <i class="fa-solid fa-chart-bar icon" style="animation-delay: 0.3s;"></i>
                    <p>វិភាគស្នើសុំ</p>
                </div>
            </a>
        </div>
    </div>

    <a href="homes.php" class="btn-custom mt-4">
        <i class="fa-solid fa-arrow-left"></i>ត្រឡប់ទៅទំព័រដើម
    </a>
    
    <a href="?logout=true" id="logout-btn" class="btn-custom btn-danger mt-3">
        <i class="fa-solid fa-right-from-bracket"></i>ចាកចេញ
    </a>

    <div class="bottom-menu">
        <a href="homes.php"><i class="fa-solid fa-house"></i></a>
        <a href="admin/submit_requests.php"><i class="fa-solid fa-plus"></i></a>
        <a href="admin/table_requests.php"><i class="fa-solid fa-table"></i></a>
        <a href="?logout=true" id="logout" title="ចាកចេញ"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.3/gsap.min.js" integrity="sha512-gmwBmiTVER57N3jYS3LinA9eb8aHrJua5iQD7yqYCKa5x6Jjc7VDVaEA0je0Lu0bP9j7tEjV3+1qUm6loO99Kw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    document.getElementById('logout').addEventListener('click', function(e) {
        if (!confirm('តើអ្នកប្រាកដជាចង់ចាកចេញមែនទេ?')) {
            e.preventDefault();
        }
    });

    document.getElementById('logout-btn').addEventListener('click', function(e) {
        if (!confirm('តើអ្នកប្រាកដជាចង់ចាកចេញមែនទេ?')) {
            e.preventDefault();
        }
    });
</script>
</body>
</html>