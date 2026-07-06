<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/send_mail.php';

if (!isset($_SESSION['reset_user'])) {
    echo "<script>alert('Session expired. Please try again.'); window.location.href='login.php';</script>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($new) || empty($confirm)) {
        echo "<script>alert('Please enter both new password and confirmation.'); window.history.back();</script>";
        exit;
    }

    if ($new !== $confirm) {
        echo "<script>alert('Passwords do not match'); window.history.back();</script>";
        exit;
    }

    // password rules
    if (strlen($new) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).{8,}$/', $new)) {
        echo "<script>alert('Password must be at least 8 characters with uppercase, lowercase, number, and special character.'); window.history.back();</script>";
        exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $email = $_SESSION['reset_user'];

    $stmt = $conn->prepare(
        "UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?"
    );
    $stmt->bind_param("ss", $hash, $email);
    
    if ($stmt->execute()) {
        //send mail
        $reset_time=date('F j, Y, g:i:s A'); //set the format 
        $subject="Your password was reset";
        $body="<p>This email is to confirm that your HomeNest account password was changed at {$reset_time}. </p>
        <p>If you have not recently changed your password or believe you have been sent this message in error, please contact our support team immediately.</p>
        <p>Thank you.</p>
        <p>HomeNest Team</p>";
        $mail_result=sendOrderEmail($email, $subject, $body);
        if($mail_result !== true){
            error_log("Password reset confirmation email failed for order #{$email}: " . $mail_result);
        }

        session_destroy();
        echo "<script>
            alert('Password updated successfully! You can now login with your new password.');
            window.location.href='login.php';
        </script>";
        exit;
    } else {
        echo "<script>alert('Error updating password. Please try again.'); window.history.back();</script>";
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - HomeNest</title>
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/forgot_password.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <section class="forgot-page">
        <div class="forgot-container">
            <div class="form-header">
                <h1>Reset Password</h1>
                <p>Set your new password</p>
            </div>
            
            <form action="reset_password.php" method="POST" class="login-form" id="resetForm">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                    <small class="error-message" id="passwordError"></small>
                        <div class="password-hint">
                        Password must be at least 8 characters with:
                        <ul style="margin: 5px 0 0 20px;">
                            <li>One uppercase letter</li>
                            <li>One lowercase letter</li>
                            <li>One number</li>
                            <li>One special character (@$!%*#?&)</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    <small class="error-message" id="confirmPasswordError"></small>
                </div>

                <button type="submit" id="submitBtn" class="btn-login">Update Password</button>
                
                <div class="auth-link">
                    <p><a href="login.php">Back to Login</a></p>
                </div>
            </form>
        </div>
    </section>

    <?php include_once 'includes/footer.php'; ?>
    <script src="js/reset_password.js"></script>
</body>
</html>
