<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    // check is active or inactive, check if admin wants to deactivate a user
    if (isset($_POST['inactive_user'])) {
        // update user status to inactive 
        $inactive = $conn->prepare("UPDATE users SET status = 'inactive' WHERE User_ID = ?");
        $inactive->bind_param("i", $user_id);
        if ($inactive->execute()) { //store success message in session
            $_SESSION['message'] = 'deactivated';
        } else { //store error message in session
            $_SESSION['message'] = 'error';
        }
        $inactive->close();
    } elseif (isset($_POST['active_user'])) {
        //update user status to active
        $active = $conn->prepare("UPDATE users SET status = 'active' WHERE User_ID = ?");
        $active->bind_param("i", $user_id);
        if ($active->execute()) {
            $_SESSION['message'] = 'activated';
        } else {
            $_SESSION['message'] = 'error';
        }
        $active->close();
    }
    //take the hidden input from post form 
    $redirect_url = "manage_user.php";
    if (isset($_POST['search']) && !empty($_POST['search'])) {
        $redirect_url .= "?search=" . urlencode($POST['search']);
    }
    header("Location: $redirect_url");
    exit();
    //go back to same page while keeping search filter content
    $search = [];
    if (isset($_GET['UserName']) && !empty($_GET['UserName'])) {
        $search['UserName'] = $_GET['UserName'];
    }

    
    
}
//count total royal users
$countRoyalUser = $conn->prepare("SELECT COUNT(*) FROM users WHERE level_id=? AND status='active'"); 
$level_id = 2; // 2 is the level_id for royal users
$countRoyalUser->bind_param("i", $level_id);
$countRoyalUser->execute();
$countRoyalUser->bind_result($totalRoyalUsers);
$countRoyalUser->fetch();
$countRoyalUser->close();

//check level id=2 is what name
$levelName="SELECT level_type FROM level_tag WHERE id=?";
$name=$conn->prepare($levelName);
$name->bind_param("i", $level_id);
$name->execute();
$name->bind_result($currentName);
$name->fetch();
$name->close();

//count total normal users
$countNormalUser = $conn->prepare("SELECT COUNT(*) FROM users WHERE level_id IS NULL");
$countNormalUser->execute();
$countNormalUser->bind_result($totalNormalUsers);
$countNormalUser->fetch();
$countNormalUser->close();

//get user total spend
//$getTotalSpend=$conn->prepare("SELECT total_spent FROM users WHERE User_ID=?");
//$getTotalSpend->bind_param("i",$_GET['User_ID']);
//$getTotalSpend->execute();
//$getTotalSpend->bind_result($totalSpend);
//$getTotalSpend->fetch();
//$getTotalSpend->close();

//get user total order, all order event is paid, or partially refunded, or fully refunded, all of them count as a order
//$getTotalOrder=$conn->prepare("SELECT users.User_ID, users.User_Name, users.email, users.total_spent, level_tag.level_type
//(SELECT COUNT(*) FROM orders WHERE User_ID=users.User_ID) as total_orders
//FROM users LEFT JOIN level_tag ON users.level_id = level_tag.level_id
//WHERE users.User_ID = ?"); 
//$getTotalOrder->bind_param("i",$_GET['User_ID']);
//$getTotalOrder->execute();
//$getTotalOrder->bind_result($totalOrders);
//$getTotalOrder->fetch();
//$getTotalOrder->close();

$filter = "";
//filter by user name
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $keyword=$conn->real_escape_string($_GET['search']);
    $filter .= " AND (users.User_Name LIKE '%$keyword%' OR users.User_ID LIKE '%$keyword%')";
}

//retrieve user list 
$userQuery = "SELECT users.User_ID, users.User_Name, users.email, users.total_spent, users.status, level_tag.level_type,
(SELECT COUNT(*) FROM orders WHERE User_ID=users.User_ID) as total_orders
 FROM users LEFT JOIN level_tag 
                    ON users.level_id = level_tag.id 
                    WHERE 1" . $filter;

