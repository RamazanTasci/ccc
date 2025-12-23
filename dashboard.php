<?php
// ---------------- CONFIG ----------------
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// DB
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'login_system';

// Session keys (auth)
$SESSION_USER_ID_KEY = 'user_id';
$SESSION_USERNAME_KEY = 'username';

// Simple demo-login fallback if no auth.php (for local dev)
if (!isset($_SESSION[$SESSION_USER_ID_KEY])) {
    // Demo auto-login as user id 1 if exists — remove in production
    // You can create user with: INSERT INTO users (username,password_hash) VALUES ('demo','hash');
    // For safety, do not auto-login on production.
    // $_SESSION[$SESSION_USER_ID_KEY] = 1;
    // $_SESSION[$SESSION_USERNAME_KEY] = 'demo';
}

// ---------------- DB CONNECT ----------------
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB bağlantı hatası: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Helper
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function json_ok($data=[]){ header('Content-Type: application/json'); echo json_encode(array_merge(['success'=>true], $data)); exit; }
function json_err($msg='Hata'){ header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>$msg]); exit; }

// Basic auth check for API
$me_id = isset($_SESSION[$SESSION_USER_ID_KEY]) ? (int)$_SESSION[$SESSION_USER_ID_KEY] : 0;
$me_username = isset($_SESSION[$SESSION_USERNAME_KEY]) ? $_SESSION[$SESSION_USERNAME_KEY] : null;

