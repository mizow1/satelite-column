<?php
// さくらレンタルサーバー対応
if (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['X-Forwarded-Proto']) && $headers['X-Forwarded-Proto'] === 'https') {
        $_SERVER['HTTPS'] = 'on';
    }
}

// エラー出力設定
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// さくらレンタルサーバー用のセキュリティ設定
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

// PHP設定を動的に変更（.htaccessで設定できない場合の代替）
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '256M');
ini_set('post_max_size', '32M');
ini_set('upload_max_filesize', '32M');

// 出力バッファリングを開始
ob_start();

// デバッグ用: HTTPステータスとヘッダーを明示的に設定
// さくらレンタルサーバーでは、ヘッダーの設定順序が重要
header('HTTP/1.1 200 OK');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 200 OK');
    exit(0);
}

// JSONレスポンスを送信する関数
function sendJsonResponse($data) {
    // 出力バッファをクリア
    ob_clean();
    
    // レスポンスヘッダーを再設定
    header('HTTP/1.1 200 OK');
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode($data);
    exit;
}

try {
    // デバッグ用: リクエスト情報をログに記録（本番環境では無効化）
    
    // 必要なファイルをインクルード
    if (!file_exists('config.php')) {
        sendJsonResponse(['success' => false, 'error' => 'config.php not found']);
    }
    if (!file_exists('ai_service.php')) {
        sendJsonResponse(['success' => false, 'error' => 'ai_service.php not found']);
    }
    
    define('INCLUDED_FROM_API', true);
    
    // デバッグ用: ファイルの存在確認（本番環境では無効化）
    
    require_once 'config.php';
    require_once 'ai_service.php';
    require_once 'auth.php';
    
    $input = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        
        if (!empty($rawInput)) {
            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                sendJsonResponse(['success' => false, 'error' => 'Invalid JSON input: ' . json_last_error_msg()]);
            }
        } else {
            sendJsonResponse(['success' => false, 'error' => 'No input data received']);
        }
    } else {
        sendJsonResponse(['success' => false, 'error' => 'Only POST requests are supported']);
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'test':
            sendJsonResponse(['success' => true, 'message' => 'Main API is working', 'input' => $input]);
            break;
        case 'login':
            if (!isset($input['email']) || !isset($input['password'])) {
                sendJsonResponse(['success' => false, 'error' => 'email and password are required']);
            }
            try {
                $auth = new AuthService();
                $user = $auth->login($input['email'], $input['password']);
                $sessionId = $auth->startSession($user['id']);
                sendJsonResponse(['success' => true, 'user' => $user, 'session_id' => $sessionId]);
            } catch (Exception $e) {
                sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        case 'register':
            if (!isset($input['email']) || !isset($input['password'])) {
                sendJsonResponse(['success' => false, 'error' => 'email and password are required']);
            }
            try {
                $auth = new AuthService();
                $userId = $auth->register($input['email'], $input['password']);
                sendJsonResponse(['success' => true, 'user_id' => $userId]);
            } catch (Exception $e) {
                sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        case 'check_session':
            try {
                $auth = new AuthService();
                $userId = $auth->checkSession();
                if ($userId) {
                    $user = $auth->getUser($userId);
                    sendJsonResponse(['success' => true, 'user' => $user]);
                } else {
                    sendJsonResponse(['success' => false, 'error' => 'No valid session']);
                }
            } catch (Exception $e) {
                sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        case 'logout':
            try {
                $auth = new AuthService();
                $auth->logout();
                sendJsonResponse(['success' => true, 'message' => 'Logged out successfully']);
            } catch (Exception $e) {
                sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        case 'get_sites':
            sendJsonResponse(getSites());
            break;
        case 'get_site_data':
            if (!isset($input['site_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id is required']);
            }
            sendJsonResponse(getSiteData($input['site_id']));
            break;
        case 'analyze_sites':
            if (!isset($input['urls']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'urls and ai_model are required']);
            }
            sendJsonResponse(analyzeSites($input['urls'], $input['ai_model']));
            break;
        case 'analyze_sites_group':
            if (!isset($input['urls']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'urls and ai_model are required']);
            }
            sendJsonResponse(analyzeSitesGroup($input['urls'], $input['ai_model'], $input['group_index'] ?? 1, $input['total_groups'] ?? 1));
            break;
        case 'integrate_analyses':
            if (!isset($input['analyses']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'analyses and ai_model are required']);
            }
            sendJsonResponse(integrateAnalyses($input['analyses'], $input['ai_model'], $input['total_urls'] ?? 0, $input['base_url'] ?? ''));
            break;
        case 'create_article_outline':
            if (!isset($input['site_id']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id and ai_model are required']);
            }
            sendJsonResponse(createArticleOutline($input['site_id'], $input['ai_model']));
            break;
        case 'add_article_outline':
            if (!isset($input['site_id']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id and ai_model are required']);
            }
            $count = isset($input['count']) ? intval($input['count']) : 10;
            sendJsonResponse(addArticleOutline($input['site_id'], $input['ai_model'], $count));
            break;
        case 'generate_article':
            if (!isset($input['article_id']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'article_id and ai_model are required']);
            }
            sendJsonResponse(generateArticle($input['article_id'], $input['ai_model']));
            break;
        case 'generate_all_articles':
            if (!isset($input['site_id']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id and ai_model are required']);
            }
            sendJsonResponse(generateAllArticles($input['site_id'], $input['ai_model']));
            break;
        case 'export_csv':
            if (!isset($input['site_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id is required']);
            }
            exportCsv($input['site_id']);
            break;
        case 'update_publish_date':
            if (!isset($input['article_id']) || !isset($input['publish_date'])) {
                sendJsonResponse(['success' => false, 'error' => 'article_id and publish_date are required']);
            }
            sendJsonResponse(updatePublishDate($input['article_id'], $input['publish_date']));
            break;
        case 'crawl_site_urls':
            if (!isset($input['base_url'])) {
                sendJsonResponse(['success' => false, 'error' => 'base_url is required']);
            }
            sendJsonResponse(crawlSiteUrls($input['base_url']));
            break;
        case 'save_reference_urls':
            if (!isset($input['site_id']) || !isset($input['urls'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id and urls are required']);
            }
            sendJsonResponse(saveReferenceUrls($input['site_id'], $input['urls']));
            break;
        case 'get_reference_urls':
            if (!isset($input['site_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id is required']);
            }
            sendJsonResponse(getReferenceUrls($input['site_id']));
            break;
        case 'get_ai_usage_logs':
            $siteId = $input['site_id'] ?? null;
            $limit = $input['limit'] ?? 50;
            $offset = $input['offset'] ?? 0;
            sendJsonResponse(getAiUsageLogs($siteId, $limit, $offset));
            break;
        case 'get_multilingual_settings':
            if (!isset($input['site_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id is required']);
            }
            sendJsonResponse(getMultilingualSettings($input['site_id']));
            break;
        case 'update_multilingual_settings':
            if (!isset($input['site_id']) || !isset($input['settings'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id and settings are required']);
            }
            sendJsonResponse(updateMultilingualSettings($input['site_id'], $input['settings']));
            break;
        case 'update_site_policy':
            if (!isset($input['site_id']) || !isset($input['policy'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id and policy are required']);
            }
            sendJsonResponse(updateSitePolicy($input['site_id'], $input['policy']));
            break;
        case 'generate_multilingual_articles':
            if (!isset($input['site_id']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id and ai_model are required']);
            }
            sendJsonResponse(generateMultilingualArticles($input['site_id'], $input['ai_model']));
            break;
        case 'generate_multilingual_articles_with_progress':
            if (!isset($input['site_id']) || !isset($input['ai_model'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id and ai_model are required']);
            }
            sendJsonResponse(startMultilingualArticleGeneration($input['site_id'], $input['ai_model']));
            break;
        case 'get_multilingual_articles':
            if (!isset($input['site_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id is required']);
            }
            $languageCode = $input['language_code'] ?? null;
            sendJsonResponse(getMultilingualArticles($input['site_id'], $languageCode));
            break;
        case 'get_article_translations':
            if (!isset($input['article_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'article_id is required']);
            }
            sendJsonResponse(getArticleTranslations($input['article_id']));
            break;
        case 'get_article_with_translations':
            if (!isset($input['article_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'article_id is required']);
            }
            sendJsonResponse(getArticleWithTranslations($input['article_id']));
            break;
        case 'get_articles':
            if (!isset($input['site_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id is required']);
            }
            sendJsonResponse(getArticles($input['site_id']));
            break;
        case 'get_translation_progress':
            if (!isset($input['progress_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'progress_id is required']);
            }
            sendJsonResponse(getTranslationProgress($input['progress_id']));
            break;
        case 'execute_multilingual_generation':
            if (!isset($input['site_id']) || !isset($input['ai_model']) || !isset($input['progress_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id, ai_model, and progress_id are required']);
            }
            sendJsonResponse(executeMultilingualGeneration($input['site_id'], $input['ai_model'], $input['progress_id']));
            break;
        case 'generate_single_language_article':
            if (!isset($input['article_id']) || !isset($input['language_code'])) {
                sendJsonResponse(['success' => false, 'error' => 'article_id and language_code are required']);
            }
            $aiModel = $input['ai_model'] ?? 'gpt-4';
            
            try {
                error_log("Generating single language article - Article ID: " . $input['article_id'] . ", Language: " . $input['language_code']);
                $result = generateSingleLanguageArticle($input['article_id'], $input['language_code'], $aiModel);
                error_log("Single language article generation result: " . json_encode($result));
                sendJsonResponse($result);
            } catch (Exception $e) {
                error_log("generateSingleLanguageArticle error: " . $e->getMessage() . " - " . $e->getFile() . ":" . $e->getLine());
                sendJsonResponse(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
            }
            break;
        case 'get_site_data_with_translations':
            if (!isset($input['site_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id is required']);
            }
            sendJsonResponse(getSiteDataWithTranslations($input['site_id']));
            break;
        case 'get_all_sites':
            sendJsonResponse(getAllSites());
            break;
        case 'get_site_policy':
            if (!isset($input['site_id'])) {
                sendJsonResponse(['success' => false, 'error' => 'site_id is required']);
            }
            sendJsonResponse(getSitePolicy($input['site_id']));
            break;
        case 'delete_articles':
            if (!isset($input['article_ids']) || !is_array($input['article_ids'])) {
                sendJsonResponse(['success' => false, 'error' => 'article_ids array is required']);
            }
            error_log('Delete articles called with IDs: ' . json_encode($input['article_ids']));
            $result = deleteArticles($input['article_ids']);
            error_log('Delete articles result: ' . json_encode($result));
            sendJsonResponse($result);
            break;
        case 'update_article_field':
            if (!isset($input['article_id']) || !isset($input['field']) || !isset($input['value'])) {
                sendJsonResponse(['success' => false, 'error' => 'article_id, field, and value are required']);
            }
            sendJsonResponse(updateArticleField($input['article_id'], $input['field'], $input['value']));
            break;
        default:
            sendJsonResponse(['success' => false, 'error' => 'Invalid action']);
    }
} catch (ParseError $e) {
    error_log('PHP Parse Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'PHP Parse Error: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    error_log('PHP Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'PHP Fatal Error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

function getSites() {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->query("SELECT id, name, url, created_at FROM sites ORDER BY created_at DESC");
        $sites = $stmt->fetchAll();
        
        return ['success' => true, 'sites' => $sites];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getSiteData($siteId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // サイト情報取得
        $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found'];
        }
        
        // 記事一覧取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $articles = $stmt->fetchAll();
        
        return [
            'success' => true,
            'data' => [
                'site' => $site,
                'analysis' => $site['analysis_result'],
                'articles' => $articles
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function analyzeSites($urls, $aiModel) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // URLからサイト情報を取得
        $siteContents = [];
        foreach ($urls as $url) {
            $content = fetchWebContent($url);
            if ($content) {
                $siteContents[] = [
                    'url' => $url,
                    'content' => $content
                ];
            }
        }
        
        if (empty($siteContents)) {
            return ['success' => false, 'error' => 'No valid content found'];
        }
        
        // AIでサイト分析
        $aiService = new AIService();
        $analysisPrompt = createAnalysisPrompt($siteContents);
        
        $startTime = microtime(true);
        $analysis = $aiService->generateText($analysisPrompt, $aiModel);
        $processingTime = microtime(true) - $startTime;
        
        // サイト情報をDBに保存
        $siteName = extractSiteName($urls[0]);
        $stmt = $pdo->prepare("INSERT INTO sites (name, url, analysis_result, features, keywords) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $siteName,
            json_encode($urls),
            $analysis,
            '', // 後で更新
            ''  // 後で更新
        ]);
        
        $siteId = $pdo->lastInsertId();
        
        // AI使用ログを記録
        logAiUsage($siteId, null, $aiModel, 'site_analysis', $analysisPrompt, $analysis, $processingTime);
        
        // サイト分析履歴にも記録
        $stmt = $pdo->prepare("INSERT INTO site_analysis_history (site_id, ai_model, analysis_result) VALUES (?, ?, ?)");
        $stmt->execute([$siteId, $aiModel, $analysis]);
        
        return [
            'success' => true,
            'site_id' => $siteId,
            'analysis' => $analysis
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function analyzeSitesGroup($urls, $aiModel, $groupIndex, $totalGroups) {
    try {
        // URLからサイト情報を取得
        $siteContents = [];
        $failedUrls = [];
        
        foreach ($urls as $url) {
            $content = fetchWebContent($url);
            if ($content) {
                // HTMLからテキストを抽出
                $textContent = extractTextFromHtml($content);
                if (!empty($textContent) && strlen(trim($textContent)) > 100) {
                    $siteContents[] = [
                        'url' => $url,
                        'content' => substr($textContent, 0, 3000) // 長すぎる場合は切り詰め
                    ];
                } else {
                    $failedUrls[] = $url . ' (insufficient content)';
                }
            } else {
                $failedUrls[] = $url . ' (fetch failed)';
            }
        }
        
        // ログ出力（本番環境では無効化）
        
        // 有効なコンテンツが1つもない場合は空の分析結果を返す
        if (empty($siteContents)) {
            return [
                'success' => true,
                'analysis' => "このグループのURLからは有効なコンテンツを取得できませんでした。",
                'group_index' => $groupIndex,
                'total_groups' => $totalGroups,
                'processed_urls' => 0,
                'failed_urls' => count($failedUrls)
            ];
        }
        
        // AIでサイト分析
        $aiService = new AIService();
        $analysisPrompt = createAnalysisPrompt($siteContents);
        
        $startTime = microtime(true);
        $analysis = $aiService->generateText($analysisPrompt, $aiModel);
        $processingTime = microtime(true) - $startTime;
        
        return [
            'success' => true,
            'analysis' => $analysis,
            'group_index' => $groupIndex,
            'total_groups' => $totalGroups,
            'processed_urls' => count($siteContents),
            'failed_urls' => count($failedUrls)
        ];
    } catch (Exception $e) {
        error_log("Error in analyzeSitesGroup: " . $e->getMessage());
        return [
            'success' => true,
            'analysis' => "このグループの分析中にエラーが発生しました：" . $e->getMessage(),
            'group_index' => $groupIndex,
            'total_groups' => $totalGroups,
            'processed_urls' => 0,
            'failed_urls' => count($urls)
        ];
    }
}

function integrateAnalyses($analyses, $aiModel, $totalUrls, $baseUrl) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 有効な分析結果のみを抽出
        $validAnalyses = array_filter($analyses, function($analysis) {
            return !empty(trim($analysis)) && 
                   !strpos($analysis, 'エラーが発生しました') && 
                   !strpos($analysis, '有効なコンテンツを取得できませんでした');
        });
        
        if (empty($validAnalyses)) {
            // 有効な分析結果がない場合は基本的なサイト情報を作成
            $siteName = extractSiteName($baseUrl);
            $basicAnalysis = "# サイト分析結果\n\n";
            $basicAnalysis .= "**サイト名**: {$siteName}\n";
            $basicAnalysis .= "**URL**: {$baseUrl}\n\n";
            $basicAnalysis .= "**注意**: このサイトの詳細な分析は、コンテンツの取得に問題があったため実行できませんでした。\n";
            $basicAnalysis .= "手動でサイトの特徴を確認して記事作成を進めてください。\n";
            
            $stmt = $pdo->prepare("INSERT INTO sites (name, url, analysis_result, features, keywords) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $siteName,
                $baseUrl,
                $basicAnalysis,
                '',
                ''
            ]);
            
            $siteId = $pdo->lastInsertId();
            
            return [
                'success' => true,
                'site_id' => $siteId,
                'analysis' => $basicAnalysis
            ];
        }
        
        // 複数の分析結果を統合するプロンプトを作成
        $integrationPrompt = "以下は同一サイトの複数のページを分析した結果です。これらを統合して、サイト全体の包括的な分析結果を作成してください。\n\n";
        
        foreach ($validAnalyses as $index => $analysis) {
            $integrationPrompt .= "=== 分析結果 " . ($index + 1) . " ===\n";
            $integrationPrompt .= $analysis . "\n\n";
        }
        
        $integrationPrompt .= "統合の要件：\n";
        $integrationPrompt .= "- 各分析結果の共通点と相違点を整理\n";
        $integrationPrompt .= "- サイト全体の特徴とテーマを明確化\n";
        $integrationPrompt .= "- 矛盾する情報は適切に統合または除外\n";
        $integrationPrompt .= "- 最終的にマークダウン形式で出力\n";
        
        // AIで統合分析
        $aiService = new AIService();
        $startTime = microtime(true);
        $integratedAnalysis = $aiService->generateText($integrationPrompt, $aiModel);
        $processingTime = microtime(true) - $startTime;
        
        // サイト情報をDBに保存
        $siteName = extractSiteName($baseUrl);
        $stmt = $pdo->prepare("INSERT INTO sites (name, url, analysis_result, features, keywords) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $siteName,
            $baseUrl,
            $integratedAnalysis,
            '',
            ''
        ]);
        
        $siteId = $pdo->lastInsertId();
        
        // AI使用ログを記録
        logAiUsage($siteId, null, $aiModel, 'site_analysis_integration', $integrationPrompt, $integratedAnalysis, $processingTime);
        
        // サイト分析履歴にも記録
        $stmt = $pdo->prepare("INSERT INTO site_analysis_history (site_id, ai_model, analysis_result) VALUES (?, ?, ?)");
        $stmt->execute([$siteId, $aiModel, $integratedAnalysis]);
        
        return [
            'success' => true,
            'site_id' => $siteId,
            'analysis' => $integratedAnalysis
        ];
    } catch (Exception $e) {
        error_log("Error in integrateAnalyses: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createArticleOutline($siteId, $aiModel) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // サイト情報取得
        $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found'];
        }
        
        // 記事概要生成
        $aiService = new AIService();
        $outlinePrompt = createOutlinePrompt($site['analysis_result']);
        
        $startTime = microtime(true);
        $outlineData = $aiService->generateText($outlinePrompt, $aiModel);
        $processingTime = microtime(true) - $startTime;
        
        // AI使用ログを記録
        logAiUsage($siteId, null, $aiModel, 'article_outline', $outlinePrompt, $outlineData, $processingTime);
        
        // 記事概要をパース
        $articles = parseArticleOutline($outlineData);
        
        // DBに保存
        $stmt = $pdo->prepare("INSERT INTO articles (site_id, title, seo_keywords, summary, ai_model, status) VALUES (?, ?, ?, ?, ?, 'draft')");
        
        foreach ($articles as $article) {
            $stmt->execute([
                $siteId,
                $article['title'],
                $article['keywords'],
                $article['summary'],
                $aiModel
            ]);
        }
        
        // 保存された記事一覧を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $savedArticles = $stmt->fetchAll();
        
        return [
            'success' => true,
            'articles' => $savedArticles
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function generateArticle($articleId, $aiModel) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 記事情報取得
        $stmt = $pdo->prepare("SELECT a.*, s.analysis_result FROM articles a JOIN sites s ON a.site_id = s.id WHERE a.id = ?");
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();
        
        if (!$article) {
            return ['success' => false, 'error' => 'Article not found'];
        }
        
        // 記事生成
        $aiService = new AIService();
        $articlePrompt = createArticlePrompt($article);
        
        $startTime = microtime(true);
        $content = $aiService->generateText($articlePrompt, $aiModel);
        $processingTime = microtime(true) - $startTime;
        
        if (empty($content)) {
            return ['success' => false, 'error' => 'AI service returned empty content'];
        }
        
        // 記事更新
        $stmt = $pdo->prepare("UPDATE articles SET content = ?, status = 'generated', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $articleId]);
        
        // AI使用ログを記録
        logAiUsage($article['site_id'], $articleId, $aiModel, 'article_generation', $articlePrompt, $content, $processingTime);
        
        // 生成ログ保存（従来の形式も保持）
        try {
            $stmt = $pdo->prepare("INSERT INTO ai_generation_logs (article_id, ai_model, prompt, response, generation_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $articleId,
                $aiModel,
                $articlePrompt,
                $content,
                $processingTime
            ]);
        } catch (Exception $e) {
            // ログ保存エラーは無視して処理を続行
        }
        
        // 更新された記事を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$articleId]);
        $updatedArticle = $stmt->fetch();
        
        return [
            'success' => true,
            'article' => $updatedArticle
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function generateAllArticles($siteId, $aiModel) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 下書き記事一覧取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? AND status = 'draft'");
        $stmt->execute([$siteId]);
        $draftArticles = $stmt->fetchAll();
        
        $aiService = new AIService();
        
        foreach ($draftArticles as $article) {
            $articlePrompt = createArticlePrompt($article);
            $content = $aiService->generateText($articlePrompt, $aiModel);
            
            // 記事更新
            $stmt = $pdo->prepare("UPDATE articles SET content = ?, status = 'generated', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$content, $article['id']]);
            
            // 生成ログ保存
            $stmt = $pdo->prepare("INSERT INTO ai_generation_logs (article_id, ai_model, prompt, response, generation_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $article['id'],
                $aiModel,
                $articlePrompt,
                $content,
                0
            ]);
            
            // タイムアウト対策のため少し待機
            usleep(100000); // 0.1秒待機
        }
        
        // 全記事取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $allArticles = $stmt->fetchAll();
        
        return [
            'success' => true,
            'articles' => $allArticles
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function exportCsv($siteId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 生成済み記事取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? AND status = 'generated' ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $articles = $stmt->fetchAll();
        
        if (empty($articles)) {
            echo json_encode(['success' => false, 'error' => 'No articles to export']);
            return;
        }
        
        // CSV生成
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="articles_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM追加
        fputs($output, "\xEF\xBB\xBF");
        
        // ヘッダー
        fputcsv($output, ['ID', 'タイトル', 'SEOキーワード', '概要', '記事内容', '投稿日時', '作成日']);
        
        // データ
        foreach ($articles as $article) {
            fputcsv($output, [
                $article['id'],
                $article['title'],
                $article['seo_keywords'],
                $article['summary'],
                $article['content'],
                $article['publish_date'],
                $article['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function addArticleOutline($siteId, $aiModel, $count = 10) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // サイト情報取得
        $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found'];
        }
        
        // 既存の記事数を確認
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE site_id = ?");
        $stmt->execute([$siteId]);
        $existingCount = $stmt->fetchColumn();
        
        // 既存記事のタイトル一覧を取得（重複チェック用）
        $stmt = $pdo->prepare("SELECT title FROM articles WHERE site_id = ?");
        $stmt->execute([$siteId]);
        $existingTitles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 記事概要を追加生成
        $aiService = new AIService();
        $outlinePrompt = createAdditionalOutlinePrompt($site['analysis_result'], $existingCount, $count);
        
        $startTime = microtime(true);
        $outlineData = $aiService->generateText($outlinePrompt, $aiModel);
        $processingTime = microtime(true) - $startTime;
        
        if (empty($outlineData)) {
            return ['success' => false, 'error' => 'AI service returned empty response'];
        }
        
        // AI使用ログを記録
        logAiUsage($siteId, null, $aiModel, 'additional_outline', $outlinePrompt, $outlineData, $processingTime);
        
        // 記事概要をパース
        $articles = parseArticleOutline($outlineData);
        
        if (empty($articles)) {
            return ['success' => false, 'error' => 'Failed to parse article outline'];
        }
        
        // DBに保存（重複チェック付き）
        $stmt = $pdo->prepare("INSERT INTO articles (site_id, title, seo_keywords, summary, ai_model, status) VALUES (?, ?, ?, ?, ?, 'draft')");
        $insertedCount = 0;
        $skippedTitles = [];
        
        foreach ($articles as $article) {
            if (empty($article['title']) || empty($article['keywords']) || empty($article['summary'])) {
                continue; // 不完全な記事データはスキップ
            }
            
            // 重複チェック（大文字小文字を区別せず、前後の空白も無視）
            $normalizedTitle = trim(strtolower($article['title']));
            $isDuplicate = false;
            
            foreach ($existingTitles as $existingTitle) {
                if ($normalizedTitle === trim(strtolower($existingTitle))) {
                    $isDuplicate = true;
                    break;
                }
            }
            
            if ($isDuplicate) {
                $skippedTitles[] = $article['title'];
                continue; // 重複する記事はスキップ
            }
            
            $stmt->execute([
                $siteId,
                $article['title'],
                $article['keywords'],
                $article['summary'],
                $aiModel
            ]);
            
            // 新たに追加したタイトルも既存リストに追加
            $existingTitles[] = $article['title'];
            $insertedCount++;
        }
        
        // 全記事一覧を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $allArticles = $stmt->fetchAll();
        
        $result = [
            'success' => true,
            'articles' => $allArticles,
            'inserted_count' => $insertedCount
        ];
        
        // スキップされた記事がある場合は情報を含める
        if (!empty($skippedTitles)) {
            $result['skipped_duplicates'] = $skippedTitles;
            $result['skipped_count'] = count($skippedTitles);
        }
        
        return $result;
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function fetchWebContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $content !== false) {
        return $content;
    }
    
    return null;
}

function extractTextFromHtml($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    return strip_tags($dom->textContent);
}

function extractSiteName($url) {
    $parsed = parse_url($url);
    return $parsed['host'] ?? 'Unknown Site';
}

function createAnalysisPrompt($siteContents) {
    $prompt = "以下のサイトの内容を分析して、このサイトに最適化されたコラム記事を作成するための特徴とキーワードを分析してください。\n\n";
    
    foreach ($siteContents as $site) {
        $prompt .= "URL: " . $site['url'] . "\n";
        $prompt .= "内容: " . substr($site['content'], 0, 2000) . "...\n\n";
    }
    
    $prompt .= "以下の観点で分析し、マークダウン形式で出力してください：\n";
    $prompt .= "1. サイトの特徴とテーマ\n";
    $prompt .= "2. ターゲット読者層と興味関心\n";
    $prompt .= "3. SEOに有効なキーワード（主要キーワード、関連キーワード、ロングテールキーワード）\n";
    $prompt .= "4. コンテンツの傾向とトーン\n";
    $prompt .= "5. 記事作成時の注意点\n";
    $prompt .= "6. 競合他社分析と差別化ポイント\n";
    $prompt .= "7. 検索意図と読者のニーズ\n";
    
    return $prompt;
}

function createOutlinePrompt($analysisResult) {
    $prompt = "以下のサイト分析結果を基に、コラム記事を100記事分作成してください。\n\n";
    $prompt .= "分析結果:\n" . $analysisResult . "\n\n";
    $prompt .= "以下の形式で、記事タイトル、SEOキーワード、記事概要をセットで100記事分出力してください：\n\n";
    $prompt .= "---記事1---\n";
    $prompt .= "タイトル: [記事タイトル]\n";
    $prompt .= "キーワード: [SEOキーワード（カンマ区切り）]\n";
    $prompt .= "概要: [記事の概要]\n\n";
    $prompt .= "（100記事まで繰り返し）\n";
    
    return $prompt;
}

function createAdditionalOutlinePrompt($analysisResult, $existingCount, $count = 10) {
    $prompt = "以下のサイト分析結果を基に、コラム記事を{$count}記事分作成してください。\n\n";
    $prompt .= "分析結果:\n" . $analysisResult . "\n\n";
    $prompt .= "以下の形式で、記事タイトル、SEOキーワード、記事概要をセットで{$count}記事分出力してください：\n\n";
    $prompt .= "---記事1---\n";
    $prompt .= "タイトル: [記事タイトル]\n";
    $prompt .= "キーワード: [SEOキーワード（カンマ区切り）]\n";
    $prompt .= "概要: [記事の概要]\n\n";
    $prompt .= "（{$count}記事まで繰り返し）\n";
    
    return $prompt;
}

function createArticlePrompt($article) {
    $prompt = "以下の記事概要を基に、ターゲット読者に最適化された詳細なコラム記事を作成してください。\n\n";
    $prompt .= "タイトル: " . $article['title'] . "\n";
    $prompt .= "SEOキーワード: " . $article['seo_keywords'] . "\n";
    $prompt .= "概要: " . $article['summary'] . "\n\n";
    $prompt .= "記事の要件：\n";
    $prompt .= "- 必ず10,000文字以上の詳細な記事を作成する（これは最重要要件です）\n";
    $prompt .= "- 完全なマークダウン形式で出力する\n";
    $prompt .= "- ターゲット読者が求める価値のある内容\n";
    $prompt .= "- SEOを意識したキーワードの自然な配置（キーワード密度2-3%程度）\n";
    $prompt .= "- 読みやすい構成（見出し、段落分け、箇条書き）\n";
    $prompt .= "- 具体的で実用的な内容\n";
    $prompt .= "- 深い洞察と詳細な解説\n";
    $prompt .= "- 例や事例を豊富に含む\n";
    $prompt .= "- 実践的なアドバイスとガイダンス\n";
    $prompt .= "- 読者が最後まで読み続けられる魅力的な内容\n";
    $prompt .= "- 各セクションごとに詳しい説明を含む\n";
    $prompt .= "- 検索意図を満たす包括的な情報\n";
    $prompt .= "- 読者が実際に活用できる具体的な方法を提示\n";
    $prompt .= "- 内部リンクの提案（関連記事の想定タイトル）\n";
    
    $prompt .= "\n記事構成の指針：\n";
    $prompt .= "1. 導入部：読者の関心を引く導入（問題提起、統計データ、興味深い事実）\n";
    $prompt .= "2. 基礎知識：テーマの基本的な説明（初心者にもわかりやすく）\n";
    $prompt .= "3. 詳細解説：具体的な内容の深掘り（専門性の高い情報）\n";
    $prompt .= "4. 実践的な応用：読者が実践できる方法（ステップバイステップ）\n";
    $prompt .= "5. 事例・体験談：具体的な例や体験談（信頼性の向上）\n";
    $prompt .= "6. よくある質問：FAQ形式で疑問を解決\n";
    $prompt .= "7. まとめ・次のステップ：総括と今後の展望\n";
    
    $prompt .= "\nSEO最適化の注意事項：\n";
    $prompt .= "- タイトルタグとして使用できる魅力的なH1を含める\n";
    $prompt .= "- メタディスクリプションとして使用できる要約を含める\n";
    $prompt .= "- 関連キーワードを自然に文章に組み込む\n";
    $prompt .= "- 読者の検索意図（情報収集、比較検討、購入意向）を意識した内容\n";
    $prompt .= "- E-A-T（専門性・権威性・信頼性）を意識した記述\n";
    
    $prompt .= "\n重要な注意事項：\n";
    $prompt .= "- 省略表現（「[以下、さらに詳細な解説と実践的なアドバイスが続きます...]」など）は絶対に使用しない\n";
    $prompt .= "- 「この記事の続きをご希望の場合」などの制作者向けメッセージは厳禁\n";
    $prompt .= "- 必ず完全な記事を作成し、最後まで詳細に執筆する\n";
    $prompt .= "- 記事内に文字数や文字数カウントは一切記載しない\n";
    $prompt .= "- 各セクションは充実した内容で執筆する\n";
    $prompt .= "- 独自性のある視点や情報を含める\n";
    
    return $prompt;
}

function parseArticleOutline($outlineData) {
    $articles = [];
    $lines = explode("\n", $outlineData);
    
    $currentArticle = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (strpos($line, '---記事') === 0) {
            if ($currentArticle) {
                $articles[] = $currentArticle;
            }
            $currentArticle = [];
        } elseif (strpos($line, 'タイトル:') === 0) {
            $currentArticle['title'] = trim(str_replace('タイトル:', '', $line));
        } elseif (strpos($line, 'キーワード:') === 0) {
            $currentArticle['keywords'] = trim(str_replace('キーワード:', '', $line));
        } elseif (strpos($line, '概要:') === 0) {
            $currentArticle['summary'] = trim(str_replace('概要:', '', $line));
        }
    }
    
    if ($currentArticle) {
        $articles[] = $currentArticle;
    }
    
    return $articles;
}

function sanitizeTextContent($text) {
    if (empty($text)) {
        return '';
    }
    
    // 元の文字エンコーディングを検出
    $encoding = mb_detect_encoding($text, ['UTF-8', 'Shift_JIS', 'EUC-JP', 'ISO-8859-1', 'Windows-1252'], true);
    
    // UTF-8に強制変換
    if ($encoding !== 'UTF-8') {
        $text = mb_convert_encoding($text, 'UTF-8', $encoding ?: 'auto');
    }
    
    // 不正なUTF-8文字を除去
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    
    // HTMLエンティティをデコード
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // 制御文字を除去（改行とタブとスペースは保持）
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    
    // 4バイト以上のUTF-8文字（絵文字など）を除去
    $text = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
    
    // 連続する空白文字を単一のスペースに変換
    $text = preg_replace('/\s+/', ' ', $text);
    
    // 最終的に有効なUTF-8かチェック
    if (!mb_check_encoding($text, 'UTF-8')) {
        // 無効な場合は安全な文字のみを抽出
        $text = preg_replace('/[^\x20-\x7E\x{3000}-\x{9FFF}\x{FF00}-\x{FFEF}]/u', '', $text);
    }
    
    // 前後の空白を削除
    return trim($text);
}

function updatePublishDate($articleId, $publishDate) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 記事の存在確認
        $stmt = $pdo->prepare("SELECT id FROM articles WHERE id = ?");
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();
        
        if (!$article) {
            return ['success' => false, 'error' => 'Article not found'];
        }
        
        // 投稿日時を更新
        $stmt = $pdo->prepare("UPDATE articles SET publish_date = ? WHERE id = ?");
        $stmt->execute([$publishDate, $articleId]);
        
        return ['success' => true, 'message' => 'Publish date updated successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function crawlSiteUrls($baseUrl) {
    try {
        $parsedBaseUrl = parse_url($baseUrl);
        if (!$parsedBaseUrl || !isset($parsedBaseUrl['host'])) {
            return ['success' => false, 'error' => 'Invalid URL'];
        }
        
        $domain = $parsedBaseUrl['host'];
        $scheme = $parsedBaseUrl['scheme'] ?? 'https';
        $basePath = rtrim($parsedBaseUrl['path'] ?? '/', '/') . '/';
        $baseUrlPrefix = $scheme . '://' . $domain . $basePath;
        
        $foundUrls = [];
        $visitedUrls = [];
        $urlsToVisit = [$baseUrl];
        $maxUrls = 100; // 最大取得数を制限
        
        while (!empty($urlsToVisit) && count($foundUrls) < $maxUrls) {
            $currentUrl = array_shift($urlsToVisit);
            
            if (in_array($currentUrl, $visitedUrls)) {
                continue;
            }
            
            $visitedUrls[] = $currentUrl;
            
            // ページのHTMLを取得
            $html = fetchWebContent($currentUrl);
            if (!$html) {
                continue;
            }
            
            // リンクを抽出（ベースURL以下の階層のみ）
            $links = extractLinksFromHtmlWithPathFilter($html, $currentUrl, $domain, $baseUrlPrefix);
            
            foreach ($links as $link) {
                if (!in_array($link, $foundUrls) && !in_array($link, $visitedUrls)) {
                    $foundUrls[] = $link;
                    
                    // ベースURL以下の階層のURLのみを巡回対象に追加
                    if (isUrlUnderBasePath($link, $baseUrlPrefix) && count($urlsToVisit) < 50) {
                        $urlsToVisit[] = $link;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'urls' => array_values(array_unique($foundUrls)),
            'total_found' => count($foundUrls)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function extractLinksFromHtml($html, $baseUrl, $domain) {
    $links = [];
    
    if (empty($html)) {
        return $links;
    }
    
    // DOMDocumentを使用してHTMLを解析
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    $anchorTags = $dom->getElementsByTagName('a');
    
    foreach ($anchorTags as $anchor) {
        $href = $anchor->getAttribute('href');
        
        if (empty($href) || $href === '#') {
            continue;
        }
        
        // 相対URLを絶対URLに変換
        $absoluteUrl = resolveUrl($href, $baseUrl);
        
        // 同じドメインのURLのみを対象とする
        if (strpos($absoluteUrl, 'http') === 0 && strpos($absoluteUrl, $domain) !== false) {
            // フラグメント（#）を除去
            $absoluteUrl = strtok($absoluteUrl, '#');
            
            // 特定のファイル形式を除外
            if (!preg_match('/\.(jpg|jpeg|png|gif|pdf|zip|doc|docx|xls|xlsx)$/i', $absoluteUrl)) {
                $links[] = $absoluteUrl;
            }
        }
    }
    
    return array_unique($links);
}

function extractLinksFromHtmlWithPathFilter($html, $baseUrl, $domain, $baseUrlPrefix) {
    $links = [];
    
    if (empty($html)) {
        return $links;
    }
    
    // DOMDocumentを使用してHTMLを解析
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    $anchorTags = $dom->getElementsByTagName('a');
    
    foreach ($anchorTags as $anchor) {
        $href = $anchor->getAttribute('href');
        
        if (empty($href) || $href === '#') {
            continue;
        }
        
        // 相対URLを絶対URLに変換
        $absoluteUrl = resolveUrl($href, $baseUrl);
        
        // 同じドメインでベースURL以下の階層のURLのみを対象とする
        if (strpos($absoluteUrl, 'http') === 0 && strpos($absoluteUrl, $domain) !== false && isUrlUnderBasePath($absoluteUrl, $baseUrlPrefix)) {
            // フラグメント（#）を除去
            $absoluteUrl = strtok($absoluteUrl, '#');
            
            // 特定のファイル形式を除外
            if (!preg_match('/\.(jpg|jpeg|png|gif|pdf|zip|doc|docx|xls|xlsx)$/i', $absoluteUrl)) {
                $links[] = $absoluteUrl;
            }
        }
    }
    
    return array_unique($links);
}

function isUrlUnderBasePath($url, $baseUrlPrefix) {
    // URLがベースURL以下の階層にあるかチェック
    if (strpos($url, $baseUrlPrefix) === 0) {
        return true;
    }
    
    // ベースURLの階層と同じレベルかチェック
    $parsedUrl = parse_url($url);
    $parsedBase = parse_url($baseUrlPrefix);
    
    if (!$parsedUrl || !$parsedBase) {
        return false;
    }
    
    $urlPath = rtrim($parsedUrl['path'] ?? '/', '/');
    $basePath = rtrim($parsedBase['path'] ?? '/', '/');
    
    // ベースパスが空の場合（ルート）は、同じドメインのすべてのURLを許可
    if (empty($basePath) || $basePath === '/') {
        return true;
    }
    
    // URLパスがベースパス以下にあるかチェック
    return strpos($urlPath, $basePath) === 0;
}

function resolveUrl($href, $baseUrl) {
    // 絶対URLの場合はそのまま返す
    if (strpos($href, 'http') === 0) {
        return $href;
    }
    
    $parsedBase = parse_url($baseUrl);
    $scheme = $parsedBase['scheme'];
    $host = $parsedBase['host'];
    $basePath = isset($parsedBase['path']) ? $parsedBase['path'] : '/';
    
    // プロトコル相対URLの場合
    if (strpos($href, '//') === 0) {
        return $scheme . ':' . $href;
    }
    
    // ルート相対URLの場合
    if (strpos($href, '/') === 0) {
        return $scheme . '://' . $host . $href;
    }
    
    // 相対URLの場合
    $basePath = rtrim(dirname($basePath), '/') . '/';
    return $scheme . '://' . $host . $basePath . $href;
}

function saveReferenceUrls($siteId, $urls) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 既存の参照URLをすべて削除
        $stmt = $pdo->prepare("DELETE FROM reference_urls WHERE site_id = ?");
        $stmt->execute([$siteId]);
        
        // 新しい参照URLを保存
        $stmt = $pdo->prepare("INSERT INTO reference_urls (site_id, url) VALUES (?, ?)");
        
        foreach ($urls as $url) {
            $stmt->execute([$siteId, $url]);
        }
        
        return ['success' => true, 'message' => 'Reference URLs saved successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getReferenceUrls($siteId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        $stmt = $pdo->prepare("SELECT url FROM reference_urls WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $urls = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return ['success' => true, 'urls' => $urls];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function logAiUsage($siteId, $articleId, $aiModel, $usageType, $prompt, $response, $processingTime) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // トークン数を推定（簡易的な計算）
        $tokensUsed = estimateTokens($prompt . $response);
        
        $stmt = $pdo->prepare("INSERT INTO ai_usage_logs (site_id, article_id, ai_model, usage_type, prompt_text, response_text, tokens_used, processing_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $siteId,
            $articleId,
            $aiModel,
            $usageType,
            $prompt,
            $response,
            $tokensUsed,
            $processingTime
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log('AI使用ログ記録エラー: ' . $e->getMessage());
        return false;
    }
}

function estimateTokens($text) {
    // 簡易的なトークン数推定（実際のAPIではより正確な計算が必要）
    $textLength = mb_strlen($text, 'UTF-8');
    
    // 日本語の場合は文字数をそのまま、英語の場合は単語数を基準に
    if (preg_match('/[ひらがなカタカナ漢字]/', $text)) {
        // 日本語テキストの場合、1文字≒1トークン
        return $textLength;
    } else {
        // 英語テキストの場合、平均的に4文字≒1トークン
        return intval($textLength / 4);
    }
}

function getAiUsageLogs($siteId = null, $limit = 50, $offset = 0) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        $whereClause = $siteId ? "WHERE aul.site_id = ?" : "";
        $params = $siteId ? [$siteId] : [];
        
        // 総件数を取得
        $countSql = "SELECT COUNT(*) FROM ai_usage_logs aul $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetchColumn();
        
        // ログデータを取得
        $sql = "
            SELECT 
                aul.*,
                s.name as site_name,
                a.title as article_title
            FROM ai_usage_logs aul
            LEFT JOIN sites s ON aul.site_id = s.id
            LEFT JOIN articles a ON aul.article_id = a.id
            $whereClause
            ORDER BY aul.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
        
        // 統計情報を取得
        $statsSql = "
            SELECT 
                ai_model,
                usage_type,
                COUNT(*) as usage_count,
                SUM(tokens_used) as total_tokens,
                AVG(processing_time) as avg_processing_time
            FROM ai_usage_logs aul
            $whereClause
            GROUP BY ai_model, usage_type
            ORDER BY usage_count DESC
        ";
        
        $statsParams = $siteId ? [$siteId] : [];
        $statsStmt = $pdo->prepare($statsSql);
        $statsStmt->execute($statsParams);
        $stats = $statsStmt->fetchAll();
        
        return [
            'success' => true,
            'logs' => $logs,
            'stats' => $stats,
            'total_count' => $totalCount,
            'current_page' => intval($offset / $limit) + 1,
            'per_page' => $limit
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getMultilingualSettings($siteId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM multilingual_settings WHERE site_id = ? ORDER BY language_code");
        $stmt->execute([$siteId]);
        $settings = $stmt->fetchAll();
        
        return ['success' => true, 'settings' => $settings];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateMultilingualSettings($siteId, $settings) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        
        // まず、該当するレコードが存在するかチェック
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM multilingual_settings WHERE site_id = ?");
        $checkStmt->execute([$siteId]);
        $recordCount = $checkStmt->fetchColumn();
        
        
        if ($recordCount === 0) {
            // レコードが存在しない場合は作成
            createDefaultLanguageSettings($pdo, $siteId);
        }
        
        $stmt = $pdo->prepare("UPDATE multilingual_settings SET is_enabled = ? WHERE site_id = ? AND language_code = ?");
        
        foreach ($settings as $languageCode => $isEnabled) {
            $boolValue = $isEnabled ? 1 : 0;
            
            $result = $stmt->execute([$boolValue, $siteId, $languageCode]);
            $affectedRows = $stmt->rowCount();
            
            
            if ($affectedRows === 0) {
                // 更新できなかった場合は挿入を試行
                $insertStmt = $pdo->prepare("INSERT INTO multilingual_settings (site_id, language_code, language_name, is_enabled) VALUES (?, ?, ?, ?)");
                $languageNames = [
                    'en' => 'English',
                    'zh-CN' => '中文（简体）',
                    'zh-TW' => '中文（繁體）',
                    'ko' => '한국어',
                    'es' => 'Español',
                    'ar' => 'العربية',
                    'pt' => 'Português',
                    'fr' => 'Français',
                    'de' => 'Deutsch',
                    'ru' => 'Русский',
                    'it' => 'Italiano'
                ];
                $languageName = $languageNames[$languageCode] ?? $languageCode;
                $insertStmt->execute([$siteId, $languageCode, $languageName, $boolValue]);
            }
        }
        
        return ['success' => true, 'message' => 'Multilingual settings updated successfully'];
    } catch (PDOException $e) {
        error_log("Database error in updateMultilingualSettings: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("General error in updateMultilingualSettings: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateSitePolicy($siteId, $policy) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // sitesテーブルのanalysisフィールドを更新
        $stmt = $pdo->prepare("UPDATE sites SET analysis = ? WHERE id = ?");
        $result = $stmt->execute([$policy, $siteId]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Site policy updated successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to update site policy'];
        }
    } catch (PDOException $e) {
        error_log("Database error in updateSitePolicy: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("General error in updateSitePolicy: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function generateMultilingualArticles($siteId, $aiModel) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 多言語設定テーブルが存在しない場合は作成
        $stmt = $pdo->query("SHOW TABLES LIKE 'multilingual_settings'");
        if ($stmt->rowCount() === 0) {
            createMultilingualTables($pdo);
        }
        
        // 当該サイトの言語設定が存在しない場合は作成
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM multilingual_settings WHERE site_id = ?");
        $stmt->execute([$siteId]);
        if ($stmt->fetchColumn() === 0) {
            createDefaultLanguageSettings($pdo, $siteId);
        }
        
        // 有効な言語設定を取得
        $stmt = $pdo->prepare("SELECT * FROM multilingual_settings WHERE site_id = ? AND is_enabled = 1");
        $stmt->execute([$siteId]);
        $enabledLanguages = $stmt->fetchAll();
        
        
        // デバッグ用：全ての言語設定を取得
        $debugStmt = $pdo->prepare("SELECT * FROM multilingual_settings WHERE site_id = ?");
        $debugStmt->execute([$siteId]);
        $allLanguages = $debugStmt->fetchAll();
        
        if (empty($enabledLanguages)) {
            return ['success' => false, 'error' => 'No languages enabled for translation. Please enable languages in the multilingual settings first.'];
        }
        
        // 日本語の記事を取得（コンテンツが作成済みの記事のみ）
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? AND status IN ('draft', 'generated') AND content IS NOT NULL AND content != ''");
        $stmt->execute([$siteId]);
        $articles = $stmt->fetchAll();
        
        if (empty($articles)) {
            return ['success' => false, 'error' => 'No articles with content found for translation. Please create full Japanese articles first.'];
        }
        
        $aiService = new AIService();
        $translatedCount = 0;
        
        foreach ($articles as $article) {
            foreach ($enabledLanguages as $language) {
                // 既に翻訳済みかチェック
                $stmt = $pdo->prepare("SELECT id FROM multilingual_articles WHERE original_article_id = ? AND language_code = ?");
                $stmt->execute([$article['id'], $language['language_code']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    continue; // 既に翻訳済みの場合はスキップ
                }
                
                // 記事のコンテンツが存在しない場合はスキップ
                if (empty($article['content']) || trim($article['content']) === '') {
                    continue;
                }
                
                // 翻訳プロンプトを作成（改善版を使用）
                $languageName = getLanguageName($language['language_code']);
                $translationPrompt = createImprovedTranslationPrompt($article, $language['language_code'], $languageName);
                
                $startTime = microtime(true);
                $translatedContent = $aiService->generateText($translationPrompt, $aiModel);
                $processingTime = microtime(true) - $startTime;
                
                // 翻訳結果をパース
                $translatedArticle = parseTranslatedArticle($translatedContent);
                
                // 多言語記事を保存
                $stmt = $pdo->prepare("INSERT INTO multilingual_articles (original_article_id, language_code, title, seo_keywords, summary, content, ai_model, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'generated')");
                $stmt->execute([
                    $article['id'],
                    $language['language_code'],
                    $translatedArticle['title'],
                    $translatedArticle['keywords'],
                    $translatedArticle['summary'],
                    $translatedArticle['content'],
                    $aiModel
                ]);
                
                // AI使用ログを記録
                logAiUsage($siteId, $article['id'], $aiModel, 'multilingual_translation', $translationPrompt, $translatedContent, $processingTime);
                
                $translatedCount++;
            }
        }
        
        return ['success' => true, 'translated_count' => $translatedCount];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getMultilingualArticles($siteId, $languageCode = null) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        if ($languageCode === null || $languageCode === 'ja') {
            // 日本語（オリジナル）の記事を取得
            $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? ORDER BY created_at DESC");
            $stmt->execute([$siteId]);
            $articles = $stmt->fetchAll();
        } else {
            // 指定された言語の記事を取得
            $stmt = $pdo->prepare("
                SELECT 
                    ma.*,
                    a.publish_date
                FROM multilingual_articles ma
                JOIN articles a ON ma.original_article_id = a.id
                WHERE a.site_id = ? AND ma.language_code = ?
                ORDER BY ma.created_at DESC
            ");
            $stmt->execute([$siteId, $languageCode]);
            $articles = $stmt->fetchAll();
        }
        
        return ['success' => true, 'articles' => $articles];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function startMultilingualArticleGeneration($siteId, $aiModel) {
    try {
        // 進捗IDを生成
        $progressId = uniqid('progress_', true);
        
        // 事前チェック
        $pdo = DatabaseConfig::getConnection();
        
        // 多言語設定テーブルが存在しない場合は作成
        $stmt = $pdo->query("SHOW TABLES LIKE 'multilingual_settings'");
        if ($stmt->rowCount() === 0) {
            createMultilingualTables($pdo);
        }
        
        // 当該サイトの言語設定が存在しない場合は作成
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM multilingual_settings WHERE site_id = ?");
        $stmt->execute([$siteId]);
        if ($stmt->fetchColumn() === 0) {
            createDefaultLanguageSettings($pdo, $siteId);
        }
        
        // 有効な言語設定を取得
        $stmt = $pdo->prepare("SELECT * FROM multilingual_settings WHERE site_id = ? AND is_enabled = 1");
        $stmt->execute([$siteId]);
        $enabledLanguages = $stmt->fetchAll();
        
        if (empty($enabledLanguages)) {
            return ['success' => false, 'error' => 'No languages enabled for translation.'];
        }
        
        // 日本語の記事を取得（コンテンツが作成済みの記事のみ）
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? AND status IN ('draft', 'generated') AND content IS NOT NULL AND content != ''");
        $stmt->execute([$siteId]);
        $articles = $stmt->fetchAll();
        
        if (empty($articles)) {
            return ['success' => false, 'error' => 'No articles with content found for translation.'];
        }
        
        // 総タスク数を計算
        $totalTasks = 0;
        foreach ($articles as $article) {
            foreach ($enabledLanguages as $language) {
                // 既に翻訳済みかチェック
                $stmt = $pdo->prepare("SELECT id FROM multilingual_articles WHERE original_article_id = ? AND language_code = ?");
                $stmt->execute([$article['id'], $language['language_code']]);
                if (!$stmt->fetch()) {
                    $totalTasks++;
                }
            }
        }
        
        // 進捗を初期化
        updateTranslationProgress($progressId, 0, $totalTasks, '準備完了', '初期化中');
        
        // 進捗IDを即座に返す
        return [
            'success' => true, 
            'progress_id' => $progressId,
            'total_tasks' => $totalTasks,
            'message' => 'Translation started'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function executeMultilingualGeneration($siteId, $aiModel, $progressId) {
    try {
        
        $pdo = DatabaseConfig::getConnection();
        
        // 有効な言語設定を取得
        $stmt = $pdo->prepare("SELECT * FROM multilingual_settings WHERE site_id = ? AND is_enabled = 1");
        $stmt->execute([$siteId]);
        $enabledLanguages = $stmt->fetchAll();
        
        
        // 日本語の記事を取得（コンテンツが作成済みの記事のみ）
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? AND status IN ('draft', 'generated') AND content IS NOT NULL AND content != ''");
        $stmt->execute([$siteId]);
        $articles = $stmt->fetchAll();
        
        
        $aiService = new AIService();
        $translatedCount = 0;
        $totalTasks = 0;
        
        // 総タスク数を再計算
        foreach ($articles as $article) {
            foreach ($enabledLanguages as $language) {
                // 既に翻訳済みかチェック
                $stmt = $pdo->prepare("SELECT id FROM multilingual_articles WHERE original_article_id = ? AND language_code = ?");
                $stmt->execute([$article['id'], $language['language_code']]);
                if (!$stmt->fetch()) {
                    $totalTasks++;
                }
            }
        }
        
        
        $currentTask = 0;
        
        foreach ($articles as $article) {
            foreach ($enabledLanguages as $language) {
                // 既に翻訳済みかチェック
                $stmt = $pdo->prepare("SELECT id FROM multilingual_articles WHERE original_article_id = ? AND language_code = ?");
                $stmt->execute([$article['id'], $language['language_code']]);
                if ($stmt->fetch()) {
                    continue;
                }
                
                $currentTask++;
                
                // 言語名を取得
                $languageName = getLanguageName($language['language_code']);
                
                // 進捗を更新
                updateTranslationProgress($progressId, $currentTask, $totalTasks, $article['title'], $languageName);
                
                // 翻訳プロンプトを作成（強化版を使用）
                $translationPrompt = createEnhancedTranslationPrompt($article, $language['language_code'], $languageName);
                
                $startTime = microtime(true);
                $translatedContent = $aiService->generateText($translationPrompt, $aiModel);
                $processingTime = microtime(true) - $startTime;
                
                // 翻訳結果をパース（改善版を使用）
                $translatedArticle = parseTranslatedArticleEnhanced($translatedContent);
                
                // デバッグログ
                
                // 多言語記事を保存
                $stmt = $pdo->prepare("INSERT INTO multilingual_articles (original_article_id, language_code, title, seo_keywords, summary, content, ai_model, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'generated')");
                $stmt->execute([
                    $article['id'],
                    $language['language_code'],
                    $translatedArticle['title'],
                    $translatedArticle['keywords'],
                    $translatedArticle['summary'],
                    $translatedArticle['content'],
                    $aiModel
                ]);
                
                // AI使用ログを記録
                logAiUsage($siteId, $article['id'], $aiModel, 'multilingual_translation', $translationPrompt, $translatedContent, $processingTime);
                
                $translatedCount++;
            }
        }
        
        // 完了時の進捗更新
        updateTranslationProgress($progressId, $totalTasks, $totalTasks, '完了', "計{$translatedCount}件作成");
        
        
        return ['success' => true, 'translated_count' => $translatedCount];
        
    } catch (Exception $e) {
        error_log("多言語記事生成エラー: " . $e->getMessage());
        updateTranslationProgress($progressId, 0, 0, 'エラー', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function generateMultilingualArticlesWithProgress($siteId, $aiModel) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 進捗IDを生成
        $progressId = uniqid('progress_', true);
        
        // 多言語設定テーブルが存在しない場合は作成
        $stmt = $pdo->query("SHOW TABLES LIKE 'multilingual_settings'");
        if ($stmt->rowCount() === 0) {
            createMultilingualTables($pdo);
        }
        
        // 当該サイトの言語設定が存在しない場合は作成
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM multilingual_settings WHERE site_id = ?");
        $stmt->execute([$siteId]);
        if ($stmt->fetchColumn() === 0) {
            createDefaultLanguageSettings($pdo, $siteId);
        }
        
        // 有効な言語設定を取得
        $stmt = $pdo->prepare("SELECT * FROM multilingual_settings WHERE site_id = ? AND is_enabled = 1");
        $stmt->execute([$siteId]);
        $enabledLanguages = $stmt->fetchAll();
        
        if (empty($enabledLanguages)) {
            return ['success' => false, 'error' => 'No languages enabled for translation. Please enable languages in the multilingual settings first.'];
        }
        
        // 日本語の記事を取得（コンテンツが作成済みの記事のみ）
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? AND status IN ('draft', 'generated') AND content IS NOT NULL AND content != ''");
        $stmt->execute([$siteId]);
        $articles = $stmt->fetchAll();
        
        if (empty($articles)) {
            return ['success' => false, 'error' => 'No articles with content found for translation. Please create full Japanese articles first.'];
        }
        
        $aiService = new AIService();
        $translatedCount = 0;
        $totalTasks = 0;
        
        // 総タスク数を計算
        foreach ($articles as $article) {
            foreach ($enabledLanguages as $language) {
                // 既に翻訳済みかチェック
                $stmt = $pdo->prepare("SELECT id FROM multilingual_articles WHERE original_article_id = ? AND language_code = ?");
                $stmt->execute([$article['id'], $language['language_code']]);
                if (!$stmt->fetch()) {
                    $totalTasks++;
                }
            }
        }
        
        // 進捗を初期化
        updateTranslationProgress($progressId, 0, $totalTasks, '準備完了', '');
        
        $currentTask = 0;
        
        foreach ($articles as $article) {
            foreach ($enabledLanguages as $language) {
                // 既に翻訳済みかチェック
                $stmt = $pdo->prepare("SELECT id FROM multilingual_articles WHERE original_article_id = ? AND language_code = ?");
                $stmt->execute([$article['id'], $language['language_code']]);
                if ($stmt->fetch()) {
                    continue;
                }
                
                $currentTask++;
                
                // 言語名を取得
                $languageName = getLanguageName($language['language_code']);
                
                // 進捗を更新
                updateTranslationProgress($progressId, $currentTask, $totalTasks, $article['title'], $languageName);
                
                
                // 翻訳プロンプトを作成（強化版を使用）
                $translationPrompt = createEnhancedTranslationPrompt($article, $language['language_code'], $languageName);
                
                $startTime = microtime(true);
                $translatedContent = $aiService->generateText($translationPrompt, $aiModel);
                $processingTime = microtime(true) - $startTime;
                
                // 翻訳結果をパース（改善版を使用）
                $translatedArticle = parseTranslatedArticleEnhanced($translatedContent);
                
                // デバッグログ
                
                // 多言語記事を保存
                $stmt = $pdo->prepare("INSERT INTO multilingual_articles (original_article_id, language_code, title, seo_keywords, summary, content, ai_model, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'generated')");
                $stmt->execute([
                    $article['id'],
                    $language['language_code'],
                    $translatedArticle['title'],
                    $translatedArticle['keywords'],
                    $translatedArticle['summary'],
                    $translatedArticle['content'],
                    $aiModel
                ]);
                
                // AI使用ログを記録
                logAiUsage($siteId, $article['id'], $aiModel, 'multilingual_translation', $translationPrompt, $translatedContent, $processingTime);
                
                $translatedCount++;
            }
        }
        
        // 完了時の進捗更新
        updateTranslationProgress($progressId, $totalTasks, $totalTasks, '完了', '');
        
        return ['success' => true, 'translated_count' => $translatedCount, 'progress_id' => $progressId];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getLanguageName($languageCode) {
    $languageNames = [
        'en' => 'English',
        'zh-CN' => '中文（简体）',
        'zh-TW' => '中文（繁體）',
        'ko' => '한국어',
        'es' => 'Español',
        'ar' => 'العربية',
        'pt' => 'Português',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'ru' => 'Русский',
        'it' => 'Italiano'
    ];
    
    return $languageNames[$languageCode] ?? $languageCode;
}

function createImprovedTranslationPrompt($article, $languageCode, $languageName) {
    $prompt = "あなたは専門的な翻訳者として、以下の日本語記事を{$languageName}に翻訳してください。\n\n";
    $prompt .= "【重要な要件】\n";
    $prompt .= "- 原文の長さと詳細度を完全に保持してください\n";
    $prompt .= "- 記事の構造、段落分け、情報量を元の通りに維持してください\n";
    $prompt .= "- 要約や短縮は絶対に行わないでください\n";
    $prompt .= "- すべての情報を漏れなく翻訳してください\n\n";
    
    $prompt .= "【翻訳対象の記事情報】\n";
    $prompt .= "タイトル: " . $article['title'] . "\n";
    $prompt .= "SEOキーワード: " . $article['seo_keywords'] . "\n";
    $prompt .= "概要: " . $article['summary'] . "\n\n";
    $prompt .= "記事本文:\n" . $article['content'] . "\n\n";
    
    $prompt .= "【翻訳の詳細要件】\n";
    $prompt .= "1. 原文の意味と文脈を100%保持する\n";
    $prompt .= "2. {$languageName}の自然で読みやすい表現を使用する\n";
    $prompt .= "3. 専門用語は適切に翻訳し、必要に応じて説明を追加する\n";
    $prompt .= "4. マークダウン形式、リスト、段落構造を維持する\n";
    $prompt .= "5. 文化的な文脈を考慮し、読者に分かりやすくする\n";
    $prompt .= "6. SEOキーワードも適切に翻訳する\n";
    $prompt .= "7. 記事の長さは原文と同等かそれ以上にする\n\n";
    
    if ($languageCode === 'zh-CN') {
        $prompt .= "【中文简体特別要件】\n";
        $prompt .= "- 簡体字を使用し、大陸での一般的な表現を採用する\n";
        $prompt .= "- 日本の固有名詞は適切に中国語に翻訳する\n";
        $prompt .= "- 記事の詳細度を保ち、すべての段落を完全に翻訳する\n\n";
    }
    
    $prompt .= "【出力形式】\n";
    $prompt .= "以下の形式で、各項目を完全に翻訳して出力してください：\n\n";
    $prompt .= "Title: [翻訳されたタイトル]\n";
    $prompt .= "Keywords: [翻訳されたSEOキーワード（カンマ区切り）]\n";
    $prompt .= "Summary: [翻訳された概要]\n";
    $prompt .= "Content: [翻訳された記事内容（原文と同等の長さと詳細度を保持）]\n";
    
    return $prompt;
}

function updateTranslationProgress($progressId, $current, $total, $articleTitle, $language) {
    $progressFile = sys_get_temp_dir() . "/translation_progress_{$progressId}.json";
    $progressData = [
        'current' => $current,
        'total' => $total,
        'article_title' => $articleTitle,
        'language' => $language,
        'timestamp' => time()
    ];
    file_put_contents($progressFile, json_encode($progressData));
}

function getTranslationProgress($progressId) {
    $progressFile = sys_get_temp_dir() . "/translation_progress_{$progressId}.json";
    
    if (!file_exists($progressFile)) {
        return ['success' => false, 'error' => 'Progress not found'];
    }
    
    $progressData = json_decode(file_get_contents($progressFile), true);
    if (!$progressData) {
        return ['success' => false, 'error' => 'Invalid progress data'];
    }
    
    // 5分以上古いファイルは削除
    if (time() - $progressData['timestamp'] > 300) {
        unlink($progressFile);
        return ['success' => false, 'error' => 'Progress expired'];
    }
    
    return ['success' => true, 'progress' => $progressData];
}

function createEnhancedTranslationPrompt($article, $languageCode, $languageName) {
    $prompt = "# 専門翻訳タスク\n\n";
    $prompt .= "あなたは多言語コンテンツの専門翻訳者です。以下の日本語記事を{$languageName}に翻訳してください。\n\n";
    
    $prompt .= "## 【CRITICAL REQUIREMENTS - 絶対遵守事項】\n";
    $prompt .= "1. **完全性**: 原文のすべての情報を漏れなく翻訳する\n";
    $prompt .= "2. **長さ保持**: 原文と同等またはそれ以上の詳細度を維持する\n";
    $prompt .= "3. **構造保持**: 段落、見出し、リスト構造を完全に保持する\n";
    $prompt .= "4. **要約禁止**: 内容の省略、要約、短縮は絶対に行わない\n";
    $prompt .= "5. **品質**: 自然で読みやすい{$languageName}表現を使用する\n\n";
    
    $prompt .= "## 翻訳対象記事\n\n";
    $prompt .= "**タイトル**: " . $article['title'] . "\n";
    $prompt .= "**SEOキーワード**: " . $article['seo_keywords'] . "\n";
    $prompt .= "**概要**: " . $article['summary'] . "\n\n";
    $prompt .= "**記事本文**:\n```\n" . $article['content'] . "\n```\n\n";
    
    // 言語別の特別指示
    if ($languageCode === 'zh-CN') {
        $prompt .= "## 中文简体特別指示\n";
        $prompt .= "- 使用标准简体中文\n";
        $prompt .= "- 保持文章的完整性和详细程度\n";
        $prompt .= "- 确保每个段落都被完整翻译\n";
        $prompt .= "- 专业术语使用准确的中文表达\n\n";
    } elseif ($languageCode === 'zh-TW') {
        $prompt .= "## 中文繁體特別指示\n";
        $prompt .= "- 使用標準繁體中文\n";
        $prompt .= "- 保持文章的完整性和詳細程度\n";
        $prompt .= "- 確保每個段落都被完整翻譯\n";
        $prompt .= "- 專業術語使用準確的中文表達\n\n";
    } elseif ($languageCode === 'fr') {
        $prompt .= "## Instructions spéciales pour le français\n";
        $prompt .= "- Utilisez un français naturel et fluide\n";
        $prompt .= "- Maintenez la structure complète de l'article\n";
        $prompt .= "- Traduisez intégralement chaque paragraphe\n";
        $prompt .= "- Adaptez les expressions culturelles au contexte français\n\n";
    }
    
    $prompt .= "## 詳細要件\n";
    $prompt .= "- マークダウン形式を維持\n";
    $prompt .= "- 専門用語は適切に翻訳\n";
    $prompt .= "- 文化的文脈を考慮\n";
    $prompt .= "- SEOキーワードも翻訳\n";
    $prompt .= "- 読者にとって価値ある内容を保持\n\n";
    
    $prompt .= "## 出力形式（厳密に従ってください）\n\n";
    $prompt .= "必ず以下の形式で出力してください：\n\n";
    $prompt .= "Title: [完全に翻訳されたタイトル]\n\n";
    $prompt .= "Keywords: [翻訳されたSEOキーワード（カンマ区切り）]\n\n";
    $prompt .= "Summary: [完全に翻訳された概要]\n\n";
    $prompt .= "Content:\n[原文と同等の長さと詳細度を持つ完全な翻訳記事]\n\n";
    
    $prompt .= "**重要**: 記事内容セクションでは、原文のすべての段落、情報、構造を漏れなく翻訳してください。\n";
    
    return $prompt;
}

function parseTranslatedArticleEnhanced($translatedContent) {
    $article = [
        'title' => '',
        'keywords' => '',
        'summary' => '',
        'content' => ''
    ];
    
    
    // より柔軟なパターンマッチング
    $patterns = [
        'title' => '/(?:タイトル|Title|标题)[:：]\s*(.+?)(?=\n|\r|$)/u',
        'keywords' => '/(?:キーワード|Keywords|关键词)[:：]\s*(.+?)(?=\n|\r|$)/u',
        'summary' => '/(?:概要|Summary|摘要)[:：]\s*(.+?)(?=\n(?:記事内容|Article|文章内容|Content)|\r|$)/su',
        'content' => '/(?:記事内容|Article|文章内容|Content)[:：]\s*(.+?)$/su'
    ];
    
    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $translatedContent, $matches)) {
            $article[$key] = trim($matches[1]);
        } else {
        }
    }
    
    // フォールバック: 記事内容が見つからない場合
    if (empty($article['content'])) {
        // より単純なパターンで再試行
        $lines = explode("\n", $translatedContent);
        $contentStarted = false;
        $contentLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (!$contentStarted) {
                if (strpos($line, 'タイトル:') === 0 && empty($article['title'])) {
                    $article['title'] = trim(str_replace('タイトル:', '', $line));
                } elseif (strpos($line, 'キーワード:') === 0 && empty($article['keywords'])) {
                    $article['keywords'] = trim(str_replace('キーワード:', '', $line));
                } elseif (strpos($line, '概要:') === 0 && empty($article['summary'])) {
                    $article['summary'] = trim(str_replace('概要:', '', $line));
                } elseif (strpos($line, '記事内容:') === 0) {
                    $contentStarted = true;
                    $remainingContent = trim(str_replace('記事内容:', '', $line));
                    if (!empty($remainingContent)) {
                        $contentLines[] = $remainingContent;
                    }
                }
            } else {
                if (!empty($line)) {
                    $contentLines[] = $line;
                }
            }
        }
        
        if (!empty($contentLines)) {
            $article['content'] = implode("\n", $contentLines);
        }
    }
    
    // 最終的なコンテンツチェック
    if (empty($article['content'])) {
        // 最後の手段として、翻訳結果全体を使用
        $article['content'] = $translatedContent;
    }
    
    
    return $article;
}

function getArticles($siteId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 記事を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $articles = $stmt->fetchAll();
        
        // 各記事の翻訳データを取得
        foreach ($articles as &$article) {
            $stmt = $pdo->prepare("SELECT * FROM multilingual_articles WHERE original_article_id = ?");
            $stmt->execute([$article['id']]);
            $article['translations'] = $stmt->fetchAll();
        }
        
        return ['success' => true, 'articles' => $articles];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getArticleTranslations($articleId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // オリジナル記事を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$articleId]);
        $originalArticle = $stmt->fetch();
        
        if (!$originalArticle) {
            return ['success' => false, 'error' => 'Article not found'];
        }
        
        // 翻訳記事を取得
        $stmt = $pdo->prepare("SELECT * FROM multilingual_articles WHERE original_article_id = ?");
        $stmt->execute([$articleId]);
        $translations = $stmt->fetchAll();
        
        return [
            'success' => true,
            'original' => $originalArticle,
            'translations' => $translations
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createTranslationPrompt($article, $languageCode, $languageName) {
    $prompt = "以下の日本語記事を{$languageName}に翻訳してください。\n\n";
    $prompt .= "元のタイトル: " . $article['title'] . "\n";
    $prompt .= "元のSEOキーワード: " . $article['seo_keywords'] . "\n";
    $prompt .= "元の概要: " . $article['summary'] . "\n";
    
    if (!empty($article['content'])) {
        $prompt .= "元の記事内容: " . $article['content'] . "\n\n";
    } else {
        $prompt .= "元の記事内容: （記事内容は概要のみとなります）\n\n";
    }
    
    $prompt .= "翻訳の要件：\n";
    $prompt .= "- 原文の意味と構造を保持する\n";
    $prompt .= "- {$languageName}の自然な表現を使用する\n";
    $prompt .= "- SEOキーワードも適切に翻訳する\n";
    $prompt .= "- マークダウン形式を維持する\n";
    $prompt .= "- 文化的な文脈を考慮する\n";
    
    if (empty($article['content'])) {
        $prompt .= "- 記事内容がない場合は概要を基に適切な内容を作成する\n";
    }
    
    $prompt .= "\n以下の形式で出力してください：\n\n";
    $prompt .= "タイトル: [翻訳されたタイトル]\n";
    $prompt .= "キーワード: [翻訳されたSEOキーワード（カンマ区切り）]\n";
    $prompt .= "概要: [翻訳された概要]\n";
    $prompt .= "記事内容: [翻訳された記事内容]\n";
    
    return $prompt;
}

function parseTranslatedArticle($translatedContent) {
    $article = [
        'title' => '',
        'keywords' => '',
        'summary' => '',
        'content' => ''
    ];
    
    // 多言語対応のパターンマッチング（英語キーワードを優先）
    $patterns = [
        'title' => '/(?:Title|タイトル|标题|標題|제목|Título|Titre|Titel|Titolo|Заголовок|عنوان|शीर्षक|ชื่อเรื่อง|Tiêu đề|Judul|Tajuk|Pamagat)[:：]\s*(.+?)(?=\n|\r|$)/u',
        'keywords' => '/(?:Keywords|キーワード|关键词|關鍵詞|키워드|Palabras clave|Mots-clés|Schlüsselwörter|Parole chiave|Ключевые слова|كلمات مفتاحية|मुख्य शब्द|คำสำคัญ|Từ khóa|Kata kunci|Mga keyword)[:：]\s*(.+?)(?=\n|\r|$)/u',
        'summary' => '/(?:Summary|概要|摘要|摘要|요약|Resumen|Résumé|Zusammenfassung|Riassunto|Краткое изложение|ملخص|सारांश|สรุป|Tóm tắt|Ringkasan|Buod)[:：]\s*(.+?)(?=\n(?:Content|記事内容|Article|文章内容|內容|기사 내용|Contenido del artículo|Contenu de l\'article|Artikelinhalt|Contenuto dell\'articolo|Содержание статьи|محتوى المقال|लेख की सामग्री|เนื้อหาบทความ|Nội dung bài viết|Konten artikel|Kandungan artikel|Nilalaman ng artikulo)|\r|$)/su',
        'content' => '/(?:Content|記事内容|Article|文章内容|內容|기사 내용|Contenido del artículo|Contenu de l\'article|Artikelinhalt|Contenuto dell\'articolo|Содержание статьи|محتوى المقال|लेख की सामग्री|เนื้อหาบทความ|Nội dung bài viết|Konten artikel|Kandungan artikel|Nilalaman ng artikulo)[:：]\s*(.+?)$/su'
    ];
    
    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $translatedContent, $matches)) {
            $article[$key] = trim($matches[1]);
        }
    }
    
    // フォールバック処理：パターンマッチングが失敗した場合は従来の方法を使用
    if (empty($article['title']) && empty($article['keywords']) && empty($article['summary']) && empty($article['content'])) {
        $lines = explode("\n", $translatedContent);
        $currentSection = '';
        $contentLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'タイトル:') === 0) {
                $article['title'] = trim(str_replace('タイトル:', '', $line));
            } elseif (strpos($line, 'キーワード:') === 0) {
                $article['keywords'] = trim(str_replace('キーワード:', '', $line));
            } elseif (strpos($line, '概要:') === 0) {
                $article['summary'] = trim(str_replace('概要:', '', $line));
            } elseif (strpos($line, '記事内容:') === 0) {
                $currentSection = 'content';
                continue;
            } elseif ($currentSection === 'content') {
                $contentLines[] = $line;
            }
        }
        
        $article['content'] = implode("\n", $contentLines);
    }
    
    return $article;
}

// 多言語テーブル作成関数
function createMultilingualTables($pdo) {
    // 多言語記事テーブル
    $pdo->exec("CREATE TABLE multilingual_articles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_article_id INT NOT NULL,
        language_code VARCHAR(10) NOT NULL,
        title VARCHAR(500) NOT NULL,
        seo_keywords TEXT,
        summary TEXT,
        content LONGTEXT,
        ai_model VARCHAR(50),
        status ENUM('draft', 'generated', 'published') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (original_article_id) REFERENCES articles(id) ON DELETE CASCADE,
        INDEX idx_multilingual_articles_original_id (original_article_id),
        INDEX idx_multilingual_articles_language (language_code),
        INDEX idx_multilingual_articles_status (status),
        UNIQUE KEY unique_article_language (original_article_id, language_code)
    )");
    
    // 多言語設定テーブル
    $pdo->exec("CREATE TABLE multilingual_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        site_id INT NOT NULL,
        language_code VARCHAR(10) NOT NULL,
        language_name VARCHAR(50) NOT NULL,
        is_enabled BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
        UNIQUE KEY unique_site_language (site_id, language_code),
        INDEX idx_multilingual_settings_site_id (site_id),
        INDEX idx_multilingual_settings_enabled (is_enabled)
    )");
    
    // ai_usage_logs テーブルに multilingual_translation を追加
    $pdo->exec("ALTER TABLE ai_usage_logs MODIFY COLUMN usage_type ENUM('site_analysis', 'article_outline', 'article_generation', 'additional_outline', 'site_analysis_integration', 'multilingual_translation') NOT NULL");
}

// デフォルト言語設定作成関数
function createDefaultLanguageSettings($pdo, $siteId) {
    $languages = [
        ['en', 'English'],
        ['zh-CN', '中文（简体）'],
        ['zh-TW', '中文（繁體）'],
        ['ko', '한국어'],
        ['es', 'Español'],
        ['ar', 'العربية'],
        ['pt', 'Português'],
        ['fr', 'Français'],
        ['de', 'Deutsch'],
        ['ru', 'Русский'],
        ['it', 'Italiano']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO multilingual_settings (site_id, language_code, language_name, is_enabled) VALUES (?, ?, ?, FALSE)");
    
    foreach ($languages as $lang) {
        $stmt->execute([$siteId, $lang[0], $lang[1]]);
    }
}

// 記事とその翻訳データを取得
function getArticleWithTranslations($articleId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // オリジナル記事を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();
        
        if (!$article) {
            return ['success' => false, 'error' => 'Article not found'];
        }
        
        // 翻訳記事を取得
        $stmt = $pdo->prepare("SELECT * FROM multilingual_articles WHERE original_article_id = ?");
        $stmt->execute([$articleId]);
        $translations = $stmt->fetchAll();
        
        return [
            'success' => true,
            'article' => $article,
            'translations' => $translations
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 単一記事の単一言語生成
function generateSingleLanguageArticle($articleId, $languageCode, $aiModel) {
    try {
        error_log("generateSingleLanguageArticle called with: articleId=$articleId, languageCode=$languageCode, aiModel=$aiModel");
        
        $pdo = DatabaseConfig::getConnection();
        
        // オリジナル記事を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();
        
        if (!$article) {
            error_log("Article not found with ID: $articleId");
            return ['success' => false, 'error' => 'Article not found'];
        }
        
        error_log("Article found: " . json_encode($article));
        
        // 言語情報を取得
        $languageNames = [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'zh-CN' => 'Chinese (Simplified)',
            'zh-TW' => 'Chinese (Traditional)',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'tl' => 'Filipino',
            'ja' => 'Japanese'
        ];
        
        $languageName = $languageNames[$languageCode] ?? 'Unknown';
        error_log("Language name resolved: $languageName for code: $languageCode");
        
        // 翻訳プロンプトを作成
        try {
            $prompt = createEnhancedTranslationPrompt($article, $languageCode, $languageName);
            error_log("Translation prompt created successfully");
        } catch (Exception $e) {
            error_log("Error creating translation prompt: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error creating translation prompt: ' . $e->getMessage()];
        }
        
        // AI APIを呼び出し
        try {
            $aiService = new AIService();
            $response = $aiService->generateText($prompt, $aiModel);
            error_log("AI service response: " . json_encode($response));
        } catch (Exception $e) {
            error_log("AI service error: " . $e->getMessage());
            return ['success' => false, 'error' => 'AI service error: ' . $e->getMessage()];
        }
        
        if (empty($response)) {
            return ['success' => false, 'error' => 'AI generation failed: Empty response'];
        }
        
        // 翻訳結果を解析
        $translatedArticle = parseTranslatedArticle($response);
        
        // 翻訳データのバリデーション
        if (empty($translatedArticle['title']) || empty($translatedArticle['content'])) {
            error_log("Translation validation failed: title or content is empty");
            return ['success' => false, 'error' => 'Translation failed: title or content is empty'];
        }
        
        // 翻訳データを保存（既存の翻訳がある場合は更新）
        $stmt = $pdo->prepare("
            INSERT INTO multilingual_articles 
            (original_article_id, language_code, title, seo_keywords, summary, content, ai_model, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'generated', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            seo_keywords = VALUES(seo_keywords),
            summary = VALUES(summary),
            content = VALUES(content),
            ai_model = VALUES(ai_model),
            status = 'generated',
            updated_at = NOW()
        ");
        
        $stmt->execute([
            $articleId,
            $languageCode,
            $translatedArticle['title'],
            $translatedArticle['keywords'],
            $translatedArticle['summary'],
            $translatedArticle['content'],
            $aiModel
        ]);
        
        $translationId = $pdo->lastInsertId();
        if ($translationId === 0) {
            // 更新の場合は既存のIDを取得
            $stmt = $pdo->prepare("SELECT id FROM multilingual_articles WHERE original_article_id = ? AND language_code = ?");
            $stmt->execute([$articleId, $languageCode]);
            $translationId = $stmt->fetchColumn();
        }
        
        return [
            'success' => true,
            'message' => 'Translation generated successfully',
            'translation_id' => $translationId,
            'article_id' => $articleId,
            'language_code' => $languageCode,
            'language_name' => $languageName,
            'translated_article' => $translatedArticle
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// サイトデータと翻訳データを含む記事一覧を取得
function getSiteDataWithTranslations($siteId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // サイト情報取得
        $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found'];
        }
        
        // 記事一覧を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $articles = $stmt->fetchAll();
        
        // 各記事の翻訳データを取得
        foreach ($articles as &$article) {
            $stmt = $pdo->prepare("SELECT * FROM multilingual_articles WHERE original_article_id = ?");
            $stmt->execute([$article['id']]);
            $article['translations'] = $stmt->fetchAll();
        }
        
        return [
            'success' => true,
            'data' => [
                'site' => $site,
                'analysis' => $site['analysis_result'],
                'articles' => $articles
            ]
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 翻訳データを保存する関数（内部使用）
function saveTranslationData($pdo, $articleId, $languageCode, $translatedArticle, $aiModel) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO multilingual_articles 
            (original_article_id, language_code, title, seo_keywords, summary, content, ai_model, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'generated', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            seo_keywords = VALUES(seo_keywords),
            summary = VALUES(summary),
            content = VALUES(content),
            ai_model = VALUES(ai_model),
            status = 'generated',
            updated_at = NOW()
        ");
        
        $stmt->execute([
            $articleId,
            $languageCode,
            $translatedArticle['title'],
            $translatedArticle['keywords'],
            $translatedArticle['summary'],
            $translatedArticle['content'],
            $aiModel
        ]);
        
        return true;
    } catch (PDOException $e) {
        throw new Exception('Translation save failed: ' . $e->getMessage());
    }
}

// 各記事の翻訳データを取得する関数
function getArticleTranslationData($articleId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        $stmt = $pdo->prepare("
            SELECT 
                language_code,
                title,
                seo_keywords,
                summary,
                content,
                ai_model,
                status,
                created_at,
                updated_at
            FROM multilingual_articles 
            WHERE original_article_id = ?
            ORDER BY language_code
        ");
        $stmt->execute([$articleId]);
        $translations = $stmt->fetchAll();
        
        $translationData = [];
        foreach ($translations as $translation) {
            $translationData[$translation['language_code']] = $translation;
        }
        
        return [
            'success' => true,
            'translations' => $translationData
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 全サイト一覧を取得（複数サイト記事作成用）
function getAllSites() {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->query("SELECT id, name, url, created_at FROM sites ORDER BY created_at DESC");
        $sites = $stmt->fetchAll();
        
        return ['success' => true, 'sites' => $sites];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// サイトの記事作成方針を取得
function getSitePolicy($siteId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id, name, analysis_result FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found'];
        }
        
        return [
            'success' => true, 
            'policy' => [
                'site_id' => $site['id'],
                'site_name' => $site['name'],
                'analysis' => $site['analysis_result']
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteArticles($articleIds) {
    error_log('deleteArticles function called with: ' . json_encode($articleIds));
    
    try {
        $pdo = DatabaseConfig::getConnection();
        error_log('Database connection established');
        
        // 入力値の検証
        if (empty($articleIds) || !is_array($articleIds)) {
            error_log('Input validation failed: empty or not array');
            return ['success' => false, 'error' => '削除対象の記事が指定されていません。'];
        }
        
        // 記事IDを整数に変換
        $cleanIds = array_map('intval', array_filter($articleIds, 'is_numeric'));
        error_log('Clean IDs: ' . json_encode($cleanIds));
        
        if (empty($cleanIds)) {
            error_log('No valid article IDs found');
            return ['success' => false, 'error' => '有効な記事IDが指定されていません。'];
        }
        
        error_log('Starting transaction');
        $pdo->beginTransaction();
        
        // まず多言語記事の削除（外部キー制約対応）
        $placeholders = str_repeat('?,', count($cleanIds) - 1) . '?';
        error_log('Deleting from multilingual_articles with placeholders: ' . $placeholders);
        try {
            $stmt = $pdo->prepare("DELETE FROM multilingual_articles WHERE article_id IN ($placeholders)");
            $stmt->execute($cleanIds);
            error_log('Multilingual articles deleted');
        } catch (PDOException $e) {
            error_log('Multilingual articles table might not exist or error: ' . $e->getMessage());
            // Continue execution even if multilingual_articles table doesn't exist
        }
        
        // 記事の削除
        error_log('Deleting from articles');
        $stmt = $pdo->prepare("DELETE FROM articles WHERE id IN ($placeholders)");
        $stmt->execute($cleanIds);
        
        $deletedCount = $stmt->rowCount();
        error_log('Articles deleted count: ' . $deletedCount);
        
        $pdo->commit();
        error_log('Transaction committed');
        
        return [
            'success' => true,
            'message' => "{$deletedCount}件の記事を削除しました。",
            'deleted_count' => $deletedCount
        ];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log('記事削除エラー: ' . $e->getMessage());
        return ['success' => false, 'error' => '記事の削除中にエラーが発生しました。'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log('記事削除エラー: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 記事フィールドの更新
function updateArticleField($articleId, $field, $value) {
    $pdo = DatabaseConfig::getConnection();
    
    try {
        // 入力値の検証
        if (empty($articleId) || !is_numeric($articleId)) {
            return ['success' => false, 'error' => '有効な記事IDが指定されていません。'];
        }
        
        $articleId = intval($articleId);
        
        // 許可されたフィールドの確認（セキュリティのため）
        $allowedFields = ['title', 'seo_keywords', 'summary', 'publish_date'];
        if (!in_array($field, $allowedFields)) {
            return ['success' => false, 'error' => '更新が許可されていないフィールドです。'];
        }
        
        // フィールドの更新
        $sql = "UPDATE articles SET {$field} = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$value, $articleId]);
        
        $updatedCount = $stmt->rowCount();
        
        if ($updatedCount > 0) {
            return [
                'success' => true,
                'message' => "記事の{$field}を更新しました。"
            ];
        } else {
            return ['success' => false, 'error' => '指定された記事が見つからないか、更新する内容がありません。'];
        }
        
    } catch (PDOException $e) {
        error_log('記事フィールド更新エラー: ' . $e->getMessage());
        return ['success' => false, 'error' => '記事フィールドの更新中にエラーが発生しました。'];
    } catch (Exception $e) {
        error_log('記事フィールド更新エラー: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>