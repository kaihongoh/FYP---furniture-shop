<?php
require_once(__DIR__ . "/../includes/config.php");
require_once(__DIR__ . "/../includes/update_level.php");
session_start();

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


// check is active or inactive, check if admin wants to deactivate a level
    if (isset($_POST['inactive_level'])) {  
        $level_id=intval($_POST['level_id']);
            $check_level=$conn->prepare("SELECT level_type FROM level_tag WHERE id=?");
            $check_level->bind_param("i", $level_id);
            $check_level->execute();
            $check_level->bind_result($level_type);  
            $check_level->fetch();
            $check_level->close();
            if(strtolower($level_type) === 'normal') {
                $_SESSION['message'] = 'level_error';
                header("Location: manage_level_tag.php");
                exit();
            } else{
                $conn->begin_transaction();
                try{
                // update level status to inactive 
                $inactive = $conn->prepare("UPDATE level_tag SET status = 'Inactive' WHERE id = ?");
                $inactive->bind_param("i", $level_id);
                $inactive->execute();
                $inactive->close();

                // also set all voucher of this level to inactive
                $updateVoucherInactive=$conn->prepare("UPDATE vouchers SET status = 'Inactive' WHERE restricted_to_level_id = ?");
                $updateVoucherInactive->bind_param("i", $level_id);
                $updateVoucherInactive->execute();
                $updateVoucherInactive->close();
                recalculateAllUserLevels($conn);
                $conn->commit();
                $_SESSION['message'] = 'deactivated'; //store success message in session
                    header("Location: manage_level_tag.php");
                    exit();
            } catch(Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = 'error';
                    header("Location: manage_level_tag.php");
                    exit();
            }
        }
    } elseif (isset($_POST['active_level'])) {
        $level_id=intval($_POST['level_id']);
            $check_level=$conn->prepare("SELECT level_type FROM level_tag WHERE id=?");
            $check_level->bind_param("i", $level_id);
            $check_level->execute();
            $check_level->bind_result($level_type);  
            $check_level->fetch();
            $check_level->close();
            if(strtolower($level_type) === 'normal') {
                $_SESSION['message'] = 'level_error';
                header("Location: manage_level_tag.php");
                exit();
            } else {
                $conn->begin_transaction();
                try{
                //update level status to active
                $active = $conn->prepare("UPDATE level_tag SET status = 'Active' WHERE id = ?");
                $active->bind_param("i", $level_id);
                $active->execute();
                $active->close();

                // also set all voucher of this level to active
                $updateVoucherInactive=$conn->prepare("UPDATE vouchers SET status = 'Active' WHERE restricted_to_level_id = ?");
                $updateVoucherInactive->bind_param("i", $level_id);
                $updateVoucherInactive->execute();
                $updateVoucherInactive->close();

                recalculateAllUserLevels($conn);
                $conn->commit();
                $_SESSION['message'] = 'activated'; //store success message in session
                    header("Location: manage_level_tag.php");
                    exit();
                } catch(Exception $e) {
                    $conn->rollback();
                    $_SESSION['message'] = 'error';
                        header("Location: manage_level_tag.php");
                        exit();
                }
            } 
 
    }


//count total active level
$countActive = $conn->prepare("SELECT COUNT(*) FROM level_tag WHERE status = 'Active'");
$countActive->execute();
$countActive->bind_result($totalActiveLevels);
$countActive->fetch();
$countActive->close();

//count total inactive level
$countInactive = $conn->prepare("SELECT COUNT(*) FROM level_tag WHERE status != 'Active'");
$countInactive->execute();
$countInactive->bind_result($totalInactiveLevels);
$countInactive->fetch();
$countInactive->close();

$display_level='';
$display_min_spent='';
$errors=[];

