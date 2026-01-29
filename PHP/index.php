<?php
include("db.php");

$sql = "SELECT * FROM products";
$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Products</title>
    <link rel="icon"
        href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABwAAAAcCAMAAABF0y+mAAAAeFBMVEWXWhdHcEx9TBI5MBu1XxuYYiK+ZBvKcyLGdyHpgyPxiyb0kyfmiCfLeyXegCP8kyj1kyjviSXnhSVALxLxiCXJdCQeKBTskCn6jSXlgCPQciHyhyQ8KQ4AAAAAABD+mSnsgySzciL5jCa4ax/rgSPdgCX0iyanaRpYyeWHAAAAKHRSTlM8ADIECRYsSo7f9NSLV7z//rltIeV4C6b/05T/LBVB//5J6GrCfvtnjd1Y2AAAAKBJREFUeAHVUjUWwzAMtcLMzOb737BLuVbmRpue8AMhgIZF4CSuUbQd1yOusej6QRjFiWcoOmmWF2VVm9a6SZQ3bWe8affFMPrIQ1M+j+TtW7te1ke27UdD4b2YxvveTMQB6Ngc8S+cNs1YvpcUeD6X9i8JVBxDU4mDLSaGXNnM6hgmhL40nI+8Rop22gwZTnyba7zYlR5eBNf5Aw+dVJcbUwYK5uv5IWgAAAAASUVORK5CYII=">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/scrollReveal.js/2.0.0/scrollReveal.js"
        integrity="sha512-C5vaj0THdudBZn7DvfkEb/Bt12RDCn2oPOyWN1bsH6NPSw4xJ1eA8NE5QsgURl6cCAd7pvvqtrums8iGcoJU6g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
        integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="product.css" />
</head>
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

