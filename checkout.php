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
product.Product_Description,
product.status AS product_status, product_variant.status AS variant_status
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
$valid_item=[];

while($item=$cart_result->fetch_assoc()){
    //check if the product or variant is inactive, then show alert message and redirect back to shopping cart 
    if(strtolower($item['product_status']) !== 'active' || strtolower($item['variant_status']) !== 'active') {
               $_SESSION['error']="Some item in your cart is no longer available. Please remove it from your cart.";
                header("Location: shopping_cart.php");
                exit();
            }

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
                $valid_item[]=$item; //store valid item for display
}
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

    <form action="place_order.php" method="POST" id="checkoutForm" novalidate>
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
    <?php
    $shipping_address=$address['address_line1'];
    if(!empty($address['address_line2'])) {
        $shipping_address .=', ' . $address['address_line2'];
    }
    $shipping_address .=', ' . $address['city'] . ', ' . $address['postcode'] . ', ' . $address['State'];
    ?>
    <p><?=htmlspecialchars($address['Full_Name'])?></p>
    <p><?=htmlspecialchars($address['Phone'])?></p>
    <p>
        <?php if(!empty($address['Unit_No'])): ?>
            <?=htmlspecialchars($address['Unit_No'])?>, 
        <?php endif; ?>
        <?=htmlspecialchars($shipping_address)?>
    </p>

    <a href="address_list.php?id=<?=$address['Address_ID']?>"class="btn btn-large">Change</a> 
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
        <?php foreach($valid_item as $item): ?> 
            <?php 
                $final_picture="";
                if(!empty($item['Variant_Image'])) {
                    $final_picture='uploads/variants/' . $item['Variant_Image'];
                } else {
                    $final_picture='uploads/products/' . $item['Product_Picture'];
                }
            $item_total=$item['Price'] * $item['Quantity'];
            ?>
            <div class="checkout-items">
                <div class="product-picture">
                    <img src="<?=htmlspecialchars($final_picture)?>" width="80">
                </div> 
                <div class="product-info"> 
                    <div class="product-name">
                        <?=htmlspecialchars($item['Product_Name'])?> 
                    </div>
                    <div class="product-description">
                        <?=htmlspecialchars(substr($item['Product_Description'], 0, 50))."..."; ?>
                    </div>
                    <div class="product-color">
                        Color: <?=htmlspecialchars($item['Color'])?>
                    </div>
                    <div class="product-quantity">
                        Quantity: <?=$item['Quantity']?>
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
        <?php endforeach; ?>  
    </div>  
    <div class="product-subtotal">
        <span>Subtotal</span> 
        <span>RM <?=number_format($subtotal, 2)?></span>
    </div>
    </div>


<div class="payment-container">
    <?php 
    $setting=$conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
    $tax_rate = $setting['Tax_Rate']/100;
    $tax=$subtotal * $tax_rate; // calculate tax based on tax rate from settings

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


    $discount=0;
    $total=$subtotal + $tax + $shipping_fee - $discount;
    ?>

    <input type="hidden" id="subtotal" value="<?=$subtotal?>">
    <input type="hidden" id="tax" value="<?=$tax?>">
    <input type="hidden" id="shipping_fee" value="<?=$shipping_fee?>">

    <div class="payment">
        <h2>Payment</h2>
            <label value="Card">Credit Card</label>
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
    </div>
    </div>
            </div>

    <div class="checkout-bottom">
        <div class="voucher-section">
            <div class="voucher">
                <input type="text" id="voucher_code" name="voucher_code" placeholder="Enter voucher code">
                <button type="button" id="apply_voucher">Apply</button>
                <p id="voucher_message"></p>
            </div>
        </div>
        <div class="summary-section">
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

            <div class="place-order-container">
                <button type="submit" id="place_order">Place Order</button>
                <a href="shopping_cart.php" class="cancel-btn" id="cancelBtn" onclick="return confirm ('Are you sure you want to cancel checkout and go back to cart?');">Cancel</a>
            </div>

        </div>
    </div>


</form>

<?php include_once 'includes/footer.php'; ?>


