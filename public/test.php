<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Test endpoint working',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
]);
?> 