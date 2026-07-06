<?php
session_start();
require_once 'includes/config.php';
header('Content-Type: application/json');

if(!isset($_GET['id']) || !isset($_GET['action'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please try again.']);
    exit();
}


$id = (int)$_GET['id'];
$action = $_GET['action'];
$is_logged_in=isset($_SESSION['user_id']);

if(!in_array($action, ['increase', 'decrease'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

if($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    if($action === 'increase') {
        $update=$conn->prepare("UPDATE cart 
        SET Quantity = Quantity + 1 
        WHERE Cart_ID=? 
        AND User_ID=? 
        AND Quantity < (SELECT Stock FROM product_variant WHERE Variant_ID = cart.Variant_ID)");
        $update->bind_param("ii", $id, $user_id);
        $update->execute();

        if($update->affected_rows === 0) {
            echo json_encode(["success"=> false,"message"=> "Out of stock"]);
            exit();
        }
    } else  if($action === 'decrease') {
        $check=$conn->prepare("SELECT Quantity FROM cart WHERE Cart_ID = ? AND User_ID = ?");
        $check->bind_param("ii", $id, $user_id);
        $check->execute();
        $quantity=$check->get_result()->fetch_assoc();
        $qty=$quantity['Quantity'];

        if($qty == 1) {
            $del=$conn->prepare("DELETE FROM cart WHERE Cart_ID = ? AND User_ID = ?");
            $del->bind_param("ii", $id, $user_id);
            $del->execute();
            echo json_encode(['success'=> true,'deleted'=> true]);
            exit();        
        } else {
            //only decrease when quantity >1
            $update=$conn->prepare("UPDATE cart SET Quantity = Quantity-1 WHERE Cart_ID = ? AND User_ID = ? AND Quantity > 1");
            $update->bind_param("ii", $id, $user_id);
            $update->execute();
        }
    }
    $new_quantity=$conn->prepare("SELECT Quantity FROM cart WHERE Cart_ID = ? AND User_ID = ?");
    $new_quantity->bind_param("ii", $id, $user_id);
    $new_quantity->execute();
    $new_qty=$new_quantity->get_result()->fetch_assoc();
    if($new_qty) {
        echo json_encode(['success'=> true,'new_quantity'=> $new_qty['Quantity']]);
    } else {
        echo json_encode(['success'=> true,'deleted'=> true]);
    }
    exit();
} else {
    //guest user, update cart item in session
    if(!isset($_SESSION['cart'][$id])) {
        echo json_encode(['success' => false, 'message' => 'Item not found in cart.']);
        exit();
    }   
    $current_qty=$_SESSION['cart'][$id]['quantity'];    
    if($action === 'increase') {
        $stock_query = $conn->prepare("SELECT Stock FROM product_variant WHERE Variant_ID = ?");
        $stock_query->bind_param("i", $id);
        $stock_query->execute();
        $stock_result=$stock_query->get_result()->fetch_assoc();
        $stock=$stock_result['Stock'];

        if($current_qty >= $stock) {
            echo json_encode(['success' => false, 'message' => "Sorry. Only $stock items are available in stock."]);
            exit();
        }
        $_SESSION['cart'][$id]['quantity']++;
    } else if($action === 'decrease') {
        if($current_qty == 1) {
            unset($_SESSION['cart'][$id]);
            echo json_encode(['success'=> true, 'deleted'=>true]);
            exit();
        } else {
            $_SESSION['cart'][$id]['quantity']--;
        }
    }
    echo json_encode(['success'=> true,'new_quantity'=>$_SESSION['cart'][$id]['quantity'] ?? 0]);
    exit();
}
?>





