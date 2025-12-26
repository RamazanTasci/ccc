<?php
session_start();
include 'db.php';

$me_id = $_SESSION['user_id'] ?? 0;
if (!$me_id) {
    echo json_encode(['success'=>false,'error'=>'Giriş yok']);
    exit;
}

$id = intval($_POST['task_id'] ?? 0);
if (!$id) {
    echo json_encode(['success'=>false,'error'=>'task_id yok']);
    exit;
}

$res = $conn->query("
    SELECT id, sender_id, receiver_id, status 
    FROM tasks 
    WHERE id = $id
");
$t = $res->fetch_assoc();
if (!$t) {
    echo json_encode(['success'=>false,'error'=>'Görev yok']);
    exit;
}

// Yetki kontrolü
if ($t['sender_id'] != $me_id && $t['receiver_id'] != $me_id) {
    echo json_encode(['success'=>false,'error'=>'Yetkisiz']);
    exit;
}

if ($t['status'] === 'completed') {
    // 🔁 GERİ AL
    $conn->query("
        UPDATE tasks
        SET status='pending',
            completed_by=NULL,
            updated_at=NOW()
        WHERE id=$id
    ");
    echo json_encode(['success'=>true,'undone'=>true]);
} else {
    // ✅ TAMAMLA
    $conn->query("
        UPDATE tasks
        SET status='completed',
            completed_by=$me_id,
            updated_at=NOW()
        WHERE id=$id
    ");
    echo json_encode(['success'=>true,'completed'=>true]);
}
