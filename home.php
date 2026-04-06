<?php
require_once 'auth_check.php';
require_once 'config.php';

$uid = $_SESSION['user_id'];
$city_msg = '';

// --- ОБРАБОТКА ДЕЙСТВИЙ (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. УДАЛЕНИЕ ИЗ ИЗБРАННОГО
    if (isset($_POST['delete_fav'])) {
        $fav_id = $_POST['fav_id'];
        $stmt = $pdo->prepare("DELETE FROM favorite_cities WHERE id = ? AND user_id = ?");
        $stmt->execute([$fav_id, $uid]);
        header("Location: home.php"); exit;
    }

    // 2. УДАЛЕНИЕ ОТЗЫВА
    if (isset($_POST['delete_review'])) {
        $rev_id = $_POST['rev_id'];
        if ($_SESSION['role'] === 'admin') {
            $pdo->prepare("DELETE FROM city_reviews WHERE id = ?")->execute([$rev_id]);
        } else {
            $pdo->prepare("DELETE FROM city_reviews WHERE id = ? AND user_id = ?")->execute([$rev_id, $uid]);
        }
        header("Location: home.php#reviews"); exit;
    }

    // 3. ДОБАВЛЕНИЕ ГОРОДА
    $city = isset($_POST['city_name']) ? trim($_POST['city_name']) : '';
    if (!empty($city)) {
        if (isset($_POST['add_city']) || isset($_POST['add_to_fav'])) {
            $check = $pdo->prepare("SELECT id FROM weather_cities WHERE city_name = ?");
            $check->execute([$city]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO weather_cities (city_name, added_by) VALUES (?, ?)")
                    ->execute([$city, $uid]);
            }
        }
        if (isset($_POST['add_to_fav'])) {
            $checkFav = $pdo->prepare("SELECT id FROM favorite_cities WHERE user_id = ? AND city_name = ?");
            $checkFav->execute([$uid, $city]);
            if (!$checkFav->fetch()) {
                $pdo->prepare("INSERT INTO favorite_cities (user_id, city_name) VALUES (?, ?)")
                    ->execute([$uid, $city]);
            }
        }
        header("Location: home.php?msg=success"); exit;
    }

    // 4. СОХРАНЕНИЕ НАСТРОЕК
    if (isset($_POST['save_user_settings'])) {
        $pdo->prepare("DELETE FROM user_weather_settings WHERE user_id = ?")->execute([$uid]);
        if (!empty($_POST['user_params'])) {
            $stmt = $pdo->prepare("INSERT INTO user_weather_settings (user_id, param_key) VALUES (?, ?)");
            foreach ($_POST['user_params'] as $p) { $stmt->execute([$uid, $p]); }
        }
        header("Location: home.php?msg=settings_saved"); exit;
    }

    // 5. ДОБАВЛЕНИЕ ОТЗЫВА
    if (isset($_POST['add_review'])) {
        $rev_city = trim($_POST['review_city']);
        $rev_text = trim($_POST['review_text']);
        if (!empty($rev_city) && !empty($rev_text)) {
            $pdo->prepare("INSERT INTO city_reviews (user_id, city_name, review_text) VALUES (?, ?, ?)")
                ->execute([$uid, $rev_city, $rev_text]);
        }
        header("Location: home.php#reviews"); exit;
    }
}

// Загрузка данных
$stmtUser = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
$stmtUser->execute([$uid]);
$user = $stmtUser->fetch();
$avatar = $user['avatar'] ?? 'default_avatar.png';

$user_enabled_params = $pdo->prepare("SELECT param_key FROM user_weather_settings WHERE user_id = ?");
$user_enabled_params->execute([$uid]);
$user_enabled_params = $user_enabled_params->fetchAll(PDO::FETCH_COLUMN);

$adminEnabled = $pdo->query("SELECT param_key FROM weather_display_settings WHERE is_enabled = 1")->fetchAll(PDO::FETCH_COLUMN);

