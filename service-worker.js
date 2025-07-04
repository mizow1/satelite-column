// Service Worker Version 2.0 - デバッグ強化版
class AutoGenerationWorker {
    constructor() {
        this.isRunning = false;
        this.currentTask = null;
        this.totalArticles = 0;
        this.generatedCount = 0;
        this.config = null;
        this.siteId = null;
        this.aiModel = null;
        this.version = '2.0';
        console.log('AutoGenerationWorker initialized - version:', this.version);
        this.init();
    }

    init() {
        self.addEventListener('message', (event) => {
            const { type, data } = event.data;
            
            switch (type) {
                case 'START_AUTO_GENERATION':
                    this.startAutoGeneration(data);
                    break;
                case 'STOP_AUTO_GENERATION':
                    this.stopAutoGeneration();
                    break;
                case 'GET_STATUS':
                    this.sendStatus();
                    break;
            }
        });
    }

    async startAutoGeneration(config) {
        if (this.isRunning) {
            this.sendMessage('ERROR', { message: '既に自動生成が実行中です' });
            return;
        }

        this.isRunning = true;
        this.config = config;
        this.siteId = config.siteId;
        this.aiModel = config.aiModel;
        this.totalArticles = config.articleCount || 50;
        this.generatedCount = 0;

        this.sendMessage('STATUS_UPDATE', {
            isRunning: true,
            message: '自動生成を開始しました',
            progress: 0,
            totalArticles: this.totalArticles,
            generatedCount: 0
        });

        try {
            await this.generateArticlesSequentially();
        } catch (error) {
            this.sendMessage('ERROR', { message: 'エラーが発生しました: ' + error.message });
        } finally {
            this.isRunning = false;
            this.sendMessage('STATUS_UPDATE', {
                isRunning: false,
                message: '自動生成が完了しました',
                progress: 100,
                totalArticles: this.totalArticles,
                generatedCount: this.generatedCount
            });
        }
    }

    async generateArticlesSequentially() {
        while (this.isRunning && this.generatedCount < this.totalArticles) {
            try {
                // 記事概要を10件追加
                const outlineResult = await this.addArticleOutline();
                if (!outlineResult.success) {
                    throw new Error('記事概要の追加に失敗しました');
                }

                // 追加された記事から10件を生成
                const articles = outlineResult.articles.filter(article => article.status === 'draft');
                const batchSize = Math.min(10, articles.length);
                
                for (let i = 0; i < batchSize && this.isRunning && this.generatedCount < this.totalArticles; i++) {
                    const article = articles[i];
                    
                    this.sendMessage('STATUS_UPDATE', {
                        isRunning: true,
                        message: `記事を生成中: ${article.title}`,
                        progress: (this.generatedCount / this.totalArticles) * 100,
                        totalArticles: this.totalArticles,
                        generatedCount: this.generatedCount,
                        currentArticle: article.title
                    });

                    const result = await this.generateArticle(article.id);
                    if (result.success) {
                        this.generatedCount++;
                        this.sendMessage('STATUS_UPDATE', {
                            isRunning: true,
                            message: `記事を生成しました: ${article.title}`,
                            progress: (this.generatedCount / this.totalArticles) * 100,
                            totalArticles: this.totalArticles,
                            generatedCount: this.generatedCount
                        });
                    }

                    // 次の記事まで少し待機（API負荷軽減）
                    await this.sleep(2000);
                }

                // バッチ間で少し長めに待機
                await this.sleep(5000);

            } catch (error) {
                console.error('Batch processing error:', error);
                this.sendMessage('ERROR', { message: 'バッチ処理中にエラーが発生しました: ' + error.message });
                
                // 連続するエラーの場合は処理を中断
                if (error.message.includes('サーバーからHTMLレスポンスが返されました') || 
                    error.message.includes('HTTP error')) {
                    break;
                }
                
                // その他のエラーは少し待機して再試行
                await this.sleep(10000);
            }
        }
    }

    async addArticleOutline() {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_article_outline',
                site_id: this.siteId,
                ai_model: this.aiModel
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text();
        
        // レスポンスがHTMLかJSONかを判別
        if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
            console.error('HTML response received (first 500 chars):', text.substring(0, 500));
            
            // エラーメッセージを抽出してみる
            const errorMatch = text.match(/<title>(.*?)<\/title>/i);
            const errorTitle = errorMatch ? errorMatch[1] : 'Unknown error';
            
            throw new Error(`サーバーからHTMLレスポンスが返されました: ${errorTitle}`);
        }
        
        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('=== JSON PARSE ERROR ===');
            console.error('Error message:', error.message);
            console.error('Response text (first 1000 chars):', text.substring(0, 1000));
            console.error('Response text (full):', text);
            console.error('========================');
            throw new Error('サーバーから無効なJSONが返されました: ' + error.message);
        }
    }

    async generateArticle(articleId) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_article',
                article_id: articleId,
                ai_model: this.aiModel
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text();
        
        // レスポンスがHTMLかJSONかを判別
        if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
            console.error('HTML response received (first 500 chars):', text.substring(0, 500));
            
            // エラーメッセージを抽出してみる
            const errorMatch = text.match(/<title>(.*?)<\/title>/i);
            const errorTitle = errorMatch ? errorMatch[1] : 'Unknown error';
            
            throw new Error(`サーバーからHTMLレスポンスが返されました: ${errorTitle}`);
        }
        
        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('=== JSON PARSE ERROR ===');
            console.error('Error message:', error.message);
            console.error('Response text (first 1000 chars):', text.substring(0, 1000));
            console.error('Response text (full):', text);
            console.error('========================');
            throw new Error('サーバーから無効なJSONが返されました: ' + error.message);
        }
    }

    stopAutoGeneration() {
        this.isRunning = false;
        this.sendMessage('STATUS_UPDATE', {
            isRunning: false,
            message: '自動生成を停止しました',
            progress: (this.generatedCount / this.totalArticles) * 100,
            totalArticles: this.totalArticles,
            generatedCount: this.generatedCount
        });
    }

    sendStatus() {
        this.sendMessage('STATUS_UPDATE', {
            isRunning: this.isRunning,
            message: this.isRunning ? '自動生成実行中' : '自動生成停止中',
            progress: this.totalArticles > 0 ? (this.generatedCount / this.totalArticles) * 100 : 0,
            totalArticles: this.totalArticles,
            generatedCount: this.generatedCount
        });
    }

    sendMessage(type, data) {
        self.postMessage({ type, data });
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Service Worker のインスタンスを作成
const autoGenerationWorker = new AutoGenerationWorker();