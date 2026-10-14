<?php
require_once 'config/session_connect.php';
require_once 'uploads-original-thumbs-config.php';
require_once 'config/db_connect.php';    
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sentToken)) {
        die('Ошибка: CSRF-токен не совпадает.');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        die('Неверный ID товара.');
    }

    //  Получаем пути к картинкам, чтобы удалить файлы
    $stmt = $pdo->prepare("SELECT image_path, thumb_path FROM product_images WHERE product_id = ?");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($images as $img) {
        $originalFile = DIR_ORIGINAL_ABS . DIRECTORY_SEPARATOR . basename($img['image_path']);
        $thumbFile = DIR_THUMBS_ABS . DIRECTORY_SEPARATOR . basename($img['thumb_path']);
        if (file_exists($originalFile)) unlink($originalFile);
        if (file_exists($thumbFile)) unlink($thumbFile);
    }

    //  Удаляем записи картинок из БД
    $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
    $stmt->execute([$id]);

    //  Удаляем сам товар
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    // Флеш-сообщение и возврат к списку
    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Товар удалён.'];
    header('Location: admin-list.php');
    exit;
} else {
    header('Location: admin-list.php');
    exit;
}
