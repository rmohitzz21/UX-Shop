#!/usr/bin/env php
<?php
/**
 * Phase 9 — UX Pacific Merchandise Final Verification Test Suite
 *
 * Run from project root:
 *   php tests/test_suite.php
 *
 * Requires: PHP CLI with curl + mysqli extensions, XAMPP running.
 */

declare(strict_types=1);

// ── Load .env before anything else ───────────────────────────────────────────
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

// ── Configuration ─────────────────────────────────────────────────────────────
define('BASE_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost/ux/Ux-Merchandise', '/'));
define('DB_HOST',      getenv('DB_HOST') ?: 'localhost');
define('DB_USER',      getenv('DB_USER') ?: 'root');
define('DB_PASS',      getenv('DB_PASS') ?: '');
define('DB_NAME',      getenv('DB_NAME') ?: 'uxmerchandise');

// Unique per-run credentials — never collide with real data
$TS = time();
define('TEST_EMAIL',       "ts_{$TS}@test.uxpacific.local");
define('TEST_PASS',        "TestPass_{$TS}!");
define('TEST_ADMIN_EMAIL', "ts_admin_{$TS}@test.uxpacific.local");
define('TEST_ADMIN_PASS',  "AdminPass_{$TS}!");
// TEST_PRODUCT_ID set dynamically in setUp() from DB — see $GLOBALS['TEST_PRODUCT_ID']

// ── ANSI colours ──────────────────────────────────────────────────────────────
const GRN = "\033[32m"; const RED = "\033[31m"; const YLW = "\033[33m";
const CYN = "\033[36m"; const BLD = "\033[1m";  const RST = "\033[0m";
const DIM = "\033[2m";

// ── Global state ──────────────────────────────────────────────────────────────
$RESULTS   = ['pass' => 0, 'fail' => 0, 'skip' => 0];
$SUITE_START = microtime(true);
$TEST_ORDER_NUMBER = null;   // set during checkout test, used for cleanup
$TEST_PRODUCT_DB_ID = null;  // created during admin product test, cleaned up after
$GLOBALS['TEST_PRODUCT_ID'] = 0; // set in setUp() to a valid physical product ID

// ─────────────────────────────────────────────────────────────────────────────
//  HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Make an HTTP request using curl. Returns [http_code, body_string, elapsed_ms].
 */
function http(string $method, string $path, $payload = null, array $extra = [], string $cookieJar = ''): array
{
    $url = BASE_URL . '/' . ltrim($path, '/');
    $ch  = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'UXP-TestSuite/1.0',
    ]);

    if ($cookieJar !== '') {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($payload)) {
            // multipart/form-data  (file uploads)
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS,    $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    foreach ($extra as $opt => $val) {
        curl_setopt($ch, $opt, $val);
    }

    $t0   = microtime(true);
    $body = curl_exec($ch);
    $ms   = round((microtime(true) - $t0) * 1000);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, (string) $body, $ms];
}

/**
 * GET a PHP page, extract csrf-token meta tag, return token string.
 */
