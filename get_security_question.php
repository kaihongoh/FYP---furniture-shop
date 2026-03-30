<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['email'])) {
    $email = trim($_GET['email']);
    
    // check email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "";
        exit;
    }
    
    // check database
    $stmt = $conn->prepare(
        "SELECT security_question FROM users WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        echo htmlspecialchars($row['security_question']);
    } else {
        echo ""; 
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo "";
}
?>