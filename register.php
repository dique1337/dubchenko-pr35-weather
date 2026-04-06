<?php
require 'config.php';

$show_code_input = false;
$login_for_code = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['code_submit'])) {
        $login = trim($_POST['login']);
        $code  = trim($_POST['code']);

        $stmt = $pdo->prepare("SELECT id, confirm_code FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Пользователь не найден";
        } elseif ($user['confirm_code'] !== $code) {
            $error = "Неверный код";
            $show_code_input = true;
            $login_for_code = $login;
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1, confirm_code = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            $success = "✅ Аккаунт подтверждён! Теперь можно войти.";
        }

    } else {
        $login    = trim($_POST['login']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm  = $_POST['confirm'];

        // --- ДОБАВЛЕНА ПРОВЕРКА ЛОГИНА ---
        if (mb_strlen($login) < 5) {
            $error = "Логин должен быть не менее 5 символов";
        } elseif (substr($email, -10) !== '@gmail.com') {
            $error = "Email должен оканчиваться на @gmail.com";
        } elseif (strlen($password) < 6) {
            $error = "Пароль минимум 6 символов";
        } elseif ($password !== $confirm) {
            $error = "Пароли не совпадают";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ? OR email = ?");
            $stmt->execute([$login, $email]);

            if ($stmt->fetch()) {
                $error = "Пользователь с таким логином или email уже существует";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $confirm_code  = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (login, email, password_hash, is_active, confirm_code, created_at, updated_at)
                    VALUES (?, ?, ?, 0, ?, NOW(), NOW())
                ");
                $stmt->execute([$login, $email, $password_hash, $confirm_code]);

                $success = "Регистрация прошла! Введите код подтверждения.";
                $show_code_input = true;
                $login_for_code = $login;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация | Weather</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <span class="badge bg-primary rounded-circle p-3" style="font-size:2rem">👤</span>
                        <h3 class="mt-3">Создайте аккаунт</h3>
                        <p class="text-muted">Заполните форму для регистрации</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <?php if (!$show_code_input): ?>
                        <form method="POST">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="login" name="login"
                                       placeholder="Логин" required minlength="5" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
                                <label for="login">👤 Логин (минимум 5 символов)</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <label for="email">📧 Email адрес</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="Пароль" required minlength="6">
                                <label for="password">🔒 Пароль</label>
                            </div>
                            <div class="form-floating mb-4">
                                <input type="password" class="form-control" id="confirm" name="confirm"
                                       placeholder="Повтор пароля" required minlength="6">
                                <label for="confirm">🔒 Повторите пароль</label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">Зарегистрироваться</button>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="login" value="<?= htmlspecialchars($login_for_code) ?>">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="code" name="code" placeholder="Код" required>
                                <label for="code">Введите код подтверждения</label>
                            </div>
                            <button type="submit" name="code_submit" class="btn btn-success btn-lg w-100">
                                Подтвердить аккаунт
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1">
                        <span class="mx-2 text-muted">или</span>
                        <hr class="flex-grow-1">
                    </div>
                    <a href="login.php" class="btn btn-outline-secondary w-100">Войти в существующий аккаунт</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>