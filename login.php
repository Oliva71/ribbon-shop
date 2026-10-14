<?php
require_once 'config/session_connect.php';
require_once 'config/db_connect.php';

// Если уже залогинен как админ — отправляем в админку
if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: admin-add.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = 'Введите логин и пароль.';
    } else {
        $stmt = $pdo->prepare("SELECT id, login, password_hash, role FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Пользователь не найден.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Неверный пароль.';
        } else {
            // Успешный вход
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $user['login'];
            $_SESSION['role'] = $user['role'];

      // Редирект в зависимости от роли
          if ($user['role'] === 'admin') {
              header('Location: admin-add.php');  // Админ — в админку
          } else {
              header('Location: index.php');  // Клиент — на главную
          }
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="styles/reset.css">
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
     <header class="header"> 
        <div class="header__top">
          <div class="container">
            <div class="header__wrapper">       
             <img src="images/logotip.png" class="logo" alt="RIBBON PRINTING">   
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
          <div class="header__wrapper">
          <nav class="menu">
            <ul class="menu__list">
          
              <li class="menu__item-home">
                <a class="menu__link" href="index.php">
                  <i class="bi bi-house-door-fill"></i>
                  <span>Главная</span>
                </a>
              </li>
              <li class="menu__item-favorites">
                <a class="menu__link" href="favorites.php">
                  <i class="bi bi-heart-fill"></i>
                  <span>Избранное</span>
                </a>
              </li>
              <li class="menu__item-cart">
                <a class="menu__link" href="cart.php">
                  <i class="bi bi-cart-fill"></i>
                  <span>Корзина</span>
                </a>
              </li>
              <li class="menu__item-login">
                <a class="menu__link" href="logout.php">
                  <i class="bi bi-door-open-fill"></i>
                  <span>Выход</span>
                </a>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </header>

    <div class="section-box_top">
      <div class="container">

          <h2 class="form__title section__title">Вход</h2>

          <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" class="mt-4" action="">
              <div class="mb-3">
                <label class="form-label" for="">Логин:</label>
                <input type="text" name="login" class="form-control" required>
              </div>

              <div class="mb-3">
                <label class="form-label" for="">Пароль</label>
                <input type="password" name="password" class="form-control" required>
              </div>

              <button type="submit" class="btn btn__gradieht login-btn__gradieht-login">Войти</button>
              <button type="submit" class="btn btn__gradieht login-btn__gradieht-register"><a href="register.php">Зарегистрироваться</a></button>

            </form>
          <button class="btn__gradieht btn__gradieht-calc">
          <a href="index.php">На главную</a>
        </button>
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
            пн - пт:  9:00 - 17:00
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
    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>