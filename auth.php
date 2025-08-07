<?php
// 直接アクセス防止
if (!defined('INCLUDED_FROM_API')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

require_once 'config.php';

class AuthService {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
    }
    
    /**
     * ユーザー登録
     */
    public function register($email, $password) {
        // バリデーション
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('有効なメールアドレスを入力してください。');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('パスワードは6文字以上で入力してください。');
        }
        
        // 既存ユーザーチェック
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('このメールアドレスは既に登録されています。');
        }
        
        // パスワードハッシュ化
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // ユーザー作成
        $stmt = $this->pdo->prepare("
            INSERT INTO users (email, password, created_at, updated_at) 
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$email, $hashedPassword]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * ログイン認証
     */
    public function login($email, $password) {
        $stmt = $this->pdo->prepare("
            SELECT id, email, password, name, created_at 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('メールアドレスまたはパスワードが正しくありません。');
        }
        
        // ログイン情報を更新
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'created_at' => $user['created_at']
        ];
    }
    
    /**
     * セッション開始
     */
    public function startSession($userId) {
        session_start();
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_time'] = time();
        
        // セッションIDを再生成（セキュリティ対策）
        session_regenerate_id(true);
        
        return session_id();
    }
    
    /**
     * セッション確認
     */
    public function checkSession() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // セッションタイムアウト確認（24時間）
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 86400) {
            $this->logout();
            return false;
        }
        
        return $_SESSION['user_id'];
    }
    
    /**
     * ログアウト
     */
    public function logout() {
        session_start();
        session_destroy();
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-42000, '/');
        }
    }
    
    /**
     * ユーザー情報取得
     */
    public function getUser($userId) {
        $stmt = $this->pdo->prepare("
            SELECT id, email, created_at, last_login 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch();
    }
    
    /**
     * パスワード変更
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        // 現在のパスワード確認
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            throw new Exception('現在のパスワードが正しくありません。');
        }
        
        if (strlen($newPassword) < 6) {
            throw new Exception('新しいパスワードは6文字以上で入力してください。');
        }
        
        // パスワード更新
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        return true;
    }
    
    /**
     * プロフィール更新
     */
    public function updateProfile($userId, $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('有効なメールアドレスを入力してください。');
        }
        
        // 他のユーザーが同じメールアドレスを使用していないかチェック
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            throw new Exception('このメールアドレスは既に他のユーザーによって使用されています。');
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET email = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$email, $userId]);
        
        return true;
    }
}
?>