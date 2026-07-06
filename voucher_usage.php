<?php
require_once(__DIR__ . "/../includes/config.php");
session_start();


if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


if(!isset($_GET['voucher_id']) || empty($_GET['voucher_id'])) {
    header("Location: manage_voucher.php");
    exit();
}

$voucher_id=(int)$_GET['voucher_id'];

$get_voucher=$conn->prepare("SELECT Voucher_Code, Voucher_Name FROM vouchers WHERE Voucher_ID=?");
$get_voucher->bind_param("i", $voucher_id);
$get_voucher->execute();
$voucher=$get_voucher->get_result()->fetch_assoc();

if(!$voucher){
    echo "Voucher not found."; //should show in the manage_voucher page
    header("Location: manage_voucher.php");
    exit();
}

//get voucher usage for every user
$get_usage=$conn->prepare("SELECT voucher_usage.Usage_ID, voucher_usage.Voucher_ID, voucher_usage.Used_At,
orders.Order_ID, orders.Order_Number, orders.Total_Amount, orders.Order_Status, users.User_ID,
users.User_Name, users.email
FROM voucher_usage 
JOIN orders ON voucher_usage.Order_ID=orders.Order_ID
JOIN users ON voucher_usage.User_ID=users.User_ID
WHERE voucher_usage.Voucher_ID=?
ORDER BY voucher_usage.Used_At DESC");

$get_usage->bind_param("i", $voucher_id);
$get_usage->execute();
$usages=$get_usage->get_result();
$get_usage->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Usage for <?=htmlspecialchars($voucher['Voucher_Code']) ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="../css/voucher_usage.css">

</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Voucher Usage for <?php echo htmlspecialchars($voucher['Voucher_Code'])?></h1>
            <div class="header-right">
                <div class="user-info"></div>
            </div>
        </div>
            <div class="action-bar">
                <a href="manage_voucher.php" class="btn btn-secondary btn-large" style="text-decoration: none;">
                     Back to Vouchers
                </a>
            </div>
        <div class="content">

       <div class="usage-table-container">
    <?php if($usages->num_rows>0): ?>
    <table class="usage-table">
        <tr>
            <th>User ID</th>
            <th>Order Number</th>
            <th>Order Total</th>
            <th>Order Status</th>
            <th>Used At</th>
            <th>View Order</th>
        </tr>
        <?php while ($usage=$usages->fetch_assoc()): ?>
            <tr>
                <td><?=$usage['User_ID'] ?></td>
                <td><?=$usage['Order_Number'] ?></td>
                <td>RM <?=number_format($usage['Total_Amount'], 2)?></td>
                <td><?=$usage['Order_Status'] ?></td>
                <td><?=date('d-m-Y H:i', strtotime($usage['Used_At']))?></td>
                <td>
                    <a href="manage_order_details.php?Order_ID=<?=$usage['Order_ID']?>" class="btn btn-view">View</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        </div>
        <?php else: ?>
</div>
        <div class="no-usage-found">
            <h3>No usage founds</h3>
            <p>This voucher has not been use by any user yet.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>