<?php
// logger.php
function logSecurityEvent($pdo, $event_type, $user_id = null, $details = '') {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $user_id,
            $event_type,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $details
        ]);
    } catch (Exception $e) { /* silent */ }
}
