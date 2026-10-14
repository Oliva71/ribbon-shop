<?php
require_once __DIR__ .  '/config/session_connect.php'; 
require_once __DIR__ .  '/config/db_connect.php'; 
require_once __DIR__ .  '/config/favorites_connect.php';

$favData = getFavoritesData($pdo, $_SESSION['user_id'] ?? null);
$favCount = $favData['count'];
$isFavorite = $favData['ids'];

// 1. Один запрос — получаем сразу все товары для слайдера
$sql = "
    SELECT 
        c.name AS category_name,
        pi.image_path,
        c.id AS category_id
    FROM categories c
    JOIN products p ON c.id = p.category_id
    JOIN product_images pi ON p.id = pi.product_id
    WHERE p.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order, c.id
    LIMIT 3
";

$stmt = $pdo->query($sql);
$sliderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Главная</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    />
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
    />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
      crossorigin="anonymous"
    />
    
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
        <h2 class="services__title section__title">
          Печать на лентах по индивидуальному заказу и по готовым макетам
        </h2>
        <p class="section__text">
          Термотрансферная печать на ленте позволяет донести до потребителя
          необходимую информацию, к примеру, такую как: состав ткани,
          рекомендации по уходу за иэделием, размерный ряд и прочее, а также это
          отличный способ проявить свою индивидуальность - разместить свой
          логотип, название бренда, подходящий слоган и многое другое на ленте.
        </p>
        <ul class="services__list">
          <li class="services__item">
            <p class="services__item-title">Виды лент для печати</p>
            <p class="services__item-text">
              Печатаем на сатиновой, нейлоновой, силиконовой лентах, шириной от
              1 см до 10 см.
            </p>
          </li>
          <li class="services__item">
            <p class="services__item-title">Дизайн макета для печати</p>
            <p class="services__item-text">
              Разработаем дизайн макета с учетом Ваших пожеланий и задач. <br />
              А так же Вы можете воспользоваться готовым макетом.
            </p>
          </li>
          <li class="services__item">
            <p class="services__item-title">Каталог готовой продукции</p>
            <p class="services__item-text">
              В нашем каталоге Вы найдете примеры готовых работ, среди которых:
              составники, размерники, декоративные ленты и прочее.
            </p>
          </li>
        </ul>

        <!-- <button class="btn__gradieht home-btn__gradieht-calc">
          <a href="calculator.html">Рассчитать стоимость</a>
        </button> -->
        <button class="btn__gradieht home-btn__gradieht-placeorder">
          <a href="placeorder.html">Оформить заказ</a>
        </button>
      </div>
    </section>

    <section class="catalog section-box">
    <div class="container">
        <h2 class="catalog__title section__title">Каталог готовой продукции</h2>
        <p class="section__text-catalog">
          Здесь можно посмотреть примеры готовой продукции.<br />
          Увидеть, к примеру, как смотрится цветная нечать на ленте того или
          иного цвета. Подчерпнуть идеи для индивидуального макета или просто
          заказать печать уже по готовому макету, из представленных в каталоге.
        </p>

        <div class="swiper">
          <div class="swiper-wrapper">
             <?php if (!empty($sliderItems)): ?>
            <?php foreach ($sliderItems as $item): ?>
            <div class="swiper-slide">
               <!-- Динамический фон -->
                  
              <div class="slide-bg" style="background-image: 
              url('<?= htmlspecialchars($item['image_path']) ?>');">          
              </div>
              <div class="slide-content">
                <!-- <h2 class="catalog__item-title">Составники</h2> -->

                  <h2 class="catalog__item-title"><?= htmlspecialchars($item['category_name']) ?></h2>
                
                <button class="btn__gradieht home-btn__gradieht-slide">

                <a href="catalog.php?category_id=<?= (int)$item['category_id'] ?>" class="catalog__item-link">Подробнее...</a>

                </button>
              </div>
            </div>

             <?php endforeach; ?>
        <?php else: ?>
          <div class="swiper-slide">
                <div class="slide-bg" style="background-color: #f0f0f0;"></div>
                <div class="slide-content">
                    <h2>Нет товаров для отображения</h2>
                </div>
            </div>
            <?php endif; ?>
           </div>
    
          <div class="swiper-button-prev"></div>
          <div class="swiper-button-next"></div>

          <!-- Пагинация -->
          <div class="swiper-pagination"></div>
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

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
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
