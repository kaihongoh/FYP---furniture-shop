<?php
require_once(__DIR__ . "/../includes/config.php");
session_start();

if(!isset($_GET['unmatch_id'])) {
    header("Location: manage_chatbot.php");
    exit();
}

$unmatch_id= intval($_GET['unmatch_id']);

$delete_unmatch=$conn->prepare("DELETE FROM chatbot_unmatched WHERE id=?");
$delete_unmatch->bind_param("i", $unmatch_id);
if($delete_unmatch->execute()) {
    $_SESSION['message'] = 'unmatch_deleted';
} else {
    $_SESSION['message'] = 'error';
}
$delete_unmatch->close();

header("Location: manage_chatbot.php");
exit();

?>