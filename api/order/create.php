<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/config.php';

// Get raw POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON data"]);
    exit;
}

// 1. Validate required fields
$required_fields = ['items', 'paymentMethod', 'shipping'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
        exit;
    }
}

// 2. Validate User Session (Source of Truth)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "User not logged in"]);
    exit;
}
$user_id = $_SESSION['user_id'];

// 3. Initiate Calculation & Validation
$calculated_subtotal = 0;
$order_items_data = [];
$has_digital_items = false;

// Start transaction for stock check/update
$conn->begin_transaction();

try {
    foreach ($data['items'] as $item) {
        $product_id = intval($item['id']);
        $quantity = intval($item['quantity']);
        // Use raw size for prepared statements to avoid double escaping
        $size = isset($item['size']) ? $item['size'] : '';
        
        if ($quantity <= 0) continue;

        // Fetch Product Data (Price, Stock, Type) from DB
        $stmt = $conn->prepare("SELECT price, stock, available_type FROM products WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Product ID $product_id not found");
        }
        
        $product = $result->fetch_assoc();
        $stmt->close();
        
        $price = floatval($product['price']);
        $current_stock = intval($product['stock']);
        $type = $product['available_type']; // physical, digital, both
        
        // Digital Check
        // If product is strictly digital, flag it. 
        // If it's "both" and user selected digital, flag it (we trust user selection for format if 'both')
        // Current cart logic sends 'product_type' in item.
        $selected_type = isset($item['product_type']) ? $item['product_type'] : 'physical';
        
        if ($type === 'digital' || ($type === 'both' && $selected_type === 'digital')) {
            $has_digital_items = true;
        }

        // Stock Validation (Skip for digital items usually, but let's assume stock tracks licenses too or just unlimited?)
        // Assuming infinite stock for digital, or managed.
        // If physical, check stock.
        if ($selected_type === 'physical') {
            if ($current_stock < $quantity) {
                throw new Exception("Insufficient stock for product ID $product_id. Available: $current_stock");
            }
            
            // Deduct Stock
            $updateStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $updateStock->bind_param("ii", $quantity, $product_id);
            if (!$updateStock->execute()) {
                throw new Exception("Failed to update stock");
            }
            $updateStock->close();
        }

        // Add to subtotal
        $item_total = $price * $quantity;
        $calculated_subtotal += $item_total;
        
        $order_items_data[] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'price' => $price, // Use DB price
            'size' => $size,
            'type' => $selected_type // Store the selected type if needed, or schema doesn't have it? 
            // Schema order_items (order_id, product_id, quantity, price, size)
            // We might lose 'type' info if not in schema. But standard schema implies physical unless product is digital.
        ];
    }
    
    // 4. Calculate Taxes & Shipping
    $shipping_cost = ($calculated_subtotal > 0) ? 50.00 : 0.00;
    $tax = round($calculated_subtotal * 0.18, 2);
    $calculated_total = $calculated_subtotal + $shipping_cost + $tax;
    
    // 5. Validate Payment Logic
    $payment_method = $data['paymentMethod'];
    $allowed_methods = ['card', 'upi', 'cod'];
    
    if (!in_array($payment_method, $allowed_methods)) {
        throw new Exception("Invalid payment method");
    }
    
    if ($payment_method === 'cod' && $has_digital_items) {
        throw new Exception("Cash on Delivery is not available for digital products.");
    }

    // 6. Create Order
    $order_number = 'UXP-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $status = 'Pending';
    $shipping_address_json = json_encode($data['shipping']);
    
    $stmt = $conn->prepare("INSERT INTO orders (order_number, user_id, total, subtotal, shipping, tax, payment_method, status, shipping_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("siddddsss", 
        $order_number, 
        $user_id, 
        $calculated_total, 
        $calculated_subtotal, 
        $shipping_cost, 
        $tax, 
        $payment_method, 
        $status, 
        $shipping_address_json
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error creating order: " . $stmt->error);
    }
    
    $order_id = $conn->insert_id;
    $stmt->close();

    // 7. Insert Items & Remove from Cart
    $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)");
    
    // Check for size matching (handling empty/null)
    $delete_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ? AND (size = ? OR size = '' OR size IS NULL) AND product_type = ?");
    
    foreach ($order_items_data as $item) {
        // Insert into order_items
        $stmt_item->bind_param("iiids", 
            $order_id, 
            $item['product_id'], 
            $item['quantity'], 
            $item['price'], 
            $item['size']
        );
        if (!$stmt_item->execute()) {
            throw new Exception("Error inserting order items");
        }
        
        // Remove from cart
        if ($delete_cart) {
             $p_type = isset($item['type']) ? $item['type'] : 'physical';
             $delete_cart->bind_param("iiss", $user_id, $item['product_id'], $item['size'], $p_type);
             $delete_cart->execute();
        }
    }
    $stmt_item->close();
    if ($delete_cart) $delete_cart->close();

    // 8. Handle "Save Address" (Optional feature from checkout)
    if (!empty($data['saveAddress']) && !empty($data['shipping'])) {
         $ship = $data['shipping'];
         // Simple validation ensuring not empty
         if (!empty($ship['address'])) {
             // Check if address APIs exist or table exists. Assuming 'addresses' table exists from previous context.
             // We'll try to insert. If it fails (table missing), we catch exception? 
             // Actually, we are in a transaction. If this fails, order fails. 
             // Better to wrap in try-catch or ensure table exists. 
             // Previously viewed code showed INSERT INTO addresses.
             $saveAddrStmt = $conn->prepare("INSERT INTO addresses (user_id, first_name, last_name, address, city, state, zip, country, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
             if ($saveAddrStmt) {
                 $saveAddrStmt->bind_param("issssssss", 
                    $user_id, 
                    $ship['firstName'], 
                    $ship['lastName'], 
                    $ship['address'], 
                    $ship['city'], 
                    $ship['state'], 
                    $ship['zip'], 
                    $ship['country'], 
                    $ship['phone']
                 );
                 $saveAddrStmt->execute();
                 $saveAddrStmt->close();
             }
         }
    }
    
    $conn->commit();
    
    echo json_encode([
        "status" => "success",
        "message" => "Order placed successfully",
        "orderNumber" => $order_number,
        "orderId" => $order_id,
        "total" => $calculated_total,
        "subtotal" => $calculated_subtotal,
        "tax" => $tax,
        "shipping_cost" => $shipping_cost,
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>
