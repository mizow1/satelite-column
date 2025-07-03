import pymysql
import os
from dotenv import load_dotenv
from datetime import datetime

load_dotenv()

class Database:
    def __init__(self):
        self.host = os.getenv('DB_HOST', 'localhost')
        self.user = os.getenv('DB_USER', 'root')
        self.password = os.getenv('DB_PASSWORD', '')
        self.database = os.getenv('DB_NAME', 'fortune_articles')
        self.connection = None
    
    def connect(self):
        try:
            self.connection = pymysql.connect(
                host=self.host,
                user=self.user,
                password=self.password,
                database=self.database,
                charset='utf8mb4',
                cursorclass=pymysql.cursors.DictCursor
            )
            return True
        except Exception as e:
            print(f"データベース接続エラー: {e}")
            return False
    
    def disconnect(self):
        if self.connection:
            self.connection.close()
            self.connection = None
    
    def create_tables(self):
        if not self.connection:
            if not self.connect():
                return False
        
        cursor = self.connection.cursor()
        
        # サイト分析テーブル
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS site_analysis (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_url VARCHAR(500) NOT NULL,
                analysis_data TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        
        # 記事概要テーブル
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS article_outlines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_analysis_id INT,
                title VARCHAR(255) NOT NULL,
                keywords TEXT,
                outline TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (site_analysis_id) REFERENCES site_analysis(id)
            )
        ''')
        
        # 記事本文テーブル
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS articles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                outline_id INT,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (outline_id) REFERENCES article_outlines(id)
            )
        ''')
        
        self.connection.commit()
        cursor.close()
        return True
    
    def save_site_analysis(self, site_url, analysis_data):
        if not self.connection:
            if not self.connect():
                return None
        
        cursor = self.connection.cursor()
        query = "INSERT INTO site_analysis (site_url, analysis_data) VALUES (%s, %s)"
        cursor.execute(query, (site_url, analysis_data))
        self.connection.commit()
        
        site_id = cursor.lastrowid
        cursor.close()
        return site_id
    
    def save_article_outline(self, site_analysis_id, title, keywords, outline):
        if not self.connection:
            if not self.connect():
                return None
        
        cursor = self.connection.cursor()
        query = "INSERT INTO article_outlines (site_analysis_id, title, keywords, outline) VALUES (%s, %s, %s, %s)"
        cursor.execute(query, (site_analysis_id, title, keywords, outline))
        self.connection.commit()
        
        outline_id = cursor.lastrowid
        cursor.close()
        return outline_id
    
    def save_article(self, outline_id, title, content):
        if not self.connection:
            if not self.connect():
                return None
        
        cursor = self.connection.cursor()
        query = "INSERT INTO articles (outline_id, title, content) VALUES (%s, %s, %s)"
        cursor.execute(query, (outline_id, title, content))
        self.connection.commit()
        
        article_id = cursor.lastrowid
        cursor.close()
        return article_id
    
    def get_site_analyses(self):
        if not self.connection:
            if not self.connect():
                return []
        
        cursor = self.connection.cursor()
        query = "SELECT * FROM site_analysis ORDER BY created_at DESC"
        cursor.execute(query)
        results = cursor.fetchall()
        cursor.close()
        return results
    
    def get_article_outlines(self, site_analysis_id):
        if not self.connection:
            if not self.connect():
                return []
        
        cursor = self.connection.cursor()
        query = "SELECT * FROM article_outlines WHERE site_analysis_id = %s ORDER BY created_at DESC"
        cursor.execute(query, (site_analysis_id,))
        results = cursor.fetchall()
        cursor.close()
        return results
    
    def get_articles(self, outline_id):
        if not self.connection:
            if not self.connect():
                return []
        
        cursor = self.connection.cursor()
        query = "SELECT * FROM articles WHERE outline_id = %s ORDER BY created_at DESC"
        cursor.execute(query, (outline_id,))
        results = cursor.fetchall()
        cursor.close()
        return results
    
    def get_all_articles_for_site(self, site_analysis_id):
        if not self.connection:
            if not self.connect():
                return []
        
        cursor = self.connection.cursor()
        query = '''
            SELECT a.*, ao.title as outline_title, ao.keywords, ao.outline
            FROM articles a
            JOIN article_outlines ao ON a.outline_id = ao.id
            WHERE ao.site_analysis_id = %s
            ORDER BY a.created_at DESC
        '''
        cursor.execute(query, (site_analysis_id,))
        results = cursor.fetchall()
        cursor.close()
        return results