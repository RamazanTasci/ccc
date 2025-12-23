<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $done = $_POST['done'];

    // Görevi bul
    $stmt = $conn->prepare("SELECT sender_id, receiver_id, title FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($task) {
        $status = $done ? 'done' : 'pending';

        // Görevin durumunu güncelle
        $stmt = $conn->prepare("UPDATE tasks SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();

        // Eğer tamamlandıysa bildirim gönder
        if ($done) {
            $sender_id = $task['sender_id'];
            $title = $task['title'];
            $msg = "Gönderdiğin '{$title}' görevi tamamlandı ✅";

            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->bind_param("is", $sender_id, $msg);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>
