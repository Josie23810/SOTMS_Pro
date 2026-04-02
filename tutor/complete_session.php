<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['tutor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id = intval($_POST['session_id']);
    $tutorId = getTutorId($pdo, $_SESSION['user_id']);
    
    try {
        $stmt = $pdo->prepare('UPDATE sessions SET status = "completed" WHERE id = ? AND tutor_id = ? AND status = "confirmed"');
        $result = $stmt->execute([$session_id, $tutorId]);
        
        if ($result && $stmt->rowCount() > 0) {
            $_SESSION['success'] = 'Session marked as completed! Student will be notified to pay.';
            header('Location: my_sessions.php');
        } else {
            $_SESSION['error'] = 'Session not found or already completed.';
            header('Location: my_sessions.php');
        }
    } catch (PDOException $e) {
        error_log('Complete session error: ' . $e->getMessage());
        $_SESSION['error'] = 'Error updating session.';
        header('Location: my_sessions.php');
    }
    exit();
}
?>

