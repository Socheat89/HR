<?php
session_start();
if (!isset($_SESSION['board'])) {
    $_SESSION['board'] = array_fill(0, 9, '');
    $_SESSION['turn'] = 'X';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $index = $_POST['index'];
    if ($_SESSION['board'][$index] === '') {
        $_SESSION['board'][$index] = $_SESSION['turn'];
        $_SESSION['turn'] = ($_SESSION['turn'] === 'X') ? 'O' : 'X';
    }
}

if (isset($_POST['reset'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

echo json_encode(['board' => $_SESSION['board'], 'turn' => $_SESSION['turn']]);
