<?php
// forgot_password.php
session_start();
require 'config.php';
require_once 'logger.php';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$step = 'request'; // request | code | newpass | done
$error = $success = '';
$login_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF error');

    // Шаг 1: запрос кода
    if (isset($_POST['request_reset'])) {
        $email = trim($_POST['email'] ?? '');
        $stmt  = $pdo->prepare("SELECT id FROM users WHERE email=? AND is_active=1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $error = 'Email не найден или аккаунт не активирован';
            $step = 'request';
        } else {
            $token = bin2hex(random_bytes(6)); // 12-char token используем как код
            $code  = strtoupper($token);
            $pdo->prepare("DELETE FROM password_resets WHERE user_id=?")->execute([$user['id']]);
            $pdo->prepare("INSERT INTO password_resets (user_id,token,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))")
                ->execute([$user['id'], $code]);
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_code']  = $code;
            $step = 'code';
            $success = "Код сброса пароля:";
        }
    }

    // Шаг 2: ввод кода
    if (isset($_POST['verify_code'])) {
        $code  = strtoupper(trim($_POST['code'] ?? ''));
        $email = $_SESSION['reset_email'] ?? '';
        $stmt  = $pdo->prepare("SELECT pr.id, pr.user_id FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE u.email=? AND pr.token=? AND pr.expires_at > NOW() AND pr.used=0");
        $stmt->execute([$email, $code]);
        $reset = $stmt->fetch();
        if (!$reset) {
            $error = 'Неверный или истёкший код';
            $step = 'code';
        } else {
            $_SESSION['reset_user_id'] = $reset['user_id'];
            $_SESSION['reset_id']      = $reset['id'];
            $step = 'newpass';
        }
    }

    // Шаг 3: новый пароль
    if (isset($_POST['set_password'])) {
        $pass    = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        if (strlen($pass) < 8) {
            $error = 'Пароль минимум 8 символов'; $step = 'newpass';
        } elseif ($pass !== $confirm) {
            $error = 'Пароли не совпадают'; $step = 'newpass';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $uid  = $_SESSION['reset_user_id'];
            $pdo->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?")->execute([$hash, $uid]);
            $pdo->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$_SESSION['reset_id']]);
            logSecurityEvent($pdo, 'password_reset', $uid, 'Пароль восстановлен');
            unset($_SESSION['reset_email'],$_SESSION['reset_code'],$_SESSION['reset_user_id'],$_SESSION['reset_id']);
            $step = 'done';
        }
    }
} elseif (isset($_SESSION['reset_user_id'])) {
    $step = 'newpass';
} elseif (isset($_SESSION['reset_email']) && isset($_SESSION['reset_code'])) {
    $step = 'code';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Восстановление пароля — WeatherApp</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#0a0f1e;font-family:'DM Sans',sans-serif}
.bg{position:fixed;inset:0;z-index:0}
.orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.3}
.o1{width:400px;height:400px;background:radial-gradient(#7c3aed,#3b0764);top:-80px;left:-80px}
.o2{width:350px;height:350px;background:radial-gradient(#1d4ed8,#0a0f1e);bottom:-60px;right:-60px}
.wrap{position:relative;z-index:1;width:100%;max-width:420px;padding:20px}
.card{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.1);
  border-radius:24px;padding:40px;box-shadow:0 32px 64px rgba(0,0,0,.5)}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{font-size:40px;display:block;margin-bottom:8px}
h1{font-family:'Space Mono',monospace;font-size:18px;color:#fff;text-align:center;margin-bottom:6px}
.sub{text-align:center;color:rgba(255,255,255,.4);font-size:13px;margin-bottom:24px}
label{display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px;margin-bottom:7px}
input{width:100%;padding:13px 16px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
  border-radius:12px;color:#fff;font-size:15px;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;margin-bottom:16px}
input:focus{border-color:#7c3aed;background:rgba(124,58,237,.1)}
input::placeholder{color:rgba(255,255,255,.25)}
.btn{width:100%;padding:13px;border-radius:12px;border:none;font-size:15px;font-weight:600;cursor:pointer;
  font-family:'DM Sans',sans-serif;transition:.2s}
.btn-v{background:linear-gradient(135deg,#7c3aed,#4c1d95);color:#fff}
.btn-v:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(124,58,237,.4)}
.alert-e{padding:12px 16px;border-radius:12px;font-size:14px;margin-bottom:16px;
  background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.alert-s{padding:12px 16px;border-radius:12px;font-size:14px;margin-bottom:16px;
  background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#6ee7b7}
.code-box{text-align:center;padding:20px;background:rgba(124,58,237,.15);border:2px dashed #7c3aed;
  border-radius:16px;margin-bottom:20px}
.code-box .code{font-family:'Space Mono',monospace;font-size:28px;font-weight:700;color:#c4b5fd;letter-spacing:4px}
.code-box p{color:rgba(255,255,255,.4);font-size:12px;margin-top:8px}
.steps{display:flex;justify-content:center;gap:8px;margin-bottom:24px}
.step{width:28px;height:4px;border-radius:2px;background:rgba(255,255,255,.15)}
.step.active{background:#7c3aed}
.step.done{background:#10b981}
.back{display:block;text-align:center;margin-top:20px;color:rgba(255,255,255,.4);font-size:13px;text-decoration:none}
.back:hover{color:#c4b5fd}
</style>
</head>
<body>
<div class="bg"><div class="orb o1"></div><div class="orb o2"></div></div>
<div class="wrap">
<div class="card">
  <div class="logo"><span class="logo-icon">🔐</span></div>
  <h1>Восстановление пароля</h1>

  <div class="steps">
    <div class="step <?= in_array($step,['request','code','newpass','done'])?'done':'' ?>"></div>
    <div class="step <?= in_array($step,['code','newpass','done'])?($step==='request'?'':'done'):'' ?>"></div>
    <div class="step <?= in_array($step,['newpass','done'])?'done':'' ?>"></div>
    <div class="step <?= $step==='done'?'done':'' ?>"></div>
  </div>

  <?php if ($error): ?><div class="alert-e">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if ($step === 'request'): ?>
    <div class="sub">Введите email — получите код сброса</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <label>Email</label>
      <input type="email" name="email" placeholder="you@gmail.com" required>
      <button class="btn btn-v" name="request_reset">Получить код →</button>
    </form>

  <?php elseif ($step === 'code'): ?>
    <?php if ($success): ?>
    <div class="alert-s"><?= $success ?></div>
    <div class="code-box">
      <div class="code"><?= htmlspecialchars($_SESSION['reset_code'] ?? '') ?></div>
      <p>Действует 30 минут. Скопируйте и введите ниже.</p>
    </div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <label>Код подтверждения</label>
      <input type="text" name="code" placeholder="XXXXXXXXXXXXXX" required
             style="text-align:center;letter-spacing:4px;font-family:'Space Mono',monospace">
      <button class="btn btn-v" name="verify_code">Подтвердить →</button>
    </form>

  <?php elseif ($step === 'newpass'): ?>
    <div class="sub">Придумайте новый пароль</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <label>Новый пароль (мин. 8 символов)</label>
      <input type="password" name="new_password" placeholder="••••••••" minlength="8" required>
      <label>Повторите пароль</label>
      <input type="password" name="confirm" placeholder="••••••••" minlength="8" required>
      <button class="btn btn-v" name="set_password">Сохранить пароль ✓</button>
    </form>

  <?php elseif ($step === 'done'): ?>
    <div class="alert-s" style="text-align:center;font-size:16px">✅ Пароль успешно изменён!</div>
    <a href="login.php" class="btn btn-v" style="text-decoration:none;display:block;text-align:center;padding:13px;margin-top:8px">Войти →</a>
  <?php endif; ?>

  <a href="login.php" class="back">← Назад к входу</a>
</div>
</div>
</body>
</html>
