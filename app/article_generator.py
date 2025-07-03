import requests
from bs4 import BeautifulSoup
import csv
import json
import pandas as pd
from datetime import datetime
import re
from urllib.parse import urljoin, urlparse
from .ai_engine import AIEngine
from .db import Database

class ArticleGenerator:
    def __init__(self, model_type='gpt-4'):
        self.ai_engine = AIEngine(model_type)
        self.db = Database()
        self.db.create_tables()
    
    def scrape_website(self, url):
        try:
            headers = {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            }
            response = requests.get(url, headers=headers, timeout=10)
            response.raise_for_status()
            
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # 不要なタグを除去
            for script in soup(["script", "style", "nav", "header", "footer"]):
                script.decompose()
            
            # テキストコンテンツを抽出
            text_content = soup.get_text()
            
            # 空白文字を正規化
            text_content = re.sub(r'\s+', ' ', text_content).strip()
            
            # 長すぎるコンテンツは切り詰める
            if len(text_content) > 5000:
                text_content = text_content[:5000] + "..."
            
            return text_content
        
        except Exception as e:
            print(f"サイトスクレイピングエラー ({url}): {e}")
            return None
    
    def analyze_sites(self, urls):
        analyses = []
        
        for url in urls:
            if not url.strip():
                continue
            
            print(f"分析中: {url}")
            
            # サイトコンテンツを取得
            content = self.scrape_website(url)
            if not content:
                continue
            
            # AI分析実行
            analysis = self.ai_engine.analyze_site_content(content)
            if analysis:
                # データベースに保存
                site_id = self.db.save_site_analysis(url, analysis)
                analyses.append({
                    'url': url,
                    'analysis': analysis,
                    'site_id': site_id
                })
        
        return analyses
    
    def generate_article_outlines(self, site_analysis, num_articles=100):
        try:
            outlines_text = self.ai_engine.generate_article_outlines(site_analysis, num_articles)
            if not outlines_text:
                return []
            
            # 生成されたテキストから記事概要を抽出
            outlines = self.parse_article_outlines(outlines_text)
            
            return outlines
        
        except Exception as e:
            print(f"記事概要生成エラー: {e}")
            return []
    
    def parse_article_outlines(self, outlines_text):
        outlines = []
        
        # 記事ごとに分割
        article_sections = re.split(r'## 記事\d+', outlines_text)[1:]  # 最初の空要素を除去
        
        for section in article_sections:
            try:
                # タイトル抽出
                title_match = re.search(r'\*\*タイトル\*\*:\s*(.+)', section)
                if not title_match:
                    continue
                title = title_match.group(1).strip()
                
                # キーワード抽出
                keywords_match = re.search(r'\*\*キーワード\*\*:\s*(.+)', section)
                keywords = keywords_match.group(1).strip() if keywords_match else ""
                
                # 概要抽出
                outline_match = re.search(r'\*\*概要\*\*:\s*(.+)', section)
                outline = outline_match.group(1).strip() if outline_match else ""
                
                outlines.append({
                    'title': title,
                    'keywords': keywords,
                    'outline': outline
                })
                
            except Exception as e:
                print(f"記事概要パースエラー: {e}")
                continue
        
        return outlines
    
    def generate_full_article(self, title, keywords, outline):
        try:
            article_content = self.ai_engine.generate_full_article(title, keywords, outline)
            return article_content
        
        except Exception as e:
            print(f"記事生成エラー: {e}")
            return None
    
    def save_article_outlines_to_db(self, site_analysis_id, outlines):
        saved_outlines = []
        
        for outline in outlines:
            outline_id = self.db.save_article_outline(
                site_analysis_id,
                outline['title'],
                outline['keywords'],
                outline['outline']
            )
            
            if outline_id:
                outline['id'] = outline_id
                saved_outlines.append(outline)
        
        return saved_outlines
    
    def save_article_to_db(self, outline_id, title, content):
        return self.db.save_article(outline_id, title, content)
    
    def export_to_csv(self, site_analysis_id, filename=None):
        if not filename:
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filename = f"data/articles_{timestamp}.csv"
        
        try:
            articles = self.db.get_all_articles_for_site(site_analysis_id)
            
            if not articles:
                print("エクスポートする記事がありません")
                return None
            
            # DataFrameを作成
            df = pd.DataFrame(articles)
            
            # CSVファイルに保存
            df.to_csv(filename, index=False, encoding='utf-8')
            
            return filename
        
        except Exception as e:
            print(f"CSV出力エラー: {e}")
            return None
    
    def get_site_analyses(self):
        return self.db.get_site_analyses()
    
    def get_article_outlines(self, site_analysis_id):
        return self.db.get_article_outlines(site_analysis_id)
    
    def get_articles(self, outline_id):
        return self.db.get_articles(outline_id)
    
    def get_all_articles_for_site(self, site_analysis_id):
        return self.db.get_all_articles_for_site(site_analysis_id)
    
    def set_ai_model(self, model_type):
        return self.ai_engine.set_model(model_type)
    
    def batch_generate_articles(self, site_analysis_id, outlines):
        generated_articles = []
        
        for outline in outlines:
            try:
                print(f"記事生成中: {outline['title']}")
                
                # 記事を生成
                article_content = self.generate_full_article(
                    outline['title'],
                    outline['keywords'],
                    outline['outline']
                )
                
                if article_content:
                    # データベースに保存
                    article_id = self.save_article_to_db(
                        outline['id'],
                        outline['title'],
                        article_content
                    )
                    
                    if article_id:
                        generated_articles.append({
                            'id': article_id,
                            'title': outline['title'],
                            'content': article_content,
                            'outline_id': outline['id']
                        })
                
            except Exception as e:
                print(f"記事生成エラー ({outline['title']}): {e}")
                continue
        
        return generated_articles