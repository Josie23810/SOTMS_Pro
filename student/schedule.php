<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

$deletion_message = '';
$deletion_type = '';
$studentId = getStudentId($pdo, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_session_id'])) {
    $session_id = intval($_POST['delete_session_id']);

    try {
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE id = ? AND student_id = ? AND status IN ("pending", "cancelled")');
        $stmt->execute([$session_id, $studentId]);

        if ($stmt->rowCount() > 0) {
            $deletion_message = 'Session deleted successfully.';
            $deletion_type = 'success';
        } else {
            $deletion_message = 'Only pending or cancelled sessions can be deleted.';
            $deletion_type = 'error';
        }
    } catch (PDOException $e) {
        $deletion_message = 'An error occurred while deleting the session.';
        $deletion_type = 'error';
        error_log('Student session deletion error: ' . $e->getMessage());
    }
}

$sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.session_date,
            s.subject,
            s.curriculum,
            s.study_level,
            s.duration,
            s.notes,
            s.status,
            s.tutor_id,
            s.meeting_link,
            s.payment_status,
            s.amount,
            u.id AS tutor_user_id,
            u.name AS tutor_name
        FROM sessions s
        LEFT JOIN tutors t ON s.tutor_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE s.student_id = ?
        ORDER BY
            CASE WHEN s.session_date >= NOW() THEN 0 ELSE 1 END,
            CASE WHEN s.session_date >= NOW() THEN s.session_date END ASC,
            CASE WHEN s.session_date < NOW() THEN s.session_date END DESC
    ");
    $stmt->execute([$studentId]);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Student sessions fetch error: ' . $e->getMessage());
}

$summary = [
    'upcoming' => 0,
    'completed' => 0,
    'pending' => 0,
    'payments' => 0,
];

