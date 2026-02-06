<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Include database configuration
// Path: root/api/admin/product/create.php -> ../../../includes/config.php
require_once '../../../includes/config.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

// Get form data
$name = $_POST['name'] ?? '';
$category = $_POST['category'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? 0;
$old_price = !empty($_POST['old_price']) ? $_POST['old_price'] : NULL;
$stock = $_POST['stock'] ?? 0;
$rating = $_POST['rating'] ?? 0;
// $featured = isset($_POST['featured']) ? 1 : 0;
$created_at = date('Y-m-d H:i:s');
$updated_at = date('Y-m-d H:i:s');      

// Validation
if (empty($name) || empty($category) || empty($price)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Name, category, and price are required"]);
    exit;
}

// Handle Media Upload (Multiple Files)
$mainImagePath = '';
$uploadedMedia = []; // To store successful uploads before DB insertion

if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0])) {
    // Define upload directory
    $uploadDir = '../../../img/products/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileCount = count($_FILES['media']['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['media']['error'][$i] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['media']['tmp_name'][$i];
            $fileName = $_FILES['media']['name'][$i];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            // Generate unique name
            $newFileName = md5(time() . $fileName . $i) . '.' . $fileExtension;
            $dest_path = $uploadDir . $newFileName;
            $db_path = 'img/products/' . $newFileName;

            // Determine type
            $type = 'document';
            $allowedImages = ['jpg', 'gif', 'png', 'webp', 'jpeg'];
            $allowedVideos = ['mp4', 'webm', 'ogg'];
            $allowedDocs = ['pdf', 'doc', 'docx'];

            if (in_array($fileExtension, $allowedImages)) {
                $type = 'image';
            } elseif (in_array($fileExtension, $allowedVideos)) {
                $type = 'video';
            } elseif (in_array($fileExtension, $allowedDocs)) {
                $type = 'document';
            } else {
                continue; // Skip unsupported files
            }

            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $uploadedMedia[] = [
                    'path' => $db_path,
                    'type' => $type
                ];

                // Set first image as main image
                if ($type === 'image' && empty($mainImagePath)) {
                    $mainImagePath = $db_path;
                }
            }
        }
    }
}

// Fallback: If no image found in media[], check legacy 'image' field
if (empty($mainImagePath) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    // ... (Legacy single file logic could go here if needed, but we'll rely on the loop above)
}
        
// Insert into DB
$sql = "INSERT INTO products (name, description, category, price, old_price, image, stock, rating, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // Types: s=string, s=string, s=string, d=double, d=double, s=string, i=integer, d=double, s=string, s=string
    // name, description, category, price, old_price, image, stock, rating, created_at, updated_at
    $stmt->bind_param("sssdssidss", $name, $description, $category, $price, $old_price, $mainImagePath, $stock, $rating, $created_at, $updated_at);
    
    if ($stmt->execute()) {
        $product_id = $conn->insert_id;

        // Insert Media Files
        if (!empty($uploadedMedia)) {
            $mediaStmt = $conn->prepare("INSERT INTO product_media (product_id, file_path, file_type) VALUES (?, ?, ?)");
            foreach ($uploadedMedia as $media) {
                $mediaStmt->bind_param("iss", $product_id, $media['path'], $media['type']);
                $mediaStmt->execute();
            }
            $mediaStmt->close();
        }

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Product created successfully with " . count($uploadedMedia) . " media files.",
            "product_id" => $product_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $stmt->error
        ]);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Statement preparation failed: " . $conn->error
    ]);
}

$conn->close();
?>