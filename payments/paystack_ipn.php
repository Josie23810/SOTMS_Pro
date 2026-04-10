<?php
require_once __DIR__ . '/../includes/db.php';

// Paystack secret key
$secret_key = "sk_test_ca5db3a766442480ecfa8d520ad10c540f70dbb3";

// Get request payload
$input = @file_get_contents("php://input");
$event = json_decode($input, true);

// Log webhook for debugging
file_put_contents("paystack_ipn_log.txt", json_encode($event) . PHP_EOL, FILE_APPEND);

// Verify Paystack signature
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if (!$signature || $signature !== hash_hmac('sha512', $input, $secret_key)) {
    http_response_code(400);
    echo "Invalid signature";
    exit;
}

// Process payment
if (isset($event['event']) && $event['event'] === 'charge.success') {
    $tx_ref = $event['data']['reference']; // tracking_id in your DB
    $status = $event['data']['status']; // should be 'success'

    if ($status === 'success') {
        $stmt = $conn->prepare("UPDATE payments SET status = 'PAID' WHERE tracking_id = ?");
        $stmt->bind_param("s", $tx_ref);
        $stmt->execute();
        file_put_contents("paystack_ipn_log.txt", "Payment SUCCESS for: $tx_ref" . PHP_EOL, FILE_APPEND);
    }
}

http_response_code(200);
echo "Webhook received successfully";
exit;