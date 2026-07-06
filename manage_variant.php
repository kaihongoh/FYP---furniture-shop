<?php
require_once(__DIR__ . "/../includes/config.php");
session_start();


if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


//check product id
if (!isset($_GET['Product_ID']) || empty($_GET['Product_ID'])) {
    header("Location: admin_login.php");
    exit();
}
$product_id = intval($_GET['Product_ID']);

//get product name
$get_product_name = $conn->prepare("SELECT Product_Name FROM product WHERE Product_ID = ?");
$get_product_name->bind_param("i", $product_id);
$get_product_name->execute();
$result = $get_product_name->get_result();
if ($row= $result->fetch_assoc()) {
    $product_name = $row['Product_Name'];
} else {
    echo "Product not found!";
    exit();
}
$get_product_name->close();

//count total active variant
$countActive = $conn->prepare("SELECT COUNT(*) FROM product_variant WHERE Product_ID=? AND status = 'active'");
$countActive->bind_param("i", $product_id);
$countActive->execute();
$countActive->bind_result($totalActiveVariants);
$countActive->fetch();
$countActive->close();

//count total inactive variant
$countInactive = $conn->prepare("SELECT COUNT(*) FROM product_variant WHERE Product_ID=? AND status != 'active'");
$countInactive->bind_param("i", $product_id);
$countInactive->execute();
$countInactive->bind_result($totalInactiveVariants);
$countInactive->fetch();
$countInactive->close();

//count low stock variant (stock < 10)
$countLowStock = $conn->prepare("SELECT COUNT(*) FROM product_variant WHERE Product_ID=? AND Stock < 10 AND status = 'active'");
$countLowStock->bind_param("i", $product_id);
$countLowStock->execute();
$countLowStock->bind_result($totalLowStockVariants);
$countLowStock->fetch();
$countLowStock->close();

//filter condition
$filter = "";
if (isset($_GET['stock']) && !empty($_GET['stock'])) {
    if ($_GET['stock'] == 'low') {
        $filter .= " AND product_variant.Stock < 10";
    } elseif ($_GET['stock'] == 'out') {
        $filter .= " AND product_variant.Stock = 0";
    } elseif ($_GET['stock'] == 'in') {
        $filter .= " AND product_variant.Stock >= 10";
    }
}

