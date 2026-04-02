<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['tutor']);

// Ensure this tutor has a row in tutors and can appear for students
getTutorId($pdo, $_SESSION['user_id']);
$tutorId = getTutorId($pdo, $_SESSION['user_id']);

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tutor_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        profile_image VARCHAR(255),
        full_name VARCHAR(100),
        phone VARCHAR(20),
        subjects_taught TEXT,
        qualifications TEXT,
        bio TEXT,
        experience TEXT,
        hourly_rate VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log('Tutor profile table creation error: ' . $e->getMessage());
}

$upcomingSessions = 0;
$pendingSessions = 0;
$completedSessions = 0;
$newMessages = 0;
$materialsCount = 0;
$profileImage = null;
$recentSessions = [];

try {
$stmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE tutor_id = ? AND status IN ("pending", "confirmed") AND session_date >= NOW()');
    $stmt->execute([$tutorId]);
    $upcomingSessions = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE tutor_id = ? AND status = "pending"');
$stmt->execute([$tutorId]);
    $pendingSessions = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE tutor_id = ? AND status = "completed"');
    $stmt->execute([$_SESSION['user_id']]);
    $completedSessions = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$_SESSION['user_id']]);
    $newMessages = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tutor_materials WHERE tutor_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $materialsCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT profile_image FROM tutor_profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profileData = $stmt->fetch();
    $profileImage = $profileData['profile_image'] ?? null;

    $stmt = $pdo->prepare('SELECT s.id, s.session_date, s.status, u.name AS student_name FROM sessions s LEFT JOIN users u ON s.student_id = u.id WHERE s.tutor_id = ? ORDER BY s.session_date ASC LIMIT 5');
    $stmt->execute([$_SESSION['user_id']]);
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
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)),
                        url('../uploads/image003.jpg') center/cover no-repeat;
            color: #1f2937;
            margin: 0;
            padding: 20px;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255,255,255,0.96);
            border-radius: 16px;
            box-shadow: 0 24px 50px rgba(15,23,42,0.18);
            overflow: hidden;
            display: flex;
            min-height: 80vh;
        }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #111827, #0f172a);
            padding: 32px 20px;
            color: white;
        }
        .sidebar h2 {
            margin: 0 0 24px;
            font-size: 1.15rem;
            color: #e2e8f0;
        }
        .sidebar .nav-link {
            display: block;
            width: 100%;
            margin-bottom: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            background: rgba(255,255,255,0.08);
            transition: transform 0.2s, background 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(56,189,248,0.18);
            transform: translateX(4px);
        }
        .sidebar .primary-link {
            background: #2563eb;
            box-shadow: 0 12px 25px rgba(37,99,235,0.2);
            border: 1px solid rgba(255,255,255,0.15);
            color: white;
        }
        .sidebar .logout-btn {
            margin-top: 28px;
            background: rgba(239,68,68,0.85);
        }
        .main-content {
            flex: 1;
            padding: 30px;
        }
        .header {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .header-title {
            max-width: calc(100% - 240px);
        }
        .header-title h1 {
            margin: 0;
            font-size: 2.4rem;
        }
        .header-title p {
            margin: 10px 0 0;
            opacity: 0.9;
            line-height: 1.6;
        }
        .profile-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.14);
            padding: 14px 18px;
            border-radius: 22px;
        }
        .profile-photo {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid rgba(255,255,255,0.9);
            background: rgba(255,255,255,0.18);
        }
        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-name {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            text-align: center;
        }
        .profile-role {
            margin: 0;
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 22px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            padding: 24px;
            box-shadow: 0 12px 24px rgba(15,23,42,0.08);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 1rem;
            color: #374151;
        }
        .stat-card .number {
            margin-top: 16px;
            font-size: 2.4rem;
            font-weight: 700;
            color: #1d4ed8;
        }
        .stat-card p {
            margin: 10px 0 0;
            color: #6b7280;
            line-height: 1.6;
        }
        .panel {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 12px 24px rgba(15,23,42,0.05);
            margin-bottom: 30px;
        }
        .panel h2 {
            margin-top: 0;
            font-size: 1.2rem;
            color: #111827;
        }
        .session-item,
        .message-item,
        .material-item {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            padding: 18px 20px;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            margin-bottom: 16px;
            background: #f9fafb;
        }
        .session-details,
        .message-details,
        .material-details {
            flex: 1;
        }
        .session-meta,
        .message-meta,
        .material-meta {
            color: #6b7280;
            font-size: 0.95rem;
            margin-top: 8px;
            line-height: 1.6;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .pending { background: #fef3c7; color: #b45309; }
        .confirmed { background: #dbeafe; color: #1d4ed8; }
        .completed { background: #d1fae5; color: #065f46; }
        .cancelled { background: #fee2e2; color: #991b1b; }
        .panel-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .panel-footer .btn {
            margin: 0;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
        }
        .btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #111827;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        @media (max-width: 992px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                padding: 22px;
            }
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            .profile-panel {
                margin-left: auto;
                justify-self: flex-end;
            }
        }
        @media (max-width: 768px) {
            .sidebar .nav-link {
                font-size: 14px;
            }
            .profile-photo {
                width: 56px;
                height: 56px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .sections {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Tutor Control Panel</h2>
<a href="schedule.php" class="nav-link primary-link">📅 Manage Schedule</a>
            <a href="upload_materials.php" class="nav-link">📤 Upload Materials</a>
            <a href="messages.php" class="nav-link">💬 Messages</a>
            <a href="profile.php" class="nav-link">👤 My Profile</a>
            <a href="settings.php" class="nav-link">⚙️ Settings</a>
<a href="my_sessions.php" class="nav-link active" title="View your tutoring sessions and schedule">🔄 My Sessions</a>
            <a href="../config/auth/logout.php" class="nav-link logout-btn">🚪 Logout</a>
        </div>

        <div class="main-content">
            <div class="header">
                <div class="header-title">
                    <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
                    <p>Manage your tutoring schedule, upload study materials, communicate with students, and track your success.</p>
                </div>
                <div class="profile-panel">
                    <div class="profile-photo">
                        <?php if (!empty($profileImage)): ?>
                            <img src="../<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Image">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></p>
                    <p class="profile-role">Tutor Dashboard</p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Upcoming Sessions</h3>
                    <div class="number"><?php echo $upcomingSessions; ?></div>
                    <p>Sessions scheduled for today and future dates.</p>
                </div>
                <div class="stat-card">
                    <h3>Pending Requests</h3>
                    <div class="number"><?php echo $pendingSessions; ?></div>
                    <p>Sessions that still need your confirmation.</p>
                </div>
                <div class="stat-card">
                    <h3>Completed Sessions</h3>
                    <div class="number"><?php echo $completedSessions; ?></div>
                    <p>Sessions marked complete by you.</p>
                </div>
                <div class="stat-card">
                    <h3>New Messages</h3>
                    <div class="number"><?php echo $newMessages; ?></div>
                    <p>Unread student messages waiting for your reply.</p>
                </div>
                <div class="stat-card">
                    <h3>Materials Uploaded</h3>
                    <div class="number"><?php echo $materialsCount; ?></div>
                    <p>Resources available for your students.</p>
                </div>
            </div>

            <div class="panel">
                <h2>Next Sessions</h2>
                <?php if (empty($recentSessions)): ?>
                    <p style="color:#6b7280;">No upcoming sessions are currently assigned to you.</p>
                    <div class="panel-footer">
                        <a href="schedule.php" class="btn">View Full Schedule</a>
                        <a href="messages.php" class="btn btn-secondary">Check Messages</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentSessions as $session): ?>
                        <div class="session-item">
                            <div class="session-details">
                                <strong><?php echo !empty($session['student_name']) ? htmlspecialchars($session['student_name']) : 'Student'; ?></strong>
                                <div class="session-meta">Date: <?php echo date('M j, Y @ g:i A', strtotime($session['session_date'])); ?></div>
                                <div class="session-meta">Status: <span class="status-pill <?php echo htmlspecialchars($session['status']); ?>"><?php echo ucfirst($session['status']); ?></span></div>
                            </div>
                            <a href="messages.php" class="btn btn-secondary">Message Student</a>
                        </div>
                    <?php endforeach; ?>
                    <div class="panel-footer">
                        <a href="schedule.php" class="btn">View Full Schedule</a>
                        <a href="upload_materials.php" class="btn btn-secondary">Add New Material</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Action Center</h2>
                <div class="panel-footer">
                    <a href="upload_materials.php" class="btn">Upload Study Resources</a>
                    <a href="schedule.php" class="btn btn-secondary">Manage Availability</a>
                    <a href="profile.php" class="btn btn-secondary">Update Tutor Profile</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>