function getFavorites() {
  try {
    const str = localStorage.getItem('favorites');
    return str ? JSON.parse(str) : [];
  } catch (e) {
    return [];
  }
}

function saveFavorites(arr) {
  localStorage.setItem('favorites', JSON.stringify(arr));
}

function isGuestUser() {
  return document.body.getAttribute('data-is-guest') === '1';
}

function updateFavoriteUI(btnElement, isAdded) {
  const icon = btnElement.querySelector('i');
  if (!icon) return;

  if (isAdded) {
    icon.classList.remove('text-muted');
    icon.classList.add('text-danger');
    btnElement.title = 'Удалить из избранного';
  } else {
    icon.classList.remove('text-danger');
    icon.classList.add('text-muted');
    btnElement.title = 'Добавить в избранное';
  }
}

function updateFavoritesCount(count) {
  const badge = document.querySelector('.count-favorites-badge');
  if (!badge) return;

  let displayCount;
  if (count === -1 || isGuestUser()) {
    displayCount = getFavorites().length;
  } else {
    displayCount = count;
  }

  badge.textContent = displayCount;

  if (displayCount > 0) {
    badge.style.display = 'flex';
    badge.style.animation = 'none';
    badge.offsetHeight;
    badge.style.animation = 'pulse 0.3s';
  } else {
    badge.style.display = 'none';
  }
}

function toggleFavorite(btnElement, productId) {
  const icon = btnElement.querySelector('i');
  const isCurrentlyFav = icon.classList.contains('text-danger');
  const guest = isGuestUser();

  btnElement.disabled = true;

  // гость: всё через localStorage, без запроса на сервер 
  if (guest) {
    let favorites = getFavorites();
    const index = favorites.indexOf(productId);

    if (isCurrentlyFav) {
      // Удаляем
      favorites.splice(index, 1);
      saveFavorites(favorites);
      updateFavoriteUI(btnElement, false);
      updateFavoritesCount(-1);
      showToast('Товар удалён из избранного', '#6c757d');

      if (window.location.href.includes('favorites')) {
        const card = btnElement.closest('.col-6, .col-sm-4, .col-md-3');
        if (card) {
          card.style.opacity = '0';
          setTimeout(() => card.remove(), 300);
        }
      }
    } else {
      // Добавляем
      favorites.push(productId);
      saveFavorites(favorites);
      updateFavoriteUI(btnElement, true);
      updateFavoritesCount(-1);
      showToast('Товар добавлен в избранное', '#28a745');
    }

    btnElement.disabled = false;
    return;
  }

  // авторизованный: через сервер 
  const csrfToken = btnElement.dataset.csrfToken;
  const actionFile = isCurrentlyFav ? 'remove-from-favorites.php' : 'add-to-favorites.php';

  const formData = new FormData();
  formData.append('product_id', productId);
  if (csrfToken) {
    formData.append('csrf_token', csrfToken);
  }

  fetch(actionFile, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      updateFavoriteUI(btnElement, !isCurrentlyFav);
      updateFavoritesCount(data.count);

      if (isCurrentlyFav) {
        showToast('Товар удалён из избранного', '#6c757d');
        if (window.location.href.includes('favorites')) {
          const card = btnElement.closest('.col-6, .col-sm-4, .col-md-3');
          if (card) {
            card.style.opacity = '0';
            setTimeout(() => card.remove(), 300);
          }
        }
      } else {
        showToast(data.message || 'Товар добавлен в избранное', '#28a745');
      }
    } else {
      showToast(data.message || 'Ошибка действия', '#dc3545');
    }
  })
  .catch(error => {
    console.error(error);
    showToast('Произошла ошибка соединения', '#dc3545');
  })
  .finally(() => {
    btnElement.disabled = false;
  });
}

// При загрузке страницы — отмечаем избранное для гостей из localStorage
document.addEventListener('DOMContentLoaded', () => {
  if (!isGuestUser()) return;

  const favorites = getFavorites();
  if (favorites.length === 0) {
    updateFavoritesCount(-1);
    return;
  }

  document.querySelectorAll('[data-product-id]').forEach(btn => {
    const id = parseInt(btn.dataset.productId, 10);
    if (favorites.includes(id)) {
      const icon = btn.querySelector('i');
      if (icon) {
        icon.classList.remove('text-muted');
        icon.classList.add('text-danger');
        btn.title = 'Удалить из избранного';
      }
    }
  });

  updateFavoritesCount(-1);
});

function removeCardFromFavorites(btnElement) {
  
  const cardContainer = btnElement.closest('.col-6, .col-sm-4, .col-md-3, .col-lg-3');
  
  if (!cardContainer) {
    console.warn('Не удалось найти родительскую колонку для удаления');
    return;
  }

  // Сразу применяем стили для анимации
  cardContainer.style.opacity = '0';
  cardContainer.style.transform = 'scale(0.95)';

  //  Ждём 300мс и удаляем из DOM
  setTimeout(() => {
    cardContainer.remove();
    checkEmptyFavorites();
  }, 300);
}


