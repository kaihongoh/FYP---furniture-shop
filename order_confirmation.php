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
$get_order_info=$conn->prepare("SELECT * FROM orders WHERE Order_ID=? AND User_ID=?");
$get_order_info->bind_param("ii", $order_id, $user_id);
$get_order_info->execute();
$order=$get_order_info->get_result()->fetch_assoc();

if(!$order) {
    header('Location: home.php');
    exit();
}
//get order item
$get_order_item=$conn->prepare("SELECT *
FROM order_items
WHERE Order_ID=?");

$get_order_item->bind_param("i", $order_id);
$get_order_item->execute();
$items=$get_order_item->get_result();


unset($_SESSION['last_order_id']); 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/order_confirmation.css">
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
            <h1>Order Place Successfully!</h1>
            <p class="order_number">Order number: <span><?=htmlspecialchars($order['Order_Number']) ?></span></p>
            <p>Thank you for your purchase. We appreciate your support and are thrilled you chose us.</p>
        </div>

        <!--order detail-->
        <h2>Order detail</h2>
        <div class="info">
            <div class="info_box">
                <h3>Shipping Information</h3>
                <p><strong>Shipping Name: <?=htmlspecialchars($order['Shipping_Name']) ?></strong></p>
                <p><strong>Phone: <?=htmlspecialchars($order['Shipping_Phone']) ?></strong></p>
                <p><strong>Shipping Address: <?=htmlspecialchars($order['Shipping_Address']) ?></strong></p>
            </div>

            <div class="info_box">
                <h3>Payment Information</h3>
                <p><strong>Paid by: <?=htmlspecialchars($order['Payment_Method']) ?></strong></p>
                <p><strong>Status: <?=htmlspecialchars($order['Payment_Status']) ?></strong></p>
                <p><strong>Date: <?=date("d M Y H:i",strtotime($order['Order_Date'])) ?></strong></p>
            </div>

            <div class="estimate_delivery">
                <?php if($order['Estimated_Delivery_Date']): ?> 
                <p><strong>Estimated Delivery: </strong> <?=date('d M Y',strtotime($order['Estimated_Delivery_Date'])) ?></p>
                <?php endif; ?>
                
                <?php if($order['Actual_Delivery_Date']): ?> <p><strong>Delivery On: </strong> <?=date('d M Y',strtotime($order['Actual_Delivery_Date'])) ?></p>
                <?php endif; ?>
            </div>
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
                    <img src="<?=htmlspecialchars($item['Product_Picture']) ?>"
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
            <span><strong>Subtotal:</strong></span>
            <span><strong>RM <?=number_format($order['Subtotal'],2) ?></strong></span>
        </div>
        <div class="summary_row">
            <span><strong>Shipping Fee:</strong></span>
            <span><strong>RM <?=number_format($order['Shipping_Fee'],2) ?></strong></span>
        </div>
        <div class="summary_row">
            <span><strong>Tax:</strong></span>
            <span><strong>RM <?=number_format($order['Tax_Amount'],2) ?></strong></span>
        </div>
        <?php if($order['Discount_Amount'] > 0): ?>
            <div class="summary_row">
                <span><strong>Discount:</strong></span>
                <span><strong>-RM <?=number_format($order['Discount_Amount'],2) ?></strong></span>
            </div>
            <?php endif; ?>
            <div class="summary_row summary_total">
                <span>Total:</span>
                <span>RM <?=number_format($order['Total_Amount'],2) ?></span>
            </div>
        </div>

        <div class="button">
            <a href="order_history.php" class="btn btn-primary btn-large">View Order History</a>
            <br>
            <a href="product.php" class="btn btn-secondary btn-large">Continue Shopping</a>
        </div>
    </div>

</div>

</body>
</html>
