<?php
require_once(__DIR__ . "/../includes/config.php");
require_once(__DIR__ . "/../includes/send_mail.php");
session_start();

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}



// check is active or inactive, check if admin wants to deactivate a level
    if (isset($_POST['inactive_voucher'])) {
        $voucher_id=intval($_POST['voucher_id']);
        // update level status to inactive 
        $inactive = $conn->prepare("UPDATE vouchers SET status = 'Inactive' WHERE Voucher_ID = ?");
        $inactive->bind_param("i", $voucher_id);
        if ($inactive->execute()) { //store success message in session
            $_SESSION['message'] = 'deactivated';
                
            $redirect_url = "manage_voucher.php";
            if (!empty($search)) {
                $redirect_url .= "?" . http_build_query($search);
            }
    
            header("Location: $redirect_url");
            exit();
        } else { //store error message in session
            $_SESSION['message'] = 'error';
        }
        $inactive->close();
    } elseif (isset($_POST['active_voucher'])) {
        $voucher_id=intval($_POST['voucher_id']);
        //if want to set active, need to check the category is active or not
        $get_voucher_level=$conn->prepare("SELECT restricted_to_level_id FROM vouchers
                            WHERE Voucher_ID = ?");
            $get_voucher_level->bind_param("i", $voucher_id);
            $get_voucher_level->execute();
            $restricted_level=$get_voucher_level->get_result()->fetch_assoc()['restricted_to_level_id'];
            $get_voucher_level->close();
            //only check if have level
            if(!empty($restricted_level)) {
                $check_level=$conn->prepare("SELECT status FROM level_tag WHERE id=?");
                $check_level->bind_param("i", $restricted_level);
                $check_level->execute();
                $result=$check_level->get_result()->fetch_assoc();
                $check_level->close();
                
                if(strtolower($result['status']) !== 'active') {
                    $_SESSION['error']="Please make sure the level is active status before activate the voucher status.";
                    header("Location: manage_voucher.php");
                    exit(); 
                }
            }
        //update voucher status to active
        $active = $conn->prepare("UPDATE vouchers SET status = 'Active' WHERE Voucher_ID = ?");
        $active->bind_param("i", $voucher_id);
        if ($active->execute()) {
            $_SESSION['message'] = 'activated';
            $redirect_url = "manage_voucher.php";
            if (!empty($search)) {
                $redirect_url .= "?" . http_build_query($search);
            }
    
            header("Location: $redirect_url");
            exit();
        } else {
            $_SESSION['message'] = 'error';
        }
        $active->close();
    }


//count total active vouchers
$countActive = $conn->prepare("SELECT COUNT(*) FROM vouchers WHERE status = 'Active'");
$countActive->execute();
$countActive->bind_result($totalActiveVoucher);
$countActive->fetch();
$countActive->close();

//count total inactive vouchers
$countInactive = $conn->prepare("SELECT COUNT(*) FROM vouchers WHERE status != 'Active'");
$countInactive->execute();
$countInactive->bind_result($totalInactiveVoucher);
$countInactive->fetch();
$countInactive->close();

$display_voucher_code='';
$display_voucher_name='';
$display_discount_type='';
$display_discount_value='';
$display_min_spend='';
$display_max_discount='';
$display_usage_limit='';
$display_usage_per_user='';
$display_expiry_date='';
$display_restricted_to_level='';

/*add vouchers*/
if (isset($_POST['add_voucher'])) {
    $voucher_code = $_POST['voucher_code'];
    $voucher_name = $_POST['voucher_name'];
    $discount_type = $_POST['discount_type'];
    $discount_value = (float)$_POST['discount_value'];
    $minimum_spend = (float)$_POST['min_spend'];

    if(isset($_POST['max_discount']) && $_POST['max_discount'] !== ''){
        $max_discount = (float)$_POST['max_discount'];
    } else {
        $max_discount = 0;
    }

    $usage_limit = (int)$_POST['usage_limit'];
    $usage_per_user = (int)$_POST['usage_per_user'];
    $expiry_date = $_POST['expiry_date'];
    $restricted_to_level = !empty($_POST['restricted_to_level_id']) ? (int)$_POST['restricted_to_level_id'] : NULL;
    $errors=[];

    if(empty($voucher_code)) {
        $errors[] ="Voucher code is required.";
    } else {
        // check the level type already exists
        $check_code = $conn->prepare("SELECT Voucher_ID FROM vouchers WHERE Voucher_Code=?");
        $check_code->bind_param("s", $voucher_code);
        $check_code->execute();
        $check_code->store_result();
        
        if ($check_code->num_rows > 0) {
            $errors[] = "The vouchers code already exists. Please create the vouchers with different code.";
        }
        $check_code->close();
    }

    if(empty($voucher_name)) {
        $errors[] ="Voucher name is required.";
    } else {
        // check the level type already exists
        $check_name = $conn->prepare("SELECT Voucher_ID FROM vouchers WHERE Voucher_Name=?");
        $check_name->bind_param("s", $voucher_name);
        $check_name->execute();
        $check_name->store_result();
        
        if ($check_name->num_rows > 0) {
            $errors[] = "The vouchers name already exists. Please create the vouchers with different name.";
        }
        $check_name->close();
    }

    if(empty($discount_type)) {
        $errors[] = "The discount type cannot be blank. Please select the discount type.";
    } elseif($discount_type=='percentage') {
        if($discount_value<=0 || $discount_value>=100) {
            $errors[] = "Percentage discount must between 1 and 99.";
        } 
        if($max_discount<=0) {
            $errors[] = "Max discount must be greater than 0 for percentage vouchers.";
        }
    } else { //fixed
        $max_discount=NULL;
        if($discount_value<=0) {
            $errors[] = "Please enter valid discount amount.";
        }
        if($discount_value>$minimum_spend){
            $errors[] ="Discount value (RM " . number_format($discount_value,2) . ") cannot be greather than minimum spend.";
        }
    }


    if($minimum_spend<0) {
        $errors[]="Please provide a valid minimum spend amount.";
    }

    if($usage_limit<=0) {
        $errors[]="Please provide a valid usage limit for the vouchers.";
    }

    if($usage_per_user<=0) {
        $errors[]="Please provide a valid usage for every user.";
    }

    if($usage_per_user>$usage_limit){
        $errors[]="Usage per user cannot exceed total usage limit.";
    }

    if(empty($expiry_date)) {
        $expiry_date=null;
    } elseif (strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
        $errors[]="Invalid expire date.";
    }
    
    if(empty($errors)) {
        $add_voucher = $conn->prepare("INSERT INTO vouchers 
                (Voucher_Code, Voucher_Name, Discount_Type, Discount_Value, Minimum_Spend, Max_Discount, 
                Usage_Limit, Usage_Per_User, Expiry_Date, Status, restricted_to_level_id, Created_At) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, NOW())");
        $add_voucher->bind_param("sssdddiisi", $voucher_code, $voucher_name, $discount_type, $discount_value, $minimum_spend,
                            $max_discount, $usage_limit, $usage_per_user, $expiry_date, $restricted_to_level);     
        if($add_voucher->execute()) {
            $_SESSION['success'] = "added successfully";
            header("Location: manage_voucher.php");
            exit();
        } else {
            $errors[]="Voucher added failed. Please try again.";
        } 
        $add_voucher->close();                       
    }
    //keep old input 
    $display_voucher_code=htmlspecialchars($voucher_code);
    $display_voucher_name=htmlspecialchars($voucher_name);
    $display_discount_type=htmlspecialchars($discount_type);
    $display_discount_value=htmlspecialchars($discount_value);
    $display_min_spend=htmlspecialchars($minimum_spend);
    $display_max_discount=htmlspecialchars($max_discount);
    $display_usage_limit=htmlspecialchars($usage_limit);
    $display_usage_per_user=htmlspecialchars($usage_per_user);
    $display_expiry_date=htmlspecialchars($expiry_date);
    $display_restricted_to_level=htmlspecialchars($restricted_to_level);
    
}


/*update vouchers*/
if(isset($_POST['update_voucher'])){
    $voucher_id = (int)$_POST['Voucher_ID'];
    $voucher_code = $_POST['Voucher_Code'];
    $voucher_name = $_POST['Voucher_Name'];
    $discount_type = $_POST['Discount_Type'];
    $discount_value = (float)$_POST['Discount_Value'];
    $minimum_spend = (float)$_POST['Minimum_Spend'];

    if(isset($_POST['Max_Discount']) && $_POST['Max_Discount'] !== ''){
        $max_discount = (float)$_POST['Max_Discount'];
    } else {
        $max_discount = 0;
    }

    $usage_limit = (int)$_POST['Usage_Limit'];
    $usage_per_user = (int)$_POST['Usage_Per_User'];
    $expiry_date = $_POST['Expiry_Date'];
    $status = $_POST['Status'];
    $restricted_to_level = !empty($_POST['restricted_to_level_id']) ? (int)$_POST['restricted_to_level_id'] : NULL;
    $errors=[];

    if(empty($voucher_code)) {
        $errors[] ="Voucher code is required.";
    } else {
        // check the voucher code already exists
        $check_code = $conn->prepare("SELECT Voucher_ID FROM vouchers WHERE Voucher_Code=? AND Voucher_ID!=?");
        $check_code->bind_param("si", $voucher_code, $voucher_id);
        $check_code->execute();
        $check_code->store_result();
        
        if ($check_code->num_rows > 0) {
            $errors[] = "The vouchers code already exists. Please create the vouchers with different code.";
        }
        $check_code->close();
    }
    if(empty($voucher_name)) {
        $errors[] ="Voucher name is required.";
    } else {
        // check the voucher name already exists
        $check_name = $conn->prepare("SELECT Voucher_ID FROM vouchers WHERE Voucher_Name=? AND Voucher_ID!=?");
        $check_name->bind_param("si", $voucher_name, $voucher_id);
        $check_name->execute();
        $check_name->store_result();
        
        if ($check_name->num_rows > 0) {
            $errors[] = "The vouchers name already exists. Please create the vouchers with different name.";
        }
        $check_name->close();
    }

    if(empty($discount_type)) {
        $errors[] = "The discount type cannot be blank. Please select the discount type.";
    } elseif($discount_type=='percentage') {
        if($discount_value<=0 || $discount_value>=100) {
            $errors[] = "Percentage discount must between 1 and 99.";
        } 
        if($max_discount<=0) {
            $errors[] = "Max discount must be greater than 0 for percentage vouchers.";
        }
    } else { //fixed
        $max_discount=NULL;
        if($discount_value<=0) {
            $errors[] = "Please enter valid discount amount.";
        }
        if($discount_value>$minimum_spend){
            $errors[] ="Discount value (RM " . number_format($discount_value,2) . ") cannot be greather than minimum spend.";
        }
    }

    if($minimum_spend<0) {
        $errors[]="Please provide a valid minimum spend amount.";
    }

    if($usage_limit<=0) {
        $errors[]="Please provide a valid usage limit for the vouchers.";
    }

    if($usage_per_user<=0) {
        $errors[]="Please provide a valid usage for every user.";
    }

    if($usage_per_user>$usage_limit){
        $errors[]="Usage per user cannot exceed total usage limit.";
    }

    if(empty($expiry_date)) {
        $expiry_date=null;
    } elseif (strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
        $errors[]="Invalid expire date.";
    }

    if(empty($errors)) {
        $update_voucher =$conn->prepare("UPDATE vouchers SET
        Voucher_Code=?,
        Voucher_Name=?,
        Discount_Type=?,
        Discount_Value=?,
        Minimum_Spend=?,
        Max_Discount=?,
        Usage_Limit=?,
        Usage_Per_User=?,
        Expiry_Date=?,
        Status=?,
        restricted_to_level_id=?
        WHERE Voucher_ID=?");
        $update_voucher->bind_param("sssdddiissii", $voucher_code, $voucher_name, $discount_type, $discount_value, $minimum_spend,
                         $max_discount, $usage_limit, $usage_per_user, $expiry_date, $status, $restricted_to_level, $voucher_id);
        if($update_voucher->execute()) {
            $_SESSION['success'] = 'updated successfully';
            header("Location: manage_voucher.php");
            exit();
        } else {
            $errors[] = "Update failed. Please try again.";
        }
    }
}

    //go back to same page while keeping search filter content
    $search = [];
    if (isset($_GET['voucher_name']) && !empty($_GET['voucher_name'])) {
        $search['voucher_name'] = $_GET['voucher_name'];
    }
    if (isset($_GET['voucher_code']) && !empty($_GET['voucher_code'])) {
        $search['voucher_code'] = $_GET['voucher_code'];
    }



    $filter = "";
//filter by vouchers name
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $keyword=$conn->real_escape_string($_GET['search']);
    $filter .= " AND (Voucher_Name LIKE '%$keyword%' OR Voucher_Code LIKE '%$keyword%')";
}

//retrieve voucher list information
$vouchersQuery = "SELECT * FROM vouchers WHERE 1" . $filter . " ORDER BY Voucher_ID ASC";

$vouchers= $conn->query($vouchersQuery);


$level_query=$conn->query("SELECT id, level_type FROM level_tag WHERE status='Active'");
$level_types=[];
while($level_list=$level_query->fetch_assoc()) {
    $level_types[$level_list['id']] = $level_list['level_type'];
}

// retrieve and clear session message (if exists)
$message=null;
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}  


