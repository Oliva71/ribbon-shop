<?php
require_once 'config/db_connect.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $nameRaw    = trim($_POST['name'] ?? '');   
    $email    = trim($_POST['email'] ?? '');
    $phoneRaw = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

     // Очистка и валидация
    $phone = preg_replace('/[^0-9\+\-]/', '', $phoneRaw);
    $name  = preg_replace('/[^a-zA-Z\sА-Яа-яЁё]/u', '', $nameRaw); // Оставляем буквы и пробелы

    if (empty($login) || empty($name) || empty($email) || empty($phoneRaw) || empty($password)) {
        $error = 'Заполните все поля';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $login)) {
        $error = 'Логин: только латиница, цифры и _, от 3 до 20 символов';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email';
    } elseif (empty($phone) || !preg_match('/^\+?[0-9]{10,}$/', $phone)) {
        $error = 'Некорректный номер телефона';
    }

    
    if (empty($error)) {
        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? OR login = ?");
            $stmtCheck->execute([$email, $login]);
            if ($stmtCheck->fetch()) {
                $error = 'Такой email или логин уже занят';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmtInsert = $pdo->prepare(
                    "INSERT INTO users (login, name, email, phone, password_hash, role) 
                     VALUES (?, ?, ?, ?, ?, 'customer')"
                );
               
                $stmtInsert->execute([$login, $name, $email, $phone, $hash]);
                $newUserId = $pdo->lastInsertId();

                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name ?: $login; // Если имени нет, показываем логин
                $_SESSION['user_role'] = 'customer';

                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error = 'Ошибка базы данных. Попробуйте позже.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
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
                <a class="menu__link" href="index.html">
                  <i class="bi bi-house-door-fill"></i>
                  <span>Главная</span>
                </a>
              </li>
              <li class="menu__item-favorites">
                <a class="menu__link" href="favorites.html">
                  <i class="bi bi-heart-fill"></i>
                  <span>Избранное</span>
                </a>
              </li>
              <li class="menu__item-cart">
                <a class="menu__link" href="cart.html">
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
            <div class="register-card bg-white p-4 rounded shadow">
        <h2 class="text-center mb-4">Регистрация</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <!-- Имя -->
            <div class="mb-3">
                <label class="form-label">Ваше имя</label>
                <input type="text" name="name" class="form-control" required placeholder="Иван">
            </div>
            <!-- Логин -->
            <div class="mb-3">
                <label class="form-label">Придумайте логин</label>
                <input type="text" name="login" class="form-control" required pattern="[a-zA-Z0-9_]{3,20}" placeholder="Только латиница и цифры">
            </div>
            <!-- Email -->
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required placeholder="example@mail.com">
            </div>
            <!-- Телефон -->
            <div class="mb-3">
                <label class="form-label">Телефон</label>
                <input type="tel" name="phone" class="form-control" required placeholder="+7 (999) 000-00-00">
            </div>
            <!-- Пароль -->
            <div class="mb-3">
                <label class="form-label">Пароль</label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="Минимум 6 символов">
            </div>
            <button type="submit" class="btn btn__gradieht w-100">Зарегистрироваться</button>
        </form>
        <div class="text-center mt-3">
            Уже есть аккаунт? <a href="login.php" class="text-decoration-none">Войти</a>
        </div>
    </div>
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

</body>
</html>
