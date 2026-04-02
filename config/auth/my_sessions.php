<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/user_helpers.php';

// Restore session from database manually (can't use auth_check yet)
function restoreSessionFromDatabaseLocal($pdo) {
    if (!isset($_COOKIE['session_token'])) {
        return false;
    }
    
    $session_token = $_COOKIE['session_token'];
    
    try {
        $stmt = $pdo->prepare("SELECT us.user_id, us.role, u.name, u.email 
            FROM user_sessions us 
            JOIN users u ON us.user_id = u.id 
            WHERE us.session_token = ? 
            AND us.expires_at > NOW()");
        $stmt->execute([$session_token]);
        $session = $stmt->fetch();
        
        if ($session) {
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['name'] = $session['name'];
            $_SESSION['role'] = $session['role'];
            $_SESSION['session_token'] = $session_token;
            
            $stmt = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_token = ?");
            $stmt->execute([$session_token]);
            
            return true;
        }
    } catch (PDOException $e) {
        error_log('Session restore error: ' . $e->getMessage());
    }
    
    return false;
}

// Restore session from database
restoreSessionFromDatabaseLocal($pdo);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get all active sessions for this user
$active_sessions = [];
try {
    $stmt = $pdo->prepare("SELECT id, role, session_token, ip_address, last_activity, created_at,
        CASE WHEN session_token = ? THEN 1 ELSE 0 END as is_current
        FROM user_sessions 
        WHERE user_id = ? 
        AND expires_at > NOW()
        ORDER BY is_current DESC, last_activity DESC");
    $stmt->execute([$_COOKIE['session_token'] ?? '', $_SESSION['user_id']]);
    $active_sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Fetch sessions error: ' . $e->getMessage());
}

// Handle session switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_to_role'])) {
    $role = $_POST['switch_to_role'];
    
    // Find session with this role
    foreach ($active_sessions as $s) {
        if ($s['role'] === $role) {
            setcookie('session_token', $s['session_token'], time() + (30 * 24 * 60 * 60), '/');
            $_SESSION['session_token'] = $s['session_token'];
            
            // Redirect to appropriate dashboard
            if ($role == 'admin') {
                header("Location: ../../admin/dashboard.php");
            } elseif ($role == 'tutor') {
                header("Location: ../../tutor/dashboard.php");
            } else {
                header("Location: ../../student/dashboard.php");
            }
            exit();
        }
    }
}

// Handle session logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_token'])) {
    $logout_token = $_POST['logout_token'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ? AND user_id = ?");
        $stmt->execute([$logout_token, $_SESSION['user_id']]);
        
        // Redirect to refresh the page
        header("Location: my_sessions.php");
        exit();
    } catch (PDOException $e) {
        error_log('Logout session error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Active Sessions - SOTMS PRO</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)),
                        url('../../uploads/image003.jpg') center/cover no-repeat;
            color: #1f2937;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.15);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2563eb;
            font-size: 2rem;
            margin: 0 0 10px;
        }
        .header p {
            color: #6b7280;
            margin: 0;
        }
        .sessions-list {
            margin-bottom: 30px;
        }
        .session-item {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .session-item.current {
            border-color: #2563eb;
            background: #f0f9ff;
        }
        .session-item:hover {
            border-color: #2563eb;
            background: #f0f9ff;
        }
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .role-badge {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: capitalize;
        }
        .current-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }
        .session-meta {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 8px 0;
        }
        .session-meta strong {
            color: #1f2937;
        }
        .session-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        .btn {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
            flex: 0;
            padding: 8px 14px;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .info-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        .no-sessions {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Active Sessions</h1>
            <p>Manage your logged-in devices and roles</p>
        </div>

        <?php if ($_SESSION['role'] === 'tutor'): 
            $tutorId = getTutorId($pdo, $_SESSION['user_id']);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE tutor_id = ?');
            $stmt->execute([$tutorId]);
            $sessionCount = $stmt->fetchColumn();
        ?>
        <div class="info-box" style="background: #d1fae5; border-color: #a7f3d0; color: #065f46;">
            ✓ You have <?php echo $sessionCount; ?> tutoring sessions. 
            <a href="../../tutor/schedule.php" style="color: #1d4ed8; font-weight: 700;">View Schedule →</a><br>
            Below are your active login sessions.
        </div>
        <?php else: ?>
        <div class="info-box">
            ✓ Login with different roles to see them here. Switch between sessions or logout from specific devices.
        </div>
        <?php endif; ?>

        <div class="sessions-list">
            <?php if (empty($active_sessions)): ?>
                <div class="no-sessions">
                    <h3>No active sessions</h3>
                    <p>You're not logged in anywhere. Please login to continue.</p>
                    <a href="login.php" style="display: inline-block; background: #2563eb; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 20px;">Go to Login</a>
                </div>
            <?php else: ?>
                <?php foreach ($active_sessions as $session): ?>
                    <div class="session-item <?php echo $session['is_current'] ? 'current' : ''; ?>">
                        <div class="session-header">
                            <div>
                                <span class="role-badge"><?php echo ucfirst($session['role']); ?></span>
                                <?php if ($session['is_current']): ?>
                                    <span class="current-badge">CURRENT</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="session-meta">
                            <strong>IP Address:</strong> <?php echo htmlspecialchars($session['ip_address']); ?>
                        </div>
                        <div class="session-meta">
                            <strong>Last Active:</strong> <?php echo date('M j, Y @ g:i A', strtotime($session['last_activity'])); ?>
                        </div>
                        <div class="session-meta">
                            <strong>Logged In:</strong> <?php echo date('M j, Y @ g:i A', strtotime($session['created_at'])); ?>
                        </div>
                        
                        <?php if (!$session['is_current']): ?>
                            <div class="session-actions">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="switch_to_role" value="<?php echo htmlspecialchars($session['role']); ?>">
                                    <button type="submit" class="btn btn-primary">Switch to <?php echo ucfirst($session['role']); ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="logout_token" value="<?php echo htmlspecialchars($session['session_token']); ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Logout from this session?');">Logout</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="back-link">
            <?php if ($_SESSION['role'] === 'student'): ?>
                <a href="../../student/dashboard.php">← Back to Student Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'tutor'): ?>
                <a href="../../tutor/dashboard.php">← Back to Tutor Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                <a href="../../admin/dashboard.php">← Back to Admin Dashboard</a>
            <?php else: ?>
                <a href="../../index.php">← Back to Home</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>