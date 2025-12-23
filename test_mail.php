<?php
include 'send_task_email.php';

if (sendTaskEmail('ramazantascin@gmail.com', 'Ramazan', 'Test Görevi', 'Bu bir test mesajıdır.', 'GörevApp Bot')) {
    echo "✅ Mail gönderildi!";
} else {
    echo "❌ Mail gönderilemedi!";
}
?>
