<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

    if (!$email) {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'No account found for that email address.';
        } else {
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_code'] = random_int(100000, 999999);
            $_SESSION['reset_code_generated'] = time();
            header('Location: reset_password.php?notice=1');
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)),
                        url('../uploads/image003.jpg') center/cover no-repeat;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }
        .card { width: min(540px, 100%); background: rgba(255,255,255,0.96); border-radius: 28px; box-shadow: 0 30px 80px rgba(15,23,42,0.16); padding: 36px; border: 1px solid rgba(148,163,184,0.24); }
        .card h1 { margin: 0 0 14px; font-size: 2.3rem; }
        .card p { margin: 0 0 24px; color: #475569; line-height: 1.7; }
        label { display: block; font-weight: 700; margin-bottom: 10px; color: #334155; }
        input { width: 100%; padding: 14px 16px; border: 1px solid #cbd5e1; border-radius: 14px; background: #f8fafc; font-size: 1rem; }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(59,130,246,0.14); }
        .btn-primary { width: 100%; border: none; border-radius: 14px; padding: 16px 18px; background: #2563eb; color: white; font-weight: 700; cursor: pointer; transition: background 0.2s ease; }
        .btn-primary:hover { background: #1d4ed8; }
        .message { padding: 16px; border-radius: 16px; margin-bottom: 18px; font-size: 0.95rem; }
        .message.error { background: #fee2e2; color: #991b1b; }
        .message.success { background: #dcfce7; color: #166534; }
        .card-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 20px; color: #475569; }
        .card-footer a { color: #2563eb; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Forgot Password</h1>
        <p>Enter the email address associated with your account. A reset code will be generated for you.</p>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" required>
            <button type="submit" class="btn-primary">Send reset code</button>
        </form>

        <div class="card-footer">
            <span>Remembered your password?</span>
            <a href="login.php">Back to login</a>
        </div>
    </div>
</body>
</html>
