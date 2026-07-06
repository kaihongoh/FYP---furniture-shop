<?php
require_once(__DIR__ . "/../includes/config.php");
session_start(); //start session to store temporary messages

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


// acive and inactive product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']); // get product id safely as integer
    
    // check is active or inactive, check if admin wants to deactivate a product
    if (isset($_POST['inactive_product'])) {
        $conn->begin_transaction();
        try{
        // update product status to inactive 
        $updateProductInactive = $conn->prepare("UPDATE product SET status = 'inactive' WHERE Product_ID = ?");
        $updateProductInactive->bind_param("i", $product_id);
        $updateProductInactive->execute();
        $updateProductInactive->close();

        // also set all variants of this product to inactive
        $updateVariantInactive=$conn->prepare("UPDATE product_variant SET status = 'inactive' WHERE Product_ID = ?");
        $updateVariantInactive->bind_param("i", $product_id);
        $updateVariantInactive->execute();
        $updateVariantInactive->close();

        $conn->commit();
        $_SESSION['message'] = 'deactivated'; //store success message in session
        } catch(Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = 'error';
        }
    } elseif (isset($_POST['active_product'])) {
        $conn->begin_transaction();
        try{
        //if want to set active, need to check the category is active or not
        $check_category=$conn->prepare("SELECT category.status FROM category
                            JOIN product ON category.Category_ID = product.Category_ID 
                            WHERE product.Product_ID = ?");
            $check_category->bind_param("i", $product_id);
            $check_category->execute();
            $category_status=$check_category->get_result()->fetch_assoc()['status'];
            $check_category->close();
            if(strtolower($category_status) !== 'active') {
                $_SESSION['error']="Please make sure the category is active status before activate the product status.";
                header("Location: manage_product.php");
                exit(); 
            }
        //update product status to active
        $updateProductActive = $conn->prepare("UPDATE product SET status = 'active' WHERE Product_ID = ?");
        $updateProductActive->bind_param("i", $product_id);
        $updateProductActive->execute();
        $updateProductActive->close();

        // also set all variants of this product to active
        $updateVariantActive=$conn->prepare("UPDATE product_variant SET status = 'active' WHERE Product_ID = ?");
        $updateVariantActive->bind_param("i", $product_id);
        $updateVariantActive->execute();
        $updateVariantActive->close();

        $conn->commit();
        $_SESSION['message'] = 'activated';
        } catch(Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = 'error';
        }
    }
    
    //go back to same page while keeping search filter content
    $search = [];
    if (isset($_GET['productname']) && !empty($_GET['productname'])) {
        $search['productname'] = $_GET['productname'];
    }
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $search['category'] = $_GET['category'];
    }

    
    $redirect_url = "manage_product.php";
    if (!empty($search)) {
        $redirect_url .= "?" . http_build_query($search);
    }
    
    header("Location: $redirect_url");
    exit();
}

// retrieve and clear session message (if exists)
$message=null;
if (isset($_SESSION['success'])) {
    $message = 'success';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

//count total active product items
$countActiveProduct = $conn->prepare("SELECT COUNT(*) FROM product WHERE status = 'active'");
$countActiveProduct->execute();
$countActiveProduct->bind_result($totalActiveProducts);
$countActiveProduct->fetch();
$countActiveProduct->close();

//count total inactive product items
$countInactiveProduct = $conn->prepare("SELECT COUNT(*) FROM product WHERE status != 'active'");
$countInactiveProduct->execute();
$countInactiveProduct->bind_result($totalInactiveProducts);
$countInactiveProduct->fetch();
$countInactiveProduct->close();



$filter = "";
//filter by product name
if (isset($_GET['productname']) && !empty($_GET['productname'])) {
    $filter .= " AND product.Product_Name LIKE '%".$_GET['productname']."%'";
}
//filter by category
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $filter .= " AND category.Category_Name = '".$_GET['category']."'";
}

if (isset($_GET['eco_friendly']) && ($_GET['eco_friendly']=='0' || $_GET['eco_friendly']=='1')) {
    $filter .= " AND product.eco_friendly = " . intval($_GET['eco_friendly']);
}
//retrieve product list with category information
$productsQuery = "SELECT product.*, category.Category_Name FROM product INNER JOIN category 
                    ON product.Category_ID = category.Category_ID 
                    WHERE 1" . $filter;

$productsQuery .= " ORDER BY product.Product_ID";

$productsResult = $conn->query($productsQuery);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - HomeNest</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="../css/manage_product.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    
</head>

<body>
   <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Product Management</h1>
            <div class="header-right">
                <div class="user-info">
                </div>
            </div>
        </div>
    

        <div class="content">
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error" id="errorAlert">
                    <?=htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
<?php if (!empty($message)): ?>

    <div class="alert alert-<?php echo ($message == 'error') ? 'error' : 'success'; ?>" id="successAlert">
       <?php 
       if ($message == "activated"): 
            echo "The product status has been activated"; 
        elseif ($message == "deactivated"):  
            echo "The product status has been deactivated"; 
		elseif ($message == "success"):  
             echo "The product has been added successfully"; 
        elseif ($message == "error"): 
             echo "There was an error updating the product status."; 
        endif;
        ?>
        </div>

