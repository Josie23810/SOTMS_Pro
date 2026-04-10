<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/PaymentService.php';
checkAccess(['admin']);

ensurePlatformStructures($pdo);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['review_action'])) {
    try {
        PaymentService::reviewPayment(
            $pdo,
            (int) $_POST['payment_id'],
            trim((string) $_POST['review_action']),
            (int) $_SESSION['user_id'],
            trim((string) ($_POST['review_notes'] ?? ''))
        );
        $message = 'Payment review action saved successfully.';
    } catch (Throwable $e) {
        $message = 'Unable to save the payment review action.';
        $messageType = 'error';
        error_log('Payment review error: ' . $e->getMessage());
    }
}

$payments = PaymentService::fetchPaymentReviewQueue($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Review - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family:'Poppins', sans-serif; background:linear-gradient(rgba(15,23,42,0.72), rgba(15,23,42,0.72)), url('../uploads/image005.jpg') center/cover fixed; margin:0; padding:20px; }
        .container { max-width:1200px; margin:0 auto; background:rgba(255,255,255,0.96); border-radius:20px; overflow:hidden; box-shadow:0 25px 50px rgba(0,0,0,0.2); }
        .header { background:linear-gradient(135deg, #2563eb, #1d4ed8); color:white; padding:28px; text-align:center; }
        .content { padding:28px; }
        .nav a { display:inline-flex; margin-right:12px; margin-bottom:12px; color:#2563eb; text-decoration:none; font-weight:600; background:#dbeafe; padding:10px 16px; border-radius:10px; }
        .message { padding:14px 16px; border-radius:12px; margin-bottom:20px; font-weight:600; }
        .success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .card { background:white; border:1px solid #e2e8f0; border-radius:18px; padding:22px; margin-bottom:18px; box-shadow:0 10px 28px rgba(15,23,42,0.08); }
        .grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:18px; }
        .meta { color:#475569; line-height:1.7; }
        .badge { display:inline-flex; padding:7px 12px; border-radius:999px; font-size:0.82rem; font-weight:700; text-transform:uppercase; }
        .gateway_submitted { background:#fef3c7; color:#b45309; }
        .pending { background:#dbeafe; color:#1d4ed8; }
        .paid { background:#d1fae5; color:#065f46; }
        .failed, .refunded { background:#fee2e2; color:#991b1b; }
        textarea, select { width:100%; box-sizing:border-box; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; }
        textarea { min-height:90px; resize:vertical; }
        .btn { background:#2563eb; color:white; border:none; border-radius:10px; padding:11px 16px; font-weight:700; cursor:pointer; }
        @media (max-width: 768px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payments Review</h1>
            <p>Monitor payment submissions, verified transactions, failures, and refunds.</p>
        </div>
        <div class="content">
            <div class="nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="tutor_verifications.php">Tutor Verifications</a>
                <a href="reports.php">Reports</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($payments)): ?>
                <div class="card">No payments have been submitted yet.</div>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <div class="card">
                        <div class="grid">
                            <div>
                                <h3 style="margin-top:0;"><?php echo htmlspecialchars($payment['reference']); ?></h3>
                                <div class="meta">
                                    Student: <?php echo htmlspecialchars($payment['student_name'] ?: 'Unknown'); ?><br>
                                    Tutor: <?php echo htmlspecialchars($payment['tutor_name'] ?: 'Unknown'); ?><br>
                                    Session: <?php echo htmlspecialchars($payment['subject'] ?: 'Session'); ?> on <?php echo !empty($payment['session_date']) ? date('M j, Y g:i A', strtotime($payment['session_date'])) : 'N/A'; ?><br>
                                    Provider: <?php echo htmlspecialchars(strtoupper($payment['provider'] ?: 'n/a')); ?><br>
                                    Amount: KSh <?php echo number_format((float) ($payment['amount'] ?: 0), 2); ?>
                                </div>
                            </div>
                            <div>
                                <p style="margin-top:0;">
                                    <span class="badge <?php echo htmlspecialchars($payment['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $payment['status'] ?: 'pending')); ?></span>
                                </p>
                                <div class="meta">
                                    Session Payment State: <?php echo htmlspecialchars(str_replace('_', ' ', $payment['payment_status'] ?: 'unpaid')); ?><br>
                                    Submitted: <?php echo !empty($payment['created_at']) ? date('M j, Y g:i A', strtotime($payment['created_at'])) : 'N/A'; ?><br>
                                    Updated: <?php echo !empty($payment['updated_at']) ? date('M j, Y g:i A', strtotime($payment['updated_at'])) : 'N/A'; ?>
                                </div>
                                <form method="POST" style="margin-top:16px;">
                                    <input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>">
                                    <label for="review_action_<?php echo (int) $payment['id']; ?>"><strong>Review Action</strong></label>
                                    <select id="review_action_<?php echo (int) $payment['id']; ?>" name="review_action">
                                        <?php foreach (PaymentService::reviewActions() as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="review_notes_<?php echo (int) $payment['id']; ?>" style="display:block; margin-top:12px;"><strong>Review Notes</strong></label>
                                    <textarea id="review_notes_<?php echo (int) $payment['id']; ?>" name="review_notes" placeholder="Record gateway evidence, reconciliation notes, or refund reasons."></textarea>
                                    <button type="submit" class="btn" style="margin-top:12px;">Save Action</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
