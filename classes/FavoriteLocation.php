<?php

class FavoriteLocation {
    private $conn;
    private $table = 'favorites';

    public $id;
    public $user_id;
    public $location_id;
    public $alias;
    public $sort_order;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ===== CREATE =====
    public function create() {
        $sql = 'INSERT INTO ' . $this->table . '
            (user_id, location_id, alias, sort_order)
            VALUES (:user_id, :location_id, :alias, :sort_order)';

        $stmt = $this->conn->prepare($sql);

        // Очистка текстовых данных
        $this->alias = htmlspecialchars(strip_tags($this->alias));

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->bindParam(':alias', $this->alias);
        $stmt->bindParam(':sort_order', $this->sort_order);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // ===== READ ALL for a user =====
    public function getAllByUser($user_id) {
        $sql = 'SELECT * FROM ' . $this->table . ' WHERE user_id = :user_id ORDER BY sort_order ASC';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
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
            $this->user_id = $row['user_id'];
            $this->location_id = $row['location_id'];
            $this->alias = $row['alias'];
            $this->sort_order = $row['sort_order'];
            return true;
        }
        return false;
    }

    // ===== UPDATE =====
    public function update() {
        $sql = 'UPDATE ' . $this->table . ' SET
            user_id = :user_id,
            location_id = :location_id,
            alias = :alias,
            sort_order = :sort_order
            WHERE id = :id';

        $stmt = $this->conn->prepare($sql);

        $this->alias = htmlspecialchars(strip_tags($this->alias));

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->bindParam(':alias', $this->alias);
        $stmt->bindParam(':sort_order', $this->sort_order);
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
