from flask import Flask, render_template, request, jsonify, send_file
from flask_cors import CORS
import os
import json
from .article_generator import ArticleGenerator
import markdown

app = Flask(__name__, template_folder='../templates', static_folder='../static')
CORS(app)

# グローバルな記事生成器インスタンス
article_generator = ArticleGenerator()

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/analyze-sites', methods=['POST'])
def analyze_sites():
    try:
        data = request.get_json()
        urls = data.get('urls', [])
        model_type = data.get('model_type', 'gpt-4')
        
        # AIモデルを設定
        article_generator.set_ai_model(model_type)
        
        # サイト分析を実行
        analyses = article_generator.analyze_sites(urls)
        
        if not analyses:
            return jsonify({'error': 'サイトの分析に失敗しました'}), 400
        
        # MarkdownをHTMLに変換
        for analysis in analyses:
            analysis['analysis_html'] = markdown.markdown(analysis['analysis'])
        
        return jsonify({
            'success': True,
            'analyses': analyses
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/generate-outlines', methods=['POST'])
def generate_outlines():
    try:
        data = request.get_json()
        site_analysis_id = data.get('site_analysis_id')
        site_analysis_text = data.get('site_analysis_text')
        num_articles = data.get('num_articles', 100)
        
        # 記事概要を生成
        outlines = article_generator.generate_article_outlines(site_analysis_text, num_articles)
        
        if not outlines:
            return jsonify({'error': '記事概要の生成に失敗しました'}), 400
        
        # データベースに保存
        saved_outlines = article_generator.save_article_outlines_to_db(site_analysis_id, outlines)
        
        return jsonify({
            'success': True,
            'outlines': saved_outlines
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/generate-article', methods=['POST'])
def generate_article():
    try:
        data = request.get_json()
        outline_id = data.get('outline_id')
        title = data.get('title')
        keywords = data.get('keywords')
        outline = data.get('outline')
        
        # 記事を生成
        article_content = article_generator.generate_full_article(title, keywords, outline)
        
        if not article_content:
            return jsonify({'error': '記事の生成に失敗しました'}), 400
        
        # データベースに保存
        article_id = article_generator.save_article_to_db(outline_id, title, article_content)
        
        if not article_id:
            return jsonify({'error': '記事の保存に失敗しました'}), 400
        
        # MarkdownをHTMLに変換
        article_html = markdown.markdown(article_content)
        
        return jsonify({
            'success': True,
            'article': {
                'id': article_id,
                'title': title,
                'content': article_content,
                'content_html': article_html
            }
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/batch-generate-articles', methods=['POST'])
def batch_generate_articles():
    try:
        data = request.get_json()
        site_analysis_id = data.get('site_analysis_id')
        outlines = data.get('outlines', [])
        
        # 一括記事生成
        generated_articles = article_generator.batch_generate_articles(site_analysis_id, outlines)
        
        # MarkdownをHTMLに変換
        for article in generated_articles:
            article['content_html'] = markdown.markdown(article['content'])
        
        return jsonify({
            'success': True,
            'articles': generated_articles
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/export-csv', methods=['POST'])
def export_csv():
    try:
        data = request.get_json()
        site_analysis_id = data.get('site_analysis_id')
        
        # CSVファイルを生成
        csv_filename = article_generator.export_to_csv(site_analysis_id)
        
        if not csv_filename:
            return jsonify({'error': 'CSVファイルの生成に失敗しました'}), 400
        
        return send_file(csv_filename, as_attachment=True, download_name='articles.csv')
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/get-site-analyses', methods=['GET'])
def get_site_analyses():
    try:
        analyses = article_generator.get_site_analyses()
        return jsonify({
            'success': True,
            'analyses': analyses
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/get-article-outlines/<int:site_analysis_id>', methods=['GET'])
def get_article_outlines(site_analysis_id):
    try:
        outlines = article_generator.get_article_outlines(site_analysis_id)
        return jsonify({
            'success': True,
            'outlines': outlines
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/get-articles/<int:outline_id>', methods=['GET'])
def get_articles(outline_id):
    try:
        articles = article_generator.get_articles(outline_id)
        
        # MarkdownをHTMLに変換
        for article in articles:
            article['content_html'] = markdown.markdown(article['content'])
        
        return jsonify({
            'success': True,
            'articles': articles
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/get-all-articles/<int:site_analysis_id>', methods=['GET'])
def get_all_articles(site_analysis_id):
    try:
        articles = article_generator.get_all_articles_for_site(site_analysis_id)
        
        # MarkdownをHTMLに変換
        for article in articles:
            article['content_html'] = markdown.markdown(article['content'])
        
        return jsonify({
            'success': True,
            'articles': articles
        })
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/set-model', methods=['POST'])
def set_model():
    try:
        data = request.get_json()
        model_type = data.get('model_type')
        
        success = article_generator.set_ai_model(model_type)
        
        if success:
            return jsonify({'success': True, 'model_type': model_type})
        else:
            return jsonify({'error': 'サポートされていないモデルです'}), 400
    
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)