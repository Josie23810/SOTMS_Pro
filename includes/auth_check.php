<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

/**
 * Function to restore user session from database
 * Supports multiple concurrent sessions across different browser tabs
 */
function restoreSessionFromDatabase() {
    global $pdo;
    
    // Check for session token in cookie
    if (!isset($_COOKIE['session_token'])) {
        return false;
    }
    
    $session_token = $_COOKIE['session_token'];
    
    try {
        // Fetch session from database
        $stmt = $pdo->prepare("SELECT us.user_id, us.role, u.name, u.email 
            FROM user_sessions us 
            JOIN users u ON us.user_id = u.id 
            WHERE us.session_token = ? 
            AND us.expires_at > NOW()");
        $stmt->execute([$session_token]);
        $session = $stmt->fetch();
        
        if ($session) {
            // Restore session variables
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['name'] = $session['name'];
            $_SESSION['role'] = $session['role'];
            $_SESSION['session_token'] = $session_token;
            
            // Update last activity
            $stmt = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_token = ?");
            $stmt->execute([$session_token]);
            
            return true;
        }
    } catch (PDOException $e) {
        error_log('Session restore error: ' . $e->getMessage());
    }
    
    return false;
}

/**
 * Function to restrict access based on roles
 * Supports multiple concurrent sessions across different browser tabs
 * @param array $allowed_roles List of roles permitted to view the page
 */
function checkAccess($allowed_roles) {
    // Try to restore session from database first
    if (!restoreSessionFromDatabase()) {
        // If no valid session token, check if logged in via PHP session
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../config/auth/login.php");
            exit();
        }
    }
    
    // Verify user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: ../config/auth/login.php");
        exit();
    }
    
    // Check if user's role is in the allowed list
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        die("Access Denied: You do not have permission to view this page.<br>" .
            "<a href=\"../config/auth/my_sessions.php\">View your active sessions</a>");
    }
}

/**
 * Get all active sessions for current user
 */
function getUserActiveSessions() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, role, session_token, ip_address, last_activity, created_at 
            FROM user_sessions 
            WHERE user_id = ? 
            AND expires_at > NOW()
            ORDER BY last_activity DESC");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get sessions error: ' . $e->getMessage());
        return [];
    }
}
?>