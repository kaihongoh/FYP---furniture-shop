<?php
require_once 'includes/config.php';
//form will not be cleared if validation fails (save previous enter info)
$user_name="";
$full_name="";
$email="";
$phoneNumber="";
$address="";
$state="";
$postcode="";
$security_question="";
$security_answer="";

//only process when post
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // get form data
    $user_name= trim($_POST['user_name'] ?? '');
    $full_name= trim($_POST['full_name'] ?? '');
    $email= trim($_POST['email'] ?? '');
    $password= $_POST['password'] ?? '';
    $confirm_password= $_POST['confirmPassword'] ?? '';
    $phoneNumber= trim($_POST['phoneNumber'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $address= trim($_POST['address'] ?? '');
    $security_question= $_POST['security_question'] ?? '';
    $security_answer= trim($_POST['security_answer'] ?? '');
    $terms = isset($_POST['terms']) ? true : false;

    // js will do frontend validation
    //backend validation
    $errors = [];

     if (empty($user_name)) 
        $errors[] = 'Username is required.';

    if (empty($full_name)) //这个要吗？
        $errors[] = 'Full name is required.';

    if (empty($email)) 
        $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
        $errors[] = 'The email format is invalid.';
    
    if (empty($password)) 
        $errors[] = 'Password is required.';
    elseif (strlen($password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).{8,}$/', $password)) {
        $errors[] = 'Please enter the password with at least 8 characters with uppercase, lowercase, number, and special character.';
    }
    
    if ($password !== $confirm_password) 
        $errors[] = 'Passwords do not match.';


    if (empty($phoneNumber)) 
        $errors[] = 'Please enter the phone number.';
     elseif (!preg_match('/^\d{3}-\d{3}-\d{4}$/', $phoneNumber)) 
    $errors[] = 'Phone number must be in format: 012-345-6789';
    

    if (empty($state)) 
        $errors[] = 'Please select the state.';

    if (empty($postcode)) 
        $errors[] = 'Please enter the postcode.';
    elseif (!preg_match('/^\d{5}$/', $postcode)) 
        $errors[] = 'Postcode must be 5 digits.';
    
    if (empty($address)) 
        $errors[] = 'Please enter the complete address.';
    elseif (strlen($address) < 10) 
        $errors[] = 'Address must be at least 10 characters.';
    
    if (empty($security_question)) 
        $errors[] = 'Please select the security question.';

    if (empty($security_answer)) 
        $errors[] = 'Please create the security answer.';

    if (!$terms) 
        $errors[] = 'You must accept the terms of service and privacy policy.';
    
    //if got error, will return
    if (!empty($errors)) {
        //alert
        echo 
        "<script>
            alert('" . implode('\\n', $errors) . "');
        </script>";
        return;
    } else {
    // check username is exists
    $usernameCheckStmt = $conn->prepare("SELECT User_ID FROM users WHERE User_Name = ?");
    $usernameCheckStmt->bind_param("s", $user_name);
    $usernameCheckStmt->execute();
    $usernameCheckStmt->store_result();

    if ($usernameCheckStmt->num_rows > 0) {
        echo "<script>
        alert('Username already taken. Please choose another username.'); 
        </script>";
        $user_name = "";
        $usernameCheckStmt->close();
    }else {
    $usernameCheckStmt->close();
    
        // check email address is exists
    $emailCheckStmt = $conn->prepare("SELECT User_ID FROM users WHERE email = ?");
    $emailCheckStmt->bind_param("s", $email);
    $emailCheckStmt->execute();
    $emailCheckStmt->store_result();

    if ($emailCheckStmt->num_rows > 0) {
        echo "<script>
        alert('Email already registered. Please use another email.'); 
        </script>";
        $email = "";
        $emailCheckStmt->close();  
    }else{
    $emailCheckStmt->close();

    // dont check phone number is exists, same phone number can use in different user and address
    /*$phoneCheckStmt = $conn->prepare(
        "SELECT User_ID FROM user_address WHERE Phone = ?"
    );
    $phoneCheckStmt->bind_param("s", $phoneNumber);
    $phoneCheckStmt->execute();
    $phoneCheckStmt->store_result();

    if ($phoneCheckStmt->num_rows > 0) {
        echo "<script>
        alert('Phone number already registered. Please use another phone number.');
        </script>";
        $phoneNumber="";
        $phoneCheckStmt->close();
    }else {
    $phoneCheckStmt->close();
*/
    // hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    //change the security answer to lower case then hash
    $hashedSecurityAnswer = password_hash(strtolower($security_answer), PASSWORD_DEFAULT);
    $role = 'customer';
    
    $conn->begin_transaction();
    try{
    $insertUser = $conn->prepare(
        "INSERT INTO users (User_Name, email, password, security_question, security_answer, role, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $insertUser->bind_param(
        "ssssss",
        $user_name,
        $email,
        $hashedPassword,
        $security_question,
        $hashedSecurityAnswer,
        $role
    );
    $insertUser->execute();
    $user_id=$conn->insert_id;
    $insertUser->close();

        $insertAddress= $conn->prepare("INSERT INTO user_address 
        (User_ID, Full_Name, Phone, State, 
        postcode, Unit_No, Address, 
        Label, Is_Default, Created_At)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $unit_no="";
        $label="Home";
        $is_default=1;

        $insertAddress->bind_param(
        "isssssssi",
        $user_id, 
        $full_name, 
        $phoneNumber, 
        $state, 
        $postcode, 
        $unit_no, 
        $address, 
        $label, 
        $is_default
        );
        $insertAddress->execute();
        $insertAddress->close();

        $conn->commit();

        echo "<script>
            alert('Registration successful! You can now log in.');
            window.location.href = 'login.php';
        </script>";
        exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Registration error: " . $e->getMessage());
            echo "<script>
            alert('Registration failed. Please try again.');
            </script>";
        }

    }
}
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Ikea4U</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/register.css">
</head>

<body>
    <?php include_once 'includes/header.php'; ?>

    <section class="register-page">
        <div class="register-container">
            <div class="form-header">
                <h2 class="register-title">Create Your Account</h2>
                <p class="form-subtitle">Join Ikea4U and enjoy quality furniture delivered to your door</p>
            </div>

            <form method="POST" action="" class="register-form" id="registerForm" novalidate>
                <div class="form-group">
                    <label class="form-label">Username </label>
                    <input type="text" name="user_name" class="form-control" 
                           value="<?= htmlspecialchars($user_name) ?>" 
                           placeholder="Enter your username" required>
                    <small class="error-message" id="userNameError"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Full Name </label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?= htmlspecialchars($full_name) ?>" 
                           placeholder="Enter your full name" required>
                    <small class="error-message" id="fullNameError"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address </label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($email) ?>" 
                           placeholder="example@email.com" required>
                    <small class="error-message" id="emailError"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Password </label>
                    <input type="password" name="password" id="password" class="form-control"
                            value="" 
                            placeholder="Enter your password" required>
                    <small class="password-hint">Must be at least 8 characters with uppercase, lowercase, number, and special character</small>
                    <small class="error-message" id="passwordError"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password </label>
                    <input type="password" name="confirmPassword" id="confirmPassword" class="form-control" 
                           placeholder="Confirm your password" required>
                    <small class="error-message" id="confirmPasswordError"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number </label>
                    <input type="tel" name="phoneNumber" class="form-control"
                           value="<?= htmlspecialchars($phoneNumber) ?>" 
                           pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" 
                           placeholder="123-456-7890" required>
                    <small class="password-hint">Format: 012-345-6789</small>
                    <small class="error-message" id="phoneError"></small>
                </div>

                
                <div class="form-group">
                    <label class="form-label">State </label>
                    <select name="state" id ="stateSelect" class="form-control" required>
                        <option value="">Select your state</option>
                        <option value="Johor"<?=$state=='Johor'?'selected':''?>>Johor</option>
                        <option value="Kedah"<?=$state=='Kedah'?'selected':''?>>Kedah</option>
                        <option value="Kelantan"<?=$state=='Kelantan'?'selected':''?>>Kelantan</option>
                        <option value="Malacca"<?=$state=='Malacca'?'selected':''?>>Malacca</option>
                        <option value="Negeri Sembilan"<?=$state=='Negeri Sembilan'?'selected':''?>>Negeri Sembilan</option>
                        <option value="Pahang"<?=$state=='Pahang'?'selected':''?>>Pahang</option>
                        <option value="Perak"<?=$state=='Perak'?'selected':''?>>Perak</option>
                        <option value="Perlis"<?=$state=='Perlis'?'selected':''?>>Perlis</option>
                        <option value="Penang"<?=$state=='Penang'?'selected':''?>>Penang</option>
                        <option value="Sabah"<?=$state=='Sabah'?'selected':''?>>Sabah</option>
                        <option value="Sarawak"<?=$state=='Sarawak'?'selected':''?>>Sarawak</option>
                        <option value="Selangor"<?=$state=='Selangor'?'selected':''?>>Selangor</option>
                        <option value="Terengganu"<?=$state=='Terengganu'?'selected':''?>>Terengganu</option>
                        <option value="Wilayah Persekutuan Kuala Lumpur"<?=$state=='Wilayah Persekutuan Kuala Lumpur'?'selected':''?>>Wilayah Persekutuan Kuala Lumpur</option>
                        <option value="Wilayah Persekutuan Putrajaya"<?=$state=='Wilayah Persekutuan Putrajaya'?'selected':''?>>Wilayah Persekutuan Putrajaya</option>
                        <option value="Wilayah Persekutuan Labuan"<?=$state=='Wilayah Persekutuan Labuan'?'selected':''?>>Wilayah Persekutuan Labuan</option>
                    </select>
                    <small class="error-message" id="stateError"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Postcode</label>
                    <input type="text" name="postcode" id="postcode" maxlength="5" inputmode="numeric" class="form-control"
                        value="<?=htmlspecialchars($postcode) ?>"
                        pattern="[0-9]{5}"
                        placeholder="75000" required>
                    <small class="error-message" id="postcodeError"></small>
                </div>
                
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3" 
                              placeholder="Enter your full address" required><?= htmlspecialchars($address) ?></textarea>
                    <small class="password-hint">Please provide complete address for delivery</small>
                    <small class="error-message" id="addressError"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Security Question</label>
                    <select name="security_question" class="form-control" required>
                        <option value="">Select a security question</option>
                        <option value="What is your favorite movie?" <?= $security_question == "What is your favorite movie?" ? 'selected' : '' ?>>What is your favorite movie?</option>
                        <option value="What is your mother maiden name?" <?= $security_question == "What is your mother maiden name?" ? 'selected' : '' ?>>What is your mother maiden name?</option>
                        <option value="What is your favorite color?" <?= $security_question == "What is your favorite color?" ? 'selected' : '' ?>>What is your favorite color?</option>
                    </select>
                    <small class="error-message" id="securityQuestionError"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Security Answer</label>
                    <input type="text" name="security_answer" class="form-control"
                           value="<?= htmlspecialchars($security_answer) ?>" 
                           placeholder="Your answer" required>
                    <small class="error-message" id="securityAnswerError"></small>
                </div>

                <div class="terms">
                    <label>
                        <input type="checkbox" name="terms" value="1" <?= isset($_POST['terms']) ? 'checked' : '' ?> required>
                        I accept the 
                        <a href="#" target="_blank">Terms of Service</a> & 
                        <a href="#" target="_blank">Privacy Policy</a>
                    </label>
                    <small class="error-message" id="termsError"></small>
                </div>

                <button type="submit" class="btn-register" id="submitBtn">Create Account</button>
            </form>

            <div class="auth-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </section>

    <?php include_once 'includes/footer.php'; ?>
    
    <script src="js/register_validation.js"></script>
</body>
</html>
