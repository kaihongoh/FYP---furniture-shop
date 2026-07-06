<?php
require_once(__DIR__ . "/../includes/config.php");
session_start();

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


// Check if ID is provided (when type from browser), if error will redirect back to product list
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_chatbot.php");
    exit();
}

$id = intval($_GET['id']);

// fetch knowledge
$get_knowledge = $conn->prepare("SELECT * FROM chatbot_knowledge WHERE id = ?");
$get_knowledge->bind_param("i", $id);
$get_knowledge->execute();
$knowledge= $get_knowledge->get_result()->fetch_assoc();
$get_knowledge->close();
if (!$knowledge) {
    header("Location: manage_chatbot.php");
    exit();
}

$errors =[];


// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get form data
    $keywords= trim($_POST['keywords']);
    $answer = trim($_POST['answer']);
    $priority = trim($_POST['priority']);

// validation for edit
    if (empty($keywords)) {
        $errors[]="keywords is required";
    } 
    if($answer == ''){
        $errors[]="Please provide the answer for this knowledge";
    } elseif (strlen($answer) < 15) {
        $errors[] = "Answer must be at least 15 characters.";
    }

    if (!is_numeric($priority) || $priority < 0) {
        $errors[]="Please enter a number, the higher the number the higher the priority";
    }

    
    // if no error then update product in database
    if (empty($errors)){
    $update_knowledge = $conn->prepare("UPDATE chatbot_knowledge SET 
        keywords = ?, 
        answer = ?, 
        priority = ?
        WHERE id = ?");
    
    $update_knowledge->bind_param("ssii", 
        $keywords, 
        $answer, 
        $priority, 
        $id
    );
    
    if ($update_knowledge->execute()) {
        $_SESSION['update_success'] = true;
        $_SESSION['update_message'] = "Knowledge updated successfully!";
        header("Location: EditKnowledge.php?id=" . $id);
        exit();
    } else {
    $errors[] = "Failed to update knowledge: " . $conn->error;
    }
    
    $update_knowledge->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Knowledge</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/EditKnowledge.css">


</head>
<body>
    <?php include_once 'admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Edit Knowledge</h1>
        </div>

        <div class="content-wrapper">
            <div class="action-bar">
                <a href="manage_chatbot.php" class="btn btn-secondary btn-large"> Back to Manage</a>
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
                
                <form action="EditKnowledge.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data" id="editForm" novalidate>
                    <div class="form-section">
                        
                        
                        <div class="form-group">
                            <label for="keywords">Keywords</label>
                            <input type="text" id="keywords" name="keywords" 
                                   value="<?= htmlspecialchars($knowledge['keywords']) ?>" 
                                    placeholder="refund, order," required">
                            <small class="error-message" id="keywordsError"></small>
                            <small class="password-hint">Please use comma to separate keywords</small>
                        </div>

                    
                        <div class="form-group">
                            <label for="answer">Answer</label>
                            <textarea id="answer" name="answer" class="form-control" rows="3"
                            placeholder="Please enter the answer for the keywords" required>
                                <?= htmlspecialchars($knowledge['answer']) ?>
                            </textarea>
                            <small class="error-message" id="answerError"></small>
                        </div>
                    
                        <div class="form-group">
                            <label for="priority">Priority </label>
                            <input type="number" id="priority" name="priority" class="form-control" 
                            value="<?=htmlspecialchars($knowledge['priority']) ?>" required>
                            <small class="error-message" id="priorityError"></small>
                            <small class="form-text">Higher number higher priority.</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" id="submitBtn" class="btn btn-primary btn-large">
                                Update Knowledge
                            </button>
                            <a href="manage_chatbot.php" class="btn btn-secondary btn-large">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
   document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('editForm');
    const keywords=document.getElementById('keywords');
    const answer=document.getElementById('answer');
    const priority=document.getElementById('priority');
    const submitBtn=document.getElementById('submitBtn');
    

    // get all error message element
    const keywordsError=document.getElementById('keywordsError');
    const answerError=document.getElementById('answerError');
    const priorityError=document.getElementById('priorityError');



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

    // keywords validation
    function validateKeywords() {
        if (!keywords.value.trim()) {
            showError(keywords, keywordsError, 'Keywords is required.');
            return false;
        }
        clearError(keywords, keywordsError);
        return true;
    }

    //answer validation
    function validateAnswer() {
        if (!answer.value.trim()) {
            showError(answer, answerError, 'Please provide the answer for this knowledge.');
            return false;
        } else if (answer.value.trim().length < 15) {
            showError(answer, answerError, 'Description must be at least 15 characters.');
            return false;
        }
        clearError(answer, answerError);
        return true;
    }

    //image priority
    function validatePriority() {
        if(priority.value ==='' || isNaN(priority.value) || priority.value < 0) {
            showError(priority, priorityError, 'Please fill up the priority, if higher the number the higher the priority.');
            return false;
        }
        clearError(priority, priorityError);
        return true;
    }
    priority.addEventListener('input', function() {
        let number=this.value.split('.')[0]; //only take infront of .
        number=number.replace(/[^0-9]/g, ''); //delete symbol and text
        if(number===''){
            this.value=0;
            return;
        }
        let value=parseInt(number,10);
        this.value=value;
    });

    //clear error messages while user is typing or selecting
    keywords.addEventListener('input', () => clearError(keywords, keywordsError));
    answer.addEventListener('input', () => clearError(answer, answerError));                    
    priority.addEventListener('input', () => clearError(priority, priorityError)); //input or change

    //validate input fields when user leaves the field (blur)
    keywords.addEventListener('blur', validateKeywords);
    answer.addEventListener('blur', validateAnswer);
    priority.addEventListener('blur', validatePriority);


       // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateKeywords(),
            validateAnswer(),
            validatePriority()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Adding Knowledge...';
            form.submit();
        }
    });
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
        

    </script>
</body>
</html>