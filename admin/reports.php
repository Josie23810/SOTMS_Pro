<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['admin']);

ensurePlatformStructures($pdo);

function reportsPeriodOptions(): array
{
    return [
        '7d' => 'Last 7 Days',
        '30d' => 'Last 30 Days',
        '90d' => 'Last 90 Days',
        '365d' => 'Last 12 Months',
        'all' => 'All Time',
    ];
}

function selectedReportsPeriod(string $queryKey, string $default = '30d'): string
{
    $options = reportsPeriodOptions();
    return isset($_GET[$queryKey], $options[$_GET[$queryKey]]) ? $_GET[$queryKey] : $default;
}

function buildReportBuckets(string $periodKey): array
{
    $now = new DateTimeImmutable('now');
    $buckets = [];

    if ($periodKey === '365d') {
        $currentMonth = $now->setTime(0, 0)->modify('first day of this month');
        for ($i = 11; $i >= 0; $i--) {
            $start = $currentMonth->modify("-{$i} months");
            $buckets[] = [
                'label' => $start->format('M'),
                'start' => $start,
                'end' => $start->modify('+1 month'),
            ];
        }
        return $buckets;
    }

    if ($periodKey === 'all') {
        $currentMonth = $now->setTime(0, 0)->modify('first day of this month');
        for ($i = 5; $i >= 0; $i--) {
            $start = $currentMonth->modify("-{$i} months");
            $buckets[] = [
                'label' => $start->format('M'),
                'start' => $start,
                'end' => $start->modify('+1 month'),
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

function reportDateCondition(string $column, string $periodKey): string
{
    return match ($periodKey) {
        '7d' => "{$column} >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '30d' => "{$column} >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        '90d' => "{$column} >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        '365d' => "{$column} >= DATE_SUB(NOW(), INTERVAL 365 DAY)",
        default => '1=1',
    };
}

function reportValueLabel(float $value, string $type = 'count'): string
{
    return $type === 'money' ? 'KSh ' . number_format($value, 0) : number_format($value, 0);
}

function reportQuery(array $params = []): string
{
    $query = array_merge($_GET, $params);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    return '?' . http_build_query($query);
}

$periodOptions = reportsPeriodOptions();
$chartRevenuePeriod = selectedReportsPeriod('revenue_period');
$chartSessionsPeriod = selectedReportsPeriod('sessions_period');
$chartPaymentsPeriod = selectedReportsPeriod('payments_period');
$reportWindow = selectedReportsPeriod('window', '30d');

$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$subjectFilter = isset($_GET['subject']) ? trim((string) $_GET['subject']) : '';
$curriculumFilter = isset($_GET['curriculum']) ? trim((string) $_GET['curriculum']) : '';
$tutorFilter = isset($_GET['tutor']) ? (int) $_GET['tutor'] : 0;

$sessionWhere = [reportDateCondition('s.session_date', $reportWindow)];
$sessionParams = [];

if ($statusFilter !== '') {
    $sessionWhere[] = 's.status = :status';
    $sessionParams[':status'] = $statusFilter;
}
if ($subjectFilter !== '') {
    $sessionWhere[] = 's.subject = :subject';
    $sessionParams[':subject'] = $subjectFilter;
}
if ($curriculumFilter !== '') {
    $sessionWhere[] = 's.curriculum = :curriculum';
    $sessionParams[':curriculum'] = $curriculumFilter;
}
if ($tutorFilter > 0) {
    $sessionWhere[] = 'tt.user_id = :tutor_user_id';
    $sessionParams[':tutor_user_id'] = $tutorFilter;
}

$sessionWhereSql = implode(' AND ', $sessionWhere);

$paymentWhere = [reportDateCondition('p.created_at', $reportWindow)];
$paymentParams = [];
if ($subjectFilter !== '') {
    $paymentWhere[] = 's.subject = :payment_subject';
    $paymentParams[':payment_subject'] = $subjectFilter;
}
if ($curriculumFilter !== '') {
    $paymentWhere[] = 's.curriculum = :payment_curriculum';
    $paymentParams[':payment_curriculum'] = $curriculumFilter;
}
if ($tutorFilter > 0) {
    $paymentWhere[] = 'tt.user_id = :payment_tutor_user_id';
    $paymentParams[':payment_tutor_user_id'] = $tutorFilter;
}

$paymentWhereSql = implode(' AND ', $paymentWhere);

if (isset($_GET['export'])) {
    $export = (string) $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sotms_' . $export . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');

    if ($export === 'users') {
        fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Registered']);
        $usersSql = 'SELECT id, name, email, role, created_at FROM users WHERE ' . reportDateCondition('created_at', $reportWindow) . ' ORDER BY created_at DESC';
        foreach ($pdo->query($usersSql) as $row) {
            fputcsv($output, [$row['id'], $row['name'], $row['email'], $row['role'], $row['created_at']]);
        }
        exit;
    }

    if ($export === 'sessions') {
        fputcsv($output, ['ID', 'Student', 'Tutor', 'Subject', 'Curriculum', 'Study Level', 'Date', 'Status', 'Payment']);
        $stmt = $pdo->prepare("
            SELECT s.id, su.name AS student_name, tu.name AS tutor_name, s.subject, s.curriculum, s.study_level, s.session_date, s.status, s.payment_status
            FROM sessions s
            LEFT JOIN students st ON s.student_id = st.id
            LEFT JOIN users su ON st.user_id = su.id
            LEFT JOIN tutors tt ON s.tutor_id = tt.id
            LEFT JOIN users tu ON tt.user_id = tu.id
            WHERE {$sessionWhereSql}
            ORDER BY s.session_date DESC
        ");
        $stmt->execute($sessionParams);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['id'], $row['student_name'], $row['tutor_name'], $row['subject'], $row['curriculum'], $row['study_level'], $row['session_date'], $row['status'], $row['payment_status']]);
        }
        exit;
    }

    if ($export === 'payments') {
        fputcsv($output, ['Reference', 'Provider', 'Student', 'Tutor', 'Amount', 'Status', 'Created']);
        $stmt = $pdo->prepare("
            SELECT p.reference, p.provider, su.name AS student_name, tu.name AS tutor_name, p.amount, p.status, p.created_at
            FROM payments p
            LEFT JOIN sessions s ON p.session_id = s.id
            LEFT JOIN students st ON s.student_id = st.id
            LEFT JOIN users su ON st.user_id = su.id
            LEFT JOIN tutors tt ON s.tutor_id = tt.id
            LEFT JOIN users tu ON tt.user_id = tu.id
            WHERE {$paymentWhereSql}
            ORDER BY p.created_at DESC
        ");
        $stmt->execute($paymentParams);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['reference'], $row['provider'], $row['student_name'], $row['tutor_name'], $row['amount'], $row['status'], $row['created_at']]);
        }
        exit;
    }

    if ($export === 'materials') {
        fputcsv($output, ['Tutor', 'Title', 'Subject', 'Curriculum', 'Study Level', 'Uploaded']);
        $materialsWhere = [reportDateCondition('tm.uploaded_at', $reportWindow)];
        $materialsParams = [];
        if ($subjectFilter !== '') {
            $materialsWhere[] = 'tm.subject = :materials_subject';
            $materialsParams[':materials_subject'] = $subjectFilter;
        }
        if ($curriculumFilter !== '') {
            $materialsWhere[] = 'tm.curriculum = :materials_curriculum';
            $materialsParams[':materials_curriculum'] = $curriculumFilter;
        }
        if ($tutorFilter > 0) {
            $materialsWhere[] = 'tm.tutor_id = :materials_tutor_user_id';
            $materialsParams[':materials_tutor_user_id'] = $tutorFilter;
        }

        $stmt = $pdo->prepare("
            SELECT u.name AS tutor_name, tm.title, tm.subject, tm.curriculum, tm.study_level, tm.uploaded_at
            FROM tutor_materials tm
            JOIN users u ON tm.tutor_id = u.id
            WHERE " . implode(' AND ', $materialsWhere) . "
            ORDER BY tm.uploaded_at DESC
        ");
        $stmt->execute($materialsParams);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['tutor_name'], $row['title'], $row['subject'], $row['curriculum'], $row['study_level'], $row['uploaded_at']]);
        }
        exit;
    }
}

