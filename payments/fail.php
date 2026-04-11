<?php
$reference = trim((string) ($_GET['ref'] ?? ''));
$reason = trim((string) ($_GET['reason'] ?? 'payment_failed'));
$reasonLabel = ucwords(str_replace(['_', '-'], ' ', $reason));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PesaPal Payment Failed - SOTMS PRO</title>
    <style>
        body { font-family: Poppins, Arial, sans-serif; background:#f8fafc; margin:0; padding:32px 16px; color:#0f172a; }
        .shell { max-width:640px; margin:0 auto; background:#ffffff; border-radius:20px; padding:32px; box-shadow:0 20px 40px rgba(15,23,42,0.08); }
        .badge { display:inline-flex; padding:8px 12px; border-radius:999px; font-weight:700; background:#fee2e2; color:#b91c1c; margin-bottom:16px; }
        .btn { display:inline-flex; padding:12px 18px; border-radius:12px; text-decoration:none; font-weight:700; background:#2563eb; color:#fff; }
        .btn.alt { background:#475569; }
        .muted { color:#64748b; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="badge">PesaPal Payment</div>
        <h1 style="margin-top:0;">Payment not completed</h1>
        <p class="muted">The PesaPal checkout did not complete successfully.</p>
        <?php if ($reference !== ''): ?>
            <p><strong>Reference:</strong> <?php echo htmlspecialchars($reference); ?></p>
        <?php endif; ?>
        <p><strong>Reason:</strong> <?php echo htmlspecialchars($reasonLabel); ?></p>
        <a href="../student/schedule.php" class="btn">Back to Schedule</a>
    </div>
</body>
</html>
