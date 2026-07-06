<?php
//get product average rating and total rating count
function getProductAverageRating($conn, $product_id) {
    $sql="SELECT ROUND(AVG(Rating), 1) as average_rating, COUNT(Rating) AS total_rating
    FROM product_ratings 
    JOIN product_variant ON product_ratings.Variant_ID = product_variant.Variant_ID
    WHERE product_variant.Product_ID=?";

    $stmt=$conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'average'=>$result['average_rating'] ? (float)$result['average_rating'] : 0, // Cast average rating to float, default to 0 if null
        'count' => isset($result['total_rating']) ? (int)$result['total_rating'] : 0 // Cast total rating to integer
    ];
}

//get product variant average rating and total rating count
function getVariantAverageRating($conn, $variant_id) {
    $sql="SELECT ROUND(AVG(Rating), 1) as average_rating, COUNT(Rating) AS total_rating
    FROM product_ratings 
    WHERE Variant_ID=?";

    $stmt=$conn->prepare($sql);
    $stmt->bind_param("i", $variant_id);
    $stmt->execute();
    $result=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'average'=>$result['average_rating'] ? (float)$result['average_rating'] : 0, // Cast average rating to float, default to 0 if null
        'count' => isset($result['total_rating']) ? (int)$result['total_rating'] : 0 // Cast total rating to integer
    ];
}

//generate star rating HTML
function generateStarRating($average_rating) {
    $full_stars = floor($average_rating);
    $half_star = ($average_rating - $full_stars) >= 0.5 ? 1 : 0;
    $empty_stars = 5 - $full_stars - $half_star;

    $html='';
    for($i=0; $i<$full_stars; $i++) {
        $html .= '⭐';
    }
    if ($half_star) {
        $html .= '⯪'; // 
    }
    for($i=0; $i<$empty_stars; $i++) {
        $html .= '☆';
    }

    return $html;
}
?>