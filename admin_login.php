<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? ''); 
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('The email format is invalid.'); window.history.back();</script>";
    exit;
    }
    //password cannnot be empty
    if (empty($password)) {
        echo "<script>alert('Please enter your password.'); window.history.back();</script>";
        exit;
    }
    //only allow user with role is admin/superadmin
    $check_admin = $conn->prepare("SELECT Admin_ID, Username, Password, Role, Status FROM admins WHERE Email = ? AND Status='active'");
    $check_admin->bind_param("s", $email);
    $check_admin->execute();
    $result = $check_admin->get_result();

    //check if exactly one user record is found
    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();

        //verify entered password with hashed password in database
        if (password_verify($password, $admin['Password'])) {
            if(strtolower($admin['Status'])!=='active'){
                echo "<script>alert('Your account is inactive.'); window.history.back();</script>";
                exit;
            }
            $_SESSION['admin_id'] = $admin['Admin_ID'];
            $_SESSION['admin_email'] = $admin['Email'];
            $_SESSION['admin_name'] = $admin['Username'];
            $_SESSION['admin_role'] = $admin['Role'];
            $_SESSION['admin_logged_in'] = true;
            
            // Update last login time
            $update = $conn->prepare("UPDATE admins SET Last_Login_At = NOW() WHERE Admin_ID = ?");
            $update->bind_param("s", $admin['Admin_ID']);
            $update->execute();
            $update->close();
            
            header("Location: admin_dashboard.php");
            exit;
        } else {
            echo "<script>alert('Invalid password. Please try again.'); window.history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('Email not found. Please check and try again.'); window.history.back();</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access - HomeNest</title>
    <link rel="stylesheet" href="../css/template.css">
    <link rel="stylesheet" href="../css/admin_login.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
    <section class="login-page">
        <div class="login-container">
            <div class="login-welcome admin-welcome">
                <div class="admin-badge">ADMIN PORTAL</div>
                <h2>Authorized Personnel Only</h2>
                <p>Welcome to the HomeNest backend management system. Access here is restricted to staff members only.</p>
                <ul class="admin-checklist">
                    <li><i class="bi bi-graph-up"></i> Manage Sales & Revenue</li>
                    <li><i class="bi bi-box-seam"></i> Inventory Control</li>
                    <li><i class="bi bi-people"></i> User Management</li>
                </ul>
            </div>

            <div class="login-form-container admin-form-container">
                <div class="form-header admin-form-header">
                    <h1>Admin Login</h1>
                    <p>Enter your staff credentials</p>
                </div>

                <form class="login-form admin-login-form" action="" method="POST" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">Admin Email:</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="admin@gmail.com" required>
                        <small class="error-message" id="emailError"></small>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password:</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
                        <small class="error-message" id="passwordError"></small>
                    </div>
                <div class="forgot-password">
                        <a href="admin_forgot_password.php">Forgot password?</a>
                </div>

                    <button type="submit" id="submitBtn" class="btn-login admin-btn-login">Verify Identity</button>
                </form>


                <div class="form-footer admin-form-footer">
                    <a href="../login.php">
                        <i class="bi bi-arrow-left"></i> Return to Customer Login
                    </a>
                </div>
            </div>
        </div>
    </section>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    //get main form element
    const form=document.getElementById('loginForm');
    const emailInput=document.getElementById('email');   
    const password=document.getElementById('password');
    const submitBtn=document.getElementById('submitBtn');

    // get all error message element
    const emailError=document.getElementById('emailError');
    const passwordError=document.getElementById('passwordError');


    //stop script execution if form does not exist
    if (!form) return;

    //display inline error message and apply error style
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
    //password validation
    function validatePassword() {
        const passwordValue = password.value;
        if (!passwordValue) {
            showError(password, passwordError, 'Password is required.');
            return false;
        }  
        clearError(password, passwordError);
        return true;
    }

   //clear error messages while user is typing or selecting
    emailInput.addEventListener('input', () => clearError(emailInput, emailError));
    password.addEventListener('input', () => clearError(password, passwordError));

    //validate input fields when user leaves the field (blur)
    emailInput.addEventListener('blur', validateEmail);
    password.addEventListener('blur', validatePassword);

    // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateEmail(),
            validatePassword()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Logging...';
            form.submit();
        }
    });
});
</script>
</body>
</html>