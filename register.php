<?php
// register.php
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) { header("Location: home.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$show_code_input = false;
$login_for_code  = '';
$confirm_code_display = '';
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ШАГ 2: подтверждение кода
    if (isset($_POST['code_submit'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF error');
        $login = trim($_POST['login']);
        $code  = trim($_POST['code']);
        $stmt  = $pdo->prepare("SELECT id, confirm_code FROM users WHERE login = ? AND is_active = 0");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        if (!$user) {
            $error = 'Пользователь не найден или уже активирован';
        } elseif ($user['confirm_code'] !== $code) {
            $error = 'Неверный код подтверждения';
            $show_code_input = true;
            $login_for_code  = $login;
        } else {
            $pdo->prepare("UPDATE users SET is_active=1, confirm_code=NULL, updated_at=NOW() WHERE id=?")->execute([$user['id']]);
            $success = '✅ Аккаунт подтверждён! Теперь можно войти.';
        }
    } else {
        // ШАГ 1: регистрация
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF error');
        $login    = trim($_POST['login'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        $email_parts = explode('@', $email);
        $email_name  = $email_parts[0] ?? '';

        if (mb_strlen($login) < 5) {
            $error = 'Логин должен быть не менее 5 символов';
        } elseif (mb_strlen($email_name) < 5 || substr($email, -10) !== '@gmail.com') {
            $error = 'Email должен быть вида name@gmail.com (мин. 5 символов до @)';
        } elseif (strlen($password) < 8) {
            $error = 'Пароль минимум 8 символов';
        } elseif ($password !== $confirm) {
            $error = 'Пароли не совпадают';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE login=? OR email=?");
            $stmt->execute([$login, $email]);
            if ($stmt->fetch()) {
                $error = 'Логин или email уже используются';
            } else {
                $hash  = password_hash($password, PASSWORD_DEFAULT);
                $code  = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO users (login,email,password_hash,is_active,confirm_code,created_at,updated_at) VALUES(?,?,?,0,?,NOW(),NOW())")
                    ->execute([$login, $email, $hash, $code]);
                $confirm_code_display = $code;
                $show_code_input      = true;
                $login_for_code       = $login;
                $success = 'Регистрация прошла успешно! Ваш код подтверждения:';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Регистрация — WeatherApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#0a0f1e;font-family:'DM Sans',sans-serif;overflow:hidden}
.bg{position:fixed;inset:0;z-index:0}
.bg-orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.35}
.orb1{width:500px;height:500px;background:radial-gradient(#1a6fff,#0033cc);top:-100px;left:-100px;animation:drift 12s ease-in-out infinite alternate}
.orb2{width:400px;height:400px;background:radial-gradient(#00c6ff,#004080);bottom:-80px;right:-80px;animation:drift 15s ease-in-out infinite alternate-reverse}
.orb3{width:300px;height:300px;background:radial-gradient(#7c3aed,#3b0764);top:40%;left:50%;transform:translate(-50%,-50%);animation:drift 18s ease-in-out infinite}
@keyframes drift{from{transform:translate(0,0)}to{transform:translate(40px,30px)}}
.container{position:relative;z-index:1;width:100%;max-width:440px;padding:20px}
.card{background:rgba(255,255,255,0.05);backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,0.1);
  border-radius:24px;padding:40px;box-shadow:0 32px 64px rgba(0,0,0,0.5)}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{font-size:40px;display:block;margin-bottom:8px}
.logo h1{font-family:'Space Mono',monospace;font-size:20px;color:#fff;letter-spacing:2px;text-transform:uppercase}
.logo p{color:rgba(255,255,255,0.4);font-size:13px;margin-top:4px}
.form-group{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:600;color:rgba(255,255,255,0.5);
  text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
input{width:100%;padding:13px 16px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);
  border-radius:12px;color:#fff;font-size:15px;font-family:'DM Sans',sans-serif;transition:.2s;outline:none}
input:focus{border-color:#3b82f6;background:rgba(59,130,246,0.1)}
input::placeholder{color:rgba(255,255,255,0.25)}
.btn{width:100%;padding:14px;border-radius:12px;border:none;font-size:15px;font-weight:600;
  cursor:pointer;transition:.2s;margin-top:4px;font-family:'DM Sans',sans-serif}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(59,130,246,.4)}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(16,185,129,.4)}
.alert{padding:12px 16px;border-radius:12px;font-size:14px;margin-bottom:20px}
.alert-error{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.alert-success{background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#6ee7b7}
.code-display{text-align:center;padding:20px;background:rgba(59,130,246,.15);border:2px dashed #3b82f6;
  border-radius:16px;margin:16px 0}
.code-display .code{font-family:'Space Mono',monospace;font-size:36px;font-weight:700;color:#60a5fa;letter-spacing:8px}
.code-display p{color:rgba(255,255,255,.5);font-size:12px;margin-top:8px}
.divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:rgba(255,255,255,.3);font-size:13px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.1)}
.link{display:block;text-align:center;color:rgba(255,255,255,.5);font-size:13px;text-decoration:none;transition:.2s}
.link:hover{color:#60a5fa}
</style>
</head>
<body>
<div class="bg">
  <div class="bg-orb orb1"></div>
  <div class="bg-orb orb2"></div>
  <div class="bg-orb orb3"></div>
</div>
<div class="container">
  <div class="card">
    <div class="logo">
      <span class="logo-icon">🌤️</span>
      <h1>WeatherApp</h1>
      <p>Создайте аккаунт</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success && !$confirm_code_display): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$show_code_input): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="form-group">
        <label>Логин (мин. 5 символов)</label>
        <input type="text" name="login" placeholder="username" minlength="5" required value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email (только @gmail.com)</label>
        <input type="email" name="email" placeholder="you@gmail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Пароль (мин. 8 символов)</label>
        <input type="password" name="password" placeholder="••••••••" minlength="8" required>
      </div>
      <div class="form-group">
        <label>Повторите пароль</label>
        <input type="password" name="confirm" placeholder="••••••••" minlength="8" required>
      </div>
      <button type="submit" class="btn btn-primary">Зарегистрироваться →</button>
    </form>
    <?php else: ?>
    <?php if ($confirm_code_display): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <div class="code-display">
        <div class="code"><?= htmlspecialchars($confirm_code_display) ?></div>
        <p>Скопируйте этот код и введите ниже</p>
      </div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="login" value="<?= htmlspecialchars($login_for_code) ?>">
      <div class="form-group">
        <label>Код подтверждения</label>
        <input type="text" name="code" placeholder="000000" maxlength="6" required autocomplete="off"
               style="text-align:center;font-size:24px;font-family:'Space Mono',monospace;letter-spacing:8px">
      </div>
      <button type="submit" name="code_submit" class="btn btn-success">✓ Подтвердить аккаунт</button>
    </form>
    <?php endif; ?>

    <div class="divider">или</div>
    <a href="login.php" class="link">Уже есть аккаунт? Войти →</a>
  </div>
</div>
</body>
</html>
