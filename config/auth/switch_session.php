<?php
session_start();

// Check if user has multiple sessions
if (!isset($_SESSION['sessions']) || empty($_SESSION['sessions'])) {
    header("Location: login.php");
    exit();
}

// Handle role switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'])) {
    $role = $_POST['role'];
    
    // Verify the role exists in sessions
    if (isset($_SESSION['sessions'][$role])) {
        $_SESSION['current_role'] = $role;
        $session_data = $_SESSION['sessions'][$role];
        $_SESSION['user_id'] = $session_data['user_id'];
        $_SESSION['name'] = $session_data['name'];
        $_SESSION['role'] = $session_data['role'];
        
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

$available_roles = $_SESSION['sessions'] ?? [];
$current_role = $_SESSION['current_role'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Switch Role - SOTMS PRO</title>
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
            max-width: 500px;
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
        .roles-list {
            margin-bottom: 30px;
        }
        .role-option {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .role-option:hover {
            border-color: #2563eb;
            background: #f0f9ff;
        }
        .role-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .role-info {
            flex: 1;
        }
        .role-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #1f2937;
            margin-bottom: 5px;
            text-transform: capitalize;
        }
        .role-email {
            font-size: 0.9rem;
            color: #6b7280;
        }
        .current-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }
        .form-group {
            display: flex;
            gap: 10px;
        }
        .btn {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }
        .btn-secondary:hover {
            background: #d1d5db;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Switch Role</h1>
            <p>You are logged in to multiple roles</p>
        </div>

        <div class="info-box">
            ✓ You can now switch between your logged-in roles without logging out. Each role maintains its own session.
        </div>

        <form method="POST">
            <div class="roles-list">
                <?php foreach ($available_roles as $role => $data): ?>
                    <label class="role-option">
                        <input type="radio" name="role" value="<?php echo $role; ?>" 
                            <?php echo ($current_role === $role) ? 'checked' : ''; ?> required>
                        <div class="role-info">
                            <div class="role-name">
                                <?php echo ucfirst($role); ?>
                                <?php if ($current_role === $role): ?>
                                    <span class="current-badge">Current</span>
                                <?php endif; ?>
                            </div>
                            <div class="role-email"><?php echo htmlspecialchars($data['name']); ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Switch Role</button>
                <a href="../../index.php" class="btn btn-secondary" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">Back Home</a>
            </div>
        </form>
    </div>
</body>
</html>