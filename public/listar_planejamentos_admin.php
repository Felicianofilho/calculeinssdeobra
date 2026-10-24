<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/PlanejamentoTributario.php';
header('Content-Type: application/json');

// Verificar autenticação e permissões
if (!Auth::check() || !Auth::canAccessAdminPanel()) {
    echo json_encode(['html' => '<tr><td colspan="7" class="text-center text-danger">Acesso negado.</td></tr>', 'total' => 0]);
    exit;
}

if (!isset($_GET['offset'])) $_GET['offset'] = 0;
if (!isset($_GET['limit'])) $_GET['limit'] = 6;
$offset = intval($_GET['offset']);
$limit = intval($_GET['limit']);
$filtrosPlanejamento = [
    'usuario' => $_GET['usuario'] ?? '',
    'nome' => $_GET['nome'] ?? '',
    'destinacao' => $_GET['destinacao'] ?? '',
    'data' => $_GET['data'] ?? ''
];
$planejamentos = PlanejamentoTributario::allWithUserAndSimulacao($filtrosPlanejamento, $limit, $offset);
$total = PlanejamentoTributario::allWithUserAndSimulacao($filtrosPlanejamento, 1000000, 0); // para saber o total
$total = count($total);
$html = '';
if (empty($planejamentos)) {
    $html .= '<tr><td colspan="7" class="text-center text-muted">Nenhum planejamento encontrado.</td></tr>';
} else {
    // Função para formatar data no formato Mês/Ano
    function formatarDataPeriodo($data) {
        if (empty($data) || $data === '0000-00-00') return 'N/A';
        $meses = [
            '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', 
            '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', 
            '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
        ];
        $timestamp = strtotime($data);
        $mes = date('m', $timestamp);
        $ano = date('Y', $timestamp);
        return $meses[$mes] . '/' . $ano;
    }

    foreach ($planejamentos as $p) {
        $html .= '<tr>';
        $html .= '<td>' . $p['id'] . '</td>';
        $usuario_nome = htmlspecialchars($p['usuario_nome']);
        if (!empty($p['created_by_admin'])) {
            $usuario_nome .= ' <i class="fas fa-user-cog text-info ms-2" title="Planejamento criado por um Administrador"></i>';
        }
        $html .= '<td>' . $usuario_nome . '</td>';
        $html .= '<td>' . htmlspecialchars($p['simulacao_nome']) . '</td>';
        $html .= '<td>' . htmlspecialchars($p['destinacao']) . '</td>';
        $html .= '<td>' . formatarDataPeriodo($p['periodo_inicio']) . ' a ' . formatarDataPeriodo($p['periodo_fim']) . '</td>';
        $html .= '<td>' . date('d/m/Y', strtotime($p['criado_em'])) . '</td>';
        $html .= '<td>';
        $html .= '<div class="btn-group" role="group">';
        $html .= '<a href="planejamento_tributario.php?simulacao_id=' . urlencode($p['simulacao_id']) . '" class="btn btn-outline-warning" title="Editar Planejamento"><i class="fas fa-edit"></i></a>';
        $html .= '<a href="uploads/planejamentos/' . preg_replace('/[^a-z0-9_\-]/i', '_', strtolower($p['simulacao_nome'])) . '-' . $p['simulacao_id'] . '.pdf" class="btn btn-outline-success" target="_blank" title="Baixar PDF"><i class="fas fa-file-pdf"></i></a>';
        $html .= '<button type="button" class="btn btn-outline-danger" onclick="submeterExclusaoPlanejamento(' . $p['id'] . ', this)" title="Excluir Planejamento"><i class="fas fa-trash"></i></button>';
        $html .= '</div>';
        $html .= '</td>';
        $html .= '</tr>';
    }
}
echo json_encode(['html' => $html, 'total' => $total]); 