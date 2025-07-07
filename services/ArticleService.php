<?php
class ArticleService {
    private $promptGenerator;
    
    public function __construct() {
        $this->promptGenerator = new PromptGenerator();
    }
    
    public function createArticleOutline($siteId, $aiModel) {
        try {
            $pdo = DatabaseConfig::getConnection();
            
            $site = $this->getSiteById($siteId);
            if (!$site) {
                return ['success' => false, 'error' => 'Site not found'];
            }
            
            $aiService = new AIService();
            $outlinePrompt = $this->promptGenerator->createOutlinePrompt($site['analysis_result']);
            $outlineData = $aiService->generateText($outlinePrompt, $aiModel);
            
            $articles = $this->parseArticleOutline($outlineData);
            
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
    
    public function addArticleOutline($siteId, $aiModel) {
        try {
            $pdo = DatabaseConfig::getConnection();
            
            $site = $this->getSiteById($siteId);
            if (!$site) {
                return ['success' => false, 'error' => 'Site not found'];
            }
            
            $existingCount = $this->getArticleCount($siteId);
            
            $aiService = new AIService();
            $outlinePrompt = $this->promptGenerator->createAdditionalOutlinePrompt($site['analysis_result'], $existingCount);
            $outlineData = $aiService->generateText($outlinePrompt, $aiModel);
            
            if (empty($outlineData)) {
                return ['success' => false, 'error' => 'AI service returned empty response'];
            }
            
            $articles = $this->parseArticleOutline($outlineData);
            
            if (empty($articles)) {
                return ['success' => false, 'error' => 'Failed to parse article outline'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO articles (site_id, title, seo_keywords, summary, ai_model, status) VALUES (?, ?, ?, ?, ?, 'draft')");
            
            foreach ($articles as $article) {
                if (empty($article['title']) || empty($article['keywords']) || empty($article['summary'])) {
                    continue;
                }
                
                $stmt->execute([
                    $siteId,
                    $article['title'],
                    $article['keywords'],
                    $article['summary'],
                    $aiModel
                ]);
            }
            
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
    
    public function generateArticle($articleId, $aiModel) {
        try {
            $pdo = DatabaseConfig::getConnection();
            
            $article = $this->getArticleWithSite($articleId);
            if (!$article) {
                return ['success' => false, 'error' => 'Article not found'];
            }
            
            $aiService = new AIService();
            $articlePrompt = $this->promptGenerator->createArticlePrompt($article);
            $content = $aiService->generateText($articlePrompt, $aiModel);
            
            if (empty($content)) {
                return ['success' => false, 'error' => 'AI service returned empty content'];
            }
            
            $stmt = $pdo->prepare("UPDATE articles SET content = ?, status = 'generated', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$content, $articleId]);
            
            $this->logGeneration($articleId, $aiModel, $articlePrompt, $content);
            
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
    
    public function generateAllArticles($siteId, $aiModel) {
        try {
            $pdo = DatabaseConfig::getConnection();
            
            $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? AND status = 'draft'");
            $stmt->execute([$siteId]);
            $draftArticles = $stmt->fetchAll();
            
            $aiService = new AIService();
            
            foreach ($draftArticles as $article) {
                $articlePrompt = $this->promptGenerator->createArticlePrompt($article);
                $content = $aiService->generateText($articlePrompt, $aiModel);
                
                $stmt = $pdo->prepare("UPDATE articles SET content = ?, status = 'generated', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$content, $article['id']]);
                
                $this->logGeneration($article['id'], $aiModel, $articlePrompt, $content);
                
                usleep(100000);
            }
            
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
    
    public function exportCsv($siteId) {
        try {
            $pdo = DatabaseConfig::getConnection();
            
            $stmt = $pdo->prepare("SELECT * FROM articles WHERE site_id = ? AND status = 'generated' ORDER BY created_at DESC");
            $stmt->execute([$siteId]);
            $articles = $stmt->fetchAll();
            
            if (empty($articles)) {
                echo json_encode(['success' => false, 'error' => 'No articles to export']);
                return;
            }
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="articles_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            fputs($output, "\xEF\xBB\xBF");
            
            fputcsv($output, ['ID', 'タイトル', 'SEOキーワード', '概要', '記事内容', '投稿日時', '作成日']);
            
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
    
    public function updatePublishDate($articleId, $publishDate) {
        try {
            $pdo = DatabaseConfig::getConnection();
            
            $stmt = $pdo->prepare("SELECT id FROM articles WHERE id = ?");
            $stmt->execute([$articleId]);
            $article = $stmt->fetch();
            
            if (!$article) {
                return ['success' => false, 'error' => 'Article not found'];
            }
            
            $stmt = $pdo->prepare("UPDATE articles SET publish_date = ? WHERE id = ?");
            $stmt->execute([$publishDate, $articleId]);
            
            return ['success' => true, 'message' => 'Publish date updated successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function getSiteById($siteId) {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        return $stmt->fetch();
    }
    
    private function getArticleCount($siteId) {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE site_id = ?");
        $stmt->execute([$siteId]);
        return $stmt->fetchColumn();
    }
    
    private function getArticleWithSite($articleId) {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT a.*, s.analysis_result FROM articles a JOIN sites s ON a.site_id = s.id WHERE a.id = ?");
        $stmt->execute([$articleId]);
        return $stmt->fetch();
    }
    
    private function logGeneration($articleId, $aiModel, $prompt, $response) {
        try {
            $pdo = DatabaseConfig::getConnection();
            $stmt = $pdo->prepare("INSERT INTO ai_generation_logs (article_id, ai_model, prompt, response, generation_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$articleId, $aiModel, $prompt, $response, 0]);
        } catch (Exception $e) {
            // ログ保存エラーは無視
        }
    }
    
    private function parseArticleOutline($outlineData) {
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
}