<?php
class WeatherHistory {
    private $conn;
    private $table = 'weather_history';

    public $id;
    public $location_id;
    public $parameter;
    public $value;
    public $timestamp_utc;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ===== CREATE =====
    public function create() {
        $sql = 'INSERT INTO ' . $this->table . '
            (location_id, parameter, value, timestamp_utc)
            VALUES (:location_id, :parameter, :value, :timestamp_utc)';

        $stmt = $this->conn->prepare($sql);

        // Очистка текстовых данных
        $this->parameter = htmlspecialchars(strip_tags($this->parameter));

        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->bindParam(':parameter', $this->parameter);
        $stmt->bindParam(':value', $this->value);
        $stmt->bindParam(':timestamp_utc', $this->timestamp_utc);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // ===== READ ALL for a location =====
    public function getAllByLocation($location_id) {
        $sql = 'SELECT * FROM ' . $this->table . ' WHERE location_id = :location_id ORDER BY timestamp_utc DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':location_id', $location_id);
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
            $this->parameter = $row['parameter'];
            $this->value = $row['value'];
            $this->timestamp_utc = $row['timestamp_utc'];
            return true;
        }
        return false;
    }

    // ===== DELETE =====
    public function delete($id) {
        $sql = 'DELETE FROM ' . $this->table . ' WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
