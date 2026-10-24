<?php

require_once __DIR__ . '/../Utils/Database.php';

class ConcretoUsinado
{
    private static $pdo;
    
    public function __construct()
    {
        self::$pdo = Database::getConnection();
    }
    
    /**
     * Busca percentual de concreto usinado por UF e destinação
     * 
     * @param string $uf UF da obra
     * @param string $destinacao Destinação da obra
     * @return float Percentual de concreto usinado
     */
    public static function buscarPercentual($uf, $destinacao)
    {
        $pdo = Database::getConnection();
        
        // Mapear destinação para coluna da tabela
        $coluna = self::mapearDestinacaoParaColuna($destinacao);
        
        if (!$coluna) {
            return 0.0; // Destinação não encontrada
        }
        
        $stmt = $pdo->prepare("SELECT {$coluna} FROM concreto_usinado WHERE uf = ?");
        $stmt->execute([$uf]);
        $resultado = $stmt->fetch();
        
        return $resultado ? floatval($resultado[$coluna]) : 0.0;
    }
    
    /**
     * Mapeia a destinação para o nome da coluna na tabela
     * 
     * @param string $destinacao Destinação da obra
     * @return string|null Nome da coluna ou null se não encontrada
     */
    private static function mapearDestinacaoParaColuna($destinacao)
    {
        $mapeamento = [
            'Casa Popular' => 'casa_popular',
            'Comercial Salas/Lojas' => 'comercial_salas_lojas',
            'Conj. Hab. Popular' => 'conj_hab_popular',
            'Galpão Ind.' => 'galpao_ind',
            'Res. Multifamiliar' => 'res_multifamiliar',
            'Res. Unifamiliar' => 'res_unifamiliar',
            'Edifício de Garagens' => 'edificio_garagens'
        ];
        
        return $mapeamento[$destinacao] ?? null;
    }
    
    /**
     * Lista todos os registros de concreto usinado
     * 
     * @return array Lista de registros
     */
    public static function listarTodos()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM concreto_usinado ORDER BY uf");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca registro por UF
     * 
     * @param string $uf UF
     * @return array|null Registro encontrado ou null
     */
    public static function buscarPorUF($uf)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM concreto_usinado WHERE uf = ?");
        $stmt->execute([$uf]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualiza ou insere registro de concreto usinado
     * 
     * @param array $dados Dados do registro
     * @return bool Sucesso da operação
     */
    public static function salvar($dados)
    {
        $pdo = Database::getConnection();
        
        $sql = "INSERT INTO concreto_usinado (
            uf, casa_popular, comercial_salas_lojas, conj_hab_popular, 
            galpao_ind, res_multifamiliar, res_unifamiliar, edificio_garagens
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            casa_popular = VALUES(casa_popular),
            comercial_salas_lojas = VALUES(comercial_salas_lojas),
            conj_hab_popular = VALUES(conj_hab_popular),
            galpao_ind = VALUES(galpao_ind),
            res_multifamiliar = VALUES(res_multifamiliar),
            res_unifamiliar = VALUES(res_unifamiliar),
            edificio_garagens = VALUES(edificio_garagens),
            data_importacao = CURRENT_TIMESTAMP";
        
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([
            $dados['uf'],
            $dados['casa_popular'],
            $dados['comercial_salas_lojas'],
            $dados['conj_hab_popular'],
            $dados['galpao_ind'],
            $dados['res_multifamiliar'],
            $dados['res_unifamiliar'],
            $dados['edificio_garagens']
        ]);
    }
    
    /**
     * Limpa todos os registros da tabela
     * 
     * @return bool Sucesso da operação
     */
    public static function limparTabela()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("TRUNCATE TABLE concreto_usinado");
        return $stmt->execute();
    }
    
    /**
     * Conta total de registros
     * 
     * @return int Total de registros
     */
    public static function contarRegistros()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM concreto_usinado");
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($resultado['total']);
    }
    
    /**
     * Obtém estatísticas da tabela
     * 
     * @return array Estatísticas
     */
    public static function obterEstatisticas()
    {
        $pdo = Database::getConnection();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total_registros FROM concreto_usinado");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total_registros'];
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT uf) as ufs_cobertas FROM concreto_usinado");
        $ufs_cobertas = $stmt->fetch(PDO::FETCH_ASSOC)['ufs_cobertas'];
        
        return [
            'total_registros' => intval($total),
            'ufs_cobertas' => intval($ufs_cobertas)
        ];
    }
}
