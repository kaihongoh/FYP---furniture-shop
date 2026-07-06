<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: admin_dashboard.php");
    exit;
}

   $errors =[];

// --- CREATE NEW ADMIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['inactive_admin'])) {
            $admin_id=intval($_POST['admin_id']);

            if($admin_id==$_SESSION['admin_id']){
                $_SESSION['message'] = 'self_deactive';
                header("Location: manage_admin.php");
                exit();
            }
        // update admin status to inactive 
        $inactive = $conn->prepare("UPDATE admins SET status = 'inactive' WHERE Admin_ID = ?");
        $inactive->bind_param("i", $admin_id);
        if ($inactive->execute()) { //store success message in session
            $_SESSION['message'] = 'deactivated';
        } else { //store error message in session
            $_SESSION['message'] = 'error';
        }
        $inactive->close();
        header("Location: manage_admin.php");
        exit();
    } elseif (isset($_POST['active_admin'])) {
        $admin_id=intval($_POST['admin_id']);
        //update user status to active
        $active = $conn->prepare("UPDATE admins SET status = 'active' WHERE Admin_ID = ?");
        $active->bind_param("i", $admin_id);
        if ($active->execute()) {
            $_SESSION['message'] = 'activated';
        } else {
            $_SESSION['message'] = 'error';
        }
        $active->close();
        header("Location: manage_admin.php");
        exit();
    }
}   

    if(isset($_POST['add_admin'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password= $_POST['confirmPassword'] ?? '';
        $role = $_POST['role'] ?? 'admin';

        $errors =[];

        // Validation
        if (empty($username)) {
            $errors[]="Username is required";
        } else {
            // Check if username already exists
            $check_name = $conn->prepare("SELECT Admin_ID FROM admins WHERE Username = ?");
            $check_name->bind_param("s", $username);
            $check_name->execute();
            $check_name->store_result();
            
            if ($check_name->num_rows > 0) {
                $errors[]="Username already exists.";
            } 
            $check_name->close();
        }

        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The email format is invalid.';
        } else {
            // check email address is exists
            $CheckEmail = $conn->prepare("SELECT Admin_ID FROM admins WHERE Email = ?");
            $CheckEmail->bind_param("s", $email);
            $CheckEmail->execute();
            $CheckEmail->store_result();

            if ($CheckEmail->num_rows > 0) {
                $errors[]="Email already registered. Please use another email.";
                $CheckEmail->close();  
            }else{
                $CheckEmail->close();
            }
        }


        if(empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).{8,}$/', $password)) {
            $errors[] = 'Please enter the password with at least 8 characters with uppercase, lowercase, number, and special character.';
        }elseif ($password !== $confirm_password){
            $errors[] = 'Passwords do not match.';
        }


        if (empty($role)) {
            $errors[] = 'Please select the role.';
        }

        if(empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);   
            // Insert new admin
            $add_admin = $conn->prepare(
                "INSERT INTO admins (Username, Email, Password, Role, Status, Created_At) 
                VALUES ( ?, ?, ?, ?, 'active', NOW())");
            $add_admin->bind_param("ssss", $username, $email, $hashedPassword, $role);
                
            if ($add_admin->execute()) {
                $_SESSION['success'] = "added successfully";   
                header("Location: manage_admin.php");
                exit();
            } else {
                $errors[] = "Failed to create account. Please try again.";
            }
            $add_admin->close();
            }
        }

//get data for edit use
$edit_username='';
$edit_email='';
$edit_password='';
$edit_role=''; 

