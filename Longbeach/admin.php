<?php
session_start();
include 'db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$user_role = $_SESSION['user_role'] ?? 'admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Longbeach</title>
    <meta name="keywords" content="Longbeach Cambodia, saosombath, Cambodia products, management system">
    <meta name="description" content="Longbeach Cambodia Admin Dashboard - Manage users, categories, and products efficiently.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.css"
        integrity="sha512-kJlvECunwXftkPwyvHbclArO8wszgBGisiLeuDFwNM8ws+wKIw0sv1os3ClWZOcrEB2eRXULYUsm8OVRGJKwGA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSV7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABwAAAAcCAMAAABF0y+mAAAAeFBMVEWXWhdHcEx9TBI5MBu1XxuYYiK+ZBvKcyLGdyHpgyPxiyb0kyfmiCfLeyXegCP8kyj1kyjviSXnhSVALxLxiCXJdCQeKBTskCn6jSXlgCPQciHyhyQ8KQ4AAAAAABD+mSnsgySzciL5jCa4ax/rgSPdgCX0iyanaRpYyeWHAAAAKHRSTlM8ADIECRYsSo7f9NSLV7z//rltIeV4C6b/05T/LBVB//5J6GrCfvtnjd1Y2AAAAKBJREFUeAHVUjUWwzAMtcLMzOb737BLuVbmRpue8AMhgIZF4CSuUbQd1yOusej6QRjFiWcoOmmWF2VVm9a6SZQ3bWe8affFMPrIQ1M+j+TtW7te1ke27UdD4b2YxvveTMQB6Ngc8S+cNs1YvpcUeD6X9i8JVBxDU4mDLSaGXNnM6hgmhL40nI+8Rop22gwZTnyba7zYlR5eBNf5Aw+dVJcbUwYK5uv5IWgAAAAASUVORK5CYII=" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    /* CSS ដើមរបស់អ្នក (រក្សាដដែល) */
    .category-controls {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .category-folders {
        padding: 10px;
    }

    .folder {
        margin: 10px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .folder-header {
        padding: 10px 15px;
        cursor: pointer;
        background-color: #f8f9fa;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background-color var(--transition-speed);
    }

    .folder-header:hover {
        background-color: #e9ecef;
    }

    .folder-content {
        padding: 15px;
        display: none;
    }

    .folder-content.active {
        display: block;
    }

    .folder-table {
        width: 100%;
        border-collapse: collapse;
    }

    .folder-table th,
    .folder-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .folder-header .folder-toggle {
        font-size: 14px;
        transition: transform 0.3s ease;
    }

    .folder-header.active .folder-toggle {
        transform: rotate(180deg);
    }

    .folder-count {
        background-color: var(--primary-color);
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
    }

    .pagination-controls {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
    }

    .pagination-controls select {
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #ddd;
        cursor: pointer;
    }

    .pagination-controls button {
        padding: 8px 15px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .pagination-controls button:disabled {
        background-color: #ccc;
        cursor: not-allowed;
    }

    .pagination-controls button:hover:not(:disabled) {
        background-color: #e65100;
    }

    #image-preview-modal {
        width: 600px;
        max-width: 90%;
        padding: 20px;
        text-align: center;
    }

    #image-preview-overlay {
        z-index: 100;
    }

    #preview-image {
        margin: 15px 0;
        object-fit: contain;
        z-index: -1;
        transition: transform 0.3s ease;
    }

    .modal-buttons .btn-add {
        background: linear-gradient(135deg, #3498db, #2980b9);
        margin-right: 10px;
    }

    .modal-buttons .btn-add:hover {
        background: linear-gradient(135deg, #2980b9, #3498db);
    }

    .btn-cancel-image,
    .btn-in,
    .btn-out {
        position: relative;
        background-color: #f26923;
        color: white;
        top: 13.5rem;
        z-index: 999;
    }

    :root {
        --primary-color: #f26923;
        --secondary-color: #2c3e50;
        --accent-color: #1abc9c;
        --text-color: #333;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }

    body {
        background-color: #f4f4f4;
        color: var(--text-color);
        line-height: 1.6;
        height: 700vh;
    }

    .container {
        display: flex;
        min-height: 100vh;
        flex-direction: column;
    }

    .sidebar {
    width: 250px;
    background-color: var(--secondary-color);
    color: white;
    padding: 20px;
    position: fixed;
    height: 100%;
    overflow-y: auto;
    transition: width 0.3s;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 30px;
    font-size: 24px;
}

.sidebar ul {
    list-style: none;
}

.sidebar ul li {
    padding: 15px;
    margin: 10px 0;
    background-color: #34495e;
    cursor: pointer;
    border-radius: 4px;
    transition: background-color 0.3s, transform 0.3s;
    display: flex;
    align-items: center;
    gap: 12px; /* ចន្លោះរវាង icon និង text */
}

.sidebar ul li:hover,
.sidebar ul li.active {
    background-color: var(--primary-color);
    transform: scale(1.03); /* កាត់បន្ថយ scale បន្តិចដើម្បីឱ្យស៊ីសង្វាក់ */
}

.sidebar ul li a {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
}

/* កែសម្រួល icon */
.sidebar-icon {
    font-size: 20px; /* កាត់បន្ថយទំហំបន្តិចពី 28px មក 20px */
    width: 24px; /* កំណត់ទទឹងឱ្យស្មើគ្នា */
    text-align: center; /* ឱ្យ icon នៅកណ្តាល */
    transition: transform 0.3s ease;
}

.sidebar ul li:hover .sidebar-icon {
    transform: scale(1.1); /* ពង្រីក icon បន្តិចពេល hover */
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar ul li {
        padding: 12px;
    }
    
    .sidebar-icon {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .sidebar-icon {
        font-size: 16px;
    }
}

    .main-content {
        margin-left: 250px;
        padding: 20px;
        width: calc(100% - 250px);
        transition: margin-left 0.3s, width 0.3s;
        flex: 1;
    }

    .header {
        background-color: white;
        position: sticky;
        top: 0;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 100;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .btn-visit {
        background: linear-gradient(135deg, #1abc9c, #16a085);
        color: white;
        padding: 8px 15px;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: transform 0.2s ease, background-color 0.3s ease;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .btn-visit:hover {
        transform: translateY(-2px);
        background: linear-gradient(135deg, #16a085, #1abc9c);
    }

    .dashboard-cards,
    .category-section,
    .products-section,
    .users-section {
        display: none;
    }

    .dashboard-cards.active,
    .category-section.active,
    .products-section.active,
    .users-section.active {
        display: block;
    }

    .card {
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        text-align: center;
        margin: 10px;
        display: inline-block;
        width: 400px;
        transition: transform 0.3s;
    }

    .card:hover {
        transform: scale(1.05);
    }

    .card h3 {
        margin-bottom: 10px;
        color: var(--secondary-color);
        font-size: 18px;
    }

    .card p {
        font-size: 24px;
        color: var(--accent-color);
    }

    .card .btn-small {
        margin-top: 10px;
        padding: 6px 10px;
        background-color: var(--accent-color);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .card .btn-small:hover {
        background-color: var(--primary-color);
    }

    .table-container {
        overflow-x: auto;
    }

    .category-table,
    .products-table,
    .users-table {
        width: 100%;
        border-collapse: collapse;
        background-color: white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-top: 20px;
    }

    th,
    td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: var(--secondary-color);
        color: white;
        position: sticky;
        top: 0;
    }

    tr:hover {
        background-color: #f1f1f1;
    }

    /* Style ទូទៅសម្រាប់ Buttons */
.btn {
    padding: 8px 16px; /* កែ padding ឱ្យមានទំហំសមស្រប */
    border: none;
    border-radius: 6px; /* បន្ថែម border-radius ឱ្យទន់ជាងមុន */
    cursor: pointer;
    font-size: 14px; /* ទំហំអក្សរសមស្រប */
    font-weight: 500; /* ធ្វើឱ្យអក្សរកាន់តែច្បាស់ */
    text-align: center;
    text-transform: capitalize; /* ធ្វើឱ្យអក្សរដំបូងធំ */
    display: inline-flex; /* ប្រើ flex ដើម្បីរៀបចំ icon និង text */
    align-items: center;
    justify-content: center;
    gap: 6px; /* ចន្លោះរវាង icon និង text */
    transition: all 0.3s ease; /* Animation សម្រាប់ hover និង active */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* បន្ថែម shadow ស្រាល */
}

/* Hover Effect ទូទៅ */
.btn:hover {
    transform: translateY(-2px); /* លើក button ឡើងបន្តិ� */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); /* បង្កើន shadow */
}

/* Disabled State */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Style ជាក់លាក់សម្រាប់ប្រភេទ Buttons */

/* Add Button */
.btn-add {
    background: linear-gradient(135deg, #f26923, #e65100); /* Gradient ពី primary color */
    color: white;
}

.btn-add:hover {
    background: linear-gradient(135deg, #e65100, #f26923); /* ប្តូរទិស gradient */
}

/* Edit Button */
.btn-edit {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}

.btn-edit:hover {
    background: linear-gradient(135deg, #2980b9, #3498db);
}

/* Delete Button */
.btn-delete {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    padding: 6px; /* កាត់បន្ថយ padding សម្រាប់ button ដែលមានតែ icon */
}

.btn-delete:hover {
    background: linear-gradient(135deg, #c0392b, #e74c3c);
}

/* Confirm Button */
.btn-confirm {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
}

.btn-confirm:hover {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
}

/* Cancel Button */
.btn-cancel {
    background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    color: white;
}

.btn-cancel:hover {
    background: linear-gradient(135deg, #7f8c8d, #95a5a6);
}

/* Visit Button (Header) */
.btn-visit {
    background: linear-gradient(135deg, #1abc9c, #16a085);
    color: white;
    padding: 8px 15px;
}

.btn-visit:hover {
    background: linear-gradient(135deg, #16a085, #1abc9c);
}

/* Small Button (Dashboard Cards) */
.btn-small {
    padding: 6px 12px;
    background-color: var(--accent-color);
    color: white;
    border-radius: 4px;
    font-size: 13px;
}

.btn-small:hover {
    background-color: var(--primary-color);
}

/* Pagination Buttons */
.pagination-controls button {
    padding: 8px 16px;
    background: linear-gradient(135deg, #f26923, #e65100);
    color: white;
}

.pagination-controls button:hover:not(:disabled) {
    background: linear-gradient(135deg, #e65100, #f26923);
}

.pagination-controls button:disabled {
    background: linear-gradient(135deg, #d3d3d3, #b0b0b0);
}

/* Image Preview Buttons (Zoom In/Out, Close) */
.btn-in, .btn-out, .btn-cancel-image {
    background: linear-gradient(135deg, #f26923, #e65100);
    color: white;
    padding: 8px 12px;
    position: relative; /* ដក top: 15.5rem ចេញ ព្រោះឥឡូវប្រើ layout សមស្រប */
    z-index: 999;
}

.btn-in:hover, .btn-out:hover, .btn-cancel-image:hover {
    background: linear-gradient(135deg, #e65100, #f26923);
}

/* Modal Buttons */
.modal-buttons .btn {
    padding: 10px 20px;
    min-width: 80px; /* កំណត់ទទឹងអប្បបរមា */
}

/* Responsive Design */
@media (max-width: 768px) {
    .btn {
        padding: 6px 12px;
        font-size: 13px;
    }
    
    .btn-delete {
        padding: 5px;
    }
    
    .modal-buttons .btn {
        padding: 8px 16px;
    }
}

@media (max-width: 480px) {
    .btn {
        padding: 5px 10px;
        font-size: 12px;
    }
    
    .btn-small {
        padding: 5px 10px;
        font-size: 12px;
    }
    
    .actions-cell .btn {
        width: 100%; /* ពង្រីក button ឱ្យពេញទទឹងនៅអេក្រង់តូច */
    }
}

    /* កែសម្រួល Form Container ទូទៅ */
    .form-container {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        border-radius: 12px;
        width: 450px;
        max-width: 90%;
        z-index: 1000;
        transition: all 0.3s ease;
        overflow-y: auto;
        max-height: 80vh;
    }

    .form-container.active {
        display: block;
    }

    .form-container h3 {
        margin-bottom: 20px;
        color: var(--secondary-color);
        font-size: 24px;
        text-align: center;
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 10px;
        overflow-y: hidden;
    }

    .form-container input,
    .form-container select,
    .form-container textarea {
        width: 100%;
        margin: 12px 0;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }

    .form-container input:focus,
    .form-container select:focus,
    .form-container textarea:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 5px rgba(242, 105, 35, 0.3);
    }

    .form-container button {
        margin-right: 10px;
        padding: 10px 20px;
        font-size: 16px;
        border-radius: 6px;
        transition: transform 0.2s ease, background-color 0.3s ease;
    }

    .form-container button:hover {
        transform: translateY(-2px);
    }

    .form-container .btn-add {
        background: linear-gradient(135deg, var(--primary-color), #e65100);
    }

    .form-container .btn-delete {
        background: linear-gradient(135deg, #ff5555, #cc0000);
        padding: 10px;
    }

    /* ការកែសម្រួលជាក់លាក់សម្រាប់ Edit Product Form */
    .form-container#edit-product-form {
        width: 850px; /* បង្កើនទំហំសម្រាប់ content ច្រើន */
        max-width: 95%;
        padding: 30px;
        max-height: 90vh; /* បង្កើនកម្ពស់ */
    }

    #edit-product-form .form-group {
        margin-bottom: 1.2rem;
    }

    #edit-product-form .form-label {
        display: block;
        margin-bottom: 5px;
        color: var(--secondary-color);
        font-weight: 500;
        font-size: 14px;
    }

    #edit-product-form input,
    #edit-product-form select {
        width: 100%;
        margin: 0 0 10px 0;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    #edit-product-form textarea {
        width: 100%;
        min-height: 500px;
        max-height: 500px;
        margin: 0 0 10px 0;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 15px;
        resize: vertical;
        overflow-y: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
        line-height: 1.5;
        transition: border-color 0.3s ease;
    }

    #edit-product-form textarea::-webkit-scrollbar {
        width: 8px;
    }

    #edit-product-form textarea::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    #edit-product-form textarea::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 4px;
    }

    #edit-product-form textarea::-webkit-scrollbar-thumb:hover {
        background: #e65100;
    }

    #edit-product-form .form-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
    }

    /* Responsive Design សម្រាប់ Edit Product Form */
   /* Media Query សម្រាប់អេក្រង់ 1024px */
@media (max-width: 1024px) {
    .sidebar {
        width: 200px; /* កាត់បន្ថយទទឹង sidebar */
    }

    .main-content {
        margin-left: 200px;
        width: calc(100% - 200px);
        padding: 15px;
    }

    .footer {
        margin-left: 200px;
        width: calc(100% - 200px);
    }

    .card {
        width: 48%; /* កែសម្រួលទំហំ card ឱ្យសមស្រប */
        margin: 1%;
    }

    .header {
        padding: 15px;
        flex-direction: column; /* រៀបជាជួរឈរ */
        gap: 10px;
        text-align: center;
    }

    .header-actions {
        flex-direction: row; /* រក្សាជាជួរដេក */
        gap: 15px;
    }

    .table-container {
        margin-top: 15px;
    }

    th, td {
        padding: 10px; /* កាត់បន្ថយ padding */
        font-size: 14px;
    }

    .form-container {
        width: 80%; /* ពង្រីក form បន្តិច */
        padding: 20px;
        max-height: 85vh;
    }

    #edit-product-form {
        width: 90%;
        padding: 20px;
    }

    #edit-product-form textarea {
        min-height: 120px; /* កាត់បន្ថយកម្ពស់ textarea */
        max-height: 200px;
    }

    .modal {
        width: 70%;
    }

    .btn {
        padding: 6px 12px;
    }

    .sidebar ul li {
        padding: 12px;
    }

    .language-selector select {
        padding: 6px;
    }
}

/* Media Query សម្រាប់អេក្រង់ 768px */
@media (max-width: 768px) {
    .sidebar {
        width: 180px; /* កាត់បន្ថយទទឹង sidebar បន្ថែម */
        padding: 15px;
    }

    .main-content {
        margin-left: 180px;
        width: calc(100% - 180px);
        padding: 10px;
    }

    .footer {
        margin-left: 180px;
        width: calc(100% - 180px);
        padding: 15px;
    }

    .card {
        width: 100%; /* ពង្រីក card ឱ្យពេញទទឹង */
        margin: 10px 0;
    }

    .header-actions {
        flex-direction: column; /* រៀបជាជួរឈរ */
        gap: 10px;
    }

    .btn-visit {
        padding: 6px 12px;
        width: 100%; /* ពង្រីក button ឱ្យពេញទទឹង */
        justify-content: center;
    }

    .table-container {
        margin-top: 10px;
    }

    th, td {
        padding: 8px;
        font-size: 13px;
    }

    .actions-cell {
        flex-direction: column; /* រៀប buttons ជាជួរឈរ */
        gap: 6px;
    }

    .actions-cell .btn {
        width: 100%; /* ពង្រីក buttons ឱ្យពេញទទឹង */
        justify-content: center;
    }

    .form-container {
        width: 90%;
        padding: 15px;
    }

    #edit-product-form {
        width: 95%;
        padding: 15px;
    }

    #edit-product-form textarea {
        min-height: 100px;
        max-height: 150px;
        font-size: 13px;
    }

    #edit-product-form input,
    #edit-product-form select {
        font-size: 13px;
    }

    #edit-product-form button {
        padding: 8px 15px;
        font-size: 13px;
    }

    .modal {
        width: 85%;
    }

    .pagination-controls {
        flex-direction: column; /* រៀបជាជួរឈរ */
        gap: 10px;
    }

    .pagination-controls select {
        width: 100%;
    }

    .pagination-controls button {
        width: 100%;
    }
}

/* Media Query សម្រាប់អេក្រង់ 480px */
@media (max-width: 480px) {
    .sidebar {
        width: 100%; /* ពង្រីក sidebar ឱ្យពេញទទឹង */
        height: auto;
        position: relative;
        padding: 10px;
    }

    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 10px;
    }

    .footer {
        margin-left: 0;
        width: 100%;
        padding: 10px;
        position: relative; /* ដក position: fixed ចេញ ដើម្បីឱ្យ scroll បាន */
    }

    .footer-links {
        margin-top: 10px;
    }

    .footer-links a {
        display: block; /* រៀប links ជាជួរឈរ */
        margin: 5px 0;
    }

    .container {
        flex-direction: column;
    }

    .card {
        width: 100%;
        margin: 5px 0;
    }

    .header {
        padding: 10px;
    }

    .header h1 {
        font-size: 18px; /* កាត់បន្ថយទំហំអក្សរ */
    }

    th, td {
        padding: 6px;
        font-size: 12px;
    }

    /* លាក់ column មួយចំនួនដែលមិនសំខាន់នៅអេក្រង់តូច */
    .products-table th:nth-child(4), /* Category */
    .products-table td:nth-child(4),
    .products-table th:nth-child(5), /* Section */
    .products-table td:nth-child(5),
    .products-table th:nth-child(6), /* Type */
    .products-table td:nth-child(6) {
        display: none;
    }

    .products-table td img {
        max-width: 40px; /* កាត់បន្ថយទំហំរូបភាព */
    }

    .form-container {
        width: 95%;
        padding: 10px;
        max-height: 90vh;
    }

    .form-container h3 {
        font-size: 18px;
    }

    .form-container input,
    .form-container select,
    .form-container textarea {
        font-size: 12px;
        padding: 8px;
    }

    .form-container button {
        padding: 8px 12px;
        font-size: 12px;
    }

    #edit-product-form .form-label {
        font-size: 12px;
    }

    #edit-product-form textarea {
        min-height: 80px;
        max-height: 120px;
    }

    .modal {
        width: 90%;
        padding: 15px;
    }

    .modal h3 {
        font-size: 18px;
    }

    .modal p {
        font-size: 14px;
    }

    .modal-buttons {
        flex-direction: column; /* រៀប buttons ជាជួរឈរ */
        gap: 10px;
    }

    .modal-buttons .btn {
        width: 100%;
    }

    .pagination-controls select,
    .pagination-controls button {
        font-size: 12px;
        padding: 6px;
    }

    #scroll-top {
        bottom: 10px;
        right: 10px;
        padding: 8px;
    }
}

/* Media Query សម្រាប់អេក្រង់ធំ (min-width: 1080px) */
@media (min-width: 1080px) {
    .footer {
        margin-left: 250px;
        width: calc(100% - 250px);
        padding: 10px;
    }

    #txt1 {
        position: relative;
        left: 0; /* ដក left: -10rem ចេញ ព្រោះវាមិនចាំបាច់ */
    }
}

    @media (max-width: 480px) {
        .form-container#edit-product-form {
            width: 95%;
            padding: 10px;
        }
        
        #edit-product-form .form-label {
            font-size: 12px;
        }
        
        #edit-product-form input,
        #edit-product-form select,
        #edit-product-form textarea {
            font-size: 12px;
        }
    }

    /* ផ្នែកផ្សេងៗដែលនៅសល់ (រក្សាដដែល) */
    .products-table img {
        max-width: 50px;
        height: auto;
        border-radius: 4px;
    }

    .loading-spinner {
        text-align: center;
        padding: 20px;
    }

    .mb-3 {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        margin-bottom: .5rem;
        color: var(--secondary-color);
        font-weight: 500;
    }

    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
    }

    th input[type="checkbox"],
    td input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        vertical-align: middle;
    }

    .select-item {
        margin: 0 auto;
        display: block;
    }

    .users-table th:first-child,
    .users-table td:first-child,
    .category-table th:first-child,
    .category-table td:first-child,
    .products-table th:first-child,
    .products-table td:first-child {
        width: 40px;
        text-align: center;
        padding: 10px;
    }

    .success-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #1abc9c, #16a085);
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 1001;
        display: none;
        animation: slideIn 0.5s ease forwards, fadeOut 0.5s ease 2.5s forwards;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1002;
    }

    .modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        z-index: 1003;
        width: 400px;
        max-width: 90%;
        text-align: center;
        animation: popIn 0.3s ease forwards;
    }

    .modal h3 {
        color: var(--secondary-color);
        font-size: 22px;
        margin-bottom: 15px;
    }

    .modal p {
        color: #555;
        font-size: 16px;
        margin-bottom: 20px;
    }

    .modal-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
    }

    .modal .btn {
        padding: 10px 20px;
        font-size: 16px;
        transition: transform 0.2s ease, background-color 0.3s ease;
    }

    .modal .btn:hover {
        transform: translateY(-2px);
    }

    .modal .btn-confirm {
        background: linear-gradient(135deg, #ff5555, #cc0000);
    }

    .modal .btn-cancel {
        background: linear-gradient(135deg, #ccc, #999);
        color: white;
    }

    .success-modal {
        background: linear-gradient(135deg, #1abc9c, #16a085);
        color: white;
    }

    .success-modal h3 {
        color: white;
        border-bottom: 2px solid rgba(255, 255, 255, 0.3);
        padding-bottom: 10px;
    }

    .success-modal p {
        color: white;
    }

    .footer {
        background-color: var(--secondary-color);
        color: white;
        padding: 20px;
        text-align: center;
        position: fixed;
        width: 100%;
        transition: margin-left 0.3s;
        z-index: 100;
        bottom: 0;
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
    }

    .footer-links a {
        color: var(--primary-color);
        text-decoration: none;
        margin: 0 5px;
        transition: color 0.3s ease;
    }

    .footer-links a:hover {
        color: var(--accent-color);
    }

    .password-container {
        width: 100%;
        position: relative;
    }

    .password-toggle {
        color: #666;
        transition: color 0.3s ease;
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
    }

    .password-toggle:hover {
        color: var(--primary-color);
    }

    .password-toggle i {
        font-size: 16px;
    }

    .fa-arrow-up-right-from-square:hover {
        transform: scale(1.05);
        transition: all 0.4s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes fadeOut {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
            display: none;
        }
    }

    @keyframes popIn {
        from {
            transform: translate(-50%, -50%) scale(0.8);
            opacity: 0;
        }
        to {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
    }

    .folder {
        opacity: 0;
        animation: fadeIn 0.5s ease-in forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @media (min-width: 1080px) {
        .footer {
            background-color: var(--secondary-color);
            color: white;
            padding: 10px;
            text-align: center;
            position: fixed;
            margin-left: 250px;
            width: 95%;
            transition: margin-left 0.3s;
            z-index: 100;
            bottom: 0;
        }
        #txt1 {
            position: relative;
            left: -10rem;
        }
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 220px;
        }

        .main-content {
            margin-left: 220px;
            width: calc(100% - 220px);
            padding: 15px;
        }

        .card {
            width: 45%;
            margin: 10px 2.5%;
        }

        .header {
            flex-direction: column;
            text-align: center;
            padding: 15px;
        }

        .table-container {
            margin-top: 15px;
        }

        th,
        td {
            padding: 12px;
            font-size: 14px;
        }

        .form-container {
            width: 70%;
            padding: 15px;
            max-height: 80vh;
            overflow-y: auto;
        }

        #product-form {
            width: 65%;
            padding: 15px;
        }

        #product-form input,
        #product-form select,
        #product-form textarea {
            padding: 8px;
            margin: 8px 0;
            font-size: 14px;
        }

        #product-form textarea {
            height: 80px;
            resize: vertical;
        }

        #product-form .mb-3 {
            margin-bottom: 0.8rem;
        }

        #product-form button {
            padding: 8px 15px;
            margin: 5px 5px 0 0;
            display: inline-block;
        }

        .modal {
            width: 60%;
        }

        .btn {
            padding: 6px 10px;
        }

        .sidebar ul li {
            padding: 12px;
        }

        .language-selector select {
            padding: 6px;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 200px;
        }

        .main-content {
            margin-left: 200px;
            width: calc(100% - 200px);
        }

        .footer {
            margin-left: 200px;
        }

        .card {
            width: 100%;
        }

        .header-actions {
            flex-direction: column;
            gap: 10px;
        }

        .btn-visit {
            padding: 6px 12px;
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
            height: auto;
            position: relative;
        }

        .main-content {
            margin-left: 0;
            width: 100%;
        }

        .footer {
            margin-left: 0;
            padding: 10px;
        }

        .footer-links {
            margin-top: 10px;
        }

        .footer-links a {
            display: block;
            margin: 5px 0;
        }

        .container {
            flex-direction: column;
        }

        .card {
            width: 100%;
        }

        .form-container,
        .modal {
            width: 90%;
        }
    }

    .icon {
        font-size: 28px;
    }

    #scroll-top {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: linear-gradient(135deg, #f26923, #e65100);
        color: white;
        border: none;
        padding: 15px 20px;
        cursor: pointer;
        display: none;
        z-index: 101;
        border-radius: 50%;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        transition: transform 0.3s ease, background-color 0.3s ease;
    }

    #scroll-top:hover {
        background: linear-gradient(135deg, #e65100, #f26923);
        transform: scale(1.1);
    }


    .table-container {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .folder-table,
    .products-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background-color: #fff;
        border-radius: 10px;
        overflow: hidden;
    }

    .folder-table th,
    .products-table th {
        background: linear-gradient(135deg, #2c3e50, #34495e);
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .folder-table th:first-child,
    .products-table th:first-child {
        border-top-left-radius: 10px;
    }

    .folder-table th:last-child,
    .products-table th:last-child {
        border-top-right-radius: 10px;
    }

    .folder-table td,
    .products-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.3s ease;
    }

    .folder-table tr:last-child td,
    .products-table tr:last-child td {
        border-bottom: none;
    }

    .folder-table tr:hover,
    .products-table tr:hover {
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .folder-table th input[type="checkbox"],
    .folder-table td input[type="checkbox"],
    .products-table th input[type="checkbox"],
    .products-table td input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--primary-color);
        vertical-align: middle;
    }

    .folder-table td .btn,
    .products-table td .btn {
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 14px;
        margin-right: 5px;
        transition: transform 0.2s ease, background-color 0.3s ease;
    }

    .folder-table td .btn:hover,
    .products-table td .btn:hover {
        transform: translateY(-2px);
    }

    .btn-edit {
        background-color: #3498db;
        color: white;
    }

    .btn-delete {
        background-color: #e74c3c;
        color: white;
    }

    .products-table td img {
        max-width: 60px;
        height: auto;
        border-radius: 6px;
        transition: transform 0.3s ease;
    }

    .products-table td img:hover {
        transform: scale(1.1);
    }

    .folder-table tr:nth-child(even),
    .products-table tr:nth-child(even) {
        background-color: #fdfdfd;
    }

    @media (max-width: 768px) {
        .folder-table th,
        .folder-table td,
        .products-table th,
        .products-table td {
            padding: 10px;
            font-size: 14px;
        }

        .products-table td img {
            max-width: 50px;
        }

        .folder-table td .btn,
        .products-table td .btn {
            padding: 6px 10px;
            font-size: 12px;
        }
    }
