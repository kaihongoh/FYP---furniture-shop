<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/rating.php';

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

//get product average rating
$product_rating = getProductAverageRating($conn, $product_id);

//get all variant rating
$variant_rating=[];
foreach ($variants as $v) {
    $variant_rating[$v['Variant_ID']] = getVariantAverageRating($conn, $v['Variant_ID']);
}

//default image is product picture
if(!empty($product['Product_Picture'])){
    $default_image_url="uploads/products/" . $product['Product_Picture'];
} else{
    $default_image_url='uploads/placeholder.jpg';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['Product_Name']); ?> - HomeNest</title> 
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/product_details.css">

</head>
<body>

<?php include_once 'includes/header.php'; ?>

<div class="container">
    <a href="product.php" class="btn-back">← Back</a>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div id="successAlert" class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="product-details">
        <div class="product-image">
            <img id="product-image" 
            src="<?=$default_image_url ?>"
            alt="<?= htmlspecialchars($product['Product_Name']) ?>"
            onerror="this.src='uploads/placeholder.jpg'">
        </div>
        <div class="product-right">
            <div class="product-info">  
                <h1><?= htmlspecialchars($product['Product_Name']); ?></h1>
                <?php if($product['eco_friendly']): ?>
                    <span class="eco-tag">Eco-Friendly</span>
                <?php endif; ?>
                <p class="product-description"><?= nl2br(htmlspecialchars($product['Product_Description'] ?? '')) ?></p>

                <!--price, default will hidden-->
                <div id="price" class="price">RM </div>
            </div>
            <div id="rating-section" class="rating-section">
                <div class="product-rating"><!--default show product rating-->
                    <span class="stars" id="star-display"><?=generateStarRating($product_rating['average']) ?></span>
                    <span class="rating-count" id="rating-count">(<?=$product_rating['count'] ?> reviews)</span>
                </div>
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
                <input type="number" id="quantity" name="quantity" value="1" min="1" step="1" disabled>
            </div>

            <button class="add-to-cart" id="add-to-cart" disabled>Add to Cart </button>
        </div>
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
                productImage.src = 'uploads/variants/' + variantImage;
            } else {
                productImage.src = defaultImage;
            }

            //update price
            price.textContent = 'RM ' + parseFloat(button.dataset.price).toFixed(2);

            //stock
            if(currentStock > 0) {
                stockStatus.textContent = `In Stock (${currentStock} available)`;
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
            //update rating when select variant
            //update star display 
            fetch('get_rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `variant_id=${selected_variantID}`
            })
            .then(response => response.json())
            .then(data => {
            document.getElementById('star-display').innerHTML = data.stars;
            document.getElementById('rating-count').textContent = `(${data.count} reviews)`;
                });
        });
    });

    quantityInput.addEventListener('input', function() {
        let number=this.value.split('.')[0]; //only take infront of .
        number=number.replace(/[^0-9]/g, ''); //delete symbol and text
        if(number===''){
            this.value=1;
            return;
        }
        let value=parseInt(number,10);
        if(value < 1) {
            value = 1;
        } else if(value > currentStock) {
            value = currentStock;
        }
        this.value=value;
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

<?php include_once 'includes/footer.php'; ?>
</body>
</html>
