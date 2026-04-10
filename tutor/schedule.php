<?php
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/auth_check.php';
require_once '../includes/services/SessionService.php';
checkAccess(['tutor']);

ensurePlatformStructures($pdo);

$message = '';
$messageType = '';
$sessions = [];
$tutorId = getTutorId($pdo, $_SESSION['user_id']);
$profile = fetchTutorProfile($pdo, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_id'], $_POST['action'])) {
    $session_id = intval($_POST['session_id']);
    $action = $_POST['action'];

    try {
        $result = SessionService::applyTutorScheduleAction($pdo, $session_id, $tutorId, $action);
        $message = $result['message'];
        $messageType = $result['type'];
    } catch (PDOException $e) {
        $message = 'An error occurred while updating the session.';
        $messageType = 'error';
        error_log('Tutor schedule action error: ' . $e->getMessage());
    }
}

try {
    $sessions = SessionService::fetchTutorScheduleSessions($pdo, $tutorId);
} catch (PDOException $e) {
    error_log('Tutor schedule fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Tutor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(180deg, rgba(15,23,42,0.6), rgba(15,23,42,0.6)), url('../uploads/image003.jpg') center/cover no-repeat; margin:0; color:#1f2937; padding:20px; }
        .container { max-width:1120px; margin:0 auto; background: rgba(255,255,255,0.96); border-radius:20px; overflow:hidden; box-shadow:0 24px 50px rgba(15,23,42,0.18);}
        .header { background: linear-gradient(135deg,#2563eb,#1d4ed8); color:white; padding:30px; }
        .header h1 { margin:0; font-size:2.4rem; }
        .nav { padding:20px; background:#f8fafc; border-bottom:1px solid #e5e7eb; }
        .nav a { color:#2563eb; margin-right:18px; text-decoration:none; font-weight:600; }
        .content { padding:30px; }
        .session-card { background:white; border:1px solid #e5e7eb; border-radius:18px; padding:22px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:flex-start; gap:20px; box-shadow:0 10px 24px rgba(15,23,42,0.08); }
        .session-card h3 { margin:0 0 8px; }
        .session-meta { color:#6b7280; margin:8px 0 0; line-height:1.6; }
        .status-pill { display:inline-flex; align-items:center; justify-content:center; padding:8px 14px; border-radius:999px; font-size:0.8rem; font-weight:700; text-transform:uppercase; }
        .pending{background:#fef3c7;color:#b45309;} .confirmed{background:#dbeafe;color:#1d4ed8;} .completed{background:#d1fae5;color:#065f46;} .cancelled{background:#fee2e2;color:#991b1b;}
        .btn { display:inline-flex; align-items:center; justify-content:center; background:#2563eb; color:white; padding:12px 20px; border:none; border-radius:12px; text-decoration:none; font-weight:700; cursor:pointer; }
        .btn:hover{background:#1d4ed8;}
        .btn-success { background:#10b981; }
        .btn-success:hover { background:#059669; }
        .btn-danger { background:#ef4444; }
        .btn-danger:hover { background:#dc2626; }
        .summary-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:16px; padding:18px; margin-bottom:24px; }
        .empty-state { text-align:center; color:#475569; padding:50px 20px; }
        @media(max-width:768px){ .session-card{flex-direction:column;} .nav a{display:block;margin-bottom:10px;} }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Schedule</h1>
            <p>Review requests, prevent collisions, and manage confirmed tutoring sessions.</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="upload_materials.php">Upload Materials</a>
            <a href="messages.php">Messages</a>
            <a href="profile.php">Profile</a>
        </div>
        <div class="content">
            <div class="summary-box">
                <strong>Availability summary:</strong>
                <?php echo htmlspecialchars($profile['availability_summary'] ?? ($profile['availability_days'] ?? 'Not set')); ?>
                | Max sessions per day: <?php echo htmlspecialchars((string) ($profile['max_sessions_per_day'] ?? 'Not set')); ?>
            </div>

            <?php if ($message): ?>
                <div style="background: <?php echo $messageType === 'success' ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $messageType === 'success' ? '#065f46' : '#991b1b'; ?>; border: 1px solid <?php echo $messageType === 'success' ? '#a7f3d0' : '#fecaca'; ?>; border-radius: 12px; padding: 16px; margin-bottom: 24px; font-weight: 600;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <h3>No scheduled sessions yet.</h3>
                    <p>Once students book sessions, they will appear here with their date, time, payment status, and curriculum details.</p>
                </div>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div class="session-card">
                        <div>
                            <h3><?php echo htmlspecialchars($session['student_name'] ?: 'Student'); ?></h3>
                            <div class="session-meta"><strong>Subject:</strong> <?php echo htmlspecialchars($session['subject']); ?></div>
                            <div class="session-meta"><strong>Date:</strong> <?php echo date('l, F j, Y \a\t g:i A', strtotime($session['session_date'])); ?></div>
                            <div class="session-meta"><strong>Duration:</strong> <?php echo (int) $session['duration']; ?> minutes</div>
                            <div class="session-meta"><strong>Curriculum:</strong> <?php echo htmlspecialchars($session['curriculum'] ?: 'Not provided'); ?></div>
                            <div class="session-meta"><strong>Study Level:</strong> <?php echo htmlspecialchars($session['study_level'] ?: 'Not provided'); ?></div>
                            <div class="session-meta"><strong>Payment:</strong> <?php echo htmlspecialchars(ucfirst($session['payment_status'] ?: 'unpaid')); ?></div>
                            <?php if (!empty($session['notes'])): ?>
                                <div class="session-meta"><strong>Notes:</strong> <?php echo htmlspecialchars($session['notes']); ?></div>
                            <?php endif; ?>
                            <?php if ($session['status'] === 'confirmed' && $session['meeting_link']): ?>
                                <div class="session-meta"><strong>Meeting Link:</strong> <a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" target="_blank" style="color:#10b981; font-weight:600;">Open</a></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                            <a href="edit_session.php?id=<?php echo (int) $session['id']; ?>" class="btn">Edit</a>
                            <span class="status-pill <?php echo htmlspecialchars($session['status']); ?>"><?php echo htmlspecialchars($session['status']); ?></span>
                            <?php if ($session['status'] === 'pending'): ?>
                                <div style="display:flex; gap:10px;">
                                    <form method="POST">
                                        <input type="hidden" name="session_id" value="<?php echo (int) $session['id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-success">Accept</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Decline this session request?');">
                                        <input type="hidden" name="session_id" value="<?php echo (int) $session['id']; ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <button type="submit" class="btn btn-danger">Decline</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
