<?php
session_start();
include 'db.php';

$sub_sections = [
    'coffee_tea' => 'Coffee & Tea',
    'syrup' => 'Syrup',
    'toppings' => 'Toppings',
    'zero_sugar' => 'Zero Sugar Zero Calories Syrup',
    'kawami_tea' => 'Kawami Japanese Tea',
    'puree' => 'Puree',
    'sauce' => 'Sauce',
    'powder' => 'Powder',
    'gofresh' => 'GoFresh Premixed Beverage'
];

$menu = 'products';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Products - Longbeach</title>
    <link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABwAAAAcCAMAAABF0y+mAAAAeFBMVEWXWhdHcEx9TBI5MBu1XxuYYiK+ZBvKcyLGdyHpgyPxiyb0kyfmiCfLeyXegCP8kyj1kyjviSXnhSVALxLxiCXJdCQeKBTskCn6jSXlgCPQciHyhyQ8KQ4AAAAAABD+mSnsgySzciL5jCa4ax/rgSPdgCX0iyanaRpYyeWHAAAAKHRSTlM8ADIECRYsSo7f9NSLV7z//rltIeV4C6b/05T/LBVB//5J6GrCfvtnjd1Y2AAAAKBJREFUeAHVUjUWwzAMtcLMzOb737BLuVbmRpue8AMhgIZF4CSuUbQd1yOusej6QRjFiWcoOmmWF2VVm9a6SZQ3bWe8affFMPrIQ1M+j+TtW7te1ke27UdD4b2YxvveTMQB6Ngc8S+cNs1YvpcUeD6X9i8JVBxDU4mDLSaGXNnM6hgmhL40nI+8Rop22gwZTnyba7zYlR5eBNf5Aw+dVJcbUwYK5uv5IWgAAAAASUVORK5CYII=">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/scrollReveal.js/2.0.0/scrollReveal.js" integrity="sha512-C5vaj0THdudBZn7DvfkEb/Bt12RDCn2oPOyWN1bsH6NPSw4xJ1eA8NE5QsgURl6cCAd7pvvqtrums8iGcoJU6g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSV7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Koulen&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
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
            .new-bg {
                display: none;
            }
            .title-product {
                display: none;
            }
        }

       @media screen and (min-width: 1024px) {
    .title-product{
        display: none;
    }
    .carousel-inner{
        width: 60%;
        display: block;
        margin: auto;
        border-radius: 8px;
        top: 6rem;
    }
    .slide-image{
        position: relative;
        top: 5rem;
    }
    .navbar-nav{
        margin-right: 140px;
        font-weight: bold;
        font-size: 16px;
        position: relative;
    }
    .main-logo img{
        margin-left: 140px;
        top: -0.5rem;
        position: fixed;
    }
    .sub-title h3{
        text-align: center;
    }
    .main-product{
        display: flex;
        justify-content: center;
        

    }
    .sub-product{
        position: relative;
        display: grid;
        grid-template-columns: repeat(4,1fr);
        align-items: center;
        margin-top: 2rem;
        text-align: center;
        margin: auto;
        transition: all 0.3s ;
    }
    .sub-product a{
        align-items: center;
        width: 200px;
        justify-content: center;
        margin: auto;
        transition: all 0.5s ;
        -webkit-border-radius: 8px;
        -moz-border-radius: 8px;
        -ms-border-radius: 8px;
        -o-border-radius: 8px;
        -webkit-transition: all 0.5s ;
        -moz-transition: all 0.5s ;
        -ms-transition: all 0.5s ;
        -o-transition: all 0.5s ;
}

    .sub-product span{
        text-align: center;
    }
    .sub-product img:hover{
        transform: scale(1.05);
    }
    .sub-product a:hover{
        box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
    }
    .body-recommended-product{
        display: flex;
        justify-content: center;
        margin-top: 5rem;
    }
    .recommended-product{
        position: relative;
        display: grid;
        grid-template-columns: repeat(3,1fr);
        align-items: center;
        margin-top: 2rem;
        text-align: center;
        gap: 30px;
        margin: auto;
        transition: all 0.3s ;
    }
    .recommended-product a{
        align-items: center;
        width: 250px;
        background-color: #fae6ce;
        justify-content: center;
        margin: auto;
        border-radius: 8px;
        padding: 10px;
        transition: all 0.3s ;
    }
    .recommended-product img:hover{
        transform: scale(1.05);
        text-decoration: underline;
    }
    .recommended-product a{
        text-decoration: none;
        color: black;
        font-weight: bold;
    }
    .recommended-product a:hover{
        color: #f26923;
    }
    .main-content{

        justify-content: center;
        align-items: center;
        text-align: center;
        margin-top: 2rem;
    }
    .nav-link{
        color: white;
        transition: all 0.3s ;
    }
    .main-menu ul li a:hover{
        color: black;
    }
    .sub-product a{
        text-decoration: none;
        color: black;
        font-weight: bold;
    }
    .sub-product a:hover{
        color: #f26923;
    }
    .bg-header{
        background-color: #f26923;
        display: none;
        position: fixed;
        width: 100%;
        height: 15vh;
    }
    .main-body-image{
        display: block;
        justify-content: center;
        position: relative;
        top: 3rem;
    }
    .main-body-image .img1{
        width: 300px;
    }
    .body-title{
        display: flex;
        justify-content: center;
    }
    .body-title img{
        width: 500px;
    }
    .body-title2{
        justify-content: center;
        display: flex;
        position: absolute;
        left: 17.5rem;
        top: 13rem;
    }
    .body-product{
        display: flex;
        margin-top: 3rem;
        justify-content: center;
        gap: 20px;
    }
    .body-product img{
        border-radius: 8px;
        width: 255px;
    }
    .menu-category{
        display: none;
    }
    .fa-magnifying-glass{
        position: relative;
        margin-top: 1rem;
        padding: 10px;
        color: #000;
    }
    .main-detail{
        justify-content: center;
        display: block;
        margin: auto;
    }
    .product-detail{
        margin: auto;
        align-items: center;
    }
    .footer {
        display: flex;
        justify-content: space-between;
        width: 60%;
        margin-left: auto;
        margin-right: auto; /* ធ្វើឲ្យ table នៅកណ្ដាល */
        position: relative;
    }
    hr{
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
    .footer a{
        text-decoration: none;
        color: #000000;
    }
    .footer a:hover{
        color: #f26923;
    }
    table  {
        border-collapse: collapse;
        width: 50%;
        top: -1rem;
        margin-left: auto;
        margin-right: auto; /* ធ្វើឲ្យ table នៅកណ្ដាល */
        position: relative;
    }
    .Caramel h2{
        align-items: center;
        text-align: center;
    }
    .Caramel img{
        margin: auto;
        justify-content: center;
        display: block;
    }
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
        .main {
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
    .new-bg{
        display: none;
    }
    .accordion a{
        color: black;
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
                margin-top: -1rem;
                text-align: center;
                border-radius: 8px;
                gap: 10px;
            }
            .sub-product a {
                text-align: center;
                text-decoration: none;
                background-color: #fae6ce;
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
                box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
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
                z-index: 100;
                height: 11.5vh;
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
            }
            #page {
                width: 100px;
                height: 152px;
            }
            #page2 {
                width: 155px;
                height: 152px;
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
                width: 100%;
                margin-left: auto;
                margin-right: auto;
                position: relative;
            }
            hr {
                border: 2px solid #f26923;
                opacity: 100%;
                width: 100%;
                display: block;
                margin: auto;
                position: relative;
                top: -0.5rem;
            }
            .footer h3 {
                font-size: 9px;
                width: 100%;
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
                width: 100%;
                top: -2rem;
                margin-left: auto;
                margin-right: auto;
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
                width: 200px;
            }
            .submenu {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            .submenu img {
                height: 150px;
            }
            .sub-menu a {
                position: relative;
                top: -6rem;
                display: none;
            }
            .main-card {
                position: relative;
                top: -10rem;
            }
            .new-bg {
                background-color: #fae6ce;
                border-radius: 8px;
                border: none;
                padding: 10px;
            }
            .accordion a {
                color: black;
            }
            .accordion-button {
                border: none;
                outline: none;
                border-radius: 8px;
                background: none;
            }
            button {
                border: none;
                outline: none;
                background: none;
            }
            #collapseOne {
                max-height: 300px;
                overflow-y: auto;
            }
            #collapseFour {
                max-height: 100px;
                overflow-y: auto;
            }
            .nav-link.active-menu {
                font-weight: bold;
                color: #f26923 !important;
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
            position: relative;
            background-size: cover;
            background-position: center;
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
            padding: 10px;
            border-radius: 10px;
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
        body {
            margin: 0;
            padding: 0;
        }
        .section5 {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            text-align: left;
            padding: 20px;
            width: 50%;
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
            height: 5px;
            background: linear-gradient(to right, #ff7f50, #ff6347, #ff4500);
            width: 29%;
            margin: 0;
            position: relative;
            top: -10px;
            border-radius: 20px 0px 20px 0;
        }
        @media screen and (max-width: 768px) {
            .section5 {
                width: 90%;
                padding: 10px;
            }
            .container8 {
                grid-template-columns: 2fr;
                gap: 20px;
            }
            .item {
                flex-direction: row;
                align-items: flex-start;
            }
            .item img {
                width: 120px;
            }
            .text {
                font-size: 14px;
            }
            .subtext {
                font-size: 12px;
            }
            .hr1 {
                width: 50%;
            }
        }
        .loading-spinner { text-align: center; padding: 20px; }
        .error-message { display: none; }
    </style>
</head>
<body>
    <div class="main-remove">
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
                            <div class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                                <i class="fa-solid fa-bars"></i>
                            </div>
                            <div class="collapse navbar-collapse" id="navbarNav">
                                <ul class="navbar-nav ms-auto">
                                    <li class="nav-item"><a class="nav-link" href="About.html">About Us</a></li>
                                    <li class="nav-item"><a class="nav-link active" href="Product.php">Products</a></li>
                                    <li class="nav-item"><a class="nav-link" href="recipes.html">Recipes</a></li>
                                    <li class="nav-item"><a class="nav-link" href="events.html">Events</a></li>
                                    <li class="nav-item"><a class="nav-link" href="stores.html">Stores</a></li>
                                    <li class="nav-item"><a class="nav-link" href="contact.html">Contact</a></li>
                                    <li class="nav-item"><a class="nav-link" href="careers.html">Careers</a></li>
                                </ul>
                                <div class="accordion new-bg" id="productAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingOne">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                                Main Categories
                                            </button>
                                        </h2>
                                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#productAccordion">
                                            <?php foreach ($sub_sections as $key => $name): ?>
                                                <a class="nav-link" href="#<?php echo $key; ?>"><?php echo $name; ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </section>

        <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <a href="#"><img src="https://www.longbeachsyrup.com/images/_head3.jpg?crc=3995169363" class="d-block w-100" alt="Product Banner" loading="lazy"></a>
                </div>
            </div>
        </div>

        <div class="sub-menu">
            <ul>
                <?php foreach ($sub_sections as $key => $name): ?>
                    <li><a href="#<?php echo $key; ?>" class="nav-link"><?php echo $name; ?> <?php echo $key !== 'gofresh' ? ' <span style="color: gold;">|</span>' : ''; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="title-product" style="text-align: center; font-size: 26px; color: black; font-weight: bold; position: relative; top: -7rem;">Products<hr style="border: 1px solid #f26923; opacity: 100%; width: 30%;"></div>

        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #f26923;"></i> Loading products...
        </div>

        <?php foreach ($sub_sections as $section_key => $section_name): ?>
            <div class="main-product" id="<?php echo $section_key; ?>" style="display: none; justify-content: center; margin-top: 2rem;">
                <div class="sub-title"><h3><?php echo $section_name; ?></h3></div>
                <div class="sub-product"></div>
            </div>
            <div class="error-message" id="error-<?php echo $section_key; ?>">No products available for <?php echo $section_name; ?>.</div>
        <?php endforeach; ?>

        <button id="scrollToTopBtn" style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 100; background-color: #f26923; color: white; border: none; border-radius: 50%; padding: 10px 15px; cursor: pointer;">
            <i class="fa-solid fa-caret-up"></i>
        </button>
    </div>

    <section class="main-detail">
        <!-- Dynamic product details will be populated here -->
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        document.addEventListener("DOMContentLoaded", async function () {
            const subSections = <?php echo json_encode($sub_sections); ?>;
            const menu = '<?php echo $menu; ?>';
            const loadingSpinner = document.querySelector('.loading-spinner');
            const scrollToTopBtn = document.getElementById('scrollToTopBtn');
            const mainDetail = document.querySelector('.main-detail');
            const state = { products: [] };

            async function fetchProducts(menu) {
                const url = `api.php?action=get_products${menu ? `&menu=${encodeURIComponent(menu)}` : ''}`;
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    console.log(`Fetched products for menu '${menu}':`, data);
                    if (!data.success) {
                        console.error(`Error fetching products: ${data.error}`);
                        return [];
                    }
                    return data.data;
                } catch (error) {
                    console.error('Fetch error:', error);
                    return [];
                }
            }

            loadingSpinner.style.display = 'block';
            state.products = await fetchProducts(menu);
            console.log('All products:', state.products);

            // Populate products into subsections
            for (const [sectionKey, sectionName] of Object.entries(subSections)) {
                const products = state.products.filter(product => 
                    product.menu === menu && product.submenu === sectionKey && product.active == 1
                );
                console.log(`${sectionKey} products:`, products);
                const sectionDiv = document.getElementById(sectionKey);
                const subProductDiv = sectionDiv.querySelector('.sub-product');

                if (products.length > 0) {
                    sectionDiv.style.display = 'flex';
                    subProductDiv.innerHTML = products.map(product => `
                        <a href="#" data-product-id="${product.id}">
                            <img src="${product.image_url || 'https://via.placeholder.com/146x219'}" alt="${product.name}" loading="lazy" id="${product.id > 12 ? 'page2' : 'page'}">
                            <div><span>${product.name}</span></div>
                        </a>
                    `).join('');
                } else {
                    sectionDiv.style.display = 'none';
                }
            }
            loadingSpinner.style.display = 'none';

            // Populate product details
            mainDetail.innerHTML = state.products.map(product => {
                let detailContent = '';
                if (product.description) {
                    detailContent = `
                        <div class="Caramel">
                            <h2>${product.name}</h2>
                            <img src="${product.image_url || 'https://via.placeholder.com/300'}" alt="${product.name}" loading="lazy">
                        </div>
                        <p>${product.description || 'No description available.'}</p>
                    `;
                } else if (product.id > 12 && product.id <= 19) {
                    detailContent = `
                        <section class="main">
                            <h2>${product.name} Menu</h2>
                            <div class="img1">
                                <img src="${product.image_url || 'https://via.placeholder.com/300'}" alt="${product.name}" loading="lazy">
                                <div class="text">
                                    <h3>${product.name}</h3>
                                    <p>${product.description || 'No description available.'}</p>
                                </div>
                            </div>
                        </section>
                    `;
                } else if (product.id >= 20) {
                    detailContent = `
                        <section class="section5">
                            <h1>${product.name}</h1>
                            <hr class="hr1">
                            <br>
                            <h2 style="font-size: 16px;">${product.description || 'No description available.'}</h2>
                            <div class="container8">
                                <div class="item">
                                    <img src="${product.image_url || 'https://via.placeholder.com/150'}" alt="${product.name}" loading="lazy">
                                    <div><div class="text">${product.name}</div></div>
                                </div>
                            </div>
                        </section>
                    `;
                }
                return `
                    <div class="container product-detail" data-product-id="${product.id}" style="display: none;">
                        ${detailContent}
                        <hr>
                        <div class="footer">
                            <a href="index.php"><h3><< Return to first page</h3></a>
                            <a href="Product.php"><h3><< Back to product page</h3></a>
                            <a href="recipes.html"><h3><< Drink recipe page</h3></a>
                        </div>
                    </div>
                `;
            }).join('');

            const productLinks = document.querySelectorAll('.sub-product a');
            const mainRemove = document.querySelector('.main-remove');
            const productDetails = document.querySelectorAll('.product-detail');

            productLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const productId = this.getAttribute('data-product-id');
                    showProductDetails(productId);
                });
            });

            function showProductDetails(productId) {
                mainRemove.style.display = 'none';
                productDetails.forEach(detail => detail.style.display = 'none');
                const selectedDetail = document.querySelector(`.product-detail[data-product-id="${productId}"]`);
                if (selectedDetail) {
                    selectedDetail.style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            window.addEventListener('scroll', () => {
                scrollToTopBtn.style.display = window.scrollY > 300 ? 'block' : 'none';
            });

            scrollToTopBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            document.querySelectorAll('.sub-menu a').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const sectionId = this.getAttribute('href').substring(1);
                    const section = document.getElementById(sectionId);
                    if (section) section.scrollIntoView({ behavior: 'smooth' });
                });
            });

            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });

            $(document).ready(function() {
                $('.menu-category').click(function() {
                    $('.sub-menu a').hide();
                    $('.sub-menu a').show();
                    $('.sub-menu ul').toggle();
                });
            });
        });
    </script>
</body>
</html>