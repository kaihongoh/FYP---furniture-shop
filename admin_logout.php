<?php
session_start();

// Destroy admin sessions
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_logged_in']);
unset($_SESSION['is_superadmin']);
unset($_SESSION['permission_level']);

// Clear all session data
$_SESSION = array();

// Destroy the session completely
session_destroy();

// Redirect to admin login page
header("Location: admin_login.php");
exit();
?>