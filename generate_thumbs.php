<?php
require_once __DIR__ . '/uploads-original-thumbs-config.php';

$sourceDir = DIR_ORIGINAL_ABS; 
$thumbsDir = DIR_THUMBS_ABS;    
$maxWidth  = 400;
$quality   = 85;

if (!is_dir(DIR_ORIGINAL_ABS)) {
    die('Ошибка: Папка для оригиналов не найдена по пути: ' . DIR_ORIGINAL_ABS . '<br>Создайте папку uploads/original внутри проекта.');
}

if (!is_dir($thumbsDir)) {
    if (!mkdir($thumbsDir, 0755, true)) {
        die('Не удалось создать папку uploads/thumbs/. Надо проверить права на запись.');
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

try {

// Проходим по всем файлам в sourceDir и его подпапках, если нужно 

$successCount = 0; // Счётчик реально созданных превью

echo "<h2>Результаты генерации</h2>";
echo "<div style='display: flex; flex-wrap: wrap; gap: 15px;'>";

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) continue;

    $ext = strtolower($fileInfo->getExtension());
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

    $sourcePath = $fileInfo->getPathname();
    $targetPath = $thumbsDir . '/' . $fileInfo->getFilename();
    
    // Получаем размеры оригинала
    $size = getimagesize($sourcePath);
    if (!$size) {
        error_log("Не удалось получить размеры: $sourcePath");
        continue;
    }
    
    list($width, $height) = $size;
    
    $ratio = $width / $height;
    $newWidth = $maxWidth;
    $newHeight = $maxWidth / $ratio;

    // Создаём холст
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Настройка прозрачности для PNG/WebP
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

    // если не загрузилось — пропускаем
    if (!$image) {
        error_log("Не удалось загрузить изображение: $sourcePath");
        imagedestroy($newImage);
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

    // Вывод результата
    if ($saveSuccess) {
        $successCount++;
        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $targetPath);
        echo "<div style='border: 1px solid #ddd; padding: 5px; width: 200px; text-align: center;'>";
        echo "<img src='$relativePath' alt='thumb' style='max-width: 100%; height: auto;'>";
        echo "<p style='margin: 5px 0; font-size: 12px;'>" . basename($sourcePath) . "</p>";
        echo "</div>";
    } else {
        error_log("Не удалось сохранить: $targetPath");
    }

    // Очистка памяти
    if ($image) {
        imagedestroy($image);
    }
    imagedestroy($newImage);
}
} catch (Exception $e) {
    echo 'Произошла ошибка при сканировании файлов: ' . $e -> getMessage();
}
echo 'Готово! Превью сохранены в upload/thumbs  (всего обработано файлов: ' . iterator_count($iterator) . ')';

   ?>