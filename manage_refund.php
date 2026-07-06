<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


//get all refund requests
$refunds=$conn->query("SELECT order_refunds.*, orders.Order_Number, users.User_Name, 
product.Product_Name, product_variant.Color, product_variant.Variant_Image
FROM order_refunds
LEFT JOIN orders ON order_refunds.Order_ID = orders.Order_ID
LEFT JOIN users ON order_refunds.User_ID = users.User_ID
LEFT JOIN product_variant ON order_refunds.Variant_ID = product_variant.Variant_ID
LEFT JOIN product ON product_variant.Product_ID = product.Product_ID
ORDER BY order_refunds.Created_At DESC");


//count total pending refund
$countPendingRefunds=$conn->prepare("SELECT COUNT(*) FROM order_refunds WHERE Status = 'Pending'");
$countPendingRefunds->execute();
$countPendingRefunds->bind_result($totalPendingRefunds);
$countPendingRefunds->fetch();
$countPendingRefunds->close();

//count total approve refund
$countApprovedRefunds=$conn->prepare("SELECT COUNT(*) FROM order_refunds WHERE Status = 'Approved'");
$countApprovedRefunds->execute();
$countApprovedRefunds->bind_result($totalApprovedRefunds);
$countApprovedRefunds->fetch();
$countApprovedRefunds->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Refund Requests</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_refund.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Manage Refund Requests</h1>
            <div class="header-right">
                <div class="user-info"></div>
            </div>
        </div>

        <div class="content">
        <h2 class="page-title">Refunds Overview</h2>
            <div class="cards">
                <div class="card"> 
                    <div class="card-header">
                        <span class="card-title">Total Pending Refunds</span>
                    </div>
                    <div class="card-value"><?php echo $totalPendingRefunds; ?></div>
                    <div class="card-footer">All pending refunds</div>
                </div>
                <div class="card">
                    <div class="card-header"><!--product overview of total inactive product-->
                        <span class="card-title">Total Approved Refunds</span>
                    </div>
                    <div class="card-value"><?php echo $totalApprovedRefunds; ?></div>
                    <div class="card-footer">All approved refunds</div>
                </div>
            </div>
        <div class="refund-table-container">
            <?php if($refunds->num_rows>0): ?> 
            <table class="refund-table">
                <tr>
                   <!-- <th>Refund ID</th>-->
                    <th>Order Number</th>
                    <th>User ID</th>
                    <th>Product Name</th>
                    <th>Color</th>
                    <th>Quantity</th>
                    <th>Amount</th>
                    <!--<th>Reason</th>-->
                    <th>Requested At</th>
                    <th>Status</th>
                    <th>Return Action</th>
                    <th>Action</th>
                </tr>
                <?php while($row=$refunds->fetch_assoc()): ?>
                <tr>
                    <!--<td><?=htmlspecialchars($row['Refund_ID']) ?></td>-->
                    <td><?=htmlspecialchars($row['Order_Number']) ?></td>
                    <td><?=htmlspecialchars($row['User_ID']) ?></td>
                    <td>
                            <?php
                            if($row['type'] == 'shipping'){
                                echo "Shipping Fee";
                            } elseif($row['type'] == 'tax'){
                                echo "Tax";
                            } else{
                                echo htmlspecialchars($row['Product_Name']);
                            }
                            ?>
                        </td>
                
                    
                    <td>
                            <?php
                            if($row['type'] == 'shipping' || $row['type'] == 'tax'){
                                echo "-";
                            } else{
                                echo htmlspecialchars($row['Color']);
                            }
                            ?>
                        </td>
                    
                    <td><?=htmlspecialchars($row['Refund_Quantity']) ?></td>
                    <td>RM <?=number_format($row['Refund_Amount'], 2) ?></td>
                    <!--<td><?=nl2br(htmlspecialchars(substr($row['Reason'] ?? '', 0, 30))) ?>...</td>-->
                    <td><?=date('Y-m-d H:i', strtotime($row['Created_At'])) ?></td>
                    <td class="status-badge status-<?=strtolower($row['Status']) ?>">
                        <?=htmlspecialchars($row['Status']) ?>
                    </td>
                    <td><?=htmlspecialchars($row['Return_Action'] ?? '-') ?></td>
                    <td><a href="manage_refund_details.php?refund_id=<?= $row['Refund_ID'] ?>" class="btn btn-view">View Details</a></td>

                </tr>

                <?php endwhile; ?>

            </table>
        </div>

            <?php else: ?>
                <div class="no-refund-found">
                    <h3>No request refunds found</h3>
                    <p>There are currently no refund requests.</p>
                </div>
            <?php endif; ?>
    </div>
</div>
</body>
</html>
