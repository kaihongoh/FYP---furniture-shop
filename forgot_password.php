<?php
session_start();
require_once 'includes/config.php';

// handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $answer = trim($_POST['security_answer'] ?? '');

    if (empty($email)) {
        echo "<script>alert('Please enter your email address.'); window.history.back();</script>";
        exit;
    }

    if (empty($answer)) {
        echo "<script>alert('Please enter your security answer.'); window.history.back();</script>";
        exit;
    }
    
    // verify the security answer
    $stmt = $conn->prepare(
        "SELECT security_question, security_answer FROM users WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify(strtolower($answer), $row['security_answer'])) {
            $_SESSION['reset_user'] = $email;
            header("Location: reset_password.php");
            exit;
        } else {
            echo "<script>alert('Wrong security answer. Please try again.'); window.history.back();</script>";
            $stmt->close();
            $conn->close();
            exit;
        }
    } else {
        echo "<script>alert('Email not found. Please check and try again.'); window.history.back();</script>";
        $stmt->close();
        $conn->close();
        exit;
    }
}

// check the email
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$security_question = '';

if (!empty($email)) {
    // if got email，check databse and get security question
    $stmt = $conn->prepare(
        "SELECT security_question FROM users WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $security_question = $row['security_question'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Ikea4U</title>
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/forgot_password.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <section class="forgot-page">
        <div class="forgot-container">
            <div class="auth-form-container">
                <h1>Forgot Password</h1>
                <p>Please enter your email and security answer</p>
            </div>
            
            <form action="forgot_password.php" method="POST" class="login-form" id="forgotForm">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($email); ?>" required
                           placeholder="Enter your registered email">
                </div>
                
                <div class="form-group">
                    <label>Your Security Question</label>
                    <input type="text" id="security_question" name="security_question" class="form-control" 
                           value="<?php echo htmlspecialchars($security_question); ?>" 
                           readonly
                           placeholder="Enter email above to see security question">
                </div>
                
                <div class="form-group">
                    <label>Security Answer</label>
                    <input type="text" id="security_answer" name="security_answer" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>" 
                           required
                           placeholder="Enter your security answer">
                </div>

                <button type="submit" class="btn-login">Verify</button>
                
                <div class="auth-link">
                    <p>Remember your password? <a href="login.php">Login here</a></p>
                    <p>Don't have an account? <a href="register.php">Register now</a></p>
                </div>
            </form>
        </div>
    </section>

    <?php include_once 'includes/footer.php'; ?>

    <script src="js/forgot_password.js"></script>
</body>
</html>