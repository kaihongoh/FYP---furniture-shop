<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


//count total active category
$countActive = $conn->prepare("SELECT COUNT(*) FROM category WHERE Status = 'active'");
$countActive->execute();
$countActive->bind_result($totalActiveCategory);
$countActive->fetch();
$countActive->close();

//count total inactive category
$countInactive = $conn->prepare("SELECT COUNT(*) FROM category WHERE Status != 'active'");
$countInactive->execute();
$countInactive->bind_result($totalInactiveCategory);
$countInactive->fetch();
$countInactive->close();

$category_name='';
$errors =[];


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_id=intval($_POST['category_id'] ?? 0);
    $category_name = trim($_POST['category_name'] ?? '');
    
    // check is active or inactive, check if admin wants to deactivate a product
    if (isset($_POST['inactive_category'])) {
        $conn->begin_transaction();
        try{
        // update category status to inactive 
        $updateCategoryInactive = $conn->prepare("UPDATE category SET status = 'inactive' WHERE Category_ID = ?");
        $updateCategoryInactive->bind_param("i", $category_id);
        $updateCategoryInactive->execute();
        $updateCategoryInactive->close();

        // also set all product that under this category to inactive
        $updateProductInactive=$conn->prepare("UPDATE product SET status = 'inactive' WHERE Category_ID = ?");
        $updateProductInactive->bind_param("i", $category_id);
        $updateProductInactive->execute();
        $updateProductInactive->close();

        // also set all variant that under this category to inactive
        $updateVariantInactive=$conn->prepare("UPDATE product_variant 
        JOIN product ON product_variant.Product_ID=product.Product_ID 
        SET product_variant.status = 'inactive' WHERE product.Category_ID = ?");
        $updateVariantInactive->bind_param("i", $category_id);
        $updateVariantInactive->execute();
        $updateVariantInactive->close();

        $conn->commit();
        $_SESSION['message'] = 'deactivated'; //store success message in session
        } catch(Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = 'error';
        }
    } elseif (isset($_POST['active_category'])) {
        $conn->begin_transaction();
        try{
        //update category status to active
        $updateCategoryActive = $conn->prepare("UPDATE category SET status = 'active' WHERE Category_ID = ?");
        $updateCategoryActive->bind_param("i", $category_id);
        $updateCategoryActive->execute();
        $updateCategoryActive->close();

        // also set all product of this category to active
        $updateProductActive=$conn->prepare("UPDATE product SET status = 'active' WHERE Category_ID = ?");
        $updateProductActive->bind_param("i", $category_id);
        $updateProductActive->execute();
        $updateProductActive->close();

        // also set all variant that under this category to active
        $updateVariantActive=$conn->prepare("UPDATE product_variant 
        JOIN product ON product_variant.Product_ID=product.Product_ID 
        SET product_variant.status = 'active' WHERE product.Category_ID = ?");
        $updateVariantActive->bind_param("i", $category_id);
        $updateVariantActive->execute();
        $updateVariantActive->close();

        $conn->commit();
        $_SESSION['message'] = 'activated';
        } catch(Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = 'error';
        }
    }

    //add category
    if (isset($_POST['add_category'])) {
        if(empty($category_name)){
            $errors[]="Category name is required.";
        } else {
            // check the category already exists
            $check_name = $conn->prepare("SELECT Category_ID FROM category WHERE Category_Name = ?");
            $check_name->bind_param("s", $category_name);
            $check_name->execute();
            $check_name->store_result();
        
            if ($check_name->num_rows > 0) {
                $errors[] = "The category already exists. Please create with different category name.";
            }
            $check_name->close();
        }
        if(empty($errors)){
            $add_category=$conn->prepare("INSERT INTO category (Category_Name, Status)
            VALUES (?, 'active')");
            $add_category->bind_param("s", $category_name);
            if($add_category->execute()){
                $_SESSION['success'] = "added successfully";
                header("Location: manage_category.php");
                exit();
            } else {
                $errors[]="Category added failed. Please try again.";
            }
            $add_category->close();
        }
    } 
}
//get data for edit use
$edit_category_name='';  

if(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['category_id'])) {
    $category_id=intval($_GET['category_id']);

    //if click save button 
    if(isset($_POST['update_category'])) {
        $category_name = trim($_POST['category_name']);

        if(empty($category_name)){
            $errors[]="Category name is required.";
        } else {
            // check the category already exists
            $check_name = $conn->prepare("SELECT Category_ID FROM category WHERE Category_Name = ? AND Category_ID!=?");
            $check_name->bind_param("si", $category_name, $category_id);
            $check_name->execute();
            $check_name->store_result();
        
            if ($check_name->num_rows > 0) {
                $errors[] = "The category already exists. Please create with different category name.";
            }
            $check_name->close();
        }
        if(empty($errors)){
            $update_category=$conn->prepare("UPDATE category SET Category_Name=? WHERE Category_ID=?");
            $update_category->bind_param("si", $category_name, $category_id);
            if($update_category->execute()){
                $_SESSION['success'] = 'updated successfully';   
                header("Location: manage_category.php");
                exit();
            } else {
                $errors[] = "Updated failed. Please try again.";
            }
            $update_category->close();
        }
    }

// fetch category data for edit
$get_category = $conn->prepare("SELECT * FROM category WHERE Category_ID = ?");
$get_category->bind_param("i", $category_id);
$get_category->execute();
$category = $get_category->get_result();

        if ($edit_category=$category->fetch_assoc()) {
            $edit_category_name=$edit_category['Category_Name'];
        }
        $get_category->close();
}

$display_category_name='';

if($_SERVER['REQUEST_METHOD']==='POST') {
   $display_category_name=htmlspecialchars(trim($_POST['category_name'] ?? '')); 
} elseif(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['category_id'])) {
    $display_category_name=htmlspecialchars($edit_category_name); 
}

