<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['admin']);

ensurePlatformStructures($pdo);

function dashboardPeriodOptions(): array
{
    return [
        '7d' => ['label' => 'Last 7 Days'],
        '30d' => ['label' => 'Last 30 Days'],
        '90d' => ['label' => 'Last 90 Days'],
        '365d' => ['label' => 'Last 12 Months'],
    ];
}

function selectedDashboardPeriod(string $queryKey, string $default = '30d'): string
{
    $options = dashboardPeriodOptions();
    return isset($_GET[$queryKey], $options[$_GET[$queryKey]]) ? $_GET[$queryKey] : $default;
}

function buildDashboardBuckets(string $periodKey): array
{
    $now = new DateTimeImmutable('now');
    $buckets = [];

    if ($periodKey === '365d') {
        $currentMonth = $now->setTime(0, 0)->modify('first day of this month');
        for ($i = 11; $i >= 0; $i--) {
            $start = $currentMonth->modify("-{$i} months");
            $end = $start->modify('+1 month');
            $buckets[] = [
                'label' => $start->format('M'),
                'start' => $start,
                'end' => $end,
            ];
        }
        return $buckets;
    }

    $groupSize = $periodKey === '90d' ? 15 : ($periodKey === '30d' ? 5 : 1);
    $days = $periodKey === '90d' ? 90 : ($periodKey === '30d' ? 30 : 7);
    $bucketCount = (int) ceil($days / $groupSize);
    $windowStart = $now->setTime(0, 0)->modify('-' . ($days - 1) . ' days');

    for ($i = 0; $i < $bucketCount; $i++) {
        $start = $windowStart->modify('+' . ($i * $groupSize) . ' days');
        $end = $start->modify("+{$groupSize} days");
        $lastDay = $end->modify('-1 day');
        $label = $groupSize === 1 ? $start->format('D') : $start->format('M j') . ' - ' . $lastDay->format('M j');

        $buckets[] = [
            'label' => $label,
            'start' => $start,
            'end' => $end,
        ];
    }

    return $buckets;
}

function formatChartValue(float $value, string $type = 'count'): string
{
    if ($type === 'money') {
        return 'KSh ' . number_format($value, 0);
    }

    return number_format($value, 0);
}

