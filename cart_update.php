<?php
session_start();
require_once 'includes/config.php';

if(!isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: shopping_cart.php");
    exit();
}

$id = (int)$_GET['id'];
$action = $_GET['action'];

if(!in_array($action, ['increase', 'decrease'])) {
    header("Location: shopping_cart.php");
    exit();
}

if(isset($_SESSION['user_id'])) {
    //logged in user, update cart item in database
    $user_id = $_SESSION['user_id'];
    $cart=$conn->prepare("SELECT cart.Quantity, product_variant.Stock 
    FROM cart 
    JOIN product_variant ON cart.Variant_ID = product_variant.Variant_ID
    WHERE cart.Cart_ID = ? AND cart.User_ID = ?");
    $cart->bind_param("ii", $id, $user_id);
    $cart->execute();
    $item=$cart->get_result()->fetch_assoc();

    if(!$item) {
        header("Location: shopping_cart.php");
        exit();
    }



//use atomic update to prevent race condition, can make sure quantity not exceed available stock
    if($action === 'increase') {
        $update=$conn->prepare("UPDATE cart 
        SET Quantity = Quantity + 1 
        WHERE Cart_ID=? 
        AND User_ID=? 
        AND Quantity < (SELECT Stock FROM product_variant WHERE Variant_ID = cart.Variant_ID)");
        $update->bind_param("ii", $id, $user_id);
        $update->execute();

        //if no enough stock or inactive
        if($update->affected_rows === 0) {
            $check=$conn->prepare("SELECT product_variant.Stock
            FROM cart
            JOIN product_variant 
            ON cart.Variant_ID = product_variant.Variant_ID
            WHERE cart.Cart_ID=? AND cart.User_ID=?");
            $check->bind_param("ii", $id, $user_id);
            $check->execute();
            $item=$check->get_result()->fetch_assoc();

            if(!$item) {
                $_SESSION['error'] = "Item not found in cart.";
                header("Location: shopping_cart.php");
                exit();
            } else {
                $_SESSION['error'] = "Sorry. Only {$item['Stock']} items are available in stock.";
                header("Location: shopping_cart.php");
                exit();
            }
        }
    } 
    else if($action === 'decrease') { //only decrease when quantity >1
        $update=$conn->prepare("UPDATE cart SET Quantity = Quantity-1 WHERE Cart_ID = ? AND User_ID = ? AND Quantity > 1");
        $update->bind_param("ii", $id, $user_id);
        $update->execute();
    }
//guest user
} else {
    if(!isset($_SESSION['cart'][$id])) {
        header("Location: shopping_cart.php");
        exit();
    }

        $qty=$_SESSION['cart'][$id]['quantity'];

        $stock_query = $conn->prepare("SELECT Stock FROM product_variant WHERE Variant_ID = ?");
        $stock_query->bind_param("i", $id);
        $stock_query->execute();
        $stock_result=$stock_query->get_result()->fetch_assoc();

        if(!$stock_result) {
            $_SESSION['error'] = "Item not found in cart.";
            header("Location: shopping_cart.php");
            exit();
        }
        $stock=$stock_result['Stock'];

        if($action === 'increase') {
            if($qty >= $stock) {
                $_SESSION['error'] = "Sorry. Only $stock items are available in stock.";
                header("Location: shopping_cart.php");
                exit();
            }
            $_SESSION['cart'][$id]['quantity']++;
        } else if($action === 'decrease' && $qty > 1) {
            $_SESSION['cart'][$id]['quantity']--;
        }
    }

header("Location: shopping_cart.php");
exit();




