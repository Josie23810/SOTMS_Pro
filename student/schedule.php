<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']);

$deletion_message = '';
$deletion_type = '';

// Handle session deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_session_id'])) {
    $session_id = intval($_POST['delete_session_id']);
    $studentId = getStudentId($pdo, $_SESSION['user_id']);
    
    try {
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE id = ? AND student_id = ?');
        $stmt->execute([$session_id, $studentId]);
        
        if ($stmt->rowCount() > 0) {
            $deletion_message = "✓ Session deleted successfully.";
            $deletion_type = 'success';
        } else {
            $deletion_message = "Session not found or you don't have permission to delete it.";
            $deletion_type = 'error';
        }
    } catch (PDOException $e) {
        $deletion_message = "An error occurred while deleting the session.";
        $deletion_type = 'error';
        error_log('Session deletion error: ' . $e->getMessage());
    }
}

// Get user's sessions - FULL SELECT
$sessions = [];
try {
    $studentId = getStudentId($pdo, $_SESSION['user_id']);
    
    if ($studentId) {
        $stmt = $pdo->prepare('
            SELECT s.id, s.session_date, s.subject, s.duration, s.notes, s.status, s.tutor_id, s.meeting_link, s.payment_status, s.amount, 
                   u.id as tutor_user_id, u.name as tutor_name 
            FROM sessions s 
            LEFT JOIN tutors t ON s.tutor_id = t.id
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE s.student_id = ? 
            ORDER BY s.session_date ASC
        ');
        $stmt->execute([$studentId]);
        $sessions = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Sessions fetch error: ' . $e->getMessage());
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
            background: rgba(255,255,255,0.95);
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
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
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
            transition: background 0.2s;
        }
        .nav a:hover {
            background: #e0f2fe;
        }
        .content {
            padding: 30px;
        }
        .schedule-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .session-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .session-info h3 {
            margin: 0 0 5px;
            color: #1f2937;
        }
        .session-meta {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .session-details {
            flex: 1;
        }
        .session-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-confirmed { background: #dbeafe; color: #2563eb; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .btn { background: #2563eb; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-decoration: none; margin-left: 10px; }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-pay { background: #ef4444; }
        .btn-pay:hover { background: #dc2626; }
        .paid-badge { background: #d1fae5; color: #065f46; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .no-sessions { text-align: center; padding: 50px; color: #6b7280; }
        .calendar-view { margin-top: 30px; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
        .calendar-day { background: white; border: 1px solid #e2e8f0; padding: 10px; text-align: center; min-height: 80px; }
        .calendar-day-header { background: #f8fafc; font-weight: 600; color: #374151; }
        .has-session { background: #dbeafe; border-color: #2563eb; }
        @media (max-width: 768px) { .session-card { flex-direction: column; align-items: flex-start; } .btn { margin: 5px 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Schedule</h1>
            <p>View and manage your tutoring sessions</p>
        </div>
        
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="book_session.php">Book New Session</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>
        
        <div class="content">
            <?php if ($deletion_message): ?>
                <div style="background: <?php echo $deletion_type === 'success' ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $deletion_type === 'success' ? '#065f46' : '#991b1b'; ?>; border: 1px solid <?php echo $deletion_type === 'success' ? '#a7f3d0' : '#fecaca'; ?>; border-radius: 12px; padding: 16px; margin-bottom: 24px; font-weight: 600;">
                    <?php echo htmlspecialchars($deletion_message); ?>
                </div>
            <?php endif; ?>
            <div class="schedule-section">
                <h2>Your Sessions</h2>
                <?php if (empty($sessions)): ?>
                    <div class="no-sessions">
                        <h3>No sessions scheduled</h3>
                        <p>You haven't booked any tutoring sessions yet.</p>
                        <a href="book_session.php" class="btn">Book Your First Session</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($sessions as $session): ?>
                        <div class="session-card">
                            <div class="session-details">
                                <h3><?php echo htmlspecialchars($session['subject']); ?></h3>
                                <div class="session-meta">
                                    Date & Time: <?php echo date('l, F j, Y \a\t g:i A', strtotime($session['session_date'])); ?> • 
                                    Duration: <?php echo $session['duration']; ?> minutes
                                    <?php if ($session['tutor_name']): ?>
                                        • Tutor: <?php echo htmlspecialchars($session['tutor_name']); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($session['notes'])): ?>
                                    <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($session['notes'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">

                                <a href="pay_session.php?id=<?php echo $session['id']; ?>" class="btn-pay session-status status-<?php echo $session['status']; ?>">
                                    <?php echo $session['payment_status'] === 'unpaid' ? '💳 Pay Now' : ucfirst($session['status']); ?>
                                </a>
                                <?php if ($session['status'] === 'confirmed' && $session['meeting_link']): ?>
                                    <a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" class="btn" style="background: #059669;" target="_blank">🎯 Join</a>
                                <?php endif; ?>
                                <a href="messages.php?to=<?php echo htmlspecialchars($session['tutor_user_id']); ?>" class="btn btn-secondary">Message</a>
                                <?php if ($session['status'] === 'completed'): ?>
                                    <?php if ($session['payment_status'] === 'unpaid'): ?>
                                        <a href="pay_session.php?id=<?php echo $session['id']; ?>" class="btn btn-pay">💳 Pay KSh <?php echo number_format(($session['amount'] ?: 20.00), 2); ?></a>
                                    <?php else: ?>
                                        <span class="paid-badge">PAID ✓</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="edit_session.php?id=<?php echo $session['id']; ?>" class="btn" style="background: #10b981;">Edit</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this session?');">
                                    <input type="hidden" name="delete_session_id" value="<?php echo $session['id']; ?>">
                                    <button type="submit" class="btn" style="background: #ef4444;">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="calendar-view">
                <div class="calendar-header">
                    <h2>Calendar View</h2>
                    <div>
                        <button class="btn btn-secondary">Previous</button>
                        <button class="btn btn-secondary">Next</button>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="calendar-day calendar-day-header">Sun</div>
                    <div class="calendar-day calendar-day-header">Mon</div>
                    <div class="calendar-day calendar-day-header">Tue</div>
                    <div class="calendar-day calendar-day-header">Wed</div>
                    <div class="calendar-day calendar-day-header">Thu</div>
                    <div class="calendar-day calendar-day-header">Fri</div>
                    <div class="calendar-day calendar-day-header">Sat</div>
                    <?php for ($i = 1; $i <= 35; $i++): ?>
                        <div class="calendar-day <?php echo ($i % 7 === 0 || $i % 7 === 3) ? 'has-session' : ''; ?>">
                            <?php echo $i <= 31 ? $i : ''; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

