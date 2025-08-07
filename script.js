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
            this.showErrorMessage('まずサイト分析を実行してください。');
            return;
        }

        const siteId = window.satelliteSystem.currentSiteId;
        const currentStatus = this.siteStatuses.get(siteId);
        
        if (currentStatus && currentStatus.isRunning) {
            this.showErrorMessage('このサイトは既に自動生成中です。');
            return;
        }

        const articleCount = parseInt(this.autoArticleCountInput.value);
        if (isNaN(articleCount) || articleCount < 1) {
            this.showErrorMessage('作成件数を正しく入力してください。');
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
            this.showErrorMessage('エラー: ' + message);
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

    // エラーメッセージを表示
    showErrorMessage(message) {
        console.error(message);
        if (window.satelliteSystem && window.satelliteSystem.showErrorMessage) {
            window.satelliteSystem.showErrorMessage(message);
        }
    }
}

class SatelliteColumnSystem {
    constructor() {
        this.initializeElements();
        this.bindEvents();
        this.loadSites();
        this.autoGenerationManager = new AutoGenerationManager();
        this.updatingMultilingualSetting = false;
        // 初期状態で多言語設定の基本表示を設定
        this.initializeMultilingualSettings();
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
        
        // サイト分析関連の要素
        this.siteDescriptionInput = document.getElementById('site-description-input');
        
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
        
        // 記事選択関連の要素
        this.selectAllArticlesBtn = document.getElementById('select-all-articles');
        this.deselectAllArticlesBtn = document.getElementById('deselect-all-articles');
        this.generateSelectedArticlesBtn = document.getElementById('generate-selected-articles');
        this.generateAllUncreatedArticlesBtn = document.getElementById('generate-all-uncreated-articles');
        this.deleteSelectedArticlesBtn = document.getElementById('delete-selected-articles');
        
        // AI使用ログ関連の要素
        this.showAiLogsBtn = document.getElementById('show-ai-logs');
        this.hideAiLogsBtn = document.getElementById('hide-ai-logs');
        this.aiLogsSection = document.getElementById('ai-logs-section');
        this.aiLogsContent = document.getElementById('ai-logs-content');
        
        // 多言語関連の要素
        this.multilingualSettings = document.getElementById('multilingual-settings');
        this.articleLanguageSelect = document.getElementById('article-language-select');
        this.articleDetailLanguageSelect = document.getElementById('article-detail-language-select');
        
        this.currentSiteId = null;
        this.articles = [];
        this.discoveredUrls = [];
        this.savedUrls = [];
        this.currentLanguage = 'ja'; // デフォルトは日本語
        this.multilingualConfig = {};
    }

