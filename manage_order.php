<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id=(int)$_POST['order_id'];
    $est_date=$_POST['estimated_date'] ?? null;
    $act_date_raw=$_POST['actual_date'] ?? null;
    $act_date=null;
    
    if($act_date_raw) {
        //convert to datetime format
        $act_date=date('Y-m-d H:i', strtotime($act_date_raw));
    }

    $get_order_info=$conn->prepare("SELECT Order_Date, Order_Status FROM orders WHERE Order_ID=?");
    $get_order_info->bind_param("i", $order_id);
    $get_order_info->execute();
    $current_order=$get_order_info->get_result()->fetch_assoc();
    $get_order_info->close();

    $errors=[];
    if(!$current_order) {
        $errors[]="Order not found.";
    } else {
        if($est_date && date('Y-m-d', strtotime($est_date)) < date('Y-m-d', strtotime($current_order['Order_Date']))) {
        $errors[]="Estimate delivery date cannot earlier than order date. Please try again.";
        }
        if($act_date) {
            //only allow when shipped
            if($current_order["Order_Status"] !== 'Shipped') {
                $errors[]= "Actual delivery date can only be set when order is shipped.";
            }
            else if ($act_date && strtotime($act_date) < strtotime($current_order['Order_Date'])) { 
                $errors[]= "Actual delivery date cannot earlier than order date. Please try again.";
            }
        }
        if($current_order['Order_Status'] === 'Completed' || $current_order['Order_Status'] === 'Refund_Requested' || $current_order['Order_Status'] === 'Partially_Refunded' || $current_order['Order_Status'] === 'Fully_Refunded'){
            if($est_date){
                $errors[]="Cannot change estimated delivery date for completed orders.";
            }
        }
    }
    if(empty($errors)) {
    $update=$conn->prepare("UPDATE orders SET Estimated_Delivery_Date=?,
    Actual_Delivery_Date=?
    WHERE Order_ID=?");
    $update->bind_param("ssi", $est_date, $act_date, $order_id);

    if($update->execute()) {
        $_SESSION['success']="Delivery dates updated successfully.";
        } else {
            $_SESSION['message']="Updated failed. Please try again.";
        }
        $update->close();   

        //go back to same page while keeping search filter content
        $search = [];
        if (isset($_GET['orderNo']) && !empty($_GET['orderNo'])) {
            $search['orderNo'] = $_GET['orderNo'];
        }

        
        $redirect_url = "manage_order.php";
        if (!empty($search)) {
            $redirect_url .= "?" . http_build_query($search);
        }
        
        header("Location: $redirect_url");
        exit();
    }


}



//count total completed orders
$countCompletedOrders = $conn->prepare("SELECT COUNT(*) FROM orders WHERE Order_Status = 'completed'");
$countCompletedOrders->execute();
$countCompletedOrders->bind_result($totalCompletedOrders);
$countCompletedOrders->fetch();
$countCompletedOrders->close();

//count total partially refunded orders
$countPartialOrders = $conn->prepare("SELECT COUNT(*) FROM orders WHERE Order_Status = 'partially_refunded'");
$countPartialOrders->execute();
$countPartialOrders->bind_result($totalPartialOrders);
$countPartialOrders->fetch();
$countPartialOrders->close();

$filter = "";
//filter by order number
if (isset($_GET['orderNo']) && !empty($_GET['orderNo'])) {
    $filter .= " AND orders.Order_Number LIKE '%".$_GET['orderNo']."%'";
}

//retrieve order list 
$orderQuery = "SELECT Order_ID, Order_Number, User_ID, Total_Amount, Order_Status, Estimated_Delivery_Date, Actual_Delivery_Date, Order_Date
FROM orders WHERE 1=1 $filter ";

$orderQuery .= " ORDER BY orders.Order_Date DESC";

$ordersResult = $conn->query($orderQuery);

