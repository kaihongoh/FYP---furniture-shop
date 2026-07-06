<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/rating.php';

$category_id=isset ($_GET['category'])? (int)$_GET['category'] : 0;
$search=isset($_GET['search'])? trim($_GET['search']) : '';
$eco_friendly=isset ($_GET['eco_friendly'])? $_GET['eco_friendly']:'';

// get all active products, when the variant is all inactive then will not show in the product list
$sql = "SELECT product.Product_ID, product.Product_Name, 
product.Product_Picture, product.Product_Description, 
category.Category_ID, category.Category_Name, product.eco_friendly, 
MIN(product_variant.Price) as min_Price 
FROM product  
JOIN category ON product.Category_ID = category.Category_ID 
JOIN product_variant ON product.Product_ID = product_variant.Product_ID AND product_variant.Status='Active' 
WHERE product.status = 'Active'";

$params = [];
$types="";
//use for search function, after search display it
if(!empty($search)){
    $search_param="%" . $search . "%";
    $sql .=" AND product.Product_Name LIKE ?";
    $params[]=$search_param;
    $types .="s";
}

//check category active
if($category_id> 0){
    $category_check=$conn->prepare("SELECT Category_ID FROM category WHERE Category_ID=? AND Status='Active'");
    $category_check->bind_param("i",$category_id);
    $category_check->execute();
    $category_check->store_result();
    if($category_check->num_rows > 0){
        $sql .=" AND product.Category_ID = ?";   //filter that category
        $params[]=$category_id;
        $types .='i';
    } else {
        $category_id=0;
    }
    $category_check->close();
  
}

//filter eco friendly
if($eco_friendly !== ''){
    $sql .=" AND product.eco_friendly = ?";
    $params[]=$eco_friendly;
    $types .='i';
}

$sql .=" GROUP BY product.Product_ID";

if(isset($_GET['sort']) && $_GET['sort'] == 'ascending_price'){
    $sql .=" ORDER BY min_Price ASC";
} elseif(isset($_GET['sort']) && $_GET['sort'] == 'descending_price'){
    $sql .=" ORDER BY min_Price DESC";
} else {
    $sql .=" ORDER BY product.Product_ID";
}

$stmt= $conn->prepare($sql);
if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products= $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


