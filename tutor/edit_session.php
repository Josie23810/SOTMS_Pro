<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['tutor']);

$message = '';
$messageType = '';
$session = null;

$session_id = intval($_GET['id'] ?? 0);
$tutorId = getTutorId($pdo, $_SESSION['user_id']);

// Fetch session to edit - only if tutor owns it
try {
    $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ? AND tutor_id = ?');
    $stmt->execute([$session_id, $tutorId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        $message = "Session not found or you don't have permission to edit it.";
        $messageType = 'error';
    }
} catch (PDOException $e) {
    $message = "Error loading session details.";
    $messageType = 'error';
    error_log('Tutor session fetch error: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $session) {
    $subject = trim($_POST['subject'] ?? '');
    $student_id = $session['student_id']; // Don't change
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $duration = intval($_POST['duration'] ?? 60);
    $notes = trim($_POST['notes'] ?? '');
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($subject)) $errors[] = "Subject is required.";
    if (empty($date)) $errors[] = "Date is required.";
    if (empty($time)) $errors[] = "Time is required.";
    
    if (empty($errors)) {
        try {
            $session_data = date('Y-m-d H:i:s', strtotime("$date $time"));
            $stmt = $pdo->prepare('UPDATE sessions SET subject = ?, session_date = ?, duration = ?, notes = ?, meeting_link = ? WHERE id = ? AND tutor_id = ?');
            $stmt->execute([$subject, $session_data, $duration, $notes, $meeting_link, $session_id, $tutorId]);
            
            $_SESSION['edit_success'] = "✓ Session updated successfully, meeting link set.";
            header('Location: ../tutor/schedule.php');
            exit();
        } catch (PDOException $e) {
            $message = "An error occurred while updating the session.";
            $messageType = 'error';
            error_log('Tutor session update error: ' . $e->getMessage());
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}

// Get session student name for display
$student_name = '';
if ($session) {
    try {
        $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$session['student_id']]);
        $student = $stmt->fetch();
        $student_name = $student ? $student['name'] : 'Unknown Student';
    } catch (PDOException $e) {
        error_log('Student name fetch error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Session - Tutor | SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)), url('../uploads/image003.jpg') center/cover; margin:0; padding:20px; }
        .container { max-width:800px; margin:0 auto; background: rgba(255,255,255,0.95); border-radius:16px; box-shadow:0 20px 40px rgba(15,23,42,0.15); overflow:hidden; }
        .header { background: linear-gradient(135deg, #10b981, #059669); color:white; padding:30px; text-align:center; }
        .header h1 { margin:0; font-size:2.5rem; }
        .nav { background:#f8fafc; padding:20px; border-bottom:1px solid #e2e8f0; text-align:center; }
        .nav a { color:#2563eb; text-decoration:none; margin:0 15px; font-weight:600; padding:10px 15px; border-radius:8px; }
        .nav a:hover { background:#e0f2fe; }
        .content { padding:30px; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:700; color:#374151; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:14px; border:1px solid #d1d5db; border-radius:10px; font-size:1rem; box-sizing:border-box; }
        .form-group textarea { min-height:120px; resize:vertical; }
        .btn { background:#10b981; color:white; padding:14px 28px; border:none; border-radius:10px; font-size:16px; font-weight:700; cursor:pointer; transition:background 0.2s; }
        .btn:hover { background:#059669; }
        .btn-secondary { background:#6b7280; margin-right:10px; }
        .btn-secondary:hover { background:#4b5563; }
        .message { padding:16px; border-radius:12px; margin-bottom:24px; }
        .success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .meeting-link-group { background:#f0fdfa; border:2px solid #a7f3d0; border-radius:12px; padding:20px; margin:20px 0; }
        .meeting-link-group label { color:#065f46; font-weight:700; font-size:1.1rem; }
        .meeting-link-group textarea { border-color:#10b981; }
        @media (max-width:768px) { .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Edit Session Details</h1>
            <p>Update session info and set meeting link for your student</p>
        </div>
        <div class="nav">
            <a href="schedule.php">← Back to Schedule</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($session): ?>
                <div style="background:#dbeafe; padding:16px; border-radius:12px; margin-bottom:24px;">
                    <strong>👤 Student:</strong> <?php echo htmlspecialchars($student_name); ?> | 
                    <strong>📚 Subject:</strong> <?php echo htmlspecialchars($session['subject']); ?> | 
                    <strong>📅 Current Time:</strong> <?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?>
                </div>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" required>
                                <option value="">Select subject</option>
                                <option value="Mathematics" <?php echo $session['subject'] === 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                <option value="Physics" <?php echo $session['subject'] === 'Physics' ? 'selected' : ''; ?>>Physics</option>
                                <option value="Chemistry" <?php echo $session['subject'] === 'Chemistry' ? 'selected' : ''; ?>>Chemistry</option>
                                <option value="Biology" <?php echo $session['subject'] === 'Biology' ? 'selected' : ''; ?>>Biology</option>
                                <option value="English" <?php echo $session['subject'] === 'English' ? 'selected' : ''; ?>>English</option>
                                <option value="Computer Science" <?php echo $session['subject'] === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="History" <?php echo $session['subject'] === 'History' ? 'selected' : ''; ?>>History</option>
                                <option value="Geography" <?php echo $session['subject'] === 'Geography' ? 'selected' : ''; ?>>Geography</option>
                                <option value="Other" <?php echo $session['subject'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" disabled>
                                <option value="pending" <?php echo $session['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $session['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $session['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                            <small>Change status from Schedule page</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Session Date *</label>
                            <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d', strtotime($session['session_date'])); ?>" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="time">Session Time *</label>
                            <select id="time" name="time" required>
                                <?php
                                    $current_time = date('H:i', strtotime($session['session_date']));
                                    $times = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
                                    foreach ($times as $t):
                                ?>
                                    <option value="<?php echo $t; ?>" <?php echo $t === $current_time ? 'selected' : ''; ?>><?php echo date('g:i A', strtotime($t)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="duration">Duration</label>
                        <select id="duration" name="duration">
                            <option value="60" <?php echo $session['duration'] == 60 ? 'selected' : ''; ?>>1 hour</option>
                            <option value="90" <?php echo $session['duration'] == 90 ? 'selected' : ''; ?>>1.5 hours</option>
                            <option value="120" <?php echo $session['duration'] == 120 ? 'selected' : ''; ?>>2 hours</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes"><?php echo htmlspecialchars($session['notes']); ?></textarea>
                    </div>

                    <div class="meeting-link-group">
                        <label for="meeting_link">🔗 Meeting Link (Zoom/Google Meet)</label>
                        <textarea id="meeting_link" name="meeting_link" placeholder="Paste your meeting link here (e.g., https://zoom.us/j/123456789 or https://meet.google.com/abc-defg-hij)..."><?php echo htmlspecialchars($session['meeting_link'] ?? ''); ?></textarea>
                        <small style="color:#059669;">Students will join via button once set. Leave blank if not ready.</small>
                    </div>

                    <div style="display:flex; gap:12px;">
                        <button type="submit" class="btn">💾 Save Changes & Set Link</button>
                        <a href="schedule.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
