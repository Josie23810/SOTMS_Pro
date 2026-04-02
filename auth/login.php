<?php
// Start the session at the very top
session_start();

// Include database connection (up one level)
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

if (isset($_GET['registered'])) {
    $success = 'Account created successfully. Please login with your credentials.';
}
if (isset($_GET['reset'])) {
    $success = 'Your password has been reset. Please login with your new password.';
}
if (isset($_GET['google'])) {
    $success = 'Google authentication is ready. Please login with Google.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare('SELECT id, name, password, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'admin') {
                header('Location: ../admin/dashboard.php');
            } elseif ($user['role'] == 'tutor') {
                header('Location: ../tutor/dashboard.php');
            } else {
                header('Location: ../student/dashboard.php');
            }
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            color: #1f2937;
            background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)),
                        url('../uploads/image003.jpg') center/cover no-repeat;
        }
        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .auth-card { width: min(560px, 100%); background: rgba(255,255,255,0.96); backdrop-filter: blur(12px); border-radius: 28px; box-shadow: 0 30px 80px rgba(15,23,42,0.18); border: 1px solid rgba(148,163,184,0.24); overflow: hidden; }
        .auth-top { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; padding: 40px 32px; }
        .auth-top h1 { margin: 0 0 10px; font-size: 2.5rem; }
        .auth-top p { margin: 0; color: rgba(255,255,255,0.9); line-height: 1.7; }
        .auth-body { padding: 32px; }
        .auth-body form { display: grid; gap: 18px; }
        .auth-body label { display: block; margin-bottom: 8px; font-weight: 700; color: #334155; }
        .auth-body input { width: 100%; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 1rem; }
        .auth-body input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(59,130,246,0.14); }
        .btn-primary { width: 100%; border: none; background: #2563eb; color: white; padding: 16px 18px; border-radius: 14px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 16px 30px rgba(37,99,235,0.18); }
        .divider { display: flex; align-items: center; gap: 16px; margin: 16px 0 6px; color: #64748b; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .google-btn { display: inline-flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 14px 16px; border-radius: 14px; border: 1px solid #cbd5e1; background: white; color: #0f172a; text-decoration: none; font-weight: 700; transition: background 0.2s ease; }
        .google-btn:hover { background: #f8fafc; }
        .message { border-radius: 16px; padding: 16px; font-size: 0.95rem; margin-bottom: 18px; }
        .message.success { background: #dcfce7; color: #166534; }
        .message.error { background: #fee2e2; color: #991b1b; }
        .auth-footer { margin-top: 20px; text-align: center; color: #475569; }
        .auth-footer a { color: #2563eb; font-weight: 700; text-decoration: none; }
        .form-foot { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-top">
                <h1>Welcome Back</h1>
                <p>Login to your SOTMS PRO account to continue learning, teaching, or managing the platform.</p>
            </div>
            <div class="auth-body">
                <?php if ($success): ?>
                    <div class="message success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div>
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" autocomplete="username" required>
                    </div>
                    <div>
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="form-foot">
                        <button class="btn-primary" type="submit">Login</button>
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                </form>

                <div class="divider">or</div>

                <a class="google-btn" href="google_login.php">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google icon" width="20" height="20">
                    Continue with Google
                </a>

                <div class="auth-footer">
                    Don’t have an account? <a href="register.php">Register here</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
