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
header('Access-Control-Allow-Headers: Content-Type, User-Agent');

// WAF対策：リクエストの詳細をログに記録
error_log("User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
error_log("X-Forwarded-For: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown'));
error_log("Remote-Addr: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

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
    // デバッグ用: リクエスト情報をログに記録
    error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    
    // 必要なファイルをインクルード
    if (!file_exists('config.php')) {
        sendJsonResponse(['success' => false, 'error' => 'config.php not found']);
    }
    if (!file_exists('ai_service.php')) {
        sendJsonResponse(['success' => false, 'error' => 'ai_service.php not found']);
    }
    
    define('INCLUDED_FROM_API', true);
    
    // デバッグ用: ファイルの存在確認
    error_log("API called. config.php exists: " . (file_exists('config.php') ? 'yes' : 'no'));
    error_log("API called. ai_service.php exists: " . (file_exists('ai_service.php') ? 'yes' : 'no'));
    
    require_once 'config.php';
    require_once 'ai_service.php';
    
    $input = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        error_log("Raw POST data: " . $rawInput);
        
        if (!empty($rawInput)) {
            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                sendJsonResponse(['success' => false, 'error' => 'Invalid JSON input: ' . json_last_error_msg()]);
            }
            error_log("Decoded input: " . print_r($input, true));
        } else {
            error_log("No POST data received");
            sendJsonResponse(['success' => false, 'error' => 'No input data received']);
        }
    } else {
        error_log("Non-POST request method: " . $_SERVER['REQUEST_METHOD']);
        sendJsonResponse(['success' => false, 'error' => 'Only POST requests are supported']);
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'test':
            sendJsonResponse(['success' => true, 'message' => 'Main API is working', 'input' => $input]);
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
            sendJsonResponse(addArticleOutline($input['site_id'], $input['ai_model']));
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
        
        // まず、ai_modelカラムが存在するかチェック
        $checkSql = "SHOW COLUMNS FROM sites LIKE 'ai_model'";
        $checkStmt = $pdo->query($checkSql);
        $hasAiModelColumn = $checkStmt->rowCount() > 0;
        
        // デバッグ情報をログに記録
        error_log("ai_model column exists: " . ($hasAiModelColumn ? 'yes' : 'no'));
        error_log("Column count: " . $checkStmt->rowCount());
        
        // 一時的にai_modelカラムを使用しないでテスト
        $stmt = $pdo->prepare("INSERT INTO sites (name, url, analysis_result, features, keywords) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $siteName,
            json_encode($urls),
            $analysis,
            '', // 後で更新
            ''  // 後で更新
        ]);
        
        $siteId = $pdo->lastInsertId();
        
        // 後でai_modelを更新
        if ($hasAiModelColumn) {
            $updateStmt = $pdo->prepare("UPDATE sites SET ai_model = ? WHERE id = ?");
            $updateStmt->execute([$aiModel, $siteId]);
        }
        
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

function analyzeSitesGroup($urls, $aiModel, $groupIndex = 1, $totalGroups = 1) {
    try {
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
        
        // AIでサイト分析（グループ用プロンプト）
        $aiService = new AIService();
        $analysisPrompt = createGroupAnalysisPrompt($siteContents, $groupIndex, $totalGroups);
        
        $startTime = microtime(true);
        $analysis = $aiService->generateText($analysisPrompt, $aiModel);
        $processingTime = microtime(true) - $startTime;
        
        return [
            'success' => true,
            'analysis' => $analysis,
            'group_index' => $groupIndex,
            'urls_count' => count($urls)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function integrateAnalyses($analyses, $aiModel, $totalUrls = 0, $baseUrl = '') {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        // 複数の分析結果を統合
        $aiService = new AIService();
        $integrationPrompt = createIntegrationPrompt($analyses, $totalUrls);
        
        $startTime = microtime(true);
        $finalAnalysis = $aiService->generateText($integrationPrompt, $aiModel);
        $processingTime = microtime(true) - $startTime;
        
        // 統合結果をDBに保存
        // サイト名を「一番上のURL（URL数）」形式に変更
        if (!empty($baseUrl)) {
            $siteName = extractSiteName($baseUrl) . " ({$totalUrls}個のURL)";
        } else {
            $siteName = "統合分析結果 ({$totalUrls}個のURL)";
        }
        
        // ai_modelカラムの存在チェック
        $checkSql = "SHOW COLUMNS FROM sites LIKE 'ai_model'";
        $checkStmt = $pdo->query($checkSql);
        $hasAiModelColumn = $checkStmt->rowCount() > 0;
        
        $stmt = $pdo->prepare("INSERT INTO sites (name, url, analysis_result, features, keywords) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $siteName,
            json_encode([]), // 統合分析なのでURLは空
            $finalAnalysis,
            '', // 後で更新
            ''  // 後で更新
        ]);
        
        $siteId = $pdo->lastInsertId();
        
        // 後でai_modelを更新
        if ($hasAiModelColumn) {
            $updateStmt = $pdo->prepare("UPDATE sites SET ai_model = ? WHERE id = ?");
            $updateStmt->execute([$aiModel, $siteId]);
        }
        
        // AI使用ログを記録
        logAiUsage($siteId, null, $aiModel, 'integration_analysis', $integrationPrompt, $finalAnalysis, $processingTime);
        
        return [
            'success' => true,
            'site_id' => $siteId,
            'analysis' => $finalAnalysis
        ];
    } catch (Exception $e) {
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

function addArticleOutline($siteId, $aiModel) {
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
        
        // 記事概要を追加生成（10記事分）
        $aiService = new AIService();
        $outlinePrompt = createAdditionalOutlinePrompt($site['analysis_result'], $existingCount, 10);
        
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
        
        // DBに保存
        $stmt = $pdo->prepare("INSERT INTO articles (site_id, title, seo_keywords, summary, ai_model, status) VALUES (?, ?, ?, ?, ?, 'draft')");
        
        foreach ($articles as $article) {
            if (empty($article['title']) || empty($article['keywords']) || empty($article['summary'])) {
                continue; // 不完全な記事データはスキップ
            }
            
            $stmt->execute([
                $siteId,
                $article['title'],
                $article['keywords'],
                $article['summary'],
                $aiModel
            ]);
        }
        
        // 全記事一覧を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $allArticles = $stmt->fetchAll();
        
        return [
            'success' => true,
            'articles' => $allArticles
        ];
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

function createGroupAnalysisPrompt($siteContents, $groupIndex, $totalGroups) {
    $prompt = "以下のサイトの内容を分析してください。これは全{$totalGroups}グループ中の{$groupIndex}番目のグループです。\n\n";
    
    foreach ($siteContents as $site) {
        $prompt .= "URL: " . $site['url'] . "\n";
        $prompt .= "内容: " . substr($site['content'], 0, 2000) . "...\n\n";
    }
    
    $prompt .= "このグループの特徴を以下の観点で分析し、簡潔にマークダウン形式で出力してください：\n";
    $prompt .= "1. サイト群の特徴とテーマ\n";
    $prompt .= "2. ターゲット読者層と興味関心\n";
    $prompt .= "3. SEOに有効なキーワード（主要キーワード、関連キーワード、ロングテールキーワード）\n";
    $prompt .= "4. コンテンツの傾向とトーン\n";
    $prompt .= "5. 検索意図と読者のニーズ\n";
    $prompt .= "\n注意：これは複数グループの一部なので、他のグループと統合できるような形式で分析してください。\n";
    
    return $prompt;
}

function createIntegrationPrompt($analyses, $totalUrls) {
    $prompt = "以下は複数のサイトグループ（合計{$totalUrls}個のURL）を分析した結果です。これらを統合して、このサイトに最適化されたコラム記事を作成するための総合的な分析を行ってください。\n\n";
    
    foreach ($analyses as $index => $analysis) {
        $prompt .= "=== グループ" . ($index + 1) . "の分析結果 ===\n";
        $prompt .= $analysis . "\n\n";
    }
    
    $prompt .= "上記の分析結果を統合して、以下の観点で総合的な分析をマークダウン形式で出力してください：\n";
    $prompt .= "1. 全体的なサイトの特徴とテーマ\n";
    $prompt .= "2. ターゲット読者層と興味関心（重要度順）\n";
    $prompt .= "3. SEOに有効なキーワード（出現頻度と重要度を考慮）\n";
    $prompt .= "4. コンテンツの傾向と分析\n";
    $prompt .= "5. 記事作成時の注意点\n";
    $prompt .= "6. 推奨される記事戦略\n";
    $prompt .= "7. 競合他社分析と差別化ポイント\n";
    $prompt .= "8. 検索意図と読者のニーズ\n";
    
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
        $parsedUrl = parse_url($baseUrl);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return ['success' => false, 'error' => 'Invalid URL'];
        }
        
        $domain = $parsedUrl['host'];
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $baseDomain = $scheme . '://' . $domain;
        
        // ベースURL以下の下層に限定するためのパス設定
        $basePath = rtrim($parsedUrl['path'] ?? '/', '/');
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
                error_log("Failed to fetch content from: " . $currentUrl);
                continue;
            }
            
            // リンクを抽出
            $links = extractLinksFromHtml($html, $currentUrl, $domain);
            error_log("Found " . count($links) . " links from: " . $currentUrl);
            
            foreach ($links as $link) {
                if (!in_array($link, $foundUrls) && !in_array($link, $visitedUrls)) {
                    $foundUrls[] = $link;
                    
                    // ベースURL以下の下層のみを巡回対象に追加
                    if (strpos($link, $baseUrlPrefix) === 0 && count($urlsToVisit) < 50) {
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
    // ベースURL以下の下層に限定するためのプレフィックス設定
    $parsedBaseUrl = parse_url($baseUrl);
    $basePath = rtrim($parsedBaseUrl['path'] ?? '/', '/');
    $scheme = $parsedBaseUrl['scheme'] ?? 'https';
    $baseUrlPrefix = $scheme . '://' . $domain . $basePath;
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
        
        // ベースURL以下の下層のみを対象とする
        if (strpos($absoluteUrl, 'http') === 0 && strpos($absoluteUrl, $baseUrlPrefix) === 0) {
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
?>