<?php
// Initiate Google OAuth flow using the configured Google client.
require_once __DIR__ . '/../includes/auth_helpers.php';
startAppSession();
require_once __DIR__ . '/../config/google_config.php';

$redirectTarget = 'login.php';
if (isset($_GET['source']) && $_GET['source'] === 'register') {
    $redirectTarget = 'register.php';
}

$_SESSION['google_source'] = isset($_GET['source']) ? $_GET['source'] : 'login';

if (!empty($googleAuthAvailable) && isset($client) && $client instanceof Google_Client) {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
}

$source = isset($_GET['source']) && $_GET['source'] === 'register' ? 'register' : 'login';
header('Location: google_fallback.php?source=' . $source);
exit();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Login - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; font-family: 'Poppins', sans-serif; background: #eff6ff; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #0f172a; }
        .google-card { max-width: 520px; width: 100%; background: white; border-radius: 24px; box-shadow: 0 24px 50px rgba(15,23,42,0.12); padding: 36px; text-align: center; }
        .google-card h1 { margin: 0 0 18px; font-size: 2rem; }
        .google-card p { color: #475569; line-height: 1.75; margin-bottom: 24px; }
        .google-card a, .retry-btn { display: inline-block; background: #2563eb; color: white; padding: 14px 24px; border-radius: 999px; text-decoration: none; font-weight: 600; margin-top: 12px; }
        .google-card a:hover, .retry-btn:hover { background: #1d4ed8; }
        .message { background: #fee2e2; color: #991b1b; padding: 14px 16px; border-radius: 12px; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="google-card">
        <h1>Google Login</h1>
        <?php if (!empty($error)): ?>
            <div class="message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <p>If Google login is configured correctly, you should be redirected automatically. Otherwise, return to the login page.</p>
        <a href="login.php">Back to Login</a>
    </div>
</body>
</html>
