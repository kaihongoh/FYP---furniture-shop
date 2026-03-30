<?php
session_start();
require_once 'includes/config.php';

$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if(!isset($_GET['order_id']) || !isset($_SESSION['last_order_id'])) {
    header('Location: home.php');
    exit ();
}

$order_id=(int)$_GET['order_id'];
$user_id=$_SESSION['user_id'];

if($order_id != $_SESSION['last_order_id']) {
    header('Location: home.php');
    exit ();
}
//get order info
$stmt=$conn->prepare("SELECT * FROM orders WHERE Order_ID=? AND User_ID=?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order=$stmt->get_result()->fetch_assoc();

if(!$order) {
    header('Location: home.php');
    exit();
}
//get order item
$stmt2=$conn->prepare("SELECT *
FROM order_items
WHERE Order_ID=?");

$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items=$stmt2->get_result();


unset($_SESSION['last_order_id']); 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
</head>
<body>
    <!--show success alert message-->
    <?php if ($success): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="order_confirmation_container">
        <div class="success_card">
            <div class="success_icon">✓</div>
            <h1>Order Place Successfully!</h1>
            <p class="order_number">Order number: <span><?=htmlspecialchars($order['Order_Number']) ?></span></p>
            <p>Thank you for your purchase. We appreciate your support and are thrilled you chose us.</p>
        </div>

        <!--order detail-->
        <h2>Order detail</h2>
        <div class="info">
            <div class="info_box">
                <h3>Shipping Information</h3>
                <p>Shipping Name: <?=htmlspecialchars($order['Shipping_Name']) ?></p>
                <p>Phone: <?=htmlspecialchars($order['Shipping_Phone']) ?></p>
                <p>Shipping Address: <?=htmlspecialchars($order['Shipping_Address']) ?></p>
            </div>

            <div class="info_box">
                <h3>Payment Information</h3>
                <p><strong>Paid by: </strong><?=htmlspecialchars($order['Payment_Method']) ?></p>
                <p><strong>Status: </strong><?=htmlspecialchars($order['Payment_Status']) ?></p>
                <p><strong>Date: </strong><?=date("d M Y H:i",strtotime($order['Order_Date'])) ?></p>
            </div>
            <?php 
            if($order['Estimated_Delivery_Date']): ?> 
            <p><strong>Estimated Delivery: </strong> <?=date('d M Y',strtotime($order['Estimated_Delivery_Date'])) ?></p>
            <?php endif; ?>
            
            <?php 
            if($order['Actual_Delivery_Date']): ?> <p><strong>Delivery On: </strong> <?=date('d M Y',strtotime($order['Actual_Delivery_Date'])) ?></p>
            <?php endif; ?>
        </div>

        <h3 style="margin-bottom: 15px;">Product Ordered</h3>

    <table>
        <thead>
        <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $display_subtotal=0;
        while($item=$items->fetch_assoc()):
            $item_total=$item['Price'] * $item['Quantity'];
            $display_subtotal +=$item_total;
        ?>
        <tr>
            <td>
                <div class="product_info">
                    <img src="uploads/<?=htmlspecialchars($item['Product_Picture']) ?>"
                    alt="<?=htmlspecialchars($item['Product_Name']) ?>" class="product_img">
                    
                    <div class="product_details">
                        <strong><?=htmlspecialchars($item['Product_Name']) ?></strong><!--这个color是跟着order_items? order_items是Varaint_Color-->
                        <p style="color: #666; font-size: 0.9rem;">Color: <?=htmlspecialchars($item['Variant_Color']) ?></p>
                    </div>
                </div>
            </td>
            <td>RM <?=number_format($item['Price'],2) ?></td>
            <td><?=$item['Quantity']?></td>
            <td>RM <?=number_format($item_total,2) ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

    <!--order summary-->
    <div class="summary">
        <div class="summary_row">
            <span>Subtotal:</span>
            <span>RM <?=number_format($order['Subtotal'],2) ?></span>
        </div>
        <div class="summary_row">
            <span>Shipping Fee:</span>
            <span>RM <?=number_format($order['Shipping_Fee'],2) ?></span>
        </div>
        <div class="summary_row">
            <span>Tax:</span>
            <span>RM <?=number_format($order['Tax_Amount'],2) ?></span>
        </div>
        <?php if($order['Discount_Amount'] > 0): ?>
            <div class="summary_row">
                <span>Discount:</span>
                <span>-RM <?=number_format($order['Discount_Amount'],2) ?></span>
            </div>
            <?php endif; ?>
            <div class="summary_row summary_total">
                <span>Total:</span>
                <span>RM <?=number_format($order['Total_Amount'],2) ?></span>
            </div>
        </div>
    </div>

    <div class="button">
        <a href="order_history.php" class="btn-primary">View Order History</a>
        <a href="home.php" class="btn-secondary">Continue Shopping</a>
    </div>
</div>

</body>
</html>