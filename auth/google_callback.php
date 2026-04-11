<?php
// SOTMS_PRO/auth/google_callback.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google_config.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

startAppSession();

if (isset($_GET['mock']) || empty($googleAuthAvailable)) {
    $source = $_SESSION['google_source'] ?? 'login';
    header('Location: google_fallback.php?source=' . $source);
    exit();
}

if (isset($_GET['code']) && !empty($googleAuthAvailable)) {
    try {
        $client->authenticate($_GET['code']);
    } catch (Exception $e) {
        error_log('Google OAuth error: ' . $e->getMessage());
        header('Location: google_fallback.php?source=' . ($_SESSION['google_source'] ?? 'login'));
        exit();
    }

    $accessToken = $client->getAccessToken();
    if ($accessToken) {
        $client->setAccessToken($accessToken);
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $email = filter_var($google_account_info->email, FILTER_SANITIZE_EMAIL);
        $name = htmlspecialchars($google_account_info->name);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['google_oauth_email'] = $email;
            $_SESSION['google_oauth_name'] = $name;
            header('Location: google_complete.php');
            exit();
        }

        try {
            issueUserSession($pdo, $user);
            redirectForRole($user['role']);
        } catch (PDOException $e) {
            error_log('Google callback session creation error: ' . $e->getMessage());
            header('Location: login.php?google_error=1');
            exit();
        }
    }

    echo 'Google Authentication Failed.';
    exit();
}

header('Location: login.php');
exit();
?>