$periodOptions = dashboardPeriodOptions();
$revenuePeriodKey = selectedDashboardPeriod('revenue_period');
$sessionsPeriodKey = selectedDashboardPeriod('sessions_period');
$paymentsPeriodKey = selectedDashboardPeriod('payments_period');

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
$charts = [
    'revenue' => [
        'period' => $revenuePeriodKey,
        'label' => $periodOptions[$revenuePeriodKey]['label'],
        'points' => [],
        'total' => 0.0,
    ],
    'sessions' => [
        'period' => $sessionsPeriodKey,
        'label' => $periodOptions[$sessionsPeriodKey]['label'],
        'points' => [],
        'total' => 0,
    ],
    'payments' => [
        'period' => $paymentsPeriodKey,
        'label' => $periodOptions[$paymentsPeriodKey]['label'],
        'rows' => [],
        'total' => 0,
    ],
];
$kpis = [
    'completion_rate' => 0.0,
    'payment_capture_rate' => 0.0,
    'active_students' => 0,
    'active_tutors' => 0,
    'avg_session_value' => 0,
    'upcoming_7d' => 0,
    'failed_payments' => 0,
];

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

    $revenueBuckets = buildDashboardBuckets($revenuePeriodKey);
    $revenueStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status='paid' AND created_at >= :start AND created_at < :end");
    foreach ($revenueBuckets as $bucket) {
        $revenueStmt->execute([
            ':start' => $bucket['start']->format('Y-m-d H:i:s'),
            ':end' => $bucket['end']->format('Y-m-d H:i:s'),
        ]);
        $value = (float) ($revenueStmt->fetchColumn() ?: 0);
        $charts['revenue']['points'][] = [
            'label' => $bucket['label'],
            'value' => $value,
        ];
        $charts['revenue']['total'] += $value;
    }

    $sessionBuckets = buildDashboardBuckets($sessionsPeriodKey);
    $sessionStmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE session_date >= :start AND session_date < :end");
    foreach ($sessionBuckets as $bucket) {
        $sessionStmt->execute([
            ':start' => $bucket['start']->format('Y-m-d H:i:s'),
            ':end' => $bucket['end']->format('Y-m-d H:i:s'),
        ]);
        $value = (int) ($sessionStmt->fetchColumn() ?: 0);
        $charts['sessions']['points'][] = [
            'label' => $bucket['label'],
            'value' => $value,
        ];
        $charts['sessions']['total'] += $value;
    }

    $paymentDateFilter = match ($paymentsPeriodKey) {
        '7d' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '30d' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        '90d' => "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        default => "created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)",
    };
    $paymentStatusMap = [
        'pending' => 'Pending',
        'gateway_submitted' => 'Submitted',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ];
    $paymentStatusStmt = $pdo->query("SELECT status, COUNT(*) AS total FROM payments WHERE {$paymentDateFilter} GROUP BY status");
    $paymentStatusCounts = array_fill_keys(array_keys($paymentStatusMap), 0);
    foreach ($paymentStatusStmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        if (isset($paymentStatusCounts[$status])) {
            $paymentStatusCounts[$status] = (int) ($row['total'] ?? 0);
        }
    }
    foreach ($paymentStatusMap as $status => $label) {
        $charts['payments']['rows'][] = [
            'label' => $label,
            'status' => $status,
            'value' => $paymentStatusCounts[$status],
        ];
        $charts['payments']['total'] += $paymentStatusCounts[$status];
    }

    $completedSessions = (int) ($pdo->query("SELECT COUNT(*) FROM sessions WHERE status='completed'")->fetchColumn() ?: 0);
    $kpis['completion_rate'] = $stats['sessions'] > 0 ? round(($completedSessions / $stats['sessions']) * 100, 1) : 0.0;
    $kpis['payment_capture_rate'] = $stats['payments'] > 0 ? round(($stats['paid_payments'] / $stats['payments']) * 100, 1) : 0.0;
    $sessionsFilterForOps = match ($sessionsPeriodKey) {
        '7d' => "session_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '30d' => "session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        '90d' => "session_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        default => "session_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)",
    };
    $kpis['active_students'] = (int) ($pdo->query("SELECT COUNT(DISTINCT student_id) FROM sessions WHERE student_id IS NOT NULL AND {$sessionsFilterForOps}")->fetchColumn() ?: 0);
    $kpis['active_tutors'] = (int) ($pdo->query("SELECT COUNT(DISTINCT tutor_id) FROM sessions WHERE tutor_id IS NOT NULL AND {$sessionsFilterForOps}")->fetchColumn() ?: 0);
    $kpis['avg_session_value'] = (float) ($pdo->query("SELECT COALESCE(AVG(payment_amount), 0) FROM sessions WHERE payment_amount > 0 AND {$sessionsFilterForOps}")->fetchColumn() ?: 0);
    $kpis['upcoming_7d'] = (int) ($pdo->query("SELECT COUNT(*) FROM sessions WHERE session_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn() ?: 0);
    $kpis['failed_payments'] = (int) ($pdo->query("SELECT COUNT(*) FROM payments WHERE status='failed'")->fetchColumn() ?: 0);

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
    <link rel="stylesheet" href="../assets/css/admin_portal.css">
</head>
<body class="admin-portal">
    <button class="hamburger" onclick="toggleSidebar()">Menu</button>

    <div class="sidebar" id="sidebar">
        <h2>SOTMS Admin</h2>
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
            <p style="font-size: 1.05rem; color: #64748b;">Track trends, review queues, and platform activity from one place.</p>
        </div>

        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-head">
                    <div>
                        <p class="kpi-eyebrow">Revenue Trend</p>
                        <p class="chart-total"><?php echo formatChartValue((float) $charts['revenue']['total'], 'money'); ?></p>
                    </div>
                    <form method="GET" class="chart-filter">
                        <input type="hidden" name="sessions_period" value="<?php echo htmlspecialchars($sessionsPeriodKey); ?>">
                        <input type="hidden" name="payments_period" value="<?php echo htmlspecialchars($paymentsPeriodKey); ?>">
                        <select name="revenue_period" onchange="this.form.submit()">
                            <?php foreach ($periodOptions as $key => $option): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $revenuePeriodKey === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="bar-chart">
                    <?php $maxRevenue = max(array_column($charts['revenue']['points'], 'value')) ?: 1; ?>
                    <?php foreach ($charts['revenue']['points'] as $point): ?>
                        <div class="bar-item">
                            <div class="bar-track">
                                <span class="bar-fill revenue-fill" style="height: <?php echo max(12, ($point['value'] / $maxRevenue) * 100); ?>%;"></span>
                            </div>
                            <div class="bar-label"><?php echo htmlspecialchars($point['label']); ?></div>
                            <div class="bar-value"><?php echo formatChartValue((float) $point['value'], 'money'); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-head">
                    <div>
                        <p class="kpi-eyebrow">Sessions Trend</p>
                        <p class="chart-total"><?php echo formatChartValue((float) $charts['sessions']['total']); ?></p>
                    </div>
                    <form method="GET" class="chart-filter">
                        <input type="hidden" name="revenue_period" value="<?php echo htmlspecialchars($revenuePeriodKey); ?>">
                        <input type="hidden" name="payments_period" value="<?php echo htmlspecialchars($paymentsPeriodKey); ?>">
                        <select name="sessions_period" onchange="this.form.submit()">
                            <?php foreach ($periodOptions as $key => $option): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $sessionsPeriodKey === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="bar-chart">
                    <?php $maxSessions = max(array_column($charts['sessions']['points'], 'value')) ?: 1; ?>
                    <?php foreach ($charts['sessions']['points'] as $point): ?>
                        <div class="bar-item">
                            <div class="bar-track">
                                <span class="bar-fill session-fill" style="height: <?php echo max(12, ($point['value'] / $maxSessions) * 100); ?>%;"></span>
                            </div>
                            <div class="bar-label"><?php echo htmlspecialchars($point['label']); ?></div>
                            <div class="bar-value"><?php echo formatChartValue((float) $point['value']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-head">
                    <div>
                        <p class="kpi-eyebrow">Payment Status</p>
                        <p class="chart-total"><?php echo formatChartValue((float) $charts['payments']['total']); ?></p>
                    </div>
                    <form method="GET" class="chart-filter">
                        <input type="hidden" name="revenue_period" value="<?php echo htmlspecialchars($revenuePeriodKey); ?>">
                        <input type="hidden" name="sessions_period" value="<?php echo htmlspecialchars($sessionsPeriodKey); ?>">
                        <select name="payments_period" onchange="this.form.submit()">
                            <?php foreach ($periodOptions as $key => $option): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $paymentsPeriodKey === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="status-chart">
                    <?php $maxPayments = max(array_column($charts['payments']['rows'], 'value')) ?: 1; ?>
                    <?php foreach ($charts['payments']['rows'] as $row): ?>
                        <div class="status-row">
                            <div class="status-row-top">
                                <span><?php echo htmlspecialchars($row['label']); ?></span>
                                <strong><?php echo number_format((float) $row['value']); ?></strong>
                            </div>
                            <div class="status-bar-track">
                                <span class="status-bar-fill status-<?php echo htmlspecialchars($row['status']); ?>" style="width: <?php echo $row['value'] > 0 ? max(10, ($row['value'] / $maxPayments) * 100) : 0; ?>%;"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="split-panels">
            <div class="panel">
                <h2>Operational Snapshot</h2>
                <div class="metric-list">
                    <div class="metric-row"><span class="metric-name">Active Students</span><span class="metric-strong"><?php echo $kpis['active_students']; ?> <small><?php echo htmlspecialchars($charts['sessions']['label']); ?></small></span></div>
                    <div class="metric-row"><span class="metric-name">Active Tutors</span><span class="metric-strong"><?php echo $kpis['active_tutors']; ?> <small><?php echo htmlspecialchars($charts['sessions']['label']); ?></small></span></div>
                    <div class="metric-row"><span class="metric-name">Upcoming Sessions (7d)</span><span class="metric-strong"><?php echo $kpis['upcoming_7d']; ?></span></div>
                    <div class="metric-row"><span class="metric-name">Average Session Value</span><span class="metric-strong">KSh <?php echo number_format($kpis['avg_session_value'], 0); ?> <small><?php echo htmlspecialchars($charts['sessions']['label']); ?></small></span></div>
                    <div class="metric-row"><span class="metric-name">Learning Materials</span><span class="metric-strong"><?php echo $stats['materials']; ?></span></div>
                </div>
            </div>
            <div class="panel">
                <h2>Action Queue</h2>
                <div class="metric-list">
                    <div class="metric-row"><span class="metric-name">Pending Sessions</span><span class="metric-strong"><?php echo $stats['pending_sessions']; ?></span></div>
                    <div class="metric-row"><span class="metric-name">Payments To Review</span><span class="metric-strong"><?php echo $stats['payment_reviews']; ?></span></div>
                    <div class="metric-row"><span class="metric-name">Tutor Reviews</span><span class="metric-strong"><?php echo $stats['pending_tutor_verifications']; ?></span></div>
                    <div class="metric-row"><span class="metric-name">Failed Payments</span><span class="metric-strong"><?php echo $kpis['failed_payments']; ?></span></div>
                    <div class="metric-row"><span class="metric-name">Total Users</span><span class="metric-strong"><?php echo $stats['users']; ?></span></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <a href="manage_users.php" class="quick-btn">Manage Users</a>
                <a href="manage_sessions.php" class="quick-btn">Review Sessions</a>
                <a href="tutor_verifications.php" class="quick-btn">Tutor Verifications</a>
                <a href="payments_review.php" class="quick-btn">Payments Review</a>
                <a href="reports.php" class="quick-btn">Reports</a>
                <a href="../system_details.php" class="quick-btn">System Details</a>
            </div>
        </div>

        <div class="panel">
            <h2>Recent Activity</h2>
            <?php if (empty($recentActivity)): ?>
                <p style="color:#64748b;">No recent session activity yet.</p>
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
