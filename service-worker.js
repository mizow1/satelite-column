// Service Worker Version 3.0 - マルチサイト対応版
class AutoGenerationWorker {
    constructor() {
        this.runningTasks = new Map(); // サイトID → タスク情報のマップ
        this.version = '3.0';
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
                    // 現在はdata.siteIdがないので、URLパラメータから取得
                    const urlParams = new URLSearchParams(self.location.search);
                    const siteId = urlParams.get('siteId') || data.siteId;
                    this.stopAutoGeneration(siteId);
                    break;
                case 'GET_STATUS':
                    this.sendStatus(data.siteId);
                    break;
            }
        });
    }

    async startAutoGeneration(config) {
        const siteId = config.siteId;
        
        if (this.runningTasks.has(siteId)) {
            this.sendMessage('ERROR', { message: `サイト${siteId}は既に自動生成が実行中です`, siteId });
            return;
        }

        const taskInfo = {
            isRunning: true,
            siteId: siteId,
            aiModel: config.aiModel,
            totalArticles: config.articleCount || 50,
            generatedCount: 0,
            config: config
        };
        
        this.runningTasks.set(siteId, taskInfo);

        this.sendMessage('STATUS_UPDATE', {
            isRunning: true,
            message: '自動生成を開始しました',
            progress: 0,
            totalArticles: taskInfo.totalArticles,
            generatedCount: 0,
            siteId: siteId
        });

        try {
            await this.generateArticlesSequentially(siteId);
        } catch (error) {
            this.sendMessage('ERROR', { message: 'エラーが発生しました: ' + error.message, siteId });
        } finally {
            const finalTaskInfo = this.runningTasks.get(siteId);
            this.runningTasks.delete(siteId);
            
            this.sendMessage('STATUS_UPDATE', {
                isRunning: false,
                message: '自動生成が完了しました',
                progress: 100,
                totalArticles: finalTaskInfo ? finalTaskInfo.totalArticles : 0,
                generatedCount: finalTaskInfo ? finalTaskInfo.generatedCount : 0,
                siteId: siteId
            });
        }
    }

    async generateArticlesSequentially(siteId) {
        const taskInfo = this.runningTasks.get(siteId);
        if (!taskInfo) return;
        
        while (taskInfo.isRunning && taskInfo.generatedCount < taskInfo.totalArticles) {
            try {
                // 記事概要を10件追加
                const outlineResult = await this.addArticleOutline(siteId, taskInfo.aiModel);
                if (!outlineResult.success) {
                    throw new Error('記事概要の追加に失敗しました');
                }

                // 追加された記事から10件を生成
                const articles = outlineResult.articles.filter(article => article.status === 'draft');
                const batchSize = Math.min(10, articles.length);
                
                for (let i = 0; i < batchSize && taskInfo.isRunning && taskInfo.generatedCount < taskInfo.totalArticles; i++) {
                    const article = articles[i];
                    
                    this.sendMessage('STATUS_UPDATE', {
                        isRunning: true,
                        message: `記事を生成中: ${article.title}`,
                        progress: (taskInfo.generatedCount / taskInfo.totalArticles) * 100,
                        totalArticles: taskInfo.totalArticles,
                        generatedCount: taskInfo.generatedCount,
                        currentArticle: article.title,
                        siteId: siteId
                    });

                    const result = await this.generateArticle(article.id, taskInfo.aiModel);
                    if (result.success) {
                        taskInfo.generatedCount++;
                        this.sendMessage('STATUS_UPDATE', {
                            isRunning: true,
                            message: `記事を生成しました: ${article.title}`,
                            progress: (taskInfo.generatedCount / taskInfo.totalArticles) * 100,
                            totalArticles: taskInfo.totalArticles,
                            generatedCount: taskInfo.generatedCount,
                            siteId: siteId
                        });
                    }

                    // 次の記事まで少し待機（API負荷軽減）
                    await this.sleep(2000);
                }

                // バッチ間で少し長めに待機
                await this.sleep(5000);

            } catch (error) {
                console.error(`Batch processing error for site ${siteId}:`, error);
                this.sendMessage('ERROR', { message: 'バッチ処理中にエラーが発生しました: ' + error.message, siteId });
                
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

    async addArticleOutline(siteId, aiModel) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_article_outline',
                site_id: siteId,
                ai_model: aiModel
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

    async generateArticle(articleId, aiModel) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_article',
                article_id: articleId,
                ai_model: aiModel
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

    stopAutoGeneration(siteId) {
        const taskInfo = this.runningTasks.get(siteId);
        if (taskInfo) {
            taskInfo.isRunning = false;
            this.sendMessage('STATUS_UPDATE', {
                isRunning: false,
                message: '自動生成を停止しました',
                progress: (taskInfo.generatedCount / taskInfo.totalArticles) * 100,
                totalArticles: taskInfo.totalArticles,
                generatedCount: taskInfo.generatedCount,
                siteId: siteId
            });
            this.runningTasks.delete(siteId);
        }
    }

    sendStatus(siteId) {
        if (siteId) {
            const taskInfo = this.runningTasks.get(siteId);
            if (taskInfo) {
                this.sendMessage('STATUS_UPDATE', {
                    isRunning: taskInfo.isRunning,
                    message: taskInfo.isRunning ? '自動生成実行中' : '自動生成停止中',
                    progress: taskInfo.totalArticles > 0 ? (taskInfo.generatedCount / taskInfo.totalArticles) * 100 : 0,
                    totalArticles: taskInfo.totalArticles,
                    generatedCount: taskInfo.generatedCount,
                    siteId: siteId
                });
            }
        } else {
            // 全サイトのステータスを送信
            for (const [id, taskInfo] of this.runningTasks.entries()) {
                this.sendMessage('STATUS_UPDATE', {
                    isRunning: taskInfo.isRunning,
                    message: taskInfo.isRunning ? '自動生成実行中' : '自動生成停止中',
                    progress: taskInfo.totalArticles > 0 ? (taskInfo.generatedCount / taskInfo.totalArticles) * 100 : 0,
                    totalArticles: taskInfo.totalArticles,
                    generatedCount: taskInfo.generatedCount,
                    siteId: id
                });
            }
        }
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