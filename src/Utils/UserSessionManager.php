<?php
/**
 * Classe para gerenciar sessões de usuários de forma mais confiável
 */

require_once __DIR__ . '/Database.php';

class UserSessionManager
{
    /**
     * Registrar/atualizar sessão do usuário
     */
    public static function updateSession($userId, $sessionId = null, $pageUrl = null)
    {
        if (!$sessionId) {
            $sessionId = session_id();
        }
        
        try {
            $db = Database::getConnection();
            
            // Obter IP e User Agent
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Atualizar ou inserir na tabela user_sessions
            $stmt = $db->prepare("
                INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_activity) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                last_activity = NOW(),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent)
            ");
            $stmt->execute([$userId, $sessionId, $ipAddress, $userAgent]);
            
            // Atualizar current_session_id na tabela users
            $stmt = $db->prepare("UPDATE users SET current_session_id = ? WHERE id = ?");
            $stmt->execute([$sessionId, $userId]);
            
            // Registrar heartbeat se fornecido
            if ($pageUrl) {
                self::recordHeartbeat($userId, $sessionId, $pageUrl);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("UserSessionManager: Erro ao atualizar sessão - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar heartbeat do usuário
     */
    public static function recordHeartbeat($userId, $sessionId = null, $pageUrl = null)
    {
        if (!$sessionId) {
            $sessionId = session_id();
        }
        
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO user_heartbeat (user_id, session_id, heartbeat_time, page_url) 
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([$userId, $sessionId, $pageUrl]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("UserSessionManager: Erro ao registrar heartbeat - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se usuário está online (método mais confiável)
     */
    public static function isUserOnline($userId, $minutesThreshold = 5)
    {
        try {
            $db = Database::getConnection();
            
            // Verificar se há sessão ativa nos últimos X minutos
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM user_sessions 
                WHERE user_id = ? 
                AND last_activity > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            $stmt->execute([$userId, $minutesThreshold]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            error_log("UserSessionManager: Erro ao verificar se usuário está online - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter contagem de usuários online por tipo (método melhorado)
     */
    public static function getOnlineUsersCount($minutesThreshold = 5)
    {
        try {
            $db = Database::getConnection();
            
            // Contar usuários com sessões ativas
            $stmt = $db->prepare("
                SELECT 
                    u.user_type,
                    COUNT(DISTINCT u.id) as count
                FROM users u
                INNER JOIN user_sessions us ON u.id = us.user_id
                WHERE u.ativo = 1
                AND us.last_activity > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                GROUP BY u.user_type
            ");
            $stmt->execute([$minutesThreshold]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organizar resultados
            $counts = [
                'leads_online' => 0,
                'clientes_online' => 0,
                'admins_online' => 0,
                'superadmins_online' => 0,
                'total_online' => 0
            ];
            
            foreach ($results as $result) {
                $userType = $result['user_type'];
                $count = $result['count'];
                
                switch ($userType) {
                    case 'lead':
                        $counts['leads_online'] = $count;
                        break;
                    case 'client':
                        $counts['clientes_online'] = $count;
                        break;
                    case 'admin':
                        $counts['admins_online'] = $count;
                        break;
                    case 'superadmin':
                        $counts['superadmins_online'] = $count;
                        break;
                }
                
                $counts['total_online'] += $count;
            }
            
            return $counts;
            
        } catch (Exception $e) {
            error_log("UserSessionManager: Erro ao contar usuários online - " . $e->getMessage());
            return [
                'leads_online' => 0,
                'clientes_online' => 0,
                'admins_online' => 0,
                'superadmins_online' => 0,
                'total_online' => 0
            ];
        }
    }
    
    /**
     * Obter detalhes dos usuários online
     */
    public static function getOnlineUsersDetails($minutesThreshold = 5)
    {
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                SELECT 
                    u.id,
                    u.nome,
                    u.user_type,
                    us.last_activity,
                    us.ip_address,
                    TIMESTAMPDIFF(SECOND, us.last_activity, NOW()) as seconds_ago
                FROM users u
                INNER JOIN user_sessions us ON u.id = us.user_id
                WHERE u.ativo = 1
                AND us.last_activity > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY us.last_activity DESC
            ");
            $stmt->execute([$minutesThreshold]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("UserSessionManager: Erro ao obter detalhes dos usuários online - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpar sessões antigas
     */
    public static function cleanupOldSessions($minutesThreshold = 10)
    {
        try {
            $db = Database::getConnection();
            
            // Limpar user_sessions antigas
            $stmt = $db->prepare("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
            $stmt->execute([$minutesThreshold]);
            $sessionsDeleted = $stmt->rowCount();
            
            // Limpar heartbeats antigos
            $stmt = $db->prepare("DELETE FROM user_heartbeat WHERE heartbeat_time < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
            $stmt->execute([$minutesThreshold]);
            $heartbeatsDeleted = $stmt->rowCount();
            
            // Limpar current_session_id de usuários sem sessões ativas
            $stmt = $db->prepare("
                UPDATE users 
                SET current_session_id = NULL 
                WHERE current_session_id IS NOT NULL 
                AND id NOT IN (
                    SELECT DISTINCT user_id 
                    FROM user_sessions 
                    WHERE last_activity > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                )
            ");
            $stmt->execute([$minutesThreshold]);
            $usersUpdated = $stmt->rowCount();
            
            return [
                'sessions_deleted' => $sessionsDeleted,
                'heartbeats_deleted' => $heartbeatsDeleted,
                'users_updated' => $usersUpdated
            ];
            
        } catch (Exception $e) {
            error_log("UserSessionManager: Erro ao limpar sessões antigas - " . $e->getMessage());
            return false;
        }
    }
}
