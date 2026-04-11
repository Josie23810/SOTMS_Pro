<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['admin']);

ensurePlatformStructures($pdo);

if (isset($_GET['export'])) {
    $export = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sotms_' . $export . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');

    if ($export === 'users') {
        fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Registered']);
        $stmt = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC');
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['id'], $row['name'], $row['email'], $row['role'], $row['created_at']]);
        }
        exit;
    }

    if ($export === 'sessions') {
        fputcsv($output, ['ID', 'Student', 'Tutor', 'Subject', 'Curriculum', 'Study Level', 'Date', 'Status', 'Payment']);
        $stmt = $pdo->query("
            SELECT s.id, su.name AS student_name, tu.name AS tutor_name, s.subject, s.curriculum, s.study_level, s.session_date, s.status, s.payment_status
            FROM sessions s
            LEFT JOIN students st ON s.student_id = st.id
            LEFT JOIN users su ON st.user_id = su.id
            LEFT JOIN tutors tt ON s.tutor_id = tt.id
            LEFT JOIN users tu ON tt.user_id = tu.id
            ORDER BY s.session_date DESC
        ");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['id'], $row['student_name'], $row['tutor_name'], $row['subject'], $row['curriculum'], $row['study_level'], $row['session_date'], $row['status'], $row['payment_status']]);
        }
        exit;
    }

    if ($export === 'payments') {
        fputcsv($output, ['Reference', 'Provider', 'Amount', 'Status', 'Created']);
        $stmt = $pdo->query("SELECT reference, provider, amount, status, created_at FROM payments ORDER BY created_at DESC");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['reference'], $row['provider'], $row['amount'], $row['status'], $row['created_at']]);
        }
        exit;
    }

    if ($export === 'materials') {
        fputcsv($output, ['Tutor', 'Title', 'Subject', 'Curriculum', 'Study Level', 'Uploaded']);
        $stmt = $pdo->query("
            SELECT u.name AS tutor_name, tm.title, tm.subject, tm.curriculum, tm.study_level, tm.uploaded_at
            FROM tutor_materials tm
            JOIN users u ON tm.tutor_id = u.id
            ORDER BY tm.uploaded_at DESC
        ");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['tutor_name'], $row['title'], $row['subject'], $row['curriculum'], $row['study_level'], $row['uploaded_at']]);
        }
        exit;
    }
}

