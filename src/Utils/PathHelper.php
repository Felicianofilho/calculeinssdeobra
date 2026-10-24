<?php

class PathHelper
{
    /**
     * Detecta automaticamente o caminho base da aplicação
     */
    public static function getBasePath()
    {
        // Obter o diretório do script atual
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        
        // Se estiver na raiz do servidor, retorna vazio
        if ($scriptDir === '/' || $scriptDir === '\\' || empty($scriptDir)) {
            return '';
        }
        
        // Normalizar separadores para sempre usar /
        $basePath = str_replace('\\', '/', $scriptDir);
        
        // Garantir que comece com / mas não termine com /
        $basePath = '/' . trim($basePath, '/');
        
        return $basePath;
    }
    
    /**
     * Constrói uma URL completa baseada no caminho base detectado
     */
    public static function buildUrl($path = '')
    {
        $basePath = self::getBasePath();
        $path = ltrim($path, '/');
        
        if (empty($path)) {
            return $basePath ?: '/';
        }
        
        return $basePath . '/' . $path;
    }
    
    /**
     * Remove o caminho base de uma URL para obter apenas a rota relativa
     */
    public static function getRelativePath($fullPath)
    {
        $basePath = self::getBasePath();
        
        // Normalizar o caminho
        $fullPath = trim($fullPath, '/');
        
        // Se não há caminho base, retorna o caminho como está
        if (empty($basePath)) {
            return $fullPath;
        }
        
        // Remover barra inicial do basePath para comparação
        $basePathClean = trim($basePath, '/');
        
        // Se o caminho começa com o basePath, remove-o
        if (strpos($fullPath, $basePathClean . '/') === 0) {
            return substr($fullPath, strlen($basePathClean) + 1);
        }
        
        // Se o caminho é exatamente igual ao basePath, retorna vazio (página inicial)
        if ($fullPath === $basePathClean) {
            return '';
        }
        
        // Se não encontrou o basePath, retorna o caminho original
        return $fullPath;
    }
    
    /**
     * Verifica se estamos em ambiente de desenvolvimento local
     */
    public static function isLocalDevelopment()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return in_array($host, ['localhost', '127.0.0.1']) || 
               strpos($host, 'localhost:') === 0 ||
               strpos($host, '127.0.0.1:') === 0;
    }
    
    /**
     * Obtém informações do ambiente atual
     */
    public static function getEnvironmentInfo()
    {
        return [
            'base_path' => self::getBasePath(),
            'is_local' => self::isLocalDevelopment(),
            'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
    }
}
