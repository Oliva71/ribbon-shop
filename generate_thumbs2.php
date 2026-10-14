<?php
require_once __DIR__ . '/uploads-original-thumbs-config.php';
require_once 'config/db_connect.php';

$thumbsDir  = DIR_THUMBS_ABS;
$maxWidth   = 400;
$quality    = 85;

if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
}

$stmt = $pdo->query("SELECT id, image_path FROM products WHERE image_path IS NOT NULL AND image_path != ''");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$successCount = 0;
$skipCount = 0;

echo "<h2>Генерация превью из базы данных</h2>";
echo "<div style='display: flex; flex-wrap: wrap; gap: 15px;'>";

foreach ($products as $product) {
    $productId = $product['id'];
    $relativePath = $product['image_path'];

    // 1. Превращаем относительный путь в абсолютный
    $sourcePath = $_SERVER['DOCUMENT_ROOT'] . '/' .  $projectSubdir . '/' . $relativePath;

    if (!file_exists($sourcePath)) {
        echo "<p style='color:red;'>❌ Товар ID {$productId}: файл не найден — {$sourcePath}</p>";
        $skipCount++;
        continue;
    }

    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $skipCount++;
        continue;
    }

    // формируем уникальное имя: ID + оригинальное имя
    $originalName = basename($sourcePath);
    $thumbName = $productId . '_' . $originalName;
    $targetPath = $thumbsDir . '/' . $thumbName;

    // Получаем размеры оригинала
    $size = getimagesize($sourcePath);
    if (!$size) {
        $skipCount++;
        continue;
    }

    list($width, $height) = $size;
    $ratio = $width / $height;
    $newWidth = $maxWidth;
    $newHeight = $maxWidth / $ratio;

    // Создаём холст
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    if ($ext === 'png' || $ext === 'webp') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefill($newImage, 0, 0, $transparent);
    }

    // Загрузка оригинала
    if ($ext === 'png') {
        $image = imagecreatefrompng($sourcePath);
    } elseif ($ext === 'webp') {
        $image = imagecreatefromwebp($sourcePath);
    } else {
        $image = imagecreatefromjpeg($sourcePath);
    }

    if (!$image) {
        imagedestroy($newImage);
        $skipCount++;
        continue;
    }

    // Ресайз
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Сохранение
    $saveSuccess = false;
    if ($ext === 'png') {
        $saveSuccess = imagepng($newImage, $targetPath, 9);
    } elseif ($ext === 'webp') {
        $saveSuccess = imagewebp($newImage, $targetPath, $quality);
    } else {
        $saveSuccess = imagejpeg($newImage, $targetPath, $quality);
    }

    // запись пути в базу
    if ($saveSuccess) {
        $thumbRelPath = 'uploads/thumbs/' . $thumbName;

        $updateStmt = $pdo->prepare("UPDATE products SET thumb_path = ? WHERE id = ?");
        $updateStmt->execute([$thumbRelPath, $productId]);

        $successCount++;
        echo "<div style='border: 1px solid #ddd; padding: 5px; width: 200px; text-align: center;'>";
        echo "<img src='{$thumbRelPath}' alt='thumb' style='max-width: 100%; height: auto;'>";
        echo "<p style='margin: 5px 0; font-size: 12px;'>ID {$productId}: {$thumbName}</p>";
        echo "</div>";
    } else {
        echo "<p style='color:red;'>❌ Не удалось сохранить: {$thumbName}</p>";
        $skipCount++;
    }

    if ($image) imagedestroy($image);
    imagedestroy($newImage);
}

echo "</div>";
echo "<p><b>Готово!</b> Превью создано: {$successCount}, пропущено: {$skipCount}</p>";
?>
