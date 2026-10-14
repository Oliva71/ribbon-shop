<?php
/**
 * @param PDO $pdo Объект подключения к БД
 * @param int|null $userId ID пользователя или null
 * @return array
 */
function getFavoritesData($pdo, $userId) {
    if (!$userId) {
        return ['count' => 0, 'ids' => []];
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return ['count' => $count, 'ids' => array_flip($ids)];
}
