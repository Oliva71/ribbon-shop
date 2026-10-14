<?php
require_once __DIR__ . '/config/session_connect.php';
require_once __DIR__ . '/config/db_connect.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
// Если не админ — редирект на главную 
if (!$isAdmin) {
    header('Location: index.php'); 
    exit;
}
?>