<?php
require_once 'config/session_connect.php';
require_once 'config/db_connect.php';

$cartCount = array_sum($_SESSION['cart']);

$isFavorite = []; // Этот массив нужен, чтобы кнопки сразу были красными

//  Получаем ID товаров из избранного ТОЛЬКО если пользователь авторизован
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $favIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $isFavorite = array_flip($favIds); 
    $favCount = count($favIds);

    //  Выбираем сами товары ТОЛЬКО если есть ID
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

$message = '';
$class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $width = (int)($_POST['width'] ?? 0);
    $colorRibbon = trim($_POST['color_ribbon'] ?? '');
    $colorPrint = trim($_POST['color_print'] ?? '');

    // Простая проверка
     if (!$name || !$phone || !$description || !$colorRibbon || !$colorPrint) {
        $message = 'Пожалуйста, заполните все обязательные поля.';
        $class = 'alert-warning';
    } elseif (!preg_match('/^\+?[0-9\s\-\(\)]{10,}$/', $phone)) {
        // простая проверка телефона: хотя бы 10 цифр, разрешены +, пробелы, скобки, тире
        $message = 'Некорректный формат телефона.';
        $class = 'alert-danger';
    } else {
        $pdo->beginTransaction();
        try {
           

            //  Создаем заказ в таблице orders
            $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_phone, status, created_at) VALUES (?, ?, 'new', NOW())");
            $stmt->execute([$name, $phone]);
            $orderId = $pdo->lastInsertId();

            $price = 0;
            $totalPrice = $price * 1;
            
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, custom_name, custom_width_mm, custom_color_ribbon, custom_color_print) 
                                  VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([$orderId, NULL, 1, $price, $totalPrice, $description, $width, $colorRibbon, $colorPrint]);

            $pdo->commit();
            $message = 'Спасибо! Ваш заказ принят. Менеджер скоро свяжется с вами.';
            $class = 'alert-success';

             // Очищаем форму
            $_POST = []; 
            
            header('Location: custom-order.php?success=1');
            exit;
           
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Произошла ошибка при сохранении заказа. Попробуйте позже.';
            $class = 'alert-danger';
        }
    } 
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Индивидуальный заказ</title>    
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                  <a class="menu__link" href="catalog.php">
                    <i class=""></i>
                    <span>Каталог</span>
                  </a>
                </li>

                <li class="menu__item-home">
                  <a class="menu__link" href="index.php">
                    <i class=""></i>
                    <span>Главная</span>
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

<section class="services section-box_top">

<div class="container">

<!-- <div class="container mt-5"> -->
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">Заказать ленту с индивидуальным текстом</h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($message): ?>
                        <div class="alert <?= $class ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Ваше имя *</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Телефон *</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+7 (999) 000-00-00" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Что нужно напечатать? *</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Например: 'С Днем Рождения, Саша!'" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            
                             <div class="col-md-6">
                                <label for="ribbon_color">Цвет ленты *</label>
                                <input type="text" id="ribbon_color" name="ribbon_color" class="form-control" placeholder="Например: красный, белый, черный" required>
                            </div>
                            <div class="col-md-6">
                                <label for="print_color">Цвет печати *</label>
                                <input type="text" id="print_color" name="print_color" class="form-control" placeholder="Например: золотой, серебряный, черный" required>
                            </div>
                        </div>
                        <div class="row">
                                <div class="col-md-6 mb-3">
                                <label class="form-label">Ширина (мм)</label>
                                <input type="number" name="width" class="form-control" value="<?= htmlspecialchars($_POST['width'] ?? '') ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-gradient-action w-100">Отправить заказ</button>
                        <a href="catalog.php" class="btn btn-outline-secondary w-100 mt-2">Вернуться в каталог</a>
                    </form>
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
    <script src="js/main.js"></script>
    <script src="js/cart.js"></script>
    <script src="js/favorites.js"></script>
</body>
</html>