// ---------------- ROUTER (API) ----------------
$action = $_REQUEST['action'] ?? null;
if ($action) {
    header('Content-Type: application/json; charset=utf-8');

    // Require login for API calls except 'demo_login' or 'setup'
    $public_actions = ['demo_login','setup_migration'];
    if (!$me_id && !in_array($action, $public_actions)) {
        json_err('Oturum yok');
    }

    // ---------- PUBLIC: demo login ----------
    if ($action === 'demo_login') {
        // For dev only: create demo users if not exist and login as first
        $u = 'demo_user';
        $res = $conn->query("SELECT id, username FROM users WHERE username = '{$conn->real_escape_string($u)}' LIMIT 1");
        if ($res->num_rows==0) {
            $pw = password_hash('demo123', PASSWORD_DEFAULT);
            $conn->query("INSERT INTO users (username,password_hash,role) VALUES ('{$conn->real_escape_string($u)}','$pw','admin')");
            $id = $conn->insert_id;
        } else {
            $r = $res->fetch_assoc(); $id = $r['id'];
        }
        $_SESSION[$SESSION_USER_ID_KEY] = (int)$id;
        $_SESSION[$SESSION_USERNAME_KEY] = $u;
        json_ok(['user_id'=>$id,'username'=>$u]);
    }

    // ---------- Setup migration (create tables) ----------
    if ($action === 'setup_migration') {
        // Caution: run once
        $sqls = [];
        $sqls[] = "CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, avatar_url VARCHAR(255) DEFAULT NULL, role ENUM('admin','editor','viewer') DEFAULT 'viewer', created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS groups (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, description TEXT, created_by INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS group_members (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL, user_id INT NOT NULL, is_admin TINYINT(1) DEFAULT 0, status ENUM('pending','accepted','rejected') DEFAULT 'pending', joined_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS projects (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, description TEXT, created_by INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS tasks (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT DEFAULT NULL, group_id INT DEFAULT NULL, parent_task_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT, sender_id INT NOT NULL, receiver_id INT DEFAULT NULL, due_date DATE DEFAULT NULL, priority ENUM('low','normal','high') DEFAULT 'normal', status ENUM('pending','in_progress','completed') DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message TEXT NOT NULL, type ENUM('task','group_invite','chat','system') DEFAULT 'task', related_id INT DEFAULT NULL, is_read TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS group_messages (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL, sender_id INT NOT NULL, message TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS direct_messages (id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, receiver_id INT NOT NULL, message TEXT NOT NULL, is_read TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS task_comments (id INT AUTO_INCREMENT PRIMARY KEY, task_id INT NOT NULL, user_id INT NOT NULL, comment TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS task_files (id INT AUTO_INCREMENT PRIMARY KEY, task_id INT NOT NULL, uploader_id INT NOT NULL, file_name VARCHAR(255), file_path VARCHAR(255), uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $sqls[] = "CREATE TABLE IF NOT EXISTS task_tags (id INT AUTO_INCREMENT PRIMARY KEY, task_id INT NOT NULL, tag VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $failed = [];
        foreach ($sqls as $s) {
            if (!$conn->query($s)) $failed[] = $conn->error;
        }
        if ($failed) json_err('Migration hatası: ' . implode('; ', $failed));
        json_ok(['message'=>'Migration tamamlandı']);
    }

    // ---------- USERS ----------
    if ($action === 'list_users') {
        $out = [];
        $res = $conn->query("SELECT id, username, avatar_url, role FROM users ORDER BY username");
        while ($r = $res->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    if ($action === 'create_user' && $_SERVER['REQUEST_METHOD']==='POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username==='' || $password==='') json_err('Alanlar boş olamaz');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->bind_param('ss', $username, $hash);
        if ($stmt->execute()) json_ok(['user_id'=>$stmt->insert_id]);
        else json_err('DB: '.$conn->error);
    }

    // ---------- GROUPS ----------
    if ($action === 'list_groups') {
        $out = [];
        $stmt = $conn->prepare("SELECT g.*, gm.is_admin, gm.status FROM groups g LEFT JOIN group_members gm ON gm.group_id=g.id AND gm.user_id=? WHERE gm.user_id IS NOT NULL OR g.created_by=? ORDER BY g.name");
        $stmt->bind_param('ii', $me_id, $me_id);
        $stmt->execute(); $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    if ($action === 'create_group' && $_SERVER['REQUEST_METHOD']==='POST') {
        $name = trim($_POST['name'] ?? '');
        $members = $_POST['members'] ?? [];
        if ($name==='') json_err('Grup adı gerekli');
        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO groups (name, description, created_by) VALUES (?, ?, ?)");
        $desc = $_POST['description'] ?? '';
        $stmt->bind_param('ssi', $name, $desc, $me_id);
        if (!$stmt->execute()) { $conn->rollback(); json_err('DB: '.$conn->error); }
        $group_id = $stmt->insert_id;
        // add creator as accepted admin
        $stmt2 = $conn->prepare("INSERT INTO group_members (group_id, user_id, is_admin, status) VALUES (?, ?, ?, 'accepted')");
        $is_admin = 1;
        $stmt2->bind_param('iii', $group_id, $me_id, $is_admin);
        $stmt2->execute();
        // invite others
        $stmtInv = $conn->prepare("INSERT INTO group_members (group_id, user_id, is_admin, status) VALUES (?, ?, 0, 'pending')");
        $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'group_invite', ?)");
        foreach ($members as $m) {
            $m = (int)$m;
            if ($m && $m !== $me_id) {
                $stmtInv->bind_param('ii', $group_id, $m);
                $stmtInv->execute();
                $msg = "Grup daveti: '{$name}' grubuna davet edildiniz.";
                $stmtNotif->bind_param('isi', $m, $msg, $group_id);
                $stmtNotif->execute();
            }
        }
        $conn->commit();
        json_ok(['group_id'=>$group_id]);
    }

    if ($action === 'respond_invite' && $_SERVER['REQUEST_METHOD']==='POST') {
        $group_id = intval($_POST['group_id'] ?? 0);
        $resp = $_POST['response'] ?? 'reject';
        if (!$group_id) json_err('group_id gerekli');
        if ($resp=='accept') {
            $stmt = $conn->prepare("UPDATE group_members SET status='accepted', joined_at=NOW() WHERE group_id=? AND user_id=?");
            $stmt->bind_param('ii', $group_id, $me_id); $stmt->execute();
            // notify admins
            $res = $conn->query("SELECT user_id FROM group_members WHERE group_id=$group_id AND is_admin=1");
            while ($a = $res->fetch_assoc()) {
                $uid = (int)$a['user_id'];
                if ($uid != $me_id) {
                    $msg = $me_username . " grubunuza katıldı.";
                    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'system', ?)");
                    $stmtN->bind_param('isi', $uid, $msg, $group_id); $stmtN->execute();
                }
            }
            json_ok(['status'=>'accepted']);
        } else {
            $stmt = $conn->prepare("UPDATE group_members SET status='rejected' WHERE group_id=? AND user_id=?");
            $stmt->bind_param('ii',$group_id,$me_id); $stmt->execute();
            json_ok(['status'=>'rejected']);
        }
    }

    if ($action === 'delete_group' && $_SERVER['REQUEST_METHOD']==='POST') {
        $group_id = intval($_POST['group_id'] ?? 0);
        // check admin
        $res = $conn->query("SELECT is_admin FROM group_members WHERE group_id=$group_id AND user_id=$me_id AND status='accepted' LIMIT 1");
        $row = $res->fetch_assoc();
        if (!$row || !$row['is_admin']) json_err('Yetkiniz yok');
        // delete cascade manually (DB should be set to cascade, but ensure)
        $conn->begin_transaction();
        $conn->query("DELETE FROM task_files WHERE task_id IN (SELECT id FROM tasks WHERE group_id=$group_id)");
        $conn->query("DELETE FROM task_comments WHERE task_id IN (SELECT id FROM tasks WHERE group_id=$group_id)");
        $conn->query("DELETE FROM tasks WHERE group_id=$group_id");
        $conn->query("DELETE FROM group_members WHERE group_id=$group_id");
        $conn->query("DELETE FROM group_messages WHERE group_id=$group_id");
        $conn->query("DELETE FROM groups WHERE id=$group_id");
        $conn->commit();
        json_ok(['deleted'=>true]);
    }

// ---------- TASKS ----------
if ($action === 'create_task' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $receiver_id = !empty($_POST['receiver_id']) ? intval($_POST['receiver_id']) : null;
    $group_id = !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $priority = in_array($_POST['priority'] ?? 'normal', ['low','normal','high']) ? $_POST['priority'] : 'normal';
    if ($title === '') json_err('Başlık gerekli');

    // Görevi veritabanına ekle
    $stmt = $conn->prepare("INSERT INTO tasks (project_id, group_id, parent_task_id, title, description, sender_id, receiver_id, due_date, priority, status, created_at) 
                            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param('iississs', $project_id, $group_id, $title, $description, $me_id, $receiver_id, $due_date, $priority);
    if (!$stmt->execute()) json_err('DB: '.$conn->error);

    $task_id = $stmt->insert_id;

    // Bildirim kaydı
    if ($receiver_id && $receiver_id !== $me_id) {
        $msg = "Yeni görev: {$title}";
        $stmtN = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'task', ?)");
        $stmtN->bind_param('isi', $receiver_id, $msg, $task_id);
        $stmtN->execute();
    }

    // --- EMAIL GÖNDERİMİ (PHPMailer) ---
    require 'PHPMailer/PHPMailer.php';
    require 'PHPMailer/SMTP.php';
    require 'PHPMailer/Exception.php';


    // Alıcı e-postasını al
    $result = $conn->query("SELECT email, username FROM users WHERE id = $receiver_id");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $receiver_email = $user['email'];
        $receiver_name = $user['username'];

        $mail = new PHPMailer(true);
        try {
            // Sunucu ayarları
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'rmztesttask@gmail.com';
            $mail->Password = 'slfb rcks akcb zxop'; // senin verdiğin uygulama şifresi
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            // Gönderen & Alıcı
            $mail->setFrom('rmztesttask@gmail.com', 'Görev Sistemi');
            $mail->addAddress($receiver_email, $receiver_name);

            // İçerik
            $mail->isHTML(true);
            $mail->Subject = "Yeni Görev Atandı: $title";
            $mail->Body = "
                <h3>Yeni Görev Detayları</h3>
                <p><strong>Görev Başlığı:</strong> $title</p>
                <p><strong>Açıklama:</strong> $description</p>
                <p><strong>Öncelik:</strong> $priority</p>
                <p><strong>Bitiş Tarihi:</strong> $due_date</p>
                <p><strong>Atayan:</strong> Kullanıcı ID $me_id</p>
                <hr>
                <p>Bu e-posta sistem tarafından otomatik gönderilmiştir.</p>
            ";

            $mail->send();
        } catch (Exception $e) {
            error_log("E-posta gönderim hatası: {$mail->ErrorInfo}");
        }
    }

    json_ok(['id' => $task_id]);
}

    if ($action === 'list_tasks') {
    $filter = $_GET['filter'] ?? 'all';
    $project_id = !empty($_GET['project_id']) ? intval($_GET['project_id']) : null;
    $where = [];

    if ($filter === 'inbox') $where[] = "t.receiver_id = $me_id";
    if ($filter === 'sent') $where[] = "t.sender_id = $me_id";
    if ($filter === 'today') $where[] = "DATE(t.due_date) = CURDATE()";
    if ($project_id) $where[] = "t.project_id = $project_id";

    // 🔧 Burayı değiştirdik:
    $q = "
    SELECT 
        t.*, 
        s.username AS sender_username, 
        r.username AS receiver_username, 
        c.username AS completed_by_username
    FROM tasks t
    LEFT JOIN users s ON s.id = t.sender_id 
    LEFT JOIN users r ON r.id = t.receiver_id 
    LEFT JOIN users c ON c.id = t.completed_by
    ";

    if ($where) $q .= " WHERE " . implode(' AND ', $where);
    $q .= " ORDER BY t.created_at DESC LIMIT 1000";

    $res = $conn->query($q);
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out);
    exit;
}


    if ($action === 'get_task') {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json_err('id gerekli');
        $res = $conn->query("SELECT t.*, s.username AS sender_username, r.username AS receiver_username FROM tasks t LEFT JOIN users s ON s.id=t.sender_id LEFT JOIN users r ON r.id=t.receiver_id WHERE t.id=$id LIMIT 1");
        $task = $res->fetch_assoc();
        echo json_encode($task); exit;
    }

    if ($action === 'update_task' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) json_err('id gerekli');
        // only sender or receiver can update basic fields (for demo)
        $res = $conn->query("SELECT sender_id, receiver_id FROM tasks WHERE id=$id");
        $t = $res->fetch_assoc();
        if (!$t) json_err('Görev yok');
        if (!($t['sender_id']==$me_id || $t['receiver_id']==$me_id)) json_err('Yetkiniz yok');
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $due = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $priority = in_array($_POST['priority'] ?? 'normal', ['low','normal','high']) ? $_POST['priority'] : 'normal';
        $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, due_date=?, priority=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('ssssi',$title,$desc,$due,$priority,$id);
        if (!$stmt->execute()) json_err('DB: '.$conn->error);
        // notification: inform receiver/sender
        json_ok(['updated'=>true]);
    }

    if ($action === 'complete_task' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id = intval($_POST['task_id'] ?? 0);
        if (!$id) json_err('task_id gerekli');
        $res = $conn->query("SELECT id,title,sender_id,receiver_id,status FROM tasks WHERE id=$id");
        $t = $res->fetch_assoc();
        if (!$t) json_err('Görev bulunamadı');
        if (!($t['sender_id']==$me_id || $t['receiver_id']==$me_id)) json_err('Yetkiniz yok');
        if ($t['status']=='completed') json_ok(['already'=>true]);
        $conn->query("UPDATE tasks SET status='completed', updated_at=NOW() WHERE id=$id");
        // notify sender if different
        if ($t['sender_id'] && $t['sender_id'] != $me_id) {
            $msg = "Görev tamamlandı: " . $conn->real_escape_string($t['title']);
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'task', ?)");
            $stmtN->bind_param('isi', $t['sender_id'], $msg, $id);
            $stmtN->execute();
        }
        json_ok(['completed'=>true]);
    }

    // ---------- NOTIFICATIONS ----------
    if ($action === 'list_notifications') {
        $out = ['notifications'=>[], 'unread_count'=>0];
        $res = $conn->query("SELECT id, message, type, related_id, is_read, created_at FROM notifications WHERE user_id = $me_id ORDER BY created_at DESC LIMIT 200");
        while ($r = $res->fetch_assoc()) {
            $out['notifications'][] = $r;
            if (!$r['is_read']) $out['unread_count']++;
        }
        echo json_encode($out); exit;
    }

    if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD']==='POST') {
        $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$me_id");
        json_ok();
    }

    if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD']==='POST') {
        $nid = intval($_POST['notification_id'] ?? 0);
        if (!$nid) json_err('notification_id gerekli');
        $conn->query("UPDATE notifications SET is_read=1 WHERE id=$nid AND user_id=$me_id");
        json_ok();
    }

    // ---------- MESSAGES (Group & Direct) ----------
    if ($action === 'send_direct_message' && $_SERVER['REQUEST_METHOD']==='POST') {
        $to = intval($_POST['to'] ?? 0); $msg = trim($_POST['message'] ?? '');
        if (!$to || $msg==='') json_err('to ve message gerekli');
        $stmt = $conn->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $me_id, $to, $msg);
        if (!$stmt->execute()) json_err('DB: '.$conn->error);
        $mid = $stmt->insert_id;
        $nt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_id) VALUES (?, ?, 'chat', ?)");
        $nt->bind_param('isi', $to, $msg, $mid); $nt->execute();
        json_ok(['message_id'=>$mid]);
    }

    if ($action === 'list_direct_messages') {
        $other = intval($_GET['other'] ?? 0);
        if (!$other) json_err('other gerekli');
        $q = $conn->prepare("SELECT dm.*, s.username AS sender_username FROM direct_messages dm JOIN users s ON s.id=dm.sender_id WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC");
        $q->bind_param('iiii',$me_id,$other,$other,$me_id); $q->execute(); $res = $q->get_result();
        $out = []; while ($r = $res->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    // ---------- FILE UPLOAD (stub) ----------
    if ($action === 'upload_task_file' && $_SERVER['REQUEST_METHOD']==='POST') {
        // NOTE: for production please validate file types, sizes & store outside webroot
        if (!isset($_FILES['file'])) json_err('file missing');
        $task_id = intval($_POST['task_id'] ?? 0);
        $f = $_FILES['file'];
        $uploads_dir = __DIR__ . '/uploads';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        $name = basename($f['name']);
        $target = $uploads_dir . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_', $name);
        if (!move_uploaded_file($f['tmp_name'], $target)) json_err('Dosya yüklenemedi');
        $stmt = $conn->prepare("INSERT INTO task_files (task_id, uploader_id, file_name, file_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $task_id, $me_id, $name, $target);
        $stmt->execute();
        json_ok(['file_id'=>$stmt->insert_id, 'path'=>$target]);
    }

    // ---------- ADMIN (example) ----------
    if ($action === 'list_all_users' && $_SERVER['REQUEST_METHOD']==='GET') {
        // only admins
        $r = $conn->query("SELECT role FROM users WHERE id = $me_id")->fetch_assoc();
        if (!$r || $r['role'] !== 'admin') json_err('Yetkiniz yok');
        $res = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
        $out = []; while ($row = $res->fetch_assoc()) $out[] = $row;
        echo json_encode($out); exit;
    }

    json_err('Bilinmeyen action: ' . $action);
}

// ---------------- UI (HTML/CSS/JS) ----------------
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Görev Uygulaması</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<style>
:root{--bg:#f6f7f9;--accent:#ff4d4f;--muted:#7b7f87;--panel:#fff;--green:#2ecc71}
*{box-sizing:border-box} body{font-family:Inter,system-ui; margin:0; background:var(--bg); color:#111}
.app{display:flex;min-height:100vh}
.sidebar{width:280px;background:#fff;border-right:1px solid #eee;padding:22px;display:flex;flex-direction:column;gap:14px}
.avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#7b2ff7,#ff6a88);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700}
.small-muted{color:var(--muted);font-size:13px}
.btn-add{display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:10px;background:#fff;border:1px solid #f2f2f2;cursor:pointer;color:var(--accent);font-weight:700}
.nav-section{margin-top:4px}
.nav-item{display:flex;align-items:center;gap:12px;padding:10px;border-radius:8px;color:#333;cursor:pointer}
.nav-item.active{background:linear-gradient(90deg, rgba(255,74,85,0.06), rgba(255,191,168,0.04));font-weight:600}
.main{flex:1;padding:26px 36px}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.title{font-size:26px;font-weight:700}
.top-actions{display:flex;gap:12px;align-items:center}
.notif{position:relative;cursor:pointer;color:var(--muted)}
.notif .count{position:absolute;top:-6px;right:-6px;background:var(--accent);color:white;font-size:11px;padding:2px 6px;border-radius:12px}
.panel{background:var(--panel);border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(18,18,18,0.04)}
.task-list{display:flex;flex-direction:column;gap:8px}
.task-row{display:flex;align-items:center;justify-content:space-between;padding:12px;border-radius:8px;border:1px solid #f0f0f0;background:#fbfbfb}
.radio-circle{width:18px;height:18px;border-radius:50%;border:2px solid #ddd;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.radio-circle.checked{border-color:var(--green);background:rgba(46,204,113,0.12)}
.small-btn{padding:6px 10px;border-radius:8px;border:1px solid #eee;background:#fff;cursor:pointer}
.notif-dropdown{position:absolute;right:20px;top:58px;width:380px;background:white;border:1px solid #eee;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,0.06);display:none;z-index:2000}
.notif-item{padding:10px;border-bottom:1px solid #f2f2f2}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);align-items:center;justify-content:center;padding:20px;z-index:9999}
.modal .card{max-width:720px;width:100%}
.hint{font-size:13px;color:#777}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="avatar" id="avatar"><?php echo $me_username ? strtoupper(substr(h($me_username),0,1)) : '?'; ?></div>
        <div>
          <div style="font-weight:700" id="username"><?php echo $me_username ? h($me_username) : 'Misafir'; ?></div>
          <div class="small-muted">Hoş geldin</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <button id="btnLogout" title="Çıkış" style="background:none;border:none;cursor:pointer;color:var(--muted)"><i class="fa-solid fa-right-from-bracket"></i></button>
      </div>
    </div>

    <div><button id="quickNew" class="btn-add"><i class="fa-solid fa-plus" style="color:var(--accent)"></i> Yeni Görev</button></div>

    <div class="nav-section">
      <div class="nav-item" data-filter="today" id="nav-today">Bugün</div>
      <div class="nav-item" data-filter="inbox" id="nav-inbox">Bana Gelenler</div>
      <div class="nav-item" data-filter="sent" id="nav-sent">Gönderdiklerim</div>
      <div class="nav-item active" data-filter="all" id="nav-all">Tüm Görevler</div>
    </div>

    <hr style="border:none;border-top:1px solid #f0f0f0;margin:6px 0">
    <div class="small-muted">Hızlı Erişim</div>
    <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
      <div class="nav-item" id="nav-projects"><i class="fa-regular fa-folder-open"></i> Projeler</div>
      <div class="nav-item" id="nav-kanban"><i class="fa-solid fa-table-columns"></i> Kanban</div>
      <div class="nav-item" id="nav-calendar"><i class="fa-regular fa-calendar"></i> Takvim</div>
      <div class="nav-item" id="nav-groups"><i class="fa-solid fa-users"></i> Ekipler</div>
      <div class="nav-item" id="nav-messages"><i class="fa-solid fa-message"></i> Mesajlar</div>
    </div>

    <div style="margin-top:auto">
      <div class="hint">Panel base - tüm özellikler arka planda etkinleştirilebilir.</div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div class="title">Görev Paneli</div>
      <div class="top-actions">
        <div class="notif" id="notifBtn"><i class="fa-regular fa-bell fa-lg"></i> <span class="count" id="notif_count" style="display:none">0</span></div>
      </div>
    </div>

    <div class="panel" id="mainPanel">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h4 id="panelTitle">Tüm Görevler</h4>
        <div>
          <select id="filterPriority"><option value="">Tümü</option><option value="high">Yüksek</option><option value="normal">Normal</option><option value="low">Düşük</option></select>
          <button class="small-btn" id="refreshBtn">Yenile</button>
        </div>
      </div>
      <div id="listContainer" class="task-list">Yükleniyor...</div>
    </div>
  </main>
</div>

<!-- notif dropdown -->
<div class="notif-dropdown" id="notifDropdown">
  <div style="padding:12px;border-bottom:1px solid #f2f2f2;font-weight:700">Bildirimler <button style="float:right" id="markAll" class="small-muted">Tümünü okundu yap</button></div>
  <div id="notifList" style="max-height:420px;overflow:auto"></div>
</div>

<!-- Modals -->
<div class="modal" id="modalTask">
  <div class="card" style="background:white;padding:18px;border-radius:8px">
    <h3>Yeni Görev</h3>
    <form id="taskForm">
      <div style="margin-top:8px"><input name="title" placeholder="Başlık" required style="width:100%;padding:8px;border:1px solid #eee;border-radius:6px"></div>
      <div style="margin-top:8px"><textarea name="description" placeholder="Açıklama" style="width:100%;padding:8px;border:1px solid #eee;border-radius:6px"></textarea></div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <select name="receiver_id" id="taskReceiver" style="padding:8px;border-radius:6px;border:1px solid #eee">
          <option value="">Kendime</option>
        </select>
        <input type="date" name="due_date" style="padding:8px;border-radius:6px;border:1px solid #eee">
        <select name="priority" style="padding:8px;border-radius:6px;border:1px solid #eee">
          <option value="normal">Normal</option><option value="high">Yüksek</option><option value="low">Düşük</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <button type="button" id="taskCancel" class="small-btn">İptal</button>
        <button type="submit" class="small-btn" style="background:var(--accent);color:#fff;border:none">Kaydet</button>
      </div>
    </form>
  </div>
</div>

<script>
// Minimal client API helper
function api(action, data = {}, method = 'GET') {
  method = method.toUpperCase();
  const url = location.pathname + '?action=' + encodeURIComponent(action);
  if (method === 'GET') {
    const params = new URLSearchParams(data).toString();
    return fetch(url + (params ? '&' + params : ''), {credentials: 'include'}).then(r => r.json());
  } else {
    const fd = new FormData();
    for (const k in data) fd.append(k, data[k]);
    return fetch(url, {method:'POST', body:fd, credentials:'include'}).then(r => r.json());
  }
}

// UI wiring
const listContainer = document.getElementById('listContainer');
const panelTitle = document.getElementById('panelTitle');
let currentFilter = 'all';

function renderTaskRow(t) {
  const checked = t.status === 'completed';
  const chk = checked
    ? '<div class="radio-circle checked"><i class="fa-solid fa-check" style="font-size:10px;color:var(--green)"></i></div>'
    : `<div class="radio-circle" data-id="${t.id}"></div>`;

  const due = t.due_date ? `<span class="hint">${t.due_date}</span>` : '';

  // 🔧 Düzeltilmiş kısım burası:
  const yapanInfo =
    t.status === 'completed' && t.completed_by_username
      ? ` • Yapan: ${escapeHtml(t.completed_by_username)}`
      : '';

  return `
  <div class="task-row">
    <div style="display:flex;align-items:center;gap:12px">
      ${chk}
      <div>
        <div style="font-weight:600">${escapeHtml(t.title)}</div>
        <div class="hint">
          ${escapeHtml(t.description || '')} 
          • Gönderen: ${escapeHtml(t.sender_username || '-')}

        • Yapan: ${escapeHtml(t.completed_by_username || '-')}
          ${yapanInfo}
        </div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      ${due}
      <div class="hint">${escapeHtml(t.priority)}</div>
      ${
        t.status === 'completed'
          ? '<div style="color:var(--green)">Tamamlandı</div>'
          : `<button class="small-btn complete-btn" data-id="${t.id}">Tamamla</button>`
      }
    </div>
  </div>`;
}


function escapeHtml(s){ if(!s) return ''; return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }

function loadTasks(filter = 'all') {
  currentFilter = filter;
  panelTitle.innerText = filter==='all' ? 'Tüm Görevler' : (filter==='inbox'?'Bana Gelenler':(filter==='sent'?'Gönderdiklerim':'Bugün'));
  listContainer.innerHTML = 'Yükleniyor...';
  api('list_tasks', {filter}).then(res => {
    if (!Array.isArray(res)) { listContainer.innerHTML = '<div class="hint">Hata</div>'; return; }
    if (res.length === 0) listContainer.innerHTML = '<div class="hint">Görev yok</div>';
    else listContainer.innerHTML = res.map(renderTaskRow).join('');
    // attach handlers
    document.querySelectorAll('.radio-circle').forEach(el=>{
      const id = el.getAttribute('data-id');
      if (id) el.addEventListener('click', ()=>completeTask(id));
    });
    document.querySelectorAll('.complete-btn').forEach(b=>{
      b.addEventListener('click', ()=>completeTask(b.dataset.id));
    });
  });
}

function completeTask(id) {
  api('complete_task',{task_id:id},'POST').then(res=>{
    if (res.success) loadTasks(currentFilter);
    else alert(res.message || 'Hata');
  });
}

// notifications
function refreshNotifications() {
  api('list_notifications').then(res=>{
    if (!res || !res.notifications) return;
    const count = res.unread_count || 0;
    const elc = document.getElementById('notif_count');
    if (count>0) { elc.style.display='inline-block'; elc.textContent = count; } else elc.style.display='none';
    const list = document.getElementById('notifList'); list.innerHTML = '';
    res.notifications.forEach(n=>{
      const div = document.createElement('div'); div.className='notif-item';
      div.innerHTML = `<div style="font-weight:600">${escapeHtml(n.message)}</div><div class="hint">${n.type} • ${n.created_at}</div>`;
      list.appendChild(div);
    });
  });
}

document.getElementById('notifBtn').addEventListener('click', ()=>{
  const d = document.getElementById('notifDropdown');
  d.style.display = d.style.display==='block' ? 'none' : 'block';
  refreshNotifications();
});

document.getElementById('markAll').addEventListener('click', ()=>{
  api('mark_all_read', {}, 'POST').then(()=> refreshNotifications());
});

// sidebar nav
document.querySelectorAll('.nav-item').forEach(ni=>{
  ni.addEventListener('click', function(){
    document.querySelectorAll('.nav-item').forEach(x=>x.classList.remove('active'));
    this.classList.add('active');
    const f = this.dataset.filter || 'all';
    loadTasks(f);
  });
});

// quick modals
document.getElementById('quickNew').addEventListener('click', ()=>openTaskModal());
document.getElementById('taskCancel').addEventListener('click', ()=>closeModal('modalTask'));
document.getElementById('taskForm').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  const data = {};
  for (const [k,v] of fd.entries()) data[k]=v;
  api('create_task', data, 'POST').then(res=>{
    if (res.success) { closeModal('modalTask'); loadTasks(currentFilter); refreshNotifications(); this.reset(); }
    else alert(res.message || 'Hata');
  });
});

function openTaskModal(){ document.getElementById('modalTask').style.display='flex'; loadUsersIntoSelect(); }
function closeModal(id){ document.getElementById(id).style.display='none'; }

function loadUsersIntoSelect(){
  api('list_users').then(list=>{
    const sel = document.getElementById('taskReceiver');
    sel.innerHTML = '<option value="">Kendime</option>';
    list.forEach(u=>{
      const opt = document.createElement('option'); opt.value = u.id; opt.textContent = u.username; sel.appendChild(opt);
    });
  });
}

// logout
document.getElementById('btnLogout').addEventListener('click', ()=>{
  if (!confirm('Çıkış yapmak istediğine emin misin?')) return;
  api('logout', {}, 'POST').then(()=> location.href = 'index.php');
});

// initial load
loadTasks('all');
refreshNotifications();
setInterval(refreshNotifications, 3500);

</script>
</body>
</html>
