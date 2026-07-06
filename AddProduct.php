<?php
require_once(__DIR__ . "/../includes/config.php");
session_start();

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


$errors =[];
$product_name='';
$category_id=0;
$description='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get data
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eco_friendly = isset($_POST['eco_friendly']) ? 1 : 0;

    //validatation
    if (empty($product_name)) {
        $errors[]="Product name is required";
    } else {
        // check the product name already exists
        $check_name = $conn->prepare("SELECT Product_ID FROM product WHERE Product_Name = ?");
        $check_name->bind_param("s", $product_name);
        $check_name->execute();
        $check_name->store_result();
        
        if ($check_name->num_rows > 0) {
            $errors[] = "Product name already exists. Please create the product with different name.";
        }
        $check_name->close();
    }
    
    if ($category_id <= 0) {
        $errors[]="Please select a category";
    }

    if($description == ''){
        $errors[]="Product description is required";
    } elseif (strlen($description) < 15) {
        $errors[] = "Description must be at least 15 characters.";
    }
    

    //upload image
$image_name = ""; //default blank

if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
    $file_type = $_FILES['image']['type'];
    $file_size = $_FILES['image']['size'];

    if (!in_array($file_type, $allowed_types)) {//only accept
        $errors[] = "Only JPG, PNG, GIF, WEBP and AVIF files are allowed";
    } elseif ($file_size > 2 * 1024 * 1024) {//limit 2mb
        $errors[] = "Image size must be less than 2MB";
    } else {
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_name = uniqid() . '.' . $file_extension;
        $upload_path = '../uploads/products/' . $image_name;

        if (!file_exists('../uploads/products')) {
            mkdir('../uploads/products', 0777, true);
        }

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $errors[] = "Failed to upload image";
            $image_name = "";
        }
    }
} else {
    $errors[] = "Product image is required";
}


   // if no error, upload to database
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO product (Product_Name, Category_ID, Product_Description, Product_Picture, status, eco_friendly) 
                               VALUES (?, ?, ?, ?, 'active', ?)");
        $stmt->bind_param("sissi", $product_name, $category_id, $description, $image_name, $eco_friendly);
    
            //go back to manage_product.php
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Product added successfully!';   
                header("Location: manage_product.php");
                exit();
        } else {
            $errors[] = "Failed to add product to database: " . $conn->error;
        }
        $stmt->close();
    }
}
//get category 
$categoryResult = $conn->query("SELECT * FROM category ORDER BY Category_Name");
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | HomeNest</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/AddProduct.css">
    
