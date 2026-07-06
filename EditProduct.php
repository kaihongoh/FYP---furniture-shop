<?php
require_once(__DIR__ . "/../includes/config.php");
session_start();

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


// Check if ID is provided (when type from browser), if error will redirect back to product list
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_product.php");
    exit();
}

$id = intval($_GET['id']);

// fetch product data
$product = null;
$get_product = $conn->prepare("SELECT * FROM product WHERE Product_ID = ?");
$get_product->bind_param("i", $id);
$get_product->execute();
$result = $get_product->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_product.php");
    exit();
}

$product = $result->fetch_assoc();
$get_product->close();

// fetch categories for dropdown
$categories = [];
$categoryResult = $conn->query("SELECT * FROM category ORDER BY Category_Name");
while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
}

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get form data
    $product_name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $description = trim($_POST['description']);
    $eco_friendly = isset($_POST['eco_friendly']) ? 1 : 0;
    //error array
    $errors=[];
// validation for edit
    if (empty($product_name)) {
        $errors[] = "Product name is required";
    } else {
        // check if product name already exists (exclude current product)
        $check_name = $conn->prepare("SELECT Product_ID FROM product WHERE Product_Name = ? AND Product_ID != ?");
        $check_name->bind_param("si", $product_name, $id);
        $check_name->execute();
        $check_name->store_result();
        
        if ($check_name->num_rows > 0) {
            $errors[] = "Product name already exists. Please choose a different name.";
        }
        $check_name->close();
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    

    //if dont have file uploads/products, then create uploads/products
    $upload_dir = '../uploads/products';

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // handle image upload
    $image_name = $product['Product_Picture']; // Keep existing by default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
        $file_type = $_FILES['image']['type'];
        $file_size = $_FILES['image']['size'];

        //validate the size and type of the file
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF , WEBP and AVIF files are allowed";
        } else if ($file_size > 2 * 1024 * 1024) {
            $errors[] = "Image size must be less than 2MB";
        } else { //no error then can upload
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = uniqid() . '.' . $file_extension;
            $upload_path = '../uploads/products/' . $image_name;

            // upload the file (directory must already exist)
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($product['Product_Picture'])) {
                    $old_image = '../uploads/products/' . $product['Product_Picture'];
                    if (file_exists($old_image)) {
                        unlink($old_image);
                    }
                }
            } else {
                $errors[] = "Failed to upload image";
                $image_name = $product['Product_Picture'];
            }
        }                  
    }

    
    // if no error then update product in database
    if (empty($errors)){
    $update_stmt = $conn->prepare("UPDATE product SET 
        Product_Name = ?, 
        Category_ID = ?, 
        Product_Description = ?, 
        Product_Picture = ?,
        eco_friendly=? 
        WHERE Product_ID = ?");
    
    $update_stmt->bind_param("sissii", 
        $product_name, 
        $category_id, 
        $description, 
        $image_name,
        $eco_friendly, 
        $id
    );
    
    if ($update_stmt->execute()) {
        $_SESSION['update_success'] = true;
        $_SESSION['update_message'] = "Product updated successfully!";
        header("Location: EditProduct.php?id=" . $id);
        exit();
    } else {
    $errors[] = "Failed to update product: " . $conn->error;
    }
    
    $update_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - HomeNest</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/EditProduct.css">


</head>
<body>
    <?php include_once 'admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Edit Product</h1>
        </div>

        <div class="content-wrapper">
            <div class="action-bar">
                <a href="manage_product.php" class="btn btn-secondary btn-large"> Back to Products</a>
            </div>

            <?php if (isset($_SESSION['update_success']) && $_SESSION['update_success']): ?>
                <div class="alert alert-success" id="successAlert">
                    <span><?php echo $_SESSION['update_message']; ?></span>
                </div>
                <?php 
                unset($_SESSION['update_success']);
                unset($_SESSION['update_message']);
                ?>
            <?php endif; ?>
                    <!--show the error if product name is repeat-->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-container">
                
                <form action="EditProduct.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data" id="editForm" novalidate>
                    <div class="form-section">
                        
                        
                        <div class="form-group">
                            <label for="name">Product Name </label>
                            <input type="text" id="product_name" name="name" 
                                   value="<?php echo htmlspecialchars(isset($_POST['name']) ? $_POST['name'] : $product['Product_Name']); ?>" 
                                   required placeholder="Enter product name">
                                <small class="error-message" id="productNameError"></small>
                        </div>

                        <div class="form-group">
                            <label for="category_id">Category </label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php 
                                $selectedCategory = isset($_POST['category_id']) ? $_POST['category_id'] : $product['Category_ID'];
                                foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['Category_ID']; ?>" 
                                        <?php echo ($category['Category_ID'] == $selectedCategory) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['Category_Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                        </select>
                        <small class="error-message" id="categoryError"></small>
                        </div>

                    </div>

                    <div class="form-section">
                        
                        <div class="form-group">
                            <label for="description">Product Description</label>
                            <textarea id="description" name="description" rows="5" 
                                      placeholder="Enter product description..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : htmlspecialchars($product['Product_Description']); ?>
                            </textarea>
                            <small class="error-message" id="descriptionError"></small>
                            <small class="password-hint">Please provide complete description for product</small>
                                      
                        </div>
                    </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="eco_friendly" value="1" <?=($product['eco_friendly']==1) ? 'checked':''?>>
                                Eco Friendly Product
                            </label>
                         </div>
                    <div class="form-section">
                        
                        <div class="form-group">
                            <?php if ($product['Product_Picture']): ?>
                                <div class="current-image">
                                    <div><strong>Current Image:</strong> <?php echo htmlspecialchars($product['Product_Picture']); ?></div>
                                    <img src="../uploads/products/<?php echo htmlspecialchars($product['Product_Picture']); ?>" 
                                         alt="Current Product Image">
                                </div>
                            <?php else: ?>
                                <div class="current-image">
                                    <div><strong>No image currently set.</strong></div>
                                </div>
                            <?php endif; ?>
                            
                            <label for="image" style="margin-top: 15px;">Upload New Image:</label>
                            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif, image/webp, image/avif">
                            <small class="error-message" id="imageError"></small>
                            <small>Maximum file size: 2MB. Allowed formats: JPG, PNG, GIF, WEBP, AVIF. Leave empty to keep current image.</small>
                            
                            <div id="imagePreviewContainer" style="margin-top: 15px; display: none;">
                                <div><strong>New Image Preview:</strong></div>
                                <img id="imagePreview" style="max-width: 250px; max-height: 250px; margin-top: 10px; border: 1px solid #ddd; border-radius: 6px; padding: 8px;">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-large">
                             Update Product
                        </button>
                        <a href="manage_product.php" class="btn btn-secondary btn-large">
                             Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>

    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('editForm');
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
        if(image.files.length > 0) {
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

    // image preview when new image selected
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
            submitBtn.innerHTML = 'Updating Product...';
            form.submit();
        }
    });
        // show success message only 5 seconds 
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
    });
        

    </script>
</body>
</html>