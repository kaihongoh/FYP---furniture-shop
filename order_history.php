<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id=$_SESSION['user_id'];
$status=$_GET['Order_Status'] ?? 'all';

$where="";

switch ($status) {
    case 'to_ship':
        $where="AND Order_Status IN ('Paid', 'Processing')";
        break;
    case 'to_receive':
        $where="AND Order_Status='Shipped'";
        break;    
    case 'completed':
        $where="AND Order_Status='Completed'"; //少delivered?
        break;
    case 'cancelled':
        $where="AND Order_Status='Cancelled'";
        break;
        
    default:
        $where="";
}

//may n+1 query problem
$stmt=$conn->prepare ("SELECT * 
FROM orders 
WHERE User_ID=? 
$where ORDER BY Order_Date DESC");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$orders=$stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History</title>
</head>
<body>
    <h1>Order History</h1>
    <div class="order_tabs">
        <a href="?Order_Status=all">All</a>
        <a href="?Order_Status=to_ship">To Ship</a>
        <a href="?Order_Status=to_receive">To Receive</a>
        <a href="?Order_Status=completed">Completed</a>
        <a href="?Order_Status=cancelled">Cancelled</a>
    </div>

    <?php
    if($orders->num_rows === 0): ?>
    <p>No order found</p>
    <?php else: ?>
        <?php
        while($order=$orders->fetch_assoc()): ?>

<div class="order_card">
    <div class="order_header">
        <span>Order #<?=htmlspecialchars($order['Order_Number']) ?></span>
        <span class="order_status"><?=htmlspecialchars($order['Order_Status']) ?></span>
    </div>

    <?php 
    $stmt2=$conn->prepare ("SELECT order_items.Product_Name, order_items.Product_Picture, 
    order_items.Variant_Color, order_items.Quantity, order_items.Price
    FROM order_items
    WHERE order_items.Order_ID=?");

    $stmt2->bind_param("i", $order['Order_ID']);
    $stmt2->execute();
    $items=$stmt2->get_result();

    while($item=$items->fetch_assoc()):
    ?>

    <div class="order_product">
        <img src="uploads/<?=htmlspecialchars($item['Product_Picture']) ?>"
        alt="<?=htmlspecialchars($item['Product_Name']) ?>" class="product_img">
        <div class="product_info">
            <div class="product_name">
                <?=htmlspecialchars($item['Product_Name']) ?>
            </div>
            <div class="product_variant">
                <?=htmlspecialchars($item['Variant_Color']) ?>
            </div>
            <div class="product_quantity">
                <?=$item['Quantity'] ?>
            </div>
        </div>
    </div>
<?php endwhile; ?>

<div class="order_footer">
    Total RM <?=number_format($order['Total_Amount'],2)?>
    <a href="order_details.php?order_id=<?=$order['Order_ID']?>"
    class="btn-detail">View details </a>
</div>
</div>
<?php endwhile; ?>
<?php endif; ?>

</body>
</html>