<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

function sendTaskEmail($toEmail, $toUsername, $taskTitle, $taskDesc, $fromUser)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'gorevapp0@gmail.com';

        // ⚠️ BOŞLUKSUZ!
        $mail->Password = 'slfbrcksakcbzxop';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('gorevapp0@gmail.com', 'GörevApp');
        $mail->addAddress($toEmail, $toUsername);

        $mail->isHTML(true);
        $mail->Subject = '📬 Yeni Görevin Var!';
        $mail->Body = "
            <h3>Merhaba $toUsername</h3>
            <p><b>$fromUser</b> sana yeni bir görev atadı.</p>
            <p><b>Görev:</b> $taskTitle</p>
            <p><b>Açıklama:</b> $taskDesc</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        echo 'MAIL HATASI: ' . $mail->ErrorInfo;
        exit;
    }
}
