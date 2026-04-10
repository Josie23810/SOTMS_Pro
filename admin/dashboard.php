<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['admin']);

ensurePlatformStructures($pdo);

$stats = [
    'users' => 0,
    'students' => 0,
    'tutors' => 0,
    'sessions' => 0,
    'pending_sessions' => 0,
    'materials' => 0,
    'payments' => 0,
    'paid_payments' => 0,
    'payment_reviews' => 0,
    'tutor_profiles' => 0,
    'pending_tutor_verifications' => 0
];
$recentActivity = [];

try {
    $stats['users'] = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['students'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $stats['tutors'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='tutor'")->fetchColumn();
    $stats['sessions'] = (int) $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
    $stats['pending_sessions'] = (int) $pdo->query("SELECT COUNT(*) FROM sessions WHERE status='pending'")->fetchColumn();
    $stats['materials'] = (int) $pdo->query("SELECT COUNT(*) FROM tutor_materials")->fetchColumn();
    $stats['payments'] = (int) $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
    $stats['paid_payments'] = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status='paid'")->fetchColumn();
    $stats['payment_reviews'] = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status IN ('pending', 'gateway_submitted', 'failed')")->fetchColumn();
    $stats['tutor_profiles'] = (int) $pdo->query("SELECT COUNT(*) FROM tutor_profiles")->fetchColumn();
    $stats['pending_tutor_verifications'] = (int) $pdo->query("SELECT COUNT(*) FROM tutor_profiles WHERE verification_status IN ('submitted', 'under_review')")->fetchColumn();

    $stmt = $pdo->query("
        SELECT
            s.subject,
            s.status,
            s.session_date,
            su.name AS student_name,
            tu.name AS tutor_name
        FROM sessions s
        LEFT JOIN students st ON s.student_id = st.id
        LEFT JOIN users su ON st.user_id = su.id
        LEFT JOIN tutors tt ON s.tutor_id = tt.id
        LEFT JOIN users tu ON tt.user_id = tu.id
        ORDER BY s.updated_at DESC
        LIMIT 8
    ");
    $recentActivity = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SOTMS Pro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, rgba(15,23,42,0.7), rgba(15,23,42,0.7)),
                        url('../uploads/image005.jpg') center/cover fixed no-repeat;
            margin: 0;
            color: #1f2937;
            min-height: 100vh;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: rgba(255,255,255,0.95);
            border-right: 1px solid #e2e8f0;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar h2 { text-align: center; color: #1f2937; margin-bottom: 30px; padding-bottom: 10px; border-bottom: 2px solid #2563eb; font-size: 1.4rem; }
        .sidebar-nav a {
            display: block;
            color: #374151;
            text-decoration: none;
            padding: 15px 25px;
            margin: 5px 20px;
            border-radius: 12px;
            font-weight: 500;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; }
        .main-content { margin-left: 250px; padding: 30px; min-height: 100vh; }
        .welcome-section, .panel {
            background: rgba(255,255,255,0.92);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .stat-card {
            background: rgba(255,255,255,0.92);
            border-radius: 20px;
            padding: 26px 22px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(37,99,235,0.1);
            border-top: 4px solid #2563eb;
        }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #1f2937; margin-bottom: 8px; }
        .stat-label { font-size: 0.95rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .activity-item { padding: 16px 0; border-bottom: 1px solid #e5e7eb; }
        .activity-item:last-child { border-bottom: none; }
        .quick-actions { display: flex; gap: 18px; flex-wrap: wrap; }
        .quick-btn { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; padding: 14px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; }
        .hamburger { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background: rgba(255,255,255,0.9); border: none; border-radius: 8px; padding: 12px; font-size: 1.5rem; cursor: pointer; }
        @media (max-width: 768px) {
            .sidebar { left: -250px; transition: left 0.3s; }
            .sidebar.open { left: 0; }
            .main-content { margin-left: 0; }
            .hamburger { display: block; }
        }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()">☰</button>

    <div class="sidebar" id="sidebar">
        <h2>Admin Panel</h2>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="manage_users.php">Manage Users</a>
            <a href="manage_sessions.php">Manage Sessions</a>
            <a href="tutor_verifications.php">Tutor Verifications</a>
            <a href="payments_review.php">Payments Review</a>
            <a href="reports.php">Reports</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="welcome-section">
            <h1 style="font-size: 2.4rem; margin-bottom: 15px;">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
            <p style="font-size: 1.1rem; color: #6b7280;">Monitor tutor onboarding, student bookings, learning materials, and payment activity across the entire SOTMS Pro platform.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $stats['users']; ?></div><div class="stat-label">Total Users</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['students']; ?></div><div class="stat-label">Students</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['tutors']; ?></div><div class="stat-label">Tutors</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['tutor_profiles']; ?></div><div class="stat-label">Tutor Profiles</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['sessions']; ?></div><div class="stat-label">Sessions</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['pending_sessions']; ?></div><div class="stat-label">Pending Sessions</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['materials']; ?></div><div class="stat-label">Materials</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['paid_payments']; ?>/<?php echo $stats['payments']; ?></div><div class="stat-label">Paid Payments</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['payment_reviews']; ?></div><div class="stat-label">Payments To Review</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['pending_tutor_verifications']; ?></div><div class="stat-label">Tutor Reviews</div></div>
        </div>

        <div class="panel">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <a href="manage_users.php" class="quick-btn">Manage Users</a>
                <a href="manage_sessions.php" class="quick-btn">Review Sessions</a>
                <a href="tutor_verifications.php" class="quick-btn">Tutor Verifications</a>
                <a href="payments_review.php" class="quick-btn">Payments Review</a>
                <a href="reports.php" class="quick-btn">Open Reports</a>
                <a href="system_readiness.php" class="quick-btn">System Readiness</a>
                <a href="../system_details.php" class="quick-btn">System Details</a>
            </div>
        </div>

        <div class="panel">
            <h2>Recent Activity</h2>
            <?php if (empty($recentActivity)): ?>
                <p style="color:#6b7280;">No recent session activity yet.</p>
            <?php else: ?>
                <?php foreach ($recentActivity as $activity): ?>
                    <div class="activity-item">
                        <strong><?php echo htmlspecialchars($activity['subject'] ?: 'Session'); ?></strong>
                        <div style="margin-top:6px; color:#475569;">
                            Student: <?php echo htmlspecialchars($activity['student_name'] ?: 'Unknown'); ?> |
                            Tutor: <?php echo htmlspecialchars($activity['tutor_name'] ?: 'Unassigned'); ?> |
                            Status: <?php echo htmlspecialchars($activity['status']); ?> |
                            Date: <?php echo date('M j, Y g:i A', strtotime($activity['session_date'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
    </script>
</body>
</html>
