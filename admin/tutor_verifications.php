<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/TutorVerificationService.php';
checkAccess(['admin']);

ensurePlatformStructures($pdo);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tutor_user_id'], $_POST['decision'])) {
    try {
        TutorVerificationService::reviewTutor(
            $pdo,
            (int) $_POST['tutor_user_id'],
            (int) $_SESSION['user_id'],
            trim((string) $_POST['decision']),
            trim((string) ($_POST['review_notes'] ?? ''))
        );
        $message = 'Tutor verification review saved successfully.';
    } catch (Throwable $e) {
        $message = 'Unable to save the verification review.';
        $messageType = 'error';
        error_log('Tutor verification review error: ' . $e->getMessage());
    }
}

$rows = TutorVerificationService::fetchVerificationQueue($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Verification Queue - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family:'Poppins', sans-serif; background:linear-gradient(rgba(15,23,42,0.72), rgba(15,23,42,0.72)), url('../uploads/image005.jpg') center/cover fixed; margin:0; padding:20px; }
        .container { max-width:1200px; margin:0 auto; background:rgba(255,255,255,0.96); border-radius:20px; overflow:hidden; box-shadow:0 25px 50px rgba(0,0,0,0.2); }
        .header { background:linear-gradient(135deg, #0f766e, #14b8a6); color:white; padding:28px; text-align:center; }
        .content { padding:28px; }
        .nav a { display:inline-flex; margin-right:12px; margin-bottom:12px; color:#2563eb; text-decoration:none; font-weight:600; background:#dbeafe; padding:10px 16px; border-radius:10px; }
        .message { padding:14px 16px; border-radius:12px; margin-bottom:20px; font-weight:600; }
        .success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .card { background:white; border:1px solid #e2e8f0; border-radius:18px; padding:22px; margin-bottom:18px; box-shadow:0 10px 28px rgba(15,23,42,0.08); }
        .grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:18px; }
        .meta { color:#475569; line-height:1.7; }
        .badge { display:inline-flex; padding:7px 12px; border-radius:999px; font-size:0.82rem; font-weight:700; text-transform:uppercase; }
        .submitted { background:#fef3c7; color:#b45309; }
        .under_review { background:#dbeafe; color:#1d4ed8; }
        .verified { background:#d1fae5; color:#065f46; }
        .rejected { background:#fee2e2; color:#991b1b; }
        textarea, select { width:100%; box-sizing:border-box; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; }
        textarea { min-height:90px; resize:vertical; }
        .btn { background:#2563eb; color:white; border:none; border-radius:10px; padding:11px 16px; font-weight:700; cursor:pointer; }
        @media (max-width: 768px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Tutor Verification Queue</h1>
            <p>Review tutor identity details, qualification documents, and approval status changes.</p>
        </div>
        <div class="content">
            <div class="nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_users.php">Manage Users</a>
                <a href="payments_review.php">Payments Review</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($rows)): ?>
                <div class="card">No tutor profiles are available for verification yet.</div>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <div class="card">
                        <div class="grid">
                            <div>
                                <h3 style="margin-top:0;"><?php echo htmlspecialchars($row['full_name'] ?: $row['account_name']); ?></h3>
                                <div class="meta">
                                    Account Email: <?php echo htmlspecialchars($row['account_email']); ?><br>
                                    Profile Email: <?php echo htmlspecialchars($row['email'] ?: 'Not provided'); ?><br>
                                    ID Number: <?php echo htmlspecialchars($row['id_number'] ?: 'Not provided'); ?><br>
                                    Location: <?php echo htmlspecialchars($row['location'] ?: 'Not provided'); ?><br>
                                    Updated: <?php echo !empty($row['updated_at']) ? date('M j, Y g:i A', strtotime($row['updated_at'])) : 'N/A'; ?>
                                </div>
                                <p class="meta" style="margin-top:12px;"><strong>Qualifications:</strong> <?php echo nl2br(htmlspecialchars($row['qualifications'] ?: 'Not provided')); ?></p>
                                <?php if (!empty($row['qualification_document'])): ?>
                                    <p><a href="../<?php echo htmlspecialchars($row['qualification_document']); ?>" target="_blank" class="btn" style="text-decoration:none; display:inline-flex;">Open Document</a></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p style="margin-top:0;"><span class="badge <?php echo htmlspecialchars($row['verification_status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $row['verification_status'] ?: 'submitted')); ?></span></p>
                                <div class="meta">
                                    Last Decision: <?php echo htmlspecialchars(str_replace('_', ' ', $row['last_decision'] ?: 'None')); ?><br>
                                    Reviewed By: <?php echo htmlspecialchars($row['reviewed_by_name'] ?: 'N/A'); ?><br>
                                    Reviewed At: <?php echo !empty($row['last_reviewed_at']) ? date('M j, Y g:i A', strtotime($row['last_reviewed_at'])) : 'N/A'; ?>
                                </div>
                                <?php if (!empty($row['last_review_notes'])): ?>
                                    <p class="meta" style="margin-top:12px;"><strong>Last Notes:</strong> <?php echo nl2br(htmlspecialchars($row['last_review_notes'])); ?></p>
                                <?php endif; ?>
                                <form method="POST" style="margin-top:16px;">
                                    <input type="hidden" name="tutor_user_id" value="<?php echo (int) $row['tutor_user_id']; ?>">
                                    <label for="decision_<?php echo (int) $row['tutor_user_id']; ?>"><strong>Decision</strong></label>
                                    <select id="decision_<?php echo (int) $row['tutor_user_id']; ?>" name="decision">
                                        <?php foreach (TutorVerificationService::allowedDecisions() as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($row['verification_status'] ?? '') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="review_notes_<?php echo (int) $row['tutor_user_id']; ?>" style="display:block; margin-top:12px;"><strong>Review Notes</strong></label>
                                    <textarea id="review_notes_<?php echo (int) $row['tutor_user_id']; ?>" name="review_notes" placeholder="Record why this tutor was approved, rejected, or placed under review."></textarea>
                                    <button type="submit" class="btn" style="margin-top:12px;">Save Review</button>
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
