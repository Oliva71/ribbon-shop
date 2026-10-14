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

// Проверяем, что заказ принадлежит этому пользователю
$stmtCheck = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$stmtCheck->execute([$orderId, $_SESSION['user_id']]);
if (!$stmtCheck->fetch()) {
    header('Location: catalog.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Заказ оформлен</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="styles/reset.css" />
    <link rel="stylesheet" href="styles/style.css" />
</head>
<body>
<div class="container py-5 text-center">
    <div class="mb-4">
        <i class="bi bi-check-circle-fill" style="font-size: 72px; color: #28a745;"></i>
    </div>
    <h1 class="mb-3">Спасибо! Ваш заказ принят</h1>
    <p class="lead">Номер заказа: <strong>№<?= $orderId ?></strong></p>
    <p class="text-muted mb-4">
        Менеджер свяжется с вами в ближайшее время для подтверждения деталей.
    </p>
    <a href="profile.php" class="btn btn-primary btn-gradient-action">Мои заказы</a>
    <a href="catalog.php" class="btn btn-outline-secondary">Продолжить покупки</a>
</div>
</body>
</html>
