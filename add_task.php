<?php
ob_start();
include 'db.php';
include 'send_task_email.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 🔹 VERİLER
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$sender_id = $_SESSION['user_id'];
$receiver_id = intval($_POST['receiver_id'] ?? 0);
$task_date = $_POST['task_date'] ?? '';

// 🔹 TARİH ZORUNLU
if (empty($task_date)) {
    header("Location: dashboard.php?error=task_date_required");
    exit();
}

// 🔹 FOTO YÜKLEME
$imageName = null;
if (!empty($_FILES['task_image']['name'])) {
    $ext = pathinfo($_FILES['task_image']['name'], PATHINFO_EXTENSION);
    $imageName = uniqid() . "." . $ext;
    move_uploaded_file(
        $_FILES['task_image']['tmp_name'],
        "uploads/tasks/" . $imageName
    );
}

if ($receiver_id > 0) {

    // 🔹 GÖREVİ KAYDET (image DAHİL)
    $stmt = $conn->prepare(
        "INSERT INTO tasks 
        (title, description, sender_id, receiver_id, task_date, image)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "ssiiss",
        $title,
        $description,
        $sender_id,
        $receiver_id,
        $task_date,
        $imageName
    );

    $stmt->execute();
    $task_id = $stmt->insert_id;
    $stmt->close();

    // 🔹 GÖREV DETAYLARI (MAIL)
    $taskQuery = $conn->prepare("
        SELECT t.title, t.description,
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

    // 🔹 MAIL
    if ($task) {
        @sendTaskEmail(
            $task['receiver_email'],
            $task['receiver_name'],
            $task['title'],
            $task['description'],
            $task['sender_name']
        );
    }

    header("Location: dashboard.php?success=1");
    exit();

} else {
    header("Location: dashboard.php?error=invalid_user");
    exit();
}

ob_end_flush();
