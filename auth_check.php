<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Перенаправляем на логин, если не авторизован
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Генерация CSRF-токена, если его еще нет
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// (Опционально) Принудительный HTTPS в OpenServer
/*
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
*/