<?php
require 'config/database.php'; // Подключаем БД
use Faker\Factory;

$faker = Factory::create('ru_RU');

echo "🚀 Начинаем наполнение базы данных...\n";

// --- ЧАСТЬ 1: ПОЛЬЗОВАТЕЛИ ---
echo "👤 Создаем пользователей...";
$userIds = [];
for ($i = 0; $i < 10; $i++) {
    $login = $faker->userName;
    $password = password_hash('123456', PASSWORD_DEFAULT); 
    $is_active = 1;

    $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$login, $password, $is_active]);

    $userIds[] = $pdo->lastInsertId(); 
}
echo " Готово!\n";

// --- ЧАСТЬ 2: ДАННЫЕ ПОГОДЫ ---
echo "☀️ Создаем прогнозы погоды...\n";

// Получаем реальные id локаций из БД
$locationIds = $pdo->query("SELECT id FROM locations")->fetchAll(PDO::FETCH_COLUMN);

if (empty($locationIds)) {
    die("❌ В таблице locations нет записей! Создайте хотя бы одну локацию.\n");
}

$weatherTypes = ['sunny', 'cloudy', 'rain', 'snow', 'storm', 'fog'];

for ($i = 0; $i < 50; $i++) {
    $location_id = $locationIds[array_rand($locationIds)];
    $temperature = $faker->numberBetween(-20, 40);
    $humidity = $faker->numberBetween(0, 100);
    $pressure = $faker->numberBetween(950, 1050);
    $precipitation = $faker->numberBetween(0, 50);
    $weather_type = $weatherTypes[array_rand($weatherTypes)];
    $timestamp_utc = $faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s');
    $source = 'Faker';

    $stmt = $pdo->prepare("
        INSERT INTO weather_data 
        (location_id, temperature, humidity, pressure, precipitation, weather_type, timestamp_utc, source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $location_id,
        $temperature,
        $humidity,
        $pressure,
        $precipitation,
        $weather_type,
        $timestamp_utc,
        $source
    ]);
}

echo " Готово! (50 прогнозов создано)\n";
?>
