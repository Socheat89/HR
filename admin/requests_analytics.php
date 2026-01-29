<?php
session_start();
include 'admin/includes/auth.php'; // Ensure this path matches your file structure

// Handle logout
if (isset($_GET['logout'])) {
    logout(); // Use the logout() function from auth.php
    header("Location: login.php");
    exit();
}

// Check if user is logged in; if not, redirect to login.php
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Sample analytics data (replace with actual database query later)
$analytics_data = [
    'labels' => ['សុំច្បាប់', 'សុំសម្ភារៈ', 'ផ្សេងៗ'],
    'data' => [25, 15, 10],
    'backgroundColors' => ['#ff8f00', '#e91e63', '#4caf50']
];
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Requests Analytics - HR App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSB7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body {
            margin: 0;
            background: url(https://png.pngtree.com/background/20230401/original/pngtree-khmer-new-year-frame-vector-picture-image_2253486.jpg) no-repeat center center/cover;
            min-height: 100vh;
            position: relative;
            font-family: 'Arial', sans-serif;
        }
        .card {
            position: relative;
            text-align: center;
            padding: 30px 20px;
            border-radius: 20px;
            background: #f9f9f9;
            box-shadow: 10px 10px 20px rgba(0, 0, 0, 0.1), -10px -10px 20px rgba(255, 255, 255, 0.8);
            transition: all 0.4s ease;
            overflow: hidden;
            border: 1px solid rgba(255, 215, 0, 0.2);
        }
        .card:hover {
            transform: translateY(-10px) scale(1.03);
            box-shadow: 15px 15px 30px rgba(0, 0, 0, 0.15), -15px -15px 30px rgba(255, 255, 255, 0.9);
            background: linear-gradient(145deg, #fff8e1, #ffebee);
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
        .card-content {
            position: relative;
            z-index: 1;
        }
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
        .analytics-title {
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
            justify-content: space-around;
            border-radius: 26px 26px 0 0;
            padding: 10px 0;
        }
        .bottom-menu a {
            color: #424242;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }
        .bottom-menu a:hover {
            color: #e91e63;
        }
        canvas {
            max-width: 100%;
        }
        @media (max-width:12000px) {
            .bottom-menu { display: none; }
        }
        @media (max-width:768px) {
            .bottom-menu { display: flex; }
            .analytics-title { font-size: 1.8rem; }
            .card { padding: 20px; }
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
    <h1 class="analytics-title">វិភាគស្នើសុំ</h1>
    
    <div class="card mx-auto" style="max-width: 800px;">
        <div class="card-content">
            <canvas id="requestsChart"></canvas>
        </div>
    </div>
    
    <a href="requests_menu.php" class="btn-custom mt-4">
        <i class="fa-solid fa-arrow-left"></i>ត្រឡប់ទៅម៉ឺនុយ
    </a>
    
    <a href="?logout=true" id="logout-btn" class="btn-custom btn-danger mt-3">
        <i class="fa-solid fa-right-from-bracket"></i>ចាកចេញ
    </a>
</div>

<div class="bottom-menu">
    <a href="index.php"><i class="fa-solid fa-house"></i></a>
    <a href="submit_requests.php"><i class="fa-solid fa-plus"></i></a>
    <a href="table_requests.php"><i class="fa-solid fa-table"></i></a>
    <a href="?logout=true" id="logout" title="ចាកចេញ"><i class="fa-solid fa-right-from-bracket"></i></a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const logoutLinks = document.querySelectorAll('#logout, #logout-btn');
        logoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('តើអ្នកប្រាកដជាចង់ចាកចេញមែនទេ?')) {
                    e.preventDefault();
                }
            });
        });

        // Chart.js configuration
        const ctx = document.getElementById('requestsChart').getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($analytics_data['labels']); ?>,
                    datasets: [{
                        label: 'ចំនួនស្នើសុំ',
                        data: <?php echo json_encode($analytics_data['data']); ?>,
                        backgroundColor: <?php echo json_encode($analytics_data['backgroundColors']); ?>,
                        borderWidth: 1,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'ចំនួនស្នើសុំ',
                                color: '#424242'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'ប្រភេទស្នើសុំ',
                                color: '#424242'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#424242'
                            }
                        }
                    }
                }
            });
        } else {
            console.error('Canvas element "requestsChart" not found');
        }
    });
</script>
</body>
</html>