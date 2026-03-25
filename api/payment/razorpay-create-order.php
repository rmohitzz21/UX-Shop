<?php
/**
 * api/payment/razorpay-create-order.php
 *
 * Creates a Razorpay order for the current cart total.
 * Returns the Razorpay order_id used to initialise the checkout widget.
 *
 * POST (authenticated, CSRF required)
 * Request body: { "amount": 1999.00, "currency": "INR" }
 * Response:     { status, message, data: { razorpay_order_id, amount, currency, key_id } }
 */

header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/helpers.php';

requireUserAuth();
validateCsrf();

$input = json_decode(file_get_contents('php://input'), true);

$amount   = isset($input['amount'])   ? round((float)$input['amount'], 2) : 0;
$currency = isset($input['currency']) ? strtoupper($input['currency'])    : 'INR';

if ($amount <= 0) {
    sendResponse('error', 'Invalid amount', null, 400);
}

$allowedCurrencies = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD'];
if (!in_array($currency, $allowedCurrencies)) {
    sendResponse('error', 'Unsupported currency', null, 400);
}

$keyId     = getenv('RAZORPAY_KEY_ID')     ?: '';
$keySecret = getenv('RAZORPAY_KEY_SECRET') ?: '';

if (empty($keyId) || empty($keySecret) || str_contains($keyId, 'REPLACE_ME')) {
    sendResponse('error', 'Payment gateway not configured', null, 503);
}

// Razorpay expects amount in smallest currency unit (paise for INR)
$amountInPaise = (int) round($amount * 100);

$receipt = 'rcpt_' . uniqid('', true);

$postData = json_encode([
    'amount'   => $amountInPaise,
    'currency' => $currency,
    'receipt'  => $receipt,
    'notes'    => [
        'user_id' => (int) $_SESSION['user_id'],
        'source'  => 'uxpacific_shop'
    ]
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_USERPWD        => "{$keyId}:{$keySecret}",
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    error_log("Razorpay curl error: {$curlErr}");
    sendResponse('error', 'Payment gateway unavailable', null, 503);
}

$rzpResponse = json_decode($response, true);

if ($httpCode !== 200 || empty($rzpResponse['id'])) {
    $errMsg = $rzpResponse['error']['description'] ?? 'Failed to create payment order';
    error_log("Razorpay error: " . $response);
    sendResponse('error', $errMsg, null, 502);
}

sendResponse('success', 'Payment order created', [
    'razorpay_order_id' => $rzpResponse['id'],
    'amount'            => $amount,
    'amount_in_paise'   => $amountInPaise,
    'currency'          => $currency,
    'key_id'            => $keyId
]);
