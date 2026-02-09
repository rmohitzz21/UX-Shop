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
// Get form data
$name = $_POST['name'] ?? '';
$category = $_POST['category'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? 0;
$old_price = !empty($_POST['old_price']) ? $_POST['old_price'] : NULL;
$stock = $_POST['stock'] ?? 0;
$rating = $_POST['rating'] ?? 0;
$related_products = $_POST['related_products'] ?? '';
$whats_included = $_POST['whats_included'] ?? '';
$file_specification = $_POST['file_specification'] ?? '';
// $featured = isset($_POST['featured']) ? 1 : 0;
$created_at = date('Y-m-d H:i:s');
$updated_at = date('Y-m-d H:i:s');      

// Validation
if (empty($name) || empty($category) || empty($price)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Name, category, and price are required"]);
    exit;
}

// Handle Image Upload
$imagePath = '';
$uploadedImages = [];

if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    // Define upload directory
    $uploadDir = '../../../img/products/';
    
    // Create directory if it doesn't exist
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
    
    if (empty($imagePath)) {
         http_response_code(500);
         echo json_encode(["status" => "error", "message" => "Failed to upload any images."]);
         exit;
    }

} else {
    // If image is required in the form, fail here.
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Product image is required"]);
    exit;
}

// Serialize additional images (all uploaded images)
$additional_images = json_encode($uploadedImages);

// Insert into DB
$sql = "INSERT INTO products (name, description, category, price, old_price, image, stock, rating, related_products, whats_included, file_specification, additional_images, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // Types: s=string, d=double, i=int
    // name(s), description(s), category(s), price(d), old_price(d), image(s), stock(i), rating(d), related(s), included(s), spec(s), add_imgs(s), created(s), updated(s)
    $stmt->bind_param("sssdssidssssss", $name, $description, $category, $price, $old_price, $imagePath, $stock, $rating, $related_products, $whats_included, $file_specification, $additional_images, $created_at, $updated_at);
    
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Product created successfully",
            "product_id" => $conn->insert_id,
            "images_count" => count($uploadedImages)
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