</style>
</head>

<body>
    <button id="scroll-top"><i class="fa-solid fa-caret-up"></i></button>
    <!-- Popup -->
    <div class="modal-overlay" id="image-preview-overlay">
        <div class="modal" id="image-preview-modal">
            <h3>Image Preview</h3>
            <img id="preview-image" style="max-width: 100%; max-height: 60vh; border-radius: 8px; z-index: -999;" alt="Product Image">
        </div>
        <div class="modal-buttons">
            <button class="btn btn-in" onclick="zoomImage(1.2)"><i class="fas fa-search-plus"></i></button>
            <button class="btn btn-out" onclick="zoomImage(0.8)"><i class="fas fa-search-minus"></i></button>
            <button class="btn btn-cancel-image" onclick="hideModal('image-preview')">Close</button>
        </div>
    </div>

    <!-- Admin Panel -->
    <div class="container">
<div class="sidebar">
    <h2>Admin Panel</h2>
    <ul>
        <li onclick="showSection('dashboard')">
            <i class="ri-dashboard-fill sidebar-icon"></i>
            <span>Dashboard</span>
        </li>
        <?php if ($user_role === 'admin'): ?>
        <li onclick="showSection('users')">
            <i class="ri-user-3-fill sidebar-icon"></i>
            <span>Users</span>
        </li>
        <?php endif; ?>
        <li onclick="showSection('categories')">
            <i class="ri-folder-fill sidebar-icon"></i>
            <span>Categories</span>
        </li>
        <li onclick="showSection('products')">
            <i class="ri-image-fill sidebar-icon"></i>
            <span>Products</span>
        </li>
        <li style="display: none;" onclick="showSection('reports')">
            <i class="ri-bar-chart-fill sidebar-icon"></i>
            <span>Reports</span>
        </li>
        <li>
            <a href="?logout=true">
                <i class="ri-logout-box-r-fill sidebar-icon"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

        <div class="main-content">
            <div class="header">
                <h1 id="header-title">Welcome to Admin Dashboard</h1>
                <div class="header-actions">
                    <button class="btn btn-visit" onclick="visitWebsite()">
                        Visit Website
                    </button>
                    <i class="fa-solid fa-arrow-up-right-from-square" onclick="visitWebsite()"></i>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                </div>
            </div>

            <div class="loading-spinner" id="loading-spinner">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #2c3e50;"></i> <span>Loading...</span>
            </div>

            <div class="dashboard-cards active" id="dashboard-section"></div>

            <div class="users-section" id="users-section">
                <button class="btn btn-add" onclick="showAddForm('user')">Create New User</button>
                <button class="btn btn-delete" onclick="bulkDelete('users')" id="bulk-delete-users" style="display: none;"><i class="fas fa-trash"></i></button>
                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-users" onclick="toggleSelectAll('users')"></th>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Confirmed</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody"></tbody>
                    </table>
                </div>
            </div>

            <div class="category-section" id="categories-section">
                <button class="btn btn-add" onclick="showAddForm('folder')">Create New Folder</button>
                <button class="btn btn-add" onclick="showAddForm('category')">Add New Category</button>
                <button class="btn btn-delete" onclick="bulkDelete('categories')" id="bulk-delete-categories" style="display: none;"><i class="fas fa-trash"></i></button>
                <div class="table-container">
                    
                    <div class="category-folders" id="category-folders"></div>
                </div>
            </div>

         <div class="products-section" id="products-section">
    <div class="category-controls">
        <button class="btn btn-add" onclick="showAddForm('product')">Add New Product</button>
        <button class="btn btn-delete" onclick="bulkDelete('products')" id="bulk-delete-products" style="display: none;"><i class="fas fa-trash"></i></button>
    </div>
    <div class="search-wrapper">
    <span style="font-weight: bold; font-size: 16px; color: #2c3e50; margin-right: 10px;">Search Product</span>
        <input type="text" id="product-search" class="form-control" placeholder="Search Products..." onkeyup="searchProducts()" style="margin-bottom: 15px; border: 1px solid #ccc; background-color: #fff; padding: 10px 10px 10px 35px; border-radius: 8px; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);">
    </div>
    <div class="table-container">
        <table class="products-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-products" onclick="toggleSelectAll('products')"></th>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Section</th>
                    <th>Type</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="products-tbody"></tbody>
        </table>
    </div>
    <div class="pagination-controls" style="margin-top: 20px; text-align: center;">
        <select id="items-per-page" onchange="updatePagination('products')">
            <option value="5">5 per page</option>
            <option value="10" selected>10 per page</option>
            <option value="25">25 per page</option>
            <option value="50">50 per page</option>
        </select>
        <button class="btn" id="prev-page" onclick="changePage('products', -1)" disabled>Previous</button>
        <span id="page-info">Page 1 of 1</span>
        <button class="btn" id="next-page" onclick="changePage('products', 1)" disabled>Next</button>
    </div>
