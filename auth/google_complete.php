<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['google_oauth_email']) || empty($_SESSION['google_oauth_name'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$email = $_SESSION['google_oauth_email'];
$name = $_SESSION['google_oauth_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'student';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$password || !$confirm_password) {
        $error = 'Please create and confirm your password.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/', $password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                session_unset();
                header('Location: login.php?registered=1');
                exit();
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (name, email, role, password) VALUES (?, ?, ?, ?)');
            $insert->execute([$name, $email, $role, $hashedPassword]);

            session_unset();
            header('Location: login.php?registered=1');
            exit();
        } catch (PDOException $e) {
            error_log('Google complete registration error: ' . $e->getMessage());
            $error = 'Unable to complete registration. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Google Registration - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; font-family: 'Poppins', sans-serif; background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)), url('../uploads/image003.jpg') center/cover no-repeat; color: #1f2937; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { width: min(560px, 100%); background: rgba(255,255,255,0.96); border-radius: 28px; box-shadow: 0 30px 80px rgba(15,23,42,0.16); padding: 36px; border: 1px solid rgba(148,163,184,0.24); }
        .card h1 { margin: 0 0 14px; font-size: 2.4rem; }
        .card p { margin: 0 0 24px; color: #475569; line-height: 1.7; }
        label { display: block; font-weight: 700; margin-bottom: 10px; color: #334155; }
        input, select { width: 100%; padding: 14px 16px; border: 1px solid #cbd5e1; border-radius: 14px; background: #f8fafc; font-size: 1rem; }
        input:focus, select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(59,130,246,0.14); }
        .btn-primary { width: 100%; border: none; border-radius: 14px; padding: 16px 18px; background: #2563eb; color: white; font-weight: 700; cursor: pointer; transition: background 0.2s ease; }
        .btn-primary:hover { background: #1d4ed8; }
        .message { margin-bottom: 18px; padding: 16px; border-radius: 16px; font-size: 0.95rem; }
        .message.error { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 20px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; color: #475569; }
        .footer a { color: #2563eb; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Complete your Google registration</h1>
        <p>Confirm your details, choose an account type, and create a password to finish registration.</p>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="google_complete.php">
            <label for="name">Full Name</label>
            <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($name); ?>" readonly>
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email); ?>" autocomplete="username" readonly>
            <label for="role">Account Type</label>
            <select id="role" name="role">
                <option value="student">Student</option>
                <option value="tutor">Tutor</option>
                <option value="admin">Admin</option>
            </select>
            <label for="password">Create password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required oninput="updatePasswordStrength()">
            <small style="color:#475569; display:block; margin-top:6px;">Use 8+ characters with uppercase, lowercase, and a number.</small>
            <div id="strengthText" style="margin-top:8px; color:#475569; font-size:0.95rem;">Password strength: </div>
            <label for="confirm_password">Confirm password</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
            <button type="submit" class="btn-primary">Complete registration</button>
        </form>
        <div class="footer">
            <span>After registration you will be taken to the login page.</span>
            <a href="login.php">Back to Login</a>
        </div>
    </div>
    <script>
        function updatePasswordStrength() {
            const input = document.getElementById('password');
            const output = document.getElementById('strengthText');
            const value = input.value;
            let score = 0;
            if (value.length >= 8) score += 1;
            if (/[A-Z]/.test(value)) score += 1;
            if (/[a-z]/.test(value)) score += 1;
            if (/\d/.test(value)) score += 1;
            if (/[^A-Za-z0-9]/.test(value)) score += 1;
            const labels = ['Very weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'];
            output.textContent = 'Password strength: ' + labels[score];
            output.style.color = score >= 4 ? '#166534' : score >= 3 ? '#d97706' : '#991b1b';
        }
        updatePasswordStrength();
    </script>
</body>
</html>
