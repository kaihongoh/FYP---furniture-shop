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


//get order item and refund quantity
//get variant rating and check user have rate or not
$stmt2=$conn->prepare ("SELECT order_items.Product_Name, 
order_items.Product_Picture, order_items.Variant_Color, 
order_items.Quantity, order_items.Price, 
order_items.Variant_ID, 

(SELECT 1 FROM product_ratings 
WHERE product_ratings.Order_ID = order_items.Order_ID 
AND product_ratings.Variant_ID = order_items.Variant_ID 
AND product_ratings.User_ID = ?) AS already_rated,

(SELECT COALESCE(SUM(Refund_Quantity),0) FROM order_refunds
WHERE order_refunds.Order_ID = order_items.Order_ID
AND order_refunds.Variant_ID = order_items.Variant_ID
AND order_refunds.User_ID = ?
AND Status='Approved'
AND type='product') AS approved_quantity,

(SELECT COALESCE(SUM(Refund_Quantity),0) FROM order_refunds
WHERE order_refunds.Order_ID = order_items.Order_ID
AND order_refunds.Variant_ID = order_items.Variant_ID
AND order_refunds.User_ID = ?
AND Status='Pending'
AND type='product')  AS pending_quantity

/*
(SELECT 1 FROM order_refunds
WHERE order_refunds.Order_ID = order_items.Order_ID
AND order_refunds.Variant_ID = order_items.Variant_ID
AND order_refunds.User_ID = ? LIMIT 1) AS has_refund,

(SELECT COUNT(*) FROM order_refunds
WHERE order_refunds.Order_ID = order_items.Order_ID
AND order_refunds.Variant_ID = order_items.Variant_ID
AND order_refunds.Status = 'Approved') AS approved_refund_count,

(SELECT Status FROM order_refunds
WHERE order_refunds.Order_ID = order_items.Order_ID
AND order_refunds.Variant_ID = order_items.Variant_ID
AND order_refunds.User_ID = ?
AND type='product' 
ORDER BY order_refunds.Created_At DESC LIMIT 1) AS refund_status
*/
FROM order_items
WHERE order_items.Order_ID=?");
$stmt2->bind_param("iiii",$user_id, $user_id, $user_id, $order_id);
$stmt2->execute();
$items=$stmt2->get_result();
$stmt2->close();

//$can_rate

$refund_allowed=false;
if(($order['Order_Status'] == 'Completed' || $order['Order_Status'] == 'Partially_Refunded' || $order['Order_Status'] == 'Refund_Requested') && $order['Actual_Delivery_Date'] ) {
     $delivery_time=strtotime($order['Actual_Delivery_Date']);
     $current=time();

     $days=($current - $delivery_time) / (60 * 60 * 24);

    if($days <= 3) {  //can request refund within 3 days after delivery 
        $refund_allowed=true;
    }
}






?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
    <link rel="stylesheet" href="css/order_details.css">
</head>
<body>
    <div class="container">
        <a href="order_history.php" class="back_btn">Back to orders</a>
        <h1>Order Details</h1>


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
        <th>Rating</th>
        <th>Refund Status</th>
    </tr>
</thead>
    <tbody>
        <?php while($item=$items->fetch_assoc()):
            $already_rated = $item['already_rated'];
            $variant_id = $item['Variant_ID']; 
            $disabled=$already_rated ? 'disabled' : '';
            $rate_display=$already_rated ? 'Rated' : '';
            $item_total=$item['Price'] * $item['Quantity'];
            
            //$refund_status=$item['refund_status'];

            $approved_quantity=(int)$item['approved_quantity'];
            $pending_quantity=(int)$item['pending_quantity'];
            $total_quantity=(int)$item['Quantity'];

            $remaining_quantity=$total_quantity - ($approved_quantity + $pending_quantity);//for rating

            $can_rate = (!$already_rated && $remaining_quantity > 0 && $pending_quantity ==0); //can only rate when order is completed, and no approve quantity in the order
            
            $available_refund_quantity=$total_quantity - ($approved_quantity + $pending_quantity);

            $can_refund=($refund_allowed && $available_refund_quantity > 0 && $pending_quantity == 0);

        $text="";
            if($pending_quantity > 0) {
                $text= "Pending ($pending_quantity)";
            }elseif($approved_quantity > 0) {
                $text= "Refunded ($approved_quantity)";
            }
                
            ?>
            <tr>
                <td>

                    <div class="product_info">
                        <img src="<?=htmlspecialchars($item['Product_Picture']) ?>"
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
                <td class="rating_column">
                    <?php if($already_rated): ?>
                        <span class="rated-label">Rated</span>  
                    <?php elseif($can_rate): ?>
                            <div class="star-rating" data-variant-id="<?=$variant_id ?>" data-order-id="<?=$order_id ?>">
                                <span data-value="1">☆</span>
                                <span data-value="2">☆</span>
                                <span data-value="3">☆</span>
                                <span data-value="4">☆</span>
                                <span data-value="5">☆</span>
                            </div>
                        <?php else: ?>
                            <span class="cannot-rate">Rating is Unavailable</span>
                    <?php endif; ?>
                </td>
                <td>

                        <?php if($approved_quantity > 0): ?>
                        <span>Refunded (<?=$approved_quantity?>)</span>
                        <?php endif; ?>

                        <?php if($pending_quantity > 0): ?>
                            <span>Pending (<?=$pending_quantity?>)</span>

                    <?php elseif($can_refund): ?>
                        <a href="request_refund.php?order_id=<?=$order_id ?>&variant_id=<?=$variant_id ?>">
                            Request Refund
                        </a>

                        <?php elseif (!$refund_allowed && $order['Order_Status'] == 'Completed' ):?>
                            <span>Refund period expired</span>
                            
                        <?php else: ?>
                        <span>-</span>
                    <?php endif; ?>
                </td>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $('.star-rating').each(function() {
        $(this).data('selectedRating', 0); // Store selected rating
    });
        $('.star-rating span').hover(function() {
            var rating = $(this).data('value');
            var parent = $(this).parent();
            parent.find('span').each(function(index) {
                $(this).html(index < rating ? '⭐' : '☆');
                /*if (index < rating) {
                    $(this).html('⭐');
                } else {
                    $(this).html('☆');
                } */
            });
        }, function() {
            var parent = $(this).parent();//will recover to user rate after hover
            var selectedRating = parent.data('selectedRating');
            parent.find('span').each(function(index) {
                $(this).html(index < selectedRating ? '⭐' : '☆');
            });
        });

        $('.star-rating span').click(function() {
            var parent=$(this).parent();
            var rating = $(this).data('value');
            var variant_id = $(this).parent().data('variant-id');
            var order_id = $(this).parent().data('order-id');
            var $stars = $(this).parent().find('span');
            var $row = $(this).closest('tr');
            var $rating_cell = $row.find('.rating_column'); // rating row
            
            parent.data('selectedRating',rating);

            $.post('submit_rating.php', {
                variant_id: variant_id,
                order_id: order_id,
                rating: rating
            }, function(response) {
                if (response.trim()==='success') {
                    $rating_cell.html('Rated ⭐'.repeat(rating)+' (' + rating + '/5)');
                    alert ("Thank you for your rating!");
                } else {
                    alert('Rating Failed: '+ response);
                }
            }).fail(function() {
                alert('Error submitting rating. Please try again.');
            });
        });

</script>

</body>
</html>
