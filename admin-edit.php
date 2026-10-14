<?php  
require_once 'config/session_connect.php';
require_once 'uploads-original-thumbs-config.php';
require_once 'config/db_connect.php';    
require_once 'auth.php';

// Сохраняем сообщение, но не выводим — выведем внутри HTML
$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Получаем дизайны для выпадающего списка
$stmtDesigns = $pdo->query("SELECT id, name FROM designs ORDER BY name ASC");
$designs = $stmtDesigns->fetchAll(PDO::FETCH_ASSOC);

// Получаем категории
$stmtCats = $pdo->query("SELECT id FROM categories ORDER BY name");
$catIds = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

// Получаем ID товара
$product_id = $_GET['id'] ?? 0;
if (!$product_id) {
    header('Location: admin-list.php');
    exit;
}

// сохранение 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sentToken)) {
        die('Ошибка: CSRF-токен не совпадает.');
    }

    //  обновляем товар 
    $sql = "
        UPDATE products SET
            name = :name,
            price = :price,
            width_mm = :width_mm,
            color_ribbon = :color_ribbon,
            color_print = :color_print,
            sku = :sku,
            description = :description,
            product_type = :product_type,
            min_order_qty = :min_order_qty,
            lead_time_days = :lead_time_days,
            stock_qty = :stock_qty,
            is_active = :is_active,
            sort_order = :sort_order,
            design_id = :design_id
        WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $_POST['name'],
        ':price' => $_POST['price'],
        ':width_mm' => $_POST['width_mm'],
        ':color_ribbon' => $_POST['color_ribbon'],
        ':color_print' => $_POST['color_print'],
        ':sku' => $_POST['sku'],
        ':description' => $_POST['description'],
        ':product_type' => $_POST['product_type'],
        ':min_order_qty' => $_POST['min_order_qty'],
        ':lead_time_days' => $_POST['lead_time_days'],
        ':stock_qty' => $_POST['stock_qty'],
        ':is_active' => isset($_POST['is_active']) ? 1 : 0,
        ':sort_order' => $_POST['sort_order'],
        ':design_id' => !empty($_POST['design_id']) ? (int)$_POST['design_id'] : null,
        ':id' => $product_id
    ]);

    // логика главного фото 
    // Сначала сбрасываем все фото этого товара
    $reset = $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ?");
    $reset->execute([$product_id]);


    // Загрузка новых фото 
if (!empty($_FILES['new_images']['name'][0])) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $uploadDir = DIR_ORIGINAL_ABS . DIRECTORY_SEPARATOR;
    $thumbDir = DIR_THUMBS_ABS . DIRECTORY_SEPARATOR;

    foreach ($_FILES['new_images']['tmp_name'] as $i => $tmpName) {
        if ($_FILES['new_images']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $ext = strtolower(pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            continue;
        }

        // генерация уникального имени
        $filename = uniqid('img_', true) . '.' . $ext;
        $originalPath = $uploadDir . $filename;
        $thumbPath = $thumbDir . $filename;

        // сохраняем оригинал
        move_uploaded_file($tmpName, $originalPath);

        // создаём миниатюру
        $src = imagecreatefromjpeg($originalPath);
        if ($ext === 'png') $src = imagecreatefrompng($originalPath);
        if ($ext === 'webp') $src = imagecreatefromwebp($originalPath);
        if ($ext === 'gif') $src = imagecreatefromgif($originalPath);

        if ($src) {
            $origW = imagesx($src);
            $origH = imagesy($src);
            $thumbW = 200;
            $thumbH = (int)($origH * $thumbW / $origW);

            $thumb = imagecreatetruecolor($thumbW, $thumbH);
            imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);

            imagejpeg($thumb, $thumbPath, 85);
            imagedestroy($src);
            imagedestroy($thumb);
        }

        // Запись в БД
        $ins = $pdo->prepare("
            INSERT INTO product_images (product_id, image_path, thumb_path, is_main) 
            VALUES (?, ?, ?, 0)
        ");
        $ins->execute([
            $product_id,
            'uploads/originals/' . $filename,
            'uploads/thumbs/' . $filename
        ]);
    }
}


    // Если выбрано главное фото — ставим ему 1
    if (!empty($_POST['is_main'])) {
        $setMain = $pdo->prepare("UPDATE product_images SET is_main = 1 WHERE id = ?");
        $setMain->execute([$_POST['is_main']]);
    }

    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Товар обновлён.'];
    header('Location: admin-edit.php?id=' . $product_id);
    exit;

}

// Получаем данные товара
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die('Товар не найден');
}

