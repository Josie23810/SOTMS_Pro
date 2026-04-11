<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

$studentId = getStudentId($pdo, $_SESSION['user_id']);
$profile = fetchStudentProfile($pdo, $_SESSION['user_id']) ?: [];
list(, $matchedTutors) = fetchTutorMatches($pdo, $_SESSION['user_id'], 3);

$profileFieldsCompleted = 0;
foreach (['curriculum_display', 'study_level_display', 'location', 'subjects_display'] as $field) {
    if (!empty($profile[$field])) {
        $profileFieldsCompleted++;
    }
}
$profileCompletion = (int) round(($profileFieldsCompleted / 4) * 100);

$stats = [
    'upcoming' => 0,
    'completed' => 0,
    'resources' => 0,
    'pending_payment' => 0
];
$recentSessions = [];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status IN ('pending', 'confirmed') AND session_date >= NOW()");
    $stmt->execute([$studentId]);
    $stats['upcoming'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status = 'completed'");
    $stmt->execute([$studentId]);
    $stats['completed'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE student_id = ? AND payment_status IN ('unpaid', 'processing', 'failed', 'refunded')");
    $stmt->execute([$studentId]);
    $stats['pending_payment'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT tm.id)
        FROM tutor_materials tm
        JOIN tutors t ON t.user_id = tm.tutor_id
        JOIN sessions s ON s.tutor_id = t.id
        WHERE s.student_id = ?
          AND s.status IN ('pending', 'confirmed', 'completed')
    ");
    $stmt->execute([$studentId]);
    $stats['resources'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT s.id, s.subject, s.session_date, s.status, s.payment_status, s.amount, u.name AS tutor_name
        FROM sessions s
        LEFT JOIN tutors t ON s.tutor_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE s.student_id = ?
        ORDER BY
            CASE WHEN s.session_date >= NOW() THEN 0 ELSE 1 END,
            CASE WHEN s.session_date >= NOW() THEN s.session_date END ASC,
            CASE WHEN s.session_date < NOW() THEN s.session_date END DESC
        LIMIT 4
    ");
    $stmt->execute([$studentId]);
    $recentSessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Student dashboard error: ' . $e->getMessage());
}

$studentDisplayName = $profile['full_name'] ?? ($_SESSION['name'] ?? 'Student');
$studentInitial = strtoupper(substr($studentDisplayName, 0, 1));
$profileSummary = implode(' / ', array_filter([
    $profile['curriculum_display'] ?? '',
    $profile['study_level_display'] ?? ''
]));
$profileSummary = $profileSummary !== '' ? $profileSummary : 'Add curriculum and level';
$locationSummary = trim((string) ($profile['location'] ?? ''));
$locationSummary = $locationSummary !== '' ? $locationSummary : 'Add location';
$subjects = array_values(array_filter(array_map('trim', explode(',', (string) ($profile['subjects_display'] ?? '')))));
$subjectSummary = !empty($subjects) ? implode(', ', array_slice($subjects, 0, 3)) : 'Add subjects';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student / Parent Dashboard - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard_improvements.css">
</head>
<body class="portal-page student-portal student-dashboard-page">
    <div class="portal-shell dashboard-shell">
        <aside class="portal-sidebar dashboard-sidebar">
            <div class="brand-mark">
                <div class="brand-badge"><i class="fas fa-graduation-cap"></i></div>
                <div>
                    <div>SOTMS Pro</div>
                    <div class="portal-user-role">Student and Parent Portal</div>
                </div>
            </div>

            <div class="portal-user">
                <div class="portal-avatar"><?php echo htmlspecialchars($studentInitial); ?></div>
                <div class="portal-user-name"><?php echo htmlspecialchars($studentDisplayName); ?></div>
                <div class="portal-user-role">Student account</div>
            </div>

            <nav class="portal-nav">
                <div class="nav-section">
                    <div class="nav-section-label">Main</div>
                    <a href="dashboard.php" class="active"><i class="fas fa-house"></i> Dashboard</a>
                    <a href="find_tutors.php"><i class="fas fa-magnifying-glass"></i> Find Tutors</a>
                    <a href="book_session.php" class="primary-link"><i class="fas fa-circle-plus"></i> Book Session</a>
                    <a href="schedule.php"><i class="fas fa-calendar-days"></i> Schedule</a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Account</div>
                    <a href="resources.php"><i class="fas fa-book-open"></i> Resources</a>
                    <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                    <a href="profile.php"><i class="fas fa-user-gear"></i> Profile</a>
                    <a href="settings.php"><i class="fas fa-sliders"></i> Settings</a>
                    <a href="../config/auth/logout.php" class="logout-link"><i class="fas fa-right-from-bracket"></i> Logout</a>
                </div>
            </nav>

            <div class="portal-sidebar-footer">
                <div><i class="fas fa-shield-halved"></i> Secure learning platform</div>
                <div>&copy; <?php echo date('Y'); ?> SOTMS Pro</div>
            </div>
        </aside>

        <main class="portal-main">
            <section class="hero-banner">
                <div>
                    <h1 class="hero-title">Your learning space.</h1>
                    <p class="hero-copy">Sessions, tutors, payments, and profile details in one place.</p>
                </div>
                <div class="hero-meta">
                    <div class="hero-panel">
                        <div class="hero-panel-label">Profile</div>
                        <div class="hero-panel-value"><?php echo $profileCompletion; ?>%</div>
                        <div class="hero-panel-subtext"><?php echo htmlspecialchars($profileSummary); ?></div>
                    </div>
                    <div class="hero-panel">
                        <div class="hero-panel-label">Location</div>
                        <div class="hero-panel-value compact"><?php echo htmlspecialchars($locationSummary); ?></div>
                        <div class="hero-panel-subtext"><?php echo htmlspecialchars($subjectSummary); ?></div>
                    </div>
                </div>
            </section>

            <?php if (isset($_SESSION['booking_success'])): ?>
                <div class="dashboard-alert success">
                    <?php echo htmlspecialchars($_SESSION['booking_success']); ?>
                </div>
                <?php unset($_SESSION['booking_success']); ?>
            <?php endif; ?>

            <section class="stats-grid">
                <article class="dashboard-card metric-card">
                    <div class="metric-top">
                        <div class="metric-label">Upcoming Sessions</div>
                        <div class="metric-icon"><i class="fas fa-calendar-check"></i></div>
                    </div>
                    <div class="metric-value"><?php echo $stats['upcoming']; ?></div>
                    <div class="metric-note">Next lessons</div>
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
                        <div class="metric-label">Resources Ready</div>
                        <div class="metric-icon"><i class="fas fa-folder-open"></i></div>
                    </div>
                    <div class="metric-value"><?php echo $stats['resources']; ?></div>
                    <div class="metric-note">Files</div>
                </article>
                <article class="dashboard-card metric-card">
                    <div class="metric-top">
                        <div class="metric-label">Payments to Review</div>
                        <div class="metric-icon"><i class="fas fa-credit-card"></i></div>
                    </div>
                    <div class="metric-value"><?php echo $stats['pending_payment']; ?></div>
                    <div class="metric-note">Pending</div>
                </article>
            </section>

            <section class="dashboard-card section-card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Quick Actions</h2>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="book_session.php" class="quick-action">
                        <div class="quick-action-icon"><i class="fas fa-circle-plus"></i></div>
                        <div class="quick-action-title">Book a session</div>
                    </a>
                    <a href="find_tutors.php" class="quick-action">
                        <div class="quick-action-icon"><i class="fas fa-user-group"></i></div>
                        <div class="quick-action-title">Browse tutor matches</div>
                    </a>
                    <a href="schedule.php" class="quick-action">
                        <div class="quick-action-icon"><i class="fas fa-calendar-days"></i></div>
                        <div class="quick-action-title">Open your schedule</div>
                    </a>
                    <a href="profile.php" class="quick-action">
                        <div class="quick-action-icon"><i class="fas fa-pen-to-square"></i></div>
                        <div class="quick-action-title">Update your profile</div>
                    </a>
                </div>
            </section>

            <section class="dashboard-grid">
                <article class="dashboard-card section-card">
                    <div class="section-head">
                        <div>
                            <h2 class="section-title">Recent Sessions</h2>
                        </div>
                        <a href="schedule.php" class="section-link">Open schedule</a>
                    </div>

                    <?php if (empty($recentSessions)): ?>
                        <div class="empty-state">
                            <p>No sessions yet.</p>
                            <p class="space-top-sm">
                                <a href="book_session.php" class="btn"><i class="fas fa-circle-plus"></i> Book Session</a>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="content-list">
                            <?php foreach ($recentSessions as $session): ?>
                                <?php $paymentLabel = ucfirst(str_replace('_', ' ', (string) ($session['payment_status'] ?? 'unpaid'))); ?>
                                <div class="item-card">
                                    <div class="item-head">
                                        <div>
                                            <h3 class="item-title"><?php echo htmlspecialchars($session['subject']); ?></h3>
                                            <div class="item-meta">
                                                Tutor: <?php echo htmlspecialchars($session['tutor_name'] ?: 'Not assigned'); ?><br>
                                                Date: <?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?><br>
                                                Amount: KSh <?php echo number_format((float) ($session['amount'] ?: 500), 2); ?>
                                            </div>
                                        </div>
                                        <span class="status-pill <?php echo htmlspecialchars($session['status']); ?>"><?php echo htmlspecialchars($session['status']); ?></span>
                                    </div>
                                    <div class="item-tags">
                                        <span class="tag"><i class="fas fa-wallet"></i> <?php echo htmlspecialchars($paymentLabel); ?></span>
                                    </div>
                                    <div class="item-actions">
                                        <?php if (in_array($session['payment_status'], ['unpaid', 'failed', 'refunded'], true)): ?>
                                            <a href="pay_session.php?id=<?php echo (int) $session['id']; ?>" class="btn"><i class="fas fa-credit-card"></i> Pay Now</a>
                                        <?php elseif ($session['payment_status'] === 'processing'): ?>
                                            <span class="btn secondary"><i class="fas fa-hourglass-half"></i> Awaiting Verification</span>
                                        <?php endif; ?>
                                        <a href="schedule.php" class="btn secondary"><i class="fas fa-calendar-days"></i> View Schedule</a>
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
                                <h2 class="section-title">Profile Snapshot</h2>
                            </div>
                            <a href="profile.php" class="section-link">Edit profile</a>
                        </div>

                        <div class="mini-grid">
                            <div class="mini-card">
                                <div class="mini-label">Curriculum</div>
                                <div class="mini-value"><?php echo htmlspecialchars($profile['curriculum_display'] ?? 'Not set'); ?></div>
                            </div>
                            <div class="mini-card">
                                <div class="mini-label">Level</div>
                                <div class="mini-value"><?php echo htmlspecialchars($profile['study_level_display'] ?? 'Not set'); ?></div>
                            </div>
                            <div class="mini-card">
                                <div class="mini-label">Location</div>
                                <div class="mini-value"><?php echo htmlspecialchars($locationSummary); ?></div>
                            </div>
                            <div class="mini-card">
                                <div class="mini-label">Completion</div>
                                <div class="mini-value"><?php echo $profileCompletion; ?>%</div>
                            </div>
                        </div>

                        <div class="item-tags space-top-md">
                            <?php if (empty($subjects)): ?>
                                <span class="tag"><i class="fas fa-circle-info"></i> Add subjects</span>
                            <?php else: ?>
                                <?php foreach (array_slice($subjects, 0, 5) as $subject): ?>
                                    <span class="tag"><i class="fas fa-book"></i> <?php echo htmlspecialchars($subject); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            </section>

            <section class="dashboard-card section-card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Top Tutor Matches</h2>
                    </div>
                    <a href="find_tutors.php" class="section-link">View all matches</a>
                </div>

                <?php if (empty($matchedTutors)): ?>
                    <div class="empty-state">
                        <p>Complete your profile for better matches.</p>
                        <p class="space-top-sm">
                            <a href="profile.php" class="btn"><i class="fas fa-user-gear"></i> Update Profile</a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="content-list compact-grid">
                        <?php foreach ($matchedTutors as $tutor): ?>
                            <div class="item-card">
                                <div class="item-head">
                                    <div>
                                        <h3 class="item-title"><?php echo htmlspecialchars($tutor['full_name'] ?: $tutor['name']); ?></h3>
                                        <div class="item-meta">
                                            <?php echo htmlspecialchars($tutor['subjects_taught_display'] ?: 'General tutoring'); ?><br>
                                            <?php echo htmlspecialchars($tutor['location'] ?: 'Location not provided'); ?><br>
                                            Rate: KSh <?php echo number_format((float) $tutor['session_rate'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="item-tags">
                                    <span class="tag"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($tutor['location'] ?: 'Area pending'); ?></span>
                                    <?php foreach (($tutor['match_reasons'] ?: ['General profile']) as $reason): ?>
                                        <span class="tag"><i class="fas fa-check"></i> <?php echo htmlspecialchars($reason); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="item-actions">
                                    <a href="book_session.php?tutor=<?php echo (int) $tutor['tutor_id']; ?>" class="btn"><i class="fas fa-calendar-plus"></i> Book</a>
                                    <a href="messages.php?to=<?php echo (int) $tutor['user_id']; ?>" class="btn secondary"><i class="fas fa-envelope"></i> Message</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
