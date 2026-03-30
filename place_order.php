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
$shipping_address=trim($address['Unit_No'] ? $address['Unit_No'].", " : "") .
                    $address['Address'].", ".
                    $address['postcode'].", ".
                    $address['State'];

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
    product_variant.Status, product.Product_Name, 
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

    if($cart_item['Status'] !== 'Active') {
        throw new Exception("Some item are no longer available or out of stock.");
    }

    if($quantity > $cart_item['Stock']) { 
        throw new Exception("Sorry, some items are exceed the available stock. Please adjust your cart and try again.");
    }
        //recalculate subtotal in case price has changed since checkout page loaded
        $subtotal += $price * $quantity;
        $order_items[] = [
            'Variant_ID' => $variant_id,
            'Quantity' => $quantity,
            'Price' => $price,
            'Product_Name' =>$cart_item['Product_Name'],
            'Product_Picture' =>!empty($cart_item['Variant_Image']) ? $cart_item['Variant_Image'] : $cart_item['Product_Picture'],
            'Color' =>$cart_item['Color']
        ];
}
if(empty($order_items)) {
    throw new Exception("No valid items in your cart. Please adjust your cart and try again.");
}

//recalculate tax and shipping fee based on new subtotal and address
$setting=$conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
$tax_rate = $setting['Tax_Rate']/100;
$tax=$subtotal * $tax_rate; // calculate tax based on tax rate from settings

$delivery_days=0;
    if ($address && ($address['State'] === 'Sabah' || $address['State'] === 'Sarawak')) {
        $shipping_fee=$setting['Shipping_East'];
        $delivery_days=14;
    } else {
        $shipping_fee=$setting['Shipping_West'];
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
        AND Expiry_Date >= NOW() 
        AND (Usage_Limit IS NULL OR Used_Count < Usage_Limit) FOR UPDATE");
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

            if(!is_null($voucher['Usage_Per_User']) && $used>=$voucher['Usage_Per_User']) {
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

        //update voucher use count
        $stmt2=$conn->prepare("UPDATE vouchers SET Used_Count = Used_Count + 1 WHERE Voucher_ID = ?");
        $stmt2->bind_param("i", $voucher_id);
        $stmt2->execute();

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

$expire_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

$stmt=$conn->prepare("INSERT INTO orders 
(Order_Number, User_ID, Customer_Email, Subtotal, Discount_Amount, Voucher_ID,
Total_Amount, Shipping_Address, Shipping_Phone, Shipping_Name, 
Shipping_Fee, Estimated_Delivery_Date, Tax_Amount, Payment_Method, Payment_Status, Order_Status, Expire_At)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sisddidsssdsdssss", $order_number, $user_id, $email, $subtotal, $discount, $voucher_id, $total, $shipping_address, $shipping_phone, $shipping_name, $shipping_fee, $estimate_date, $tax, $payment_method, $payment_status, $order_status, $expire_at);

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

if(!empty($voucher_id)) {
    //record the usage of voucher (need $order_id)
    $stmt_usage=$conn->prepare("INSERT INTO voucher_usage (User_ID, Voucher_ID, Order_ID)
    VALUES (?, ?, ?)");
    $stmt_usage->bind_param("iii", $user_id, $voucher_id, $order_id);
    $stmt_usage->execute();
}

//clear cart
$placeholder=implode(',', array_fill(0, count($items), '?'));
$sql="DELETE FROM cart WHERE Cart_ID IN ($placeholder) AND User_ID = ?";
$stmt=$conn->prepare($sql);
$types=str_repeat('i', count($items)).'i';
$params=array_merge($items,[$user_id]);
$stmt->bind_param($types,...$params);
$stmt->execute();




$conn->commit();


$_SESSION['last_order_id']=$order_id;
$_SESSION['pending_order_id']=$order_id;

unset($_SESSION['order_processing']); // reset order processing flag 
unset($_SESSION['checkout_items']);
unset($_SESSION['voucher_id']);
unset($_SESSION['discount']);

header("Location: payment.php?order_id=$order_id");
exit();
} catch (Exception $e) {
    error_log("Order failed: " . $e->getMessage());
    $conn->rollback();
    unset($_SESSION['order_processing']);
    $_SESSION['error'] = $e->getMessage();
    header("Location: shopping_cart.php");
    exit();
}
?>
