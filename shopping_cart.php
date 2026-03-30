<?php
session_start();
require_once 'includes/config.php';

//show alert message
$error= $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

$subtotal = 0; 
$cart_items = [];
$is_logged_in = isset($_SESSION['user_id']);

if($is_logged_in) {
    //already login user, fetch cart items from database
    $stmt = $conn->prepare("SELECT cart.Cart_ID,
    cart.Quantity,
    product.Product_Name,
    product.Product_Picture,
    product.Product_Description,
    product_variant.Variant_Image,
    product_variant.Price,
    product_variant.Color,
    product_variant.Stock
    FROM cart
    JOIN product_variant ON cart.Variant_ID = product_variant.Variant_ID
    JOIN product ON product_variant.Product_ID = product.Product_ID
    WHERE cart.User_ID = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    //guest user, fetch cart items from session
        $cart_items = $_SESSION['cart'] ?? [];
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/shopping_cart.css">
    <title>Shopping cart</title>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>

    <div class="container-title">
        <h1>Shopping Cart</h1>
    </div>

    <!--display error and success message-->
    <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!--prompt user to login if they want to checkout-->
    <?php if(!$is_logged_in && !empty($cart_items)): ?>
        <div class="login-prompt">
            <p>Login to save your cart items and proceed to checkout</p>
            <a href="login.php">Login</a>
        </div>
    <?php endif; ?>
    
    <?php if(empty($cart_items)): ?>
    <div class="empty-cart">
        <div class="card-body">
            <h4>No items in cart</h4>
            <p>You shopping cart is currently empty</p>
            <a href="product.php" class="start-shopping">Start Shopping</a>
        </div>
    </div>
    <?php else: ?>

<form action="checkout.php" method="POST">
    <div class="container-cart">
        <div class="cart-header">
            <input type="checkbox" id="select_all">Select all
        </div>
        <div class="cart-items">
            <!-- Shopping cart items -->
             <?php foreach($cart_items as $item): 
             if($is_logged_in) { // fetch product details from the database using the product ID
                $id = $item['Cart_ID'];
                $name=$item['Product_Name'];
                $description=$item['Product_Description'];
                $price=$item['Price'];
                $picture=!empty($item['Variant_Image']) ? $item['Variant_Image'] : $item['Product_Picture'];
                $color=$item['Color'];
                $quantity=$item['Quantity'];
                $total_price = $price * $quantity;
             } else {
                // temporary data for guest user, data store in session
                $id=$item['variant_id'];
                $name=$item['name'];
                $description=$item['description'];
                $price=$item['price'];
                $picture=$item['picture'];
                $color=$item['color'];
                $quantity=$item['quantity'];
                $total_price = $price * $quantity;
             }
            $subtotal += $total_price;
                ?>
            <div class="cart-item">
                <!--checkbox--> <!--value="< ?php echo $id; ?>"-->
                <input type="checkbox" class="item-checkbox" name="selected_items[]" value="<?=$is_logged_in ? $item['Cart_ID'] : $item['variant_id']?>" 
                data-price="<?php echo $total_price; ?>"
                data-qty="<?php echo $quantity; ?>">
                <img src="uploads/<?php echo !empty($picture) ? htmlspecialchars($picture) : 'placeholder.jpg'; ?>" 
                alt="<?php echo htmlspecialchars($name); ?>" width="120" height="120">
                
                <div class="item-details">
                <h3><?php echo htmlspecialchars($name); ?></h3>
                <p><?php echo htmlspecialchars(substr($description, 0, 50))."..."; ?></p>


                <?php if(!empty($color)): ?>
                <p>Color: <?php echo htmlspecialchars($color); ?></p>
                <?php endif; ?>
                </div>

                <div class="item-action">
                    <div class="quantity-control">
                        <a href="cart_update.php?id=<?php echo $id; ?>&action=decrease">-</a>
                        <span><?php echo $quantity; ?></span>
                        <a href="cart_update.php?id=<?php echo $id; ?>&action=increase">+</a>
                    </div>
                    <p class="item-price">RM<?php echo number_format($price, 2); ?></p>
                    <p>Total: RM<?php echo number_format($total_price, 2); ?></p>
                    <a href="remove_cart.php?id=<?php echo $id; ?>" onclick="return confirm('Remove this items from cart?')">Remove</a>
                </div>
        <hr>
        <?php endforeach; ?>
        </div>

        <div class="cart-summary">
            <?php
            $setting=$conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
            $tax_rate = $setting['Tax_Rate']/100;
            $tax=$subtotal * $tax_rate;
            $total = $subtotal + $tax;
            ?>
            <!-- Cart summary -->
             <p>Subtotal: RM <span id="subtotal_display"><?php echo number_format($subtotal, 2); ?></span></p>
             <p>Tax (<?= $setting['Tax_Rate'] ?>%): RM <span id="tax_display"><?php echo number_format($tax, 2); ?></span></p>
             <p>Total: RM <span id="total_display"><?php echo number_format($total, 2); ?></span></p>
             <!--checkout button-->
             <?php
             if($is_logged_in): ?>
                <button type="submit" id="checkout_button">Checkout (<span id="checkout_count">0</span> items)</button>
                <?php else: ?>
                    <button onclick="window.location.href='login.php'">Please login to save your cart and proceed to checkout</button>
                    <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
                </form>


    <?php include_once 'includes/footer.php'; ?>

    <script>
    //select all checkbox
    const selectAll=document.getElementById('select_all');
    const items=document.querySelectorAll('.item-checkbox');

    if(selectAll) {
        selectAll.addEventListener('change',function(){
            items.forEach(item=>{
                item.checked=this.checked;
            });
            updateSummary();
            updateCheckoutButton();
            updateItemCount();
        });
    }

//if cancel one item then select all checkbox will untick
items.forEach(function(item) {
    item.addEventListener('change',function(){
        if(!selectAll) return;
        const checkedItems=document.querySelectorAll('.item-checkbox:checked');

        if(checkedItems.length===items.length) {
            selectAll.checked=true;
        } else {
            selectAll.checked=false;
        }
        updateSummary();
        updateCheckoutButton();
        updateItemCount();
    });
});

//dynamic calculate total base selected item
const checkboxes=document.querySelectorAll('.item-checkbox');
function updateSummary() {
    let subtotal=0;
    checkboxes.forEach(function(box) {
        if(box.checked) {
            subtotal += parseFloat(box.dataset.price);
        }
    });

    const taxRate=<?php echo $setting['Tax_Rate']/100 ?>;
    const tax=subtotal * taxRate;
    const total = subtotal + tax;

    document.getElementById('subtotal_display').innerText = subtotal.toFixed(2);
    document.getElementById('tax_display').innerText = tax.toFixed(2);
    document.getElementById('total_display').innerText = total.toFixed(2);
}           



//disable checkout button if no item select
function updateCheckoutButton() {
    const checkedItems=document.querySelectorAll('.item-checkbox:checked');
    const checkoutBtn=document.getElementById('checkout_button');

    if(checkedItems.length===0) {
        checkoutBtn.disabled=true;
        checkoutBtn.style.opacity="0.5";
        checkoutBtn.innerText="Select item to checkout";
    } else {
        checkoutBtn.disabled=false;
        checkoutBtn.style.opacity="1";   
        checkoutBtn.innerText="Checkout ("+ checkedItems.length + " items)";    
    }
}


//calculate item quantity for (show in the checkout item)
function updateItemCount() {
    let count=0;
    checkboxes.forEach(function(box) {
        if(box.checked) {
            count += parseInt(box.dataset.qty);
        }
    });
    document.getElementById("checkout_count").innerText=count;
}
//checkbox change also check
checkboxes.forEach(function(box) {
    box.addEventListener('change',function() {
        updateSummary();
        updateCheckoutButton();
        updateItemCount();
    });
});
//page load also check
updateSummary();
updateCheckoutButton();
updateItemCount();
    </script>
</body>
</html>

