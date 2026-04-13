<?php
// home.php
require_once 'auth_check.php';
require_once 'config.php';
require_once 'weather_api.php';

$uid        = $_SESSION['user_id'];
$units      = $_SESSION['units'] ?? 'metric';
$unitSign   = $units === 'imperial' ? '°F' : '°C';
$windUnit   = $units === 'imperial' ? 'mph' : 'м/с';

// POST-обработка
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF error');
    }

    // Добавить в избранное
    if (isset($_POST['add_to_fav'])) {
        $city = trim($_POST['city_name'] ?? '');
        $lat  = $_POST['lat'] ?? null;
        $lon  = $_POST['lon'] ?? null;
        if (!empty($city)) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM favorite_cities WHERE user_id=?");
            $cnt->execute([$uid]);
            if ($cnt->fetchColumn() < 10) {
                $geo = ($lat && $lon) ? ['lat'=>$lat,'lon'=>$lon,'name'=>$city] : geocodeCity($city);
                if ($geo) {
                    $pdo->prepare("INSERT INTO favorite_cities (user_id,city_name,lat,lon,sort_order) VALUES(?,?,?,?,0)
                                   ON DUPLICATE KEY UPDATE use_count=use_count+1")
                        ->execute([$uid, $geo['name'], $geo['lat'], $geo['lon']]);
                }
            }
        }
        // Редирект сохраняет текущий город
        $redirect = 'home.php?msg=fav_added';
        if (!empty($city)) $redirect .= '&city='.urlencode($city);
        if ($lat) $redirect .= '&lat='.$lat.'&lon='.$lon;
        header("Location: $redirect"); exit;
    }

    // Удалить из избранного
    if (isset($_POST['delete_fav'])) {
        $pdo->prepare("DELETE FROM favorite_cities WHERE id=? AND user_id=?")->execute([$_POST['fav_id'], $uid]);
        header("Location: home.php"); exit;
    }

    // Сохранить параметры отображения
    if (isset($_POST['save_user_settings'])) {
        $pdo->prepare("DELETE FROM user_weather_settings WHERE user_id=?")->execute([$uid]);
        if (!empty($_POST['user_params'])) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_weather_settings (user_id,param_key) VALUES(?,?)");
            foreach ($_POST['user_params'] as $p) $stmt->execute([$uid, $p]);
        }
        $redirect = 'home.php?msg=settings_saved';
        if (!empty($_GET['city'])) $redirect .= '&city='.urlencode($_GET['city']);
        if (!empty($_GET['lat']))  $redirect .= '&lat='.$_GET['lat'].'&lon='.$_GET['lon'];
        header("Location: $redirect"); exit;
    }

    // Добавить отзыв
    if (isset($_POST['add_review'])) {
        $rc = trim($_POST['review_city'] ?? '');
        $rt = trim($_POST['review_text'] ?? '');
        if ($rc && $rt) {
            $pdo->prepare("INSERT INTO city_reviews (user_id,city_name,review_text) VALUES(?,?,?)")
                ->execute([$uid, $rc, $rt]);
        }
        header("Location: home.php#reviews"); exit;
    }

    // Удалить отзыв
    if (isset($_POST['delete_review'])) {
        if ($_SESSION['role'] === 'admin') {
            $pdo->prepare("DELETE FROM city_reviews WHERE id=?")->execute([$_POST['rev_id']]);
        } else {
            $pdo->prepare("DELETE FROM city_reviews WHERE id=? AND user_id=?")->execute([$_POST['rev_id'], $uid]);
        }
        header("Location: home.php#reviews"); exit;
    }

    // Единицы измерения
    if (isset($_POST['toggle_units'])) {
        $newUnits = $units === 'metric' ? 'imperial' : 'metric';
        $pdo->prepare("UPDATE users SET unit_system=? WHERE id=?")->execute([$newUnits, $uid]);
        $_SESSION['units'] = $newUnits;
        $redirect = 'home.php';
        if (!empty($_POST['current_city'])) $redirect .= '?city='.urlencode($_POST['current_city']);
        if (!empty($_POST['current_lat']))  $redirect .= '&lat='.$_POST['current_lat'].'&lon='.$_POST['current_lon'];
        header("Location: $redirect"); exit;
    }
}

// Данные пользователя
$stmtU = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmtU->execute([$uid]);
$user   = $stmtU->fetch();
$avatar = $user['avatar'] ?? 'default_avatar.png';

// Избранные города
$favs = $pdo->prepare("SELECT * FROM favorite_cities WHERE user_id=? ORDER BY use_count DESC, sort_order ASC");
$favs->execute([$uid]);
$favRows = $favs->fetchAll();

// Настройки параметров пользователя
$userParamsStmt = $pdo->prepare("SELECT param_key FROM user_weather_settings WHERE user_id=?");
$userParamsStmt->execute([$uid]);
$userParams = $userParamsStmt->fetchAll(PDO::FETCH_COLUMN);

// Что разрешил админ
$adminEnabled = $pdo->query("SELECT param_key FROM weather_display_settings WHERE is_enabled=1")->fetchAll(PDO::FETCH_COLUMN);