//get all active category 
$active_category= $conn->prepare("SELECT Category_ID, Category_Name 
FROM category WHERE Status='Active'
ORDER BY Category_Name ASC");
$active_category->execute();
$categories=$active_category->get_result()->fetch_all(MYSQLI_ASSOC);
$active_category->close();



?>

<!DOCTYPE html>
<html>
<head>
    <title>Product - HomeNest</title>
    <link rel="stylesheet" href="css/template.css"> 
    <link rel="stylesheet" href="css/product.css">

</head>
<body>

<?php include_once 'includes/header.php'; ?>

<div class="container">
        <?php if(isset($_SESSION['error'])): ?>
        <div id="successAlert" class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <div style="text-align:left; width: 100%; margin-bottom:10px;">
        <form action="" method="GET" class="filter-form">
            <?php if($category_id>0): ?> <!--to keep current category in the url, to prevent display all eco product-->
                <input type="hidden" name="category" value="<?=$category_id?>"> 
                <?php endif; ?>
            <div class="filter-group">
                <label>Eco Friendly</label>
                <select name="eco_friendly" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="1" <?=(isset($_GET['eco_friendly']) && $_GET['eco_friendly']=='1') ? 'selected' : ''?>>Eco Friendly only</option>
                    <option value="0"<?=(isset($_GET['eco_friendly']) && $_GET['eco_friendly']=='0') ? 'selected' : ''?>>Non Eco Friendly</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort By</label>
                <select name="sort" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="ascending_price" <?=(isset($_GET['sort']) && $_GET['sort']=='ascending_price') ? 'selected' : ''?>>Price, low to high</option>
                    <option value="descending_price"<?=(isset($_GET['sort']) && $_GET['sort']=='descending_price') ? 'selected' : ''?>>Price, high to low</option>
                </select>
            </div>
        </form>
    </div>

    <?php if (empty($products)): ?>
        <div class="no-products">
            <h3>No products found.</h3>
            <p>We could not find any products in this category.</p>
            <a href="product.php" class="browse-product">Browse All Products</a>
        </div>
    <?php else: ?>  

        <div class="product-grid">
            <?php foreach($products as $prod): 
            $image_url="";
            if(!empty($prod['Product_Picture'])){
                $image_url="uploads/products/".$prod['Product_Picture'];
            } else{
                $image_url='uploads/placeholder.jpg';
            }
            $min_price=$prod['min_Price'];

            $ratingData = getProductAverageRating($conn, $prod['Product_ID']);
            $stars = generateStarRating($ratingData['average']);
            ?>

                <div class="product-card">
                    <div class="image-wrapper">
                        <img src="<?=$image_url ?>"
                             alt="<?= htmlspecialchars($prod['Product_Name']) ?>"
                             class="product-image"  
                             onerror="this.src='uploads/placeholder.jpg'">  
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?= htmlspecialchars($prod['Product_Name']) ?></h3>
                        <div class="category-name"><?= htmlspecialchars($prod['Category_Name']) ?></div>
                        <?php if($prod['eco_friendly']): ?>
                            <span class="eco-tag">Eco-Friendly</span>
                        <?php endif; ?>
                        <div class="product-price"> RM <?= number_format($min_price, 2) ?></div>
                        <div class="product-rating">
                            <span class="stars"><?=$stars ?></span>
                            <span class="rating-count">(<?=$ratingData['count'] ?> reviews)</span>
                        </div>
                        <a href="product_details.php?id=<?= $prod['Product_ID'] ?>" class="view-details">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

    <div id="chatbot-icon">
        <img id="chatbot-img" src="image\chatbot-image.jpeg" alt="chatbot"></div>
    <div id="chatbot-box" style="display:none;">
        <div id="chat-header">
            Chatbot
            <span id="close-chat">X</span>
        </div>

        <div id="chat-body"></div>
        <div id="chat-input-area">
            <input type="text" id="chat-input" placeholder="Ask something...">
            <button id="chat-send">Send</button>
        </div>
    </div>

<?php include_once 'includes/footer.php'; ?>

</body>
</html>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    //jQuery
    $(function(){
        var chatWindow=$('#chatbot-box');
        var chatBody=$('#chat-body');
        var chatInput=$('#chat-input');
        var sendBtn=$('#chat-send');
        var closeBtn=$('#close-chat');
        var icon=$('#chatbot-icon');

   icon.on('click',function(){
        chatWindow.show();
        if(chatBody.children().length === 0){
            loadHistory(); //if no message in chat, load history from database
        }
    });

    closeBtn.on('click',function(){
        chatWindow.hide();
    });

    function loadHistory() {
        $.get('load_history.php', function(data){
            if(data.history && data.history.length) {
                data.history.forEach(function(msg){
                    displayMessage(msg.message, msg.sender === 'user');
                });
                autoScroll();
            } else {
                //no history
                sendBotMessage("Hello, How can I help you today?");
            }
        }, 'json');
    }

    function displayMessage(text,isUser) {
        var messageDisplay=$('<div>').addClass('message').addClass(isUser ? 'user-message' : 'bot-message');
        var bubble=$('<div>').addClass('message-bubble').text(text);
        messageDisplay.append(bubble);
        chatBody.append(messageDisplay);
        autoScroll(); //every new message will auto scroll to bottom
    }
    
    function autoScroll() {
        setTimeout(function(){
            chatBody.scrollTop(chatBody[0].scrollHeight);  
        }, 50);
    }

    function sendBotMessage(text) {
        displayMessage(text, false);
    }

    function sendUserMessage() {
        var msg=chatInput.val().trim();
        if(msg === ''){
            return;  //if blank will not send
        }
        displayMessage(msg, true); //display a message 
        chatInput.val(''); //then clear input field
    
        //display typing...
        var typing=$('<div>').addClass('message bot-message').attr('id', 'typing-indicator');
        var typingBubble=$('<div>').addClass('message-bubble typing-indicator').text('Chatbot is typing...');
        
        typing.append(typingBubble);
        chatBody.append(typing);
        autoScroll();
        
        $.ajax({ //send ajax request to chatbot.php
            url:'chatbot.php',
            type:'POST',
            data:JSON.stringify({message:msg}), //keep the message in JSON format and send to backend
            contentType:'application/json',
            dataType:'json',
            success:function(res) { //success get response from backend
            $('#typing-indicator').remove(); //delete the typing... once get response
            if(res.response){
                displayMessage(res.response,false);  //display chatbot response
            } else {
                displayMessage('System now is busy. Please try again later.', false);
            }
        }, error:function(){ //if network error or chatbot.php error
            $('#typing-indicator').remove();
            displayMessage('Something\'s error. Please refresh and try again.', false);
            }
        });
    }

    sendBtn.on('click', sendUserMessage); //click send button
    chatInput.on('keypress', function(e){
        if(e.which === 13) {
            sendUserMessage(); //press enter key to send
        }
    });

});
</script>
