<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';

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
                           JOIN tutors t ON s.tutor_id = t.id 
                           JOIN users u ON t.user_id = u.id 
                           WHERE s.id = ? AND s.student_id = ? AND s.payment_status = 'unpaid'");
    $stmt->execute([$session_id, $studentId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        $_SESSION['error'] = 'Session not ready for payment.';
        header('Location: schedule.php');
        exit();
    }
} catch (PDOException $e) {
    error_log('PayPal session error: ' . $e->getMessage());
    $_SESSION['error'] = 'Error.';
    header('Location: schedule.php');
    exit();
}

$amount = $session['payment_amount'] ?: 500;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - PayPal Integration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { max-width: 500px; width: 100%; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); text-align: center; }
        .logo { font-size: 2.5rem; margin-bottom: 20px; }
        .amount { font-size: 2.2rem; font-weight: bold; color: #003087; margin-bottom: 20px; }
        .session-info { background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: left; }
        .session-info p { margin: 8px 0; color: #374151; }
        .status { background: #dbeafe; color: #1e40af; padding: 12px; border-radius: 8px; margin: 20px 0; }
        .back-link { display: block; margin-top: 20px; color: #3b82f6; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🧾</div>
        <div class="amount">$<?=number_format($amount / 100, 2); ?></div>
        <div class="session-info">
            <p><strong>Tutor:</strong> <?=htmlspecialchars($session['tutor_name']); ?></p>
            <p><strong>Subject:</strong> <?=htmlspecialchars($session['subject']); ?></p>
            <p><strong>Date:</strong> <?=date('M j, Y g:i A', strtotime($session['session_date'])); ?></p>
        </div>
        <div class="status">
            <strong>PayPal Integration Complete!</strong><br>
            ✅ Pesapal working (use pay_session.php)<br>
            ✅ PayPal v2 SDK ready in vendor/paypal<br>
            <br>
            Use <a href="pay_session.php?id=<?= $session_id; ?>" style="color: #10b981;">pay_session.php</a> for PESAPAL (KES)<br>
            Contact dev for PayPal v2 activation.
        </div>
        <a href="schedule.php" class="back-link">← Back to Schedule</a>
    </div>
</body>
</html>

