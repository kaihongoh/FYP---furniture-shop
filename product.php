<?php
session_start();

require_once 'includes/config.php';

$category_id=isset ($_GET['category'])? (int)$_GET['category'] : 0;

// get all active products
$sql = "SELECT product.Product_ID, product.Product_Name, 
product.Product_Picture, product.Product_Description, 
category.Category_ID, category.Category_Name 
FROM product  
LEFT JOIN category ON product.Category_ID = category.Category_ID 
WHERE product.status = 'Active'";

$params = [];
$types="";

//check category active
if($category_id> 0){
    $category_check=$conn->prepare("SELECT Category_ID FROM category WHERE Category_ID=? AND Status='Active'");
    $category_check->bind_param("i",$category_id);
    $category_check->execute();
    $category_check->store_result();
    if($category_check->num_rows > 0){
        $sql .=" AND product.Category_ID = ?";
        $params[]=$category_id;
        $types .='i';
    } else {
        $category_id=0;
    }
    $category_check->close();
}

$stmt= $conn->prepare($sql);
if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products= $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


//get all active category 
$category_stmt= $conn->prepare("SELECT Category_ID, Category_Name 
FROM category WHERE Status='Active'
ORDER BY Category_Name ASC");
$category_stmt->execute();
$categories=$category_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$category_stmt->close();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Product - Ikea4U</title>
    <link rel="stylesheet" href="css/template.css"> 
    <link rel="stylesheet" href="css/product.css">

</head>
<body>

<?php include_once 'includes/header.php'; ?>

<div class="container">

    <div class="category-bar">
        <a href="product.php" class="category-link" <?= $category_id==0 ? 'active' : '' ?>> All products</a>
        <?php foreach($categories as $category): ?>
            <a href="product.php?category=<?= $category['Category_ID'] ?>" class="category-link" <?= $category_id==$category['Category_ID'] ? 'active' : '' ?>> 
                <?= htmlspecialchars($category['Category_Name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="no-products">
            <h3>No products found.</h3>
            <p>We could not find any products in this category.</p>
            <a href="product.php">Browse All Products</a>
        </div>
    <?php else: ?>  

        <div class="product-grid">
            <?php foreach($products as $prod): 
            $image_path="uploads/".$prod['Product_Picture'];
            $image_exists=!empty($prod['Product_Picture']) && file_exists($image_path);

                $price_stmt=$conn->prepare("SELECT MIN(Price) as min_price FROM product_variant WHERE Product_ID=? AND Status='Active' ");
                $price_stmt->bind_param("i", $prod["Product_ID"]);
                $price_stmt->execute();
                $min_price=$price_stmt->get_result()->fetch_assoc()['min_price'];
                $price_stmt->close();
            ?>

                <div class="product-card">
                    <div class="image-wrapper">
                        <img src="<?=$image_exists ? $image_path : 'uploads/placeholder.jpg' ?>" 
                             alt="<?= htmlspecialchars($prod['Product_Name']) ?>"
                             class="product-image"
                             onerror="this.src='uploads/placeholder.jpg'">  
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?= htmlspecialchars($prod['Product_Name']) ?></h3>
                        <div class="category-name"><?= htmlspecialchars($prod['Category_Name']) ?></div>
                        <div class="product-price"> RM <?= number_format($min_price, 2) ?></div>
                        <a href="product_details.php?id=<?= $prod['Product_ID'] ?>" class="view-details">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
        

<?php include_once 'includes/footer.php'; ?>

</body>
</html>

