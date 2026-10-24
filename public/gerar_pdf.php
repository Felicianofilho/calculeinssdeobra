<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Controllers/SimulacaoController.php';
require_once __DIR__ . '/../src/Models/RedutorConcretoUsinado.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/PerformanceControl.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Aplicar delay de performance antes da geração do PDF
PerformanceControl::applyDelay('pdf_generation');

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'];
$dados = SimulacaoController::verSimulacao($id);

if (!$dados) {
    header('Location: dashboard.php');
    exit;
}

// Verificar permissão: usuário deve ser o dono da simulação OU ser administrador
if ($dados['user_id'] != $_SESSION['user_id'] && !Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

// Recalcular valores para garantir consistência
$data_inicio = $dados['data_inicio'];
$mes = intval(date('m', strtotime($data_inicio)));
$ano = intval(date('Y', strtotime($data_inicio)));

$pdo = Database::getConnection();

// Buscar VAU
$stmt = $pdo->prepare("SELECT valor FROM vau WHERE uf = ? AND destinacao = ? AND mes = ? AND ano = ? LIMIT 1");
$stmt->execute([$dados['uf'], $dados['destinacao'], $mes, $ano]);
$vau_row = $stmt->fetch();

if (!$vau_row) {
    $stmt = $pdo->prepare("SELECT valor FROM vau WHERE uf = ? AND destinacao = ? ORDER BY ano DESC, mes DESC LIMIT 1");
    $stmt->execute([$dados['uf'], $dados['destinacao']]);
    $vau_row = $stmt->fetch();
}

$vau = $vau_row ? floatval($vau_row['valor']) : 2000;
$area = floatval($dados['area'] ?? 0);
$pre_fabricados = $dados['pre_fabricados'] ?? 0;
$percentual_pre_fabricados = floatval($dados['percentual_pre_fabricados'] ?? 0);
$social = $dados['social'] ?? 0;
$aliquota = 0.2;
$utiliza_concreto_usinado = $dados['utiliza_concreto_usinado'] ?? 0;

// Redutor concreto usinado
$percentual_reducao_concreto = 0;
if ($utiliza_concreto_usinado == 1) {
    $percentual_reducao_concreto = RedutorConcretoUsinado::getPercentual($dados['uf'], $dados['destinacao']);
}

// Cálculos detalhados
$rmt_base = $vau * $area;
$valor_sem_fator = $rmt_base;
$reducao_concreto = $rmt_base * ($percentual_reducao_concreto / 100);
$rmt_apos_concreto = $rmt_base - $reducao_concreto;
$reducao_prefab = $rmt_apos_concreto * ($pre_fabricados ? ($percentual_pre_fabricados / 100) : 0);
$rmt_apos_prefab = $rmt_apos_concreto - $reducao_prefab;
$reducao_social = $rmt_apos_prefab * ($social ? 0.3 : 0);
$valor_com_fator = $rmt_apos_prefab - $reducao_social;
$valor_inss = $valor_com_fator * $aliquota;

// Honorários (exemplo: 10% de economia total, pode ser ajustado conforme regra do sistema)
$honorarios = $valor_sem_fator > 0 ? ($valor_sem_fator - $valor_com_fator) * 0.3 : 0; // Exemplo: 30% da redução
$economia_liquida = ($valor_sem_fator - $valor_com_fator) - $honorarios;
$percentual_economia_liquida = $valor_sem_fator > 0 ? ($economia_liquida / $valor_sem_fator) * 100 : 0;

// Calcular totais
$economia_total = $rmt_base - $rmt_ajustada;
$percentual_economia = $rmt_base > 0 ? ($economia_total / $rmt_base) * 100 : 0;

// Dados para o relatório
$data_geracao = date('d/m/Y H:i:s');
$duracao_meses = max(1, intval(date('m', strtotime($dados['data_fim']))) - intval(date('m', strtotime($dados['data_inicio']))) + 1);

// HTML do PDF com design profissional
$html = '
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Simulação INSS - ' . htmlspecialchars($dados['nome']) . '</title>
    <style>
        @page {
            margin: 15mm;
            size: A4;
        }
        
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #007bff;
            font-size: 24px;
            margin: 0 0 5px 0;
            font-weight: bold;
        }
        
        .header h2 {
            color: #6c757d;
            font-size: 16px;
            margin: 0 0 10px 0;
            font-weight: normal;
        }
        
        .header .meta {
            color: #6c757d;
            font-size: 10px;
        }
        
        .summary-box {
            background: #007bff;
            color: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            margin: 15px 0;
            border: 2px solid #0056b3;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .summary-label {
            font-size: 12px;
            margin: 0;
        }
        
        .metrics-grid {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
        }
        
        .metric-item {
            display: inline-block;
            width: 24%;
            text-align: center;
            padding: 12px 5px;
            border: 1px solid #e9ecef;
            background: #f8f9fa;
            vertical-align: top;
        }
        
        .metric-value {
            font-size: 16px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 3px;
        }
        
        .metric-label {
            font-size: 10px;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .section {
            margin: 15px 0;
        }
        
        .section-title {
            background: #007bff;
            color: white;
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-cell {
            display: table-cell;
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            vertical-align: top;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
            width: 25%;
            background: #f8f9fa;
        }
        
        .info-value {
            color: #212529;
        }
        
        .calculation-steps {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .calc-step {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
        
        .calc-step:last-child {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .calc-description {
            display: table-cell;
            width: 40%;
            font-weight: bold;
            vertical-align: middle;
        }
        
        .calc-formula {
            display: table-cell;
            width: 35%;
            text-align: center;
            font-family: monospace;
            color: #6c757d;
            vertical-align: middle;
        }
        
        .calc-result {
            display: table-cell;
            width: 25%;
            text-align: right;
            font-weight: bold;
            color: #007bff;
            vertical-align: middle;
        }
        
        .reducers-applied {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .reducer-item {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            margin: 3px;
            font-size: 10px;
        }
        
        .footer {
            position: fixed;
            bottom: 10mm;
            left: 15mm;
            right: 15mm;
            text-align: center;
            font-size: 9px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
            padding-top: 8px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .highlight-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .technical-specs {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .tech-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 10px;
            border: 1px solid #e9ecef;
        }
        
        .tech-icon {
            font-size: 16px;
            margin-bottom: 5px;
            color: #007bff;
        }
        
        .tech-status {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .tech-detail {
            font-size: 9px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO DE SIMULAÇÃO INSS</h1>
        <h2>' . htmlspecialchars($dados['nome']) . '</h2>
        <div class="meta">
            Gerado em ' . $data_geracao . ' | Usuário: ' . htmlspecialchars($user['nome']) . '
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-value">R$ ' . number_format($valor_inss, 2, ',', '.') . '</div>
        <div class="summary-label">VALOR ESTIMADO DO INSS</div>
    </div>

    <div class="metrics-grid">
        <div class="metric-item">
            <div class="metric-value">R$ ' . number_format($valor_sem_fator, 2, ',', '.') . '</div>
            <div class="metric-label">Sem Fator de Ajuste</div>
        </div><!--
        --><div class="metric-item">
            <div class="metric-value">R$ ' . number_format($valor_com_fator, 2, ',', '.') . '</div>
            <div class="metric-label">Com Fator de Ajuste</div>
        </div><!--
        --><div class="metric-item">
            <div class="metric-value">R$ ' . number_format($valor_sem_fator - $valor_com_fator, 2, ',', '.') . '</div>
            <div class="metric-label">Redução Obtida</div>
        </div><!--
        --><div class="metric-item">
            <div class="metric-value">R$ ' . number_format($honorarios, 2, ',', '.') . '</div>
            <div class="metric-label">Honorários</div>
        </div><!--
        --><div class="metric-item">
            <div class="metric-value">R$ ' . number_format($economia_liquida, 2, ',', '.') . '</div>
            <div class="metric-label">Economia Líquida</div>
        </div><!--
        --><div class="metric-item">
            <div class="metric-value">' . number_format($percentual_economia_liquida, 1, ',', '.') . '%</div>
            <div class="metric-label">% Economia Líquida</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">DADOS COMPLETOS DA OBRA</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-cell info-label">Nome da Simulação</div>
                <div class="info-cell info-value">' . htmlspecialchars($dados['nome']) . '</div>
                <div class="info-cell info-label">Tipo de Obra</div>
                <div class="info-cell info-value">' . htmlspecialchars($dados['destinacao']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-cell info-label">UF</div>
                <div class="info-cell info-value">' . htmlspecialchars($dados['uf']) . '</div>
                <div class="info-cell info-label">Município</div>
                <div class="info-cell info-value">' . htmlspecialchars($dados['municipio']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-cell info-label">Endereço</div>
                <div class="info-cell info-value" colspan="3">' . htmlspecialchars($dados['endereco']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-cell info-label">Área Construída</div>
                <div class="info-cell info-value">' . number_format($area, 2, ',', '.') . ' m²</div>
                <div class="info-cell info-label">VAU Utilizado</div>
                <div class="info-cell info-value">R$ ' . number_format($vau, 2, ',', '.') . '</div>
            </div>
            <div class="info-row">
                <div class="info-cell info-label">Data de Início</div>
                <div class="info-cell info-value">' . date('d/m/Y', strtotime($dados['data_inicio'])) . '</div>
                <div class="info-cell info-label">Data de Término</div>
                <div class="info-cell info-value">' . date('d/m/Y', strtotime($dados['data_fim'])) . '</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">CONFIGURAÇÕES TÉCNICAS</div>
        <div class="technical-specs">
            <div class="tech-item">
                <div class="tech-icon">🏗️</div>
                <div class="tech-status">' . ($pre_fabricados ? 'ATIVO' : 'INATIVO') . '</div>
                <div class="tech-detail">Pré-fabricados' . ($pre_fabricados ? '<br>' . number_format($percentual_pre_fabricados, 1, ',', '.') . '% utilizado' : '') . '</div>
            </div>
            <div class="tech-item">
                <div class="tech-icon">🏠</div>
                <div class="tech-status">' . ($social ? 'SIM' : 'NÃO') . '</div>
                <div class="tech-detail">Obra Social' . ($social ? '<br>30% de redução' : '') . '</div>
            </div>
            <div class="tech-item">
                <div class="tech-icon">🚛</div>
                <div class="tech-status">' . ($utiliza_concreto_usinado ? 'SIM' : 'NÃO') . '</div>
                <div class="tech-detail">Concreto Usinado' . ($utiliza_concreto_usinado && $percentual_reducao_concreto > 0 ? '<br>' . number_format($percentual_reducao_concreto, 1, ',', '.') . '% redução' : '') . '</div>
            </div>
            <div class="tech-item">
                <div class="tech-icon">📊</div>
                <div class="tech-status">20%</div>
                <div class="tech-detail">Alíquota INSS<br>Padrão aplicado</div>
            </div>
        </div>
    </div>';

// Adicionar redutores aplicados se houver
$redutores_aplicados = [];
if ($utiliza_concreto_usinado && $percentual_reducao_concreto > 0) {
    $redutores_aplicados[] = 'Concreto Usinado: ' . number_format($percentual_reducao_concreto, 1, ',', '.') . '%';
}
if ($pre_fabricados && $percentual_pre_fabricados > 0) {
    $redutores_aplicados[] = 'Pré-fabricados: ' . number_format($percentual_pre_fabricados, 1, ',', '.') . '%';
}
if ($social) {
    $redutores_aplicados[] = 'Obra Social: 30%';
}

if (!empty($redutores_aplicados)) {
    $html .= '
    <div class="reducers-applied">
        <strong>Redutores Aplicados:</strong><br>';
    foreach ($redutores_aplicados as $redutor) {
        $html .= '<span class="reducer-item">' . $redutor . '</span>';
    }
    $html .= '
    </div>';
}

$html .= '
    <div class="section">
        <div class="section-title">CÁLCULOS DETALHADOS PASSO A PASSO</div>
        <div class="calculation-steps">
            <div class="calc-step">
                <div class="calc-description">1. Cálculo da RMT Base</div>
                <div class="calc-formula">VAU × Área = R$ ' . number_format($vau, 2, ',', '.') . ' × ' . number_format($area, 2, ',', '.') . ' m²</div>
                <div class="calc-result">R$ ' . number_format($rmt_base, 2, ',', '.') . '</div>
            </div>';

$step = 2;
if ($utiliza_concreto_usinado && $percentual_reducao_concreto > 0) {
    $html .= '
            <div class="calc-step">
                <div class="calc-description">' . $step . '. Redução Concreto Usinado</div>
                <div class="calc-formula">R$ ' . number_format($rmt_base, 2, ',', '.') . ' - R$ ' . number_format($reducao_concreto, 2, ',', '.') . ' (' . number_format($percentual_reducao_concreto, 1, ',', '.') . '%)</div>
                <div class="calc-result">R$ ' . number_format($rmt_apos_concreto, 2, ',', '.') . '</div>
            </div>';
    $step++;
}

if ($pre_fabricados && $percentual_pre_fabricados > 0) {
    $html .= '
            <div class="calc-step">
                <div class="calc-description">' . $step . '. Redução Pré-fabricados</div>
                <div class="calc-formula">R$ ' . number_format($rmt_apos_concreto, 2, ',', '.') . ' - R$ ' . number_format($reducao_prefab, 2, ',', '.') . ' (' . number_format($percentual_pre_fabricados, 1, ',', '.') . '%)</div>
                <div class="calc-result">R$ ' . number_format($rmt_apos_prefab, 2, ',', '.') . '</div>
            </div>';
    $step++;
}

if ($social) {
    $html .= '
            <div class="calc-step">
                <div class="calc-description">' . $step . '. Redução Obra Social</div>
                <div class="calc-formula">R$ ' . number_format($rmt_apos_prefab, 2, ',', '.') . ' - R$ ' . number_format($reducao_social, 2, ',', '.') . ' (30%)</div>
                <div class="calc-result">R$ ' . number_format($valor_com_fator, 2, ',', '.') . '</div>
            </div>';
    $step++;
}

$html .= '
            <div class="calc-step">
                <div class="calc-description">' . $step . '. Cálculo Final do INSS</div>
                <div class="calc-formula">R$ ' . number_format($valor_com_fator, 2, ',', '.') . ' × 20% (alíquota)</div>
                <div class="calc-result">R$ ' . number_format($valor_inss, 2, ',', '.') . '</div>
            </div>
        </div>
    </div>

    <div class="success-box">
        <strong>💰 ECONOMIA LÍQUIDA OBTIDA:</strong><br>
        <div style="font-size: 18px; margin-top: 8px;">
            <strong>R$ ' . number_format($economia_liquida, 2, ',', '.') . '</strong> 
            (' . number_format($percentual_economia_liquida, 1, ',', '.') . '% de economia líquida em relação ao valor original)
        </div>
        <div style="font-size: 13px; margin-top: 8px; color: #333;">
            Após aplicar todos os fatores e considerar os honorários do contador, a economia líquida obtida foi de <strong>R$ ' . number_format($economia_liquida, 2, ',', '.') . '</strong>, representando <strong>' . number_format($percentual_economia_liquida, 1, ',', '.') . '%</strong> de economia em relação ao valor original.
        </div>
    </div>

    <div class="highlight-box">
        <strong>ℹ️ INFORMAÇÕES IMPORTANTES:</strong><br>
        • Este relatório foi gerado automaticamente pelo Sistema de Simulação INSS<br>
        • Os valores são estimativos e baseados nos dados fornecidos<br>
        • O VAU utilizado corresponde ao período de ' . date('m/Y', strtotime($dados['data_inicio'])) . ' para ' . htmlspecialchars($dados['uf']) . ' - ' . htmlspecialchars($dados['destinacao']) . '<br>
        • Consulte sempre a legislação vigente e profissionais especializados<br>
        • Duração estimada da obra: ' . $duracao_meses . ' meses
    </div>

    <div class="footer">
        Sistema de Simulação INSS | Relatório gerado em ' . $data_geracao . ' | Usuário: ' . htmlspecialchars($user['nome']) . ' | ID da Simulação: #' . $id . '
    </div>
</body>
</html>';

// Configurar e gerar o PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nome do arquivo
$nome_arquivo = 'Simulacao_INSS_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $dados['nome']) . '_' . date('Y-m-d_H-i') . '.pdf';

// Enviar o PDF
$dompdf->stream($nome_arquivo, ['Attachment' => false]);
exit;
?>

