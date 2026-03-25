<?php
/**
 * api/payment/razorpay-verify.php
 *
 * Verifies the Razorpay payment signature and converts the pending order
 * to a confirmed, paid order.
 *
 * POST (authenticated, CSRF required)
 * Request body: {
 *   "razorpay_order_id":   "order_xxx",
 *   "razorpay_payment_id": "pay_xxx",
 *   "razorpay_signature":  "sig_xxx",
 *   "order_id":            123          <- our internal order id
 * }
 */

header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/helpers.php';

requireUserAuth();
validateCsrf();

$input = json_decode(file_get_contents('php://input'), true);

$rzpOrderId   = $input['razorpay_order_id']   ?? '';
$rzpPaymentId = $input['razorpay_payment_id'] ?? '';
$rzpSignature = $input['razorpay_signature']  ?? '';
$internalOrderId = (int) ($input['order_id'] ?? 0);

if (empty($rzpOrderId) || empty($rzpPaymentId) || empty($rzpSignature) || $internalOrderId <= 0) {
    sendResponse('error', 'Missing required payment fields', null, 400);
}

$keySecret = getenv('RAZORPAY_KEY_SECRET') ?: '';
if (empty($keySecret) || str_contains($keySecret, 'REPLACE_ME')) {
    sendResponse('error', 'Payment gateway not configured', null, 503);
}

// ── Verify signature ──────────────────────────────────────────────────────────
// Razorpay signature = HMAC-SHA256(order_id + "|" + payment_id, key_secret)
$expectedSignature = hash_hmac(
    'sha256',
    $rzpOrderId . '|' . $rzpPaymentId,
    $keySecret
);

if (!hash_equals($expectedSignature, $rzpSignature)) {
    error_log("Razorpay signature mismatch. order={$internalOrderId} payment={$rzpPaymentId}");
    sendResponse('error', 'Payment verification failed: invalid signature', null, 400);
}

// ── Update our order ──────────────────────────────────────────────────────────
$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    UPDATE orders
    SET status = 'confirmed',
        payment_method = 'razorpay',
        payment_id = ?,
        razorpay_order_id = ?
    WHERE id = ? AND user_id = ? AND status IN ('pending', 'awaiting_payment')
");

// Add payment_id and razorpay_order_id columns if they don't exist yet
// (see migrations/production_fixes.sql for ALTER TABLE)
$stmt->bind_param('ssii', $rzpPaymentId, $rzpOrderId, $internalOrderId, $userId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    $stmt->close();
    sendResponse('error', 'Order not found or already processed', null, 404);
}

$stmt->close();

// ── Clear the cart after successful payment ───────────────────────────────────
$clearStmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
$clearStmt->bind_param('i', $userId);
$clearStmt->execute();
$clearStmt->close();

$conn->close();

sendResponse('success', 'Payment verified and order confirmed', [
    'order_id'    => $internalOrderId,
    'payment_id'  => $rzpPaymentId
]);
