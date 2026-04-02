<?php
require_once '../config/db.php';
require dirname(__DIR__) . '/vendor/autoload.php';

use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

$PayerID = $_GET['PayerID'] ?? '';
$paymentId = $_GET['paymentId'] ?? '';
$token = $_GET['token'] ?? '';

if (!($PayerID && $paymentId)) {
    http_response_code(400);
    echo 'Missing PayPal parameters';
    exit();
}

$paypal_config = require dirname(__DIR__) . '/config/paypal.php';

try {
    $apiContext = new ApiContext(
        new OAuthTokenCredential(
            $paypal_config['client_id'],
            $paypal_config['client_secret']
        )
    );
    $apiContext->setConfig(['mode' => $paypal_config['mode']]);
    
    $payment = Payment::get($paymentId, $apiContext);
    
    $execution = new PaymentExecution();
    $execution->setPayerId($PayerID);
    
    $result = $payment->execute($execution, $apiContext);
    
    if ($result->getState() === 'approved') {
        $paymentRecord = $payment->getTransactions()[0];
        $reference = $paymentRecord->getCustom();
        
        // Update database
        $stmt = $pdo->prepare("UPDATE payments SET status = 'paid', paypal_payment_id = ? WHERE reference = ?");
        $stmt->execute([$paymentId, $reference]);
        
        $stmt = $pdo->prepare("UPDATE sessions SET payment_status = 'paid' WHERE id = (SELECT session_id FROM payments WHERE reference = ?)");
        $stmt->execute([$reference]);
        
        header('Location: success.php?ref=' . $reference);
    } else {
        header('Location: fail.php?reason=not_approved');
    }
    
} catch (Exception $e) {
    error_log('PayPal callback error: ' . $e->getMessage());
    http_response_code(500);
    header('Location: fail.php?reason=error');
}
?>

