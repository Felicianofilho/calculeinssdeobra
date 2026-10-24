<?php
require_once __DIR__ . '/Database.php';

class Auth
{
    public static function login($email, $senha)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($senha, $user['senha_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'] ?? $user['tipo']; // Usar user_type se disponível, senão tipo
            $_SESSION['user_nome'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            return true;
        }
        return false;
    }

    public static function logout()
    {
        session_unset();
        session_destroy();
    }

    public static function check()
    {
        return isset($_SESSION['user_id']);
    }

    public static function user()
    {
        if (!self::check()) return null;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }

    public static function isAdmin()
    {
        return (isset($_SESSION['user_type']) && ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'superadmin'));
    }

    public static function isSuperAdmin()
    {
        return (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'superadmin');
    }

    public static function canAccessAdminPanel()
    {
        return self::isAdmin();
    }

    public static function isAdminUser()
    {
        return (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin');
    }

    public static function isClient()
    {
        return (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'client');
    }

    public static function isCliente()
    {
        return self::isClient();
    }

    public static function isLead()
    {
        return (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'lead');
    }

    public static function getUserType()
    {
        return $_SESSION['user_type'] ?? 'lead';
    }

    public static function canAccessPlanningScreen()
    {
        // Permitir clientes, admins, superadmins e leads acessarem a tela de planejamento
        return self::isCliente() || self::isAdminUser() || self::isSuperAdmin() || self::isLead();
    }

    public static function canManageUsers()
    {
        // Apenas admins e superadmins podem gerenciar usuários
        return self::isAdminUser() || self::isSuperAdmin();
    }

    public static function canPromoteUsers()
    {
        // Apenas superadmins podem promover/rebaixar usuários
        return self::isSuperAdmin();
    }

    /**
     * Gera um token CSRF
     */
    public static function generateCSRFToken()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida um token CSRF
     */
    public static function validateCSRFToken($token)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Verifica se a requisição é POST e valida o CSRF
     */
    public static function validateCSRFRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
            if (!$token || !self::validateCSRFToken($token)) {
                http_response_code(403);
                die('CSRF token inválido ou ausente');
            }
        }
    }

    /**
     * Verifica rate limiting para login
     */
    public static function checkLoginRateLimit($email, $maxAttempts = 5, $timeWindow = 300)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "login_attempts_{$ip}_{$email}";
        
        $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => time()];
        
        // Reset se passou o tempo
        if (time() - $attempts['first_attempt'] > $timeWindow) {
            $attempts = ['count' => 0, 'first_attempt' => time()];
        }
        
        // Incrementar tentativa
        $attempts['count']++;
        $_SESSION[$key] = $attempts;
        
        // Log da tentativa
        error_log("Login attempt from IP: $ip, Email: $email, Attempt: {$attempts['count']}");
        
        return $attempts['count'] <= $maxAttempts;
    }
    
    /**
     * Reseta rate limiting para um email/IP
     */
    public static function resetLoginRateLimit($email)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "login_attempts_{$ip}_{$email}";
        
        unset($_SESSION[$key]);
    }
}