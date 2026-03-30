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
?>
    <header>
      <div class="header-container">
        <a href="home.php">
          <img
            class="logo"
            src="image\Ikea4U-logo (1).png"
            alt="Ikea4U logo"
          />
        </a>
        <div class="search-container">
          <form id="searchForm" action="catalogue.php" method="GET">
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
          <a href="profile.php">Profile</a>
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
          <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a 
                            href="product.php?category=<?= htmlspecialchars($category['Category_ID']) ?>" 
                            class="category-link"
                        >
                            <?php echo htmlspecialchars($category['Category_Name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
              <li>
                <a
                  href="catalogue.php?category=1"
                  class="category-link"
                >
                  Armchairs
                </a>
              </li>
              <li>
                <a
                  href="catalogue.php?category=2"
                  class="category-link"
                >
                  Sofas
                </a>
              </li>
              <li>
                <a
                  href="catalogue.php?category=3"
                  class="category-link"
                >
                  Storage Boxes & Baskets
                </a>
              </li>
              <li>
                <a
                  href="catalogue.php?category=4"
                  class="category-link"
                >
                  Beds & Mattresses
                </a>
              </li>
              <li>
                <a
                  href="catalogue.php?category=5"
                  class="category-link"
                >
                  Kitchen Cabinets
                </a>
              </li>
              <li>
                <a
                  href="catalogue.php?category=6"
                  class="category-link"
                >
                  Laundry & Cleaning
                </a>
              </li>
              <li>
                <a
                  href="catalogue.php?category=7"
                  class="category-link"
                >
                  Home Decoration
                </a>
              </li>
            <?php endif; ?>
            </ul>
          </nav>
        </header>
        