<?php
require_once 'config/db_connect.php';
$projectSubdir = 'site-OliVa';

define('DIR_ORIGINAL_REL', '/uploads/original');
define('DIR_THUMBS_REL', '/uploads/thumbs');

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/'); // Убираем лишние слеши в конце

// Абсолютный путь 
define('DIR_ORIGINAL_ABS', $docRoot . '/' . $projectSubdir . DIR_ORIGINAL_REL);
define('DIR_THUMBS_ABS', $docRoot . '/' . $projectSubdir . DIR_THUMBS_REL);

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
?>