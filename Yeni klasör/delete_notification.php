<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $notif_id = $_POST['id'];
    $user_id = $_SESSION['user_id'];

    // Sadece kendi bildirimini silebilsin
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
}
?>
