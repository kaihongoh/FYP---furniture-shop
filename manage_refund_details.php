<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");
require_once(__DIR__ . "/../includes/update_level.php");

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}

$refund_id=isset($_GET['refund_id']) ? (int)$_GET['refund_id'] : 0;
if($refund_id <= 0) {
    header("Location: manage_refund.php");
    exit();
}

$errors =[];

//get refund details
$stmt=$conn->prepare("SELECT order_refunds.*, orders.Order_Number,
orders.Customer_Email, product.Product_Name, product_variant.Color, 
order_items.Price AS unit_price
FROM order_refunds
JOIN orders ON order_refunds.Order_ID = orders.Order_ID
LEFT JOIN product_variant ON order_refunds.Variant_ID = product_variant.Variant_ID
LEFT JOIN product ON product_variant.Product_ID = product.Product_ID
LEFT JOIN order_items ON order_items.Order_ID = order_refunds.Order_ID 
AND order_items.Variant_ID = order_refunds.Variant_ID
WHERE order_refunds.Refund_ID =?");

$stmt->bind_param("i", $refund_id);
$stmt->execute();
$refund=$stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$refund) {
    header("Location: manage_refund.php");
    exit();
}

//handle approve or reject
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_id']) && isset($_POST['action'])) {
    $action = $_POST['action'];
    $admin_note=trim($_POST['admin_note'] ?? '');
    $return_action=$_POST['return_action'] ?? '';

    //if approve, check that the all product in order is full refund or partial
    if($action === 'approve') {
        if($refund['type'] == 'product' && empty($return_action)){
            $errors[]="Please select return action.";
        } 
        if(empty($errors)){
            //update record
            $update=$conn->prepare("UPDATE order_refunds SET Status='Approved', Return_Action=?, Admin_Note=?, Processed_At=NOW()
            WHERE Refund_ID=?");
            $update->bind_param("ssi",$return_action, $admin_note, $refund_id);
            $update->execute();
            $update->close();

            if($refund['type'] == 'product') {
                updateSpend($conn, $refund['User_ID'], -$refund['Refund_Amount']); //deduct total spent (not include shipping fee and tax)
                if($return_action == 'Return') {
                //restore stock
                $restore_stock=$conn->prepare("UPDATE product_variant SET Stock = Stock + ? 
                WHERE Variant_ID=?");
                $restore_stock->bind_param("ii", $refund['Refund_Quantity'], $refund['Variant_ID']);
                $restore_stock->execute();
                $restore_stock->close();
                } elseif($return_action == 'No_Return') {
                    //no restore stock, do nothing
                }
            }
            //when variant rating then refund
            //check variant quantity
            $check_variant_qty=$conn->prepare("SELECT Quantity FROM order_items WHERE Order_ID=? AND Variant_ID=?");
            $check_variant_qty->bind_param("ii", $refund['Order_ID'], $refund['Variant_ID']);
            $check_variant_qty->execute();
            $order_variant_qty=$check_variant_qty->get_result()->fetch_assoc();
            $total_buy=$order_variant_qty['Quantity']; //get all variant quantity for that product
            $check_variant_qty->close();

            //get the already refund variant qty
            $check_refund_qty=$conn->prepare("SELECT COALESCE(SUM(Refund_Quantity),0) as total_refunded FROM order_refunds WHERE Order_ID=? AND Variant_ID=? AND Status='Approved' AND type='product'");
            $check_refund_qty->bind_param("ii", $refund['Order_ID'], $refund['Variant_ID']);
            $check_refund_qty->execute();
            $refunded=$check_refund_qty->get_result()->fetch_assoc();
            $total_variant_refunded=$refunded['total_refunded']; //get all variant quantity for that product
            $check_refund_qty->close();

            if($total_variant_refunded>=$total_buy){ 
                //delete rating record
                $delete_rating=$conn->prepare("DELETE FROM product_ratings WHERE Order_ID=? 
                AND Variant_ID=? AND User_ID=?");
                $delete_rating->bind_param("iii", $refund['Order_ID'], $refund['Variant_ID'], $refund['User_ID']);
                $delete_rating->execute();
                $delete_rating->close();
            }
            //get order_id
            $get=$conn->prepare("SELECT Order_ID FROM order_refunds WHERE Refund_ID=?");
            $get->bind_param("i", $refund_id);
            $get->execute();
            $result=$get->get_result()->fetch_assoc();
            $order_id=$result['Order_ID'] ?? 0; 
            $get->close();

            //get total ordered quantity in that order
            $order_quantity=$conn->query("SELECT SUM(Quantity) as total_qty
            FROM order_items
            WHERE Order_ID=$order_id");
            $order_qty=$order_quantity->fetch_assoc();
            $total_order=$order_qty['total_qty'];

            //get total refund quantity in that order
            $refund_quantity=$conn->query("SELECT SUM(Refund_Quantity) as total_refund
            FROM order_refunds
            WHERE Order_ID=$order_id AND Status='Approved' AND type='product'");
            $refund_qty=$refund_quantity->fetch_assoc();
            $total_refunded=$refund_qty['total_refund'];

            //if total refunded quantity is >= ordered quantity, update order status to fully refunded
            if($total_refunded >= $total_order) {
                $order_status='Fully_Refunded';

                //check shipping fee already refund or not
                $check_shipping=$conn->prepare("SELECT 1 FROM order_refunds WHERE Order_ID=? AND type='shipping'");
                $check_shipping->bind_param("i", $order_id);
                $check_shipping->execute();
                $shipping_exists=$check_shipping->get_result()->num_rows > 0;
                $check_shipping->close();

                //check tax already refund or not
                $check_tax=$conn->prepare("SELECT 1 FROM order_refunds WHERE Order_ID=? AND type='tax'");
                $check_tax->bind_param("i", $order_id);
                $check_tax->execute();
                $tax_exists=$check_tax->get_result()->num_rows > 0;
                $check_tax->close();

                //get shipping fee and tax from order
                $result=$conn->query("SELECT Tax_Amount, Shipping_Fee
                FROM orders WHERE Order_ID=$order_id");
                $order_info=$result->fetch_assoc();

                $tax_amount=$order_info['Tax_Amount'];
                $shipping_fee=$order_info['Shipping_Fee'];
                
                //insert shipping fee order refund (when amount>0 and never refund)
                if($shipping_fee > 0 && !$shipping_exists) {
                    $stmt=$conn->prepare("INSERT INTO order_refunds 
                    (Order_ID, User_ID, Refund_Quantity, Refund_Amount, Reason, type, Status, Processed_At)
                    VALUES(?, ?, 1, ?, 'Auto refund shipping fee', 'shipping', 'Approved', NOW())");
                    $stmt->bind_param("iid", $order_id, $refund['User_ID'], $shipping_fee);
                    $stmt->execute();
                    $stmt->close();
                }
                //insert tax order refund (when amount>0 and never refund)
                if($tax_amount > 0 && !$tax_exists) {
                    $stmt=$conn->prepare("INSERT INTO order_refunds 
                    (Order_ID, User_ID, Refund_Quantity, Refund_Amount, Reason, type, Status, Processed_At)
                    VALUES(?, ?, 1, ?, 'Auto refund tax', 'tax', 'Approved', NOW())");
                    $stmt->bind_param("iid", $order_id, $refund['User_ID'], $tax_amount);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                    $order_status='Partially_Refunded';
                }
    
            $update_order=$conn->prepare("UPDATE orders SET Order_Status=? WHERE Order_ID=?");
            $update_order->bind_param("si", $order_status, $order_id);
            $update_order->execute();
            $update_order->close();

            header("Location: manage_refund.php");
            exit();
        }



            
    } elseif ($action === 'reject') {
        //if reject
        $update=$conn->prepare("UPDATE order_refunds SET Status='Rejected', Admin_Note=? WHERE Refund_ID=?");
        $update->bind_param("si", $admin_note, $refund_id);
        $update->execute();
        $update->close();
        //check that order have any other pending refund request, if not update order status back to completed
        $check_pending=$conn->prepare("SELECT COUNT(*) as pending_count FROM order_refunds 
        WHERE Order_ID=? AND Status ='Pending'");
        $check_pending->bind_param("i", $refund['Order_ID']);
        $check_pending->execute();
        $pending_result=$check_pending->get_result()->fetch_assoc();

        //check approve
        $check_approved=$conn->prepare("SELECT COUNT(*) as approved_count FROM order_refunds
        WHERE Order_ID=? AND Status='Approved'");
        $check_approved->bind_param("i", $refund['Order_ID']);
        $check_approved->execute();
        $approved_result=$check_approved->get_result()->fetch_assoc();

        if($pending_result['pending_count'] == 0) {
            if($approved_result['approved_count'] > 0) {
                $new_status='Partially_Refunded';
            } else {
                $new_status='Completed';
            }
            $update_order=$conn->prepare("UPDATE orders SET Order_Status=? WHERE Order_ID=?");
            $update_order->bind_param("si", $new_status, $refund['Order_ID']);
            $update_order->execute();
            $update_order->close();
        }
        header("Location: manage_refund.php");
        exit();
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Refund Details</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_refund_details.css">
</head> 
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Refund Details</h1>
            <div class="header-right">
                <div class="user-info">
                </div>
            </div>
        </div>

        <div class="content">
            <div class="action-bar">
                <a href="manage_refund.php" class="btn btn-secondary btn-large" style="text-decoration: none;">
                    Back to Refund List
                </a>
            </div>
            
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
            <div class="refund-form-container">
                <div class="form-group">
                    <label class="form-label">Order Number:</label> 
                    <div><?=htmlspecialchars($refund['Order_Number']) ?></div>
                </div>
            

                <div class="form-group">
                    <label class="form-label">User ID:</label>
                    <div><?=htmlspecialchars($refund['User_ID']) ?></div>
                </div>

                <div class="form-group"> 
                    <label class="form-label">Product Name </label>
                    <div>
                        <?php if($refund['type'] == 'shipping'){
                            echo "Shipping Fee";
                        } elseif ($refund['type'] == 'tax'){
                            echo "Tax";
                        } else{
                            echo htmlspecialchars($refund['Product_Name']);
                        }
                        ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Color </label>
                    <div>
                        <?php if($refund['type'] == 'shipping' || $refund['type'] == 'tax'){
                            echo "-";
                        } else{
                            echo htmlspecialchars($refund['Color']);
                        }
                        ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Refund Quantity </label>
                    <div>
                        <?php if($refund['type'] == 'product') {
                            echo htmlspecialchars($refund['Refund_Quantity']);
                        } else {
                            echo '1';
                        }
                        ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Refund Amount</label>
                    <div>RM <?=number_format($refund['Refund_Amount'], 2) ?></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <div><?=nl2br(htmlspecialchars($refund['Reason'] ?? '')) ?></div>
                </div>

                <?php if($refund['Refund_Image']): ?>
                <div class="form-group">
                    <label class="form-label">Supporting Image</label>
                    <div>
                        <img src="../<?=htmlspecialchars($refund['Refund_Image']) ?>" class="refund-image" alt="refund image">
                    </div>
                </div>
                <?php endif; ?>

    <!--display the return action and admin note-->
            <?php if($refund['type'] == 'product' && !empty($refund['Return_Action'])): ?>
                <div class="form-group">
                    <label class="form-label">Return Action</label>
                    <div><?=htmlspecialchars($refund['Return_Action']) ?></div>
                </div>
                <?php endif; ?>

            <?php if(!empty($refund['Admin_Note'])): ?>
                <div class="form-group">
                    <label class="form-label">Admin Note</label>
                    <div><?=htmlspecialchars($refund['Admin_Note']) ?></div>
                </div>
            <?php endif; ?>

            <?php if($refund['Status'] === 'Pending'): ?>
                <form method="POST" id="refundForm" novalidate>
                <input type="hidden" name="refund_id" value="<?=$refund['Refund_ID']?>">
                <?php if($refund['type'] == 'product'): ?>
                    <div class="form-group">
                        <label class="form-label">Return Action</label>
                        <select name="return_action" id="actionSelect" required>
                            <option value="">Select Action</option>
                            <option value="Return">Return Item</option>
                            <option value="No_Return">Do Not Return</option>
                        </select>
                        <small class="error-message" id="actionError"></small>
                    </div>
                    <?php endif; ?>


                    <div class=form-group>
                        <label class="form-label" for="admin_note">Admin Note (optional)</label>                  <!--auto display back the admin note if backend valdiation--> 
                        <textarea name="admin_note" rows="3" id="admin_note" class="form-control"><?=htmlspecialchars($_POST['admin_note'] ?? $refund['Admin_Note'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="action" value="approve" id="submitBtn" class="btn btn-primary btn-large">Approve Refund</button>
                        <button type="submit" name="action" value="reject" class="btn btn-delete btn-large">Reject Refund</button>
                    </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</div>

            

</body>
</html>