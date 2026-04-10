<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['tutor']);

ensurePlatformStructures($pdo);

$tutorId = getTutorId($pdo, $_SESSION['user_id']);
$profile = fetchTutorProfile($pdo, $_SESSION['user_id']);

$stats = [
    'upcoming' => 0,
    'pending' => 0,
    'completed' => 0,
    'materials' => 0,
    'paid_sessions' => 0
];
$recentSessions = [];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tutor_id = ? AND status = 'confirmed' AND session_date >= NOW()");
    $stmt->execute([$tutorId]);
    $stats['upcoming'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tutor_id = ? AND status = 'pending'");
    $stmt->execute([$tutorId]);
    $stats['pending'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tutor_id = ? AND status = 'completed'");
    $stmt->execute([$tutorId]);
    $stats['completed'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tutor_id = ? AND payment_status = 'paid'");
    $stmt->execute([$tutorId]);
    $stats['paid_sessions'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tutor_materials WHERE tutor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['materials'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT s.id, s.subject, s.session_date, s.status, s.payment_status, u.name AS student_name
        FROM sessions s
        LEFT JOIN students st ON s.student_id = st.id
        LEFT JOIN users u ON st.user_id = u.id
        WHERE s.tutor_id = ?
        ORDER BY s.session_date ASC
        LIMIT 5
    ");
    $stmt->execute([$tutorId]);
    $recentSessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Tutor dashboard error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Dashboard - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)), url('../uploads/image003.jpg') center/cover no-repeat; color: #1f2937; margin: 0; padding: 20px; }
        .dashboard-container { max-width: 1400px; margin: 0 auto; background: rgba(255,255,255,0.96); border-radius: 16px; box-shadow: 0 24px 50px rgba(15,23,42,0.18); overflow: hidden; display: flex; min-height: 80vh; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #111827, #0f172a); padding: 32px 20px; color: white; }
        .sidebar h2 { margin: 0 0 24px; font-size: 1.15rem; color: #e2e8f0; }
        .sidebar .nav-link { display: block; width: 100%; margin-bottom: 12px; padding: 14px 16px; border-radius: 12px; color: white; text-decoration: none; font-size: 15px; font-weight: 600; background: rgba(255,255,255,0.08); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(56,189,248,0.18); }
        .sidebar .primary-link { background: #2563eb; }
        .sidebar .logout-btn { margin-top: 28px; background: rgba(239,68,68,0.85); }
        .main-content { flex: 1; padding: 30px; }
        .header { background: linear-gradient(135deg, #1d4ed8, #2563eb); color: white; padding: 30px; border-radius: 20px; margin-bottom: 30px; display:flex; justify-content:space-between; gap:20px; align-items:center; }
        .header h1 { margin: 0; font-size: 2.4rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 22px; margin-bottom: 30px; }
        .stat-card, .panel { background: white; border-radius: 18px; border: 1px solid #e5e7eb; padding: 24px; box-shadow: 0 12px 24px rgba(15,23,42,0.08); }
        .stat-card h3 { margin: 0; font-size: 1rem; color: #374151; }
        .stat-card .number { margin-top: 16px; font-size: 2.4rem; font-weight: 700; color: #1d4ed8; }
        .panel { margin-bottom: 26px; }
        .panel h2 { margin-top: 0; }
        .session-item { display:flex; justify-content:space-between; gap:20px; padding:18px 20px; border-radius:14px; border:1px solid #e5e7eb; margin-bottom:14px; background:#f9fafb; }
        .status-pill { display:inline-flex; align-items:center; justify-content:center; padding:8px 14px; border-radius:999px; font-size:0.8rem; font-weight:700; text-transform:uppercase; }
        .pending { background:#fef3c7; color:#b45309; }
        .confirmed { background:#dbeafe; color:#1d4ed8; }
        .completed { background:#d1fae5; color:#065f46; }
        .cancelled { background:#fee2e2; color:#991b1b; }
        .btn { display:inline-flex; align-items:center; justify-content:center; background:#2563eb; color:white; border:none; border-radius:12px; padding:12px 20px; font-size:0.95rem; font-weight:700; cursor:pointer; text-decoration:none; }
        .btn:hover { background:#1d4ed8; }
        .btn-secondary { background:#f3f4f6; color:#111827; }
        .btn-secondary:hover { background:#e5e7eb; }
        @media (max-width: 992px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; padding: 22px; } .header { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Tutor Control Panel</h2>
            <a href="schedule.php" class="nav-link primary-link">Manage Schedule</a>
            <a href="upload_materials.php" class="nav-link">Upload Materials</a>
            <a href="messages.php" class="nav-link">Messages</a>
            <a href="profile.php" class="nav-link">My Profile</a>
            <a href="my_sessions.php" class="nav-link">My Sessions</a>
            <a href="../config/auth/logout.php" class="nav-link logout-btn">Logout</a>
        </div>

        <div class="main-content">
            <div class="header">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
                    <p style="margin:10px 0 0; opacity:0.92;">Manage your schedule, avoid session collisions, upload learning materials, and keep your tutor profile visible to students who match your curriculum and location.</p>
                </div>
                <div style="background:rgba(255,255,255,0.14); border-radius:20px; padding:16px 18px; min-width:210px;">
                    <div style="font-size:0.85rem; opacity:0.85;">Verification</div>
                    <div style="font-size:1.5rem; font-weight:700; margin-top:8px;"><?php echo htmlspecialchars(ucfirst($profile['verification_status'] ?? 'submitted')); ?></div>
                    <div style="margin-top:8px;"><?php echo htmlspecialchars($profile['location'] ?? 'Location not set'); ?></div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Upcoming Sessions</h3>
                    <div class="number"><?php echo $stats['upcoming']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Requests</h3>
                    <div class="number"><?php echo $stats['pending']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Completed Sessions</h3>
                    <div class="number"><?php echo $stats['completed']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Paid Sessions</h3>
                    <div class="number"><?php echo $stats['paid_sessions']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Materials Uploaded</h3>
                    <div class="number"><?php echo $stats['materials']; ?></div>
                </div>
            </div>

            <div class="panel">
                <h2>Next Sessions</h2>
                <?php if (empty($recentSessions)): ?>
                    <p style="color:#6b7280;">No sessions are currently assigned to you.</p>
                <?php else: ?>
                    <?php foreach ($recentSessions as $session): ?>
                        <div class="session-item">
                            <div>
                                <strong><?php echo htmlspecialchars($session['student_name'] ?: 'Student'); ?></strong>
                                <div style="margin-top:8px; color:#6b7280; line-height:1.6;">
                                    Subject: <?php echo htmlspecialchars($session['subject']); ?><br>
                                    Date: <?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?><br>
                                    Payment: <?php echo htmlspecialchars(ucfirst($session['payment_status'] ?: 'unpaid')); ?>
                                </div>
                            </div>
                            <span class="status-pill <?php echo htmlspecialchars($session['status']); ?>"><?php echo htmlspecialchars($session['status']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:18px;">
                    <a href="schedule.php" class="btn">Open Schedule</a>
                    <a href="upload_materials.php" class="btn btn-secondary">Manage Materials</a>
                    <a href="profile.php" class="btn btn-secondary">Update Profile</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
