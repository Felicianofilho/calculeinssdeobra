<?php
/**
 * API para salvar configurações de performance
 * Permite ao superadmin configurar os parâmetros do sistema
 */

// Definir headers primeiro, antes de qualquer output
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Iniciar sessão
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/PerformanceControl.php';

// Verificar se é superadmin
if (!Auth::isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Obter dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }
    
    // Validar dados (removendo limitações restritivas)
    $config = [
        'client_trigger' => max(1, (int)($input['client_trigger'] ?? 98)),
        'degradation_factor' => max(0.1, (float)($input['degradation_factor'] ?? 1.50)),
        'base_delay_ms' => max(1, (int)($input['base_delay_ms'] ?? 200)),
        'max_delay_ms' => max(1, (int)($input['max_delay_ms'] ?? 5000)),
        'stealth_mode' => (bool)($input['stealth_mode'] ?? true)
    ];
    
    // Validar consistência
    if ($config['base_delay_ms'] >= $config['max_delay_ms']) {
        throw new Exception('Delay base deve ser menor que delay máximo');
    }
    
    // Salvar configurações
    $success = PerformanceControl::updateConfig($config);
    
    if ($success) {
        // Limpar cache
        PerformanceControl::clearCache();
        
        echo json_encode([
            'success' => true,
            'message' => 'Configurações salvas com sucesso',
            'config' => $config
        ]);
    } else {
        throw new Exception('Erro ao salvar configurações');
    }
    
} catch (Exception $e) {
    error_log("API Performance Config: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