// Получаем все фото товара
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY id");
$imgStmt->execute([$product_id]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    
    <title>Админка: Редактирование</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap" rel="stylesheet">
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/reset.css">
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1400px; }
        .badge-stock-zero { color: white; background-color: #dc3545; }
    </style>
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
                <a class="menu__link" href="admin-add.php">
                  <i class="bi bi-pencil-fill"></i>
                  <span>Редактировать товары</span>
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

    <h2>Редактировать товар</h2>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashMessage['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <!-- Основная информация -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">Название</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Цена (₽)</label>
                <input type="number" step="0.01" name="price" class="form-control" value="<?= $product['price'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($product['sku']) ?>">
            </div>
        </div>

        <!-- Размеры и цвета -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Ширина ленты (мм)</label>
                <input type="number" name="width_mm" class="form-control" value="<?= $product['width_mm'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Цвет ленты</label>
                <input type="text" name="color_ribbon" class="form-control" value="<?= htmlspecialchars($product['color_ribbon']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Цвет печати</label>
                <input type="text" name="color_print" class="form-control" value="<?= htmlspecialchars($product['color_print']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Тип товара</label>
                <select name="product_type" class="form-select">
                    <option value="stock" <?= $product['product_type'] === 'stock' ? 'selected' : '' ?>>В наличии</option>
                    <option value="custom" <?= $product['product_type'] === 'custom' ? 'selected' : '' ?>>Под заказ</option>
                </select>
            </div>

             <!-- Вариант нанесения -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Вариант нанесения (дизайн)</label>
        <select name="design_id" class="form-select">
            <option value="" <?= empty($product['design_id']) ? 'selected' : '' ?>>Не выбран</option>
            <?php if (!empty($designs)): ?>
                <?php foreach ($designs as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= ($product['design_id'] ?? '') == (int)$d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name'] ?? 'Без названия') ?>
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="" disabled>Нет доступных дизайнов</option>
            <?php endif; ?>
        </select>
    </div>
</div>
</div>

        <!-- Дополнительные поля -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Минимальный заказ (шт)</label>
                <input type="number" name="min_order_qty" class="form-control" value="<?= $product['min_order_qty'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Срок изготовления (дней)</label>
                <input type="number" name="lead_time_days" class="form-control" value="<?= $product['lead_time_days'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Остаток (шт)</label>
                <input type="number" name="stock_qty" class="form-control" value="<?= $product['stock_qty'] ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Описание</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">Порядок сортировки</label>
                <input type="number" name="sort_order" class="form-control" value="<?= $product['sort_order'] ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active_check" <?= $product['is_active'] ? 'checked' : '' ?>>
                    <label for="is_active_check" class="form-check-label">Активен (отображать в каталоге)</label>
                </div>
            </div>
        </div>

        <!-- Блок с фото и выбором главного -->
        <div class="card mb-4">
            <div class="card-header">
                Изображения товара
            </div>

            <div class="card-body">
    <?php if (empty($images)): ?>
        <p class="text-muted">Фото пока нет. Загрузите новые ниже.</p>
    <?php else: ?>
        <?php foreach ($images as $img): ?>
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-2 image-row">
                
                <!-- Превью картинки  -->
                <div style="flex-shrink: 0;">
                    <img src="<?= htmlspecialchars($img['thumb_path']) ?>" 
                         class="img-thumbnail" 
                         style="width: 100px; height: 100px; object-fit: cover;" 
                         alt="Фото товара">
                </div>

                <!-- Радио-кнопка -->
                <div class="d-flex align-items-center">
                    <input type="radio" 
                           name="is_main" 
                           value="<?= $img['id'] ?>" 
                           id="main_<?= $img['id'] ?>" 
                           <?= ($img['is_main'] == 1) ? 'checked' : '' ?>>
                    <label for="main_<?= $img['id'] ?>" class="mb-0 ms-2 text-muted small">
                        Главное
                    </label>
                </div>

                <!-- Кнопки действий -->
                <div class="action-buttons ms-auto d-flex gap-2" style="flex-shrink: 0;">

                    <!-- Кнопка "Удалить" -->
                    <button type="button" 
                            class="btn btn-sm btn-outline-danger delete-btn" 
                            data-id="<?= $img['id'] ?>">
                        Удалить
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Добавить новые фото</label>
            <input type="file" name="new_images[]" class="form-control" id="fileInput" multiple accept="image/*">
            <small class="text-muted">Можно выбрать несколько файлов сразу. Превью появятся сразу, но сохранятся только после нажатия "Сохранить изменения".</small>
          </div>

          <!-- Зона мгновенных превью (только в браузере) -->
        <div id="previewContainer" class="mb-4"></div>
          
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <button type="submit" class="btn btn__gradieht login-btn__gradieht-register">Сохранить изменения</button>
        <a href="admin-list.php" class="btn btn__gradieht login-btn__gradieht-register">Отмена</a>
    </form>
</div>
</div>
<footer class="footer">
      <div class="container">        
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

      <!-- скрипт удаления -->

<script>
(function() {
    const buttons = document.querySelectorAll('.delete-btn');
    const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'; 

    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
        
            if (!confirm('Вы уверены, что хотите удалить это фото?  Это действие нельзя отменить.')) {
              return;
            }

            const imageId = this.getAttribute('data-id');
            
            // Динамически создаем форму для отправки POST-запроса
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin-delete-image.php';
            
            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'id';
            inputId.value = imageId;
            
            const inputToken = document.createElement('input');
            inputToken.type = 'hidden';
            inputToken.name = 'csrf_token';
            inputToken.value = csrfToken;

            form.appendChild(inputId);
            form.appendChild(inputToken);
            document.body.appendChild(form);
            
            form.submit();
        });
    });
})();
</script>

 <!-- показываем превью выбранных файлов -->
<script>
(function() {
    const fileInput = document.getElementById('fileInput');
    const previewContainer = document.getElementById('previewContainer');

    if (!fileInput || !previewContainer) return;

    fileInput.addEventListener('change', function() {
        previewContainer.innerHTML = ''; // очищаем старые превью
        const files = this.files;

        if (files.length === 0) return;

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (!file.type.startsWith('image/')) continue; // пропускаем не картинки

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-thumbnail me-2 mb-2';
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                img.alt = file.name;

                const label = document.createElement('span');
                label.textContent = file.name;
                label.className = 'small text-muted d-block';

                const wrapper = document.createElement('div');
                wrapper.className = 'd-inline-block';
                wrapper.appendChild(img);
                wrapper.appendChild(label);

                previewContainer.appendChild(wrapper);
            };
            reader.readAsDataURL(file);
        }
    });
})();
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
</body>
</html>
