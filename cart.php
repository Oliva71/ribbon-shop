<?php
require_once __DIR__ . '/config/session_connect.php'; 
require_once __DIR__ . '/config/db_connect.php'; 
require_once __DIR__ . '/config/funcBreadcrumbs.php';
require_once __DIR__ . '/uploads-original-thumbs-config.php';
require_once __DIR__ . '/config/favorites_connect.php';

$favData = getFavoritesData($pdo, $_SESSION['user_id'] ?? null);
$favCount = $favData['count'];
$isFavorite = $favData['ids'];

$cartCount = array_sum($_SESSION['cart']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

        if ($productId > 0 && !empty($_SESSION['cart'][$productId])) {

        // Получаем min_order_qty для товара
        $stmtMin = $pdo->prepare("SELECT min_order_qty FROM products WHERE id = ?");
        $stmtMin->execute([$productId]);
        $minQty = max(1, (int)$stmtMin->fetchColumn());

        // Удаление товара
        if ($action === 'remove') {
            unset($_SESSION['cart'][$productId]);
        }

        // Увеличение количества
        if ($action === 'increase') {
            $_SESSION['cart'][$productId]++;
        }

        // Уменьшение количества — не ниже минимума
        if ($action === 'decrease') {
            // Если текущее количество больше минимума — уменьшаем
            if ($_SESSION['cart'][$productId] > $minQty) {
                $_SESSION['cart'][$productId]--;
            }
             // Если равно минимуму — ничего не делаем (защита от падения ниже)
        }

        // Ручной ввод количества
        if ($action === 'update' && isset($_POST['quantity'])) {
            $qty = (int)$_POST['quantity'];

            if ($qty <= 0) {
                unset($_SESSION['cart'][$productId]);
            } elseif ($qty < $minQty) {
              // Если ввели меньше минимума — ставим минимум и возвращаем ошибку
                $_SESSION['cart'][$productId] = $minQty;    
                $_SESSION['flash_message'] = "Минимальное количество заказа: {$minQty}. Установлено автоматически.";
            } else {
                $_SESSION['cart'][$productId] = $qty;
            }
        }
    }
    // Перезагружаем страницу, чтобы избежать повторной отправки формы
    header('Location: cart.php');
    exit;
}

// получение товаров из БД
$cartItems = [];
$totalPrice = 0;

if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    // берём фото из product_images через LEFT JOIN
    $sql = "
        SELECT 
            p.id, p.name, p.width_mm, p.color_ribbon, p.color_print, p.price, p.min_order_qty, i.thumb_path
        FROM products p
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_main = 1
        WHERE p.id IN ($placeholders)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $id = $product['id'];
        $qty = $_SESSION['cart'][$id];
        $sum = $product['price'] * $qty;
        $totalPrice += $sum;

        // Формируем путь к превью
        $dbPath = $product['thumb_path'] ?? '';
    
        if (empty($dbPath)) {
            $imgUrl = 'assets/img/no-image-thumb.png'; 
        } else {
        $imgUrl = '/' . $projectSubdir . '/' . ltrim($dbPath, '/');
        }

        $cartItems[] = [
            'id'           => $id,
            'name'         => $product['name'],
            'width_mm'     => $product['width_mm'],
            'color_ribbon' => $product['color_ribbon'],
            'color_print'  => $product['color_print'],
            'price'        => $product['price'],
            'qty'          => $qty,
            'sum'          => $sum,
            'thumb'        => $imgUrl, // Сюда попадает чистый путь из базы
            'min_qty'      => $product['min_order_qty'] ?? 1,
        ];

    }
}

$breadcrumbs = getBreadcrumbs('cart', [
    'id' => $id
]);

?>

