<?php
session_start();
require 'config.php'; // Подключаем $pdo

$error = '';

// Если пользователь уже залогинен — перенаправляем
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = 'Заполните все поля!'; // комментарий
    } else {
        // Ищем пользователя по логину
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? AND is_active = 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Сохраняем данные в сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $user['login'];

            header("Location: home.php");
            exit;
        } else {
            $error = "Неверный логин или пароль"; // ошибка
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">🌤 Вход</h2>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="login" class="form-label">Логин</label>
                            <input type="text" class="form-control" id="login"
                                   name="login" required
                                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password"
                                   name="password" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Войти</button>
                    </form>

                    <p class="text-center mt-3">
                        Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>