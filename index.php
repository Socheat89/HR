<?php
// Auto redirect to /homes if root domain
if ($_SERVER['REQUEST_URI'] == '/') {
    header('Location: /homes');
    exit;
}
?>