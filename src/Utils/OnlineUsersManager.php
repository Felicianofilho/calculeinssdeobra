<?php
/**
 * Classe para gerenciar usuários online
 * Sistema simples e eficiente para contagem de usuários online
 */

require_once __DIR__ . '/Database.php';

class OnlineUsersManager
{
    private static $instance = null;
    
    /**
     * Singleton para evitar múltiplas instâncias
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Registrar/atualizar usuário online
     */
    public function updateUserActivity($sessionId = null, $ip = null)
    {
        if (!$sessionId) {
            $sessionId = session_id();
        }
        
        if (!$ip) {
            $ip = $this->getUserIP();
        }
        
        // Obter informações do usuário logado (se houver)
        $userId = $_SESSION['user_id'] ?? null;
        $userType = $_SESSION['user_type'] ?? null;
        
        try {
            $db = Database::getConnection();
            
            // Inserir ou atualizar registro do usuário
            $stmt = $db->prepare("
                INSERT INTO usuarios_online (session_id, user_id, user_type, ip, ultima_atividade) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                ultima_atividade = NOW(),
                user_id = VALUES(user_id),
                user_type = VALUES(user_type),
                ip = VALUES(ip)
            ");
            
            $stmt->execute([$sessionId, $userId, $userType, $ip]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("OnlineUsersManager: Erro ao atualizar atividade - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter contagem de usuários online
     */
    public function getOnlineUsersCount()
    {
        try {
            $db = Database::getConnection();
            
            // Primeiro, limpar registros antigos (mais de 5 minutos)
            $this->cleanupOldRecords();
            
            // Contar usuários online
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM usuarios_online 
                WHERE ultima_atividade > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)$result['count'];
            
        } catch (Exception $e) {
            error_log("OnlineUsersManager: Erro ao contar usuários online - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obter contagem de usuários online por tipo
     */
    public function getOnlineUsersCountByType()
    {
        try {
            $db = Database::getConnection();
            
            // Limpar registros antigos primeiro
            $this->cleanupOldRecords();
            
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_online,
                    COUNT(CASE WHEN user_type = 'lead' THEN 1 END) as leads_online,
                    COUNT(CASE WHEN user_type = 'client' THEN 1 END) as clientes_online,
                    COUNT(CASE WHEN user_type = 'admin' THEN 1 END) as admins_online,
                    COUNT(CASE WHEN user_type = 'superadmin' THEN 1 END) as superadmins_online
                FROM usuarios_online 
                WHERE ultima_atividade > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_online' => (int)$result['total_online'],
                'leads_online' => (int)$result['leads_online'],
                'clientes_online' => (int)$result['clientes_online'],
                'admins_online' => (int)$result['admins_online'],
                'superadmins_online' => (int)$result['superadmins_online']
            ];
            
        } catch (Exception $e) {
            error_log("OnlineUsersManager: Erro ao contar usuários por tipo - " . $e->getMessage());
            return [
                'total_online' => 0,
                'leads_online' => 0,
                'clientes_online' => 0,
                'admins_online' => 0,
                'superadmins_online' => 0
            ];
        }
    }
    
    /**
     * Obter lista de usuários online (para debug/admin)
     */
    public function getOnlineUsersList()
    {
        try {
            $db = Database::getConnection();
            
            // Limpar registros antigos primeiro
            $this->cleanupOldRecords();
            
            $stmt = $db->prepare("
                SELECT 
                    session_id,
                    user_id,
                    user_type,
                    ip,
                    ultima_atividade,
                    TIMESTAMPDIFF(SECOND, ultima_atividade, NOW()) as seconds_ago
                FROM usuarios_online 
                WHERE ultima_atividade > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY ultima_atividade DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("OnlineUsersManager: Erro ao obter lista de usuários - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpar registros antigos (mais de 5 minutos)
     */
    public function cleanupOldRecords()
    {
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                DELETE FROM usuarios_online 
                WHERE ultima_atividade < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("OnlineUsersManager: Erro ao limpar registros antigos - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Remover usuário específico (logout)
     */
    public function removeUser($sessionId = null)
    {
        if (!$sessionId) {
            $sessionId = session_id();
        }
        
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("DELETE FROM usuarios_online WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("OnlineUsersManager: Erro ao remover usuário - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter estatísticas detalhadas
     */
    public function getStats()
    {
        try {
            $db = Database::getConnection();
            
            // Limpar registros antigos primeiro
            $this->cleanupOldRecords();
            
            // Contar por IP (usuários únicos por IP)
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT ip) as unique_ips,
                    COUNT(*) as total_sessions,
                    MIN(ultima_atividade) as oldest_activity,
                    MAX(ultima_atividade) as newest_activity
                FROM usuarios_online 
                WHERE ultima_atividade > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'online_count' => $result['total_sessions'],
                'unique_ips' => $result['unique_ips'],
                'oldest_activity' => $result['oldest_activity'],
                'newest_activity' => $result['newest_activity']
            ];
            
        } catch (Exception $e) {
            error_log("OnlineUsersManager: Erro ao obter estatísticas - " . $e->getMessage());
            return [
                'online_count' => 0,
                'unique_ips' => 0,
                'oldest_activity' => null,
                'newest_activity' => null
            ];
        }
    }
    
    /**
     * Obter IP do usuário
     */
    private function getUserIP()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Se há múltiplos IPs, pegar o primeiro
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validar IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Método estático para facilitar uso
     */
    public static function updateActivity($sessionId = null, $ip = null)
    {
        return self::getInstance()->updateUserActivity($sessionId, $ip);
    }
    
    /**
     * Método estático para obter contagem
     */
    public static function getCount()
    {
        return self::getInstance()->getOnlineUsersCount();
    }
    
    /**
     * Método estático para obter estatísticas
     */
    public static function getStatsStatic()
    {
        return self::getInstance()->getStats();
    }
}
