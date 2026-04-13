<?php
// api/weather.php

// 1. Подключаем конфиг и библиотеку функций
// ВАЖНО: Если файл лежит в папке api/, используйте ../ для выхода на уровень выше
require_once '../config.php';
require_once '../weather_api.php';

// 2. Устанавливаем заголовок, что мы отдаем JSON
header('Content-Type: application/json');

// 3. Получаем действие из GET-запроса
$action = $_GET['action'] ?? '';

try {
    if ($action === 'autocomplete') {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            echo json_encode([]);
            exit;
        }

        // Вызываем функцию из присланного вами файла
        $results = autocompleteCity($q);
        echo json_encode($results);
        exit;
    }

    if ($action === 'weather') {
        $lat   = (float)($_GET['lat'] ?? 0);
        $lon   = (float)($_GET['lon'] ?? 0);
        $city  = $_GET['city'] ?? '';
        $units = $_GET['units'] ?? 'metric';

        // Инициализируем PDO для работы кеша (если нужно)
        // В данном случае можно вызвать напрямую из weather_api
        $data = getWeatherFull($pdo, $lat, $lon, $city, $units);
        echo json_encode($data);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}