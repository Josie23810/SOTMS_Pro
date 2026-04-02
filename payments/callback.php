<?php
// Pesapal callback - VERIFY PAYMENT
require_once '../config/db.php';
require_once '../config/pesapal.php';
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
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE reference = ?");
    $stmt->execute([$pesapal_merchant_reference]);
    $payment = $stmt->fetch();
    
    if (!$payment || $payment['status'] !== 'pending') {
        http_response_code(400);
        echo 'Payment not found or already processed';
        exit();
    }

    // Real Pesapal verify
    $pesapal_config = require '../config/pesapal.php';
    $status = Pesapal::transactionStatus($pesapal_txn_id, $pesapal_config['consumer_key'], $pesapal_config['consumer_secret'], $pesapal_config['environment']);
    
    $payment_status = ($status === 'COMPLETED' || $status === 'PAID') ? 'paid' : 'failed';
    $session_id = $payment['session_id'];
    
    // Update payments
    $stmt = $pdo->prepare("UPDATE payments SET status = ?, pesapal_txn_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$payment_status, $pesapal_txn_id, $payment['id']]);
    
    // Update session
    $stmt = $pdo->prepare("UPDATE sessions SET payment_status = ? WHERE id = ?");
    $stmt->execute([$payment_status, $session_id]);
    
    echo 'OK';
    
} catch (PDOException | Exception $e) {
    error_log('Callback error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error processing callback';
}
?>

