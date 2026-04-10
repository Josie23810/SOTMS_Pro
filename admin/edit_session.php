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
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(rgba(15,23,42,0.7), rgba(15,23,42,0.7)), url('../uploads/image005.jpg') center/cover fixed; margin: 0; padding: 20px; }
        .container { max-width:760px; margin:50px auto; background:white; padding:40px; border-radius:20px; box-shadow:0 25px 50px rgba(0,0,0,0.2); }
        .form-group { margin-bottom:25px; }
        label { display:block; font-weight:600; margin-bottom:8px; color:#374151; }
        input, select { width:100%; padding:12px 16px; border:2px solid #e5e7eb; border-radius:12px; font-size:16px; box-sizing:border-box; }
        .btn { padding:14px 28px; border:none; border-radius:12px; font-size:16px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; margin:5px; }
        .btn-primary { background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:white; }
        .btn-secondary { background:#6b7280; color:white; }
        .error { background:#fee2e2; color:#dc2626; padding:12px; border-radius:12px; margin-bottom:20px; border:1px solid #fecaca; }
        .success { background:#d1fae5; color:#065f46; padding:12px; border-radius:12px; margin-bottom:20px; border:1px solid #a7f3d0; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:30px; background:#f8fafc; padding:20px; border-radius:12px; }
        .info-item { text-align:center; }
        .info-label { font-size:0.9rem; color:#6b7280; }
        .info-value { font-size:1.2rem; font-weight:600; color:#1f2937; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Session #<?php echo (int) $session['id']; ?></h1>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Student</div>
                <div class="info-value"><?php echo htmlspecialchars($session['student_name']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Tutor</div>
                <div class="info-value"><?php echo htmlspecialchars($session['tutor_name']); ?></div>
            </div>
        </div>

        <?php if (isset($error) && $error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
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
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration" value="<?php echo htmlspecialchars((string) $duration_value); ?>">
                </div>
                <div class="form-group">
                    <label>Payment Amount (KSh)</label>
                    <input type="number" name="payment_amount" value="<?php echo htmlspecialchars((string) $payment_value); ?>" step="50" min="0">
                </div>
            </div>
            <div class="form-group">
                <label>Meeting Link</label>
                <input type="url" name="meeting_link" value="<?php echo htmlspecialchars($session['meeting_link'] ?? ''); ?>" placeholder="https://meet.google.com/...">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
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
                        <?php foreach (['unpaid', 'paid', 'failed', 'cancelled'] as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($session['payment_status'] ?? 'unpaid') === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Update Session</button>
                <a href="manage_sessions.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
