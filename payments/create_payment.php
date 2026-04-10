<?php
require_once '../includes/auth_check.php';
checkAccess(['student']);

$sessionId = intval($_GET['session_id'] ?? $_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: ../student/schedule.php');
    exit();
}

header('Location: ../student/pay_session.php?id=' . $sessionId);
exit();
?>
