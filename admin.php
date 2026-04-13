<?php
require_once 'auth_check.php';
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: home.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF error');

    // Обновить параметры
    if (isset($_POST['update_weather_settings'])) {
        $pdo->exec("UPDATE weather_display_settings SET is_enabled=0");
        if (!empty($_POST['params'])) {
            $stmt = $pdo->prepare("UPDATE weather_display_settings SET is_enabled=1 WHERE param_key=?");
            foreach ($_POST['params'] as $p) $stmt->execute([$p]);
        }
        header("Location: admin.php?msg=settings_saved"); exit;
    }

    // Очистить логи
    if (isset($_POST['clear_logs'])) {
        $pdo->exec("DELETE FROM security_logs");
        header("Location: admin.php?msg=logs_cleared"); exit;
    }

    // Удалить город
    if (isset($_POST['delete_city'])) {
        $pdo->prepare("DELETE FROM weather_cities WHERE id=?")->execute([$_POST['city_id']]);
        header("Location: admin.php?msg=city_deleted"); exit;
    }

    // Очистить кеш погоды
    if (isset($_POST['clear_cache'])) {
        $pdo->exec("DELETE FROM weather_cache");
        header("Location: admin.php?msg=cache_cleared"); exit;
    }

    // Изменить роль пользователя
    if (isset($_POST['toggle_role'])) {
        $uid = (int)$_POST['target_uid'];
        if ($uid !== $_SESSION['user_id']) {
            $curr = $pdo->prepare("SELECT role FROM users WHERE id=?");
            $curr->execute([$uid]);
            $cur = $curr->fetchColumn();
            $newRole = $cur === 'admin' ? 'user' : 'admin';
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$newRole,$uid]);
        }
        header("Location: admin.php?msg=role_changed"); exit;
    }
}

// GET: удаление города
if (isset($_GET['delete_city_id'])) {
    $pdo->prepare("DELETE FROM weather_cities WHERE id=?")->execute([$_GET['delete_city_id']]);
    header("Location: admin.php?msg=city_deleted"); exit;
}

// Данные
$settings = $pdo->query("SELECT * FROM weather_display_settings ORDER BY id")->fetchAll();
$logs      = $pdo->query("SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 100")->fetchAll();
$cities    = $pdo->query("SELECT c.*,u.login FROM weather_cities c LEFT JOIN users u ON u.id=c.added_by ORDER BY c.created_at DESC")->fetchAll();
$users     = $pdo->query("SELECT id,login,email,role,is_active,created_at FROM users ORDER BY id DESC")->fetchAll();
$cacheCount = $pdo->query("SELECT COUNT(*) FROM weather_cache")->fetchColumn();
$logsCount  = count($logs);
$favCount   = $pdo->query("SELECT COUNT(*) FROM favorite_cities")->fetchColumn();

