<?php
/**
 * Configurações de Segurança
 * Este arquivo implementa headers de segurança e outras medidas de proteção
 */

class SecurityHeaders {
    
    /**
     * Detecta se está rodando em localhost
     */
    public static function isLocalhost() {
        $localhost_ips = ['127.0.0.1', '::1', 'localhost'];
        $server_name = $_SERVER['SERVER_NAME'] ?? '';
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        $http_host = $_SERVER['HTTP_HOST'] ?? '';
        
        return in_array($server_name, $localhost_ips) || 
               in_array($remote_addr, $localhost_ips) ||
               in_array($http_host, $localhost_ips) ||
               strpos($server_name, 'localhost') !== false ||
               strpos($http_host, 'localhost') !== false ||
               strpos($server_name, '127.0.0.1') !== false ||
               strpos($http_host, '127.0.0.1') !== false;
    }
    
    /**
     * Detecta se a conexão é HTTPS (considerando proxies como Cloudflare)
     */
    public static function isHttps() {
        // Verificação padrão HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        
        // Porta 443 (HTTPS)
        if ($_SERVER['SERVER_PORT'] == 443) {
            return true;
        }
        
        // Headers de proxy (Cloudflare, Load Balancers, etc.)
        $proxy_headers = [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_SSL' => 'on',
            'HTTP_X_FORWARDED_SCHEME' => 'https',
            'HTTP_CF_VISITOR' => '"scheme":"https"',  // Cloudflare específico
            'HTTP_X_PROTO' => 'https',
            'HTTP_X_SCHEME' => 'https'
        ];
        
        foreach ($proxy_headers as $header => $value) {
            if (!empty($_SERVER[$header])) {
                if ($header === 'HTTP_CF_VISITOR') {
                    // Cloudflare envia JSON: {"scheme":"https"}
                    if (strpos($_SERVER[$header], $value) !== false) {
                        return true;
                    }
                } else {
                    if (strtolower($_SERVER[$header]) === $value) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Força redirecionamento para HTTPS (apenas em produção e se necessário)
     */
    public static function forceHttps() {
        // Não redirecionar se for localhost
        if (self::isLocalhost()) {
            return;
        }
        
        // Não redirecionar se já for HTTPS
        if (self::isHttps()) {
            return;
        }
        
        // Verificar se não estamos em um loop de redirecionamento
        $redirect_count = $_SERVER['HTTP_X_REDIRECT_COUNT'] ?? 0;
        if ($redirect_count > 3) {
            error_log("Possível loop de redirecionamento detectado. Parando redirecionamento HTTPS.");
            return;
        }
        
        // Construir URL HTTPS
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (!empty($host)) {
            $redirect_url = 'https://' . $host . $uri;
            
            // Log do redirecionamento para debug
            error_log("Redirecionando HTTP para HTTPS: " . $_SERVER['REQUEST_URI'] . " -> " . $redirect_url);
            
            // Adicionar header para rastrear redirecionamentos
            header('X-Redirect-Count: ' . ($redirect_count + 1));
            header('Location: ' . $redirect_url, true, 301);
            exit();
        }
    }
    
    /**
     * Define headers de segurança
     */
    public static function setSecurityHeaders() {
        // Headers básicos de segurança
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // HSTS apenas em HTTPS e não em localhost
        if (self::isHttps() && !self::isLocalhost()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https: blob:; " .
               "connect-src 'self'; " .
               "frame-src 'none'; " .
               "object-src 'none'; " .
               "base-uri 'self'";
        
        header('Content-Security-Policy: ' . $csp);
        
        // Ocultar informações do servidor (se possível via PHP)
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
            header_remove('Server');
        }
    }
    
    /**
     * Sanitiza entrada de dados
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Valida email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Gera token CSRF
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida token CSRF
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Implementa rate limiting básico
     */
    public static function rateLimit($action, $max_attempts = 5, $time_window = 300) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = $action . '_' . $ip;
        
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        
        // Limpar tentativas antigas
        if (isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = array_filter(
                $_SESSION['rate_limit'][$key],
                function($timestamp) use ($now, $time_window) {
                    return ($now - $timestamp) < $time_window;
                }
            );
        } else {
            $_SESSION['rate_limit'][$key] = [];
        }
        
        // Verificar se excedeu o limite
        if (count($_SESSION['rate_limit'][$key]) >= $max_attempts) {
            return false;
        }
        
        // Registrar tentativa
        $_SESSION['rate_limit'][$key][] = $now;
        return true;
    }
    
    /**
     * Inicializa todas as medidas de segurança
     */
    public static function init() {
        // Força HTTPS em produção
        self::forceHttps();
        
        // Define headers de segurança
        self::setSecurityHeaders();
        
        // Configurações de sessão seguras
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Configurar cookies de sessão seguros
            $secure = self::isHttps();
            $httponly = true;
            $samesite = 'Strict';
            
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $secure,
                    'httponly' => $httponly,
                    'samesite' => $samesite
                ]);
            } else {
                session_set_cookie_params(0, '/', '', $secure, $httponly);
            }
        }
    }
}

// Inicializar segurança automaticamente quando o arquivo for incluído
SecurityHeaders::init();
?>