</head>
<body>
<?php include_once 'admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Add New Product</h1>
            <div class="header-right">
                <div class="user-info">
                </div>
            </div>
        </div>

        <div class="content">
            <div class="action-bar">
                <a href="manage_product.php" class="btn btn-secondary btn-large" style="text-decoration: none;">
                     Back to Products
                </a>
            </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

            <form id="productForm" action="AddProduct.php" method="POST" enctype="multipart/form-data" class="product-form" novalidate>
                <div class="form-group">
                    <label for="product_name">Product Name </label>
                    <input type="text" id="product_name" name="product_name" class="form-control" 
                           value="<?= htmlspecialchars($product_name) ?>" 
                           placeholder="Enter the product name" required>
                    <small class="error-message" id="productNameError"></small>
                </div>

                <div class="form-group">
                    <label for="category_id">Category </label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php while ($row = $categoryResult->fetch_assoc()): ?>
                        <option value="<?= $row['Category_ID'] ?>"
                            <?= ($category_id == $row['Category_ID']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($row['Category_Name']); ?>
                        </option>
                    <?php endwhile; ?>
                    </select>
                    <small class="error-message" id="categoryError"></small>
                </div>
                
                
                <div class="form-group">
                    <label for="description">Product Description</label>
                    <textarea id="description" name="description" class="form-control" rows="5"
                    placeholder="Enter product description..." required>
                        <?= htmlspecialchars($description) ?>
                    </textarea>
                    <small class="error-message" id="descriptionError"></small>
                    <small class="form-text">Please provide complete description for product</small>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="eco_friendly" value="1" <?=isset($_POST['eco_friendly']) ? 'checked':''?>>
                        Eco Friendly Product
                    </label>
                </div>
                    
                <div class="form-group">
                    <label for="image">Product Image </label>
                    <input type="file" id="image" name="image" class="form-control" 
                           accept="image/jpeg,image/png,image/gif,image/webp,image/avif" required>
                    <small class="error-message" id="imageError"></small>
                    <small class="form-text">Maximum file size: 2MB. Allowed formats: JPG, PNG, GIF, WEBP, AVIF. Leave empty to keep current image.</small>
                    

                    <div id="imagePreviewContainer" style="margin-top: 15px; display: none;">
                        <div><strong>Preview:</strong></div>
                        <img id="imagePreview" style="max-width: 250px; max-height: 250px; margin-top: 10px; border: 1px solid #ddd; border-radius: 6px; padding: 8px;">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-large">
                         Save Product
                    </button>
                    <a href="manage_product.php" class="btn btn-secondary btn-large">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('productForm');
    const productName=document.getElementById('product_name');
    const category=document.getElementById('category_id');
    const description=document.getElementById('description');
    const image=document.getElementById('image');
    const submitBtn=document.getElementById('submitBtn');
    

    // get all error message element
    const productNameError=document.getElementById('productNameError');
    const categoryError=document.getElementById('categoryError');
    const descriptionError=document.getElementById('descriptionError');
    const imageError=document.getElementById('imageError');
    const previewContainer=document.getElementById('imagePreviewContainer');
    const previewImg=document.getElementById('imagePreview');


    //stop script execution if form does not exist
    if (!form) return;

    //display inline error message and apply error style
    function showError(input, errorElement, message) {
        if (input) input.classList.add('error');
        if (errorElement) {
            errorElement.innerText = message;
            errorElement.style.display = 'block';
        }
    }
    //clear error message and remove error style
    function clearError(input, errorElement) {
        if (input) input.classList.remove('error');
        if (errorElement) {
            errorElement.innerText = '';
            errorElement.style.display = 'none';
        }
    }

    // product name validation
    function validateProductName() {
        if (!productName.value.trim()) {
            showError(productName, productNameError, 'Product name is required.');
            return false;
        }
        clearError(productName, productNameError);
        return true;
    }

    //category validation
    function validateCategory() {
        if (!category.value) {
            showError(category, categoryError, 'Please select a category.');
            return false;
        }
        clearError(category, categoryError);
        return true;
    }

    //description validation
    function validateDescription() {
        if (!description.value.trim()) {
            showError(description, descriptionError, 'Product description is required.');
            return false;
        } else if (description.value.trim().length < 15) {
            showError(description, descriptionError, 'Description must be at least 15 characters.');
            return false;
        }
        clearError(description, descriptionError);
        return true;
    }

    //image validation
    function validateImage() {
        if(image.files.length === 0) {
            showError(image, imageError, 'Product image is required.');
            return false;
        }
        const file=image.files[0];
        const allowType=['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
        if (!allowType.includes(file.type)) {
            showError(image, imageError, 'Only JPG, PNG, GIF, WEBP and AVIF files are allowed');
            return false;
        } else if (file.size > 2 * 1024 * 1024) {
            showError(image, imageError, 'Image size must be less than 2MB');
            return false;
        }
        clearError(image, imageError);
        return true;
    }

    //clear error messages while user is typing or selecting
    productName.addEventListener('input', () => clearError(productName, productNameError));
    category.addEventListener('change', () => clearError(category, categoryError));
    description.addEventListener('input', () => clearError(description, descriptionError));                    
    image.addEventListener('input', () => clearError(image, imageError)); //input or change

    //validate input fields when user leaves the field (blur)
    productName.addEventListener('blur', validateProductName);
    category.addEventListener('blur', validateCategory);
    description.addEventListener('blur', validateDescription);
    image.addEventListener('blur', validateImage);

    // preview image
    image.addEventListener('change', function(e) {
        if(this.files && this.files[0]) {
            const reader=new FileReader();
            reader.onload=function(e) {
                previewImg.src = e.target.result;
                previewContainer.style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        } else {
                previewContainer.style.display = 'none';
            }
        });

       // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateProductName(),
            validateCategory(),
            validateDescription(),
            validateImage()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Adding Product...';
            form.submit();
        }
    });
});


    </script>
</body>
</html>
