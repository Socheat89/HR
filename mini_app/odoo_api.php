<?php
// Set headers for JSON response and allow cross-origin requests (for development)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Headers: Content-Type, X-Telegram-Init-Data');

// Include the Ripcord library for XML-RPC communication
require_once('ripcord.php');

// Odoo Configuration (SAFE on the server, not visible to users)
$odoo_url = "https://www.vvc.asia";
$odoo_db = "zender";
$odoo_username = "samannoeun@gmail.com";
$odoo_api_key = "59cb5114faa709a3977586dca546b838b39110cc"; // This is your password or API key

/**
 * Connects and authenticates with the Odoo server.
 * @return array An array containing the models client and user ID (uid).
 * @throws Exception If authentication fails.
 */
function get_odoo_client() {
    global $odoo_url, $odoo_db, $odoo_username, $odoo_api_key;

    // Connect to the common endpoint to get the user ID (uid)
    $common = ripcord::client("$odoo_url/xmlrpc/2/common");
    $uid = $common->authenticate($odoo_db, $odoo_username, $odoo_api_key, []);

    if (!$uid) {
        // Stop execution and return an error if authentication fails
        throw new Exception("Authentication failed. Please check your Odoo credentials.");
    }

    // Connect to the object endpoint for executing methods on models
    $models = ripcord::client("$odoo_url/xmlrpc/2/object");

    return ['models' => $models, 'uid' => $uid];
}

/**
 * Fetches products from Odoo.
 * @param array $client The Odoo client connection details.
 * @return array A list of formatted products.
 */
function get_products($client) {
    global $odoo_db, $odoo_api_key;
    
    $models = $client['models'];
    $uid = $client['uid'];
    
    // Define the fields to retrieve from the 'product.product' model
    $fields = ['id', 'name', 'list_price', 'image_1920', 'public_categ_ids', 'qty_available', 'description_sale'];
    
    // Define search criteria (e.g., only products that are sellable on the website)
    $domain = [['sale_ok', '=', true]];
    
    // Execute the search_read method
    $products_data = $models->execute_kw($odoo_db, $uid, $odoo_api_key,
        'product.product', 'search_read',
        [$domain],
        ['fields' => $fields, 'limit' => 100] // Increase limit if needed
    );
    
    $products = [];
    foreach ($products_data as $product) {
        // Fetch the category name using the category ID
        $category_name = 'Uncategorized';
        if (!empty($product['public_categ_ids'])) {
            $category_id = $product['public_categ_ids'][0];
            $category_data = $models->execute_kw($odoo_db, $uid, $odoo_api_key,
                'product.public.category', 'read',
                [$category_id],
                ['fields' => ['name']]
            );
            if (!empty($category_data)) {
                 $category_name = $category_data[0]['name'];
            }
        }

        // Format the product data for the frontend
        $products[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['list_price'],
            // Odoo returns image as a base64 string, so we create a usable data URI
            'image' => $product['image_1920'] ? 'data:image/jpeg;base64,' . $product['image_1920'] : 'https://via.placeholder.com/150',
            'category' => $category_name,
            'description' => $product['description_sale'] ?: 'No description available.',
            'points' => round($product['list_price'] * 1), // Example points calculation
            'stock' => $product['qty_available'],
            'discount' => 0, // TODO: Add logic if you use pricelists for discounts in Odoo
            'label' => $product['qty_available'] > 0 ? ($product['qty_available'] < 10 ? 'low_stock' : 'new') : 'sold_out'
        ];
    }
    
    return $products;
}

/**
 * Fetches product categories from Odoo.
 * @param array $client The Odoo client connection details.
 * @return array A list of formatted categories.
 */
function get_categories($client) {
    global $odoo_db, $odoo_api_key;
    
    $models = $client['models'];
    $uid = $client['uid'];
    
    // Fetch all parent categories (categories that don't have a parent)
    $categories_data = $models->execute_kw($odoo_db, $uid, $odoo_api_key,
        'product.public.category', 'search_read',
        [['parent_id', '=', false]], // Get top-level categories
        ['fields' => ['id', 'name', 'child_id']]
    );

    $categories = [];
    foreach ($categories_data as $category) {
        $subcategories = [];
        // If the category has children, fetch their names
        if (!empty($category['child_id'])) {
             $subcategory_data = $models->execute_kw($odoo_db, $uid, $odoo_api_key,
                'product.public.category', 'read',
                [$category['child_id']],
                ['fields' => ['name']]
            );
            foreach ($subcategory_data as $sub) {
                $subcategories[] = ['name' => $sub['name']];
            }
        }

        // Format the category data for the frontend
        $categories[] = [
            'id' => $category['id'],
            'category' => $category['name'],
            'subcategories' => $subcategories,
        ];
    }
    
    return $categories;
}

// --- Main API Router ---
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // Establish connection to Odoo once
    $client = get_odoo_client();
    
    // Route the request based on the 'action' parameter
    switch ($action) {
        case 'get_products':
            $response = get_products($client);
            break;
            
        case 'get_subcategories':
            $response = get_categories($client);
            break;
            
        // --- ADD MORE ACTIONS FOR YOUR APP HERE ---
        // Example:
        // case 'checkout':
        //     $post_data = json_decode(file_get_contents('php://input'), true);
        //     $response = handle_checkout($client, $post_data);
        //     break;
            
        default:
            // Handle invalid actions
            $response = ['error' => "Invalid action requested: '{$action}'."];
            http_response_code(400); // Bad Request
            break;
    }
    
    // Send the response back to the frontend as JSON
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Catch any errors (like authentication failure) and return a server error response
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $e->getMessage()]);
}
?>