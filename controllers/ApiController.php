<?php
class ApiController {
    private $siteService;
    private $articleService;
    
    public function __construct() {
        $this->siteService = new SiteService();
        $this->articleService = new ArticleService();
    }
    
    public function handleRequest() {
        $this->setHeaders();
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
        
        try {
            $input = $this->parseInput();
            $action = $input['action'] ?? '';
            
            switch ($action) {
                case 'get_sites':
                    $this->sendResponse($this->siteService->getSites());
                    break;
                case 'get_site_data':
                    $this->validateRequired($input, ['site_id']);
                    $this->sendResponse($this->siteService->getSiteData($input['site_id']));
                    break;
                case 'analyze_sites':
                    $this->validateRequired($input, ['urls', 'ai_model']);
                    $this->sendResponse($this->siteService->analyzeSites($input['urls'], $input['ai_model']));
                    break;
                case 'create_article_outline':
                    $this->validateRequired($input, ['site_id', 'ai_model']);
                    $this->sendResponse($this->articleService->createArticleOutline($input['site_id'], $input['ai_model']));
                    break;
                case 'add_article_outline':
                    $this->validateRequired($input, ['site_id', 'ai_model']);
                    $this->sendResponse($this->articleService->addArticleOutline($input['site_id'], $input['ai_model']));
                    break;
                case 'generate_article':
                    $this->validateRequired($input, ['article_id', 'ai_model']);
                    $this->sendResponse($this->articleService->generateArticle($input['article_id'], $input['ai_model']));
                    break;
                case 'generate_all_articles':
                    $this->validateRequired($input, ['site_id', 'ai_model']);
                    $this->sendResponse($this->articleService->generateAllArticles($input['site_id'], $input['ai_model']));
                    break;
                case 'export_csv':
                    $this->validateRequired($input, ['site_id']);
                    $this->articleService->exportCsv($input['site_id']);
                    break;
                case 'update_publish_date':
                    $this->validateRequired($input, ['article_id', 'publish_date']);
                    $this->sendResponse($this->articleService->updatePublishDate($input['article_id'], $input['publish_date']));
                    break;
                default:
                    $this->sendResponse(['success' => false, 'error' => 'Invalid action']);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    private function setHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    
    private function parseInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON input');
        }
        
        return $input;
    }
    
    private function validateRequired($input, $required) {
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                throw new InvalidArgumentException("$field is required");
            }
        }
    }
    
    private function sendResponse($data) {
        ob_clean();
        echo json_encode($data);
        exit;
    }
    
    private function handleError(Exception $e) {
        error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        
        $errorType = get_class($e);
        $errorMessage = $e->getMessage();
        
        if ($e instanceof InvalidArgumentException) {
            $response = ['success' => false, 'error' => $errorMessage];
        } else {
            $response = ['success' => false, 'error' => 'Internal server error'];
        }
        
        $this->sendResponse($response);
    }
}