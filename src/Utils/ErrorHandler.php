<?php

class ErrorHandler
{
    /**
     * Configura os manipuladores de erro personalizados
     */
    public static function initialize()
    {
        // Definir manipulador de erro personalizado
        set_error_handler([self::class, 'handleError']);
        
        // Definir manipulador de exceção personalizada
        set_exception_handler([self::class, 'handleException']);
        
        // Registrar função de desligamento para capturar erros fatais
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * Manipula erros PHP
     */
    public static function handleError($severity, $message, $file, $line)
    {
        // Não processar se error_reporting estiver desligado
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        // Log do erro (apenas se não for em produção)
        if (!self::isProduction()) {
            error_log("PHP Error: [{$severity}] {$message} in {$file} on line {$line}");
        }
        
        // Para erros críticos, mostrar página de erro
        if ($severity & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            self::show404Page();
        }
        
        return true;
    }
    
    /**
     * Manipula exceções não capturadas
     */
    public static function handleException($exception)
    {
        // Log da exceção (apenas se não for em produção)
        if (!self::isProduction()) {
            error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
        }
        
        self::show404Page();
    }
    
    /**
     * Manipula erros fatais durante o desligamento
     */
    public static function handleShutdown()
    {
        $error = error_get_last();
        
        if ($error && $error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
            // Log do erro fatal (apenas se não for em produção)
            if (!self::isProduction()) {
                error_log("Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");
            }
            
            self::show404Page();
        }
    }
    
    /**
     * Exibe a página 404 personalizada
     */
    public static function show404Page()
    {
        // Evitar loop infinito
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '404.html') !== false) {
            return;
        }
        
        // Definir cabeçalho 404
        if (!headers_sent()) {
            http_response_code(404);
            header('HTTP/1.1 404 Not Found');
        }
        
        // Caminho para a página 404
        $errorPagePath = __DIR__ . '/../../404.html';
        
        // Verificar se a página 404 existe
        if (file_exists($errorPagePath)) {
            // Limpar qualquer saída anterior
            if (ob_get_level()) {
                ob_clean();
            }
            
            // Incluir a página 404
            include $errorPagePath;
        } else {
            // Fallback caso o arquivo 404.html não exista
            echo self::getBasic404Html();
        }
        
        exit;
    }
    
    /**
     * Verifica se estamos em ambiente de produção
     */
    public static function isProduction()
    {
        return (defined('ENVIRONMENT') && ENVIRONMENT === 'production') || 
               (!defined('ENVIRONMENT') && $_SERVER['HTTP_HOST'] !== 'localhost');
    }
    
    /**
     * HTML básico de 404 como fallback
     */
    private static function getBasic404Html()
    {
        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página Não Encontrada</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            padding: 50px;
            background: #f5f5f5;
        }
        .error-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        h1 { color: #e74c3c; font-size: 72px; margin: 0; }
        h2 { color: #2c3e50; margin: 20px 0; }
        p { color: #7f8c8d; line-height: 1.6; }
        a { 
            color: #3498db; 
            text-decoration: none; 
            padding: 10px 20px; 
            background: #ecf0f1; 
            border-radius: 5px; 
            display: inline-block; 
            margin-top: 20px; 
        }
        a:hover { background: #bdc3c7; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <h2>Página Não Encontrada</h2>
        <p>A página que você está procurando não existe ou foi movida.</p>
        <a href="/">Voltar à Página Inicial</a>
    </div>
</body>
</html>';
    }
    
    /**
     * Verifica se uma página/recurso existe no sistema
     */
    public static function checkPageExists($path)
    {
        // Lista de páginas válidas no diretório public
        $validPages = [
            'index.php', 'login.php', 'register.php', 'dashboard.php',
            'nova_simulacao.php', 'minhas_simulacoes.php', 'planejamento_tributario.php',
            'meus_planejamentos.php', 'relatorios.php', 'perfil.php',
            'admin.php', 'admin_settings.php', 'admin_theme.php', 'admin_selic.php',
            'admin_vau.php', 'reset_senha.php', 'nova_senha.php', 'confirmar_email.php'
        ];
        
        // Remover query string e fragmentos
        $cleanPath = strtok($path, '?');
        $cleanPath = strtok($cleanPath, '#');
        
        // Remover barras iniciais e finais
        $cleanPath = trim($cleanPath, '/');
        
        // Se estiver vazio, é a página inicial
        if (empty($cleanPath)) {
            return true;
        }
        
        // Verificar se é uma página válida
        if (in_array($cleanPath, $validPages)) {
            $fullPath = __DIR__ . '/../../public/' . $cleanPath;
            return file_exists($fullPath);
        }
        
        // Verificar se é um arquivo estático (CSS, JS, imagens)
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|pdf)$/i', $cleanPath)) {
            $fullPath = __DIR__ . '/../../public/' . $cleanPath;
            return file_exists($fullPath);
        }
        
        return false;
    }
}
