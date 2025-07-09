<?php
// さくらレンタルサーバー対応のシンプルなAPIファイル
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 基本的なレスポンス関数
function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// メイン処理
try {
    $input = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                response(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // GETリクエストの場合はテスト用レスポンス
        $input = $_GET;
    }
    
    response([
        'success' => true,
        'message' => 'API is working',
        'method' => $_SERVER['REQUEST_METHOD'],
        'received_data' => $input
    ]);
    
} catch (Exception $e) {
    response(['success' => false, 'error' => $e->getMessage()], 500);
}
?>