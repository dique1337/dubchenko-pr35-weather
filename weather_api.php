<?php
// weather_api.php — вся логика получения погоды
require_once 'config.php';

/**
 * Запрос к OpenWeatherMap с кешированием в БД
 */
function owmRequest(string $url): ?array {
    $ctx = stream_context_create(['http' => [
        'timeout' => 5,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    return $data;
}

/**
 * Геокодирование: название города → lat/lon
 */
function geocodeCity(string $city): ?array {
    $url = OWM_GEO . 'direct?q=' . urlencode($city) . '&limit=1&lang=ru&appid=' . OWM_API_KEY;
    $res = owmRequest($url);
    if (empty($res[0])) return null;
    return [
        'lat'      => $res[0]['lat'],
        'lon'      => $res[0]['lon'],
        'name'     => $res[0]['local_names']['ru'] ?? $res[0]['name'],
        'country'  => $res[0]['country'] ?? '',
    ];
}

/**
 * Автодополнение городов
 */
function autocompleteCity(string $q): array {
    $url = OWM_GEO . 'direct?q=' . urlencode($q) . '&limit=5&lang=ru&appid=' . OWM_API_KEY;
    $res = owmRequest($url);
    if (!$res) return [];
    $out = [];
    foreach ($res as $r) {
        $out[] = [
            'name'    => $r['local_names']['ru'] ?? $r['name'],
            'country' => $r['country'] ?? '',
            'lat'     => $r['lat'],
            'lon'     => $r['lon'],
        ];
    }
    return $out;
}

/**
 * Полная погода для города (с кешем)
 */
function getWeatherFull($pdo, float $lat, float $lon, string $cityName, string $units = 'metric'): ?array {
    global $pdo;
    $cacheKey = round($lat,3) . '_' . round($lon,3) . '_' . $units;

    // Проверяем кеш
    $stmt = $pdo->prepare("SELECT data, expires_at FROM weather_cache WHERE city_key = ?");
    $stmt->execute([$cacheKey]);
    $cached = $stmt->fetch();
    if ($cached && strtotime($cached['expires_at']) > time()) {
        $d = json_decode($cached['data'], true);
        $d['from_cache'] = true;
        return $d;
    }

    // Текущая погода
    $urlCurrent = OWM_BASE . "weather?lat=$lat&lon=$lon&units=$units&lang=ru&appid=" . OWM_API_KEY;
    $current = owmRequest($urlCurrent);
    if (!$current || isset($current['cod']) && $current['cod'] != 200) return null;

    // Прогноз 5 дней / каждые 3 часа
    $urlForecast = OWM_BASE . "forecast?lat=$lat&lon=$lon&units=$units&lang=ru&cnt=40&appid=" . OWM_API_KEY;
    $forecast = owmRequest($urlForecast);

    // UV index (через One Call — используем текущую pogodu)
    $urlOneCall = "https://api.openweathermap.org/data/3.0/onecall?lat=$lat&lon=$lon&units=$units&lang=ru&exclude=minutely,alerts&appid=" . OWM_API_KEY;
    $onecall = owmRequest($urlOneCall);

    // Качество воздуха
    $urlAir = OWM_AIR . "?lat=$lat&lon=$lon&appid=" . OWM_API_KEY;
    $air = owmRequest($urlAir);
    $aqi = $air['list'][0]['main']['aqi'] ?? null;
    $aqiLabels = [1=>'Хорошее',2=>'Удовлетворительное',3=>'Умеренное',4=>'Плохое',5=>'Очень плохое'];

    // Собираем почасовой прогноз
    $hourly = [];
    if ($forecast && isset($forecast['list'])) {
        foreach (array_slice($forecast['list'], 0, 16) as $h) {
            $hourly[] = [
                'time'      => $h['dt'],
                'temp'      => round($h['main']['temp']),
                'feels'     => round($h['main']['feels_like']),
                'humidity'  => $h['main']['humidity'],
                'pressure'  => $h['main']['pressure'],
                'wind'      => round($h['wind']['speed'], 1),
                'icon'      => $h['weather'][0]['icon'],
                'desc'      => $h['weather'][0]['description'],
                'pop'       => isset($h['pop']) ? round($h['pop']*100) : 0,
                'rain'      => $h['rain']['3h'] ?? 0,
                'snow'      => $h['snow']['3h'] ?? 0,
            ];
        }
    }

    // Прогноз на 7 дней (группируем по дням)
    $daily = [];
    if ($forecast && isset($forecast['list'])) {
        $byDay = [];
        foreach ($forecast['list'] as $h) {
            $day = date('Y-m-d', $h['dt']);
            $byDay[$day][] = $h;
        }
        foreach ($byDay as $day => $items) {
            $temps = array_column(array_column($items,'main'),'temp');
            $icons = array_column(array_column($items,'weather'),'0');
            $daily[] = [
                'date'     => $day,
                'temp_min' => round(min($temps)),
                'temp_max' => round(max($temps)),
                'icon'     => $icons[count($icons)>>1]['icon'] ?? '01d',
                'desc'     => $icons[count($icons)>>1]['description'] ?? '',
                'pop'      => round(max(array_column($items,'pop')) * 100),
            ];
        }
    }

    // UV из onecall если доступен
    $uvIndex = $onecall['current']['uvi'] ?? null;

    $result = [
        'city'        => $cityName,
        'country'     => $current['sys']['country'] ?? '',
        'lat'         => $lat,
        'lon'         => $lon,
        'timezone'    => $current['timezone'] ?? 0,
        'units'       => $units,
        'updated_at'  => time(),
        'from_cache'  => false,
        'current'     => [
            'temp'        => round($current['main']['temp']),
            'feels_like'  => round($current['main']['feels_like']),
            'temp_min'    => round($current['main']['temp_min']),
            'temp_max'    => round($current['main']['temp_max']),
            'humidity'    => $current['main']['humidity'],
            'pressure'    => $current['main']['pressure'],
            'wind_speed'  => round($current['wind']['speed'], 1),
            'wind_deg'    => $current['wind']['deg'] ?? 0,
            'visibility'  => isset($current['visibility']) ? round($current['visibility']/1000,1) : null,
            'clouds'      => $current['clouds']['all'] ?? 0,
            'icon'        => $current['weather'][0]['icon'] ?? '01d',
            'desc'        => $current['weather'][0]['description'] ?? '',
            'sunrise'     => $current['sys']['sunrise'] ?? null,
            'sunset'      => $current['sys']['sunset'] ?? null,
            'uv_index'    => $uvIndex,
            'rain_1h'     => $current['rain']['1h'] ?? 0,
            'snow_1h'     => $current['snow']['1h'] ?? 0,
            'aqi'         => $aqi,
            'aqi_label'   => $aqiLabels[$aqi] ?? null,
        ],
        'hourly' => $hourly,
        'daily'  => $daily,
    ];

    // Сохраняем в кеш
    $expiresAt = date('Y-m-d H:i:s', time() + CACHE_TTL);
    $pdo->prepare(
        "INSERT INTO weather_cache (city_key,city_name,lat,lon,data,fetched_at,expires_at)
         VALUES (?,?,?,?,?,NOW(),?)
         ON DUPLICATE KEY UPDATE data=VALUES(data), fetched_at=NOW(), expires_at=VALUES(expires_at)"
    )->execute([$cacheKey, $cityName, $lat, $lon, json_encode($result), $expiresAt]);

    return $result;
}

/**
 * Иконка OWM → emoji
 */
function iconToEmoji(string $icon): string {
    $map = [
        '01d'=>'☀️','01n'=>'🌙','02d'=>'⛅','02n'=>'🌙',
        '03d'=>'☁️','03n'=>'☁️','04d'=>'☁️','04n'=>'☁️',
        '09d'=>'🌧️','09n'=>'🌧️','10d'=>'🌦️','10n'=>'🌧️',
        '11d'=>'⛈️','11n'=>'⛈️','13d'=>'❄️','13n'=>'❄️',
        '50d'=>'🌫️','50n'=>'🌫️',
    ];
    return $map[$icon] ?? '🌡️';
}

/**
 * Направление ветра
 */
function windDirection(int $deg): string {
    $dirs = ['С','СВ','В','ЮВ','Ю','ЮЗ','З','СЗ'];
    return $dirs[round($deg/45) % 8];
}
