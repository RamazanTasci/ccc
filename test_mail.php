<?php
include 'send_task_email.php';

$mailSent = sendTaskEmail('ramazantascin@gmail.com', 'Ramazan', 'Deneme Görevi', 'Görev Sistemi Çalışıyor', 'GörevApp Bot');

if ($mailSent) {
    echo "✅ Mail gönderildi!";
} else {
    echo "❌ Mail gönderilemedi!";
}

// HTML mail içeriği
$mailHTML = '
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GörevApp Mail</title>
<style>
    body {
        margin:0; padding:0; font-family: Arial, sans-serif; background-color: #f4f6f8;
    }
    .container {
        max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 15px;
        overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .header {
        background: linear-gradient(90deg, #6a11cb, #000000ff); color: #fff;
        text-align: center; padding: 25px; font-size: 1.6rem; font-weight: bold;
    }
    .content {
        padding: 30px; color: #333; line-height: 1.6;
    }
    .content h2 { color: #2575fc; margin-bottom: 10px; }
    .content p { margin-bottom: 20px; font-size: 1rem; }
    .btn {
        display: inline-block; padding: 12px 25px; background: #2575fc; color: #fff;
        text-decoration: none; border-radius: 50px; font-weight: bold; transition: 0.3s;
    }
    .btn:hover { background: #6a11cb; }
    .footer {
        text-align: center; padding: 15px; font-size: 0.85rem; color: #888;
        background: #f0f0f0;
    }
</style>
</head>
<body>
    <div class="container">
        <div class="header">GörevApp</div>
        <div class="content">
            <h2>Merhaba Ramazan!</h2>
            <p>Yeni bir görev aldınız: <strong>Deneme Görevi</strong></p>
            <p>Görev Mesajı: Görev Sistemi Çalışıyor</p>
            <a href="#" class="btn">Göreve Git</a>
        </div>
        <div class="footer">
            Bu bir otomatik bildirimdir. GörevApp &copy; '.date("Y").'
        </div>
    </div>
</body>
</html>
';
