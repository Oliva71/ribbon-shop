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

    $image_id = (int)($_POST['id'] ?? 0);
    if ($image_id <= 0) {
        header('Location: admin-list.php');
        exit;
    }

    //  Получаем информацию о картинке
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$img) {
        header('Location: admin-list.php');
        exit;
    }

    $product_id = $img['product_id'];

    //  Удаляем файлы с диска
    $originalFile = DIR_ORIGINAL_ABS . DIRECTORY_SEPARATOR . basename($img['image_path']);
    $thumbFile = DIR_THUMBS_ABS . DIRECTORY_SEPARATOR . basename($img['thumb_path']);

    if (file_exists($originalFile)) unlink($originalFile);
    if (file_exists($thumbFile)) unlink($thumbFile);

    //  Удаляем запись из БД
    $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
    $stmt->execute([$image_id]);

    //  Если удалили главное фото — назначаем главное на первое оставшееся
    if ($img['is_main']) {
        $stmt = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$product_id]);
        $nextMain = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($nextMain) {
            $stmt = $pdo->prepare("UPDATE product_images SET is_main = 1 WHERE id = ?");
            $stmt->execute([$nextMain['id']]);
        }
    }

    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Фото удалено.'];
    header('Location: admin-edit.php?id=' . $product_id);
    exit;
}
// Если кто-то зашёл через GET — просто возврат в список
header('Location: admin-list.php');
exit;