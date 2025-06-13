<?php
// Simple test script for POST requests
header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Don't read input, just respond
        echo json_encode([
            'success' => true,
            'method' => $method,
            'message' => 'POST request received',
            'timestamp' => date('c')
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'method' => $method,
            'message' => 'Send a POST request to test'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
