<?php 
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

    <footer class="footer">
      <div class="footer-container">
        <div class="footer-about">
          <img
            src="image\footer_new_logo.jpeg"
            alt="HomeNest"
            class="footer-logo"
          />
          <p style="text-align:justify;">
            Some clubs are for the select few, 
            but HomeNest Family is for everyone. 
            Become a Family member and enjoy rewards, discounts, 
            inspirations and a few surprises all year round.
          </p>
          <div class="footer-social">
            <a
              href="https://www.facebook.com"
              target="_blank"
              ><img src="image\fblogo.png" alt="facebook"
            /></a>
            <a href="https://www.instagram.com" target="_blank"
              ><img src="image\instalogo.png" alt="instagram"
            /></a>
            <a href="https://x.com" target="_blank"
              ><img src="image\X-Logo-Round-Color.png" alt="X"
            /></a>
          </div>
        </div>

        <div class="footer-links">
          <h3 class="footer-heading">Quick Links</h3>
          <ul class="footer-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="AboutUs.php">About Us</a></li>
            <li><a href="order_history.php">Order History</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h3 class="footer-heading">Categories</h3>
          <ul class="footer-links">
          <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                      <li>
                        <a 
                            href="product.php?category=<?= htmlspecialchars($category['Category_ID']) ?>">
                            <?= htmlspecialchars($category['Category_Name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php endif; ?>
          </ul>
        </div>

        <div class="footer-contact">
          <h3 class="footer-heading">Contact Us</h3>
          <p>
            Level 1, 2, Jalan PJU 7/2, Mutiara Damansara,
            47800 Petaling Jaya, Selangor
          </p>
          <p> 03-7952 7575</p>
          <p>customerservice.homenest@homenest.asia</p>
          <p>Mon-Sun: 9:30 am - 10:00 pm</p>
          <p style="text-align:justfy;">
            For HomeNest Business inquiries, you can reach them at +603 7730 0194 or via email at business.homenest@homenest.asia. 
          </p>

          <h4 style="color: white; margin-top: 20px">We Accept</h4>
          <div class="payment-methods">
            <img
              src="image\visa.png"
              alt="Visa"
              style="height: 30px; margin-right: 10px"
            />
            <img
              src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/1280px-Mastercard-logo.svg.png"
              alt="Mastercard"
              style="height: 30px"
            />
          </div>
        </div>
      </div>

      <div class="footer-copyright">
        <p>
          &copy; 2026 HomeNest. All Rights Reserved. |
          <a
            href="#"
            >Privacy Policy</a
          >
          |
          <a
            href="#"
            >Terms of Service</a
          >
        </p>
      </div>
    </footer>
