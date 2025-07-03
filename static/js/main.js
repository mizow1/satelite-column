// グローバル変数
let currentAnalyses = [];
let currentOutlines = [];
let currentSiteAnalysisId = null;

// ページ読み込み時の処理
document.addEventListener('DOMContentLoaded', function() {
    loadSiteAnalyses();
    
    // モデル選択の変更イベント
    document.getElementById('modelSelect').addEventListener('change', function() {
        const modelType = this.value;
        setAIModel(modelType);
    });
    
    // サイト選択の変更イベント
    document.getElementById('siteSelect').addEventListener('change', function() {
        const siteId = this.value;
        if (siteId) {
            loadSiteData(siteId);
        } else {
            clearAll();
        }
    });
});

// URL入力欄を追加
function addUrlInput() {
    const urlInputs = document.getElementById('urlInputs');
    const inputGroup = document.createElement('div');
    inputGroup.className = 'url-input-group';
    inputGroup.innerHTML = `
        <div class="input-group">
            <input type="url" class="form-control url-input" placeholder="https://example.com">
            <button class="btn btn-outline-secondary" type="button" onclick="removeUrlInput(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    urlInputs.appendChild(inputGroup);
}

// URL入力欄を削除
function removeUrlInput(button) {
    const inputGroup = button.closest('.url-input-group');
    inputGroup.remove();
}

// サイト分析を実行
async function analyzeSites() {
    const urlInputs = document.querySelectorAll('.url-input');
    const urls = Array.from(urlInputs).map(input => input.value.trim()).filter(url => url);
    
    if (urls.length === 0) {
        alert('URLを入力してください');
        return;
    }
    
    const modelType = document.getElementById('modelSelect').value;
    
    showLoading('loadingAnalysis');
    hideSection('analysisResults');
    
    try {
        const response = await fetch('/api/analyze-sites', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                urls: urls,
                model_type: modelType
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentAnalyses = data.analyses;
            displayAnalysisResults(data.analyses);
            loadSiteAnalyses(); // 選択肢を更新
        } else {
            alert('分析に失敗しました: ' + data.error);
        }
    } catch (error) {
        alert('エラーが発生しました: ' + error.message);
    } finally {
        hideLoading('loadingAnalysis');
    }
}

// 分析結果を表示
function displayAnalysisResults(analyses) {
    const analysisContent = document.getElementById('analysisContent');
    analysisContent.innerHTML = '';
    
    analyses.forEach((analysis, index) => {
        const analysisDiv = document.createElement('div');
        analysisDiv.className = 'site-analysis';
        analysisDiv.innerHTML = `
            <h5><i class="fas fa-globe"></i> ${analysis.url}</h5>
            <div class="analysis-content">${analysis.analysis_html}</div>
        `;
        analysisContent.appendChild(analysisDiv);
        
        // 最初のサイトのIDを保存
        if (index === 0) {
            currentSiteAnalysisId = analysis.site_id;
        }
    });
    
    showSection('analysisResults');
}

// 記事概要を生成
async function generateOutlines() {
    if (!currentAnalyses || currentAnalyses.length === 0) {
        alert('まずサイト分析を実行してください');
        return;
    }
    
    const siteAnalysisText = currentAnalyses.map(a => a.analysis).join('\n\n');
    
    showLoading('loadingOutlines');
    hideSection('outlinesSection');
    
    try {
        const response = await fetch('/api/generate-outlines', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                site_analysis_id: currentSiteAnalysisId,
                site_analysis_text: siteAnalysisText,
                num_articles: 100
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentOutlines = data.outlines;
            displayOutlines(data.outlines);
        } else {
            alert('記事概要生成に失敗しました: ' + data.error);
        }
    } catch (error) {
        alert('エラーが発生しました: ' + error.message);
    } finally {
        hideLoading('loadingOutlines');
    }
}

// 記事概要を表示
function displayOutlines(outlines) {
    const outlinesContent = document.getElementById('outlinesContent');
    outlinesContent.innerHTML = '';
    
    outlines.forEach((outline, index) => {
        const outlineDiv = document.createElement('div');
        outlineDiv.className = 'article-outline';
        outlineDiv.innerHTML = `
            <h6><strong>記事${index + 1}</strong></h6>
            <p><strong>タイトル:</strong> ${outline.title}</p>
            <p><strong>キーワード:</strong> ${outline.keywords}</p>
            <p><strong>概要:</strong> ${outline.outline}</p>
            <button class="btn btn-sm btn-primary btn-generate" onclick="generateSingleArticle(${index})">
                <i class="fas fa-edit"></i> 記事作成
            </button>
            <div id="articleContent_${index}" class="article-content" style="display: none;"></div>
        `;
        outlinesContent.appendChild(outlineDiv);
    });
    
    showSection('outlinesSection');
}

// 単一記事を生成
async function generateSingleArticle(index) {
    const outline = currentOutlines[index];
    
    showLoading('loadingArticle');
    
    try {
        const response = await fetch('/api/generate-article', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                outline_id: outline.id,
                title: outline.title,
                keywords: outline.keywords,
                outline: outline.outline
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            displaySingleArticle(index, data.article);
        } else {
            alert('記事生成に失敗しました: ' + data.error);
        }
    } catch (error) {
        alert('エラーが発生しました: ' + error.message);
    } finally {
        hideLoading('loadingArticle');
    }
}

// 単一記事を表示
function displaySingleArticle(index, article) {
    const articleContent = document.getElementById(`articleContent_${index}`);
    articleContent.innerHTML = `
        <h6><a href="#" onclick="showArticleModal('${article.title}', '${article.content_html}')">${article.title}</a></h6>
        <p><em>クリックして全文を表示</em></p>
    `;
    articleContent.style.display = 'block';
}

// 一括記事生成
async function batchGenerateArticles() {
    if (!currentOutlines || currentOutlines.length === 0) {
        alert('まず記事概要を生成してください');
        return;
    }
    
    showLoading('loadingBatch');
    hideSection('articlesSection');
    
    try {
        const response = await fetch('/api/batch-generate-articles', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                site_analysis_id: currentSiteAnalysisId,
                outlines: currentOutlines
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayBatchArticles(data.articles);
        } else {
            alert('一括記事生成に失敗しました: ' + data.error);
        }
    } catch (error) {
        alert('エラーが発生しました: ' + error.message);
    } finally {
        hideLoading('loadingBatch');
    }
}

// 一括生成記事を表示
function displayBatchArticles(articles) {
    const articlesContent = document.getElementById('articlesContent');
    articlesContent.innerHTML = '';
    
    articles.forEach((article, index) => {
        const articleDiv = document.createElement('div');
        articleDiv.className = 'article-outline';
        articleDiv.innerHTML = `
            <h6><a href="#" onclick="showArticleModal('${article.title}', '${article.content_html}')">${article.title}</a></h6>
            <p><em>クリックして全文を表示</em></p>
        `;
        articlesContent.appendChild(articleDiv);
    });
    
    showSection('articlesSection');
}

// 記事モーダルを表示
function showArticleModal(title, contentHtml) {
    document.getElementById('articleModalTitle').textContent = title;
    document.getElementById('articleModalContent').innerHTML = contentHtml;
    
    const modal = new bootstrap.Modal(document.getElementById('articleModal'));
    modal.show();
}

// CSV出力
async function exportCSV() {
    if (!currentSiteAnalysisId) {
        alert('まずサイト分析を実行してください');
        return;
    }
    
    try {
        const response = await fetch('/api/export-csv', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                site_analysis_id: currentSiteAnalysisId
            })
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'articles.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        } else {
            alert('CSV出力に失敗しました');
        }
    } catch (error) {
        alert('エラーが発生しました: ' + error.message);
    }
}

// 過去のサイト分析を読み込み
async function loadSiteAnalyses() {
    try {
        const response = await fetch('/api/get-site-analyses');
        const data = await response.json();
        
        if (data.success) {
            const siteSelect = document.getElementById('siteSelect');
            siteSelect.innerHTML = '<option value="">新しい分析を開始</option>';
            
            data.analyses.forEach(analysis => {
                const option = document.createElement('option');
                option.value = analysis.id;
                option.textContent = `${analysis.site_url} (${formatDate(analysis.created_at)})`;
                siteSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('サイト分析データの読み込みに失敗しました:', error);
    }
}

// 過去のサイトデータを読み込み
async function loadSiteData(siteId) {
    try {
        currentSiteAnalysisId = siteId;
        
        // 記事概要を取得
        const outlinesResponse = await fetch(`/api/get-article-outlines/${siteId}`);
        const outlinesData = await outlinesResponse.json();
        
        if (outlinesData.success) {
            currentOutlines = outlinesData.outlines;
            displayOutlines(outlinesData.outlines);
        }
        
        // 記事を取得
        const articlesResponse = await fetch(`/api/get-all-articles/${siteId}`);
        const articlesData = await articlesResponse.json();
        
        if (articlesData.success && articlesData.articles.length > 0) {
            displayBatchArticles(articlesData.articles);
        }
        
    } catch (error) {
        console.error('サイトデータの読み込みに失敗しました:', error);
    }
}

// AIモデルを設定
async function setAIModel(modelType) {
    try {
        const response = await fetch('/api/set-model', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                model_type: modelType
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            alert('モデル設定に失敗しました: ' + data.error);
        }
    } catch (error) {
        console.error('モデル設定エラー:', error);
    }
}

// ユーティリティ関数
function showLoading(elementId) {
    document.getElementById(elementId).style.display = 'block';
}

function hideLoading(elementId) {
    document.getElementById(elementId).style.display = 'none';
}

function showSection(elementId) {
    document.getElementById(elementId).style.display = 'block';
}

function hideSection(elementId) {
    document.getElementById(elementId).style.display = 'none';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP') + ' ' + date.toLocaleTimeString('ja-JP');
}

function clearAll() {
    currentAnalyses = [];
    currentOutlines = [];
    currentSiteAnalysisId = null;
    
    hideSection('analysisResults');
    hideSection('outlinesSection');
    hideSection('articlesSection');
    
    document.getElementById('analysisContent').innerHTML = '';
    document.getElementById('outlinesContent').innerHTML = '';
    document.getElementById('articlesContent').innerHTML = '';
}