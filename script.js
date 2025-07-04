class SatelliteColumnSystem {
    constructor() {
        this.initializeElements();
        this.bindEvents();
        this.loadSites();
    }

    initializeElements() {
        this.siteSelect = document.getElementById('site-select');
        this.aiModelSelect = document.getElementById('ai-model');
        this.urlInputs = document.getElementById('url-inputs');
        this.addUrlBtn = document.getElementById('add-url');
        this.analyzeSitesBtn = document.getElementById('analyze-sites');
        this.analysisSection = document.getElementById('analysis-section');
        this.analysisResult = document.getElementById('analysis-result');
        this.createArticleOutlineBtn = document.getElementById('create-article-outline');
        this.articleOutlineSection = document.getElementById('article-outline-section');
        this.articleOutlineTable = document.getElementById('article-outline-table');
        this.generateAllArticlesBtn = document.getElementById('generate-all-articles');
        this.exportCsvBtn = document.getElementById('export-csv');
        this.articleDetailModal = document.getElementById('article-detail-modal');
        this.loadingOverlay = document.getElementById('loading-overlay');
        
        this.currentSiteId = null;
        this.articles = [];
    }

    bindEvents() {
        this.addUrlBtn.addEventListener('click', () => this.addUrlInput());
        this.analyzeSitesBtn.addEventListener('click', () => this.analyzeSites());
        this.createArticleOutlineBtn.addEventListener('click', () => this.createArticleOutline());
        this.generateAllArticlesBtn.addEventListener('click', () => this.generateAllArticles());
        this.exportCsvBtn.addEventListener('click', () => this.exportCsv());
        this.siteSelect.addEventListener('change', (e) => this.loadSiteData(e.target.value));
        
        // モーダル関連
        document.querySelector('.close').addEventListener('click', () => this.closeModal());
        window.addEventListener('click', (e) => {
            if (e.target === this.articleDetailModal) {
                this.closeModal();
            }
        });
        
        // URL削除ボタンのイベント委譲
        this.urlInputs.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-url')) {
                this.removeUrlInput(e.target.closest('.url-input-group'));
            }
        });
    }

    addUrlInput() {
        const urlInputGroup = document.createElement('div');
        urlInputGroup.className = 'url-input-group';
        urlInputGroup.innerHTML = `
            <input type="url" placeholder="参照URLを入力" class="url-input">
            <button type="button" class="remove-url">削除</button>
        `;
        this.urlInputs.appendChild(urlInputGroup);
    }

    removeUrlInput(urlInputGroup) {
        if (this.urlInputs.children.length > 1) {
            urlInputGroup.remove();
        }
    }

    getValidUrls() {
        const urlInputs = this.urlInputs.querySelectorAll('.url-input');
        const urls = [];
        urlInputs.forEach(input => {
            const url = input.value.trim();
            if (url) {
                urls.push(url);
            }
        });
        return urls;
    }

    showLoading() {
        this.loadingOverlay.style.display = 'flex';
    }

    hideLoading() {
        this.loadingOverlay.style.display = 'none';
    }

    async loadSites() {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_sites' })
            });
            const result = await response.json();
            
            if (result.success) {
                this.updateSiteSelect(result.sites);
            }
        } catch (error) {
            console.error('サイト読み込みエラー:', error);
        }
    }

    updateSiteSelect(sites) {
        this.siteSelect.innerHTML = '<option value="">新規サイト</option>';
        sites.forEach(site => {
            const option = document.createElement('option');
            option.value = site.id;
            option.textContent = site.name;
            this.siteSelect.appendChild(option);
        });
    }

    async loadSiteData(siteId) {
        if (!siteId) {
            this.currentSiteId = null;
            this.analysisSection.style.display = 'none';
            this.articleOutlineSection.style.display = 'none';
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'get_site_data', 
                    site_id: siteId 
                })
            });
            const result = await response.json();
            
            if (result.success) {
                this.currentSiteId = siteId;
                this.displaySiteData(result.data);
            }
        } catch (error) {
            console.error('サイトデータ読み込みエラー:', error);
        } finally {
            this.hideLoading();
        }
    }

    displaySiteData(data) {
        if (data.analysis) {
            this.analysisResult.innerHTML = this.formatAnalysisResult(data.analysis);
            this.analysisSection.style.display = 'block';
        }
        
        if (data.articles && data.articles.length > 0) {
            this.articles = data.articles;
            this.displayArticleOutline();
            this.articleOutlineSection.style.display = 'block';
        }
    }

    async analyzeSites() {
        const urls = this.getValidUrls();
        if (urls.length === 0) {
            alert('少なくとも1つのURLを入力してください。');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'analyze_sites',
                    urls: urls,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await response.json();
            
            if (result.success) {
                this.currentSiteId = result.site_id;
                this.analysisResult.innerHTML = this.formatAnalysisResult(result.analysis);
                this.analysisSection.style.display = 'block';
                this.loadSites(); // サイト選択を更新
            } else {
                alert('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('サイト分析エラー:', error);
            alert('サイト分析中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    formatAnalysisResult(analysis) {
        return `
            <h3>サイト特徴分析</h3>
            <div style="white-space: pre-wrap; margin: 15px 0;">${analysis}</div>
        `;
    }

    async createArticleOutline() {
        if (!this.currentSiteId) {
            alert('まずサイト分析を実行してください。');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create_article_outline',
                    site_id: this.currentSiteId,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await response.json();
            
            if (result.success) {
                this.articles = result.articles;
                this.displayArticleOutline();
                this.articleOutlineSection.style.display = 'block';
            } else {
                alert('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('記事概要作成エラー:', error);
            alert('記事概要作成中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    displayArticleOutline() {
        const tableHtml = `
            <table class="article-table">
                <thead>
                    <tr>
                        <th>記事タイトル</th>
                        <th>SEOキーワード</th>
                        <th>概要</th>
                        <th>ステータス</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.articles.map(article => `
                        <tr>
                            <td>
                                ${article.status === 'generated' ? 
                                    `<a href="#" class="article-link" data-article-id="${article.id}">${article.title}</a>` : 
                                    article.title}
                            </td>
                            <td>${article.seo_keywords}</td>
                            <td>${article.summary}</td>
                            <td><span class="status-${article.status}">${this.getStatusText(article.status)}</span></td>
                            <td>
                                ${article.status !== 'generated' ? 
                                    `<button class="generate-article-btn" data-article-id="${article.id}">記事作成</button>` : 
                                    '作成済み'}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        
        this.articleOutlineTable.innerHTML = tableHtml;
        
        // 記事作成ボタンのイベント
        this.articleOutlineTable.addEventListener('click', (e) => {
            if (e.target.classList.contains('generate-article-btn')) {
                const articleId = e.target.dataset.articleId;
                this.generateArticle(articleId);
            } else if (e.target.classList.contains('article-link')) {
                e.preventDefault();
                const articleId = e.target.dataset.articleId;
                this.showArticleDetail(articleId);
            }
        });
    }

    getStatusText(status) {
        switch (status) {
            case 'draft': return '下書き';
            case 'generated': return '生成済み';
            case 'published': return '公開済み';
            default: return 'unknown';
        }
    }

    async generateArticle(articleId) {
        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_article',
                    article_id: articleId,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await response.json();
            
            if (result.success) {
                // 記事一覧を更新
                const articleIndex = this.articles.findIndex(a => a.id == articleId);
                if (articleIndex !== -1) {
                    this.articles[articleIndex] = result.article;
                    this.displayArticleOutline();
                }
                alert('記事を生成しました。');
            } else {
                alert('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('記事生成エラー:', error);
            alert('記事生成中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    async generateAllArticles() {
        if (!this.currentSiteId) {
            alert('まず記事概要を作成してください。');
            return;
        }

        const draftArticles = this.articles.filter(article => article.status === 'draft');
        if (draftArticles.length === 0) {
            alert('生成する記事がありません。');
            return;
        }

        if (!confirm(`${draftArticles.length}記事を一括生成しますか？（時間がかかる場合があります）`)) {
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_all_articles',
                    site_id: this.currentSiteId,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await response.json();
            
            if (result.success) {
                this.articles = result.articles;
                this.displayArticleOutline();
                alert('全記事を生成しました。');
            } else {
                alert('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('全記事生成エラー:', error);
            alert('全記事生成中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    async showArticleDetail(articleId) {
        const article = this.articles.find(a => a.id == articleId);
        if (!article || article.status !== 'generated') {
            alert('記事が見つからないか、まだ生成されていません。');
            return;
        }

        document.getElementById('article-detail-content').innerHTML = `
            <h3>${article.title}</h3>
            <p><strong>SEOキーワード:</strong> ${article.seo_keywords}</p>
            <p><strong>概要:</strong> ${article.summary}</p>
            <hr>
            <div style="white-space: pre-wrap; line-height: 1.6;">${article.content}</div>
        `;
        
        this.articleDetailModal.style.display = 'block';
    }

    closeModal() {
        this.articleDetailModal.style.display = 'none';
    }

    async exportCsv() {
        if (!this.currentSiteId) {
            alert('まずサイトを選択してください。');
            return;
        }

        const generatedArticles = this.articles.filter(article => article.status === 'generated');
        if (generatedArticles.length === 0) {
            alert('エクスポートする記事がありません。');
            return;
        }

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'export_csv',
                    site_id: this.currentSiteId
                })
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
            } else {
                alert('CSV出力中にエラーが発生しました。');
            }
        } catch (error) {
            console.error('CSV出力エラー:', error);
            alert('CSV出力中にエラーが発生しました。');
        }
    }
}

// アプリケーション初期化
document.addEventListener('DOMContentLoaded', () => {
    new SatelliteColumnSystem();
});