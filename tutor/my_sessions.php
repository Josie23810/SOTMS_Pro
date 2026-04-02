<?php
// No auth_check - public access to own sessions via session restore or tutor login
session_start();
require_once '../config/db.php';
require_once '../includes/user_helpers.php';

// Manual session restore for tutors
function restoreTutorSession($pdo) {
    if (!isset($_COOKIE['session_token']) || !isset($_SESSION['user_id'])) {
        return false;
    }
    $session_token = $_COOKIE['session_token'];
    try {
        $stmt = $pdo->prepare("SELECT us.user_id, us.role FROM user_sessions us WHERE us.session_token = ? AND us.role = 'tutor' AND us.expires_at > NOW()");
        $stmt->execute([$session_token]);
        $session = $stmt->fetch();
        if ($session && $session['user_id'] == $_SESSION['user_id']) {
            $_SESSION['role'] = 'tutor';
            return true;
        }
    } catch (PDOException $e) {
        error_log('Tutor session restore error: ' . $e->getMessage());
    }
    return false;
}

if (!restoreTutorSession($pdo) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor')) {
    header("Location: ../../config/auth/login.php");
    exit();
}

$message = '';
$messageType = '';
$sessions = [];

// Get tutor ID
$tutorId = getTutorId($pdo, $_SESSION['user_id']);

// Handle accept/decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_id']) && isset($_POST['action'])) {
    $session_id = intval($_POST['session_id']);
    $action = $_POST['action'];
    $new_status = $action === 'accept' ? 'confirmed' : 'cancelled';
    $success_msg = $action === 'accept' ? 'Session accepted!' : 'Session declined.';
    
    try {
        $stmt = $pdo->prepare('SELECT id FROM sessions WHERE id = ? AND tutor_id = ?');
        $stmt->execute([$session_id, $tutorId]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare('UPDATE sessions SET status = ? WHERE id = ? AND tutor_id = ?');
            $stmt->execute([$new_status, $session_id, $tutorId]);
            $message = $success_msg;
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Update failed.';
        $messageType = 'error';
    }
}