$errors =[];
$color='';
$price='';
$stock='';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['variant_id'])){
        $variant_id=intval($_POST['variant_id']);

        if (isset($_POST['inactive_product_variant'])) {
            // update variant status to inactive 
            $updateVariantInactive = $conn->prepare("UPDATE product_variant SET status = 'inactive' WHERE Variant_ID = ?");
            $updateVariantInactive->bind_param("i", $variant_id);
            if($updateVariantInactive->execute()){
                $_SESSION['message'] = 'deactivated';
            } else{
                $_SESSION['message'] = 'error';
            }
            $updateVariantInactive->close();
            header("Location: manage_variant.php?Product_ID=$product_id");
            exit();
            
        } elseif (isset($_POST['active_product_variant'])){
            //if want to set active, need to check the product is active or not
            $check_product=$conn->prepare("SELECT product.status FROM product
                            JOIN product_variant ON product.Product_ID = product_variant.Product_ID 
                            WHERE product_variant.Variant_ID = ?");
            $check_product->bind_param("i", $variant_id);
            $check_product->execute();
            $product_status=$check_product->get_result()->fetch_assoc()['status'];
            $check_product->close();
            if(strtolower($product_status) !== 'active') {
                $_SESSION['error']="Please make sure the product is active status before activate the variant status.";
                header("Location: manage_variant.php?Product_ID=$product_id");
                exit();
            }
            $updateVariantActive = $conn->prepare("UPDATE product_variant SET status = 'active' WHERE Variant_ID = ?");
            $updateVariantActive->bind_param("i", $variant_id);
            if($updateVariantActive->execute()){
                $_SESSION['message'] = 'activated';
            } else{
                $_SESSION['message'] = 'error';
            }
            $updateVariantActive->close();
            header("Location: manage_variant.php?Product_ID=$product_id");
            exit();
        }
    }

    if(isset($_POST['old_image_name'])) {
        $old_image_name=trim($_POST['old_image_name']);
    }
    $color = trim($_POST['color']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);

    if(isset($_POST['add_variant'])) { //add variant
        if(empty($color)) {
            $errors[] ="Color is required.";
        } else {
        // check the variant already exists
        $check_name = $conn->prepare("SELECT Product_ID FROM product_variant WHERE Color = ? AND Product_ID=?");
        $check_name->bind_param("si", $color, $product_id);
        $check_name->execute();
        $check_name->store_result();
        
        if ($check_name->num_rows > 0) {
            $errors[] = "The color already exists. Please create the product with different color.";
        }
        $check_name->close();
    }
    if($price <= 0) {
        $errors[]="Please provide a valid price.";
    }

    if($stock < 0) {
        $errors[]="Stock quantity cannot be negative";
    }
    //upload image
    $new_image_name = ""; //default blank

    if (isset($_FILES['variant_image']) && $_FILES['variant_image']['error'] === 0) {

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
        $file_type = $_FILES['variant_image']['type'];
        $file_size = $_FILES['variant_image']['size'];

        if (!in_array($file_type, $allowed_types)) {//only accept
            $errors[] = "Only JPG, PNG, GIF and AVIF files are allowed";
        } elseif ($file_size > 2 * 1024 * 1024) {//limit 2mb
            $errors[] = "Image size must be less than 2MB";
        } else {
            $file_extension = pathinfo($_FILES['variant_image']['name'], PATHINFO_EXTENSION);
            $new_image_name = uniqid() . '.' . $file_extension;
            $upload_path = '../uploads/variants/' . $new_image_name;

            if (!file_exists('../uploads/variants')) { //create file if dont uploads/variants/ dont exist 
                mkdir('../uploads/variants', 0777, true);
            }

            if (!move_uploaded_file($_FILES['variant_image']['tmp_name'], $upload_path)) {
                $errors[] = "Failed to upload image";
                $new_image_name = "";
            }
        }
    } else {
        $errors[] = "Variant image is required";
    }
        if(empty($errors)){
            $add_variant=$conn->prepare("INSERT INTO product_variant (Product_ID, Color, Variant_Image, Price, Stock, Status)
            VALUES (?, ?, ?, ?, ?, 'active')");
            $add_variant->bind_param("issdi", $product_id, $color, $new_image_name, $price, $stock);
            if($add_variant->execute()) {
                $_SESSION['success'] = "added successfully";
                header("Location: manage_variant.php?Product_ID=$product_id");
                exit();
            } else {
                $errors[]="Product variant added failed. Please try again.";
            }  
            $add_variant->close();
        } 
    }
}

//get data for edit use
$edit_color='';
$edit_price='';
$edit_stock='';
$old_image_name='';

    if(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['variant_id'])) {
        $variant_id=intval($_GET['variant_id']);

        //if click save button 
        if(isset($_POST['update_variant'])) {
            $color = trim($_POST['color']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock']);
            $old_image_name=trim($_POST['old_image_name']);
        
        // validation for edit
        if (empty($color)) {
            $errors[] = "Color is required";
        } else {
            // check if color already exists (exclude current color)
            $check_name = $conn->prepare("SELECT Variant_ID FROM product_variant WHERE Color = ? AND Product_ID=? AND Variant_ID != ?");
            $check_name->bind_param("sii", $color, $product_id, $variant_id);
            $check_name->execute();
            $check_name->store_result();
            
            if ($check_name->num_rows > 0) {
                $errors[] = "This color already exists. Please create with different color.";
            }
            $check_name->close();
        }
    
        if ($price <= 0) {
            $errors[] = "Please provide a valid price";
        }

        if ($stock < 0) {
            $errors[] = "Stock quantity cannot be negative";
        }
    

        //if dont have file uploads/variants, then create uploads/variants
        $upload_dir = '../uploads/variants';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

    // handle image upload
    $new_image_name = $old_image_name; // default keep existing by default
    
    if (isset($_FILES['variant_image']) && $_FILES['variant_image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
        $file_type = $_FILES['variant_image']['type'];
        $file_size = $_FILES['variant_image']['size'];

        //validate the size and type of the file
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF, WEBP and AVIF files are allowed";
        } else if ($file_size > 2 * 1024 * 1024) {
            $errors[] = "Image size must be less than 2MB";
        } else { //no error then can upload
            $file_extension = pathinfo($_FILES['variant_image']['name'], PATHINFO_EXTENSION);
            $new_image_name = uniqid() . '.' . $file_extension;
            $upload_path = '../uploads/variants/' . $new_image_name;

            // upload the file (directory must already exist)
            if (move_uploaded_file($_FILES['variant_image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($old_image_name)) {
                    $old_image = '../uploads/variants/' . $old_image_name;
                    if (file_exists($old_image)) {
                        unlink($old_image);
                    }
                }
            } else {
                $errors[] = "Failed to upload image";
                $new_image_name = $old_image_name; //upload fail continue use old image
            }
        } 
    }
        if(empty($errors)) {
            $update_variant=$conn->prepare("UPDATE product_variant SET Color=?, Price=?, Stock=?, Variant_Image=? WHERE Variant_ID=?");
            $update_variant->bind_param("sdisi", $color, $price, $stock, $new_image_name, $variant_id);
 
            if ($update_variant->execute()) {
                $_SESSION['success'] = 'updated successfully';   
                header("Location: manage_variant.php?Product_ID=$product_id");
                exit();
        } else {
            $errors[] = "Updated failed. Please try again.  ";
        }
        $update_variant->close();
    }                 
    
} 
        // fetch variant data for edit
        $get_variant = $conn->prepare("SELECT * FROM product_variant WHERE Variant_ID = ?");
        $get_variant->bind_param("i", $variant_id);
        $get_variant->execute();
        $variant = $get_variant->get_result();

        if ($edit_variant=$variant->fetch_assoc()) {
            $edit_color=$edit_variant['Color'];
            $edit_price=$edit_variant['Price'];
            $edit_stock=$edit_variant['Stock'];
            $old_image_name=$edit_variant['Variant_Image'];
        }
        $get_variant->close();
    
}

$display_color='';
$display_price='';
$display_stock='';

if($_SERVER['REQUEST_METHOD']==='POST') {
   $display_color=htmlspecialchars(trim($_POST['color'] ?? '')); 
   $display_price=htmlspecialchars(trim($_POST['price'] ?? ''));
   $display_stock=htmlspecialchars(trim($_POST['stock'] ?? ''));
} elseif(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['variant_id'])) {
    $display_color=htmlspecialchars($edit_color); 
    $display_price=htmlspecialchars($edit_price); 
    $display_stock=htmlspecialchars($edit_stock); 
}

//get all variant for that product
$getAll=$conn->prepare("SELECT * FROM product_variant WHERE Product_ID=? ORDER BY Variant_ID ASC");
$getAll->bind_param("i", $product_id);
$getAll->execute();
$variants=$getAll->get_result();
$getAll->close();

// retrieve and clear session message (if exists)
$message=null;
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Variants - <?php echo htmlspecialchars($product_name); ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_variant.css">
</head>
<body>    
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Variants Management for <?php echo htmlspecialchars($product_name)?></h1>
            <div class="header-right">
                <div class="user-info"></div>
            </div>
        </div>
            <div class="action-bar">
                <a href="manage_product.php" class="btn btn-secondary btn-large" style="text-decoration: none;">
                     Back to Products
                </a>
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
            echo "The variant status has been activated"; 
        elseif ($message == "deactivated"):  
            echo "The variant status has been deactivated"; 
		elseif ($message == "added successfully"):  
             echo "The product variant has been added successfully!"; 
        elseif ($message == "updated successfully"):  
             echo "The product variant updated successfully!";
        elseif ($message == "error"): 
             echo "There was an error updating the product status."; 
        endif;
        ?>
        </div>
<?php endif; ?>
<h2 class="page-title">Variants Overview</h2>
           <div class="cards">
        <div class="card"> <!--overview of total royal users-->
            <div class="card-header">
                <span class="card-title">Total Active Variants</span>
            </div>
            <div class="card-value"><?php echo $totalActiveVariants; ?></div>
            <div class="card-footer">All active variants</div>
        </div>
        <div class="card">
            <div class="card-header"><!--overview of total normal users-->
                <span class="card-title">Total Inactive Variants</span>
            </div>
            <div class="card-value"><?php echo $totalInactiveVariants; ?></div>
            <div class="card-footer">All inactive variants</div>
        </div>
        <div class="card">
            <div class="card-header"><!--overview of total normal users-->
                <span class="card-title">Low Stock</span>
            </div>
            <div class="card-value"><?php echo $totalLowStockVariants; ?></div>
            <div class="card-footer">Products with stock < 10</div>
        </div>
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

            <form id="variantForm" action="manage_variant.php?Product_ID=<?=$product_id ?><?=(isset($_GET['action']) && $_GET['action'] == 'edit') ? '&action=edit&variant_id='.$variant_id: '' ?>" 
            method="POST" enctype="multipart/form-data" class="variant-form" novalidate>
            <!--when submit will turn old image to backend-->
            <input type="hidden" name="old_image_name" value="<?=htmlspecialchars($old_image_name) ?>">
                <div class="form-group">
                    <label for="color">Color </label>
                    <input type="text" id="color" name="color" class="form-control" 
                           value="<?= $display_color ?>" 
                           placeholder="Enter the color" required>
                    <small class="error-message" id="variantNameError"></small>
                </div>
                <div class="form-group">
                    <label for="price">Price (RM) </label>
                    <input type="number" id="price" name="price" class="form-control" step="0.01" min="0.01" 
                           value="<?= $display_price ?>" 
                           placeholder="Enter the price" required>
                    <small class="error-message" id="priceError"></small>
                </div>
                
                <div class="form-group">
                    <label for="stock">Stock Quantity </label>
                    <input type="number" id="stock" name="stock" class="form-control" min="0" 
                           value="<?= $display_stock ?>" 
                           placeholder="Enter the stock quantity" required>
                    <small class="error-message" id="stockError"></small>
                </div>
                    
                <div class="form-group">
                    <label for="variant_image">Variant Image <?=(isset($_GET['action']) && $_GET['action'] == 'edit') ? ' (Current: ' . htmlspecialchars($old_image_name) . ')' : '' ?></label>
                    <input type="file" id="variant_image" name="variant_image" class="form-control" 
                           accept="image/jpeg,image/png,image/gif,image/webp,image/avif">
                    <small class="error-message" id="variantImageError"></small>
                    <small class="form-text">Maximum file size: 2MB. Allowed formats: JPG, PNG, GIF, WEBP, AVIF. Leave empty to keep current image.</small>
                    

                    <div id="imagePreviewContainer" style="margin-top: 15px; <?=(!empty($old_image_name)) ? '' : 'display: none;'?>">
                        <div><strong>Preview:</strong></div>
                        <img id="imagePreview" src="<?=!empty($old_image_name) ? '../uploads/variants/' . $old_image_name: '' ?>" style="max-width: 250px; max-height: 250px; margin-top: 10px; border: 1px solid #ddd; border-radius: 6px; padding: 8px;">
                    </div>
                </div>
        
                <div class="form-group">
                <?php if(isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-large"    
                    name="update_variant">Save </button> 
                    <a href="manage_variant.php?Product_ID=<?=$product_id?>" class="btn btn-secondary btn-large">
                        Cancel  
                    </a>    
                <?php else: ?>
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-large"    
                    name="add_variant">Add Variant</button>
                <?php endif; ?> 
                </div>
            </form>       
    <div class="variant-table-container">
    <?php if($variants->num_rows>0): ?>
    <table class="variant-table">
        <tr>
            <th>Variant Image</th>
            <th>Color</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Action</th>
            <th>Status</th>
        </tr>
        <?php while ($variant=$variants->fetch_assoc()): ?>
            <tr>
                <td><img src="../uploads/variants/<?=htmlspecialchars($variant['Variant_Image'])?>"
                        onerror="this.src='../uploads/placeholder.jpg'"> </td>
                <td><h4 title="<?= htmlspecialchars($variant['Color']) ?>">
                   <?=htmlspecialchars($variant['Color']) ?> 
                    </h4>
                </td>
                <td><span class="price">RM <?= number_format($variant['Price'],2) ?></span> </td>
                <td><?= $variant['Stock'] ?> </td>
                <td>
                    <a href="manage_variant.php?Product_ID=<?=$product_id?>&action=edit&variant_id=<?= $variant['Variant_ID'] ?>" class="btn btn-edit">Edit</a>
                </td>
                <td>
                    <!--active button change-->
                    <?php if (strtolower($variant['Status']) === 'active'): ?>
                        <form method="POST" action="manage_variant.php?Product_ID=<?=$product_id?>">
                            <input type="hidden" name="variant_id" value="<?php echo $variant['Variant_ID']; ?>">
                            <input type="hidden" name="inactive_product_variant" value="inactive">
                            <button type="submit" class="btn btn-delete" 
                                    onclick="return confirm('Are you sure you want to mark this variant as inactive? This will hide it from customers.');">
                                    Make Inactive
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="manage_variant.php?Product_ID=<?=$product_id?>">
                            <input type="hidden" name="variant_id" value="<?php echo $variant['Variant_ID']; ?>">
                            <input type="hidden" name="active_product_variant" value="active"> 
                            <button type="submit" class="btn btn-primary" 
                                onclick="return confirm('Are you sure you want to activate this variant? It will be visible to customers.');">
                                Make Active
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
        <?php else: ?>
            <div class="no-variant-found">
                <h3>No variants founds</h3>
                <p>Try add a new variant.</p>
            </div>
        <?php endif; ?>
</div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('variantForm');
    const variantName=document.getElementById('color');
    const price=document.getElementById('price');
    const stock=document.getElementById('stock');
    const variant_image=document.getElementById('variant_image');
    const submitBtn=document.getElementById('submitBtn');
    

    // get all error message element
    const variantNameError=document.getElementById('variantNameError');
    const priceError=document.getElementById('priceError');
    const stockError=document.getElementById('stockError');
    const variantImageError=document.getElementById('variantImageError');
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

    // variant name validation
    function validateVariantName() {
        if (!variantName.value.trim()) {
            showError(variantName, variantNameError, 'Variant name is required.');
            return false;
        }
        clearError(variantName, variantNameError);
        return true;
    }

    //price validation
    function validatePrice() {
        if (!price.value) {
            showError(price, priceError, 'Price is required.');
            return false;
        }
        if(parseFloat(price.value)<=0) {
            showError(price, priceError, 'Price must grather than 0.');
            return false;
        }
        
        clearError(price, priceError);
        return true;
    }    

    //stock validation
    function validateStock() {
        if (!stock.value) {
            showError(stock, stockError, 'Stock quantity is required.');
            return false;
        }
        if(parseInt(stock.value)<0) {
            showError(stock, stockError, 'Stock quantity cannot be negative.');
            return false;
        }
        clearError(stock, stockError);
        return true;
    }
    stock.addEventListener('input', function() {
        let number=this.value.split('.')[0]; //only take infront of .
        number=number.replace(/[^0-9]/g, ''); //delete symbol and text
        if(number===''){
            this.value=0;
            return;
        }
        let value=parseInt(number,10);
        this.value=value;
    });

    //image validation
    function validateImage() {
        const isEdit=window.location.search.includes('action=edit');
        if(!isEdit && variant_image.files.length === 0) {
            showError(variant_image, variantImageError, 'Product variant image is required.');
            return false;
        }
        if(variant_image.files.length > 0) {
            const file=variant_image.files[0];
            const allowType=['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
            if (!allowType.includes(file.type)) {
                showError(variant_image, variantImageError, 'Only JPG, PNG, GIF, WEBP and AVIF files are allowed');
                return false;
            } else if (file.size > 2 * 1024 * 1024) {
                showError(variant_image, variantImageError, 'Image size must be less than 2MB');
                return false;
            }
        }
        clearError(variant_image, variantImageError);
        return true;
    }
    //clear error messages while user is typing or selecting
    variantName.addEventListener('input', () => clearError(variantName, variantNameError));
    price.addEventListener('input', () => clearError(price, priceError));
    stock.addEventListener('input', () => clearError(stock, stockError));
    variant_image.addEventListener('input', () => clearError(variant_image, variantImageError));

    //validate input fields when user leaves the field (blur)
    variantName.addEventListener('blur', validateVariantName);
    price.addEventListener('blur', validatePrice);
    stock.addEventListener('blur', validateStock);
    variant_image.addEventListener('blur', validateImage);

    // preview image
    variant_image.addEventListener('change', function(e) {
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
        
        //validate all field and show red color
        const validations = [
            validateVariantName(),
            validatePrice(),
            validateStock(),
            validateImage()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (!isValid) {
            e.preventDefault();
            return;
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
