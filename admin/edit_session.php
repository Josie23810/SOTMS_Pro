<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['admin']);

ensurePlatformStructures($pdo);

$session_id = intval($_GET['id'] ?? 0);
if (!$session_id) {
    header('Location: manage_sessions.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT
        s.*,
        COALESCE((SELECT u.name FROM students st JOIN users u ON st.user_id = u.id WHERE st.id = s.student_id LIMIT 1), 'N/A') AS student_name,
        COALESCE((SELECT u.name FROM tutors t JOIN users u ON t.user_id = u.id WHERE t.id = s.tutor_id LIMIT 1), 'N/A') AS tutor_name
    FROM sessions s
    WHERE s.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    $_SESSION['error'] = 'Session not found.';
    header('Location: manage_sessions.php');
    exit();
}

$duration_value = $session['duration'] ?: 60;
$payment_value = $session['payment_amount'] ?: ($session['amount'] ?: 500);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $session_date = trim($_POST['session_date'] ?? '');
    $duration = intval($_POST['duration'] ?? 60);
    $payment_amount = floatval($_POST['payment_amount'] ?? 0);
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');
    $payment_status = trim($_POST['payment_status'] ?? 'unpaid');

    $error = '';
    if ($subject === '') {
        $error = 'Subject is required.';
    } elseif ($session_date === '') {
        $error = 'Date and time are required.';
    } elseif ($session['tutor_id'] && hasTutorScheduleCollision($pdo, $session['tutor_id'], $session_date, $duration, $session_id)) {
        $error = 'This update collides with another tutor session.';
    } elseif ($session['student_id'] && hasStudentScheduleCollision($pdo, $session['student_id'], $session_date, $duration, $session_id)) {
        $error = 'This update collides with another student session.';
    }

    if ($error === '') {
        try {
            $stmt = $pdo->prepare('
                UPDATE sessions
                SET subject = ?, session_date = ?, preferred_date = DATE(?), preferred_time = TIME(?), duration = ?, payment_amount = ?, amount = ?, meeting_link = ?, status = ?, payment_status = ?
                WHERE id = ?
            ');
            $stmt->execute([$subject, $session_date, $session_date, $session_date, $duration, $payment_amount, $payment_amount, $meeting_link, $status, $payment_status, $session_id]);
            $_SESSION['success'] = 'Session updated successfully.';
            header('Location: manage_sessions.php');
            exit();
        } catch (PDOException $e) {
            $error = 'Error updating session: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Session - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin_portal.css">
</head>
<body class="admin-portal">
    <div class="admin-form-card">
        <h1 style="margin-top:0;">Edit Session</h1>

        <div class="info-grid" style="margin-bottom:24px;">
            <div class="panel" style="margin-bottom:0;">
                <div class="info-label">Student</div>
                <div class="info-value"><?php echo htmlspecialchars($session['student_name']); ?></div>
            </div>
            <div class="panel" style="margin-bottom:0;">
                <div class="info-label">Tutor</div>
                <div class="info-value"><?php echo htmlspecialchars($session['tutor_name']); ?></div>
            </div>
        </div>

        <?php if (isset($error) && $error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" value="<?php echo htmlspecialchars($session['subject']); ?>" required>
            </div>
            <div class="form-group">
                <label>Date & Time</label>
                <input type="datetime-local" name="session_date" value="<?php echo date('Y-m-d\TH:i', strtotime($session['session_date'] ?? 'now')); ?>" required>
            </div>
            <div class="info-grid">
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration" value="<?php echo htmlspecialchars((string) $duration_value); ?>">
                </div>
                <div class="form-group">
                    <label>Amount (KSh)</label>
                    <input type="number" name="payment_amount" value="<?php echo htmlspecialchars((string) $payment_value); ?>" step="50" min="0">
                </div>
            </div>
            <div class="form-group">
                <label>Meeting Link</label>
                <input type="url" name="meeting_link" value="<?php echo htmlspecialchars($session['meeting_link'] ?? ''); ?>" placeholder="https://meet.google.com/...">
            </div>
            <div class="info-grid">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $session['status'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Status</label>
                    <select name="payment_status">
                        <?php foreach (['unpaid', 'paid', 'failed', 'cancelled', 'processing', 'refunded'] as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($session['payment_status'] ?? 'unpaid') === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="stack-actions">
                <button type="submit" class="btn btn-primary">Update Session</button>
                <a href="manage_sessions.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
