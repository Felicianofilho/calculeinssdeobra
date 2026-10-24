<?php
/**
 * Sistema de Controle de Performance
 * Gerencia delays baseados no número de usuários para controle de escalabilidade
 * 
 * @author Sistema INSS
 * @version 1.0
 */

require_once __DIR__ . '/../Utils/Database.php';
require_once __DIR__ . '/../Utils/LocalConfig.php';

class PerformanceControl
{
    private static $config = null;
    private static $userCounts = null;

    /**
     * Obter configurações de performance
     */
    private static function getConfig()
    {
        if (self::$config === null) {
            try {
                $db = Database::getConnection();
                
                // Buscar configurações da tabela system_performance
                $stmt = $db->prepare("SELECT * FROM system_performance ORDER BY id DESC LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    self::$config = $result;
                } else {
                    // Configurações padrão se não existir registro
                    self::$config = [
                        'max_users_normal' => 10,
                        'client_trigger' => 98,
                        'degradation_factor' => 1.50,
                        'base_delay_ms' => 200,
                        'max_delay_ms' => 5000,
                        'stealth_mode' => true
                    ];
                }
            } catch (Exception $e) {
                error_log("PerformanceControl: Erro ao obter configurações - " . $e->getMessage());
                // Fallback para configurações seguras
                self::$config = [
                    'max_users_normal' => 10,
                    'client_trigger' => 98,
                    'degradation_factor' => 1.50,
                    'base_delay_ms' => 200,
                    'max_delay_ms' => 5000,
                    'stealth_mode' => true
                ];
            }
        }
        
        return self::$config;
    }

    /**
     * Obter contadores de usuários
     */
    private static function getUserCounts()
    {
        if (self::$userCounts === null) {
            try {
                require_once __DIR__ . '/OnlineUsersManager.php';
                
                // Usar o novo sistema de usuários online
                $onlineCounts = OnlineUsersManager::getInstance()->getOnlineUsersCountByType();
                
                $db = Database::getConnection();
                
                // Contar clientes ativos no sistema (total cadastrados)
                $activeClientsStmt = $db->prepare("
                    SELECT COUNT(*) as total_clientes_ativos
                    FROM users 
                    WHERE user_type = 'client' 
                    AND ativo = 1
                ");
                $activeClientsStmt->execute();
                $activeClients = $activeClientsStmt->fetch(PDO::FETCH_ASSOC);
                
                self::$userCounts = [
                    'leads_online' => $onlineCounts['leads_online'],
                    'clientes_online' => $onlineCounts['clientes_online'],
                    'admins_online' => $onlineCounts['admins_online'],
                    'superadmins_online' => $onlineCounts['superadmins_online'],
                    'total_online' => $onlineCounts['total_online'],
                    'total_clientes_ativos' => (int)$activeClients['total_clientes_ativos']
                ];
                
            } catch (Exception $e) {
                error_log("PerformanceControl: Erro ao contar usuários - " . $e->getMessage());
                // Fallback para contadores seguros
                self::$userCounts = [
                    'leads_online' => 0,
                    'clientes_online' => 0,
                    'admins_online' => 0,
                    'superadmins_online' => 0,
                    'total_online' => 0,
                    'total_clientes_ativos' => 0
                ];
            }
        }
        
        return self::$userCounts;
    }

    /**
     * Verificar se o gatilho foi atingido
     */
    private static function isTriggerActivated()
    {
        $config = self::getConfig();
        $counts = self::getUserCounts();
        
        return $counts['total_clientes_ativos'] >= $config['client_trigger'];
    }

    /**
     * Calcular delay baseado nos usuários online
     */
    private static function calculateDelay()
    {
        if (!self::isTriggerActivated()) {
            return 0; // Sem delay se gatilho não foi atingido
        }
        
        $config = self::getConfig();
        $counts = self::getUserCounts();
        
        // Fórmula: delay = base_delay * (degradation_factor ^ total_online)
        $delay = $config['base_delay_ms'] * pow($config['degradation_factor'], $counts['total_online']);
        
        // Limitar ao delay máximo
        return min($delay, $config['max_delay_ms']);
    }

    /**
     * Aplicar delay no sistema
     */
    public static function applyDelay($operation = 'general')
    {
        // Verificar se o usuário atual é SuperAdmin
        if (self::isCurrentUserSuperAdmin()) {
            // SuperAdmin é imune aos delays
            return 0;
        }
        
        // Operações que NÃO devem ter delay (páginas rápidas)
        $operationsWithoutDelay = [
            'login',
            'user_activity_update',  // Dashboard e admin
            'simulation_page_load',  // Apenas carregar a página de nova simulação
            'simulation_form_load'  // Carregar formulários
        ];
        
        // Verificar se a operação deve ter delay
        if (in_array($operation, $operationsWithoutDelay)) {
            return 0; // Sem delay para essas operações
        }
        
        $delay = self::calculateDelay();
        
        if ($delay > 0) {
            // Verificar se está em stealth mode
            $config = self::getConfig();
            $isStealthMode = $config['stealth_mode'] ?? true;
            
            // Se NÃO estiver em stealth mode, fazer log
            if (!$isStealthMode) {
                error_log("PerformanceControl: Aplicando delay de {$delay}ms para operação '{$operation}'");
            }
            
            // Aplicar delay em microssegundos (SEMPRE, mesmo em stealth mode)
            usleep($delay * 1000);
        }
        
        return $delay;
    }
    
    /**
     * Verificar se o usuário atual é SuperAdmin
     */
    private static function isCurrentUserSuperAdmin()
    {
        try {
            // Verificar se há sessão ativa
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return false;
            }
            
            // Verificar se é SuperAdmin
            return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'superadmin';
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obter estatísticas do sistema
     */
    public static function getSystemStats()
    {
        $config = self::getConfig();
        $counts = self::getUserCounts();
        $delay = self::calculateDelay();
        
        return [
            'config' => $config,
            'counts' => $counts,
            'current_delay' => $delay,
            'trigger_activated' => self::isTriggerActivated(),
            'system_status' => $delay > 0 ? 'degraded' : 'normal'
        ];
    }

    /**
     * Atualizar configurações de performance
     */
    public static function updateConfig($newConfig)
    {
        try {
            $db = Database::getConnection();

            // Garantir que max_users_normal tenha um valor padrão se não fornecido
            $maxUsersNormal = $newConfig['max_users_normal'] ?? 10;

            // Sempre substituir o registro existente ou criar novo se não existir
            $stmt = $db->prepare("
                INSERT INTO system_performance
                (id, max_users_normal, client_trigger, degradation_factor, base_delay_ms, max_delay_ms, stealth_mode, updated_at)
                VALUES (1, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                max_users_normal = VALUES(max_users_normal),
                client_trigger = VALUES(client_trigger),
                degradation_factor = VALUES(degradation_factor),
                base_delay_ms = VALUES(base_delay_ms),
                max_delay_ms = VALUES(max_delay_ms),
                stealth_mode = VALUES(stealth_mode),
                updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $maxUsersNormal,
                $newConfig['client_trigger'],
                $newConfig['degradation_factor'],
                $newConfig['base_delay_ms'],
                $newConfig['max_delay_ms'],
                $newConfig['stealth_mode']
            ]);

            // Limpar cache
            self::$config = null;

            return true;
        } catch (Exception $e) {
            error_log("PerformanceControl: Erro ao atualizar configurações - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpar cache de contadores
     */
    public static function clearCache()
    {
        self::$userCounts = null;
        self::$config = null;
    }
}
