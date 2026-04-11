<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/PaymentService.php';
require_once '../includes/services/PesapalService.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

$session_id = intval($_GET['id'] ?? $_POST['session_id'] ?? 0);
$studentId = getStudentId($pdo, $_SESSION['user_id']);
$message = '';
$messageType = '';

if (!$session_id || !$studentId) {
    header('Location: schedule.php');
    exit();
}

$session = PaymentService::findStudentSessionForPayment($pdo, $session_id, $studentId);
$pesapalReadiness = PesapalService::getReadiness();

if (!$session) {
    header('Location: schedule.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = PaymentService::submitSessionPayment($pdo, $session, $studentId, $_SESSION['user_id'], 'pesapal');
    $session = $result['session'];
    $message = $result['message'];
    $messageType = $result['type'];

    if (!empty($result['redirect_url'])) {
        header('Location: ' . $result['redirect_url']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay for Session - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)), url('../uploads/image003.jpg') center/cover no-repeat; margin:0; padding:20px; color:#1f2937; }
        .container { max-width:720px; margin:0 auto; background:rgba(255,255,255,0.96); border-radius:20px; box-shadow:0 24px 50px rgba(15,23,42,0.18); overflow:hidden; }
        .header { background:linear-gradient(135deg,#2563eb,#1d4ed8); color:white; padding:30px; text-align:center; }
        .content { padding:30px; }
        .card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:22px; margin-bottom:20px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; background:#2563eb; color:white; border:none; border-radius:12px; padding:12px 20px; font-weight:700; cursor:pointer; text-decoration:none; }
        .btn:hover { background:#1d4ed8; }
        .message { padding:16px; border-radius:12px; margin-bottom:20px; }
        .success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .muted { color:#64748b; }
        .warning { background:#fff7ed; color:#9a3412; border:1px solid #fdba74; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Session Payment</h1>
            <p>Pay securely with PesaPal.</p>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!$pesapalReadiness['ready']): ?>
                <div class="message warning">
                    <strong>PesaPal setup is incomplete.</strong><br>
                    <?php echo htmlspecialchars(implode(' ', $pesapalReadiness['issues'])); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-top:0;"><?php echo htmlspecialchars($session['subject']); ?></h3>
                <p><strong>Tutor:</strong> <?php echo htmlspecialchars($session['tutor_name']); ?></p>
                <p><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?></p>
                <p><strong>Amount:</strong> KSh <?php echo number_format((float) ($session['payment_amount'] ?: $session['amount'] ?: 500), 2); ?></p>
                <p><strong>Payment Status:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $session['payment_status'] ?: 'unpaid'))); ?></p>
            </div>

            <?php if ($session['payment_status'] === 'paid'): ?>
                <a href="schedule.php" class="btn">Back to Schedule</a>
            <?php elseif ($session['payment_status'] === 'processing'): ?>
                <div class="card">
                    <strong>Verification in progress</strong>
                    <p class="muted" style="margin-bottom:0;">Your PesaPal payment is awaiting confirmation. You can return to your schedule while it updates.</p>
                </div>
                <a href="schedule.php" class="btn">Back to Schedule</a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="session_id" value="<?php echo (int) $session_id; ?>">
                    <div class="card">
                        <strong>PesaPal Checkout</strong>
                        <p class="muted" style="margin-bottom:0;">You will be redirected to PesaPal to complete the payment, then returned to SOTMS Pro.</p>
                    </div>
                    <?php if (!$pesapalReadiness['ready']): ?>
                        <a href="schedule.php" class="btn" style="background:#475569;">Back to Schedule</a>
                    <?php else: ?>
                        <button type="submit" class="btn">Continue to PesaPal</button>
                    <?php endif; ?>
                    <a href="schedule.php" class="btn" style="background:#475569; margin-left:8px;">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
