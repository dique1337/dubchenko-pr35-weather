<?php

class WeatherData {
    private $conn;
    private $table = 'weather_data';

    // Свойства = столбцы таблицы weather_data
    public $id;
    public $location_id;
    public $temperature;
    public $humidity;
    public $pressure;
    public $precipitation;
    public $weather_type;
    public $timestamp_utc;
    public $source;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ===== CREATE =====
    public function create() {
        $sql = 'INSERT INTO ' . $this->table
            . ' (location_id, temperature, humidity, pressure, precipitation, weather_type, timestamp_utc, source)'
            . ' VALUES (:location_id, :temperature, :humidity, :pressure, :precipitation, :weather_type, :timestamp_utc, :source)';

        $stmt = $this->conn->prepare($sql);

        // Очистка текстовых данных
        $this->weather_type = htmlspecialchars(strip_tags($this->weather_type));
        $this->source = htmlspecialchars(strip_tags($this->source));

        // Привязка параметров
        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->bindParam(':temperature', $this->temperature);
        $stmt->bindParam(':humidity', $this->humidity);
        $stmt->bindParam(':pressure', $this->pressure);
        $stmt->bindParam(':precipitation', $this->precipitation);
        $stmt->bindParam(':weather_type', $this->weather_type);
        $stmt->bindParam(':timestamp_utc', $this->timestamp_utc);
        $stmt->bindParam(':source', $this->source);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // ===== READ ALL =====
    public function getAll() {
        $sql = 'SELECT * FROM ' . $this->table . ' ORDER BY timestamp_utc DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    // ===== READ ONE =====
    public function getById($id) {
        $sql = 'SELECT * FROM ' . $this->table . ' WHERE id = :id LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->location_id = $row['location_id'];
            $this->temperature = $row['temperature'];
            $this->humidity = $row['humidity'];
            $this->pressure = $row['pressure'];
            $this->precipitation = $row['precipitation'];
            $this->weather_type = $row['weather_type'];
            $this->timestamp_utc = $row['timestamp_utc'];
            $this->source = $row['source'];
            return true;
        }
        return false;
    }

    // ===== UPDATE =====
    public function update() {
        $sql = 'UPDATE ' . $this->table . ' SET
            location_id = :location_id,
            temperature = :temperature,
            humidity = :humidity,
            pressure = :pressure,
            precipitation = :precipitation,
            weather_type = :weather_type,
            timestamp_utc = :timestamp_utc,
            source = :source
            WHERE id = :id';

        $stmt = $this->conn->prepare($sql);

        $this->weather_type = htmlspecialchars(strip_tags($this->weather_type));
        $this->source = htmlspecialchars(strip_tags($this->source));

        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->bindParam(':temperature', $this->temperature);
        $stmt->bindParam(':humidity', $this->humidity);
        $stmt->bindParam(':pressure', $this->pressure);
        $stmt->bindParam(':precipitation', $this->precipitation);
        $stmt->bindParam(':weather_type', $this->weather_type);
        $stmt->bindParam(':timestamp_utc', $this->timestamp_utc);
        $stmt->bindParam(':source', $this->source);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    // ===== DELETE =====
    public function delete($id) {
        $sql = 'DELETE FROM ' . $this->table . ' WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
