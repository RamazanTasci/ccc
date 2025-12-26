<?php
session_start();

/* DATABASE CONFIG */
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "login_system";

/* Bağlantı */
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Veritabanı bağlantı hatası: " . $conn->connect_error);
}

/* Hangi sekmede olduğumuzu belirle (login / register) */
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

/* Kayıt işlemi */

if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm']);

    if ($password !== $confirm) {
        $error = "Şifreler uyuşmuyor!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçerli bir e-posta adresi giriniz!";
    } elseif (strpos($email,"@hasem.com.tr")!==FALSE&&strpos($email,"@eymer.com.tr")!==FALSE) { // Buradaki kontrol eklendi
        $error = "bu e-posta ile kayıt olunamaz!";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $password_hash);
        if ($stmt->execute()) {
            $success = "Kayıt başarılı! Giriş yapabilirsiniz.";
            $page = "login";
        } else {
            $error = "Bu kullanıcı adı veya e-posta zaten kayıtlı olabilir.";
        }
        $stmt->close();
    }
}


/* Giriş işlemi */
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE username=? OR email=?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hash);
        $stmt->fetch();
        if (password_verify($password, $hash)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Hatalı şifre!";
        }
    } else {
        $error = "Kullanıcı veya e-posta bulunamadı!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page == 'login' ? 'Giriş Yap' : 'Kayıt Ol'; ?></title>
<style>
/* Stil dosyası aynı şekilde korundu */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

:root{
  --accent:#ffbfa8;
  --neon:#7afcff;
  --glass-bg: rgba(255,255,255,0.06);
  --glass-border: rgba(255,255,255,0.14);
  --text: rgba(255,255,255,0.95);
}

*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  font-family: 'Poppins', sans-serif;
  color:var(--text);
  background: linear-gradient(120deg,#0f1022 0%, #35124c 35%, #862d6d 60%, #ff8a65 100%);
  overflow:hidden;
}

.blob{
  position: absolute;
  filter: blur(40px) saturate(120%);
  opacity: 0.55;
  mix-blend-mode: screen;
  border-radius: 50%;
  transform: translate3d(0,0,0);
  animation: float 12s ease-in-out infinite;
}
.blob.b1{width:520px;height:520px;left:-120px;top:-120px;background:linear-gradient(45deg,#7b2ff7,#ff6a88);}
.blob.b2{width:420px;height:420px;right:-80px;bottom:-40px;background:linear-gradient(45deg,#00c9ff,#92fe9d); animation-duration:14s;}
.blob.b3{width:300px;height:300px;left:50%;top:10%;background:linear-gradient(45deg,#ffd8a9,#ff6a88);opacity:0.25; animation-duration:18s;}

@keyframes float{
  0%{ transform: translateY(0) scale(1) }
  50%{ transform: translateY(-30px) scale(1.03) }
  100%{ transform: translateY(0) scale(1) }
}

.container {
  position:relative;
  width:100%;
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:40px;
  z-index:2;
}

.card {
  width:420px;
  max-width:92%;
  border-radius:16px;
  background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
  border: 1px solid var(--glass-border);
  box-shadow: 0 8px 30px rgba(2,6,23,0.6), inset 0 1px 0 rgba(255,255,255,0.03);
  backdrop-filter: blur(12px) saturate(140%);
  padding:36px;
  color:var(--text);
  position:relative;
  overflow:hidden;
}

.brand {text-align:center;margin-bottom:10px;}
.brand h1 {font-size:20px;letter-spacing:1px;margin-bottom:6px;}
.brand p {color: rgba(255,255,255,0.75);font-size:13px;}

.form {margin-top:18px;}
.input {margin-bottom:14px;position:relative;}
.input input{
  width:100%;
  padding:14px 18px;
  border-radius:999px;
  border: 1px solid rgba(255,255,255,0.06);
  background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
  color:var(--text);
  outline:none;
  font-size:15px;
  text-align:center;
  transition: box-shadow .25s, border-color .25s, transform .12s;
}
.input input::placeholder{ color: rgba(255,255,255,0.45); text-align:center; }
.input input:focus{
  box-shadow: 0 6px 24px rgba(122,252,255,0.06), 0 0 12px rgba(255,191,168,0.04);
  border-color: rgba(122,252,255,0.32);
  transform: translateY(-2px);
}

.btn {
  margin-top:10px;
  width:100%;
  display:inline-block;
  text-align:center;
  padding:13px 18px;
  border-radius:999px;
  background: linear-gradient(90deg,var(--accent), #ffd6c0);
  color:#222;
  font-weight:600;
  border:none;
  cursor:pointer;
  box-shadow: 0 8px 30px rgba(255,191,168,0.12), 0 1px 0 rgba(255,255,255,0.2) inset;
  transition: transform .15s ease, box-shadow .15s ease;
}
.btn:hover{ transform: translateY(-4px); box-shadow: 0 18px 50px rgba(255,191,168,0.16);}
.divider {margin:18px 0; text-align:center; font-size:13px; color: rgba(255,255,255,0.6);}
.register {
  display:block;
  margin:0 auto;
  padding:10px 28px;
  border-radius:999px;
  border: 1px solid rgba(255,255,255,0.08);
  text-decoration:none;
  color:var(--text);
  background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent);
  box-shadow: 0 6px 18px rgba(122,252,255,0.03);
  transition: all .18s ease;
  font-weight:600;
}
.register:hover{
  box-shadow: 0 20px 60px rgba(122,252,255,0.08);
  transform: translateY(-6px) scale(1.02);
}
.small{margin-top:12px;font-size:13px;color:rgba(255,255,255,0.65);text-align:center}
</style>
</head>
<body>
<div class="blob b1"></div>
<div class="blob b2"></div>
<div class="blob b3"></div>
<div class="container">
  <div class="card">
    <div class="brand">
      <h1><?php echo $page == 'login' ? 'Giriş Yap' : 'Kayıt Ol'; ?></h1>
      <p>Hoş geldiniz!</p>
    </div>

    <?php if (isset($error)): ?>
      <p style="color:#ff8080; text-align:center; margin-bottom:10px;"><?php echo $error; ?></p>
    <?php elseif (isset($success)): ?>
      <p style="color:#7afcff; text-align:center; margin-bottom:10px;"><?php echo $success; ?></p>
    <?php endif; ?>

    <form method="post" class="form">
      <div class="input">
        <input type="text" name="username" placeholder="Ad Soyad" required>
      </div>

      <?php if ($page == 'register'): ?>
      <div class="input">
        <input type="email" name="email" placeholder="E-posta adresi" required>
      </div>
      <?php endif; ?>

      <div class="input">
        <input type="password" name="password" placeholder="Şifre" required>
      </div>

      <?php if ($page == 'register'): ?>
      <div class="input">
        <input type="password" name="confirm" placeholder="Şifreyi tekrar" required>
      </div>
      <?php endif; ?>

      <button type="submit" name="<?php echo $page; ?>" class="btn">
        <?php echo $page == 'login' ? 'Giriş Yap' : 'Kayıt Ol'; ?>
      </button>
    </form>

    <div class="divider">
      <?php if ($page == 'login'): ?>
        <p>Hesabın yok mu?</p>
        <a href="?page=register" class="register">Kayıt Ol</a>
      <?php else: ?>
        <p>Zaten hesabın var mı?</p>
        <a href="?page=login" class="register">Giriş Yap</a>
      <?php endif; ?>
    </div>

    <p class="small">&copy; <?php echo date("Y"); ?> Görev App</p>
  </div>
</div>
</body>
</html>