if(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['admin_id'])) {
    $admin_id=intval($_GET['admin_id']);

    //if click save button 
    if(isset($_POST['update_admin'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password= $_POST['confirmPassword'] ?? '';
        $role = $_POST['role'] ?? 'admin';

        $errors =[];

        if(empty($username)){
            $errors[]="CUsername is required.";
        } else {
            // check the username already exists
            $check_name = $conn->prepare("SELECT Admin_ID FROM admins WHERE Username = ? AND Admin_ID!=?");
            $check_name->bind_param("si", $username, $admin_id);
            $check_name->execute();
            $check_name->store_result();
        
            if ($check_name->num_rows > 0) {
                $errors[] = "The username already exists. Please create with different name.";
            }
            $check_name->close();
        }

        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The email format is invalid.';
        } else {
            // check email address is exists
            $CheckEmail = $conn->prepare("SELECT Admin_ID FROM admins WHERE Email = ? AND Admin_ID!=?");
            $CheckEmail->bind_param("si", $email, $admin_id);
            $CheckEmail->execute();
            $CheckEmail->store_result();

            if ($CheckEmail->num_rows > 0) {
                $errors[]="Email already registered. Please use another email.";
                $CheckEmail->close();  
            }else{
                $CheckEmail->close();
            }
        }

        $current_admin_role=$conn->prepare("SELECT Role FROM admins WHERE Admin_ID=?");
        $current_admin_role->bind_param("i", $admin_id);
        $current_admin_role->execute();
        $current_role=$current_admin_role->get_result()->fetch_assoc()['Role'];
        $current_admin_role->close();

        if($current_role==='super_admin' && $role==='admin'){
            $count_super_admin=$conn->prepare("SELECT COUNT(*) as total FROM admins WHERE Role='super_admin'");
            $count_super_admin->execute();
            $total_super=$count_super_admin->get_result()->fetch_assoc()['total'];
            $count_super_admin->close();

            if($total_super<=1){
                $errors[]="You must have at least one super admin account. Please keep this account as super admin or create another super admin account first.";
            }
        }


        $password_update=null;
        if(!empty($password)) {
            if (strlen($password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).{8,}$/', $password)) {
            $errors[] = 'Please enter the password with at least 8 characters with uppercase, lowercase, number, and special character.';
        } elseif ($password !== $confirm_password){
            $errors[] = 'Passwords do not match.';
        } else {
            $password_update=password_hash($password, PASSWORD_DEFAULT);
        }
        }



        if (empty($role)) {
            $errors[] = 'Please select the role.';
        }

        if(empty($errors)){
            if($password_update){
                $update_admin=$conn->prepare("UPDATE admins SET Username=?, Email=?, Password=?, Role=? WHERE Admin_ID=?");
                $update_admin->bind_param("ssssi", $username, $email, $password_update, $role, $admin_id);
            } else {
                $update_admin=$conn->prepare("UPDATE admins SET Username=?, Email=?, Role=? WHERE Admin_ID=?");
                $update_admin->bind_param("sssi", $username, $email, $role, $admin_id); 
            }
            if($update_admin->execute()){
                $_SESSION['success'] = 'updated successfully';   
                header("Location: manage_admin.php");
                exit();
            } else {
                $errors[] = "Updated failed. Please try again.  ";
            }
            $update_admin->close();
        }
    }

    // fetch admin data for edit
    $get_admin = $conn->prepare("SELECT Username, Email, Role FROM admins WHERE Admin_ID = ?");
    $get_admin->bind_param("i", $admin_id);
    $get_admin->execute();
    $admin = $get_admin->get_result();

            if ($edit_admin=$admin->fetch_assoc()) {
                $edit_username=$edit_admin['Username'];
                $edit_email=$edit_admin['Email'];
                $edit_role=$edit_admin['Role'];
            }
            $get_admin->close();
    }

$display_username='';
$display_email='';
$display_role='';

if($_SERVER['REQUEST_METHOD']==='POST') {
   $display_username=htmlspecialchars(trim($_POST['username'] ?? '')); 
   $display_email=htmlspecialchars(trim($_POST['email'] ?? '')); 
   $display_role=htmlspecialchars(trim($_POST['role'] ?? '')); 
} elseif(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['admin_id'])) {
    $display_username=htmlspecialchars($edit_username); 
    $display_email=htmlspecialchars($edit_email); 
   $display_role=htmlspecialchars($edit_role); 
}

// --- FETCH ALL ADMINS ---
$admins = "SELECT Admin_ID, Username,Email, Role, Status,
    DATE_FORMAT(Created_At, '%d-%b-%Y %h:%i %p') as created_date,
    DATE_FORMAT(Last_Login_At, '%d-%b-%Y %h:%i %p') as last_login_formatted
    FROM admins
    ORDER BY Created_At ASC";
$admins_result = $conn->query($admins);

// retrieve and clear session message (if exists)
$message=null;
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - HomeNest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"> <!--sidebar icon-->
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="../css/manage_admins.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-dark">Manage Administrators</h2>
            <div class="badge bg-warning text-dark p-2">
                <i class="bi bi-shield-check me-1"></i> Superadmin Access
            </div>
        </div>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error" id="errorAlert">
                    <?=htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                </div>
                <?php endif; ?>
