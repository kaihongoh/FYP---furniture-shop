<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/send_mail.php'; //send email

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: shopping_cart.php");
    exit();
}

//check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

//prevent double submit
if(isset($_SESSION['order_processing']) && $_SESSION['order_processing'] === true) {
    $_SESSION['error'] = "Order is already being processed. Please wait.";
    header("Location: checkout.php");
    exit();
}
$_SESSION['order_processing'] = true; // set flag to prevent multiple order processing


$user_id = $_SESSION['user_id'];
$order_number="ORD".date("Ymd").$user_id.rand(100,999); // generate unique order number

$voucher_id = $_SESSION['voucher_id'] ?? null; 


$payment_method= $_POST['payment_method'] ?? 'Card'; // default to Card if not set

//get user default address 
$stmt=$conn->prepare("SELECT * FROM user_address
WHERE User_ID = ? AND Is_Default = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$address=$stmt->get_result()->fetch_assoc();

if(!$address) {
    unset($_SESSION['order_processing']);
    $_SESSION['error'] = "No default address found. Please set a default address before placing an order.";
    header("Location: shopping_cart.php");
    exit();
}
//send email
$stmt=$conn->prepare("SELECT email FROM users WHERE User_ID=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data=$stmt->get_result()->fetch_assoc();
$email=$user_data['email'];


$shipping_name = $address['Full_Name'] ?? null;
$shipping_phone= $address['Phone'] ?? null;
$shipping_address=$address['address_line1'];
if(!empty($address['address_line2'])) {
    $shipping_address .=', ' . $address['address_line2'];
}
$shipping_address .=', ' . $address['city'] . ', ' . $address['postcode'] . ', ' . $address['State'];

if(!empty($address['Unit_No'])) {
    $shipping_address =$address['Unit_No'] . ', ' . $shipping_address;
}

//check items
$items=$_SESSION['checkout_items'] ?? [];
if(empty($items)) {
    unset($_SESSION['order_processing']);
    $_SESSION['error'] = "No valid items in your cart. Please add items to cart and try again.";
    header("Location: shopping_cart.php");
    exit();
}

try {
$conn->begin_transaction();

//recalculate subtotal 
$subtotal=0;
$order_items=[];

foreach($items as $cart_id) {
    $stmt=$conn->prepare("SELECT cart.Variant_ID, cart.Quantity, 
    product_variant.Price, product_variant.Stock, 
    product_variant.Status AS variant_status, 
    product.status AS product_status,
    product.Product_Name, 
    product.Product_Picture, product_variant.Variant_Image, product_variant.Color
    FROM cart 
    JOIN product_variant ON cart.Variant_ID = product_variant.Variant_ID 
    JOIN product ON product_variant.Product_ID = product.Product_ID                                                             
    WHERE cart.Cart_ID = ? AND cart.User_ID = ? FOR UPDATE");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $cart_item=$stmt->get_result()->fetch_assoc();

    //skip item if remove from cart before order process
    if(!$cart_item) {
        continue; // skip if cart item not found (removed by user)
    }

    //snapshot
        $variant_id = $cart_item['Variant_ID'] ?? null;
        $quantity= $cart_item['Quantity'] ?? null;
        $price = $cart_item['Price'] ?? null;

    if(strtolower($cart_item['product_status']) !== 'active' || strtolower($cart_item['variant_status']) !== 'active') {
        throw new Exception("Some items are no longer available or out of stock.");
    }

    if($quantity > $cart_item['Stock']) { 
        throw new Exception("Sorry, some items are exceed the available stock. Please adjust your cart and try again.");
    }
    $final_picture="";
    if(!empty($cart_item['Variant_Image'])) {
        $final_picture='uploads/variants/' . $cart_item['Variant_Image'];
    } else {
        $final_picture='uploads/products/' . $cart_item['Product_Picture'];
    }
        //recalculate subtotal in case price has changed since checkout page loaded
        $subtotal += $price * $quantity;
        $order_items[] = [
            'Variant_ID' => $variant_id,
            'Quantity' => $quantity,
            'Price' => $price,
            'Product_Name' =>$cart_item['Product_Name'],
            'Product_Picture' =>$final_picture,
            'Color' =>$cart_item['Color']
        ];
}
if(empty($order_items)) {
    throw new Exception("No valid items in your cart. Please adjust your cart and try again.");
}

//recalculate tax and shipping fee based on new subtotal and address
$setting_result=$conn->query("SELECT * FROM settings LIMIT 1");
$setting=$setting_result->fetch_assoc();
$tax_rate = $setting['Tax_Rate']/100;
//get shipping fee from shipping fee setting
$shipping_fee=0;
    if ($address && !empty($address['State'])) {
        $set_shipping_fee=$conn->prepare("SELECT shipping_fee FROM shipping_fee_setting WHERE state_name=? AND status='Active' LIMIT 1");
        $set_shipping_fee->bind_param("s", $address['State']);
        $set_shipping_fee->execute();
        $result=$set_shipping_fee->get_result();

        if($row=$result->fetch_assoc()) {
            $shipping_fee=$row['shipping_fee'];
        }
        $set_shipping_fee->close();
    } 
    if($shipping_fee === 0) { //default shipping fee, normally would not happen
        $shipping_fee=19;
    }

$tax=$subtotal * $tax_rate; // calculate tax based on tax rate from settings

$delivery_days=0;
    if ($address && ($address['State'] === 'Sabah' || $address['State'] === 'Sarawak')) {
        $delivery_days=14;
    } else {
        $delivery_days=7;
    }

$estimate_date=date('Y-m-d', strtotime("+$delivery_days days"));

//check if voucher is still valid before applying to order
$discount=0;
$voucher=null;

if(!empty($_SESSION['voucher_id'])) {
    //revalidate voucher before apply 
        $stmt=$conn->prepare("SELECT * FROM vouchers WHERE Voucher_ID = ? 
        AND Status = 'Active' 
        AND (Expiry_Date IS NULL OR DATE(Expiry_Date)>= CURDATE()) 
        AND (Usage_Limit >0 AND Used_Count < Usage_Limit) FOR UPDATE"); 
        $stmt->bind_param("i", $voucher_id);
        $stmt->execute();
        $voucher=$stmt->get_result()->fetch_assoc();

        //check voucher usage per user
        if($voucher) {
            $usage_stmt=$conn->prepare("SELECT COUNT(*) as used 
            FROM voucher_usage WHERE User_ID=?
            AND Voucher_ID=?");
            $usage_stmt->bind_param("ii", $user_id, $voucher_id);
            $usage_stmt->execute();
            $usage_data=$usage_stmt->get_result()->fetch_assoc();
            $used=$usage_data['used'];

            if(($voucher['Usage_Per_User'] <=0) || $used>=$voucher['Usage_Per_User']) {
                $discount=0;
                $voucher_id=null;
                $voucher=null;
            }
        }

//calculate discount if voucher is valid
    if($voucher && $subtotal >= $voucher['Minimum_Spend']) { //check minimum spend 
            //calculate discount
            if($voucher['Discount_Type']=='percentage') {
                $discount = $subtotal * ($voucher['Discount_Value'] / 100);

                if(!is_null($voucher['Max_Discount']) && $discount > $voucher['Max_Discount']) {
                        $discount=$voucher['Max_Discount'];
                    }
            } else {
                $discount = $voucher['Discount_Value'];
            }
            $discount =min($discount,$subtotal);
            $discount=round($discount,2);
    } else {
        $discount=0;
        $voucher_id=null;
        $voucher=null;
        } 
    }

$total=max(0, $subtotal + $tax + $shipping_fee - $discount);

//create order 
$payment_status = 'Pending';
$order_status = 'Pending';
$payment_method = 'Card';


$stmt=$conn->prepare("INSERT INTO orders 
(Order_Number, User_ID, Customer_Email, Subtotal, Discount_Amount, Voucher_ID,
Total_Amount, Shipping_Address, Shipping_Phone, Shipping_Name, 
Shipping_Fee, Estimated_Delivery_Date, Tax_Amount, Payment_Method, Payment_Status, Order_Status)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sisddidsssdsdsss", $order_number, $user_id, $email, $subtotal, $discount, $voucher_id, $total, $shipping_address, $shipping_phone, $shipping_name, $shipping_fee, $estimate_date, $tax, $payment_method, $payment_status, $order_status);

$stmt->execute();

$order_id = $conn->insert_id;


//insert order item 
foreach($order_items as $item) {

    //insert snapshot (product name,picture,color )
    //insert into order_items
    $stmt2=$conn->prepare("INSERT INTO order_items (Order_ID, Variant_ID,Product_Name, Product_Picture, Variant_Color, Quantity, Price) 
    VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt2->bind_param("iisssid", $order_id, $item['Variant_ID'], $item['Product_Name'], $item['Product_Picture'], $item['Color'], $item['Quantity'], $item['Price']);
    $stmt2->execute();
}



//clear cart
$placeholder=implode(',', array_fill(0, count($items), '?'));
$sql="DELETE FROM cart WHERE Cart_ID IN ($placeholder) AND User_ID = ?";
$stmt=$conn->prepare($sql);
$types=str_repeat('i', count($items)).'i';
$params=array_merge($items,[$user_id]);
$stmt->bind_param($types,...$params);
$stmt->execute();


$card_number=preg_replace('/\D/','',$_POST['card_number'] ?? '');
$card_holder=trim($_POST['card_holder'] ?? '');
$expiry=trim($_POST['expiry_date'] ?? '');
$cvv=trim($_POST['cvv'] ??'');

//validate
if(strlen($card_number)!==16) {
    throw new Exception("Card number must be exactly 16 digits.");
}
if(!preg_match('/^[a-zA-Z\s]+$/',$card_holder)) {
    throw new Exception("Card holder name must contain only letter and spaces.");
}
if(!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/',$expiry)) {
    throw new Exception("Expiration date must be in MM/YY format.");
}
if(!preg_match('/^\d{3}$/',$cvv)) {
    throw new Exception("CVV number must be 3 digit.");//can enter abc?
} 

//check card expiry
list ($month,$year)=explode('/', $expiry);
$year=2000 + (int)$year;
$month=(int)$month;

$now=new DateTime();
$date=new DateTime("$year-$month-01");
$date->modify('last day of this month');               
    if($date < $now) {
        throw new Exception('Card has expired.');
    }

    //deduct stock after order created
    foreach($order_items as $item) {
    $update_stock=$conn->prepare("UPDATE product_variant SET Stock=Stock-? WHERE Variant_ID=? AND Stock>=? AND Status='Active'");
    $update_stock->bind_param("iii", $item["Quantity"], $item["Variant_ID"], $item["Quantity"]);
    if(!$update_stock->execute() || $update_stock->affected_rows === 0) {
        throw new Exception("Stock insufficient for variant " .$item['Variant_ID']);
    }
}

    //updte to paid
    $update_order=$conn->prepare("UPDATE orders SET Payment_Status='Paid', Order_Status='Paid' WHERE Order_ID=?");
    $update_order->bind_param("i", $order_id);
    $update_order->execute();

    //update voucher use count
    if($voucher_id && $voucher && $discount > 0) {
    $stmt2=$conn->prepare("UPDATE vouchers SET Used_Count = Used_Count + 1 WHERE Voucher_ID = ?");
    $stmt2->bind_param("i", $voucher_id);
    $stmt2->execute();
    //record the usage of voucher 
    $stmt_usage=$conn->prepare("INSERT INTO voucher_usage (User_ID, Voucher_ID, Order_ID)
    VALUES (?, ?, ?)");
    $stmt_usage->bind_param("iii", $user_id, $voucher_id, $order_id);
    $stmt_usage->execute();
    }

$conn->commit();

//send order confirmation email
//take latest order
$order=$conn->query("SELECT * FROM orders WHERE Order_ID = $order_id")->fetch_assoc();

//get order item
$stmt_items=$conn->prepare("SELECT Product_Name, Product_Picture, Variant_Color, Quantity, Price
FROM order_items
WHERE Order_ID=?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result=$stmt_items->get_result();

        $items_html="";
        while($item=$items_result->fetch_assoc()) {
            $img_src=htmlspecialchars($item["Product_Picture"]);  
            $items_html .="
            <tr>
            <td>
            ". htmlspecialchars($item["Product_Name"]) . "
            </td>
            <td>". htmlspecialchars($item["Variant_Color"]) ."</td>
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
            $body.="<p><strong>You may check on the Order History! <a href='http://localhost/FYP/fyp_project/order_history.php'>http://localhost/FYP/fyp_project/home.php</strong></a></p>";
            $body.= "<p>Thank you for your purchase. We appreciate your support and are thrilled you chose us.</p>";
            
            $result=sendOrderEmail($order['Customer_Email'], $subject, $body);
            if($result !== true){
                error_log("Order confirmation email failed for order #{$order_id}: " . $result);
            }   

$_SESSION['last_order_id']=$order_id;
$_SESSION['pending_order_id']=$order_id;

unset($_SESSION['order_processing']); // reset order processing flag 
unset($_SESSION['checkout_items']);
unset($_SESSION['voucher_id']);
unset($_SESSION['discount']);

header("Location: order_confirmation.php?order_id=$order_id");
exit();
} catch (Exception $e) {
    error_log("Order failed for user $user_id: " . $e->getMessage());
    $conn->rollback();
    unset($_SESSION['order_processing']);
    $_SESSION['error'] = $e->getMessage();
    header("Location: shopping_cart.php");
    exit();
}
?>
