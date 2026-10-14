<?php
require_once 'config/session_connect.php';
require_once 'config/db_connect.php';
require_once 'config/favorites_connect.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

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
//  Проверка метода (только POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешён']);
    exit;
}

//  Проверка CSRF-токена 
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'code'    => 'CSRF_INVALID',
        'message' => 'Ошибка безопасности: неверный или отсутствующий токен'
    ]);
    exit;
}

// Валидация product_id
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
if (!$productId || $productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID товара']);
    exit;
}

try {
    //  Проверяем, существует ли товар
    $stmt = $pdo->prepare('SELECT id, is_active FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Товар не найден']);
        exit;
    }

    if (!$product['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Товар недоступен']);
        exit;
    }

    //  добавляем в избранное 
    $stmtInsert = $pdo->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
    try {
        $stmtInsert->execute([$_SESSION['user_id'], $productId]);
        $isNew = true;
    } catch (PDOException $e) {
        $errorCode = $stmtInsert->errorInfo()[1];
        if ($errorCode === 1062) { // MySQL: Duplicate entry
            $isNew = false;
        } else {
            throw $e;
        }
    }

    // 3. Считаем общее количество избранных товаров 
    $stmtCount = $pdo->prepare('SELECT COUNT(*) AS count FROM favorites WHERE user_id = ?');
    $stmtCount->execute([$_SESSION['user_id']]);
    $countRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $totalCount = (int)$countRow['count'];

    echo json_encode([
        'success' => true,
        'message' => $isNew ? 'Товар добавлен в избранное' : 'Товар уже в избранном',
        'count'   => $totalCount,
        'is_new'  => $isNew,
    ]);

} catch (PDOException $e) {
    error_log('add_to_favorites.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера. Попробуйте позже.']);
    exit;
}
