<?php
require_once __DIR__ . '/config/session_connect.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/uploads-original-thumbs-config.php';
require_once __DIR__ . '/config/favorites_connect.php';
require_once __DIR__ . '/auth-user.php';

$favData = getFavoritesData($pdo, $_SESSION['user_id'] ?? null);
$favCount = $favData['count'];
$isFavorite = $favData['ids'];

$orderId = (int)($_GET['order_id'] ?? 0);

//  Проверяем, что заказ принадлежит этому пользователю
$stmtCheck = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$stmtCheck->execute([$orderId, $_SESSION['user_id']]);
if (!$stmtCheck->fetch()) {
    header('Location: profile.php');
    exit;
}

// Получаем данные заказа
$stmtOrder = $pdo->prepare("
    SELECT * FROM orders WHERE id = ?
");
$stmtOrder->execute([$orderId]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: profile.php');
    exit;
}

// Получаем позиции заказа
$stmtItems = $pdo->prepare("
    SELECT oi.*, p.name, p.sku, i.thumb_path
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_images i ON p.id = i.product_id AND i.is_main = 1
    WHERE oi.order_id = ?
");
$stmtItems->execute([$orderId]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$statusLabels = [
    'new'        => ['Новый',       'warning'],
    'processing' => ['В работе',    'info'],
    'shipped'    => ['Отправлен',   'primary'],
    'completed'  => ['Завершён',    'success'],
    'cancelled'  => ['Отменён',     'danger'],
];

$label = $statusLabels[$order['status']] ?? ['Неизвестно', 'secondary'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Заказ №<?= $orderId ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="styles/reset.css" />
    <link rel="stylesheet" href="styles/style.css" />
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
                <li class="breadcrumb-item"><a href="profile.php">Личный кабинет</a></li>
                <li class="breadcrumb-item active" aria-current="page">Заказ №<?= $orderId ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- Левая колонка: статус, контакты, адрес -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Статус заказа</h5>
                        <div class="mb-4">
                            <span class="badge bg-<?=$label ?>" style="font-size: 1.2rem;">
                                <?=$label ?>
                            </span>
                        </div>

                        <h5 class="mb-3">Данные получателя</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><strong>Имя:</strong> <?= htmlspecialchars($order['customer_name']) ?></li>
                            <li class="mb-2"><strong>Телефон:</strong> <?= htmlspecialchars($order['customer_phone']) ?></li>
                            <li class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($order['customer_email'] ?: '—') ?></li>
                            <li class="mb-2"><strong>Адрес:</strong> <?= nl2br(htmlspecialchars($order['address'] ?: '—')) ?></li>
                            <li class="mb-2"><strong>Комментарий:</strong> <?= nl2br(htmlspecialchars($order['comment'] ?: '—')) ?></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Правая колонка: состав заказа -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Состав заказа №<?= $orderId ?></h5>
                            <span class="fw-bold text-muted">
                                Дата: <?= (new DateTime($order['created_at']))->format('d.m.Y H:i') ?>
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Фото</th>
                                        <th>Товар</th>
                                        <th>Параметры</th>
                                        <th class="text-end">Цена</th>
                                        <th class="text-end">Кол-во</th>
                                        <th class="text-end">Итого</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): 
                                        $thumbPath = $item['thumb_path'] ?? '';
                                        $imgUrl = !empty($thumbPath)
                                            ? '/' . ($projectSubdir ?? '') . '/' . ltrim($thumbPath, '/')
                                            : 'assets/img/no-image-thumb.png';
                                    ?>
                                        <tr>
                                            <td>
                                                <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="img-fluid" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                                <?php if (!empty($item['sku'])): ?>
                                                    <div class="small text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted small">
                                                <?php if ($item['custom_width_mm'] > 0): ?>
                                                    <?= (int)$item['custom_width_mm'] ?> мм<br>
                                                <?php endif; ?>
                                                <?php if (!empty($item['custom_color'])): ?>
                                                    Цвет: <?= htmlspecialchars($item['custom_color']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?= number_format($item['unit_price'], 2, ',', ' ') ?> ₽</td>
                                            <td class="text-end"><?= (int)$item['quantity'] ?> шт</td>
                                            <td class="text-end fw-bold"><?= number_format($item['total_price'], 2, ',', ' ') ?> ₽</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="5" class="text-end fw-bold">Итого:</th>
                                        <th class="text-end fw-bold"><?= number_format($order['total_amount'], 2, ',', ' ') ?> ₽</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4">
                            <a href="profile.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> К списку заказов
                            </a>
                        </div>
                    </div>
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
