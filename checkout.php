<?php
session_start();
require_once 'includes/config.php';

if(!isset($_SESSION['user_id'])) {
    //guest user, redirect to login page
    header("Location: login.php");
    exit();
}

unset($_SESSION['voucher_id']); // clear any previously applied voucher
unset($_SESSION['discount']); // clear any previously applied discount

if(!isset($_POST['selected_items']) || empty($_POST['selected_items'])) {
    $_SESSION['error'] = "Please select at least one item to checkout.";
    header("Location: shopping_cart.php");
    exit();
}
$selected_items = $_POST['selected_items'];
$_SESSION['checkout_items'] = $selected_items; // store selected items in session for later use in place_order.php

$user_id = $_SESSION['user_id'];
//fetch default address for logged in user
$address_sql=$conn->prepare("SELECT * FROM user_address
WHERE User_ID = ? AND Is_Default = 1
LIMIT 1");
$address_sql->bind_param("i", $user_id);
$address_sql->execute();
$address=$address_sql->get_result()->fetch_assoc();

//fetch cart items for logged in user
$placeholders = implode(',', array_fill(0, count($selected_items), '?')); // create placeholders for prepared statement

/* only fetch selected items for checkout, selected_items will be passed from shopping_cart.php */
$sql= "SELECT cart.Cart_ID, 
cart.Quantity, 
product_variant.Price, 
product_variant.Stock,
product_variant.Color,
product_variant.Variant_Image,
product.Product_Name,
product.Product_Picture,
product.Product_Description
FROM cart
JOIN product_variant ON cart.Variant_ID = product_variant.Variant_ID
JOIN product ON product_variant.Product_ID = product.Product_ID
WHERE cart.User_ID = ? AND cart.Cart_ID IN ($placeholders)"; 



$stmt=$conn->prepare($sql);
$types='i'.str_repeat('i', count($selected_items)); // create types string for bind_param
$params=array_merge([$user_id], $selected_items);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$cart_result=$stmt->get_result();

if($cart_result->num_rows === 0) {
    $_SESSION['error'] = "no valid items is selected.";
    header("Location: shopping_cart.php");
    exit();
}

//calculate subtotal
$subtotal=0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/checkout.css">
</head>
<body> 
    <?php include_once 'includes/header.php'; ?>

    <form action="place_order.php" method="POST">
    <?php foreach($selected_items as $item_id): ?>
        <input type="hidden" name="selected_items[]" value="<?=$item_id ?>">
    <?php endforeach; ?>
        <div class="address-container"> <!--top（full fill container)-->
            <h2>Delivery Address</h2>
            <!--show default address-->
       <!--if default address exist, show default address with change button, else show message and link to add address page-->     
<?php 
if ($address): ?>
<div class="address-details">
    <p><?=htmlspecialchars($address['Full_Name'])?></p>
    <p><?=htmlspecialchars($address['Phone'])?></p>
    <p><?=htmlspecialchars($address['Unit_No'])?>, <?=htmlspecialchars($address['Address'])?></p>
    <p><?=htmlspecialchars($address['postcode'])?>, <?=htmlspecialchars($address['State'])?></p>

    <a href="address_list.php?id=<?=$address['Address_ID']?>"class="change-link">Change</a> 
</div>
<?php else: ?>
    <p>No default address found. Please add an address.</p>
    <a href="add_address.php">Add Address</a>
<?php endif; ?>


    </div>
    <div class="main-container">
    <div class="order-container"> <!--left container-->
        <h2>Products Ordered</h2>
        <div class="checkout-product">
            <?php
            while($item=$cart_result->fetch_assoc()):
                $item_total = $item['Price'] * $item['Quantity'];

                if($item['Quantity']>$item['Stock']) {
                    $_SESSION['error']="Sorry, there are some items is exceed the available stock. Please adjust your cart and try again.";
                    header("Location: shopping_cart.php");
                    exit();
                }
                
                if($item['Quantity']<=0) {
                $_SESSION['error']="Invalid cart quantity detected.Please adjust your cart and try again.";
                header("Location: shopping_cart.php");
                exit();
                }

                $subtotal += $item_total;
            ?> 
            <div class="checkout-items">
                <div class="product-picture">
                    <img src="uploads/<?=htmlspecialchars(!empty($item['Variant_Image']) ? $item['Variant_Image'] : $item['Product_Picture'])?>" width="80">
                </div> 
                <div class="product-info"> 
                    <div class="product-name">
                        <?=htmlspecialchars($item['Product_Name'])?> 
                    </div>
                    <div class="product-description">
                        <?=htmlspecialchars(substr($item['Product_Description'], 0, 50))."..."; ?>
                    </div>
                    <div class="product-color">
                        <?=htmlspecialchars($item['Color'])?>
                    </div>
                    <div class="product-quantity">
                        <?=$item['Quantity']?>
                    </div> 
                </div> 

                <div class="item-price">
                    <div class="price-row">
                        <span class="price-label">Unit Price</span>
                        <span class="price-value">RM<?=number_format($item['Price'], 2)?></span>
                    </div>

                    <div class="price-row-total">
                        <span class="price-label">Total Price</span>
                        <span class="price-value">RM<?=number_format($item_total, 2)?></span>
                    </div>   
                </div>    
            </div>   
        <?php endwhile; ?>  
    </div>  
    <div class="product-subtotal">
        <span>Subtotal</span> 
        <span>RM <?=number_format($subtotal, 2)?></span>

        <!--show products ordered-->






