<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ហាងអនឡាញ - Telegram Mini App</title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      font-family: 'Khmer', Arial, sans-serif;
      background-color: #f0f2f5;
    }
    img {
      border: none !important;
    }
    .telegram-button {
      transition: background-color 0.3s ease;
    }
    .category-btn.active {
      background-color: #0088cc;
      color: white;
    }
  </style>
</head>
<body class="bg-gray-100 text-gray-800">
  <div class="container mx-auto max-w-lg p-4">
    <h1 class="text-2xl font-bold text-center text-teal-600 mb-6"></h1>
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
      <p class="text-lg font-semibold text-teal-600">ពិន្ទុបច្ចុប្បន្ន: <span id="points">0</span></p>
      <button id="redeemButton" class="telegram-button w-full mt-4 bg-yellow-400 text-gray-800 font-semibold py-2 rounded-lg hover:bg-yellow-500 transition duration-300">ប្តូររង្វាន់</button>
    </div>
    <div class="category-filter mb-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">ប្រភេទផលិតផល</h2>
      <div id="categories" class="flex flex-wrap gap-2"></div>
    </div>
    <div class="product-list mb-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">ផលិតផល</h2>
      <div id="products" class="grid gap-4"></div>
    </div>
    <div class="cart bg-white rounded-lg shadow-md p-4">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">កន្ត្រកទំនិញ</h2>
      <div id="cartItems" class="space-y-2"></div>
      <button id="checkoutButton" class="telegram-button w-full mt-4 bg-green-500 text-white font-semibold py-2 rounded-lg hover:bg-green-600 transition duration-300">បញ្ជាទិញ</button>
    </div>
  </div>

  <script>
    // Backend API base URL (ប្តូរទៅជា URL ពិតប្រាកដរបស់ Backend របស់អ្នក)
    const API_BASE_URL = 'https://app.vcc.asia/mini_app/backend';

    // ចាប់ផ្តើម Telegram Web App
    window.Telegram.WebApp.ready();
    window.Telegram.WebApp.expand();

    // ទទួលបានទិន្នន័យអ្នកប្រើ
    const user = window.Telegram.WebApp.initDataUnsafe.user;
    let userId = null;
    let userSaved = false; // Track if user data was successfully saved

    if (user) {
      document.querySelector('h1').textContent = `សូមស្វាគមន៍, ${user.first_name}!`;
      userId = user.id;
      saveUserData(user).then(() => {
        userSaved = true;
        if (userId) loadUserData();
      });
    } else {
      document.querySelector('h1').textContent = 'សូមស្វាគមន៍!';
      window.Telegram.WebApp.showAlert('មិនអាចទាញទិន្នន័យអ្នកប្រើបាន។ សូមបើកកម្មវិធីនេះនៅក្នុង Telegram។');
    }

    // ទិន្នន័យផលិតផល 100 ផលិតផល
    const products = [
      { id: 1, name: 'សៀវភៅប្រលោមលោក', price: 10, image: 'https://images.unsplash.com/photo-1544947950-fa07a98d45f6?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'សៀវភៅ' },
      { id: 2, name: 'សៀវភៅវិទ្យាសាស្ត្រ', price: 15, image: 'https://images.unsplash.com/photo-1589829085413-56de8f607c20?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'សៀវភៅ' },
      { id: 3, name: 'សៀវភៅប្រវត្តិសាស្ត្រ', price: 12, image: 'https://images.unsplash.com/photo-1512820790803-83ca734da794?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'សៀវភៅ' },
      { id: 20, name: 'សៀវភៅអប់រំ', price: 18, image: 'https://images.unsplash.com/photo-1516979187457-6376e7a9b567?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'សៀវភៅ' },
      { id: 21, name: 'កាសស្តាប់ត្រចៀក', price: 20, image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'អេឡិចត្រូនិក' },
      { id: 22, name: 'ទូរស័ព្ទឆ្លាតវៃ', price: 300, image: 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'អេឡិចត្រូនិក' },
      { id: 40, name: 'ឧបករណ៍បញ្ជា', price: 50, image: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'អេឡិចត្រូនិក' },
      { id: 41, name: 'អាវយឺត', price: 15, image: 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'សម្លៀកបំពាក់' },
      { id: 60, name: 'ស្បែកជើង', price: 35, image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'សម្លៀកបំពាក់' },
      { id: 61, name: 'ក្រែមលាបមុខ', price: 12, image: 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'គ្រឿងសម្អាង' },
      { id: 80, name: 'លីបស្ទីក', price: 10, image: 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'គ្រឿងសម្អាង' },
      { id: 81, name: 'ចានកែវ', price: 8, image: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'គ្រឿងប្រើប្រាស់ផ្ទះ' },
      { id: 100, name: 'ខ្នើយ', price: 20, image: 'https://images.unsplash.com/photo-1584100936595-7791b4577370?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80', category: 'គ្រឿងប្រើប្រាស់ផ្ទះ' },
    ];

    // បញ្ជីប្រភេទ
    const categories = ['ទាំងអស់', 'សៀវភៅ', 'អេឡិចត្រូនិក', 'សម្លៀកបំពាក់', 'គ្រឿងសម្អាង', 'គ្រឿងប្រើប្រាស់ផ្ទះ'];

    // ទិន្នន័យកន្ត្រក និងពិន្ទុ
    let cart = [];
    let points = 0;
    let selectedCategory = 'ទាំងអស់';

    // ផ្ញើទិន្នន័យអ្នកប្រើទៅ Backend
    async function saveUserData(user) {
      try {
        const userData = {
          telegram_id: user.id,
          first_name: user.first_name || '',
          last_name: user.last_name || '',
          username: user.username || ''
        };
        console.log('Sending user data:', userData);
        const response = await fetch(`${API_BASE_URL}/save_user.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(userData)
        });
        const data = await response.json();
        console.log('Response from save_user.php (Status: ' + response.status + '):', data);
        if (!data.success) {
          console.error('Failed to save user data:', data.error);
          window.Telegram.WebApp.showAlert('កំហុសក្នុងការរក្សាទុកទិន្នន័យអ្នកប្រើ (Status: ' + response.status + '): ' + (data.error || 'សូមព្យាយាមម្តងទៀត។'));
        }
      } catch (error) {
        console.error('Error saving user data:', error);
        window.Telegram.WebApp.showAlert('កំហុសក្នុងការរក្សាទុកទិន្នន័យអ្នកប្រើ (Network Error): ' + error.message);
      }
    }

    // ទាញទិន្នន័យពិន្ទុ និងកន្ត្រកពី Backend
    async function loadUserData() {
      if (!userId) return;
      try {
        const response = await fetch(`${API_BASE_URL}/get_user_data.php?telegram_id=${userId}`);
        const data = await response.json();
        if (data.success) {
          points = data.points || 0;
          cart = data.cart
            ? data.cart.map(productId => products.find(p => p.id === parseInt(productId))).filter(item => item !== undefined)
            : [];
          document.getElementById('points').textContent = points;
          displayCart();
        } else {
          console.error('Failed to load user data:', data.error);
        }
      } catch (error) {
        console.error('Error loading user data:', error);
      }
    }

    // បង្ហាញប្រភេទ
    function displayCategories() {
      const categoryList = document.getElementById('categories');
      categoryList.innerHTML = '';
      categories.forEach(category => {
        const button = document.createElement('button');
        button.className = `telegram-button px-4 py-2 rounded-lg text-sm font-semibold ${selectedCategory === category ? 'category-btn active' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'}`;
        button.textContent = category;
        button.addEventListener('click', () => {
          selectedCategory = category;
          displayCategories();
          displayProducts();
        });
        categoryList.appendChild(button);
      });
    }

    // បង្ហាញផលិតផល
    function displayProducts() {
      const productList = document.getElementById('products');
      productList.innerHTML = '';
      const filteredProducts = selectedCategory === 'ទាំងអស់' ? products : products.filter(p => p.category === selectedCategory);
      filteredProducts.forEach(product => {
        const productDiv = document.createElement('div');
        productDiv.className = 'bg-white rounded-lg shadow-md p-4 flex items-center space-x-4';
        productDiv.innerHTML = `
          <img src="${product.image}" alt="${product.name}" class="w-24 h-24 object-cover rounded-md">
          <div class="flex-1">
            <h3 class="text-lg font-semibold text-gray-800">${product.name}</h3>
            <p class="text-gray-600">តម្លៃ: $${product.price}</p>
            <p class="text-sm text-gray-500">${product.category}</p>
          </div>
          <button onclick="addToCart(${product.id})" class="telegram-button bg-teal-500 text-white px-4 py-2 rounded-lg hover:bg-teal-600 transition duration-300">បន្ថែម</button>
        `;
        productList.appendChild(productDiv);
      });
    }

    // បន្ថែមទៅកន្ត្រក
    async function addToCart(productId) {
      const product = products.find(p => p.id === productId);
      if (!product) return;
      cart.push(product);
      displayCart();
      if (!userId || !userSaved) {
        window.Telegram.WebApp.showAlert('មិនអាចរក្សាទុកកន្ត្រកបាន៖ សូមព្យាយាមម្តងទៀតបន្ទាប់ពីទិន្នន័យអ្នកប្រើត្រូវបានរក្សាទុក។');
        return;
      }
      try {
        const response = await fetch(`${API_BASE_URL}/save_cart.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ telegram_id: userId, cart })
        });
        const data = await response.json();
        if (!data.success) {
          console.error('Failed to save cart:', data.error);
        }
      } catch (error) {
        console.error('Error saving cart:', error);
      }
    }

    // បង្ហាញកន្ត្រក
    function displayCart() {
      const cartItems = document.getElementById('cartItems');
      cartItems.innerHTML = '';
      cart.forEach((item, index) => {
        if (!item) return;
        const cartItem = document.createElement('div');
        cartItem.className = 'flex justify-between items-center p-2 bg-gray-50 rounded-md';
        cartItem.innerHTML = `
          <div class="flex items-center space-x-2">
            <img src="${item.image}" alt="${item.name}" class="w-12 h-12 object-cover rounded-md">
            <span>${item.name} - $${item.price}</span>
          </div>
          <button onclick="removeFromCart(${index})" class="telegram-button bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition duration-300">លុប</button>
        `;
        cartItems.appendChild(cartItem);
      });
    }

    // លុបចេញពីកន្ត្រក
    async function removeFromCart(index) {
      cart.splice(index, 1);
      displayCart();
      if (!userId || !userSaved) {
        window.Telegram.WebApp.showAlert('មិនអាចរក្សាទុកកន្ត្រកបាន៖ សូមព្យាយាមម្តងទំបន្ទាប់ពីទិន្នន័យអ្នកប្រើត្រូវបានរក្សាទុក។');
        return;
      }
      try {
        const response = await fetch(`${API_BASE_URL}/save_cart.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ telegram_id: userId, cart })
        });
        const data = await response.json();
        if (!data.success) {
          console.error('Failed to save cart:', data.error);
        }
      } catch (error) {
        console.error('Error saving cart:', error);
      }
    }

    // បញ្ជាទិញ
    document.getElementById('checkoutButton').addEventListener('click', async () => {
      if (cart.length === 0) {
        window.Telegram.WebApp.showAlert('កន្ត្រកទទេ! សូមបន្ថែមផលិតផល។');
        return;
      }

      const total = cart.reduce((sum, item) => sum + item.price, 0);
      points += total;
      document.getElementById('points').textContent = points;

      if (!userId) {
        window.Telegram.WebApp.showAlert(`អ្នកបានបញ្ជាទិញជោគជ័យ! ទទួលបាន ${total} ពិន្ទុ។`);
        cart = [];
        displayCart();
        return;
      }

      if (!userSaved) {
        window.Telegram.WebApp.showAlert('មិនអាចបញ្ជាទិញបាន៖ សូមព្យាយាមម្តងទៀតបន្ទាប់ពីទិន្នន័យអ្នកប្រើត្រូវបានរក្សាទុក។');
        return;
      }

      // Log the data being sent to the backend
      const orderData = { telegram_id: userId, cart, points, total };
      console.log('Sending order data:', orderData);

      try {
        const response = await fetch(`${API_BASE_URL}/save_order.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(orderData)
        });

        const data = await response.json();
        console.log('Response from save_order.php (Status: ' + response.status + '):', data);

        if (data.success) {
          window.Telegram.WebApp.showAlert(`អ្នកបានបញ្ជាទិញជោគជ័យ! ទទួលបាន ${total} ពិន្ទុ។`);
          cart = [];
          displayCart();
        } else {
          window.Telegram.WebApp.showAlert('កំហុសក្នុងការបញ្ជាទិញ (Status: ' + response.status + '): ' + (data.error || 'សូមព្យាយាមម្តងទៀត។'));
        }
      } catch (error) {
        console.error('Error during checkout:', error);
        window.Telegram.WebApp.showAlert('កំហុសក្នុងការបញ្ជាទិញ (Network Error): ' + error.message);
      }
    });

    // ប្តូររង្វាន់
    document.getElementById('redeemButton').addEventListener('click', async () => {
      if (points < 50) {
        window.Telegram.WebApp.showAlert('អ្នកត្រូវការយ៉ាងហោចណាស់ 50 ពិន្ទុដើម្បីប្តូររង្វាន់។');
        return;
      }

      window.Telegram.WebApp.showAlert('អ្នកនឹងប្តូរ 50 ពិន្ទុសម្រាប់ការបញ្ជុះតម្លៃ $5។');
      points -= 50;
      document.getElementById('points').textContent = points;

      if (!userId || !userSaved) {
        window.Telegram.WebApp.showAlert('មិនអាចប្តូររង្វាន់បាន៖ សូមព្យាយាមម្តងទៀតបន្ទាប់ពីទិន្នន័យអ្នកប្រើត្រូវបានរក្សាទុក។');
        return;
      }

      try {
        const response = await fetch(`${API_BASE_URL}/update_points.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ telegram_id: userId, points })
        });
        const data = await response.json();
        if (data.success) {
          window.Telegram.WebApp.showAlert('អ្នកបានប្តូរការបញ្ជុះតម្លៃ $5 ដោយជោគជ័យ!');
        } else {
          window.Telegram.WebApp.showAlert('កំហុសក្នុងការប្តូររង្វាន់ (Status: ' + response.status + '): ' + (data.error || 'សូមព្យាយាមម្តងទៀត។'));
        }
      } catch (error) {
        console.error('Error updating points:', error);
        window.Telegram.WebApp.showAlert('កំហុសក្នុងការប្តូររង្វាន់ (Network Error): ' + error.message);
      }
    });

    // ចាប់ផ្តើមបង្ហាញប្រភេទ និងផលិតផល
    displayCategories();
    displayProducts();
  </script>
</body>
</html>