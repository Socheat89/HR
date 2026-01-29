<?php
include("db.php");

// Get the product ID from the URL
$product_id = isset($_GET['id']) ? $_GET['id'] : '';

if (!$product_id) {
    echo "Product not found!";
    exit;
}

// Fetch the product details from the database
$sql = "SELECT * FROM products WHERE id = $product_id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
} else {
    echo "Product not found!";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product["name"] ?> - Product Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
       @import url('https://fonts.googleapis.com/css2?family=Koulen&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html,
body {
    scroll-behavior: smooth;
    font-family: Montserrat;
    background-color: #faf3e3;
}

@media screen and (min-width:1919px) {
    .carousel-item a {
        width: 70%;
        justify-content: center;
        display: block;
        margin: auto;
        align-items: center;
        position: relative;
    }

    .carousel-item img {
        border-radius: 8px;
        -webkit-border-radius: 8px;
        -moz-border-radius: 8px;
        -ms-border-radius: 8px;
        -o-border-radius: 8px;
    }

    .sub-menu {
        margin-top: 7rem;
    }

    .sub-menu {
        width: 1450px;
        align-items: center;
        text-align: center;
        padding: 0;
        margin: auto;
        list-style: none;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
    }

    .sub-menu ul li a {
        text-decoration: none;
        color: rgb(0, 0, 0);
        font-weight: bold;
        font-size: 16px;
        transition: color 0.3s ease;
    }


    .sub-menu ul li a:hover {
        color: rgb(255, 166, 0);
    }

    nav {
        margin-right: 100px;
    }

    .main-logo {
        margin-left: 100px;
    }

    .bg-header {
        display: none;
    }

    .main-body-image .body-title2 {
        justify-content: center;
        display: flex;
        position: absolute;
        left: 17.1rem;
        top: 12rem;
    }

    .menu-category {
        display: none;
    }

}

@media screen and (min-width: 1024px) {
    .carousel-inner {
        width: 60%;
        display: block;
        margin: auto;
        border-radius: 8px;
        top: 6rem;
    }

    .slide-image {
        position: relative;
        top: 5rem;
    }

    .navbar-nav {
        margin-right: 140px;
        font-weight: bold;
        font-size: 16px;
        position: relative;
    }

    .main-logo img {
        margin-left: 140px;
        top: -0.5rem;
        position: fixed;
    }

    .sub-title h3 {
        text-align: center;
    }

    .main-product {
        display: flex;
        justify-content: center;


    }

    .sub-product {
        position: relative;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        align-items: center;
        margin-top: 2rem;
        text-align: center;
        margin: auto;
        transition: all 0.3s;
    }

    .sub-product a {
        align-items: center;
        width: 200px;
        justify-content: center;
        margin: auto;
        transition: all 0.5s;
        -webkit-border-radius: 8px;
        -moz-border-radius: 8px;
        -ms-border-radius: 8px;
        -o-border-radius: 8px;
        -webkit-transition: all 0.5s;
        -moz-transition: all 0.5s;
        -ms-transition: all 0.5s;
        -o-transition: all 0.5s;
    }

    .sub-product span {
        text-align: center;
    }

    .sub-product img:hover {
        transform: scale(1.05);
    }

    .sub-product a:hover {
        box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
    }

    .body-recommended-product {
        display: flex;
        justify-content: center;
        margin-top: 5rem;
    }

    .recommended-product {
        position: relative;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        align-items: center;
        margin-top: 2rem;
        text-align: center;
        gap: 30px;
        margin: auto;
        transition: all 0.3s;
    }

    .recommended-product a {
        align-items: center;
        width: 250px;
        background-color: #fae6ce;
        justify-content: center;
        margin: auto;
        border-radius: 8px;
        padding: 10px;
        transition: all 0.3s;
    }

    .recommended-product img:hover {
        transform: scale(1.05);
        text-decoration: underline;
    }

    .recommended-product a {
        text-decoration: none;
        color: black;
        font-weight: bold;
    }

    .recommended-product a:hover {
        color: #f26923;
    }

    .main-content {

        justify-content: center;
        align-items: center;
        text-align: center;
        margin-top: 2rem;
    }

    .nav-link {
        color: white;
        transition: all 0.3s;
    }

    .main-menu ul li a:hover {
        color: black;
    }

    .sub-product a {
        text-decoration: none;
        color: black;
        font-weight: bold;
    }

    .sub-product a:hover {
        color: #f26923;
    }

    .bg-header {
        background-color: #f26923;
        display: none;
        position: fixed;
        width: 100%;
        height: 15vh;
    }

    .main-body-image {
        display: block;
        justify-content: center;
        position: relative;
        top: 3rem;
    }

    .main-body-image .img1 {
        width: 500px;
    }

    .body-title {
        display: flex;
        justify-content: center;
    }

    .body-title img {
        width: 500px;
    }

    .body-title2 {
        justify-content: center;
        display: flex;
        position: absolute;
        left: 17.5rem;
        top: 13rem;
    }

    .body-product {
        display: flex;
        margin-top: 3rem;
        justify-content: center;
        gap: 20px;
    }

    .body-product img {
        border-radius: 8px;
        width: 255px;
    }

    .menu-category {
        display: none;
    }

    .fa-magnifying-glass {
        position: relative;
        margin-top: 1rem;
        padding: 10px;
        color: #000;
    }

    .main-detail {
        justify-content: center;
        display: block;
        margin: auto;
    }

    .product-detail {
        margin: auto;
        align-items: center;
    }

    .footer {
        display: flex;
        justify-content: space-between;
        width: 60%;
        margin-left: auto;
        margin-right: auto;
        /* ធ្វើឲ្យ table នៅកណ្ដាល */
        position: relative;
    }

    hr {
        border: 2px solid #f26923;
        opacity: 100%;
        width: 70%;
        display: block;
        margin: auto;
        position: relative;
        top: -0.5rem;
    }

    .footer h3 {
        font-size: 15px;
        cursor: pointer;
    }

    .footer a {
        text-decoration: none;
        color: #000000;
    }

    .footer a:hover {
        color: #f26923;
    }

    table {
        border-collapse: collapse;
        width: 50%;
        top: -1rem;
        margin-left: auto;
        margin-right: auto;
        /* ធ្វើឲ្យ table នៅកណ្ដាល */
        position: relative;
    }

    .Caramel h2 {
        align-items: center;
        text-align: center;
    }

    .Caramel img {
        margin: auto;
        justify-content: center;
        display: block;
    }

}

@media screen and (max-width: 768px) {
    .main-menu ul li a {
        position: relative;
        display: block;
        color: rgb(0, 0, 0);
        font-size: 14px;
        padding: 20px;
        z-index: 100;
    }

    .fa-bars {
        margin-left: 20rem;
        position: fixed;
        top: 1.5rem;
        z-index: 100;
    }

    .main-logo img {
        width: 210px;
        position: fixed;
        top: -0.5rem;
        margin-left: 30px;
        z-index: 100;
    }

    .sub-header {
        display: flex;
        position: absolute;
    }

    .navbar-nav {
        position: relative;
        display: flex;
        top: -1rem;
        font-weight: bold;

    }

    #navbarNav {
        z-index: 98;
        margin-top: 3rem;
    }

    .navbar-nav a {
        color: rgb(255, 255, 255);
        font-weight: bold;
        z-index: 100;


    }

    .main-header {
        align-items: center;
    }

    .slide-image {
        position: relative;
        top: 8rem;
    }

    .carousel-inner {
        width: 86%;
        display: block;
        margin: auto;
        border-radius: 8px;
    }

    .carousel-item a {
        margin-top: 6rem;
    }

    .sub-title h3 {
        text-align: center;
        margin-top: 2rem;
        font-size: 18px;
    }

    .main-product {
        justify-content: center;
        display: flex;
        position: relative;
        top: -6rem;
    }

    .sub-product {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        justify-content: center;
        align-items: center;
        margin-top: 2rem;
        text-align: center;
        background-color: #fae6ce;
        border-radius: 8px;
        padding: 10px;
        -webkit-border-radius: 8px;
        -moz-border-radius: 8px;
        -ms-border-radius: 8px;
        -o-border-radius: 8px;
    }

    .sub-product a {
        text-align: center;
        text-decoration: none;
        width: 170px;
        position: relative;
        justify-content: center;
        font-weight: 12px;
        border-radius: 8px;
        transition: all 0.3s;
        color: black;
        font-weight: bold;
    }

    .sub-product span {
        display: block;
        font-size: 10px;
        align-items: center;
        text-align: center;
    }

    .sub-product img {
        text-align: center;
        text-decoration: none;
        width: 100px;
        position: relative;
        justify-content: center;
        transition: all 0.3s;
    }

    .sub-product a:hover {
        color: #f26923;
    }

    .sub-product img:hover {
        transform: scale(1.1);
    }

    .sub-product a {
        text-decoration: none;
    }

    .bg-header {
        background-color: #f26923;
        position: fixed;
        width: 100%;
        height: 10vh;
        z-index: 99;
        top: 0;
    }

    .main-body-image {
        display: block;
        justify-content: center;
        position: relative;
        top: 3rem;
    }

    .main-body-image .img1 {
        width: 150px;
    }

    .body-title {
        display: flex;
        justify-content: center;
    }

    .body-title img {
        width: 200px;
    }

    .body-title2 {
        justify-content: center;
        display: flex;
        position: absolute;
        left: 2.1rem;
        top: 6rem;
    }

    .body-title2 img {
        width: 100px;
    }

    .body-product {
        display: flex;
        margin-top: 3rem;
        justify-content: center;
        gap: 20px;
    }

    .body-product img {
        border-radius: 8px;
        width: 105px;
    }

    .body-recommended-product {
        display: flex;
        justify-content: center;
        margin-top: -2rem;
    }

    .recommended-product {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        justify-content: center;
        align-items: center;
        margin-top: -10rem;
        text-align: center;
        gap: 10px;
    }

    .recommended-product a {
        text-align: center;
        text-decoration: none;
        width: 170px;
        background-color: #fae6ce;
        position: relative;
        justify-content: center;
        padding: 10px 20px;
        font-weight: 12px;
        border-radius: 8px;
        transition: all 0.3s;
    }

    .recommended-product img {
        text-align: center;
        text-decoration: none;
        width: 130px;
        position: relative;
        justify-content: center;
        transition: all 0.3s;
    }

    .recommended-product span {
        display: block;
        font-size: 11.5px;
        align-items: center;
        text-align: center;
    }

    .recommended-product img:hover {
        transform: scale(1.05);
        text-decoration: underline;
    }

    .recommended-product a {
        text-decoration: none;
        font-size: 11.5px;
        color: black;
        font-weight: bold;
    }

    .recommended-product a:hover {
        color: gold;
    }

    .sub-title {
        position: relative;
        top: -5rem;
    }

    .main-body-image {
        position: relative;
        top: -8rem;
    }

    .Creations {
        position: relative;
        top: -5rem;
    }

    .Recommended {
        position: relative;
        top: -9rem;
    }

    .sub-menu ul {
        position: relative;
        top: -5rem;
        font-size: 11px;
    }

    .sub-menu a {
        display: none;
        font-size: 11px;
    }

    .carousel-item {
        margin-top: 6rem;
    }

    .carousel-item img {
        border-radius: 8px;
        -webkit-border-radius: 8px;
        -moz-border-radius: 8px;
        -ms-border-radius: 8px;
        -o-border-radius: 8px;
    }

    button {
        outline: none;
        border: none;
        margin: auto;
        justify-content: center;
        display: block;
        z-index: 99;
    }

    .menu-category {
        align-items: center;
        position: relative;
        text-align: center;
        color: white;
        background-color: #f26923;
        padding: 10px;
        width: 50%;
        margin: auto;
        border-radius: 8px;
        -webkit-border-radius: 8px;
        -moz-border-radius: 8px;
        -ms-border-radius: 8px;
        -o-border-radius: 8px;
    }

}

.fa-bars {
    color: white;
    font-size: 28px;
}

.navbar-toggler {
    border: none;
    outline: none;
}

.nav-link.active {
    font-weight: bold;
    color: #000000 !important;
}

.nav-link.active-menu {
    font-weight: bold;
    color: #f26923 !important;
}

.banner {
    /* top: -18rem; */
    position: relative;
    background-size: cover;
    background-position: center;
    /* height: 100vh; */
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 10px;
}

.sub-menu {
    margin-top: 7rem;
}

.sub-menu ul {
    width: 60%;
    align-items: center;
    text-align: center;
    padding: 0;
    margin: auto;
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
}

.sub-menu ul li {
    /* list-style: none; */
    padding: 10px;
}

.sub-menu ul li a {
    text-decoration: none;
    color: rgb(0, 0, 0);
    font-weight: bold;
    font-size: 16px;
    transition: color 0.3s ease;
}


.sub-menu ul li a:hover {
    color: #f26923;
}

.product {
    display: flex;
    justify-content: center;
}

/* style for menu */
.main1 {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 60%;
    margin: 20px auto;
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.img1 {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 20px;
    text-align: left;
}

.img1 img {
    width: 150px;
    border-radius: 10px;
}

.text {
    max-width: 500px;
}

.video iframe {
    max-width: 100%;
    border-radius: 10px;
}

.submenu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
    width: 100%;
    padding: 20px;
}

.submenu div {
    text-align: center;
    /* background: #fff; */
    padding: 10px;
    border-radius: 10px;
    /* box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); */
    transition: transform 0.2s ease-in-out;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.submenu img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 10px;
}

.submenu .img2 {
    width: 180px;
    height: 180px;
    object-fit: cover;
    border-radius: 10px;
    margin: auto;
}

.submenu div:hover {
    transform: scale(1.05);
}

.submenu span {
    display: block;
    margin-top: 10px;
    font-size: 14px;
    font-weight: bold;
}

a {
    text-decoration: none;
    color: black;
}

@media screen and (max-width: 1024px) {
    section {
        width: 80%;
    }

    .img1 {
        flex-direction: column;
        text-align: center;
    }

    .img1 img {
        width: 100%;
        max-width: 300px;
    }

    .video iframe {
        width: 100%;
        height: auto;
    }
}

@media screen and (max-width: 768px) {
    section {
        width: 90%;
    }

    .submenu {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }

    .submenu img {
        height: 150px;
    }
}

body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    /* background-color: #f9f9f9; */
    text-align: center;
}

