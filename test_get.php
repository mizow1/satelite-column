<?php
// GETリクエストテスト用
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'unknown';
$response = [
    'success' => true,
    'message' => 'GET request successful',
    'action' => $action,
    'server_info' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'php_version' => phpversion(),
        'timestamp' => date('Y-m-d H:i:s')
    ]
];

echo json_encode($response);
?>