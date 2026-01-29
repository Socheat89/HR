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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSB7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script defer src="https://unpkg.com/scrollreveal"></script>

    <style>
        :root {
            --primary-color: #ff6f61;
            --secondary-color: #2d2d2d;
            --background-gradient: linear-gradient(135deg, #f9f7f3 0%, #e8e4d9 100%);
            --card-bg: rgba(255, 255, 255, 0.9);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { 
            scroll-behavior: smooth; 
            font-family: 'Noto Sans Khmer', 'Roboto', sans-serif;
            background: var(--background-gradient);
            min-height: 100vh;
        }
        [lang="km"] .en-only { display: none; }
        [lang="en"] .km-only { display: none; }

        /* Header */
        .bg-header {
            background: linear-gradient(90deg, var(--primary-color), #ff8a75);
            box-shadow: var(--shadow);
            height: 80px;
            position: fixed;
            width: 100%;
            z-index: 120;
            transition: all 0.3s ease;
        }
        .navbar {
            background: transparent;
            padding: 1rem 2rem;
        }
        .nav-link {
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: var(--secondary-color);
            transform: translateY(-2px);
        }
        .navbar-toggler { border: none; outline: none; }
        .fa-bars { color: white; font-size: 28px; }

        /* Carousel */
        .slide-image { padding-top: 100px; }
        .carousel-inner {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.5s ease;
            width: 70%;
            margin: 2rem auto;
        }
        .carousel-item img {
            object-fit: cover;
            height: 500px;
            transition: transform 0.5s ease;
        }
        .carousel-item:hover img {
            transform: scale(1.05);
        }
        .carousel-indicators button {
            background-color: var(--primary-color);
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        /* Titles */
        .sub-title h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary-color);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            position: relative;
            text-align: center;
        }
        .sub-title h3::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        /* Product Sections */
        .sub-product, .recommended-product {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .sub-product a, .recommended-product a {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px;
            box-shadow: var(--shadow);
            transition: all 0.4s ease;
            text-decoration: none;
            color: var(--secondary-color);
            font-weight: 600;
            text-align: center;
        }
        .sub-product a:hover, .recommended-product a:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            color: var(--primary-color);
        }
        .sub-product img, .recommended-product img {
            width: 100%;
            border-radius: 10px;
            transition: transform 0.4s ease;
        }
        .sub-product a:hover img, .recommended-product a:hover img {
            transform: scale(1.1);
        }

        /* Beverage Creations Section */
        .main-body-image {
            position: relative;
            text-align: center;
            margin: 3rem 0;
        }
        .body-title img { width: 500px; }
        .body-title2 { position: absolute; top: 13rem; left: 50%; transform: translateX(-50%); }
        .body-product { display: flex; justify-content: center; gap: 2rem; margin-top: 3rem; }
        .body-product img { border-radius: 15px; width: 255px; transition: transform 0.4s ease; }
        .body-product img:hover { transform: scale(1.05); }

        /* Scroll to Top */
        #scrollToTopBtn {
            background: var(--primary-color);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border: none;
            color: white;
            cursor: pointer;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
        }
        #scrollToTopBtn:hover {
            transform: scale(1.1);
            background: #ff8a75;
        }

        /* Responsive Design */
        @media screen and (min-width: 1919px) {
            .carousel-inner { width: 50%; }
            .body-title2 { top: 12rem; }
        }

        @media screen and (min-width: 1024px) {
            .navbar-nav { margin-right: 2rem; }
            .main-logo img { margin-left: 2rem; width: 250px; position: fixed; top: 0.5rem; }
        }

        @media screen and (max-width: 768px) {
            .carousel-inner { width: 90%; }
            .carousel-item img { height: 300px; }
            .sub-product, .recommended-product { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .sub-title h3 { font-size: 1.8rem; }
            .fa-bars { margin-left: 1rem; }
            .main-logo img { width: 180px; margin-left: 1rem; }
            .body-title img { width: 200px; }
            .body-title2 { top: 6rem; }
            .body-title2 img { width: 100px; }
            .body-product img { width: 105px; }
            .navbar-nav { margin-top: 1rem; }
        }
    </style>
</head>
<body>
    <div class="bg-header"></div>
    <section id="main-header" class="py-3">
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
                <h3 data-en="The Ultimate Beverage Solution" data-km="ដំណោះស្រាយភេសជ្ជៈចុងក្រោយ">ដំណោះស្រាយភេសជ្ជៈចុងក្រោយ</h3>
            </div>
        </div>
        <div class="main-product">
            <div class="sub-product" id="sub-product"></div>
        </div>
        <div class="main-content Creations">
            <div class="sub-title" style="margin-top: 5rem">
                <h3 data-en="Enhance Your Beverage Creations" data-km="បង្កើនការបង្កើតភេសជ្ជៈរបស់អ្នក">បង្កើនការបង្កើតភេសជ្ជៈរបស់អ្នក</h3>
            </div>
        </div>
        <div class="main-body-image">
            <div class="body-title" id="body-title"></div>
            <div class="body-title2" id="body-title2"></div>
            <div class="body-product" id="body-product"></div>
        </div>
        <div class="main-content Recommended">
            <div class="sub-title" style="margin-top: 8rem">
                <h3 data-en="Recommended Products" data-km="ផលិតផលដែលបានណែនាំ">ផលិតផលដែលបានណែនាំ</h3>
            </div>
        </div>
        <div class="body-recommended-product">
            <div class="recommended-product" id="recommended-product"></div>
        </div>
    </div>
    <button id="scrollToTopBtn" style="display: none;">
        <i class="fa-solid fa-caret-up"></i>
    </button>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        document.documentElement.lang = "km";
        const savedLang = localStorage.getItem('language') || 'km';
        document.documentElement.lang = savedLang;

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
                const enhanceCreations = products.filter(p => p.section === 'enhance_creation');

                populateSubProducts(subProducts);
                populateRecommendedProducts(recommendedProducts);
                populateEnhanceCreations(enhanceCreations);
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

            const title1 = products.filter(p => p.type === 'title1');
            const title2 = products.filter(p => p.type === 'title2');
            const title3 = products.filter(p => p.type === 'title3');
            const bodyProducts = products.filter(p => p.type === 'product');

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

            bodyTitle2Div.innerHTML = title3.map(product => `
                <a href="#">
                    <img src="${product.image_url || 'https://via.placeholder.com/150'}" alt="${product.name}" />
                </a>
            `).join('');

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
        languageSwitcher.style.backgroundColor = "var(--primary-color)";
        languageSwitcher.style.color = "white";
        languageSwitcher.style.border = "none";
        languageSwitcher.style.borderRadius = "50%";
        languageSwitcher.style.padding = "10px 15px";
        languageSwitcher.style.cursor = "pointer";
        languageSwitcher.innerHTML = savedLang === 'en' ? 'EN/ខ្មែរ' : 'ខ្មែរ/EN';
        document.body.appendChild(languageSwitcher);

        languageSwitcher.addEventListener("click", () => {
            const currentLang = document.documentElement.lang;
            const newLang = currentLang === "en" ? "km" : "en";
            document.documentElement.lang = newLang;
            localStorage.setItem('language', newLang);
            updateLanguage();
        });

        updateLanguage();
    });
    </script>
</body>
</html>