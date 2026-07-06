<?php 
require_once(__DIR__ . "/../includes/config.php");
session_start();

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


// check is active or inactive, check if admin wants to deactivate a state
    if (isset($_POST['inactive_shipping'])) {  
        $shipping_id=intval($_POST['shipping_id']);

        // update shipping status to inactive 
        $inactive = $conn->prepare("UPDATE shipping_fee_setting SET status = 'Inactive' WHERE id = ?");
        $inactive->bind_param("i", $shipping_id);
        if($inactive->execute()){
            $_SESSION['message'] = 'deactivated';
        } else{
            $_SESSION['message'] = 'error';
        }
        $inactive->close();
            header("Location: manage_shipping.php");
            exit();

    } elseif (isset($_POST['active_shipping'])) {
        $shipping_id=intval($_POST['shipping_id']);
        //update shipping status to active
        $active = $conn->prepare("UPDATE shipping_fee_setting SET status = 'Active' WHERE id = ?");
        $active->bind_param("i", $shipping_id);
        if($active->execute()){
            $_SESSION['message'] = 'activated';
        } else {
            $_SESSION['message'] = 'error';
        }
        $active->close();
            header("Location: manage_shipping.php");
            exit();
    }


//count total active shipping
$countActive = $conn->prepare("SELECT COUNT(*) FROM shipping_fee_setting WHERE status = 'Active'");
$countActive->execute();
$countActive->bind_result($totalActiveShippings);
$countActive->fetch();
$countActive->close();

//count total inactive shipping
$countInactive = $conn->prepare("SELECT COUNT(*) FROM shipping_fee_setting WHERE status != 'Active'");
$countInactive->execute();
$countInactive->bind_result($totalInactiveShippings);
$countInactive->fetch();
$countInactive->close();

