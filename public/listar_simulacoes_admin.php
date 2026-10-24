<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';
require_once __DIR__ . '/../src/Models/User.php';
header('Content-Type: application/json');

if (!Auth::canAccessAdminPanel()) {
    http_response_code(403);
    exit('Acesso negado');
}

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 6;
$filtroUsuario = $_GET['usuario'] ?? '';
$filtroNome = $_GET['nome'] ?? '';
$filtroData = $_GET['data'] ?? '';

$usuarios = User::all();
$todasSimulacoes = Simulacao::all();

// Filtros
$simulacoesFiltradas = array_filter($todasSimulacoes, function($sim) use ($usuarios, $filtroUsuario, $filtroNome, $filtroData) {
    $usuarioNome = '';
    foreach ($usuarios as $usr) {
        if ($usr['id'] == $sim['user_id']) {
            $usuarioNome = $usr['nome'];
            break;
        }
    }
    $matchUsuario = $filtroUsuario === '' || stripos($usuarioNome, $filtroUsuario) !== false;
    $matchNome = $filtroNome === '' || stripos($sim['nome'], $filtroNome) !== false;
    $matchData = $filtroData === '' || (date('Y-m-d', strtotime($sim['criado_em'])) === $filtroData);
    return $matchUsuario && $matchNome && $matchData;
});
$simulacoesFiltradas = array_values($simulacoesFiltradas);
$total = count($simulacoesFiltradas);
$simulacoesExibir = array_slice($simulacoesFiltradas, $offset, $limit);
$html = '';
foreach ($simulacoesExibir as $sim) {
    $html .= '<tr data-date-raw="' . date('Y-m-d', strtotime($sim['criado_em'])) . '">';
    $html .= '<td>' . $sim['id'] . '</td>';
    $autor = 'Usuário não encontrado';
    foreach($usuarios as $usr) {
        if ($usr['id'] == $sim['user_id']) {
            $autor = htmlspecialchars($usr['nome']);
            break;
        }
    }
    $html .= '<td>' . $autor . '</td>';
    $html .= '<td><strong>' . htmlspecialchars($sim['nome']) . '</strong>';
    if ($sim['has_planejamento']) {
        $html .= ' <i class="fas fa-file-alt text-info ms-2" title="Esta simulação possui um Planejamento Tributário associado."></i>';
    }
    $html .= '</td>';
    $html .= '<td>' . htmlspecialchars($sim['destinacao'] ?? '-') . '</td>';
    $html .= '<td>' . htmlspecialchars($sim['responsavel'] ?? 'Pessoa Física') . '</td>';
    $html .= '<td>' . number_format($sim['area'], 2, ',', '.') . '</td>';
    $html .= '<td>' . date('d/m/Y', strtotime($sim['criado_em'])) . '</td>';
    $html .= '<td><div class="btn-group" role="group">';
    $html .= '<a href="relatorio_simulacao.php?id=' . $sim['id'] . '" class="btn btn-outline-primary" title="Visualizar"><i class="fas fa-eye"></i></a>';
    $html .= '<button type="button" class="btn btn-outline-info" onclick="window.location.href=\'duplicar_simulacao.php?id=' . $sim['id'] . '\'" title="Duplicar"><i class="fas fa-copy"></i></button>';
    $html .= '<a href="editar_simulacao.php?id=' . $sim['id'] . '" class="btn btn-outline-warning" title="Editar"><i class="fas fa-edit"></i></a>';
    $has_planejamento_js = $sim['has_planejamento'] ? 'true' : 'false';
    $html .= '<button type="button" class="btn btn-outline-danger" onclick="excluirSimulacao(' . $sim['id'] . ', ' . $has_planejamento_js . ', this)" title="Excluir"><i class="fas fa-trash"></i></button>';
    $html .= '</div></td>';
    $html .= '</tr>';
}
echo json_encode(['html' => $html, 'total' => $total]); 