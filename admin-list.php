<?php
require_once 'config/session_connect.php';
require_once 'uploads-original-thumbs-config.php';
require_once 'config/db_connect.php';    
require_once 'auth.php';

// Генерируем токен только при GET-запросе, если просто открываешь страницу
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Читаем флеш-сообщение и сразу очищаем, чтобы не показывалось при обновлении
$flashMessage = '';
$flashType = '';
if (!empty($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message']['text'];
    $flashType = $_SESSION['flash_message']['type'] ?? 'success';
    unset($_SESSION['flash_message']);
}

// SQL-запрос: берём только нужное, сразу с алиасами, чтобы в PHP было понятно
$stmt = $pdo->query("
    SELECT 
        p.id, p.name, p.price, p.width_mm, p.color_ribbon, 
        p.color_print, p.sku, p.stock_qty, p.is_active,
        c.name AS category_name,
        m.name AS material_name,
        d.name AS design_name,
        COALESCE(
            (SELECT pi.thumb_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1),
            (SELECT pi.thumb_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1)
        ) AS thumb_path
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN materials m ON p.material_id = m.id
    LEFT JOIN designs d ON p.design_id = d.id

    ORDER BY p.sort_order ASC, p.id DESC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка: Список товаров</title>
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
              
              <li class="menu__item-favorites">
                <a class="menu__link" href="admin-add.php">
                  <!-- <i class="bi bi-heart-fill"></i> -->
                  <span>Добавить товар</span>
                </a>
              </li>
             
              <li class="menu__item-cart">
                <a class="menu__link" href="admin-orders.php">
                  <!-- <i class="bi bi-cart-fill"></i> -->
                  <span>Заказы</span>
                </a>
              </li>
              </ul>

              <ul class="menu__icons">
               <li class="menu__item-cart">
                <a class="menu__link" href="catalog.php">
                  <i class="bi bi-folder-fill"></i>
                  <!-- <span>Каталог</span> -->
                </a>
              </li>
               <li class="menu__item-home">
                <a class="menu__link" href="index.php">
                  <i class="bi bi-house-door-fill"></i>
                  <!-- <span>Главная</span> -->
                </a>
              </li>
              <li class="menu__item-login">
                <a class="menu__link" href="logout.php">
                  <i class="bi bi-door-open-fill"></i>
                  <!-- <span>Выход</span> -->
                </a>
              </li>
              </ul>
          </nav>        
        </div>
      </div>
    </header>

    <div class="section-box_top">

    <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Управление товарами</h2>
        <a href="admin-add.php" class="btn btn__gradieht login-btn__gradieht-register">+ Добавить товар</a>
    </div>

    <?php if (empty($products)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            <i class="bi bi-info-circle me-2"></i>
            Товаров пока нет. Добавь первый через кнопку выше.
        </div>
    <?php else: ?>
       <!-- вывод сообщения "товар обновлен" после редактирования -->
      <?php if ($flashMessage): ?>
          <div class="alert alert-<?= $flashType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($flashMessage) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
      <?php endif; ?>


        <!-- table-responsive даёт горизонтальную прокрутку на узких экранах -->
        <div class="bg-white p-3 rounded shadow-sm table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table- light" >
                    <tr>
                        <th class="w-5%">ID</th>
                        <th class="w-8%">Фото</th>
                        <th>Название</th>
                        <th>Категория</th>
                        <th>Материал</th>
                        <th>Дизайн</th>
                        <th>Ширина (мм)</th>
                        <th>Цвет ленты</th>
                        <th>Цвет печати</th>
                        <th>SKU</th>
                        <th>Цена</th>
                        <th>Остаток</th>
                        <th>Статус</th>
                        <th class="text-end w-12%">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            
                            <td class="align-middle text-center">
                                <?php if (!empty($p['thumb_path'])): ?>
                                    <img src="<?= htmlspecialchars($p['thumb_path']) ?>" 
                                        class="img-thumbnail align-middle" 
                                        style="max-width: 60px; max-height: 60px;" 
                                        alt="Миниатюра">
                                <?php else: ?>
                                    <div class="bg-light border rounded p-2 align-middle" 
                                        style="width: 60px; height: 60px; line-height: 60px; color: #999; display: inline-block;">
                                        Нет фото
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($p['material_name'] ?? '—') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($p['design_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['width_mm'] ?? '—') ?></td>
                            <td><span class="badge bg-light text-dark"><?= htmlspecialchars($p['color_ribbon'] ?? '—') ?></span></td>
                            <td><span class="badge bg-light text-dark"><?= htmlspecialchars($p['color_print'] ?? '—') ?></span></td>
                            <td><span class="text-nowrap"><?= htmlspecialchars($p['sku'] ?? '—') ?></span></td>
                            <td class="fw-bold"><?= number_format($p['price'], 2, ',', ' ') ?> ₽</td>

                            <!-- Остаток: красный бейдж, если 0 -->
                            <td>
                                <?php if ((int)$p['stock_qty'] === 0): ?>
                                    <span class="badge badge-stock-zero">0</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($p['stock_qty']) ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Статус: зелёный/серый бейдж -->
                            <td>
                                <?php if ($p['is_active']): ?>
                                    <span title="Товар активен" style="color:#28a745;">&#x2714;</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">скрыт</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <a href="admin-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Редактировать</a>

                                  <form method="POST" action="admin-delete.php" style="display: inline-block;"
                                        onsubmit="return confirm('Удалить этот товар?');">
                                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                      <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                  </form>
                            </td>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
