<?php
/**
 * Modelo PlanejamentoTributario
 * Gerencia operações de banco de dados para planejamento tributário
 * 
 * @author Sistema INSS
 * @version 2.0
 */

require_once __DIR__ . '/../Utils/Database.php';

// Inicializar sessão se não estiver ativa (necessário para verificar permissões)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class PlanejamentoTributario 
{
    private static $pdo;
    
    /**
     * Inicializa conexão com banco
     */
    private static function init() 
    {
        if (!self::$pdo) {
            self::$pdo = Database::getConnection();
        }
    }
    
    /**
     * Criar ou atualizar planejamento tributário
     * Garante que nunca haja mais de um planejamento por simulação
     * 
     * @param int $simulacao_id ID da simulação
     * @param int $user_id ID do usuário
     * @param array $dados Dados do planejamento
     * @return int|false ID do planejamento criado/atualizado ou false em caso de erro
     */
    public static function create($simulacao_id, $user_id, $dados) 
    {
        self::init();
        
        try {
            // Verificar se já existe planejamento para esta simulação
            $existing = self::findBySimulacao($simulacao_id, $user_id);
            
            if (!empty($existing)) {
                // Se já existe, fazer update
                $existing_id = $existing[0]['id'];
                $result = self::update($existing_id, $dados, $user_id);
                return $result ? $existing_id : false;
            }
            
            // Se não existe, criar novo
        $sql = "INSERT INTO planejamento_tributario 
                (simulacao_id, user_id, created_by_admin, created_by_admin_id, area_obra, rmt_base, inss_original, 
                 periodo_inicio, periodo_fim, dados_calculados, honorarios_valor, 
                 honorarios_tipo, honorarios_calculados) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = self::$pdo->prepare($sql);
        $params = [
            $simulacao_id,
            $user_id,
            (int)($dados['created_by_admin'] ?? false),
            $dados['created_by_admin_id'] ?? null,
            $dados['area_obra'],
            $dados['rmt_base'],
            $dados['inss_original'],
            $dados['periodo_inicio'],
            $dados['periodo_fim'],
            json_encode($dados['dados_calculados']),
            $dados['honorarios_valor'] ?? 0,
            $dados['honorarios_tipo'] ?? 'real',
            json_encode($dados['honorarios_calculados'] ?? [])
        ];
        
            if ($stmt->execute($params)) {
                return self::$pdo->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::create] Erro: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualizar planejamento existente
     * 
     * @param int $id ID do planejamento
     * @param array $dados Dados atualizados
     * @param int $user_id ID do usuário (opcional para validação)
     * @return bool Sucesso da operação
     */
    public static function update($id, $dados, $user_id = null) 
    {
        self::init();
        
        try {
        $sql = "UPDATE planejamento_tributario SET 
                created_by_admin = ?, created_by_admin_id = ?, area_obra = ?, rmt_base = ?, inss_original = ?, 
                    periodo_inicio = ?, periodo_fim = ?, dados_calculados = ?,
                    honorarios_valor = ?, honorarios_tipo = ?, honorarios_calculados = ?,
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $params = [
            (int)($dados['created_by_admin'] ?? false),
            $dados['created_by_admin_id'] ?? null,
            $dados['area_obra'],
            $dados['rmt_base'],
            $dados['inss_original'],
            $dados['periodo_inicio'],
            $dados['periodo_fim'],
            json_encode($dados['dados_calculados']),
            $dados['honorarios_valor'] ?? 0,
            $dados['honorarios_tipo'] ?? 'real',
            json_encode($dados['honorarios_calculados'] ?? []),
            $id
        ];
        
        if ($user_id) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = self::$pdo->prepare($sql);
        return $stmt->execute($params);
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::update] Erro: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar planejamento por ID
     * 
     * @param int $id ID do planejamento
     * @param int $user_id ID do usuário (opcional para validação)
     * @return array|false Dados do planejamento ou false se não encontrado
     */
    public static function find($id, $user_id = null) 
    {
        self::init();
        
        try {
            $sql = "SELECT * FROM planejamento_tributario WHERE id = ?";
            $params = [$id];
            
            if ($user_id) {
                $sql .= " AND user_id = ?";
                $params[] = $user_id;
            }
            
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Decodificar dados calculados
                $result['dados_calculados'] = json_decode($result['dados_calculados'], true);
                // Decodificar honorários calculados
                $result['honorarios_calculados'] = json_decode($result['honorarios_calculados'], true);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::find] Erro: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar planejamento por simulação
     * 
     * @param int $simulacao_id ID da simulação
     * @param int $user_id ID do usuário (opcional para validação)
     * @return array Lista de planejamentos
     */
    public static function findBySimulacao($simulacao_id, $user_id = null) 
    {
        self::init();
        
        try {
            $sql = "SELECT * FROM planejamento_tributario WHERE simulacao_id = ?";
            $params = [$simulacao_id];
            
            if ($user_id) {
                $sql .= " AND user_id = ?";
                $params[] = $user_id;
            }
            
            $sql .= " ORDER BY criado_em DESC";
            
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodificar dados calculados para cada resultado
            foreach ($results as &$result) {
                $result['dados_calculados'] = json_decode($result['dados_calculados'], true);
                $result['honorarios_calculados'] = json_decode($result['honorarios_calculados'], true);
            }
            
            return $results;
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::findBySimulacao] Erro: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buscar planejamentos por usuário
     * 
     * @param int $user_id ID do usuário
     * @return array Lista de planejamentos com dados da simulação
     */
    public static function findByUser($user_id) 
    {
        self::init();
        
        try {
            $sql = "SELECT pt.*, s.nome as simulacao_nome, s.responsavel, s.destinacao, s.uf
                    FROM planejamento_tributario pt
                    INNER JOIN simulacoes s ON pt.simulacao_id = s.id
                    WHERE pt.user_id = ?
                    ORDER BY pt.criado_em DESC";
            
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute([$user_id]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodificar dados calculados para cada resultado
            foreach ($results as &$result) {
                $result['dados_calculados'] = json_decode($result['dados_calculados'], true);
            }
            
            return $results;
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::findByUser] Erro: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buscar planejamentos por usuário com filtros opcionais
     * @param int $user_id
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public static function findByUserComFiltros($user_id, $filters = [], $limit = 100)
    {
        self::init();
        try {
            $sql = "SELECT pt.*, s.nome as simulacao_nome, s.responsavel, s.destinacao, s.uf
                    FROM planejamento_tributario pt
                    INNER JOIN simulacoes s ON pt.simulacao_id = s.id
                    WHERE pt.user_id = ?";
            $params = [$user_id];
            if (!empty($filters['nome'])) {
                $sql .= " AND s.nome LIKE ?";
                $params[] = '%' . $filters['nome'] . '%';
            }
            if (!empty($filters['periodo_inicio'])) {
                $sql .= " AND pt.periodo_inicio >= ?";
                $params[] = $filters['periodo_inicio'];
            }
            if (!empty($filters['periodo_fim'])) {
                $sql .= " AND pt.periodo_fim <= ?";
                $params[] = $filters['periodo_fim'];
            }
            $sql .= " ORDER BY pt.criado_em DESC LIMIT " . intval($limit);
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$result) {
                $result['dados_calculados'] = json_decode($result['dados_calculados'], true);
            }
            return $results;
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::findByUserComFiltros] Erro: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Excluir planejamento
     * 
     * @param int $id ID do planejamento
     * @param int $user_id ID do usuário (opcional para validação)
     * @return bool Sucesso da operação
     */
    public static function delete($id, $user_id = null) 
    {
        self::init();
        
        try {
        $sql = "DELETE FROM planejamento_tributario WHERE id = ?";
        $params = [$id];
        
        if ($user_id) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = self::$pdo->prepare($sql);
        return $stmt->execute($params);
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::delete] Erro: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se usuário tem acesso ao planejamento
     * 
     * @param int $id ID do planejamento
     * @param int $user_id ID do usuário
     * @return bool True se tem acesso
     */
    public static function hasAccess($id, $user_id) 
    {
        self::init();
        
        try {
        $sql = "SELECT COUNT(*) FROM planejamento_tributario 
                WHERE id = ? AND user_id = ?";
        
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([$id, $user_id]);
        
        return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::hasAccess] Erro: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Contar planejamentos por simulação
     * 
     * @param int $simulacao_id ID da simulação
     * @param int $user_id ID do usuário (opcional para validação)
     * @return int Número de planejamentos
     */
    public static function countBySimulacao($simulacao_id, $user_id = null) 
    {
        self::init();
        
        try {
        $sql = "SELECT COUNT(*) FROM planejamento_tributario WHERE simulacao_id = ?";
        $params = [$simulacao_id];
        
        if ($user_id) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::countBySimulacao] Erro: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Verificar se já existe planejamento para simulação
     * 
     * @param int $simulacao_id ID da simulação
     * @param int $user_id ID do usuário
     * @return array|false Dados do planejamento existente ou false
     */
    public static function findExistingBySimulacao($simulacao_id, $user_id) 
    {
        $planejamentos = self::findBySimulacao($simulacao_id, $user_id);
        return !empty($planejamentos) ? $planejamentos[0] : false;
    }

    /**
     * Excluir todos os planejamentos de um usuário.
     *
     * @param int $user_id ID do usuário
     * @return bool Sucesso da operação
     */
    public static function deleteByUser($user_id)
    {
        self::init();
        try {
            $sql = "DELETE FROM planejamento_tributario WHERE user_id = ?";
            $stmt = self::$pdo->prepare($sql);
            return $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::deleteByUser] Erro: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Listar todos os planejamentos do sistema, com dados do usuário e da simulação
     * @param array $filters Filtros opcionais: nome, usuario, destinacao, data
     * @param int $limit Limite de registros (padrão: 200)
     * @param int $offset Offset para paginação (padrão: 0)
     * @return array
     */
    public static function allWithUserAndSimulacao($filters = [], $limit = 200, $offset = 0)
    {
        self::init();
        try {
            $sql = "SELECT pt.*, s.nome as simulacao_nome, s.destinacao, s.user_id, s.responsavel, s.regime_tributario, s.area, s.criado_em as simulacao_criado_em, u.nome as usuario_nome, u.email as usuario_email
                    FROM planejamento_tributario pt
                    INNER JOIN simulacoes s ON pt.simulacao_id = s.id
                    INNER JOIN users u ON s.user_id = u.id
                    WHERE 1=1";
            $params = [];
            
            // Verificar se o usuário logado não é SuperAdmin
            // Se for apenas Admin, não mostrar planejamentos de SuperAdmins
            if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
                $sql .= " AND u.user_type != 'superadmin'";
            }
            if (!empty($filters['nome'])) {
                $sql .= " AND s.nome LIKE ?";
                $params[] = '%' . $filters['nome'] . '%';
            }
            if (!empty($filters['usuario'])) {
                $sql .= " AND u.nome LIKE ?";
                $params[] = '%' . $filters['usuario'] . '%';
            }
            if (!empty($filters['destinacao'])) {
                $sql .= " AND s.destinacao LIKE ?";
                $params[] = '%' . $filters['destinacao'] . '%';
            }
            if (!empty($filters['data'])) {
                $sql .= " AND DATE(pt.criado_em) = ?";
                $params[] = $filters['data'];
            }
            $limit = max(1, intval($limit));
            $offset = max(0, intval($offset));
            $sql .= " ORDER BY pt.criado_em DESC LIMIT $limit OFFSET $offset";
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$result) {
                $result['dados_calculados'] = json_decode($result['dados_calculados'], true);
                $result['honorarios_calculados'] = json_decode($result['honorarios_calculados'], true);
            }
            return $results;
        } catch (Exception $e) {
            error_log('[PlanejamentoTributario::allWithUserAndSimulacao] Erro: ' . $e->getMessage());
            return [];
        }
    }
} 