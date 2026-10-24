<?php
/**
 * Classe para gerenciar atividade dos usuários
 * Atualiza automaticamente a last_activity para detectar usuários online
 */

require_once __DIR__ . '/Database.php';

class UserActivity
{
    /**
     * Atualizar atividade do usuário atual
     */
    public static function updateActivity()
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {
            // Silenciar erros para não interferir no funcionamento normal
            error_log("UserActivity: Erro ao atualizar atividade - " . $e->getMessage());
        }
    }
    
    /**
     * Atualizar atividade de um usuário específico
     */
    public static function updateUserActivity($userId)
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("UserActivity: Erro ao atualizar atividade do usuário $userId - " . $e->getMessage());
        }
    }
    
    /**
     * Limpar atividade de usuários inativos (mais de 1 hora)
     */
    public static function cleanupInactiveUsers()
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET last_activity = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute();
        } catch (Exception $e) {
            error_log("UserActivity: Erro ao limpar usuários inativos - " . $e->getMessage());
        }
    }
}