//get all category
$getAll=$conn->prepare("SELECT category.*, COUNT(product.Product_ID) AS Product_Count FROM category
LEFT JOIN product ON category.Category_ID=product.Category_ID 
GROUP BY category.Category_ID
ORDER BY category.Category_ID ASC");
$getAll->execute();
$categories=$getAll->get_result();
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
    <title>Manage Category</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_category.css">
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Category Management</h1>
            <div class="header-right">
                <div class="user-info"></div>
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
            echo "The category status has been activated"; 
        elseif ($message == "deactivated"):  
            echo "The category status has been deactivated"; 
		elseif ($message == "added successfully"):  
             echo "The category has been added successfully!"; 
        elseif ($message == "updated successfully"):  
             echo "The category updated successfully!";
        elseif ($message == "error"): 
             echo "There was an error updating the category status."; 
        endif;
        ?>
        </div>
<?php endif; ?>   
<h2 class="page-title">Category Overview</h2>
           <div class="cards">
        <div class="card"> <!--overview of total royal users-->
            <div class="card-header">
                <span class="card-title">Total Active Category</span>
            </div>
            <div class="card-value"><?php echo $totalActiveCategory; ?></div>
            <div class="card-footer">All active category</div>
        </div>
        <div class="card">
            <div class="card-header"><!--overview of total normal users-->
                <span class="card-title">Total Inactive Category</span>
            </div>
            <div class="card-value"><?php echo $totalInactiveCategory; ?></div>
            <div class="card-footer">All inactive category</div>
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

        <form id="categoryForm" action="manage_category.php<?=(isset($_GET['action']) && $_GET['action'] == 'edit') ? '?action=edit&category_id='.$category_id: '' ?>"
        method="POST" enctype="multipart/form-data" class="category-form" novalidate>
            <div class="form-group">
                <label for="category_name">Category Name </label>
                <input type="text" id="category_name" name="category_name" class="form-control" 
                        value="<?= $display_category_name ?>" 
                        placeholder="Enter the category" required>
                <small class="error-message" id="categoryNameError"></small>
            </div>
            
            <div class="form-group">
                <?php if(isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-large"    
                    name="update_category">Save </button> 
                    <a href="manage_category.php" class="btn btn-secondary btn-large">
                        Cancel  
                    </a>    
                <?php else: ?>
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-large"    
                    name="add_category">Add Category</button>
                <?php endif; ?> 
            </div>
        </form>
    <div class="category-table-container">
    <?php if($categories->num_rows>0): ?>
    <table class="category-table">
        <tr>
            <th>Category</th>
            <th>Linked Products</th>
            <th>Action</th>
            <th>Status</th>
        </tr>
        <?php while ($category=$categories->fetch_assoc()): ?>
            <tr>
                <td><?=$category['Category_Name']?></td>
                <td><?=$category['Product_Count']; ?> products</td>
                <td>
                    <a href="manage_category.php?action=edit&category_id=<?= $category['Category_ID'] ?>" class="btn btn-edit">Edit</a>
                </td>
                <td>
                    <!--active button change-->
                    <?php if (strtolower($category['Status']) === 'active'): ?>
                        <form method="POST" action="manage_category.php">
                            <input type="hidden" name="category_id" value="<?php echo $category['Category_ID']; ?>">
                            <input type="hidden" name="inactive_category" value="inactive">
                            <button type="submit" class="btn btn-delete" 
                                    onclick="return confirm('Are you sure you want to mark this category as inactive? This will hide it from customers.');">
                                    Make Inactive
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="manage_category.php">
                            <input type="hidden" name="category_id" value="<?php echo $category['Category_ID']; ?>">
                            <input type="hidden" name="active_category" value="active"> 
                            <button type="submit" class="btn btn-primary" 
                                onclick="return confirm('Are you sure you want to activate this category? It will be visible to customers.');">
                                Make Active
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
            <?php else: ?>
            <div class="no-category-found">
                <h3>No categories founds</h3>
                <p>Try add a new category.</p>
            </div>
        <?php endif; ?>
    </div>

   <script>
    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('categoryForm');
    const categoryName=document.getElementById('category_name');
    const submitBtn=document.getElementById('submitBtn');
    

    // get all error message element
    const categoryNameError=document.getElementById('categoryNameError');

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

    // category name validation
    function validateCategoryName() {
        if (!categoryName.value.trim()) {
            showError(categoryName, categoryNameError, 'Category is required.');
            return false;
        }
        clearError(categoryName, categoryNameError);
        return true;
    }

    //clear error messages while user is typing or selecting
    categoryName.addEventListener('input', () => clearError(categoryName, categoryNameError));

    //validate input fields when user leaves the field (blur)
    categoryName.addEventListener('blur', validateCategoryName);

    // final validation before submit form
    form.addEventListener('submit', function(e) {
        if(!validateCategoryName()){
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