</div>
        </div>

        <footer class="footer">
            <div class="footer-content" id="txt1">
                <p>© <span id="current-year"></span> LongBeachCambodia. All rights reserved.</p>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a> |
                    <a href="#">Terms of Service</a> |
                    <a href="#">Contact Us</a>
                </div>
            </div>
        </footer>
    </div>

    <div class="success-notification" id="success-notification">
        <i class="fas fa-check-circle" style="margin-right: 10px;"></i> <span>Successfully Added!</span>
    </div>

    <div class="modal-overlay" id="delete-confirm-overlay">
        <div class="modal" id="delete-confirm-modal">
            <h3>Confirm Deletion</h3>
            <p id="delete-confirm-message"></p>
            <div class="modal-buttons">
                <button class="btn btn-confirm" id="delete-confirm-yes">Yes</button>
                <button class="btn btn-cancel" onclick="hideModal('delete-confirm')">No</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="delete-success-overlay">
        <div class="modal success-modal" id="delete-success-modal">
            <h3>Success</h3>
            <p id="delete-success-message"></p>
            <div class="modal-buttons">
                <button class="btn btn-add" onclick="hideModal('delete-success')">OK</button>
            </div>
        </div>
    </div>

    <form class="form-container" id="user-form">
        <h3>Create User</h3>
        <input type="text" id="user-username" class="form-control" placeholder="Username" required>
        <div class="mb-3">
            <label for="user-password" class="form-label">Password</label>
            <div class="password-container">
                <input type="password" id="user-password" class="form-control" placeholder="Password" required minlength="6">
                <span class="password-toggle" onclick="togglePassword('user-password')">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
        </div>
        <select id="user-confirmed" class="form-control">
            <option value="0">Unconfirmed</option>
            <option value="1">Confirmed</option>
        </select>
        <select id="user-role" class="form-control">
            <option value="user">User</option>
            <option value="admin">Admin</option>
        </select>
        <button type="button" class="btn btn-add" onclick="createUser()">Save</button>
        <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
    </form>

    <form class="form-container" id="edit-user-form">
        <h3>Edit User</h3>
        <input type="hidden" id="edit-user-id">
        <input type="text" id="edit-user-username" class="form-control" placeholder="Username" required>
        <div class="mb-3">
            <label for="edit-user-password" class="form-label">Password</label>
            <div class="password-container">
                <input type="password" id="edit-user-password" class="form-control" placeholder="Password (leave blank to keep current)">
                <span class="password-toggle" onclick="togglePassword('edit-user-password')">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
        </div>
        <select id="edit-user-confirmed" class="form-control">
            <option value="0">Unconfirmed</option>
            <option value="1">Confirmed</option>
        </select>
        <select id="edit-user-role" class="form-control">
            <option value="user">User</option>
            <option value="admin">Admin</option>
        </select>
        <button type="button" class="btn btn-add" onclick="saveEditUser()">Save</button>
        <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
    </form>

    <form class="form-container" id="folder-form">
        <h3>Create Folder</h3>
        <input type="text" id="folder-name" placeholder="Folder Name" required>
        <textarea id="folder-description" placeholder="Description" rows="3"></textarea>
        <button type="button" class="btn btn-add" onclick="addFolder()">Save</button>
        <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
    </form>

    <form class="form-container" id="edit-folder-form">
        <h3>Edit Folder</h3>
        <input type="hidden" id="edit-folder-id">
        <input type="text" id="edit-folder-name" placeholder="Folder Name" required>
        <textarea id="edit-folder-description" placeholder="Description" rows="3"></textarea>
        <button type="button" class="btn btn-add" onclick="saveEditFolder()">Save</button>
        <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
    </form>
    <form class="form-container" id="category-form">
        <h3>Add Category</h3>
        <input type="text" id="category-name" placeholder="Category Name" required>
        <select id="category-main-category">
            <option value="">Select Main Category</option>
        </select>
        <textarea id="category-description" placeholder="Description" rows="3"></textarea>
        <button type="button" class="btn btn-add" onclick="addCategory()">Save</button>
        <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
    </form>

  <form class="form-container" id="product-form">
    <h3>Add New Product</h3>
    <input type="text" id="product-name" placeholder="Product Name" required>
    <input type="text" id="product-name-km" placeholder="ឈ្មោះផលិតផល (ខ្មែរ)" required>
    <select id="product-category">
        <option value="">Select Category</option>
    </select>
    <input type="url" id="product-image-url" placeholder="Image URL">
    <select id="product-section" onchange="toggleTypeField('product')">
        <option value="">Select Section</option>
        <option value="">None</option>
        <option value="sub_product">The Ultimate Beverage Solution</option>
        <option value="enhance_creation">Enhance Your Beverage Creations</option>
        <option value="recommended_product">Recommended Products</option>
    </select>
    <select id="product-type" style="display: none;">
        <option value="">Select Type (for Enhance Your Beverage Creations)</option>
        <option value="title1">Title 1 (First Image in Body Title)</option>
        <option value="title2">Title 2 (Second Image in Body Title)</option>
        <option value="title3">Title 3 (Body Title 2)</option>
        <option value="product">Product (Body Product)</option>
    </select>
    <textarea id="product-description" placeholder="Product Description" rows="4"></textarea>
    <textarea id="product-description-km" placeholder="ការពិពណ៌នាផលិតផល (ខ្មែរ)" rows="4"></textarea>
    <div class="mb-3">
        <label for="recipe" class="form-label">Recipe</label>
        <textarea type="text" name="recipe" id="product-recipe" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="recipe_km" class="form-label">រូបមន្ត (ខ្មែរ)</label>
        <textarea type="text" name="recipe_km" id="product-recipe-km" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="scale" class="form-label">Scale</label>
        <textarea type="text" name="scale" id="product-scale" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="scale_km" class="form-label">មាត្រដ្ឋាន (ខ្មែរ)</label>
        <textarea type="text" name="scale_km" id="product-scale-km" class="form-control" rows="4"></textarea>
    </div>
    <button type="button" class="btn btn-add" onclick="addProduct()">Save</button>
    <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
