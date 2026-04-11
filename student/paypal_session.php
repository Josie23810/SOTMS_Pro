<?php
require_once '../includes/auth_check.php';
checkAccess(['student']);

$sessionId = intval($_GET['id'] ?? $_GET['session_id'] ?? 0);
$target = 'pay_session.php';
if ($sessionId > 0) {
    $target .= '?id=' . $sessionId;
}

header('Location: ' . $target);
exit();
