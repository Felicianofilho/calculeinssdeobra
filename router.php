<?php
/**
 * Router Principal do Sistema
 * 
 * Este arquivo é chamado pelo .htaccess quando uma URL não corresponde
 * a um arquivo ou diretório físico existente.
 */

// Incluir configurações de segurança
require_once __DIR__ . '/config/security.php';

// Incluir utilitários necessários
require_once __DIR__ . '/src/Utils/ErrorHandler.php';
require_once __DIR__ . '/src/Utils/Auth.php';
require_once __DIR__ . '/src/Utils/LocalConfig.php';
require_once __DIR__ . '/src/Utils/PathHelper.php';

// Inicializar manipulador de erros
ErrorHandler::initialize();

// Iniciar sessão
session_start();

// Obter a URL solicitada e detectar caminho base automaticamente
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Usar PathHelper para obter caminho relativo dinâmico
$requestPath = PathHelper::getRelativePath($requestPath);

// Se a requisição vem do diretório public (via ../router.php), 
// precisamos adicionar 'public/' ao caminho para o roteamento correto
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/public/') !== false && 
    !empty($requestPath) && 
    strpos($requestPath, 'public/') !== 0) {
    $requestPath = 'public/' . $requestPath;
}

// Se for tentativa de acesso direto ao 404, permitir
if ($requestPath === '404.html' || $requestPath === '404.php') {
    if (file_exists(__DIR__ . '/404.php')) {
        include __DIR__ . '/404.php';
        exit;
    } elseif (file_exists(__DIR__ . '/404.html')) {
        header('Content-Type: text/html; charset=utf-8');
        header('HTTP/1.1 404 Not Found');
        readfile(__DIR__ . '/404.html');
        exit;
    }
}

// Log da tentativa de acesso (apenas em desenvolvimento)
if (!ErrorHandler::isProduction()) {
    $envInfo = PathHelper::getEnvironmentInfo();
    error_log("Router: Tentativa de acesso à URL: " . $requestPath . " | Request URI: " . $requestUri . " | Base Path: " . $envInfo['base_path']);
}

// Definir rotas válidas
$routes = [
    // Páginas públicas (raiz)
    '' => 'public/index.php',                    // Página inicial
    'index' => 'public/index.php',
    
    // Páginas públicas (com public/)
    'public/index.php' => 'public/index.php',
    'public/login.php' => 'public/login.php',
    'public/register.php' => 'public/register.php',
    'public/reset_senha.php' => 'public/reset_senha.php',
    'public/nova_senha.php' => 'public/nova_senha.php',
    'public/confirmar_email.php' => 'public/confirmar_email.php',
    
    // Páginas protegidas (com public/)
    'public/dashboard.php' => 'public/dashboard.php',
    'public/nova_simulacao.php' => 'public/nova_simulacao.php',
    'public/minhas_simulacoes.php' => 'public/minhas_simulacoes.php',
    'public/planejamento_tributario.php' => 'public/planejamento_tributario.php',
    'public/meus_planejamentos.php' => 'public/meus_planejamentos.php',
    'public/relatorios.php' => 'public/relatorios.php',
    'public/perfil.php' => 'public/perfil.php',
    'public/logout.php' => 'public/logout.php',
    
    // Páginas administrativas (com public/)
    'public/admin.php' => 'public/admin.php',
    'public/admin_settings.php' => 'public/admin_settings.php',
    'public/admin_theme.php' => 'public/admin_theme.php',
    'public/admin_selic.php' => 'public/admin_selic.php',
    'public/admin_vau.php' => 'public/admin_vau.php',
    
    // Rotas sem public/ (para compatibilidade)
    'login' => 'public/login.php',
    'register' => 'public/register.php',
    'dashboard' => 'public/dashboard.php',
    'admin' => 'public/admin.php',
    
    // Arquivos especiais
    '404' => '404.html',
    'manual' => 'manual-usuario.html'
];

// Verificar se é uma rota válida
if (array_key_exists($requestPath, $routes)) {
    $targetFile = $routes[$requestPath];
    
    // Verificar se o arquivo existe fisicamente
    if (file_exists(__DIR__ . '/' . $targetFile)) {
        // Para páginas PHP, incluir diretamente
        if (pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
            include __DIR__ . '/' . $targetFile;
            exit;
        } else {
            // Para arquivos HTML, redirecionar
            header('Location: /' . $targetFile);
            exit;
        }
    }
}

// Verificar se é um arquivo estático na pasta public
if (!empty($requestPath)) {
    $staticFile = __DIR__ . '/public/' . $requestPath;
    
    if (file_exists($staticFile) && is_file($staticFile)) {
        // Determinar o tipo MIME
        $mimeType = getMimeType($staticFile);
        
        // Definir cabeçalhos apropriados
        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            
            // Cache para arquivos estáticos
            if (in_array($mimeType, ['text/css', 'application/javascript', 'image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'])) {
                header('Cache-Control: public, max-age=2592000'); // 30 dias
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
            }
        }
        
        // Servir o arquivo
        readfile($staticFile);
        exit;
    }
}

// Se chegou até aqui, a página não existe
// Definir cabeçalho 404
if (!headers_sent()) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/html; charset=utf-8');
}

// Usar a página 404 dinâmica (PHP) primeiro, depois HTML como fallback
if (file_exists(__DIR__ . '/404.php')) {
    include __DIR__ . '/404.php';
    exit;
} elseif (file_exists(__DIR__ . '/404.html')) {
    readfile(__DIR__ . '/404.html');
    exit;
}

// Último fallback - mostrar página HTML básica
echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>404 - Página Não Encontrada</title>
</head>
<body>
    <h1>404 - Página Não Encontrada</h1>
    <p>A página que você procura não existe.</p>
    <p>Router funcionando! Requested: ' . htmlspecialchars($requestPath) . '</p>
    <a href="' . PathHelper::buildUrl() . '">Voltar à página inicial</a>
</body>
</html>';
exit;

/**
 * Determina o tipo MIME de um arquivo
 */
function getMimeType($file)
{
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'pdf' => 'application/pdf',
        'html' => 'text/html',
        'htm' => 'text/html',
        'txt' => 'text/plain',
        'json' => 'application/json',
        'xml' => 'application/xml'
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}
