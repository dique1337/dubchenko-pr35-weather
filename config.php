<?php
// config.php
define('OWM_API_KEY', 'b193f8bb1bf31a82b8e3691b99f913ef');
define('OWM_BASE',    'https://api.openweathermap.org/data/2.5/');
define('OWM_GEO',     'https://api.openweathermap.org/geo/1.0/');
define('OWM_AIR',     'https://api.openweathermap.org/data/2.5/air_pollution');
define('CACHE_TTL',   600); // 10 минут

$host    = 'localhost';
$db      = 'weather';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die(json_encode(['error' => 'DB connection failed']));
}