$msgs = ['settings_saved'=>'✅ Параметры сохранены','logs_cleared'=>'✅ Логи очищены',
         'city_deleted'=>'✅ Город удалён','cache_cleared'=>'✅ Кеш очищен','role_changed'=>'✅ Роль изменена'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Админ-панель — WeatherApp</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0d1117;color:#e6edf3;font-family:'DM Sans',sans-serif;min-height:100vh}
.navbar{background:rgba(13,17,23,.95);border-bottom:1px solid rgba(255,255,255,.08);
  padding:12px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.nav-brand{font-family:'Space Mono',monospace;font-size:14px;color:#f59e0b;letter-spacing:2px}
.btn-nav{padding:7px 14px;border-radius:8px;border:1px solid rgba(255,255,255,.1);
  background:rgba(255,255,255,.05);color:#e6edf3;font-size:13px;text-decoration:none;cursor:pointer;font-family:'DM Sans',sans-serif}
.container{max-width:1400px;margin:0 auto;padding:24px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:28px}
.stat-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:18px;text-align:center}
.stat-val{font-family:'Space Mono',monospace;font-size:28px;font-weight:700;color:#60a5fa;margin-bottom:4px}
.stat-label{font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px}
.grid{display:grid;grid-template-columns:320px 1fr;gap:24px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
.card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:16px;margin-bottom:20px;overflow:hidden}
.card-header{padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.08);font-size:12px;
  font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.5);
  display:flex;justify-content:space-between;align-items:center}
.card-body{padding:18px}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;
  border-bottom:1px solid rgba(255,255,255,.05)}
.toggle-row:last-child{border-bottom:none}
.toggle-row label{font-size:13px;cursor:pointer}
.toggle{position:relative;width:40px;height:22px}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:rgba(255,255,255,.1);border-radius:22px;transition:.25s;cursor:pointer}
.toggle input:checked+.toggle-slider{background:#3b82f6}
.toggle-slider::before{content:'';position:absolute;width:16px;height:16px;border-radius:50%;
  background:#fff;top:3px;left:3px;transition:.25s}
.toggle input:checked+.toggle-slider::before{transform:translateX(18px)}
table{width:100%;border-collapse:collapse;font-size:13px}
th{padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;
  color:rgba(255,255,255,.4);border-bottom:1px solid rgba(255,255,255,.08);font-weight:600}
td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.05)}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.02)}
.badge{display:inline-block;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.badge-danger{background:rgba(239,68,68,.2);color:#fca5a5}
.badge-success{background:rgba(16,185,129,.2);color:#6ee7b7}
.badge-warn{background:rgba(245,158,11,.2);color:#fcd34d}
.badge-info{background:rgba(59,130,246,.2);color:#93c5fd}
.badge-admin{background:rgba(245,158,11,.2);color:#fcd34d}
.badge-user{background:rgba(99,102,241,.2);color:#c4b5fd}
.btn{padding:8px 16px;border-radius:8px;border:none;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s}
.btn-primary{background:#3b82f6;color:#fff}
.btn-primary:hover{background:#2563eb}
.btn-danger{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.btn-danger:hover{background:rgba(239,68,68,.25)}
.btn-outline{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#e6edf3}
.btn-outline:hover{border-color:#3b82f6;color:#60a5fa}
.btn-sm{padding:5px 12px;font-size:11px}
code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:4px;font-family:'Space Mono',monospace;font-size:11px}
.toast{position:fixed;top:70px;right:20px;padding:12px 20px;border-radius:12px;
  background:rgba(16,185,129,.9);color:#fff;font-size:14px;z-index:999;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.table-wrap{max-height:420px;overflow-y:auto}
.table-wrap::-webkit-scrollbar{width:4px}
.table-wrap::-webkit-scrollbar-track{background:transparent}
.table-wrap::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:2px}
.tab-nav{display:flex;gap:4px;margin-bottom:16px;background:rgba(255,255,255,.05);border-radius:10px;padding:4px}
.tab-btn{flex:1;padding:8px;border-radius:6px;border:none;background:transparent;
  color:rgba(255,255,255,.4);font-size:12px;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif}
.tab-btn.active{background:#3b82f6;color:#fff}
.tab-pane{display:none}.tab-pane.active{display:block}
</style>
</head>
<body>

<?php if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])): ?>
<div class="toast" id="toast"><?= $msgs[$_GET['msg']] ?></div>
<script>setTimeout(()=>document.getElementById('toast')?.remove(),3000)</script>
<?php endif; ?>

<nav class="navbar">
  <div class="nav-brand">⚙️ ADMIN PANEL</div>
  <a href="home.php" class="btn-nav">← На главную</a>
</nav>

<div class="container">
  <!-- СТАТИСТИКА -->
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-val"><?= count($users) ?></div><div class="stat-label">Пользователей</div></div>
    <div class="stat-card"><div class="stat-val"><?= $favCount ?></div><div class="stat-label">Избранных</div></div>
    <div class="stat-card"><div class="stat-val"><?= count($cities) ?></div><div class="stat-label">Городов</div></div>
    <div class="stat-card"><div class="stat-val"><?= $cacheCount ?></div><div class="stat-label">Кеш записей</div></div>
    <div class="stat-card"><div class="stat-val"><?= $logsCount ?></div><div class="stat-label">Логов</div></div>
  </div>

  <div class="grid">
    <!-- ЛЕВАЯ: ПАРАМЕТРЫ -->
    <div>
      <div class="card">
        <div class="card-header">⚙️ Параметры пользователей</div>
        <div class="card-body">
          <p style="font-size:12px;color:rgba(255,255,255,.4);margin-bottom:14px">
            Включите/выключите параметры, которые пользователи могут добавить в виджет
          </p>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <?php foreach ($settings as $s): ?>
            <div class="toggle-row">
              <label for="p_<?= $s['param_key'] ?>"><?= htmlspecialchars($s['label_ru']) ?></label>
              <label class="toggle">
                <input type="checkbox" id="p_<?= $s['param_key'] ?>" name="params[]"
                       value="<?= $s['param_key'] ?>" <?= $s['is_enabled']?'checked':'' ?>>
                <span class="toggle-slider"></span>
              </label>
            </div>
            <?php endforeach; ?>
            <button name="update_weather_settings" class="btn btn-primary" style="width:100%;margin-top:16px">Сохранить параметры</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">🗄️ Управление кешем</div>
        <div class="card-body">
          <p style="font-size:12px;color:rgba(255,255,255,.4);margin-bottom:12px">Кеш погоды: <?= $cacheCount ?> записей</p>
          <form method="POST" onsubmit="return confirm('Очистить кеш погоды?')">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button name="clear_cache" class="btn btn-outline" style="width:100%">🗑 Очистить кеш погоды</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">🏙️ Города в системе</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Город</th><th>Добавил</th><th></th></tr></thead>
            <tbody>
              <?php if (empty($cities)): ?>
              <tr><td colspan="3" style="text-align:center;color:rgba(255,255,255,.3);padding:20px">Нет городов</td></tr>
              <?php else: ?>
              <?php foreach ($cities as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c['city_name']) ?></td>
                <td style="color:rgba(255,255,255,.4)"><?= htmlspecialchars($c['login'] ?? 'System') ?></td>
                <td>
                  <a href="admin.php?delete_city_id=<?= $c['id'] ?>"
                     onclick="return confirm('Удалить?')"
                     style="color:#ef4444;font-size:14px;text-decoration:none">🗑</a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ПРАВАЯ: ЛОГИ И ПОЛЬЗОВАТЕЛИ -->
    <div>
      <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('logs',this)">📋 Логи безопасности</button>
        <button class="tab-btn" onclick="switchTab('users',this)">👥 Пользователи</button>
      </div>

      <!-- ЛОГИ -->
      <div class="tab-pane active" id="tab-logs">
        <div class="card">
          <div class="card-header">
            📋 Последние <?= count($logs) ?> событий
            <form method="POST" style="margin:0" onsubmit="return confirm('Очистить все логи?')">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <button name="clear_logs" class="btn btn-danger btn-sm">Очистить</button>
            </form>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Время</th><th>Событие</th><th>User ID</th><th>IP</th><th>Детали</th></tr></thead>
              <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="5" style="text-align:center;color:rgba(255,255,255,.3);padding:24px">Журнал пуст</td></tr>
                <?php else: ?>
                <?php foreach ($logs as $l):
                  $badgeClass = match($l['event_type']) {
                    'login_fail'          => 'badge-danger',
                    'login_success'       => 'badge-success',
                    'suspicious_activity' => 'badge-warn',
                    'logout'              => 'badge-info',
                    default               => 'badge-info',
                  };
                ?>
                <tr>
                  <td style="white-space:nowrap;color:rgba(255,255,255,.4);font-size:12px"><?= $l['created_at'] ?></td>
                  <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($l['event_type']) ?></span></td>
                  <td style="color:rgba(255,255,255,.4)"><?= $l['user_id'] ? 'ID:'.$l['user_id'] : '—' ?></td>
                  <td><code><?= htmlspecialchars($l['ip_address']) ?></code></td>
                  <td style="color:rgba(255,255,255,.5);font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                      title="<?= htmlspecialchars($l['details']) ?>"><?= htmlspecialchars($l['details']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ПОЛЬЗОВАТЕЛИ -->
      <div class="tab-pane" id="tab-users">
        <div class="card">
          <div class="card-header">👥 Все пользователи (<?= count($users) ?>)</div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Статус</th><th>Дата</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                  <td style="color:rgba(255,255,255,.3)"><?= $u['id'] ?></td>
                  <td style="font-weight:500"><?= htmlspecialchars($u['login']) ?></td>
                  <td style="color:rgba(255,255,255,.5);font-size:12px"><?= htmlspecialchars($u['email']) ?></td>
                  <td><span class="badge <?= $u['role']==='admin'?'badge-admin':'badge-user' ?>"><?= $u['role'] ?></span></td>
                  <td><span class="badge <?= $u['is_active']?'badge-success':'badge-danger' ?>"><?= $u['is_active']?'Активен':'Неактивен' ?></span></td>
                  <td style="color:rgba(255,255,255,.3);font-size:11px"><?= date('d.m.y', strtotime($u['created_at'])) ?></td>
                  <td>
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                    <form method="POST" style="margin:0">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <input type="hidden" name="target_uid" value="<?= $u['id'] ?>">
                      <button name="toggle_role" class="btn btn-outline btn-sm"
                              onclick="return confirm('Изменить роль?')">
                        <?= $u['role']==='admin'?'→ user':'→ admin' ?>
                      </button>
                    </form>
                    <?php else: ?><span style="font-size:11px;color:rgba(255,255,255,.3)">Вы</span><?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function switchTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  btn.classList.add('active');
}
</script>
</body>
</html>
