class AutoGenerationManager {
    constructor() {
        this.siteWorkers = new Map(); // サイトIDをキーとしてWorkerを管理
        this.siteStatuses = new Map(); // サイトIDをキーとしてステータスを管理
        this.initializeElements();
        this.bindEvents();
    }

    initializeElements() {
        this.autoArticleCountInput = document.getElementById('auto-article-count');
        this.startAutoGenerationBtn = document.getElementById('start-auto-generation');
        this.stopAutoGenerationBtn = document.getElementById('stop-auto-generation');
        this.autoGenerationStatus = document.getElementById('auto-generation-status');
        this.progressFill = document.getElementById('progress-fill');
        this.statusText = document.getElementById('status-text');
        this.progressDetails = document.getElementById('progress-details');
    }

    bindEvents() {
        this.startAutoGenerationBtn.addEventListener('click', () => this.startAutoGeneration());
        this.stopAutoGenerationBtn.addEventListener('click', () => this.stopAutoGeneration());
    }

    createWorkerForSite(siteId) {
        if (this.siteWorkers.has(siteId)) {
            return this.siteWorkers.get(siteId);
        }

        // サイト専用のWorkerを作成
        const timestamp = Date.now();
        const worker = new Worker(`service-worker.js?v=${timestamp}&siteId=${siteId}`);
        
        worker.onmessage = (event) => {
            const { type, data } = event.data;
            
            switch (type) {
                case 'STATUS_UPDATE':
                    this.updateStatus(siteId, data);
                    break;
                case 'ERROR':
                    this.showError(siteId, data.message);
                    break;
            }
        };
        
        this.siteWorkers.set(siteId, worker);
        this.siteStatuses.set(siteId, { isRunning: false, progress: 0, generatedCount: 0, totalArticles: 0 });
        
        console.log(`Worker initialized for site ${siteId} with version:`, timestamp);
        return worker;
    }

    startAutoGeneration() {
        if (!window.satelliteSystem.currentSiteId) {
            alert('まずサイト分析を実行してください。');
            return;
        }

        const siteId = window.satelliteSystem.currentSiteId;
        const currentStatus = this.siteStatuses.get(siteId);
        
        if (currentStatus && currentStatus.isRunning) {
            alert('このサイトは既に自動生成中です。');
            return;
        }

        const articleCount = parseInt(this.autoArticleCountInput.value);
        if (isNaN(articleCount) || articleCount < 1) {
            alert('作成件数を正しく入力してください。');
            return;
        }

        const worker = this.createWorkerForSite(siteId);
        
        // 現在のサイトのUIを更新
        this.updateUIForSite(siteId, true);

        worker.postMessage({
            type: 'START_AUTO_GENERATION',
            data: {
                siteId: siteId,
                aiModel: window.satelliteSystem.aiModelSelect.value,
                articleCount: articleCount
            }
        });
    }

    stopAutoGeneration() {
        if (!window.satelliteSystem.currentSiteId) {
            return;
        }

        const siteId = window.satelliteSystem.currentSiteId;
        const worker = this.siteWorkers.get(siteId);
        
        if (worker) {
            worker.postMessage({ type: 'STOP_AUTO_GENERATION' });
        }
        
        // 現在のサイトのUIを更新
        this.updateUIForSite(siteId, false);
    }

    updateStatus(siteId, data) {
        // サイトのステータスを更新
        this.siteStatuses.set(siteId, data);
        
        // 現在表示中のサイトのみUIを更新
        if (siteId === window.satelliteSystem.currentSiteId) {
            this.updateUIFromStatus(data);
        }
    }

