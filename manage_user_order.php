<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['User_ID']) || empty($_GET['User_ID'])) {
    header("Location: manage_user.php");
    exit();
}

$user_id=$_GET['User_ID'];

//get all order of the user
$getOrder=$conn->prepare("SELECT Order_ID, Order_Number, Order_Date, Order_Status, Total_Amount
FROM orders WHERE User_ID=?
ORDER BY Order_Date DESC");
$getOrder->bind_param("i",$user_id);
$getOrder->execute();
$order=$getOrder->get_result();
$getOrder->close();

//get user total completed order
$getTotalCompleteOrder=$conn->prepare("SELECT COUNT(*) as total_completed FROM orders WHERE User_ID=? AND Order_Status='completed'");
$getTotalCompleteOrder->bind_param("i",$user_id);
$getTotalCompleteOrder->execute();
$getTotalCompleteOrder->bind_result($totalCompleted);
$getTotalCompleteOrder->fetch();
$getTotalCompleteOrder->close();

//get user total partial refund order
$getTotalPartialOrder=$conn->prepare("SELECT COUNT(*) as total_partial_refunded FROM orders WHERE User_ID=? AND Order_Status='partially_refunded'");
$getTotalPartialOrder->bind_param("i",$user_id);
$getTotalPartialOrder->execute();
$getTotalPartialOrder->bind_result($totalPartialRefunded);
$getTotalPartialOrder->fetch();
$getTotalPartialOrder->close();

//get user info
$userInfo=$conn->query("SELECT User_Name FROM users WHERE User_ID=$user_id");
$user=$userInfo->fetch_assoc();
$userName=$user['User_Name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Orders - <?=htmlspecialchars($user['User_Name'])?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_user.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
    <div class="content">
        <div class="action-bar">
            <a href="manage_user.php" class="btn btn-secondary btn-large" style="text-decoration: none;">
                Back to user List
            </a>
        </div>
        <h1>User Orders - <?=htmlspecialchars($user['User_Name'])?></h1>
        <br>
        <div class="cards">
        <div class="card"> <!--product overview of total completed orders-->
            <div class="card-header">
                <span class="card-title">Total Completed Orders</span>
            </div>
            <div class="card-value"><?php echo $totalCompleted; ?></div>
            <div class="card-footer">All completed orders</div>
        </div>
        <div class="card">
            <div class="card-header"><!--product overview of total partial refund orders-->
                <span class="card-title">Total Partial Refunded Orders</span>
            </div>
            <div class="card-value"><?php echo $totalPartialRefunded; ?></div>
            <div class="card-footer">All partial refunded orders</div>
        </div>
    </div>
    
    <div class="user-container">
        <?php if ($order->num_rows > 0): ?>
            <table class="user-table">
                <tr>
                    <th>Order ID</th>
                    <th>Order Number</th>
                    <th>Order Date</th>
                    <th>Status</th>
                    <th>Total Amount</th>
                    <th>View Order Details</th>
                </tr>
                <?php while ($row = $order->fetch_assoc()): ?>
                    <tr>
                        <td><?=htmlspecialchars($row['Order_ID'])?></td>
                        <td><?=htmlspecialchars($row['Order_Number'])?></td>
                        <td><?=htmlspecialchars($row['Order_Date'])?></td>
                        <td><?=htmlspecialchars($row['Order_Status'])?></td>
                        <td>RM <?=htmlspecialchars($row['Total_Amount'])?></td>
                        <td><a href="manage_order_details.php?Order_ID=<?=htmlspecialchars($row['Order_ID'])?>" class="btn btn-view">View</a></td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
                    <div class="no-order-found">
                        <h3>No orders found</h3>
                    </div>
            <?php endif; ?>
    </div>
</div>
</div>  
    
</body>
</html>