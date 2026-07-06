<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");
require_once(__DIR__ . "/../includes/send_mail.php");

$email='';

// handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo "<script>alert('Please enter your email address.'); window.history.back();</script>";
        exit;
    }elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)){
        echo "<script>alert('The email format is invalid.'); window.history.back();</script>";
        exit;
    } else {
        $check_email=$conn->prepare("SELECT Admin_ID FROM admins WHERE Email=? AND Status='active'");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result=$check_email->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            //random otp number
            $otp=sprintf("%06d", mt_rand(1, 999999));
            $expire=date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $update=$conn->prepare("UPDATE admins SET code=?, code_expire=? WHERE Admin_ID=?");
            $update->bind_param("ssi", $otp, $expire, $admin['Admin_ID']);
            $update->execute();
            $update->close();

            //send otp
            $subject="Reset password request";
            $body="<p>This email is to send you OTP to reset password. </p>
            <p><strong>$otp</strong></p>
            <p>This OTP is only valid for 5 minutes.</p>
            <p>If you do not request this, Please ignore this email.</p>
            <p>Thank you.</p>
            <p>HomeNest Team</p>";
            $mail_result=sendOrderEmail($email, $subject, $body);
            if($mail_result === true){
                $_SESSION['reset_admin_email']=$email;
                $_SESSION['reset_admin_expire']=strtotime($expire);
                $_SESSION['send_otp_message']="the otp has been send to this email. please check the email and get the otp number.";
                header("Location: admin_verify_otp.php");
                exit();
            } else {
                echo "<script>alert('Failed to send OTP mail.'); window.history.back();</script>";
            }
        } else {
            echo "<script>alert('No active admin account found with that email.'); window.history.back();</script>";
        }
        $check_email->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Forgot Password - HomeNest</title>
    <link rel="stylesheet" href="../css/template.css">
    <link rel="stylesheet" href="../css/forgot_password.css">
</head>
<body>
    
    <section class="forgot-page">
        <div class="forgot-container">
            <div class="auth-form-container">
                <h1>Admin Forgot Password</h1>
                <p>Please enter your email to receive OTP</p>
            </div>
            
            <form method="POST" class="login-form" id="forgotForm">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($email); ?>" required
                           placeholder="Enter your registered email">
                    <small class="error-message" id="emailError"></small>
                </div>
                

                <button type="submit" id="submitBtn" class="btn-login">Send OTP</button>
                
                <div class="auth-link">
                    <p>Remember your password? <a href="admin_login.php">Login here</a></p>
                </div>
            </form>
        </div>
    </section>

    <script>
document.addEventListener('DOMContentLoaded',function() {
    const emailInput=document.getElementById('email');
    const form=document.getElementById('forgotForm');
    const submitBtn=document.getElementById('submitBtn');

    const emailError=document.getElementById('emailError');

    if(!form) return;

    function showError(input, errorElement, message) {
        if (input) input.classList.add('error');
        if (errorElement) {
            errorElement.innerText = message;
            errorElement.style.display = 'block';
        }
    }
    //clear error message and remove error style
    function clearError(input, errorElement) {
        if (input) input.classList.remove('error');
        if (errorElement) {
            errorElement.innerText = '';
            errorElement.style.display = 'none';
        }
    }

        //email validation
    function validateEmail() {
        const email = emailInput.value.trim();
        if (!email) {
            showError(emailInput, emailError, 'Email is required.');
            return false;
        }
        
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) {
            showError(emailInput, emailError, 'Invalid email format.');
            return false;
        }
        
        clearError(emailInput, emailError);
        return true;
    }



    emailInput.addEventListener('input', () => clearError(emailInput, emailError));
    //validate input fields when user leaves the field (blur)
    emailInput.addEventListener('blur', validateEmail);

      // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateEmail()
            
        ];
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Verifying...';
            form.submit();
        }
        });
});


    </script>
</body>
</html>