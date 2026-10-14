<?php
require_once __DIR__ . '/config/session_connect.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/funcBreadcrumbs.php';
require_once __DIR__ . '/config/favorites_connect.php';
require_once __DIR__ . '/uploads-original-thumbs-config.php';

$favData = getFavoritesData($pdo, $_SESSION['user_id'] ?? null);
$favCount = $favData['count'];
$isFavorite = $favData['ids'];

// получение товара ID
$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: catalog.php');
    exit;
}

// Товар
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    // Товар не найден или неактивен
    http_response_code(404);
    echo '<h1>Товар не найден</h1><a href="catalog.php">Вернуться в каталог</a>';
    exit;
}

$isFavorite = [];
if (isset($_SESSION['user_id'])) {
    $stmtFav = $pdo->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmtFav->execute([$_SESSION['user_id']]);
    $favIds = $stmtFav->fetchAll(PDO::FETCH_COLUMN);
    $isFavorite = array_flip($favIds); // Превращаем [1, 2] в [1=>true, 2=>true]
}

// Все фото товара (главное — первым по is_main, потом по sort_order)
$stmtImg = $pdo->prepare("
    SELECT image_path, thumb_path, is_main, sort_order 
    FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_main DESC, sort_order ASC
");
$stmtImg->execute([$productId]);
$images = $stmtImg->fetchAll();

// Главное фото (первое в массиве) и остальные
$mainImage = null;
$galleryImages = [];

foreach ($images as $img) {
    $imgPath = $img['thumb_path'] ?? $img['image_path'] ?? '';
    if (empty($imgPath)) continue;

    $url = '/' . $projectSubdir . '/' . ltrim($imgPath, '/');

    if ($mainImage === null) {
        $mainImage = $url;
    }
    $galleryImages[] = $url;
}

// Если фото вообще нет — заглушка
if ($mainImage === null) {
    $mainImage = 'assets/img/no-image-thumb.png';
    $galleryImages = [$mainImage];
}

  $breadcrumbs = getBreadcrumbs('product', [
    'category_id' => $product['category_id'] ?? null,
    'product_name' => $product['name']
]);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

    <title>Карточка товара</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles/reset.css" />
    <link rel="stylesheet" href="styles/style.css" />
    <style>
         .product-gallery-main {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #eee;
            object-fit: cover;
        }
        .product-thumb {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .product-thumb:hover {
            border-color: #ccc;
        }
        .product-thumb.active {
            border-color: var(--grad3, #dc3545);
        }
        .product-info-label {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 2px;
        }
        .product-info-value {
            font-weight: 600;
        }
        .qty-input {
            width: 70px;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light" data-is-guest="<?= isset($_SESSION['user_id']) ? '0' : '1' ?>">

<header class="header">
      <div class="header__top">
        <div class="container">
          <div class="header__wrapper">
            <img src="images/logotip.png" class="logo" alt="RIBBON PRINTING" />
            <div class="header__top-contacts">
              <a href="tel:+78632500000" class="header__top-contacts-phone">
                <i class="bi bi-telephone-fill"></i>
                <span>+7 (863) 250 00 00</span>
              </a>
              <a
                href="mailto:o6040686@gmail.com"
                class="header__top-contacts-email"
              >
                <i class="bi bi-envelope-fill"></i>
                <span>Наша почта</span>
              </a>
            </div>
          </div>
        </div>
      </div>

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

                <li class="menu__item-catalog">
                  <a class="menu__link" href="index.php">
                    <i class=""></i>
                    <span>Главная</span>
                  </a>
                </li>

                <li class="menu__item-catalog">
                  <a class="menu__link" href="catalog.php">
                    <i class=""></i>
                    <span>Каталог</span>
                  </a>
                </li>

                <li class="menu__item-home">
                  <a class="menu__link" href="custom-order.php">
                    <i class=""></i>
                    <span>Печать на заказ</span>
                  </a>
                </li>
              </ul>

              <ul class="menu__icons">
                <li class="menu__item-favorites">
                  <a class="menu__link" href="favorites.php">
                    <i class="bi bi-heart-fill"></i>
                    <span class="count-favorites-badge"><?= $favCount ?? 0 ?></span>
                    <!-- <span>Избранное</span> -->
                  </a>
                </li>
                <li class="menu__item-cart">
                  <a class="menu__link menu__link-cart" href="cart.php">
                    <i class="bi bi-cart-fill"></i>
                    <span class="count-cart-badge"><?= $cartCount ?? 0 ?></span>
                    <!-- <span>Корзина</span> -->
                  </a>
                </li>

                <li class="menu__item-cart">
                  <a class="menu__link" href="profile.php" title="Личный кабинет">
                    <i class="bi bi-person-circle"></i>
                  </a>
                </li>

                <li class="menu__item-login">
                  <a class="menu__link" href="login.php">
                    <i class="bi bi-door-open-fill"></i>
                    <!-- <span>Вход</span> -->
                  </a>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
</header>

 <section class="section-box_top">

 <div class="container mb-5">

      <?php include __DIR__ . '/breadcrumbs-render.php'; ?>

        <div class="row g-4">

            <!-- галерея (слева) -->
            <div class="col-lg-6">
                <!-- Главное фото -->
                <img 
                    src="<?= $mainImage ?>" 
                    alt="<?= htmlspecialchars($product['name']) ?>" 
                    class="product-gallery-main mb-3"
                    id="mainImage"
                >

                <!-- Миниатюры -->
                <?php if (count($galleryImages) > 1): ?>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach ($galleryImages as $i => $thumbUrl): ?>
                        <img 
                            src="<?= $thumbUrl ?>" 
                            alt="Фото <?= $i + 1 ?>" 
                            class="product-thumb <?= $i === 0 ? 'active' : '' ?>"
                            onclick="switchMainImage(this, '<?= $thumbUrl ?>')"
                        >
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- информация (справа) -->
            <div class="col-lg-6">
                <h1 class="mb-2"><?= htmlspecialchars($product['name']) ?></h1>

                <!-- Кнопка ИЗБРАННОЕ -->
                <div class="mb-3">
                    <button type="button" 
                            class="btn btn-outline-secondary btn-sm" 
                            title="<?= isset($isFavorite[$product['id']]) ? 'Удалить из избранного' : 'Добавить в избранное' ?>"
                            data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                            data-product-id="<?= (int)$product['id'] ?>"
                            onclick="toggleFavorite(this, <?= (int)$product['id'] ?>)">
                        <i class="bi bi-heart-fill <?= isset($isFavorite[$product['id']]) ? 'text-danger' : 'text-muted' ?>"></i>
                    </button>
                </div>

                <?php if (!empty($product['sku'])): ?>
                    <p class="text-muted small mb-3">Артикул: <?= htmlspecialchars($product['sku']) ?></p>
                <?php endif; ?>

                <div class="fs-3 fw-bold mb-3" style="color: #504b64;">
                    <?= number_format($product['price'], 2, ',', ' ') ?> ₽ / метр
                </div>

                <!-- Характеристики -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="product-info-label">Ширина ленты</div>
                        <div class="product-info-value"><?= (int)$product['width_mm'] ?> мм</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="product-info-label">Цвет ленты</div>
                        <div class="product-info-value">
                            <?= htmlspecialchars($product['color_ribbon']) ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="product-info-label">Цвет печати</div>
                        <div class="product-info-value">
                            <?= htmlspecialchars($product['color_print']) ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="product-info-label">Минимальный заказ</div>
                        <div class="product-info-value">
                            <?= (int)($product['min_order_qty'] ?? 1) ?> 
                        </div>
                    </div>
                    
                    <div class="col-sm-6">
                        <div class="product-info-label">В наличии</div>
                        <div class="product-info-value">
                            <?php if ((int)$product['stock_qty'] > 0): ?>
                                <span class="text-success"><?= (int)$product['stock_qty'] ?></span>
                            <?php else: ?>
                                <span class="text-warning">Под заказ</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Описание -->
                <?php if (!empty($product['description'])): ?>
                <div class="mb-4">
                    <h5 class="mb-2">Описание</h5>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>
                <?php endif; ?>

                <?php
                // Проверяем, есть ли товар уже в корзине
                $isInCart = isset($_SESSION['cart'][$product['id']]);

                // Если есть — берём текущее количество, если нет — 0
                $currentQtyInCart = $isInCart ? (int)$_SESSION['cart'][$product['id']] : 0;

                // 2. Определяем минимальное значение для инпута
                // Если товара нет в корзине -> мин. заказ 5. Если есть -> можно добавлять по 1.
                $minOrderQty = $isInCart ? 1 : max(1, (int)$product['min_order_qty']);

                // 3. Определяем начальное значение (если товара нет, сразу показываем миним.)
                 $startValue  = $isInCart ? 1 : max(1, (int)$product['min_order_qty']);
                ?>

              <!-- Блок товара -->
<div class="product-actions">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <label class="product-info-label mb-0">Количество:</label>
        
        <input type="number" 
               name="quantity" 
               id="qty-input-<?= (int)$product['id'] ?>" 
               class="form-control qty-input" 
               value="<?= $startValue ?>" 
               min="<?= $minOrderQty ?>" 
               max="<?= (int)$product['stock_qty'] ?>" 
               data-product-id="<?= (int)$product['id'] ?>"
               data-stock="<?= (int)$product['stock_qty'] ?>"
               required>
    </div>

    <!-- кнопка -->
    <button 
        type="button" 
        id="card-add-btn"
        class="btn btn-primary btn-gradient-action mt-2"
        onclick="addToCart(<?= (int)$product['id'] ?>, event)"
    >
        <i class="bi bi-cart-plus"></i> 
        <span id="btn-text-<?= (int)$product['id'] ?>">Добавить в корзину</span>
    </button>

    <!-- текст статуса -->
    <p class="text-muted small mt-2" id="cart-hint-<?= (int)$product['id'] ?>">
        <?php if ($isInCart): ?>
            В корзине: <?= $currentQtyInCart ?> шт. Можно добавлять по 1 шт.
        <?php else: ?>
            Минимальный заказ: <?= (int)$product['min_order_qty'] ?> шт.
        <?php endif; ?>
    </p>
</div>


            </div>
        </div>
    </div>

 </section>
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
                <span>Ростов-на-Дону</span>
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
              пн - пт: 10:00 - 18:00
            </li>
          </ul>
        </div>

        <div class="footer__bottom">
          <img src="images/logotip.png" class="logo" alt="RIBBON PRINTING" />
          <div class="footer__politica">
            <p class="footer__copy">&#169;2010-2025 RIBBON PRINTING</p>
            <p class="footer__politika-kpd">
              <a href=""
                >Политика конфиденциальности и обработки персональных данных</a
              >
            </p>
          </div>
        </div>
      </div>
    </footer>
    
    <script>
    function switchMainImage(thumb, url) {
    // Меняем главное фото
    document.getElementById('mainImage').src = url;

    // Подсвечиваем активную миниатюру
    document.querySelectorAll('.product-thumb').forEach(function(el) {
        el.classList.remove('active');
    });
    thumb.classList.add('active');
}
</script>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<script src="js/main.js"></script>
<script src="js/cart.js"></script>
<script src="js/favorites.js"></script>
</body>
</html>