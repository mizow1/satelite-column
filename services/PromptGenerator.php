<?php
class PromptGenerator {
    
    public function createAnalysisPrompt($siteContents) {
        $prompt = "以下のサイトの内容を分析して、コラム記事を作成するための特徴とキーワードを分析してください。\n\n";
        
        foreach ($siteContents as $site) {
            $prompt .= "URL: " . $site['url'] . "\n";
            $prompt .= "内容: " . substr($site['content'], 0, 2000) . "...\n\n";
        }
        
        $prompt .= "以下の観点で分析し、マークダウン形式で出力してください：\n";
        $prompt .= "1. サイトの特徴\n";
        $prompt .= "2. 読者が興味を持ちそうなポイント\n";
        $prompt .= "3. SEOに有効なキーワード\n";
        $prompt .= "4. コンテンツの傾向\n";
        $prompt .= "5. 記事作成時の注意点\n";
        
        return $prompt;
    }
    
    public function createOutlinePrompt($analysisResult) {
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
    
    public function createAdditionalOutlinePrompt($analysisResult, $existingCount, $count = 10) {
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
    
    public function createArticlePrompt($article) {
        $prompt = "以下の記事概要を基に、詳細なコラム記事を作成してください。\n\n";
        $prompt .= "タイトル: " . $article['title'] . "\n";
        $prompt .= "SEOキーワード: " . $article['seo_keywords'] . "\n";
        $prompt .= "概要: " . $article['summary'] . "\n\n";
        
        $prompt .= $this->getArticleRequirements();
        $prompt .= $this->getArticleStructure();
        $prompt .= $this->getImportantNotes();
        
        return $prompt;
    }
    
    private function getArticleRequirements() {
        return "記事の要件：\n" .
               "- 詳細で充実した記事を作成する\n" .
               "- 完全なマークダウン形式で出力する\n" .
               "- 占い好きな人が興味を持つ内容\n" .
               "- SEOを意識したキーワードの自然な配置\n" .
               "- 読みやすい構成（見出し、段落分け）\n" .
               "- 具体的で実用的な内容\n" .
               "- 深い洞察と詳細な解説\n" .
               "- 例や事例を豊富に含む\n" .
               "- 実践的なアドバイスとガイダンス\n" .
               "- 読者が最後まで読み続けられる魅力的な内容\n" .
               "- 各セクションごとに詳しい説明を含む\n" .
               "- 占いの背景知識や歴史的な情報も含める\n" .
               "- 読者が実際に活用できる具体的な方法を提示\n\n";
               "- 一万文字以上の記事を作成する\n\n";
    }
    
    private function getArticleStructure() {
        return "記事構成の指針：\n" .
               "1. 導入部：読者の関心を引く導入\n" .
               "2. 基礎知識：テーマの基本的な説明\n" .
               "3. 詳細解説：具体的な内容の深掘り\n" .
               "4. 実践的な応用：読者が実践できる方法\n" .
               "5. 事例・体験談：具体的な例や体験談\n" .
               "6. まとめ・次のステップ：総括と今後の展望\n\n";
    }
    
    private function getImportantNotes() {
        return "重要な注意事項：\n" .
               "- 省略表現（「[以下、さらに詳細な解説と実践的なアドバイスが続きます...]」など）は絶対に使用しない\n" .
               "- 「この記事の続きをご希望の場合」などの制作者向けメッセージは厳禁\n" .
               "- 必ず完全な記事を作成し、最後まで詳細に執筆する\n" .
               "- 記事内に文字数や文字数カウントは一切記載しない\n" .
               "- 各セクションは充実した内容で執筆する\n";
               "- 一万文字以上の記事を作成する\n\n";
    }
    
    public function createCustomPrompt($template, $variables = []) {
        $prompt = $template;
        
        foreach ($variables as $key => $value) {
            $prompt = str_replace("{{" . $key . "}}", $value, $prompt);
        }
        
        return $prompt;
    }
    
    public function validatePrompt($prompt) {
        if (empty($prompt)) {
            throw new InvalidArgumentException('Prompt cannot be empty');
        }
        
        if (mb_strlen($prompt) > 50000) {
            throw new InvalidArgumentException('Prompt is too long (max 50,000 characters)');
        }
        
        return true;
    }
    
    public function optimizePrompt($prompt) {
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        $prompt = preg_replace('/\n{3,}/', "\n\n", $prompt);
        $prompt = trim($prompt);
        
        return $prompt;
    }
}