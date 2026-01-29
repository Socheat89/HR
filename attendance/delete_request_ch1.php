<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['username'])) {
    header("Location: https://app.vvc.asia/login.php");
    exit();
}

$request_id = $_GET['id'];
$selected_date = $_GET['date'];

$stmt = $pdo->prepare("DELETE FROM staff_requests_ch1 WHERE request_id = ?");
$stmt->execute([$request_id]);

header("Location: attendance_CH1.php?date=$selected_date");
exit();