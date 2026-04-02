<?php
session_start();
require_once '../db.php';

// Get session token from cookie
$session_token = $_COOKIE['session_token'] ?? null;

// Check if logout current role only or all sessions
$logout_all = !isset($_GET['current_role_only']);

if ($session_token) {
    try {
        if ($logout_all) {
            // Logout from all sessions for this user
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$session_token]);
        } else {
            // Logout from current role only - get user_id first
            $stmt = $pdo->prepare("SELECT user_id FROM user_sessions WHERE session_token = ? LIMIT 1");
            $stmt->execute([$session_token]);
            $session = $stmt->fetch();
            
            if ($session) {
                $user_id = $session['user_id'];
                $current_role = $_SESSION['role'] ?? null;
                
                // Delete this specific role session
                $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
                $stmt->execute([$session_token]);
                
                // Check if user has other sessions
                $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE user_id = ? AND expires_at > NOW() LIMIT 1");
                $stmt->execute([$user_id]);
                $other_session = $stmt->fetch();
                
                if ($other_session) {
                    // Redirect to session switcher
                    setcookie('session_token', '', time() - 3600, '/');
                    session_unset();
                    session_destroy();
                    header("Location: ../../auth/my_sessions.php");
                    exit();
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Logout error: ' . $e->getMessage());
    }
}

// Clear session cookie
setcookie('session_token', '', time() - 3600, '/');
session_unset();
session_destroy();

header("Location: ../../index.php");
exit();
?>