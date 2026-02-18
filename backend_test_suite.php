<?php
// CLI-only script - block web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$baseUrl = "http://localhost/ux/Ux-Merchandise/";
$cookieFile = sys_get_temp_dir() . '/cookie_test_' . time() . '.txt';

function request($url, $method = 'GET', $data = [], $cookies = '') {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        // If data is array and contains json_payload, send as JSON
        if (isset($data['json_payload'])) {
             $jsonData = json_encode($data['json_payload']);
             curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
             curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        } else {
             // Standard POST
             curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'body' => $response];
}

echo "Starting Backend Test Suite...\n";
echo "Cookie File: $cookieFile\n\n";

// ==========================================
// TEST 1: User Login Flow with CSRF
// ==========================================
echo "[TEST 1] User Login Check\n";

// 1. Visit Signin Page to get Session & CSRF Token
$res = request($baseUrl . 'signin.php');
if ($res['code'] !== 200) die("Failed to load signin.php\n");

// Extract CSRF
preg_match('/id="csrf_token" value="([^"]+)"/', $res['body'], $matches);
$csrfToken = $matches[1] ?? '';

if (empty($csrfToken)) {
    echo "FAILED: Could not find CSRF token in signin.php\n";
    // Check if session started?
    // Maybe verify session start in signin.php?
} else {
    echo "SUCCESS: Found CSRF Token: " . substr($csrfToken, 0, 10) . "...\n";
}

// 2. Attempt Login with Token
$payload = [
    'email' => 'testuser@example.com',
    'password' => 'password123',
    'csrf_token' => $csrfToken
];

$loginRes = request($baseUrl . 'api/auth/login.php', 'POST', ['json_payload' => $payload]);
echo "Login Response Code: " . $loginRes['code'] . "\n";
echo "Body: " . substr($loginRes['body'], 0, 100) . "...\n";

if ($loginRes['code'] == 200 && strpos($loginRes['body'], 'success') !== false) {
    echo "SUCCESS: User Login Passed.\n";
} else {
    echo "FAILED: User Login Failed.\n";
}

// 3. Attempt Login WITHOUT Token (Should Fail)
echo "\n[TEST 2] User Login WITHOUT CSRF\n";
$badPayload = [
    'email' => 'testuser@example.com',
    'password' => 'password123'
    // No csrf_token
];
$badLoginRes = request($baseUrl . 'api/auth/login.php', 'POST', ['json_payload' => $badPayload]);
if ($badLoginRes['code'] == 403) {
    echo "SUCCESS: Missing CSRF correctly blocked (403).\n";
} else {
    echo "FAILED: Missing CSRF was NOT blocked. Code: " . $badLoginRes['code'] . "\n";
}


// ==========================================
// TEST 3: Admin Product Creation with CSRF
// ==========================================
// We need to login as admin first.
// I didn't add CSRF check to api/auth/admin-login.php, so we can just login directly.
echo "\n[TEST 3] Admin Product Creation Flow\n";

$adminPayload = [
    'email' => 'admin@uxpacific.com',
    'password' => 'password123'
];
$adminLoginRes = request($baseUrl . 'api/auth/admin-login.php', 'POST', ['json_payload' => $adminPayload]);
if ($adminLoginRes['code'] == 200) {
    echo "SUCCESS: Admin Logged In.\n";
} else {
    die("FAILED: Admin Login Failed.\n");
}

// Now verify product creation Access Control
// 1. Try to create product WITHOUT CSRF (Should Fail)
$productData = [
    'name' => 'Test Product',
    'category' => 'T-Shirts',
    'price' => 10,
    'description' => 'Test',
    'stock' => 5
];
$createRes = request($baseUrl . 'api/admin/product/create.php', 'POST', $productData);

if ($createRes['code'] == 403) {
    echo "SUCCESS: Product API correctly blocked request without CSRF (403).\n";
} else {
    echo "FAILED: Product API allowed request without CSRF. Code: " . $createRes['code'] . "\n";
}

// 2. Get CSRF from addproduct.php page
$addPageRes = request($baseUrl . 'admin/addproduct.php');
preg_match('/name="csrf_token" value="([^"]+)"/', $addPageRes['body'], $matches);
$adminCsrf = $matches[1] ?? '';

if ($adminCsrf) {
    echo "SUCCESS: Found Admin CSRF Token in addproduct.php\n";
    
    // 3. Try to create WITH CSRF
    // Note: product/create.php expects multipart/form-data usually for images.
    // Our helper function does x-www-form-urlencoded or JSON.
    // We need to simulate the POST parameters.
    // The previous edit to `create.php` checks $_POST['csrf_token'].
    
    $productDataWithCsrf = array_merge($productData, ['csrf_token' => $adminCsrf]);
    
    // We might get "Image required" error (400), but definitely NOT 403.
    $validRes = request($baseUrl . 'api/admin/product/create.php', 'POST', $productDataWithCsrf);
    
    if ($validRes['code'] !== 403) {
        echo "SUCCESS: Request with CSRF was NOT blocked by Security (Code: " . $validRes['code'] . ").\n";
        // Logic check: If code is 400 (Bad Request), it means it passed security but failed validation (missing image).
        if ($validRes['code'] == 400) {
             echo "       (Correctly failed validation due to missing image, proving security passed).\n";
        }
    } else {
        echo "FAILED: Request with CSRF was still blocked (403).\n";
    }
    
} else {
    echo "FAILED: Could not find CSRF token in admin/addproduct.php\n";
}

// Cleanup
@unlink($cookieFile);
?>
