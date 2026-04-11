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
        $message = 'Tutor review saved.';
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
    <link rel="stylesheet" href="../assets/css/admin_portal.css">
</head>
<body class="admin-portal">
    <div class="container">
        <div class="header">
            <h1>Tutor Verifications</h1>
            <p>Review tutor identity, documents, and approval status.</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="manage_users.php">Manage Users</a>
            <a href="payments_review.php">Payments Review</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($rows)): ?>
                <div class="card">No tutor profiles are ready for review.</div>
            <?php else: ?>
                <div class="table-shell">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Tutor</th>
                                <th>Contacts</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Document</th>
                                <th>Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['full_name'] ?: $row['account_name']); ?></strong>
                                        <div class="table-subtle"><?php echo htmlspecialchars($row['account_email']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($row['email'] ?: 'No profile email'); ?></div>
                                        <div class="table-subtle"><?php echo htmlspecialchars($row['location'] ?: 'No location'); ?></div>
                                    </td>
                                    <td>
                                        <div><strong>ID:</strong> <?php echo htmlspecialchars($row['id_number'] ?: 'Not provided'); ?></div>
                                        <div class="table-subtle"><?php echo !empty($row['updated_at']) ? date('M j, Y g:i A', strtotime($row['updated_at'])) : 'N/A'; ?></div>
                                        <div class="table-subtle"><?php echo htmlspecialchars(strlen((string) ($row['qualifications'] ?: '')) > 87 ? substr((string) $row['qualifications'], 0, 87) . '...' : (string) ($row['qualifications'] ?: 'No qualifications listed')); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo htmlspecialchars($row['verification_status']); ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $row['verification_status'] ?: 'submitted')); ?>
                                        </span>
                                        <div class="table-subtle">Last: <?php echo htmlspecialchars(str_replace('_', ' ', $row['last_decision'] ?: 'none')); ?></div>
                                        <div class="table-subtle"><?php echo htmlspecialchars($row['reviewed_by_name'] ?: 'Not reviewed'); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['qualification_document'])): ?>
                                            <a href="../<?php echo htmlspecialchars($row['qualification_document']); ?>" target="_blank" class="btn">Open</a>
                                        <?php else: ?>
                                            <span class="table-subtle">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="table-form">
                                            <input type="hidden" name="tutor_user_id" value="<?php echo (int) $row['tutor_user_id']; ?>">
                                            <select id="decision_<?php echo (int) $row['tutor_user_id']; ?>" name="decision">
                                                <?php foreach (TutorVerificationService::allowedDecisions() as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($row['verification_status'] ?? '') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <textarea id="review_notes_<?php echo (int) $row['tutor_user_id']; ?>" name="review_notes" placeholder="Notes"><?php echo htmlspecialchars((string) ($row['last_review_notes'] ?? '')); ?></textarea>
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
