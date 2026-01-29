<?php
// =========================================================================
// === PART 1: PHP BACKEND LOGIC                                         ===
// =========================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- ODOO CONNECTION DETAILS (EDIT THESE) ---
define('ODOO_URL', 'https://www.vvc.asia');
define('ODOO_DB', 'zender');
define('ODOO_USER', 'samannoeun@gmail.com');
define('ODOO_PASS', '59cb5114faa709a3977586dca546b838b39110cc');
// --- END OF ODOO DETAILS ---

// --- ODOO API HELPER FUNCTIONS ---

function callOdoo($service, $method, $args) {
    $url = ODOO_URL . '/jsonrpc';
    $payload = json_encode([
        'jsonrpc' => '2.0', 'method' => 'call', 'params' => ['service' => $service, 'method' => $method, 'args' => $args], 'id' => rand()
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) throw new Exception("cURL Error: " . $error);
    $result = json_decode($response, true);
    if (isset($result['error'])) {
        throw new Exception("Odoo API Error: " . ($result['error']['data']['message'] ?? json_encode($result['error'])));
    }
    return $result['result'];
}

function executeOdooMethod($model, $method, $params = [], $options = []) {
    $uid = callOdoo('common', 'authenticate', [ODOO_DB, ODOO_USER, ODOO_PASS, []]);
    if (!$uid) throw new Exception("Odoo authentication failed.");
    $args = [ODOO_DB, $uid, ODOO_PASS, $model, $method, $params];
    if (!empty($options)) $args[] = $options;
    return callOdoo('object', 'execute_kw', $args);
}

function findOrCreatePartner($user) {
    if (empty($user['id'])) return null;
    $domain = [['x_telegram_id', '=', (string)$user['id']]];
    $partner_ids = executeOdooMethod('res.partner', 'search', [$domain], ['limit' => 1]);
    if (!empty($partner_ids)) return $partner_ids[0];
    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $partner_data = [[
        'name' => $fullName, 'x_telegram_id' => (string)$user['id'], 'email' => $user['id'] . '@telegram.user', 'x_loyalty_points' => 0
    ]];
    return executeOdooMethod('res.partner', 'create', $partner_data);
}

// --- API ROUTER: Handles all AJAX/Fetch requests from the frontend ---
if (isset($_GET['action'])) {
    header("Content-Type: application/json; charset=UTF-8");
    $action = $_GET['action'];
    $post_data = json_decode(file_get_contents('php://input'), true);
    $response_data = [];
    try {
        switch ($action) {
            case 'get_products':
                $domain = [['sale_ok', '=', true], ['is_published', '=', true]];
                $fields = ['id', 'name', 'list_price', 'public_categ_ids', 'qty_available'];
                $products = executeOdooMethod('product.template', 'search_read', [$domain], ['fields' => $fields, 'context' => ['lang' => 'km_KH']]);
                foreach ($products as $product) {
                    $response_data[] = [
                        'id' => $product['id'], 'name' => $product['name'], 'price' => $product['list_price'],
                        'image' => ODOO_URL . '/web/image/product.template/' . $product['id'] . '/image_1920',
                        // --- FIX IS APPLIED HERE ---
                        // Check if the category array and its second element (the name) exist before accessing it.
                        'category' => (!empty($product['public_categ_ids']) && isset($product['public_categ_ids'][1])) ? $product['public_categ_ids'][1] : 'Uncategorized',
                        'discount' => 0, 
                        'stock' => (int)$product['qty_available'],
                        'label' => ((int)$product['qty_available'] <= 0) ? 'sold_out' : 'new'
                    ];
                }
                break;
            case 'get_points':
                $user_data = $post_data['user_data'] ?? [];
                if (empty($user_data['id'])) throw new Exception("User ID not provided for getting points.");
                $partner_id = findOrCreatePartner($user_data);
                $points_data = executeOdooMethod('res.partner', 'read', [[$partner_id]], ['fields' => ['x_loyalty_points']]);
                $response_data = ['points' => $points_data[0]['x_loyalty_points'] ?? 0];
                break;
            case 'get_orders':
                $user_data = $post_data['user_data'] ?? [];
                 if (empty($user_data['id'])) throw new Exception("User ID not provided for getting orders.");
                $partner_id = findOrCreatePartner($user_data);
                $domain = [['partner_id', '=', $partner_id]];
                $fields = ['id', 'name', 'date_order', 'amount_total', 'state'];
                $orders = executeOdooMethod('sale.order', 'search_read', [$domain], ['fields' => $fields, 'order' => 'date_order desc']);
                foreach ($orders as $order) {
                    $response_data[] = [
                        'id' => $order['id'], 'created_at' => (new DateTime($order['date_order']))->format('d/m/Y H:i:s'),
                        'total' => $order['amount_total'], 'points_earned' => floor($order['amount_total']),
                        'status' => $order['state']
                    ];
                }
                break;
            case 'checkout':
                $user_data = $post_data['user_data'] ?? [];
                $cart_items = $post_data['cart_items'] ?? [];
                $shipping_info = $post_data['shipping_info'] ?? [];
                if (empty($cart_items) || empty($user_data) || empty($user_data['id'])) throw new Exception('User, cart, or shipping data is missing.');
                $partner_id = findOrCreatePartner($user_data);
                if (!$partner_id) throw new Exception('Could not find or create customer.');

                $order_lines = [];
                foreach ($cart_items as $item) {
                    $variant_ids = executeOdooMethod('product.product', 'search', [[['product_tmpl_id', '=', (int)$item['product_id']]]], ['limit' => 1]);
                    if (empty($variant_ids)) continue;
                    $order_lines[] = [0, 0, ['product_id' => $variant_ids[0], 'product_uom_qty' => (int)$item['quantity']]];
                }
                if(empty($order_lines)) throw new Exception('No valid products to create an order.');

                $order_data = [[
                    'partner_id' => $partner_id, 'order_line' => $order_lines,
                    'x_payment_method' => $shipping_info['payment_method'] ?? 'N/A',
                    'x_shipping_phone' => $shipping_info['phone'] ?? 'N/A',
                    'note' => "Address: " . ($shipping_info['address'] ?? 'N/A'),
                    'x_map_link' => $shipping_info['map_link'] ?? 'N/A',
                ]];
                $order_id = executeOdooMethod('sale.order', 'create', $order_data);
                executeOdooMethod('sale.order', 'action_confirm', [[$order_id]]);
                $new_order_data = executeOdooMethod('sale.order', 'read', [[$order_id]], ['fields' => ['amount_total']]);
                $points_earned = floor($new_order_data[0]['amount_total']);
                executeOdooMethod('res.partner', 'write', [[$partner_id], ['x_loyalty_points' => ['+', $points_earned]]]);
                $response_data = ['success' => true, 'order_id' => $order_id, 'points_earned' => $points_earned];
                break;
            default:
                throw new Exception('Invalid action specified.');
                break;
        }
        echo json_encode($response_data);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// --- PRE-LOADING DATA for initial page load ---
$initial_products = [];
$error_message = '';
try {
    $domain = [['sale_ok', '=', true], ['is_published', '=', true]];
    $fields = ['id', 'name', 'list_price', 'public_categ_ids', 'qty_available'];
    $products_raw = executeOdooMethod('product.template', 'search_read', [$domain], ['fields' => $fields, 'context' => ['lang' => 'km_KH']]);
    foreach ($products_raw as $product) {
        $initial_products[] = [
            'id' => $product['id'], 'name' => $product['name'], 'price' => $product['list_price'],
            'image' => ODOO_URL . '/web/image/product.template/' . $product['id'] . '/image_1920',
             // --- FIX IS APPLIED HERE AS WELL ---
            'category' => (!empty($product['public_categ_ids']) && isset($product['public_categ_ids'][1])) ? $product['public_categ_ids'][1] : 'Uncategorized',
            'discount' => 0, 
            'stock' => (int)$product['qty_available'],
            'label' => ((int)$product['qty_available'] <= 0) ? 'sold_out' : 'new'
        ];
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
$initial_products_json = json_encode($initial_products);
?>
<!DOCTYPE html>
<html lang="km">

<!-- ========================================================================= -->
<!-- === PART 2: HTML & JAVASCRIPT FRONTEND (NO CHANGES NEEDED)            === -->
<!-- ========================================================================= -->

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <meta name="robots" content="noindex, nofollow" />
    <title>ហាងអនឡាញ - Telegram Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css"/>
    <style>
        body { font-family: 'Noto Sans Khmer', sans-serif; }
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background-color: #fefefe; margin: auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 10px; animation: zoomIn 0.3s; }
        @keyframes zoomIn { from { transform: scale(0.7); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .sidebar { position: fixed; top: 0; right: -100%; width: 90%; max-width: 400px; height: 100%; background: white; z-index: 2000; transition: right 0.3s ease-in-out; display: flex; flex-direction: column; box-shadow: -5px 0 15px rgba(0,0,0,0.1); }
        .sidebar.open { right: 0; }
        .sidebar-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #0088cc; color: white; }
        .sidebar-tabs { display: flex; border-bottom: 1px solid #ddd; }
        .sidebar-tabs button { flex: 1; padding: 1rem; border: none; background: #f9f9f9; cursor: pointer; font-weight: 600; color: #555; }
        .sidebar-tabs button.active { background: white; border-bottom: 3px solid #0088cc; color: #0088cc; }
        .sidebar-content { padding: 1rem; overflow-y: auto; flex-grow: 1; }
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0.5rem; border-bottom: 1px solid #eee; }
        .sidebar-toggle { position: fixed; bottom: 80px; right: 20px; width: 50px; height: 50px; background: #0088cc; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 1000; cursor: pointer; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .cart-badge { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; display: flex; align-items: center; justify-content: center; }
        .product-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .product-img { width: 100%; height: 140px; object-fit: cover; }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto max-w-md p-4">
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Connection Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="profile-section flex items-center mb-6 bg-gradient-to-r from-cyan-100 to-blue-50 shadow-lg rounded-xl p-4 space-x-4">
            <img id="profilePicture" src="https://via.placeholder.com/48" alt="Profile Picture" class="profile-picture rounded-full w-12 h-12 shadow-md border-2 border-yellow-300"/>
            <div class="profile-info">
                <h1 class="text-xl font-extrabold text-teal-700 drop-shadow-sm">Loading...</h1>
                <p class="text-lg font-semibold text-blue-700 flex items-center gap-2">
                    <i class="fas fa-star text-yellow-400"></i>ពិន្ទុ: <span id="points" class="font-bold text-green-600">0</span>
                </p>
            </div>
        </div>

        <div class="swiper-container hideable rounded-lg overflow-hidden mb-6 shadow-lg">
            <div class="swiper-wrapper">
                <div class="swiper-slide"><img src="https://www.longbeachsyrup.com/images/_head1.jpg?crc=3804342330" alt="Slide 1" class="w-full h-auto"/></div>
                <div class="swiper-slide"><img src="https://www.longbeachsyrup.com/images/_head2.jpg?crc=4075138205" alt="Slide 2" class="w-full h-auto"/></div>
            </div>
            <div class="swiper-pagination"></div>
        </div>

        <div class="product-list mb-20">
            <div id="productGroups" class="product-groups grid grid-cols-2 gap-4">
            </div>
        </div>
    </div>
    
    <div id="sidebarToggle" class="sidebar-toggle">
        <i class="fa-solid fa-cart-shopping"></i>
        <span id="cartBadge" class="cart-badge hidden">0</span>
    </div>

    <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h2 class="text-xl font-bold">ទំនិញ និង ប្រវត្តិ</h2>
            <button id="sidebarClose" class="text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="sidebar-tabs">
            <button id="cartTab" class="active"><i class="fas fa-shopping-cart"></i> កន្ត្រក</button>
            <button id="historyTab"><i class="fas fa-history"></i> ប្រវត្តិ</button>
        </div>
        <div class="sidebar-content">
            <div id="cartContent"></div>
            <div id="historyContent" class="hidden"></div>
        </div>
    </div>
    
    <div class="footer fixed bottom-0 left-0 right-0 bg-white shadow-lg flex justify-around items-center p-2 z-50 border-t">
        <button id="checkoutButton" class="flex-1 bg-green-500 text-white font-semibold py-3 rounded-lg shadow-md hover:bg-green-600 mx-2">
            <i class="fas fa-check"></i> បញ្ជាទិញ
        </button>
    </div>

    <div id="checkoutModal" class="modal">
        <div class="modal-content">
            <h2 class="text-center text-2xl font-bold text-gray-800 mb-4">បញ្ជាក់ការបញ្ជាទិញ</h2>
            <div id="modalCartItems" class="space-y-2 mb-4 p-2 bg-gray-50 rounded-lg max-h-48 overflow-y-auto"></div>
            <p class="text-lg font-semibold mb-2">ទឹកប្រាក់សរុប: <span id="totalAmount" class="text-green-600">$0.00</span></p>
            <p class="text-lg font-semibold mb-4">ពិន្ទុនឹងទទួលបាន: <span id="estimatedPoints" class="text-blue-500">0</span></p>
            <h3 class="text-lg font-semibold mb-2">ព័ត៌មានដឹកជញ្ជូន:</h3>
            <div class="flex flex-col gap-3">
                <input type="text" id="shippingName" placeholder="ឈ្មោះ" class="w-full p-3 border rounded-md"/>
                <input type="text" id="shippingAddress" placeholder="អាសយដ្ឋាន" class="w-full p-3 border rounded-md"/>
                <input type="text" id="shippingPhone" placeholder="លេខទូរស័ព្ទ" class="w-full p-3 border rounded-md"/>
            </div>
            <div class="flex justify-end space-x-2 mt-4">
                <button id="cancelCheckout" class="bg-gray-300 px-4 py-2 rounded-md font-semibold">បោះបង់</button>
                <button id="confirmCheckout" class="bg-blue-500 text-white px-4 py-2 rounded-md font-semibold">បញ្ជាក់</button>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    window.Telegram.WebApp.ready();
    window.Telegram.WebApp.expand();
    const API_URL = ''; 
    const user = window.Telegram.WebApp.initDataUnsafe.user || {};
    let fullName = ((user.first_name || '') + ' ' + (user.last_name || '')).trim();
    let cachedProducts = <?php echo $initial_products_json; ?>;
    let cachedCart = [];
    const profileName = document.querySelector("h1");
    const profilePic = document.getElementById("profilePicture");
    const pointsSpan = document.getElementById("points");
    const productGroups = document.getElementById("productGroups");

    const showToast = (message) => {
        const toast = document.createElement("div");
        toast.className = "fixed bottom-20 left-1/2 -translate-x-1/2 bg-black text-white px-4 py-2 rounded-lg shadow-lg z-[9999]";
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    };

    const fetchPost = async (action, payload = {}) => {
        try {
            const response = await fetch(`${API_URL}?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (!response.ok) {
                const err = await response.json();
                throw new Error(err.error || `Action ${action} failed`);
            }
            return await response.json();
        } catch (error) {
            showToast(`Error: ${error.message}`);
            throw error;
        }
    };
    
    const renderProducts = (products) => {
        productGroups.innerHTML = '';
        if (!products || products.length === 0) {
            if (!document.querySelector('.alert-danger')) {
                 productGroups.innerHTML = `<p class="col-span-2 text-center text-gray-500">No products available.</p>`;
            }
            return;
        }
        products.forEach(p => {
            const stockClass = p.stock > 0 ? 'text-green-600' : 'text-red-500';
            const buttonDisabled = p.stock > 0 ? '' : 'disabled opacity-50 cursor-not-allowed';
            const productCard = document.createElement('div');
            productCard.className = 'product-card flex flex-col p-2';
            productCard.innerHTML = `
                <img src="${p.image}" alt="${p.name}" class="product-img mb-2"/>
                <div class="flex-grow flex flex-col p-2">
                    <h3 class="font-bold text-sm flex-grow mb-1">${p.name}</h3>
                    <p class="text-xs ${stockClass}">Stock: ${p.stock}</p>
                </div>
                <div class="flex justify-between items-center p-2">
                    <p class="font-bold text-blue-600">$${p.price.toFixed(2)}</p>
                    <button onclick="app.addToCart(${p.id})" class="bg-blue-500 text-white text-xs px-3 py-2 rounded-md hover:bg-blue-600 ${buttonDisabled}">
                        <i class="fas fa-cart-plus"></i>
                    </button>
                </div>
            `;
            productGroups.appendChild(productCard);
        });
    };

    const displayCart = () => {
        const cartContent = document.getElementById('cartContent');
        const badge = document.getElementById('cartBadge');
        cartContent.innerHTML = '';
        let totalItems = 0;
        if (cachedCart.length === 0) {
            cartContent.innerHTML = '<p class="text-center text-gray-500 p-4">Your cart is empty.</p>';
            badge.classList.add('hidden');
            return;
        }
        cachedCart.forEach(item => {
            totalItems += item.quantity;
            const itemDiv = document.createElement('div');
            itemDiv.className = 'cart-item';
            itemDiv.innerHTML = `
                <div class="flex-grow font-semibold">${item.name}</div>
                <div class="font-bold">Qty: ${item.quantity}</div>
                <button onclick="app.removeFromCart(${item.product_id})" class="text-red-500 ml-4 hover:text-red-700"><i class="fas fa-trash"></i></button>
            `;
            cartContent.appendChild(itemDiv);
        });
        if (totalItems > 0) {
            badge.textContent = totalItems;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    };
    
    const renderOrderHistory = (orders) => {
        const historyContent = document.getElementById("historyContent");
        historyContent.innerHTML = '';
        if (!orders || orders.length === 0) {
            historyContent.innerHTML = '<p class="text-center text-gray-500 p-4">No order history.</p>';
            return;
        }
        orders.forEach(order => {
             const orderDiv = document.createElement('div');
             orderDiv.className = 'p-3 border-b';
             orderDiv.innerHTML = `
                <p class="font-bold">Order #${order.id} - <span class="text-indigo-600">${order.status}</span></p>
                <p class="text-sm text-gray-600">${order.created_at}</p>
                <p class="text-sm">Total: <span class="font-semibold text-green-700">$${order.total.toFixed(2)}</span></p>
             `;
             historyContent.appendChild(orderDiv);
        });
    };

    const app = {
        init: async () => {
            new Swiper(".swiper-container", { loop: true, pagination: { el: ".swiper-pagination", clickable: true }, autoplay: { delay: 3000 } });
            if(user.id) {
                profileName.textContent = `Welcome, ${fullName}!`;
                profilePic.src = user.photo_url || 'https://via.placeholder.com/48';
                document.getElementById('shippingName').value = fullName;
            } else {
                 profileName.textContent = `Welcome!`;
            }
            renderProducts(cachedProducts);
            if(user.id) await app.loadPoints();
            displayCart();
            app.setupEventListeners();
        },
        setupEventListeners: () => {
            document.getElementById('sidebarToggle').addEventListener('click', () => document.getElementById('sidebar').classList.add('open'));
            document.getElementById('sidebarClose').addEventListener('click', () => document.getElementById('sidebar').classList.remove('open'));
            document.getElementById('cartTab').addEventListener('click', () => app.switchTab('cart'));
            document.getElementById('historyTab').addEventListener('click', () => app.switchTab('history'));
            document.getElementById('checkoutButton').addEventListener('click', app.showCheckoutModal);
            document.getElementById('cancelCheckout').addEventListener('click', () => document.getElementById('checkoutModal').style.display = 'none');
            document.getElementById('confirmCheckout').addEventListener('click', app.handleCheckout);
        },
        switchTab: (tabName) => {
             const cartContent = document.getElementById('cartContent');
             const historyContent = document.getElementById('historyContent');
             const cartTabBtn = document.getElementById('cartTab');
             const historyTabBtn = document.getElementById('historyTab');
             if(tabName === 'history') {
                 cartContent.classList.add('hidden');
                 historyContent.classList.remove('hidden');
                 cartTabBtn.classList.remove('active');
                 historyTabBtn.classList.add('active');
                 if(user.id) app.loadOrderHistory();
             } else {
                 historyContent.classList.add('hidden');
                 cartContent.classList.remove('hidden');
                 historyTabBtn.classList.remove('active');
                 cartTabBtn.classList.add('active');
             }
        },
        addToCart: (productId) => {
            const product = cachedProducts.find(p => p.id === productId);
            if (!product || product.stock <= 0) return;
            const cartItem = cachedCart.find(item => item.product_id === productId);
            if (cartItem) {
                cartItem.quantity++;
            } else {
                cachedCart.push({ product_id: productId, name: product.name, price: product.price, quantity: 1 });
            }
            showToast(`${product.name} added to cart.`);
            displayCart();
        },
        removeFromCart: (productId) => {
             cachedCart = cachedCart.filter(item => item.product_id !== productId);
             displayCart();
        },
        loadPoints: async () => {
            if(!user.id) return;
            const data = await fetchPost('get_points', { user_data: user });
            if (data && data.points !== undefined) {
                pointsSpan.textContent = data.points;
            }
        },
        loadOrderHistory: async () => {
            if(!user.id) return;
            const orders = await fetchPost('get_orders', { user_data: user });
            renderOrderHistory(orders);
        },
        showCheckoutModal: () => {
            if (cachedCart.length === 0) {
                showToast("Your cart is empty!");
                return;
            }
            const modal = document.getElementById("checkoutModal");
            const modalItems = document.getElementById("modalCartItems");
            const totalAmountSpan = document.getElementById("totalAmount");
            const pointsSpan = document.getElementById("estimatedPoints");
            modalItems.innerHTML = '';
            let total = 0;
            cachedCart.forEach(item => {
                total += item.price * item.quantity;
                const itemDiv = document.createElement('div');
                itemDiv.className = 'py-1 border-b';
                itemDiv.innerHTML = `${item.name} x ${item.quantity} = <b>$${(item.price * item.quantity).toFixed(2)}</b>`;
                modalItems.appendChild(itemDiv);
            });
            totalAmountSpan.textContent = `$${total.toFixed(2)}`;
            pointsSpan.textContent = Math.floor(total);
            modal.style.display = 'flex';
        },
        handleCheckout: async () => {
            const shippingName = document.getElementById("shippingName").value.trim();
            const shippingAddress = document.getElementById("shippingAddress").value.trim();
            const shippingPhone = document.getElementById("shippingPhone").value.trim();
            if (!shippingName || !shippingAddress || !shippingPhone) {
                showToast("Please fill all shipping details.");
                return;
            }
            const confirmBtn = document.getElementById('confirmCheckout');
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            try {
                const payload = {
                    user_data: user,
                    cart_items: cachedCart,
                    shipping_info: { name: shippingName, address: shippingAddress, phone: shippingPhone, payment_method: 'cash_on_delivery' }
                };
                const result = await fetchPost('checkout', payload);
                if (result && result.success) {
                    showToast(`Order #${result.order_id} placed!`);
                    cachedCart = [];
                    displayCart();
                    app.loadPoints();
                    document.getElementById('checkoutModal').style.display = 'none';
                }
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'បញ្ជាក់';
            }
        }
    };
    window.app = app;
    app.init();
});
</script>

</body>
</html>