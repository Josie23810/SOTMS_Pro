<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['admin']);

if (isset($_GET['export']) && $_GET['export'] === 'users') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sotms_users_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Registered']);
    
    $stmt = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC');
    while ($row = $stmt->fetch()) {
        fputcsv($output, [$row['id'], $row['name'], $row['email'], $row['role'], $row['created_at']]);
    }
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'sessions') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sotms_sessions_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Student', 'Subject', 'Date', 'Status', 'Duration']);
    
    $stmt = $pdo->query('SELECT s.id, s.session_date, s.status, s.subject, u.name as student_name FROM sessions s LEFT JOIN users u ON s.student_id = u.id ORDER BY s.created_at DESC LIMIT 10');
    while ($row = $stmt->fetch()) {
        fputcsv($output, [$row['id'], $row['student_name'], $row['subject'], $row['session_date'], $row['status'], $row['duration']]);
    }
    exit;
}

$total_revenue = $pdo->query("SELECT SUM(duration * 0.5) as revenue FROM sessions WHERE status='completed'")->fetchColumn() ?: 0;
$recent_registrations = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
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
        .container { max-width:1200px; margin:0 auto; background:rgba(255,255,255,0.95); border-radius:20px; box-shadow:0 25px 50px rgba(0,0,0,0.2); overflow:hidden; }
        .header { background:linear-gradient(135deg, #f59e0b, #d97706); color:white; padding:25px; text-align:center; }
        .header h1 { margin:0; font-size:2.2rem; }
        .nav-top { background:#f8fafc; padding:20px 30px; border-bottom:1px solid #e2e8f0; display:flex; gap:20px; justify-content:center; flex-wrap:wrap; }
        .export-btn { background:linear-gradient(135deg, #10b981, #059669); color:white; padding:12px 24px; border-radius:10px; text-decoration:none; font-weight:600; transition:all 0.3s; box-shadow:0 4px 15px rgba(16,185,129,0.3); }
        .export-btn:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(16,185,129,0.4); }
        .content { padding:40px; }
        .report-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(350px,1fr)); gap:30px; margin-bottom:40px; }
        .report-card { background:white; border-radius:16px; padding:30px; box-shadow:0 15px 35px rgba(0,0,0,0.08); border-top:5px solid #f59e0b; }
        .report-card h3 { margin:0 0 20px; color:#1f2937; }
        .metric { font-size:1.1rem; margin:15px 0; display:flex; justify-content:space-between; }
        .metric-value { font-weight:700; font-size:1.8rem; color:#2563eb; }
        .recent-table { width:100%; margin-top:20px; }
        .recent-table th { background:#f8fafc; padding:12px; font-weight:600; }
        .recent-table td { padding:12px; border-bottom:1px solid #f1f5f9; }
        @media (max-width:768px) { .report-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📈 Reports & Analytics</h1>
            <p>Download data, view insights, track platform performance</p>
        </div>
        
        <div class="nav-top">
            <a href="dashboard.php" class="export-btn">← Dashboard</a>
            <a href="?export=users" class="export-btn">📋 Users CSV</a>
            <a href="?export=sessions" class="export-btn">📋 Sessions CSV</a>
            <a href="dashboard.php" class="export-btn">Refresh Stats</a>
        </div>
        
        <div class="content">
            <canvas id="sessionStatusChart" style="max-height: 300px; margin-bottom: 30px;"></canvas>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
            <?php
            $status_counts = $pdo->query("SELECT status, COUNT(*) as count FROM sessions GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
            ?>
            const ctx = document.getElementById('sessionStatusChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
                    datasets: [{
                        data: [<?php echo $status_counts['pending'] ?? 0; ?>, <?php echo $status_counts['confirmed'] ?? 0; ?>, <?php echo $status_counts['completed'] ?? 0; ?>, <?php echo $status_counts['cancelled'] ?? 0; ?>],
                        backgroundColor: ['#f59e0b', '#10b981', '#3b82f6', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Session Status Distribution' } }
                }
            });
            </script>
            <div class="report-grid">
                <div class="report-card">
                    <h3>💰 Revenue Overview</h3>
                    <div class="metric">
                        <span>Total Revenue</span>
                        <span class="metric-value">$<?php echo number_format($total_revenue, 2); ?></span>
                    </div>
                    <div class="metric">
                        <span>Completed Sessions</span>
                        <span class="metric-value"><?php echo $pdo->query("SELECT COUNT(*) FROM sessions WHERE status='completed'")->fetchColumn(); ?></span>
                    </div>
                </div>
                
                <div class="report-card">
                    <h3>📊 User Growth</h3>
                    <div class="metric">
                        <span>Recent Registrations (7d)</span>
                        <span class="metric-value"><?php echo $recent_registrations; ?></span>
                    </div>
                    <div class="metric">
                        <span>Active Students</span>
                        <span class="metric-value"><?php echo $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 0; ?></span>
                    </div>
                </div>
                
                <div class="report-card">
                    <h3>📅 Session Stats</h3>
                    <div class="metric">
                        <span>Today's Sessions</span>
                        <span class="metric-value"><?php echo $pdo->query("SELECT COUNT(*) FROM sessions WHERE DATE(session_date) = CURDATE()")->fetchColumn(); ?></span>
                    </div>
                    <div class="metric">
                        <span>Avg Duration</span>
                        <span class="metric-value"><?php echo round(($pdo->query("SELECT AVG(duration) FROM sessions")->fetchColumn() ?: 0), 1); ?> min</span>
                    </div>
                </div>
            </div>

            <div class="report-card" style="grid-column:1 / -1;">
                <h3>Recent Activity</h3>
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $recent_sessions = $pdo->query("SELECT s.id, s.subject, s.status, s.session_date, u.name as student_name FROM sessions s LEFT JOIN users u ON s.student_id = u.id ORDER BY s.session_date DESC LIMIT 10")->fetchAll();
                            if (empty($recent_sessions)):
                        ?>
                                <tr><td colspan="3">No recent sessions</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_sessions as $sess): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sess['student_name']); ?></td>
                                    <td><?php echo ucfirst($sess['status']); ?> - <?php echo htmlspecialchars($sess['subject']); ?></td>
                                    <td><?php echo date('M j, g:i A', strtotime($sess['session_date'])); ?></td>
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
