<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['admin']);

$session_id = intval($_GET['id'] ?? 0);
if (!$session_id) {
    header('Location: manage_sessions.php');
    exit();
}

$stmt = $pdo->prepare("SELECT s.*, 
    COALESCE((SELECT u.name FROM students st JOIN users u ON st.user_id = u.id WHERE st.id = s.student_id LIMIT 1), 'N/A') as student_name,
    COALESCE((SELECT u.name FROM tutors t JOIN users u ON t.user_id = u.id WHERE t.id = s.tutor_id LIMIT 1), 'N/A') as tutor_name
    FROM sessions s WHERE s.id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    $_SESSION['error'] = 'Session not found.';
    header('Location: manage_sessions.php');
    exit();
}

$duration_value = $session['duration'] ?: '60';
$payment_value = $session['payment_amount'] ?: '500';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $session_date = $_POST['session_date'];
    $duration = intval($_POST['duration']);
    $payment_amount = floatval($_POST['payment_amount']);
    $meeting_link = trim($_POST['meeting_link']);
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare('UPDATE sessions SET subject = ?, session_date = ?, duration = ?, payment_amount = ?, meeting_link = ?, status = ? WHERE id = ?');
        $stmt->execute([$subject, $session_date, $duration, $payment_amount, $meeting_link, $status, $session_id]);
        $_SESSION['success'] = 'Session updated successfully.';
        header('Location: manage_sessions.php');
        exit();
    } catch (PDOException $e) {
        $error = 'Error updating session: ' . $e->getMessage();
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
        .container { max-width:700px; margin:50px auto; background:white; padding:40px; border-radius:20px; box-shadow:0 25px 50px rgba(0,0,0,0.2); }
        .form-group { margin-bottom:25px; }
        label { display:block; font-weight:600; margin-bottom:8px; color:#374151; }
        input, select { width:100%; padding:12px 16px; border:2px solid #e5e7eb; border-radius:12px; font-size:16px; transition: border-color 0.2s; box-sizing:border-box; }
        input:focus, select:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
        .btn { padding:14px 28px; border:none; border-radius:12px; font-size:16px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; margin:5px; transition: all 0.2s; }
        .btn-primary { background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:white; }
        .btn-secondary { background:#6b7280; color:white; }
        .btn:hover { transform: translateY(-2px); box-shadow:0 10px 25px rgba(0,0,0,0.2); }
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
        <h1>✏️ Edit Session #<?php echo $session['id']; ?></h1>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Student</div>
                <div class="info-value"><?php echo $session['student_name']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Tutor</div>
                <div class="info-value"><?php echo $session['tutor_name']; ?></div>
            </div>
        </div>
        <?php if (isset($error)): ?>
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
                    <input type="number" name="duration" value="<?php echo $duration_value; ?>">
                </div>
                <div class="form-group">
                    <label>Payment Amount (KSh)</label>
                    <input type="number" name="payment_amount" value="<?php echo $payment_value; ?>" step="50" min="0">
                </div>
            </div>
            <div class="form-group">
                <label>Meeting Link</label>
                <input type="url" name="meeting_link" value="<?php echo htmlspecialchars($session['meeting_link'] ?? ''); ?>" placeholder="https://meet.jit.si/...">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="pending" <?php echo ($session['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo ($session['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo ($session['status'] == 'completed') ? 'selected' : ''; ?> >Completed</option>
                    <option value="cancelled" <?php echo ($session['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Update Session</button>
                <a href="manage_sessions.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>