<div class="payment-container">
    <?php 
    $setting=$conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
    $tax_rate = $setting['Tax_Rate']/100;
    $tax=$subtotal * $tax_rate; // calculate tax based on tax rate from settings

    if ($address && ($address['State'] === 'Sabah' || $address['State'] === 'Sarawak')) {
        $shipping_fee=$setting['Shipping_East'];
    } else {
        $shipping_fee=$setting['Shipping_West'];
    }


    $discount=0;
    $total=$subtotal + $tax + $shipping_fee - $discount;
    ?>

    <input type="hidden" id="subtotal" value="<?=$subtotal?>">
    <input type="hidden" id="tax" value="<?=$tax?>">
    <input type="hidden" id="shipping_fee" value="<?=$shipping_fee?>">

    <div class="payment_method">
        <h2>Payment Method</h2>
        <!--select payment method-->
        <select name="payment_method" id="payment_method_select">
            <option value="Card">Credit Card</option>
            <option value="Online Banking">Online Banking</option>
        </select>
    </div>

    <div id="order-summary-container">
        <h2>Order Summary</h2>
        <div class="summary-row">
            <span>Subtotal</span>
            <span>RM <?=number_format($subtotal, 2)?></span>
        </div>
        <div class="summary-row">
            <span>Tax (<?= $setting['Tax_Rate'] ?>%)</span>
            <span>RM <?=number_format($tax, 2)?></span>
        </div>
        <div class="summary-row">
            <span>Shipping Fee</span>
            <span>RM <?=number_format($shipping_fee, 2)?></span>
        </div>
        <div class="summary-row">
            <span>Voucher Discount</span>
            <span id="discount_display">-RM0.00</span>
        </div>
        <div class="summary-row-total">
            <span>Total</span>
            <span id="total_display"> RM<?=number_format($total, 2)?></span>
        </div>
    </div>

    <div class="voucher">
        <!--select vouchers-->
        <input type="text" id="voucher_code" name="voucher_code" placeholder="Enter voucher code">
        <button type="button" id="apply_voucher">Apply</button>
        <p id="voucher_message"></p>
    </div>




    <div class="place-order-container">
        <button type="submit" id="place_order">Place Order</button>
    </div>

</form>

<?php include_once 'includes/footer.php'; ?>



<script>
document.getElementById('apply_voucher').addEventListener('click', function() {

const code=document.getElementById('voucher_code').value.trim();
const subtotal=document.getElementById('subtotal').value;
const tax=document.getElementById('tax').value;
const shipping_fee=document.getElementById('shipping_fee').value;

fetch('apply_voucher.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: 'voucher_code=' + encodeURIComponent(code) 
    /*+ '&subtotal=' + encodeURIComponent(subtotal)
    + '&tax=' + encodeURIComponent(tax)
    + '&shipping_fee=' + encodeURIComponent(shipping_fee) */
})
.then(response => response.json())
.then(data => {
    if(data.success) {
    const total=parseFloat(subtotal) + parseFloat(tax) + parseFloat(shipping_fee) - parseFloat(data.discount);

    document.getElementById('voucher_message').innerHTML=
    "Voucher applied successfully! Discount: RM" + data.discount.toFixed(2);
    
    document.getElementById('discount_display').innerHTML=
    "-RM" + data.discount.toFixed(2);

    document.getElementById('total_display').innerHTML=
    "RM" + total.toFixed(2);

    document.getElementById('apply_voucher').disabled = true; // disable apply button after successful application
    document.getElementById('voucher_code').readOnly=true; // clear voucher code input
    } else {
        document.getElementById('voucher_message').innerHTML=
        data.message;
    }
});
});

//show/hide payment details based on selected payment method
const paymentSelect=document.getElementById('payment_method_select');


paymentSelect.addEventListener('change', function() {
    if(this.value === 'Online Banking') {
        bankDiv.style.display = 'block';
        cardDiv.style.display = 'none';
    } else if(this.value === 'Card') {
        bankDiv.style.display = 'none';
        cardDiv.style.display = 'block';
    } else {
        bankDiv.style.display = 'none';
        cardDiv.style.display = 'none';
    }
});

paymentSelect.dispatchEvent(new Event('change'));

document.getElementById('place_order').addEventListener('click', function(e) {
    const paymentMethod=document.getElementById('payment_method_select').value;
    if(!paymentMethod) {
        alert("Please select a payment method.");
        e.preventDefault();
        return;
    }
    this.disabled = true; // disable button to prevent multiple clicks
    this.innerText="Placing Order..."; // change button text to indicate processing
    this.closest('form').submit();

});
</script>


</body>
</html>
