
<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['admin']); // Only allow admins

// Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_tutors = $pdo->query("SELECT COUNT(*) FROM users WHERE role='tutor'")->fetchColumn();
$total_sessions = $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
$pending_sessions = $pdo->query("SELECT COUNT(*) FROM sessions WHERE status='pending'")->fetchColumn();
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
            backdrop-filter: blur(10px);
            border-right: 1px solid #e2e8f0;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar h2 {
            text-align: center;
            color: #1f2937;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2563eb;
            font-size: 1.4rem;
        }
        .sidebar-nav a {
            display: block;
            color: #374151;
            text-decoration: none;
            padding: 15px 25px;
            margin: 5px 20px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            border-left-color: #1d4ed8;
            transform: translateX(5px);
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }
        .welcome-section {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border-left: 6px solid #10b981;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 800px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px 25px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(37,99,235,0.1);
            border-top: 4px solid #2563eb;
            transition: transform 0.3s;
            max-width: 320px;
            margin: 0 auto;
        }
        .stat-card:hover {
            transform: translateY(-8px);
        }
        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .stat-label {
            font-size: 1rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .quick-actions {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            max-width: 1000px;
            margin: 0 auto;
        }
        .quick-btn {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(37,99,235,0.3);
            font-size: 1rem;
        }
        .quick-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(37,99,235,0.4);
        }
        .hamburger {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 1.5rem;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .sidebar { 
                left: -250px; 
                transition: left 0.3s;
            }
            .sidebar.open { left: 0; }
            .main-content { margin-left: 0; }
            .hamburger { display: block; }
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { flex-direction: column; align-items: center; }
            .welcome-section { padding: 20px; margin-bottom: 20px; }
        }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()">☰</button>
    
    <div class="sidebar" id="sidebar">
        <h2>Admin Panel</h2>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="active">📊 Dashboard</a>
            <a href="manage_users.php">👥 Manage Users</a>
            <a href="manage_sessions.php">📅 Manage Sessions</a>
            <a href="reports.php">📈 Reports</a>
            <a href="../config/auth/logout.php">🚪 Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="welcome-section">
            <h1 style="font-size: 2.5rem; margin-bottom: 15px;">🏆 Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
            <p style="font-size: 1.2rem; color: #6b7280;">Complete control over SOTMS Pro. Monitor, manage, and optimize your tutoring platform.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_tutors; ?></div>
                <div class="stat-label">Tutors</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_sessions; ?></div>
                <div class="stat-label">Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_sessions; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="manage_users.php" class="quick-btn">👥 Manage Users</a>
            <a href="manage_sessions.php" class="quick-btn">📅 Sessions</a>
            <a href="reports.php" class="quick-btn">📈 Reports</a>
            <a href="../system_details.php" class="quick-btn">⚙️ System</a>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
    </script>
</body>
</html>