<script>
function showAutoAlert(message) {
    const alertBox=document.createElement('div');
    alertBox.className='alert alert-error';
    alertBox.innerText=message;
    document.body.appendChild(alertBox);

    setTimeout(() => {
        alertBox.remove();
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() { 
    const form=document.getElementById('checkoutForm');
    if(!form) {
        return;
    }
    const cardNumberInput=document.getElementById('card_number');
    const cardHolderInput=document.getElementById('card_holder');
    const expiryInput=document.getElementById('expiry_date');
    const cvvInput=document.getElementById('cvv');

    const cardNumberError=document.getElementById('card_number_error');
    const cardHolderError=document.getElementById('card_holder_error');
    const expiryError=document.getElementById('expiry_error');
    const cvvError=document.getElementById('cvv_error');
    
    const placeBtn=document.getElementById('place_order');


    //display inline error message and apply error style
    function showError(input, errorElement, message) {
        if (input) input.classList.add('error');
        if (errorElement) {
            errorElement.innerText = message;
            errorElement.style.display = 'block';
        }   
    }
    //clear error message and remove error style
    function clearError(input, errorElement) {
        if (input) input.classList.remove('error');
        if (errorElement) {
            errorElement.innerText = '';
            errorElement.style.display = 'none';
        }
    }

    function validateCardNumber() {
        const raw=cardNumberInput.value.replace(/\s/g, '');
        const valid=/^\d{16}$/.test(raw);
        if(raw.length===0) {
            showError(cardNumberInput, cardNumberError, 'Card number is required.');
            return false;
        }
        if(!valid) {
            showError(cardNumberInput, cardNumberError, 'Please enter a valid 16 digits card numbers.');
            return false;   
        } else {
            clearError(cardNumberInput, cardNumberError);
        }
        return valid;
    }

    //only accept number , auto fill blank
    if(cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            let value=this.value.replace(/\D/g, '').slice(0,16);
            let formatted=value.match(/.{1,4}/g) || [];
            this.value=formatted.join(' ').trim();
            clearError(this, cardNumberError);
        });
    }

    function validateCardHolder() {
        const value=cardHolderInput.value.trim();
        const valid=/^[a-zA-Z\s]+$/.test(value);
        if(value.length===0) {
            showError(cardHolderInput, cardHolderError, 'Card holder name is required.');
            return false;
        }
        if(!valid) {
            showError(cardHolderInput, cardHolderError, 'Only letters and spaces are allowed.');
            return false;
        } else {
            clearError(cardHolderInput, cardHolderError);
        }
        return valid;
    }

    //only accept letter and space
    if(cardHolderInput) {
        cardHolderInput.addEventListener('input', function() {
            this.value=this.value.replace(/[^a-zA-Z\s]/g, '');
            clearError(this, cardHolderError);
        });
    }

    function validateExpiryDate() { 
        const value=expiryInput.value;
        const valid=/^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(value);
        if(value.length===0) {
            showError(expiryInput, expiryError, 'Expiration date is required.');
            return false;
        }
        if(!valid) {
            showError(expiryInput, expiryError, 'Invalid date format.');
            return false; 
           }
        
            const [month,year]=value.split('/');
            const expiryYear=2000 + parseInt(year,10);
            const expiryMonth=parseInt(month,10);

            const now=new Date();
            const currentYear=now.getFullYear();
            const currentMonth=now.getMonth()+1;
                
            if(expiryYear < currentYear || (expiryYear === currentYear && expiryMonth < currentMonth)) {
                showError(expiryInput, expiryError, 'Card has expired. Please enter a valid expiration date.');
                return false;
            }//clear the error and reset error message
            clearError(expiryInput, expiryError);
            return valid;
    }

    if(expiryInput) {
        expiryInput.addEventListener('input', function() {
            let value=this.value.replace(/\D/g, '').slice(0,4);
            if(value.length>=2) {
                this.value=value.slice(0,2) + '/' + value.slice(2,4);
            } else {
                this.value=value;
            }
            clearError(this, expiryError);  
        });
    }


    function validateCVV() {
        const value=cvvInput.value;
        const valid=/^\d{3}$/.test(value);
        if(value.length===0) {
            showError(cvvInput, cvvError, 'CVV number is required.');
            return false;
        }
        if(!valid) {
            showError(cvvInput, cvvError, 'CVV must be 3 digit.'); 
            return false;  
        } else {
            clearError(cvvInput, cvvError);
        }
        return valid;
    }
    //CVV only accept 3 digit number
    if(cvvInput) {
        cvvInput.addEventListener('input', function() {
            this.value=this.value.replace(/\D/g, '').slice(0,3);
            clearError(this, cvvError);
        });
    }

    //clear error messages while user is typing or selecting

    cardHolderInput.addEventListener('input', () => clearError(cardHolderInput, cardHolderError));
    expiryInput.addEventListener('input', () => clearError(expiryInput, expiryError));
    cvvInput.addEventListener('input', () => clearError(cvvInput, cvvError));

    //validate input fields when user leaves the field (blur)
    cardNumberInput.addEventListener('blur', validateCardNumber);
    cardHolderInput.addEventListener('blur', validateCardHolder);
    expiryInput.addEventListener('blur', validateExpiryDate);
    cvvInput.addEventListener('blur', validateCVV);
    
    // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateCardNumber(),
            validateCardHolder(),
            validateExpiryDate(),
            validateCVV(),
        ];

        const isValid = validations.every(v => v === true);

        if (isValid) {
            placeBtn.disabled = true;
            const cancelBtn=document.getElementById('cancelBtn');
            if(cancelBtn) {
                cancelBtn.disabled=true;
            }
            placeBtn.innerText = 'Placing order...';
        form.submit();
    } else {
        placeBtn.disabled=false;
        const cancelBtn=document.getElementById('cancelBtn');
        if(cancelBtn) {
            cancelBtn.disabled=false;
        }
        placeBtn.innerText="Place order";
        showAutoAlert("Please complete the require field before paying."); 
}  
});



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
        '<span style="color:red;">'+ data.message +'</span>';
    }
});
});

//show/hide payment details based on selected payment method
//const paymentSelect=document.getElementById('payment_method_select');

/*
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
*/
});
</script>


</body>
</html>
