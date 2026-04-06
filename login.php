<?php
session_start();
require 'config.php'; // Подключаем $pdo
require_once 'logger.php'; // Подключаем логгер для записи событий

$error = '';

// --- ГЕНЕРАЦИЯ CSRF-ТОКЕНА ---
// Создаем уникальный ключ сессии для защиты от CSRF атак
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Если пользователь уже залогинен — перенаправляем
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // --- ЗАЩИТА ОТ BRUTE-FORCE ---
    // Проверяем количество неудачных попыток с этого IP за последние 15 минут
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE ip_address = ? AND event_type = 'login_fail' AND created_at > NOW() - INTERVAL 15 MINUTE");
    $stmtCheck->execute([$ip]);
    $fail_count = $stmtCheck->fetchColumn();

    if ($fail_count >= 5) {
        $error = 'Слишком много неудачных попыток. Подождите 15 минут.';
    } elseif (empty($login) || empty($password)) {
        $error = 'Заполните все поля!';
    } else {
        // Ищем пользователя по логину
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? AND is_active = 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // --- СОХРАНЕНИЕ ДАННЫХ В СЕССИЮ ---
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $user['login'];

            // Добавляем роль в сессию для прав доступа
            $_SESSION['role'] = $user['role'] ?? 'user';

            // Логируем успешный вход
            logSecurityEvent($pdo, 'login_success', $user['id'], "Успешный вход в систему");

            header("Location: home.php");
            exit;
        } else {
            $error = "Неверный логин или пароль";

            // Логируем неудачную попытку
            logSecurityEvent($pdo, 'login_fail', null, "Попытка входа с логином: " . $login);
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