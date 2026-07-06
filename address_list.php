<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id=$_SESSION['user_id'];



$success=$_SESSION['success'] ?? '';
$error=$_SESSION['error'] ??'';
unset($_SESSION['success']);  
unset($_SESSION['error']);

//check user all address, the default address will be first one
$stmt = $conn->prepare('SELECT * FROM user_address 
WHERE User_ID=? 
ORDER BY Is_Default DESC, Created_At DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result=$stmt->get_result();
$address=$result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Address</title>
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/address_list.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>

    <div class="container">
            <div class="profile-wrapper">
                <div class="profile-sidebar">
                    <?php
                    // Get current filename to set active class
                    $current_page = basename($_SERVER['PHP_SELF']);
                    ?>
                    <a href="user_profile.php" class="nav-link <?php echo ($current_page == 'user_profile.php') ? 'active' : ''; ?>">
                        User Profile
                    </a>        
                    <a href="address_list.php" class="nav-link <?php echo ($current_page == 'address_list.php') ? 'active' : ''; ?>">
                        My Address
                    </a>
                </div>
            </div>

        <div class="address_list-container">
            <div class="form-header">
                <h2 class="address-title">My Address</h2>
                <p class="form-subtitle">Manage your shipping address</p>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success" id="successAlert">
                    <?=htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="error-message" id="errorAlert">
                    <?=htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="address-list">
                <div>
                    <a href="add_address.php" class="add-address-btn">+ Add new address</a>
                </div>

                <?php if(empty($address)): ?>
                    <div class="empty-message">
                        <p>You have no saved address. Lets click "Add new address" to add one.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($address as $adrs): ?>
                        <div class="address-info">
                            <p>
                                <strong><?=htmlspecialchars($adrs['Full_Name']) ?></strong>
                                <?php if ($adrs['Is_Default'] ): ?>
                                    <span class="default">Default</span>
                                <?php endif; ?>
                                <span class="address-label"><?=htmlspecialchars($adrs['Label']) ?></span>
                            </p>

                            <p>
                                <?php                             
                                $full_address=$adrs['address_line1'];
                                if(!empty($adrs['address_line2'])) {
                                    $full_address .=', ' . $adrs['address_line2'];
                                }
                                $full_address .=', ' . $adrs['city'] . ', ' . $adrs['postcode'] . ', ' . $adrs['State'];
                                echo htmlspecialchars($full_address);
                                ?>
                            </p>
                            <p><i>Phone: </i><?=htmlspecialchars($adrs['Phone']) ?></p>
                        </div>
                        <div class="address-action">
                            <a href="edit_address.php?id=<?= $adrs['Address_ID'] ?>" 
                            class="edit-btn">Change</a>
                            <?php if (!$adrs['Is_Default']): ?>
                                <a href="set_default_address.php?id=<?= $adrs['Address_ID'] ?>" 
                                class="setDefault-btn" 
                                onclick="return confirm('Set this as your default address?');">
                                Set as Default</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    <script src="js/address.js"></script>
    <script src="js/alert.js"></script>
</body>
</html>
