<?php
// エラー出力を抑制（JSON以外の出力を防ぐため）
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 出力バッファリングを開始
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// JSONレスポンスを送信する関数
function sendJsonResponse($data) {
    // 出力バッファをクリア
    ob_clean();
    echo json_encode($data);
    exit;
}

try {
    // 必要なファイルをインクルード
    if (!file_exists('config.php')) {
        sendJsonResponse(['success' => false, 'error' => 'config.php not found']);
    }
    if (!file_exists('ai_service.php')) {
        sendJsonResponse(['success' => false, 'error' => 'ai_service.php not found']);
    }
    
    require_once 'config.php';
    require_once 'ai_service.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(['success' => false, 'error' => 'Invalid JSON input']);
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
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
        default:
            sendJsonResponse(['success' => false, 'error' => 'Invalid action']);
    }
} catch (ParseError $e) {
    error_log('PHP Parse Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    sendJsonResponse(['success' => false, 'error' => 'PHP Parse Error: ' . $e->getMessage()]);
} catch (Error $e) {
    error_log('PHP Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    sendJsonResponse(['success' => false, 'error' => 'PHP Fatal Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
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
        $analysis = $aiService->generateText($analysisPrompt, $aiModel);
        
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
        
        return [
            'success' => true,
            'site_id' => $siteId,
            'analysis' => $analysis
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
        $outlineData = $aiService->generateText($outlinePrompt, $aiModel);
        
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
        $content = $aiService->generateText($articlePrompt, $aiModel);
        
        if (empty($content)) {
            return ['success' => false, 'error' => 'AI service returned empty content'];
        }
        
        // 記事更新
        $stmt = $pdo->prepare("UPDATE articles SET content = ?, status = 'generated', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $articleId]);
        
        // 生成ログ保存
        try {
            $stmt = $pdo->prepare("INSERT INTO ai_generation_logs (article_id, ai_model, prompt, response, generation_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $articleId,
                $aiModel,
                $articlePrompt,
                $content,
                0 // 実際の生成時間は測定していない
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
        $outlinePrompt = createAdditionalOutlinePrompt($site['analysis_result'], $existingCount);
        $outlineData = $aiService->generateText($outlinePrompt, $aiModel);
        
        if (empty($outlineData)) {
            return ['success' => false, 'error' => 'AI service returned empty response'];
        }
        
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
        $textContent = extractTextFromHtml($content);
        return sanitizeTextContent($textContent);
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
    $prompt = "以下のサイトの内容を分析して、占い好きな人に向けたコラム記事を作成するための特徴とキーワードを分析してください。\n\n";
    
    foreach ($siteContents as $site) {
        $prompt .= "URL: " . $site['url'] . "\n";
        $prompt .= "内容: " . substr($site['content'], 0, 2000) . "...\n\n";
    }
    
    $prompt .= "以下の観点で分析し、マークダウン形式で出力してください：\n";
    $prompt .= "1. サイトの特徴\n";
    $prompt .= "2. 占い好きな人が興味を持ちそうなポイント\n";
    $prompt .= "3. SEOに有効なキーワード\n";
    $prompt .= "4. コンテンツの傾向\n";
    $prompt .= "5. 記事作成時の注意点\n";
    
    return $prompt;
}

function createOutlinePrompt($analysisResult) {
    $prompt = "以下のサイト分析結果を基に、占い好きな人向けのコラム記事を100記事分作成してください。\n\n";
    $prompt .= "分析結果:\n" . $analysisResult . "\n\n";
    $prompt .= "以下の形式で、記事タイトル、SEOキーワード、記事概要をセットで100記事分出力してください：\n\n";
    $prompt .= "---記事1---\n";
    $prompt .= "タイトル: [記事タイトル]\n";
    $prompt .= "キーワード: [SEOキーワード（カンマ区切り）]\n";
    $prompt .= "概要: [記事の概要]\n\n";
    $prompt .= "（100記事まで繰り返し）\n";
    
    return $prompt;
}

function createAdditionalOutlinePrompt($analysisResult, $existingCount) {
    $prompt = "以下のサイト分析結果を基に、占い好きな人向けのコラム記事を10記事追加で作成してください。\n\n";
    $prompt .= "分析結果:\n" . $analysisResult . "\n\n";
    $prompt .= "既に{$existingCount}記事が存在するため、重複しない新しい記事を作成してください。\n\n";
    $prompt .= "以下の形式で、記事タイトル、SEOキーワード、記事概要をセットで10記事分出力してください：\n\n";
    $prompt .= "---記事1---\n";
    $prompt .= "タイトル: [記事タイトル]\n";
    $prompt .= "キーワード: [SEOキーワード（カンマ区切り）]\n";
    $prompt .= "概要: [記事の概要]\n\n";
    $prompt .= "（10記事まで繰り返し）\n";
    
    return $prompt;
}

function createArticlePrompt($article) {
    $prompt = "以下の記事概要を基に、占い好きな人向けの詳細なコラム記事を作成してください。\n\n";
    $prompt .= "タイトル: " . $article['title'] . "\n";
    $prompt .= "SEOキーワード: " . $article['seo_keywords'] . "\n";
    $prompt .= "概要: " . $article['summary'] . "\n\n";
    $prompt .= "記事の要件：\n";
    $prompt .= "- 必ず10,000文字以上の詳細な記事を作成する（これは最重要要件です）\n";
    $prompt .= "- 完全なマークダウン形式で出力する\n";
    $prompt .= "- 占い好きな人が興味を持つ内容\n";
    $prompt .= "- SEOを意識したキーワードの自然な配置\n";
    $prompt .= "- 読みやすい構成（見出し、段落分け）\n";
    $prompt .= "- 具体的で実用的な内容\n";
    $prompt .= "- 深い洞察と詳細な解説\n";
    $prompt .= "- 例や事例を豊富に含む\n";
    $prompt .= "- 実践的なアドバイスとガイダンス\n";
    $prompt .= "- 読者が最後まで読み続けられる魅力的な内容\n";
    $prompt .= "- 各セクションごとに詳しい説明を含む\n";
    $prompt .= "- 占いの背景知識や歴史的な情報も含める\n";
    $prompt .= "- 読者が実際に活用できる具体的な方法を提示\n";
    
    $prompt .= "\n記事構成の指針：\n";
    $prompt .= "1. 導入部（800-1000文字）：読者の関心を引く導入\n";
    $prompt .= "2. 基礎知識（1500-2000文字）：テーマの基本的な説明\n";
    $prompt .= "3. 詳細解説（2000-3000文字）：具体的な内容の深掘り\n";
    $prompt .= "4. 実践的な応用（2000-2500文字）：読者が実践できる方法\n";
    $prompt .= "5. 事例・体験談（1500-2000文字）：具体的な例や体験談\n";
    $prompt .= "6. まとめ・次のステップ（1000-1500文字）：総括と今後の展望\n";
    
    $prompt .= "\n重要な注意事項：\n";
    $prompt .= "- 省略表現（「[以下、さらに詳細な解説と実践的なアドバイスが続きます...]」など）は絶対に使用しない\n";
    $prompt .= "- 「この記事の続きをご希望の場合」などの制作者向けメッセージは厳禁\n";
    $prompt .= "- 必ず完全な記事を作成し、最後まで詳細に執筆する\n";
    $prompt .= "- 文字数カウントを行い、10,000文字以上を確実に達成する\n";
    $prompt .= "- 各セクションは必ず指定された文字数を満たす内容で執筆する\n";
    
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
?>