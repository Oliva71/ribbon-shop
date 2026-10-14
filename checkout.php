<?php
require_once __DIR__ . '/config/session_connect.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/uploads-original-thumbs-config.php';
require_once __DIR__ . '/config/favorites_connect.php';
require_once __DIR__ . '/auth-user.php';

$favData = getFavoritesData($pdo, $_SESSION['user_id'] ?? null);
$favCount = $favData['count'];
$isFavorite = $favData['ids'];

// Определяем статус 
$isGuest = !isset($_SESSION['user_id']);

// Подтягиваем данные пользователя для автозаполнения формы
$stmtUser = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

$cartCount = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// Пустая корзина — в каталог
if (empty($_SESSION['cart'])) {
    header('Location: catalog.php');
    exit;
}

$cart = $_SESSION['cart'];
$totalAmount = 0;
$cartItems = [];

// Получаем товары
$ids = array_keys($cart);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.price, p.sku, p.width_mm, p.color_ribbon, p.color_print,
           i.thumb_path
    FROM products p
    LEFT JOIN product_images i ON p.id = i.product_id AND i.is_main = 1
    WHERE p.id IN ($placeholders)
");

$stmt->execute($ids);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $p) {
    $qty = $cart[$p['id']] ?? 0;
    $subtotal = $p['price'] * $qty;
    $totalAmount += $subtotal;

    // Путь к фото
    $thumbPath = $p['thumb_path'] ?? '';
    $imgUrl = !empty($thumbPath)
        ? '/' . $projectSubdir . '/' . ltrim($thumbPath, '/')
        : 'assets/img/no-image-thumb.png';

    // Склеиваем цвет ленты и цвет печати в одну строку для custom_color
    $colorStr = trim(
        ($p['color_ribbon'] ?? '') .
        (!empty($p['color_print']) ? ' / печать: ' . $p['color_print'] : '')
    );

    $cartItems[] = [
        'id'           => $p['id'],
        'name'         => $p['name'],
        'price'        => $p['price'],
        'qty'          => $qty,
        'subtotal'     => $subtotal,
        'thumb'        => $imgUrl,
        'sku'          => $p['sku'] ?? '',
        'width_mm'     => $p['width_mm'] ?? 0,
        'color_string' => $colorStr,
    ];
}


