<?php
/**
 * API Planejamento Tributário
 * Endpoints para operações de planejamento tributário
 * 
 * @author Sistema INSS
 * @version 2.0
 */

// Configurações de segurança
require_once __DIR__ . '/../config/security.php';

// Iniciar sessão se necessário
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações de erro
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Incluir dependências
    require_once __DIR__ . '/../src/Utils/Auth.php';
    require_once __DIR__ . '/../src/Utils/PerformanceControl.php';
    require_once __DIR__ . '/../src/Controllers/PlanejamentoTributarioController.php';
    require_once __DIR__ . '/../src/Utils/ProcessLock.php';
    
    // Log de debug (opcional)
    if (isset($_GET['debug'])) {
        file_put_contents(__DIR__.'/sessao_debug.log', print_r($_COOKIE, true).PHP_EOL, FILE_APPEND);
        file_put_contents(__DIR__.'/sessao_debug.log', '$_SESSION: ' . print_r($_SESSION, true) . PHP_EOL, FILE_APPEND);
    }
    
    // Verificar autenticação
    if (!Auth::check()) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Não autorizado',
            'message' => 'Usuário não autenticado'
        ]);
        exit;
    }
    
    $user = Auth::user();
    
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false, 
            'error' => 'Método não permitido',
            'message' => 'Apenas POST é permitido'
        ]);
        exit;
    }
    
    // Receber dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Dados inválidos',
            'message' => 'JSON inválido ou vazio'
        ]);
        exit;
    }
    
    $action = $input['action'] ?? '';
    
    // Aplicar delay de performance antes do processamento
    PerformanceControl::applyDelay($action);
    
    // Roteamento de ações
    switch ($action) {
        case 'calcular':
            $resultado = processarCalcular($input, $user);
            break;
            
        case 'buscar':
            $resultado = processarBuscar($input, $user);
            break;
            
        case 'listar':
            $resultado = processarListar($user);
            break;
            
        case 'excluir':
            $resultado = processarExcluir($input, $user);
            break;
            
        default:
            http_response_code(400);
            $resultado = [
                'success' => false, 
                'error' => 'Ação não reconhecida',
                'message' => 'Ação inválida: ' . $action
            ];
            break;
    }
    
    // Retornar resposta
    if (isset($resultado['error'])) {
        http_response_code(400);
    }
    
    echo json_encode($resultado);
    
} catch (Throwable $e) {
    // Log detalhado do erro
    error_log('[Planejamento API] Erro: ' . $e->getMessage());
    error_log('[Planejamento API] Arquivo: ' . $e->getFile() . ' Linha: ' . $e->getLine());
    error_log('[Planejamento API] Trace: ' . $e->getTraceAsString());
    
    // Capturar erro SQL se disponível
    $sql_error = null;
    if (class_exists('PlanejamentoTributario') && property_exists('PlanejamentoTributario', 'pdo') && isset(PlanejamentoTributario::$pdo)) {
        $sql_error = PlanejamentoTributario::$pdo->errorInfo();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage(),
        'sql_error' => $sql_error
    ]);
    exit;
}

/**
 * Processar ação 'calcular'
 */
function processarCalcular($input, $user) 
{
    // Validar parâmetros obrigatórios
    $simulacao_id = $input['simulacao_id'] ?? null;
    $periodo_inicio = $input['periodo_inicio'] ?? null;
    $periodo_fim = $input['periodo_fim'] ?? null;
    $force_update = $input['force_update'] ?? false;
    $planejamento_id = $input['planejamento_id'] ?? null;
    $honorarios_valor = $input['honorarios_valor'] ?? 0;
    $honorarios_tipo = $input['honorarios_tipo'] ?? 'real';
    $concreto_usinado = $input['concreto_usinado'] ?? false;
    
    if (!$simulacao_id || !$periodo_inicio || !$periodo_fim) {
        return [
            'success' => false,
            'error' => 'Dados obrigatórios não fornecidos',
            'message' => 'simulacao_id, periodo_inicio e periodo_fim são obrigatórios'
        ];
    }
    
    // Validar formato das datas
    if (!DateTime::createFromFormat('Y-m-d', $periodo_inicio) || 
        !DateTime::createFromFormat('Y-m-d', $periodo_fim)) {
        return [
            'success' => false,
            'error' => 'Formato de data inválido',
            'message' => 'Use formato YYYY-MM-DD'
        ];
    }
    
    // Validar período
    $data_inicio = new DateTime($periodo_inicio);
    $data_fim = new DateTime($periodo_fim);
    
    if ($data_inicio > $data_fim) {
        return [
            'success' => false,
            'error' => 'Período inválido',
            'message' => 'Data de início deve ser anterior à data de fim'
        ];
    }
    
    // Tentar adquirir o lock
    if (!ProcessLock::acquireLock('inss_calculation')) {
        return [
            'success' => false,
            'error' => 'Sistema ocupado',
            'message' => 'O sistema está processando outro cálculo. Por favor, tente novamente em alguns instantes.'
        ];
    }

    try {
        // Executar cálculo
        $resultado = PlanejamentoTributarioController::calcular(
            $simulacao_id,
            $user['id'],
            $periodo_inicio,
            $periodo_fim,
            $force_update,
            $planejamento_id,
            $honorarios_valor,
            $honorarios_tipo,
            $concreto_usinado
        );
    } finally {
        // Garantir que o lock seja liberado
        ProcessLock::releaseLock('inss_calculation');
    }
    
    if (isset($resultado['error'])) {
        return [
            'success' => false,
            'error' => $resultado['error'],
            'message' => $resultado['error']
        ];
    }
    
    return $resultado;
}

/**
 * Processar ação 'buscar'
 */
function processarBuscar($input, $user) 
{
    $planejamento_id = $input['planejamento_id'] ?? null;
    
    if (!$planejamento_id) {
        return [
            'success' => false,
            'error' => 'ID do planejamento não fornecido',
            'message' => 'planejamento_id é obrigatório'
        ];
    }
    
    $resultado = PlanejamentoTributarioController::buscar($planejamento_id, $user['id']);
    
    if (isset($resultado['error'])) {
        return [
            'success' => false,
            'error' => $resultado['error'],
            'message' => $resultado['error']
        ];
    }
    
    return $resultado;
}

/**
 * Processar ação 'listar'
 */
function processarListar($user) 
{
    return PlanejamentoTributarioController::listar($user['id']);
}

/**
 * Processar ação 'excluir'
 */
function processarExcluir($input, $user) 
{
    $planejamento_id = $input['planejamento_id'] ?? null;
    
    if (!$planejamento_id) {
        return [
            'success' => false,
            'error' => 'ID do planejamento não fornecido',
            'message' => 'planejamento_id é obrigatório'
        ];
    }
    
    $resultado = PlanejamentoTributarioController::excluir($planejamento_id, $user['id']);
    
    if (isset($resultado['error'])) {
        return [
            'success' => false,
            'error' => $resultado['error'],
            'message' => $resultado['error']
        ];
    }
    
    return $resultado;
}