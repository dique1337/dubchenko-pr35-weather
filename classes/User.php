<?php
class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $login;
    public $password_hash;
    public $is_active;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }
    public function create() {
        $sql = 'INSERT INTO ' . $this->table . '
            (login, password_hash, is_active, created_at, updated_at)
            VALUES (:login, :password_hash, :is_active, :created_at, :updated_at)';

        $stmt = $this->conn->prepare($sql);

        $this->login = htmlspecialchars(strip_tags($this->login));
        $this->password_hash = htmlspecialchars(strip_tags($this->password_hash));
        $this->is_active = htmlspecialchars(strip_tags($this->is_active));

        $stmt->bindParam(':login', $this->login);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':created_at', $this->created_at);
        $stmt->bindParam(':updated_at', $this->updated_at);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getAll() {
        $sql = 'SELECT * FROM ' . $this->table . ' ORDER BY created_at DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id) {
        $sql = 'SELECT * FROM ' . $this->table . ' WHERE id = :id LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->login = $row['login'];
            $this->password_hash = $row['password_hash'];
            $this->is_active = $row['is_active'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    public function update() {
        $sql = 'UPDATE ' . $this->table . ' SET
            login = :login,
            password_hash = :password_hash,
            is_active = :is_active,
            updated_at = :updated_at
            WHERE id = :id';

        $stmt = $this->conn->prepare($sql);

        $this->login = htmlspecialchars(strip_tags($this->login));
        $this->password_hash = htmlspecialchars(strip_tags($this->password_hash));
        $this->is_active = htmlspecialchars(strip_tags($this->is_active));

        $stmt->bindParam(':login', $this->login);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':updated_at', $this->updated_at);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function delete($id) {
        $sql = 'DELETE FROM ' . $this->table . ' WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
