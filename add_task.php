<?php
include 'db.php';
include 'send_task_email.php';
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 🔹 Görev bilgilerini al
$title = trim($_POST['title']);
$description = trim($_POST['description']);
$sender_id = $_SESSION['user_id']; // Oturumdan al
$receiver_id = intval($_POST['receiver_id']);

// 🔹 Görevi kaydet
$stmt = $conn->prepare("INSERT INTO tasks (title, description, sender_id, receiver_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssii", $title, $description, $sender_id, $receiver_id);
$stmt->execute();
$task_id = $stmt->insert_id;
$stmt->close();

// 🔹 Görev detaylarını al
$taskQuery = $conn->prepare("
    SELECT 
        t.title, t.description, 
        s.username AS sender_name, 
        r.username AS receiver_name, 
        r.email AS receiver_email
    FROM tasks t
    JOIN users s ON t.sender_id = s.id
    JOIN users r ON t.receiver_id = r.id
    WHERE t.id = ?
");
$taskQuery->bind_param("i", $task_id);
$taskQuery->execute();
$task = $taskQuery->get_result()->fetch_assoc();
$taskQuery->close();

// 🔹 E-posta gönder
if ($task) {
    $mailSent = sendTaskEmail(
        $task['receiver_email'],
        $task['receiver_name'],
        $task['title'],
        $task['description'],
        $task['sender_name']
    );
} else {
    $mailSent = false;
}

// 🔹 Dashboard’a yönlendir
if ($mailSent) {
    header("Location: dashboard.php?success=1");
} else {
    header("Location: dashboard.php?error=1");
}
exit();
?>
