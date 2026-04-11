<?php
require_once '../../config/db.php';
require_once '../../includes/auth_helpers.php';

destroyUserSession($pdo);
header('Location: ../../index.php');
exit();
?>
