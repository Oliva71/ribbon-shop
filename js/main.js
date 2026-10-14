document.addEventListener('DOMContentLoaded', function() {
const menuBtn = document.getElementById('menuBtn');
const menu = document.getElementById('menuList');

// if (menuBtn && menu) {
menuBtn.addEventListener("click", function() {
  menuBtn.classList.toggle("active");
  menu.classList.toggle("active");

  document.body.style.overflow = menu.classList.contains('active') ? 'hidden' : '';
});
//}

const navLinks = document.querySelectorAll('.menu__list a');
navLinks.forEach(link => {
  link.addEventListener('click', function() {
    menuBtn.classList.remove('active');
    menu.classList.remove('active');
    document.body.style.overflow = '';
  });
});

document.addEventListener('click', function(e) {
  if (!menu.contains(e.targrt) && !menuBtn.contains(e.target) && menu.classList.contains('active')) {
    menuBtn.classList.remove('active');
    menu.classList.remove('active');
    document.body.style.overflow = '';
  }
});
});

document.addEventListener('DOMContentLoaded', () => {
  const swiperContainer = document.querySelector('.swiper');
  if (swiperContainer) {
    const swiper = new Swiper('.swiper', {
      slidesPerView: 1,
      spaceBetween: 20,
      loop: true,
      breakpoints: {
        640: { slidesPerView: 1, spaceBetween: 20 },
        1550: { slidesPerView: 2, spaceBetween: 20 },
        1730: { slidesPerView: 3, spaceBetween: 20 },
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
    });
  }
});


function showToast(message, color = '#28a745') {
    const existingToast = document.getElementById('custom-toast');
    if (existingToast) existingToast.remove();

    const toast = document.createElement('div');
    toast.id = 'custom-toast';
    toast.style.cssText = `
        position: fixed;
        top: 250px;
        right: 20px;
        background-color: ${color};
        color: white;
        padding: 15px 25px;
        border-radius: 5px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        font-family: Arial, sans-serif;
        font-size: 14px;
        animation: slideIn 0.3s ease-out;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}