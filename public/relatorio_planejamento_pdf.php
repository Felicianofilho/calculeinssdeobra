<?php
ob_start(); // Inicia o buffer de saída no topo do arquivo

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Controllers/PlanejamentoTributarioController.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Utils/ProcessLock.php';
require_once __DIR__ . '/../src/Utils/PerformanceControl.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Definir cabeçalho para JSON
header('Content-Type: application/json');

session_start();

$response = [
    'success' => false,
    'message' => ''
];

try {
    // Aplicar delay de performance antes da geração do PDF
    PerformanceControl::applyDelay('pdf');
    
    // Tentar adquirir o lock
    if (!ProcessLock::acquireLock('pdf_generation')) {
        $response['message'] = 'O sistema está ocupado gerando outro PDF. Por favor, tente novamente em alguns instantes.';
        echo json_encode($response);
        exit;
    }

    // Bloco try para garantir a liberação do lock
    try {
        if (!Auth::check()) {
            $response['message'] = 'Não autorizado.';
            echo json_encode($response);
            exit;
        }

        $user = Auth::user();
        $input = json_decode(file_get_contents('php://input'), true);

        $simulacao_id = $input['simulacao_id'] ?? null;
        $periodo_inicio = $input['periodo_inicio'] ?? null;
        $periodo_fim = $input['periodo_fim'] ?? null;
        $honorarios_valor = $input['honorarios_valor'] ?? 0;
        $honorarios_tipo = $input['honorarios_tipo'] ?? 'real';
        $concreto_usinado = $input['concreto_usinado'] ?? false;

        if (!$simulacao_id || !$periodo_inicio || !$periodo_fim) {
            $response['message'] = 'Parâmetros obrigatórios ausentes.';
            echo json_encode($response);
            exit;
        }

        $simulacao = Simulacao::find($simulacao_id);
        if (!$simulacao || ($simulacao['user_id'] != $user['id'] && !Auth::isAdmin())) {
            $response['message'] = 'Acesso negado.';
            echo json_encode($response);
            exit;
        }

        // Buscar dados do planejamento
        $resultado = PlanejamentoTributarioController::calcular(
            $simulacao_id,
            $user['id'],
            $periodo_inicio,
            $periodo_fim,
            false,
            null,
            0,
            'real',
            $concreto_usinado
        );
        if (isset($resultado['error'])) {
            $response['message'] = 'Erro ao calcular planejamento: ' . $resultado['error'];
            echo json_encode($response);
            exit;
        }
        $dados = $resultado['dados'] ?? [];
        $resumo = $resultado['resumo'] ?? [];

        // Calcular RMT base usando a mesma lógica da simulação
        require_once __DIR__ . '/../src/Utils/CalculoINSS.php';
        $dados_simulacao = $simulacao;
        if (isset($simulacao['criado_em'])) {
            $dados_simulacao['data_simulacao'] = $simulacao['criado_em'];
        }
        // Incluir concreto usinado nos dados
        $dados_simulacao['concreto_usinado'] = $concreto_usinado;
        $dados_calculo = CalculoINSS::calcular($dados_simulacao);
        $rmt_base = $dados_calculo['rmt_original']; // RMT original (antes da dedução)
        $rmt_final = $dados_calculo['rmt']; // RMT final (após dedução)

        // Configurações e branding
        $config = LocalConfig::getAll();
        $showLogo = $config['reports']['show_logo'] ?? true;
        $logoFile = $config['theme']['branding']['logo_file_report'] ?? $config['theme']['branding']['logo_file'] ?? '';
        $logoPath = __DIR__ . '/' . $logoFile;
        $logoToUse = (!empty($logoFile) && file_exists($logoPath)) ? $logoPath : '';
        $baseUrl = LocalConfig::getBaseUrl();
        $companyName = $config['company']['name'] ?? ($config['system']['name'] ?? 'Sistema Obra Legal');
        $reportTitle = 'Relatório de Planejamento Tributário';
        $footerText = $config['reports']['footer_text'] ?? 'Relatório gerado pelo Sistema Obra Legal';

        // Montar HTML do PDF
        $html = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Relatório de Planejamento Tributário</title><style>';
        $html .= '@page { margin: 15mm 12mm 15mm 12mm; size: A4; }';
        $html .= 'body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #222; background: #f8f9fa; }';
        $html .= '.header { text-align: center; margin-bottom: 12px; }';
        $html .= '.header .logo img { max-width: 400px; max-height: 100px; display: block; margin: 0 auto; }';
        $html .= '.header .company { font-size: 16px; font-weight: bold; color: #2c5aa0; margin-bottom: 2px; }';
        $html .= '.header .title { font-size: 14px; font-weight: 600; color: #007bff; margin-bottom: 4px; }';
        $html .= '.divider { border-bottom: 2px solid #007bff; margin: 8px 0 12px 0; }';
        $html .= '.card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(44,90,160,0.07); margin-bottom: 12px; padding: 12px 14px; border: 1px solid #e3e6f0; }';
        $html .= '.card-title { color: #007bff; font-size: 12px; font-weight: bold; margin-bottom: 6px; letter-spacing: 0.3px; }';
        $html .= '.summary-card { background: #eaf4ff; border: 2px solid #007bff; color: #007bff; text-align: center; font-size: 16px; font-weight: bold; border-radius: 10px; margin-bottom: 12px; padding: 14px 0 8px 0; }';
        $html .= '.summary-label { font-size: 11px; color: #007bff; font-weight: 600; margin-top: 2px; }';
        $html .= '.table-planning { width: 100%; border-collapse: collapse; margin-bottom: 12px; }';
        $html .= '.table-planning th { background: #007bff; color: #fff; font-weight: 600; text-align: center; padding: 4px; }';
        $html .= '.table-planning td { text-align: center; padding: 3px; border-bottom: 1px solid #e3e6f0; word-break: break-all; }';
        $html .= '.total-row { background: #e9ecef; font-weight: bold; font-size: 10px; }';
        $html .= '.comparison-card { border-radius: 8px !important; border: 1px solid #dee2e6 !important; margin-bottom: 1px !important; padding: 2px 1px !important; }';
        $html .= '.comparison-title { font-size: 7px !important; font-weight: bold !important; margin-bottom: 0 !important; }';
        $html .= '.comparison-value { font-size: 9px !important; font-weight: bold !important; }';
        $html .= '.comparison-cards-row { display: table !important; width: 100% !important; table-layout: fixed !important; border-spacing: 1px 0 !important; margin-bottom: 0 !important; }';
        $html .= '.footer { border-top: 2px solid #007bff; text-align: center; font-size: 9px; color: #6c757d; margin-top: 20px; padding-top: 6px; }';
        $html .= '</style></head><body>';

        // Cabeçalho
        $html .= '<div class="header">';
        if ($showLogo && !empty($logoToUse) && extension_loaded('gd')) {
            try {
                $imgData = base64_encode(file_get_contents($logoToUse));
                $imgSrc = 'data:image/png;base64,' . $imgData;
                $html .= '<div class="logo"><img src="' . $imgSrc . '" alt="Logo"></div>';
            } catch (Exception $e) {}
        } else {
            $html .= '<div class="company">' . htmlspecialchars($companyName) . '</div>';
        }
        $html .= '<div class="title">' . htmlspecialchars($reportTitle) . '</div>';
        $html .= '<div class="divider"></div>';
        $html .= '</div>';

        // Dados da Simulação
        $html .= '<div class="card"><div class="card-title">Dados da Simulação</div>';
        $html .= '<div style="font-size:12px;">';
        // Linha 1
        $html .= '<div>';
        $html .= '<b>Nome da Obra:</b> ' . htmlspecialchars($simulacao['nome'] ?? '-') . '&nbsp;&nbsp;&nbsp;';
        $html .= '<b>Endereço:</b> ' . htmlspecialchars($simulacao['endereco'] ?? '-') . '&nbsp;&nbsp;&nbsp;';
        $html .= '<b>Município:</b> ' . htmlspecialchars($simulacao['municipio'] ?? '-') . '&nbsp;&nbsp;&nbsp;';
        $html .= '<b>UF:</b> ' . htmlspecialchars($simulacao['uf'] ?? '-') . '';
        $html .= '</div>';
        // Linha 2
        $html .= '<div>';
        $html .= '<b>Destinação:</b> ' . htmlspecialchars($simulacao['destinacao'] ?? '-') . '&nbsp;&nbsp;&nbsp;';
        $html .= '<b>Tipo de Obra:</b> ' . htmlspecialchars($simulacao['tipo_obra'] ?? '-') . '&nbsp;&nbsp;&nbsp;';
        $html .= '<b>Concreto Usinado:</b> ' . ($concreto_usinado ? 'Sim' : 'Não') . '&nbsp;&nbsp;&nbsp;';
        $data_inicio = new DateTime($periodo_inicio);
        $data_fim = new DateTime($periodo_fim);
        $intervalo = $data_inicio->diff($data_fim);
        $meses = ($intervalo->y * 12) + $intervalo->m + 1;
        $html .= '<b>Período:</b> ' . $data_inicio->format('m/Y') . ' a ' . $data_fim->format('m/Y') . ' (' . $meses . ' meses)';
        $html .= '</div>';
        // Linha 3 destacada em card separado
        $html .= '<div class="summary-card" style="background:#eaf4ff; border:2px solid #007bff; color:#007bff; text-align:center; font-size:13px; font-weight:bold; border-radius:10px; margin-bottom:12px; padding:10px 0 6px 0;">';
        $html .= '<span><b>RMT Base:</b> R$ ' . number_format($rmt_base, 2, ',', '.') . '</span>&nbsp;&nbsp;&nbsp;';
        $html .= '<span><b>INSS Previsto:</b> R$ ' . number_format($simulacao['valor_inss'] ?? 0, 2, ',', '.') . '</span>&nbsp;&nbsp;&nbsp;';
        $html .= '<span><b>Área:</b> ' . number_format($simulacao['area'] ?? 0, 2, ',', '.') . ' m²</span>';
        $html .= '</div>';
        
        // Seção de dedução do concreto usinado (se aplicável)
        if ($concreto_usinado && isset($dados_calculo['deducao_concreto']) && $dados_calculo['deducao_concreto'] > 0) {
            $html .= '<div class="summary-card" style="background:#e8f5e8; border:2px solid #28a745; color:#28a745; text-align:center; font-size:12px; font-weight:bold; border-radius:10px; margin-bottom:12px; padding:8px 0 4px 0;">';
            $html .= '<div><b>DEDUÇÃO CONCRETO USINADO APLICADA</b></div>';
            $html .= '<div style="font-size:11px; margin-top:4px;">';
            $html .= '<span><b>COD:</b> R$ ' . number_format($dados_calculo['cod'], 2, ',', '.') . '</span>&nbsp;&nbsp;&nbsp;';
            $html .= '<span><b>% ' . $simulacao['uf'] . ':</b> ' . number_format($dados_calculo['percentual_concreto_uf'], 2, ',', '.') . '%</span>&nbsp;&nbsp;&nbsp;';
            $html .= '<span><b>Dedução:</b> R$ ' . number_format($dados_calculo['deducao_concreto'], 2, ',', '.') . '</span>&nbsp;&nbsp;&nbsp;';
            $html .= '<span><b>RMT Final:</b> R$ ' . number_format($rmt_final, 2, ',', '.') . '</span>';
            $html .= '</div>';
            $html .= '<div style="font-size:10px; margin-top:2px; font-style:italic;">Conforme IN RFB 2.021/2021 - Redução da base de cálculo do INSS</div>';
            $html .= '</div>';
        }
        
        // Seção: RMT com Desconto (CORREÇÃO: usar RMT Final quando houver concreto usinado)
        $area_obra = $simulacao['area'] ?? 0;
        // Determinar qual RMT usar para calcular o desconto
        $rmt_para_desconto = $concreto_usinado ? $rmt_final : $rmt_base;
        
        // Calcular RMT com desconto baseado na área
        if ($area_obra <= 350) {
            $rmt_com_desconto = $rmt_para_desconto * 0.5;
            $texto_desconto = 'RMT (50% obra até 350 m²):';
        } else {
            $rmt_com_desconto = $rmt_para_desconto * 0.7;
            $texto_desconto = 'RMT (70% acima de 350 m²):';
        }
        
        $html .= '<div class="summary-card" style="background:#eaf4ff; border:2px solid #007bff; color:#007bff; text-align:center; font-size:13px; font-weight:bold; border-radius:10px; margin-bottom:12px; padding:10px 0 6px 0;">';
        $html .= '<span><b>' . $texto_desconto . '</b> R$ ' . number_format($rmt_com_desconto, 2, ',', '.') . '</span>';
        $html .= '</div>';
        
        $html .= '</div></div>';

        // Tabela de Distribuição Mensal
        $html .= '<div class="card"><div class="card-title">Distribuição Mensal</div>';
        $html .= '<table class="table-planning"><thead><tr>';
        $html .= '<th>MÊS/ANO</th><th>REM. ATUAL.</th><th>REM. ORIG.</th><th>CPP</th><th>MULTA</th><th>SELIC</th><th>MORA</th><th>MAED</th><th>TOTAL</th>';
        $html .= '</tr></thead><tbody>';
        // Função para mês em português abreviado
        function mesAbreviadoPt($mes) {
            $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            if (is_numeric($mes) && $mes >= 1 && $mes <= 12) return $meses[(int)$mes];
            $en = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Ago','Set','Out','Nov','Dec'];
            $pt = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
            $idx = array_search(ucfirst(strtolower($mes)), $en);
            if ($idx !== false) return $pt[$idx];
            $idx = array_search(ucfirst(strtolower($mes)), $pt);
            if ($idx !== false) return $pt[$idx];
            return $mes;
        }
        foreach ($dados as $mes) {
            $html .= '<tr>';
            // Mês em português abreviado
            $mesLabel = '';
            if (!empty($mes['mes']) && !empty($mes['ano'])) {
                $mesLabel = mesAbreviadoPt($mes['mes']) . '/' . $mes['ano'];
            } elseif (!empty($mes['mesNome'])) {
                if (preg_match('/([A-Za-z]{3}|\d{2})\/?(\d{4})/', $mes['mesNome'], $m)) {
                    $mesLabel = mesAbreviadoPt($m[1]) . '/' . $m[2];
                } else {
                    $mesLabel = $mes['mesNome'];
                }
            } else {
                $mesLabel = '-';
            }
            $html .= '<td>' . htmlspecialchars($mesLabel) . '</td>';
            $html .= '<td>R$ ' . number_format($mes['remuneracaoAjustada'] ?? 0, 2, ',', '.') . '</td>';
            $html .= '<td>R$ ' . number_format($mes['remuneracaoOriginal'] ?? 0, 2, ',', '.') . '</td>';
            $html .= '<td>R$ ' . number_format($mes['cpp'] ?? 0, 2, ',', '.') . '</td>';
            $html .= '<td>R$ ' . number_format($mes['multa'] ?? 0, 2, ',', '.') . '</td>';
            $html .= '<td>' . number_format($mes['selic'] ?? 0, 2, ',', '.') . '%</td>';
            $html .= '<td>R$ ' . number_format($mes['mora'] ?? 0, 2, ',', '.') . '</td>';
            $html .= '<td>R$ ' . number_format($mes['maed'] ?? 0, 2, ',', '.') . '</td>';
            $html .= '<td>R$ ' . number_format($mes['totalMes'] ?? 0, 2, ',', '.') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        // Totais
        $html .= '<tfoot><tr class="total-row">';
        $html .= '<td><b>TOTAL</b></td>';
        $html .= '<td>R$ ' . number_format($resumo['total_remuneracao_ajustada'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td>R$ ' . number_format($resumo['total_remuneracao_original'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td>R$ ' . number_format($resumo['total_cpp'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td>R$ ' . number_format($resumo['total_multa'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td>' . number_format($resumo['total_selic'] ?? 0, 2, ',', '.') . '%</td>';
        $html .= '<td>R$ ' . number_format($resumo['total_mora'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td>R$ ' . number_format($resumo['total_maed'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td>R$ ' . number_format($resumo['total_geral'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '</tr></tfoot>';
        $html .= '</table></div>';

        // Observação sobre os cálculos
        $html .= '<div style="font-size: 10px; color: #6c757d; font-style: italic; margin-top: 8px; text-align: center;">Cálculos realizados com base no desconto de 50% da MULTA DA MAED, para pagamentos em até 30 dias.</div>';

        // Quebra de página
        $html .= '<div style="page-break-after: always;"></div>';

        // Cabeçalho na segunda página
        $html .= '<div class="header">';
        if ($showLogo && !empty($logoToUse) && extension_loaded('gd')) {
            try {
                $imgData = base64_encode(file_get_contents($logoToUse));
                $imgSrc = 'data:image/png;base64,' . $imgData;
                $html .= '<div class="logo"><img src="' . $imgSrc . '" alt="Logo"></div>';
            } catch (Exception $e) {}
        } else {
            $html .= '<div class="company">' . htmlspecialchars($companyName) . '</div>';
        }
        $html .= '<div class="title">' . htmlspecialchars($reportTitle) . '</div>';
        $html .= '<div class="divider"></div>';
        $html .= '</div>';

        // Cálculos para os cards comparativos
        // CORREÇÃO: Usar RMT Final × 36,8% quando concreto usinado for aplicado
        if ($concreto_usinado && isset($dados_calculo['rmt_original']) && isset($dados_calculo['deducao_concreto'])) {
            $rmt_final = $dados_calculo['rmt_original'] - $dados_calculo['deducao_concreto'];
            $valor_original = $rmt_final * 0.368; // RMT Final × 36,8%
            
            error_log("PDF CORREÇÃO - Concreto aplicado: RMT Final=" . $rmt_final . ", Valor Original=" . $valor_original);
        } else {
            $valor_original = $simulacao['valor_inss'] ?? 0; // Fallback para valor original
            error_log("PDF CORREÇÃO - Sem concreto: Valor Original=" . $valor_original);
        }
        $valor_otimizado = $resumo['total_geral'] ?? 0;
        $economia_obtida = $valor_original - $valor_otimizado;
        $percentual_economia = $valor_original > 0 ? ($economia_obtida / $valor_original) * 100 : 0;
        // Honorários: usar valores da URL (mais confiável)
        $honorarios = floatval($honorarios_valor);
        $tipo_honorario = $honorarios_tipo;

        // Se for percentual, calcular o valor real
        if ($tipo_honorario === 'percent') {
            $honorarios = ($economia_obtida * $honorarios) / 100;
        }

        error_log("DEBUG PDF - Honorários da URL: " . $honorarios_valor . " (tipo: " . $tipo_honorario . ") - Valor final: " . $honorarios);
        $economia_liquida = $economia_obtida - $honorarios;
        $percentual_economia_liquida = $valor_original > 0 ? ($economia_liquida / $valor_original) * 100 : 0;

        // Cards comparativos (tabela, alinhamento à esquerda)
        $html .= '<div class="card" style="margin-bottom:2px;"><div class="card-title" style="margin-bottom:2px;">Resultado do Planejamento</div>';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="table-layout:fixed;margin-bottom:0;border-collapse:separate;border-spacing:2px 0;">';
        $html .= '<tr>';
        // Determinar texto do card baseado no concreto usinado
        $card_texto_sem_fator = $concreto_usinado ? 'RMT - Conc. Usin.' : 'Valor Original';
        
        // Card 1
        $html .= '<td style="width:20%;vertical-align:top;border:2px solid #dc3545;border-radius:12px;padding:8px;text-align:left;">
    <div style="font-size:11px;font-weight:bold;margin-bottom:2px;color:#dc3545;text-align:left;">Sem Fator de Ajuste</div>
    <div style="font-size:14px;font-weight:bold;color:#dc3545;text-align:left;">R$ ' . number_format($valor_original, 2, ',', '.') . '</div>
    <div style="font-size:9px;text-align:left;">' . $card_texto_sem_fator . '</div>
</td>';
        // Card 2
        $html .= '<td style="width:20%;vertical-align:top;border:2px solid #28a745;border-radius:12px;padding:8px;text-align:left;">
    <div style="font-size:11px;font-weight:bold;margin-bottom:2px;color:#28a745;text-align:left;">Com Fator de Ajuste</div>
    <div style="font-size:14px;font-weight:bold;color:#28a745;text-align:left;">R$ ' . number_format($valor_otimizado, 2, ',', '.') . '</div>
    <div style="font-size:9px;text-align:left;">Valor Otimizado</div>
</td>';
        // Card 3
        $html .= '<td style="width:20%;vertical-align:top;border:2px solid #ffc107;border-radius:12px;padding:8px;text-align:left;">
    <div style="font-size:11px;font-weight:bold;margin-bottom:2px;color:#ffc107;text-align:left;">Economia Obtida</div>
    <div style="font-size:14px;font-weight:bold;color:#ffc107;text-align:left;">R$ ' . number_format($economia_obtida, 2, ',', '.') . '</div>
    <div style="font-size:9px;text-align:left;">Redução Total</div>
</td>';
        // Card 4 - Honorários
        $html .= '<td style="width:20%;vertical-align:top;border:2px solid #17a2b8;border-radius:12px;padding:8px;text-align:left;">
    <div style="font-size:11px;font-weight:bold;margin-bottom:2px;color:#17a2b8;text-align:left;">Honorários</div>
    <div style="font-size:14px;font-weight:bold;color:#17a2b8;text-align:left;">R$ ' . number_format($honorarios, 2, ',', '.') . '</div>
    <div style="font-size:9px;text-align:left;">Honorários do contador</div>
</td>';
        // Card 5 - Economia Líquida
        $html .= '<td style="width:20%;vertical-align:top;border:2px solid #28a745;border-radius:12px;padding:8px;text-align:left;background-color:#d4edda;">
    <div style="font-size:11px;font-weight:bold;margin-bottom:2px;color:#155724;text-align:left;">Economia Líquida</div>
    <div style="font-size:14px;font-weight:bold;color:#155724;text-align:left;">R$ ' . number_format($economia_liquida, 2, ',', '.') . '</div>
    <div style="font-size:9px;text-align:left;color:#155724;">' . number_format($percentual_economia_liquida, 1, ',', '.') . '% de economia líquida</div>
</td>';
        $html .= '</tr>';
        $html .= '</table></div>';

        // Proposta de Serviços
        $html .= '<div class="card" style="margin-top: 12px;"><div class="card-title">Proposta de Serviços</div>';
        $html .= '<div style="font-size: 11px; line-height: 1.4;">';
        $html .= '<div style="font-weight: bold; margin-bottom: 8px;">Serviços inclusos na contratação:</div>';
        $html .= '<div style="margin-left: 10px;">';
        $html .= '<div style="margin-bottom: 4px;">1 - Analisar e planejar a aplicação do Fator de Ajuste;</div>';
        $html .= '<div style="margin-bottom: 4px;">2 - Realizar o Cadastro Nacional de Obras-CNO junto à RFB;</div>';
        $html .= '<div style="margin-bottom: 4px;">3 - Calcular o INSS da obra;</div>';
        $html .= '<div style="margin-bottom: 4px;">4 - Realizar o cadastro dos prestadores de serviços no e-Social;</div>';
        $html .= '<div style="margin-bottom: 4px;">5 - Realizar as aferições mensais no SERO;</div>';
        $html .= '<div style="margin-bottom: 4px;">6 - Transmitir a DCTFWEB mensalmente durante todo o período de obra;</div>';
        $html .= '<div style="margin-bottom: 4px;">7 - Emitir a guia (DARF) para pagamento dos tributos aferidos no período;</div>';
        $html .= '<div style="margin-bottom: 4px;">8 - Realizar processos no Serviço Eletrônico de Aferição de Obras SERO;</div>';
        $html .= '<div style="margin-bottom: 4px;">9 - Realizar a aferição total da obra;</div>';
        $html .= '<div style="margin-bottom: 4px;">10 - Realizar o parcelamento do INSS de obra quando solicitado no final da obra;</div>';
        $html .= '<div style="margin-bottom: 4px;">11 - Emitir a Certidão Negativa com Efeitos de Positiva de Débitos ou;</div>';
        $html .= '<div style="margin-bottom: 4px;">12 - Emitir a Certidão Negativa de Débitos ao final da Obra;</div>';
        $html .= '<div style="margin-bottom: 8px;">13 - Consultoria e assessoria por 5 anos para resolução de qualquer solicitação da RFB para a obra do contrato</div>';
        $html .= '</div>';
        $html .= '<div style="font-style: italic; margin-top: 8px; margin-bottom: 8px;">Esta Simulação é baseada nas informações iniciais do contratante e os valores são aproximados</div>';
        $html .= '<div style="font-weight: bold;">Proposta válida até ' . date('d/m/Y', strtotime('last day of this month')) . '</div>';
        $html .= '</div></div>';

        // Rodapé
        $html .= '<div class="footer">' . htmlspecialchars($footerText) . ' | Relatório gerado em ' . date('d/m/Y H:i:s') . ' | Usuário: ' . htmlspecialchars($user['nome']) . '</div>';
        $html .= '</body></html>';

        // Ajuste global de espaçamentos mínimos
        $html = str_replace('margin-bottom: 12px;', 'margin-bottom: 2px;', $html);
        $html = str_replace('margin-bottom:12px;', 'margin-bottom:2px;', $html);
        $html = str_replace('margin-bottom: 10px;', 'margin-bottom: 2px;', $html);
        $html = str_replace('margin-bottom:10px;', 'margin-bottom:2px;', $html);
        $html = str_replace('padding: 12px 14px;', 'padding: 8px 8px;', $html);
        $html = str_replace('padding:12px 14px;', 'padding:8px 8px;', $html);
        $html = str_replace('padding: 14px 0 8px 0;', 'padding: 8px 0 4px 0;', $html);
        $html = str_replace('padding:10px 0 6px 0;', 'padding:4px 0 2px 0;', $html);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Gerar nome seguro para o arquivo
        function slugify($str) {
            $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str); // Remove acentos
            $str = strtolower($str);
            $str = preg_replace('/[^a-z0-9 ]/', '', $str); // Remove caracteres especiais, exceto espaço
            $str = preg_replace('/\s+/', '_', $str); // Substitui espaços por _
            $str = preg_replace('/_+/', '_', $str); // Evita múltiplos underlines
            $str = trim($str, '_');
            return $str;
        }
        $nomeObraSanitizado = slugify($simulacao['nome']);
        $nomeArquivo = $nomeObraSanitizado . '-' . $simulacao_id . '.pdf';
        $caminhoPasta = __DIR__ . '/uploads/planejamentos/';
        if (!is_dir($caminhoPasta)) {
            mkdir($caminhoPasta, 0777, true);
        }
        $caminhoCompleto = $caminhoPasta . $nomeArquivo;
        file_put_contents($caminhoCompleto, $dompdf->output());

        $response['success'] = true;
        $response['message'] = 'PDF gerado com sucesso!';
        $response['pdf_url'] = 'uploads/planejamentos/' . rawurlencode($nomeArquivo);

    } catch (Throwable $e) {
        $response['message'] = 'Erro ao gerar PDF: ' . $e->getMessage();
        error_log('Erro ao gerar PDF de planejamento: ' . $e->getMessage());
    } finally {
        ProcessLock::releaseLock('pdf_generation');
    }

} catch (Throwable $e) {
    $response['message'] = 'Erro inesperado: ' . $e->getMessage();
    error_log('Erro inesperado no relatorio_planejamento_pdf.php: ' . $e->getMessage());
}

// Captura todo o output e o loga
$debug_output = ob_get_clean();
if (!empty($debug_output)) {
    file_put_contents(__DIR__ . '/pdf_debug.log', "\n\n--- DEBUG OUTPUT ---\n" . $debug_output . "\n--- END DEBUG OUTPUT ---\n", FILE_APPEND);
}

echo json_encode($response);