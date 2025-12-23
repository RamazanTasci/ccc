<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $task_id = $_POST['id'];

    // Görev bilgilerini al
    $stmt = $conn->prepare("SELECT sender_id, receiver_id FROM tasks WHERE id=?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($task) {
        $user_id = $_SESSION['user_id'];

        // Sadece görevi gönderen veya alan silebilir
        if ($task['sender_id'] == $user_id || $task['receiver_id'] == $user_id) {

            // İlgili bildirimi de silebiliriz (isteğe bağlı)
            $stmt = $conn->prepare("DELETE FROM notifications WHERE message LIKE CONCAT('%', ?) OR message LIKE CONCAT('%', ?)");
            $stmt->bind_param("ss", $task_id, $task_id);
            $stmt->execute();
            $stmt->close();

            // Görevi sil
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>
