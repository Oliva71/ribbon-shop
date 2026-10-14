const catalog = document.getElementById('catalog');
fetch('data/products.json')
.then(response => response.json())
.then(data => {
  data.forEach(product => {

console.log(product.category);





    // const productDiv = document.createElement('div');
    // productDiv.classList.add('product');
    // productDiv.innerHTML = `
    // <h3>${product.prod_name}</h3>
    // <p><strong>Цвет:</strong>${product.tapes_color}</p>
    // <p><strong>Печать:</strong>${product.print_color}</p>
    // `;
    // catalog.appendChild(productDiv);
  });
  })
  .catch(error => {
    console.error("Ошибка загрузки Json", error);
});