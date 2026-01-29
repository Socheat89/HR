<?php
session_start(); // Optional: If you need session data for user-specific features
include 'db.php'; // Assuming this connects to your database
echo '<meta charset="UTF-8">'; // បើមានទំព័រ HTML
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Longbeach</title>
    <link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABwAAAAcCAMAAABF0y+mAAAAeFBMVEWXWhdHcEx9TBI5MBu1XxuYYiK+ZBvKcyLGdyHpgyPxiyb0kyfmiCfLeyXegCP8kyj1kyjviSXnhSVALxLxiCXJdCQeKBTskCn6jSXlgCPQciHyhyQ8KQ4AAAAAABD+mSnsgySzciL5jCa4ax/rgSPdgCX0iyanaRpYyeWHAAAAKHRSTlM8ADIECRYsSo7f9NSLV7z//rltIeV4C6b/05T/LBVB//5J6GrCfvtnjd1Y2AAAAKBJREFUeAHVUjUWwzAMtcLMzOb737BLuVbmRpue8AMhgIZF4CSuUbQd1yOusej6QRjFiWcoOmmWF2VVm9a6SZQ3bWe8affFMPrIQ1M+j+TtW7te1ke27UdD4b2YxvveTMQB6Ngc8S+cNs1YvpcUeD6X9i8JVBxDU4mDLSaGXNnM6hgmhL40nI+8Rop22gwZTnyba7zYlR5eBNf5Aw+dVJcbUwYK5uv5IWgAAAAASUVORK5CYII=" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/scrollReveal.js/2.0.0/scrollReveal.js" integrity="sha512-C5vaj0THdudBZn7DvfkEb/Bt12RDCn2oPOyWN1bsH6NPSw4xJ1eA8NE5QsgURl6cCAd7pvvqtrums8iGcoJU6g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script defer src="https://unpkg.com/scrollreveal"></script>

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Koulen&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap");
        @import url("https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap");

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { 
            scroll-behavior: smooth; 
            font-family: 'Khmer OS System';
            background-color: #faf3e3; 
        }
        [lang="km"] .en-only { display: none; }
        [lang="en"] .km-only { display: none; }

        @media screen and (min-width: 1919px) {
            .carousel-inner { width: 50%; display: block; margin: auto; }
            .slide-image { width: 70%; justify-content: center; display: block; margin: auto; position: relative; }
            nav { margin-right: 100px; }
            .main-logo { margin-left: 100px; }
            .bg-header { display: none; }
            .main-body-image .body-title2 { justify-content: center; display: flex; position: absolute; left: 17.1rem; top: 12rem; }
        }

        @media screen and (min-width: 1024px) {
            .carousel-inner { width: 60%; display: block; margin: auto; border-radius: 8px; top: 1rem; }
            .slide-image { position: relative; top: 5rem; }
            .navbar-nav { margin-right: 140px; font-weight: bold; font-size: 16px; position: relative; }
            .main-logo img { margin-left: 140px; top: -0.5rem; position: fixed; }
            .sub-title h3 { text-align: center; }
            .main-product { display: flex; justify-content: center; }
            .sub-product { position: relative; display: grid; grid-template-columns: repeat(3, 1fr); align-items: center; margin-top: 2rem; text-align: center; gap: 30px; margin: auto; transition: all 0.3s; }
            .sub-product a { align-items: center; width: 250px; background-color: #fae6ce; justify-content: center; margin: auto; border-radius: 8px; padding: 10px; transition: all 0.3s; }
            .sub-product span { text-align: center; }
            .sub-product img:hover { transform: scale(1.05); }
            .body-recommended-product { display: flex; justify-content: center; margin-top: 5rem; }
            .recommended-product { position: relative; display: grid; grid-template-columns: repeat(3, 1fr); align-items: center; margin-top: 2rem; text-align: center; gap: 30px; margin: auto; transition: all 0.3s; }
            .recommended-product a { align-items: center; width: 250px; background-color: #fae6ce; justify-content: center; margin: auto; border-radius: 8px; padding: 10px; transition: all 0.3s; }
            .recommended-product img:hover { transform: scale(1.05); text-decoration: underline; }
            .recommended-product a { text-decoration: none; color: black; font-weight: bold; }
            .recommended-product a:hover { color: #f26923; }
            .main-content { justify-content: center; align-items: center; text-align: center; margin-top: 2rem; }
            .nav-link { color: white; transition: all 0.3s; }
            .main-menu ul li a:hover { color: black; }
            .sub-product a { text-decoration: none; color: black; font-weight: bold; }
            .sub-product a:hover { color: #f26923; }
            .bg-header { background-color: #f26923; display: none; position: fixed; width: 100%; height: 15vh; }
            .main-body-image { display: block; justify-content: center; position: relative; top: 3rem; }
            .main-body-image .img1 { width: 300px; }
            .body-title { display: flex; justify-content: center; }
            .body-title img { width: 500px; }
            .body-title2 { justify-content: center; display: flex; position: absolute; left: 17.5rem; top: 13rem; }
            .body-product { display: flex; margin-top: 3rem; justify-content: center; gap: 20px; }
            .body-product img { border-radius: 8px; width: 255px; }
        }

        @media screen and (max-width: 768px) {
            .main-menu ul li a { position: relative; display: block; color: rgb(0, 0, 0); font-size: 14px; padding: 20px; z-index: 100; }
            .fa-bars { margin-left: 20rem; position: fixed; top: 1.5rem; z-index: 100; }
            .main-logo img { width: 210px; position: fixed; top: -0.5rem; margin-left: 30px; z-index: 100; }
            .sub-header { display: flex; position: absolute; }
            .navbar-nav { position: relative; display: flex; top: -1rem; font-weight: bold; }
            #navbarNav { z-index: 98; margin-top: 3rem; }
            .navbar-nav a { color: rgb(255, 255, 255); font-weight: bold; z-index: 100; }
            .main-header { align-items: center; }
            .slide-image { position: relative; top: 6rem; }
            .carousel-inner { width: 86%; display: block; margin: auto; border-radius: 8px; }
            .sub-title h3 { text-align: center; margin-top: 2rem; font-size: 18px; }
            .main-product { justify-content: center; display: flex; position: relative; top: -6rem; }
            .sub-product { display: grid; grid-template-columns: repeat(2, 1fr); justify-content: center; align-items: center; margin-top: 2rem; text-align: center; gap: 10px; }
            .sub-product a { text-align: center; text-decoration: none; width: 170px; background-color: #fae6ce; position: relative; justify-content: center; padding: 10px 20px; font-weight: 12px; border-radius: 8px; transition: all 0.3s; color: black; font-weight: bold; }
            .sub-product span { display: block; font-size: 11.5px; align-items: center; text-align: center; }
            .sub-product img { text-align: center; text-decoration: none; width: 130px; position: relative; justify-content: center; transition: all 0.3s; }
            .sub-product a:hover { color: #f26923; }
            .sub-product img:hover { transform: scale(1.1); }
            .bg-header { background-color: #f26923; position: fixed; width: 100%; z-index: 100; height: 11.5vh; top: 0; }
            .main-body-image { display: block; justify-content: center; position: relative; top: 3rem; }
            .main-body-image .img1 { width: 150px; }
            .body-title { display: flex; justify-content: center; }
            .body-title img { width: 200px; }
            .body-title2 { justify-content: center; display: flex; position: absolute; left: 2.1rem; top: 6rem; }
            .body-title2 img { width: 100px; }
            .body-product { display: flex; margin-top: 3rem; justify-content: center; gap: 20px; }
            .body-product img { border-radius: 8px; width: 105px; }
            .body-recommended-product { display: flex; justify-content: center; margin-top: -2rem; }
            .recommended-product { display: grid; grid-template-columns: repeat(2, 1fr); justify-content: center; align-items: center; margin-top: -10rem; text-align: center; gap: 10px; }
            .recommended-product a { text-align: center; text-decoration: none; width: 170px; background-color: #fae6ce; position: relative; justify-content: center; padding: 10px 20px; font-weight: 12px; border-radius: 8px; transition: all 0.3s; }
            .recommended-product img { text-align: center; text-decoration: none; width: 130px; position: relative; justify-content: center; transition: all 0.3s; }
            .recommended-product span { display: block; font-size: 11.5px; align-items: center; text-align: center; }
            .recommended-product img:hover { transform: scale(1.05); text-decoration: underline; }
            .recommended-product a { text-decoration: none; font-size: 11.5px; color: black; font-weight: bold; }
            .recommended-product a:hover { color: #f26923; }
            .sub-title { position: relative; top: -5rem; }
            .main-body-image { position: relative; top: -8rem; }
            .Creations { position: relative; top: -5rem; }
            .Recommended { position: relative; top: -9rem; }
        }

        .fa-bars { color: white; font-size: 28px; }
        .navbar-toggler { border: none; outline: none; }
        .nav-link.active { font-weight: bold; color: rgb(0, 0, 0) !important; }
        .navbar { position: relative; }
    </style>
</head>
<body>
    <div class="bg-header"></div>
    <section id="main-header" class="py-3" style="background-color: #f26923; position: fixed; justify-content: space-between; width: 100%; z-index: 120;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="main-logo">
                        <a href="index.php"><img src="https://www.longbeachsyrup.com/images/logo%20longbeach-08.png?crc=377204701" alt="Longbeach Logo" class="img-fluid" /></a>
                    </div>
                </div>
                <div class="col-md-9">
                    <nav class="navbar navbar-expand-lg navbar-light">
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                            <i class="fa-solid fa-bars"></i>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarNav">
                            <ul class="navbar-nav ms-auto">
                                <li class="nav-item"><a class="nav-link" href="about-us.php" data-en="About Us" data-km="អំពីយើង">អំពីយើង</a></li>
                                <li class="nav-item"><a class="nav-link" href="best-sellers.php" data-en="Products" data-km="ផលិតផល">ផលិតផល</a></li>
                                <li class="nav-item"><a class="nav-link" href="Recipes.php" data-en="Recipes" data-km="រូបមន្ត">រូបមន្ត</a></li>
                                <li class="nav-item"><a class="nav-link" href="events.php" data-en="Events" data-km="ព្រឹត្តិការណ៍">ព្រឹត្តិការណ៍</a></li>
                                <li class="nav-item"><a class="nav-link" href="stores.php" data-en="Stores" data-km="ហាង">ហាង</a></li>
                                <li class="nav-item"><a class="nav-link" href="contact.php" data-en="Contact" data-km="ទំនាក់ទំនង">ទំនាក់ទំនង</a></li>
                                <li class="nav-item"><a class="nav-link" href="careers.php" data-en="Careers" data-km="អាជីព">អាជីព</a></li>
                            </ul>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <div class="slide-image">
        <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators m-1">
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <a href="#"><img src="https://www.longbeachsyrup.com/images/_head1.jpg?crc=3804342330" class="d-block w-100" alt="Slide 1" /></a>
                </div>
                <div class="carousel-item">
                    <a href="#"><img src="https://www.longbeachsyrup.com/images/_head2.jpg?crc=4075138205" class="d-block w-100" alt="Slide 2" /></a>
                </div>
                <div class="carousel-item">
                    <a href="#"><img src="https://www.longbeachsyrup.com/images/_head3.jpg?crc=3995169363" class="d-block w-100" alt="Slide 3" /></a>
                </div>
            </div>
        </div>
        <div class="main-content">
            <div class="sub-title" style="margin-top: 8rem">
                <h3 style="font-weight: bold; color: #000" data-en="The Ultimate Beverage Solution" data-km="ដំណោះស្រាយភេសជ្ជៈចុងក្រោយ">ដំណោះស្រាយភេសជ្ជៈចុងក្រោយ</h3>
            </div>
        </div>
        <div class="main-product" style="display: flex; justify-content: center; margin-top: 2rem">
            <div class="sub-product" id="sub-product"></div>
        </div>
        <div class="main-content Creations">
            <div class="sub-title" style="margin-top: 5rem">
                <h3 style="font-weight: bold; color: #000" data-en="Enhance Your Beverage Creations" data-km="បង្កើនការបង្កើតភេសជ្ជៈរបស់អ្នក">បង្កើនការបង្កើតភេសជ្ជៈរបស់អ្នក</h3>
            </div>
        </div>
        <div class="main-body-image">
    <div class="body-title" id="body-title"></div>
    <div class="body-title2" id="body-title2"></div>
    <div class="body-product" id="body-product"></div>
</div>
        <div class="main-content Recommended">
            <div class="sub-title" style="margin-top: 8rem">
                <h3 style="font-weight: bold; color: #000" data-en="Recommended Products" data-km="ផលិតផលដែលបានណែនាំ">ផលិតផលដែលបានណែនាំ</h3>
            </div>
        </div>
        <div class="body-recommended-product">
            <div class="recommended-product" id="recommended-product"></div>
        </div>
    </div>
    <button id="scrollToTopBtn" style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 100; background-color: #f26923; color: white; border: none; border-radius: 50%; padding: 10px 15px; cursor: pointer;">
        <i class="fa-solid fa-caret-up"></i>
    </button>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
    // កំណត់ភាសាដំបូងជាភាសាខ្មែរ
    document.documentElement.lang = "km";
    const savedLang = localStorage.getItem('language') || 'km';
    document.documentElement.lang = savedLang;
    
    
    // Fetch products from the backend API
    fetch('api.php?action=get_products')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success === false || data.error) {
                console.error('Error fetching products:', data.error || 'Unknown error');
                return;
            }
            const products = data.data || data;
            console.log('Fetched products:', products);

            const subProducts = products.filter(p => p.section === 'sub_product').slice(0, 9);
            const recommendedProducts = products.filter(p => p.section === 'recommended_product').slice(0, 6);
            const enhanceCreations = products.filter(p => p.section === 'enhance_creation'); // Fixed section name

            populateSubProducts(subProducts);
            populateRecommendedProducts(recommendedProducts);
            populateEnhanceCreations(enhanceCreations); // Updated function name for clarity
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('sub-product').innerHTML = '<p data-en="Error loading products. Please try again later." data-km="កំហុសក្នុងការផ្ទុកផលិតផល។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។">កំហុសក្នុងការផ្ទុកផលិតផល។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។</p>';
            document.getElementById('recommended-product').innerHTML = '<p data-en="Error loading products. Please try again later." data-km="កំហុសក្នុងការផ្ទុកផលិតផល។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។">កំហុសក្នុងការផ្ទុកផលិតផល។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។</p>';
            document.getElementById('body-title').innerHTML = '<p data-en="Error loading beverage creations. Please try again later." data-km="កំហុសក្នុងការផ្ទុកការបង្កើតភេសជ្ជៈ។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។">កំហុសក្នុងការផ្ទុកការបង្កើតភេសជ្ជៈ។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។</p>';
            document.getElementById('body-title2').innerHTML = '';
            document.getElementById('body-product').innerHTML = '';
            updateLanguage();
        });

    function populateSubProducts(products) {
        const subProductDiv = document.getElementById('sub-product');
        if (!products || products.length === 0) {
            subProductDiv.innerHTML = '<p data-en="No sub-products available." data-km="គ្មានផលិតផលរងអាចប្រើបានទេ។">គ្មានផលិតផលរងអាចប្រើបានទេ។</p>';
            updateLanguage();
            return;
        }
        subProductDiv.innerHTML = products.map((product, index) => `
            <a href="#" class="ani-${index + 1}">
                <img src="${product.image_url || 'https://via.placeholder.com/150'}" alt="${product.name}" />
                <div><span data-en="${product.name}" data-km="${product.name_km || product.name}">${product.name_km || product.name}</span></div>
            </a>
        `).join('');
        updateLanguage();
    }

    function populateRecommendedProducts(products) {
        const recommendedProductDiv = document.getElementById('recommended-product');
        if (!products || products.length === 0) {
            recommendedProductDiv.innerHTML = '<p data-en="No recommended products available." data-km="គ្មានផលិតផលដែលបានណែនាំអាចប្រើបានទេ។">គ្មានផលិតផលដែលបានណែនាំអាចប្រើបានទេ។</p>';
            updateLanguage();
            return;
        }
        recommendedProductDiv.innerHTML = products.map((product, index) => `
            <a href="#" class="pro-${index + 1}">
                <img src="${product.image_url || 'https://via.placeholder.com/150'}" alt="${product.name}" />
                <div><span data-en="${product.name}" data-km="${product.name_km || product.name}">${product.name_km || product.name}</span></div>
            </a>
        `).join('');
        updateLanguage();
    }

    function populateEnhanceCreations(products) {
        const bodyTitleDiv = document.getElementById('body-title');
        const bodyTitle2Div = document.getElementById('body-title2');
        const bodyProductDiv = document.getElementById('body-product');

        if (!products || products.length === 0) {
            bodyTitleDiv.innerHTML = '<p data-en="No beverage creations available." data-km="គ្មានការបង្កើតភេសជ្ជៈអាចប្រើបានទេ។">គ្មានការបង្កើតភេសជ្ជៈអាចប្រើបានទេ។</p>';
            bodyTitle2Div.innerHTML = '';
            bodyProductDiv.innerHTML = '';
            updateLanguage();
            return;
        }

        // Filter products for each type
        const title1 = products.filter(p => p.type === 'title1');
        const title2 = products.filter(p => p.type === 'title2');
        const title3 = products.filter(p => p.type === 'title3');
        const bodyProducts = products.filter(p => p.type === 'product');

        // Populate body-title (title1 and title2)
        bodyTitleDiv.innerHTML = `
            ${title1.map(product => `
                <a href="#">
                    <img src="${product.image_url || 'https://via.placeholder.com/150'}" alt="${product.name}" class="img1" />
                     
                </a>
            `).join('')}
            ${title2.map(product => `
                <a href="#">
                    <img src="${product.image_url || 'https://via.placeholder.com/150'}" alt="${product.name}" />
                </a>
            `).join('')}
        `;

        // Populate body-title2 (title3)
        bodyTitle2Div.innerHTML = title3.map(product => `
            <a href="#">
                <img src="${product.image_url || 'https://via.placeholder.com/150'}" alt="${product.name}" />
                
            </a>
        `).join('');

        // Populate body-product (products)
        bodyProductDiv.innerHTML = bodyProducts.map((product, index) => `
            <a href="#" class="bev-${index + 1}">
                <img src="${product.image_url || 'https://via.placeholder.com/150'}" alt="${product.name}" />
          
            </a>
        `).join('');

        updateLanguage();
    }

   function updateLanguage() {
        const lang = document.documentElement.lang;
        document.querySelectorAll('[data-en][data-km]').forEach(element => {
            element.textContent = lang === 'en' ? element.dataset.en : element.dataset.km;
        });
        // ធ្វើបច្ចុប្បន្នភាព Navigation Links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.textContent = lang === 'en' ? link.dataset.en : link.dataset.km;
        });
        languageSwitcher.innerHTML = lang === 'en' ? 'EN/ខ្មែរ' : 'ខ្មែរ/EN';
    }

    let currentPage = window.location.pathname.split("/").pop() || 'index.php';
    let navLinks = document.querySelectorAll(".nav-link");
    navLinks.forEach(link => {
        if (link.getAttribute("href") === currentPage) {
            link.classList.add("active");
        }
    });

    const scrollToTopBtn = document.getElementById("scrollToTopBtn");
    window.addEventListener("scroll", () => {
        scrollToTopBtn.style.display = window.scrollY > 300 ? "block" : "none";
    });
    scrollToTopBtn.addEventListener("click", () => {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });

    ScrollReveal().reveal(".slide-image", { duration: 200, origin: "bottom", distance: "50px" });
    const scrollRevealOption = { origin: "bottom", distance: "10px", duration: 1000 };
    ScrollReveal().reveal(".ani-1", { ...scrollRevealOption });
    ScrollReveal().reveal(".ani-2", { ...scrollRevealOption, delay: 200 });
    ScrollReveal().reveal(".ani-3", { ...scrollRevealOption, delay: 400 });
    ScrollReveal().reveal(".ani-4", { ...scrollRevealOption, delay: 600 });
    ScrollReveal().reveal(".ani-5", { ...scrollRevealOption, delay: 800 });
    ScrollReveal().reveal(".ani-6", { ...scrollRevealOption, delay: 1000 });
    ScrollReveal().reveal(".ani-7", { ...scrollRevealOption, delay: 1200 });
    ScrollReveal().reveal(".ani-8", { ...scrollRevealOption, delay: 1400 });
    ScrollReveal().reveal(".ani-9", { ...scrollRevealOption, delay: 1600 });
    ScrollReveal().reveal(".pro-1", { ...scrollRevealOption });
    ScrollReveal().reveal(".pro-2", { ...scrollRevealOption, delay: 1200 });
    ScrollReveal().reveal(".pro-3", { ...scrollRevealOption, delay: 1300 });
    ScrollReveal().reveal(".pro-4", { ...scrollRevealOption, delay: 1400 });
    ScrollReveal().reveal(".pro-5", { ...scrollRevealOption, delay: 1500 });
    ScrollReveal().reveal(".pro-6", { ...scrollRevealOption, delay: 1600 });
    ScrollReveal().reveal(".bev-1", { ...scrollRevealOption });
    ScrollReveal().reveal(".bev-2", { ...scrollRevealOption, delay: 200 });
    ScrollReveal().reveal(".bev-3", { ...scrollRevealOption, delay: 400 });

    const languageSwitcher = document.createElement("div");
    languageSwitcher.style.position = "fixed";
    languageSwitcher.style.bottom = "20px";
    languageSwitcher.style.left = "20px";
    languageSwitcher.style.zIndex = "100";
    languageSwitcher.style.backgroundColor = "#f26923";
    languageSwitcher.style.color = "white";
    languageSwitcher.style.border = "none";
    languageSwitcher.style.borderRadius = "50%";
    languageSwitcher.style.padding = "10px 15px";
    languageSwitcher.style.cursor = "pointer";
    languageSwitcher.innerHTML = savedLang === 'en' ? 'EN/ខ្មែរ' : 'ខ្មែរ/EN'; // កំណត់តាមភាសាដំបូង
    document.body.appendChild(languageSwitcher);

    // ដោះស្រាយការចុច Language Switcher និងរក្សាទុកភាសាទៅ Local Storage
    languageSwitcher.addEventListener("click", () => {
        const currentLang = document.documentElement.lang;
        const newLang = currentLang === "en" ? "km" : "en";
        document.documentElement.lang = newLang;
        localStorage.setItem('language', newLang); // រក្សាទុកភាសាថ្មី
        updateLanguage();
    });


    // ហៅមុខងារធ្វើបច្ចុប្បន្នភាពភាសាដំបូង
    updateLanguage();
    
});
</script>
</body>
</html>