<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

require_once 'config.php';
require_once 'ai_service.php';
require_once 'config/AppConfig.php';
require_once 'services/ContentProcessor.php';
require_once 'services/PromptGenerator.php';
require_once 'services/SiteService.php';
require_once 'services/ArticleService.php';
require_once 'controllers/ApiController.php';

try {
    if (!file_exists('config.php')) {
        throw new Exception('config.php not found');
    }
    if (!file_exists('ai_service.php')) {
        throw new Exception('ai_service.php not found');
    }
    
    $apiController = new ApiController();
    $apiController->handleRequest();
    
} catch (ParseError $e) {
    error_log('PHP Parse Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'PHP Parse Error: ' . $e->getMessage()]);
} catch (Error $e) {
    error_log('PHP Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'PHP Fatal Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>