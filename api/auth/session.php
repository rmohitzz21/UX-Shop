<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'success',
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ]
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authenticated'
    ]);
}
?>
