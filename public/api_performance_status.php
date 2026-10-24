<?php
/**
 * API para obter status do sistema de performance
 * Retorna estatísticas de usuários e configurações
 */

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

try {
    // Obter estatísticas do sistema
    $stats = PerformanceControl::getSystemStats();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("API Performance Status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao obter status do sistema',
        'error' => $e->getMessage()
    ]);
}
