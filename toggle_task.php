<?php
require 'db.php';

$id = (int)($_POST['id'] ?? 0);
$completed = (int)($_POST['completed'] ?? 0);

if ($id > 0) {
  if ($completed === 1) {
    $stmt = $db->prepare("UPDATE tasks SET status='completed' WHERE id=?");
  } else {
    $stmt = $db->prepare("UPDATE tasks SET status='pending' WHERE id=?");
  }
  $stmt->execute([$id]);
}
