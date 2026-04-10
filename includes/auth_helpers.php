<?php
require_once __DIR__ . '/../config/db.php';

function startAppSession() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    session_start();
}

function userSessionTableReady(PDO $pdo) {
    if (!function_exists('tableExists')) {
        require_once __DIR__ . '/user_helpers.php';
    }

    if (!tableExists($pdo, 'user_sessions')) {
        return false;
    }

    $requiredColumns = ['user_id', 'role', 'session_token', 'browser_fingerprint', 'ip_address', 'expires_at'];
    foreach ($requiredColumns as $column) {
        if (!columnExists($pdo, 'user_sessions', $column)) {
            return false;
        }
    }

    return true;
}

function ensureSessionStorageReady(PDO $pdo) {
    if (!userSessionTableReady($pdo)) {
        http_response_code(500);
        die('Session storage is not ready. Run run_phase1_migration.php before using authentication.');
    }
}

function allowedPublicRoles() {
    return ['student', 'tutor'];
}

function isValidPublicRole($role) {
    return in_array($role, allowedPublicRoles(), true);
}

function passwordMeetsPolicy($password) {
    return (bool) preg_match('/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/', (string) $password);
}

function currentBrowserFingerprint() {
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
}

function issueUserSession(PDO $pdo, array $user) {
    ensureSessionStorageReady($pdo);
    startAppSession();

    session_regenerate_id(true);

    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

    $stmt = $pdo->prepare('
        INSERT INTO user_sessions (user_id, role, session_token, browser_fingerprint, ip_address, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $user['id'],
        $user['role'],
        $sessionToken,
        currentBrowserFingerprint(),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $expiresAt
    ]);

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('session_token', $sessionToken, [
        'expires' => time() + (30 * 24 * 60 * 60),
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['session_token'] = $sessionToken;

    return $sessionToken;
}

function restoreUserSession(PDO $pdo) {
    ensureSessionStorageReady($pdo);
    startAppSession();

    $sessionToken = $_COOKIE['session_token'] ?? null;
    if (!$sessionToken) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('
            SELECT us.user_id, us.role, us.browser_fingerprint, u.name, u.email
            FROM user_sessions us
            JOIN users u ON us.user_id = u.id
            WHERE us.session_token = ?
              AND us.expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([$sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return false;
        }

        if (!empty($session['browser_fingerprint']) && !hash_equals($session['browser_fingerprint'], currentBrowserFingerprint())) {
            $stmt = $pdo->prepare('DELETE FROM user_sessions WHERE session_token = ?');
            $stmt->execute([$sessionToken]);
            return false;
        }

        $_SESSION['user_id'] = $session['user_id'];
        $_SESSION['name'] = $session['name'];
        $_SESSION['role'] = $session['role'];
        $_SESSION['session_token'] = $sessionToken;

        $stmt = $pdo->prepare('UPDATE user_sessions SET last_activity = NOW() WHERE session_token = ?');
        $stmt->execute([$sessionToken]);

        return true;
    } catch (PDOException $e) {
        error_log('Session restore error: ' . $e->getMessage());
        return false;
    }
}

function destroyUserSession(PDO $pdo, $token = null) {
    ensureSessionStorageReady($pdo);
    startAppSession();

    $sessionToken = $token ?: ($_COOKIE['session_token'] ?? $_SESSION['session_token'] ?? null);
    if ($sessionToken) {
        try {
            $stmt = $pdo->prepare('DELETE FROM user_sessions WHERE session_token = ?');
            $stmt->execute([$sessionToken]);
        } catch (PDOException $e) {
            error_log('Session delete error: ' . $e->getMessage());
        }
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('session_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_unset();
    session_destroy();
}

function redirectForRole($role) {
    if ($role === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($role === 'tutor') {
        header('Location: ../tutor/dashboard.php');
    } else {
        header('Location: ../student/dashboard.php');
    }
    exit();
}
?>
