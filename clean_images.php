<?php
require 'config/db_connect.php'; 

// очистка THUMBS 
$dirThumbs = 'uploads/thumbs/';

$filesInFolderThumbs = scandir($dirThumbs);
$filesInFolderThumbs = array_diff($filesInFolderThumbs, ['.', '..']);

$stmt = $pdo->query("SELECT thumb_path FROM product_images");
$imagesInDbThumbs = $stmt->fetchAll(PDO::FETCH_COLUMN);

$filesInDbThumbs = array_map(function($path) {
    return basename($path);
}, $imagesInDbThumbs);

$orphanedThumbs = array_diff($filesInFolderThumbs, $filesInDbThumbs);

echo "<h3>Миниатюры (thumbs)</h3>";
echo "<p>Найдено сиротских файлов: " . count($orphanedThumbs) . "</p>";

foreach ($orphanedThumbs as $file) {
    $fullPath = $dirThumbs . $file;
    if (file_exists($fullPath)) {
        unlink($fullPath);
        echo "Удален (thumb): $file<br>";
    }
}


// очистка ORIGINAL
$dirOriginals = 'uploads/original/'; 

// Проверка: существует ли папка, чтобы скрипт не упал с ошибкой
if (is_dir($dirOriginals)) {
    $filesInFolderOrig = scandir($dirOriginals);
    $filesInFolderOrig = array_diff($filesInFolderOrig, ['.', '..']);

    $stmtOrig = $pdo->query("SELECT image_path FROM product_images");
    $imagesInDbOrig = $stmtOrig->fetchAll(PDO::FETCH_COLUMN);

    $filesInDbOrig = array_map(function($path) {
        return basename($path);
    }, $imagesInDbOrig);

    $orphanedOrigins = array_diff($filesInFolderOrig, $filesInDbOrig);

    echo "<h3>Оригиналы (originals)</h3>";
    echo "<p>Найдено сиротских файлов: " . count($orphanedOrigins) . "</p>";

    foreach ($orphanedOrigins as $file) {
    $fullPath = $dirOriginals . $file;
    
    // Проверяем: это файл и существует ли он
    if (is_file($fullPath)) {
        unlink($fullPath);
        echo "Удален (original): $file<br>";
    }
    }
}

// Финальная проверка
// Проверяем обе группы файлов
// Если и там, и там пусто — значит всё чисто
if (empty($orphanedThumbs) && empty($orphanedOrigins)) {
    echo "<h2 style='color: green;'>Лишних файлов не найдено. Все чисто!</h2>";
} else {
    echo "<h2 style='color: orange;'>Очистка завершена. Проверь список выше.</h2>";
}
?>
