<?php
//get level that match user current total spent 
function getLevel($conn, $total_spent){
    if($total_spent === null) {
        return null; //no spend, no level
    }

    $getLevel="SELECT id, min_spent FROM level_tag
    WHERE status='Active' ORDER BY min_spent ASC";
    $level=$conn->query($getLevel);
    $level_id=null;

    while($row=$level->fetch_assoc()){
        if($total_spent >= $row['min_spent']){
            $level_id=$row['id'];
        }else{
            break;  //if low than current level
        }
    }
    return $level_id;

}

//update user total spent and reset level
function updateSpend($conn, $user_id, $change_amount){
    //update
    $update=$conn->prepare("UPDATE users SET total_spent=total_spent + ? WHERE User_ID=?");
    $update->bind_param("di", $change_amount, $user_id);
    $update->execute();
    $update->close();

    //get new total spent
    $get=$conn->prepare("SELECT total_spent FROM users WHERE User_ID=?");
    $get->bind_param("i", $user_id);
    $get->execute();
    $total_spent=$get->get_result()->fetch_assoc()['total_spent'];
    $get->close();

    //reset level
    $new_level_id=getLevel($conn, $total_spent);
    if($new_level_id){
        $update_level=$conn->prepare("UPDATE users SET level_id=? WHERE User_ID=?");
        $update_level->bind_param("ii", $new_level_id, $user_id);
        $update_level->execute();
        $update_level->close();
    }
}

function recalculateAllUserLevels($conn) {
    $update=$conn->query("UPDATE users SET users.level_id=(SELECT level_tag.id FROM level_tag 
    WHERE level_tag.status='Active' AND level_tag.min_spent<=users.total_spent
    ORDER BY level_tag.min_spent DESC, level_tag.id ASC LIMIT 1)");

}

?>