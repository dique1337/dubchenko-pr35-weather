<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $code = trim($_POST['code']);

    $stmt = $pdo->prepare("SELECT id, confirm_code FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Пользователь не найден";
    } elseif ($user['confirm_code'] !== $code) {
        $error = "Неверный код";
    } else {
        // Активируем аккаунт
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1, confirm_code = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        $success = "✅ Аккаунт подтверждён! Теперь можно войти.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подтверждение | Weather</title>
    <link rel="stylesheet" href="style.css?v=<?=time()?>">
</head>
<body>

<div class="auth-container">
    <h2>☀ Подтверждение аккаунта</h2>

    <?php 
    if (!empty($error)) echo "<div class='error'>$error</div>"; 
    if (!empty($success)) echo "<div class='success'>$success</div>";
    ?>

    <?php if (empty($success)) : ?>
        <form method="POST">
            <input type="hidden" name="login" value="<?=htmlspecialchars($login ?? '')?>">
            <input type="text" name="code" placeholder="Введите код подтверждения" required>
            <button type="submit">Подтвердить аккаунт</button>
        </form>
    <?php else: ?>
        <a href="login.php">Войти на сайт</a>
    <?php endif; ?>
</div>

</body>
</html>