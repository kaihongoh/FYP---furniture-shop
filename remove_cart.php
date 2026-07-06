<?php
session_start();
require_once 'includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id<=0) {
    $_SESSION['error'] = "Invalid item.";
    header("Location: product.php");
    exit();
}

if(isset($_SESSION['user_id'])) {
    //logged in user, remove cart item from database
    $user_id = $_SESSION['user_id'];
    $delete=$conn->prepare("DELETE FROM cart WHERE Cart_ID = ? AND User_ID = ?");
    $delete->bind_param("ii", $id, $user_id);
    $delete->execute();
    $_SESSION['success']="Item remove from cart successfully.";
} else {
    //guest user, remove cart item from session
    if(isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
        $_SESSION['success']="Item remove from cart successfully.";
    }
}

header("Location: shopping_cart.php");
exit();
