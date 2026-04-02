<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']); // Only allow students

// Get session statistics
$upcomingSessions = 0;
$completedSessions = 0;

try {
    $studentId = getStudentId($pdo, $_SESSION['user_id']);

    if ($studentId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status IN ("pending", "confirmed") AND session_date >= NOW()');
        $stmt->execute([$studentId]);
        $upcomingSessions = $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status = "completed"');
        $stmt->execute([$studentId]);
        $completedSessions = $stmt->fetchColumn();
    }

    // Load profile image for top-right avatar
    $stmt = $pdo->prepare('SELECT profile_image FROM student_profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profileData = $stmt->fetch();
    $profileImage = $profileData['profile_image'] ?? null;

    // Load tutors + profile previews for student usage (all tutors, optionally profile record)
    $tutors = [];
    $stmt = $pdo->prepare('SELECT u.id AS user_id, t.id AS tutor_id, u.name, u.email, tp.profile_image, tp.subjects_taught, tp.qualifications, tp.bio, tp.experience, tp.hourly_rate
        FROM users u
        LEFT JOIN tutors t ON t.user_id = u.id
        LEFT JOIN tutor_profiles tp ON tp.user_id = u.id
        WHERE u.role = "tutor"
        ORDER BY u.name
        LIMIT 5');
    $stmt->execute();
    $tutors = $stmt->fetchAll();
} catch (PDOException $e) {
    $profileImage = null;
    error_log('Dashboard stats error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SOTMS PRO</title>
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
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.15);
            overflow: hidden;
            display: flex;
            min-height: 80vh;
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1e293b, #0f172a);
            padding: 30px 20px;
            color: white;
        }
        .sidebar h2 {
            margin: 0 0 20px;
            font-size: 1.2rem;
            color: #e2e8f0;
        }
        .sidebar .btn {
            display: block;
            width: 100%;
            margin-bottom: 10px;
            text-align: left;
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .sidebar .btn.schedule-btn {
            background: #2563eb;
            color: white !important;
            font-size: 18px;
            font-weight: 700;
            padding: 16px 18px;
            box-shadow: 0 10px 20px rgba(37,99,235,0.25);
        }
        .sidebar .btn.schedule-btn:hover {
            background: #1d4ed8;
            transform: translateX(2px);
        }
        .sidebar .btn:hover, .sidebar .btn.active {
            background: #2563eb;
            transform: translateX(5px);
        }
        .sidebar .logout-btn {
            margin-top: 30px;
            background: rgba(239,68,68,0.8);
        }
        .sidebar .logout-btn:hover {
            background: #dc2626;
        }
        .main-content {
            flex: 1;
            padding: 30px;
        }
        .header {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        .header-content {
            max-width: calc(100% - 240px);
        }
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
        }
        .profile-dropdown {
            position: relative;
            cursor: pointer;
        }
        .profile-trigger {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 12px 14px 14px;
            background: rgba(255,255,255,0.18);
            border-radius: 20px;
            transition: background 0.2s;
            min-width: 110px;
        }
        .profile-trigger:hover {
            background: rgba(255,255,255,0.28);
        }
        .profile-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.9);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.2);
        }
        .profile-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-icon span {
            font-size: 1.6rem;
            color: white;
        }
        .profile-name {
            color: white;
            font-weight: 700;
            text-align: center;
            font-size: 0.95rem;
            line-height: 1.2;
        }
        .profile-role {
            color: rgba(255,255,255,0.8);
            font-size: 0.82rem;
            text-align: center;
        }
        .profile-menu {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            min-width: 220px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.18);
            display: none;
            z-index: 20;
            overflow: hidden;
        }
        .profile-menu.open {
            display: block;
        }
        .profile-menu a {
            display: block;
            padding: 14px 18px;
            color: #1f2937;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.15s;
        }
        .profile-menu a:hover {
            background: #f8fafc;
        }
        .profile-menu .menu-title {
            padding: 18px;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
            font-weight: 700;
        }
        .main-content {
            flex: 1;
            padding: 30px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            margin: 0 0 10px;
            color: #374151;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
        }
        .sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .section h2 {
            margin: 0 0 15px;
            color: #1f2937;
        }
        .section ul {
            list-style: none;
            padding: 0;
        }
        .section li {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .section li:last-child {
            border-bottom: none;
        }
        .section a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .section a:hover {
            text-decoration: underline;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px 10px 10px 0;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                padding: 20px;
            }
            .sidebar .btn {
                display: inline-block;
                width: auto;
                margin: 5px;
                flex: 1;
                min-width: 120px;
            }
            .sections {
                grid-template-columns: 1fr;
            }
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Quick Actions</h2>
            <a href="book_session.php" class="btn schedule-btn">📅 Schedule Session</a>
            <a href="schedule.php" class="btn">📆 My Sessions</a>
            <a href="find_tutors.php" class="btn">👨‍🏫 Find Tutors</a>
            <a href="profile.php" class="btn">👤 My Profile</a>
            <a href="messages.php" class="btn">💬 Messages</a>
            <a href="resources.php" class="btn">📚 Resources</a>
            <a href="../config/auth/logout.php" class="btn logout-btn">🚪 Logout</a>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <h1>Welcome to Your Dashboard</h1>
                    <p>Hello, <?php echo htmlspecialchars($_SESSION['name']); ?>! Here's your learning overview.</p>
                </div>
                <div class="header-actions">
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-trigger" id="profileTrigger">
                            <div class="profile-icon">
                                <?php if (!empty($profileImage)): ?>
                                    <img src="../<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Image">
                                <?php else: ?>
                                    <span><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                            <div class="profile-role">Student Profile</div>
                        </div>
                        <div class="profile-menu" id="profileMenu">
                            <div class="menu-title">Account</div>
                            <a href="profile.php">View Profile</a>
                            <a href="settings.php">Settings</a>
                            <a href="messages.php">Messages</a>
                            <a href="schedule.php">My Sessions</a>
                            <a href="../config/auth/logout.php">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        
        <div class="content">
            <?php if (isset($_SESSION['booking_success'])): ?>
                <div style="background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 12px; padding: 16px; margin-bottom: 24px; font-weight: 600;">
                    <?php echo htmlspecialchars($_SESSION['booking_success']); ?>
                </div>
                <?php unset($_SESSION['booking_success']); ?>
            <?php endif; ?>
            <div class="stats">
                <div class="stat-card">
                    <h3>Upcoming Sessions</h3>
                    <div class="number"><?php echo $upcomingSessions; ?></div>
                    <p><?php echo $upcomingSessions > 0 ? 'Sessions scheduled' : 'No sessions scheduled'; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Completed Sessions</h3>
                    <div class="number"><?php echo $completedSessions; ?></div>
                    <p><?php echo $completedSessions > 0 ? 'Sessions completed' : 'Start learning today!'; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Available Tutors</h3>
                    <div class="number">--</div>
                    <p>Browse our tutor directory</p>
                </div>
            </div>
            
            <div class="sections">
                <div class="section">
                    <h2>Recent Activity</h2>
                    <ul>
                        <li>Welcome to SOTMS PRO! Complete your profile to get started.</li>
                        <li>No recent sessions. Book your first tutoring session today.</li>
                        <li>Explore available resources in the Resources section.</li>
                    </ul>
                </div>
                
                <div class="section">
                    <h2>My Sessions</h2>
                    <?php if ($upcomingSessions > 0): ?>
                        <p>You have <?php echo $upcomingSessions; ?> upcoming session(s).</p>
                        <a href="#" class="btn">View All Sessions</a>
                    <?php else: ?>
                        <p>You have no upcoming sessions.</p>
                        <a href="book_session.php" class="btn">Schedule a Session</a>
                    <?php endif; ?>
                </div>
                
                <div class="section">
                    <h2>Recommended Tutors</h2>
                    <?php if (empty($tutors)): ?>
                        <p>No tutors available right now. Check back soon.</p>
                    <?php else: ?>
                        <?php foreach ($tutors as $t): ?>
                            <div style="border:1px solid #e5e7eb; border-radius:10px; padding:12px; margin-bottom:10px; background:#ffffff;">
                                <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                                <p style="margin:4px 0; color:#6b7280;"><?php echo htmlspecialchars($t['subjects_taught'] ?? 'Subjects not set yet'); ?></p>
                                <p style="margin:4px 0; font-size:.92rem; color:#475569;"><?php echo htmlspecialchars(strlen($t['bio']) ? substr($t['bio'], 0, 120) . (strlen($t['bio']) > 120 ? '...' : '') : 'No bio available'); ?></p>
                                <div style="margin-top: 8px; font-size:.9rem; color:#0f172a; font-weight:600;">
                                    <?php if (!empty($t['hourly_rate'])): ?>
                                        Rate: <?php echo htmlspecialchars($t['hourly_rate']); ?>
                                    <?php else: ?>
                                        Rate: Not specified
                                    <?php endif; ?>
                                </div>
                                <a href="book_session.php?tutor=<?php echo $t['tutor_id']; ?>" class="btn" style="margin-top:8px;">Book a Session</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <h2>Quick Links</h2>
                    <ul>
                        <li><a href="profile.php">Update your profile information</a></li>
                        <li><a href="messages.php">Send messages to tutors</a></li>
                        <li><a href="find_tutors.php">Browse available tutors</a></li>
                        <li><a href="resources.php">Access learning resources</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script>
        const profileTrigger = document.getElementById('profileTrigger');
        const profileMenu = document.getElementById('profileMenu');
        const profileDropdown = document.getElementById('profileDropdown');

        profileTrigger.addEventListener('click', function(event) {
            event.stopPropagation();
            profileMenu.classList.toggle('open');
        });

        document.addEventListener('click', function() {
            profileMenu.classList.remove('open');
        });

        profileDropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
</body>
</html>