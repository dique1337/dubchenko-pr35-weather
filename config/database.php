<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'weather';  // <-- ЗАМЕНИТЬ
    private $username = 'root';
    private $password = '';           // <-- ЗАМЕНИТЬ
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host
                . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );
            $this->conn->exec('SET NAMES utf8');
        } catch(PDOException $e) {
            echo 'Ошибка: ' . $e->getMessage();
        }
        return $this->conn;
    }
}
