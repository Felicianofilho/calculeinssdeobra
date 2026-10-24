<?php
require_once __DIR__ . '/../Utils/Database.php';

class Simulacao
{
    public static function create($data)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO simulacoes 
            (user_id, responsavel, regime_tributario, nome, destinacao, tipo_obra, uf, area, municipio, endereco, 
             valor_inss, vau_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['user_id'],
            $data['responsavel'] ?? 'Pessoa Física',
            $data['regime_tributario'] ?? null,
            $data['nome'],
            $data['destinacao'],
            $data['tipo_obra'],
            $data['uf'],
            $data['area'],
            $data['municipio'],
            $data['endereco'],
            $data['valor_inss'],
            $data['vau_id']
        ]);
    }

    public static function allByUser($user_id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM simulacoes WHERE user_id = ? ORDER BY criado_em DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public static function find($id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM simulacoes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function all()
    {
        $pdo = Database::getConnection();
        $user_type = $_SESSION['user_type'] ?? 'lead';

        // Base query with LEFT JOIN to check for 'planejamento_tributario'
        $base_sql = "SELECT s.*, 
               CASE WHEN pt.simulacao_id IS NOT NULL THEN 1 ELSE 0 END as has_planejamento
                FROM simulacoes s
                LEFT JOIN planejamento_tributario pt ON s.id = pt.simulacao_id";

        if ($user_type === 'admin') {
            // Excluir simulações de superadmin
            $sql = $base_sql . " JOIN users u ON s.user_id = u.id WHERE u.user_type != 'superadmin' ORDER BY s.criado_em DESC";
            $stmt = $pdo->query($sql);
        } else {
            // superadmin ou outros: retorna todas
            $sql = $base_sql . " ORDER BY s.criado_em DESC";
            $stmt = $pdo->query($sql);
        }
        return $stmt->fetchAll();
    }

    public static function update($id, $data)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE simulacoes SET 
            responsavel = ?, regime_tributario = ?, nome = ?, destinacao = ?, tipo_obra = ?, uf = ?, area = ?, 
            municipio = ?, endereco = ?, valor_inss = ?, vau_id = ? WHERE id = ?");
        return $stmt->execute([
            $data['responsavel'] ?? 'Pessoa Física',
            $data['regime_tributario'] ?? null,
            $data['nome'],
            $data['destinacao'],
            $data['tipo_obra'],
            $data['uf'],
            $data['area'],
            $data['municipio'],
            $data['endereco'],
            $data['valor_inss'],
            $data['vau_id'],
            $id
        ]);
    }

    public static function delete($id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM simulacoes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Excluir todas as simulações de um usuário.
     *
     * @param int $user_id ID do usuário
     * @return bool Sucesso da operação
     */
    public static function deleteByUser($user_id)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM simulacoes WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    }

    /**
     * Busca simulações com filtros
     * 
     * @param int $userId ID do usuário
     * @param array $filters Filtros aplicados
     * @param int $limit Limite de registros (padrão: 100)
     * @return array Lista de simulações
     */
    public static function search($userId, $filters = [], $limit = 100)
    {
        $pdo = Database::getConnection();
        
        $sql = "SELECT s.*, 
               CASE WHEN pt.simulacao_id IS NOT NULL THEN 1 ELSE 0 END as has_planejamento
                FROM simulacoes s
                LEFT JOIN planejamento_tributario pt ON s.id = pt.simulacao_id
                WHERE s.user_id = ?";
        $params = [$userId];
        
        // Aplicar filtros
        if (!empty($filters['nome'])) {
            $sql .= " AND nome LIKE ?";
            $params[] = '%' . $filters['nome'] . '%';
        }
        
        if (!empty($filters['destinacao'])) {
            $sql .= " AND destinacao = ?";
            $params[] = $filters['destinacao'];
        }

        if (!empty($filters['responsavel'])) {
            $sql .= " AND responsavel = ?";
            $params[] = $filters['responsavel'];
        }

        if (!empty($filters['uf'])) {
            $sql .= " AND uf = ?";
            $params[] = $filters['uf'];
        }

        if (!empty($filters['criado_em_inicio'])) {
            $sql .= " AND DATE(criado_em) >= ?";
            $params[] = $filters['criado_em_inicio'];
        }

        if (!empty($filters['criado_em_fim'])) {
            $sql .= " AND DATE(criado_em) <= ?";
            $params[] = $filters['criado_em_fim'];
        }
        
        $sql .= " ORDER BY criado_em DESC LIMIT " . intval($limit);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Busca simulações para exportação CSV (sem limite)
     * 
     * @param int $userId ID do usuário
     * @param array $filters Filtros aplicados
     * @return array Lista de simulações
     */
    public static function searchForExport($userId, $filters = [])
    {
        $pdo = Database::getConnection();
        
        $sql = "SELECT * FROM simulacoes WHERE user_id = ?";
        $params = [$userId];
        
        // Aplicar filtros
        if (!empty($filters['nome'])) {
            $sql .= " AND nome LIKE ?";
            $params[] = '%' . $filters['nome'] . '%';
        }
        
        if (!empty($filters['destinacao'])) {
            $sql .= " AND destinacao = ?";
            $params[] = $filters['destinacao'];
        }

        if (!empty($filters['responsavel'])) {
            $sql .= " AND responsavel = ?";
            $params[] = $filters['responsavel'];
        }

        if (!empty($filters['uf'])) {
            $sql .= " AND uf = ?";
            $params[] = $filters['uf'];
        }

        if (!empty($filters['criado_em_inicio'])) {
            $sql .= " AND DATE(criado_em) >= ?";
            $params[] = $filters['criado_em_inicio'];
        }

        if (!empty($filters['criado_em_fim'])) {
            $sql .= " AND DATE(criado_em) <= ?";
            $params[] = $filters['criado_em_fim'];
        }
        
        $sql .= " ORDER BY criado_em DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Conta total de simulações com filtros
     * 
     * @param int $userId ID do usuário
     * @param array $filters Filtros aplicados
     * @return int Total de registros
     */
    public static function count($userId, $filters = [])
    {
        $pdo = Database::getConnection();
        
        $sql = "SELECT COUNT(*) as total FROM simulacoes WHERE user_id = ?";
        $params = [$userId];
        
        // Aplicar filtros
        if (!empty($filters['nome'])) {
            $sql .= " AND nome LIKE ?";
            $params[] = '%' . $filters['nome'] . '%';
        }
        
        if (!empty($filters['destinacao'])) {
            $sql .= " AND destinacao = ?";
            $params[] = $filters['destinacao'];
        }

        if (!empty($filters['responsavel'])) {
            $sql .= " AND responsavel = ?";
            $params[] = $filters['responsavel'];
        }

        if (!empty($filters['uf'])) {
            $sql .= " AND uf = ?";
            $params[] = $filters['uf'];
        }

        if (!empty($filters['criado_em_inicio'])) {
            $sql .= " AND DATE(criado_em) >= ?";
            $params[] = $filters['criado_em_inicio'];
        }

        if (!empty($filters['criado_em_fim'])) {
            $sql .= " AND DATE(criado_em) <= ?";
            $params[] = $filters['criado_em_fim'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['total'];
    }
}