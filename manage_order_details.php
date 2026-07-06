<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['Order_ID']) || empty($_GET['Order_ID'])) {
    header("Location: manage_order.php");
}
$order_id = $_GET['Order_ID'];

//get order details
$order_details=$conn->prepare("SELECT orders.*, users.User_Name
FROM orders
LEFT JOIN users ON orders.User_ID = users.User_ID
WHERE orders.Order_ID=?");
$order_details->bind_param("i", $order_id);
$order_details->execute();
$details=$order_details->get_result()->fetch_assoc();
$order_details->close();

if(!$details){
    echo "Order not found.";
    exit();
}

//get order items
$order_items=$conn->prepare("SELECT Variant_ID, Product_Name, Product_Picture, Variant_Color, Quantity, Price 
FROM order_items
WHERE Order_ID=? ");
$order_items->bind_param("i", $order_id);
$order_items->execute();
$items=$order_items->get_result();
$order_items->close();



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?=htmlspecialchars($details['Order_Number'])?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_order_details.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="main">
            <a href="javascript:history.back()"class="btn btn-secondary btn-large" style="text-decoration: none;">Back</a>
            <h1>
                Order Details - <?=htmlspecialchars($details['Order_Number'])?>
                <span class="order_status"><?=htmlspecialchars($details['Order_Status']) ?></span>
            </h1>
            
            <div class="info-flex-container">
                <div class="shipping_info">
                    <h2>Shipping Information</h2>
                    <p>Name: <?=htmlspecialchars($details['Shipping_Name'])?></p>
                    <p>Address: <?=htmlspecialchars($details['Shipping_Address'])?></p>
                    <p>Phone: <?=htmlspecialchars($details['Shipping_Phone'])?></p>
                </div>

                <div class="order_info">
                    <h2>Order Info</h2>
                    <p>Order Number: <?=htmlspecialchars($details['Order_Number'])?></p>
                    <p>User Name: <?=htmlspecialchars($details['User_Name'])?></p>
                    <p>Order Date: <?=htmlspecialchars($details['Order_Date'])?></p>
                    <p>Status: <?=htmlspecialchars($details['Order_Status'])?></p>
                    <p>Subtotal: RM <?=number_format($details['Subtotal'], 2)?></p>
                    <p>Shipping Fee: RM <?=number_format($details['Shipping_Fee'], 2)?></p>
                    <p>Tax: RM <?=number_format($details['Tax_Amount'], 2)?></p>
                    <p>Discount: -RM <?=number_format($details['Discount_Amount'], 2)?></p>
                    <p>Total Amount: RM <?=number_format($details['Total_Amount'], 2)?></p>
                
                    <?php if ($details['Estimated_Delivery_Date']): ?>
                        <p>Estimated Delivery Date: <?=date('Y-m-d H:i', strtotime($details['Estimated_Delivery_Date']))?></p>
                    <?php endif; ?>

                    <?php if ($details['Actual_Delivery_Date']): ?>
                        <p>Actual Delivery Date: <?=date('Y-m-d H:i', strtotime($details['Actual_Delivery_Date']))?></p>
                        <?php endif; ?>
                </div>
            </div>

    <h2>Order Items</h2>
    <?php if($items->num_rows>0): ?>
        <table>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>  
                <th>Rating</th> 
                <th>Refunded Quantity</th>  <!--for partially refund/fully refund-->       
            </tr>
            <?php while($item=$items->fetch_assoc()): 
                $item_total=$item['Price'] * $item['Quantity'];

                $variant_id=$item['Variant_ID'];
                //get refunded quantity for the item
                $check_refund=$conn->prepare("SELECT COALESCE(SUM(Refund_Quantity), 0) as refunded_quantity
                FROM order_refunds
                WHERE Order_ID=? AND Variant_ID=? AND Status='Approved'");
                $check_refund->bind_param("ii", $order_id, $variant_id);
                $check_refund->execute();
                $refund=$check_refund->get_result()->fetch_assoc();

                $already_refunded=$refund['refunded_quantity'] ?? 0;
                $check_refund->close();
                ?>
                <tr>
                    <td>
                        <div class="product_info">
                            <img src="../<?=htmlspecialchars($item['Product_Picture']) ?>"
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
                    <td>
                        <?php
                        //get rating for the product variant
                        $get_rating=$conn->prepare("SELECT Rating FROM product_ratings WHERE Order_ID=? AND Variant_ID=?");
                        $get_rating->bind_param("ii", $order_id, $variant_id);
                        $get_rating->execute();
                        $get_rating->bind_result($rating);
                        if($get_rating->fetch()){
                            echo number_format($rating) . " / 5";
                        } else {
                            echo "No rating";
                        }
                        $get_rating->close();
                        ?>
                    </td>
                    <td>
                        <?php if($already_refunded >0): ?>
                            <?= $already_refunded ?>
                        <?php else: ?>
                            -
                            <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <?php else: ?>
            <p>No items found for this order.</p>
            <?php endif; ?>
        </div>
        </div>
    

</body>
</html>