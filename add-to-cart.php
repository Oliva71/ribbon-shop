<?php
require_once 'config/session_connect.php';
require_once 'config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$inputData = [];

// 1. Если запрос с JSON 
if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $inputData = $decoded;
    }
} 
// 2. Если обычный POST
else {
    $inputData = $_POST;
}

// Извлекаем данные из единого массива 
$productId = isset($inputData['product_id']) ? (int)$inputData['product_id'] : 0;
$qty = isset($inputData['quantity']) ? (int)$inputData['quantity'] : 1;
$qty = max(1, $qty);
$csrfToken = isset($inputData['csrf_token']) ? $inputData['csrf_token'] : null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешён']);
    exit;
}

if (!$productId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'code' => 'MISSING_PARAMS', 'message' => 'Не указан ID товара']);
    exit;
}

$isAuthorized = isset($_SESSION['user_id']);

if ($isAuthorized) {
    if (!$csrfToken || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code' => 'INVALID_CSRF',
            'message' => 'Неверный токен безопасности'
        ]);
        exit;
    }
} else {
    // Для гостей CSRF не обязателен
    if (!$csrfToken) {
        // Игнорируем отсутствие токена для гостей
    }
}

try {
    $stmt = $pdo->prepare('SELECT id, is_active, min_order_qty, stock_qty FROM products WHERE id = ?');
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

    $minQty = max(1, (int)$product['min_order_qty']);
    $stockQty = (int)$product['stock_qty'];
    $currentInCart = isset($_SESSION['cart'][$productId]) ? (int)$_SESSION['cart'][$productId] : 0;

    if (!isset($_SESSION['cart'][$productId]) && $qty < $minQty) {
        $qty = $minQty;
    }

    $totalRequested = $currentInCart + $qty;

    if ($totalRequested > $stockQty) {
        $canAdd = $stockQty - $currentInCart;
        $message = ($canAdd <= 0) 
            ? 'Товар закончился. В корзине уже все доступное количество.' 
            : "В наличии осталось только {$canAdd} шт. Больше добавить нельзя.";

        echo json_encode([
            'success'         => false,
            'message'         => $message,
            'available_stock' => $stockQty,
            'can_add_more'    => max(0, $canAdd),
            'current_in_cart' => $currentInCart
        ]);
        exit;
    }

    if (!isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = $qty;
    } else {
        $_SESSION['cart'][$productId] += $qty;
    }

    $totalCount = array_sum($_SESSION['cart']);

    echo json_encode([
        'success' => true,
        'message' => 'Товар добавлен в корзину',
        'count'   => $totalCount,
        'product_id'  => $productId,
        'product_qty' => $_SESSION['cart'][$productId],
        'min_qty'     => $minQty,
        'is_authorized' => $isAuthorized
    ]);

} catch (PDOException $e) {
    error_log('add_to_cart.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера. Попробуйте позже.']);
    exit;
}
