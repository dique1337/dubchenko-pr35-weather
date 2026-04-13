<?php
// logout.php
session_start();
require 'config.php';
require_once 'logger.php';

if (isset($_SESSION['user_id'])) {
    logSecurityEvent($pdo, 'logout', $_SESSION['user_id'], 'Выход из системы');
}
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
