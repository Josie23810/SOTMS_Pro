<?php
require_once '../config/db.php';
require_once '../includes/services/PaymentService.php';
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

        $paymentRow = PaymentService::findPaymentByReference($pdo, $reference);
        if ($paymentRow) {
            PaymentService::transitionPaymentStatus($pdo, $paymentRow['id'], 'paid', null, 'PayPal callback approved the payment.', [
                'paypal_payment_id' => $paymentId,
                'payer_id' => $PayerID
            ]);
        }
        
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