    updateUIFromStatus(data) {
        this.startAutoGenerationBtn.disabled = data.isRunning;
        this.stopAutoGenerationBtn.disabled = !data.isRunning;
        
        if (data.isRunning) {
            this.autoGenerationStatus.style.display = 'block';
        }

        this.statusText.textContent = data.message;
        this.progressFill.style.width = `${data.progress}%`;
        this.progressDetails.textContent = `${data.generatedCount}/${data.totalArticles} 記事完了`;

        if (data.currentArticle) {
            this.progressDetails.textContent += ` (現在: ${data.currentArticle})`;
        }

        if (!data.isRunning) {
            setTimeout(() => {
                // 現在のサイトで処理が終了した場合のみ非表示
                if (window.satelliteSystem.currentSiteId) {
                    const currentStatus = this.siteStatuses.get(window.satelliteSystem.currentSiteId);
                    if (!currentStatus || !currentStatus.isRunning) {
                        this.autoGenerationStatus.style.display = 'none';
                        window.satelliteSystem.loadSiteData(window.satelliteSystem.currentSiteId);
                    }
                }
            }, 3000);
        }
    }

    updateUIForSite(siteId, isRunning) {
        if (siteId === window.satelliteSystem.currentSiteId) {
            this.startAutoGenerationBtn.disabled = isRunning;
            this.stopAutoGenerationBtn.disabled = !isRunning;
            if (isRunning) {
                this.autoGenerationStatus.style.display = 'block';
            }
        }
    }

    showError(siteId, message) {
        // 現在表示中のサイトのエラーのみ表示
        if (siteId === window.satelliteSystem.currentSiteId) {
            alert('エラー: ' + message);
        }
        
        // サイトのステータスを更新
        this.siteStatuses.set(siteId, { isRunning: false, progress: 0, generatedCount: 0, totalArticles: 0 });
        
        // 現在表示中のサイトのUIを更新
        if (siteId === window.satelliteSystem.currentSiteId) {
            this.startAutoGenerationBtn.disabled = false;
            this.stopAutoGenerationBtn.disabled = true;
        }
    }

    // 自動生成表示を更新する
    updateAutoGenerationDisplay() {
        const currentSiteId = window.satelliteSystem.currentSiteId;
        
        if (!currentSiteId) {
            this.autoGenerationStatus.style.display = 'none';
            this.startAutoGenerationBtn.disabled = false;
            this.stopAutoGenerationBtn.disabled = true;
            return;
        }
        
        const currentStatus = this.siteStatuses.get(currentSiteId);
        
        if (currentStatus && currentStatus.isRunning) {
            // 現在のサイトで処理中の場合は表示
            this.autoGenerationStatus.style.display = 'block';
            this.startAutoGenerationBtn.disabled = true;
            this.stopAutoGenerationBtn.disabled = false;
            this.updateUIFromStatus(currentStatus);
        } else {
            // 現在のサイトで処理中でない場合は非表示
            this.autoGenerationStatus.style.display = 'none';
            this.startAutoGenerationBtn.disabled = false;
            this.stopAutoGenerationBtn.disabled = true;
        }
    }

    // サイト切り替え時に呼び出される
    onSiteChanged(newSiteId) {
        this.updateAutoGenerationDisplay();
    }

    // 全てのサイトの処理状況を取得
    getAllSiteStatuses() {
        return Array.from(this.siteStatuses.entries()).filter(([siteId, status]) => status.isRunning);
    }

    // Workerをクリーンアップ
    terminateWorkerForSite(siteId) {
        const worker = this.siteWorkers.get(siteId);
        if (worker) {
            worker.terminate();
            this.siteWorkers.delete(siteId);
        }
        this.siteStatuses.delete(siteId);
    }
}

class SatelliteColumnSystem {
    constructor() {
        this.initializeElements();
        this.bindEvents();
        this.loadSites();
        this.autoGenerationManager = new AutoGenerationManager();
    }

