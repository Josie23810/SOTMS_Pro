<?php
// Pesapal callback - VERIFY PAYMENT
require_once '../config/db.php';
require_once '../config/pesapal.php';
require_once '../includes/services/PaymentService.php';
require_once '../vendor/autoload.php';
use Pesapal\Pesapal;

$pesapal_txn_id = $_GET['pesapal_transaction_tracking_id'] ?? '';
$pesapal_merchant_reference = $_GET['pesapal_merchant_reference'] ?? '';

if (empty($pesapal_txn_id) || empty($pesapal_merchant_reference)) {
    http_response_code(400);
    echo 'Invalid callback data';
    exit();
}

// Fetch payment
try {
    $payment = PaymentService::findPaymentByReference($pdo, $pesapal_merchant_reference);
    
    if (!$payment || !in_array($payment['status'], ['pending', 'gateway_submitted'], true)) {
        http_response_code(400);
        echo 'Payment not found or already processed';
        exit();
    }
    
    // Real Pesapal verify
    $pesapal_config = require '../config/pesapal.php';
    $status = Pesapal::transactionStatus($pesapal_txn_id, $pesapal_config['consumer_key'], $pesapal_config['consumer_secret'], $pesapal_config['environment']);
    
    $payment_status = ($status === 'COMPLETED' || $status === 'PAID') ? 'paid' : 'failed';
    PaymentService::transitionPaymentStatus($pdo, $payment['id'], $payment_status, null, 'Pesapal callback verification processed.', [
        'pesapal_txn_id' => $pesapal_txn_id,
        'gateway_status' => $status
    ]);
    
    echo 'OK';
    
} catch (PDOException | Exception $e) {
    error_log('Callback error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error processing callback';
}
?>

