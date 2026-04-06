<?php
require_once 'auth_check.php';
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: home.php"); exit;
}

// 1. Удаление города
if (isset($_GET['delete_city_id'])) {
    $stmt = $pdo->prepare("DELETE FROM weather_cities WHERE id = ?");
    $stmt->execute([$_GET['delete_city_id']]);
    header("Location: admin.php?msg=city_deleted"); exit;
}

// 2. Очистка логов (по желанию)
if (isset($_POST['clear_logs'])) {
    $pdo->exec("DELETE FROM security_logs");
    header("Location: admin.php?msg=logs_cleared"); exit;
}

// 3. Обновление параметров погоды
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_weather_settings'])) {
    $pdo->exec("UPDATE weather_display_settings SET is_enabled = 0");
    if (!empty($_POST['params'])) {
        $stmt = $pdo->prepare("UPDATE weather_display_settings SET is_enabled = 1 WHERE param_key = ?");
        foreach ($_POST['params'] as $p) { $stmt->execute([$p]); }
    }
    header("Location: admin.php?success=1"); exit;
}

// Получаем данные
$logs = $pdo->query("SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();
$cities = $pdo->query("SELECT c.*, u.login FROM weather_cities c LEFT JOIN users u ON c.added_by = u.id ORDER BY c.created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель | Полный контроль</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .event-login_fail { background-color: #f8d7da; }
        .event-suspicious_activity { background-color: #fff3cd; }
        .table-fixed-height { max-height: 400px; overflow-y: auto; }
        .badge-system { font-size: 0.7rem; text-transform: uppercase; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">🛡 Панель администратора</span>
        <a href="home.php" class="btn btn-outline-info btn-sm">На главную</a>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row">

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning fw-bold">⚙️ Доступные параметры</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                        <?php
                        $stmtS = $pdo->query("SELECT * FROM weather_display_settings");
                        foreach ($stmtS->fetchAll() as $s): ?>
                            <div class="col-6 mb-1">
                                <div class="form-check form-switch small">
                                    <input class="form-check-input" type="checkbox" name="params[]" value="<?= $s['param_key'] ?>" <?= $s['is_enabled'] ? 'checked' : '' ?>>
                                    <label class="small"><?= $s['param_key'] ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <button name="update_weather_settings" class="btn btn-dark btn-sm w-100 mt-3">Сохранить доступ</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">🏙 Активные города</div>
                <div class="table-fixed-height">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>Город</th>
                                <th>Кто</th>
                                <th>Удалить</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cities as $c): ?>
                            <tr>
                                <td class="small fw-bold"><?= htmlspecialchars($c['city_name']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($c['login'] ?? 'System') ?></td>
                                <td><a href="admin.php?delete_city_id=<?= $c['id'] ?>" class="btn btn-link btn-sm text-danger p-0">🗑</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-danger">📋 Логи безопасности (Последние 50)</h5>
                    <form method="POST" onsubmit="return confirm('Вы уверены, что хотите очистить ВСЕ логи?');">
                        <button name="clear_logs" class="btn btn-outline-danger btn-sm">Очистить журнал</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Дата / Время</th>
                                <th>Тип события</th>
                                <th>User ID</th>
                                <th>IP-адрес</th>
                                <th>Детали</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Журнал пуст</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr class="event-<?= htmlspecialchars($log['event_type']) ?>">
                                    <td class="text-nowrap text-muted"><?= $log['created_at'] ?></td>
                                    <td>
                                        <span class="badge badge-system bg-<?php
                                            echo match($log['event_type']) {
                                                'login_fail' => 'danger',
                                                'login_success' => 'success',
                                                'suspicious_activity' => 'warning text-dark',
                                                default => 'primary'
                                            };
                                        ?>">
                                            <?= htmlspecialchars($log['event_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $log['user_id'] ? "ID: ".$log['user_id'] : '<span class="text-muted">—</span>' ?></td>
                                    <td><code><?= htmlspecialchars($log['ip_address']) ?></code></td>
                                    <td class="small"><?= htmlspecialchars($log['details']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>