<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require dirname(__DIR__) . '/vendor/autoload.php'; // Pesapal
require dirname(__DIR__) . '/vendor/pesapal-php/Pesapal.php';

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
    error_log('Pay session error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error.';
    header('Location: schedule.php');
    exit();
}

// Pesapal config
$pesapal_config = require '../config/pesapal.php';
$consumer_key = $pesapal_config['consumer_key'];
$consumer_secret = $pesapal_config['consumer_secret'];
$environment = $pesapal_config['environment'];
$amount = $session['payment_amount'] ?: 500;
$description = "SOTMS Session #{$session['id']} - {$session['subject']}";
$callback_url = $pesapal_config['callback_url'] ?? 'https://kiesha-prerational-duke.ngrok-free.dev/payments/callback.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create payment record first
    $reference = 'SOTMS-' . time() . '-' . $session_id;
    try {
        $stmt = $pdo->prepare("INSERT INTO payments (session_id, student_id, tutor_id, amount, reference, status) VALUES (?, ?, ?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE status = 'pending'");
        $stmt->execute([$session_id, $studentId, $session['tutor_id'] ?? 0, $amount, $reference]);
    } catch (PDOException $e) {
        error_log('Payment record error: ' . $e->getMessage());
        $_SESSION['error'] = 'Payment record creation failed.';
        header('Location: schedule.php');
        exit();
    }
    
    // Test Pesapal with demo data first
    try {
        $pesapal = new \Pesapal\Pesapal($consumer_key, $consumer_secret, $environment);
        
        // Minimal test order
        $test_data = [
            'id' => $reference,
            'currency' => 'KES',
            'amount' => (string)$amount,
            'description' => substr($description, 0, 100),
            'callback_url' => $callback_url,
            'notification_id' => $callback_url,
            'billing_address' => [
                'email' => $_SESSION['email'] ?? 'test@test.com',
                'phone_number' => '0712345678',
                'country_code' => 'KE',
                'first_name' => 'Test',
                'middle_name' => '',
                'last_name' => 'User'
            ]
        ];
        
        error_log('Pesapal test data: ' . json_encode($test_data));
        $token = $pesapal->submitOrder($test_data);
        
        error_log('Pesapal token response: ' . print_r($token, true));
        
        if ($token) {
            $baseUrl = $environment === 'sandbox' ? 'https://demo.pesapal.com/API/v3' : 'https://www.pesapal.com/API/v3';
            $pesapal_url = $baseUrl . '/ProcessPayment?token=' . $token;
            header("Location: $pesapal_url");
            exit();
        } else {
            $_SESSION['error'] = 'Payment token generation failed. Pesapal returned empty token.';
            header('Location: schedule.php');
            exit();
        }
    } catch (Exception $e) {
        error_log('Pesapal full error: ' . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
        $_SESSION['error'] = 'Payment service temporarily unavailable. Please try again in a few minutes or contact support.';
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
    <title>Pay Session - SOTMS PRO</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { max-width: 500px; width: 100%; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); text-align: center; }
        .logo { font-size: 2.5rem; margin-bottom: 20px; }
        .amount { font-size: 2.2rem; font-weight: bold; color: #10b981; margin-bottom: 20px; }
        .session-info { background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: left; }
        .session-info p { margin: 8px 0; color: #374151; }
        .btn-pay { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 16px 40px; font-size: 18px; font-weight: 600; border: none; border-radius: 12px; cursor: pointer; width: 100%; transition: all 0.3s; box-shadow: 0 10px 20px rgba(16,185,129,0.3); margin-bottom: 10px; }
        .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(16,185,129,0.4); }
        .btn-paypal { background: linear-gradient(135deg, #003087, #1e3a8a); color: white; padding: 16px 40px; font-size: 18px; font-weight: 600; border: none; border-radius: 12px; cursor: pointer; width: 100%; transition: all 0.3s; box-shadow: 0 10px 20px rgba(0,48,135,0.3); margin-bottom: 10px; }
        .btn-paypal:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(0,48,135,0.4); }
        .back-link { display: block; margin-top: 20px; color: #3b82f6; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin: 20px 0; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">💳</div>
        <div class="amount">KSh <?=number_format($amount, 2); ?></div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?=htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="session-info">
            <p><strong>Tutor:</strong> <?=htmlspecialchars($session['tutor_name']); ?></p>
            <p><strong>Subject:</strong> <?=htmlspecialchars($session['subject']); ?></p>
            <p><strong>Date:</strong> <?=date('M j, Y g:i A', strtotime($session['session_date'])); ?></p>
        </div>
        
        <form method="POST">
            <button type="submit" class="btn-pay">Pay Now → Pesapal (KES)</button>
        </form>
        
        <a href="paypal_session.php?id=<?= $session_id; ?>" class="btn-paypal">Pay with PayPal (USD)</a>
        
        <p style="color: #6b7280; margin-top: 20px; font-size: 0.9rem;">
            Both options secure. Pesapal for KES, PayPal for USD via ngrok.
        </p>
        
        <a href="schedule.php" class="back-link">← Back to Schedule</a>
    </div>
</body>
</html>