$edit=null;
if(isset($_GET['edit'])){
    $edit_id=(int)$_GET['edit'];
    $stmt=$conn->prepare("SELECT Order_ID, Order_Number, Order_Status, Order_Date, 
    Estimated_Delivery_Date, Actual_Delivery_Date
    FROM orders
    WHERE Order_ID=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit=$stmt->get_result()->fetch_assoc();
    $stmt->close();

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



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - manage order</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_order.css">

</head>
<body>

    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Orders Management</h1>
            <div class="header-right">
                <div class="user-info"></div>
            </div>
        </div>

        <div class="content">
<?php if (!empty($message)): ?>

    <div class="alert alert-<?php echo ($message == 'error') ? 'error' : 'success'; ?>" id="successAlert">
       <?php echo htmlspecialchars($message) ?>
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

<!--for update status-->
<div class="alert alert-error" id="validationError" style="display:none;"></div>

<h2 class="page-title">Orders Overview</h2>
            <div class="cards">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Total Compeleted Orders</span>
                    </div>
                    <div class="card-value"><?php echo $totalCompletedOrders; ?></div>
                    <div class="card-footer">All completed orders</div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Total Partially Refunded Orders</span>
                    </div>
                    <div class="card-value"><?php echo $totalPartialOrders; ?></div>
                    <div class="card-footer">All partially refunded orders</div>
                </div>
            </div>
<form action="" method="GET" class="filter-form">
            <div class="search-filters">
                <div class="filter-group">
                    <label for="orderSearch">Search Orders</label>
                    <input type="text" id="orderSearch" 
                    placeholder="Enter Order ID..." class="search-input" 
                    name="orderNo" 
                    value="<?php echo isset($_GET['orderNo']) ? htmlspecialchars($_GET['orderNo']) : ''; ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-search">Search</button>
            </div>
        </form>

    <div class="order-table-container">
        <?php if($ordersResult->num_rows>0): ?>
    <table class="order-table">
        <tr>
            <th>Order No</th>
            <th>User ID</th>
            <th>Total</th>
            <th>Status</th>
            <th>Estimate Delivery Date</th>
            <th>Actual Delivery Date</th>
            <th>Action</th>
            <th>View Order</th>
        </tr>
        <?php while ($order=$ordersResult->fetch_assoc()): ?>
            <tr>
                <td><?= $order['Order_Number'] ?> </td>
                <td><?= $order['User_ID'] ?> </td>
                <td>RM <?= $order['Total_Amount'] ?> </td>
                <td>
                <form method="POST" action="update_order_status.php" onsubmit="return validateStatus(this, '<?=$order['Order_Status']?>')">
                    <input type="hidden" name="order_id" value="<?=$order['Order_ID'] ?>">
                    <select name="status">
                        <option value="Pending" <?=$order['Order_Status']=='Pending'?'selected':'' ?>>Pending</option>
                        <option value="Paid" <?=$order['Order_Status']=='Paid'?'selected':'' ?>>Paid</option>
                        <option value="Processing" <?=$order['Order_Status']=='Processing'?'selected':'' ?>>Processing</option>
                        <option value="Shipped" <?=$order['Order_Status']=='Shipped'?'selected':'' ?>>Shipped</option>
                        <option value="Completed" <?=$order['Order_Status']=='Completed'?'selected':'' ?>>Completed</option>
                        <option value="Refund_Requested" <?=$order['Order_Status']=='Refund_Requested'?'selected':'' ?> disabled>Refund Requested</option>
                        <option value="Partially_Refunded" <?=$order['Order_Status']=='Partially_Refunded'?'selected':'' ?> disabled>Partially Refunded</option>
                        <option value="Fully_Refunded" <?=$order['Order_Status']=='Fully_Refunded'?'selected':'' ?> disabled>Fully Refunded</option>

                    </select>
                    <button type="submit" class="btn btn-primary">Update</button>
                </form>    
                <td><?= $order['Estimated_Delivery_Date'] ?: '-' ?> </td>
                <td class="actual-date"><?= $order['Actual_Delivery_Date'] ? date('Y-m-d H:i', strtotime($order['Actual_Delivery_Date'])) : '-' ?> </td>
                <td><a href="?edit=<?= $order['Order_ID'] ?>" class="btn btn-edit">Edit</a></td>
                <td><a href="manage_order_details.php?Order_ID=<?= $order['Order_ID'] ?>" class="btn btn-view">View</a></td>
            </tr>
            <?php endwhile; ?>
    </table>
    <?php else: ?>
        <div class="no-order-found">
            <h3>No orders found</h3>
            <p>Try adjusting your search filters.</p>
        </div>
        <?php endif; ?>
        </div>
        </div>

<!--edit delivery date-->
<?php if(!empty($edit)): ?>
    <div class="modal" onclick="if(event.target === this) location.href='manage_order.php'">
        <div class="modal-content">
            <h2>Edit Delivery Dates</h2>
            <form method="POST" id="deliveryForm">
                <input type="hidden" name="order_id" value="<?= $edit['Order_ID']; ?>">
                
                <div class="form-group">
                <label>Order Number</label>
                <input type="text" value="<?= htmlspecialchars($edit['Order_Number']); ?>"readonly>
                </div>
                
                <div class="form-group">
                <label>Estimatd Delivery Date</label>
                <input type="date" class="form-control" id="estimate_date" name="estimated_date" value="<?=$edit['Estimated_Delivery_Date'] ?>">
                <small class="error-message" id="estimateError"></small>
                </div>

                <div class="form-group">
                <label>Actual Delivery Date</label>
                <input type="datetime-local" class="form-control" id="actual_date" name="actual_date" value="<?=$edit['Actual_Delivery_Date'] ? date('Y-m-d\TH:i', strtotime($edit['Actual_Delivery_Date'])) : '' ?>"
                <?=$edit['Order_Status'] !== 'Shipped' ? 'disabled' : '' ?>>
                <small class="error-message" id="actualError"></small>
                
                <?php if($edit['Order_Status'] !== 'Shipped'): ?>
                <p class="hint">Can only edit Acutal Delivery Date when order is shipped.</p>
                <?php endif; ?>
                </div>

            <div class="modal-buttons">
                <button type="submit" name="update_delivery" class="btn btn-primary btn-large">Update</button>
                <a href="manage_order.php" class="btn btn-secondary btn-large">Cancel</a>
            </div>

            </form>
    </div>
    </div>
    <?php endif; ?>
<script>
    function showValidationError(message){
        const errorStatus=document.getElementById('validationError');
        if(errorStatus){
            errorStatus.innerText = message;
            errorStatus.style.display = 'block';
        }
    }

    function validateStatus(form, currentStatus) {
        let newStatus = form.status.value;

        const flow={
            'Pending':['Paid'],
            'Paid':['Processing'],
            'Processing':['Shipped'],
            'Shipped':['Completed'],
            'Completed':['Refund_Requested'],
            'Refund_Requested':['Partially_Refunded', 'Fully_Refunded'],
            'Partially_Refunded':['Fully_Refunded'],
            'Fully_Refunded':[]
        };

        if(currentStatus == newStatus) {
            showValidationError("You have select the same status. Please choose a different status to update.");
            return false;
        }

        if(!flow[currentStatus] || !flow[currentStatus].includes(newStatus)) {
            showValidationError("Invalid status. Please follow the flow.");
            return false;
        }

        //check completed must have actual delivery date
        if(newStatus === 'Completed') {
            const actDate=form.closest('tr').querySelector('.actual-date').innerText;
            if(actDate === '-') {
                showValidationError("Cannot set status to Completed when Actual Delivery Date is not set.");
                return false;
            }
        }
        return true;
    }

    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('deliveryForm');
    const estimate=document.getElementById('estimate_date');
    const actual=document.getElementById('actual_date');

    // get all error message element
    const estimateError=document.getElementById('estimateError');
    const actualError=document.getElementById('actualError');

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

    function validateEstimateDate(){
        if(estimate.value===''){
            clearError(estimate, estimateError);
            return true;
        }
        const orderStatus="<?=$edit ? $edit['Order_Status'] : '' ?>";
        if(orderStatus==='Completed' || orderStatus==='Refund_Requested' || orderStatus==='Partially_Refunded' || orderStatus==='Fully_Refunded'){
            showError(estimate, estimateError, "Cannot change estimated delivery date when the order is completed.");
            return false;
        }

        const orderDate="<?=$edit ? date('Y-m-d', strtotime($edit['Order_Date'])) : '' ?>";

        if(orderDate && estimate.value.trim()<orderDate){
            showError(estimate, estimateError, "Estimate delivery date cannot earlier than order date. Please try again.");
            return false;
        }
        clearError(estimate, estimateError);
        return true;
    }

    function validateActualDate() {
        if(actual.disabled){ //if not shipped
            clearError(actual, actualError);
            return true; 
        }
        if(actual.value.trim()===''){
            clearError(actual, actualError);
            return true;
        }
        
        let actualDateTime=actual.value.trim().replace('T', ' '); //turn date time to 2026-12-21 14:45
        const orderDateTime="<?=$edit ? date('Y-m-d H:i', strtotime($edit['Order_Date'])) : '' ?>";

        if(orderDateTime && actualDateTime < orderDateTime){
            showError(actual, actualError, "Actual delivery date cannot earlier than order date. Please try again.");
            return false;
        }

        clearError(actual, actualError);
        return true;
    }

    
    if(estimate){
        estimate.addEventListener('input', () => clearError(estimate, estimateError));    //clear error messages while user is typing or selecting
        estimate.addEventListener('blur', validateEstimateDate);    //validate input fields when user leaves the field (blur)
    }

    if(actual){
        actual.addEventListener('input', () => clearError(actual, actualError));
        actual.addEventListener('blur', validateActualDate);
    }
    

        // final validation before submit form
    if(form){
        form.addEventListener('submit', function(e) {
        
        //validate all field and show red color
        const validations = [
            validateEstimateDate(),
            validateActualDate()
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
            successAlert.style.transition = 'opacity 0.5s';
            successAlert.style.opacity = '0';
            setTimeout(() => {
                successAlert.style.display = 'none';
            }, 500);
        }, 5000);
    }
});
                
</script>
</body>
</html>