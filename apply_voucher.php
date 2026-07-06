<?php 
session_start();
require_once 'includes/config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || !isset($_SESSION['checkout_items'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please go back to cart.']);
    exit();
}

if(!isset($_POST['voucher_code']) || $_POST['voucher_code'] === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter a voucher code.']);
    exit();
}

$user_id=$_SESSION['user_id'];
$items=$_SESSION['checkout_items'] ?? [];
$voucher_code=trim($_POST['voucher_code'] ?? '');

$subtotal=0;

if(!empty($items)) {
    //recalculate subtotal
    $placeholder=implode(',', array_fill(0, count($items), '?'));
    $sql="SELECT cart.Quantity, product_variant.Price
    FROM cart
    JOIN product_variant ON cart.Variant_ID = product_variant.Variant_ID
    WHERE cart.Cart_ID IN ($placeholder) AND cart.User_ID = ?";
    $stmt=$conn->prepare($sql);
    $types=str_repeat('i', count($items)).'i';
    $params=array_merge($items,[$user_id]);
    $stmt->bind_param($types,...$params);
    $stmt->execute();
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc()) {
        $subtotal+=$row['Quantity'] * $row['Price'];
        }
} 

//check voucher
    $check_voucher=$conn->prepare("SELECT * FROM vouchers 
    WHERE Voucher_Code = ? 
    AND Status = 'Active' 
    AND (Expiry_Date IS NULL OR DATE(Expiry_Date)>= CURDATE())
    AND (Usage_Limit >0 AND Used_Count < Usage_Limit)");

    $check_voucher->bind_param("s", $voucher_code);
    $check_voucher->execute();
    $voucher=$check_voucher->get_result()->fetch_assoc();

            if(!$voucher) {
                echo json_encode(['success' => false, 'message' => 'Invalid voucher code.']);
                exit();
            } 

            //check level
            $check_level=$conn->prepare("SELECT level_id FROM users WHERE User_ID=?");
            $check_level->bind_param("i", $user_id);
            $check_level->execute();
            $user_level_id=$check_level->get_result()->fetch_assoc()['level_id'];
            $check_level->close();

            if(!empty($voucher['restricted_to_level_id']) && $user_level_id !=$voucher['restricted_to_level_id']) {
                echo json_encode(['success' => false, 'message' => 'This voucher is only for specific membership levels.']);
                exit();
            }

            //check subtotal reach minimum spend 
            if($subtotal < $voucher['Minimum_Spend']) {
                echo json_encode(['success' => false, 'message' => 'Minimum spend of RM'.number_format($voucher['Minimum_Spend'], 2).' not reached.']);
                exit();
            } 

            //check voucher usage per user
            $usage_stmt=$conn->prepare("SELECT COUNT(*) as used 
                FROM voucher_usage WHERE User_ID=?
                AND Voucher_ID=?");
                $usage_stmt->bind_param("ii", $user_id, $voucher['Voucher_ID']);
                $usage_stmt->execute();
                $usage_data=$usage_stmt->get_result()->fetch_assoc();
                $used=$usage_data['used'];

                if(($voucher['Usage_Per_User'] <=0) || $used>=$voucher['Usage_Per_User']) {
                    echo json_encode(['success'=> false, 'message' => 'You have used this voucher over the limit.']);
                    exit();
                }

            //calculate discount
            $discount=0;
            if($voucher['Discount_Type'] === 'percentage') {
                    $discount = $subtotal * ($voucher['Discount_Value'] / 100);
                    if(!is_null($voucher['Max_Discount']) && $discount > $voucher['Max_Discount']) {
                        $discount=$voucher['Max_Discount'];
                    }
                } else {
                    $discount = $voucher['Discount_Value'];
                }

            $discount=min($discount,$subtotal);// discount cannot exceed subtotal
            $discount=round($discount,2);

            //save session
            $_SESSION['voucher_id'] = $voucher['Voucher_ID'];
            $_SESSION['discount'] = $discount;
            echo json_encode(['success' => true, 'discount' => $discount]);


    ?>