    bindEvents() {
        // 基本的なイベント
        if (this.analyzeSitesBtn) this.analyzeSitesBtn.addEventListener('click', () => this.analyzeSites());
        if (this.createArticleOutlineBtn) this.createArticleOutlineBtn.addEventListener('click', () => this.createArticleOutline());
        if (this.exportCsvBtn) this.exportCsvBtn.addEventListener('click', () => this.exportCsv());
        if (this.siteSelect) this.siteSelect.addEventListener('change', (e) => this.loadSiteData(e.target.value));
        
        // 記事選択関連のイベント
        if (this.selectAllArticlesBtn) this.selectAllArticlesBtn.addEventListener('click', () => this.selectAllArticles());
        if (this.deselectAllArticlesBtn) this.deselectAllArticlesBtn.addEventListener('click', () => this.deselectAllArticles());
        if (this.generateSelectedArticlesBtn) this.generateSelectedArticlesBtn.addEventListener('click', () => this.generateSelectedArticles());
        if (this.generateAllUncreatedArticlesBtn) this.generateAllUncreatedArticlesBtn.addEventListener('click', () => this.generateAllUncreatedArticles());
        if (this.deleteSelectedArticlesBtn) this.deleteSelectedArticlesBtn.addEventListener('click', () => this.deleteSelectedArticles());
        
        // 新しいURL解析関連のイベント
        if (this.crawlUrlsBtn) this.crawlUrlsBtn.addEventListener('click', () => this.crawlSiteUrls());
        if (this.addManualUrlBtn) this.addManualUrlBtn.addEventListener('click', () => this.addManualUrl());
        if (this.selectAllUrlsBtn) this.selectAllUrlsBtn.addEventListener('click', () => this.selectAllUrls());
        if (this.deselectAllUrlsBtn) this.deselectAllUrlsBtn.addEventListener('click', () => this.deselectAllUrls());
        
        // AI使用ログ関連のイベント
        if (this.showAiLogsBtn) this.showAiLogsBtn.addEventListener('click', () => this.showAiLogs());
        if (this.hideAiLogsBtn) this.hideAiLogsBtn.addEventListener('click', () => this.hideAiLogs());
        
        // 多言語関連のイベント
        if (this.articleLanguageSelect) this.articleLanguageSelect.addEventListener('change', (e) => this.changeArticleLanguage(e.target.value));
        if (this.articleDetailLanguageSelect) this.articleDetailLanguageSelect.addEventListener('change', (e) => this.changeArticleDetailLanguage(e.target.value));
        
        // モーダル関連
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('close')) {
                this.closeModal();
            }
        });
        
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
            const response = await fetch('api.php', {
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
            
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'get_site_data_with_translations', 
                    site_id: siteId 
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.currentSiteId = siteId;
                this.displaySiteData(result.data);
                // サイト切り替え時にステータス表示を更新
                this.autoGenerationManager.onSiteChanged(siteId);
                // 多言語設定を読み込み
                this.loadMultilingualSettings(siteId);
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
            this.bindPolicyEditorEvents();
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

    bindPolicyEditorEvents() {
        const savePolicyBtn = document.getElementById('save-policy-btn');
        const resetPolicyBtn = document.getElementById('reset-policy-btn');
        
        if (savePolicyBtn) {
            savePolicyBtn.addEventListener('click', () => this.saveSitePolicy());
        }
        
        if (resetPolicyBtn) {
            resetPolicyBtn.addEventListener('click', () => this.resetSitePolicy());
        }
    }

    async saveSitePolicy() {
        const policyEditor = document.getElementById('site-policy-editor');
        if (!policyEditor || !this.currentSiteId) {
            this.showErrorMessage('記事作成方針を保存できませんでした。');
            return;
        }

        const newPolicy = policyEditor.value.trim();
        if (!newPolicy) {
            this.showErrorMessage('記事作成方針を入力してください。');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_site_policy',
                    site_id: this.currentSiteId,
                    policy: newPolicy
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.showSuccessMessage('記事作成方針を保存しました。');
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('記事作成方針保存エラー:', error);
            this.showErrorMessage('記事作成方針の保存中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    resetSitePolicy() {
        const policyEditor = document.getElementById('site-policy-editor');
        if (!policyEditor) {
            return;
        }

        if (confirm('記事作成方針をリセットしますか？変更内容は失われます。')) {
            this.loadSiteData(this.currentSiteId);
        }
    }

    async crawlSiteUrls() {
        const baseUrl = this.baseUrlInput.value.trim();
        if (!baseUrl) {
            this.showErrorMessage('ベースURLを入力してください。');
            return;
        }

        this.crawlUrlsBtn.disabled = true;
        this.crawlProgress.style.display = 'block';
        this.crawlProgressText.textContent = 'URLを取得中...';
        this.crawlProgressFill.style.width = '0%';

        try {
            const response = await fetch('api.php', {
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
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('URL取得エラー:', error);
            this.showErrorMessage('URL取得中にエラーが発生しました。');
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


    async analyzeSites() {
        const urls = this.getSelectedUrls();
        const siteDescription = this.siteDescriptionInput.value.trim();
        
        // URLかサイト説明のどちらかが入力されていることを確認
        if (urls.length === 0 && !siteDescription) {
            this.showErrorMessage('参考URLを選択するか、記事作成方針を入力してください。');
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
                    site_description: siteDescription,
                    ai_model: this.aiModelSelect.value,
                    group_index: i + 1,
                    total_groups: urlGroups.length
                };
                
                const response = await fetch('api.php', {
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
                    if (result.processed_urls !== undefined) {
                        this.updateLoadingProgress(completedProgress + ` (処理成功: ${result.processed_urls}, 失敗: ${result.failed_urls || 0})`);
                    } else {
                        this.updateLoadingProgress(completedProgress);
                    }
                } else {
                    console.warn(`グループ ${i + 1} の分析に問題がありました: ${result.error}`);
                    // エラーでも空の分析結果を追加して処理を続行
                    groupAnalyses.push(`グループ ${i + 1} の分析でエラーが発生しました: ${result.error}`);
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
                site_description: siteDescription,
                ai_model: this.aiModelSelect.value,
                total_urls: urls.length,
                base_url: urls.length > 0 ? urls[0] : null // URLがある場合のみベースURLを設定
            };
            
            const response = await fetch('api.php', {
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
                this.bindPolicyEditorEvents();
                this.loadSites(); // サイト選択を更新
                
                // 新規サイト作成時に多言語設定を読み込み
                this.loadMultilingualSettings(result.site_id);
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('サイト分析エラー:', error);
            this.showErrorMessage('サイト分析中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    formatAnalysisResult(analysis) {
        return `
            <div class="analysis-result-container">
                <div class="analysis-policy-section">
                    <h3>記事作成方針</h3>
                    <div class="policy-edit-container">
                        <textarea id="site-policy-editor" class="policy-editor" placeholder="記事作成方針を入力してください...">${analysis}</textarea>
                        <div class="policy-actions">
                            <button id="save-policy-btn" class="btn btn-primary">保存</button>
                            <button id="reset-policy-btn" class="btn btn-secondary">リセット</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async createArticleOutline() {
        if (!this.currentSiteId) {
            this.showErrorMessage('まずサイト分析を実行してください。');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_article_outline',
                    site_id: this.currentSiteId,
                    ai_model: this.aiModelSelect.value,
                    check_duplicates: true
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.articles = result.articles;
                this.displayArticleOutline();
                this.articleOutlineSection.style.display = 'block';
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('記事概要作成エラー:', error);
            this.showErrorMessage('記事概要作成中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }


    displayArticleOutline() {
        const tableHtml = `
            <table class="article-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-header" onchange="window.satelliteSystem.toggleAllArticles(this.checked)"></th>
                        <th>記事タイトル</th>
                        <th>SEOキーワード</th>
                        <th>概要</th>
                        <th>投稿日時</th>
                        <th>多言語記事</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.articles.map(article => `
                        <tr>
                            <td>
                                <input type="checkbox" class="article-checkbox" data-article-id="${article.id}">
                            </td>
                            <td>
                                ${article.status === 'generated' ? 
                                    `<a href="#" class="article-link" data-article-id="${article.id}">${article.title}</a>` : 
                                    `<input type="text" class="editable-title" data-article-id="${article.id}" value="${article.title}" onblur="window.satelliteSystem.updateArticleField(${article.id}, 'title', this.value)">`}
                            </td>
                            <td>
                                <input type="text" class="editable-keywords" data-article-id="${article.id}" value="${article.seo_keywords}" onblur="window.satelliteSystem.updateArticleField(${article.id}, 'seo_keywords', this.value)">
                            </td>
                            <td>
                                <textarea class="editable-summary" data-article-id="${article.id}" onblur="window.satelliteSystem.updateArticleField(${article.id}, 'summary', this.value)">${article.summary}</textarea>
                            </td>
                            <td>
                                <input type="datetime-local" 
                                       class="publish-date-input" 
                                       data-article-id="${article.id}"
                                       value="${this.formatDateTimeLocal(article.publish_date)}"
                                       onchange="window.satelliteSystem.updatePublishDate(${article.id}, this.value)"
                                       onfocus="window.satelliteSystem.autoFillPublishDate(this)">
                            </td>
                            <td class="language-icons">
                                ${this.generateLanguageIcons(article)}
                            </td>
                            <td class="article-actions">
                                ${article.status === 'generated' ? 
                                    `<button class="btn-edit-content" data-article-id="${article.id}">編集</button>
                                     <button class="btn-regenerate" data-article-id="${article.id}">作り直し</button>
                                     <button class="btn-delete-single" data-article-id="${article.id}">削除</button>` : 
                                    `<button class="btn-delete-single" data-article-id="${article.id}">削除</button>`}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        
        this.articleOutlineTable.innerHTML = tableHtml;
        
        // 記事作成ボタンと言語アイコンのイベント
        this.articleOutlineTable.addEventListener('click', (e) => {
            if (e.target.classList.contains('generate-article-btn')) {
                const articleId = e.target.dataset.articleId;
                this.generateArticle(articleId);
            } else if (e.target.classList.contains('article-link')) {
                e.preventDefault();
                const articleId = e.target.dataset.articleId;
                this.showArticleDetail(articleId);
            } else if (e.target.classList.contains('language-icon')) {
                const languageCode = e.target.dataset.language;
                const articleId = e.target.dataset.articleId;
                this.handleLanguageIconClick(articleId, languageCode);
            } else if (e.target.classList.contains('btn-edit-content')) {
                const articleId = e.target.dataset.articleId;
                this.editArticleContent(articleId);
            } else if (e.target.classList.contains('btn-regenerate')) {
                const articleId = e.target.dataset.articleId;
                this.regenerateArticle(articleId);
            } else if (e.target.classList.contains('btn-delete-single')) {
                const articleId = e.target.dataset.articleId;
                this.deleteArticle(articleId);
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

    async handleLanguageIconClick(articleId, languageCode) {
        const article = this.articles.find(a => a.id == articleId);
        if (!article) {
            this.showErrorMessage('記事が見つかりません。');
            return;
        }

        const hasContent = this.checkArticleLanguageContent(article, languageCode);
        
        if (hasContent) {
            // 作成済みの記事を表示
            if (languageCode === 'ja') {
                this.showArticleDetail(articleId);
            } else {
                this.showMultilingualArticleDetail(articleId, languageCode);
            }
        } else {
            // 未作成の記事を生成
            if (languageCode === 'ja') {
                // 日本語記事の生成
                await this.generateArticle(articleId);
            } else {
                // 多言語記事の生成（単体）
                await this.generateSingleLanguageArticle(articleId, languageCode);
            }
        }
    }

    async generateSingleLanguageArticle(articleId, languageCode) {
        const article = this.articles.find(a => a.id == articleId);
        if (!article) {
            this.showErrorMessage('記事が見つかりません。');
            return;
        }

        // 日本語記事がない場合は先に作成が必要
        if (!this.checkArticleLanguageContent(article, 'ja')) {
            this.showErrorMessage('多言語記事を生成するには、まず日本語記事を作成してください。');
            return;
        }

        this.showLoading();
        
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_single_language_article',
                    article_id: articleId,
                    language_code: languageCode,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.showSuccessMessage(`${languageCode}記事を生成しました。`);
                // 記事データを更新
                await this.updateArticleWithTranslations(articleId);
                // 念のため記事一覧を再読み込み
                setTimeout(() => {
                    this.loadSiteData(this.currentSiteId);
                }, 1000);
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('単体言語記事生成エラー:', error);
            this.showErrorMessage('記事生成中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    async updateArticleWithTranslations(articleId) {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_article_with_translations',
                    article_id: articleId
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                const articleIndex = this.articles.findIndex(a => a.id == articleId);
                if (articleIndex !== -1) {
                    // 記事データを更新
                    this.articles[articleIndex] = { ...this.articles[articleIndex], ...result.article };
                    if (result.translations) {
                        this.articles[articleIndex].translations = result.translations;
                    }
                    // 記事一覧を再表示
                    this.displayArticleOutline();
                } else {
                    // 記事が見つからない場合はサイト全体を再読み込み
                    console.log('記事が見つからないため、サイトデータを再読み込みします');
                    this.loadSiteData(this.currentSiteId);
                }
            }
        } catch (error) {
            console.error('記事翻訳データ更新エラー:', error);
        }
    }

    generateLanguageIcons(article) {
        const languages = [
            { code: 'ja', name: '日' },
            { code: 'en', name: '英' },
            { code: 'zh-CN', name: '簡' },
            { code: 'zh-TW', name: '繁' },
            { code: 'ko', name: '韓' },
            { code: 'es', name: '西' },
            { code: 'ar', name: '阿' },
            { code: 'pt', name: '葡' },
            { code: 'fr', name: '仏' },
            { code: 'de', name: '独' },
            { code: 'ru', name: '露' },
            { code: 'it', name: '伊' },
            { code: 'hi', name: '印' }
        ];

        return languages.map(lang => {
            const hasContent = this.checkArticleLanguageContent(article, lang.code);
            const status = hasContent ? 'created' : 'not-created';
            const colorClass = hasContent ? 'language-created' : 'language-not-created';
            
            return `<span class="language-icon ${colorClass}" data-language="${lang.code}" data-article-id="${article.id}">${lang.name}</span>`;
        }).join('');
    }

    checkArticleLanguageContent(article, languageCode) {
        // 日本語の場合は記事のstatusをチェック
        if (languageCode === 'ja') {
            return article.status === 'generated' && article.content && article.content.trim() !== '';
        }
        
        // 他の言語の場合は翻訳データをチェック
        if (article.translations && Array.isArray(article.translations)) {
            return article.translations.some(translation => 
                translation.language_code === languageCode && 
                translation.content && 
                translation.content.trim() !== ''
            );
        }
        
        // 翻訳データがない場合は未作成として扱う
        return false;
    }

    async updatePublishDate(articleId, publishDate) {
        try {
            const response = await fetch('api.php', {
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
                this.showErrorMessage('投稿日時の更新に失敗しました: ' + result.error);
            }
        } catch (error) {
            console.error('投稿日時更新エラー:', error);
            this.showErrorMessage('投稿日時の更新中にエラーが発生しました。');
        }
    }

    // 年月日指定がないものに対して最後の年月日＋1日ずつ設定する機能
    autoFillPublishDate(inputElement) {
        // 既に値が設定されている場合は何もしない
        if (inputElement.value && inputElement.value.trim() !== '') {
            return;
        }
        
        // 全てのpublish-date-input要素を取得
        const allDateInputs = document.querySelectorAll('.publish-date-input');
        let lastValidDate = null;
        
        // 設定済みの最後の日付を取得
        for (let i = 0; i < allDateInputs.length; i++) {
            const input = allDateInputs[i];
            if (input.value && input.value.trim() !== '') {
                const date = new Date(input.value);
                if (!isNaN(date.getTime())) {
                    if (!lastValidDate || date > lastValidDate) {
                        lastValidDate = date;
                    }
                }
            }
        }
        
        // 設定済みの日付がない場合は今日の日付を使用
        if (!lastValidDate) {
            lastValidDate = new Date();
        }
        
        // 最後の日付の翌日を計算
        const nextDate = new Date(lastValidDate);
        nextDate.setDate(nextDate.getDate() + 1);
        
        // 同じ時間帯でない場合は前の記事と同じ時刻に設定
        if (lastValidDate.getHours() !== 0 || lastValidDate.getMinutes() !== 0) {
            nextDate.setHours(lastValidDate.getHours());
            nextDate.setMinutes(lastValidDate.getMinutes());
        }
        
        // フォーマットしてinput要素に設定
        const formattedDate = this.formatDateTimeLocal(nextDate.toISOString());
        inputElement.value = formattedDate;
        
        // 値が変更されたことを通知するためにchangeイベントを発火
        const articleId = inputElement.getAttribute('data-article-id');
        if (articleId) {
            this.updatePublishDate(parseInt(articleId), formattedDate);
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
            const response = await fetch('api.php', {
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
                this.showSuccessMessage('記事を生成しました。');
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('記事生成エラー:', error);
            this.showErrorMessage('記事生成中にエラーが発生しました。');
        } finally {
            this.generatingArticles.delete(articleId);
            this.hideLoading();
        }
    }

    async generateAllArticles() {
        if (!this.currentSiteId) {
            this.showErrorMessage('まず記事概要を作成してください。');
            return;
        }

        const draftArticles = this.articles.filter(article => article.status === 'draft');
        if (draftArticles.length === 0) {
            this.showErrorMessage('生成する記事がありません。');
            return;
        }

        if (!confirm(`${draftArticles.length}記事を一括生成しますか？（時間がかかる場合があります）`)) {
            return;
        }

        // 一括生成実行中フラグを設定
        if (this.isGeneratingAll) {
            this.showErrorMessage('既に一括生成中です。しばらくお待ちください。');
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
                    const response = await fetch('api.php', {
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
            
            this.showSuccessMessage(message);
            
        } catch (error) {
            console.error('一括生成エラー:', error);
            this.showErrorMessage('一括生成中にエラーが発生しました。');
        } finally {
            this.isGeneratingAll = false;
            this.bulkGenerationCancelled = false;
            this.hideBulkGenerationProgress();
        }
    }

    async showArticleDetail(articleId) {
        const article = this.articles.find(a => a.id == articleId);
        if (!article || article.status !== 'generated') {
            this.showErrorMessage('記事が見つからないか、まだ生成されていません。');
            return;
        }

        this.currentDetailArticleId = articleId;
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
            this.showErrorMessage('まずサイトを選択してください。');
            return;
        }

        const generatedArticles = this.articles.filter(article => article.status === 'generated');
        if (generatedArticles.length === 0) {
            this.showErrorMessage('エクスポートする記事がありません。');
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
                this.showErrorMessage('CSV出力中にエラーが発生しました。');
            }
        } catch (error) {
            console.error('CSV出力エラー:', error);
            this.showErrorMessage('CSV出力中にエラーが発生しました。');
        }
    }

    async saveReferenceUrls(siteId, urls) {
        try {
            const response = await fetch('api.php', {
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
            const response = await fetch('api.php', {
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
            this.showErrorMessage('まずサイトを選択してください。');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
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
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('AIログ取得エラー:', error);
            this.showErrorMessage('AIログ取得中にエラーが発生しました。');
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

    // エラーメッセージを表示
    showErrorMessage(message) {
        console.error(message);
        this.showNotification(message, 'error');
    }

    // 成功メッセージを表示
    showSuccessMessage(message) {
        console.log(message);
        this.showNotification(message, 'success');
    }

    // 通知を表示
    showNotification(message, type = 'info') {
        // 既存の通知を削除
        const existingNotification = document.getElementById('notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        // 新しい通知を作成
        const notification = document.createElement('div');
        notification.id = 'notification';
        notification.className = `notification ${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            max-width: 400px;
            word-wrap: break-word;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out;
        `;

        // タイプに応じて色を設定
        switch (type) {
            case 'error':
                notification.style.backgroundColor = '#f44336';
                break;
            case 'success':
                notification.style.backgroundColor = '#4CAF50';
                break;
            case 'warning':
                notification.style.backgroundColor = '#ff9800';
                break;
            default:
                notification.style.backgroundColor = '#2196F3';
        }

        // CSSアニメーションを追加
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(notification);

        // 5秒後に自動削除
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 5000);

        // クリックで削除
        notification.addEventListener('click', () => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
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

    async loadMultilingualSettings(siteId) {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_multilingual_settings',
                    site_id: siteId
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.displayMultilingualSettings(result.settings);
            }
        } catch (error) {
            console.error('多言語設定読み込みエラー:', error);
        }
    }

    displayMultilingualSettings(settings) {
        // 言語設定のチェックボックスを更新
        settings.forEach(setting => {
            const checkbox = document.querySelector(`input[data-language="${setting.language_code}"]`);
            if (checkbox) {
                checkbox.checked = setting.is_enabled;
                // 既存のイベントリスナーを削除してから新しいものを追加
                checkbox.removeEventListener('change', checkbox._multilingualHandler);
                checkbox._multilingualHandler = () => this.updateMultilingualSetting(setting.language_code, checkbox.checked);
                checkbox.addEventListener('change', checkbox._multilingualHandler);
            }
        });
    }

    async updateMultilingualSetting(languageCode, isEnabled) {
        // 既に処理中の場合は無視
        if (this.updatingMultilingualSetting) {
            return;
        }
        
        try {
            this.updatingMultilingualSetting = true;
            const settings = {};
            settings[languageCode] = isEnabled;
            
            console.log('多言語設定更新:', { site_id: this.currentSiteId, language: languageCode, enabled: isEnabled });
            
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_multilingual_settings',
                    site_id: this.currentSiteId,
                    settings: settings
                })
            });
            const result = await this.handleApiResponse(response);
            
            console.log('多言語設定更新結果:', result);
            
            if (!result.success) {
                console.error('多言語設定更新エラー:', result.error);
                this.showErrorMessage('多言語設定の更新に失敗しました: ' + result.error);
            } else {
                console.log(`言語 ${languageCode} が ${isEnabled ? '有効' : '無効'} になりました`);
            }
        } catch (error) {
            console.error('多言語設定更新エラー:', error);
            this.showErrorMessage('多言語設定の更新中にエラーが発生しました。');
        } finally {
            this.updatingMultilingualSetting = false;
        }
    }

    async changeArticleLanguage(languageCode) {
        if (!this.currentSiteId) return;
        
        this.currentLanguage = languageCode;
        this.showLoading();
        
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_multilingual_articles',
                    site_id: this.currentSiteId,
                    language_code: languageCode
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.articles = result.articles;
                this.displayArticleOutline();
            }
        } catch (error) {
            console.error('言語切り替えエラー:', error);
        } finally {
            this.hideLoading();
        }
    }

    async changeArticleDetailLanguage(languageCode) {
        const currentArticleId = this.currentDetailArticleId;
        if (!currentArticleId) return;
        
        if (languageCode === 'ja') {
            // 日本語の場合はオリジナル記事を表示
            this.showArticleDetail(currentArticleId);
        } else {
            // 多言語版を表示
            this.showMultilingualArticleDetail(currentArticleId, languageCode);
        }
    }

    async showMultilingualArticleDetail(articleId, languageCode) {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_article_translations',
                    article_id: articleId
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                const translation = result.translations.find(t => t.language_code === languageCode);
                if (translation) {
                    this.currentDetailArticleId = articleId;
                    document.getElementById('article-detail-content').innerHTML = `
                        <h3>${translation.title}</h3>
                        <p><strong>SEOキーワード:</strong> ${translation.seo_keywords}</p>
                        <p><strong>概要:</strong> ${translation.summary}</p>
                        <hr>
                        <div style="white-space: pre-wrap; line-height: 1.6;">${translation.content}</div>
                    `;
                    
                    // モーダルを表示
                    this.articleDetailModal.style.display = 'block';
                } else {
                    this.showErrorMessage(`この記事の${languageCode}翻訳はまだ作成されていません。`);
                }
            }
        } catch (error) {
            console.error('多言語記事詳細エラー:', error);
            this.showErrorMessage('多言語記事の詳細表示中にエラーが発生しました。');
        }
    }

    async generateMultilingualArticles(selectedLanguages = null) {
        if (!this.currentSiteId) {
            this.showErrorMessage('まずサイトを選択してください。');
            return;
        }

        // 日本語記事が作成済みかチェック
        const hasJapaneseArticles = await this.checkJapaneseArticles();
        if (!hasJapaneseArticles) {
            this.showErrorMessage('多言語記事を生成するには、まず日本語記事を作成してください。');
            return;
        }

        if (!selectedLanguages && !confirm('有効な言語で多言語記事を生成しますか？')) {
            return;
        }

        this.showMultilingualProgress('準備中...');
        
        // 通知フラグをリセット
        this.completionNotificationShown = false;
        this.errorNotificationShown = false;
        
        try {
            // まず準備処理を実行して進捗IDを取得
            const requestData = {
                action: 'generate_multilingual_articles_with_progress',
                site_id: this.currentSiteId,
                ai_model: this.aiModelSelect.value
            };
            
            // 選択した言語がある場合は追加
            if (selectedLanguages) {
                requestData.selected_languages = selectedLanguages;
            }
            
            const startResponse = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            });
            const startResult = await this.handleApiResponse(startResponse);
            
            if (!startResult.success) {
                this.hideMultilingualProgress();
                this.showErrorMessage('エラー: ' + startResult.error);
                return;
            }
            
            // 進捗IDを保存して進捗ポーリングを開始
            this.currentProgressId = startResult.progress_id;
            this.totalProgressTasks = startResult.total_tasks;
            this.startProgressPolling();
            
            // 翻訳処理を開始
            try {
                const executeRequestData = {
                    action: 'execute_multilingual_generation',
                    site_id: this.currentSiteId,
                    ai_model: this.aiModelSelect.value,
                    progress_id: this.currentProgressId
                };
                
                // 選択した言語がある場合は追加
                if (selectedLanguages) {
                    executeRequestData.selected_languages = selectedLanguages;
                }
                
                const executeResponse = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(executeRequestData)
                });
                
                const executeResult = await this.handleApiResponse(executeResponse);
                console.log('翻訳処理完了:', executeResult);
                
                // 進捗ポーリングを停止
                this.stopProgressPolling();
                this.hideMultilingualProgress();
                
                if (executeResult.success) {
                    this.showSuccessMessage(`${executeResult.translated_count}件の多言語記事を生成しました。`);
                    // 現在の言語で記事一覧を更新
                    this.changeArticleLanguage(this.currentLanguage);
                } else {
                    this.showErrorMessage('エラー: ' + executeResult.error);
                }
            } catch (executeError) {
                console.error('翻訳処理エラー:', executeError);
                this.stopProgressPolling();
                this.hideMultilingualProgress();
                this.showErrorMessage('翻訳処理中にエラーが発生しました。');
            }
            
        } catch (error) {
            console.error('多言語記事生成エラー:', error);
            this.stopProgressPolling();
            this.hideMultilingualProgress();
            this.showErrorMessage('多言語記事生成中にエラーが発生しました。');
        }
    }

    showMultilingualProgress(message) {
        let progressModal = document.getElementById('multilingual-progress-modal');
        if (!progressModal) {
            progressModal = document.createElement('div');
            progressModal.id = 'multilingual-progress-modal';
            progressModal.innerHTML = `
                <div class="modal-overlay">
                    <div class="modal-content">
                        <h3>多言語記事生成中</h3>
                        <div id="multilingual-progress-message">${message}</div>
                        <div class="progress-bar">
                            <div id="multilingual-progress-fill" style="width: 0%"></div>
                        </div>
                        <div id="multilingual-progress-details"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(progressModal);
        }
        progressModal.style.display = 'block';
        document.getElementById('multilingual-progress-message').textContent = message;
    }

    updateMultilingualProgress(articleTitle, language, current, total) {
        const progressFill = document.getElementById('multilingual-progress-fill');
        const progressDetails = document.getElementById('multilingual-progress-details');
        const progressMessage = document.getElementById('multilingual-progress-message');
        
        if (progressFill && progressDetails && progressMessage) {
            const percentage = Math.round((current / total) * 100);
            progressFill.style.width = `${percentage}%`;
            
            // 言語名を日本語で表示
            const languageNames = {
                'en': '英語',
                'es': 'スペイン語',
                'fr': 'フランス語',
                'de': 'ドイツ語',
                'it': 'イタリア語',
                'pt': 'ポルトガル語',
                'ru': 'ロシア語',
                'zh': '中国語',
                'ko': '韓国語',
                'ar': 'アラビア語',
                'hi': 'ヒンディー語',
                'th': 'タイ語',
                'vi': 'ベトナム語',
                'id': 'インドネシア語',
                'ms': 'マレー語',
                'tl': 'フィリピン語',
                'ja': '日本語'
            };
            
            const languageName = languageNames[language] || language;
            progressMessage.textContent = `${current}/${total}件の多言語記事を生成中`;
            progressDetails.innerHTML = `
                <p><strong>記事:</strong> ${articleTitle}</p>
                <p><strong>対応中の言語:</strong> ${languageName}</p>
            `;
        }
    }

    hideMultilingualProgress() {
        const progressModal = document.getElementById('multilingual-progress-modal');
        if (progressModal) {
            progressModal.style.display = 'none';
        }
    }

    startProgressPolling() {
        if (!this.currentProgressId) {
            console.error('進捗IDが設定されていません');
            return;
        }
        
        console.log('進捗ポーリング開始 - ID:', this.currentProgressId);
        
        this.progressPollingInterval = setInterval(async () => {
            try {
                console.log('進捗取得中... ID:', this.currentProgressId);
                
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'get_translation_progress',
                        progress_id: this.currentProgressId
                    })
                });
                
                const result = await this.handleApiResponse(response);
                console.log('進捗結果:', result);
                
                if (result.success && result.progress) {
                    const progress = result.progress;
                    console.log('進捗データ:', progress);
                    
                    this.updateMultilingualProgress(
                        progress.article_title || '記事を翻訳中...',
                        progress.language || '処理中',
                        progress.current || 0,
                        progress.total || this.totalProgressTasks
                    );
                    
                    // 完了チェック
                    if (progress.current >= progress.total && progress.article_title === '完了') {
                        console.log('処理完了を検出');
                        this.stopProgressPolling();
                        this.hideMultilingualProgress();
                        if (!this.completionNotificationShown) {
                            this.showSuccessMessage(`多言語記事生成が完了しました。${progress.language}`);
                            this.completionNotificationShown = true;
                        }
                        // 現在の言語で記事一覧を更新
                        this.changeArticleLanguage(this.currentLanguage);
                    }
                    
                    // エラーチェック
                    if (progress.article_title === 'エラー') {
                        console.log('エラーを検出');
                        this.stopProgressPolling();
                        this.hideMultilingualProgress();
                        if (!this.errorNotificationShown) {
                            this.showErrorMessage('エラーが発生しました: ' + progress.language);
                            this.errorNotificationShown = true;
                        }
                    }
                } else if (!result.success && result.error === 'Progress expired') {
                    console.log('進捗ファイル期限切れ');
                    // 進捗ファイルが期限切れ（処理が完了している可能性）
                    this.stopProgressPolling();
                    this.hideMultilingualProgress();
                    this.showSuccessMessage('多言語記事生成が完了しました。');
                    this.changeArticleLanguage(this.currentLanguage);
                } else {
                    console.log('進捗取得失敗:', result);
                }
            } catch (error) {
                console.error('進捗取得エラー:', error);
            }
        }, 3000); // 3秒間隔で更新
    }

    stopProgressPolling() {
        if (this.progressPollingInterval) {
            clearInterval(this.progressPollingInterval);
            this.progressPollingInterval = null;
        }
        this.currentProgressId = null;
        this.totalProgressTasks = 0;
    }

    async checkJapaneseArticles() {
        try {
            console.log('日本語記事チェック開始 - サイトID:', this.currentSiteId);
            
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_articles',
                    site_id: this.currentSiteId
                })
            });
            const result = await this.handleApiResponse(response);
            
            console.log('取得した記事データ:', result);
            
            if (result.success && result.articles && result.articles.length > 0) {
                console.log('全記事数:', result.articles.length);
                
                // 各記事のcontentをチェック
                result.articles.forEach((article, index) => {
                    console.log(`記事${index + 1}:`, {
                        id: article.id,
                        title: article.title,
                        status: article.status,
                        hasContent: !!article.content,
                        contentLength: article.content ? article.content.length : 0,
                        contentPreview: article.content ? article.content.substring(0, 100) + '...' : 'なし'
                    });
                });
                
                // コンテンツが作成済みの記事があるかチェック
                const articlesWithContent = result.articles.filter(article => 
                    article.content && article.content.trim() !== ''
                );
                
                console.log('コンテンツありの記事数:', articlesWithContent.length);
                console.log('コンテンツありの記事:', articlesWithContent.map(a => ({ id: a.id, title: a.title })));
                
                return articlesWithContent.length > 0;
            }
            
            console.log('記事が見つからないか、取得に失敗');
            return false;
        } catch (error) {
            console.error('日本語記事チェックエラー:', error);
            return false;
        }
    }

    // 初期状態で多言語設定のチェックボックスを設定
    initializeMultilingualSettings() {
        const languages = [
            { code: 'en', name: 'English' },
            { code: 'zh-CN', name: '中文（简体）' },
            { code: 'zh-TW', name: '中文（繁體）' },
            { code: 'ko', name: '한국어' },
            { code: 'es', name: 'Español' },
            { code: 'ar', name: 'العربية' },
            { code: 'pt', name: 'Português' },
            { code: 'fr', name: 'Français' },
            { code: 'de', name: 'Deutsch' },
            { code: 'ru', name: 'Русский' },
            { code: 'it', name: 'Italiano' }
        ];

        languages.forEach(language => {
            const checkbox = document.querySelector(`input[data-language="${language.code}"]`);
            if (checkbox) {
                checkbox.checked = false; // 初期状態は無効
                checkbox.addEventListener('change', () => {
                    if (this.currentSiteId) {
                        this.updateMultilingualSetting(language.code, checkbox.checked);
                    } else {
                        // サイトが選択されていない場合は警告
                        this.showErrorMessage('サイトを選択または作成後に言語設定を変更してください。');
                        checkbox.checked = false;
                    }
                });
            }
        });
    }

    // 言語選択モーダルを表示
    showLanguageSelectionModal(action) {
        const languages = [
            { code: 'ja', name: '日本語', icon: '日' },
            { code: 'en', name: 'English', icon: '英' },
            { code: 'zh-CN', name: '中文（简体）', icon: '中' },
            { code: 'zh-TW', name: '中文（繁體）', icon: '繁' },
            { code: 'ko', name: '한국어', icon: '韓' },
            { code: 'es', name: 'Español', icon: '西' },
            { code: 'ar', name: 'العربية', icon: '阿' },
            { code: 'pt', name: 'Português', icon: '葡' },
            { code: 'fr', name: 'Français', icon: '仏' },
            { code: 'de', name: 'Deutsch', icon: '独' },
            { code: 'ru', name: 'Русский', icon: '露' },
            { code: 'it', name: 'Italiano', icon: '伊' },
            { code: 'hi', name: 'हिन्दी', icon: '印' }
        ];

        const modalHtml = `
            <div id="language-selection-modal" class="modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>言語選択</h2>
                        <span class="close" onclick="window.satelliteSystem.hideLanguageSelectionModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="language-selection-controls">
                            <button id="select-all-languages" class="btn btn-secondary">全選択</button>
                            <button id="deselect-all-languages" class="btn btn-secondary">全解除</button>
                        </div>
                        <div class="language-grid">
                            ${languages.map(lang => `
                                <div class="language-item">
                                    <input type="checkbox" 
                                           id="lang-${lang.code}" 
                                           value="${lang.code}" 
                                           ${lang.code === 'ja' ? 'checked' : ''}>
                                    <label for="lang-${lang.code}">
                                        <span class="language-icon-modal">${lang.icon}</span>
                                        <span class="language-name">${lang.name}</span>
                                    </label>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="confirm-language-selection" class="btn btn-primary">確定</button>
                        <button onclick="window.satelliteSystem.hideLanguageSelectionModal()" class="btn btn-secondary">キャンセル</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // イベントリスナーを追加
        document.getElementById('select-all-languages').addEventListener('click', () => {
            document.querySelectorAll('#language-selection-modal input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        });

        document.getElementById('deselect-all-languages').addEventListener('click', () => {
            document.querySelectorAll('#language-selection-modal input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        });

        document.getElementById('confirm-language-selection').addEventListener('click', () => {
            const selectedLanguages = Array.from(document.querySelectorAll('#language-selection-modal input[type="checkbox"]:checked'))
                .map(checkbox => checkbox.value);
            
            this.hideLanguageSelectionModal();
            
            if (action === 'generate-all') {
                this.generateAllArticlesWithLanguages(selectedLanguages);
            } else if (action === 'generate-multilingual') {
                this.generateMultilingualArticlesWithLanguages(selectedLanguages);
            }
        });

        // モーダルクリックで閉じる
        document.getElementById('language-selection-modal').addEventListener('click', (e) => {
            if (e.target.id === 'language-selection-modal') {
                this.hideLanguageSelectionModal();
            }
        });
    }

    // 言語選択モーダルを非表示
    hideLanguageSelectionModal() {
        const modal = document.getElementById('language-selection-modal');
        if (modal) {
            modal.remove();
        }
    }

    // 選択した言語で全記事一括作成
    async generateAllArticlesWithLanguages(selectedLanguages) {
        if (selectedLanguages.length === 0) {
            this.showErrorMessage('言語を選択してください。');
            return;
        }

        if (selectedLanguages.includes('ja')) {
            // 日本語が選択されている場合は従来の処理
            await this.generateAllArticles();
        }

        // 日本語以外の言語が選択されている場合は多言語記事生成
        const otherLanguages = selectedLanguages.filter(lang => lang !== 'ja');
        if (otherLanguages.length > 0) {
            await this.generateMultilingualArticlesWithLanguages(otherLanguages);
        }
    }

    // 選択した言語で多言語記事生成
    async generateMultilingualArticlesWithLanguages(selectedLanguages) {
        if (selectedLanguages.length === 0) {
            this.showErrorMessage('言語を選択してください。');
            return;
        }

        if (!this.currentSiteId) {
            this.showErrorMessage('まずサイトを選択してください。');
            return;
        }

        // 日本語記事が作成済みかチェック
        const hasJapaneseArticles = await this.checkJapaneseArticles();
        if (!hasJapaneseArticles) {
            this.showErrorMessage('多言語記事を生成するには、まず日本語記事を作成してください。');
            return;
        }

        const filteredLanguages = selectedLanguages.filter(lang => lang !== 'ja');
        if (filteredLanguages.length === 0) {
            this.showErrorMessage('日本語以外の言語を選択してください。');
            return;
        }

        if (!confirm(`選択した${filteredLanguages.length}言語で多言語記事を生成しますか？`)) {
            return;
        }

        await this.generateMultilingualArticles(filteredLanguages);
    }

    // 記事選択関連のメソッド
    selectAllArticles() {
        const checkboxes = document.querySelectorAll('.article-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        document.getElementById('select-all-header').checked = true;
    }

    deselectAllArticles() {
        const checkboxes = document.querySelectorAll('.article-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        document.getElementById('select-all-header').checked = false;
    }

    toggleAllArticles(checked) {
        const checkboxes = document.querySelectorAll('.article-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
        });
    }

    getSelectedArticleIds() {
        const checkboxes = document.querySelectorAll('.article-checkbox:checked');
        return Array.from(checkboxes).map(checkbox => checkbox.dataset.articleId);
    }

    // 選択記事生成
    async generateSelectedArticles() {
        const selectedIds = this.getSelectedArticleIds();
        if (selectedIds.length === 0) {
            this.showErrorMessage('記事を選択してください。');
            return;
        }

        // 言語選択モーダルを表示
        this.showLanguageSelectionModalForSelected(selectedIds);
    }

    // 未作成記事を全部作成
    async generateAllUncreatedArticles() {
        // 日本語記事が未作成の記事を特定
        const uncreatedArticles = this.articles.filter(article => 
            !this.checkArticleLanguageContent(article, 'ja')
        );

        if (uncreatedArticles.length === 0) {
            this.showErrorMessage('未作成の記事がありません。');
            return;
        }

        // 確認アラート
        if (!confirm(`${uncreatedArticles.length}件の記事を作成します。よろしいですか？`)) {
            return;
        }

        // プログレスバー表示
        this.showAutoGenerationProgress(uncreatedArticles.length);
        
        // 未作成記事のIDを取得
        const uncreatedIds = uncreatedArticles.map(article => article.id);
        
        try {
            // 各記事を順次生成
            for (let i = 0; i < uncreatedIds.length; i++) {
                const articleId = uncreatedIds[i];
                const article = this.articles.find(a => a.id === articleId);
                
                // 進捗を更新
                this.updateAutoGenerationProgress(i, uncreatedIds.length, `記事生成中: ${article.title}`);
                
                // 記事を生成
                await this.generateSingleArticleForBatch(articleId);
                
                // 少し待機してAPIレート制限を回避
                await this.sleep(1000);
            }
            
            // 完了
            this.updateAutoGenerationProgress(uncreatedIds.length, uncreatedIds.length, '完了');
            this.showSuccessMessage(`${uncreatedIds.length}件の記事を作成しました。`);
            
            // データを再読み込み
            await this.loadSiteData(this.currentSiteId);
            
        } catch (error) {
            console.error('記事生成エラー:', error);
            this.showErrorMessage('記事生成中にエラーが発生しました: ' + error.message);
        } finally {
            // プログレスバーを非表示
            this.hideAutoGenerationProgress();
        }
    }

    // 選択記事削除
    async deleteSelectedArticles() {
        const selectedIds = this.getSelectedArticleIds();
        if (selectedIds.length === 0) {
            this.showErrorMessage('削除する記事を選択してください。');
            return;
        }

        if (!confirm(`選択した${selectedIds.length}件の記事を削除しますか？この操作は取り消せません。`)) {
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_articles',
                    article_ids: selectedIds
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.showSuccessMessage(`${selectedIds.length}件の記事を削除しました。`);
                await this.loadSiteData(this.currentSiteId);
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('記事削除エラー:', error);
            this.showErrorMessage('記事削除中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    // 記事フィールド更新
    async updateArticleField(articleId, field, value) {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_article_field',
                    article_id: articleId,
                    field: field,
                    value: value
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                // 記事データを更新
                const articleIndex = this.articles.findIndex(a => a.id == articleId);
                if (articleIndex !== -1) {
                    this.articles[articleIndex][field] = value;
                }
                console.log(`記事${articleId}の${field}を更新しました`);
            } else {
                this.showErrorMessage('フィールドの更新に失敗しました: ' + result.error);
            }
        } catch (error) {
            console.error('フィールド更新エラー:', error);
            this.showErrorMessage('フィールドの更新中にエラーが発生しました。');
        }
    }

    // 記事コンテンツ編集
    async editArticleContent(articleId) {
        const article = this.articles.find(a => a.id == articleId);
        if (!article) {
            this.showErrorMessage('記事が見つかりません。');
            return;
        }

        // 編集用モーダルを表示
        this.showArticleEditModal(article);
    }

    // 記事作り直し
    async regenerateArticle(articleId) {
        if (!confirm('この記事を作り直しますか？現在の内容は失われます。')) {
            return;
        }

        await this.generateArticle(articleId);
    }

    // 単一記事削除
    async deleteArticle(articleId) {
        if (!confirm('この記事を削除しますか？この操作は取り消せません。')) {
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_articles',
                    article_ids: [articleId]
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.showSuccessMessage('記事を削除しました。');
                await this.loadSiteData(this.currentSiteId);
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('記事削除エラー:', error);
            this.showErrorMessage('記事削除中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    // 記事編集モーダル表示
    showArticleEditModal(article) {
        const modalHtml = `
            <div id="article-edit-modal" class="modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>記事編集</h2>
                        <span class="close" onclick="window.satelliteSystem.hideArticleEditModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="edit-field">
                            <label for="edit-title">タイトル:</label>
                            <input type="text" id="edit-title" value="${article.title}">
                        </div>
                        <div class="edit-field">
                            <label for="edit-keywords">SEOキーワード:</label>
                            <input type="text" id="edit-keywords" value="${article.seo_keywords}">
                        </div>
                        <div class="edit-field">
                            <label for="edit-summary">概要:</label>
                            <textarea id="edit-summary" rows="3">${article.summary}</textarea>
                        </div>
                        <div class="edit-field">
                            <label for="edit-content">記事本文:</label>
                            <textarea id="edit-content" rows="20">${article.content || ''}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="save-article-changes" class="btn btn-primary">保存</button>
                        <button onclick="window.satelliteSystem.hideArticleEditModal()" class="btn btn-secondary">キャンセル</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // 保存ボタンのイベント
        document.getElementById('save-article-changes').addEventListener('click', async () => {
            const title = document.getElementById('edit-title').value;
            const keywords = document.getElementById('edit-keywords').value;
            const summary = document.getElementById('edit-summary').value;
            const content = document.getElementById('edit-content').value;

            await this.saveArticleChanges(article.id, {
                title: title,
                seo_keywords: keywords,
                summary: summary,
                content: content
            });
        });

        // モーダルクリックで閉じる
        document.getElementById('article-edit-modal').addEventListener('click', (e) => {
            if (e.target.id === 'article-edit-modal') {
                this.hideArticleEditModal();
            }
        });
    }

    // 記事編集モーダル非表示
    hideArticleEditModal() {
        const modal = document.getElementById('article-edit-modal');
        if (modal) {
            modal.remove();
        }
    }

    // 記事変更保存
    async saveArticleChanges(articleId, changes) {
        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_article_content',
                    article_id: articleId,
                    changes: changes
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.showSuccessMessage('記事を保存しました。');
                this.hideArticleEditModal();
                await this.loadSiteData(this.currentSiteId);
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('記事保存エラー:', error);
            this.showErrorMessage('記事保存中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    // 選択記事用言語選択モーダル
    showLanguageSelectionModalForSelected(selectedIds) {
        const languages = [
            { code: 'ja', name: '日本語', icon: '日' },
            { code: 'en', name: 'English', icon: '英' },
            { code: 'zh-CN', name: '中文（简体）', icon: '中' },
            { code: 'zh-TW', name: '中文（繁體）', icon: '繁' },
            { code: 'ko', name: '한국어', icon: '韓' },
            { code: 'es', name: 'Español', icon: '西' },
            { code: 'ar', name: 'العربية', icon: '阿' },
            { code: 'pt', name: 'Português', icon: '葡' },
            { code: 'fr', name: 'Français', icon: '仏' },
            { code: 'de', name: 'Deutsch', icon: '独' },
            { code: 'ru', name: 'Русский', icon: '露' },
            { code: 'it', name: 'Italiano', icon: '伊' },
            { code: 'hi', name: 'हिन्दी', icon: '印' }
        ];

        // 選択した記事で既に作成されている言語を確認
        const selectedArticles = this.articles.filter(article => selectedIds.includes(article.id));
        const existingLanguages = new Set();
        
        selectedArticles.forEach(article => {
            languages.forEach(lang => {
                if (this.checkArticleLanguageContent(article, lang.code)) {
                    existingLanguages.add(lang.code);
                }
            });
        });

        const modalHtml = `
            <div id="selected-language-modal" class="modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>生成言語選択</h2>
                        <span class="close" onclick="window.satelliteSystem.hideSelectedLanguageModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <p>選択した${selectedIds.length}件の記事を生成する言語を選択してください。</p>
                        <div class="language-selection-controls">
                            <button id="select-all-selected-languages" class="btn btn-secondary">全選択</button>
                            <button id="deselect-all-selected-languages" class="btn btn-secondary">全解除</button>
                        </div>
                        <div class="language-grid">
                            ${languages.map(lang => `
                                <div class="language-item">
                                    <input type="checkbox" 
                                           id="selected-lang-${lang.code}" 
                                           value="${lang.code}" 
                                           ${existingLanguages.has(lang.code) ? 'checked' : ''}>
                                    <label for="selected-lang-${lang.code}">
                                        <span class="language-icon-modal">${lang.icon}</span>
                                        <span class="language-name">${lang.name}</span>
                                        ${existingLanguages.has(lang.code) ? '<span class="existing-indicator">作成済</span>' : ''}
                                    </label>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="confirm-selected-generation" class="btn btn-primary">記事生成開始</button>
                        <button onclick="window.satelliteSystem.hideSelectedLanguageModal()" class="btn btn-secondary">キャンセル</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // イベントリスナーを追加
        document.getElementById('select-all-selected-languages').addEventListener('click', () => {
            document.querySelectorAll('#selected-language-modal input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        });

        document.getElementById('deselect-all-selected-languages').addEventListener('click', () => {
            document.querySelectorAll('#selected-language-modal input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        });

        document.getElementById('confirm-selected-generation').addEventListener('click', () => {
            const selectedLanguages = Array.from(document.querySelectorAll('#selected-language-modal input[type="checkbox"]:checked'))
                .map(checkbox => checkbox.value);
            
            this.hideSelectedLanguageModal();
            this.executeSelectedArticleGeneration(selectedIds, selectedLanguages);
        });

        // モーダルクリックで閉じる
        document.getElementById('selected-language-modal').addEventListener('click', (e) => {
            if (e.target.id === 'selected-language-modal') {
                this.hideSelectedLanguageModal();
            }
        });
    }

    // 選択記事用言語モーダル非表示
    hideSelectedLanguageModal() {
        const modal = document.getElementById('selected-language-modal');
        if (modal) {
            modal.remove();
        }
    }

    // 選択記事の生成実行
    async executeSelectedArticleGeneration(selectedIds, selectedLanguages) {
        if (selectedLanguages.length === 0) {
            this.showErrorMessage('言語を選択してください。');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_selected_articles',
                    article_ids: selectedIds,
                    languages: selectedLanguages,
                    ai_model: this.aiModelSelect.value
                })
            });
            const result = await this.handleApiResponse(response);
            
            if (result.success) {
                this.showSuccessMessage(`選択した記事の生成を開始しました。`);
                await this.loadSiteData(this.currentSiteId);
            } else {
                this.showErrorMessage('エラー: ' + result.error);
            }
        } catch (error) {
            console.error('選択記事生成エラー:', error);
            this.showErrorMessage('記事生成中にエラーが発生しました。');
        } finally {
            this.hideLoading();
        }
    }

    // プログレスバー表示
    showAutoGenerationProgress(totalCount) {
        const autoGenerationStatus = document.getElementById('auto-generation-status');
        if (autoGenerationStatus) {
            autoGenerationStatus.style.display = 'block';
        }
    }

    // プログレスバー更新
    updateAutoGenerationProgress(currentIndex, totalCount, statusMessage) {
        const progressFill = document.getElementById('progress-fill');
        const statusText = document.getElementById('status-text');
        const progressDetails = document.getElementById('progress-details');
        
        const percentage = Math.round((currentIndex / totalCount) * 100);
        
        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }
        
        if (statusText) {
            statusText.textContent = statusMessage;
        }
        
        if (progressDetails) {
            progressDetails.textContent = `${currentIndex}/${totalCount} (${percentage}%)`;
        }
    }

    // プログレスバー非表示
    hideAutoGenerationProgress() {
        const autoGenerationStatus = document.getElementById('auto-generation-status');
        if (autoGenerationStatus) {
            autoGenerationStatus.style.display = 'none';
        }
    }

    // 記事生成（バッチ処理用）
    async generateSingleArticleForBatch(articleId) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_article',
                article_id: articleId,
                ai_model: this.aiModelSelect.value
            })
        });
        
        const result = await this.handleApiResponse(response);
        if (!result.success) {
            throw new Error(result.error || '記事生成に失敗しました');
        }
        
        return result;
    }

    // 待機用のヘルパー関数
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// 複数サイト記事作成クラス
class MultiSiteArticleManager {
    constructor() {
        this.selectedSites = new Set();
        this.articleProgressItems = new Map();
        this.isCreating = false;
        this.initializeElements();
        this.bindEvents();
        this.loadSites();
    }

    initializeElements() {
        this.multiSiteBtn = document.getElementById('multi-site-creation-btn');
        this.multiSitePanel = document.getElementById('multi-site-panel');
        this.siteList = document.getElementById('multi-site-list');
        this.articleCountInput = document.getElementById('multi-site-article-count');
        this.createBtn = document.getElementById('multi-site-create-btn');
        this.progressContainer = document.getElementById('multi-site-progress');
        this.progressFill = document.getElementById('multi-site-progress-fill');
        this.progressText = document.getElementById('multi-site-progress-text');
        this.toggleDetailsBtn = document.getElementById('toggle-progress-details');
        this.progressDetails = document.getElementById('multi-site-progress-details');
        this.articleProgressList = document.getElementById('article-progress-list');
    }

    bindEvents() {
        this.multiSiteBtn.addEventListener('click', () => this.togglePanel());
        this.createBtn.addEventListener('click', () => this.startCreation());
        this.toggleDetailsBtn.addEventListener('click', () => this.toggleProgressDetails());
    }

    togglePanel() {
        const isHidden = this.multiSitePanel.style.display === 'none';
        this.multiSitePanel.style.display = isHidden ? 'block' : 'none';
        
        if (isHidden) {
            this.loadSites();
        }
    }

    async loadSites() {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_all_sites' })
            });

            const result = await response.json();
            if (result.success) {
                this.renderSiteList(result.sites);
            } else {
                this.showErrorMessage(result.error || 'サイト一覧の取得に失敗しました');
            }
        } catch (error) {
            this.showErrorMessage('サイト一覧の読み込み中にエラーが発生しました: ' + error.message);
        }
    }

    renderSiteList(sites) {
        this.siteList.innerHTML = '';
        
        if (sites.length === 0) {
            this.siteList.innerHTML = '<p>作成されたサイトがありません。先にサイト分析を行ってください。</p>';
            return;
        }

        sites.forEach(site => {
            const siteItem = document.createElement('div');
            siteItem.className = 'site-item';
            siteItem.innerHTML = `
                <input type="checkbox" class="site-checkbox" data-site-id="${site.id}" data-site-name="${site.name}">
                <div class="site-name">${site.name || 'サイト' + site.id}</div>
                <div class="site-description">${site.description || 'サイト説明なし'}</div>
            `;

            const checkbox = siteItem.querySelector('.site-checkbox');
            checkbox.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.selectedSites.add({
                        id: site.id,
                        name: site.name || 'サイト' + site.id,
                        description: site.description || ''
                    });
                } else {
                    // Set から特定のサイトを削除
                    for (const selectedSite of this.selectedSites) {
                        if (selectedSite.id === site.id) {
                            this.selectedSites.delete(selectedSite);
                            break;
                        }
                    }
                }
                this.updateCreateButtonState();
            });

            this.siteList.appendChild(siteItem);
        });
    }

    updateCreateButtonState() {
        this.createBtn.disabled = this.selectedSites.size === 0 || this.isCreating;
    }

    async startCreation() {
        if (this.selectedSites.size === 0) {
            this.showErrorMessage('記事を作成するサイトを選択してください。');
            return;
        }

        const articleCount = parseInt(this.articleCountInput.value);
        if (isNaN(articleCount) || articleCount < 1) {
            this.showErrorMessage('記事数を正しく入力してください。');
            return;
        }

        this.isCreating = true;
        this.updateCreateButtonState();
        this.showProgress();
        
        // 作成予定記事リストを初期化
        this.initializeArticleProgressList();

        let totalArticles = this.selectedSites.size * articleCount;
        let completedArticles = 0;

        try {
            for (const site of this.selectedSites) {
                await this.createArticlesForSite(site, articleCount, () => {
                    completedArticles++;
                    const progress = (completedArticles / totalArticles) * 100;
                    this.updateOverallProgress(progress, `${completedArticles}/${totalArticles} 記事作成完了`);
                });
            }

            this.progressText.textContent = '全ての記事作成が完了しました！';
            
        } catch (error) {
            this.showErrorMessage('記事作成中にエラーが発生しました: ' + error.message);
        } finally {
            this.isCreating = false;
            this.updateCreateButtonState();
        }
    }

    initializeArticleProgressList() {
        this.articleProgressList.innerHTML = '';
        this.articleProgressItems.clear();

        const articleCount = parseInt(this.articleCountInput.value);
        
        for (const site of this.selectedSites) {
            for (let i = 1; i <= articleCount; i++) {
                const itemId = `${site.id}-${i}`;
                const progressItem = document.createElement('div');
                progressItem.className = 'article-progress-item';
                progressItem.innerHTML = `
                    <div class="article-progress-header">
                        <div class="article-title">記事 ${i}</div>
                        <div class="site-name-small">${site.name}</div>
                    </div>
                    <div class="article-progress-bar">
                        <div class="article-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="article-status">準備中</div>
                `;

                this.articleProgressList.appendChild(progressItem);
                this.articleProgressItems.set(itemId, {
                    element: progressItem,
                    progressFill: progressItem.querySelector('.article-progress-fill'),
                    status: progressItem.querySelector('.article-status')
                });
            }
        }
    }

    async createArticlesForSite(site, articleCount, onArticleComplete) {
        try {
            // サイトの記事作成方針を取得
            const policyResponse = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_site_policy',
                    site_id: site.id
                })
            });

            if (!policyResponse.ok) {
                throw new Error(`HTTPエラー: ${policyResponse.status}`);
            }

            const policyResult = await policyResponse.json();
            if (!policyResult.success) {
                const errorMsg = policyResult.error || `サイト ${site.name} の記事作成方針の取得に失敗しました`;
                console.error('サイトポリシー取得APIエラー:', policyResult);
                throw new Error(errorMsg);
            }

            // 記事概要を生成
            for (let i = 1; i <= articleCount; i++) {
                const itemId = `${site.id}-${i}`;
                const progressItem = this.articleProgressItems.get(itemId);
                
                try {
                    // 記事概要生成
                    progressItem.status.textContent = '記事概要生成中...';
                    progressItem.progressFill.style.width = '25%';
                    
                    const outlineResponse = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'create_article_outline',
                            site_id: site.id,
                            ai_model: document.getElementById('ai-model').value
                        })
                    });

                    if (!outlineResponse.ok) {
                        throw new Error(`HTTPエラー: ${outlineResponse.status}`);
                    }

                    const outlineResult = await outlineResponse.json();
                    if (!outlineResult.success) {
                        const errorMsg = outlineResult.error || '記事概要生成に失敗';
                        console.error('記事概要生成APIエラー:', outlineResult);
                        throw new Error(errorMsg);
                    }

                    // 記事配列とarticle_idの確認
                    if (!outlineResult.articles || !Array.isArray(outlineResult.articles) || outlineResult.articles.length === 0) {
                        console.error('記事概要生成結果に記事がありません:', outlineResult);
                        throw new Error('記事概要生成で記事が生成されませんでした');
                    }
                    
                    // 最初の記事のIDを取得
                    const articleId = outlineResult.articles[0].id;
                    if (!articleId) {
                        console.error('記事概要生成結果にarticle_idがありません:', outlineResult);
                        throw new Error('記事概要生成でarticle_idが取得できませんでした');
                    }

                    // 記事本文生成
                    progressItem.status.textContent = '記事本文生成中...';
                    progressItem.progressFill.style.width = '75%';

                    const aiModel = document.getElementById('ai-model').value;
                    if (!aiModel) {
                        throw new Error('AIモデルが選択されていません');
                    }

                    const articleResponse = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'generate_article',
                            article_id: articleId,
                            ai_model: aiModel
                        })
                    });

                    if (!articleResponse.ok) {
                        throw new Error(`HTTPエラー: ${articleResponse.status}`);
                    }

                    const articleResult = await articleResponse.json();
                    if (!articleResult.success) {
                        const errorMsg = articleResult.error || '記事生成に失敗';
                        console.error('記事生成APIエラー:', articleResult);
                        throw new Error(errorMsg);
                    }

                    // 完了
                    progressItem.status.textContent = '完了';
                    progressItem.progressFill.style.width = '100%';
                    progressItem.element.classList.add('status-completed');
                    
                    onArticleComplete();
                    
                    // 少し待機してから次の記事へ
                    await new Promise(resolve => setTimeout(resolve, 1000));

                } catch (error) {
                    progressItem.status.textContent = `エラー: ${error.message}`;
                    progressItem.element.classList.add('status-error');
                    console.error(`記事作成エラー (${itemId}):`, error);
                }
            }

        } catch (error) {
            console.error(`サイト ${site.name} での記事作成エラー:`, error);
            throw error;
        }
    }

    showProgress() {
        this.progressContainer.style.display = 'block';
        this.updateOverallProgress(0, '記事作成を開始します...');
    }

    updateOverallProgress(percentage, message) {
        this.progressFill.style.width = `${percentage}%`;
        this.progressText.textContent = message;
    }

    toggleProgressDetails() {
        const isHidden = this.progressDetails.style.display === 'none';
        this.progressDetails.style.display = isHidden ? 'block' : 'none';
        this.toggleDetailsBtn.textContent = isHidden ? '詳細非表示' : '詳細表示';
    }

    showErrorMessage(message) {
        alert(message); // 後でより良いUI表示に変更可能
        console.error(message);
    }
}

// アプリケーション初期化
document.addEventListener('DOMContentLoaded', () => {
    window.satelliteSystem = new SatelliteColumnSystem();
    window.multiSiteManager = new MultiSiteArticleManager();
});