//send voucher email
if(isset($_POST['send_voucher']) && isset($_POST['voucher_id'])) {
    $voucher_id=(int)$_POST['voucher_id'];

    $get_voucher_details=$conn->prepare("SELECT * FROM vouchers WHERE Voucher_ID=?");
    $get_voucher_details->bind_param("i", $voucher_id);
    $get_voucher_details->execute();
    $voucher_details=$get_voucher_details->get_result()->fetch_assoc();
    $get_voucher_details->close();

    if(!$voucher_details){
        $_SESSION['message']= "Voucher not found."; 
        header("Location: manage_voucher.php");
        exit(); 
    }

    //email content
    if($voucher_details['Discount_Type'] == 'percentage'){
        $discount_text=$voucher_details['Discount_Value'] . '% off';
    } else {
        $discount_text='RM ' . number_format($voucher_details['Discount_Value'],2) . ' off';
    }
    $extra_requirement=[];
    if($voucher_details['Minimum_Spend']>0) {
        $extra_requirement[]="Minimum spend: RM " . number_format($voucher_details['Minimum_Spend'],2);
    }
    if($voucher_details['Max_Discount']>0 && $voucher_details['Discount_Type']=='percentage') {
        $extra_requirement[]="Maximum Discount: RM " . number_format($voucher_details['Max_Discount'],2);
    }
    $text="";
    if(!empty($extra_requirement)) {
        $text='<b>Terms</b><br>';
        $text .=implode('<br>', $extra_requirement);
    }
    $expiry_date="";
    if(!empty($voucher_details['Expiry_Date'])) {
        $expiry_date=date('d-m-Y', strtotime($voucher_details['Expiry_Date']));
    } else {
        $expiry_date="No expiry date";  
    }
    
    $subject="New Voucher Available: " . $voucher_details['Voucher_Name'];
    $body="
    <html>
    <body>
        <h2>Good News! You have received a special voucher from HomeNest.</h2>
        <p><strong>First come first serve!</strong> Only {$voucher_details['Usage_Limit']} vouchers are available.</p>
        <p>You can use {$voucher_details['Usage_Per_User']} times for the voucher.</p>
        <p><strong>Voucher Code: </strong>{$voucher_details['Voucher_Code']}</p>
        <p><strong>Voucher Name: </strong>{$voucher_details['Voucher_Name']}</p>
        <p><strong>Discount: </strong>$discount_text</p>
        $text
        <p><strong>Valid until: </strong>$expiry_date</p>
        <p><strong>let's use it now! <a href='http://localhost/FYP/fyp_project/home.php'>http://localhost/FYP/fyp_project/home.php</strong></a></p>
        </body>
        </html>";

    //get the level user
    $get_user="SELECT email FROM users WHERE role='customer' AND status='active'";
    if(!empty($voucher_details['restricted_to_level_id'])) {
        $get_user .=" AND level_id= " . (int)$voucher_details['restricted_to_level_id'];
    }
    $users=$conn->prepare($get_user);
    $users->execute();
    $users_result=$users->get_result();

    $sent=0;
        while($user=$users_result->fetch_assoc()){
            $result=sendOrderEmail($user['email'], $subject, $body);
            if($result === true){
                $sent++;
            } else {
                error_log("Failed to send voucher email to {$user['email']}: " . $result);
            }
        }
    $_SESSION['message'] = "Email send to $sent eligible users.";
    header("Location: manage_voucher.php");
    exit(); 
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Voucher</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/voucher.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">

<!-- ===================== Voucher ===================== -->
     <div class="header">
        <h1>Manage Vouchers</h1>
        <div class="header-right">
            <div class="user-info"></div>
        </div>
    </div>
 
<div class="content">
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-error" id="errorAlert">
            <?=htmlspecialchars($_SESSION['error']);
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($message)): ?>

    <div class="alert alert-<?php echo ($message == 'error') ? 'error' : 'success'; ?>" id="successAlert">
       <?php 
       if ($message == "activated"): 
            echo "The voucher status has been activated"; 
        elseif ($message == "deactivated"):  
            echo "The voucher status has been deactivated"; 
		elseif ($message == "added successfully"):  
             echo "The voucher has been added successfully!"; 
        elseif ($message == "updated successfully"):  
             echo "The voucher updated successfully!";
        elseif ($message == "error"): 
             echo "There was an error updating the voucher status."; 
        else:
            echo htmlspecialchars($message);
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
                <span class="card-title">Total Active Vouchers</span>
            </div>
            <div class="card-value"><?php echo $totalActiveVoucher; ?></div>
            <div class="card-footer">All active vouchers</div>
        </div>
        <div class="card">
            <div class="card-header"><!--overview of total normal users-->
                <span class="card-title">Total Inactive Vouchers</span>
            </div>
            <div class="card-value"><?php echo $totalInactiveVoucher; ?></div>
            <div class="card-footer">All inactive vouchers</div>
        </div>
    </div> 
