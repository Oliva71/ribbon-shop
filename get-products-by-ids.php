<?php
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['product_ids'] ?? [];

$cleanIds = array_filter($ids, function($id) {
    return is_numeric($id) && (int)$id > 0;
});
$cleanIds = array_map('intval', $cleanIds);

if (empty($cleanIds)) {
    echo json_encode(['success' => false, 'message' => 'Нет ID товаров']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $sql = "SELECT p.*, pi.thumb_path FROM products p 
            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1 
            WHERE p.id IN ($placeholders) AND p.is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($cleanIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'products' => $products]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка БД']);
}
