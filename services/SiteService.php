<?php
class SiteService {
    private $contentProcessor;
    private $promptGenerator;
    
    public function __construct() {
        $this->contentProcessor = new ContentProcessor();
        $this->promptGenerator = new PromptGenerator();
    }
    
    public function getSites() {
        try {
            $pdo = DatabaseConfig::getConnection();
            $stmt = $pdo->query("SELECT id, name, url, created_at FROM sites ORDER BY created_at DESC");
            $sites = $stmt->fetchAll();
            
            return ['success' => true, 'sites' => $sites];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getSiteData($siteId) {
        try {
            $pdo = DatabaseConfig::getConnection();
            
            $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
            $stmt->execute([$siteId]);
            $site = $stmt->fetch();
            
            if (!$site) {
                return ['success' => false, 'error' => 'Site not found'];
            }
            
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
    
    public function analyzeSites($urls, $aiModel) {
        try {
            $pdo = DatabaseConfig::getConnection();
            
            $siteContents = [];
            foreach ($urls as $url) {
                $content = $this->contentProcessor->fetchWebContent($url);
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
            
            $aiService = new AIService();
            $analysisPrompt = $this->promptGenerator->createAnalysisPrompt($siteContents);
            $analysis = $aiService->generateText($analysisPrompt, $aiModel);
            
            $siteName = $this->extractSiteName($urls[0]);
            $stmt = $pdo->prepare("INSERT INTO sites (name, url, analysis_result, features, keywords) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $siteName,
                json_encode($urls),
                $analysis,
                '',
                ''
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
    
    private function extractSiteName($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'Unknown Site';
    }
}