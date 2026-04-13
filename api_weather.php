<?php
// api/weather.php — AJAX endpoint для получения погоды
header('Content-Type: application/json; charset=utf-8');

// Разрешаем CORS для локальной разработки
header('Access-Control-Allow-Origin: *');

require_once '../config.php';
require_once '../weather_api.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // Автодополнение города
    case 'autocomplete':
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            echo json_encode([]);
            exit;
        }
        $result = autocompleteCity($q);
        echo json_encode($result ?: []);
        break;

    // Получение погоды по городу или координатам
    case 'weather':
        $city  = trim($_GET['city'] ?? '');
        $lat   = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float)$_GET['lat'] : null;
        $lon   = isset($_GET['lon']) && $_GET['lon'] !== '' ? (float)$_GET['lon'] : null;
        $units = ($_GET['units'] ?? 'metric') === 'imperial' ? 'imperial' : 'metric';

        // Если нет координат — геокодируем по названию
        if ($lat === null || $lon === null) {
            if (empty($city)) {
                http_response_code(400);
                echo json_encode(['error' => 'No city or coordinates']);
                exit;
            }
            $geo = geocodeCity($city);
            if (!$geo) {
                http_response_code(404);
                echo json_encode(['error' => 'Город не найден']);
                exit;
            }
            $lat  = $geo['lat'];
            $lon  = $geo['lon'];
            $city = $geo['name'];
        } else {
            // Есть координаты, но нет названия — reverse geocode
            if (empty($city)) {
                $geo = reverseGeocode($lat, $lon);
                $city = $geo ? $geo['name'] : 'Unknown';
            }
        }

        $data = getWeatherFull($pdo, $lat, $lon, $city, $units);
        if (!$data) {
            http_response_code(503);
            echo json_encode(['error' => 'Не удалось получить данные погоды']);
            exit;
        }
        echo json_encode($data);
        break;

    // Геолокация по IP (fallback — работает только не на localhost)
    case 'geoip':
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        // Убираем локальные адреса
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            echo json_encode(['error' => 'localhost — geo by IP unavailable']);
            exit;
        }
        // Убираем список IP если X-Forwarded-For содержит несколько
        $ip = trim(explode(',', $ip)[0]);
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $raw = @file_get_contents("http://ip-api.com/json/{$ip}?lang=ru&fields=city,lat,lon,country,status", false, $ctx);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (isset($decoded['status']) && $decoded['status'] === 'success') {
                echo json_encode($decoded);
            } else {
                echo json_encode(['error' => 'Geo lookup failed']);
            }
        } else {
            echo json_encode(['error' => 'geo request failed']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
