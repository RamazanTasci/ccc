<?php
include 'send_task_email.php';

if (sendTaskEmail('ramazantascin@gmail.com', 'Ramazan', 'Beşiktaş Fenerbahçe Maçı Skor Bilgisi', 'Rafa Silva Beşiktaşta 20 sene sözleşme imzaladı ve uyacağına sözleşme yaptı', 'GörevApp Bot')) {
    echo "✅ Mail gönderildi!";
} else {
    echo "❌ Mail gönderilemedi!";
}
?>