// Fetch sessions - FIXED student name JOIN + meeting_link
try {
    $stmt = $pdo->prepare("SELECT s.id, s.session_date, s.status, s.subject, s.duration, s.notes, s.meeting_link, COALESCE(u.name, 'Anonymous Student') as student_name FROM sessions s LEFT JOIN students st ON s.student_id = st.id LEFT JOIN users u ON st.user_id = u.id WHERE s.tutor_id = ? ORDER BY s.session_date ASC");
    $stmt->execute([$tutorId]);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Sessions fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - Tutor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(180deg, rgba(15,23,42,0.6), rgba(15,23,42,0.6)), url('../uploads/image003.jpg') center/cover no-repeat; margin:0; color:#1f2937; padding:20px; }
        .container { max-width:1100px; margin:0 auto; background: rgba(255,255,255,0.96); border-radius:20px; overflow:hidden; box-shadow:0 24px 50px rgba(15,23,42,0.18);} 
        .header { background: linear-gradient(135deg,#2563eb,#1d4ed8); color:white; padding:30px; text-align:center; }
        .header h1 { margin:0; font-size:2.4rem; }
        .nav { padding:20px; background:#f8fafc; border-bottom:1px solid #e5e7eb; display:flex; gap:10px; flex-wrap:wrap; }
        .nav a { color:#2563eb; text-decoration:none; font-weight:600; padding:8px 16px; border-radius:8px; transition:background 0.2s; }
        .nav a:hover { background:#dbeafe; }
        .content { padding:30px; }
        .session-card { background:white; border:1px solid #e5e7eb; border-radius:18px; padding:24px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:flex-start; gap:20px; box-shadow:0 10px 24px rgba(15,23,42,0.08); }
        .session-left { flex:1; }
        .session-card h3 { margin:0 0 12px; color:#1f2937; }
        .session-meta { color:#6b7280; margin:8px 0; line-height:1.6; font-size:0.95rem; }
        .status-pill { display:inline-flex; align-items:center; padding:8px 16px; border-radius:20px; font-size:0.85rem; font-weight:700; text-transform:uppercase; }
        .pending { background:#fef3c7; color:#b45309; }
        .confirmed { background:#dbeafe; color:#1d4ed8; }
        .completed { background:#d1fae5; color:#065f46; }
        .cancelled { background:#fee2e2; color:#991b1b; }
        .btn-group { display:flex; gap:12px; flex-direction:column; align-items:flex-end; }
        .btn { background:#2563eb; color:white; border:none; padding:12px 20px; border-radius:12px; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.2s; font-size:0.95rem; }
        .btn:hover { background:#1d4ed8; transform:translateY(-1px); }
        .btn-success { background:#10b981; }
        .btn-success:hover { background:#059669; }
        .btn-danger { background:#ef4444; }
        .btn-danger:hover { background:#dc2626; }
        .btn-edit { background:#f59e0b !important; }
        .btn-edit:hover { background:#d97706 !important; }
        .message { padding:16px; border-radius:12px; margin-bottom:24px; font-weight:600; }
        .message.success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .message.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .empty-state { text-align:center; padding:60px 20px; color:#6b7280; }
        .meeting-preview { background:#f0fdf4; padding:12px; border-radius:8px; border-left:4px solid #10b981; margin:8px 0; font-size:0.9rem; }
        .meeting-preview a { color:#059669; font-weight:600; }
        @media (max-width:768px) { .session-card { flex-direction:column; align-items:stretch; } .btn-group { flex-direction:row; justify-content:flex-end; } .nav { flex-direction:column; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Tutoring Sessions</h1>
            <p>All your booked sessions and student bookings - real student names now display</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">← Dashboard</a>
            <a href="schedule.php">Schedule</a>
            <a href="messages.php">Messages</a>
            <a href="../config/auth/my_sessions.php">Login Sessions</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <h3>No sessions booked yet</h3>
                    <p>Your tutoring sessions will appear here once students book with you.</p>
                    <a href="dashboard.php" class="btn">Go to Dashboard</a>
                </div>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div class="session-card">
                        <div class="session-left">
                            <h3><?php echo htmlspecialchars($session['student_name']); ?></h3>
                            <div class="session-meta"><strong>📚 Subject:</strong> <?php echo htmlspecialchars($session['subject'] ?: 'General'); ?></div>
                            <div class="session-meta"><strong>📅 Date & Time:</strong> <?php echo date('l, F j, Y \a\t g:i A', strtotime($session['session_date'])); ?></div>
                            <div class="session-meta"><strong>⏱️ Duration:</strong> <?php echo $session['duration'] ?: 60; ?> minutes</div>
                            <?php if (!empty($session['notes'])): ?>
                                <div class="session-meta"><strong>📝 Notes:</strong> <?php echo htmlspecialchars(substr($session['notes'], 0, 100)) . (strlen($session['notes']) > 100 ? '...' : ''); ?></div>
                            <?php endif; ?>
                            <?php if ($session['status'] === 'confirmed' && !empty($session['meeting_link'])): ?>
                                <div class="meeting-preview">
                                    <strong>🔗 Meeting Link:</strong> <a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" target="_blank"><?php echo htmlspecialchars(parse_url($session['meeting_link'], PHP_URL_HOST)); ?></a>
                                </div>
                            <?php elseif ($session['status'] === 'confirmed'): ?>
                                <div class="session-meta"><strong>🔗 Meeting:</strong> <span style="color:orange;">Set Meeting Link</span></div>
                            <?php endif; ?>
                            <span class="status-pill <?php echo $session['status']; ?>"><?php echo ucfirst($session['status']); ?></span>
                        </div>
                        <div class="btn-group">
                            <a href="edit_session.php?id=<?php echo $session['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                            <?php if ($session['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="btn btn-success">Accept Session</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <input type="hidden" name="action" value="decline">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Decline this session?')">Decline</button>
                                </form>
                            <?php else: ?>
                                <a href="messages.php" class="btn">Message Student</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