// Все метки
$allLabels = [
    'feels_like' => ['Ощущается как',         '🤔'],
    'humidity'   => ['Влажность',             '💧'],
    'pressure'   => ['Давление',              '📊'],
    'wind'       => ['Скорость ветра',        '💨'],
    'uv_index'   => ['УФ-индекс',             '☀️'],
    'precip'     => ['Осадки',                '🌧️'],
    'visibility' => ['Видимость',             '👁️'],
    'sunrise'    => ['Восход / Закат',        '🌅'],
    'aqi'        => ['Качество воздуха',      '🍃'],
];

// Отзывы
$reviews = $pdo->query("SELECT r.*,u.login FROM city_reviews r JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC LIMIT 30")->fetchAll();

// Получаем погоду
$weatherData = null;
$searchCity  = trim($_GET['city'] ?? '');
$searchLat   = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float)$_GET['lat'] : null;
$searchLon   = isset($_GET['lon']) && $_GET['lon'] !== '' ? (float)$_GET['lon'] : null;

if ($searchCity || ($searchLat !== null && $searchLon !== null)) {
    if ($searchLat === null) {
        $geo = geocodeCity($searchCity);
        if ($geo) { $searchLat=$geo['lat']; $searchLon=$geo['lon']; $searchCity=$geo['name']; }
    }
    if ($searchLat !== null) {
        $weatherData = getWeatherFull($pdo, $searchLat, $searchLon, $searchCity, $units);
    }
} elseif (!empty($favRows)) {
    $f = $favRows[0];
    if ($f['lat'] && $f['lon']) {
        $weatherData = getWeatherFull($pdo, (float)$f['lat'], (float)$f['lon'], $f['city_name'], $units);
        $searchCity = $f['city_name'];
        $searchLat  = (float)$f['lat'];
        $searchLon  = (float)$f['lon'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>WeatherApp — Прогноз погоды</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root{
  --bg:#0d1117;--surface:rgba(255,255,255,.05);--border:rgba(255,255,255,.08);
  --text:#e6edf3;--muted:rgba(255,255,255,.4);--accent:#3b82f6;--accent2:#60a5fa;
  --success:#10b981;--danger:#ef4444;--warn:#f59e0b;
  --card-blur:blur(16px);
}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh}
a{color:inherit;text-decoration:none}

/* NAVBAR */
.navbar{background:rgba(13,17,23,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);
  padding:12px 24px;display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:100}
.nav-brand{font-family:'Space Mono',monospace;font-size:16px;letter-spacing:2px;color:#fff}
.nav-brand span{color:var(--accent)}
.nav-right{display:flex;align-items:center;gap:12px}
.nav-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--accent)}
.btn-nav{padding:7px 16px;border-radius:10px;border:1px solid var(--border);background:var(--surface);
  color:var(--text);font-size:13px;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif}
.btn-nav:hover{border-color:var(--accent);color:var(--accent)}
.btn-admin{border-color:var(--warn);color:var(--warn)}
.btn-admin:hover{background:rgba(245,158,11,.1)}

/* LAYOUT */
.container{max-width:1400px;margin:0 auto;padding:24px}
.grid{display:grid;grid-template-columns:1fr 340px;gap:24px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}

/* SEARCH BAR */
.search-wrap{position:relative;margin-bottom:24px}
.search-inner{display:flex;gap:8px}
.search-input{flex:1;padding:14px 20px;background:var(--surface);border:1px solid var(--border);
  border-radius:14px;color:var(--text);font-size:15px;font-family:'DM Sans',sans-serif;
  outline:none;transition:.2s;backdrop-filter:var(--card-blur)}
.search-input:focus{border-color:var(--accent);background:rgba(59,130,246,.08)}
.search-input::placeholder{color:var(--muted)}
.btn-search{padding:14px 20px;border-radius:14px;border:none;background:var(--accent);
  color:#fff;cursor:pointer;font-size:16px;transition:.2s}
.btn-search:hover{background:#2563eb;transform:scale(1.05)}
.btn-fav-add{padding:14px 18px;border-radius:14px;border:1px solid var(--warn);
  background:transparent;color:var(--warn);cursor:pointer;font-size:16px;transition:.2s}
.btn-fav-add:hover{background:rgba(245,158,11,.1)}
.autocomplete{position:absolute;top:calc(100% + 4px);left:0;right:120px;background:#1a2233;
  border:1px solid var(--border);border-radius:12px;z-index:200;overflow:hidden;display:none}
.autocomplete-item{padding:12px 16px;cursor:pointer;font-size:14px;transition:.15s;display:flex;justify-content:space-between}
.autocomplete-item:hover{background:rgba(59,130,246,.15);color:var(--accent2)}
.autocomplete-item .country{color:var(--muted);font-size:12px}

/* WEATHER CARD */
.weather-main{background:linear-gradient(135deg,#1a3a6c 0%,#0f2347 60%,#0a1628 100%);
  border-radius:20px;padding:32px;position:relative;overflow:hidden;margin-bottom:24px}
.weather-main::before{content:'';position:absolute;top:-40px;right:-40px;width:220px;height:220px;
  border-radius:50%;background:radial-gradient(circle,rgba(59,130,246,.3),transparent 70%)}
.weather-main .city-name{font-size:22px;font-weight:600;margin-bottom:4px}
.weather-main .city-sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.weather-top{display:flex;align-items:flex-start;justify-content:space-between}
.weather-temp{font-family:'Space Mono',monospace;font-size:80px;font-weight:700;line-height:1;color:#fff}
.weather-icon-big{font-size:64px;line-height:1}
.weather-desc{color:#93c5fd;font-size:16px;margin-top:8px;text-transform:capitalize}
.weather-minmax{color:var(--muted);font-size:14px;margin-top:4px}
.params-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-top:24px}
.param-card{background:rgba(255,255,255,.08);border-radius:12px;padding:14px;text-align:center}
.param-card .p-icon{font-size:22px;margin-bottom:6px}
.param-card .p-val{font-size:18px;font-weight:600;color:#fff}
.param-card .p-label{font-size:11px;color:var(--muted);margin-top:2px;text-transform:uppercase;letter-spacing:.5px}
.weather-empty{text-align:center;padding:60px 20px;color:var(--muted)}
.weather-empty .big-icon{font-size:64px;display:block;margin-bottom:16px;opacity:.5}
.weather-empty h3{font-size:18px;margin-bottom:8px;color:var(--text)}

/* ALERT */
.weather-alert{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);
  border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:14px;color:#fca5a5}

/* TABS */
.tabs{display:flex;gap:4px;margin-bottom:16px;background:var(--surface);border-radius:12px;padding:4px}
.tab{flex:1;padding:8px;border-radius:8px;border:none;background:transparent;
  color:var(--muted);font-size:13px;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif}
.tab.active{background:var(--accent);color:#fff}

/* HOURLY */
.hourly-scroll {
  display: grid;
  /* Создаем 12 колонок. На маленьких экранах можно поменять на 6 или 4 */
  grid-template-columns: repeat(12, 1fr);
  gap: 8px;
  padding-bottom: 8px;
}

/* Адаптивность: на мобильных устройствах делаем 4 или 6 колонок (будет 4 или 6 рядов) */
@media (max-width: 768px) {
  .hourly-scroll {
    grid-template-columns: repeat(6, 1fr);
  }
}

.hour-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 10px 4px;
  text-align: center;
}

/* Исправление для карточек параметров */
.param-card .p-val {
    font-size: 12px;       /* Немного уменьшим базовый шрифт (был 16px) */
    font-weight: 600;
    line-height: 1.2;      /* Чтобы строки не слипались при переносе */
    word-wrap: break-word; /* Разрешаем перенос длинных слов */
    overflow-wrap: break-word;
    display: block;        /* Гарантируем правильное отображение */
    margin: 4px 0;
}

/* Специальное правило для длинных слов */
.param-card {
    min-height: 100px;     /* Увеличим высоту, чтобы текст в 2 строки влез */
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 8px 4px;      /* Уменьшим боковые отступы, чтобы дать больше места тексту */
}

.param-card .p-label {
    font-size: 11px;       /* Подпись сделаем чуть компактнее */
    white-space: nowrap;   /* Чтобы название параметра (н-р "Воздух") не прыгало */
}

/* DAILY */
.day-card{display:flex;align-items:center;padding:12px 16px;border-bottom:1px solid var(--border)}
.day-card:last-child{border-bottom:none}
.day-name{width:90px;font-size:14px;font-weight:500}
.day-icon{font-size:22px;flex:0 0 36px;text-align:center}
.day-pop{font-size:12px;color:#60a5fa;flex:0 0 36px;text-align:center}
.day-temps{margin-left:auto;display:flex;gap:8px;font-size:14px}
.day-max{font-weight:600}
.day-min{color:var(--muted)}

/* CHART */
.chart-wrap{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:16px;margin-bottom:24px}
.chart-wrap canvas{max-height:180px}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border);backdrop-filter:var(--card-blur);
  border-radius:16px;margin-bottom:16px;overflow:hidden}
.card-header{padding:14px 16px;border-bottom:1px solid var(--border);font-size:13px;
  font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
.card-body{padding:16px}

/* FAVORITES */
.fav-item{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;
  border-bottom:1px solid var(--border);cursor:pointer;transition:.15s}
.fav-item:last-child{border-bottom:none}
.fav-item:hover{background:rgba(59,130,246,.08)}
.fav-item .fav-name{font-size:14px;font-weight:500}
.fav-item .fav-temp{font-size:13px;color:var(--muted)}
.btn-del{background:none;border:none;color:var(--danger);cursor:pointer;font-size:18px;padding:0 4px;line-height:1}

/* PARAMS SETTINGS */
.param-check{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)}
.param-check:last-child{border-bottom:none}
.param-check label{font-size:13px;cursor:pointer;flex:1}
.toggle{position:relative;width:36px;height:20px;flex:0 0 36px}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:rgba(255,255,255,.1);border-radius:20px;transition:.2s;cursor:pointer}
.toggle input:checked + .toggle-slider{background:var(--accent)}
.toggle-slider::before{content:'';position:absolute;width:14px;height:14px;border-radius:50%;
  background:#fff;top:3px;left:3px;transition:.2s}
.toggle input:checked + .toggle-slider::before{transform:translateX(128px)}

/* REVIEWS */
.review-item{padding:12px 16px;border-bottom:1px solid var(--border)}
.review-item:last-child{border-bottom:none}
.review-city{font-size:12px;font-weight:600;color:var(--accent2);text-transform:uppercase;letter-spacing:.5px}
.review-text{font-size:14px;margin-top:4px}
.review-meta{font-size:11px;color:var(--muted);margin-top:4px}
.review-form{padding:16px;border-bottom:1px solid var(--border)}
.review-form input,.review-form textarea{width:100%;padding:10px 14px;background:rgba(255,255,255,.07);
  border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:13px;
  font-family:'DM Sans',sans-serif;outline:none;margin-bottom:8px;resize:none}
.review-form input:focus,.review-form textarea:focus{border-color:var(--accent)}

/* BUTTONS */
.btn{padding:10px 18px;border-radius:10px;border:none;cursor:pointer;font-size:13px;
  font-family:'DM Sans',sans-serif;font-weight:500;transition:.2s}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:#2563eb}
.btn-sm{padding:7px 14px;font-size:12px;border-radius:8px}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.btn-unit{border:1px solid var(--border);background:var(--surface);color:var(--muted);
  padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s}
.btn-unit:hover{border-color:var(--accent);color:var(--accent)}

/* MISC */
.msg-toast{position:fixed;top:70px;right:20px;padding:12px 20px;border-radius:12px;
  background:rgba(16,185,129,.9);color:#fff;font-size:14px;z-index:999;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.no-data{text-align:center;padding:24px;color:var(--muted);font-size:14px}
.loading{text-align:center;padding:40px;color:var(--muted)}
.spinner{display:inline-block;width:24px;height:24px;border:2px solid var(--border);
  border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite;margin-right:8px}
@keyframes spin{to{transform:rotate(360deg)}}

/* TAB CONTENT — ключевое исправление: visibility вместо display:none */
.tab-content{visibility:hidden;position:absolute;pointer-events:none;width:100%}
.tab-content.active{visibility:visible;position:static;pointer-events:auto}
/* Обёртка для вкладок — фиксированная высота через min-height */
.tabs-container{position:relative}


</style>
</head>
<body>

<?php if (isset($_GET['msg'])): ?>
<div class="msg-toast" id="toast">
  <?= ['fav_added'=>'⭐ Добавлено в избранное','settings_saved'=>'✅ Настройки сохранены','city_deleted'=>'🗑 Город удалён'][$_GET['msg']] ?? '' ?>
</div>
<script>setTimeout(()=>document.getElementById('toast')?.remove(),3000)</script>
<?php endif; ?>

<nav class="navbar">
  <div class="nav-brand">WEATHER<span>APP</span></div>
  <div class="nav-right">
    <form method="POST" style="margin:0">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="current_city" value="<?= htmlspecialchars($searchCity ?? '') ?>">
      <input type="hidden" name="current_lat"  value="<?= $searchLat ?? '' ?>">
      <input type="hidden" name="current_lon"  value="<?= $searchLon ?? '' ?>">
      <button name="toggle_units" class="btn-unit"><?= $units==='metric'?'°C → °F':'°F → °C' ?></button>
    </form>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <a href="admin.php" class="btn-nav btn-admin">⚙️ Админ</a>
    <?php endif; ?>
    <a href="profile.php">
      <img src="uploads/avatars/<?= htmlspecialchars($avatar) ?>" class="nav-avatar" alt="avatar">
    </a>
    <a href="logout.php" class="btn-nav">Выйти</a>
  </div>
</nav>

<div class="container">

  <!-- ПОИСК -->
  <div class="search-wrap">
    <div class="search-inner">
      <input type="text" id="cityInput" class="search-input"
             placeholder="🔍 Введите название города..." autocomplete="off"
             value="<?= htmlspecialchars($_GET['city'] ?? '') ?>">
      <button type="button" id="btnSearch" class="btn-search">🔍</button>
      <button type="button" id="btnFavAdd" class="btn-fav-add" title="В избранное">⭐</button>
    </div>
    <div class="autocomplete" id="autocomplete"></div>
  </div>

  <!-- Скрытая форма для добавления в избранное -->
  <form method="POST" id="favForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="add_to_fav" value="1">
    <input type="hidden" name="city_name" id="favCityName">
    <input type="hidden" name="lat" id="favLat">
    <input type="hidden" name="lon" id="favLon">
  </form>

  <div class="grid" style="margin-top:24px">
    <!-- ЛЕВАЯ КОЛОНКА -->
    <div>
      <div id="weatherMain">
        <?php if ($weatherData):
            $c = $weatherData['current'];
            $emoji = iconToEmoji($c['icon']);

            // Генерируем алерты
            $alerts = [];
            if ($c['wind_speed'] > 15) $alerts[] = '⚠️ Сильный ветер ' . $c['wind_speed'] . ' ' . $windUnit;
            if (isset($c['uv_index']) && $c['uv_index'] > 6) $alerts[] = '☀️ Высокий УФ-индекс: ' . $c['uv_index'];
            if ($c['temp'] < -20) $alerts[] = '🥶 Сильный мороз! Одевайтесь теплее.';
            if ($c['temp'] > 35)  $alerts[] = '🔥 Сильная жара! Пейте воду.';
        ?>
        <!-- АЛЕРТЫ -->
        <?php foreach (array_slice($alerts,0,3) as $al): ?>
          <div class="weather-alert"><?= htmlspecialchars($al) ?></div>
        <?php endforeach; ?>

        <!-- ГЛАВНАЯ КАРТОЧКА ПОГОДЫ -->
        <div class="weather-main" id="weatherCard">
          <div class="city-name"><?= htmlspecialchars($weatherData['city']) ?>, <?= htmlspecialchars($weatherData['country']) ?></div>
          <div class="city-sub">
            Обновлено: <?= date('H:i', $weatherData['updated_at']) ?>
            <?= $weatherData['from_cache'] ? ' (из кеша)' : '' ?>
            &nbsp;·&nbsp;
            <a href="home.php?city=<?= urlencode($weatherData['city']) ?>&lat=<?= $weatherData['lat'] ?>&lon=<?= $weatherData['lon'] ?>"
               style="color:var(--accent2)">↻ Обновить</a>
          </div>
          <div class="weather-top">
            <div>
              <div class="weather-temp"><?= $c['temp'] ?><?= $unitSign ?></div>
              <div class="weather-desc"><?= htmlspecialchars($c['desc']) ?></div>
              <div class="weather-minmax">↓ <?= $c['temp_min'] ?><?= $unitSign ?> &nbsp; ↑ <?= $c['temp_max'] ?><?= $unitSign ?></div>
            </div>
            <div class="weather-icon-big"><?= $emoji ?></div>
          </div>

          <!-- ПАРАМЕТРЫ -->
          <div class="params-grid">
            <?php
            $params = [
              'feels_like' => [$c['feels_like'].$unitSign, '🤔', 'Ощущается'],
              'humidity'   => [$c['humidity'].'%', '💧', 'Влажность'],
              'pressure'   => [$c['pressure'].' гПа', '📊', 'Давление'],
              'wind'       => [$c['wind_speed'].' '.$windUnit.' '.windDirection($c['wind_deg']), '💨', 'Ветер'],
              'uv_index'   => [isset($c['uv_index']) ? $c['uv_index'] : '—', '☀️', 'УФ-индекс'],
              'visibility' => [isset($c['visibility']) ? $c['visibility'].' км' : '—', '👁️', 'Видимость'],
              'precip'     => [$c['rain_1h']>0 ? $c['rain_1h'].' мм' : ($c['snow_1h']>0 ? $c['snow_1h'].' мм ❄' : '0 мм'), '🌧️', 'Осадки'],
              'sunrise'    => [isset($c['sunrise']) ? date('H:i',$c['sunrise']).' / '.date('H:i',$c['sunset']) : '—', '🌅', 'Восход/Закат'],
              'aqi'        => [$c['aqi_label'] ?? '—', '🍃', 'Воздух'],
            ];
            foreach ($params as $key => [$val, $icon, $label]):
              if (in_array($key, $adminEnabled) && in_array($key, $userParams)):
            ?>
            <div class="param-card">
              <div class="p-icon"><?= $icon ?></div>
              <div class="p-val"><?= htmlspecialchars($val) ?></div>
              <div class="p-label"><?= $label ?></div>
            </div>
            <?php endif; endforeach; ?>
          </div>
        </div>

        <!-- TABS -->
        <div class="tabs" id="mainTabs">
          <button class="tab active" data-tab="hourly">По часам</button>
          <button class="tab" data-tab="daily">7 дней</button>
          <button class="tab" data-tab="chart">График</button>
        </div>

        <div class="tabs-container">
          <!-- ПОЧАСОВОЙ -->
          <div class="tab-content active" id="tab-hourly">
            <div class="hourly-scroll">
              <?php foreach ($weatherData['hourly'] as $h): ?>
              <div class="hour-card">
                <div class="h-time"><?= date('H:i', $h['time']) ?></div>
                <div class="h-icon"><?= iconToEmoji($h['icon']) ?></div>
                <div class="h-temp"><?= $h['temp'] ?><?= $unitSign ?></div>
                <?php if ($h['pop'] > 0): ?>
                <div class="h-pop">💧<?= $h['pop'] ?>%</div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 7 ДНЕЙ -->
          <div class="tab-content" id="tab-daily">
            <div class="card">
              <?php
              $dayNames = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];
              foreach ($weatherData['daily'] as $d):
                $dn = $dayNames[date('w', strtotime($d['date']))];
                $isToday = $d['date'] === date('Y-m-d');
              ?>
              <div class="day-card">
                <div class="day-name" style="<?= $isToday?'color:var(--accent2);font-weight:600':'' ?>">
                  <?= $isToday ? 'Сегодня' : $dn.', '.date('d.m', strtotime($d['date'])) ?>
                </div>
                <div class="day-icon"><?= iconToEmoji($d['icon']) ?></div>
                <?php if ($d['pop'] > 0): ?>
                <div class="day-pop">💧<?= $d['pop'] ?>%</div>
                <?php else: ?><div class="day-pop"></div><?php endif; ?>
                <div class="day-temps">
                  <span class="day-max"><?= $d['temp_max'] ?><?= $unitSign ?></span>
                  <span class="day-min"><?= $d['temp_min'] ?><?= $unitSign ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- ГРАФИК -->
          <div class="tab-content" id="tab-chart">
            <div class="chart-wrap">
              <canvas id="tempChart"></canvas>
            </div>
            <div class="chart-wrap">
              <canvas id="humidChart"></canvas>
            </div>
          </div>
        </div><!-- /.tabs-container -->

        <?php
        $chartLabels = array_map(fn($h) => date('H:i',$h['time']), $weatherData['hourly']);
        $chartTemps  = array_column($weatherData['hourly'], 'temp');
        $chartHumids = array_column($weatherData['hourly'], 'humidity');
        $chartPop    = array_column($weatherData['hourly'], 'pop');
        ?>
        <script>
        (function(){
          const labels = <?= json_encode($chartLabels) ?>;
          const temps  = <?= json_encode($chartTemps) ?>;
          const humids = <?= json_encode($chartHumids) ?>;
          const pop    = <?= json_encode($chartPop) ?>;

          Chart.defaults.color = 'rgba(255,255,255,0.5)';
          Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

          window._tempChart = new Chart(document.getElementById('tempChart'), {
            type: 'line',
            data: {
              labels,
              datasets: [{
                label: 'Температура (<?= $unitSign ?>)',
                data: temps,
                borderColor: '#60a5fa',
                backgroundColor: 'rgba(96,165,250,0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: '#60a5fa',
              },{
                label: 'Осадки (%)',
                data: pop,
                borderColor: '#38bdf8',
                backgroundColor: 'rgba(56,189,248,0.05)',
                fill: true,
                tension: 0.4,
                pointRadius: 2,
                yAxisID: 'y2',
              }]
            },
            options: {
              responsive:true, maintainAspectRatio:false,
              plugins:{legend:{position:'top'}},
              scales:{
                y:{grid:{color:'rgba(255,255,255,0.05)'}},
                y2:{position:'right',min:0,max:100,grid:{display:false},ticks:{callback:v=>v+'%'}}
              }
            }
          });

          window._humidChart = new Chart(document.getElementById('humidChart'), {
            type: 'bar',
            data: {
              labels,
              datasets: [{
                label: 'Влажность (%)',
                data: humids,
                backgroundColor: 'rgba(99,102,241,0.6)',
                borderColor: '#6366f1',
                borderWidth: 1,
                borderRadius: 4,
              }]
            },
            options: {
              responsive:true, maintainAspectRatio:false,
              plugins:{legend:{position:'top'}},
              scales:{
                y:{min:0,max:100,grid:{color:'rgba(255,255,255,0.05)'}},
              }
            }
          });
        })();
        </script>

        <?php else: ?>
        <div class="weather-empty">
          <span class="big-icon">🌍</span>
          <h3>Введите название города</h3>
          <p>Или выберите из избранного</p>
        </div>
        <?php endif; ?>
      </div>

      <!-- ОТЗЫВЫ -->
      <div class="card" id="reviews" style="margin-top:24px">
        <div class="card-header">💬 Отзывы о погоде</div>
        <div class="review-form">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="text" name="review_city" placeholder="Город" required>
            <textarea name="review_text" placeholder="Ваш отзыв о погоде..." rows="2" required></textarea>
            <button name="add_review" class="btn btn-primary btn-sm">Отправить</button>
          </form>
        </div>
        <?php if (empty($reviews)): ?>
          <div class="no-data">Отзывов пока нет — будьте первым!</div>
        <?php else: ?>
          <?php foreach ($reviews as $rev): ?>
          <div class="review-item">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
              <span class="review-city"><?= htmlspecialchars($rev['city_name']) ?></span>
              <?php if ($rev['user_id']==$uid || $_SESSION['role']==='admin'): ?>
              <form method="POST" style="margin:0" onsubmit="return confirm('Удалить?')">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="rev_id" value="<?= $rev['id'] ?>">
                <button name="delete_review" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:14px">🗑</button>
              </form>
              <?php endif; ?>
            </div>
            <div class="review-text"><?= htmlspecialchars($rev['review_text']) ?></div>
            <div class="review-meta">👤 <?= htmlspecialchars($rev['login']) ?> · <?= $rev['created_at'] ?></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ПРАВАЯ КОЛОНКА -->
    <div>

      <!-- ИЗБРАННОЕ -->
      <div class="card">
        <div class="card-header">⭐ Избранные города</div>
        <?php if (empty($favRows)): ?>
          <div class="no-data">Добавьте города через поиск</div>
        <?php else: ?>
          <?php foreach ($favRows as $f): ?>
          <div class="fav-item" onclick="loadCity('<?= htmlspecialchars(addslashes($f['city_name'])) ?>',<?= $f['lat'] ?>,<?= $f['lon'] ?>)">
            <div>
              <div class="fav-name"><?= htmlspecialchars($f['alias'] ?? $f['city_name']) ?></div>
              <div class="fav-temp" id="favTemp_<?= $f['id'] ?>">...</div>
            </div>
            <form method="POST" style="margin:0" onsubmit="return confirm('Удалить?')">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="hidden" name="fav_id" value="<?= $f['id'] ?>">
              <button name="delete_fav" class="btn-del" onclick="event.stopPropagation()">×</button>
            </form>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <div style="padding:10px 16px;font-size:12px;color:var(--muted)">
          <?= count($favRows) ?>/10 городов
        </div>
      </div>

      <!-- НАСТРОЙКИ ПАРАМЕТРОВ -->
      <div class="card">
        <div class="card-header">⚙️ Параметры отображения</div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <?php if (!empty($_GET['city'])): ?>
            <input type="hidden" name="city" value="<?= htmlspecialchars($_GET['city']) ?>">
            <?php endif; ?>
            <?php foreach ($allLabels as $key => [$label, $icon]): ?>
              <?php if (in_array($key, $adminEnabled)): ?>
              <div class="param-check">
                <label class="toggle">
                  <input type="checkbox" name="user_params[]" value="<?= $key ?>"
                         <?= in_array($key, $userParams) ? 'checked' : '' ?>>
                  <span class="toggle-slider"></span>
                </label>
                <label><?= $icon ?> <?= $label ?></label>
              </div>
              <?php endif; ?>
            <?php endforeach; ?>
            <button name="save_user_settings" class="btn btn-primary" style="width:100%;margin-top:14px">Сохранить</button>
          </form>
        </div>
      </div>

      <!-- ГЕОЛОКАЦИЯ -->
      <div class="card">
        <div class="card-header">📍 Моя геолокация</div>
        <div class="card-body">
          <button class="btn btn-outline" style="width:100%" onclick="getMyLocation()">
            📍 Определить моё местоположение
          </button>
          <div id="geoStatus" style="font-size:12px;color:var(--muted);margin-top:8px;text-align:center"></div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// ======= ТЕКУЩИЙ ГОРОД (для использования в JS) =======
const currentCity = <?= json_encode($searchCity ?? '') ?>;
const currentLat  = <?= json_encode($searchLat ?? null) ?>;
const currentLon  = <?= json_encode($searchLon ?? null) ?>;

// ======= АВТОДОПОЛНЕНИЕ =======
const cityInput = document.getElementById('cityInput');
const acBox     = document.getElementById('autocomplete');
let acTimeout;
let selectedLat = currentLat;
let selectedLon = currentLon;
let selectedName = currentCity;

cityInput.addEventListener('input', function(){
  clearTimeout(acTimeout);
  const q = this.value.trim();
  // Сбрасываем координаты при ручном вводе
  selectedLat = null;
  selectedLon = null;
  selectedName = q;
  if (q.length < 2) { acBox.style.display='none'; return; }
  acTimeout = setTimeout(async()=>{
    try {
      const res = await fetch(`api/weather.php?action=autocomplete&q=${encodeURIComponent(q)}`);
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      if (!data.length) { acBox.style.display='none'; return; }
      acBox.innerHTML = data.map(d=>
        `<div class="autocomplete-item" data-lat="${d.lat}" data-lon="${d.lon}" data-name="${d.name}">
          <span>${d.name}</span><span class="country">${d.country}</span>
        </div>`
      ).join('');
      acBox.style.display = 'block';
      acBox.querySelectorAll('.autocomplete-item').forEach(el=>{
        el.addEventListener('mousedown', e => {
          // mousedown вместо click — срабатывает до blur
          e.preventDefault();
          selectedName = el.dataset.name;
          selectedLat  = el.dataset.lat;
          selectedLon  = el.dataset.lon;
          cityInput.value = el.dataset.name;
          acBox.style.display = 'none';
          navigateToCity(el.dataset.name, el.dataset.lat, el.dataset.lon);
        });
      });
    } catch(e) {
      console.error('Autocomplete error:', e);
    }
  }, 350);
});

document.addEventListener('click', e=>{
  if (!e.target.closest('.search-wrap')) acBox.style.display='none';
});

// ======= ПОИСК =======
document.getElementById('btnSearch').addEventListener('click', function(){
  const name = cityInput.value.trim();
  if (!name) return;
  if (selectedLat && selectedLon && selectedName === name) {
    navigateToCity(name, selectedLat, selectedLon);
  } else {
    // Поиск без координат — сервер сам геокодирует
    window.location.href = `home.php?city=${encodeURIComponent(name)}`;
  }
});

cityInput.addEventListener('keydown', function(e){
  if (e.key === 'Enter') {
    e.preventDefault();
    document.getElementById('btnSearch').click();
  }
});

// ======= ДОБАВИТЬ В ИЗБРАННОЕ =======
document.getElementById('btnFavAdd').addEventListener('click', function(){
  const name = cityInput.value.trim() || currentCity;
  if (!name) { alert('Сначала введите город'); return; }
  document.getElementById('favCityName').value = name;
  document.getElementById('favLat').value = selectedLat || currentLat || '';
  document.getElementById('favLon').value = selectedLon || currentLon || '';
  document.getElementById('favForm').submit();
});

function navigateToCity(name, lat, lon) {
  localStorage.setItem('lastCity', JSON.stringify({name, lat: parseFloat(lat), lon: parseFloat(lon)}));
  window.location.href = `home.php?city=${encodeURIComponent(name)}&lat=${lat}&lon=${lon}`;
}

// ======= ЗАГРУЗКА ГОРОДА ИЗ ИЗБРАННОГО =======
function loadCity(name, lat, lon) {
  localStorage.setItem('lastCity', JSON.stringify({name, lat, lon}));
  window.location.href = `home.php?city=${encodeURIComponent(name)}&lat=${lat}&lon=${lon}`;
}

// ======= ГЕОЛОКАЦИЯ (браузерная) =======
function getMyLocation() {
  const status = document.getElementById('geoStatus');
  if (!navigator.geolocation) { status.textContent='Геолокация не поддерживается'; return; }
  status.textContent='⏳ Определяем положение...';
  navigator.geolocation.getCurrentPosition(
    pos => {
      const lat = pos.coords.latitude;
      const lon = pos.coords.longitude;
      status.textContent='✅ Получены координаты, загружаем...';
      // Получаем название города через reverse geocoding OWM
      fetch(`api/weather.php?action=weather&lat=${lat}&lon=${lon}&city=&units=<?= $units ?>`)
        .then(r => r.json())
        .then(d => {
          if (d && d.city) {
            localStorage.setItem('lastCity', JSON.stringify({name:d.city, lat, lon}));
            window.location.href = `home.php?city=${encodeURIComponent(d.city)}&lat=${lat}&lon=${lon}`;
          } else {
            // Нет названия — всё равно идём по координатам
            window.location.href = `home.php?lat=${lat}&lon=${lon}&city=`;
          }
        })
        .catch(() => {
          status.textContent='❌ Ошибка получения данных';
        });
    },
    err => {
      const msgs = {1:'Доступ к геолокации запрещён',2:'Позиция недоступна',3:'Превышено время ожидания'};
      status.textContent = '❌ ' + (msgs[err.code] || 'Неизвестная ошибка');
    },
    {timeout: 10000, enableHighAccuracy: false}
  );
}

// ======= ВОССТАНОВЛЕНИЕ ПОСЛЕДНЕГО ГОРОДА =======
<?php if (!$weatherData): ?>
(function(){
  const lastCity = JSON.parse(localStorage.getItem('lastCity') || 'null');
  if (lastCity && lastCity.name) {
    window.location.href = `home.php?city=${encodeURIComponent(lastCity.name)}&lat=${lastCity.lat}&lon=${lastCity.lon}`;
  }
})();
<?php endif; ?>

// ======= ПОГОДА В ИЗБРАННЫХ (mini) =======
<?php foreach ($favRows as $f): ?>
(function(){
  const id = <?= $f['id'] ?>;
  fetch(`api/weather.php?action=weather&city=${encodeURIComponent('<?= addslashes($f['city_name']) ?>')}&lat=<?= $f['lat'] ?>&lon=<?= $f['lon'] ?>&units=<?= $units ?>`)
    .then(r => r.json())
    .then(d => {
      const el = document.getElementById('favTemp_' + id);
      if (el && d && d.current) {
        el.textContent = d.current.temp + '<?= $unitSign ?>';
      }
    })
    .catch(() => {});
})();
<?php endforeach; ?>

// ======= ПЕРЕКЛЮЧЕНИЕ ВКЛАДОК =======
// Ключевое исправление: resize графиков при показе вкладки Chart
document.querySelectorAll('#mainTabs .tab').forEach(btn => {
  btn.addEventListener('click', function() {
    const tabId = this.dataset.tab;
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('#mainTabs .tab').forEach(t => t.classList.remove('active'));
    const target = document.getElementById('tab-' + tabId);
    if (target) target.classList.add('active');
    this.classList.add('active');

    // Принудительный resize для Chart.js при показе вкладки с графиками
    if (tabId === 'chart') {
      requestAnimationFrame(() => {
        if (window._tempChart)  window._tempChart.resize();
        if (window._humidChart) window._humidChart.resize();
      });
    }
  });
});
</script>
</body>
</html>
