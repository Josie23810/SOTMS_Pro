<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['tutor']);

ensurePlatformStructures($pdo);

$tutorId = getTutorId($pdo, $_SESSION['user_id']);
$profile = fetchTutorProfile($pdo, $_SESSION['user_id']) ?: [];

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
        ORDER BY
            CASE WHEN s.session_date >= NOW() THEN 0 ELSE 1 END,
            CASE WHEN s.session_date >= NOW() THEN s.session_date END ASC,
            CASE WHEN s.session_date < NOW() THEN s.session_date END DESC
        LIMIT 5
    ");
    $stmt->execute([$tutorId]);
    $recentSessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Tutor dashboard error: ' . $e->getMessage());
}

$tutorDisplayName = $profile['full_name'] ?? ($_SESSION['name'] ?? 'Tutor');
$tutorInitial = strtoupper(substr($tutorDisplayName, 0, 1));
$verificationStatus = ucfirst(str_replace('_', ' ', (string) ($profile['verification_status'] ?? 'submitted')));
$locationSummary = trim((string) ($profile['location'] ?? ''));
$locationSummary = $locationSummary !== '' ? $locationSummary : 'Add location';
$availabilitySummary = trim((string) ($profile['availability_summary'] ?? ''));
$availabilitySummary = $availabilitySummary !== '' ? $availabilitySummary : 'Add availability';
$subjects = array_values(array_filter(array_map('trim', explode(',', (string) ($profile['subjects_taught_display'] ?? '')))));
$subjectSummary = !empty($subjects) ? implode(', ', array_slice($subjects, 0, 3)) : 'Add subjects';
$subjectCount = count($subjects);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Dashboard - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard_improvements.css">
</head>
<body class="portal-page tutor-portal">
    <div class="portal-shell">
        <aside class="portal-sidebar">
            <div class="brand-mark">
                <div class="brand-badge"><i class="fas fa-chalkboard-teacher"></i></div>
                <div>
                    <div>SOTMS Pro</div>
                    <div class="portal-user-role">Tutor Portal</div>
                </div>
            </div>

            <div class="portal-user">
                <div class="portal-avatar"><?php echo htmlspecialchars($tutorInitial); ?></div>
                <div class="portal-user-name"><?php echo htmlspecialchars($tutorDisplayName); ?></div>
                <div class="portal-user-role">Tutor account</div>
            </div>

            <nav class="portal-nav">
                <a href="dashboard.php" class="active"><i class="fas fa-house"></i> Dashboard</a>
                <a href="schedule.php" class="primary-link"><i class="fas fa-calendar-plus"></i> Schedule</a>
                <a href="my_sessions.php"><i class="fas fa-list-check"></i> Sessions</a>
                <a href="upload_materials.php"><i class="fas fa-upload"></i> Materials</a>
                <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                <a href="profile.php"><i class="fas fa-user-gear"></i> Profile</a>
                <a href="settings.php"><i class="fas fa-sliders"></i> Settings</a>
                <a href="../config/auth/logout.php" class="logout-link"><i class="fas fa-right-from-bracket"></i> Logout</a>
            </nav>

            <div class="portal-sidebar-footer">
                <div><i class="fas fa-shield-halved"></i> Secure teaching workspace</div>
                <div class="footer-links">
                    <a href="schedule.php">Schedule</a>
                    <a href="upload_materials.php">Materials</a>
                    <a href="profile.php">Profile</a>
                </div>
                <div>&copy; <?php echo date('Y'); ?> SOTMS Pro</div>
            </div>
        </aside>

        <main class="portal-main">
            <section class="hero-banner">
                <div>
                    <h1 class="hero-title">Your tutor workspace.</h1>
                    <p class="hero-copy">Sessions, materials, and profile details in one place.</p>
                </div>
                <div class="hero-meta">
                    <div class="hero-panel">
                        <div class="hero-panel-label">Verification</div>
                        <div class="hero-panel-value"><?php echo htmlspecialchars($verificationStatus); ?></div>
                        <div class="hero-panel-subtext"><?php echo htmlspecialchars($locationSummary); ?></div>
                    </div>
                    <div class="hero-panel">
                        <div class="hero-panel-label">Subjects</div>
                        <div class="hero-panel-value"><?php echo $subjectCount; ?></div>
                        <div class="hero-panel-subtext"><?php echo htmlspecialchars($subjectSummary); ?></div>
                    </div>
                </div>
            </section>

            <section class="stats-grid">
                <article class="dashboard-card metric-card">
                    <div class="metric-top">
                        <div class="metric-label">Upcoming Sessions</div>
                        <div class="metric-icon"><i class="fas fa-calendar-check"></i></div>
                    </div>
                    <div class="metric-value"><?php echo $stats['upcoming']; ?></div>
                    <div class="metric-note">Next</div>
                </article>
                <article class="dashboard-card metric-card">
                    <div class="metric-top">
                        <div class="metric-label">Pending Requests</div>
                        <div class="metric-icon"><i class="fas fa-hourglass-half"></i></div>
                    </div>
                    <div class="metric-value"><?php echo $stats['pending']; ?></div>
                    <div class="metric-note">Waiting</div>
                </article>
                <article class="dashboard-card metric-card">
                    <div class="metric-top">
                        <div class="metric-label">Completed Sessions</div>
                        <div class="metric-icon"><i class="fas fa-circle-check"></i></div>
                    </div>
                    <div class="metric-value"><?php echo $stats['completed']; ?></div>
                    <div class="metric-note">Done</div>
                </article>
                <article class="dashboard-card metric-card">
                    <div class="metric-top">
                        <div class="metric-label">Paid Sessions</div>
                        <div class="metric-icon"><i class="fas fa-wallet"></i></div>
                    </div>
                    <div class="metric-value"><?php echo $stats['paid_sessions']; ?></div>
                    <div class="metric-note">Paid</div>
                </article>
                <article class="dashboard-card metric-card">
                    <div class="metric-top">
                        <div class="metric-label">Materials Uploaded</div>
                        <div class="metric-icon"><i class="fas fa-folder-open"></i></div>
                    </div>
                    <div class="metric-value"><?php echo $stats['materials']; ?></div>
                    <div class="metric-note">Files</div>
                </article>
            </section>

            <section class="dashboard-card section-card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Quick Actions</h2>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="schedule.php" class="quick-action">
                        <div class="quick-action-icon"><i class="fas fa-calendar-plus"></i></div>
                        <div class="quick-action-title">Manage availability</div>
                    </a>
                    <a href="my_sessions.php" class="quick-action">
                        <div class="quick-action-icon"><i class="fas fa-list-check"></i></div>
                        <div class="quick-action-title">Review sessions</div>
                    </a>
                    <a href="upload_materials.php" class="quick-action">
                        <div class="quick-action-icon"><i class="fas fa-upload"></i></div>
                        <div class="quick-action-title">Upload materials</div>
                    </a>
                    <a href="profile.php" class="quick-action">
                        <div class="quick-action-icon"><i class="fas fa-id-card"></i></div>
                        <div class="quick-action-title">Update profile</div>
                    </a>
                </div>
            </section>

            <section class="dashboard-grid">
                <article class="dashboard-card section-card">
                    <div class="section-head">
                        <div>
                            <h2 class="section-title">Next Sessions</h2>
                        </div>
                        <a href="my_sessions.php" class="section-link">View all sessions</a>
                    </div>

                    <?php if (empty($recentSessions)): ?>
                        <div class="empty-state">
                            <p>No sessions are assigned to you yet.</p>
                            <p class="space-top-sm">
                                <a href="schedule.php" class="btn"><i class="fas fa-calendar-plus"></i> Open Schedule</a>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="content-list">
                            <?php foreach ($recentSessions as $session): ?>
                                <?php $paymentLabel = ucfirst(str_replace('_', ' ', (string) ($session['payment_status'] ?? 'unpaid'))); ?>
                                <div class="item-card">
                                    <div class="item-head">
                                        <div>
                                            <h3 class="item-title"><?php echo htmlspecialchars($session['student_name'] ?: 'Student'); ?></h3>
                                            <div class="item-meta">
                                                Subject: <?php echo htmlspecialchars($session['subject']); ?><br>
                                                Date: <?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?><br>
                                                Payment: <?php echo htmlspecialchars($paymentLabel); ?>
                                            </div>
                                        </div>
                                        <span class="status-pill <?php echo htmlspecialchars($session['status']); ?>"><?php echo htmlspecialchars($session['status']); ?></span>
                                    </div>
                                    <div class="item-actions">
                                        <a href="schedule.php" class="btn"><i class="fas fa-calendar-days"></i> Open Schedule</a>
                                        <a href="messages.php" class="btn secondary"><i class="fas fa-envelope"></i> Messages</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <div class="stack">
                    <article class="dashboard-card section-card">
                        <div class="section-head">
                            <div>
                                <h2 class="section-title">Tutor Snapshot</h2>
                            </div>
                            <a href="profile.php" class="section-link">Edit profile</a>
                        </div>

                        <div class="mini-grid">
                            <div class="mini-card">
                                <div class="mini-label">Verification</div>
                                <div class="mini-value"><?php echo htmlspecialchars($verificationStatus); ?></div>
                            </div>
                            <div class="mini-card">
                                <div class="mini-label">Location</div>
                                <div class="mini-value"><?php echo htmlspecialchars($locationSummary); ?></div>
                            </div>
                            <div class="mini-card">
                                <div class="mini-label">Curriculum</div>
                                <div class="mini-value"><?php echo htmlspecialchars($profile['curriculum_specialties_display'] ?? 'Not set'); ?></div>
                            </div>
                            <div class="mini-card">
                                <div class="mini-label">Levels</div>
                                <div class="mini-value"><?php echo htmlspecialchars($profile['study_levels_supported_display'] ?? 'Not set'); ?></div>
                            </div>
                        </div>

                        <div class="item-tags space-top-md">
                            <?php if (empty($subjects)): ?>
                                <span class="tag"><i class="fas fa-circle-info"></i> Add subjects</span>
                            <?php else: ?>
                                <?php foreach (array_slice($subjects, 0, 5) as $subject): ?>
                                    <span class="tag"><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($subject); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="mini-card space-top-md">
                            <div class="mini-label">Availability</div>
                            <div class="mini-value"><?php echo htmlspecialchars($availabilitySummary); ?></div>
                        </div>
                    </article>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