@media screen and (max-width: 480px) {
    section {
        width: 95%;
        padding: 10px;
    }

    h2 {
        font-size: 20px;
    }

    .text h3 {
        font-size: 18px;
    }

    .text p {
        font-size: 14px;
    }

    .video iframe {
        height: 200px;
    }

    .submenu img {
        height: 120px;
    }
}

/* Style For jully */
.section5 {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0 auto;
    /* Centers the body */
    font-family: Arial, sans-serif;
    /* background-color: #f8ecdc; */
    text-align: left;
    padding: 20px;
    width: 50%;
    /* Sets the width to 80% of the viewport */
}

.container8 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 40px;
    max-width: 900px;
}

.item {
    display: flex;
    align-items: center;
    gap: 20px;
}

.item img:hover {
    transform: rotate(2deg) scale(1.05);
    transition: 0.2s;

}

.item img {
    width: 150px;
    height: auto;
    border-radius: 10px;
}

.text:hover {
    color: #ff4500;
}

.text {
    font-size: 16px;
    font-weight: bold;
}

.subtext {
    font-size: 10px;
    color: #555;
}

.hr1 {
    border: none;
    /* Removes default border */
    /* border-top: 6px solid #555;  */
    height: 5px;
    background: linear-gradient(to right, #ff7f50, #ff6347, #ff4500);
    width: 29%;
    /* Makes the line span the full width */
    margin: 0;
    /* Adds space before and after the line */
    position: relative;
    top: -10px;
    border-radius: 20px 0px 20px 0;
}

@media screen and (max-width: 768px) {
    .section5 {
        width: 90%;
        /* Adjust the body width for smaller screens */
        padding: 10px;
    }

    .container8 {
        grid-template-columns: 2fr;
        /* Stack items in one column on small screens */
        gap: 20px;
        /* Reduce the gap between items */
    }

    .item {
        flex-direction: row;
        /* Stack image and text vertically */
        align-items: flex-start;
        /* Align items to the left */
    }

    .item img {
        width: 120px;
        /* Make the images smaller */
    }

    .text {
        font-size: 14px;
        /* Adjust font size for readability */
    }

    .subtext {
        font-size: 12px;
    }

    .hr1 {
        width: 50%;
        /* Make the horizontal line shorter */
        
    }
}

    </style>
</head>
<body>
    <div class="container">
        <h2><?= $product["name"] ?></h2>
        <img src="<?= $product["image_url"] ?>" class="product-image" alt="<?= $product["name"] ?>">

        <table class="table table-bordered mt-4">
    <thead>
        <tr>
            <th colspan="2" style="background-color: rgba(216, 216, 216, 0.258);">Chocolate Frappe</th>
        </tr>
    </thead>
    <tbody>
        <tr>	
            <th  style="text-align: left;"><?= nl2br(htmlspecialchars($product["recipe"])) ?></th>
            <td><?= nl2br(htmlspecialchars($product["scale"])) ?></td>
        </tr>
    </tbody>
</table>


        <hr>

        <div class="footer">
            <a href="index.php">
                <h3>&lt;&lt; Return to first page</h3>
            </a>
            <a href="Product.html">
                <h3>&lt;&lt; Back to product page</h3>
            </a>
            <a href="#">
                <h3>&lt;&lt; Drink recipe page</h3>
            </a>
        </div>

    </div>
</body>
</html>
