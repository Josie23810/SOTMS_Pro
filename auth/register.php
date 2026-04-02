<?php
// Include the database connection.
require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $role = $_POST['role'] ?? 'student';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$name || !$email || !$password || !$confirm_password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/', $password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'This email is already registered. Please login or use another email.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');

                if ($insert->execute([$name, $email, $hashed_password, $role])) {
                    header('Location: login.php?registered=1');
                    exit();
                } else {
                    $error = 'Registration failed. Please try again later.';
                }
            }
        } catch (PDOException $e) {
            error_log('Register error: ' . $e->getMessage());
            $error = 'An unexpected error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            color: #1f2937;
            background: linear-gradient(180deg, rgba(15,23,42,0.5), rgba(15,23,42,0.5)),
                        url('../uploads/image003.jpg') center/cover no-repeat;
        }
        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .auth-card { width: min(600px, 100%); background: rgba(255,255,255,0.96); backdrop-filter: blur(14px); border-radius: 28px; box-shadow: 0 30px 80px rgba(15,23,42,0.18); overflow: hidden; border: 1px solid rgba(148,163,184,0.24); }
        .auth-hero { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; padding: 42px 34px; }
        .auth-hero h1 { margin: 0 0 12px; font-size: 2.5rem; letter-spacing: -0.04em; }
        .auth-hero p { margin: 0; color: rgba(255,255,255,0.92); line-height: 1.75; max-width: 580px; }
        .auth-body { padding: 34px; }
        .auth-body form { display: grid; gap: 18px; }
        .auth-body label { display: block; font-weight: 700; margin-bottom: 8px; color: #334155; }
        .auth-body input, .auth-body select { width: 100%; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 1rem; background: #f8fafc; }
        .auth-body input:focus, .auth-body select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(59,130,246,0.14); }
        .auth-body .btn-primary { width: 100%; background: #2563eb; color: white; border: none; padding: 16px 18px; border-radius: 14px; font-size: 1rem; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .auth-body .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 16px 30px rgba(37,99,235,0.18); }
        .divider { display: flex; align-items: center; gap: 16px; margin: 16px 0 6px; color: #64748b; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .google-btn { display: inline-flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 14px 16px; border-radius: 14px; border: 1px solid #cbd5e1; background: white; color: #0f172a; text-decoration: none; font-weight: 700; transition: background 0.2s ease; }
        .google-btn:hover { background: #f8fafc; }
        .message { padding: 16px; border-radius: 16px; font-size: 0.95rem; }
        .message.success { background: #dcfce7; color: #166534; }
        .message.error { background: #fee2e2; color: #991b1b; }
        .message.info { background: #e0f2fe; color: #0c4a6e; }
        .auth-footer { margin-top: 18px; text-align: center; color: #475569; }
        .auth-footer a { color: #2563eb; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-hero">
                <h1>Join SOTMS PRO</h1>
                <p>Create a secure account for students, tutors, or admins and access the tutoring dashboard instantly.</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <div>
                        <label for="name">Full Name</label>
                        <input id="name" name="name" type="text" autocomplete="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                    </div>
                    <div>
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" autocomplete="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                    </div>
                    <div>
                        <label for="role">Account Type</label>
                        <select id="role" name="role">
                            <option value="student" <?php echo (isset($role) && $role === 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="tutor" <?php echo (isset($role) && $role === 'tutor') ? 'selected' : ''; ?>>Tutor</option>
                            <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div>
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" autocomplete="new-password" required oninput="updatePasswordStrength()">
                        <small style="color:#475569; display:block; margin-top:6px;">Use 8+ characters with uppercase, lowercase, and a number.</small>
                        <div id="strengthText" style="margin-top:8px; color:#475569; font-size:0.95rem;">Password strength: </div>
                    </div>
                    <div>
                        <label for="confirm_password">Confirm Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
                    </div>
                    <button class="auth-body btn-primary" type="submit">Register</button>
                </form>

                <div class="divider">or</div>

                <a class="google-btn" href="google_fallback.php?source=register">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google icon" width="20" height="20">
                    Continue with Google
                </a>

                <div class="auth-footer">
                    Already registered? <a href="login.php">Login here</a>
                </div>
            </div>
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
