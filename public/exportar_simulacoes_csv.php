<?php
require_once '../vendor/autoload.php';
require_once '../src/Utils/Auth.php';
require_once '../src/Utils/Database.php';

// Iniciar sessão se não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação
if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

// Obter usuário atual
$user = Auth::user();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Obter filtros da URL
$filters = [
    'nome' => $_GET['nome'] ?? '',
    'destinacao' => $_GET['destinacao'] ?? '',
    'responsavel' => $_GET['responsavel'] ?? '',
    'uf' => $_GET['uf'] ?? ''
];

try {
    $pdo = Database::getConnection();
    
    // Construir query base simples
    $sql = "SELECT s.*, 
                   CASE 
                       WHEN s.responsavel = 'Pessoa Jurídica' THEN s.regime_tributario 
                       ELSE '-' 
                   END as regime_tributario_display
            FROM simulacoes s 
            WHERE s.user_id = :user_id";
    
    $params = [':user_id' => $user['id']];
    
    // Aplicar filtros
    if (!empty($filters['nome'])) {
        $sql .= " AND s.nome LIKE :nome";
        $params[':nome'] = '%' . $filters['nome'] . '%';
    }
    
    if (!empty($filters['destinacao'])) {
        $sql .= " AND s.destinacao = :destinacao";
        $params[':destinacao'] = $filters['destinacao'];
    }
    
    if (!empty($filters['responsavel'])) {
        $sql .= " AND s.responsavel = :responsavel";
        $params[':responsavel'] = $filters['responsavel'];
    }
    
    if (!empty($filters['uf'])) {
        $sql .= " AND s.uf = :uf";
        $params[':uf'] = $filters['uf'];
    }
    
    $sql .= " ORDER BY s.criado_em DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $simulacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Configurar headers para download CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="simulacoes_inss_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Criar arquivo CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 (importante para Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos expandidos
    fputcsv($output, [
        'ID',
        'Nome da Simulação',
        'Responsável',
        'Regime Tributário',
        'Destinação',
        'Tipo de Obra',
        'UF',
        'Município',
        'Endereço',
        'Área (m²)',
        'VAU (R$/m²)',
        'Mês/Ano VAU',
        'Valor INSS (R$)',
        'Data da Simulação',
        'Data de Criação'
    ], ';');
    
    // Dados
    foreach ($simulacoes as $simulacao) {
        // Buscar VAU vigente na data da simulação
        $mes = (int)date('n', strtotime($simulacao['criado_em']));
        $ano = (int)date('Y', strtotime($simulacao['criado_em']));
        
        $stmt_vau = $pdo->prepare("SELECT valor, mes, ano FROM vau WHERE uf = ? AND destinacao = ? AND (ano < ? OR (ano = ? AND mes <= ?)) ORDER BY ano DESC, mes DESC LIMIT 1");
        $stmt_vau->execute([$simulacao['uf'], $simulacao['destinacao'], $ano, $ano, $mes]);
        $vau_info = $stmt_vau->fetch();
        
        if ($vau_info) {
            $vau_valor = $vau_info['valor'];
            $vau_mes = $vau_info['mes'];
            $vau_ano = $vau_info['ano'];
        } else {
            $vau_valor = 'N/A';
            $vau_mes = '';
            $vau_ano = '';
        }
        
        // Formatar mês/ano do VAU
        $vau_periodo = '';
        if ($vau_mes && $vau_ano) {
            $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            $vau_periodo = $meses[$vau_mes] . '/' . $vau_ano;
        }
        
        fputcsv($output, [
            $simulacao['id'],
            $simulacao['nome'],
            $simulacao['responsavel'] ?? 'Pessoa Física',
            $simulacao['regime_tributario_display'],
            $simulacao['destinacao'],
            $simulacao['tipo_obra'],
            $simulacao['uf'],
            $simulacao['municipio'] ?? '',
            $simulacao['endereco'] ?? '',
            number_format($simulacao['area'], 2, ',', '.'),
            is_numeric($vau_valor) ? number_format($vau_valor, 2, ',', '.') : $vau_valor,
            $vau_periodo,
            number_format($simulacao['valor_inss'], 2, ',', '.'),
            date('d/m/Y H:i', strtotime($simulacao['criado_em'])),
            date('d/m/Y H:i:s', strtotime($simulacao['criado_em']))
        ], ';');
    }
    
    fclose($output);
    
} catch (Exception $e) {
    // Em caso de erro, redirecionar de volta com mensagem
    header('Location: minhas_simulacoes.php?error=export_failed');
    exit;
}
?> 