<?php
require_once(__DIR__ . "/../vendor/autoload.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOrderEmail($to, $subject, $body) {
$mail=new PHPMailer(true);

try{
    $mail->CharSet = "UTF-8";
    $mail->IsSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = 'kaihongoh5@gmail.com';
    $mail->Password = 'vfsw hkwh ukgh dkdm';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    //sender 
    $mail->setFrom('kaihongoh5@gmail.com','HomeNest Support');
    $mail->addReplyTo('noreply@homenest.com','No-Reply');
    //receiver
    $mail->addAddress($to);

    //content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();
    return true;
} catch(Exception $e){
    return$mail->ErrorInfo;
    }
}
?>