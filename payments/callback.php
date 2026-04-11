<?php
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/PaymentService.php';
require_once '../includes/services/PesapalService.php';

ensurePlatformStructures($pdo);

$trackingId = trim((string) ($_GET['OrderTrackingId'] ?? $_GET['orderTrackingId'] ?? $_GET['pesapal_transaction_tracking_id'] ?? ''));
$reference = trim((string) ($_GET['OrderMerchantReference'] ?? $_GET['orderMerchantReference'] ?? $_GET['pesapal_merchant_reference'] ?? ''));

if ($trackingId === '' && $reference === '') {
    header('Location: fail.php?reason=missing_reference');
    exit();
}

try {
    $payment = $reference !== ''
        ? PaymentService::findPaymentByReference($pdo, $reference)
        : PaymentService::findPaymentByTrackingId($pdo, $trackingId);

    if (!$payment) {
        header('Location: fail.php?reason=payment_not_found');
        exit();
    }

    $gatewayStatus = PesapalService::getTransactionStatus($trackingId ?: ($payment['tracking_id'] ?? $payment['pesapal_txn_id'] ?? ''));
    $paymentStatusDescription = $gatewayStatus['payment_status_description'] ?? $gatewayStatus['status'] ?? 'PENDING';
    $localStatus = PesapalService::mapStatusToLocal($paymentStatusDescription);

    PaymentService::transitionPaymentStatus($pdo, $payment['id'], $localStatus, null, 'PesaPal callback verification processed.', [
        'provider' => 'pesapal',
        'tracking_id' => $trackingId ?: ($payment['tracking_id'] ?? ''),
        'pesapal_txn_id' => $trackingId ?: ($payment['pesapal_txn_id'] ?? ''),
        'gateway_status' => $paymentStatusDescription,
        'merchant_reference' => $payment['reference'],
        'callback_payload' => $_GET,
    ]);

    if ($localStatus === 'paid') {
        header('Location: success.php?ref=' . rawurlencode($payment['reference']));
        exit();
    }

    if ($localStatus === 'gateway_submitted') {
        header('Location: success.php?ref=' . rawurlencode($payment['reference']) . '&state=processing');
        exit();
    }

    header('Location: fail.php?ref=' . rawurlencode($payment['reference']) . '&reason=' . rawurlencode(strtolower((string) $paymentStatusDescription)));
    exit();
} catch (Throwable $e) {
    error_log('Pesapal callback error: ' . $e->getMessage());
    header('Location: fail.php?reason=callback_error');
    exit();
}