$subjects = $pdo->query("SELECT DISTINCT subject FROM sessions WHERE subject IS NOT NULL AND subject <> '' ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$curricula = $pdo->query("SELECT DISTINCT curriculum FROM sessions WHERE curriculum IS NOT NULL AND curriculum <> '' ORDER BY curriculum ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$tutors = $pdo->query("
    SELECT DISTINCT u.id, u.name
    FROM tutors t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.name ASC
")->fetchAll() ?: [];

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_sessions,
        SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) AS completed_sessions,
        SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) AS pending_sessions,
        COUNT(DISTINCT s.student_id) AS active_students,
        COUNT(DISTINCT s.tutor_id) AS active_tutors,
        COALESCE(AVG(s.duration), 0) AS avg_duration,
        COALESCE(AVG(CASE WHEN s.payment_amount > 0 THEN s.payment_amount END), 0) AS avg_session_value
    FROM sessions s
    LEFT JOIN tutors tt ON s.tutor_id = tt.id
    WHERE {$sessionWhereSql}
");
$statsStmt->execute($sessionParams);
$sessionStats = $statsStmt->fetch() ?: [];

$paymentStatsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_payments,
        SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) AS paid_payments,
        SUM(CASE WHEN p.status IN ('pending', 'gateway_submitted', 'failed') THEN 1 ELSE 0 END) AS payments_awaiting_review,
        SUM(CASE WHEN p.status = 'failed' THEN 1 ELSE 0 END) AS failed_payments,
        COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) AS revenue
    FROM payments p
    LEFT JOIN sessions s ON p.session_id = s.id
    LEFT JOIN tutors tt ON s.tutor_id = tt.id
    WHERE {$paymentWhereSql}
