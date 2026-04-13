<?php
require_once 'auth_check.php';
require_once 'config.php';
require_once 'logger.php';

$uid = $_SESSION['user_id'];
$msg = $err = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logSecurityEvent($pdo,'suspicious_activity',$uid,'CSRF на профиле'); die('CSRF error');
    }

    // Смена имени
    if (isset($_POST['update_name'])) {
        $name = trim($_POST['login']);
        if (mb_strlen($name) < 3) {
            $err = 'Имя минимум 3 символа';
        } else {
            $pdo->prepare("UPDATE users SET login=?,updated_at=NOW() WHERE id=?")->execute([$name,$uid]);
            $_SESSION['login'] = $name;
            $user['login'] = $name;
            $msg = 'Имя обновлено';
            logSecurityEvent($pdo,'profile_update',$uid,"Имя изменено на: $name");
        }
    }

    // Загрузка аватара
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $maxSize = 2 * 1024 * 1024;
        if (!in_array($ext, $allowed)) {
            $err = 'Допустимые форматы: jpg, png, gif, webp';
        } elseif ($_FILES['avatar']['size'] > $maxSize) {
            $err = 'Файл слишком большой (макс. 2 МБ)';
        } else {
            $dir = 'uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $newName = 'avatar_'.$uid.'_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir.$newName)) {
                if ($user['avatar'] !== 'default_avatar.png' && file_exists($dir.$user['avatar'])) {
                    @unlink($dir.$user['avatar']);
                }
                $pdo->prepare("UPDATE users SET avatar=?,updated_at=NOW() WHERE id=?")->execute([$newName,$uid]);
                $user['avatar'] = $newName;
                $msg = 'Аватар обновлён';
            } else { $err = 'Ошибка загрузки файла'; }
        }
    }

    // Смена пароля
    if (isset($_POST['change_password'])) {
        $old = $_POST['old_password'];
        $new = $_POST['new_password'];
        $cnf = $_POST['confirm_password'];
        if (!password_verify($old, $user['password_hash'])) {
            $err = 'Старый пароль неверен';
            logSecurityEvent($pdo,'suspicious_activity',$uid,'Неудачная смена пароля');
        } elseif (strlen($new) < 8) {
            $err = 'Новый пароль минимум 8 символов';
        } elseif ($new !== $cnf) {
            $err = 'Пароли не совпадают';
        } else {
            $pdo->prepare("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            $msg = 'Пароль изменён';
            logSecurityEvent($pdo,'password_change',$uid,'Смена пароля');
        }
    }

    // Смена единиц
    if (isset($_POST['toggle_units_profile'])) {
        $nu = $user['unit_system'] === 'metric' ? 'imperial' : 'metric';
        $pdo->prepare("UPDATE users SET unit_system=? WHERE id=?")->execute([$nu,$uid]);
        $_SESSION['units'] = $nu;
        $user['unit_system'] = $nu;
        $msg = 'Единицы измерения изменены';
    }

    // Удаление аккаунта
    if (isset($_POST['delete_account'])) {
        $pass = $_POST['confirm_delete_pass'];
        if (!password_verify($pass, $user['password_hash'])) {
            $err = 'Неверный пароль';
        } else {
            if ($user['avatar'] !== 'default_avatar.png') @unlink('uploads/avatars/'.$user['avatar']);
            logSecurityEvent($pdo,'account_delete',$uid,'Аккаунт удалён');
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            session_destroy();
            header("Location: register.php"); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Профиль — WeatherApp</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0d1117;color:#e6edf3;font-family:'DM Sans',sans-serif;min-height:100vh}
.navbar{background:rgba(13,17,23,.9);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.08);
  padding:12px 24px;display:flex;align-items:center;justify-content:space-between}
.nav-brand{font-family:'Space Mono',monospace;font-size:15px;color:#fff;text-decoration:none}
.nav-brand span{color:#3b82f6}
.btn-nav{padding:7px 16px;border-radius:10px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);
  color:#e6edf3;font-size:13px;cursor:pointer;text-decoration:none;font-family:'DM Sans',sans-serif}
.container{max-width:900px;margin:40px auto;padding:0 20px}
.grid{display:grid;grid-template-columns:280px 1fr;gap:24px}
@media(max-width:700px){.grid{grid-template-columns:1fr}}
.card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:16px;overflow:hidden;margin-bottom:16px}
.card-header{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);font-size:12px;
  font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.4)}
.card-body{padding:20px}
.avatar-wrap{text-align:center;padding:24px}
.avatar-img{width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #3b82f6;margin-bottom:12px}
.avatar-name{font-size:18px;font-weight:600;margin-bottom:4px}
.avatar-email{font-size:13px;color:rgba(255,255,255,.4)}
.avatar-role{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
  margin-top:8px;text-transform:uppercase;letter-spacing:.5px;
  background:rgba(59,130,246,.2);color:#60a5fa}
label{display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
input[type=text],input[type=password],input[type=email],input[type=file]{
  width:100%;padding:12px 14px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
  border-radius:10px;color:#e6edf3;font-size:14px;font-family:'DM Sans',sans-serif;outline:none;margin-bottom:14px;transition:.2s}
input:focus{border-color:#3b82f6;background:rgba(59,130,246,.08)}
input::placeholder{color:rgba(255,255,255,.2)}
.btn{padding:11px 20px;border-radius:10px;border:none;font-size:14px;font-weight:500;
  cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s}
.btn-primary{background:#3b82f6;color:#fff}
.btn-primary:hover{background:#2563eb}
.btn-danger{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.btn-danger:hover{background:rgba(239,68,68,.25)}
.btn-outline{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:#e6edf3}
.btn-outline:hover{border-color:#3b82f6;color:#60a5fa}
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert-s{background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#6ee7b7}
.alert-e{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.danger-zone{border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:16px}
.danger-title{font-size:13px;font-weight:600;color:#fca5a5;margin-bottom:8px}
.danger-desc{font-size:12px;color:rgba(255,255,255,.4);margin-bottom:12px}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal{background:#161b22;border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:24px;width:100%;max-width:380px}
.modal h3{font-size:16px;margin-bottom:16px;color:#fca5a5}
.modal p{font-size:13px;color:rgba(255,255,255,.5);margin-bottom:14px}
</style>
</head>
<body>
<nav class="navbar">
  <a href="home.php" class="nav-brand">WEATHER<span>APP</span></a>
  <a href="home.php" class="btn-nav">← На главную</a>
</nav>

<div class="container">
  <?php if ($msg): ?><div class="alert alert-s">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-e">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="grid">
    <!-- ЛЕВАЯ: АВАТАР -->
    <div>
      <div class="card">
        <div class="avatar-wrap">
          <img src="uploads/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default_avatar.png') ?>"
               class="avatar-img" alt="avatar" id="avatarPreview">
          <div class="avatar-name"><?= htmlspecialchars($user['login']) ?></div>
          <div class="avatar-email"><?= htmlspecialchars($user['email']) ?></div>
          <div class="avatar-role"><?= $user['role'] === 'admin' ? '👑 Администратор' : '👤 Пользователь' ?></div>
        </div>
        <div style="padding:0 16px 16px">
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label>Сменить аватар (jpg/png, макс 2МБ)</label>
            <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)">
            <button class="btn btn-outline" style="width:100%">Загрузить фото</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">⚙️ Настройки</div>
        <div class="card-body">
          <p style="font-size:13px;color:rgba(255,255,255,.5);margin-bottom:12px">
            Единицы: <strong><?= $user['unit_system']==='metric'?'°C, м/с':'°F, mph' ?></strong>
          </p>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button name="toggle_units_profile" class="btn btn-outline" style="width:100%">
              Переключить на <?= $user['unit_system']==='metric'?'°F':'°C' ?>
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- ПРАВАЯ: ФОРМЫ -->
    <div>
      <div class="card">
        <div class="card-header">✏️ Редактировать профиль</div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label>Логин / Имя</label>
            <input type="text" name="login" value="<?= htmlspecialchars($user['login']) ?>" minlength="3" required>
            <button name="update_name" class="btn btn-primary">Сохранить имя</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">🔒 Смена пароля</div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label>Текущий пароль</label>
            <input type="password" name="old_password" placeholder="••••••••" required>
            <label>Новый пароль (мин. 8 символов)</label>
            <input type="password" name="new_password" placeholder="••••••••" minlength="8" required>
            <label>Повторите новый пароль</label>
            <input type="password" name="confirm_password" placeholder="••••••••" minlength="8" required>
            <button name="change_password" class="btn btn-primary">Изменить пароль</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header" style="color:#fca5a5">⚠️ Опасная зона</div>
        <div class="card-body">
          <div class="danger-zone">
            <div class="danger-title">Удаление аккаунта</div>
            <div class="danger-desc">Это действие необратимо. Все данные будут удалены.</div>
            <button class="btn btn-danger" onclick="document.getElementById('deleteModal').classList.add('show')">
              Удалить аккаунт
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- МОДАЛКА УДАЛЕНИЯ -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>⚠️ Удаление аккаунта</h3>
    <p>Введите ваш пароль для подтверждения. Это действие нельзя отменить.</p>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <label>Пароль</label>
      <input type="password" name="confirm_delete_pass" placeholder="Ваш пароль" required>
      <div style="display:flex;gap:10px;margin-top:4px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('deleteModal').classList.remove('show')">Отмена</button>
        <button name="delete_account" class="btn btn-danger">Удалить навсегда</button>
      </div>
    </form>
  </div>
</div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
    reader.readAsDataURL(input.files[0]);
  }
}
document.getElementById('deleteModal').addEventListener('click', function(e){
  if (e.target === this) this.classList.remove('show');
});
</script>
</body>
</html>
