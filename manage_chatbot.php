<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");

if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // check is active or inactive, check if admin wants to deactivate 
    if (isset($_POST['inactive_knowledge'])) {
        // update knowledge status to inactive 
        $stmt = $conn->prepare("UPDATE chatbot_knowledge SET status = 'Inactive' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) { //store success message in session
            $_SESSION['message'] = 'deactivated';
        } else { //store error message in session
            $_SESSION['message'] = 'error';
        }
        $stmt->close();
    } elseif (isset($_POST['active_knowledge'])) {
        //update knowledge status to active
        $stmt = $conn->prepare("UPDATE chatbot_knowledge SET status = 'Active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'activated';
        } else {
            $_SESSION['message'] = 'error';
        }
        $stmt->close();
    }

}
    //get all unmatch question
    $get_unmatched=$conn->query("SELECT * FROM chatbot_unmatched ORDER BY occurrence DESC");

    //get all already save knowledge
    $get_knowledge=$conn->query("SELECT * FROM chatbot_knowledge ORDER BY id ASC");

    $tab=$_GET['tab'] ?? 'knowledge'; //default tab is knowledge

    // retrieve and clear session message (if exists)
    $message=null;
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
    }  
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Chatbot Knowledge</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/manage_chatbot.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Chatbot Management</h1>
            <div class="header-right">
                <div class="user-info"></div>
            </div>
        </div>
    <div class="content">
<?php if (!empty($message)): ?>

    <div class="alert alert-<?php echo ($message == 'error') ? 'error' : 'success'; ?>" id="successAlert">
       <?php 
       if ($message == "activated"): 
            echo "The knowledge status has been activated"; 
        elseif ($message == "deactivated"):  
            echo "The knowledge status has been deactivated"; 
        elseif ($message == "error"): 
             echo "There was an error updating the knowledge status."; 
        elseif ($message == "unmatch_deleted"):
            echo "Unmatched question remove successfully.";
        elseif ($message == "added"):
            echo "New chatbot knowledge added successfully.";
        endif;
        ?>
        </div>

<?php endif; ?>
<h2 class="page-title">Knowledge Overview</h2>

    <div class="action-bar">
        <a href="AddKnowledge.php" class="btn btn-primary btn-large"> Add Knowledge</a>
        <a href="?tab=unmatched" class="btn btn-primary btn-large"> View Unmatched Questions</a>
        <a href="?tab=knowledge" class="btn btn-primary btn-large"> View Existing Keyword</a>
    </div>
    <div class="user-table-container">
        <?php if($tab == 'knowledge'): ?>
            <h2 class="page-title">Existing Keyword</h2>
            <?php if ($get_knowledge->num_rows > 0): ?>
    <table class="user-table">
        <tr>
            <th>ID</th>
            <th>Keywords</th>
            <th>Answer</th>
            <th>Priority</th>
            <th>Action</th>
            <th>Status</th>
        </tr>
        <?php while ($knowledge=$get_knowledge->fetch_assoc()): ?>
            <tr>
                <td><?=$knowledge['id']?></td>
                <td><?=htmlspecialchars($knowledge['keywords']) ?></td>
                <td><?=htmlspecialchars(substr($knowledge['answer'],0, 80)) ?>...</td>
                <td><?=$knowledge['priority']?></td>
                <td><a href="EditKnowledge.php?id=<?=$knowledge['id'] ?>"class="btn btn-edit">Edit</a></td>
                <td>                
                    <?php if (strtolower($knowledge['status']) === 'active'): ?>
                    <form method="POST" action="manage_chatbot.php">
                        <input type="hidden" name="id" value="<?php echo $knowledge['id']; ?>">
                        <input type="hidden" name="inactive_knowledge" value="Inactive">
                        <button type="submit" class="btn btn-delete" 
                            onclick="return confirm('Are you sure you want to mark this knowledge as inactive?');">
                            Make Inactive
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="manage_chatbot.php">
                        <input type="hidden" name="id" value="<?php echo $knowledge['id']; ?>">
                        <input type="hidden" name="active_knowledge" value="Active">
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you want to activate this knowledge?');">
                            Make Active
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
        <div class="no-knowledge-found">
            <h3>No knowledge found</h3>
            <p>Click "Add New Knowledge" button to create one.</p>
        </div>
        <?php endif; ?>

    <?php elseif ($tab == 'unmatched'): ?>
        
            <h2 class="page-title">Unmatched Questions</h2>
            <?php if ($get_unmatched->num_rows > 0): ?>
    <table class="user-table">
        <tr>
            <th>ID</th>
            <th>User ID</th>
            <th>Message</th>
            <th>Occurence</th>
            <th>Last Asked</th>
            <th>Action</th>
            <th>Remove</th>
        </tr>
        <?php while ($unmatch=$get_unmatched->fetch_assoc()): ?>
            <tr>
                <td><?=$unmatch['id']?></td>
                <td><?=$unmatch['user_id'] ?? 'Guest' ?></td>
                <td><?=htmlspecialchars($unmatch['message']) ?></td>
                <td><?=$unmatch['occurrence']?></td>
                <td><?=$unmatch['last_asked']?></td>
                <td>
                    <a href="AddKnowledge.php?unmatch_id=<?=$unmatch['id'] ?>"
                    class="btn btn-edit">Add</a>
                </td>
                <td>
                    <a href="delete_unmatched.php?unmatch_id=<?=$unmatch['id'] ?>"
                    class="btn btn-delete"
                    onclick="return confirm('Are you sure you want to remove this unmatch record?')">
                    Remove</a>
                </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
        <div class="no-unmatch-found">
            <h3>No unmatched question found</h3>
            <p></p>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    </div>
    </div>
<script>
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

