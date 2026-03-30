<?php
session_start();

// Login success popup message
$loginPopup = "";
if (!empty($_SESSION['login_success'])) {
    $loginPopup = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
// Logout success popup message
$logoutPopup = "";
if (!empty($_SESSION['logout_message'])) {
    $logoutPopup = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ikea4U - Homepage</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/home.css">

</head>

<body>

<?php include_once 'includes/header.php'; ?>

<!-- login success popup -->
<?php if (!empty($loginPopup)): ?>
<div class="popup-overlay">
    <div class="popup">
        <h3>Login Successful!</h3>
        <p><?= htmlspecialchars($loginPopup) ?></p>
    </div>
</div>

<script>
    setTimeout(() => {
        document.querySelector('.popup-overlay').style.display = 'none';
    }, 2500);
</script>
<?php endif; ?>

<!-- logout success popup -->
<?php if (!empty($logoutPopup)): ?>
<div class="popup-overlay">
    <div class="popup">
        <h3>Logged Out</h3>
        <p><?= htmlspecialchars($logoutPopup) ?></p>
    </div>
</div>
<script>
    setTimeout(() => {
        document.querySelector('.popup-overlay').style.display = 'none';
    }, 2500);
</script>
<?php endif; ?>


<!-- hero section -->
<section class="hero">
    <h1>Quality Furniture for Every Space</h1>
    <p>
        Welcome<?= isset($_SESSION['user_name']) ? ', ' . htmlspecialchars($_SESSION['user_name']) : '' ?>!
        Browse thousands of furniture items with fast and reliable delivery.
    </p>

    <a href="product.php">Start Shopping</a>
</section>

<!-- Features -->
<section class="features">
    <div class="features-grid">
        <div class="feature-box">
            <h3>🛋️ Wide Selection</h3>
            <p>Thousands of furniture items for every room and style</p>
        </div>

        <div class="feature-box">
            <h3>🚚 Fast Delivery</h3>
            <p>Free shipping on orders above RM 500,000</p>
        </div>

        <div class="feature-box">
            <h3>🏆 Quality Guaranteed</h3>
            <p>High-quality materials with 5 year warranty</p>
        </div>

        <div class="feature-box">
            <h3>🔧 Assembly Service</h3>
            <p>Professional assembly available at your location</p>
        </div>
    </div>
</section>

<?php include_once 'includes/footer.php'; ?>

</body>
</html>
