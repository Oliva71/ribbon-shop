<?php
function getBreadcrumbs(string $pageType, array $params = []): array {
    $crumbs = [['title' => 'Главная', 'url' => 'index.php']];
// Каталог (нужен почти везде, кроме главной и личных разделов без привязки к каталогу)
     if (in_array($pageType, ['product', 'catalog'])) {
        $crumbs[] = ['title' => 'Каталог', 'url' => 'catalog.php'];
    }

    // Добавляем название категории, если есть ID
    if (!empty($params['category_id'])) {
          global $pdo; 
        if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->execute([$params['category_id']]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cat && !empty($cat['name'])) {
                $crumbs[] = [
                    'title' => htmlspecialchars($cat['name']),
                    'url' => 'catalog.php?cat=' . (int)$params['category_id']
                ];
            }
         } catch (Exception $e) {
              
         }
       }
    }

    //  только для карточки товара
    if (!empty($params['product_name'])) {
        $crumbs[] = ['title' => htmlspecialchars($params['product_name']), 'url' => null];
    }

     if (in_array($pageType, ['favorites', 'cart', 'orders'])) {
        // Удаляем "Каталог", если он был добавлен выше (логика выше добавляет его только для product/catalog)
        // Сбросим массив, если это личный раздел, и построим заново, чтобы не было лишнего "Каталога"
        $labels  = [
           'favorites' => 'Избранное',
            'cart' => 'Корзина',
            'orders' => 'Заказы'
        ];

         // Полностью перезаписываем массив для личных разделов
        $crumbs = [
            ['title' => 'Главная', 'url' => 'index.php'],
            ['title' => $labels[$pageType], 'url' => $pageType . '.php']
        ];

        // Если внутри личного раздела мы смотрим конкретный товар
         if (!empty($params['product_name'])) {
             $crumbs[] = ['title' => htmlspecialchars($params['product_name']), 'url' => null];
        }
        return $crumbs;
    }

    return $crumbs;
}
?>