    initializeElements() {
        this.siteSelect = document.getElementById('site-select');
        this.aiModelSelect = document.getElementById('ai-model');
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
        
        // 新しいURL解析関連の要素
        this.baseUrlInput = document.getElementById('base-url-input');
        this.crawlUrlsBtn = document.getElementById('crawl-urls');
        this.crawlProgress = document.getElementById('crawl-progress');
        this.crawlProgressFill = document.getElementById('crawl-progress-fill');
        this.crawlProgressText = document.getElementById('crawl-progress-text');
        this.urlListSection = document.getElementById('url-list-section');
        this.urlList = document.getElementById('url-list');
        this.addManualUrlBtn = document.getElementById('add-manual-url');
        this.selectAllUrlsBtn = document.getElementById('select-all-urls');
        this.deselectAllUrlsBtn = document.getElementById('deselect-all-urls');
        
        // AI使用ログ関連の要素
        this.showAiLogsBtn = document.getElementById('show-ai-logs');
        this.hideAiLogsBtn = document.getElementById('hide-ai-logs');
        this.aiLogsSection = document.getElementById('ai-logs-section');
        this.aiLogsContent = document.getElementById('ai-logs-content');
        
        this.currentSiteId = null;
        this.articles = [];
        this.discoveredUrls = [];
        this.savedUrls = [];
    }

    bindEvents() {
        // 基本的なイベント
        if (this.analyzeSitesBtn) this.analyzeSitesBtn.addEventListener('click', () => this.analyzeSites());
        if (this.createArticleOutlineBtn) this.createArticleOutlineBtn.addEventListener('click', () => this.createArticleOutline());
        if (this.generateAllArticlesBtn) this.generateAllArticlesBtn.addEventListener('click', () => this.generateAllArticles());
        if (this.exportCsvBtn) this.exportCsvBtn.addEventListener('click', () => this.exportCsv());
        if (this.siteSelect) this.siteSelect.addEventListener('change', (e) => this.loadSiteData(e.target.value));
        
        // 新しいURL解析関連のイベント
        if (this.crawlUrlsBtn) this.crawlUrlsBtn.addEventListener('click', () => this.crawlSiteUrls());
        if (this.addManualUrlBtn) this.addManualUrlBtn.addEventListener('click', () => this.addManualUrl());
        if (this.selectAllUrlsBtn) this.selectAllUrlsBtn.addEventListener('click', () => this.selectAllUrls());
        if (this.deselectAllUrlsBtn) this.deselectAllUrlsBtn.addEventListener('click', () => this.deselectAllUrls());
        
        // AI使用ログ関連のイベント
        if (this.showAiLogsBtn) this.showAiLogsBtn.addEventListener('click', () => this.showAiLogs());
        if (this.hideAiLogsBtn) this.hideAiLogsBtn.addEventListener('click', () => this.hideAiLogs());
        
        // モーダル関連
        const closeBtn = document.querySelector('.close');
        if (closeBtn) closeBtn.addEventListener('click', () => this.closeModal());
        
        window.addEventListener('click', (e) => {
            if (e.target === this.articleDetailModal) {
                this.closeModal();
            }
        });
    }


    showLoading() {
        this.loadingOverlay.style.display = 'flex';
        // 初期状態のローディングテキストを設定
        const loadingText = this.loadingOverlay.querySelector('.loading-text');
        if (loadingText) {
            loadingText.textContent = '処理中...';
        }
    }

    hideLoading() {
        this.loadingOverlay.style.display = 'none';
    }
    
    updateLoadingProgress(message) {
        const loadingText = this.loadingOverlay.querySelector('.loading-text');
        if (loadingText) {
            loadingText.textContent = message;
        }
        // console.log('Progress: ' + message);
    }