</form>

<form class="form-container" id="product-form">
    <h3>Add New Product</h3>
    <input type="text" id="product-name" placeholder="Product Name">
    <input type="text" id="product-name-km" placeholder="ឈ្មោះផលិតផល (ខ្មែរ)">
    <select id="product-category">
        <option value="">Select Category</option>
    </select>
    <input type="url" id="product-image-url" placeholder="Image URL">
    <select id="product-section" onchange="toggleTypeField('product')">
        <option value="">Select Section</option>
        <option value="">None</option>
        <option value="sub_product">The Ultimate Beverage Solution</option>
        <option value="enhance_creation">Enhance Your Beverage Creations</option>
        <option value="recommended_product">Recommended Products</option>
    </select>
    <select id="product-type" style="display: none;">
        <option value="">Select Type (for Enhance Your Beverage Creations)</option>
        <option value="title1">Title 1 (First Image in Body Title)</option>
        <option value="title2">Title 2 (Second Image in Body Title)</option>
        <option value="title3">Title 3 (Body Title 2)</option>
        <option value="product">Product (Body Product)</option>
    </select>
    <textarea id="product-description" placeholder="Product Description" rows="4"></textarea>
    <textarea id="product-description-km" placeholder="ការពិពណ៌នាផលិតផល (ខ្មែរ)" rows="4"></textarea>
    <div class="mb-3">
        <label for="recipe" class="form-label">Recipe</label>
        <textarea type="text" name="recipe" id="product-recipe" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="recipe_km" class="form-label">រូបមន្ត (ខ្មែរ)</label>
        <textarea type="text" name="recipe_km" id="product-recipe-km" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="scale" class="form-label">Scale</label>
        <textarea type="text" name="scale" id="product-scale" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="scale_km" class="form-label">មាត្រដ្ឋាន (ខ្មែរ)</label>
        <textarea type="text" name="scale_km" id="product-scale-km" class="form-control" rows="4"></textarea>
    </div>
    <button type="button" class="btn btn-add" onclick="addProduct()">Save</button>
    <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
