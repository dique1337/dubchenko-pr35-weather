<?php

class UserSettings {
    private $conn;
    private $table = 'user_settings';

    public $user_id;
    public $language;
    public $units;
    public $timezone;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ===== CREATE =====
    public function create() {
        $sql = 'INSERT INTO ' . $this->table . '
            (user_id, language, units, timezone)
            VALUES (:user_id, :language, :units, :timezone)';

        $stmt = $this->conn->prepare($sql);

        // Очистка данных
        $this->language = htmlspecialchars(strip_tags($this->language));
        $this->units = htmlspecialchars(strip_tags($this->units));
        $this->timezone = htmlspecialchars(strip_tags($this->timezone));

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':language', $this->language);
        $stmt->bindParam(':units', $this->units);
        $stmt->bindParam(':timezone', $this->timezone);

        return $stmt->execute();
    }

    // ===== READ settings for a user =====
    public function getByUserId($user_id) {
        $sql = 'SELECT * FROM ' . $this->table . ' WHERE user_id = :user_id LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->user_id = $row['user_id'];
            $this->language = $row['language'];
            $this->units = $row['units'];
            $this->timezone = $row['timezone'];
            return true;
        }
        return false;
    }

    // ===== UPDATE =====
    public function update() {
        $sql = 'UPDATE ' . $this->table . ' SET
            language = :language,
            units = :units,
            timezone = :timezone
            WHERE user_id = :user_id';

        $stmt = $this->conn->prepare($sql);

        $this->language = htmlspecialchars(strip_tags($this->language));
        $this->units = htmlspecialchars(strip_tags($this->units));
        $this->timezone = htmlspecialchars(strip_tags($this->timezone));

        $stmt->bindParam(':language', $this->language);
        $stmt->bindParam(':units', $this->units);
        $stmt->bindParam(':timezone', $this->timezone);
        $stmt->bindParam(':user_id', $this->user_id);

        return $stmt->execute();
    }

    // ===== DELETE =====
    public function delete($user_id) {
        $sql = 'DELETE FROM ' . $this->table . ' WHERE user_id = :user_id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }
}
