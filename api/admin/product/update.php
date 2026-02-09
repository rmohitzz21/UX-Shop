<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

// Get ID
$id = $_POST['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Product ID is required"]);
    exit;
}

// Get other fields
$name = $_POST['name'] ?? '';
$category = $_POST['category'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? 0;
// $old_price = ... (optional, let's include it if posted)
$old_price = !empty($_POST['old_price']) ? $_POST['old_price'] : NULL;
$stock = $_POST['stock'] ?? 0;
$rating = $_POST['rating'] ?? 0; // Optional update
$related_products = $_POST['related_products'] ?? '';
$whats_included = $_POST['whats_included'] ?? '';
$file_specification = $_POST['file_specification'] ?? '';
$updated_at = date('Y-m-d H:i:s');

// Handle Image Upload
$imagePath = null;
$additional_images_json = null;
$uploadedImages = [];

if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    $uploadDir = '../../../img/products/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
             http_response_code(500);
             echo json_encode(["status" => "error", "message" => "Failed to create upload directory"]);
             exit;
        }
    }

    $files = $_FILES['images'];
    $allowedfileExtensions = array('jpg', 'gif', 'png', 'webp', 'jpeg');
    $fileCount = count($files['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileTmpPath = $files['tmp_name'][$i];
            $fileName = $files['name'][$i];
            
            // Get extension
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            // Sanitize file name
            $newFileName = md5(time() . $fileName . $i) . '.' . $fileExtension;
            
            if (in_array($fileExtension, $allowedfileExtensions)) {
                $dest_path = $uploadDir . $newFileName;
                
                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Save relative path for DB
                    $relativePath = 'img/products/' . $newFileName;
                    $uploadedImages[] = $relativePath;
                    
                    // Set first image as main image
                    if (empty($imagePath)) {
                        $imagePath = $relativePath;
                    }
                }
            }
        }
    }
    
    if (!empty($uploadedImages)) {
        $additional_images_json = json_encode($uploadedImages);
    }
}

// Construct SQL
$sql = "";
$types = "";
// Base params: name, description, category, price, old_price, stock, rating, related, included, spec, updated
$params = [$name, $description, $category, $price, $old_price, $stock, $rating, $related_products, $whats_included, $file_specification, $updated_at];
// Base types: s, s, s, d, d, i, d, s, s, s, s
$baseTypes = "sssd did ssss"; 
$baseTypes = "sssddidssss"; // removing spaces

if ($imagePath && $additional_images_json) {
    // Update with images
    $sql = "UPDATE products SET name=?, description=?, category=?, price=?, old_price=?, stock=?, rating=?, related_products=?, whats_included=?, file_specification=?, updated_at=?, image=?, additional_images=? WHERE id=?";
    $types = $baseTypes . "ssi"; // + image(s), additional_images(s), id(i)
    $params[] = $imagePath;
    $params[] = $additional_images_json;
    $params[] = $id;
} else {
    // Update without changing images
    $sql = "UPDATE products SET name=?, description=?, category=?, price=?, old_price=?, stock=?, rating=?, related_products=?, whats_included=?, file_specification=?, updated_at=? WHERE id=?";
    $types = $baseTypes . "i"; // + id(i)
    $params[] = $id;
}

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Product updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
}

$conn->close();
?>
