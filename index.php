<?php
// Стартуем сессию (нужно для совместимости, если сессии есть)
session_start();

// Всегда редиректим на login.php
header("Location: login.php");
exit;