<!doctype html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Корзина</title>
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
        .menu__link-cart { position: relative; display: inline-flex; align-items: center; }
        .count-cart-badge {
            position: absolute; top: -6px; right: -10px;
            background-color: var(--grad3, #dc3545); color: #fff;
            font-size: 10px; font-weight: 600;
            width: 20px; height: 20px; min-width: 20px;
            padding: 0 6px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-sizing: border-box;
        }
        .cart-item-img {
            width: 80px; height: 80px; object-fit: cover; border-radius: 8px;
            border: 1px solid #eee;
        }
        /* эффект при наведении на фото */
        .cart-item-img:hover {
            transform: scale(1.05);
            transition: transform 0.2s;
        }
        .qty-btn { width: 32px; height: 32px; padding: 0; }
        .cart-summary { background: #f8f9fa; border-radius: 12px; padding: 24px; }

        .alert-error {
            background-color: #f9e5e6;
            color: #703135;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-error a {
          color: #212529;
          text-decoration: none;
          cursor: pointer;
        }

        .alert-error a:hover{
          color: #55585a;;
          text-decoration: underline;
        }
    </style>
    
  </head>
  <body>
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

    <div class="container">
    <!-- Сообщение пользователю при ошибке оформления заказа -->
    <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
        <div class="alert-error">Не удалось оформить заказ. Проверьте данные или попробуйте позже.
        <br>
        <a href="checkout.php" class="btn-retry">Попробовать снова</a>
        </div>   
    <?php endif; ?>


    <?php include __DIR__ . '/breadcrumbs-render.php'; ?>
    
    <h1 class="mb-4">Корзина</h1>

    <?php if (empty($cartItems)): ?>
        <div class="text-center py-5">
            <i class="bi bi-cart-x" style="font-size: 64px; color: #ccc;"></i>
            <h4 class="mt-3 text-muted">Корзина пуста</h4>
            <p class="text-muted">Добавьте товары из каталога, чтобы оформить заказ.</p>
            <a href="catalog.php" class="btn btn-primary btn-gradient-action mt-2">Перейти в каталог</a>
        </div>

    <?php else: ?>
        <div class="row">
            <!-- Список товаров -->
            <div class="col-lg-8">
                <?php foreach ($cartItems as $item): ?>                  
                    <div class="card mb-3">
                        <div class="card-body d-flex align-items-center gap-3">

                            <!-- фото товара -->
                            <img 
                                src="<?= $item['thumb'] ?>" 
                                alt="<?= htmlspecialchars($item['name']) ?>" 
                                class="cart-item-img"
                                loading="lazy"
                            >

                            <!-- Информация о товаре -->
                            <div class="flex-grow-1">
                                <h5 class="mb-1"><?= htmlspecialchars($item['name']) ?></h5>
                                <p class="mb-1 text-muted small">
                                    Ширина: <?= (int)$item['width_mm'] ?> мм
                                    · Цвет ленты: <?= htmlspecialchars($item['color_ribbon']) ?>
                                    · Цвет печати: <?= htmlspecialchars($item['color_print']) ?>
                                </p>
                                <p class="mb-0 fw-bold">
                                    <?= number_format($item['price'], 2, ',', ' ') ?> ₽ / шт
                                </p>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <!-- Кнопка МИНУС -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="decrease">
                                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                    <!-- Кнопка будет серой и неактивной, если кол-во равно минимуму -->
                                    <button type="submit" class="btn btn-outline-secondary qty-btn" 
                                            <?= $item['qty'] <= $item['min_qty'] ? 'disabled' : '' ?>>
                                        -
                                    </button>
                                </form>

                                <!-- Цифра -->
                                <span class="fw-bold" style="min-width: 30px; text-align: center;">
                                    <?= $item['qty'] ?>
                                </span>

                                <!-- Кнопка ПЛЮС -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="increase">
                                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-outline-secondary qty-btn">
                                        +
                                    </button>
                                </form>
                            </div>


                            <!-- Сумма по товару -->
                            <div class="text-end" style="min-width: 100px;">
                                <div class="fw-bold">
                                    <?= number_format($item['sum'], 2, ',', ' ') ?> ₽
                                </div>
                            </div>

                            <!-- Удаление -->
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Удалить">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>

                        </div>
                    </div>
                <?php endforeach; ?>            
            </div>

            <!-- Итого -->
            <div class="col-lg-4">
                <div class="cart-summary sticky-top" style="top: 20px;">
                    <h4 class="mb-3">Итого</h4>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Товаров:</span>
                        <span><?= $cartCount ?> шт</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Сумма:</span>
                        <span class="fw-bold fs-5">
                            <?= number_format($totalPrice, 2, ',', ' ') ?> ₽
                        </span>
                    </div>
                    <button class="btn btn-primary btn-gradient-action w-100 mb-2">
                    <a href="checkout.php">
                            Оформить заказ
                    </a>       
                    </button>
                    <a href="catalog.php" class="btn btn-outline-secondary w-100">
                        Продолжить покупки
                    </a>
                </div>
            </div>
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
