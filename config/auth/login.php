<?php
// Start the session at the very top
session_start();

// Include database connection (up one level)
require_once '../db.php';

$error = "";

// Create user_sessions table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role VARCHAR(20) NOT NULL,
        session_token VARCHAR(255) UNIQUE NOT NULL,
        browser_fingerprint VARCHAR(255),
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (user_id, role),
        INDEX (session_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log('Session table creation error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // 1. Prepare statement to find the user by email
        $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 2. Verify password hash
        if ($user && password_verify($password, $user['password'])) {
            
            // 3. Generate unique session token
            $session_token = bin2hex(random_bytes(32));
            $browser_fingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
            
            // 4. Store session in database
            try {
                $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, role, session_token, browser_fingerprint, ip_address, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], $user['role'], $session_token, $browser_fingerprint, $ip_address, $expires_at]);
                
                // 5. Set session token in cookie (expires in 30 days)
                setcookie('session_token', $session_token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                
                // 6. Also store in PHP session for backward compatibility
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['session_token'] = $session_token;
                
                // 7. Role-Based Redirection 
                if ($user['role'] == 'admin') {
                    header("Location: ../../admin/dashboard.php");
                } elseif ($user['role'] == 'tutor') {
                    header("Location: ../../tutor/dashboard.php");
                } else {
                    header("Location: ../../student/dashboard.php");
                }
                exit();
            } catch (PDOException $e) {
                error_log('Session creation error: ' . $e->getMessage());
                $error = "An error occurred during login. Please try again.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <h2>Secure Login</h2>
        
        <?php if($error): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error); ?></p> <?php endif; ?>

        <form method="POST" action="login.php">
            <label>Email:</label><br>
            <input type="email" name="email" required><br><br>
            
            <label>Password:</label><br>
            <input type="password" name="password" required><br><br>
            
            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>