<?php endif; ?>


            <h2 class="page-title">Product Overview</h2>

            <div class="cards">
                <div class="card"> <!--product overview of total active product-->
                    <div class="card-header">
                        <span class="card-title">Total Active Products</span>
                    </div>
                    <div class="card-value"><?php echo $totalActiveProducts; ?></div>
                    <div class="card-footer">All active products</div>
                </div>
                <div class="card">
                    <div class="card-header"><!--product overview of total inactive product-->
                        <span class="card-title">Total Inactive Products</span>
                    </div>
                    <div class="card-value"><?php echo $totalInactiveProducts; ?></div>
                    <div class="card-footer">All inactive products</div>
                </div>
            </div>
<form action="" method="GET" class="filter-form">
            <div class="search-filters">
                <div class="filter-group">  
                    <label for="productSearch">Search Products</label>
                    <input type="text" id="productSearch" 
                    placeholder="Name or ID..." class="search-input" 
                    name="productname" 
                    value="<?php echo isset($_GET['productname']) ? htmlspecialchars($_GET['productname']) : ''; ?>">
                </div>

                <div class="filter-group">
                    <label for="categoryFilter">Category</label>
                    <select id="categoryFilter" class="form-control" name="category">
                        <option value="">All Categories</option>
                        <?php 
                        
                        $categoriesQuery = "SELECT * FROM category ORDER BY Category_Name";
                        $categoriesResult = $conn->query($categoriesQuery);

                    while ($category = $categoriesResult->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($category['Category_Name']); ?>">
                                <?php echo htmlspecialchars($category['Category_Name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">  
                    <label>Eco Friendly</label>
                    <select name="eco_friendly">
                        <option value="">All</option>
                        <option value="1" <?=(isset($_GET['eco_friendly']) && $_GET['eco_friendly']=='1') ? 'selected' : ''?>>Eco Friendly only</option>
                        <option value="0"<?=(isset($_GET['eco_friendly']) && $_GET['eco_friendly']=='0') ? 'selected' : ''?>>Non Eco Friendly</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-search">Search
                </button>
            </div>
            </form>

            <div class="action-bar">
                <a href="AddProduct.php" class="btn btn-primary btn-large"> Add Product
                </a>
            </div>


             <!-- product grid display-->
            <div class="product-grid">
                <?php if ($productsResult->num_rows > 0): ?>
                    <?php while($product = $productsResult->fetch_assoc()): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if (!empty($product['Product_Picture'])): ?>
                                    <img src="../uploads/products/<?php echo htmlspecialchars($product['Product_Picture']); ?>"
                                        onerror="this.src='../uploads/placeholder.jpg'"
                                         alt="<?php echo htmlspecialchars($product['Product_Name']); ?>">
                                    <?php else: ?>
                                    <div class="no-image">
                                        <span>No Image</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="product-info">
                                <h4 title="<?php echo htmlspecialchars($product['Product_Name']); ?>">
                                    <?php echo htmlspecialchars($product['Product_Name']); ?>
                                </h4>
                                <p class="category">
                                <?php echo htmlspecialchars($product['Category_Name']); ?>
                                </p>
                                <?php if($product['eco_friendly']): ?>
                                    <span class="eco-tag">Eco-Friendly</span>
                                <?php endif; ?>
                            </div>
                                        
                                <div class="product-actions">
                                        <!-- edit button -->
                                        <a href="EditProduct.php?id=<?php echo $product['Product_ID']; ?>" 
                                        class="btn btn-edit">Edit
                                        </a>
                                        <!--manage variant-->
                                        <a href="manage_variant.php?Product_ID=<?php echo $product['Product_ID']; ?>" 
                                        class="btn btn-manage">Manage
                                        </a>
                                        <!--active button change-->
                                <?php if (strtolower($product['status']) === 'active'): ?>
                                    <form method="POST" action="manage_product.php">
                                        <input type="hidden" name="product_id" value="<?php echo $product['Product_ID']; ?>">
                                        <input type="hidden" name="inactive_product" value="Inactive">
                                        <button type="submit" class="btn btn-delete" 
                                                onclick="return confirm('Are you sure you want to mark this product as inactive? This will hide it from customers.');">
                                                Make Inactive
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="manage_product.php">
                                        <input type="hidden" name="product_id" value="<?php echo $product['Product_ID']; ?>">
                                        <input type="hidden" name="active_product" value="Active"> 
                                        <button type="submit" class="btn btn-primary" 
                                                onclick="return confirm('Are you sure you want to activate this product? It will be visible to customers.');">
                                                Make Active
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-products-found">
                        <h3>No products found</h3>
                        <p>Try adjusting your search filters or add a new product.</p>

                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
    // Auto-hide success message after 5 seconds
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
