<?php
require_once __DIR__ . '/config/session_connect.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/favorites_connect.php';

header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'code' => 'NOT_AUTHORIZED',
        'message' => 'Требуется авторизация'
    ]);
    exit;
}

// Проверка метода
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Неверный метод']);
    exit;
}

//  Проверка и валидация входных данных
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$csrfToken = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

if (!$productId || !$csrfToken) {
    http_response_code(400);
    echo json_encode(['success' => false, 'code' => 'MISSING_PARAMS', 'message' => 'Отсутствуют обязательные параметры (product_id или csrf_token)']);
    exit;
}

// Проверка CSRF-токена
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'code' => 'INVALID_CSRF',
        'message' => 'Неверный токен безопасности'
    ]);
    exit;
}

try {
    // Удаляем из избранного
    $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$_SESSION['user_id'], $productId]);

    // Считаем новый счетчик
    $stmtCount = $pdo->prepare('SELECT COUNT(*) AS count FROM favorites WHERE user_id = ?');
    $stmtCount->execute([$_SESSION['user_id']]);
    $countRow = $stmtCount->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => (int)$countRow['count']
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage()); // Логирование ошибки вместо показа пользователю
    echo json_encode(['success' => false, 'message' => 'Ошибка БД']);
}
?>
