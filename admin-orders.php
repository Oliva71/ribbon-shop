<?php
require_once 'config/session_connect.php';
require_once 'uploads-original-thumbs-config.php';
require_once 'config/db_connect.php';    
require_once 'auth.php';

// Проверка роли админа
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Получаем все заказы
$stmt = $pdo->query("
    SELECT 
        o.*, 
        SUM(oi.total_price) AS total_amount,
        GROUP_CONCAT(CONCAT(p.name, ' (арт. ', p.sku, ')',
        IFNULL(CONCAT(' | Текст: ', oi.custom_name), ''),
        IFNULL(CONCAT(' | Лента: ', oi.custom_color_ribbon), ''), 
        IFNULL(CONCAT(' | Печать: ', oi.custom_color_print), ''), 
         ' (', oi.quantity, ' шт)'
         )
         
        SEPARATOR ', ') AS products_list
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    GROUP BY o.id
    ORDER BY o.id DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка - заказы</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto:wght@100..900&display=swap" rel="stylesheet">
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/reset.css">
    <link rel="stylesheet" href="styles/style.css">

<body class="bg-light">

 <header class="header">

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
              
              <li class="menu__item-favorites">
                <a class="menu__link" href="admin-add.php">
                  <!-- <i class="bi bi-heart-fill"></i> -->
                  <span>Добавить товар</span>
                </a>
              </li>
             
              <li class="menu__item-cart">
                <a class="menu__link" href="admin-list.php">
                  <!-- <i class="bi bi-cart-fill"></i> -->
                  <span>Список заказов</span>
                </a>
              </li>
              </ul>

              <ul class="menu__icons">
               <li class="menu__item-cart">
                <a class="menu__link" href="catalog.php">
                  <i class="bi bi-folder-fill"></i>
                  <!-- <span>Каталог</span> -->
                </a>
              </li>
               <li class="menu__item-home">
                <a class="menu__link" href="index.php">
                  <i class="bi bi-house-door-fill"></i>
                  <!-- <span>Главная</span> -->
                </a>
              </li>
              <li class="menu__item-login">
                <a class="menu__link" href="logout.php">
                  <i class="bi bi-door-open-fill"></i>
                  <!-- <span>Выход</span> -->
                </a>
              </li>
              </ul>
          </nav>        
        </div>
      </div>
    </header>

    <div class="section-box_top">

<div class="container py-4">
    <h3>Админка - Список заказов</h3>
    
    <?php if (empty($orders)): ?>
        <div class="alert alert-info">Пока нет заказов.</div>
    <?php else: ?>
        <table class="table table-striped table-bordered bg-white">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Дата</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Состав</th>
                    <th>Статус</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['id']) ?></td>
                    <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($o['created_at']))) ?></td>
                     <td><?= htmlspecialchars($o['customer_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($o['customer_phone'] ?? '—') ?>
                    <td><?= htmlspecialchars($o['products_list'] ?? 'Нет товаров') ?></td>
                    <td>
                        <?php 
                        $class = $o['status'] === 'processing' ? 'bg-info text-dark' : 'bg-warning text-dark';
                        if (in_array($o['status'], ['completed', 'shipped'])) $class = 'bg-success text-white';
                        if ($o['status'] === 'cancelled') $class = 'bg-danger text-white';
                        ?>
                        <span class="badge <?= $class ?>"><?= htmlspecialchars($o['status']) ?></span>
                    </td>
                    <td><?= number_format($o['total_amount'] ?? 0, 2, ',', ' ') ?> ₽</td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="admin-list.php" class="btn btn-secondary">← Вернуться к товарам</a>
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
              пн - пт: 9:00 - 17:00
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
</body>
</html>
