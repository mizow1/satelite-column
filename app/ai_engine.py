import os
import openai
import anthropic
import google.generativeai as genai
from dotenv import load_dotenv

load_dotenv()

class AIEngine:
    def __init__(self, model_type='gpt-4'):
        self.model_type = model_type
        self.setup_clients()
    
    def setup_clients(self):
        # OpenAI API設定
        self.openai_client = openai.OpenAI(
            api_key=os.getenv('OPENAI_API_KEY')
        )
        
        # Anthropic API設定
        self.anthropic_client = anthropic.Anthropic(
            api_key=os.getenv('ANTHROPIC_API_KEY')
        )
        
        # Google Generative AI設定
        genai.configure(api_key=os.getenv('GOOGLE_API_KEY'))
        self.gemini_model = genai.GenerativeModel('gemini-2.0-flash-exp')
    
    def generate_text(self, prompt, max_tokens=2000):
        try:
            if self.model_type == 'gpt-4':
                return self._generate_with_openai(prompt, max_tokens)
            elif self.model_type == 'claude-4':
                return self._generate_with_anthropic(prompt, max_tokens)
            elif self.model_type == 'gemini-2.0':
                return self._generate_with_gemini(prompt, max_tokens)
            else:
                raise ValueError(f"サポートされていないモデル: {self.model_type}")
        except Exception as e:
            print(f"テキスト生成エラー: {e}")
            return None
    
    def _generate_with_openai(self, prompt, max_tokens):
        response = self.openai_client.chat.completions.create(
            model="gpt-4",
            messages=[
                {"role": "user", "content": prompt}
            ],
            max_tokens=max_tokens,
            temperature=0.7
        )
        return response.choices[0].message.content
    
    def _generate_with_anthropic(self, prompt, max_tokens):
        response = self.anthropic_client.messages.create(
            model="claude-3-sonnet-20240229",
            max_tokens=max_tokens,
            messages=[
                {"role": "user", "content": prompt}
            ],
            temperature=0.7
        )
        return response.content[0].text
    
    def _generate_with_gemini(self, prompt, max_tokens):
        response = self.gemini_model.generate_content(
            prompt,
            generation_config=genai.types.GenerationConfig(
                max_output_tokens=max_tokens,
                temperature=0.7
            )
        )
        return response.text
    
    def analyze_site_content(self, site_content):
        prompt = f"""
        以下のWebサイトのコンテンツを分析して、占い好きなユーザーに響く要素を抽出してください。
        SEOを意識したキーワード、語彙、コンテンツ傾向をMarkdown形式で出力してください。
        
        サイトコンテンツ:
        {site_content}
        
        分析結果をMarkdown形式で以下の構造で出力してください：
        
        # サイト分析結果
        
        ## 占い関連キーワード
        - キーワード1
        - キーワード2
        ...
        
        ## 語彙・文体の特徴
        - 特徴1
        - 特徴2
        ...
        
        ## コンテンツ傾向
        - 傾向1
        - 傾向2
        ...
        
        ## SEO強化ポイント
        - ポイント1
        - ポイント2
        ...
        """
        
        return self.generate_text(prompt, max_tokens=3000)
    
    def generate_article_outlines(self, site_analysis, num_articles=100):
        prompt = f"""
        以下のサイト分析結果を基に、占い関連の記事概要を{num_articles}件作成してください。
        SEOに適した以下の要素を含む記事概要を生成してください：
        
        サイト分析結果:
        {site_analysis}
        
        各記事概要は以下の形式で出力してください：
        
        ## 記事{'{'}i{'}'}
        **タイトル**: [魅力的でSEOに強いタイトル]
        **キーワード**: [主要キーワード1], [主要キーワード2], [主要キーワード3]
        **概要**: [記事の概要説明（100-150文字）]
        
        ---
        
        占い、運勢、恋愛運、金運、仕事運、健康運などの要素を含む多様な記事を作成してください。
        """
        
        return self.generate_text(prompt, max_tokens=4000)
    
    def generate_full_article(self, title, keywords, outline):
        prompt = f"""
        以下の情報を基に、占い関連の記事を作成してください。
        
        タイトル: {title}
        キーワード: {keywords}
        概要: {outline}
        
        記事の要件：
        - 2000-3000文字程度の詳細な記事
        - SEOを意識したキーワードの適切な配置
        - 占い好きなユーザーに響く内容
        - 読みやすい構成（見出し、段落分け）
        - 実用的なアドバイスや情報を含む
        
        記事本文をMarkdown形式で作成してください。
        """
        
        return self.generate_text(prompt, max_tokens=4000)
    
    def set_model(self, model_type):
        if model_type in ['gpt-4', 'claude-4', 'gemini-2.0']:
            self.model_type = model_type
            return True
        return False