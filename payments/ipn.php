<?php
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/PaymentService.php';
require_once '../includes/services/PesapalService.php';

ensurePlatformStructures($pdo);
header('Content-Type: application/json');

$trackingId = trim((string) ($_GET['OrderTrackingId'] ?? $_POST['OrderTrackingId'] ?? $_GET['orderTrackingId'] ?? $_POST['orderTrackingId'] ?? ''));
$reference = trim((string) ($_GET['OrderMerchantReference'] ?? $_POST['OrderMerchantReference'] ?? $_GET['orderMerchantReference'] ?? $_POST['orderMerchantReference'] ?? ''));

if ($trackingId === '' && $reference === '') {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Missing payment identifiers']);
    exit();
}

try {
    $payment = $reference !== ''
        ? PaymentService::findPaymentByReference($pdo, $reference)
        : PaymentService::findPaymentByTrackingId($pdo, $trackingId);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'message' => 'Payment not found']);
        exit();
    }

    $gatewayStatus = PesapalService::getTransactionStatus($trackingId ?: ($payment['tracking_id'] ?? $payment['pesapal_txn_id'] ?? ''));
    $paymentStatusDescription = $gatewayStatus['payment_status_description'] ?? $gatewayStatus['status'] ?? 'PENDING';
    $localStatus = PesapalService::mapStatusToLocal($paymentStatusDescription);

    PaymentService::transitionPaymentStatus($pdo, $payment['id'], $localStatus, null, 'PesaPal IPN processed.', [
        'provider' => 'pesapal',
        'tracking_id' => $trackingId ?: ($payment['tracking_id'] ?? ''),
        'pesapal_txn_id' => $trackingId ?: ($payment['pesapal_txn_id'] ?? ''),
        'gateway_status' => $paymentStatusDescription,
        'merchant_reference' => $payment['reference'],
        'ipn_payload' => [
            'get' => $_GET,
            'post' => $_POST,
        ],
    ]);

    echo json_encode([
        'status' => 200,
        'message' => 'IPN processed',
        'payment_status' => $localStatus,
        'reference' => $payment['reference'],
    ]);
} catch (Throwable $e) {
    error_log('Pesapal IPN error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Unable to process IPN']);
}