/*add level tag*/
if (isset($_POST['add_level'])) {
    $level_type = trim($_POST['level_type']);
    $min_spent = (float)$_POST['min_spent'];
    $errors=[];

    if(empty($level_type)) {
        $errors[] ="Level type is required.";
    } elseif (strtolower(trim($level_type)) === 'normal') {
        $errors[] ="Cannot create a level name with normal.";
    }
    else {
        // check the level type already exists
        $check_level = $conn->prepare("SELECT id FROM level_tag WHERE level_type=?");
        $check_level->bind_param("s", $level_type);
        $check_level->execute();
        $check_level->store_result();
        
        if ($check_level->num_rows > 0) {
            $errors[] = "The level already exists. Please create the level with different name.";
        }
        $check_level->close();
    }

    if($_POST['min_spent']==='' ||!is_numeric($_POST['min_spent'])) {
        $errors[]="Minimum spent is required.";
    } elseif ((float)$_POST['min_spent']<0) {
        $errors[]="Please provide a valid minimum spend amount.";
    }


    if(empty($errors)){
        $add_level =$conn->prepare("INSERT INTO level_tag (level_type, min_spent, status, created_at) 
                                    VALUES (?, ?, 'Active', NOW())");
        $add_level->bind_param("sd", $level_type, $min_spent);
        if($add_level->execute()){
            recalculateAllUserLevels($conn);
            $_SESSION['success'] = "added successfully";
            header("Location: manage_level_tag.php");
            exit();
        }else {
            $errors[]="Level added failed. Please try again.";
        }
        $add_level->close();
    }   
    //keep old input 
    $display_level=htmlspecialchars($level_type);
    $display_min_spent=htmlspecialchars($min_spent);
}

    if(isset($_POST['update_level'])){
        $id=intval($_POST['id']);
            
        $check_level=$conn->prepare("SELECT level_type FROM level_tag WHERE id=?");
        $check_level->bind_param("i", $id);
        $check_level->execute();
        $check_level->bind_result($level_type);  
        $check_level->fetch();
        $check_level->close();
        if(strtolower(trim($level_type)) === 'normal') {
            $_SESSION['message'] = 'level_error';
            header("Location: manage_level_tag.php");
            exit();
        } else{
            $level_type = trim($_POST['level_type']);
            $min_spent = (float)$_POST['min_spent'];
            $errors=[];
            
            if(empty($level_type)) {
                $errors[] = "Level type is required";
            } elseif (strtolower(trim($level_type)) === 'normal') {
                $errors[] ="Cannot create a level name with normal.";
            }else{
                // check if level already exists (exclude current level)
                $check_level = $conn->prepare("SELECT id FROM level_tag WHERE level_type = ? AND id != ?");
                $check_level->bind_param("si", $level_type, $id);
                $check_level->execute();
                $check_level->store_result();
                
                if ($check_level->num_rows > 0) {
                    $errors[] = "This level already exists. Please create with different level type.";
                }
                $check_level->close();
            }
            
            if($_POST['min_spent']==='' ||!is_numeric($_POST['min_spent'])) {
                $errors[]="Minimum spent is required.";
            } elseif ((float)$_POST['min_spent']<0) {
                $errors[]="Please provide a valid minimum spend amount.";
            }

            if(empty($errors)) {
                $update_level =$conn->prepare("UPDATE level_tag SET level_type=?, min_spent=? WHERE id=?");
                $update_level->bind_param("sdi", $level_type, $min_spent, $id);
                if($update_level->execute()){
                    recalculateAllUserLevels($conn);
                    $_SESSION['success'] = 'updated successfully';
                    header("Location: manage_level_tag.php");
                    exit();
                } else {
                    $errors[] = "Update failed. Please try again.";
                }
                $update_level->close();
            }
        }
    }
//edit level page
$edit=null;
 if(isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $check_normal=$conn->prepare("SELECT level_type FROM level_tag WHERE id=?");
    $check_normal->bind_param("i", $id);
    $check_normal->execute();
    $check_result=$check_normal->get_result();
    if($check_result && $row=$check_result->fetch_assoc()) {
        if(strtolower($row['level_type']) === 'normal') {
            $_SESSION['message'] = 'level_error';
            header("Location: manage_level_tag.php");
            exit();
        }
    }
    $check_normal->close();
    $stmt = $conn->prepare("SELECT * FROM level_tag WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result && $result->num_rows > 0){
        $edit = $result->fetch_assoc();
    }
    $stmt->close();
 }

        


//get all level
$getAll=$conn->prepare("SELECT * FROM level_tag ORDER BY id ASC");
$getAll->execute();
$levels=$getAll->get_result();
$getAll->close(); 

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
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Level Tags</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/level_tag.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
 
    <!-- ===================== ADD LEVEL ===================== -->
    <div class="header">
        <h1>Level Management</h1>
        <div class="header-right">
            <div class="user-info"></div>
        </div>
    </div>
 
<div class="content">
    <?php if (!empty($message)): ?>

    <div class="alert alert-<?php echo ($message == 'error' || $message== 'level_error') ? 'error' : 'success'; ?>" id="successAlert">
       <?php 
       if ($message == "activated"): 
            echo "The level status has been activated"; 
        elseif ($message == "deactivated"):  
            echo "The level status has been deactivated"; 
		elseif ($message == "added successfully"):  
             echo "The level has been added successfully!"; 
        elseif ($message == "updated successfully"):  
             echo "The level updated successfully!";
        elseif ($message == "level_error"):  
             echo "The normal level is default setting cannot be deactivated or edited."; 
        elseif ($message == "error"): 
             echo "There was an error updating the level status."; 
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
        
<h2 class="page-title">Levels Overview</h2>
           <div class="cards">
        <div class="card"> <!--overview of total royal users-->
            <div class="card-header">
                <span class="card-title">Total Active Levels</span>
            </div>
            <div class="card-value"><?php echo $totalActiveLevels; ?></div>
            <div class="card-footer">All active levels</div>
        </div>
        <div class="card">
            <div class="card-header"><!--overview of total normal users-->
                <span class="card-title">Total Inactive Levels</span>
            </div>
            <div class="card-value"><?php echo $totalInactiveLevels; ?></div>
            <div class="card-footer">All inactive levels</div>
        </div>
    </div> 

 
<form id="levelForm" method="POST" novalidate >
    <div class="form-row">
        <div class="form-group">
            <label for="level_type">Level Type </label>
            <input type="text" id="level_type" name="level_type" class="form-control" 
                value="<?= $display_level ?>" 
                placeholder="Enter the level type" required>
            <small class="error-message" id="levelTypeError"></small>
        </div>
        <div class="form-group">
            <label for="min_spent">Minimum Spent (RM) </label>
            <input type="number" id="min_spent" name="min_spent" class="form-control" step="0.01" min="0" 
                value="<?= $display_min_spent ?>" 
                placeholder="Enter the minimum spent amount" required>
            <small class="error-message" id="spentError"></small>
            <div>
        </div>
        </div>
    </div>
    <button type="submit" id="submitBtn" name="add_level" class="btn btn-primary btn-large">Add Level</button>
</form>
 
<hr>
 
<!-- ===================== LEVEL TABLE ===================== -->
<h3>Existing Levels</h3>
<div class="level-table-container">
<?php if($levels->num_rows > 0): ?>
<table class="level-table">
    <tr>
        <!--<th>ID</th>-->
        <th>Level Type</th>
        <th>Minimum Spent (RM)</th>
        <th>Created Date</th>
        <th>Actions</th>
        <th>Status</th>
    </tr>   
    <?php while ($level=$levels->fetch_assoc()): 
        $isNormal=(strtolower($level['level_type']) === 'normal');
    ?>
    <tr>
       <!-- <td><strong><?= $level['id']; ?></strong></td>-->
        <td><?= htmlspecialchars($level['level_type']); ?></td>
        <td>RM <?= number_format($level['min_spent'], 2); ?></td>
        <td><?= date('d-m-Y H:i', strtotime($level['created_at'])); ?></td>
        <td>
        <?php if (!$isNormal): ?>
            <a href="?edit=<?= $level['id'] ?>" class="btn btn-edit">Edit</a>
        <?php else: ?>
            <span>Default Level</span>
        <?php endif; ?>
        </td>
<td>
    <?php if($isNormal): ?>
        <span>Always Active</span>
        <?php else: ?>
    <!--active button change-->
    <?php if (strtolower($level['status']) === 'active'): ?>
        <form method="POST" action="manage_level_tag.php">
            <input type="hidden" name="level_id" value="<?php echo $level['id']; ?>">
            <input type="hidden" name="inactive_level" value="inactive">
            <button type="submit" class="btn btn-delete" 
                    onclick="return confirm('Are you sure you want to mark this level as inactive?');">
                    Make Inactive
            </button>
        </form>
    <?php else: ?>
        <form method="POST" action="manage_level_tag.php">
            <input type="hidden" name="level_id" value="<?php echo $level['id']; ?>">
            <input type="hidden" name="active_level" value="active"> 
            <button type="submit" class="btn btn-primary" 
                    onclick="return confirm('Are you sure you want to activate this level?');">
                    Make Active
            </button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </td>
</tr>

<?php endwhile; ?>
    </table>
    <?php else: ?>
        <div class="no-level-found">
            <h3>No levels found</h3>
            <p>Try add a new level.</p>
        </div> 
<?php endif; ?>
</div>
 
<!--edit level-->
<?php if(!empty($edit)): ?>
<div class="modal" onclick="if(event.target === this) location.href='manage_level_tag.php'">
    <div class="modal-content">
        <h2>Edit Level</h2>
 
        <form method="POST" id="editForm">
            <input type="hidden" name="id" value="<?= $edit['id']; ?>">
 
            <div class="form-group">
                <label>Level Type Name</label>
                <input type="text" name="level_type" id="edit_level_type" value="<?= htmlspecialchars($edit['level_type']); ?>" required>
                <small class="error-message" id="editLevelTypeError"></small>
            </div>
 
            <div class="form-group">
                <label>Minimum Spent (RM)</label>
                <input type="number" step="0.01" name="min_spent" id="edit_min_spent" value="<?= $edit['min_spent']; ?>" required>
                <small class="error-message" id="editSpentError"></small>
            </div>
 
            <div class="modal-buttons">
                <button type="submit" name="update_level" class="btn btn-primary btn-large">Update Level</button>
                <a href="manage_level_tag.php" class="btn btn-secondary btn-large">Cancel</a>
            </div>
        </form>
    </div>
</div>
 
<?php endif; ?>
 
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('levelForm');
    const level_type=document.getElementById('level_type');
    const min_spent=document.getElementById('min_spent');
    const submitBtn=document.getElementById('submitBtn');

    //edit
    const edit_form=document.getElementById('editForm');
    const edit_level_type=document.getElementById('edit_level_type');
    const edit_min_spent=document.getElementById('edit_min_spent');

    // get all error message element
    const levelTypeError=document.getElementById('levelTypeError');
    const spentError=document.getElementById('spentError');

    //edit
    const editLevelTypeError=document.getElementById('editLevelTypeError');
    const editSpentError=document.getElementById('editSpentError');

    //stop script execution if form does not exist
    if (!form && !edit_form) return;

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

    // level type validation
    function validateLevelType() {
        if (!level_type.value.trim()) {
            showError(level_type, levelTypeError, 'Level type is required.');
            return false;
        }
        clearError(level_type, levelTypeError);
        return true;
    }

    // variant name validation
    function validateEditLevelType() {
        if (!edit_level_type.value.trim()) {
            showError(edit_level_type, editLevelTypeError, 'Level type is required.');
            return false;
        }
        clearError(edit_level_type, editLevelTypeError);
        return true;
    }

    //minimum spent validation
    function validateSpent() {
        if (min_spent.value.trim() === '') {
            showError(min_spent, spentError, 'Minimum spent is required.');
            return false;
        }
        if(isNaN(parseFloat(min_spent.value))) {    
            showError(min_spent, spentError, 'Please enter a valid number.');
            return false;
        }
        if(parseFloat(min_spent.value)<0) {
            showError(min_spent, spentError, 'Minimum spent must grather than or equal to 0.');
            return false;
        }
        
        clearError(min_spent, spentError);
        return true;
    }
    //edit minimum spent validation
    function validateEditSpent() {
        if (edit_min_spent.value.trim() === '') {
            showError(edit_min_spent, editSpentError, 'Minimum spent is required.');
            return false;
        }
        if(isNaN(parseFloat(edit_min_spent.value))) {    
            showError(edit_min_spent, editSpentError, 'Please enter a valid number.');
            return false;
        }
        if(parseFloat(edit_min_spent.value)<0) {
            showError(edit_min_spent, editSpentError, 'Minimum spent must grather than or equal to 0.');
            return false;
        }
        clearError(edit_min_spent, editSpentError);
        return true;
    }

    //clear error messages while user is typing or selecting
    level_type.addEventListener('input', () => clearError(level_type, levelTypeError));
    min_spent.addEventListener('input', () => clearError(min_spent, spentError));
    
    //validate input fields when user leaves the field (blur)
    level_type.addEventListener('blur', validateLevelType);
    min_spent.addEventListener('blur', validateSpent);

    if(edit_level_type) {
        edit_level_type.addEventListener('input', () => clearError(edit_level_type, editLevelTypeError));
        edit_level_type.addEventListener('blur', validateEditLevelType);
    }
    if(edit_min_spent) {
        edit_min_spent.addEventListener('input', () => clearError(edit_min_spent, editSpentError));
        edit_min_spent.addEventListener('blur', validateEditSpent);
    }
    
    
    


    // final validation before submit form
    if(form){
        form.addEventListener('submit', function(e) {
        
        //validate all field and show red color
        const validations = [
            validateLevelType(),
            validateSpent()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (!isValid) {
            e.preventDefault();
            return;
        } 
    });
    }
        if(edit_form){
        edit_form.addEventListener('submit', function(e) {
        
        //validate all field and show red color
        const validations = [
            validateEditLevelType(),
            validateEditSpent()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (!isValid) {
            e.preventDefault();
            return;
        } 
    });
    }
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
 