    async loadSites() {
        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_sites' })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.updateSiteSelect(result.sites);
            }
        } catch (error) {
            console.error('サイト読み込みエラー:', error);
        }
    }
    
    async handleApiResponse(response) {
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
            console.error('Invalid JSON response:', text.substring(0, 200));
            throw new Error('サーバーから無効なJSONが返されました: ' + error.message);
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
            this.articles = [];
            this.analysisSection.style.display = 'none';
            this.articleOutlineSection.style.display = 'none';
            // サイト切り替え時にステータス表示を更新
            this.autoGenerationManager.onSiteChanged(null);
            return;
        }

        this.showLoading();
        try {
            // 古い記事データをクリア
            this.articles = [];
            this.articleOutlineSection.style.display = 'none';
            
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'get_site_data', 
                    site_id: siteId 
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.currentSiteId = siteId;
                this.displaySiteData(result.data);
                // サイト切り替え時にステータス表示を更新
                this.autoGenerationManager.onSiteChanged(siteId);
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
        } else {
            // サイトID 2の場合、記事が存在しない場合の処理
            this.articles = [];
            this.articleOutlineSection.style.display = 'none';
        }
        
        // 保存されている参照URLを取得して表示
        this.loadReferenceUrls(this.currentSiteId);
    }

    async crawlSiteUrls() {
        const baseUrl = this.baseUrlInput.value.trim();
        if (!baseUrl) {
            alert('ベースURLを入力してください。');
            return;
        }

        this.crawlUrlsBtn.disabled = true;
        this.crawlProgress.style.display = 'block';
        this.crawlProgressText.textContent = 'URLを取得中...';
        this.crawlProgressFill.style.width = '0%';

        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'crawl_site_urls',
                    base_url: baseUrl
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.discoveredUrls = result.urls;
                this.displayUrlList();
                this.crawlProgressText.textContent = `${result.total_found}件のURLを発見しました`;
                this.crawlProgressFill.style.width = '100%';
                this.urlListSection.style.display = 'block';
                this.analyzeSitesBtn.style.display = 'block';
            } else {
                alert('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('URL取得エラー:', error);
            alert('URL取得中にエラーが発生しました。');
        } finally {
            this.crawlUrlsBtn.disabled = false;
            setTimeout(() => {
                this.crawlProgress.style.display = 'none';
            }, 2000);
        }
    }

    displayUrlList() {
        const urlListHtml = this.discoveredUrls.map((url, index) => `
            <div class="url-item" data-index="${index}">
                <input type="checkbox" class="url-checkbox" data-url="${url}" checked>
                <span class="url-text">${url}</span>
                <button class="remove-url-btn" data-index="${index}">×</button>
            </div>
        `).join('');
        
        this.urlList.innerHTML = urlListHtml;
        
        // 削除ボタンのイベント
        this.urlList.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-url-btn')) {
                const index = parseInt(e.target.dataset.index);
                this.removeUrlFromList(index);
            }
        });
    }

    removeUrlFromList(index) {
        this.discoveredUrls.splice(index, 1);
        this.displayUrlList();
    }

    addManualUrl() {
        const url = prompt('追加するURLを入力してください:');
        if (url && url.trim()) {
            this.discoveredUrls.push(url.trim());
            this.displayUrlList();
        }
    }

    selectAllUrls() {
        const checkboxes = this.urlList.querySelectorAll('.url-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
    }

    deselectAllUrls() {
        const checkboxes = this.urlList.querySelectorAll('.url-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
    }

    getSelectedUrls() {
        const checkboxes = this.urlList.querySelectorAll('.url-checkbox:checked');
        return Array.from(checkboxes).map(checkbox => checkbox.dataset.url);
    }

    // テスト用関数
    async testGetRequest() {
        try {
            const response = await fetch('test_get.php?action=test');
            const result = await response.json();
            console.log('GET Test Result:', result);
            alert('GET Test: ' + JSON.stringify(result));
        } catch (error) {
            console.error('GET Test Error:', error);
            alert('GET Test Error: ' + error.message);
        }
    }
    
    async testPostRequest() {
        try {
            const response = await fetch('test_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'test', message: 'hello' })
            });
            const result = await response.json();
            console.log('POST Test Result:', result);
            alert('POST Test: ' + JSON.stringify(result));
        } catch (error) {
            console.error('POST Test Error:', error);
            alert('POST Test Error: ' + error.message);
        }
    }
    
    async testAnalyzeSitesAction() {
        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'analyze_sites' })
            });
            const result = await response.json();
            console.log('Analyze Sites Action Test Result:', result);
            alert('Analyze Sites Action Test: ' + JSON.stringify(result));
        } catch (error) {
            console.error('Analyze Sites Action Test Error:', error);
            alert('Analyze Sites Action Test Error: ' + error.message);
        }
    }
    
    async testWithUrls() {
        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'analyze_sites',
                    urls: ['https://example.com'],
                    ai_model: 'gemini-2.0-flash'
                })
            });
            const result = await response.json();
            console.log('With URLs Test Result:', result);
            alert('With URLs Test: ' + JSON.stringify(result));
        } catch (error) {
            console.error('With URLs Test Error:', error);
            alert('With URLs Test Error: ' + error.message);
        }
    }
    
    async testWithRealUrl() {
        try {
            let testUrls = this.getSelectedUrls();
            
            // 選択されたURLがない場合は、プロンプトで入力
            if (testUrls.length === 0) {
                const inputUrl = prompt('テスト用のURLを入力してください:');
                if (!inputUrl || !inputUrl.trim()) {
                    alert('URLが入力されませんでした');
                    return;
                }
                testUrls = [inputUrl.trim()];
            }
            
            console.log('Testing with URLs:', testUrls);
            console.log('Data size:', JSON.stringify({ 
                action: 'analyze_sites',
                urls: testUrls,
                ai_model: this.aiModelSelect.value
            }).length);
            
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'analyze_sites',
                    urls: testUrls,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await response.json();
            console.log('Real URL Test Result:', result);
            alert('Real URL Test: ' + JSON.stringify(result));
        } catch (error) {
            console.error('Real URL Test Error:', error);
            alert('Real URL Test Error: ' + error.message);
        }
    }
    
    async testWithCommonUrl() {
        try {
            const testUrls = ['https://yahoo.co.jp'];
            
            console.log('Testing with common URL:', testUrls);
            console.log('Data size:', JSON.stringify({ 
                action: 'analyze_sites',
                urls: testUrls,
                ai_model: this.aiModelSelect.value
            }).length);
            
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'analyze_sites',
                    urls: testUrls,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await response.json();
            console.log('Common URL Test Result:', result);
            alert('Common URL Test: ' + JSON.stringify(result));
        } catch (error) {
            console.error('Common URL Test Error:', error);
            alert('Common URL Test Error: ' + error.message);
        }
    }

    async analyzeSites() {
        const urls = this.getSelectedUrls();
        if (urls.length === 0) {
            alert('少なくとも1つのURLを選択してください。');
            return;
        }

        this.showLoading();
        
        try {
            // URL数が多い場合は分割して処理
            const groupSize = 10; // 10個ずつのグループに分割
            const urlGroups = [];
            for (let i = 0; i < urls.length; i += groupSize) {
                urlGroups.push(urls.slice(i, i + groupSize));
            }
            
            console.log(`総URL数: ${urls.length}, グループ数: ${urlGroups.length}`);
            
            // 各グループを順次分析
            const groupAnalyses = [];
            for (let i = 0; i < urlGroups.length; i++) {
                const group = urlGroups[i];
                
                // 進捗を更新
                const processedUrls = i * groupSize;
                const currentProgress = `(${processedUrls}/${urls.length}) を分析中... グループ ${i + 1}/${urlGroups.length}`;
                this.updateLoadingProgress(currentProgress);
                
                console.log(`グループ ${i + 1}/${urlGroups.length} (${group.length}個のURL) を分析中...`);
                
                const requestData = {
                    action: 'analyze_sites_group',
                    urls: group,
                    ai_model: this.aiModelSelect.value,
                    group_index: i + 1,
                    total_groups: urlGroups.length
                };
                
                const response = await fetch('handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(requestData)
                });
                
                const result = await this.handleApiResponse(response);
                if (result.success) {
                    groupAnalyses.push(result.analysis);
                    
                    // グループ完了後の進捗を更新
                    const completedUrls = (i + 1) * groupSize;
                    const actualCompleted = Math.min(completedUrls, urls.length);
                    const completedProgress = `(${actualCompleted}/${urls.length}) 分析完了 - グループ ${i + 1}/${urlGroups.length}`;
                    this.updateLoadingProgress(completedProgress);
                } else {
                    throw new Error(`グループ ${i + 1} の分析に失敗: ${result.error}`);
                }
                
                // APIレート制限対策で少し待機
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
            
            // 複数の分析結果を統合
            console.log('分析結果を統合中...');
            this.updateLoadingProgress(`(${urls.length}/${urls.length}) 分析完了 - 結果を統合中...`);
            
            const finalRequestData = {
                action: 'integrate_analyses',
                analyses: groupAnalyses,
                ai_model: this.aiModelSelect.value,
                total_urls: urls.length,
                base_url: urls[0] // 最初のURLをベースURLとして渡す
            };
            
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(finalRequestData)
            });
            
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.currentSiteId = result.site_id;
                this.savedUrls = urls;
                
                // 参照URLをデータベースに保存
                await this.saveReferenceUrls(result.site_id, urls);
                
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
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_article_outline',
                    site_id: this.currentSiteId,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await this.handleApiResponse(response);
            
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
                        <th>投稿日時</th>
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
                            <td>
                                <input type="datetime-local" 
                                       class="publish-date-input" 
                                       data-article-id="${article.id}"
                                       value="${this.formatDateTimeLocal(article.publish_date)}"
                                       onchange="window.satelliteSystem.updatePublishDate(${article.id}, this.value)">
                            </td>
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

    formatDateTimeLocal(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    async updatePublishDate(articleId, publishDate) {
        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_publish_date',
                    article_id: articleId,
                    publish_date: publishDate
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                // 記事データを更新
                const articleIndex = this.articles.findIndex(a => a.id == articleId);
                if (articleIndex !== -1) {
                    this.articles[articleIndex].publish_date = publishDate;
                }
            } else {
                alert('投稿日時の更新に失敗しました: ' + result.error);
            }
        } catch (error) {
            console.error('投稿日時更新エラー:', error);
            alert('投稿日時の更新中にエラーが発生しました。');
        }
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
        // 既に実行中の記事IDを管理
        if (this.generatingArticles && this.generatingArticles.has(articleId)) {
            return;
        }
        
        if (!this.generatingArticles) {
            this.generatingArticles = new Set();
        }
        
        this.generatingArticles.add(articleId);
        this.showLoading();
        
        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_article',
                    article_id: articleId,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await this.handleApiResponse(response);
            
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
            this.generatingArticles.delete(articleId);
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

        // 一括生成実行中フラグを設定
        if (this.isGeneratingAll) {
            alert('既に一括生成中です。しばらくお待ちください。');
            return;
        }
        
        this.isGeneratingAll = true;
        this.bulkGenerationCancelled = false;
        this.showBulkGenerationProgress(draftArticles.length);
        
        try {
            let successCount = 0;
            let errorCount = 0;
            
            // 各記事を順番に生成（バックグラウンド処理）
            for (let i = 0; i < draftArticles.length; i++) {
                if (this.bulkGenerationCancelled) {
                    break;
                }
                
                const article = draftArticles[i];
                this.updateBulkGenerationProgress(i + 1, draftArticles.length, article.title);
                
                try {
                    const response = await fetch('handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'generate_article',
                            article_id: article.id,
                            ai_model: this.aiModelSelect.value
                        })
                    });
                    const result = await this.handleApiResponse(response);
                    
                    if (result.success) {
                        successCount++;
                        // 記事一覧を更新
                        const articleIndex = this.articles.findIndex(a => a.id == article.id);
                        if (articleIndex !== -1) {
                            this.articles[articleIndex] = result.article;
                        }
                        // 完了した記事を即座に保存・表示
                        this.displayArticleOutline();
                    } else {
                        errorCount++;
                        console.error(`記事ID ${article.id} 生成エラー:`, result.error);
                    }
                } catch (error) {
                    errorCount++;
                    console.error(`記事ID ${article.id} 生成エラー:`, error);
                }
                
                // 短時間待機してUIの応答性を保つ
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            
            const message = this.bulkGenerationCancelled ? 
                `一括生成が中断されました。\n成功: ${successCount}記事\nエラー: ${errorCount}記事` :
                `一括生成が完了しました。\n成功: ${successCount}記事\nエラー: ${errorCount}記事`;
            
            alert(message);
            
        } catch (error) {
            console.error('一括生成エラー:', error);
            alert('一括生成中にエラーが発生しました。');
        } finally {
            this.isGeneratingAll = false;
            this.bulkGenerationCancelled = false;
            this.hideBulkGenerationProgress();
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

    showBulkGenerationProgress(totalCount) {
        const progressHtml = `
            <div id="bulk-generation-progress" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                 background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 1000; min-width: 400px;">
                <h3>記事一括生成中...</h3>
                <div style="margin: 15px 0;">
                    <div style="background: #f0f0f0; height: 20px; border-radius: 10px; overflow: hidden;">
                        <div id="bulk-progress-bar" style="background: #4CAF50; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div id="bulk-progress-text" style="margin-top: 10px; text-align: center;">0 / ${totalCount} 記事完了</div>
                </div>
                <div id="bulk-current-article" style="margin: 10px 0; font-size: 14px; color: #666;">準備中...</div>
                <div style="text-align: center; margin-top: 15px;">
                    <button id="cancel-bulk-generation" style="padding: 8px 16px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        中断する
                    </button>
                </div>
            </div>
            <div id="bulk-generation-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                 background: rgba(0,0,0,0.5); z-index: 999;"></div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', progressHtml);
        
        document.getElementById('cancel-bulk-generation').addEventListener('click', () => {
            if (confirm('一括生成を中断しますか？\n（現在生成中の記事は保存されません）')) {
                this.bulkGenerationCancelled = true;
            }
        });
    }

    updateBulkGenerationProgress(currentCount, totalCount, currentTitle) {
        const progressBar = document.getElementById('bulk-progress-bar');
        const progressText = document.getElementById('bulk-progress-text');
        const currentArticle = document.getElementById('bulk-current-article');
        
        if (progressBar && progressText && currentArticle) {
            const percentage = (currentCount / totalCount) * 100;
            progressBar.style.width = percentage + '%';
            progressText.textContent = `${currentCount} / ${totalCount} 記事完了`;
            currentArticle.textContent = `現在生成中: ${currentTitle}`;
        }
    }

    hideBulkGenerationProgress() {
        const progressElement = document.getElementById('bulk-generation-progress');
        const overlayElement = document.getElementById('bulk-generation-overlay');
        
        if (progressElement) {
            progressElement.remove();
        }
        if (overlayElement) {
            overlayElement.remove();
        }
    }

    cancelBulkGeneration() {
        this.bulkGenerationCancelled = true;
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
            const response = await fetch('handler.php', {
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

    async saveReferenceUrls(siteId, urls) {
        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_reference_urls',
                    site_id: siteId,
                    urls: urls
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (!result.success) {
                console.error('参照URL保存エラー:', result.error);
            }
        } catch (error) {
            console.error('参照URL保存エラー:', error);
        }
    }

    async loadReferenceUrls(siteId) {
        if (!siteId) return;
        
        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_reference_urls',
                    site_id: siteId
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success && result.urls.length > 0) {
                this.discoveredUrls = result.urls;
                this.displayUrlList();
                this.urlListSection.style.display = 'block';
                this.analyzeSitesBtn.style.display = 'block';
            }
        } catch (error) {
            console.error('参照URL読み込みエラー:', error);
        }
    }

    async showAiLogs() {
        if (!this.currentSiteId) {
            alert('まずサイトを選択してください。');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_ai_usage_logs',
                    site_id: this.currentSiteId,
                    limit: 50,
                    offset: 0
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.displayAiLogs(result);
                this.aiLogsSection.style.display = 'block';
            } else {
                alert('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('AIログ取得エラー:', error);
            alert('AIログ取得中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    hideAiLogs() {
        this.aiLogsSection.style.display = 'none';
    }

    displayAiLogs(data) {
        const { logs, stats } = data;
        
        // 統計情報を表示
        const statsHtml = `
            <div class="ai-stats">
                <h3>AI使用統計</h3>
                <div class="stats-grid">
                    ${stats.map(stat => `
                        <div class="stat-item">
                            <div class="stat-label">${stat.ai_model} - ${this.getUsageTypeLabel(stat.usage_type)}</div>
                            <div class="stat-value">
                                <span class="usage-count">${stat.usage_count}回</span>
                                <span class="token-count">${stat.total_tokens}トークン</span>
                                <span class="avg-time">${parseFloat(stat.avg_processing_time).toFixed(2)}秒</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        // ログリストを表示
        const logsHtml = `
            <div class="ai-logs">
                <h3>AI使用ログ</h3>
                <div class="logs-table">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>日時</th>
                                <th>AIモデル</th>
                                <th>使用タイプ</th>
                                <th>記事</th>
                                <th>トークン数</th>
                                <th>処理時間</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${logs.map(log => `
                                <tr>
                                    <td>${new Date(log.created_at).toLocaleString()}</td>
                                    <td>${log.ai_model}</td>
                                    <td>${this.getUsageTypeLabel(log.usage_type)}</td>
                                    <td>${log.article_title || '-'}</td>
                                    <td>${log.tokens_used}トークン</td>
                                    <td>${parseFloat(log.processing_time).toFixed(2)}秒</td>
                                    <td>
                                        <button class="view-log-detail" data-log-id="${log.id}">詳細</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        this.aiLogsContent.innerHTML = statsHtml + logsHtml;
        
        // 詳細ボタンのイベント
        this.aiLogsContent.addEventListener('click', (e) => {
            if (e.target.classList.contains('view-log-detail')) {
                const logId = e.target.dataset.logId;
                const log = logs.find(l => l.id == logId);
                if (log) {
                    this.showLogDetail(log);
                }
            }
        });
    }

    getUsageTypeLabel(type) {
        const labels = {
            'site_analysis': 'サイト分析',
            'article_outline': '記事概要生成',
            'article_generation': '記事生成',
            'additional_outline': '追加概要生成'
        };
        return labels[type] || type;
    }

    showLogDetail(log) {
        const modalContent = `
            <div class="log-detail-modal">
                <h3>AI使用ログ詳細</h3>
                <div class="log-detail-info">
                    <p><strong>日時:</strong> ${new Date(log.created_at).toLocaleString()}</p>
                    <p><strong>AIモデル:</strong> ${log.ai_model}</p>
                    <p><strong>使用タイプ:</strong> ${this.getUsageTypeLabel(log.usage_type)}</p>
                    <p><strong>サイト:</strong> ${log.site_name}</p>
                    <p><strong>記事:</strong> ${log.article_title || '-'}</p>
                    <p><strong>トークン数:</strong> ${log.tokens_used}</p>
                    <p><strong>処理時間:</strong> ${parseFloat(log.processing_time).toFixed(2)}秒</p>
                </div>
                <div class="log-detail-content">
                    <h4>プロンプト</h4>
                    <pre class="log-text">${log.prompt_text}</pre>
                    <h4>レスポンス</h4>
                    <pre class="log-text">${log.response_text}</pre>
                </div>
            </div>
        `;
        
        document.getElementById('article-detail-content').innerHTML = modalContent;
        this.articleDetailModal.style.display = 'block';
    }
}

// アプリケーション初期化
document.addEventListener('DOMContentLoaded', () => {
    window.satelliteSystem = new SatelliteColumnSystem();
    
    // デバッグ用テストボタンのイベントリスナー
    document.getElementById('test-get-btn').addEventListener('click', () => {
        window.satelliteSystem.testGetRequest();
    });
    
    document.getElementById('test-post-btn').addEventListener('click', () => {
        window.satelliteSystem.testPostRequest();
    });
    
    document.getElementById('test-analyze-action-btn').addEventListener('click', () => {
        window.satelliteSystem.testAnalyzeSitesAction();
    });
    
    document.getElementById('test-with-urls-btn').addEventListener('click', () => {
        window.satelliteSystem.testWithUrls();
    });
    
    document.getElementById('test-with-real-url-btn').addEventListener('click', () => {
        window.satelliteSystem.testWithRealUrl();
    });
    
    document.getElementById('test-with-common-url-btn').addEventListener('click', () => {
        window.satelliteSystem.testWithCommonUrl();
    });
});