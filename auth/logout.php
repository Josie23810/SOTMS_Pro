<?php
session_start();
session_unset();
session_destroy();

// Redirect back to the homepage in the root directory
header("Location: ../index.php");
exit();
?>