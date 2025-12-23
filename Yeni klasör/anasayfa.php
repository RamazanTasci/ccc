<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db.php';
$user_id = $_SESSION['user_id'];

// Görev ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'])) {
    $receiver_id = $_POST['receiver_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO tasks (sender_id, receiver_id, title, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $receiver_id, $title, $description);
    $stmt->execute();
    $stmt->close();

    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Kullanıcılar (görev atamak için)
$users = $conn->query("SELECT id, username FROM users WHERE id != $user_id");

// Bana gelen görevler
$incoming = $conn->query("SELECT t.*, u.username AS sender FROM tasks t JOIN users u ON t.sender_id=u.id WHERE receiver_id=$user_id ORDER BY t.created_at DESC");

// Benim verdiğim görevler
$outgoing = $conn->query("SELECT t.*, u.username AS receiver FROM tasks t JOIN users u ON t.receiver_id=u.id WHERE sender_id=$user_id ORDER BY t.created_at DESC");

// Bildirimler (son 5 tane)
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Görev Paneli</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
<style>
body {
  margin: 0;
  padding: 0;
  background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
  font-family: 'Poppins', sans-serif;
  color: #fff;
}
.container {
  width: 90%;
  max-width: 1000px;
  margin: 50px auto;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  padding: 30px;
  backdrop-filter: blur(10px);
  box-shadow: 0 0 25px rgba(0,0,0,0.4);
}
h2 {
  text-align: center;
  margin-bottom: 20px;
  color: #ffbfa8;
}
form {
  display: flex;
  gap: 10px;
  margin-bottom: 30px;
  flex-wrap: wrap;
  justify-content: center;
}
input, textarea, select {
  border: none;
  border-radius: 10px;
  padding: 10px;
  background: rgba(255,255,255,0.15);
  color: white;
  outline: none;
}
input::placeholder, textarea::placeholder {
  color: #ddd;
}
button {
  background: #ffbfa8;
  color: #222;
  border: none;
  border-radius: 10px;
  padding: 10px 20px;
  cursor: pointer;
  transition: 0.2s;
  font-weight: 600;
}
button:hover {
  background: #ffc9b8;
}
.columns {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}
.column {
  flex: 1;
  min-width: 300px;
}
.task {
  background: rgba(255,255,255,0.1);
  border-radius: 10px;
  padding: 10px 15px;
  margin-bottom: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.task.done {
  opacity: 0.6;
  text-decoration: line-through;
}
.checkbox {
  accent-color: #ffbfa8;
}
.small {
  font-size: 13px;
  color: #ccc;
}
.logout {
  display: block;
  text-align: right;
  margin-bottom: 15px;
}
a.logout-btn {
  color: #ffbfa8;
  text-decoration: none;
}
.notifications {
  margin-bottom: 30px;
  padding: 10px;
  background: rgba(255,255,255,0.05);
  border-radius: 10px;
}
.notif {
  font-size: 14px;
  padding: 5px 0;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}
.notif:last-child {
  border-bottom: none;
}
.delete-btn {
  background: none;
  border: none;
  color: #ff7b7b;
  font-size: 18px;
  cursor: pointer;
}
.delete-btn:hover {
  color: #ffaaaa;
}
</style>
</head>
<body>
<div class="container">
  <div class="logout">
    <a href="index.php" class="logout-btn">🚪 Çıkış Yap</a>
  </div>

  <h2>Görev Paneli</h2>

  <!-- Bildirimler -->
  <div class="notifications">
    <h3>🔔 Bildirimler</h3>
    <?php if ($notifications->num_rows == 0): ?>
      <div class="notif">Henüz bildirimin yok.</div>
    <?php else: ?>
      <?php while ($n = $notifications->fetch_assoc()): ?>
        <div class="notif"><?= htmlspecialchars($n['message']) ?></div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>


  <form method="POST">
    <select name="receiver_id" required>
      <option value="">Görev Alıcısı</option>
      <?php while ($u = $users->fetch_assoc()): ?>
        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
      <?php endwhile; ?>
    </select>
    <input type="text" name="title" placeholder="Görev Başlığı" required>
    <textarea name="description" placeholder="Görev Açıklaması" rows="1"></textarea>
    <button type="submit">📤 Görev Gönder</button>
  </form>

  <div class="columns">
    <div class="column">
      <h3>📥 Bana Gelen Görevler</h3>
      <?php while ($t = $incoming->fetch_assoc()): ?>
        <div class="task <?= $t['status'] == 'done' ? 'done' : '' ?>" data-id="<?= $t['id'] ?>">
          <div>
            <strong><?= htmlspecialchars($t['title']) ?></strong><br>
            <span class="small">Gönderen: <?= htmlspecialchars($t['sender']) ?></span>
          </div>
          <div>
            <input type="checkbox" class="checkbox" data-id="<?= $t['id'] ?>" <?= $t['status'] == 'done' ? 'checked' : '' ?>>
            <button class="delete-btn" data-id="<?= $t['id'] ?>">🗑️</button>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <div class="column">
      <h3>📤 Benim Verdiğim Görevler</h3>
      <?php while ($t = $outgoing->fetch_assoc()): ?>
        <div class="task <?= $t['status'] == 'done' ? 'done' : '' ?>" data-id="<?= $t['id'] ?>">
          <div>
            <strong><?= htmlspecialchars($t['title']) ?></strong><br>
            <span class="small">Alıcı: <?= htmlspecialchars($t['receiver']) ?></span>
            <?= $t['status'] == 'done' ? '<br><span style="color:#7CFC00;">✔ Yapıldı</span>' : '' ?>
          </div>
          <button class="delete-btn" data-id="<?= $t['id'] ?>">🗑️</button>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.checkbox').forEach(cb => {
  cb.addEventListener('change', e => {
    const id = e.target.dataset.id;
    const done = e.target.checked ? 1 : 0;
    fetch('complete_task.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'id=' + id + '&done=' + done
    }).then(() => location.reload());
  });
});

//Görev sil
document.querySelectorAll('.delete-btn').forEach(btn => {
  btn.addEventListener('click', e => {
    const id = e.target.dataset.id;
    if (confirm("Bu görevi silmek istediğine emin misin?")) {
      fetch('delete_task.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
      }).then(() => location.reload());
    }
  });
});
</script>
</body>
</html>
