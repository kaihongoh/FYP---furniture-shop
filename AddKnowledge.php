<?php
require_once(__DIR__ . "/../includes/config.php");
session_start();

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


$errors =[];
$unmatch_id=isset($_GET['unmatch_id']) ? intval($_GET['unmatch_id']) : 0;
$keywords='';
$answer='';
$priority='';
$unmatch_question='';

    if($unmatch_id>0) { //get unmatch question
        $get_message=$conn->prepare("SELECT message FROM chatbot_unmatched WHERE id=?");
        $get_message->bind_param("i", $unmatch_id);
        $get_message->execute();
        $message=$get_message->get_result();

        if($unmatch_message=$message->fetch_assoc()) {
            $unmatch_question=$unmatch_message['message'];
        }

        $get_message->close();
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get data
    $keywords = trim($_POST['keywords'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $priority = trim($_POST['priority'] ?? '');
    $post_unmatch_id=intval($_POST['unmatch_id'] ?? 0);
    //validatation
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

    if (empty($errors)) {
            $insert_knowledge=$conn->prepare("INSERT INTO chatbot_knowledge
            (keywords, answer, priority, status)
            VALUES(?, ?, ?, 'Active')");
            $insert_knowledge->bind_param("ssi", $keywords, $answer, $priority);
            if($insert_knowledge->execute()){
                $_SESSION['message']="added";
                if($post_unmatch_id >0){
                    $delete_unmatch=$conn->prepare("DELETE FROM chatbot_unmatched WHERE id=?");
                    $delete_unmatch->bind_param("i", $post_unmatch_id);
                    $delete_unmatch->execute();
                    $delete_unmatch->close();
                }
                header("Location: manage_chatbot.php");
                exit();
            } else{
                $errors[] = "Failed to add knowledge to database: " . $conn->error;
            }
            $insert_knowledge->close();
    }

}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Knowledge</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/AddKnowledge.css">
    
</head>
<body>
<?php include_once 'admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Add New Knowledge</h1>
            <div class="header-right">
                <div class="user-info">
                </div>
            </div>
        </div>

        <div class="content">
            <div class="action-bar">
                <a href="manage_chatbot.php" class="btn btn-secondary btn-large" style="text-decoration: none;">
                     Back to Manage
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

            <form id="knowledgeForm" action="AddKnowledge.php" method="POST" enctype="multipart/form-data" class="knowledge-form" novalidate>
            <?php if($unmatch_id >0): ?> 
            <div class=info-box>
                <label for="question">Unmatch Question </label>
                <div class="info-content"><?=htmlspecialchars($unmatch_question)?></div>
                </div>
            <?php endif; ?>
                <input type="hidden" name="unmatch_id" value="<?=$unmatch_id?>">
                <div class="form-group">
                    <label for="keywords">Keywords </label>
                    <input type="text" id="keywords" name="keywords" class="form-control" 
                        value="<?= htmlspecialchars($keywords) ?>" 
                        placeholder="refund, order," required>
                    <small class="error-message" id="keywordsError"></small>
                    <small class="password-hint">Please use comma to separate keywords</small>
                </div>
           
                <div class="form-group">
                    <label for="answer">Answer</label>
                    <textarea id="answer" name="answer" class="form-control" rows="3"
                    placeholder="Please enter the answer for the keywords" required><?= htmlspecialchars($answer) ?>
                    </textarea>
                    <small class="error-message" id="answerError"></small>
                </div>
                    
                <div class="form-group">
                    <label for="priority">Priority </label>
                    <input type="number" id="priority" name="priority" class="form-control" 
                    value="0" required>
                    <small class="error-message" id="priorityError"></small>
                    <small class="form-text">Higher number higher priority.</small>
                    
                </div>
                
                <div class="form-group">
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-large">
                         Save Knowledge
                    </button>
                    <a href="manage_chatbot.php" class="btn btn-secondary btn-large">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    //get main form element, input field
    const form=document.getElementById('knowledgeForm');
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

    //priority
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


    </script>
</body>
</html>