// отправка заказа
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout_submit') {

    // Проверка: есть ли токен в сессии и есть ли он в POST
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token'])) {
    die('Ошибка безопасности: CSRF-токен отсутствует.');
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('Ошибка безопасности: неверный токен.');
}


    $name  = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');

    if (empty($name) || empty($phone)) {
        $error = 'Заполните имя и телефон — это обязательно.';
    } else {
        $userId  = $_SESSION['user_id'] ?? null;
        $email = trim($_POST['customer_email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $pdo -> beginTransaction();

        try {
        // Создаём заказ
        $stmtOrder = $pdo->prepare("
            INSERT INTO orders
                (user_id, status, total_amount, customer_name, customer_phone, customer_email, address, comment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
     
        $stmtOrder->execute([
            $userId,
            'new',
            $totalAmount,
            $name,
            $phone,
            $email ?: null,
            $address ?: null,
            $comment ?: null,
        ]);

    // получаем ID заказа из orders, чтобы вставить его в order_items
        $orderId = (int)$pdo->lastInsertId();

        // Пишем позиции
        $stmtItem = $pdo->prepare("
            INSERT INTO order_items
                (order_id, product_id, custom_name, custom_width_mm, custom_color_ribbon, custom_color_print, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($cartItems as $item) {
            $stmtItem->execute([
                $orderId,
                $item['id'],
                $item['name'],
                $item['width_mm'],
                $item['color_ribbon'],
                $item['color_print'],
                $item['qty'],
                $item['price'],
                $item['subtotal'],
            ]);
        }
        $pdo -> commit();

         // Очищаем корзину
        unset($_SESSION['cart']);

        // Редирект на страницу успеха
        header('Location: checkout-success.php?order_id=' . $orderId);
        exit;

        } catch (Exception $e) {
            // отменяем изменения при ошибке
            $pdo -> rollBack();
            // логирование ошибки 
            error_log('Ошибка оформления заказа: ' . $e->getMessage() . ' (файл: ' . $e->getFile() . ', строка: ' . $e->getLine() . ')');

            // Перенаправляем на страницу с ошибкой
            header('Location: cart.php?error=1');
            exit;
        }       
    }
}

// Значения для автозаполнения (приоритет: POST > данные из БД)
$fvName  = $_POST['customer_name']  ?? $userData['name']  ?? '';
$fvPhone = $_POST['customer_phone'] ?? $userData['phone'] ?? '';
$fvEmail = $_POST['customer_email'] ?? $userData['email'] ?? '';
$fvAddr  = $_POST['address'] ?? '';
$fvComm  = $_POST['comment'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Оформление заказа</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="styles/reset.css" />
    <link rel="stylesheet" href="styles/style.css" />
    <style>
        .cart-item-img {
            width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #eee;
        }
        .checkout-summary { background: #f8f9fa; border-radius: 12px; padding: 24px; }
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
                    <a href="mailto:o6040686@gmail.com" class="header__top-contacts-email">
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
                            <a class="menu__link" href="index.php"><span>Главная</span></a>
                        </li>
                        <li class="menu__item-catalog">
                            <a class="menu__link" href="catalog.php"><span>Каталог</span></a>
                        </li>
                        <li class="menu__item-home">
                            <a class="menu__link" href="custom.php"><span>Печать на заказ</span></a>
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
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
                <li class="breadcrumb-item"><a href="cart.php">Корзина</a></li>
                <li class="breadcrumb-item active" aria-current="page">Оформление заказа</li>
            </ol>
        </nav>

        <h1 class="mb-4">Оформление заказа</h1>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- лево: товары -->
            <div class="col-lg-7">
                <h5 class="mb-3">Состав заказа</h5>

                <?php foreach ($cartItems as $item): ?>
                    <div class="card mb-2">
                        <div class="card-body d-flex align-items-center gap-3">
                            <img src="<?= $item['thumb'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-img">
                            <div class="flex-grow-1">
                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                <div class="text-muted small">
                                    <?= (int)$item['width_mm'] ?> мм
                                    · <?= htmlspecialchars($item['color_string']) ?>
                                </div>
                                <div class="small">
                                    <?= number_format($item['price'], 2, ',', ' ') ?> ₽ × <?= (int)$item['qty'] ?> шт
                                </div>
                            </div>
                            <div class="fw-bold">
                                <?= number_format($item['subtotal'], 2, ',', ' ') ?> ₽
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-flex justify-content-between mt-3 mb-4">
                    <span class="fw-bold">Итого:</span>
                    <span class="fw-bold fs-4">
                        <?= number_format($totalAmount, 2, ',', ' ') ?> ₽
                    </span>
                </div>

                <a href="cart.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Вернуться в корзину
                </a>
            </div>

            <!-- право: форма контактов -->
            <div class="col-lg-5">
                <div class="checkout-summary">
                    <h5 class="mb-3">Контактные данные</h5>

                    <form method="post">
                        <input type="hidden" name="action" value="checkout_submit">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Имя <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control" required
                                   value="<?= htmlspecialchars($fvName) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Телефон <span class="text-danger">*</span></label>
                            <input type="tel" name="customer_phone" class="form-control"
                                   placeholder="+7 (999) 000-00-00" required
                                   value="<?= htmlspecialchars($fvPhone) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="customer_email" class="form-control"
                                   value="<?= htmlspecialchars($fvEmail) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Адрес доставки</label>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($fvAddr) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Комментарий к заказу</label>
                            <textarea name="comment" class="form-control" rows="3" placeholder="Например: позвонить за час до доставки"><?= htmlspecialchars($fvComm) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-gradient-action w-100 btn-lg">
                            <i class="bi bi-check-circle"></i> Подтвердить заказ
                        </button>
                    </form>
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

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
      crossorigin="anonymous"
    ></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/cart.js"></script>
    <script src="js/favorites.js"></script>

</body>
</html>
