<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/SessionService.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

$message = '';
$messageType = '';
$session = null;

$session_id = intval($_GET['id'] ?? 0);
$studentId = getStudentId($pdo, $_SESSION['user_id']);

try {
    $session = SessionService::findStudentSession($pdo, $session_id, $studentId);

    if (!$session) {
        $message = 'Session not found or you do not have permission to edit it.';
        $messageType = 'error';
    }
} catch (PDOException $e) {
    $message = 'Error loading session details.';
    $messageType = 'error';
    error_log('Student edit session fetch error: ' . $e->getMessage());
}

$tutors = SessionService::fetchTutorDirectory($pdo);
$tutorsById = SessionService::mapTutorsById($tutors);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $session) {
    $tutor_id = intval($_POST['tutor_id'] ?? $session['tutor_id']);
    $selectedTutor = $tutorsById[$tutor_id] ?? null;
    $validation = SessionService::validateStudentBookingRequest($pdo, $studentId, $selectedTutor, $_POST, $session_id);
    $errors = $validation['errors'];

    if (empty($errors)) {
        try {
            SessionService::updateStudentBooking($pdo, $session_id, $studentId, $selectedTutor, $validation['data']);
            $_SESSION['edit_success'] = 'Session updated successfully.';
            header('Location: schedule.php');
            exit();
        } catch (PDOException $e) {
            $message = 'An error occurred while updating the session. Please try again.';
            $messageType = 'error';
            error_log('Student session update error: ' . $e->getMessage());
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
        $session = array_merge($session, [
            'subject' => trim($_POST['subject'] ?? $session['subject']),
            'tutor_id' => intval($_POST['tutor_id'] ?? $session['tutor_id']),
            'session_date' => $validation['data']['session_date'] ?: $session['session_date'],
            'duration' => intval($_POST['duration'] ?? $session['duration']),
            'notes' => trim($_POST['notes'] ?? $session['notes']),
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Session - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)), url('../uploads/image003.jpg') center/cover no-repeat; color: #1f2937; margin: 0; padding: 20px; }
        .container { max-width: 820px; margin: 0 auto; background: rgba(255,255,255,0.96); border-radius: 16px; box-shadow: 0 20px 40px rgba(15,23,42,0.15); overflow: hidden; }
        .header { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; padding: 30px; text-align: center; }
        .nav { background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .nav a { color: #2563eb; text-decoration: none; margin: 0 15px; font-weight: 600; padding: 10px 15px; border-radius: 8px; }
        .nav a:hover { background: #e0f2fe; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 1rem; box-sizing: border-box; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn { background: #2563eb; color: white; padding: 14px 28px; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .btn-secondary { background: #6b7280; margin-left: 10px; text-decoration:none; display:inline-flex; align-items:center; }
        .message { padding: 16px; border-radius: 12px; margin-bottom: 24px; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit Session</h1>
            <p>Update your tutor, date, time, and session details without creating schedule conflicts.</p>
        </div>

        <div class="nav">
            <a href="schedule.php">Back to Schedule</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($session): ?>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($session['subject']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="tutor_id">Tutor</label>
                            <select id="tutor_id" name="tutor_id" required>
                                <?php foreach ($tutors as $tutor): ?>
                                    <option value="<?php echo (int) $tutor['id']; ?>" <?php echo (int) $tutor['id'] === (int) $session['tutor_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tutor['name']); ?> - KSh <?php echo number_format(parseRateAmount($tutor['hourly_rate'] ?? null), 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" required value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d', strtotime($session['session_date']))); ?>" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="time">Time</label>
                            <input type="time" id="time" name="time" required value="<?php echo htmlspecialchars($_POST['time'] ?? date('H:i', strtotime($session['session_date']))); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="duration">Duration</label>
                        <select id="duration" name="duration">
                            <?php foreach ([60, 90, 120] as $minutes): ?>
                                <option value="<?php echo $minutes; ?>" <?php echo (int) $session['duration'] === $minutes ? 'selected' : ''; ?>><?php echo $minutes; ?> minutes</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea id="notes" name="notes"><?php echo htmlspecialchars($session['notes']); ?></textarea>
                    </div>

                    <button type="submit" class="btn">Save Changes</button>
                    <a href="schedule.php" class="btn btn-secondary">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
