<?php
require_once __DIR__ . '/config/session_connect.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/funcBreadcrumbs.php';
require_once __DIR__ . '/config/favorites_connect.php';

$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$favData = getFavoritesData($pdo, $_SESSION['user_id'] ?? null);
$favCount = $favData['count'];
$isFavorite = $favData['ids'];

// Получаем фильтры
$category_id =(int)($_GET['category_id'] ?? null);
$material_id = $_GET['material_id'] ?? null;
$design_id = (int)($_GET['design_id'] ?? 0);

$stmt = $pdo->query("SELECT id, name, category_id FROM designs");
$allDesigns = $stmt->fetchAll(PDO::FETCH_ASSOC);


//  Получаем категории и материалы для фильтров
$stmtCat = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$cats = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

$stmtMat = $pdo->query("SELECT id, name FROM materials ORDER BY name");
$mats = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

// Готовим список избранного 
$isFavorite = [];
if (isset($_SESSION['user_id'])) {
    $stmtFav = $pdo->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmtFav->execute([$_SESSION['user_id']]);
    $favIds = $stmtFav->fetchAll(PDO::FETCH_COLUMN);
    $isFavorite = array_flip($favIds);
}

// Выбираем все поля товаров и ТОЛЬКО путь к главной картинке (is_main = 1)
$sql = "SELECT p.*, pi.thumb_path, d.name AS design_name 
        FROM products p
        LEFT JOIN designs d ON d.id = p.design_id
        LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1
        WHERE p.is_active = 1";

$params = [];

if ($category_id) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}
if ($material_id) {
    $sql .= " AND p.material_id = ?";
    $params[] = $material_id;
}
if ($design_id) {
    $sql .= " AND p.design_id = ?";
    $params[] = $design_id;
}

//  Потом выбираем товары
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Счетчик избранного для шапки
$favCount = count($isFavorite);

$breadcrumbs = getBreadcrumbs('catalog', [
    'category_id' => $category_id
]);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

    <title>Каталог товаров</title>
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
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 8px;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,.08);
        }
        .product-img-wrapper {
            padding-top: 100%;  /* Было 66.67% — это делало фото прямоугольным. 100% делает квадрат */
            position: relative;
            background-color: #f8f9fa;
            overflow: hidden;
            width: 100%;     /* растягиваем на всю ширину колонки */
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

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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

                <li class="menu__item-home">
                  <a class="menu__link" href="index.php">
                    <i class=""></i>
                    <span>Главная</span>
                  </a>
                </li>

                <li class="menu__item-custom">
                  <a class="menu__link" href="custom.html">
                    <i class=""></i>
                    <span>Печать на заказ</span>
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

 <?php include __DIR__ . '/breadcrumbs-render.php'; ?>

<div class="container">
    <h1 class="mb-4 fw-bold">Каталог товаров</h1>

    <!-- Фильтры в одной строке -->
    <form method="GET" class="row g-3 mb-4 align-items-end">
        <input type="hidden" name="page" value="1">
 
        <div class="col-md-4 col-lg-3">
            <label for="category_id" class="form-label fw-semibold">Категория</label>
            <select name="category_id" id="category_id" class="form-select">
                <option value="">Все категории</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= htmlspecialchars($c['id']) ?>"
                        <?= ($c['id'] == $category_id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 col-lg-3">
            <label for="material_id" class="form-label fw-semibold">Материал</label>
            <select name="material_id" id="material_id" class="form-select">
                <option value="">Все материалы</option>
                <?php foreach ($mats as $m): ?>
                    <option value="<?= htmlspecialchars($m['id']) ?>"
                        <?= ($m['id'] == $material_id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>  
        
        <div class="col-md-4 col-lg-3">
    <label for="design_id" class="form-label fw-semibold">Дизайн</label>
    <select name="design_id" id="design_id" class="form-select">
        <option value="">Все варианты</option>
        <?php foreach ($allDesigns as $d): ?>
            <?php 
            if (!$category_id || (int)$d['category_id'] === $category_id): ?>
                <option value="<?= htmlspecialchars($d['id']) ?>"
                    <?= ($d['id'] == $design_id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['name']) ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
</div>


        <div class="col-auto">
            <button type="submit" class="btn btn__gradieht login-btn__gradieht-register">Применить</button>
        </div>
        <div class="col-auto d-none d-md-block">
            <!-- Пустой блок, чтобы кнопка была прижата к фильтру -->
        </div>
    </form>

    <?php if (count($products) === 0): ?>
        <div class="alert alert-info text-center">
            По выбранным фильтрам товаров не найдено. Попробуйте другие параметры.
        </div>
    <?php else: ?>
        <div class="row g-4">

<?php foreach ($products as $product): ?>
    <?php
    // Берем путь к ГЛАВНОЙ картинке
    $thumbPath = $product['thumb_path'] ?? '';

    if (empty($thumbPath)) {
        $thumbPath = 'data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'300\' fill=\'none\' stroke=\'%23ccc\' stroke-width=\'2\'%3E%3Crect x=\'10\' y=\'10\' width=\'280\' height=\'280\' stroke=\'none\' fill=\'%23f5f5f5\'%3E%3C/rect%3E%3Cpath d=\'M30 150 L270 150 M150 30 L150 270\'%3E%3C/path%3E%3Ctext x=\'50\' y=\'160\' font-family=\'Arial\' font-size=\'20\' fill=\'%23999\'%3EНет фото%3C/text%3E%3C/svg%3E';
    }

    // ссылка на карточку товара 
    $productLink = 'product.php?id=' . (int)$product['id'];
    ?>
    <div class="col-6 col-sm-4 col-md-3 col-lg-3">
        <div class="product-card">
            <div class="product-img-wrapper">
                <img src="<?= htmlspecialchars($thumbPath) ?>"
                     alt="<?= htmlspecialchars($product['name']) ?>"
                     class="product-img"
                     style="object-fit: cover;">
            </div>
            <div class="product-body d-flex flex-column">
                <h3 class="card-title h6 fw-semibold mb-2 text-truncate" 
                    title="<?= htmlspecialchars($product['name']) ?>">
                    <?= htmlspecialchars($product['name']) ?>
                </h3>
                
                <!-- Блок с ценой и кнопками -->
                <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                    <span class="price fw-bold"><?= number_format($product['price'], 2, ',', ' ') ?> ₽/метр</span>
                    
                    <div class="action-buttons d-flex gap-2">

                        <!-- Кнопка "В избранное" -->
                       <button type="button" 
                                class="btn btn-sm btn-outline-secondary" 
                                title="<?= isset($isFavorite[$product['id']]) ? 'Удалить из избранного' : 'Добавить в избранное' ?>"
                                data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                                data-product-id="<?= (int)$product['id'] ?>"
                                onclick="toggleFavorite(this, <?= (int)$product['id'] ?>)">
                            
                            <!-- Класс text-danger ставится СРАЗУ на сервере, если товар в избранном -->
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
<script src="js/cart.js"></script>
<script src="js/favorites.js"></script>
</body>
</html>
