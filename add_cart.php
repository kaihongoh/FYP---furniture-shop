<?php
session_start();
require_once 'includes/config.php';

$variant_id = isset($_GET['variant_id']) ? (int)$_GET['variant_id'] : 0;
if($variant_id<=0) {
    $_SESSION['error'] = "Invalid product.";
    header("Location: product.php");
    exit();
}
$quantity = isset($_GET['quantity']) ? $_GET['quantity'] : 1; // default quantity to 1 if not provided

$stmt=$conn->prepare("SELECT product_variant.*,
product.Product_Name, 
product.Product_Picture, 
product.Product_Description,
product.Product_ID 
FROM product_variant JOIN product ON product_variant.Product_ID = product.Product_ID WHERE Variant_ID = ?");
$stmt->bind_param("i", $variant_id);
$stmt->execute();
$product=$stmt->get_result()->fetch_assoc();

if(!$product) {
    $_SESSION['error'] = "Product not found";
    header("Location: product.php");
    exit();
}

$stock = $product['Stock'];

//validation quantity
if(!preg_match('/^\d+$/', $quantity) || (int)$quantity < 1) {
    $_SESSION['error'] = "Quantity must be at least 1.";
    header("Location: product_details.php?id=" . $product['Product_ID']);
    exit(); 
}
$quantity=(int)$quantity;

if ($quantity > $stock) {
    $_SESSION['error'] = "Sorry. Only $stock items are available in stock.";
    header("Location:product_details.php?id=" . $product['Product_ID']);
    exit();
}
$quantity = max(1, min($quantity, $stock)); // let quantity at least 1 

if(isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    //logged in user, save cart item to database
    $check=$conn->prepare("SELECT * FROM cart WHERE User_ID = ? AND Variant_ID = ?");
    $check->bind_param("ii", $user_id, $variant_id);
    $check->execute();
    $result=$check->get_result();
    $existing_item = $result->fetch_assoc();

    if($existing_item) {
        $new_total= $existing_item['Quantity'] + $quantity;

        if($new_total > $stock) {
            $_SESSION['error'] = "You have {$existing_item['Quantity']} in cart. Only $stock items are available in stock.";
            header("Location: shopping_cart.php");
            exit();
        }
        
        // if product is already in cart, then just update the quantity
        $update=$conn->prepare("UPDATE cart SET Quantity = LEAST(Quantity + ?, ?) WHERE User_ID = ? AND Variant_ID = ?");
        $update->bind_param("iiii", $quantity, $stock, $user_id, $variant_id);
        $update->execute();
        $_SESSION['success'] = "Cart updated successfully.";
    } else {
        // Add new product to cart
        $insert=$conn->prepare("INSERT INTO cart (User_ID, Variant_ID, Quantity) VALUES (?, ?, ?)");
        $insert->bind_param("iii", $user_id, $variant_id, $quantity);
        $insert->execute();
        $_SESSION['success'] = "Item is added to cart.";
    }
} else {
    //guest user, save cart item to session
    if(!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // check the current quantity in session
    $current_qty = isset($_SESSION['cart'][$variant_id]['quantity']) ? $_SESSION['cart'][$variant_id]['quantity'] : 0;
    $new_total = $current_qty + $quantity;

    if($new_total > $stock) {
        $_SESSION['error'] = "You have {$current_qty} in cart. Only $stock items are available in stock.";
        header("Location: shopping_cart.php");
        exit();
    } else {
        // Add new product to cart
            $final_picture="";
            if(!empty($product['Variant_Image'])) {
                    $final_picture='uploads/variants/' . $product['Variant_Image'];
                } else {
                    $final_picture='uploads/products/' . $product['Product_Picture'];
                }
        $_SESSION['cart'][$variant_id] = [
            'variant_id' => $variant_id,
            'name' => $product['Product_Name'],
            'price' => $product['Price'],
            'picture' => $final_picture,
            'description' => $product['Product_Description'],
            'color' => $product['Color'],
            'quantity' => $new_total 
        ];
        $_SESSION['success'] = "Item is added to cart.";
    }
}

header("Location: shopping_cart.php");
exit();
