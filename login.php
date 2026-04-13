<?php
// login.php
session_start();
require 'config.php';
require_once 'logger.php';

if (isset($_SESSION['user_id'])) { header("Location: home.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF error');

    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'];

    // Brute-force защита
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE ip_address=? AND event_type='login_fail' AND created_at > NOW() - INTERVAL 15 MINUTE");
    $stmt->execute([$ip]);
    $fail_count = $stmt->fetchColumn();

    if ($fail_count >= 5) {
        $error = '🔒 Слишком много попыток. Подождите 15 минут.';
    } elseif (empty($login) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (login=? OR email=?) AND is_active=1");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login']   = $user['login'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['units']   = $user['unit_system'] ?? 'metric';
            session_regenerate_id(true);
            logSecurityEvent($pdo, 'login_success', $user['id'], 'Успешный вход');
            header("Location: home.php"); exit;
        } else {
            $error = 'Неверный логин или пароль';
            logSecurityEvent($pdo, 'login_fail', null, "Попытка: $login");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход — WeatherApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#0a0f1e;font-family:'DM Sans',sans-serif;overflow:hidden}
.bg{position:fixed;inset:0;z-index:0}
.bg-orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.35}
.orb1{width:500px;height:500px;background:radial-gradient(#1a6fff,#0033cc);top:-100px;right:-100px;animation:drift 12s ease-in-out infinite alternate}
.orb2{width:350px;height:350px;background:radial-gradient(#00c6ff,#004080);bottom:-60px;left:-60px;animation:drift 16s ease-in-out infinite alternate-reverse}
@keyframes drift{from{transform:translate(0,0)}to{transform:translate(30px,25px)}}
.container{position:relative;z-index:1;width:100%;max-width:420px;padding:20px}
.card{background:rgba(255,255,255,0.05);backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,0.1);
  border-radius:24px;padding:40px;box-shadow:0 32px 64px rgba(0,0,0,.5)}
.logo{text-align:center;margin-bottom:32px}
.logo-icon{font-size:44px;display:block;margin-bottom:10px}
.logo h1{font-family:'Space Mono',monospace;font-size:22px;color:#fff;letter-spacing:2px}
.logo p{color:rgba(255,255,255,.4);font-size:13px;margin-top:5px}
.form-group{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:600;color:rgba(255,255,255,.5);
  text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
input{width:100%;padding:13px 16px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
  border-radius:12px;color:#fff;font-size:15px;font-family:'DM Sans',sans-serif;transition:.2s;outline:none}
input:focus{border-color:#3b82f6;background:rgba(59,130,246,.1)}
input::placeholder{color:rgba(255,255,255,.25)}
.btn{width:100%;padding:14px;border-radius:12px;border:none;font-size:15px;font-weight:600;
  cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(59,130,246,.4)}
.alert-error{padding:12px 16px;border-radius:12px;font-size:14px;margin-bottom:20px;
  background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.links{display:flex;justify-content:space-between;margin-top:20px}
.links a{color:rgba(255,255,255,.5);font-size:13px;text-decoration:none;transition:.2s}
.links a:hover{color:#60a5fa}
.forgot{text-align:right;margin-top:8px}
.forgot a{color:rgba(255,255,255,.35);font-size:12px;text-decoration:none}
.forgot a:hover{color:#60a5fa}
</style>
</head>
<body>
<div class="bg">
  <div class="bg-orb orb1"></div>
  <div class="bg-orb orb2"></div>
</div>
<div class="container">
  <div class="card">
    <div class="logo">
      <span class="logo-icon">🌤️</span>
      <h1>WeatherApp</h1>
      <p>Войдите в свой аккаунт</p>
    </div>

    <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="form-group">
        <label>Логин или Email</label>
        <input type="text" name="login" placeholder="username или email" required value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Пароль</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <div class="forgot"><a href="forgot_password.php">Забыли пароль?</a></div>
      <br>
      <button type="submit" class="btn btn-primary">Войти →</button>
    </form>

    <div class="links">
      <a href="home.php?guest=1">Войти без аккаунта</a>
      <a href="register.php">Регистрация →</a>
    </div>
  </div>
</div>
</body>
</html>