$userQuery .= " ORDER BY users.User_ID";

$usersResult = $conn->query($userQuery);

// retrieve and clear session message (if exists)
$message=null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_user.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1>User Management</h1>
                <div class="header-right">
                    <div class="user-info"></div>
                </div>
            </div>
        <div class="content">
<?php if (!empty($message)): ?>

    <div class="alert alert-<?php echo ($message == 'error') ? 'error' : 'success'; ?>" id="successAlert">
       <?php 
       if ($message == "activated"): 
            echo "The user status has been activated"; 
        elseif ($message == "deactivated"):  
            echo "The user status has been deactivated"; 
        elseif ($message == "error"): 
             echo "There was an error updating the user status."; 
        endif;
        ?>
        </div>

<?php endif; ?>
<h2 class="page-title">Users Overview</h2>

    <div class="cards">
        <div class="card"> <!--overview of total royal users-->
            <div class="card-header">
                <span class="card-title">Total <?php echo htmlspecialchars($currentName); ?> Users</span>
            </div>
            <div class="card-value"><?php echo $totalRoyalUsers; ?></div>
            <div class="card-footer">All <?php echo htmlspecialchars($currentName); ?> users</div>
        </div>
        <div class="card">
            <div class="card-header"><!--overview of total normal users-->
                <span class="card-title">Total Normal Users</span>
            </div>
            <div class="card-value"><?php echo $totalNormalUsers; ?></div>
            <div class="card-footer">All normal users</div>
        </div>
    </div>

    <form action="" method="GET" class="filter-form">
        <div class="search-filters">
            <div class="filter-group">
                <label for="userSearch">Search Users</label>
                <input type="text" id="userSearch" 
                placeholder="User name..." class="search-input" 
                name="search" 
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-search">Search</button>
        </div>
    </form>
    
    <div class="user-table-container">
        <?php if ($usersResult->num_rows > 0): ?>
    <table class="user-table">
        <tr>
            <th>User ID</th>
            <th>User Name</th>
            <th>Email</th>
            <th>Level</th>
            <th>Total Spend</th>
            <th>Total Orders</th>
            <th>View Orders</th>
            <th>Action</th>
        </tr>
        
        <?php while ($user=$usersResult->fetch_assoc()): ?>
            <tr>
                <td><?=$user['User_ID']?></td>
                <td><?=$user['User_Name']?></td>
                <td><?=$user['email']?></td>
                <td><?=$user['level_type']?></td>
                <td>RM <?=number_format($user['total_spent'], 2)?></td>
                <td><?=$user['total_orders']?></td>
                <td><a href="manage_user_order.php?User_ID=<?=$user['User_ID']?>" class="btn btn-view">View</a></td>
                <td>
                <?php if (strtolower($user['status']) === 'active'): ?>
                    <form method="POST" action="manage_user.php">
                        <input type="hidden" name="user_id" value="<?php echo $user['User_ID']; ?>">
                        <input type="hidden" name="inactive_user" value="Inactive">
                        <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';?>">
                        <button type="submit" class="btn btn-delete" 
                            onclick="return confirm('Are you sure you want to mark this user as inactive?');">
                            Make Inactive
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="manage_user.php">
                        <input type="hidden" name="user_id" value="<?php echo $user['User_ID']; ?>">
                        <input type="hidden" name="active_user" value="Active">
                        <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';?>">
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you want to activate this user?');">
                            Make Active
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
            <?php else: ?>
                <div class="no-user-found">
                    <h3>No users found</h3>
                    <p>Try adjusting your search filters.</p>
                </div>
            <?php endif; ?>
        </div>   
    </div> 
</div>

<script>
        // show success message only 5 seconds 
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            successAlert.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                successAlert.style.display = 'none';
            }, 500);
        }, 5000);
    }

</script>
</body>
</html>
