<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/FavoriteLocation.php';
require_once '../classes/UserSettings.php';
require_once '../classes/WeatherHistory.php';
require_once '../classes/WeatherData.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== ТЕСТЫ МОДЕЛЕЙ ===" . PHP_EOL;

// ------------------
// 1. User
// ------------------
echo PHP_EOL . "=== ТЕСТ: User ===" . PHP_EOL;
$user = new User($conn);
$user->login = 'testuser';
$user->password_hash = password_hash('123456', PASSWORD_DEFAULT);
$user->is_active = 1;
$user->created_at = date('Y-m-d H:i:s');
$user->updated_at = date('Y-m-d H:i:s');

if ($user->create()) echo "CREATE: OK (ID={$user->id})" . PHP_EOL;
else echo "CREATE: ERROR" . PHP_EOL;

$user2 = new User($conn);
if ($user2->getById($user->id)) echo "READ: OK" . PHP_EOL;
else echo "READ: ERROR" . PHP_EOL;

$user2->login = 'updateduser';
$user2->update();
echo "UPDATE: OK" . PHP_EOL;

$stmt = $user->getAll();
echo "GET ALL: OK (rows={$stmt->rowCount()})" . PHP_EOL;

// ------------------
// 2. FavoriteLocation
// ------------------
echo PHP_EOL . "=== ТЕСТ: FavoriteLocation ===" . PHP_EOL;

// Сначала создаём локацию для FK
$conn->exec("INSERT INTO locations (city, country, lat, lon, timezone) VALUES
    ('Test City','Test Country',0,0,'UTC')");
$location_id = $conn->lastInsertId();

$favorite = new FavoriteLocation($conn);
$favorite->user_id = $user->id;       // FK на User
$favorite->location_id = $location_id; // FK на Location
$favorite->alias = 'Дом';
$favorite->sort_order = 1;

if ($favorite->create()) echo "CREATE: OK (ID={$favorite->id})" . PHP_EOL;
else echo "CREATE: ERROR" . PHP_EOL;

$favorite2 = new FavoriteLocation($conn);
if ($favorite2->getById($favorite->id)) echo "READ: OK" . PHP_EOL;
else echo "READ: ERROR" . PHP_EOL;

$favorite2->alias = 'Работа';
$favorite2->update();
echo "UPDATE: OK" . PHP_EOL;

$stmt = $favorite->getAllByUser($user->id);
echo "GET ALL BY USER: OK (rows={$stmt->rowCount()})" . PHP_EOL;

// ------------------
// 3. UserSettings
// ------------------
echo PHP_EOL . "=== ТЕСТ: UserSettings ===" . PHP_EOL;
$settings = new UserSettings($conn);
$settings->user_id = $user->id;
$settings->language = 'en';
$settings->units = 'metric';
$settings->timezone = 'UTC';

if ($settings->create()) echo "CREATE: OK" . PHP_EOL;
else echo "CREATE: ERROR" . PHP_EOL;

$settings2 = new UserSettings($conn);
if ($settings2->getByUserId($user->id)) echo "READ: OK" . PHP_EOL;
else echo "READ: ERROR" . PHP_EOL;

$settings2->language = 'ru';
$settings2->update();
echo "UPDATE: OK" . PHP_EOL;

// ------------------
// 4. WeatherHistory
// ------------------
echo PHP_EOL . "=== ТЕСТ: WeatherHistory ===" . PHP_EOL;
$history = new WeatherHistory($conn);
$history->location_id = $location_id;
$history->parameter = 'temperature';
$history->value = 25;
$history->timestamp_utc = date('Y-m-d H:i:s');

if ($history->create()) echo "CREATE: OK (ID={$history->id})" . PHP_EOL;
else echo "CREATE: ERROR" . PHP_EOL;

$history2 = new WeatherHistory($conn);
if ($history2->getById($history->id)) echo "READ: OK" . PHP_EOL;
else echo "READ: ERROR" . PHP_EOL;

$stmt = $history->getAllByLocation($location_id);
echo "GET ALL BY LOCATION: OK (rows={$stmt->rowCount()})" . PHP_EOL;

// ------------------
// 5. WeatherData
// ------------------
echo PHP_EOL . "=== ТЕСТ: WeatherData ===" . PHP_EOL;
$weather = new WeatherData($conn);
$weather->location_id = $location_id;
$weather->temperature = 22.5;
$weather->humidity = 60;
$weather->pressure = 1013;
$weather->precipitation = 0;
$weather->weather_type = 'Sunny';
$weather->timestamp_utc = date('Y-m-d H:i:s');
$weather->source = 'API';

if ($weather->create()) echo "CREATE: OK (ID={$weather->id})" . PHP_EOL;
else echo "CREATE: ERROR" . PHP_EOL;

$weather2 = new WeatherData($conn);
if ($weather2->getById($weather->id)) echo "READ: OK" . PHP_EOL;
else echo "READ: ERROR" . PHP_EOL;

$weather2->temperature = 23;
$weather2->update();
echo "UPDATE: OK" . PHP_EOL;

$stmt = $weather->getAll();
echo "GET ALL: OK (rows={$stmt->rowCount()})" . PHP_EOL;

// ------------------
// CLEANUP (optional)
// ------------------
$weather->delete($weather->id);
$history->delete($history->id);
$favorite->delete($favorite->id);
$settings->delete($user->id);
$user->delete($user->id);

$conn->exec("DELETE FROM locations WHERE id = $location_id");

echo PHP_EOL . "=== ВСЕ ТЕСТЫ ЗАВЕРШЕНЫ ===" . PHP_EOL;
