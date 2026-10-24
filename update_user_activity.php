<?php
/**
 * Script para atualizar atividade de usuários em todas as páginas
 * Incluir este arquivo no topo de todas as páginas que requerem autenticação
 * Sistema melhorado com rastreamento de sessões ativas
 */

// Verificar se há sessão ativa antes de iniciar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sempre incluir o sistema de usuários online (para todos os usuários, logados ou não)
require_once __DIR__ . '/src/Utils/OnlineUsersManager.php';

// Atualizar usuário online (funciona para todos os usuários)
OnlineUsersManager::updateActivity();

// Verificar se há usuário logado para sistemas específicos
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/src/Utils/UserActivity.php';
    require_once __DIR__ . '/src/Utils/UserSessionManager.php';
    require_once __DIR__ . '/src/Utils/PerformanceControl.php';
    
    // Atualizar last_activity (sistema antigo para compatibilidade)
    UserActivity::updateActivity();
    
    // Atualizar sessão ativa (sistema novo e mais confiável)
    $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
    UserSessionManager::updateSession($_SESSION['user_id'], session_id(), $currentUrl);
    
    // Aplicar controle de performance baseado no número de usuários online
    PerformanceControl::applyDelay('user_activity_update');
}
