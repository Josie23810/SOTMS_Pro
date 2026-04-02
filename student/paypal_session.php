<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require dirname(__DIR__) . '/vendor/autoload.php';

use PayPal\Api\Payer;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;

checkAccess(['student']);

$session_id = intval($_GET['id'] ?? 0);
$studentId = getStudentId($pdo, $_SESSION['user_id']);

if (!$session_id || !$studentId) {
    $_SESSION['error'] = 'Invalid session.';
    header('Location: schedule.php');
    exit();
}

// Fetch session
try {
    $stmt = $pdo->prepare("SELECT s.*, u.name as tutor_name 
                           FROM sessions s 
                           LEFT JOIN tutors t ON s.tutor_id = t.id 
                           LEFT JOIN users u ON t.user_id = u.id 
                           WHERE s.id = ? AND s.student_id = ? AND (s.payment_status = 'unpaid' OR s.payment_status IS NULL)");
    $stmt->execute([$session_id, $studentId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        $_SESSION['error'] = 'Session not ready for payment or already paid.';
        header('Location: schedule.php');
        exit();
    }
} catch (PDOException $e) {
    error_log('PayPal session error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error.';
    header('Location: schedule.php');
    exit();
}

$amount = $session['payment_amount'] ?: 500;
$paypal_config = require '../config/paypal.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create payment record
    $reference = 'SOTMS-' . time() . '-' . $session_id;
    try {
        $stmt = $pdo->prepare("INSERT INTO payments (session_id, student_id, tutor_id, amount, reference, status) VALUES (?, ?, ?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE status = 'pending'");
        $stmt->execute([$session_id, $studentId, $session['tutor_id'] ?? 0, $amount, $reference]);
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Payment record creation failed.';
        header('Location: schedule.php');
        exit();
    }
    
    // PayPal Payment Creation
    try {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');
        
        $amount_obj = new Amount();
        $amount_obj->setTotal(number_format($amount / 100, 2))->setCurrency('USD');
        
        $transaction = new Transaction();
        $transaction->setAmount($amount_obj)
                    ->setDescription($description = "SOTMS Session #{$session['id']} - {$session['subject']}")
                    ->setCustom($reference);
        
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($paypal_config['callback_url'] . '?success=1')
                     ->setCancelUrl($paypal_config['callback_url'] . '?cancel=1');
        
        $payment = new Payment();
        $payment->setIntent('sale')
                ->setPayer($payer)
                ->setTransactions([$transaction])
                ->setRedirectUrls($redirectUrls);
        
        $apiContext = new ApiContext(
            new OAuthTokenCredential(
                $paypal_config['client_id'],
                $paypal_config['client_secret']
            )
        );
        $apiContext->setConfig(['mode' => $paypal_config['mode'], 'log.LogEnabled' => true, 'log.FileName' => '../paypal.log', 'log.LogLevel' => 'DEBUG']);
        
        $payment->create($apiContext);
        
        header('Location: ' . $payment->getApprovalLink());
        exit();
    } catch (Exception $e) {
        error_log('PayPal error: ' . $e->getMessage());
        $_SESSION['error'] = 'PayPal error: ' . $e->getMessage();
        header('Location: schedule.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay with PayPal - SOTMS PRO</title>
</head>
<body>
    <div style="max-width:600px; margin:40px auto; padding:40px; background:white; border-radius:16px; box-shadow:0 20px 40px rgba(0,0,0,0.1); text-align:center;">
        <h1>💳 Pay $<?=number_format(($amount / 100), 2); ?></h1>
        <div style="background:#f8fafc; padding:24px; border-radius:12px; margin:30px 0; text-align:left;">
            <p><strong>Tutor:</strong> <?=$session['tutor_name']?></p>
            <p><strong>Subject:</strong> <?=$session['subject']?></p>
            <p><strong>Date:</strong> <?=date('M j, Y g:i A', strtotime($session['session_date']))?></p>
        </div>
        <form method="POST">
            <button type="submit" style="background:#003087; color:white; padding:16px 32px; font-size:18px; border:none; border-radius:12px; cursor:pointer; font-weight:700; width:100%;">
                Pay with PayPal → Secure Checkout
            </button>
        </form>
        <p style="color:#6b7280; margin-top:20px;">
            Secure PayPal payment. Redirects to PayPal sandbox via ngrok.
        </p>
        <a href="schedule.php" style="display:block; margin-top:20px; color:#2563eb;">← Back to Schedule</a>
    </div>
</body>
</html>

