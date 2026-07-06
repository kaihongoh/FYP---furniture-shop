<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if(!isset($_SESSION['reset_admin_email'])) {
    header("Location: admin_forgot_password.php");
    exit();
}

$errors=[];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp=trim($_POST['otp'] ?? '');
    if(empty($otp)){
        $errors[]="Please enter the OTP";
    } else {
        $email=$_SESSION['reset_admin_email'];
        $check_otp=$conn->prepare("SELECT Admin_ID FROM admins WHERE Email=? AND code=? AND code_expire >NOW()");
        $check_otp->bind_param("ss", $email, $otp);
        $check_otp->execute();
        $result=$check_otp->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            //once verify success, clear the otp number and reset password
            $clear=$conn->prepare("UPDATE admins SET code=NULL, code_expire=NULL WHERE Admin_ID=?");
            $clear->bind_param("i", $admin['Admin_ID']);
            $clear->execute();
            $clear->close();

            $_SESSION['reset_admin']=true;
            header("Location: admin_reset_password.php");
            exit();
        } else {
            $errors[]="Invalid or expired OTP. Please request a new OTP.";
        }
        $check_otp->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - HomeNest Admin</title>
        <link rel="stylesheet" href="../css/template.css">
    <link rel="stylesheet" href="../css/forgot_password.css">
</head>
<body>
      <section class="forgot-page">
        <div class="forgot-container">
            <div class="auth-form-container">
                <h1>Verify OTP</h1>
                <p>Please enter 6 digit code that haved send to your email</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['send_otp_message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['send_otp_message']); 
                unset($_SESSION['send_otp_message']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form" id="forgotForm">
            <div class="form-group">
                <label for="email">OTP Code:</label>
                <input type="number" id="otp" name="otp" class="form-control" 
                    placeholder="Enter 6 digit code" maxlength="6" required>
            </div>
                

            <button type="submit" class="btn-login">Verify</button>
            
            <div class="auth-link">
                <p><a href="admin_forgot_password.php">Request new OTP</a></p>
                <p><a href="admin_login.php">Back to login</a></p>
            </div>
        </form>
    </div>
</section>
<script>
    document.getElementById('otp').addEventListener('input', function(e){
        this.value=this.value.replace(/[^0-9]/g, '').slice(0,6);
        if(this.value.length===6){
            this.form.submit();
        }
    });
</script>
</body>
</html>