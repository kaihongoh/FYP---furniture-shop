<?php
session_start(); //start session to store and access login data
require_once 'includes/config.php';
//handle form submission and when request method is POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    //get and sanitize input values
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    //email cannot be empty
    if (empty($email)) {
        echo "<script>alert('Please enter your email.'); window.history.back();</script>";
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('The email format is invalid.'); window.history.back();</script>";
    exit;
    }
    //password cannnot be empty
    if (empty($password)) {
        echo "<script>alert('Please enter your password.'); window.history.back();</script>";
        exit;
    }
    //only allow user with role is customer
    $stmt = $conn->prepare(
        "SELECT User_ID, password, User_Name FROM users WHERE email = ? AND role = 'customer'"
    );
    //bind user input to prepare statement
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    //check if exactly one user record is found
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        //verify entered password with hashed password in database
        if (password_verify($password, $user['password'])) {
            //store user data in session once user success login
            $_SESSION['user_id'] = $user['User_ID'];
            $_SESSION['user_name'] = $user['User_Name'];
            $_SESSION['login_success'] = "Welcome back, " . htmlspecialchars($user['User_Name']) . "!";
            
            header("Location: home.php");
            exit;
        } else { //password do not match
            echo "<script>alert('Invalid password. Please try again.'); window.history.back();</script>";
            $stmt->close();
            $conn->close();
            exit;
        }
    } else { //email not found in database
        echo "<script>alert('Email not found. Please check and try again.'); window.history.back();</script>";
        $stmt->close();
        $conn->close();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/login.css">
    <title>Login - Ikea4U</title>
</head>

<body>
    <?php include_once 'includes/header.php'; ?>

    <section class="login-page">
        <div class="login-container">
            <div class="login-welcome">
                <h2>Welcome to Ikea4U</h2>
                <p>
                    Welcome to our online furniture shop. 
                    Browse categories, manage your account, and enjoy a smooth shopping experience with secure login and modern design.
                </p>
                <ul>
                    <li>Explore a wide selection of home furniture</li>
                    <li>Save favorite items for future purchase</li>
                    <li>Enjoy reliable delivery and quality service</li>
                </ul>
            </div>

            <div class="login-form-container">
                <div class="form-header">
                    <h1>Customer Login</h1>
                    <p>Sign in to access your account</p>
                </div>
                
                <form class="login-form" action="" method="POST">
                    <div class="form-group">
                        <label for="email" class="form-label">Email:</label>
                        <input type="text" id="email" name="email" class="form-control"
                               placeholder="Enter your email" required>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password:</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Enter your password" required>
                            <button type="button" id="eyeball">
                                <div class="eye"></div>
                            </button>
                            <div id="beam"></div>
                        </div>
                    </div>

                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>

                    <button type="submit" id="submit" class="btn-login">Sign in</button>

                    <div class="divider">
                        <span>or</span>
                    </div>

                    <div class="register-link">
                        Don't have an account? <a href="register.php">Register now</a>
                    </div>

                    <div class="register-link">
                        Login with Admin? <a href="admin_login.php">Login now</a>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include_once 'includes/footer.php'; ?>
    
    <script src="js/login.js"></script>
</body>
</html>