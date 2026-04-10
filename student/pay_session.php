<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/PaymentService.php';
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

if (!$session) {
    header('Location: schedule.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['provider'])) {
    $provider = trim($_POST['provider']);
    $result = PaymentService::submitSessionPayment($pdo, $session, $studentId, $_SESSION['user_id'], $provider);
    $session = $result['session'];
    $message = $result['message'];
    $messageType = $result['type'];
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
        select { width:100%; padding:12px; border-radius:10px; border:1px solid #d1d5db; margin-top:8px; margin-bottom:16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Session Payment</h1>
            <p>Record payment for your booked tutoring session.</p>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
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
                    <p style="color:#475569; margin-bottom:0;">Your payment submission is awaiting callback or admin verification. You can return to your schedule while this is processed.</p>
                </div>
                <a href="schedule.php" class="btn">Back to Schedule</a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="session_id" value="<?php echo (int) $session_id; ?>">
                    <label for="provider"><strong>Choose payment channel</strong></label>
                    <select name="provider" id="provider" required>
                        <option value="">Select a payment method</option>
                        <?php foreach (PaymentService::supportedProviders() as $providerValue => $providerLabel): ?>
                            <option value="<?php echo htmlspecialchars($providerValue); ?>"><?php echo htmlspecialchars($providerLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p style="color:#64748b; margin-bottom:18px;">This local build submits the payment into the platform workflow, where callbacks or an admin reviewer can verify, fail, or refund it without changing the student flow.</p>
                    <button type="submit" class="btn">Submit Payment</button>
                    <a href="schedule.php" class="btn" style="background:#475569; margin-left:8px;">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
