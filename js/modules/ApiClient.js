export class ApiClient {
    constructor(baseUrl = 'api.php') {
        this.baseUrl = baseUrl;
    }
    
    async request(action, data = {}) {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...data })
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            console.error('API request error:', error);
            throw new Error('API通信中にエラーが発生しました: ' + error.message);
        }
    }
    
    async handleResponse(response) {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        
        if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
            console.error('HTML response received (first 500 chars):', text.substring(0, 500));
            
            const errorMatch = text.match(/<title>(.*?)<\/title>/i);
            const errorTitle = errorMatch ? errorMatch[1] : 'Unknown error';
            
            throw new Error(`サーバーからHTMLレスポンスが返されました: ${errorTitle}`);
        }
        
        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('Invalid JSON response:', text.substring(0, 200));
            throw new Error('サーバーから無効なJSONが返されました: ' + error.message);
        }
    }
    
    async getSites() {
        return await this.request('get_sites');
    }
    
    async getSiteData(siteId) {
        return await this.request('get_site_data', { site_id: siteId });
    }
    
    async analyzeSites(urls, aiModel) {
        return await this.request('analyze_sites', { urls, ai_model: aiModel });
    }
    
    async createArticleOutline(siteId, aiModel) {
        return await this.request('create_article_outline', { site_id: siteId, ai_model: aiModel });
    }
    
    async addArticleOutline(siteId, aiModel) {
        return await this.request('add_article_outline', { site_id: siteId, ai_model: aiModel });
    }
    
    async generateArticle(articleId, aiModel) {
        return await this.request('generate_article', { article_id: articleId, ai_model: aiModel });
    }
    
    async generateAllArticles(siteId, aiModel) {
        return await this.request('generate_all_articles', { site_id: siteId, ai_model: aiModel });
    }
    
    async updatePublishDate(articleId, publishDate) {
        return await this.request('update_publish_date', { article_id: articleId, publish_date: publishDate });
    }
    
    async exportCsv(siteId) {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'export_csv', site_id: siteId })
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `articles_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                return { success: true };
            } else {
                throw new Error('CSV出力中にエラーが発生しました');
            }
        } catch (error) {
            console.error('CSV出力エラー:', error);
            throw error;
        }
    }
}