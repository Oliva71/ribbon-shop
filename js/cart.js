// Функция защиты от ввода количества больше, чем есть на складе
function validateQuantityInput(productId) {
  const input = document.getElementById("qty-input-" + productId);
  if (!input) return;

  const maxStockStr = input.getAttribute("data-stock");
  const minQtyStr = input.getAttribute("min"); // используем min="" из HTML
  const maxStock = maxStockStr ? parseInt(maxStockStr, 10) : 0;
  const minQty = minQtyStr ? Math.max(1, parseInt(minQtyStr, 10)) : 1;

  let currentVal = parseInt(input.value, 10);

  if (isNaN(currentVal)) {
    input.value = minQty;
    return;
  }

  // Если ввели число больше лимита
  if (currentVal > maxStock) {
    input.value = maxStock;
    if (typeof showToast === "function") {
      showToast(
        "На складе только " + maxStock + " шт! Количество ограничено.",
        "#ffc107",
      );
    }
    console.warn("Попытка превысить остаток: установлено " + maxStock);
    return;
  }

  // Если ввели 0 или отрицательное
  if (currentVal < minQty) {
    input.value = minQty;
  }
}

// Навешиваем слушатель событий на все поля количества при загрузке страницы
document.addEventListener("DOMContentLoaded", () => {
  const inputs = document.querySelectorAll("input[data-product-id]");
  inputs.forEach((input) => {
    const productId = input.getAttribute("data-product-id");

    input.addEventListener("input", (e) => {
      validateQuantityInput(productId);
    });

    // Проверка сразу при загрузке
    validateQuantityInput(productId);
  });
});

function addToCart(arg1, arg2) {
    let button = null;
    let productId = null;

    // Вызов из JS-шаблона (Гости)
    // onclick="addToCart(this, id)" -> arg1 = кнопка, arg2 = id
    if (arg1 instanceof HTMLElement) {
        button = arg1;
        productId = arg2;
    } 
    // Вызов из PHP-шаблона (Авторизованные)
    // onclick="addToCart(id, event)" -> arg1 = id, arg2 = событие
    else if (arg2 && arg2.target) {
        productId = arg1;
        button = arg2.target.closest('button');
    }
    // Фоллбэк (если вдруг что-то пошло не так)
    else {
        const event = window.event;
        if (event && event.target) {
            button = event.target.closest('button');
        }
        productId = arg1;
    }

    // защита от ошибок
    if (!button || !productId) {
        console.error('Ошибка addToCart: Не удалось определить кнопку или ID товара.', { button, productId });
        return;
    }

    // безопасно проверяем блокировку
    if (button.dataset.isProcessing === 'true') {
        return;
    }
    button.dataset.isProcessing = 'true';

    const originalText = button.innerText;
    const isCatalogButton = button.id && button.id.startsWith('catalog-btn-');

    // Поиск поля количества 
    const input = document.getElementById('qty-input-' + productId);
    let qty = 1;

    if (input) {
        validateQuantityInput(productId);
        qty = parseInt(input.value, 10) || 1;
        if (qty < 1) qty = 1;
    }

    const stockEl = input || button;
    const maxStockStr = stockEl.getAttribute('data-stock');
    const maxStock = maxStockStr ? parseInt(maxStockStr, 10) : 0;

    if (qty > maxStock) {
        const canAdd = maxStock;
        if (typeof showToast === 'function') {
            if (canAdd <= 0) {
                showToast('Товар закончился.', '#dc3545');
            } else {
                showToast('На складе только ' + canAdd + ' шт.', '#ffc107');
            }
        }
        button.dataset.isProcessing = 'false';
        return;
    }

    // Блокируем кнопку визуально
    button.disabled = true;
    button.classList.remove('btn-success', 'btn-danger', 'btn-warning');
    button.innerText = 'Добавляем...';

    // Получаем CSRF токен из мета-тега и для гостей, и для авторизованных)
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    const token = metaTag ? metaTag.content : '';

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', qty);
    formData.append('csrf_token', token);

    fetch('add-to-cart.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.add('btn-success');

            if (typeof updateCartCount === 'function' && data.count !== undefined) {
                updateCartCount(data.count);
            }

            const hintEl = document.getElementById('cart-hint-' + productId);
            if (hintEl) {
                hintEl.textContent = 'В корзине: ' + data.product_qty + ' шт.';
            }

            if (typeof showToast === 'function') {
                showToast(data.message || 'Товар добавлен!', '#28a745');
            }

            setTimeout(() => {
                restoreButtonState(button, originalText, isCatalogButton);
            }, 1500);
        } else {
            restoreButtonState(button, originalText, isCatalogButton);

            const msg = data.message || 'Не удалось добавить товар';
            const color = data.available_stock !== undefined ? '#ffc107' : '#dc3545';

            if (typeof showToast === 'function') {
                showToast(msg, color);
            }
        }
    })
    .catch(error => {
        console.error('Ошибка запроса:', error);
        restoreButtonState(button, originalText, isCatalogButton);
        if (typeof showToast === 'function') {
            showToast('Ошибка соединения с сервером', '#dc3545');
        }
    })
    .finally(() => {
        button.dataset.isProcessing = 'false';
    });
}


function restoreButtonState(button, originalText, isCatalogButton) {
  button.disabled = false;
  button.classList.remove("btn-success");

  button.innerHTML = "";

  const icon = document.createElement("i");
  if (isCatalogButton) {
    icon.className = "bi bi-cart-plus-fill";
  } else {
    icon.className = "bi bi-cart-plus";
  }
  button.appendChild(icon);

  const textNode = document.createTextNode(originalText);
  button.appendChild(textNode);

  button.classList.remove(
    "btn-primary",
    "btn-gradient-action",
    "mt-2",
    "btn-sm",
    "btn-outline-secondary",
  );

  if (!button.classList.contains("btn")) {
    button.classList.add("btn");
  }

  if (isCatalogButton) {
    button.classList.add("btn-sm", "btn-outline-secondary");
  } else {
    button.classList.add("btn-primary", "btn-gradient-action", "mt-2");
  }
}

function updateCartCount(newCount) {
  const badge = document.querySelector(".count-cart-badge");
  if (!badge) {
    console.error(
      "Элемент .count-cart-badge не найден на странице! Проверьте HTML.",
    );
    return;
  }

  badge.textContent = newCount;

  badge.style.animation = "none";
  badge.offsetHeight; // trigger reflow
  badge.style.animation = "pulse 0.3s";
}
