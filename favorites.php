<?php
require_once __DIR__ .  '/config/session_connect.php'; 
require_once __DIR__ .  '/config/db_connect.php'; 
require_once __DIR__ . '/config/funcBreadcrumbs.php';
require_once __DIR__ . '/uploads-original-thumbs-config.php';
require_once __DIR__ .  '/config/favorites_connect.php';

// Если гость — отправляем на отдельную страницу
if (!isset($_SESSION['user_id'])) {
    header('Location: favorites-guest.php');
    exit;
}

$cartCount = array_sum($_SESSION['cart']);


$products = [];
$isFavorite = []; // Этот массив нужен, чтобы кнопки сразу были красными

// Получаем ID товаров из избранного только если пользователь авторизован
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $favIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

     // Заполняем массив для быстрой проверки 
    $isFavorite = array_flip($favIds); 
    $favCount = count($favIds);

    // Выбираем сами товары тольео если есть ID
    if (!empty($favIds)) {
        $placeholders = implode(',', array_fill(0, count($favIds), '?'));
        $sql = "SELECT p.*, pi.thumb_path FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1 WHERE p.id IN ($placeholders) AND p.is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($favIds);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $favCount = 0;
}

$breadcrumbs = getBreadcrumbs('favorites', [
    'id' => $id
]);
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <title>Избранное</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    /> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles/reset.css" />
    <link rel="stylesheet" href="styles/style.css" />
    <style>
        .product-card {
            transition: opacity 0.3s ease, transform 0.3s ease;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 8px;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,.08);
        }
        .product-img-wrapper {
            padding-top: 66.67%; /* 3:2 соотношение */
            position: relative;
            background-color: #f8f9fa;
            overflow: hidden;
        }
        .product-img {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        .product-body {
            padding: 1rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .price {
            font-weight: 700;
            color: #2c3e50;
            margin-top: auto; /* прижимает цену к низу */
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

                <li class="menu__item-home">
                  <a class="menu__link" href="custom.html">
                    <i class=""></i>
                    <span>Печать на заказ</span>
                  </a>
                </li>

                <li class="menu__item-home">
                  <a class="menu__link" href="catalog.php">
                    <i class=""></i>
                    <span>Каталог</span>
                  </a>
                </li>
              </ul>

              <ul class="menu__icons">
                 <li class="menu__item-favorites">
                  <a class="menu__link" href="favorites.php">
                    <i class="bi bi-heart-fill"></i>
                    <span class="count-favorites-badge badge bg-secondary rounded-pill" style="display: <?= $favCount > 0 ? 'flex' : 'none' ?>;">
                      <?= $favCount ?></span>
                    <!-- <span>Избранное</span> -->
                  </a>
                </li>

                <li class="menu__item-cart">
                  <a class="menu__link" href="cart.php">
                    <i class="bi bi-cart-fill"></i>
                    <span class="count-cart-badge badge bg-secondary rounded-pill" style="display: <?= $cartCount > 0 ? 'flex' : 'none' ?>;">
                      <?= $cartCount ?>
                    </span>
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

 <div class="container py-4">

 <?php include __DIR__ . '/breadcrumbs-render.php'; ?>
 
    <h1 class="mb-4 fw-bold">Избранное</h1>

    <?php if (empty($products)): ?>
        <div class="alert alert-info text-center">
            У вас пока нет товаров в избранном.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $product): ?>
                <?php
                // Путь к картинке (заглушка на случай отсутствия)
                $thumbPath = $product['thumb_path'] ?? '';
                if (empty($thumbPath)) {
                    $thumbPath = 'data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'300\' fill=\'none\' stroke=\'%23ccc\' stroke-width=\'2\'%3E%3Crect x=\'10\' y=\'10\' width=\'280\' height=\'280\' stroke=\'none\' fill=\'%23f5f5f5\'%3E%3C/rect%3E%3Cpath d=\'M30 150 L270 150 M150 30 L150 270\'%3E%3C/path%3E%3Ctext x=\'50\' y=\'160\' font-family=\'Arial\' font-size=\'20\' fill=\'%23999\'%3EНет фото%3C/text%3E%3C/svg%3E';
                }
                $productLink = 'product.php?id=' . (int)$product['id'];
                
                $isInFav = true; 
                ?>

                <div class="col-6 col-sm-4 col-md-3">
                    <div class="product-card">
                        <div class="product-img-wrapper">
                            <img src="<?= htmlspecialchars($thumbPath) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="product-img">
                        </div>
                        <div class="product-body d-flex flex-column">
                            <h3 class="card-title h6 fw-semibold mb-2 text-truncate" title="<?= htmlspecialchars($product['name']) ?>">
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <span class="price fw-bold"><?= number_format($product['price'], 2, ',', ' ') ?> ₽/метр</span>
                                
                                <div class="action-buttons d-flex gap-2">
                                    <!-- Кнопка УДАЛЕНИЯ из избранного -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                            data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                                            onclick="toggleFavorite(this, <?= (int)$product['id'] ?>)">
                                        <i class="bi bi-heart-fill <?= isset($isFavorite[$product['id']]) ? 'text-danger' : 'text-muted' ?>"></i>
                                    </button>

                                    
                                   <!-- Кнопка "В корзину" -->
                        <button type="button"
                                id="catalog-btn-<?= (int)$product['id'] ?>"
                                class="btn btn-sm btn-outline-secondary" 
                                title="Добавить в корзину"
                                data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                onclick="addToCart(<?= (int)$product['id'] ?>, event)"
                                data-stock="<?= (int)$product['stock_qty']; ?>">
                            <i class="bi bi-cart-plus-fill"></i> 
                        </button>
                        
                                    
                                    <!-- Кнопка "Подробнее" -->
                                    <a href="<?= htmlspecialchars($productLink) ?>" 
                                      class="btn btn-sm btn-primary btn-gradient-action" 
                                      title="Перейти к товару">
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script src="js/favorites.js"></script>
<script src="js/cart.js"></script>

</body>
</html>