$display_state='';
$display_shipping_fee='';
$errors=[];
//add shipping
if (isset($_POST['add_shipping'])) {
    $state = trim($_POST['state_name']);
    $shipping_fee = (float)$_POST['shipping_fee'];
    $errors=[];

    if(empty($state)){
        $errors[] ="State name is required.";
    } else {
        // check the state name already exists
        $check_name = $conn->prepare("SELECT id FROM shipping_fee_setting WHERE state_name=?");
        $check_name ->bind_param("s", $state);
        $check_name ->execute();
        $check_name ->store_result();

        if ($check_name->num_rows > 0) {
            $errors[] = "The state already exists. Please create the state with different name.";
        }
        $check_name->close();
    }
    if($_POST['shipping_fee']===''){
        $errors[] ="Shipping fee is required.";
    } elseif ($shipping_fee <0){
        $errors[] ="Shipping fee cannot be negative.";
    }

    if(empty($errors)){
        $add_shipping = $conn->prepare("INSERT INTO shipping_fee_setting (state_name, shipping_fee, status, created_at)
            VALUES (?, ?, 'Active', NOW())");
        $add_shipping->bind_param("sd", $state, $shipping_fee);
        if($add_shipping->execute()){
            $_SESSION['success'] = "added successfully";
            header("Location: manage_shipping.php");
            exit();
        } else {
        $errors[]="shipping fee added failed. Please try again.";
    }
    $add_shipping->close();
    }
    //keep old input 
    $display_state=htmlspecialchars($state);
    $display_shipping_fee=htmlspecialchars($shipping_fee);
}
    //update
    if(isset($_POST['update_shipping'])) {
        $id=intval($_POST['id']);
        $state = trim($_POST['state_name']);
        $shipping_fee = (float)$_POST['shipping_fee'];
        $errors=[];

        if(empty($state)){
            $errors[] ="State name is required.";
        } else {
            // check the state name already exists
            $check_name = $conn->prepare("SELECT id FROM shipping_fee_setting WHERE state_name=? AND id!=?");
            $check_name ->bind_param("si", $state, $id);
            $check_name ->execute();
            $check_name ->store_result();

            if ($check_name->num_rows > 0) {
                $errors[] = "The state already exists. Please create the state with different name.";
            }
            $check_name->close();
        }
        if($_POST['shipping_fee']===''){
            $errors[] ="Shipping fee is required.";
        } elseif ($shipping_fee <0){
            $errors[] ="Shipping fee cannot be negative.";
        }
            if(empty($errors)) {
            $update_shipping =$conn->prepare("UPDATE shipping_fee_setting SET state_name=?, shipping_fee=? WHERE id=?");
            $update_shipping->bind_param("sdi", $state, $shipping_fee, $id);
            if($update_shipping->execute()){
                $_SESSION['success'] = 'updated successfully';
                header("Location: manage_shipping.php");
                exit();
            } else {
                $errors[] = "Update failed. Please try again.";
            }
            $update_shipping->close();
            }
    }

//get all shipping
$getAll=$conn->prepare("SELECT * FROM shipping_fee_setting ORDER BY id ASC");
$getAll->execute();
$shippings=$getAll->get_result();
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shipping</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/shipping.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
    <!--add shipping -->
    <div class="header">
        <h1>Manage Shipping</h1>
        <div class="header-right">
            <div class="user-info"></div>
        </div>
    </div>
<div class="content">
    <?php if (!empty($message)): ?>

    <div class="alert alert-<?php echo ($message == 'error') ? 'error' : 'success'; ?>" id="successAlert">
       <?php 
       if ($message == "activated"): 
            echo "The shipping status has been activated"; 
        elseif ($message == "deactivated"):  
            echo "The shipping status has been deactivated"; 
		elseif ($message == "added successfully"):  
             echo "The shipping has been added successfully!"; 
        elseif ($message == "updated successfully"):  
             echo "The shipping updated successfully!";
        elseif ($message == "error"): 
             echo "There was an error updating the shipping status."; 
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

        <h2 class="page-title">Shipping Overview</h2>
           <div class="cards">
        <div class="card"> <!--overview of total active-->
            <div class="card-header">
                <span class="card-title">Total Active Shippings</span>
            </div>
            <div class="card-value"><?php echo $totalActiveShippings; ?></div>
            <div class="card-footer">All active shippings</div>
        </div>
        <div class="card">
            <div class="card-header"><!--overview of total inactive-->
                <span class="card-title">Total Inactive shippings</span>
            </div>
            <div class="card-value"><?php echo $totalInactiveShippings; ?></div>
            <div class="card-footer">All inactive shippings</div>
        </div>
    </div> 

<form id="shippingForm" method="POST" novalidate>
    <div class="form-row">
        <div class="form-group">
            <label>State Name </label>
            <input type="text" id="state_name" name="state_name" class="form-control" 
            value="<?= $display_state ?>"
            required>
            <small class="error-message" id="stateNameError"></small>
        </div>

        <div class="form-group">
            <label>Shipping Fee (RM)</label>
            <input type="number" id="shipping_fee" name="shipping_fee" class="form-control" step="0.01" min="0"
            value="<?= $display_shipping_fee ?>"
             required>
             <small class="error-message" id="feeError"></small>
        </div>
    </div>

    <button type="submit" id="submitBtn" name="add_shipping" class="btn btn-primary btn-large"> Add Shipping Fee</button>
</form>
<hr>

<h3>Existing Shipping</h3>
<div class="shipping-table-container">
    <?php if($shippings->num_rows > 0): ?>
        <table class="shipping-table">
            <tr>
                <th>ID</th>
                <th>State</th>
                <th>Shipping Fee</th>
                <th>Action</th>
                <th>Status</th>
            </tr>
            <?php while($shipping=$shippings->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= $shipping['id']; ?></strong></td>
                    <td><?= htmlspecialchars($shipping['state_name']); ?></td>
                    <td>RM <?= number_format($shipping['shipping_fee'],2); ?></td>
                    <td>
                    <a href="?edit=<?= $shipping['id']; ?>" class="btn btn-edit">Edit</a>
                    </td>
                   <td>
                    <!--active button change-->
                    <?php if (strtolower($shipping['status']) === 'active'): ?>
                        <form method="POST" action="manage_shipping.php">
                            <input type="hidden" name="shipping_id" value="<?php echo $shipping['id']; ?>">
                            <input type="hidden" name="inactive_shipping" value="inactive">
                            <button type="submit" class="btn btn-delete" 
                                    onclick="return confirm('Are you sure you want to mark this state as inactive?');">
                                    Make Inactive
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="manage_shipping.php">
                            <input type="hidden" name="shipping_id" value="<?php echo $shipping['id']; ?>">
                            <input type="hidden" name="active_shipping" value="active"> 
                            <button type="submit" class="btn btn-primary" 
                                    onclick="return confirm('Are you sure you want to activate this state?');">
                                    Make Active
                            </button>
                        </form>
                        <?php endif; ?>
                    </td> 
                </tr>
            <?php endwhile; ?>
            </table>
            <?php else: ?>
                <div class="no-shipping-found">
                    <h3>No shipping found</h3>
                    <p>Try add a new shipping.</p>
                </div> 
        <?php endif; ?>    
</div>

<!--edit-->
<?php if(isset($_GET['edit'])):
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM shipping_fee_setting WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result && $result->num_rows > 0):
        $edit = $result->fetch_assoc();
?>
<div class="modal" onclick="if(event.target === this) location.href='manage_shipping.php'">
    <div class="modal-content">
        <h2>✏️ Edit Shipping</h2>
 
        <form method="POST" id="editForm">
            <input type="hidden" name="id" value="<?= $edit['id']; ?>">
 
            <div class="form-group">
                <label>State Name</label>
                <input type="text" name="state_name" id="edit_state_name" value="<?= htmlspecialchars($edit['state_name']); ?>" required>
                <small class="error-message" id="editStateNameError"></small>
            </div>
 
            <div class="form-group">
                <label>Shipping Fee (RM)</label>
                <input type="number" step="0.01" name="shipping_fee" id="edit_shipping_fee" value="<?= $edit['shipping_fee']; ?>" required>
                <small class="error-message" id="editFeeError"></small>
            </div>
 
            <div class="modal-buttons">
                <button type="submit" name="update_shipping" class="btn btn-primary btn-large">Update</button>
                <a href="manage_shipping.php" class="btn btn-secondary btn-large">Cancel</a>
            </div>
        </form>
    </div>
</div>
 
<?php endif; endif;?>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('shippingForm');
    const state_name=document.getElementById('state_name');
    const shipping_fee=document.getElementById('shipping_fee');
    const submitBtn=document.getElementById('submitBtn');

    //edit
    const edit_form=document.getElementById('editForm');
    const edit_state_name=document.getElementById('edit_state_name');
    const edit_shipping_fee=document.getElementById('edit_shipping_fee');

    // get all error message element
    const stateNameError=document.getElementById('stateNameError');
    const feeError=document.getElementById('feeError');

    //edit
    const editStateNameError=document.getElementById('editStateNameError');
    const editFeeError=document.getElementById('editFeeError');

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

    // state validation
    function validateStateName() {
        if (!state_name.value.trim()) {
            showError(state_name, stateNameError, 'State name is required.');
            return false;
        }
        clearError(state_name, stateNameError);
        return true;
    }

    // variant name validation
    function validateEditStateName() {
        if (!edit_state_name.value.trim()) {
            showError(edit_state_name, editStateNameError, 'State name is required.');
            return false;
        }
        clearError(edit_state_name, editStateNameError);
        return true;
    }

    //minimum spent validation
    function validateFee() {
        if (shipping_fee.value.trim() === '') {
            showError(shipping_fee, feeError, 'Shipping fee is required.');
            return false;
        }

        if(parseFloat(shipping_fee.value)<0) {
            showError(shipping_fee, feeError, 'Shipping fee cannot be negative.');
            return false;
        }
        
        clearError(shipping_fee, feeError);
        return true;
    }
    //edit fee validation
    function validateEditFee() {
        if (edit_shipping_fee.value.trim() === '') {
            showError(edit_shipping_fee, editFeeError, 'Shipping fee is required.');
            return false;
        }
        if(parseFloat(edit_shipping_fee.value)<0) {
            showError(edit_shipping_fee, editFeeError, 'Shipping fee cannot be negative.');
            return false;
        }
        clearError(edit_shipping_fee, editFeeError);
        return true;
    }

    //clear error messages while user is typing or selecting
    state_name.addEventListener('input', () => clearError(state_name, stateNameError));
    shipping_fee.addEventListener('input', () => clearError(shipping_fee, feeError));
    
    //validate input fields when user leaves the field (blur)
    state_name.addEventListener('blur', validateStateName);
    shipping_fee.addEventListener('blur', validateFee);

    if(edit_state_name) {
        edit_state_name.addEventListener('input', () => clearError(edit_state_name, editStateNameError));
        edit_state_name.addEventListener('blur', validateEditStateName);
    }
    if(edit_shipping_fee) {
        edit_shipping_fee.addEventListener('input', () => clearError(edit_shipping_fee, editFeeError));
        edit_shipping_fee.addEventListener('blur', validateEditFee);
    }
    
    // final validation before submit form
    if(form){
        form.addEventListener('submit', function(e) {
        
        //validate all field and show red color
        const validations = [
            validateStateName(),
            validateFee()
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
            validateEditStateName(),
            validateEditFee()
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