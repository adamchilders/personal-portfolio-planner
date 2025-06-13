<?php
// Simple debug script to test POST requests
header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if ($method === 'POST') {
        // Don't try to read input, just return basic info
        echo json_encode([
            'success' => true,
            'method' => $method,
            'content_type' => $contentType,
            'post_data' => $_POST,
            'timestamp' => date('c'),
            'message' => 'POST request received successfully'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'method' => $method,
            'message' => 'Only POST requests are tested here'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
