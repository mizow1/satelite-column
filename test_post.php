<?php
// POSTリクエストテスト用（最小限）
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    $response = [
        'success' => true,
        'message' => 'POST request successful',
        'received_data' => $input,
        'raw_input_length' => strlen($rawInput),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
}
?>