$all_labels = [
    'temp' => 'Температура', 'feels_like' => 'Ощущается как', 'humidity' => 'Влажность',
    'pressure' => 'Давление', 'wind' => 'Скорость ветра', 'uv_index' => 'УФ-индекс',
    'precip' => 'Осадки', 'geomagnetic' => 'Геомагн. активность'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Прогноз погоды</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #e3eef5; font-family: Arial, sans-serif; color: #333; }
        .navbar-gis { background-color: #2e629e; border-bottom: 3px solid #ffcc00; }
        .card { border: none; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .weather-gradient { background: linear-gradient(180deg, #5ba3d9 0%, #3d7cb9 100%); color: white; padding: 30px; border-radius: 8px; }
        .btn-blue { background-color: #4d94ff; color: white; border: none; }
        .btn-blue:hover { background-color: #357ae8; color: white; }
        .param-item { border-bottom: 1px solid #f0f0f0; padding: 10px 0; display: flex; justify-content: space-between; font-size: 0.95rem; }
        .review-item { border-left: 3px solid #4d94ff; background: #fafafa; padding: 10px; margin-bottom: 10px; border-radius: 0 5px 5px 0; position: relative; }
        .del-btn { background: none; border: none; color: #dc3545; font-size: 0.8rem; cursor: pointer; padding: 0; }
        .del-btn:hover { text-decoration: underline; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-gis mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="home.php">Прогноз погоды</a>
        <div class="ms-auto d-flex align-items-center">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin.php" class="btn btn-warning btn-sm me-3 fw-bold">АДМИН</a>
            <?php endif; ?>
            <a href="profile.php" class="text-white text-decoration-none me-3 d-flex align-items-center small">
                <img src="uploads/avatars/<?= htmlspecialchars($avatar) ?>" width="28" height="28" class="rounded-circle me-2 border">
                <?= htmlspecialchars($_SESSION['login']) ?>
            </a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Выйти</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-lg-8">
            <div class="weather-gradient shadow-sm mb-4">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h2 class="mb-1">Мой мониторинг</h2>
                        <p class="mb-0 opacity-75">Персональные данные</p>
                    </div>
                    <div class="col-md-5 text-md-end"><div class="display-4 fw-bold">+21°C</div></div>
                </div>
                <div class="mt-4 bg-white text-dark rounded p-4">
                    <div class="row">
                        <?php
                        $has_params = false;
                        foreach ($all_labels as $key => $label):
                            if (in_array($key, $user_enabled_params)):
                                $has_params = true; ?>
                                <div class="col-md-6 param-item">
                                    <span class="text-muted"><?= $label ?></span>
                                    <span class="fw-bold">данные...</span>
                                </div>
                        <?php endif; endforeach;
                        if (!$has_params) echo "<p class='text-center text-muted'>Настройте виджет в боковой панели →</p>"; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm" id="reviews">
                <div class="card-header fw-bold">Отзывы о погоде</div>
                <div class="card-body">
                    <form method="POST" class="row g-2 mb-4 p-2 bg-light rounded">
                        <div class="col-md-4"><input type="text" name="review_city" class="form-control form-control-sm" placeholder="Город" required></div>
                        <div class="col-md-6"><input type="text" name="review_text" class="form-control form-control-sm" placeholder="Ваш отзыв..." required></div>
                        <div class="col-md-2"><button name="add_review" class="btn btn-blue btn-sm w-100 fw-bold">OK</button></div>
                    </form>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php
                        $reviews = $pdo->query("SELECT r.*, u.login FROM city_reviews r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
                        foreach ($reviews->fetchAll() as $rev): ?>
                            <div class="review-item">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold text-primary small"><?= htmlspecialchars($rev['city_name']) ?></span>
                                    <div>
                                        <span class="text-muted me-2" style="font-size: 0.7rem;"><?= $rev['created_at'] ?></span>
                                        <?php if ($rev['user_id'] == $uid || $_SESSION['role'] === 'admin'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Удалить отзыв?');">
                                                <input type="hidden" name="rev_id" value="<?= $rev['id'] ?>">
                                                <button name="delete_review" class="del-btn">🗑</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="small"><strong><?= htmlspecialchars($rev['login']) ?>:</strong> <?= htmlspecialchars($rev['review_text']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm p-3">
                <div class="sidebar-title small text-uppercase fw-bold text-muted mb-2">Поиск</div>
                <form method="POST" class="input-group">
                    <input type="text" name="city_name" class="form-control form-control-sm" placeholder="Город..." required>
                    <button name="add_city" class="btn btn-blue btn-sm">🔍</button>
                    <button name="add_to_fav" class="btn btn-warning btn-sm">⭐</button>
                </form>
            </div>

            <div class="card shadow-sm">
                <div class="card-header py-2 small fw-bold">Избранное</div>
                <div class="list-group list-group-flush">
                    <?php
                    $favs = $pdo->prepare("SELECT * FROM favorite_cities WHERE user_id = ? ORDER BY id DESC");
                    $favs->execute([$uid]);
                    $rows = $favs->fetchAll();
                    if (!$rows) echo "<div class='p-3 text-center small text-muted'>Пусто</div>";
                    foreach ($rows as $f): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span class="small fw-bold"><?= htmlspecialchars($f['city_name']) ?></span>
                            <form method="POST" onsubmit="return confirm('Удалить?');">
                                <input type="hidden" name="fav_id" value="<?= $f['id'] ?>">
                                <button name="delete_fav" class="del-btn" style="font-size: 1rem;">&times;</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header py-2 small fw-bold">Параметры</div>
                <div class="card-body">
                    <form method="POST">
                        <?php foreach ($all_labels as $key => $label): if (in_array($key, $adminEnabled)): ?>
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" name="user_params[]" value="<?= $key ?>"
                                       id="p_<?= $key ?>" <?= in_array($key, $user_enabled_params) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="p_<?= $key ?>"><?= $label ?></label>
                            </div>
                        <?php endif; endforeach; ?>
                        <button name="save_user_settings" class="btn btn-outline-primary btn-sm w-100 mt-3">Сохранить вид</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>