<?php if (!empty($message)): ?>

    <div class="alert alert-<?php echo ($message == 'error' || $message=='self_deactive') ? 'error' : 'success'; ?>" id="successAlert">
       <?php 
       if ($message == "activated"): 
            echo "The admin account has been activated"; 
        elseif ($message == "self_deactive"):  
            echo "You cannot deactivate your own account."; 
        elseif ($message == "deactivated"):  
            echo "The admin account has been deactivated"; 
		elseif ($message == "added successfully"):  
             echo "The admin account has been added successfully!"; 
        elseif ($message == "updated successfully"):  
             echo "The admin account updated successfully!";
        elseif ($message == "error"): 
             echo "There was an error updating the admin account."; 
        endif;
        ?>
        </div>
<?php endif; ?> 
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Create New Admin Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i> Create New Admin Account</h5>
            </div>
            <div class="card-body">
                <form id="adminForm" action="manage_admin.php<?=(isset($_GET['action']) && $_GET['action'] == 'edit') ? '?action=edit&admin_id='.$admin_id: '' ?>"
                method="POST" enctype="multipart/form-data" class="admin-form" novalidate>
                    <div class="row g-3">                        
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                    value="<?= $display_username ?>" 
                                   placeholder="Enter username">
                            <small class="error-message" id="userNameError"></small>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="text" class="form-control" id="email" name="email" required 
                                    value="<?= $display_email ?>" 
                                   placeholder="Enter email">
                            <small class="error-message" id="emailError"></small>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password </label>
                            <?php if(isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Leave blank to keep current password">
                            <?php else: ?>
                            <input type="password" name="password" id="password" class="form-control" 
                            value="" 
                            placeholder="Enter your password" required>
                            <small class="password-hint">Must be at least 8 characters with uppercase, lowercase, number, and special character</small>
                            <?php endif; ?>
                            <small class="error-message" id="passwordError"></small>
                            
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password </label>
                            <?php if(isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                            <input type="password" name="confirmPassword" id="confirmPassword" class="form-control" 
                                    placeholder="Leave blank if not change password">
                            <?php else: ?>
                                <input type="password" name="confirmPassword" id="confirmPassword" class="form-control" 
                                    placeholder="Confirm your password" required>
                            <?php endif; ?>
                            <small class="error-message" id="confirmPasswordError"></small>
                            
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="roleSelect" name="role">
                                <option value="admin"
                                <?=$display_role=='admin' ? 'selected': '' ?>>Admin
                                </option>
                                <option value="super_admin"
                                <?=$display_role=='super_admin' ? 'selected': '' ?>>Super Admin
                                </option>
                            </select>
                            <small class="error-message" id="roleError"></small>
                        </div>
                        <div class="col-12">
                        <?php if(isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                            <button type="submit" id="submitBtn" class="btn btn-primary btn-large"    
                                name="update_admin">Save </button> 
                                <a href="manage_admin.php" class="btn btn-secondary btn-large">
                                Cancel  
                                </a>    
                        <?php else: ?>
                            <button type="submit" id="submitBtn" class="btn btn-primary btn-large"    
                                name="add_admin">Add Admin</button>
                            <?php endif; ?> 
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Admins List -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i> All Admin Accounts</h5>
            </div>
            <div class="card-body">
                <?php if ($admins_result && $admins_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Admin ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created Date</th>
                                    <th>Last Login</th>
                                    <th>Action</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($admin = $admins_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($admin['Admin_ID']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['Username']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['Email'] ?: 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                                $role_badge = "bg-primary";
                                                if ($admin['Role'] == 'super_admin') {
                                                    $role_badge = "bg-warning text-dark";
                                                    }
                                            ?>
                                            <span class="badge <?php echo $role_badge; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $admin['Role'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $admin['created_date']; ?></td>
                                        <td><?php echo $admin['last_login_formatted'] ?: 'Never'; ?></td>
                                        <td>
                                            <a href="manage_admin.php?action=edit&admin_id=<?= $admin['Admin_ID'] ?>" class="btn btn-edit">Edit</a>
                                        </td>
                                       <td>
                                        <!--active button change-->
                                        <?php if (strtolower($admin['Status']) === 'active'): ?>
                                            <form method="POST" action="manage_admin.php">
                                                <input type="hidden" name="admin_id" value="<?php echo $admin['Admin_ID']; ?>">
                                                <input type="hidden" name="inactive_admin" value="inactive">
                                                <button type="submit" class="btn btn-delete" 
                                                        onclick="return confirm('Are you sure you want to mark this admin as inactive?');">
                                                        Make Inactive
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="manage_admin.php">
                                                <input type="hidden" name="admin_id" value="<?php echo $admin['Admin_ID']; ?>">
                                                <input type="hidden" name="active_admin" value="active"> 
                                                <button type="submit" class="btn btn-primary" 
                                                    onclick="return confirm('Are you sure you want to activate this admin account?');">
                                                    Make Active
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td> 
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-admin-found">
                        <h3>No admin accounts found</h3>
                        <p>Create your first admin account using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const form=document.getElementById('adminForm');
            const userName=document.getElementById('username');
            const email=document.getElementById('email');
            const password=document.getElementById('password');
            const confirmPassword=document.getElementById('confirmPassword');
            const submitBtn=document.getElementById('submitBtn');
            const roleSelect=document.getElementById('roleSelect');

            const userNameError=document.getElementById('userNameError');
            const emailError=document.getElementById('emailError');
            const passwordError=document.getElementById('passwordError');
            const confirmPasswordError=document.getElementById('confirmPasswordError');
            const roleError=document.getElementById('roleError');

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
            function validateUsername() {
                if (!userName.value.trim()) {
                    showError(userName, userNameError, 'Username is required.');
                    return false;
                }
                clearError(userName, userNameError);
                return true;
            }
            //email validation
            function validateEmail() {
                const emailValue = email.value.trim();
                if (!emailValue) {
                    showError(email, emailError, 'Email is required.');
                    return false;
                }
                
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailValue)) {
                    showError(email, emailError, 'Invalid email format.');
                    return false;
                }
                
                clearError(email, emailError);
                return true;
            }
            //password validation
            function validatePassword() {
                const passwordValue = password.value;
                const isEdit=window.location.search.includes('action=edit');
                if(isEdit && passwordValue===''){
                    clearError(password, passwordError);
                    return true;
                }
                if (!passwordValue) {
                    showError(password, passwordError, 'Password is required.');
                    return false;
                }
                
                const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).{8,}$/;
                if (!passwordRegex.test(passwordValue)) {
                    showError(password, passwordError, 
                        'Password must be at least 8 characters with uppercase, lowercase, number, and special character.');
                    return false;
                }
                
                clearError(password, passwordError);
                return true;
            }
            //comfirm password validation
            function validateConfirmPassword() {
                const isEdit=window.location.search.includes('action=edit');

                if(isEdit && password.value==='' && confirmPassword.value===''){
                    clearError(confirmPassword, confirmPasswordError);
                    return true;
                }
                if (!confirmPassword.value.trim()) {
                    showError(confirmPassword, confirmPasswordError, 'Please confirm your password.');
                    return false;
                }
                //password match
                if (password.value !== confirmPassword.value) {
                    showError(confirmPassword, confirmPasswordError, 'Passwords do not match.');
                    return false;
                }
                clearError(confirmPassword, confirmPasswordError);
                return true;
            }
            //role validation
            function validateRole() {
                if (!roleSelect.value) {
                    showError(roleSelect, roleError, 'Please select a role.');
                    return false;
                }
                clearError(roleSelect, roleError);
                return true;
            }

            //clear error messages while user is typing or selecting
            userName.addEventListener('input', () => clearError(userName, userNameError));
            email.addEventListener('input', () => clearError(email, emailError));
            password.addEventListener('input', () => clearError(password, passwordError));
            confirmPassword.addEventListener('input', () => clearError(confirmPassword, confirmPasswordError));
            roleSelect.addEventListener('change', () => clearError(roleSelect, roleError));

            //validate input fields when user leaves the field (blur)
            userName.addEventListener('blur', validateUsername);
            email.addEventListener('blur', validateEmail);
            password.addEventListener('blur', validatePassword);
            confirmPassword.addEventListener('blur', validateConfirmPassword);
            roleSelect.addEventListener('change', validateRole);

            // final validation before submit form
            form.addEventListener('submit', function(e) {
                
                //validate all field and show red color
                const validations = [
                    validateUsername(),
                    validateEmail(),
                    validatePassword(),
                    validateConfirmPassword(),
                    validateRole()
                ];
                
                const isValid = validations.every(v => v === true);
                
        if (!isValid) {
            e.preventDefault();
            return;
        } 
            });    
            // show success message only 5 seconds 
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>