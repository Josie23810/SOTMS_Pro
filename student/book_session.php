<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/SessionService.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

$message = '';
$messageType = '';
$studentId = getStudentId($pdo, $_SESSION['user_id']);
$studentProfile = fetchStudentProfile($pdo, $_SESSION['user_id']);
$preselected_tutor_id = intval($_GET['tutor'] ?? 0);

$tutors = SessionService::fetchTutorDirectory($pdo);
$tutorsById = SessionService::mapTutorsById($tutors);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tutor_id = intval($_POST['tutor_id'] ?? $preselected_tutor_id);
    $selectedTutor = $tutorsById[$tutor_id] ?? null;
    $validation = SessionService::validateStudentBookingRequest($pdo, $studentId, $selectedTutor, $_POST);
    $errors = $validation['errors'];

    if (empty($errors)) {
        try {
            SessionService::createStudentBooking($pdo, $studentId, $studentProfile ?: [], $selectedTutor, $validation['data']);
            $_SESSION['booking_success'] = 'Session booked successfully. The tutor can now review it, and payment can be completed from your schedule.';
            header('Location: schedule.php');
            exit();
        } catch (PDOException $e) {
            $message = 'Booking error. Please try again.';
            $messageType = 'error';
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
        .container { max-width: 900px; margin: 0 auto; background: rgba(255,255,255,0.96); border-radius: 16px; box-shadow: 0 20px 40px rgba(15,23,42,0.15); overflow: hidden; }
        .header { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 2.5rem; }
        .header p { margin: 10px 0 0; opacity: 0.92; }
        .nav { background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .nav a { color: #2563eb; text-decoration: none; margin: 0 15px; font-weight: 600; padding: 10px 15px; border-radius: 8px; }
        .nav a:hover { background: #e0f2fe; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 16px; box-sizing: border-box; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .btn { background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .message { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; border-radius: 10px; padding: 16px; margin-bottom: 20px; }
        .help { color: #64748b; font-size: 0.9rem; margin-top: 6px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } .nav a { display: block; margin: 10px 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Book a Tutoring Session</h1>
            <p>Choose a matched tutor, select an open time slot, and reserve your session without conflicts.</p>
        </div>

        <div class="nav">
            <a href="schedule.php">My Schedule</a>
            <a href="find_tutors.php">Find Tutors</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="info">
                Payment remains linked to the session after booking. We also check tutor and student calendars here so two sessions cannot collide.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="tutor_id">Tutor</label>
                    <select id="tutor_id" name="tutor_id" required>
                        <option value="">Select tutor</option>
                        <?php foreach ($tutors as $tutor): ?>
                            <?php $selected = intval($_POST['tutor_id'] ?? $preselected_tutor_id); ?>
                            <option value="<?php echo (int) $tutor['id']; ?>" <?php echo $selected === intval($tutor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tutor['name']); ?> - <?php echo htmlspecialchars($tutor['subjects_taught_display'] ?: 'General tutoring'); ?> - KSh <?php echo number_format($tutor['session_rate'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid">
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" placeholder="e.g. Mathematics, English, Physics" required>
                        <div class="help">Use the subject you want this specific session to cover.</div>
                    </div>
                    <div class="form-group">
                        <label for="duration">Duration</label>
                        <select id="duration" name="duration">
                            <option value="60" <?php echo ($_POST['duration'] ?? '60') === '60' ? 'selected' : ''; ?>>60 minutes</option>
                            <option value="90" <?php echo ($_POST['duration'] ?? '') === '90' ? 'selected' : ''; ?>>90 minutes</option>
                            <option value="120" <?php echo ($_POST['duration'] ?? '') === '120' ? 'selected' : ''; ?>>120 minutes</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="time">Time</label>
                        <input type="time" id="time" name="time" value="<?php echo htmlspecialchars($_POST['time'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Learning Notes</label>
                    <textarea id="notes" name="notes" placeholder="Share topics to focus on, weak areas, exam targets, or learning preferences"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn">Book Session</button>
            </form>
        </div>
    </div>
</body>
</html>