<form action="" method="GET" class="filter-form">
            <div class="search-filters">
                <div class="filter-group">
                    <label for="voucherSearch">Search Vouchers</label>
                    <input type="text" id="voucherSearch" 
                    placeholder="Name or code..." class="search-input" 
                    name="search" 
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                
                <button type="submit" class="btn btn-primary btn-search">Search
                </button>
            </div>
            </form>

<form id="voucherForm" method="POST" novalidate>
    <div class="form-row">
        <div class="form-group">
            <label>Voucher Code </label>
            <input type="text" id="voucher_code" name="voucher_code" class="form-control" 
                value="<?= $display_voucher_code ?>" 
                 required>
            <small class="error-message" id="voucherCodeError"></small>
        </div>

        <div class="form-group">
            <label>Voucher Name </label>
            <input type="text" id="voucher_name" name="voucher_name" class="form-control" 
                value="<?= $display_voucher_name ?>" 
                placeholder="Summer Sale" required>
            <small class="error-message" id="voucherNameError"></small>
        </div>

        <div class="form-group">
            <label>Discount Type</label>
            <select name="discount_type" id="discount_type" required>
                <option value=""> Select Type </option>
                <option value="fixed">Fixed Amount (RM)</option>
                <option value="percentage">Percentage (%)</option>
            </select>
            <small class="error-message" id="discountTypeError"></small>
        </div>

        <div class="form-group">
            <label>Discount Value</label>
            <input type="number" id="discount_value" name="discount_value" class="form-control" step="0.01" min="0.01" 
                value="<?= $display_discount_value ?>" 
                 required>
            <small class="error-message" id="discountValueError"></small>
        </div>

        <div class="form-group">
            <label>Minimum Spend (RM)</label>
            <input type="number" id="min_spend" name="min_spend" class="form-control" step="0.01" min="0" 
                value="<?= $display_min_spend ?>" 
                 required>
            <small class="error-message" id="spendError"></small>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Max Discount (RM)</label>
            <input type="number" id="max_discount" name="max_discount" class="form-control" step="0.01" max="999999" 
                value="<?= $display_max_discount ?>" 
                >
            <small class="error-message" id="maxDiscountError"></small>
        </div>

        <div class="form-group">
            <label>Usage Limit</label>
            <input type="number" id="usage_limit" name="usage_limit" class="form-control" 
                value="<?= $display_usage_limit ?>"> 
                
            <small class="error-message" id="usageLimitError"></small>
            
        </div>

        <div class="form-group">
            <label>Usage Per User</label>
            <input type="number" id="usage_user" name="usage_per_user" class="form-control" min="0"
                value="<?= $display_usage_per_user ?>">
            <small class="error-message" id="usageUserError"></small>
            
        </div>

        <div class="form-group">
            <label>Expiry Date</label>
            <input type="date" id="expire_date" name="expiry_date" class="form-control"
            value="<?=$display_expiry_date ?>">
            <small class="error-message" id="expireDateError"></small>
            <small class="form-text">Keep empty to no expire date</small>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Restrict to Level</label> 
            <select name="restricted_to_level_id" id="level">
                <option value="">-- No Restriction --</option>
                <?php foreach ($level_types as $level_id=>$level_name): ?>
                    <option value="<?=$level_id ?>"<?=($display_restricted_to_level == $level_id) ? 'selected': ''?>>
                        <?=htmlspecialchars($level_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="error-message" id="levelError"></small>
        </div>
    </div>

    <button type="submit" id="submitBtn" name="add_voucher" class="btn btn-primary btn-large">Add Voucher</button>
</form>

<div class="voucher-table-container">
<?php if($vouchers->num_rows > 0): ?>
<table class="vouchers-table">
    <tr>
        <th>Voucher Code</th>
        <th>Voucher Name</th>
        <th>Type</th>
        <th>Value</th>
        <th>Used Count</th>   
        <th>Expiry</th>
        <th>View Voucher</th>
        <th>Send Voucher</th>
        <th>status</th>
        <th>View voucher used</th>
    </tr>
    
    <?php while($voucher=$vouchers->fetch_assoc()): ?>
    <tr>
        <td><strong><?= htmlspecialchars($voucher['Voucher_Code']); ?></strong></td>
        <td><?= nl2br(htmlspecialchars(substr($voucher['Voucher_Name'] ?? '',0,30))) ?>...</td>
        <td><?= htmlspecialchars($voucher['Discount_Type']); ?></td>
        <td><?= htmlspecialchars($voucher['Discount_Value']); ?></td>
        <td><?= $voucher['Used_Count']; ?></td>
        <td><?= !empty($voucher['Expiry_Date']) ? date('d-m-Y', strtotime($voucher['Expiry_Date'])): 'No expire date' ?></td>

        
        <td>
            <a href="?view=<?= $voucher['Voucher_ID']; ?>" class="btn btn-view">View</a>
        </td>
        <td><form method="POST" onsubmit="return confirm('Send this voucher to all eligible users?');">
            <input type="hidden" name="voucher_id" value="<?=$voucher['Voucher_ID']?>">
            <button type="submit" name="send_voucher" class="btn btn-primary">Send</button>
        </form>
    </td>
        <td>
        <!--active button change-->
        <?php if (strtolower($voucher['Status']) === 'active'): ?>
            <form method="POST" action="manage_voucher.php">
                <input type="hidden" name="voucher_id" value="<?php echo $voucher['Voucher_ID']; ?>">
                <input type="hidden" name="inactive_voucher" value="inactive">
                <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="btn btn-delete" 
                        onclick="return confirm('Are you sure you want to mark this vouchers as inactive?');">
                        Make Inactive
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="manage_voucher.php">
                <input type="hidden" name="voucher_id" value="<?php echo $voucher['Voucher_ID']; ?>">
                <input type="hidden" name="active_voucher" value="active"> 
                <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="btn btn-primary" 
                        onclick="return confirm('Are you sure you want to activate this vouchers?');">
                        Make Active
                </button>
            </form>
            <?php endif; ?>
        </td>
        <td>
            <a href="voucher_usage.php?voucher_id=<?=$voucher['Voucher_ID'] ?>"class="btn btn-view">View</a> 
        </td>
    </tr>
<?php endwhile; ?>
    </table>
    </div>
    <?php else: ?>
        <div class="no-vouchers-found">
            <h3>No vouchers found</h3>
            <p>Try adjusting your search filters or add a new vouchers.</p>
        </div> 
<?php endif; ?>
</div>

<!-- ===================== VIEW VOUCHER DETAIL ===================== -->
<?php
if(isset($_GET['view'])){
    $id = (int)$_GET['view'];
    $stmt = $conn->prepare("SELECT * FROM vouchers WHERE Voucher_ID=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result && $result->num_rows > 0):
        $row = $result->fetch_assoc();
?>

<div class="modal" onclick="if(event.target === this) location.href='manage_voucher.php'">
    <div class="modal-content">
        <h2>🎟️ Voucher Detail</h2>

        <div class="modal-info">
            <p><b>Code:</b> <strong><?= htmlspecialchars($row['Voucher_Code']); ?></strong></p>
            <p><b>Name:</b> <?= htmlspecialchars($row['Voucher_Name']); ?></p>
            <p><b>Type:</b> <?= htmlspecialchars($row['Discount_Type']); ?></p>
            <p><b>Value:</b> <span class="discount-display"><?= ($row['Discount_Type'] == 'fixed' ? 'RM ' : '') . $row['Discount_Value'] . ($row['Discount_Type'] == 'percentage' ? '%' : ''); ?></span></p>
            <p><b>Minimum Spend:</b> RM <?= number_format($row['Minimum_Spend'], 2); ?></p>
            <p><b>Max Discount:</b> RM <?= number_format($row['Max_Discount'], 2); ?></p>
            <p><b>Usage Limit:</b> <?= (int)$row['Usage_Limit']; ?></p>
            <p><b>Per User Limit:</b> <?= (int)$row['Usage_Per_User']; ?></p>
            <p><b>Times Used:</b> <?= (int)$row['Used_Count']; ?></p>
            <p><b>Expiry Date:</b> <?= !empty($row['Expiry_Date']) ? date('d-m-Y', strtotime($row['Expiry_Date'])) : 'No expiry'; ?></p>
            <p><b>Restricted to Level:</b> <?= !empty($row['restricted_to_level_id']) ? htmlspecialchars($level_types[$row['restricted_to_level_id']] ?? '') : 'No restriction'; ?></p> <!--should be show level type-->
            <p><b>Created:</b> <?= date('d-m-Y H:i', strtotime($row['Created_At'])); ?></p>
        </div>

        <div class="modal-buttons">
            <a href="?edit=<?= $row['Voucher_ID']; ?>" class="btn btn-edit">Edit</a>
            <a href="manage_voucher.php" class="btn btn-secondary btn-large">Close</a>
        </div>
    </div>
</div>

<?php 
    endif;
}
?>

<!-- ===================== EDIT VOUCHER ===================== -->
<?php if(isset($_GET['edit'])):
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM vouchers WHERE Voucher_ID=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result && $result->num_rows > 0):
        $edit = $result->fetch_assoc();
?>

<div class="modal" onclick="if(event.target === this) location.href='manage_voucher.php'">
    <div class="modal-content">
        <h2>Edit Voucher</h2>

        <form method="POST" id="editForm">
            <input type="hidden" name="Voucher_ID" value="<?= $edit['Voucher_ID']; ?>">
            <input type="hidden" name="Status" value="<?= htmlspecialchars($edit['Status']); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Voucher Code</label>
                    <input type="text" name="Voucher_Code" id="edit_voucher_code" value="<?= htmlspecialchars($edit['Voucher_Code']); ?>" required>
                    <small class="error-message" id="editVoucherCodeError"></small>
                </div>

                <div class="form-group">
                    <label>Voucher Name</label>
                    <input type="text" name="Voucher_Name" id="edit_voucher_name" value="<?= htmlspecialchars($edit['Voucher_Name']); ?>" required>
                    <small class="error-message" id="editVoucherNameError"></small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Discount Type</label>
                    <select name="Discount_Type" id="edit_discount_type" required>
                        <option value="fixed" <?= ($edit['Discount_Type'] == 'fixed') ? 'selected' : ''; ?>>Fixed</option>
                        <option value="percentage" <?= ($edit['Discount_Type'] == 'percentage') ? 'selected' : ''; ?>>Percentage</option>
                    </select>
                    <small class="error-message" id="editDiscountTypeError"></small>
                </div>

                <div class="form-group">
                    <label>Discount Value</label>
                    <input type="number" step="0.01" name="Discount_Value" id="edit_discount_value" value="<?= $edit['Discount_Value']; ?>" required>
                    <small class="error-message" id="editDiscountValueError"></small>
                </div>

                <div class="form-group">
                    <label>Minimum Spend</label>
                    <input type="number" step="0.01" name="Minimum_Spend" id="edit_min_spend" value="<?= $edit['Minimum_Spend']; ?>" required>
                    <small class="error-message" id="editSpendError"></small>
                </div>

                <div class="form-group">
                    <label>Max Discount</label>
                    <input type="number" step="0.01" name="Max_Discount" id="edit_max_discount" value="<?= $edit['Max_Discount']; ?>">
                    <small class="error-message" id="editMaxDiscountError"></small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Usage Limit</label>
                    <input type="number" name="Usage_Limit" id="edit_usage_limit" value="<?= $edit['Usage_Limit']; ?>">
                    <small class="error-message" id="editUsageLimitError"></small>
                </div>

                <div class="form-group">
                    <label>Usage Per User</label>
                    <input type="number" name="Usage_Per_User" id="edit_usage_user" value="<?= $edit['Usage_Per_User']; ?>">
                    <small class="error-message" id="editUsageUserError"></small>
                </div>

                <div class="form-group">
                    <label>Expiry Date</label> <!--cannot early that edit date or create date? 如果星期三create的星期五要edit可以换去星期四？应该不可以？-->
                    <input type="date" name="Expiry_Date" id="edit_expire_date" value="<?= $edit['Expiry_Date']; ?>">
                    <small class="error-message" id="editExpireDateError"></small>
                </div>

            </div>

            <div class="form-group">
                <label>Restrict to Level</label>
                <select name="restricted_to_level_id" id="edit_level">
                    <option value="">-- No Restriction --</option>
                    <?php foreach ($level_types as $level_id=>$level_name): ?>
                        <option value="<?=$level_id ?>"<?=($edit['restricted_to_level_id'] == $level_id) ? 'selected': ''?>>
                            <?=htmlspecialchars($level_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="error-message" id="editLevelError"></small>
            </div>

            <div class="modal-buttons">
                <button type="submit" name="update_voucher" class="btn btn-primary btn-large">Update Voucher</button>
                <a href="manage_voucher.php" class="btn btn-secondary btn-large">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php endif; endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('voucherForm');
    const voucher_code=document.getElementById('voucher_code');
    const voucher_name=document.getElementById('voucher_name');
    const discount_type=document.getElementById('discount_type');
    const discount_value=document.getElementById('discount_value');
    const min_spend=document.getElementById('min_spend');
    const max_discount=document.getElementById('max_discount');
    const usage_limit=document.getElementById('usage_limit');
    const usage_user=document.getElementById('usage_user');
    const expire_date=document.getElementById('expire_date');
    const level=document.getElementById('level');
    const submitBtn=document.getElementById('submitBtn');


    // get all error message element
    const voucherCodeError=document.getElementById('voucherCodeError');
    const voucherNameError=document.getElementById('voucherNameError');
    const discountTypeError=document.getElementById('discountTypeError');
    const discountValueError=document.getElementById('discountValueError');
    const spendError=document.getElementById('spendError');
    const maxDiscountError=document.getElementById('maxDiscountError');
    const usageLimitError=document.getElementById('usageLimitError');
    const usageUserError=document.getElementById('usageUserError');
    const expireDateError=document.getElementById('expireDateError');
    const levelError=document.getElementById('levelError');

    //get main form element, input field (edit)
    const edit_form=document.getElementById('editForm');
    const edit_voucher_code=document.getElementById('edit_voucher_code');
    const edit_voucher_name=document.getElementById('edit_voucher_name');
    const edit_discount_type=document.getElementById('edit_discount_type');
    const edit_discount_value=document.getElementById('edit_discount_value');
    const edit_min_spend=document.getElementById('edit_min_spend');
    const edit_max_discount=document.getElementById('edit_max_discount');
    const edit_usage_limit=document.getElementById('edit_usage_limit');
    const edit_usage_user=document.getElementById('edit_usage_user');
    const edit_expire_date=document.getElementById('edit_expire_date');
    const edit_level=document.getElementById('edit_level');


    const editVoucherCodeError=document.getElementById('editVoucherCodeError');
    const editVoucherNameError=document.getElementById('editVoucherNameError');
    const editDiscountTypeError=document.getElementById('editDiscountTypeError');
    const editDiscountValueError=document.getElementById('editDiscountValueError');
    const editSpendError=document.getElementById('editSpendError');
    const editMaxDiscountError=document.getElementById('editMaxDiscountError');
    const editUsageLimitError=document.getElementById('editUsageLimitError');
    const editUsageUserError=document.getElementById('editUsageUserError');
    const editExpireDateError=document.getElementById('editExpireDateError');
    const editLevelError=document.getElementById('editLevelError');


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
    function validateCode() {
        if (voucher_code.value.trim() === '') {
            showError(voucher_code, voucherCodeError, 'Voucher code is required.');
            return false;
        }
        if(!/^[A-Za-z0-9\-_]+$/.test(voucher_code.value.trim())) {
           showError(voucher_code, voucherCodeError, 'Voucher code can only contain letter, number, symbol - and _.'); 
           return false;
        }
        clearError(voucher_code, voucherCodeError);
        return true;
    }

    // voucher name validation
    function validateName() {
        if (voucher_name.value.trim()==='') {
            showError(voucher_name, voucherNameError, 'Voucher name is required.');
            return false;
        }
        clearError(voucher_name, voucherNameError);
        return true;
    }

    function validateDiscountType() {
        if (discount_type.value === '') {
            showError(discount_type, discountTypeError, 'Please select discount type.');
            return false;
        }
        clearError(discount_type, discountTypeError);
        return true;
    }

    function validateDiscountValue() {
        if (isNaN(parseFloat(discount_value.value)) || parseFloat(discount_value.value) <= 0) {
            showError(discount_value, discountValueError, 'Discount value must be greater than 0.');
            return false;
        }
        if(discount_type.value=='percentage' && (parseFloat(discount_value.value))>=100) {
            showError(discount_value, discountValueError, 'Percentage discount must be lower than 100.');
            return false;
        } else {
            if(parseFloat(discount_value.value) > parseFloat(min_spend.value)){
                showError(discount_value, discountValueError, 'Discount value cannot exceed minimum spend.');
                return false;
            }
        }
        
        clearError(discount_value, discountValueError);
        return true;
    }

    function validateSpend() {
        if (min_spend.value.trim() === '') {
            showError(min_spend, spendError, 'minimum spend is required.');
            return false;
        }
        if(isNaN(parseFloat(min_spend.value))) {    
            showError(min_spend, spendError, 'Please enter a valid number.');
            return false;
        }
        if(parseFloat(min_spend.value)<0) {
            showError(min_spend, spendError, 'minimum spend must grather than or equal to 0.');
            return false;
        }
        
        clearError(min_spend, spendError);
        return true;
    }

    function validateMaxDiscount() {
        if(discount_type.value==='percentage') {
            if(isNaN(parseFloat(max_discount.value.trim()))) {
                showError(max_discount, maxDiscountError, 'Please enter a valid number for max discount.');
                return false;
            }
            if(parseFloat(max_discount.value.trim())<=0) {
            showError(max_discount, maxDiscountError, 'Max discount must greater than 0.');
            return false;
            }
        } else{ //fixed, max discount is optional 
            if(max_discount.value.trim() !== '' &&(isNaN(parseFloat(max_discount.value.trim())) || parseFloat(max_discount.value.trim()) <0 )){
                showError(max_discount, maxDiscountError, 'Max discount cannot be negative and must be a valid number.');
                return false;
            }
        }
        clearError(max_discount, maxDiscountError);
        return true;
    }

    function validateUsageLimit() {
        if(usage_limit.value.trim() === '') {
            showError(usage_limit, usageLimitError, 'Usage limit is required.');
            return false;
        }
        if(isNaN(parseInt(usage_limit.value))) {
            showError(usage_limit, usageLimitError, 'Please enter a valid usage limit for the voucher.');
            return false;
        }
        if(parseInt(usage_limit.value) <= 0) {
            showError(usage_limit, usageLimitError, 'The usage limit must be greater than 0.');
            return false;
        }
        clearError(usage_limit, usageLimitError);
        return true;
    }    
    usage_limit.addEventListener('input', function() {
        let number=this.value.split('.')[0]; //only take infront of .
        number=number.replace(/[^0-9]/g, ''); //delete symbol and text
        if(number===''){
            this.value=1;
            return;
        }
        let value=parseInt(number,10);
        this.value=value;
    });

    function validateUsageUser() {
        if(usage_user.value.trim() === '') {
            showError(usage_user, usageUserError, 'Usage per user is required.');
            return false;
        }
        if(isNaN(parseInt(usage_user.value))) {
            showError(usage_user, usageUserError, 'Please enter a valid usage number for every user.');
            return false;
        }
        if(parseInt(usage_user.value) <= 0) {
            showError(usage_user, usageUserError, 'Usage per user must be greater than 0.');
            return false;
        }
        if(parseInt(usage_user.value)>parseInt(usage_limit.value)){
            showError(usage_user, usageUserError, 'Usage per user cannot exceed total usage limit.');
            return false;
        }
        clearError(usage_user, usageUserError);
        return true;
    }    
    usage_user.addEventListener('input', function() {
        let number=this.value.split('.')[0]; //only take infront of .
        number=number.replace(/[^0-9]/g, ''); //delete symbol and text
        if(number===''){
            this.value=1;
            return;
        }
        let value=parseInt(number,10);
        this.value=value;
    });

    function validateExpireDate() {
        if(expire_date.value.trim() === '') {
            clearError(expire_date, expireDateError);
            return true;    
        }
        let today=new Date();
        today.setHours(0,0,0,0);
        let selected=new Date(expire_date.value.trim());
        if(isNaN(selected.getTime())){
            showError(expire_date, expireDateError, 'Invalid date format.');
            return false;
        }
        if(selected < today) {
            showError(expire_date, expireDateError, 'Expire date cannot be in the past.');
            return false;
        }
        clearError(expire_date, expireDateError);
        return true;
    }

    
    // edit validation of code
    function validateEditCode() {
        if (edit_voucher_code.value.trim() === '') {
            showError(edit_voucher_code, editVoucherCodeError, 'Voucher code is required.');
            return false;
        }
        if(!/^[A-Za-z0-9\-_]+$/.test(edit_voucher_code.value.trim())) {
           showError(edit_voucher_code, editVoucherCodeError, 'Voucher code can only contain letter, number, symbol - and _.'); 
           return false;
        }
        clearError(edit_voucher_code, editVoucherCodeError);
        return true;
    }

    function validateEditName() {
        if (edit_voucher_name.value.trim()==='') {
            showError(edit_voucher_name, editVoucherNameError, 'Voucher name is required.');
            return false;
        }
        clearError(edit_voucher_name, editVoucherNameError);
        return true;
    }

    function validateEditDiscountType() {
        if (edit_discount_type.value === '') {
            showError(edit_discount_type, editDiscountTypeError, 'Please select discount type.');
            return false;
        }
        clearError(edit_discount_type, editDiscountTypeError);
        return true;
    }

    function validateEditDiscountValue() {
        if (isNaN(parseFloat(edit_discount_value.value)) || parseFloat(edit_discount_value.value) <= 0) {
            showError(edit_discount_value, editDiscountValueError, 'Discount value must be greater than 0.');
            return false;
        }
        if(edit_discount_type.value=='percentage' && (parseFloat(edit_discount_value.value))>=100) {
            showError(edit_discount_value, editDiscountValueError, 'Percentage discount must be lower than 100.');
            return false;
        } else {
            if(parseFloat(edit_discount_value.value) > parseFloat(edit_min_spend.value)){
                showError(edit_discount_value, editDiscountValueError, 'Discount value cannot exceed minimum spend.');
                return false;
            }
        }
        
        clearError(edit_discount_value, editDiscountValueError);
        return true;
    }

    function validateEditSpend() {
        if (edit_min_spend.value.trim() === '') {
            showError(edit_min_spend, editSpendError, 'Minimum spend is required.');
            return false;
        }
        if(isNaN(parseFloat(edit_min_spend.value))) {    
            showError(edit_min_spend, editSpendError, 'Please enter a valid number.');
            return false;
        }
        if(parseFloat(edit_min_spend.value)<0) {
            showError(edit_min_spend, editSpendError, 'Minimum spend must be greater than or equal to 0.');
            return false;
        }
        
        clearError(edit_min_spend, editSpendError);
        return true;
    }

    function validateEditMaxDiscount() {    
        if(edit_discount_type.value==='percentage') {
            if(isNaN(parseFloat(edit_max_discount.value.trim()))) {
                showError(edit_max_discount, editMaxDiscountError, 'Please enter a valid number for max discount.');
                return false;
            }
            if(parseFloat(edit_max_discount.value.trim())<0) {
            showError(edit_max_discount, editMaxDiscountError, 'Max discount must greater than 0.');
            return false;
            }
        } else{ //fixed 
            if(edit_max_discount.value.trim() !== '' &&(isNaN(parseFloat(edit_max_discount.value.trim())) || parseFloat(edit_max_discount.value.trim()) <0 )){
                showError(edit_max_discount, editMaxDiscountError, 'Max discount cannot be negative and must be a valid number.');
                return false;
            }
        }
        clearError(edit_max_discount, editMaxDiscountError);
        return true;
    }

    function validateEditUsageLimit() {
        if(edit_usage_limit.value.trim() === '') {
            showError(edit_usage_limit, editUsageLimitError, 'Usage limit is required.');
            return false;
        }
        if(isNaN(parseInt(edit_usage_limit.value))) {
            showError(edit_usage_limit, editUsageLimitError, 'Please enter a valid usage limit for the voucher.');
            return false;
        }
        if(parseInt(edit_usage_limit.value) <= 0) {
            showError(edit_usage_limit, editUsageLimitError, 'The usage limit must greater than 0.');
            return false;
        }
        clearError(edit_usage_limit, editUsageLimitError);
        return true;
    } 
    if(edit_usage_limit){
    edit_usage_limit.addEventListener('input', function() {
        let number=this.value.split('.')[0]; //only take infront of .
        number=number.replace(/[^0-9]/g, ''); //delete symbol and text
        if(number===''){
            this.value=1;
            return;
        }
        let value=parseInt(number,10);
        this.value=value;
    });
    }   

    function validateEditUsageUser() {
        if(edit_usage_user.value.trim() === '') {
            showError(edit_usage_user, editUsageUserError, 'Usage per user is required.');
            return false;
        }
        if(isNaN(parseInt(edit_usage_user.value))) {
            showError(edit_usage_user, editUsageUserError, 'Please enter a valid usage number for every user.');
            return false;
        }
        if(parseInt(edit_usage_user.value) <= 0) {
            showError(edit_usage_user, editUsageUserError, 'Usage per user must greater than 0.');
            return false;
        }
        if(parseInt(edit_usage_user.value)>parseInt(edit_usage_limit.value)){
            showError(edit_usage_user, editUsageUserError, 'Usage per user cannot exceed total usage limit.');
            return false;
        }
        clearError(edit_usage_user, editUsageUserError);
        return true;
    }  
    if(edit_usage_user){
    edit_usage_user.addEventListener('input', function() {
        let number=this.value.split('.')[0]; //only take infront of .
        number=number.replace(/[^0-9]/g, ''); //delete symbol and text
        if(number===''){
            this.value=1;
            return;
        }
        let value=parseInt(number,10);
        this.value=value;
    });
    }  

    function validateEditExpireDate() {
        if(edit_expire_date.value.trim() === '') {
            clearError(edit_expire_date, editExpireDateError);
            return true;   
        }
        let today=new Date();
        today.setHours(0,0,0,0);
        let selected=new Date(edit_expire_date.value);
            if(isNaN(selected.getTime())){
            showError(edit_expire_date, editExpireDateError, 'Invalid date format.');
            return false;
        }
        if(selected < today) {
            showError(edit_expire_date, editExpireDateError, 'Expire date cannot be in the past.');
            return false;
        }
        clearError(edit_expire_date, editExpireDateError);
        return true;
    }

    const addDiscountTypeSelect=document.getElementById('discount_type');
    const addMaxDiscountinput=document.getElementById('max_discount');
    if(addDiscountTypeSelect && addMaxDiscountinput){
        function changeAddMaxDiscount() {
            if(addDiscountTypeSelect.value==='fixed'){
                addMaxDiscountinput.disabled=true;
                addMaxDiscountinput.value="";   //clear value
                clearError(addMaxDiscountinput, maxDiscountError)
            } else{
                addMaxDiscountinput.disabled=false;
            }
        }
        addDiscountTypeSelect.addEventListener('change',changeAddMaxDiscount);
        changeAddMaxDiscount();
    }
    
    const editDiscountTypeSelect=document.getElementById('edit_discount_type');
    const editMaxDiscountinput=document.getElementById('edit_max_discount');
    if(editDiscountTypeSelect && editMaxDiscountinput){
        function changeEditMaxDiscount() {
            if(editDiscountTypeSelect.value==='fixed'){
                editMaxDiscountinput.disabled=true;
                editMaxDiscountinput.value="";   //clear value
                clearError(editMaxDiscountinput, editMaxDiscountError)
            } else{
                editMaxDiscountinput.disabled=false;
            }
        }
        editDiscountTypeSelect.addEventListener('change',changeEditMaxDiscount);
        changeEditMaxDiscount();
    }

    //clear error messages while user is typing or selecting
    voucher_code.addEventListener('input', () => clearError(voucher_code, voucherCodeError));
    voucher_name.addEventListener('input', () => clearError(voucher_name, voucherNameError));
    discount_type.addEventListener('input', () => clearError(discount_type, discountTypeError));
    discount_value.addEventListener('input', () => clearError(discount_value, discountValueError));
    min_spend.addEventListener('input', () => clearError(min_spend, spendError));
    max_discount.addEventListener('input', () => clearError(max_discount, maxDiscountError));
    usage_limit.addEventListener('input', () => clearError(usage_limit, usageLimitError));
    usage_user.addEventListener('input', () => clearError(usage_user, usageUserError));
    expire_date.addEventListener('input', () => clearError(expire_date, expireDateError));


    //validate input fields when user leaves the field (blur)
    voucher_code.addEventListener('blur', validateCode);
    voucher_name.addEventListener('blur', validateName);
    discount_type.addEventListener('blur', validateDiscountType);
    discount_value.addEventListener('blur', validateDiscountValue);
    min_spend.addEventListener('blur', validateSpend);
    max_discount.addEventListener('blur', validateMaxDiscount);
    usage_limit.addEventListener('blur', validateUsageLimit);
    usage_user.addEventListener('blur', validateUsageUser);
    expire_date.addEventListener('blur', validateExpireDate);

  
    if(edit_voucher_code){
        edit_voucher_code.addEventListener('input', () => clearError(edit_voucher_code, editVoucherCodeError));
        edit_voucher_code.addEventListener('blur', validateEditCode);
    }
    if(edit_voucher_name) {
        edit_voucher_name.addEventListener('input', () => clearError(edit_voucher_name, editVoucherNameError));
        edit_voucher_name.addEventListener('blur', validateEditName);
    }
    if(edit_discount_type) {
        edit_discount_type.addEventListener('input', () => clearError(edit_discount_type, editDiscountTypeError));
        edit_discount_type.addEventListener('blur', validateEditDiscountType);
    }
    if(edit_discount_value){
        edit_discount_value.addEventListener('input', () => clearError(edit_discount_value, editDiscountValueError));
        edit_discount_value.addEventListener('blur', validateEditDiscountValue);
    }
    if(edit_min_spend){
        edit_min_spend.addEventListener('input', () => clearError(edit_min_spend, editSpendError));
        edit_min_spend.addEventListener('blur', validateEditSpend);
    }
    if(edit_max_discount) {
        edit_max_discount.addEventListener('input', () => clearError(edit_max_discount, editMaxDiscountError));
        edit_max_discount.addEventListener('blur', validateEditMaxDiscount);
    }
    if(edit_usage_limit){
        edit_usage_limit.addEventListener('input', () => clearError(edit_usage_limit, editUsageLimitError));
        edit_usage_limit.addEventListener('blur', validateEditUsageLimit);
    }
    if(edit_usage_user){
        edit_usage_user.addEventListener('input', () => clearError(edit_usage_user, editUsageUserError));
        edit_usage_user.addEventListener('blur', validateEditUsageUser);
    }
    if(edit_expire_date){
        edit_expire_date.addEventListener('input', () => clearError(edit_expire_date, editExpireDateError));
        edit_expire_date.addEventListener('blur', validateEditExpireDate);
    }

    // final validation before submit form
    if(form){
        form.addEventListener('submit', function(e) {
        
        //validate all field and show red color
        const validations = [
            validateCode(),
            validateName(),
            validateDiscountType(),
            validateDiscountValue(),
            validateSpend(),
            validateMaxDiscount(),
            validateUsageLimit(),
            validateUsageUser(),
            validateExpireDate()
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
            validateEditCode(),
            validateEditName(),
            validateEditDiscountType(),
            validateEditDiscountValue(),
            validateEditSpend(),
            validateEditMaxDiscount(),
            validateEditUsageLimit(),
            validateEditUsageUser(),
            validateEditExpireDate()
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
</div>
</body>
</html>