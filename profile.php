<?php
require_once __DIR__ . '/config/session_connect.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/favorites_connect.php';
require_once __DIR__ . '/auth-user.php';

$favData = getFavoritesData($pdo, $_SESSION['user_id'] ?? null);
$favCount = $favData['count'];
$isFavorite = $favData['ids'];

// Данные пользователя
$stmtUser = $pdo->prepare("SELECT name, login, email, phone FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

// Заказы пользователя
$stmtOrders = $pdo->prepare("
    SELECT id, status, total_amount, customer_name, customer_phone, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmtOrders->execute([$_SESSION['user_id']]);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

// Метки статусов
$statusLabels = [
    'new'        => ['Новый','warning'],
    'processing' => ['В работе','info'],
    'shipped'    => ['Отправлен','primary'],
    'completed'  => ['Завершён','success'],
    'cancelled'  => ['Отменён','danger'],
];

$displayName = $user['name'] ?: $user['login'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Личный кабинет</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
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
                <li class="breadcrumb-item active" aria-current="page">Личный кабинет</li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- профиль -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-3"><?= htmlspecialchars($displayName) ?></h4>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted">Логин</span>
                                <strong><?= htmlspecialchars($user['login']) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted">Email</span>
                                <strong><?= htmlspecialchars($user['email'] ?: '—') ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted">Телефон</span>
                                <strong><?= htmlspecialchars($user['phone'] ?: '—') ?></strong>
                            </li>
                        </ul>
                        <a href="logout.php" class="btn btn-outline-danger w-100 mt-3">
                            <i class="bi bi-box-arrow-right"></i> Выйти из профиля
                        </a>
                    </div>
                </div>
            </div>

            <!-- заказы -->
            <div class="col-lg-8">
                <h4 class="mb-3">Мои заказы</h4>

                <?php if (empty($orders)): ?>
                    <div class="alert alert-light border">
                        У вас пока нет заказов.
                        <a href="catalog.php" class="alert-link">Перейти в каталог</a>
                    </div>
                                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>№</th>
                                    <th>Дата</th>
                                    <th>Статус</th>
                                    <th class="text-end">Сумма</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php foreach ($orders as $o):?>
                                    <tr>
                                        <td><strong>№<?= (int)$o['id'] ?></strong></td>
                                        <td>
                                            <?php
                                                $date = new DateTime($o['created_at']);
                                                echo $date->format('d.m.Y, H:i');
                                            ?>
                                        </td>

                                        <td>
                                            <?php 
                                                // Получаем массив [текст_статуса, класс_bootstrap]
                                                $label = $statusLabels[$o['status']] ?? ['Неизвестно', 'secondary']; 

                                                 // ВАЖНО: Берем конкретные элементы массива по индексам
                                                $statusText = $label[0]; 
                                                $statusClass = $label[1];
                                            ?>
                                            <!-- Вставляем $label в класс (например, 'warning') -->
                                            <span class="badge bg-<?= htmlspecialchars($statusClass) ?>">
                                                <?= htmlspecialchars($statusText) ?>
                                            </span>
                                        </td>

                                        
                                        <td class="text-end fw-bold">
                                            <?= number_format($o['total_amount'], 2, ',', ' ') ?> ₽
                                        </td>
                                        <td>
                                            <a href="checkout-detail.php?order_id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-gradient-action">Подробнее</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

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
