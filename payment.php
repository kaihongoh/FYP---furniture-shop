<?php
session_start();
require_once "includes/config.php";
require_once 'includes/send_mail.php'; //send email


if(!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();    
}

if(!isset($_GET["order_id"])) {
    header("Location: home.php");
    exit();        
}

$order_id=(int)$_GET["order_id"];
$user_id=$_SESSION["user_id"];

//prevent double submit
if(isset($_SESSION['payment_processing']) && $_SESSION['payment_processing'] === true) {
    $_SESSION['error'] = "payment is already being processed. Please wait.";
    header("Location: payment.php?order_id=$order_id");
    exit();
}
$_SESSION['payment_processing'] = true; // set flag to prevent multiple payment processing

$conn->begin_transaction();

//get order info (email)
$stmt_order=$conn->prepare("SELECT * FROM orders WHERE Order_ID=? FOR UPDATE");
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$order=$stmt_order->get_result()->fetch_assoc();

if(!$order) {
    $conn->rollback();
    unset($_SESSION['payment_processing']);
    $_SESSION['error']="Order not found.";
    header("Location: home.php");
    exit();
}

if($order["User_ID"] !== $user_id) {
    $conn->rollback();
    unset($_SESSION['payment_processing']);
    $_SESSION["error"]= "Unauthorized access.";
    header("Location: home.php");
    exit();
}

//if status is not pending
if($order["Order_Status"]!== "Pending" || $order["Payment_Status"] !== "Pending") {
    $conn->rollback();
    unset($_SESSION['payment_processing']);
    if($order["Order_Status"]== "Paid") {
        $_SESSION['last_order_id']=$order_id;
        header("Location: order_confirmation.php?order_id=$order_id");
    } else {
        $_SESSION['error']="Payment failed. Please try again later.";
        header("Location: shopping_cart.php");
    }
    exit();
}

