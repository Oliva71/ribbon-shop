<?php
require_once __DIR__ . '/config/session_connect.php';
require_once __DIR__ . '/config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Пожалуйста, войдите в систему';
    header('Location: login.php');
    exit;
}
?>