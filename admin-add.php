<?php
require_once 'config/session_connect.php';
require_once 'uploads-original-thumbs-config.php';
require_once 'config/db_connect.php';    
require_once 'auth.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'] ?? '';

$flashMessage = null;
if (!empty($_SESSION['flash_message'])) {
    $msg = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

// Если это массив, берем текст. Если строка - оставляем строку.
    if (is_array($msg)) {
        $flashMessage = $msg['text'] ?? $msg;
    } else {
        $flashMessage = $msg;
    }
}


//  Получаем категории
$stmtCat = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

//  Получаем материалы
$stmtMat = $pdo->query("SELECT id, name FROM materials ORDER BY name");
$materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

// Получаем дизайны 
$stmtDesigns = $pdo->query("SELECT id, name FROM designs ORDER BY name");
$designs = $stmtDesigns->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'] ?? '';

$errors = [];
$success = false;

    // Проверка токена (при POST)
    //  Когда отправляем форму — проверяем, совпадает ли токен
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Проверка CSRF
    $sentToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $sentToken)) {
        die('Ошибка: CSRF-токен не совпадает.');
    }

    //  Получение и очистка данных
    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $material_id = (int)($_POST['material_id'] ?? 0);
    $design_id = (int)($_POST['design_id'] ?? 0);
    $width_mm = $_POST['width_mm'] !== '' ? (int)($_POST['width_mm']) : null;
    $color_ribbon = trim($_POST['color_ribbon'] ?? '');
    $color_print = trim($_POST['color_print'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $product_type = trim($_POST['product_type'] ?? '');
    $stock_qty = $_POST['stock_qty'] !== '' ? (int)($_POST['stock_qty']) : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Валидация
     if (empty($name)) {
        $errors[] = 'Название товара обязательно.';
    }
    
    if ($price < 0) {
        $errors[] = 'Цена не может быть отрицательной.';
    }
    
    // Проверка категории
    if ($category_id <= 0) {
        $errors[] = 'Пожалуйста, выберите категорию товара.';
    }
    
    // Проверка материала
    if ($material_id <= 0) {
        $errors[] = 'Пожалуйста, выберите материал товара.';
    }

    // Проверка дизайна
    if ($design_id <= 0) {
        $errors[] = 'Пожалуйста, выберите дизайн товара.';
    }

    if (!in_array($product_type, ['stock', 'custom'])) {
        $errors[] = 'Выберите тип товара: готовый или на заказ.';
    }

    // СОХРАНЕНИЕ В БД  если валидация прошла
    if (empty($errors)) {
        try {
            // Вставляем товар
            $stmt = $pdo->prepare("
                INSERT INTO products 
                    (name, price, category_id, material_id, design_id, product_type, width_mm, color_ribbon, 
                     color_print, description, sku, stock_qty, is_active, created_at) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $name, $price, $category_id, $material_id, $design_id, $product_type,
                $width_mm, $color_ribbon, $color_print, $description, 
                $sku, $stock_qty, $is_active
            ]);
            
            $productId = $pdo->lastInsertId();

            // Обработка нескольких изображений
            if (!empty($_FILES['images']['tmp_name'])) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $count = count($_FILES['images']['name']);

                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

                    $file = [
                        'name'     => $_FILES['images']['name'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error'    => $_FILES['images']['error'][$i],
                        'size'     => $_FILES['images']['size'][$i],
                    ];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Ошибка загрузки файла: ' . $file['name'];
                        break;
                    }

                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) {
                        $errors[] = 'Неверный формат файла: ' . $file['name'];
                        break;
                    }

                    // Создаём папки, если нет
                    if (!is_dir(DIR_ORIGINAL_ABS)) mkdir(DIR_ORIGINAL_ABS, 0755, true);
                    if (!is_dir(DIR_THUMBS_ABS)) mkdir(DIR_THUMBS_ABS, 0755, true);

                    $uniqueName = $productId . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $relativePath = 'uploads/original/' . $uniqueName;
                    $absolutePath = DIR_ORIGINAL_ABS . DIRECTORY_SEPARATOR . $uniqueName;

                    if (move_uploaded_file($file['tmp_name'], $absolutePath)) {
                        list($width, $height) = getimagesize($absolutePath);
                        if ($width && $height) {
                            $ratio = $width / $height;
                            $newWidth = 400;
                            $newHeight = $newWidth / $ratio;
                            $newImage = imagecreatetruecolor($newWidth, $newHeight);

                            if ($ext === 'png' || $ext === 'webp') {
                                imagealphablending($newImage, false);
                                imagesavealpha($newImage, true);
                                $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                                imagefill($newImage, 0, 0, $transparent);
                            }

                            $source = ($ext === 'png') ? imagecreatefrompng($absolutePath) :
                                    (($ext === 'webp') ? imagecreatefromwebp($absolutePath) :
                                    imagecreatefromjpeg($absolutePath));

                            if ($source) {
                                imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                                
                                $thumbName = 'thumb_' . $uniqueName;
                                $thumbRelative = 'uploads/thumbs/' . $thumbName;
                                $thumbAbsolute = DIR_THUMBS_ABS . DIRECTORY_SEPARATOR . $thumbName;

                                if ($ext === 'png') imagepng($newImage, $thumbAbsolute, 9);
                                elseif ($ext === 'webp') imagewebp($newImage, $thumbAbsolute, 85);
                                else imagejpeg($newImage, $thumbAbsolute, 85);

                                imagedestroy($source);
                                imagedestroy($newImage);

                                $stmtImg = $pdo->prepare("
                                    INSERT INTO product_images 
                                        (product_id, image_path, thumb_path, sort_order, is_main) 
                                    VALUES 
                                        (?, ?, ?, ?, ?)
                                ");
                                $isMain = ($i === 0) ? 1 : 0;
                                $stmtImg->execute([
                                    $productId,
                                    $relativePath,
                                    $thumbRelative,
                                    $i + 1,
                                    $isMain
                                ]);
                            }
                        }
                    } else {
                        $error = 'Не удалось сохранить файл: ' . $file['name'];
                        break;
                    }
                }
            }

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'text' => 'Товар успешно добавлен!'
            ];
            header('Location: admin-add.php');
            exit;

        } catch (Exception $e) {
            $errors[] = 'Ошибка базы данных.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить товар</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap" rel="stylesheet">
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/reset.css">
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>

    <header class="header">

        <div class="header__nav">
         <div class="container">
          <div class="header__wrapper">
          <nav class="menu">
            <div class="menu__btn" id="menuBtn">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <ul class="menu__list" id="menuList">
              <li class="menu__item-home">
                <a class="menu__link" href="index.php">
                  <i class="bi bi-house-door-fill"></i>
                  <span>Главная</span>
                </a>
              </li>
              <li class="menu__item-favorites">
                <a class="menu__link" href="catalog.php">
                  <!-- <i class="bi bi-pencil-fill"></i> -->
                  <span>Каталог</span>
                </a>
              </li>
              <li class="menu__item-cart">
                <a class="menu__link" href="admin-list.php">
                  <i class="bi bi-tag me-2"></i>
                  <span>Список товаров</span>
                </a>
              </li>
              <li class="menu__item-login">
                <a class="menu__link" href="logout.php">
                  <i class="bi bi-door-open-fill"></i>
                  <span>Выход</span>
                </a>
              </li>
            </ul>
          </nav>
          </div>        
        </div>
    </header>
    <div class="section-box_top">
        <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">

                     <!--  показываем сообщение об успехе если оно есть в сессии -->
                       <?php if ($flashMessage): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($flashMessage) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        </div>
                        <?php endif; ?>


                         <!-- Вывод ошибок если массив не пустой -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <p><?= htmlspecialchars($error) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <div class="card-header" style="background: linear-gradient(30deg, #e5c982 0%, #f0e2a3, #e7bf75, #e4c778 100%); color: #504b64; border: none;">
                        <h4 class="mb-0">Добавить товар (админка)</h4>
                    </div>
                    <div class="card-body"> 
                         
                       <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                            <!-- Название -->
                            <div class="mb-3">
                                <label for="name" class="form-label">Название</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="Например: Лента атласная белая" required 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            </div>

                            <!-- Цена + Тип -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">Цена (руб.)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₽</span>
                                        <input type="number" step="0.01" class="form-control" id="price" name="price" 
                                               placeholder="150.00" required 
                                               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                                    </div>
                                </div>

                                 <div class="col-md-6 mb-3">
                                    <label for="product_type" class="form-label">Тип товара</label>
                                    <select name="product_type" id="product_type" class="form-select" required>
                                        <option value="" disabled selected>Выберите тип</option>
                                        <option value="stock" <?= ($_POST['product_type'] ?? '') === 'stock' ? 'selected' : '' ?>>Готовый (в наличии)</option>
                                        <option value="custom" <?= ($_POST['product_type'] ?? '') === 'custom' ? 'selected' : '' ?>>На заказ</option>
                                    </select>
                                </div>        
                            </div>

                           <!-- Материал + Категория -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="material_id" class="form-label">Материал</label>
                                    <select name="material_id" id="material_id" class="form-select" required>
                                        <option value="" disabled selected>Выберите материал</option>
                                        <?php foreach ($materials as $mat): ?>
                                            <option value="<?= $mat['id'] ?>" <?= ($_POST['material_id'] ?? '') == $mat['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($mat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Категория</label>
                                    <select name="category_id" id="category_id" class="form-select" required>
                                        <option value="" disabled selected>Выберите категорию</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>                                
                                </div>
                               
                            </div>

                            <!--Дизайн + Ширина -->
                            <div class="row">

                                <div class="col-md-6 mb-3">
                                    <label for="design_id" class="form-label">Дизайн</label>
                                    <select name="design_id" id="design_id" class="form-select">
                                        <option value="">Не выбран</option>
                                        <?php foreach ($designs as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= ($_POST['design_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="width_mm" class="form-label">Ширина ленты (мм)</label>
                                    <input type="number" class="form-control" id="width_mm" name="width_mm" 
                                        placeholder="Например: 25" 
                                        value="<?= htmlspecialchars($_POST['width_mm'] ?? '') ?>">
                                </div>                               
                            </div>

                            <!-- Цвет ленты + Цвет печати -->
                             <div class="row">
                              <div class="col-md-6 mb-3">
                                    <label for="color_ribbon" class="form-label">Цвет ленты</label>
                                    <input type="text" class="form-control" id="color_ribbon" name="color_ribbon" 
                                        placeholder="Например: белый" 
                                        value="<?= htmlspecialchars($_POST['color_ribbon'] ?? '') ?>">
                                </div>

                            <div class="col-md-6 mb-3">
                                <label for="color_print" class="form-label">Цвет печати</label>
                                <input type="text" class="form-control" id="color_print" name="color_print" 
                                    placeholder="Например: золотой" 
                                    value="<?= htmlspecialchars($_POST['color_print'] ?? '') ?>">
                            </div>
                            </div>

                            <!-- SKU + Остаток -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="sku" class="form-label">Артикул (SKU)</label>
                                    <input type="text" class="form-control" id="sku" name="sku" 
                                           placeholder="Например: L-25-white-gold" 
                                           value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>">
                                    <small class="text-muted">Оставьте пустым, если не нужен</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="stock_qty" class="form-label">Остаток на складе</label>
                                    <input type="number" class="form-control" id="stock_qty" name="stock_qty" 
                                           placeholder="0" min="0" 
                                           value="<?= htmlspecialchars($_POST['stock_qty'] ?? '0') ?>">
                                </div>
                            </div>

                            <!-- Описание -->
                            <div class="mb-3">
                                <label for="description" class="form-label">Описание</label>
                                <textarea name="description" id="description" class="form-control" rows="3" 
                                          placeholder="Мягкая атласная лента, ширина 25 мм..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>

                            <!-- Фото -->
                            <div class="mb-3">
                                <label for="images" class="form-label">Фото (можно несколько)</label>
                                <input class="form-control" type="file" id="images" name="images[]" accept="image/*" multiple required>
                                <small class="text-muted">Выберите сразу несколько файлов (зажмите Ctrl/Cmd или выделите мышкой)</small>
                            </div>

                            <!-- Опубликовать -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                           <?= !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        Опубликовать товар (показать на сайте)
                                    </label>
                                </div>
                            </div>

                            <!-- Кнопка -->
                            <div class="form-actions" style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                                <button type="submit" class="btn btn__gradieht login-btn__gradieht-register">Добавить товар</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        </div>                    
    </div>

    <footer class="footer">
      <div class="container">        
        <div class="footer__inner">       
          <ul class="footer__nav">
            <li class="footer__nav-item">
              <a href="#" class="footer__nav-link">О нас</a>
            </li>
            <li class="footer__nav-item">
              <a href="#" class="footer__nav-link">Контакты</a>
            </li>
            <li class="footer__nav-item">
              <a href="#" class="footer__nav-link">Отзывы</a>
            </li>
          </ul>
          <ul class="footer__nav">
            <li class="footer__nav-item">
              <a href="#" class="footer__nav-link">Заказать звонок</a>
            </li>
            <li class="footer__nav-item">
              <a href="#" class="footer__nav-link">Рассчитать стоимость</a>
            </li>
            <li class="footer__nav-item">
              <a href="#" class="footer__nav-link">Каталог продукции</a>
            </li>
            <li class="footer__nav-item">
              <a href="#" class="footer__nav-link"></a>
            </li>
          </ul>
          <ul class="footer__contacts">
            <li class="footer__contacts-item">
              <a href="tel:+78632500000" class="footer__contacts-link">
                <i class="bi bi-telephone-fill"></i>
                +7 (863) 250 00 00
              </a>
            </li>
            <li class="footer__contacts-item">
              <a href="tel:+78632500000" class="footer__contacts-link">
                <i class="bi bi-whatsapp"></i>
                +7 (863) 250 00 00
              </a>
            </li>
            <li class="footer__contacts-item">
              <a href="tel:+78632500000" class="footer__contacts-link">
                <i class="bi bi-telegram"></i>
                +7 (863) 250 00 00
              </a>
            </li>
          </ul>
          <ul class="footer__contacts">
            <li class="footer__contacts-item">
              <a href="#" class="footer__contacts-link">
                <i class="bi bi-geo-alt-fill"></i>
                <span>Rostov-on-Don</span>
              </a>
            </li>
            <li class="footer__contacts-item">
              <a href="#" class="footer__contacts-link">
                <i class="bi bi-envelope-fill"></i>
                Наша почта
              </a>
            </li>
            <li class="footer__contacts-time">
              <i class="bi bi-alarm-fill"></i>
              пн - пт: 9:00 - 17:00
            </li>
          </ul>
        </div>
        <div class="footer__bottom">
          <img src="images/logotip.png" class="logo" alt="RIBBON PRINTING">
          <div class="footer__politica">
            <p class="footer__copy">&#169;2010-2025 RIBBON PRINTING</p>
            <p class="footer__politika-kpd">
              <a href="">Политика конфиденциальности и обработки персональных данных</a>
            </p>
          </div>
        </div>
      </div>
    </footer>
    
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
</body>
</html>

