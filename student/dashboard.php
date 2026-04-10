<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

$studentId = getStudentId($pdo, $_SESSION['user_id']);
$profile = fetchStudentProfile($pdo, $_SESSION['user_id']);
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
        ORDER BY s.session_date ASC
        LIMIT 4
    ");
    $stmt->execute([$studentId]);
    $recentSessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Student dashboard error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student / Parent Dashboard - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)), url('../uploads/image003.jpg') center/cover no-repeat;
            color: #1f2937;
            margin: 0;
            padding: 20px;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255,255,255,0.96);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(15,23,42,0.18);
            display: flex;
            min-height: 82vh;
        }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0f172a, #111827);
            padding: 30px 20px;
            color: white;
        }
        .sidebar h2 { margin-top: 0; color: #e2e8f0; font-size: 1.1rem; }
        .sidebar .link {
            display: block;
            padding: 13px 15px;
            border-radius: 12px;
            color: white;
            text-decoration: none;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.08);
            font-weight: 600;
        }
        .sidebar .link:hover,
        .sidebar .link.active { background: rgba(59,130,246,0.24); }
        .sidebar .primary { background: #2563eb; }
        .sidebar .logout { background: rgba(239,68,68,0.88); margin-top: 24px; }
        .main-content { flex: 1; padding: 30px; }
        .hero {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 30px;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            margin-bottom: 28px;
        }
        .hero h1 { margin: 0; font-size: 2.4rem; }
        .hero p { margin: 10px 0 0; opacity: 0.92; max-width: 780px; }
        .profile-chip {
            background: rgba(255,255,255,0.15);
            border-radius: 18px;
            padding: 16px 18px;
            min-width: 190px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card, .panel {
            background: white;
            border-radius: 18px;
            padding: 24px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 12px 24px rgba(15,23,42,0.06);
        }
        .stat-card h3 { margin: 0; color: #374151; font-size: 1rem; }
        .stat-card .number { margin-top: 14px; font-size: 2.3rem; font-weight: 700; color: #1d4ed8; }
        .panels {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 24px;
        }
        .panel h2 { margin-top: 0; font-size: 1.2rem; }
        .session-card, .tutor-card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 14px;
            background: #f8fafc;
        }
        .status-pill {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .pending { background: #fef3c7; color: #b45309; }
        .confirmed { background: #dbeafe; color: #1d4ed8; }
        .completed { background: #d1fae5; color: #065f46; }
        .cancelled { background: #fee2e2; color: #991b1b; }
        .tag-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .tag { background: #dbeafe; color: #1d4ed8; border-radius: 999px; padding: 6px 10px; font-size: 0.8rem; font-weight: 600; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            padding: 11px 18px;
            font-weight: 700;
        }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #0f766e; }
        .btn-secondary:hover { background: #115e59; }
        .empty { color: #6b7280; }
        @media (max-width: 992px) {
            .dashboard-container, .panels, .hero { flex-direction: column; display: block; }
            .sidebar { width: auto; }
            .panels { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Student / Parent Panel</h2>
            <a href="book_session.php" class="link primary">Book Session</a>
            <a href="find_tutors.php" class="link active">Find Tutors</a>
            <a href="schedule.php" class="link">My Sessions</a>
            <a href="resources.php" class="link">Learning Materials</a>
            <a href="messages.php" class="link">Messages</a>
            <a href="profile.php" class="link">Profile & Match Settings</a>
            <a href="../config/auth/logout.php" class="link logout">Logout</a>
        </div>

        <div class="main-content">
            <div class="hero">
                <div>
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
                    <p>Track your sessions, review matched tutors, and keep your curriculum, study level, and location up to date so SOTMS Pro can recommend the right tutor.</p>
                </div>
                <div class="profile-chip">
                    <div style="font-size:0.82rem; text-transform:uppercase; opacity:0.85;">Profile Match Setup</div>
                    <div style="font-size:2rem; font-weight:700; margin-top:8px;"><?php echo $profileCompletion; ?>%</div>
                    <div style="margin-top:8px;"><?php echo !empty($profile['curriculum_display']) ? htmlspecialchars($profile['curriculum_display']) : 'Curriculum not set'; ?></div>
                </div>
            </div>

            <?php if (isset($_SESSION['booking_success'])): ?>
                <div class="panel" style="background:#d1fae5; color:#065f46; border-color:#a7f3d0; margin-bottom:20px;">
                    <?php echo htmlspecialchars($_SESSION['booking_success']); ?>
                </div>
                <?php unset($_SESSION['booking_success']); ?>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Upcoming Sessions</h3>
                    <div class="number"><?php echo $stats['upcoming']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Completed Sessions</h3>
                    <div class="number"><?php echo $stats['completed']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Resources Available</h3>
                    <div class="number"><?php echo $stats['resources']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <div class="number"><?php echo $stats['pending_payment']; ?></div>
                </div>
            </div>

            <div class="panels">
                <div class="panel">
                    <h2>Recent Sessions</h2>
                    <?php if (empty($recentSessions)): ?>
                        <p class="empty">No sessions yet. Book your first session to start learning.</p>
                        <a href="book_session.php" class="btn">Book Session</a>
                    <?php else: ?>
                        <?php foreach ($recentSessions as $session): ?>
                            <div class="session-card">
                                <strong><?php echo htmlspecialchars($session['subject']); ?></strong>
                                <div style="margin-top:8px; color:#475569;">
                                    Tutor: <?php echo htmlspecialchars($session['tutor_name'] ?: 'Not assigned'); ?><br>
                                    Date: <?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?><br>
                                    Amount: KSh <?php echo number_format((float) ($session['amount'] ?: 500), 2); ?>
                                </div>
                                <div style="margin-top:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <span class="status-pill <?php echo htmlspecialchars($session['status']); ?>"><?php echo htmlspecialchars($session['status']); ?></span>
                                    <?php if (in_array($session['payment_status'], ['unpaid', 'failed', 'refunded'], true)): ?>
                                        <a href="pay_session.php?id=<?php echo (int) $session['id']; ?>" class="btn">Pay Now</a>
                                    <?php elseif ($session['payment_status'] === 'processing'): ?>
                                        <span class="btn btn-secondary" style="cursor:default;">Awaiting Verification</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <a href="schedule.php" class="btn">Open Full Schedule</a>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h2>Top Tutor Matches</h2>
                    <?php if (empty($matchedTutors)): ?>
                        <p class="empty">Complete your profile to see stronger tutor matches.</p>
                        <a href="profile.php" class="btn">Update Profile</a>
                    <?php else: ?>
                        <?php foreach ($matchedTutors as $tutor): ?>
                            <div class="tutor-card">
                                <strong><?php echo htmlspecialchars($tutor['full_name'] ?: $tutor['name']); ?></strong>
                                <div style="margin-top:8px; color:#475569;">
                                    <?php echo htmlspecialchars($tutor['subjects_taught_display'] ?: 'General tutoring'); ?><br>
                                    <?php echo htmlspecialchars($tutor['location'] ?: 'Location not provided'); ?><br>
                                    KSh <?php echo number_format($tutor['session_rate'], 2); ?>
                                </div>
                                <div class="tag-list">
                                    <?php foreach (($tutor['match_reasons'] ?: ['General profile']) as $reason): ?>
                                        <span class="tag"><?php echo htmlspecialchars($reason); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                                    <a href="book_session.php?tutor=<?php echo (int) $tutor['tutor_id']; ?>" class="btn">Book</a>
                                    <a href="messages.php?to=<?php echo (int) $tutor['user_id']; ?>" class="btn btn-secondary">Message</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <a href="find_tutors.php" class="btn">View All Matches</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
