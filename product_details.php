<?php
session_start();
require_once 'includes/config.php';

//check product id
if (!isset($_GET['id']) || !is_numeric ($_GET['id'])) {
    header("Location: product.php");
    exit();
}

$product_id = (int)$_GET["id"];

//get product info 
$stmt= $conn->prepare("SELECT * FROM product WHERE Product_ID=? AND status='Active'");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product= $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$product) {
    header("Location: product.php");
    exit();    
}

//get variant
$stmt= $conn->prepare("SELECT Variant_ID, Color, Price, Stock, Variant_Image
FROM product_variant
WHERE Product_ID=? AND status='Active'
ORDER BY Price ASC");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$variants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if(empty($variants)) {
    header("Location: product.php");
    exit();      
}

//default image is product picture
$image_path="uploads/" . $product['Product_Picture'];
$image_exists = !empty($product['Product_Picture']) && file_exists($image_path);

$default_image_url= $image_exists ? $image_path : 'uploads/placeholder.jpg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['Product_Name']); ?> - Ikea4U</title> 
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/product_details.css">

</head>
<body>

<?php include_once 'includes/header.php'; ?>

<div class="container">
    <a href="product.php" class="btn-back">← Back</a>
    
    <div class="product-details">
        <div class="product-image">
            <img id="product-image" 
            src="<?=$image_exists ? $image_path : 'uploads/placeholder.jpg' ?>" 
            alt="<?= htmlspecialchars($product['Product_Name']) ?>"
            onerror="this.src='uploads/placeholder.jpg'">
        </div>

        <div class="product-info">  
            <h1><?= htmlspecialchars($product['Product_Name']); ?></h1>
            <p class="product-description"><?= nl2br(htmlspecialchars($product['Product_Description'] ?? '')) ?></p>

            <!--price, default will hidden-->
            <div id="price" class="price">RM </div>
        </div>

        <div class="color-selector">
            <label>Color: </label>
            <div class="color-buttons" id="color-buttons">
                <?php foreach($variants as $variant): 
                    $disabled=($variant['Stock'] <=0) ?>
                    <button type="button" class="color-btn <?= $disabled ? 'disabled' : '' ?>" 
                            data-variant-id="<?= $variant['Variant_ID'] ?>"
                            data-price="<?= $variant['Price'] ?>" 
                            data-stock="<?= $variant['Stock'] ?>"   
                            data-image="<?= htmlspecialchars($variant['Variant_Image'] ?? '') ?>"
                            <?=$disabled ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''?>>
                        <?= htmlspecialchars($variant['Color']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="stock-status" class="stock-status out-of-stock" >Please select color</div>


        <div class="quantity-selector">
            <input type="number" id="quantity" name="quantity" value="1" min="1"  disabled>
        </div>

        <button class="add-to-cart" id="add-to-cart" disabled>Add to Cart </button>
    </div>
</div>


<script>
    const colorButtons = document.querySelectorAll('.color-btn');
    const productImage = document.getElementById('product-image');
    const price = document.getElementById('price');
    const stockStatus = document.getElementById('stock-status');
    const quantityInput = document.getElementById('quantity');
    const addToCartBtn = document.getElementById('add-to-cart');

    let selected_variantID = null;
    let currentStock = 0;


    colorButtons.forEach(button => {
        if(button.disabled) {
            return;
        }
        button.addEventListener('click', function() {
            colorButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            selected_variantID = button.dataset.variantId;
            currentStock = parseInt(button.dataset.stock);

            //change image
            const variantImage = button.dataset.image;
            const defaultImage = "<?=$default_image_url?>";
            
            if(variantImage && variantImage.trim() !== '') {    
                productImage.src = 'uploads/' + variantImage;
            } else {
                productImage.src = defaultImage;
            }

            //update price
            price.textContent = 'RM ' + parseFloat(button.dataset.price).toFixed(2);

            //stock
            if(currentStock > 0) {
                stockStatus.textContent = 'In Stock';
                stockStatus.className = 'stock-status in-stock';
                quantityInput.disabled = false;
                addToCartBtn.disabled = false;
                quantityInput.max = currentStock;
            } else {
                stockStatus.textContent = 'Out of Stock';
                stockStatus.className = 'stock-status out-of-stock';
                quantityInput.max = 0;
                quantityInput.disabled = true;
                addToCartBtn.disabled = true;
            }
        });
    });

    quantityInput.addEventListener('input', function() {
        let value=parseInt(quantityInput.value);
        if(value < 1) {
            quantityInput.value = 1;
        } else if(value > currentStock) {
            quantityInput.value = currentStock;
        }
    });

    addToCartBtn.addEventListener('click', function() {
        if(!selected_variantID) {
            alert('Please select a color.');
            return;
        }
        const quantity = parseInt(quantityInput.value);
        if(quantity <=0 || quantity > currentStock) {
            alert('Invalid quantity. Please enter a valid quantity.');
            return;
        }   
        window.location.href = `add_cart.php?variant_id=${selected_variantID}&quantity=${quantity}`;
    });
    

</script>

<?php include_once 'includes/footer.php'; ?>
</body>
</html>