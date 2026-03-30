<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if (!isset($_GET['order_id'])) {
    header("Location: order_history.php");
    exit();
}

$order_id=(int)$_GET['order_id'];
$user_id=$_SESSION['user_id'];

//get order info, prevent view others order
$stmt=$conn->prepare ("SELECT * FROM orders WHERE Order_ID=? AND User_ID=?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order=$stmt->get_result()->fetch_assoc();

if(!$order) {
    header("Location: order_history.php");
    exit();
}

//get order item 
    $stmt2=$conn->prepare ("SELECT order_items.Product_Name, order_items.Product_Picture, 
    order_items.Variant_Color, order_items.Quantity, order_items.Price
    FROM order_items
    WHERE order_items.Order_ID=?");

    $stmt2->bind_param("i", $order_id);
    $stmt2->execute();
    $items=$stmt2->get_result();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
</head>
<body>
    <div class="container">
        <a href="order_history.php" class="back_btn">Back to orders</a>
        <h1>Order Details</h1>

    <!--user之后换了这里也不会更改 因为是history-->
    <div class="order_section">
        <div class="order_header">
            <span class="order_number">Order #<?=htmlspecialchars($order['Order_Number']) ?></span>
            <span class="order_status"><?=htmlspecialchars($order['Order_Status']) ?></span>
        </div>

        <div class="info">
            <div class="info_box">
                <h3>Shipping Information</h3>
                <p>Shipping Name: <?=htmlspecialchars($order['Shipping_Name']) ?></p>
                <p>Phone: <?=htmlspecialchars($order['Shipping_Phone']) ?></p>
                <p>Shipping Address: <?=htmlspecialchars($order['Shipping_Address']) ?></p>
            </div>
        </div>

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
        while($item=$items->fetch_assoc()):
            $item_total=$item['Price'] * $item['Quantity'];
            ?>
            <tr>
                <td>

                    <div class="product_info">
                        <img src="uploads/<?=htmlspecialchars($item['Product_Picture']) ?>"
                        alt="<?=htmlspecialchars($item['Product_Name']) ?>" class="product_img">

                        <div class="product_details">
                            <h4><?=htmlspecialchars($item['Product_Name']) ?></h4>
                            <p>Color: <?=htmlspecialchars($item['Variant_Color']) ?></p>
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
        
        <!--payment detail--> 
        <div class="info">        
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


</body>
</html>