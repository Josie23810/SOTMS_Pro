<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

startAppSession();

if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_code'])) {
    header('Location: forgot_password.php');
    exit();
}

$error = '';
$success = '';
$displayCode = $_SESSION['reset_code'];
$email = $_SESSION['reset_email'];
$codeGeneratedAt = intval($_SESSION['reset_code_generated'] ?? 0);
$codeIsExpired = !$codeGeneratedAt || (time() - $codeGeneratedAt) > 900;

if ($codeIsExpired) {
    unset($_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_code_generated']);
    header('Location: forgot_password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($code !== (string)$_SESSION['reset_code']) {
        $error = 'The reset code does not match. Please try again.';
    } elseif (!passwordMeetsPolicy($password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
        $stmt->execute([$hashedPassword, $email]);

        unset($_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_code_generated']);
        header('Location: login.php?reset=1');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SOTMS PRO</title>
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
        .notice-box { background: #e0f2fe; border: 1px solid #bae6fd; color: #0c4a6e; padding: 10px 12px; border-radius: 10px; margin-bottom: 10px; font-size: .82rem; }
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
            <h1>Reset Your Password</h1>
            <p>Use the code below and choose a secure new password.</p>
        </div>
        <div class="compact-content">
        <div class="notice-box">
            <strong>Reset code:</strong> <?php echo htmlspecialchars($displayCode); ?><br>
            <strong>Account:</strong> <?php echo htmlspecialchars($email); ?>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="reset_password.php" class="compact-form">
            <div class="form-group">
                <label for="code">Reset code</label>
                <input id="code" name="code" type="text" autocomplete="one-time-code" required>
            </div>

            <div class="form-group">
                <label for="password">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn-primary">Set new password</button>
        </form>

        <div class="card-footer">
            <span>Resetting password for <?php echo htmlspecialchars($email); ?></span>
            <a href="login.php">Back to login</a>
        </div>
        </div>
    </div>
</body>
</html>