");
$paymentStatsStmt->execute($paymentParams);
$paymentStats = $paymentStatsStmt->fetch() ?: [];

$pendingTutorVerifications = (int) ($pdo->query("SELECT COUNT(*) FROM tutor_profiles WHERE verification_status IN ('submitted', 'under_review')")->fetchColumn() ?: 0);
$materialsCount = (int) ($pdo->query("SELECT COUNT(*) FROM tutor_materials")->fetchColumn() ?: 0);

$completionRate = (int) ($sessionStats['total_sessions'] ?? 0) > 0
    ? round(((int) ($sessionStats['completed_sessions'] ?? 0) / (int) $sessionStats['total_sessions']) * 100, 1)
    : 0.0;
$paymentCaptureRate = (int) ($paymentStats['total_payments'] ?? 0) > 0
    ? round(((int) ($paymentStats['paid_payments'] ?? 0) / (int) $paymentStats['total_payments']) * 100, 1)
    : 0.0;

$topSubjectsStmt = $pdo->prepare("
    SELECT s.subject, COUNT(*) AS total
    FROM sessions s
    LEFT JOIN tutors tt ON s.tutor_id = tt.id
    WHERE {$sessionWhereSql} AND s.subject IS NOT NULL AND s.subject <> ''
    GROUP BY s.subject
    ORDER BY total DESC
    LIMIT 5
");
$topSubjectsStmt->execute($sessionParams);
$topSubjects = $topSubjectsStmt->fetchAll() ?: [];

$recentSessionsStmt = $pdo->prepare("
    SELECT s.subject, s.curriculum, s.status, s.session_date, su.name AS student_name, tu.name AS tutor_name
    FROM sessions s
    LEFT JOIN students st ON s.student_id = st.id
    LEFT JOIN users su ON st.user_id = su.id
    LEFT JOIN tutors tt ON s.tutor_id = tt.id
    LEFT JOIN users tu ON tt.user_id = tu.id
    WHERE {$sessionWhereSql}
    ORDER BY s.session_date DESC
    LIMIT 12
");
$recentSessionsStmt->execute($sessionParams);
$recentSessions = $recentSessionsStmt->fetchAll() ?: [];

$revenueChart = [
    'label' => $periodOptions[$chartRevenuePeriod],
    'points' => [],
    'total' => 0.0,
];
$revenueStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND created_at >= :start AND created_at < :end");
foreach (buildReportBuckets($chartRevenuePeriod) as $bucket) {
    $revenueStmt->execute([
        ':start' => $bucket['start']->format('Y-m-d H:i:s'),
        ':end' => $bucket['end']->format('Y-m-d H:i:s'),
    ]);
    $value = (float) ($revenueStmt->fetchColumn() ?: 0);
    $revenueChart['points'][] = ['label' => $bucket['label'], 'value' => $value];
    $revenueChart['total'] += $value;
}

$sessionsChart = [
    'label' => $periodOptions[$chartSessionsPeriod],
    'points' => [],
    'total' => 0,
];
$sessionsTrendStmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE session_date >= :start AND session_date < :end");
foreach (buildReportBuckets($chartSessionsPeriod) as $bucket) {
    $sessionsTrendStmt->execute([
        ':start' => $bucket['start']->format('Y-m-d H:i:s'),
        ':end' => $bucket['end']->format('Y-m-d H:i:s'),
    ]);
    $value = (int) ($sessionsTrendStmt->fetchColumn() ?: 0);
    $sessionsChart['points'][] = ['label' => $bucket['label'], 'value' => $value];
    $sessionsChart['total'] += $value;
}

$paymentChart = [
    'label' => $periodOptions[$chartPaymentsPeriod],
    'rows' => [],
    'total' => 0,
];
$paymentChartSql = "
    SELECT status, COUNT(*) AS total
    FROM payments
    WHERE " . reportDateCondition('created_at', $chartPaymentsPeriod) . "
    GROUP BY status
";
$paymentChartCounts = array_fill_keys(['pending', 'gateway_submitted', 'paid', 'failed', 'refunded'], 0);
foreach ($pdo->query($paymentChartSql)->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    if (isset($paymentChartCounts[$status])) {
        $paymentChartCounts[$status] = (int) ($row['total'] ?? 0);
    }
}
foreach ([
    'pending' => 'Pending',
    'gateway_submitted' => 'Submitted',
    'paid' => 'Paid',
    'failed' => 'Failed',
    'refunded' => 'Refunded',
] as $status => $label) {
    $paymentChart['rows'][] = [
        'label' => $label,
        'status' => $status,
        'value' => $paymentChartCounts[$status],
    ];
    $paymentChart['total'] += $paymentChartCounts[$status];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin_portal.css">
</head>
<body class="admin-portal">
    <div class="container">
        <div class="header">
            <h1>Reports</h1>
            <p>Filter the data, inspect trends, and export exactly what you need.</p>
        </div>

        <div class="nav-top">
            <a href="dashboard.php" class="export-btn">Dashboard</a>
            <a href="<?php echo htmlspecialchars(reportQuery(['export' => 'users'])); ?>" class="export-btn">Users CSV</a>
            <a href="<?php echo htmlspecialchars(reportQuery(['export' => 'sessions'])); ?>" class="export-btn">Sessions CSV</a>
            <a href="<?php echo htmlspecialchars(reportQuery(['export' => 'payments'])); ?>" class="export-btn">Payments CSV</a>
            <a href="<?php echo htmlspecialchars(reportQuery(['export' => 'materials'])); ?>" class="export-btn">Materials CSV</a>
        </div>

        <div class="content">
            <div class="panel filter-panel">
                <form method="GET" class="reports-filter-form">
                    <input type="hidden" name="revenue_period" value="<?php echo htmlspecialchars($chartRevenuePeriod); ?>">
                    <input type="hidden" name="sessions_period" value="<?php echo htmlspecialchars($chartSessionsPeriod); ?>">
                    <input type="hidden" name="payments_period" value="<?php echo htmlspecialchars($chartPaymentsPeriod); ?>">
                    <div class="filters-grid">
                        <div>
                            <label for="window"><strong>Report Window</strong></label>
                            <select id="window" name="window">
                                <?php foreach ($periodOptions as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $reportWindow === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="status"><strong>Status</strong></label>
                            <select id="status" name="status">
                                <option value="">All statuses</option>
                                <?php foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="subject"><strong>Subject</strong></label>
                            <select id="subject" name="subject">
                                <option value="">All subjects</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo htmlspecialchars($subject); ?>" <?php echo $subjectFilter === $subject ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="curriculum"><strong>Curriculum</strong></label>
                            <select id="curriculum" name="curriculum">
                                <option value="">All curricula</option>
                                <?php foreach ($curricula as $curriculum): ?>
                                    <option value="<?php echo htmlspecialchars($curriculum); ?>" <?php echo $curriculumFilter === $curriculum ? 'selected' : ''; ?>><?php echo htmlspecialchars($curriculum); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="tutor"><strong>Tutor</strong></label>
                            <select id="tutor" name="tutor">
                                <option value="">All tutors</option>
                                <?php foreach ($tutors as $tutor): ?>
                                    <option value="<?php echo (int) $tutor['id']; ?>" <?php echo $tutorFilter === (int) $tutor['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tutor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="stack-actions" style="margin-top: 18px;">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="reports.php" class="btn-secondary action-btn">Reset</a>
                    </div>
                </form>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <p class="kpi-eyebrow">Revenue Trend</p>
                            <p class="chart-total"><?php echo reportValueLabel((float) $revenueChart['total'], 'money'); ?></p>
                        </div>
                        <form method="GET" class="chart-filter">
                            <input type="hidden" name="window" value="<?php echo htmlspecialchars($reportWindow); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subjectFilter); ?>">
                            <input type="hidden" name="curriculum" value="<?php echo htmlspecialchars($curriculumFilter); ?>">
                            <input type="hidden" name="tutor" value="<?php echo $tutorFilter > 0 ? (int) $tutorFilter : ''; ?>">
                            <input type="hidden" name="sessions_period" value="<?php echo htmlspecialchars($chartSessionsPeriod); ?>">
                            <input type="hidden" name="payments_period" value="<?php echo htmlspecialchars($chartPaymentsPeriod); ?>">
                            <select name="revenue_period" onchange="this.form.submit()">
                                <?php foreach ($periodOptions as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $chartRevenuePeriod === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="bar-chart">
                        <?php $maxRevenue = max(array_column($revenueChart['points'], 'value')) ?: 1; ?>
                        <?php foreach ($revenueChart['points'] as $point): ?>
                            <div class="bar-item">
                                <div class="bar-track">
                                    <span class="bar-fill revenue-fill" style="height: <?php echo max(12, ($point['value'] / $maxRevenue) * 100); ?>%;"></span>
                                </div>
                                <div class="bar-label"><?php echo htmlspecialchars($point['label']); ?></div>
                                <div class="bar-value"><?php echo reportValueLabel((float) $point['value'], 'money'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <p class="kpi-eyebrow">Sessions Trend</p>
                            <p class="chart-total"><?php echo reportValueLabel((float) $sessionsChart['total']); ?></p>
                        </div>
                        <form method="GET" class="chart-filter">
                            <input type="hidden" name="window" value="<?php echo htmlspecialchars($reportWindow); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subjectFilter); ?>">
                            <input type="hidden" name="curriculum" value="<?php echo htmlspecialchars($curriculumFilter); ?>">
                            <input type="hidden" name="tutor" value="<?php echo $tutorFilter > 0 ? (int) $tutorFilter : ''; ?>">
                            <input type="hidden" name="revenue_period" value="<?php echo htmlspecialchars($chartRevenuePeriod); ?>">
                            <input type="hidden" name="payments_period" value="<?php echo htmlspecialchars($chartPaymentsPeriod); ?>">
                            <select name="sessions_period" onchange="this.form.submit()">
                                <?php foreach ($periodOptions as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $chartSessionsPeriod === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="bar-chart">
                        <?php $maxSessions = max(array_column($sessionsChart['points'], 'value')) ?: 1; ?>
                        <?php foreach ($sessionsChart['points'] as $point): ?>
                            <div class="bar-item">
                                <div class="bar-track">
                                    <span class="bar-fill session-fill" style="height: <?php echo max(12, ($point['value'] / $maxSessions) * 100); ?>%;"></span>
                                </div>
                                <div class="bar-label"><?php echo htmlspecialchars($point['label']); ?></div>
                                <div class="bar-value"><?php echo reportValueLabel((float) $point['value']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <p class="kpi-eyebrow">Payment Status</p>
                            <p class="chart-total"><?php echo reportValueLabel((float) $paymentChart['total']); ?></p>
                        </div>
                        <form method="GET" class="chart-filter">
                            <input type="hidden" name="window" value="<?php echo htmlspecialchars($reportWindow); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subjectFilter); ?>">
                            <input type="hidden" name="curriculum" value="<?php echo htmlspecialchars($curriculumFilter); ?>">
                            <input type="hidden" name="tutor" value="<?php echo $tutorFilter > 0 ? (int) $tutorFilter : ''; ?>">
                            <input type="hidden" name="revenue_period" value="<?php echo htmlspecialchars($chartRevenuePeriod); ?>">
                            <input type="hidden" name="sessions_period" value="<?php echo htmlspecialchars($chartSessionsPeriod); ?>">
                            <select name="payments_period" onchange="this.form.submit()">
                                <?php foreach ($periodOptions as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $chartPaymentsPeriod === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="status-chart">
                        <?php $maxPayments = max(array_column($paymentChart['rows'], 'value')) ?: 1; ?>
                        <?php foreach ($paymentChart['rows'] as $row): ?>
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

            <div class="report-grid">
                <div class="report-card">
                    <h3>Revenue</h3>
                    <div class="metric"><span>Captured Revenue</span><span class="metric-value">KSh <?php echo number_format((float) ($paymentStats['revenue'] ?? 0), 2); ?></span></div>
                    <div class="metric"><span>Paid Payments</span><span class="metric-value"><?php echo (int) ($paymentStats['paid_payments'] ?? 0); ?></span></div>
                    <div class="metric"><span>Payment Capture</span><span class="metric-value"><?php echo number_format($paymentCaptureRate, 1); ?>%</span></div>
                    <div class="stack-actions" style="margin-top: 14px;">
                        <a href="payments_review.php" class="btn">Open Payments</a>
                    </div>
                </div>

                <div class="report-card">
                    <h3>Sessions</h3>
                    <div class="metric"><span>Total Sessions</span><span class="metric-value"><?php echo (int) ($sessionStats['total_sessions'] ?? 0); ?></span></div>
                    <div class="metric"><span>Completed</span><span class="metric-value"><?php echo (int) ($sessionStats['completed_sessions'] ?? 0); ?></span></div>
                    <div class="metric"><span>Completion Rate</span><span class="metric-value"><?php echo number_format($completionRate, 1); ?>%</span></div>
                    <div class="stack-actions" style="margin-top: 14px;">
                        <a href="manage_sessions.php" class="btn">Open Sessions</a>
                    </div>
                </div>

                <div class="report-card">
                    <h3>Activity</h3>
                    <div class="metric"><span>Active Students</span><span class="metric-value"><?php echo (int) ($sessionStats['active_students'] ?? 0); ?></span></div>
                    <div class="metric"><span>Active Tutors</span><span class="metric-value"><?php echo (int) ($sessionStats['active_tutors'] ?? 0); ?></span></div>
                    <div class="metric"><span>Materials</span><span class="metric-value"><?php echo $materialsCount; ?></span></div>
                    <div class="stack-actions" style="margin-top: 14px;">
                        <a href="manage_users.php" class="btn">Open Users</a>
                    </div>
                </div>

                <div class="report-card">
                    <h3>Attention Needed</h3>
                    <div class="metric"><span>Payments To Review</span><span class="metric-value"><?php echo (int) ($paymentStats['payments_awaiting_review'] ?? 0); ?></span></div>
                    <div class="metric"><span>Failed Payments</span><span class="metric-value"><?php echo (int) ($paymentStats['failed_payments'] ?? 0); ?></span></div>
                    <div class="metric"><span>Tutor Reviews</span><span class="metric-value"><?php echo $pendingTutorVerifications; ?></span></div>
                    <div class="stack-actions" style="margin-top: 14px;">
                        <a href="tutor_verifications.php" class="btn">Open Reviews</a>
                    </div>
                </div>
            </div>

            <div class="split-panels">
                <div class="report-card">
                    <h3>Top Subjects</h3>
                    <?php if (empty($topSubjects)): ?>
                        <p class="summary-line">No subject data for the current filters.</p>
                    <?php else: ?>
                        <div class="metric-list">
                            <?php foreach ($topSubjects as $subjectRow): ?>
                                <div class="metric-row">
                                    <span class="metric-name"><?php echo htmlspecialchars($subjectRow['subject']); ?></span>
                                    <span class="metric-strong"><?php echo (int) $subjectRow['total']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="report-card">
                    <h3>Current Filter Snapshot</h3>
                    <div class="metric-list">
                        <div class="metric-row"><span class="metric-name">Window</span><span class="metric-strong"><?php echo htmlspecialchars($periodOptions[$reportWindow]); ?></span></div>
                        <div class="metric-row"><span class="metric-name">Status</span><span class="metric-strong"><?php echo htmlspecialchars($statusFilter !== '' ? ucfirst($statusFilter) : 'All'); ?></span></div>
                        <div class="metric-row"><span class="metric-name">Subject</span><span class="metric-strong"><?php echo htmlspecialchars($subjectFilter !== '' ? $subjectFilter : 'All'); ?></span></div>
                        <div class="metric-row"><span class="metric-name">Curriculum</span><span class="metric-strong"><?php echo htmlspecialchars($curriculumFilter !== '' ? $curriculumFilter : 'All'); ?></span></div>
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
                            <th>Curriculum</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSessions)): ?>
                            <tr><td colspan="6">No sessions for the selected filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentSessions as $session): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($session['student_name'] ?: 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($session['tutor_name'] ?: 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($session['subject'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($session['curriculum'] ?: 'N/A'); ?></td>
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
