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
        ORDER BY s.session_date ASC
    ");
    $stmt->execute([$studentId]);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Student sessions fetch error: ' . $e->getMessage());
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
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)),
                        url('../uploads/image003.jpg') center/cover no-repeat;
            color: #1f2937;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255,255,255,0.96);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.15);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .nav {
            background: #f8fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }
        .nav a {
            color: #2563eb;
            text-decoration: none;
            margin: 0 15px;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 8px;
        }
        .nav a:hover { background: #e0f2fe; }
        .content { padding: 30px; }
        .session-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            box-shadow: 0 8px 20px rgba(15,23,42,0.05);
        }
        .status-pill {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-flex;
        }
        .pending { background:#fef3c7; color:#d97706; }
        .confirmed { background:#dbeafe; color:#2563eb; }
        .completed { background:#d1fae5; color:#065f46; }
        .cancelled { background:#fee2e2; color:#dc2626; }
        .btn { background: #2563eb; color: white; padding: 10px 16px; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; display:inline-flex; }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #475569; }
        .btn-secondary:hover { background: #334155; }
        .btn-pay { background: #10b981; }
        .btn-pay:hover { background: #059669; }
        .no-sessions { text-align: center; padding: 50px; color: #6b7280; }
        @media (max-width: 768px) { .session-card { flex-direction: column; } .nav a { display:block; margin:10px 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Schedule</h1>
            <p>Review your booked sessions, payments, and upcoming lessons.</p>
        </div>

        <div class="nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="book_session.php">Book New Session</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <?php if ($deletion_message): ?>
                <div style="background: <?php echo $deletion_type === 'success' ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $deletion_type === 'success' ? '#065f46' : '#991b1b'; ?>; border: 1px solid <?php echo $deletion_type === 'success' ? '#a7f3d0' : '#fecaca'; ?>; border-radius: 12px; padding: 16px; margin-bottom: 24px; font-weight: 600;">
                    <?php echo htmlspecialchars($deletion_message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($sessions)): ?>
                <div class="no-sessions">
                    <h3>No sessions scheduled</h3>
                    <p>You have not booked any tutoring sessions yet.</p>
                    <a href="book_session.php" class="btn">Book Your First Session</a>
                </div>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div class="session-card">
                        <div style="flex:1;">
                            <h3 style="margin:0 0 8px;"><?php echo htmlspecialchars($session['subject']); ?></h3>
                            <div style="color:#475569; line-height:1.7;">
                                Date & Time: <?php echo date('l, F j, Y \a\t g:i A', strtotime($session['session_date'])); ?><br>
                                Tutor: <?php echo htmlspecialchars($session['tutor_name'] ?: 'Tutor not assigned'); ?><br>
                                Duration: <?php echo (int) $session['duration']; ?> minutes<br>
                                Curriculum: <?php echo htmlspecialchars($session['curriculum'] ?: 'Not provided'); ?><br>
                                Study Level: <?php echo htmlspecialchars($session['study_level'] ?: 'Not provided'); ?><br>
                                Amount: KSh <?php echo number_format((float) ($session['amount'] ?: 500), 2); ?><br>
                                Payment Status: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $session['payment_status'] ?: 'unpaid'))); ?>
                            </div>
                            <?php if (!empty($session['notes'])): ?>
                                <p style="margin-top:12px;"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($session['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:10px; align-items:flex-end;">
                            <span class="status-pill <?php echo htmlspecialchars($session['status']); ?>"><?php echo htmlspecialchars($session['status']); ?></span>
                            <?php if (in_array($session['payment_status'], ['unpaid', 'failed', 'refunded', ''], true)): ?>
                                <a href="pay_session.php?id=<?php echo (int) $session['id']; ?>" class="btn btn-pay">Pay Now</a>
                            <?php elseif ($session['payment_status'] === 'processing'): ?>
                                <span class="btn btn-secondary" style="cursor:default;">Awaiting Verification</span>
                            <?php endif; ?>
                            <?php if ($session['status'] === 'confirmed' && !empty($session['meeting_link'])): ?>
                                <a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" class="btn" target="_blank">Join Session</a>
                            <?php endif; ?>
                            <?php if (!empty($session['tutor_user_id'])): ?>
                                <a href="messages.php?to=<?php echo (int) $session['tutor_user_id']; ?>" class="btn btn-secondary">Message Tutor</a>
                            <?php endif; ?>
                            <a href="edit_session.php?id=<?php echo (int) $session['id']; ?>" class="btn">Edit</a>
                            <?php if (in_array($session['status'], ['pending', 'cancelled'], true)): ?>
                                <form method="POST" onsubmit="return confirm('Delete this session?');">
                                    <input type="hidden" name="delete_session_id" value="<?php echo (int) $session['id']; ?>">
                                    <button type="submit" class="btn" style="background:#ef4444;">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
