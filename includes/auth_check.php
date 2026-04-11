<?php
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';

startAppSession();

function checkAccess($allowed_roles) {
    global $pdo;

    if (!restoreUserSession($pdo)) {
        if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
            header('Location: ../auth/login.php');
            exit();
        }
    }

    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: ../auth/login.php');
        exit();
    }

    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        http_response_code(403);
        die('Access denied. You do not have permission to view this page.');
    }
}

function getUserActiveSessions() {
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        return [];
    }

    ensureSessionStorageReady($pdo);

    try {
        $stmt = $pdo->prepare('
            SELECT id, role, session_token, ip_address, last_activity, created_at
            FROM user_sessions
            WHERE user_id = ?
              AND expires_at > NOW()
            ORDER BY last_activity DESC
        ');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get sessions error: ' . $e->getMessage());
        return [];
    }
}
?>
