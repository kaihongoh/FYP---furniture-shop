<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id=$_SESSION['user_id'];
$address_id=isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if($address_id> 0) {
    $_SESSION['error'] = "Invalid address id";
    header("Location: address_list.php");
    exit();
}

//varify the address is belong to current user 
$stmt = $conn->prepare("SELECT Address_ID FROM user_address WHERE Address_ID=? AND User_ID=?"); 
$stmt->bind_param("ii", $address_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0) {
    $_SESSION["error"] = "Address not found.";
    header("Location: address_list.php");
    exit();
}

//clear other address default, and set new address as default
$conn->begin_transaction();
try{
    $reset=$conn->prepare("UPDATE user_address SET Is_Default=0 WHERE User_ID=?");
    $reset->bind_param("i",$user_id);
    $reset->execute();

    $set=$conn->prepare("UPDATE user_address SET Is_Default=1 WHERE User_ID=? AND Address_ID=?");
    $set->bind_param("ii",$user_id, $address_id);
    $set->execute();

    $conn->commit();
    $_SESSION["success"] = "You have set this address as default successfully!";
} catch(Exception $e) {
    $conn->rollback();
    $_SESSION["error"] = "Failed to set as default address. Please try again.";
}

header("Location: address_list.php");
exit();