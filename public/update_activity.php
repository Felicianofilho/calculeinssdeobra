<?php
/**
 * Script para atualizar atividade do usuário via AJAX
 * Endpoint leve para manter usuários online
 */

// Definir headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Iniciar sessão se não estiver ativa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../src/Utils/OnlineUsersManager.php';

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }
    
    // Atualizar atividade do usuário
    $success = OnlineUsersManager::updateActivity();
    
    if ($success) {
        // Retornar contagem atualizada
        $count = OnlineUsersManager::getCount();
        
        echo json_encode([
            'success' => true,
            'online_count' => $count,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Erro ao atualizar atividade');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}