</form>


    <form class="form-container" id="edit-category-form">
    <h3>Edit Category</h3>
    <input type="hidden" id="edit-category-id">
    <input type="text" id="edit-category-name" placeholder="Name" required>
    <label for="edit-category-main-category" class="form-label">Main Category (Required)</label>
    <select id="edit-category-main-category" required>
        <option value="">Select Main Category</option>
    </select>
    <textarea id="edit-category-description" placeholder="Description" rows="3"></textarea>
    <button type="button" class="btn btn-add" onclick="saveEditCategory()">Save</button>
    <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
</form>

<form class="form-container" id="edit-product-form">
    <h3>Edit Product</h3>
    <input type="hidden" id="edit-product-id">
    <input type="text" id="edit-product-name" placeholder="Product Name">
    <input type="text" id="edit-product-name-km" placeholder="ឈ្មោះផលិតផល (ខ្មែរ)">
    <select id="edit-product-category">
        <option value="">Select Category</option>
    </select>
    <input type="url" id="edit-product-image-url" placeholder="Image URL">
    <select id="edit-product-section" onchange="toggleTypeField('edit-product')">
        <option value="">Select Section Home</option>
        <option value="">None</option>
        <option value="sub_product">The Ultimate Beverage Solution</option>
        <option value="enhance_creation">Enhance Your Beverage Creations</option>
        <option value="recommended_product">Recommended Products</option>
    </select>
    <select id="edit-product-type" style="display: none;">
        <option value="">Select Type (for Enhance Your Beverage Creations)</option>
        <option value="title1">Title 1 (First Image in Body Title)</option>
        <option value="title2">Title 2 (Second Image in Body Title)</option>
        <option value="title3">Title 3 (Body Title 2)</option>
        <option value="product">Product (Body Product)</option>
    </select>
    <textarea id="edit-product-description" placeholder="Product Description" rows="4"></textarea>
    <textarea id="edit-product-description-km" placeholder="ការពិពណ៌នាផលិតផល (ខ្មែរ)" rows="4"></textarea>
    <div class="mb-3">
        <label for="recipe" class="form-label">Recipe</label>
        <textarea type="text" name="recipe" id="edit-product-recipe" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="recipe_km" class="form-label">រូបមន្ត (ខ្មែរ)</label>
        <textarea type="text" name="recipe_km" id="edit-product-recipe-km" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="scale" class="form-label">Scale</label>
        <textarea type="text" name="scale" id="edit-product-scale" class="form-control" rows="4"></textarea>
    </div>
    <div class="mb-3">
        <label for="scale_km" class="form-label">មាត្រដ្ឋាន (ខ្មែរ)</label>
        <textarea type="text" name="scale_km" id="edit-product-scale-km" class="form-control" rows="4"></textarea>
    </div>
    <button type="button" class="btn btn-add" onclick="saveEditProduct()">Save</button>
    <button type="button" class="btn btn-delete" onclick="hideAddForm()"><i class="fas fa-trash"></i></button>
