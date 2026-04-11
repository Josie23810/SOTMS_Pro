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
        $message = 'Payment review saved.';
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
    <link rel="stylesheet" href="../assets/css/admin_portal.css">
</head>
<body class="admin-portal">
    <div class="container">
        <div class="header">
            <h1>Payments Review</h1>
            <p>Review payment status, amount, and next action.</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="tutor_verifications.php">Tutor Verifications</a>
            <a href="reports.php">Reports</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($payments)): ?>
                <div class="card">No payments yet.</div>
            <?php else: ?>
                <div class="table-shell">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Student</th>
                                <th>Tutor</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['reference']); ?></strong>
                                        <div class="table-subtle"><?php echo htmlspecialchars($payment['subject'] ?: 'Session'); ?></div>
                                        <div class="table-subtle"><?php echo htmlspecialchars(strtoupper($payment['provider'] ?: 'PESAPAL')); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['student_name'] ?: 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['tutor_name'] ?: 'Unknown'); ?></td>
                                    <td>KSh <?php echo number_format((float) ($payment['amount'] ?: 0), 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo htmlspecialchars($payment['status']); ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $payment['status'] ?: 'pending')); ?>
                                        </span>
                                        <div class="table-subtle"><?php echo htmlspecialchars(str_replace('_', ' ', $payment['payment_status'] ?: 'unpaid')); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo !empty($payment['created_at']) ? date('M j, Y g:i A', strtotime($payment['created_at'])) : 'N/A'; ?></div>
                                        <div class="table-subtle">Updated <?php echo !empty($payment['updated_at']) ? date('M j, Y', strtotime($payment['updated_at'])) : 'N/A'; ?></div>
                                    </td>
                                    <td>
                                        <form method="POST" class="table-form">
                                            <input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>">
                                            <select id="review_action_<?php echo (int) $payment['id']; ?>" name="review_action">
                                                <?php foreach (PaymentService::reviewActions() as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <textarea id="review_notes_<?php echo (int) $payment['id']; ?>" name="review_notes" placeholder="Notes"></textarea>
                                            <button type="submit" class="btn">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
