<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']); // Only allow students

$message = '';
$messageType = '';

// ensure student/tutor profile mapping and sessions columns exist to avoid FK/column issues
$current_student_id = getStudentId($pdo, $_SESSION['user_id']);
ensureSessionStructure($pdo);
$tutors = getAvailableTutors($pdo);

$preselected_tutor_id = intval($_GET['tutor'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $tutor_id = intval($_POST['tutor_id'] ?? $preselected_tutor_id);
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $duration = intval($_POST['duration'] ?? 60);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($tutor_id)) $errors[] = "Please select a tutor.";
    if (empty($subject)) $errors[] = "Subject is required.";
    if (empty($date)) $errors[] = "Date is required.";
    if (empty($time)) $errors[] = "Time is required.";
    if (strtotime($date) < strtotime(date('Y-m-d'))) $errors[] = "Date cannot be in the past.";

    if (empty($errors)) {
        try {
            $session_data = date('Y-m-d H:i:s', strtotime("$date $time"));
            $stmt = $pdo->prepare('INSERT INTO sessions (student_id, tutor_id, session_date, status, payment_status, payment_amount, subject, duration, notes) VALUES (?, ?, ?, ?, ?, 500.00, ?, ?, ?)');
            $stmt->execute([$current_student_id, $tutor_id, $session_data, 'pending', 'unpaid', $subject, $duration, $notes]);

            $_SESSION['booking_success'] = "✓ Session booked! Pay now in schedule.php to activate.";
            header('Location: schedule.php');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') {
                $message = "Session booking unavailable. Contact support.";
                $messageType = 'error';
            } else {
                $message = "Booking error. Try again.";
                $messageType = 'error';
            }
            error_log('Session booking error: ' . $e->getMessage());
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Session - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)), url('../uploads/image003.jpg') center/cover; color: #1f2937; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: rgba(255,255,255,0.95); border-radius: 16px; box-shadow: 0 20px 40px rgba(15,23,42,0.15); overflow: hidden; }
        .header { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 2.5rem; }
        .nav { background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .nav a { color: #2563eb; text-decoration: none; margin: 0 15px; font-weight: 600; padding: 10px 15px; border-radius: 8px; transition: background 0.2s; }
        .nav a:hover { background: #e0f2fe; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #374151; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .btn { background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        @media (max-width: 768px) { .nav a { display: block; margin: 10px 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Book a Tutoring Session</h1>
            <p>Schedule & Pay to start your session</p>
        </div>
        <div class="nav">
            <a href="schedule.php">← My Schedule</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <div class="message info">
                <strong>💰 Pay KSh 500 to activate session</strong> - Pay after booking in schedule.
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="tutor_id">Tutor *</label>
                    <select id="tutor_id" name="tutor_id" required>
                        <option value="">Select tutor</option>
                        <?php foreach ($tutors as $t): ?>
                            <?php $selected = isset($_POST['tutor_id']) ? intval($_POST['tutor_id']) : $preselected_tutor_id; ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $selected === $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="subject">Subject *</label>
                    <select id="subject" name="subject" required>
                        <option value="">Select</option>
                        <option value="Mathematics" <?php echo ($_POST['subject'] ?? '') === 'Mathematics' ? 'selected' : ''; ?>>Math</option>
                        <option value="Physics" <?php echo ($_POST['subject'] ?? '') === 'Physics' ? 'selected' : ''; ?>>Physics</option>
                        <option value="Chemistry" <?php echo ($_POST['subject'] ?? '') === 'Chemistry' ? 'selected' : ''; ?>>Chemistry</option>
                        <option value="Biology" <?php echo ($_POST['subject'] ?? '') === 'Biology' ? 'selected' : ''; ?>>Biology</option>
                        <option value="English" <?php echo ($_POST['subject'] ?? '') === 'English' ? 'selected' : ''; ?> >English</option>
                        <option value="Computer Science" <?php echo ($_POST['subject'] ?? '') === 'Computer Science' ? 'selected' : ''; ?>>CS</option>
                        <option value="Other" <?php echo ($_POST['subject'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="time">Time *</label>
                    <select id="time" name="time" required>
                        <option value="">Select</option>
                        <option value="09:00">9AM</option>
                        <option value="10:00">10AM</option>
                        <option value="11:00">11AM</option>
                        <option value="12:00">12PM</option>
                        <option value="13:00">1PM</option>
                        <option value="14:00">2PM</option>
                        <option value="15:00">3PM</option>
                        <option value="16:00">4PM</option>
                        <option value="17:00">5PM</option>
                        <option value="18:00">6PM</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="duration">Duration</label>
                    <select id="duration" name="duration">
                        <option value="60">1h</option>
                        <option value="90">1.5h</option>
                        <option value="120">2h</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Requirements..."></textarea>
                </div>
                <button type="submit" class="btn">Book & Pay Later →</button>
            </form>
        </div>
    </div>
</body>
</html>