//check order expire time
if(strtotime($order['Expire_At']) < time()) {
    $conn->rollback();
    unset($_SESSION['payment_processing']);
    $_SESSION['error']="Order has expired. Please place a new order.";
    header("Location: shopping_cart.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if($_POST['action'] === 'pay') {
        if($order['Payment_Method']==='Card') {
            $card_number=preg_replace('/\D/','',$_POST['card_number'] ?? '');
            $card_holder=trim($_POST['card_holder'] ?? '');
            $expiry=trim($_POST['expiry_date'] ?? '');
            $cvv=trim($_POST['cvv'] ??'');

            //validate
            if(strlen($card_number)!==16) {
                $conn->rollback();
                unset($_SESSION['payment_processing']);
                $_SESSION['error']= "Card number must be exactly 16 digits.";
                header("Location: payment.php?order_id=$order_id");
                exit();
            }
            if(!preg_match('/^[a-zA-Z\s]+$/',$card_holder)) {
                $conn->rollback();
                unset($_SESSION['payment_processing']);
                $_SESSION['error']= "Card holder name must contain only letter and spaces.";
                header("Location: payment.php?order_id=$order_id");
                exit();
            }
            if(!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/',$expiry)) {
                $conn->rollback();
                unset($_SESSION['payment_processing']);
                $_SESSION['error']= "Expiration date must be in MM/YY format.";
                header("Location: payment.php?order_id=$order_id");
                exit();
            }
            if(!preg_match('/^\d{3}$/',$cvv)) {
                $conn->rollback();
                unset($_SESSION['payment_processing']);
                $_SESSION['error']= "CVV number must be 3 digit.";//can enter abc?
                header("Location: payment.php?order_id=$order_id");
                exit();
            } 
        }

        if($order['Payment_Method']==='Online Banking') {
            if(empty($_POST['online-banking'])) {
                $conn->rollback();
                unset($_SESSION['payment_processing']);
                $_SESSION['error']= "Please select a bank.";
                header("Location: payment.php?order_id=$order_id");
                exit();
            }
        }

        //payment success, update to paid, deduct stock
        if($order['Order_Status'] !== 'Pending' || $order['Payment_Status'] !== 'Pending') {
            $conn->rollback();
            unset($_SESSION['payment_processing']);
            $_SESSION['error']= "Order cannot be paid now.";
            header("Location: shopping_cart.php");
            exit();
        }
        $stmt_items=$conn->prepare("SELECT Variant_ID, Quantity FROM order_items WHERE Order_ID=?");
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $items=$stmt_items->get_result();


    try{
        while($item=$items->fetch_assoc()) {
            $update_stock=$conn->prepare("UPDATE product_variant SET Stock=Stock-? WHERE Variant_ID=? AND Stock>=? AND Status='Active'");
            $update_stock->bind_param("iii", $item["Quantity"], $item["Variant_ID"], $item["Quantity"]);
            if(!$update_stock->execute() || $update_stock->affected_rows === 0) {
                throw new Exception("Stock insufficient for variant " .$item['Variant_ID']);
            }
        }

        $update=$conn->prepare("UPDATE orders SET Payment_Status='Paid', Order_Status='Paid' WHERE Order_ID=?");
        $update->bind_param("i", $order_id);
        $update->execute();

    $conn->commit();
    $stmt_order->execute(); //take a latest order 
    $order=$stmt_order->get_result()->fetch_assoc();

    } catch(Exception $e) {
        $conn->rollback();
        unset($_SESSION['payment_processing']);
        $_SESSION["error"] = "Payment failed: ".$e->getMessage();
        header("Location: shopping_cart.php");
        exit();
    }
        //send confirmation email
        //get order item
        $stmt_items=$conn->prepare("SELECT Product_Name, Product_Picture, Variant_Color, Quantity, Price
        FROM order_items
        WHERE Order_ID=?");
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $items_result=$stmt_items->get_result();

        $items_html="";
        while($item=$items_result->fetch_assoc()) {
            $img_src='uploads/'.htmlspecialchars($item["Product_Picture"]);
            $items_html .="
            <tr>
            <td>
            <img src='" . $img_src . "' width='50' height='50' style='object-fit:cover;'>
            ". htmlspecialchars($item["Product_Name"]) . "
            </td>
            <td>Color: ". htmlspecialchars($item["Variant_Color"]) ."</td>
            <td>" . $item["Quantity"] . "</td>
            <td>RM ". number_format($item["Price"],2) . "</td>
            <td>RM ". number_format($item["Price"] * $item['Quantity'],2) . "</td>
            </tr>";
        }
            //email content
            $subject="Order Confirmation - ".$order['Order_Number'];
            $body= "<h1>Thank you for your order!</h1>";
            $body.= "<p>Your order number is: <strong>" . $order['Order_Number'] ."</strong></p>";
            $body.= "<h3>Order details: </h3>";
            $body.="<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            $body.="<tr>
            <th>Product</th>
            <th>Color</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Total</th>
            </tr>";
            $body.=$items_html;
            $body.="</table>";
            $body.= "<p><strong>Subtotal: </strong> RM ".number_format($order['Subtotal'],2) . "</p>";
            $body.= "<p><strong>Shipping fee: </strong> RM ". number_format($order["Shipping_Fee"],2) . "</p>";
            $body.= "<p><strong>Tax: </strong> RM ". number_format($order["Tax_Amount"],2) . "</p>";
            $body.= "<p><strong>Discount: </strong> -RM ". number_format($order["Discount_Amount"],2) . "</p>";
            $body.= "<p><strong>Total: </strong> RM ". number_format($order["Total_Amount"],2) . "</p>";
            $body.= "<p>Thank you for your purchase. We appreciate your support and are thrilled you chose us.</p>";
            
            $result=sendOrderEmail($order['Customer_Email'], $subject, $body);
            if($result !== true){
                error_log("Order confirmation email failed for order #{$order_id}: " . $result);
            }

            $_SESSION['last_order_id']=$order_id;
            $_SESSION['success']="Payment Successful.";
            unset($_SESSION['payment_processing']);
            header("Location: order_confirmation.php?order_id=$order_id");
            exit();         
    } 
    elseif($_POST['action']==='cancel') {
        if($order["Order_Status"] !== 'Pending' || $order['Payment_Status'] !== 'Pending') {
            $conn->rollback();
             unset($_SESSION['payment_processing']);
            header("Location: shopping_cart.php");
            exit();            
        }
        //recover the voucher used count
        if(!empty($order["Voucher_ID"])) {
            $stmt = $conn->prepare("UPDATE vouchers SET Used_Count= Used_Count-1 WHERE Voucher_ID=? AND Used_Count>0");
            $stmt->bind_param("i", $order['Voucher_ID']);
            $stmt->execute();

            //recover the voucher usage per user
            $del_usage=$conn->prepare("DELETE FROM voucher_usage WHERE Order_ID=?");
            $del_usage->bind_param("i", $order_id);
            $del_usage->execute();
        }
        //cancel order, update to cancelled
        $update=$conn->prepare("UPDATE orders SET Payment_Status='Failed', Order_Status='Cancelled' WHERE Order_ID=?");
        $update->bind_param("i", $order_id);
        $update->execute();

        $conn->commit();    
        unset($_SESSION['payment_processing']);
        $_SESSION["error"]= "Your order is cancelled";
        header("Location: shopping_cart.php");
        exit();


    } 
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/payment.css">
</head>
<body>
    <h1>Payment</h1>
    <!--left side-->
    <h2>Payment Method: <?=htmlspecialchars($order['Payment_Method'])?></h2>

    <?php if(isset($_SESSION['error'])): ?>
        <div style="color:red; border:1px solid red; padding:10px; margin-bottom:20px;">
            <?=htmlspecialchars($_SESSION['error'])?>
        </div>
        <?php unset ($_SESSION['error']); ?>
        <?php endif; ?>

    <?php if ($order['Payment_Method'] == 'Card'):?> 
        <form method="POST" id="paymentForm" class="payment-form">
            <div class="payment-layout">
                <div class="card-input">
                    <label>Card Number</label>
                    <input type="text" id="card_number" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19" required>
                    <div id="card_number_error" class="error-message">Please enter a valid 16 digits card numbers.</div>
                </div>
            
                <div class="card-input">
                    <label>Card Holder Name</label>
                    <input type="text" id="card_holder" name="card_holder" placeholder="Kobe Bryan" required>
                    <div id="card_holder_error" class="error-message">Only letters and spaces are allowed.</div>
                </div>

                <div class="card-input">
                    <label>Expiration Date</label>
                    <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" maxlength="5" required>
                    <div id="expiry_error" class="error-message">Invalid date format.</div>
                </div>    

                <div class="card-input">
                    <label>CVV</label>
                    <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="3" required>
                    <div id="cvv_error" class="error-message">CVV must be 3 digit.</div>
                </div> 
            </div>  

            <div class="payment-right">
                <div class="order-summary">    
                    <h3>Order Summary</h3>
                    <p>Order Number: <?=htmlspecialchars($order['Order_Number'])?></p>
                    <p class="total-amount">Total Pay: RM <?=number_format($order['Total_Amount'],2)?></p>
                </div>
            <div class="payment-actions">
                <button type="submit" name="action" class="pay-btn" value="pay">Pay Now</button>
                <button type="submit" name="action" class="cancel-btn" value="cancel">Cancel</button>
            </div>
        </div>

        </form>

        <?php else: ?>
            <form method="POST" class="payment-form-single">
                <div class="online-banking">
                    <label>Select Bank</label>
                    <select name="online-banking" id="online-banking-select">
                        <option value="maybank2u">Maybank2u</option>
                        <option value="RHB">RHB Bank</option>
                        <option value="hong-leong-bank">Hong Leong Bank</option>
                        <option value="public-bank">Public Bank</option>
                    </select>
                    <div class="payment-action">
                        <button type="submit" name="action" class="pay-btn" value="pay">Pay Now</button>
                        <button type="submit" name="action" class="cancel-btn" value="cancel">Cancel</button>
                    </div>
                </div>
        </form>
        <?php endif;?>


    <?php if($order['Payment_Method']==='Card'): ?>
        <script src="js/payment.js"></script> 
    <?php endif ;?>
</body>
</html>
