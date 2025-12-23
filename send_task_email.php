<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendTaskEmail($toEmail, $toUsername, $taskTitle, $taskDesc, $fromUser) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gorevapp0@gmail.com'; // kendi e-postanı yaz
        $mail->Password   = 'slfb rcks akcb zxop'; // Gmail uygulama şifren
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        $mail->setFrom('gorevapp0@gmail.com', 'GörevApp');
        $mail->addAddress($toEmail, $toUsername);

        $mail->isHTML(true);
        $mail->Subject = "📬 Yeni Görevin Var!";
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; color:#333;'>
            <p>Merhaba <b>$toUsername</b>,</p>
            <p><b>$fromUser</b> sana bir görev atadı:</p>
            <p><b>Görev Başlığı:</b> $taskTitle</p>
            <p><b>Açıklama:</b> $taskDesc</p>
            <hr>
            <small>Bu e-posta otomatik olarak GörevApp sistemi tarafından gönderilmiştir.</small>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail gönderim hatası: {$mail->ErrorInfo}");
        return false;
    }
}
?>