</form>

    <script>
    
    
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.querySelector('.container');
            container.style.opacity = 0;
            container.style.transition = 'opacity 1s ease-in-out';
            setTimeout(() => {
                container.style.opacity = 1;
            }, 100);
        });

        const scrollTopBtn = document.getElementById("scroll-top");
        window.onscroll = function() {
            if (document.documentElement.scrollTop > 100) {
            scrollTopBtn.style.display = "block";
            scrollTopBtn.style.opacity = "1";
            scrollTopBtn.style.transform = "translateY(0)";
            } else {
            scrollTopBtn.style.opacity = "0";
            scrollTopBtn.style.transform = "translateY(20px)";
            setTimeout(() => {
                if (document.documentElement.scrollTop <= 100) {
                scrollTopBtn.style.display = "none";
                }
            }, 300); // Match the transition duration
            }
        };

        scrollTopBtn.addEventListener("click", function() {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });

        // Add transition styles for smooth appearance
        scrollTopBtn.style.transition = "opacity 0.3s ease, transform 0.3s ease";

        document.querySelectorAll('.sidebar ul li').forEach(item => {
            item.addEventListener('click', function () {
                document.querySelectorAll('.sidebar ul li').forEach(li => li.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        
        

const state = {
    categories: [],
    mainCategories: [],
    products: [],
    productsDisplayed: [], // ទិន្នន័យដែលបានត្រងសម្រាប់បង្ហាញ
    users: [],
    isLoading: false,
    currentUserId: "<?php echo $_SESSION['user_id']; ?>",
    pagination: {
        products: { currentPage: 1, itemsPerPage: 10 },
        categories: { currentPage: 1, itemsPerPage: 10 },
        users: { currentPage: 1, itemsPerPage: 10 }
    }
};
        const csrfToken = `<?php echo $_SESSION['csrf_token']; ?>`;

        document.addEventListener('DOMContentLoaded', () => {
            loadAllData();
            document.getElementById('current-year').textContent = new Date().getFullYear();
        });

        function visitWebsite() {
            const websiteUrl = 'https://app.vvc.asia/Longbeach/index.php';
            window.open(websiteUrl, '_blank');
        }

        function showLoadingSpinner(show) {
            document.getElementById('loading-spinner').style.display = show ? 'block' : 'none';
        }
        
        
        
        
        function toggleTypeField(formPrefix) {
    const sectionSelect = document.getElementById(`${formPrefix}-section`);
    const typeSelect = document.getElementById(`${formPrefix}-type`);
    if (sectionSelect.value === 'enhance_creation') {
        typeSelect.style.display = 'block';
        typeSelect.setAttribute('required', 'required');
    } else {
        typeSelect.style.display = 'none';
        typeSelect.removeAttribute('required');
        typeSelect.value = '';
    }
}


   async function loadAllData() {
    if (state.isLoading) return;
    state.isLoading = true;
    showLoadingSpinner(true);
    try {
        await Promise.all([
            fetchData('main_categories', data => state.mainCategories = data),
            fetchData('categories', data => { state.categories = data; updatePagination('categories', true); }),
            fetchData('products', data => { 
                state.products = data; 
                state.productsDisplayed = data; // ចម្លងទិន្នន័យដើមទៅ productsDisplayed
                updatePagination('products', true); 
            }),
            fetchData('users', data => { state.users = data; updatePagination('users', true); })
        ]);
        populateCategories(state.categories);
        populateProducts(state.products);
        populateUsers(state.users);
        fetchDashboardStats();
    } catch (error) {
        console.error('Error loading data:', error);
        alert('Failed to load data: ' + error.message);
    } finally {
        state.isLoading = false;
        showLoadingSpinner(false);
    }
}


function searchProducts() {
    const searchTerm = document.getElementById('product-search').value.trim().toLowerCase();
    const filteredProducts = state.products.filter(product => 
        (product.name && product.name.toLowerCase().includes(searchTerm)) ||
        (product.name_km && product.name_km.toLowerCase().includes(searchTerm))
    );
    state.productsDisplayed = filteredProducts; // រក្សាទុកទិន្នន័យដែលបានត្រង
    state.pagination.products.currentPage = 1; // កំណត់ទំព័រទៅ 1 វិញនៅពេលស្វែងរក
    populateProducts(filteredProducts);
    updatePagination('products');
}


function searchCategoriesInFolders() {
    const searchTerm = document.getElementById('category-search').value.trim().toLowerCase();
    const folders = document.querySelectorAll('.folder');

    folders.forEach(folder => {
        const rows = folder.querySelectorAll('.folder-table tbody tr');
        let hasMatch = false;

        rows.forEach(row => {
            const categoryName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const description = row.querySelector('td:nth-child(4)').textContent.toLowerCase();

            if (categoryName.includes(searchTerm) || description.includes(searchTerm)) {
                row.style.display = '';
                hasMatch = true;
            } else {
                row.style.display = 'none';
            }
        });

        folder.style.display = hasMatch ? '' : 'none';
    });
}

// មុខងារស្វែងរក Categories (រក្សាដដែល បើមិនចង់កែ)
function searchCategories() {
    const searchTerm = document.getElementById('category-search').value.trim().toLowerCase();
    const filteredCategories = state.categories.filter(category => 
        (category.name && category.name.toLowerCase().includes(searchTerm)) ||
        (category.description && category.description.toLowerCase().includes(searchTerm))
    );
    populateCategories(filteredCategories);
}
        async function fetchData(type, callback) {
            try {
                const response = await fetch(`api.php?action=get_${type}`, {
                    method: 'GET',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                if (!data.success) throw new Error(data.error || 'API error');
                callback(data.data || []);
            } catch (error) {
                console.error(`Fetch ${type} failed:`, error);
                showErrorNotification(`Failed to load ${type}`);
            }
        }

        function fetchDashboardStats() {
            document.getElementById('dashboard-section').innerHTML = `
                <div class="card"><h3>Total Categories</h3><p>${state.categories.length}</p><button class="btn-small" onclick="showSection('categories')">Add Category</button></div>
                <div class="card"><h3>Total Products</h3><p>${state.products.length}</p><button class="btn-small" onclick="showSection('products')">Add Product</button></div>
                <div class="card"><h3>Unconfirmed Users</h3><p>${state.users.filter(u => !u.confirmed).length}</p><button class="btn-small" onclick="showSection('users')">View Users</button></div>
            `;
        }

        function populateCategories(data) {
            state.categories = data;
            const folderContainer = document.getElementById('category-folders');
            const categorySelects = ['product-category', 'edit-product-category'].map(id => document.getElementById(id));
            const mainCategorySelects = ['category-main-category', 'edit-category-main-category'].map(id => document.getElementById(id));

            const categoryOptions = `<option value="">Select Category</option>` + 
                data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            categorySelects.forEach(select => select.innerHTML = categoryOptions);

            const mainCategoryOptions = `<option value="">Select Main Category</option>` + 
                state.mainCategories.map(mc => `<option value="${mc.id}">${mc.name}</option>`).join('') +
                `<option value="uncategorized">Main Category</option>`;
            mainCategorySelects.forEach(select => select.innerHTML = mainCategoryOptions);

            const groupedCategories = {};
            data.forEach(category => {
                const mainId = category.main_category_id || 'uncategorized';
                if (!groupedCategories[mainId]) {
                    groupedCategories[mainId] = [];
                }
                groupedCategories[mainId].push(category);
            });

            let html = '';
            state.mainCategories.forEach(mainCategory => {
                const categories = groupedCategories[mainCategory.id] || [];
                html += createFolderHTML(mainCategory.id, mainCategory.name, categories);
            });

            const uncategorizedCategories = groupedCategories['uncategorized'] || [];
            html += createFolderHTML('uncategorized', 'Main Category', uncategorizedCategories);

            folderContainer.innerHTML = html || `<p>No categories found</p>`;
        }

        function createFolderHTML(mainId, name, categories) {
            return `
                <div class="folder" data-main-id="${mainId}">
                    <div class="folder-header" onclick="toggleFolder(this)">
                        <span>
                            <i class="fas fa-folder" style="margin-right: 8px;"></i>
                            ${name} 
                            <span class="folder-count">${categories.length}</span>
                        </span>
                        <div>
                            ${mainId !== 'uncategorized' ? `
                                <button class="btn btn-edit" style="margin-right: 10px;" onclick="event.stopPropagation(); showEditFolderForm('${mainId}')">
                                    Edit
                                </button>
                                <button class="btn btn-delete" onclick="event.stopPropagation(); deleteFolder('${mainId}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            ` : ''}
                            <i class="fas fa-chevron-down folder-toggle"></i>
                        </div>
                    </div>
                    <div class="folder-content">
                        <table class="folder-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="select-all-folder" data-main-id="${mainId}" onclick="toggleFolderSelectAll(this, '${mainId}')"></th>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${categories.map((item, index) => `
                                    <tr>
                                        <td><input type="checkbox" class="select-item" data-type="categories" data-id="${item.id}" data-main-id="${mainId}" onclick="updateBulkButton('categories')"></td>
                                        <td>${index + 1}</td>
                                        <td>${item.name}</td>
                                        <td>${item.description || ''}</td>
                                        <td>
                                            <button class="btn btn-edit" onclick="showEditForm('category', ${item.id})">Edit</button>
                                            <button class="btn btn-delete" onclick="deleteCategory(${item.id})"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        async function addFolder() {
            const form = document.getElementById('folder-form');
            if (!form.checkValidity()) return form.reportValidity();
            const data = {
                name: document.getElementById('folder-name').value.trim(),
                description: document.getElementById('folder-description').value.trim() || null
            };

            try {
                const response = await fetch('api.php?action=add_main_category', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}, ${errorText}`);
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Unknown error');
                }

                await Promise.all([
                    fetchData('main_categories', data => state.mainCategories = data),
                    fetchData('categories', data => state.categories = data)
                ]);
                populateCategories(state.categories);
                fetchDashboardStats();
                hideAddForm();
                showSuccessNotification('Folder added successfully');
            } catch (error) {
                console.error('Error adding folder:', error);
                alert(`Error adding folder: ${error.message}`);
            }
        }

        function showEditFolderForm(folderId) {
            const folder = state.mainCategories.find(f => f.id == folderId);
            if (!folder) return;

            document.getElementById('edit-folder-id').value = folder.id;
            document.getElementById('edit-folder-name').value = folder.name;
            document.getElementById('edit-folder-description').value = folder.description || '';
            
            document.querySelectorAll('.form-container').forEach(form => form.classList.remove('active'));
            document.getElementById('edit-folder-form').classList.add('active');
        }

        async function saveEditFolder() {
            const form = document.getElementById('edit-folder-form');
            if (!form.checkValidity()) return form.reportValidity();
            
            const data = {
                id: parseInt(document.getElementById('edit-folder-id').value),
                name: document.getElementById('edit-folder-name').value.trim(),
                description: document.getElementById('edit-folder-description').value.trim() || null
            };
            
            try {
                const response = await fetch('api.php?action=edit_main_category', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Unknown error');
                }
                
                await Promise.all([
                    fetchData('main_categories', data => state.mainCategories = data),
                    fetchData('categories', data => state.categories = data)
                ]);
                populateCategories(state.categories);
                fetchDashboardStats();
                hideAddForm();
                showSuccessNotification('Folder updated successfully');
            } catch (error) {
                console.error('Error updating folder:', error);
                alert(`Error updating folder: ${error.message}`);
            }
        }

        async function deleteFolder(folderId) {
            showModal('delete-confirm', 'Are you sure you want to delete this folder?', async () => {
                try {
                    const response = await fetch(`api.php?action=delete_main_category&id=${folderId}`, {
                        method: 'GET',
                        headers: { 'X-CSRF-Token': csrfToken },
                        credentials: 'include'
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.error || 'Unknown error');
                    }
                    
                    await Promise.all([
                        fetchData('main_categories', data => state.mainCategories = data),
                        fetchData('categories', data => state.categories = data)
                    ]);
                    populateCategories(state.categories);
                    fetchDashboardStats();
                    showModal('delete-success', 'Folder deleted successfully');
                    setTimeout(() => hideModal('delete-success'), 2000);
                } catch (error) {
                    console.error('Error deleting folder:', error);
                    alert(`Error deleting folder: ${error.message}`);
                }
            });
        }

        function toggleFolder(header) {
            const content = header.nextElementSibling;
            header.classList.toggle('active');
            content.classList.toggle('active');
        }

        function toggleFolderSelectAll(checkbox, mainId) {
            const folderCheckboxes = document.querySelectorAll(`.select-item[data-main-id="${mainId}"]`);
            folderCheckboxes.forEach(cb => cb.checked = checkbox.checked);
            updateBulkButton('categories');
        }

    function populateProducts(data) {
    state.productsDisplayed = data; // រក្សាទុកទិន្នន័យដែលបានត្រង
    const tbody = document.getElementById('products-tbody');
    const categorySelects = [document.getElementById('product-category'), document.getElementById('edit-product-category')];
    const options = state.categories.length ?
        `<option value="">Select Category</option>` + state.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('') :
        `<option value="">Load categories first</option>`;
    categorySelects.forEach(select => select.innerHTML = options);

    const { currentPage, itemsPerPage } = state.pagination.products;
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const paginatedData = data.slice(start, end);

    tbody.innerHTML = paginatedData.length ? paginatedData.map((item, index) => `
        <tr>
            <td><input type="checkbox" class="select-item" data-type="products" data-id="${item.id}" onclick="updateBulkButton('products')"></td>
            <td>${start + index + 1}</td>
            <td>${item.name} (${item.name_km || 'N/A'})</td>
            <td>${item.category_name || 'None'}</td>
            <td>${formatSectionName(item.section)}</td>
            <td>${item.section === 'enhance_creation' ? (item.type || 'N/A') : 'N/A'}</td>
            <td><img src="${item.image_url || 'https://via.placeholder.com/50'}" alt="${item.name}" onclick="showImagePreview('${item.image_url}')" style="cursor: pointer;" loading="lazy"></td>
            <td>
                <button class="btn btn-edit" onclick="showEditForm('product', ${item.id})">Edit</button>
                <button class="btn btn-delete" onclick="deleteProduct(${item.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('') : `<tr><td colspan="8">No products found</td></tr>`;
}

   function updatePagination(type) {
    const totalItems = (type === 'products' ? state.productsDisplayed : state[type]).length;
    const { currentPage, itemsPerPage } = state.pagination[type];
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    const select = document.getElementById('items-per-page');
    if (select) { // ពិនិត្យថាមាន select ឬអត់ (សម្រាប់ Products តែប៉ុណ្ណោះ)
        state.pagination[type].itemsPerPage = parseInt(select.value);
    }

    const newTotalPages = Math.ceil(totalItems / state.pagination[type].itemsPerPage);
    if (currentPage > newTotalPages) state.pagination[type].currentPage = newTotalPages || 1;

    if (type === 'products') { // មាន Pagination សម្រាប់ Products តែប៉ុណ្ណោះ
        document.getElementById('page-info').textContent = `Page ${state.pagination[type].currentPage} of ${newTotalPages || 1}`;
        document.getElementById('prev-page').disabled = state.pagination[type].currentPage === 1;
        document.getElementById('next-page').disabled = state.pagination[type].currentPage === newTotalPages || totalItems === 0;
    }

    if (type === 'products') populateProducts(state.productsDisplayed);
    else if (type === 'categories') populateCategories(state.categories);
    else if (type === 'users') populateUsers(state.users);
}
        function changePage(type, delta) {
            state.pagination[type].currentPage += delta;
            updatePagination(type);
        }

        let currentScale = 1;

        function showImagePreview(url) {
            const img = document.getElementById('preview-image');
            img.src = url || 'https://via.placeholder.com/500';
            img.onerror = () => img.src = 'https://via.placeholder.com/500';
            currentScale = 1;
            img.style.transform = `scale(${currentScale})`;
            document.getElementById('image-preview-overlay').style.display = 'block';
        }

        function zoomImage(factor) {
            const img = document.getElementById('preview-image');
            currentScale *= factor;
            currentScale = Math.max(0.5, Math.min(currentScale, 3));
            img.style.transform = `scale(${currentScale})`;
        }

        function populateUsers(data) {
            state.users = data;
            document.getElementById('users-tbody').innerHTML = data.length ? data.map((item, index) => `
                <tr>
                    <td><input type="checkbox" class="select-item" data-type="users" data-id="${item.id}" onclick="updateBulkButton('users')"></td>
                    <td>${index + 1}</td>
                    <td>${item.username}</td>
                    <td>${item.confirmed ? 'Yes' : 'No'}</td>
                    <td>${item.role}</td>
                    <td>
                        <button class="btn btn-edit" onclick="showEditForm('user', ${item.id})">Edit</button>
                        <button class="btn btn-delete" onclick="deleteUser(${item.id})" ${item.id === state.currentUserId ? 'disabled' : ''}><i class="fas fa-trash"></i></button>
                        ${!item.confirmed ? `<button class="btn btn-confirm" onclick="confirmUser(${item.id})">Confirm</button>` : ''}
                    </td>
                </tr>
            `).join('') : `<tr><td colspan="6">No users found</td></tr>`;
        }

        function formatSectionName(section) {
            const sections = {
                'sub_product': 'The Ultimate Beverage Solution',
                'enhance_creation': 'Enhance Beverage Creations',
                'recommended_product': 'Recommended Products'
            };
            return sections[section] || 'None';
        }

function showSection(section) {
    document.querySelectorAll('.dashboard-cards, .category-section, .products-section, .users-section')
        .forEach(el => el.classList.remove('active'));
    const sectionElement = document.getElementById(`${section}-section`);
    if (sectionElement) {
        sectionElement.classList.add('active');
        document.getElementById('header-title').textContent = section.charAt(0).toUpperCase() + section.slice(1).replace('-', ' ');
    }
    // Close the form when navigating to another section
    if (activeForm) {
        activeForm.classList.remove('active');
        activeForm.reset();
        activeForm = null;
    }
}

let activeForm = null;
function showAddForm(type) {
    document.querySelectorAll('.form-container').forEach(form => form.classList.remove('active'));
    const form = document.getElementById(`${type}-form`);
    form.classList.add('active');
    activeForm = form; // Track the active form
}

  function showEditForm(type, id) {
    document.querySelectorAll('.form-container').forEach(form => form.classList.remove('active'));
    const form = document.getElementById(`edit-${type}-form`);
    form.classList.add('active');
    activeForm = form;

    const item = state[`${type}s`].find(i => i.id == id);
    if (item) {
        if (type === 'category') {
            document.getElementById('edit-category-id').value = item.id;
            document.getElementById('edit-category-name').value = item.name;
            document.getElementById('edit-category-main-category').value = item.main_category_id || '';
            document.getElementById('edit-category-description').value = item.description || '';
        } else if (type === 'product') {
            document.getElementById('edit-product-id').value = item.id;
            document.getElementById('edit-product-name').value = item.name;
            document.getElementById('edit-product-name-km').value = item.name_km || '';
            document.getElementById('edit-product-category').value = item.category_id || '';
            document.getElementById('edit-product-image-url').value = item.image_url || '';
            document.getElementById('edit-product-section').value = item.section || '';
            document.getElementById('edit-product-type').value = item.type || '';
            document.getElementById('edit-product-description').value = item.description || '';
            document.getElementById('edit-product-description-km').value = item.description_km || '';
            document.getElementById('edit-product-recipe').value = item.recipe || '';
            document.getElementById('edit-product-recipe-km').value = item.recipe_km || '';
            document.getElementById('edit-product-scale').value = item.scale || '';
            document.getElementById('edit-product-scale-km').value = item.scale_km || '';
            toggleTypeField('edit-product');
        } else if (type === 'user') {
            document.getElementById('edit-user-id').value = item.id;
            document.getElementById('edit-user-username').value = item.username;
            document.getElementById('edit-user-password').value = '';
            document.getElementById('edit-user-confirmed').value = item.confirmed ? '1' : '0';
            document.getElementById('edit-user-role').value = item.role;
        }
    }
}
        
        document.addEventListener('click', (event) => {
    if (activeForm) {
        const isClickInsideForm = activeForm.contains(event.target);
        const isClickOnFormTrigger = event.target.closest('.btn-add') || event.target.closest('.btn-edit');
        
        // If the click is outside the form and not on a button that opens a form, close the form
        if (!isClickInsideForm && !isClickOnFormTrigger) {
            activeForm.classList.remove('active');
            activeForm.reset();
            activeForm = null;
        }
    }
});

document.querySelectorAll('.form-container').forEach(form => {
    form.addEventListener('click', (event) => {
        event.stopPropagation();
    });
});

function hideAddForm() {
    document.querySelectorAll('.form-container').forEach(form => {
        form.classList.remove('active');
        form.reset();
    });
    activeForm = null; // Reset the active form
}

        function showSuccessNotification(message) {
            const notification = document.getElementById('success-notification');
            notification.innerHTML = `<i class="fas fa-check-circle" style="margin-right: 10px;"></i> ${message || 'Successfully Added!'}`;
            notification.style.display = 'block';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        function showModal(type, message, callback) {
            const overlay = document.getElementById(`${type}-overlay`);
            const modalMessage = document.getElementById(`${type}-message`);
            modalMessage.textContent = message;
            overlay.style.display = 'block';

            if (type === 'delete-confirm' && callback) {
                const yesButton = document.getElementById('delete-confirm-yes');
                yesButton.onclick = async () => {
                    await callback();
                    hideModal('delete-confirm');
                };
            }
        }

        function hideModal(type) {
            const overlay = document.getElementById(`${type}-overlay`);
            overlay.style.display = 'none';
        }

        async function saveData(action, data, fetchType, populateFn, successMsg) {
            try {
                const response = await fetch(`api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.error || 'Unknown error');
                await fetchData(fetchType, populateFn);
                fetchDashboardStats();
                hideAddForm();
                showSuccessNotification(successMsg);
            } catch (error) {
                alert(`Error: ${error.message}`);
            }
        }

        async function createUser() {
            const form = document.getElementById('user-form');
            if (!form.checkValidity()) return form.reportValidity();
            const data = {
                username: document.getElementById('user-username').value.trim(),
                password: document.getElementById('user-password').value,
                confirmed: parseInt(document.getElementById('user-confirmed').value),
                role: document.getElementById('user-role').value
            };
            await saveData('create_user', data, 'users', populateUsers, 'User created successfully!');
        }

        async function saveEditUser() {
            const form = document.getElementById('edit-user-form');
            if (!form.checkValidity()) return form.reportValidity();
            const data = {
                id: parseInt(document.getElementById('edit-user-id').value),
                username: document.getElementById('edit-user-username').value.trim(),
                password: document.getElementById('edit-user-password').value.trim() || null,
                confirmed: parseInt(document.getElementById('edit-user-confirmed').value),
                role: document.getElementById('edit-user-role').value
            };
            await saveData('edit_user', data, 'users', populateUsers, 'User updated successfully!');
        }

        async function deleteUser(id) {
            if (id === state.currentUserId) {
                alert('You cannot delete your own account');
                return;
            }
            showModal('delete-confirm', 'Are you sure you want to delete this user?', async () => {
                try {
                    const response = await fetch(`api.php?action=delete_user&id=${id}`, {
                        method: 'GET',
                        credentials: 'include',
                        headers: { 'X-CSRF-Token': csrfToken }
                    });
                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Unknown error');
                    await fetchData('users', populateUsers);
                    fetchDashboardStats();
                    showModal('delete-success', 'User deleted successfully!');
                    setTimeout(() => hideModal('delete-success'), 2000);
                } catch (error) {
                    alert(`Error deleting user: ${error.message}`);
                }
            });
        }

        async function addCategory() {
            const form = document.getElementById('category-form');
            if (!form.checkValidity()) return form.reportValidity();
            const mainCategoryValue = document.getElementById('category-main-category').value;
            const data = {
                name: document.getElementById('category-name').value.trim(),
                main_category_id: mainCategoryValue === 'uncategorized' ? null : mainCategoryValue || null,
                description: document.getElementById('category-description').value.trim() || null
            };
            await saveData('add_category', data, 'categories', populateCategories, 'Category added successfully!');
        }

async function addProduct() {
    const section = document.getElementById('product-section').value;
    const data = {
        name: document.getElementById('product-name').value.trim() || null,
        name_km: document.getElementById('product-name-km').value.trim() || null,
        category_id: document.getElementById('product-category').value || null,
        image_url: document.getElementById('product-image-url').value.trim() || null,
        section: section || null,
        type: section === 'enhance_creation' ? document.getElementById('product-type').value || null : null,
        description: document.getElementById('product-description').value.trim() || null,
        description_km: document.getElementById('product-description-km').value.trim() || null,
        recipe: document.getElementById('product-recipe').value.trim() || null,
        recipe_km: document.getElementById('product-recipe-km').value.trim() || null,
        scale: document.getElementById('product-scale').value.trim() || null,
        scale_km: document.getElementById('product-scale-km').value.trim() || null
    };
    await saveData('add_product', data, 'products', populateProducts, 'Product added successfully!');
}

     async function saveEditCategory() {
    const form = document.getElementById('edit-category-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const name = document.getElementById('edit-category-name').value.trim();
    const mainCategoryValue = document.getElementById('edit-category-main-category').value;

    if (!name) {
        showErrorNotification('Category name is required.');
        return;
    }

    if (!mainCategoryValue) {
        showErrorNotification('Please select a Main Category.');
        return;
    }

    const data = {
        id: parseInt(document.getElementById('edit-category-id').value),
        name: name,
        main_category_id: mainCategoryValue === 'uncategorized' ? null : mainCategoryValue || null,
        description: document.getElementById('edit-category-description').value.trim() || null
    };

    console.log('Data being sent to edit_category:', data);

    try {
        const response = await fetch('api.php?action=edit_category', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(data),
            credentials: 'include'
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Unknown error');
        }

        await fetchData('categories', populateCategories);
        fetchDashboardStats();
        hideAddForm();
        showSuccessNotification('Category updated successfully!');
    } catch (error) {
        console.error('Error updating category:', error);
        if (error.message.includes('Missing required fields')) {
            showErrorNotification('Please ensure all required fields are filled. Make sure to select a Main Category.');
        } else {
            showErrorNotification(`Error updating category: ${error.message}`);
        }
    }
}

function showErrorNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'success-notification';
    notification.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
    notification.innerHTML = `<i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i> ${message}`;
    document.body.appendChild(notification);
    notification.style.display = 'block';
    setTimeout(() => {
        notification.style.display = 'none';
        notification.remove();
    }, 3000);
}

async function saveEditProduct() {
    const section = document.getElementById('edit-product-section').value;
    const data = {
        id: parseInt(document.getElementById('edit-product-id').value),
        name: document.getElementById('edit-product-name').value.trim() || null,
        name_km: document.getElementById('edit-product-name-km').value.trim() || null,
        category_id: document.getElementById('edit-product-category').value || null,
        image_url: document.getElementById('edit-product-image-url').value.trim() || null,
        section: section || null,
        type: section === 'enhance_creation' ? document.getElementById('edit-product-type').value || null : null,
        description: document.getElementById('edit-product-description').value.trim() || null,
        description_km: document.getElementById('edit-product-description-km').value.trim() || null,
        recipe: document.getElementById('edit-product-recipe').value.trim() || null,
        recipe_km: document.getElementById('edit-product-recipe-km').value.trim() || null,
        scale: document.getElementById('edit-product-scale').value.trim() || null,
        scale_km: document.getElementById('edit-product-scale-km').value.trim() || null
    };
    await saveData('edit_product', data, 'products', populateProducts, 'Product updated successfully!');
}

        async function deleteItem(action, id, fetchType, populateFn, successMsg) {
            showModal('delete-confirm', `Are you sure you want to delete this ${fetchType.slice(0, -1)}?`, async () => {
                try {
                    const response = await fetch(`api.php?action=${action}&id=${id}`, {
                        method: 'GET',
                        credentials: 'include',
                        headers: { 'X-CSRF-Token': csrfToken }
                    });
                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Unknown error');
                    await fetchData(fetchType, populateFn);
                    fetchDashboardStats();
                    showModal('delete-success', successMsg);
                    setTimeout(() => hideModal('delete-success'), 2000);
                } catch (error) {
                    alert(`Error deleting: ${error.message}`);
                }
            });
        }

        async function deleteCategory(id) {
            await deleteItem('delete_category', id, 'categories', populateCategories, 'Category deleted successfully!');
        }

        async function deleteProduct(id) {
            await deleteItem('delete_product', id, 'products', populateProducts, 'Product deleted successfully!');
        }

        async function confirmUser(id) {
            showModal('delete-confirm', 'Are you sure you want to confirm this user?', async () => {
                try {
                    const response = await fetch(`api.php?action=confirm_user&id=${id}`, {
                        method: 'GET',
                        credentials: 'include',
                        headers: { 'X-CSRF-Token': csrfToken }
                    });
                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Unknown error');
                    await fetchData('users', populateUsers);
                    fetchDashboardStats();
                    showModal('delete-success', 'User confirmed successfully!');
                    setTimeout(() => hideModal('delete-success'), 2000);
                } catch (error) {
                    alert('Error confirming user: ' + error.message);
                }
            });
        }

        function toggleSelectAll(type) {
            const selectAll = document.getElementById(`select-all-${type}`);
            const checkboxes = document.querySelectorAll(`.select-item[data-type="${type}"]`);
            checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
            updateBulkButton(type);
        }

        function updateBulkButton(type) {
            const checkedItems = document.querySelectorAll(`.select-item[data-type="${type}"]:checked`);
            const bulkButton = document.getElementById(`bulk-delete-${type}`);
            bulkButton.style.display = checkedItems.length > 0 ? 'inline-block' : 'none';
        }

        async function bulkDelete(type) {
            const checkedItems = document.querySelectorAll(`.select-item[data-type="${type}"]:checked`);
            if (checkedItems.length === 0) return;
            showModal('delete-confirm', `Are you sure you want to delete ${checkedItems.length} selected ${type}?`, async () => {
                const ids = Array.from(checkedItems).map(item => parseInt(item.dataset.id));
                try {
                    const response = await fetch(`api.php?action=bulk_delete_${type}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ ids }),
                        credentials: 'include'
                    });

                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Unknown error');

                    await fetchData(type, window[`populate${type.charAt(0).toUpperCase() + type.slice(1)}`]);
                    fetchDashboardStats();
                    updateBulkButton(type);
                    document.getElementById(`select-all-${type}`).checked = false;
                    showModal('delete-success', `Selected ${type} deleted successfully!`);
                    setTimeout(() => hideModal('delete-success'), 2000);
                } catch (error) {
                    alert(`Error deleting ${type}: ${error.message}`);
                }
            });
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.nextElementSibling.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>