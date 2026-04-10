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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            background:
                radial-gradient(circle at 10% 10%, rgba(236,72,153,0.22) 0%, rgba(236,72,153,0) 35%),
                radial-gradient(circle at 90% 10%, rgba(139,92,246,0.22) 0%, rgba(139,92,246,0) 35%),
                linear-gradient(135deg, #fff1f2 0%, #fdf4ff 45%, #ecfeff 100%);
            font-family: 'Inter', sans-serif;
            color: #1f2937;
        }
        .compact-shell { width: min(760px, 96vw); border-radius: 16px; overflow: hidden; box-shadow: 0 22px 55px rgba(219,39,119,.14); border: 1px solid rgba(236,72,153,.2); background: rgba(255,255,255,.98); }
        .compact-hero { padding: 14px 16px; background: linear-gradient(135deg, #7c3aed, #ec4899, #f59e0b); color: #fff; }
        .compact-hero h1 { margin: 0; font-size: 1.2rem; font-family: 'Poppins', sans-serif; }
        .compact-hero p { margin: 4px 0 0; font-size: 0.82rem; color: rgba(255,255,255,.92); }
        .compact-content { padding: 12px 14px; background: linear-gradient(180deg, #fff, #fff7fb); }
        .form-group label { display: block; font-size: .82rem; margin-bottom: 4px; font-weight: 600; color: #334155; }
        input { width: 100%; box-sizing: border-box; padding: 7px 9px; font-size: .84rem; border-radius: 8px; border: 1px solid #fbcfe8; background: #fffdf8; }
        input:focus { outline: none; border-color: #f472b6; box-shadow: 0 0 0 4px rgba(244,114,182,.24); background: #fff; }
        .btn-primary { width: 100%; border: none; border-radius: 8px; padding: 8px 12px; font-size: .84rem; line-height: 1.2; background: linear-gradient(135deg, #ec4899, #8b5cf6, #f59e0b); color: #fff; font-weight: 700; cursor: pointer; }
        .message { padding: 10px 12px; border-radius: 10px; margin-bottom: 10px; font-size: 0.84rem; }
        .message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .card-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 8px; color: #475569; font-size: .8rem; }
        .card-footer a { color: #7c2d12; text-decoration: none; font-weight: 700; background: #ffedd5; padding: 4px 8px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="compact-shell">
        <div class="compact-hero">
            <h1>Forgot Password</h1>
            <p>Enter your email to generate a reset code.</p>
        </div>
        <div class="compact-content">
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php" class="compact-form">
            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required>
            </div>
            <button type="submit" class="btn-primary">Send reset code</button>
        </form>

        <div class="card-footer">
            <span>Remembered your password?</span>
            <a href="login.php">Back to login</a>
        </div>
        </div>
    </div>
</body>
</html>
