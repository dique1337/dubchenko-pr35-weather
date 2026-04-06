<?php
require_once 'auth_check.php';
require_once 'config.php';
require_once 'logger.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Получаем актуальные данные пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- ПРОВЕРКА CSRF-ТОКЕНА ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logSecurityEvent($pdo, 'suspicious_activity', $user_id, "Попытка CSRF-атаки на профиль");
        die("Ошибка безопасности: неверный токен запроса.");
    }

    // --- РЕДАКТИРОВАНИЕ ИМЕНИ ---
    if (isset($_POST['update_name'])) {
        $new_name = trim($_POST['login']);
        if (!empty($new_name)) {
            $update = $pdo->prepare("UPDATE users SET login = ? WHERE id = ?");
            $update->execute([$new_name, $user_id]);
            $_SESSION['login'] = $new_name;
            $message = "Имя успешно обновлено!";
            logSecurityEvent($pdo, 'suspicious_activity', $user_id, "Изменено имя на: $new_name");
        }
    }

    // --- СМЕНА АВАТАРА ---
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $uploadDir = 'uploads/avatars/';
            // Автоматическое создание папки, если её нет
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

            $newName = "avatar_" . $user_id . "_" . time() . "." . $ext;
            $path = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $path)) {
                // Удаляем старый файл, если это не дефолтная картинка
                if ($user['avatar'] !== 'default_avatar.png' && file_exists($uploadDir . $user['avatar'])) {
                    @unlink($uploadDir . $user['avatar']);
                }

                $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$newName, $user_id]);
                $user['avatar'] = $newName;
                $message = "Аватар успешно обновлен!";
            }
        } else {
            $error = "Недопустимый формат файла.";
        }
    }

    // --- СМЕНА ПАРОЛЯ ---
    if (isset($_POST['change_password'])) {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];

        if (password_verify($old_pass, $user['password_hash'])) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hashed, $user_id]);
            logSecurityEvent($pdo, 'password_change', $user_id, "Успешная смена пароля");
            $message = "Пароль изменен!";
        } else {
            $error = "Старый пароль неверен.";
            logSecurityEvent($pdo, 'suspicious_activity', $user_id, "Неудачная попытка смены пароля");
        }
    }

    // --- УДАЛЕНИЕ АККАУНТА ---
    if (isset($_POST['delete_account'])) {
        $confirm_pass = $_POST['confirm_delete_pass'];
        if (password_verify($confirm_pass, $user['password_hash'])) {
            // Удаляем файл аватара перед удалением из БД
            if ($user['avatar'] !== 'default_avatar.png') {
                @unlink('uploads/avatars/' . $user['avatar']);
            }
            logSecurityEvent($pdo, 'account_delete', $user_id, "Аккаунт полностью удален");
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            session_destroy();
            header("Location: register.php?deleted=1");
            exit;
        } else {
            $error = "Неверный пароль для удаления аккаунта.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мой профиль</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="home.php">⬅ Назад</a>
        <span class="navbar-text text-white">Настройки профиля</span>
    </div>
</nav>

<div class="container">
    <?php if ($message): ?> <div class="alert alert-success shadow-sm"><?= $message ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger shadow-sm"><?= $error ?></div> <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card text-center p-3 shadow-sm border-0">
                <img src="uploads/avatars/<?= htmlspecialchars($user['avatar'] ?: 'default_avatar.png') ?>" class="rounded-circle img-thumbnail mx-auto" style="width: 150px; height: 150px; object-fit: cover;">
                <h4 class="mt-3"><?= htmlspecialchars($user['login']) ?></h4>
                <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>

                <form method="POST" enctype="multipart/form-data" class="mt-2">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="file" name="avatar" class="form-control form-control-sm mb-2" required>
                    <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm">Сменить фото</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm p-4 border-0">
                <h5>Редактировать данные</h5>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Имя (Логин)</label>
                        <input type="text" name="login" class="form-control" value="<?= htmlspecialchars($user['login']) ?>">
                    </div>
                    <button type="submit" name="update_name" class="btn btn-dark shadow-sm">Сохранить имя</button>
                </form>

                <hr>

                <h5>Смена пароля</h5>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="password" name="old_password" class="form-control mb-2" placeholder="Старый пароль" required>
                    <input type="password" name="new_password" class="form-control mb-2" placeholder="Новый пароль" required>
                    <button type="submit" name="change_password" class="btn btn-warning shadow-sm">Обновить пароль</button>
                </form>

                <hr>

                <div class="bg-light p-3 border rounded">
                    <h5 class="text-danger">Опасная зона</h5>
                    <p class="small text-muted">После удаления аккаунта данные нельзя будет восстановить.</p>
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">Удалить аккаунт</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Вы уверены?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Введите ваш пароль для подтверждения удаления:</p>
                <input type="password" name="confirm_delete_pass" class="form-control" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" name="delete_account" class="btn btn-danger shadow-sm">Удалить навсегда</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>