<body>
    <div class="main-remove">
        <div class="bg-header"></div>
        <section id="main-header" class=" py-3"
            style="background-color: #f26923; position: fixed; justify-content: space-between; width: 100%; z-index: 120;">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <div class="main-logo">
                            <a href="index.html"><img
                                    src="https://www.longbeachsyrup.com/images/logo%20longbeach-08.png?crc=377204701"
                                    alt="" class="img-fluid" /></a>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <nav class="navbar navbar-expand-lg navbar-light">
                            <div class="navbar-toggler" type="button" data-bs-toggle="collapse"
                                data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false"
                                aria-label="Toggle navigation">
                                <i class="fa-solid fa-bars"></i>
                            </div>
                            <div class="collapse navbar-collapse" id="navbarNav">
                                <ul class="navbar-nav ms-auto">

                                    <li class="nav-item">
                                        <a class="nav-link" href="About.html">About Us</a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" href="Product.html">Products</a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" href="recipes.html">Recipes</a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" href="events.html">Events</a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" href="stores.html">Stores</a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" href="contact.html">Contact</a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" href="careers.html">Careers</a>
                                    </li>

                                </ul>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
        <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <a href="#"><img src="https://www.longbeachsyrup.com/images/_head3.jpg?crc=3995169363"
                            class="d-block w-100" alt=""></a>
                </div>
            </div>
        </div>
        <a href="#" style="text-decoration: none;"><button class="menu-category">Category <i
                    class="fa-solid fa-caret-down"></i></button></a>
        <div class="sub-menu">
            <ul>
                <li><a class="nav-link active-menu" href="#1">Best Sellers &nbsp;<span style="color: gold;">|</span></a>
                </li>
                <li><a class="nav-link" href="product.php">Syrup &nbsp;<span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="#3">Zero Sugar Zero Calories Syrup &nbsp;<span
                            style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="#4">Puree &nbsp;<span style="color: gold;"></span></a></li>
                <li><a class="nav-link" href="#5">Sauce &nbsp;<span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="#6">Powder</a></li>
                <br>
                <li><a class="nav-link" href="#7">Coffee & Tea &nbsp;<span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="#8">Toppings &nbsp;<span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="#9">Kawami Japanese Tea &nbsp;<span style="color: gold;">|</span></a></li>
                <li><a class="nav-link" href="#10">GoFresh Premixed Beverage </a></li>
            </ul>
        </div>
        <div class="main-product" style="display: flex; justify-content: center; margin-top: 2rem;">
            <div class="sub-product">
                <?php while ($row = $result->fetch_assoc()): ?>
                <a href="#" data-page="1"><img src="<?= $row["image_url"] ?>" class="card-img-top" id="itemList" alt="<?= $row["name"] ?>"class="ani-1">
                    <div><span><?= $row["name"] ?></span></div>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <button id="scrollToTopBtn"
        style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 100; background-color: #f26923; color: white; border: none; border-radius: 50%; padding: 10px 15px; cursor: pointer;">
        <i class="fa-solid fa-caret-up"></i>
    </button>
    </div>

    <section class="main-detail">
        <div class="container product-detail" data-page="1" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Caramel Syrup</h2>
                <img src="https://www.longbeachsyrup.com/images/caramel.png?crc=533544367" alt="">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Caramel Milk</th>
                <tr>
                    <th>LongBeach Caramel Syrup </th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>fresh milk </th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>mix all ingredients and pour over ice. </th>

                </tr>
                <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Caramel Latte </th>
                <tr>
                    <th>LongBeach Caramel Syrup </th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>coffee </th>
                    <td>60 ml</td>
                </tr>
                <tr>
                    <th>fresh milk</th>
                    <td>120 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr>
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>
        </div>
        <!-- Dedatil Page 2 -->
        <div class="container product-detail" data-page="2" style="display: none;">
            <div class="Caramel">
                <h2>Classic Caramel Syrup</h2>
                <img src="https://img.boutirapp.com/i/TYlpJPjjjVfqC8Ef8PfUVrddrRdX68Jb4VegiCMk4WJ" alt="">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Classic Caramel Syrup</th>
                <tr>
                    <th>LongBeach Classic Caramel Syrup </th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>fresh milk </th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>mix all ingredients and pour over ice. </th>

                </tr>
                <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Caramel Latte </th>
                <tr>
                    <th>LongBeach Caramel Syrup </th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>coffee </th>
                    <td>60 ml</td>
                </tr>
                <tr>
                    <th>fresh milk</th>
                    <td>120 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr>
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>
        </div>
        <!-- Detail Page 3 -->
        <div class="container product-detail" data-page="3" style="display: none;">
            <div class="Caramel">
                <h2>Vanilla Syrup</h2>
                <img src="https://i.ibb.co/qLz4tFkP/412451.png" alt="Vanilla Syrup">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Vanilla Syrup</th>
                <tr>
                    <th>LongBeach Vanilla Syrup </th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>fresh milk </th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>mix all ingredients and pour over ice. </th>

                </tr>
                <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Caramel Latte </th>
                <tr>
                    <th>Vanilla latte </th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>coffee </th>
                    <td>60 ml</td>
                </tr>
                <tr>
                    <th>fresh milk</th>
                    <td>120 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr>
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 4 -->
        <div class="container product-detail" data-page="4" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Strawberry Syrup</h2>
                <img src="https://i.ibb.co/RG5bvqy7/412443.png" alt="LongBeach Strawberry Syrup">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">sparkling Strawberry</th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>Cool club soda </th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>mix all ingredients and pour over ice. </th>

                </tr>
                <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr>
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 5 -->
        <div class="container product-detail" data-page="5" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Peach Syrup</h2>
                <img src="https://www.longbeachsyrup.com/images/peach%20740ml.png?crc=3923683840"
                    alt="LongBeach Strawberry Syrup">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">sparkling Peach</th>
                <tr>
                    <th>LongBeach Peach Syrup </th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>Cool club soda </th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>mix all ingredients and pour over ice. </th>

                </tr>
                <!-- <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr> -->
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 6 -->
        <div class="container product-detail" data-page="6" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Strawberry Puree</h2>
                <img src="https://www.longbeachsyrup.com/images/strawberry%20puree.png?crc=131882328"
                    alt="LongBeach Strawberry Puree">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Smoothie</th>
                <tr>
                    <th>LongBeach Strawberry Puree </th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>Still Water</th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>ice (1 1/2 glass)</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>Pour all ingredient into a blender and blend well. </th>

                </tr>
                <!-- <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr> -->
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 7 -->
        <div class="container product-detail" data-page="7" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Mixed Berries Puree</h2>
                <img src="https://www.longbeachsyrup.com/images/mixed%20berries%20puree.png?crc=3960545028"
                    alt="LongBeach Mixed Berries Puree">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Mixed Berries Smoothie</th>
                <tr>
                    <th>LongBeach Mixed Berries Puree</th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>Still Water</th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>ice (1 1/2 glass)</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>Pour all ingredient into a blender and blend well. </th>

                </tr>
                <!-- <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr> -->
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 8 -->
        <div class="container product-detail" data-page="8" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Peach Puree</h2>
                <img src="https://www.longbeachsyrup.com/images/peach%20puree.png?crc=4198098536"
                    alt="LongBeach Peach Puree">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Peach Smoothie</th>
                <tr>
                    <th>LongBeach Peach Puree</th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>Still Water</th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>ice (1 1/2 glass)</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>Pour all ingredient into a blender and blend well. </th>

                </tr>
                <!-- <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr> -->
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 9 -->
        <div class="container product-detail" data-page="9" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Thai Honey Mango Puree</h2>
                <img src="https://www.longbeachsyrup.com/images/thai%20honey%20mango%20puree.png?crc=162090747"
                    alt="LongBeach Thai Honey Mango Puree">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Thai Honey Mango Smoothie</th>
                <tr>
                    <th>LongBeach Thai Honey Mango Puree</th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>Still Water</th>
                    <td>30 ml</td>
                </tr>
                <tr>
                    <th>ice (1 1/2 glass)</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>Pour all ingredient into a blender and blend well. </th>

                </tr>
                <!-- <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr> -->
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 10 -->
        <div class="container product-detail" data-page="10" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Chocolate Sauce</h2>
                <img src="https://www.longbeachsyrup.com/images/chocolate%20sauce.png?crc=355962380"
                    alt="LongBeach Chocolate Sauce">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Chocolate Frappe</th>
                <tr>
                    <th>LongBeach Chocolate Sauce</th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Frappe Powder (1/2 - 1 tbsp)</th>
                    <td>10 ml</td>
                </tr>
                <tr>
                    <th>fresh milk</th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>ice (1 1/2 glass)</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>Blend all ingredients until smooth. Decorate Whipped cream with Long Beach Chocolate Sauce.
                    </th>

                </tr>
                <!-- <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr> -->
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 11 -->
        <div class="container product-detail" data-page="11" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Caramel Sauce</h2>
                <img src="https://www.longbeachsyrup.com/images/caramel%20sauce.png?crc=324911031"
                    alt="LongBeach Caramel Sauce">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Caramel Frappe</th>
                <tr>
                    <th>LongBeach Caramel Sauce</th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Frappe Powder (1/2 - 1 tbsp)</th>
                    <td>10 ml</td>
                </tr>
                <tr>
                    <th>fresh milk</th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>ice (1 1/2 glass)</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>Blend all ingredients until smooth. Decorate Whipped cream with Long Beach Chocolate Sauce.
                    </th>

                </tr>
                <!-- <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr> -->
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 12 -->
        <div class="container product-detail" data-page="12" style="display: none;">
            <div class="Caramel">
                <h2>LongBeach Thai Tea Sauce</h2>
                <img src="https://www.longbeachsyrup.com/images/thai%20tea%20sauce.png?crc=4222634641"
                    alt="LongBeach Thai Tea Sauce">
            </div>
            <table>
                <th style="background-color: rgba(216, 216, 216, 0.258);">White Chocolate Thai Tea Frappe</th>
                <tr>
                    <th>LongBeach White Chocolate Syrup</th>
                    <td>15 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Frappe Powder (1/2 - 1 tbsp)</th>
                    <td>15 ml</td>
                </tr>
                <tr>
                    <th>fresh milk</th>
                    <td>90 ml</td>
                </tr>
                <tr>
                    <th>ice (1 1/2 glass)</th>
                    <td>16 oz</td>
                </tr>
                <tr>

                    <th>Blend all ingredients until smooth. Decorate Whipped cream with Long Beach Chocolate Sauce.
                    </th>

                </tr>
                <!-- <br>
                <th style="background-color: rgba(216, 216, 216, 0.258);">Strawberry Lemon Tea </th>
                <tr>
                    <th>LongBeach Strawberry Syrup </th>
                    <td>20 ml</td>
                </tr>
                <tr>
                    <th>LongBeach Lemo Tea Powder </th>
                    <td>15 g</td>
                </tr>
                <tr>
                    <th>still water</th>
                    <td>150 ml</td>
                </tr>
                <tr>
                    <th>ice</th>
                    <td>16 oz</td>
                </tr>
                <tr>
                    <th>mix all ingredients and pour over ice. </th>
                </tr> -->
            </table>
            <hr>
            <div class="footer">
                <a href="index.html">
                    <h3>
                        << Return to first page</h3>
                </a>
                <a href="Product.html">
                    <h3>
                        << Back to product page</h3>
                </a>
                <a href="#">
                    <h3>
                        << Drink recipe page</h3>
                </a>
            </div>

        </div>
        <!-- Detail Page 13 -->
        <div class="container product-detail" data-page="13" style="display: none;">
            <section class="main1">
                <h2>100% Purple Sweet Potato Menu</h2>
                <div class="img1">
                    <img src="https://www.longbeachsyrup.com/images/100-%20purple%20sweet%20potato%20powder2.png"
                        alt="Purple Sweet Potato Powder">
                    <div class="text">
                        <h3>LongBeach 100% Purple Sweet Potato Powder</h3>
                        <p>Made with imported quality sweet potato under a spray drying process. The process is rapid
                            with minimal heat exposure delivering vibrant color and smooth texture. It is 100% sweet
                            potato with no color or flavor added. Perfect for beverage, dessert, and ice cream. <br>
                            Size:
                            200g.</p>
                    </div>
                </div>
                <div class="video">
                    <iframe width="700" height="370" src="https://www.youtube.com/embed/JpAa3maj29g"
                        title="YouTube video player" frameborder="0" allowfullscreen></iframe>
                </div>
                <!-- <div class="submenu">
                    <a href="#">
                        <div class="img2">
                            <img src="https://i.ibb.co/5gfxrc3z/image.png" alt="Sweet Potato Swirl">
                            <span>Sweet Potato Swirl</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img3">
                            <img src="https://i.ibb.co/67XQKT8f/image.png" alt="Purple Sweet Potato">
                            <span>Purple Sweet Potato</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img4">
                            <img src="https://i.ibb.co/kkn3f0R/image.png" alt="Purple Sweet Potato Custard">
                            <span>Purple Sweet Potato Custard</span>
                        </div>
                    </a>
                </div> -->
            </section>
        </div>
        <!-- Detail Page 14 -->
        <div class="container product-detail" data-page="14" style="display: none;">
            <section class="main1">
                <h2>100% Butterfly Pea Menu</h2>
                <div class="img1">
                    <img src="https://www.longbeachsyrup.com/images/100-%20butterfly%20pea%20powder%20v3.png?crc=4071414669"
                        alt="Purple Sweet Potato Powder">
                    <div class="text">
                        <h3>Long Beach 100% Butterfly Pea Powder </h3>
                        <p>Made with real Thai butterfly pea which stems are removed and only petals are finely grinded
                            into powder for more intense colour. it is 100% all natural with no colour or any additives.
                            Applicable to both beverages and dessert.
                            <br> size 100 g
                        </p>
                    </div>
                </div>
                <div class="video">
                    <iframe width="700" height="370" src="https://www.youtube.com/embed/vMBC6dKaBxw?si=YiAoOY-nBF9g3ak0"
                        title="YouTube video player" frameborder="0" allowfullscreen></iframe>
                </div>
                <!-- <div class="submenu">
                    <a href="#">
                        <div class="img2">
                            <img src="https://i.ibb.co/5gfxrc3z/image.png" alt="Sweet Potato Swirl">
                            <span>Sweet Potato Swirl</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img3">
                            <img src="https://i.ibb.co/67XQKT8f/image.png" alt="Purple Sweet Potato">
                            <span>Purple Sweet Potato</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img4">
                            <img src="https://i.ibb.co/kkn3f0R/image.png" alt="Purple Sweet Potato Custard">
                            <span>Purple Sweet Potato Custard</span>
                        </div>
                    </a>
                </div> -->
            </section>
        </div>

    </section>
    <!-- Detail Page 15 -->
    <div class="container product-detail" data-page="15" style="display: none;">
        <section class="main1">
            <h2>Smoothie Menu </h2>
            <div class="img1">
                <img src="https://www.longbeachsyrup.com/images/smoothies%20powder2.png?crc=327698295"
                    alt="Purple Sweet Potato Powder">
                <div class="text">
                    <h3>LongBeach Smoothie Powder </h3>
                    <p>For smooth mouthfeel and soft texture of blended beverages. It helps reduce separation of liquid
                        and ice but not interfering the original desired taste. Perfect for NON-DAIRY blended beverages
                        including smoothies, slush, and granita.
                        <br> size 400 g
                    </p>
                </div>
            </div>
            <div class="video">
                <iframe width="700" height="370" src="https://www.youtube.com/embed/TexxTig92_w?si=9iQaOo4GHMlyU3Vn"
                    title="YouTube video player" frameborder="0" allowfullscreen></iframe>
            </div>
            <!-- <div class="submenu">
                    <a href="#">
                        <div class="img2">
                            <img src="https://i.ibb.co/5gfxrc3z/image.png" alt="Sweet Potato Swirl">
                            <span>Sweet Potato Swirl</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img3">
                            <img src="https://i.ibb.co/67XQKT8f/image.png" alt="Purple Sweet Potato">
                            <span>Purple Sweet Potato</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img4">
                            <img src="https://i.ibb.co/kkn3f0R/image.png" alt="Purple Sweet Potato Custard">
                            <span>Purple Sweet Potato Custard</span>
                        </div>
                    </a>
                </div> -->
        </section>
    </div>
    <!-- Detail Page 16 -->
    <div class="container product-detail" data-page="16" style="display: none;">
        <section class="main1">
            <h2>Assam Black Tea Leaves and Assam Green Tea Leaves Menu </h2>
            <div class="img1">
                <img src="https://www.longbeachsyrup.com/images/black%20tea%20leves%20-3.png?crc=3987365019"
                    alt="Purple Sweet Potato Powder">
                <div class="text">
                    <h3>Long Beach 100% Assam Black Tea Leaves </h3>
                    <p>The tea from high land with Taiwanese tea production process for distinctive aroma and intense
                        taste. The black tea is fermented for more smooth, mellow taste and appealing amber hue. It is
                        perfect for milk tea and also applicable to fruit tea. Simply sleep the tea in hot water for 15
                        mins and remove the tea leaves, before letting the tea sleep for 30-90 mins (the longer the tea
                        sleeps, the more intense taste of tea). The tea can also be brewed from the espresso machine.
                        Then, other ingredients can be mixed with the tea for various menus. <br> size 500 g</p>
                </div>
            </div>
            <div class="video">
                <iframe width="700" height="370" src="https://www.youtube.com/embed/Y0yE0iGxzVM?si=eIX6N5ut7gAdSDJS"
                    title="YouTube video player" frameborder="0" allowfullscreen></iframe>
            </div>
            <!-- <div class="submenu">
                    <a href="#">
                        <div class="img2">
                            <img src="https://i.ibb.co/5gfxrc3z/image.png" alt="Sweet Potato Swirl">
                            <span>Sweet Potato Swirl</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img3">
                            <img src="https://i.ibb.co/67XQKT8f/image.png" alt="Purple Sweet Potato">
                            <span>Purple Sweet Potato</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img4">
                            <img src="https://i.ibb.co/kkn3f0R/image.png" alt="Purple Sweet Potato Custard">
                            <span>Purple Sweet Potato Custard</span>
                        </div>
                    </a>
                </div> -->
        </section>
    </div>
    <!-- Detail Page 17 -->
    <div class="container product-detail" data-page="17" style="display: none;">
        <section class="main1">
            <h2>Thai Tea Menu</h2>
            <div class="img1">
                <img src="https://www.longbeachsyrup.com/images/thai%20tea%20-2.png?crc=115348855"
                    alt="Purple Sweet Potato Powder">
                <div class="text">
                    <h3>LongBeach Thai Tea</h3>
                    <p>Thai Assam tea from highland through the authentic Thai tea process.
                        With our special recipes, the tea has intense taste, superior aroma, and appealing colour at
                        shorter period of tea sleeping time. It is produced under international quality standards for
                        our customers' peace of mind.
                        <br> size 200 / 400 g
                    </p>
                </div>
            </div>
            <div class="video">
                <iframe width="700" height="370" src="https://www.youtube.com/embed/3WxmuH1ilQo?si=Y2FhGYG8rgfhKagx"
                    title="YouTube video player" frameborder="0" allowfullscreen></iframe>
            </div>
            <!-- <div class="submenu">
                    <a href="#">
                        <div class="img2">
                            <img src="https://i.ibb.co/5gfxrc3z/image.png" alt="Sweet Potato Swirl">
                            <span>Sweet Potato Swirl</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img3">
                            <img src="https://i.ibb.co/67XQKT8f/image.png" alt="Purple Sweet Potato">
                            <span>Purple Sweet Potato</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img4">
                            <img src="https://i.ibb.co/kkn3f0R/image.png" alt="Purple Sweet Potato Custard">
                            <span>Purple Sweet Potato Custard</span>
                        </div>
                    </a>
                </div> -->
        </section>
    </div>
    <!-- Detail Page 18 -->
    <div class="container product-detail" data-page="18" style="display: none;">
        <section class="main1">
            <h2>Cream Cheese Foam For Menu</h2>
            <div class="img1">
                <img src="https://www.longbeachsyrup.com/images/cream%20cheese%20foam%20powder2.png?crc=535022860"
                    alt="Purple Sweet Potato Powder">
                <div class="text">
                    <h3>Long Beach Foam Cream Cheese Powder</h3>
                    <p>Taste of premium mascarpone cheese with a soft touch of foam texture. Easy to use. Simply mix it
                        with cold milk or water in a cool container using a hand mixer for 3 minutes or until it turns
                        into foam. There is no other ingredient required besides milk or water. Perfect for fruit tea,
                        milk tea, and any specialty beverage, either hot or cold.
                        <br> size 400 g
                    </p>
                </div>
            </div>
            <div class="video">
                <iframe width="700" height="370" src="https://www.youtube.com/embed/7r_1oWDcUQU?si=84A7tdCHimsewzE3"
                    title="YouTube video player" frameborder="0" allowfullscreen></iframe>
            </div>
            <!-- <div class="submenu">
                    <a href="#">
                        <div class="img2">
                            <img src="https://i.ibb.co/5gfxrc3z/image.png" alt="Sweet Potato Swirl">
                            <span>Sweet Potato Swirl</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img3">
                            <img src="https://i.ibb.co/67XQKT8f/image.png" alt="Purple Sweet Potato">
                            <span>Purple Sweet Potato</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img4">
                            <img src="https://i.ibb.co/kkn3f0R/image.png" alt="Purple Sweet Potato Custard">
                            <span>Purple Sweet Potato Custard</span>
                        </div>
                    </a>
                </div> -->
        </section>
    </div>
    <!-- Detail Page 19 -->
    <div class="container product-detail" data-page="19" style="display: none;">
        <section class="main1">
            <h2>Cream Cheese Foam For Menu</h2>
            <div class="img1">
                <img src="https://www.longbeachsyrup.com/images/cream%20cheese%20foam%20powder2.png?crc=535022860"
                    alt="Purple Sweet Potato Powder">
                <div class="text">
                    <h3>Long Beach Foam Cream Cheese Powder</h3>
                    <p>Taste of premium mascarpone cheese with a soft touch of foam texture. Easy to use. Simply mix it
                        with cold milk or water in a cool container using a hand mixer for 3 minutes or until it turns
                        into foam. There is no other ingredient required besides milk or water. Perfect for fruit tea,
                        milk tea, and any specialty beverage, either hot or cold.
                        <br> size 400 g
                    </p>
                </div>
            </div>
            <div class="video">
                <iframe width="700" height="370" src="https://www.youtube.com/embed/7r_1oWDcUQU?si=84A7tdCHimsewzE3"
                    title="YouTube video player" frameborder="0" allowfullscreen></iframe>
            </div>
            <!-- <div class="submenu">
                    <a href="#">
                        <div class="img2">
                            <img src="https://i.ibb.co/5gfxrc3z/image.png" alt="Sweet Potato Swirl">
                            <span>Sweet Potato Swirl</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img3">
                            <img src="https://i.ibb.co/67XQKT8f/image.png" alt="Purple Sweet Potato">
                            <span>Purple Sweet Potato</span>
                        </div>
                    </a>
                    <a href="#">
                        <div class="img4">
                            <img src="https://i.ibb.co/kkn3f0R/image.png" alt="Purple Sweet Potato Custard">
                            <span>Purple Sweet Potato Custard</span>
                        </div>
                    </a>
                </div> -->
        </section>
    </div>
    <!-- Detail Page 20 -->
    <div class="container product-detail" data-page="20" style="display: none;">
        <section class="section5">
            <h1>Long Beach Konjac</h1>
            <hr class="hr1">
            <br>
            <h2 style="font-size: 16px;">
                Made with quality konjac with addition of carrageenan for desired chewy texture. No gelatin or jelly. It
                is manufactured in Thailand with international standards for guaranteed quality.
                <br>size 800 g/2 kg
            </h2>
            <div class="container8">
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/pachinko%20konjac%20-%20brown%20sugar%20800g%202.png"
                        alt="">
                    <div>
                        <div class="text">LongBeach Brown Sugar Konjac Pearl</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/pachinko%20konjac%20-%20original%20800g.png?crc=17141512"
                        alt="">
                    <div>
                        <div class="text">LongBeach Konjac Pearl in Syrup</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/pachinko%20konjac%20-%20taro%20800g.png?crc=3776410720"
                        alt="">
                    <div>
                        <div class="text">LongBeach Taro Konjac Pearl</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/pachinko%20konjac%20-%20royal%20gold%20crystal%20pearl%20800g.png?crc=471464914"
                        alt="">
                    <div>
                        <div class="text">LongBeach Royal Gold Crystal Konjac Pearl</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/konjac%20-%20sticky%20rice%20with%20coconut%20milk%20800%20g.png?crc=4110691139"
                        alt="">
                    <div>
                        <img src="https://www.longbeachsyrup.com/images/new.png?crc=4051827510" style="width: 70px;"
                            alt="">
                        <div class="text">LongBeach Sticky Rice with
                            Coconut Milk Konjac</div>

                    </div>
                </div>
            </div>
        </section>
    </div>
    <!-- Detail Page 21 -->
    <div class="container product-detail" data-page="21" style="display: none;">
        <section class="section5">
            <h1>Long Beach Popping Boba</h1>
            <hr class="hr1">
            <br>
            <h2 style="font-size: 16px;">
                Made with quality carrageenan from deep sea, red seaweed. Varieties of both sweet and refreshing
                flavours from Long Beach guarantee tastes. Under international production standards for piece of mind.
                <br>size 1 kg
            </h2>
            <div class="container8">
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20strawberry%20-%20can.png?crc=3929878052"
                        alt="">
                    <div>
                        <div class="text">Long Beach Strawberry Popping Boba</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20butterfly%20pea%20-%20can.png?crc=221097810"
                        alt="">
                    <div>
                        <div class="text">LongBeach Butterfly Pea Lemon Popping Boba</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20peach%20-%20can.png?crc=3803820395"
                        alt="">
                    <div>
                        <div class="text">Long Beach Peach Popping Boba</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20lychee%20-%20can.png?crc=3860336305"
                        alt="">
                    <div>
                        <div class="text">LongBeach Lychee Popping Boba </div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20apple%20-%20can.png?crc=422727597"
                        alt="">
                    <div>
                        <div class="text">LongBeach Apple Popping Boba </div>

                    </div>
                </div>
            </div>
        </section>
    </div>
    <!-- Detail Page 22 -->
    <div class="container product-detail" data-page="22" style="display: none;">
        <section class="section5">
            <h1>Long Beach Popping Boba</h1>
            <hr class="hr1">
            <br>
            <h2 style="font-size: 16px;">
                Made with quality carrageenan from deep sea, red seaweed. Varieties of both sweet and refreshing
                flavours from Long Beach guarantee tastes. Under international production standards for piece of mind.
                <br>size 1 kg
            </h2>
            <div class="container8">
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20strawberry%20-%20can.png?crc=3929878052"
                        alt="">
                    <div>
                        <div class="text">Long Beach Strawberry Popping Boba</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20butterfly%20pea%20-%20can.png?crc=221097810"
                        alt="">
                    <div>
                        <div class="text">LongBeach Butterfly Pea Lemon Popping Boba</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20peach%20-%20can.png?crc=3803820395"
                        alt="">
                    <div>
                        <div class="text">Long Beach Peach Popping Boba</div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20lychee%20-%20can.png?crc=3860336305"
                        alt="">
                    <div>
                        <div class="text">LongBeach Lychee Popping Boba </div>

                    </div>
                </div>
                <div class="item">
                    <img src="https://www.longbeachsyrup.com/images/popping%20boba%20-%20apple%20-%20can.png?crc=422727597"
                        alt="">
                    <div>
                        <div class="text">LongBeach Apple Popping Boba </div>

                    </div>
                </div>
            </div>
        </section>
    </div>

    </section>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const productLinks = document.querySelectorAll('.sub-product a');

            productLinks.forEach(link => {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    const page = this.getAttribute('data-page');
                    showProductDetails(page);
                });
            });

            function showProductDetails(page) {
                // Hide all product details
                const productDetails = document.querySelectorAll('.main-remove');
                productDetails.forEach(detail => detail.style.display = 'none');

                // Show the selected product detail
                const selectedDetail = document.querySelector(`.product-detail[data-page="${page}"]`);
                if (selectedDetail) {
                    selectedDetail.style.display = 'block';
                }
            }
        });
    </script>


    <script>
        const scrollToTopBtn = document.getElementById("scrollToTopBtn");

        window.addEventListener("scroll", () => {
            if (window.scrollY > 300) {
                scrollToTopBtn.style.display = "block";
            } else {
                scrollToTopBtn.style.display = "none";
            }
        });

        scrollToTopBtn.addEventListener("click", () => {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });
    </script>
    <script>
        $(document).ready(function () {
            $('.menu-category').click(function () {
                $('.sub-menu a').hide();
                $('.sub-menu a').show();
                $('.sub-menu ul').toggle();
            });
        })
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let currentPage = window.location.pathname.split("/").pop();
            let navLinks = document.querySelectorAll(".nav-link");

            navLinks.forEach((link) => {
                if (link.getAttribute("href") === currentPage) {
                    link.classList.add("active");
                }
            });
        });
        document.addEventListener("DOMContentLoaded", function () {
            const products = document.querySelectorAll('.product-item');

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target); // Stop observing once it is visible
                    }
                });
            }, {
                threshold: 0.5 // Trigger when 50% of the item is in view
            });

            products.forEach(product => {
                observer.observe(product);
            });
        });
    </script>
</body>

</html>