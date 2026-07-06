<nav class="sidebar">
    <div class="sidebar-brand"><i class="bi bi-shop"></i> HomeNest Admin</div>
    <div class="d-flex flex-column">
        <div class="sidebar-heading">Main</div>
        
        <?php
        // Get current filename to set active class
        $current_page = basename($_SERVER['PHP_SELF']);
        ?>

        <a href="admin_dashboard.php" class="nav-link <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>

        <a href="manage_user.php" class="nav-link <?php echo ($current_page == 'manage_user.php' || $current_page == 'manage_user_order.php') ? 'active' : ''; ?>">
            <i class="bi bi-people-fill"></i> Manage User
        </a>
        
        <a href="manage_order.php" class="nav-link <?php echo ($current_page == 'manage_order.php' || $current_page == 'manage_order_details.php') ? 'active' : ''; ?>">
            <i class="bi bi-receipt"></i> Manage Order
        </a>

        <a href="manage_refund.php" class="nav-link <?php echo ($current_page == 'manage_refund.php') ? 'active' : ''; ?>">
            <i class="bi bi-cash-stack"></i> Manage Refund
        </a>

        <a href="manage_category.php" class="nav-link <?php echo ($current_page == 'manage_category.php') ? 'active' : ''; ?>">
            <i class="bi bi-layers-fill"></i> Manage Category
        </a>

        <a href="manage_product.php" class="nav-link <?php echo ($current_page == 'manage_product.php' || $current_page == 'AddProduct.php' || $current_page == 'EditProduct.php' || $current_page == 'manage_variant.php') ? 'active' : ''; ?>">
            <i class="bi bi-box-seam"></i>Manage Product
        </a>

        <a href="manage_chatbot.php" class="nav-link <?php echo ($current_page == 'manage_chatbot.php' || $current_page == 'AddKnowledge.php' || $current_page == 'EditKnowledge.php') ? 'active' : ''; ?>">
            <i class="bi bi-chat-dots-fill"></i>Manage Chatbot
        </a>

        <a href="manage_level_tag.php" class="nav-link <?php echo ($current_page == 'manage_level_tag.php') ? 'active' : ''; ?>">
            <i class="bi bi-trophy-fill"></i>Manage Level
        </a>

        <a href="manage_voucher.php" class="nav-link <?php echo ($current_page == 'manage_voucher.php') ? 'active' : ''; ?>">
            <i class="bi bi-ticket-perforated-fill"></i>Manage Voucher 
        </a>

        <a href="manage_shipping.php" class="nav-link <?php echo ($current_page == 'manage_shipping.php') ? 'active' : ''; ?>">
            <i class="bi bi-truck"></i>Manage Shipping 
        </a>

            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
        <a href="manage_admin.php" class="nav-link <?php echo ($current_page == 'manage_admin.php') ? 'active' : ''; ?>">
            <i class="bi bi-shield-lock"></i>
            <span>Manage Admins</span>
        </a>
        <?php endif; ?>
        
        <div class="sidebar-heading mt-2">Account</div>
        <a href="admin_logout.php" class="nav-link text-danger">
         <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>

</nav>