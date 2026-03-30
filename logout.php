<?php
session_start();

//save the user name
$username = $_SESSION['user_name'] ?? 'Guest';

//clear the data
$_SESSION = array();

//destroy the session
session_destroy();

//logout message
session_start();
$_SESSION['logout_message'] = "Goodbye, $username! You have been logged out.";

//go back to homepage
header("Location: home.php");
exit();
?>