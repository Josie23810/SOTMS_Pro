<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['student']);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill in all password fields.';
        $messageType = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New password and confirmation do not match.';
        $messageType = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = 'Your new password must be at least 8 characters long.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$hash, $_SESSION['user_id']]);
                $message = 'Password updated successfully.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'An error occurred while updating your password. Please try again.';
            $messageType = 'error';
            error_log('Password update error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - SOTMS PRO</title>
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
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.15);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
        }
        .nav {
            background: #f8fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }
        .nav a {
            color: #2563eb;
            text-decoration: none;
            margin: 0 15px;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .nav a:hover {
            background: #e0f2fe;
        }
        .content {
            padding: 30px;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .btn {
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .settings-panel {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }
        .settings-panel h2 {
            margin-top: 0;
            color: #1f2937;
        }
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Account Settings</h1>
        </div>

        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="messages.php">Messages</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="settings-grid">
                    <div class="settings-panel">
                        <h2>Change Password</h2>
                        <form method="POST">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn">Update Password</button>
                        </form>
                    </div>

                    <div class="settings-panel">
                        <h2>Account Controls</h2>
                        <p>Use this area to manage secure account options and support access.</p>
                        <ul style="padding-left: 20px; color: #4b5563;">
                            <li>Change your password securely.</li>
                            <li>Keep your profile image and details up to date.</li>
                            <li>Contact support if you need help with your account.</li>
                        </ul>
                        <a href="profile.php" class="btn" style="margin-top: 10px; display: inline-block;">Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>