<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Регистрация нового пользователя
    public function register($login, $email, $password) {
        // Хешируем пароль (НИКОГДА не сохраняем в открытом виде!)
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (login, email, password) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$login, $email, $hash]);
    }

    // Поиск пользователя по email (для входа — ПР45)
    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    // Проверка: существует ли email (для валидации)
    public function emailExists($email) {
        $user = $this->findByEmail($email);
        return $user !== false;
    }
}
