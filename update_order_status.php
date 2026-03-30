<?php
session_start();
require_once("includes/config.php");
require_once 'includes/send_mail.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_order.php");
    exit();
}

if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    header("Location: manage_order.php");
    exit();
}

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$order_id=(int)$_POST['order_id'];
$new_status=$_POST['status'];

$allow_status=['Pending', 'Paid', 'Processing', 'Shipped', 'Delivered', 'Completed', 'Cancelled'];
if(!in_array($new_status, $allow_status)) {
    $_SESSION['error']="Invalid status.";
    header("Location: manage_order.php");
    exit();
    }

//get order
$stmt=$conn->prepare('SELECT Order_Number, Customer_Email, Order_Status
FROM orders
WHERE Order_ID=?');
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order=$stmt->get_result()->fetch_assoc();

if(!$order) {
    $_SESSION['error']="Order not found.";
    header("Location: manage_order.php");
    exit();
}

$old_status=$order["Order_Status"];

//cannot change from completed to shipped
if($old_status === $new_status) {
    $_SESSION["info"]= "Status change failed.";
    header("Location: manage_order.php");
    exit();
    }


$allow_transition=['Paid'=>['Processing'],
'Processing'=>['Shipped'],
'Shipped'=>['Delivered'],
'Delivered'=>['Completed'],
'Completed'=>[],
'Cancelled'=>[]];    

if(!isset($allow_transition[$old_status]) || !in_array($new_status, $allow_transition[$old_status])) {
    $_SESSION['error']= 'Invalid status transition. Please try again.';
    header("Location: manage_order.php");
    exit();   
}

//update status
$update=$conn->prepare("UPDATE orders SET Order_Status=? WHERE Order_ID=?");
$update->bind_param("si", $new_status, $order_id);
if($update->execute()) {
    $_SESSION['success']="Order status updated to $new_status successfully.";

    if($new_status === 'Shipped' || $new_status === 'Delivered' || $new_status === 'Completed') {
        //get order info (email)
        $stmt_order=$conn->prepare("SELECT * FROM orders WHERE Order_ID=?");
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $order=$stmt_order->get_result()->fetch_assoc();

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
        $subject="Order ".($new_status==='Shipped' ? 'Shipped':'Completed');
        $body= "<h1>Your order has been " . strtolower($new_status) . "!</h1>";
        $body.= "<p>Your order number is: <strong>" . $order['Order_Number'] ."</strong></p>";
        $body.= "<p>New status: <strong>" . $new_status . "</strong></p>";
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
        }
    } else {
        $_SESSION["error"] = "Failed to update status. Please try again.";
        }

header("Location: manage_order.php");
exit();