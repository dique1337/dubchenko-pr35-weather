<?php
session_start();
// Исправлено: заменяем db_connect.php на config.php
require 'config.php';
require_once 'logger.php';

// 1. Фиксируем выход, пока данные сессии еще доступны
if (isset($_SESSION['user_id'])) {
    // Используем $pdo, который определен внутри config.php
    logSecurityEvent($pdo, 'logout', $_SESSION['user_id'], 'Пользователь вышел из системы');
}

// 2. Очищаем сессию
$_SESSION = array();
session_destroy();

// 3. Перенаправляем
header('Location: login.php');
exit;
?>