foreach ($sessions as $session) {
    if (in_array($session['status'], ['pending', 'confirmed'], true) && strtotime($session['session_date']) >= time()) {
        $summary['upcoming']++;
    }
    if (($session['status'] ?? '') === 'completed') {
        $summary['completed']++;
    }
    if (($session['status'] ?? '') === 'pending') {
        $summary['pending']++;
    }
    if (in_array($session['payment_status'] ?? '', ['unpaid', 'processing', 'failed', 'refunded', ''], true)) {
        $summary['payments']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .schedule-shell {
            max-width: 1320px;
            margin: 0 auto;
        }
        .schedule-content {
            padding: 24px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 18px;
            padding: 16px 18px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
        }
        .summary-label {
            color: #64748b;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }
        .summary-value {
            margin-top: 8px;
            font-size: 2rem;
            font-weight: 800;
            color: #1d4ed8;
        }
        .section-card {
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
        }
        .section-title {
            margin: 0 0 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            color: #111827;
        }
        .section-copy {
            margin: 0 0 14px;
            color: #64748b;
        }
        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #ffffff;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }
        .schedule-table th,
        .schedule-table td {
            padding: 14px 16px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e5e7eb;
        }
        .schedule-table th {
            background: #eff6ff;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .schedule-table tbody tr:last-child td {
            border-bottom: none;
        }
        .subject-cell strong {
            display: block;
            color: #111827;
            font-size: 0.96rem;
            margin-bottom: 4px;
        }
        .cell-subtext {
            color: #64748b;
            font-size: 0.86rem;
            line-height: 1.45;
        }
        .pill,
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 0.8rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .pill.soft {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .status-pill {
            font-weight: 800;
            text-transform: uppercase;
        }
        .pending { background:#fef3c7; color:#b45309; }
        .confirmed { background:#dbeafe; color:#1d4ed8; }
        .completed { background:#dcfce7; color:#166534; }
        .cancelled { background:#fee2e2; color:#b91c1c; }
        .btn,
        .btn-secondary,
        .btn-danger,
        .btn-pay {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 11px 14px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 0.94rem;
        }
        .btn {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff;
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #111827;
        }
        .btn-pay {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
        }
        .btn-danger {
            background: #ef4444;
            color: #fff;
        }
        .actions-cell {
            min-width: 220px;
        }
        .actions-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .note-pill {
            display: inline-block;
            margin-top: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f8fafc;
            color: #475569;
            font-size: 0.8rem;
            max-width: 320px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .empty-card {
            text-align: center;
            padding: 56px 20px;
        }
        .empty-card h3 {
            margin: 0 0 10px;
            font-size: 1.5rem;
        }
        .inline-alert {
            margin-bottom: 20px;
        }
        @media (max-width: 980px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 700px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="form-page">
    <div class="form-shell schedule-shell">
        <div class="form-hero">
            <h1>My Schedule</h1>
            <p>Sessions, payments, and actions.</p>
        </div>

        <div class="form-nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="book_session.php">Book New Session</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="form-content schedule-content">
            <?php if ($deletion_message): ?>
                <div class="message <?php echo htmlspecialchars($deletion_type); ?> inline-alert">
                    <?php echo htmlspecialchars($deletion_message); ?>
                </div>
            <?php endif; ?>

            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-label">Upcoming</div>
                    <div class="summary-value"><?php echo $summary['upcoming']; ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Pending</div>
                    <div class="summary-value"><?php echo $summary['pending']; ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Completed</div>
                    <div class="summary-value"><?php echo $summary['completed']; ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Payments</div>
                    <div class="summary-value"><?php echo $summary['payments']; ?></div>
                </div>
            </div>

            <div class="section-card">
                <h2 class="section-title">Your Sessions</h2>
                <p class="section-copy">Upcoming first.</p>

                <?php if (empty($sessions)): ?>
                    <div class="empty-card">
                        <h3>No sessions scheduled</h3>
                        <p class="space-top-sm">
                            <a href="book_session.php" class="btn">Book Your First Session</a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Session</th>
                                    <th>Tutor</th>
                                    <th>Date</th>
                                    <th>Level</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <?php $paymentLabel = ucfirst(str_replace('_', ' ', (string) ($session['payment_status'] ?: 'unpaid'))); ?>
                                    <tr>
                                        <td class="subject-cell">
                                            <strong><?php echo htmlspecialchars($session['subject']); ?></strong>
                                            <div class="cell-subtext">
                                                <?php echo (int) $session['duration']; ?> min
                                                <?php if (!empty($session['curriculum'])): ?>
                                                    • <?php echo htmlspecialchars($session['curriculum']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($session['notes'])): ?>
                                                <span class="note-pill"><?php echo htmlspecialchars($session['notes']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="cell-subtext"><?php echo htmlspecialchars($session['tutor_name'] ?: 'Not assigned'); ?></div>
                                        </td>
                                        <td>
                                            <div class="cell-subtext"><?php echo date('D, M j', strtotime($session['session_date'])); ?></div>
                                            <div class="cell-subtext"><?php echo date('g:i A', strtotime($session['session_date'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="cell-subtext"><?php echo htmlspecialchars($session['study_level'] ?: 'Not set'); ?></div>
                                        </td>
                                        <td>
                                            <span class="status-pill <?php echo htmlspecialchars($session['status']); ?>"><?php echo htmlspecialchars($session['status']); ?></span>
                                        </td>
                                        <td>
                                            <span class="pill soft"><?php echo htmlspecialchars($paymentLabel); ?></span>
                                            <div class="cell-subtext">KSh <?php echo number_format((float) ($session['amount'] ?: 500), 2); ?></div>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="actions-stack">
                                                <?php if (in_array($session['payment_status'], ['unpaid', 'failed', 'refunded', ''], true)): ?>
                                                    <a href="pay_session.php?id=<?php echo (int) $session['id']; ?>" class="btn-pay">Pay</a>
                                                <?php elseif ($session['payment_status'] === 'processing'): ?>
                                                    <span class="btn-secondary">Checking</span>
                                                <?php endif; ?>

                                                <?php if ($session['status'] === 'confirmed' && !empty($session['meeting_link'])): ?>
                                                    <a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" class="btn" target="_blank">Join</a>
                                                <?php endif; ?>

                                                <?php if (!empty($session['tutor_user_id'])): ?>
                                                    <a href="messages.php?to=<?php echo (int) $session['tutor_user_id']; ?>" class="btn-secondary">Message</a>
                                                <?php endif; ?>

                                                <a href="edit_session.php?id=<?php echo (int) $session['id']; ?>" class="btn">Edit</a>

                                                <?php if (in_array($session['status'], ['pending', 'cancelled'], true)): ?>
                                                    <form method="POST" onsubmit="return confirm('Delete this session?');">
                                                        <input type="hidden" name="delete_session_id" value="<?php echo (int) $session['id']; ?>">
                                                        <button type="submit" class="btn-danger">Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