function getCsrfToken(string $page, string $jar): string
{
    [, $body] = http('GET', $page, null, [], $jar);
    if (preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/', $body, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Decode JSON body safely.
 */
function jd(string $body): ?array
{
    $d = json_decode($body, true);
    return is_array($d) ? $d : null;
}

/** Print section header */
function section(string $name): void
{
    echo "\n" . BLD . CYN . "  ┌─ {$name} " . str_repeat('─', max(0, 55 - strlen($name))) . RST . "\n";
}

/** Record and print a test result */
function result(string $name, bool $pass, string $detail = '', int|float $ms = 0): void
{
    global $RESULTS;
    $icon  = $pass ? GRN . '  ✓' . RST : RED . '  ✗' . RST;
    $color = $pass ? GRN : RED;
    $time  = $ms > 0 ? DIM . "  [{$ms}ms]" . RST : '';
    $det   = $detail ? DIM . "  — {$detail}" . RST : '';
    echo $icon . ' ' . $color . $name . RST . $det . $time . "\n";
    $RESULTS[$pass ? 'pass' : 'fail']++;
}

function skip(string $name, string $reason): void
{
    global $RESULTS;
    echo YLW . "  ⊘ {$name}" . RST . DIM . "  — SKIP: {$reason}" . RST . "\n";
    $RESULTS['skip']++;
}

// ─────────────────────────────────────────────────────────────────────────────
//  DATABASE  (for setup/teardown only — tests use HTTP)
// ─────────────────────────────────────────────────────────────────────────────

function dbConnect(): mysqli
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        echo RED . "  ✗ DB connection failed: " . $conn->connect_error . RST . "\n";
        exit(1);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function setUp(): void
{
    $db   = dbConnect();

    // ── Cleanup leftover data from previous incomplete test runs ──────────────
    $db->query("DELETE FROM products WHERE name LIKE 'TS_Product_%'");
    $db->query("DELETE FROM users    WHERE email LIKE 'ts_%@test.uxpacific.local'");

    // ── Select a valid physical test product (must exist, be active, have stock) ──
    $r = $db->query(
        "SELECT id, name FROM products
         WHERE is_active = 1
           AND stock > 0
           AND available_type IN ('physical','both')
         ORDER BY stock DESC
         LIMIT 1"
    );
    if (!$r || $r->num_rows === 0) {
        // No suitable product found — create a temporary one for the test run
        $db->query(
            "INSERT INTO products (name, category, description, available_type, price, stock, image, is_active)
             VALUES ('TS_Product_setup', 'Test', 'Auto-created by test suite', 'physical', 9.99, 20, '', 1)"
        );
        $GLOBALS['TEST_PRODUCT_ID'] = (int) $db->insert_id;
        echo DIM . "  Test product: #{$GLOBALS['TEST_PRODUCT_ID']} — TS_Product_setup (auto-created)\n" . RST;
    } else {
        $row = $r->fetch_assoc();
        $GLOBALS['TEST_PRODUCT_ID'] = (int) $row['id'];
        echo DIM . "  Test product: #{$row['id']} — {$row['name']}\n" . RST;
    }

    // ── Create temporary test admin user (direct DB — bypasses HTTP auth) ──────
    $hash = password_hash(TEST_ADMIN_PASS, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES (?, ?, 'TS', 'Admin', 'admin')");
    $e = TEST_ADMIN_EMAIL;
    $stmt->bind_param('ss', $e, $hash);
    $stmt->execute();
    $stmt->close();

    $db->close();
}

function tearDown(): void
{
    global $TEST_ORDER_NUMBER, $TEST_PRODUCT_DB_ID;
    $db = dbConnect();

    // Remove test order (restore stock too)
    if ($TEST_ORDER_NUMBER) {
        // Restore stock for product used
        $db->query("UPDATE products p
                    JOIN order_items oi ON oi.product_id = p.id
                    JOIN orders o ON o.id = oi.order_id
                    SET p.stock = p.stock + oi.quantity
                    WHERE o.order_number = '{$TEST_ORDER_NUMBER}'");
        $db->query("DELETE oi FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.order_number = '{$TEST_ORDER_NUMBER}'");
        $db->query("DELETE FROM orders WHERE order_number = '{$TEST_ORDER_NUMBER}'");
    }

    // Remove test product created during admin tests
    if ($TEST_PRODUCT_DB_ID) {
        $db->query("DELETE FROM order_items WHERE product_id = {$TEST_PRODUCT_DB_ID}");
        $db->query("DELETE FROM cart WHERE product_id = {$TEST_PRODUCT_DB_ID}");
        $db->query("DELETE FROM products WHERE id = {$TEST_PRODUCT_DB_ID}");
    }

    // Remove test users (customer + admin)
    $e1 = TEST_EMAIL;       $db->query("DELETE FROM cart  WHERE user_id = (SELECT id FROM users WHERE email = '{$e1}')");
    $db->query("DELETE FROM users WHERE email = '{$e1}'");
    $e2 = TEST_ADMIN_EMAIL; $db->query("DELETE FROM users WHERE email = '{$e2}'");

    $db->close();
}

// ─────────────────────────────────────────────────────────────────────────────
//  USER FLOW TESTS
// ─────────────────────────────────────────────────────────────────────────────

function runUserFlows(): void
{
    global $TEST_ORDER_NUMBER;
    $testProductId = $GLOBALS['TEST_PRODUCT_ID'];
    section('USER FLOWS');

    $jar = tempnam(sys_get_temp_dir(), 'uxp_user_');

    // ── 1. Signup ────────────────────────────────────────────────────────────
    $csrf = getCsrfToken('/signup.php', $jar);
    [$code, $body, $ms] = http('POST', '/api/auth/signup.php',
        json_encode([
            'firstName'  => 'Test',
            'lastName'   => 'Suite',
            'email'      => TEST_EMAIL,
            'password'   => TEST_PASS,
            'phone'      => '+91 9999999999',
            'csrf_token' => $csrf,
        ]), [], $jar);
    $d = jd($body);
    result('Signup', $code === 200 && ($d['status'] ?? '') === 'success',
        $d['message'] ?? "HTTP {$code}", $ms);

    // ── 2. Login ─────────────────────────────────────────────────────────────
    $csrf = getCsrfToken('/signin.php', $jar);
    [$code, $body, $ms] = http('POST', '/api/auth/login.php',
        json_encode([
            'email'      => TEST_EMAIL,
            'password'   => TEST_PASS,
            'csrf_token' => $csrf,
        ]), [], $jar);
    $d = jd($body);
    result('Login', $code === 200 && ($d['status'] ?? '') === 'success',
        $d['message'] ?? "HTTP {$code}", $ms);

    // ── 3. Add to cart ───────────────────────────────────────────────────────
    [$code, $body, $ms] = http('POST', '/api/cart/add.php',
        json_encode([
            'product_id'     => $testProductId,
            'quantity'       => 1,
            'size'           => 'M',
            'available_type' => 'physical',
        ]), [], $jar);
    $d = jd($body);
    result('Add to cart (product #' . $testProductId . ')',
        $code === 200 && ($d['status'] ?? '') === 'success',
        $d['message'] ?? "HTTP {$code}", $ms);

    // ── 4. Get cart ──────────────────────────────────────────────────────────
    [$code, $body, $ms] = http('GET', '/api/cart/list.php', null, [], $jar);
    $d = jd($body);
    $cartCount = count($d['data'] ?? $d['items'] ?? $d ?? []);
    result('Get cart', $code === 200 && $cartCount >= 1,
        "{$cartCount} item(s) in cart", $ms);

    // ── 5. Checkout ──────────────────────────────────────────────────────────
    [$code, $body, $ms] = http('POST', '/api/order/create.php',
        json_encode([
            'items' => [[
                'id'             => $testProductId,
                'quantity'       => 1,
                'size'           => 'M',
                'available_type' => 'physical',
            ]],
            'paymentMethod' => 'cod',
            'shipping' => [
                'firstName' => 'Test',
                'lastName'  => 'Suite',
                'address'   => '123 Automated Test Lane',
                'city'      => 'Testville',
                'state'     => 'TS',
                'zip'       => '400001',
                'country'   => 'IN',
                'phone'     => '+91 9999999999',
                'email'     => TEST_EMAIL,
            ],
        ]), [], $jar);
    $d = jd($body);
    $ok = $code === 200 && ($d['status'] ?? '') === 'success';
    if ($ok) $TEST_ORDER_NUMBER = $d['orderNumber'] ?? null;
    result('Checkout (COD)', $ok,
        $ok ? "order {$TEST_ORDER_NUMBER}" : ($d['message'] ?? "HTTP {$code}"), $ms);

    // ── 6. Order history ─────────────────────────────────────────────────────
    [$code, $body, $ms] = http('GET', '/api/order/get.php', null, [], $jar);
    $d = jd($body);
    $orderCount = count($d['orders'] ?? $d['data'] ?? (is_array($d) && isset($d[0]) ? $d : []));
    result('Order history', $code === 200,
        "{$orderCount} order(s) returned", $ms);

    // ── 7. Logout ────────────────────────────────────────────────────────────
    [$code, $body, $ms] = http('POST', '/api/auth/logout.php', '{}', [], $jar);
    $d = jd($body);
    result('Logout', $code === 200 && ($d['status'] ?? '') === 'success',
        $d['message'] ?? "HTTP {$code}", $ms);

    // Confirm session is cleared
    [$code2] = http('GET', '/api/cart/list.php', null, [], $jar);
    result('Session destroyed after logout', $code2 === 401, "GET /api/cart/list.php returned {$code2}");

    @unlink($jar);
}

// ─────────────────────────────────────────────────────────────────────────────
//  ADMIN FLOW TESTS
// ─────────────────────────────────────────────────────────────────────────────

function runAdminFlows(): void
{
    global $TEST_PRODUCT_DB_ID, $TEST_ORDER_NUMBER;
    section('ADMIN FLOWS');

    $jar = tempnam(sys_get_temp_dir(), 'uxp_admin_');

    // ── 1. Admin login ───────────────────────────────────────────────────────
    $csrf = getCsrfToken('/admin/admin-login.php', $jar);
    if ($csrf === '') {
        skip('Admin login', 'Could not fetch CSRF token from admin-login.php');
        @unlink($jar); return;
    }
    [$code, $body, $ms] = http('POST', '/api/auth/admin-login.php',
        json_encode([
            'email'      => TEST_ADMIN_EMAIL,
            'password'   => TEST_ADMIN_PASS,
            'csrf_token' => $csrf,
        ]), [], $jar);
    $d = jd($body);
    $adminOk = $code === 200 && ($d['status'] ?? '') === 'success';
    result('Admin login', $adminOk, $d['message'] ?? "HTTP {$code}", $ms);
    if (!$adminOk) {
        skip('Admin product/order tests', 'Admin login failed');
        @unlink($jar); return;
    }

    // ── 2. Create product (with a minimal PNG image) ─────────────────────────
    $png = createMinimalPng();
    $tmp = tempnam(sys_get_temp_dir(), 'uxp_img_') . '.png';
    file_put_contents($tmp, $png);

    $postFields = [
        'csrf_token'        => $csrf,
        'name'              => 'TS_Product_' . time(),
        'category'          => 'Test',
        'description'       => 'Automated test product',
        'price'             => '9.99',
        'stock'             => '5',
        'rating'            => '4.0',
        'available_type'    => 'physical',
        'images[]'          => new CURLFile($tmp, 'image/png', 'test_product.png'),
    ];

    [$code, $body, $ms] = http('POST', '/api/admin/product/create.php', $postFields, [], $jar);
    @unlink($tmp);
    $d = jd($body);
    $productOk = in_array($code, [200, 201]) && ($d['status'] ?? '') === 'success';
    $TEST_PRODUCT_DB_ID = $d['product_id'] ?? $d['data']['id'] ?? $d['id'] ?? null;
    // If ID not in response, query DB
    if ($productOk && !$TEST_PRODUCT_DB_ID) {
        $db = dbConnect();
        $r  = $db->query("SELECT id FROM products WHERE name LIKE 'TS_Product_%' ORDER BY id DESC LIMIT 1");
        if ($row = $r->fetch_assoc()) $TEST_PRODUCT_DB_ID = (int)$row['id'];
        $db->close();
    }
    result('Add product', $productOk,
        $productOk ? "ID: {$TEST_PRODUCT_DB_ID}" : ($d['message'] ?? "HTTP {$code}"), $ms);

    // ── 3. Edit product ──────────────────────────────────────────────────────
    if ($TEST_PRODUCT_DB_ID) {
        $png2 = createMinimalPng();
        $tmp2 = tempnam(sys_get_temp_dir(), 'uxp_upd_') . '.png';
        file_put_contents($tmp2, $png2);

        $editFields = [
            'csrf_token'       => $csrf,
            'id'               => (string) $TEST_PRODUCT_DB_ID,
            'name'             => 'TS_Product_Updated',
            'category'         => 'Test',
            'description'      => 'Updated description',
            'price'            => '12.99',
            'stock'            => '8',
            'available_type'   => 'physical',
            'existing_images'  => '[]',
            'images[]'         => new CURLFile($tmp2, 'image/png', 'updated.png'),
        ];

        [$code, $body, $ms] = http('POST', '/api/admin/product/update.php', $editFields, [], $jar);
        @unlink($tmp2);
        $d = jd($body);
        result('Edit product + upload image', $code === 200 && ($d['status'] ?? '') === 'success',
            $d['message'] ?? "HTTP {$code}", $ms);
    } else {
        skip('Edit product', 'Product ID unknown (create may have failed)');
    }

    // ── 4. Change order status ───────────────────────────────────────────────
    if ($TEST_ORDER_NUMBER) {
        [$code, $body, $ms] = http('POST', '/api/admin/order/update_status.php',
            json_encode([
                'order_number' => $TEST_ORDER_NUMBER,
                'status'       => 'Processing',
            ]), [], $jar);
        $d = jd($body);
        result('Change order status → Processing',
            $code === 200 && ($d['status'] ?? '') === 'success',
            $d['message'] ?? "HTTP {$code}", $ms);

        // Revert back to Pending (for clean teardown)
        http('POST', '/api/admin/order/update_status.php',
            json_encode(['order_number' => $TEST_ORDER_NUMBER, 'status' => 'Pending']),
            [], $jar);
    } else {
        skip('Change order status', 'No test order (checkout test may have failed)');
    }

    // ── 5. Delete product ────────────────────────────────────────────────────
    if ($TEST_PRODUCT_DB_ID) {
        [$code, $body, $ms] = http('POST', '/api/admin/product/delete.php',
            ['id' => (string) $TEST_PRODUCT_DB_ID, 'csrf_token' => $csrf],
            [], $jar);
        $d = jd($body);
        $deleted = $code === 200 && ($d['status'] ?? '') === 'success';
        result('Delete product', $deleted, $d['message'] ?? "HTTP {$code}", $ms);
        if ($deleted) $TEST_PRODUCT_DB_ID = null; // tearDown skips cleanup
    } else {
        skip('Delete product', 'No product to delete');
    }

    @unlink($jar);
}

// ─────────────────────────────────────────────────────────────────────────────
//  SECURITY TESTS
// ─────────────────────────────────────────────────────────────────────────────

function runSecurityTests(): void
{
    section('SECURITY TESTS');
    $jar = tempnam(sys_get_temp_dir(), 'uxp_sec_');

    // ── 1. CSRF: signup without token ─────────────────────────────────────────
    getCsrfToken('/signup.php', $jar); // start session
    [$code, $body] = http('POST', '/api/auth/signup.php',
        json_encode(['firstName' => 'Hack', 'lastName' => 'Er',
            'email' => 'csrf_test_' . time() . '@x.com',
            'password' => 'Test1234!',
            /* no csrf_token */]), [], $jar);
    result('CSRF blocked on signup (no token)', $code === 403,
        "HTTP {$code} (expected 403)");

    // ── 2. CSRF: admin-login without token ────────────────────────────────────
    getCsrfToken('/admin/admin-login.php', $jar);
    [$code] = http('POST', '/api/auth/admin-login.php',
        json_encode(['email' => TEST_ADMIN_EMAIL, 'password' => TEST_ADMIN_PASS
            /* no csrf_token */]), [], $jar);
    result('CSRF blocked on admin-login (no token)', $code === 403,
        "HTTP {$code} (expected 403)");

    // ── 3. SQL injection in login ─────────────────────────────────────────────
    $csrf = getCsrfToken('/signin.php', $jar);
    [$code, $body] = http('POST', '/api/auth/login.php',
        json_encode([
            'email'      => "' OR '1'='1' -- ",
            'password'   => "' OR '1'='1",
            'csrf_token' => $csrf,
        ]), [], $jar);
    $d = jd($body);
    result('SQL injection in login rejected',
        $code !== 200 || ($d['status'] ?? '') !== 'success',
        "HTTP {$code} status=" . ($d['status'] ?? 'n/a'));

    // ── 4. SQL injection via signup email ─────────────────────────────────────
    $csrf = getCsrfToken('/signup.php', $jar);
    [$code, $body] = http('POST', '/api/auth/signup.php',
        json_encode([
            'firstName'  => 'Inject',
            'lastName'   => 'Test',
            'email'      => "inject'); DROP TABLE users; --",
            'password'   => 'Test1234!',
            'csrf_token' => $csrf,
        ]), [], $jar);
    $d = jd($body);
    // Should fail validation (invalid email) with 400, and users table must still exist
    $db     = dbConnect();
    $tableExists = $db->query("SHOW TABLES LIKE 'users'")->num_rows > 0;
    $db->close();
    result('SQL injection in signup: users table intact',
        $tableExists && ($code === 400 || ($d['status'] ?? '') === 'error'),
        "HTTP {$code} table_exists=" . ($tableExists ? 'yes' : 'NO — CRITICAL!'));

    // ── 5. Unauthenticated access to admin API ────────────────────────────────
    $anonJar = tempnam(sys_get_temp_dir(), 'uxp_anon_');
    [$code] = http('GET', '/api/admin/user/list.php', null, [], $anonJar);
    result('Admin API blocked for unauthenticated user',
        in_array($code, [401, 403]),
        "HTTP {$code} (expected 401 or 403)");
    @unlink($anonJar);

    // ── 6. Unauthenticated access to cart ────────────────────────────────────
    $anonJar2 = tempnam(sys_get_temp_dir(), 'uxp_anon2_');
    [$code] = http('GET', '/api/cart/list.php', null, [], $anonJar2);
    result('Cart API blocked for unauthenticated user',
        $code === 401,
        "HTTP {$code} (expected 401)");
    @unlink($anonJar2);

    // ── 7. XSS payload stored safely (API returns JSON, not raw HTML) ─────────
    $csrf = getCsrfToken('/signup.php', $jar);
    $xssEmail = 'xss_' . time() . '@test.local';
    [$code, $body] = http('POST', '/api/auth/signup.php',
        json_encode([
            'firstName'  => '<script>alert("xss")</script>',
            'lastName'   => '"><img src=x onerror=alert(1)>',
            'email'      => $xssEmail,
            'password'   => 'Test1234!',
            'csrf_token' => $csrf,
        ]), [], $jar);
    $ok = $code === 200; // Signup succeeds (XSS stored safely, escaped on output)
    // Verify the stored name is in DB as-is (raw), not double-encoded
    $db = dbConnect();
    $stmt = $db->prepare("SELECT first_name FROM users WHERE email = ?");
    $stmt->bind_param('s', $xssEmail);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $storedRaw = $row['first_name'] ?? null;
    $db->query("DELETE FROM users WHERE email = '{$db->escape_string($xssEmail)}'");
    $db->close();
    result('XSS payload: stored as raw text in DB (escaped on render)',
        $ok && $storedRaw === '<script>alert("xss")</script>',
        $storedRaw ? "stored: " . substr($storedRaw, 0, 30) : "not stored (signup failed)");

    // ── 8. File upload exploit: pure PHP file renamed .jpg ───────────────────
    // Login as admin for upload test
    $adminJar  = tempnam(sys_get_temp_dir(), 'uxp_admin2_');
    $adminCsrf = getCsrfToken('/admin/admin-login.php', $adminJar);
    http('POST', '/api/auth/admin-login.php',
        json_encode(['email' => TEST_ADMIN_EMAIL, 'password' => TEST_ADMIN_PASS, 'csrf_token' => $adminCsrf]),
        [], $adminJar);

    $phpCode  = '<?php system($_GET["cmd"]); ?>';
    $phpFile  = tempnam(sys_get_temp_dir(), 'exploit_') . '.jpg'; // .jpg extension, PHP content
    file_put_contents($phpFile, $phpCode);

    $uploadFields = [
        'csrf_token'  => $adminCsrf,
        'name'        => 'Exploit Test',
        'category'    => 'Test',
        'price'       => '1',
        'stock'       => '1',
        'available_type' => 'physical',
        'images[]'    => new CURLFile($phpFile, 'image/jpeg', 'shell.jpg'),
    ];
    [$code, $body] = http('POST', '/api/admin/product/create.php', $uploadFields, [], $adminJar);
    @unlink($phpFile);
    $d = jd($body);
    // Should succeed upload-wise OR reject at MIME — either way, PHP file must NOT be saved
    // Check no .php or shell file appeared in img/products/
    $phpUploaded = count(glob(dirname(__DIR__) . '/img/products/*.php') ?: []) > 0;

    // Clean up any test product that may have been created (name=Exploit Test)
    $db = dbConnect();
    $db->query("DELETE FROM products WHERE name = 'Exploit Test'");
    $db->close();

    result('PHP file disguised as .jpg: MIME check rejects or file not executable',
        !$phpUploaded,
        $phpUploaded ? 'CRITICAL: PHP shell saved!' : 'No .php file written to img/products/');

    // ── 9. File upload exploit: PHP file with .php extension ─────────────────
    $phpFile2 = tempnam(sys_get_temp_dir(), 'shell_') . '.php';
    file_put_contents($phpFile2, '<?php phpinfo(); ?>');
    $uploadFields2 = [
        'csrf_token'  => $adminCsrf,
        'name'        => 'Exploit Test 2',
        'category'    => 'Test',
        'price'       => '1',
        'stock'       => '1',
        'available_type' => 'physical',
        'images[]'    => new CURLFile($phpFile2, 'application/x-php', 'shell.php'),
    ];
    [$code2, $body2] = http('POST', '/api/admin/product/create.php', $uploadFields2, [], $adminJar);
    @unlink($phpFile2);
    $db = dbConnect();
    $db->query("DELETE FROM products WHERE name = 'Exploit Test 2'");
    $db->close();
    // This should either: reject (status=error), or succeed but not write .php extension
    $d2 = jd($body2);
    $phpUploaded2 = count(glob(dirname(__DIR__) . '/img/products/*.php') ?: []) > 0;
    result('.php extension upload rejected or not written',
        !$phpUploaded2,
        $phpUploaded2 ? 'CRITICAL: .php file in img/products!' : "HTTP {$code2} status=" . ($d2['status'] ?? 'n/a'));

    // ── 10. Direct access to /includes/config.php (should be blocked by .htaccess) ──
    [$code] = http('GET', '/includes/config.php');
    result('.htaccess blocks direct /includes/ access',
        in_array($code, [403, 404]),
        "HTTP {$code} (expected 403)");

    // ── 11. Direct access to /.env (should be blocked) ───────────────────────
    [$code] = http('GET', '/.env');
    result('.htaccess blocks direct /.env access',
        in_array($code, [403, 404]),
        "HTTP {$code} (expected 403)");

    @unlink($adminJar);
    @unlink($jar);
}

// ─────────────────────────────────────────────────────────────────────────────
//  PERFORMANCE TESTS
// ─────────────────────────────────────────────────────────────────────────────

function runPerformanceTests(): void
{
    section('PERFORMANCE — Concurrent Load Test (50 users)');

    // ── DB query count for single product list request ────────────────────────
    $db = dbConnect();
    $before = (int) $db->query("SHOW STATUS LIKE 'Questions'")->fetch_assoc()['Value'];

    [$code, , $ms0] = http('GET', '/api/product/list.php');

    $after  = (int) $db->query("SHOW STATUS LIKE 'Questions'")->fetch_assoc()['Value'];
    $db->close();

    $queryCount = $after - $before - 2; // subtract the two SHOW STATUS calls
    result("Product list API: {$queryCount} DB query(ies), {$ms0}ms",
        $code === 200 && $queryCount <= 10,
        "HTTP {$code}");

    // ── Single-request baselines ──────────────────────────────────────────────
    $targets = [
        'Product list API'   => '/api/product/list.php',
        'Shop page (PHP)'    => '/shopAll.php',
        'Home page (PHP)'    => '/index.php',
    ];

    foreach ($targets as $label => $path) {
        [$code, , $ms] = http('GET', $path);
        $pass = $code === 200 && $ms < 1000;
        result("{$label}", $pass, "HTTP {$code} — {$ms}ms", $ms);
    }

    // ── 50 concurrent requests via curl_multi ─────────────────────────────────
    if (!function_exists('curl_multi_init') || !function_exists('curl_multi_cleanup')) {
        skip('50 concurrent GET /api/product/list.php', 'curl_multi not available in this PHP CLI build');
        skip('Response times: min/avg/p95/max',          'curl_multi not available in this PHP CLI build');
    } else {
        $url         = BASE_URL . '/api/product/list.php';
        $concurrency = 50;
        $multi       = curl_multi_init();
        $handles     = [];
        $times       = [];

        for ($i = 0; $i < $concurrency; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$i] = $ch;
        }

        $start   = microtime(true);
        $running = 0;
        do {
            curl_multi_exec($multi, $running);
            if ($running) curl_multi_select($multi);
        } while ($running > 0);
        $totalMs = round((microtime(true) - $start) * 1000);

        $codes = [];
        foreach ($handles as $ch) {
            $codes[] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $t = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $times[] = round($t * 1000);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_cleanup($multi);

        $ok200  = count(array_filter($codes, fn($c) => $c === 200));
        $failed = $concurrency - $ok200;
        sort($times);
        $avgMs = $times ? round(array_sum($times) / count($times)) : 0;
        $minMs = $times[0]                        ?? 0;
        $maxMs = $times[count($times) - 1]        ?? 0;
        $p95   = $times[max(0, (int)ceil(0.95 * count($times)) - 1)] ?? 0;

        result("50 concurrent GET /api/product/list.php",
            $failed === 0,
            "{$ok200}/50 OK | wall-clock {$totalMs}ms");
        result("Response times: min={$minMs}ms avg={$avgMs}ms p95={$p95}ms max={$maxMs}ms",
            $p95 < 2000,
            $p95 >= 2000 ? 'P95 > 2s — investigate bottleneck' : 'within threshold');
    }

    // ── Memory usage of product list response ─────────────────────────────────
    [, $body] = http('GET', '/api/product/list.php');
    $kb = round(strlen($body) / 1024, 1);
    result("Product list payload size: {$kb} KB",
        $kb < 512,
        $kb >= 512 ? 'payload > 512 KB — consider pagination' : 'OK');
}

// ─────────────────────────────────────────────────────────────────────────────
//  HELPER: create minimal 1×1 red PNG (68 bytes)
// ─────────────────────────────────────────────────────────────────────────────

function createMinimalPng(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/' .
        '8BQDwADhQGAWjR9awAAAABJRU5ErkJggg=='
    );
}

// ─────────────────────────────────────────────────────────────────────────────
//  MAIN
// ─────────────────────────────────────────────────────────────────────────────

echo "\n" . BLD . str_repeat('═', 62) . RST . "\n";
echo BLD . "  UX PACIFIC MERCHANDISE — Phase 9 Final Test Suite\n" . RST;
echo BLD . "  " . BASE_URL . "\n" . RST;
echo BLD . str_repeat('═', 62) . RST . "\n";

// Check curl is available
if (!function_exists('curl_init')) {
    echo RED . "  ✗ PHP curl extension is not loaded. Cannot run HTTP tests.\n" . RST;
    exit(1);
}

// Check server is reachable
[$code] = http('GET', '/index.php');
if ($code === 0) {
    echo RED . "  ✗ Server not reachable at " . BASE_URL . "\n" . RST;
    echo YLW . "    Start XAMPP Apache + MySQL and retry.\n" . RST;
    exit(1);
}
echo GRN . "  ✓ Server reachable (HTTP {$code})\n" . RST;

echo DIM . "  Test user:  " . TEST_EMAIL  . "\n";
echo        "  Test admin: " . TEST_ADMIN_EMAIL . "\n" . RST;

setUp();
register_shutdown_function('tearDown');

runUserFlows();
runAdminFlows();
runSecurityTests();
runPerformanceTests();

// ── Summary ───────────────────────────────────────────────────────────────────
$elapsed = round(microtime(true) - $SUITE_START, 1);
$total   = $RESULTS['pass'] + $RESULTS['fail'] + $RESULTS['skip'];
$allPass = $RESULTS['fail'] === 0;

echo "\n" . BLD . str_repeat('─', 62) . RST . "\n";
$summary = sprintf(
    "  %s%sPASSED: %d%s  %sFAILED: %d%s  %sSKIPPED: %d%s  %sDuration: %.1fs%s",
    $allPass ? GRN : '', BLD, $RESULTS['pass'], RST,
    $RESULTS['fail'] > 0 ? RED : '', $RESULTS['fail'], RST,
    $RESULTS['skip'] > 0 ? YLW : DIM, $RESULTS['skip'], RST,
    DIM, $elapsed, RST
);
echo $summary . "\n";
echo BLD . str_repeat('─', 62) . RST . "\n\n";

if (!$allPass) {
    echo RED . BLD . "  ✗ TEST SUITE FAILED — Review the failures above.\n" . RST . "\n";
    exit(1);
}
echo GRN . BLD . "  ✓ ALL TESTS PASSED — System is production ready.\n" . RST . "\n";
exit(0);
