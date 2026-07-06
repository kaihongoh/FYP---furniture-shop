<?php

require_once 'includes/config.php';

// take active category from database
$category_sql = "SELECT Category_ID, Category_Name FROM category WHERE Status = 'Active' ORDER BY Category_Name";
$category_result = mysqli_query($conn, $category_sql);
$categories = [];
if ($category_result) {
    while($cat = mysqli_fetch_assoc($category_result)) {
        $categories[] = $cat;
    }
}
$current_category=isset($_GET['category']) ? (int)$_GET['category'] : 0;


?>
    <header>
      <div class="header-container">
        <a href="home.php">
          <img
            class="logo"
            src="image\logo.jpeg"
            alt="HomeNest logo"
          />
        </a>
        <div class="search-container">
          <form id="searchForm" action="product.php" method="GET">
            <input
              type="text"
              name="search"
              placeholder="Search for fresh products..."
              class="search-input"
              value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                
            />
            <button type="submit" class="search-button">
              Search
            </button>
          </form>
        </div>
        <div class="link-after-login">
          <?php if (isset($_SESSION['user_id'])): ?>
            <!-- user already login/register-->
          <a href="shopping_cart.php">Shopping Cart</a>
          <a href="order_history.php">Order History</a>
          <a href="user_profile.php">Profile</a>
          <a href="logout.php">Log Out</a>
          <?php else: ?>
            <!-- user not login/register -->
          <a href="login.php">Log In</a>
          <a href="shopping_cart.php">Shopping Cart</a>
          <?php endif; ?>
        </div>

      </div>




      <nav class="navbar">
        <ul class="nav-links">
          <li>
            <a href="product.php"
            class="category-link <?=($current_category==0 && !isset($_GET['popular'])) ? 'active' : '' ?>">
              All Products
            </a>
          </li>
          <li>
            <a href="popular.php?popular=1"
            class="category-link <?= isset($_GET['popular']) ? 'active' : '' ?>">
              👑 Popular Products
            </a>
          </li>

          <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                      <li>
                        <a 
                            href="product.php?category=<?= htmlspecialchars($category['Category_ID']) ?>" 
                            class="category-link <?= $current_category == $category['Category_ID'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($category['Category_Name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
          </nav>
        </header>
        
