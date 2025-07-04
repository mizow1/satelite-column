<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';
require_once 'ai_service.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'get_sites':
            echo json_encode(getSites());
            break;
        case 'get_site_data':
            echo json_encode(getSiteData($input['site_id']));
            break;
        case 'analyze_sites':
            echo json_encode(analyzeSites($input['urls'], $input['ai_model']));
            break;
        case 'create_article_outline':
            echo json_encode(createArticleOutline($input['site_id'], $input['ai_model']));
            break;
        case 'generate_article':
            echo json_encode(generateArticle($input['article_id'], $input['ai_model']));
            break;
        case 'generate_all_articles':
            echo json_encode(generateAllArticles($input['site_id'], $input['ai_model']));
            break;
        case 'export_csv':
            exportCsv($input['site_id']);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getSites() {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->query("SELECT id, name, url, created_at FROM sites ORDER BY created_at DESC");
        $sites = $stmt->fetchAll();
        
        return ['success' => true, 'sites' => $sites];
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
        
        // 記事更新
        $stmt = $pdo->prepare("UPDATE articles SET content = ?, status = 'generated', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $articleId]);
        
        // 生成ログ保存
        $stmt = $pdo->prepare("INSERT INTO ai_generation_logs (article_id, ai_model, prompt, response, generation_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $articleId,
            $aiModel,
            $articlePrompt,
            $content,
            0 // 実際の生成時間は測定していない
        ]);
        
        // 更新された記事を取得
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$articleId]);
        $updatedArticle = $stmt->fetch();
        
        return [
            'success' => true,
            'article' => $updatedArticle
        ];
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
        fputcsv($output, ['ID', 'タイトル', 'SEOキーワード', '概要', '記事内容', '作成日']);
        
        // データ
        foreach ($articles as $article) {
            fputcsv($output, [
                $article['id'],
                $article['title'],
                $article['seo_keywords'],
                $article['summary'],
                $article['content'],
                $article['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

function createArticlePrompt($article) {
    $prompt = "以下の記事概要を基に、占い好きな人向けの詳細なコラム記事を作成してください。\n\n";
    $prompt .= "タイトル: " . $article['title'] . "\n";
    $prompt .= "SEOキーワード: " . $article['seo_keywords'] . "\n";
    $prompt .= "概要: " . $article['summary'] . "\n\n";
    $prompt .= "記事の要件：\n";
    $prompt .= "- 2000-3000文字程度\n";
    $prompt .= "- 占い好きな人が興味を持つ内容\n";
    $prompt .= "- SEOを意識したキーワードの自然な配置\n";
    $prompt .= "- 読みやすい構成（見出し、段落分け）\n";
    $prompt .= "- 具体的で実用的な内容\n";
    
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
?>