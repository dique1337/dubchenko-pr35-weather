<?php
// api/weather.php — AJAX endpoint для получения погоды
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';
require_once '../weather_api.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // Автодополнение города
    case 'autocomplete':
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode([]); exit; }
        echo json_encode(autocompleteCity($q));
        break;

    // Получение погоды по городу
    case 'weather':
        $city = trim($_GET['city'] ?? '');
        $lat  = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
        $lon  = isset($_GET['lon']) ? (float)$_GET['lon'] : null;
        $units = ($_GET['units'] ?? 'metric') === 'imperial' ? 'imperial' : 'metric';

        if ($lat === null || $lon === null) {
            if (empty($city)) { echo json_encode(['error' => 'No city']); exit; }
            $geo = geocodeCity($city);
            if (!$geo) { echo json_encode(['error' => 'Город не найден']); exit; }
            $lat  = $geo['lat'];
            $lon  = $geo['lon'];
            $city = $geo['name'];
        }

        $data = getWeatherFull($pdo, $lat, $lon, $city, $units);
        if (!$data) { echo json_encode(['error' => 'Не удалось получить данные']); exit; }
        echo json_encode($data);
        break;

    // Геолокация по IP (fallback)
    case 'geoip':
        $ip  = $_SERVER['REMOTE_ADDR'];
        $raw = @file_get_contents("http://ip-api.com/json/$ip?lang=ru&fields=city,lat,lon,country");
        echo $raw ?: json_encode(['error' => 'geo failed']);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
