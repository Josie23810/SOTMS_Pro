<?php
require_once '../includes/auth_check.php';
checkAccess(['tutor']);

header('Location: schedule.php');
exit();
?>
