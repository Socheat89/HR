<?php
// FILE: nav_logic.php

// ចាប់ផ្តើម session ប្រសិនបើវាមិនទាន់បានចាប់ផ្តើម
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ភ្ជាប់ទៅកាន់ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// --- LOGIC សម្រាប់រាប់ចំនួនសំណើរ PENDING ---
try {
    // ត្រៀម query ដើម្បីរាប់จำนวนសំណើរที่มีสถานะ 'pending'
    $stmt_nav = $pdo->prepare("SELECT COUNT(*) FROM stock_request WHERE status = 'pending'");
    $stmt_nav->execute();
    
    // យកលទ្ធផលជាจำนวน ហើយเก็บក្នុង متغیر
    $pending_request_count_nav = $stmt_nav->fetchColumn();

} catch (PDOException $e) {
    // ប្រសិនបើមានបញ្ហាក្នុងការเชื่อมต่อหรือ query, កំណត់จำนวนเป็น 0
    $pending_request_count_nav = 0;
    // អ្នកអាច log error នៅទីនេះបើចាំបាច់
    // error_log("Could not count pending requests: " . $e->getMessage());
}


// --- បន្ថែម LOGIC ថ្មីសម្រាប់រាប់ LOW STOCK ---
try {
    // កំណត់ចំនួនដែលចាត់ទុកថា Low Stock
    $low_stock_threshold = 10;

    // ត្រៀម Query ដើម្បីរាប់ចំនួនទំនិញដែលមានបរិមាណតិចជាង ឬស្មើនឹង Threshold
    $stmt_low_stock_count = $pdo->prepare("SELECT COUNT(id) FROM stock_items WHERE quantity <= :threshold");
    $stmt_low_stock_count->bindParam(':threshold', $low_stock_threshold, PDO::PARAM_INT);
    $stmt_low_stock_count->execute();

    // រក្សាទុកលទ្ធផលក្នុងអថេរ (Variable)
    $low_stock_count = $stmt_low_stock_count->fetchColumn();

} catch (PDOException $e) {
    // ប្រសិនបើមានបញ្ហា Error ជាមួយ Database ให้ค่าเป็น 0
    $low_stock_count = 0;
    // អ្នកអាចកត់ត្រា Error ទុកក៏បាន
    // error_log("Error counting low stock items: " . $e->getMessage());
}

?>