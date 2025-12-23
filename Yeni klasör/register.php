<?php
session_start();

/* DATABASE CONFIG - düzenle */
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "login_system";

/* bağlan */
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("DB bağlantı hatası: " . $conn->connect_error);
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || $password === '' || $password2 === '') {
        $error = "Lütfen tüm alanları doldurunuz.";
    } elseif ($password !== $password2) {
        $error = "Şifreler eşleşmiyor.";
    } else {
        // kullanıcı var mı kontrol
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s",$username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $error = "Bu kullanıcı adı zaten alınmış.";
            $stmt->close();
        } else {
            $stmt->close();
            // kayıt
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, NOW())");
            $ins->bind_param("ss", $username, $hash);
            if ($ins->execute()) {
                $success = "Kayıt başarılı. Yönlendiriliyorsunuz...";
                header("Refresh:1; url=index.php");
            } else {
                $error = "Kayıt sırasında hata: " . $conn->error;
            }
            $ins->close();
        }
    }
}

$conn->close();
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <title>Create Account</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="blob b1"></div>
  <div class="blob b2"></div>
  <div class="blob b3"></div>
  <div class="particles" id="particles"></div>

  <div class="container">
    <div class="card">
      <div class="brand">
        <h1>GörevApp</h1>
        <p>Görev Uygulaması</p>
      </div>

      <form class="form" method="post" novalidate>
        <div class="input"><input type="text" name="username" placeholder="Kullanıcı Adı" required></div>
        <div class="input"><input type="password" name="password" placeholder="Şifre" required></div>
        <div class="input"><input type="password" name="password2" placeholder="Tekrar Şifre" required></div>

        <button class="btn" type="submit">Hesabımı Oluştur</button>

        <div class="divider">— Zaten Hesabınız Var Mı —</div>
        <a class="register" href="index.php">Giriş Yap</a>

        <?php if($error): ?>
          <p class="small" style="color:#ff9d9d;margin-top:12px;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if($success): ?>
          <p class="small" style="color:#bfffc8;margin-top:12px;"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <script>
    /* same particle generation as login */
    (function(){
      const container = document.getElementById('particles');
      const w = window.innerWidth, h = window.innerHeight;
      const count = Math.round(Math.min(120, (w*h)/80000));
      for(let i=0;i<count;i++){
        const p = document.createElement('div');
        p.className='particle';
        p.style.left = Math.random()*100 + '%';
        p.style.top = Math.random()*100 + '%';
        p.style.width = (1 + Math.random()*3)+'px';
        p.style.height = p.style.width;
        p.style.opacity = 0.08 + Math.random()*0.6;
        p.style.animationDelay = (Math.random()*6)+'s';
        container.appendChild(p);
      }
    })();
  </script>
</body>
</html>