$totalRevenue = (float) ($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status='paid'")->fetchColumn() ?: 0);
$paidPayments = (int) ($pdo->query("SELECT COUNT(*) FROM payments WHERE status='paid'")->fetchColumn() ?: 0);
$paymentsAwaitingReview = (int) ($pdo->query("SELECT COUNT(*) FROM payments WHERE status IN ('pending', 'gateway_submitted', 'failed')")->fetchColumn() ?: 0);
$pendingTutorVerifications = (int) ($pdo->query("SELECT COUNT(*) FROM tutor_profiles WHERE verification_status IN ('submitted', 'under_review')")->fetchColumn() ?: 0);
$recentRegistrations = (int) ($pdo->query("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn() ?: 0);
$todaySessions = (int) ($pdo->query("SELECT COUNT(*) FROM sessions WHERE DATE(session_date) = CURDATE()")->fetchColumn() ?: 0);
$avgDuration = (float) ($pdo->query("SELECT COALESCE(AVG(duration), 0) FROM sessions")->fetchColumn() ?: 0);
$materialsCount = (int) ($pdo->query("SELECT COUNT(*) FROM tutor_materials")->fetchColumn() ?: 0);

$statusCounts = $pdo->query("SELECT status, COUNT(*) as count FROM sessions GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$providerCounts = $pdo->query("SELECT provider, COUNT(*) as count FROM payments GROUP BY provider")->fetchAll(PDO::FETCH_KEY_PAIR);
$legacyPaymentCount = max(0, (int) array_sum($providerCounts) - (int) ($providerCounts['pesapal'] ?? 0));
$recentSessions = $pdo->query("
    SELECT s.subject, s.status, s.session_date, su.name AS student_name, tu.name AS tutor_name
    FROM sessions s
    LEFT JOIN students st ON s.student_id = st.id
    LEFT JOIN users su ON st.user_id = su.id
    LEFT JOIN tutors tt ON s.tutor_id = tt.id
    LEFT JOIN users tu ON tt.user_id = tu.id
    ORDER BY s.session_date DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(rgba(15,23,42,0.7), rgba(15,23,42,0.7)), url('../uploads/image005.jpg') center/cover fixed; margin:0; padding:20px; }
        .container { max-width:1240px; margin:0 auto; background:rgba(255,255,255,0.96); border-radius:20px; box-shadow:0 25px 50px rgba(0,0,0,0.2); overflow:hidden; }
        .header { background:linear-gradient(135deg, #f59e0b, #d97706); color:white; padding:25px; text-align:center; }
        .header h1 { margin:0; font-size:2.2rem; }
        .nav-top { background:#f8fafc; padding:20px 30px; border-bottom:1px solid #e2e8f0; display:flex; gap:20px; justify-content:center; flex-wrap:wrap; }
        .export-btn { background:linear-gradient(135deg, #10b981, #059669); color:white; padding:12px 24px; border-radius:10px; text-decoration:none; font-weight:600; }
        .content { padding:40px; }
        .report-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:26px; margin-bottom:30px; }
        .report-card { background:white; border-radius:16px; padding:26px; box-shadow:0 15px 35px rgba(0,0,0,0.08); border-top:5px solid #f59e0b; }
        .report-card h3 { margin:0 0 18px; color:#1f2937; }
        .metric { font-size:1.02rem; margin:14px 0; display:flex; justify-content:space-between; gap:20px; }
        .metric-value { font-weight:700; font-size:1.25rem; color:#2563eb; }
        .summary-line { color:#475569; line-height:1.8; }
        .recent-table { width:100%; margin-top:20px; border-collapse:collapse; }
        .recent-table th { background:#f8fafc; padding:12px; font-weight:600; text-align:left; }
        .recent-table td { padding:12px; border-bottom:1px solid #f1f5f9; }
        @media (max-width:768px) { .report-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reports & Analytics</h1>
            <p>Track platform usage, tutoring activity, materials, and payments.</p>
        </div>

        <div class="nav-top">
            <a href="dashboard.php" class="export-btn">Dashboard</a>
            <a href="?export=users" class="export-btn">Users CSV</a>
            <a href="?export=sessions" class="export-btn">Sessions CSV</a>
            <a href="?export=payments" class="export-btn">Payments CSV</a>
            <a href="?export=materials" class="export-btn">Materials CSV</a>
        </div>

        <div class="content">
            <div class="report-grid">
                <div class="report-card">
                    <h3>Revenue Overview</h3>
                    <div class="metric"><span>Total Revenue</span><span class="metric-value">KSh <?php echo number_format($totalRevenue, 2); ?></span></div>
                    <div class="metric"><span>Paid Payments</span><span class="metric-value"><?php echo $paidPayments; ?></span></div>
                    <div class="metric"><span>Payments Awaiting Review</span><span class="metric-value"><?php echo $paymentsAwaitingReview; ?></span></div>
                </div>

                <div class="report-card">
                    <h3>User Growth</h3>
                    <div class="metric"><span>Recent Registrations (7d)</span><span class="metric-value"><?php echo $recentRegistrations; ?></span></div>
                    <div class="metric"><span>Learning Materials</span><span class="metric-value"><?php echo $materialsCount; ?></span></div>
                    <div class="metric"><span>Tutor Verifications</span><span class="metric-value"><?php echo $pendingTutorVerifications; ?></span></div>
                </div>

                <div class="report-card">
                    <h3>Session Stats</h3>
                    <div class="metric"><span>Today's Sessions</span><span class="metric-value"><?php echo $todaySessions; ?></span></div>
                    <div class="metric"><span>Avg Duration</span><span class="metric-value"><?php echo round($avgDuration, 1); ?> min</span></div>
                </div>

                <div class="report-card">
                    <h3>Session Status Breakdown</h3>
                    <div class="summary-line">
                        Pending: <?php echo (int) ($statusCounts['pending'] ?? 0); ?><br>
                        Confirmed: <?php echo (int) ($statusCounts['confirmed'] ?? 0); ?><br>
                        Completed: <?php echo (int) ($statusCounts['completed'] ?? 0); ?><br>
                        Cancelled: <?php echo (int) ($statusCounts['cancelled'] ?? 0); ?>
                    </div>
                </div>

                <div class="report-card">
                    <h3>Payment Channels</h3>
                    <div class="summary-line">
                        PesaPal: <?php echo (int) ($providerCounts['pesapal'] ?? 0); ?><br>
                        Legacy Records: <?php echo $legacyPaymentCount; ?>
                    </div>
                </div>
            </div>

            <div class="report-card">
                <h3>Recent Session Activity</h3>
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Tutor</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSessions)): ?>
                            <tr><td colspan="5">No recent sessions</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentSessions as $session): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($session['student_name'] ?: 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($session['tutor_name'] ?: 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($session['subject'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($session['status']); ?></td>
                                    <td><?php echo date('M j, g:i A', strtotime($session['session_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
