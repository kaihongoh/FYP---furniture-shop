<?php
session_start();
require_once 'includes/config.php';

if(!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();    
}

$user_id=$_SESSION['user_id'];

$full_name= '';
$phone= '';
$state = '';
$postcode = '';
$address= '';
$unit_no= '';
$delivery_notes= '';
$label= 'Home';
$is_default= 0;


$errors= [];
$success='';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // get form data
    $full_name= trim($_POST['full_name'] ?? '');
    $phone= trim($_POST['phoneNumber'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $address= trim($_POST['address'] ?? '');
    $unit_no= trim($_POST['unit_no'] ?? '');
    $delivery_notes= trim($_POST['delivery_notes'] ?? '');
    $label=$_POST['label'] ?? 'Home';
    $is_default= isset($_POST['is_default']) ?1:0;
    
    if (empty($full_name)) 
        $errors[] = 'Full name is required.';

    if (empty($phone))  
            $errors[] = 'Please enter the phone number.';
        elseif (!preg_match('/^\d{3}-\d{3}-\d{4}$/', $phone)) 
        $errors[] = 'Phone number must be in format: 012-345-6789';
        

    if (empty($state)) 
        $errors[] = 'Please select the state.';

    if (empty($postcode)) 
        $errors[] = 'Please enter the postcode.';
    elseif (!preg_match('/^\d{5}$/', $postcode)) 
        $errors[] = 'Postcode must be 5 digits.';
    
    if (empty($address)) 
        $errors[] = 'Please enter the complete address.';
    elseif (strlen($address) < 10) 
        $errors[] = 'Address must be at least 10 characters.';

    if(empty($errors)) {    
        if($is_default) { //if set as default, clear other address default
            $reset= $conn->prepare("UPDATE user_address SET Is_Default=0 WHERE User_ID=?");
            $reset->bind_param('i', $user_id);
            $reset->execute();
        }//insert new address
        $stmt = $conn->prepare("INSERT INTO user_address 
        (User_ID, Full_Name, Phone, State, 
        postcode, Address, Unit_No, Delivery_Notes, 
        Label, Is_Default, Created_At)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, Now())");
        $stmt->bind_param("issssssssi", $user_id, $full_name, $phone, $state, $postcode, $address, $unit_no, $delivery_notes, $label, $is_default);

        if( $stmt->execute()) {
            $_SESSION['success']="Address added successfully!";
            header("Location: address_list.php");
            exit();
        } else {
            $errors[]="Failed to add address. Please try again.";
        }
    }

}
?>
  
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Address</title>
    <link rel="stylesheet" href="css/template.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <section class="register-page">
        <div class="register-container">
            <div class="form-header">
                <h2 class="register-title">Add New Address</h2>
                <p class="form-subtitle">Enter your shipping details</p>
            </div>

        <?php if(!empty($errors)): ?>   
            <div class="error-message" style="color:red; border:1px solid red; padding:10px; margin-bottom:20px;">
                <?=implode('<br>', array_map('htmlspecialchars',$errors)) ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
                <div class="success-message" style="color:green; border:1px solid green; padding:10px; margin-bottom:20px;">
                <?=htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="register-form" id="addressForm" novalidate>
            <div class="form-group">
                <label class="form-label">Full Name </label>
                <input type="text" name="full_name" class="form-control" 
                    value="<?= htmlspecialchars($full_name) ?>" 
                    placeholder="Enter your full name" required>
                <small class="error-message" id="fullNameError"></small>
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number </label>
                <input type="tel" name="phoneNumber" class="form-control"
                        value="<?= htmlspecialchars($phone) ?>" 
                        pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" 
                        placeholder="123-456-7890" required>
                <small class="password-hint">Format: 012-345-6789</small>
                <small class="error-message" id="phoneError"></small>
            </div>

            <div class="form-group">
                <label class="form-label">State </label>
                <select name="state" id ="stateSelect" class="form-control" required>
                    <option value="">Select your state</option>
                    <option value="Johor"<?=$state=='Johor'?'selected':''?>>Johor</option>
                    <option value="Kedah"<?=$state=='Kedah'?'selected':''?>>Kedah</option>
                    <option value="Kelantan"<?=$state=='Kelantan'?'selected':''?>>Kelantan</option>
                    <option value="Malacca"<?=$state=='Malacca'?'selected':''?>>Malacca</option>
                    <option value="Negeri Sembilan"<?=$state=='Negeri Sembilan'?'selected':''?>>Negeri Sembilan</option>
                    <option value="Pahang"<?=$state=='Pahang'?'selected':''?>>Pahang</option>
                    <option value="Perak"<?=$state=='Perak'?'selected':''?>>Perak</option>
                    <option value="Perlis"<?=$state=='Perlis'?'selected':''?>>Perlis</option>
                    <option value="Penang"<?=$state=='Penang'?'selected':''?>>Penang</option>
                    <option value="Sabah"<?=$state=='Sabah'?'selected':''?>>Sabah</option>
                    <option value="Sarawak"<?=$state=='Sarawak'?'selected':''?>>Sarawak</option>
                    <option value="Selangor"<?=$state=='Selangor'?'selected':''?>>Selangor</option>
                    <option value="Terengganu"<?=$state=='Terengganu'?'selected':''?>>Terengganu</option>
                    <option value="Wilayah Persekutuan Kuala Lumpur"<?=$state=='Wilayah Persekutuan Kuala Lumpur'?'selected':''?>>Wilayah Persekutuan Kuala Lumpur</option>
                    <option value="Wilayah Persekutuan Putrajaya"<?=$state=='Wilayah Persekutuan Putrajaya'?'selected':''?>>Wilayah Persekutuan Putrajaya</option>
                    <option value="Wilayah Persekutuan Labuan"<?=$state=='Wilayah Persekutuan Labuan'?'selected':''?>>Wilayah Persekutuan Labuan</option>
                </select>
                <small class="error-message" id="stateError"></small>
            </div>

            <div class="form-group">
                <label class="form-label">Postcode</label>
                <input type="text" name="postcode" id="postcode" maxlength="5" inputmode="numeric" class="form-control"
                    value="<?=htmlspecialchars($postcode) ?>"
                    pattern="[0-9]{5}"      
                    placeholder="75000" required>
                <small class="error-message" id="postcodeError"></small>
            </div>
                        
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="3" 
                    placeholder="Enter your full address" required><?= htmlspecialchars($address) ?>
                </textarea>
                <small class="password-hint">Please provide complete address for delivery</small>
                <small class="error-message" id="addressError"></small>
            </div>

            <div class="form-group">
                <label class="form-label">Unit No (optional)</label>
                <input type="text" name="unit_no" id="unit_no" class="form-control"
                value="<?=htmlspecialchars($unit_no) ?>"
                placeholder="example: A-12-1, Block B"/>
            </div>

            <div class="form-group">
                <label class="form-label">Comment (optional)</label>
                <input type="text" name="delivery_notes" id="delivery_notes" class="form-control"
                value="<?=htmlspecialchars($delivery_notes) ?>"
                placeholder="example: put on the gate"/>
            </div>

            <div class="form-group">
                <label class="form-label">Label</label>
                <div>
                    <input type="radio" id="home" name="label" value="Home" <?=$label=='Home'?'checked':''?>>Home
                    <input type="radio" id="work" name="label" value="Work" <?=$label=='Work'?'checked':''?>>Work
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="is_default" name="is_default" value="1" 
                    <?=$is_default ? 'checked':''?>>Set as default address
                </label>
            </div>

            <button type="submit" class="btn-register">Add address</button>
            <a href="address_list.php" class="btn-secondary" 
            style="display:inline-block; margin-top:10px;">Cancel</a>
            </form>
        </div>
    </section>

    <?php include_once 'includes/footer.php'; ?>
    
    <script src="js/address.js"></script>        
</body>
</html> 


