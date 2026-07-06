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
$address_line1= '';
$address_line2= '';
$city='';
$unit_no= '';
$remarks= '';
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
    $address_line1= trim($_POST['address_line1'] ?? '');
    $address_line2= trim($_POST['address_line2'] ?? '');
    $city=trim($_POST['city'] ?? '');
    $unit_no= trim($_POST['unit_no'] ?? '');
    $remarks= trim($_POST['remarks'] ?? '');
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
    
    if (empty($address_line1)) 
        $errors[] = 'Please enter the address line 1 field.';
    elseif (strlen($address_line1) < 5) 
        $errors[] = 'Address must be at least 5 characters.';

    if(empty($city)){
        $errors[] = 'Please select the city.';
    } 

    if(empty($errors)) {    
        if($is_default) { //if set as default, clear other address default
            $reset= $conn->prepare("UPDATE user_address SET Is_Default=0 WHERE User_ID=?");
            $reset->bind_param('i', $user_id);
            $reset->execute();
        }//insert new address
        $stmt = $conn->prepare("INSERT INTO user_address 
        (User_ID, Full_Name, Phone, State, 
        postcode, address_line1, address_line2, city, Unit_No, Remarks, 
        Label, Is_Default, Created_At)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, Now())");
        $stmt->bind_param("issssssssssi", $user_id, $full_name, $phone, $state, $postcode, $address_line1, $address_line2, $city, $unit_no, $remarks, $label, $is_default);

        if( $stmt->execute()) {
            $_SESSION['success']="Address added successfully!";
            header("Location: address_list.php");
            exit();
        } else {
            $errors[]="Failed to add address. Please try again.";
        }
    }

}
$get_city=$conn->query("SELECT state_name, city_name FROM cities ORDER BY state_name, city_name");
$city_by_state=[];
while($city_result=$get_city->fetch_assoc()){
    $state_data=$city_result['state_name'];
    $city_data=$city_result['city_name'];
    if(!isset($city_by_state[$state_data])){
        $city_by_state[$state_data]=[];
    }
    $city_by_state[$state_data][]=$city_data;
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
            <div class="error-message" id="errorAlert">
                <?=implode('<br>', array_map('htmlspecialchars',$errors)) ?>
            </div>
        <?php endif; ?>

        
        <form method="POST" action="" class="register-form" id="addressForm" novalidate>
            <div class="form-group">
                <label class="form-label">Full Name </label>
                <input type="text" id="full_name" name="full_name" class="form-control" 
                    value="<?= htmlspecialchars($full_name) ?>" 
                    placeholder="Enter your full name" required>
                <small class="error-message" id="fullNameError"></small>
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number </label>
                <input type="tel" id="phoneNumber" name="phoneNumber" class="form-control"
                        value="<?= htmlspecialchars($phone) ?>" 
                        pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" 
                        placeholder="123-456-7890" required>
                <small class="password-hint">Format: 012-345-6789</small>
                <small class="error-message" id="phoneError"></small>
            </div>

            <div class="form-group">    
                <label class="form-label">Address Line 1</label>
                <input type="text" name="address_line1" id="address" class="form-control"
                    value="<?= htmlspecialchars($address_line1) ?>"
                    placeholder="12, Jalan Bukit Beruang 5" required>
                <small class="password-hint">Do not include the state and postcode</small>
                <small class="error-message" id="addressError"></small>
            </div>
            <div class="form-group">    
                <label class="form-label">Address Line 2 (Optional)</label>
                <input type="text" name="address_line2" class="form-control"
                    value="<?= htmlspecialchars($address_line2) ?>"
                    placeholder="Taman Bukit Melaka">
            </div>

  <?php 
    $get_states=$conn->prepare("SELECT state_name FROM shipping_fee_setting WHERE status='Active'
    ORDER BY state_name ASC");
    $get_states->execute();
    $states=$get_states->get_result();
    ?>

    <div class="form-group">
        <label class="form-label">State </label>
        <select name="state" id ="stateSelect" class="form-control" required>
            <option value="">Select your state</option>
            <?php while($STATE=$states->fetch_assoc()): ?>
                <option value="<?=htmlspecialchars($STATE['state_name'])?>"
                <?=($state==$STATE['state_name']) ?'selected':''?>> <!--drop down-->
                <?=htmlspecialchars($STATE['state_name'])?> 
                </option>
            <?php endwhile; ?>
        </select>
        <small class="error-message" id="stateError"></small>
    </div>
    
    <div class="form-group">
        <label class="form-label">City </label>
        <select name="city" id ="citySelect" class="form-control" required>
            <option value="">Select your city</option>
        </select> <!--load city will auto show the city for that state-->
        <small class="error-message" id="cityError"></small>
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
                <label class="form-label">Unit No (optional)</label>
                <input type="text" name="unit_no" id="unit_no" class="form-control"
                value="<?=htmlspecialchars($unit_no) ?>"
                placeholder="A-12-1, Block B"/>
            </div>

            <div class="form-group">
                <label class="form-label">Remark (optional)</label>
                <input type="text" name="remarks" id="remarks" class="form-control"
                value="<?=htmlspecialchars($remarks) ?>"
                placeholder="put on the gate"/>
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

            <button type="submit" id="submitBtn" class="btn-register">Add address</button>
            <a href="address_list.php" class="cancel-btn">Cancel</a>
            </form>
        </div>
    </section>

    <?php include_once 'includes/footer.php'; ?>
    
    <script>
    const cityByState=<?php echo json_encode($city_by_state); ?>;
    const selectedCity=<?php echo json_encode($city); ?>;
    </script>

    <script src="js/address.js"></script>    
    <script src="js/alert.js"></script>    
</body>
</html> 


