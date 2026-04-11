<?php
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/PaymentService.php';

ensurePlatformStructures($pdo);

$reference = trim((string) ($_GET['ref'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));
$payment = $reference !== '' ? PaymentService::fetchPaymentStatusContext($pdo, $reference) : null;
$status = $payment['status'] ?? ($state === 'processing' ? 'gateway_submitted' : 'paid');
$statusLabel = $status === 'gateway_submitted' ? 'Processing' : ucfirst(str_replace('_', ' ', $status));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PesaPal Payment - SOTMS PRO</title>
    <style>
        body { font-family: Poppins, Arial, sans-serif; background:#eff6ff; margin:0; padding:32px 16px; color:#0f172a; }
        .shell { max-width:640px; margin:0 auto; background:#ffffff; border-radius:20px; padding:32px; box-shadow:0 20px 40px rgba(15,23,42,0.08); }
        .badge { display:inline-flex; padding:8px 12px; border-radius:999px; font-weight:700; background:#dbeafe; color:#1d4ed8; margin-bottom:16px; }
        .btn { display:inline-flex; padding:12px 18px; border-radius:12px; text-decoration:none; font-weight:700; background:#2563eb; color:#fff; }
        .muted { color:#64748b; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="badge">PesaPal Payment</div>
        <h1 style="margin-top:0;"><?php echo htmlspecialchars($status === 'gateway_submitted' ? 'Payment received' : 'Payment successful'); ?></h1>
        <p class="muted">
            <?php echo htmlspecialchars($status === 'gateway_submitted'
                ? 'Your payment has been received by PesaPal and is still being confirmed.'
                : 'Your session payment was confirmed successfully.'); ?>
        </p>

        <?php if ($payment): ?>
            <p><strong>Reference:</strong> <?php echo htmlspecialchars($payment['reference']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($statusLabel); ?></p>
            <p><strong>Amount:</strong> KSh <?php echo number_format((float) ($payment['amount'] ?? 0), 2); ?></p>
            <p><strong>Session:</strong> <?php echo htmlspecialchars($payment['subject'] ?: 'Tutoring session'); ?></p>
        <?php endif; ?>

        <a href="../student/schedule.php" class="btn">Back to Schedule</a>
    </div>
</body>
</html>
