<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/CalculoINSS.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Utils/ProcessLock.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Tenta adquirir o lock para geração de PDF
if (!ProcessLock::acquireLock('pdf_generation')) {
    http_response_code(503); // Service Unavailable
    die("O sistema já está gerando um PDF. Por favor, aguarde alguns segundos e tente novamente.");
}

try {
    session_start();
    if (!Auth::check()) {
        header('Location: login.php');
        exit;
    }

    $user = Auth::user();
    $id = $_GET['id'] ?? null;
    if (!$id) {
        header('Location: minhas_simulacoes.php?error=not_found');
        exit;
    }

    $simulacao = Simulacao::find($id);
    if (!$simulacao || ($simulacao['user_id'] != $user['id'] && !Auth::isAdmin())) {
        header('Location: minhas_simulacoes.php?error=forbidden');
        exit;
    }

    $dados_simulacao = $simulacao;
    if (isset($simulacao['criado_em'])) {
        $dados_simulacao['data_simulacao'] = $simulacao['criado_em'];
    }
    $dados_calculo = CalculoINSS::calcular($dados_simulacao);

    // Logo
    $config = LocalConfig::getAll();
    $showLogo = $config['reports']['show_logo'] ?? true;
    $logoFileReport = $config['theme']['branding']['logo_file_report'] ?? '';
    $logoFile = $config['theme']['branding']['logo_file'] ?? '';
    $logoPathReport = __DIR__ . '/' . $logoFileReport;
    $logoPath = __DIR__ . '/' . $logoFile;
    $logoToUse = '';
    if (!empty($logoFileReport) && file_exists($logoPathReport)) {
        $logoToUse = $logoPathReport;
    } elseif (!empty($logoFile) && file_exists($logoPath)) {
        $logoToUse = $logoPath;
    }
    $baseUrl = LocalConfig::getBaseUrl();
    $logoUrl = LocalConfig::buildAssetUrl($logoFile);

    // Dados para o cabeçalho
    $companyName = $config['company']['name'] ?? ($config['system']['name'] ?? 'Sistema Obra Legal');
    $reportTitle = 'Relatório da Aferição';
    $footerText = $config['reports']['footer_text'] ?? 'Relatório gerado pelo Sistema Obra Legal';
    $ano = date('Y');
    $supportEmail = $config['system']['support_email'] ?? ($config['company']['contact_email'] ?? '');

    // Montar HTML do PDF
    $html = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Relatório da Aferição</title><style>
    @page { margin: 15mm 12mm 15mm 12mm; size: A4; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #222; background: #f8f9fa; }
    .header { text-align: center; margin-bottom: 12px; }
    .header .logo { margin-bottom: 4px; }
    .header .logo img { max-width: 400px; max-height: 100px; display: block; margin: 0 auto; }
    .header .company { font-size: 16px; font-weight: bold; color: #2c5aa0; margin-bottom: 2px; }
    .header .title { font-size: 14px; font-weight: 600; color: #007bff; margin-bottom: 4px; }
    .divider { border-bottom: 2px solid #007bff; margin: 8px 0 12px 0; }
    .row { display: flex; flex-wrap: wrap; margin: 0 -6px; }
    .col { flex: 1 1 0; min-width: 200px; padding: 0 6px; }
    .col-half { flex: 0 0 50%; min-width: 200px; padding: 0 6px; }
    .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(44,90,160,0.07); margin-bottom: 12px; padding: 12px 14px; border: 1px solid #e3e6f0; }
    .card-title { color: #007bff; font-size: 12px; font-weight: bold; margin-bottom: 6px; letter-spacing: 0.3px; }
    .field { margin-bottom: 4px; }
    .field-label { color: #2c5aa0; font-weight: 600; font-size: 10px; display: inline-block; min-width: 80px; }
    .field-value { color: #222; font-weight: 500; font-size: 11px; display: inline-block; }
    .field-highlight { color: #222; font-weight: bold; font-size: 11px; display: inline-block; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 10px; font-weight: bold; margin-right: 3px; }
    .badge-blue { background: #007bff; color: #fff; }
    .badge-green { background: #28a745; color: #fff; }
    .badge-orange { background: #ffc107; color: #222; }
    .badge-gray { background: #e3e6f0; color: #333; }
    .summary-card { background: #eaf4ff; border: 2px solid #007bff; color: #007bff; text-align: center; font-size: 16px; font-weight: bold; border-radius: 10px; margin-bottom: 12px; padding: 14px 0 8px 0; }
    .summary-label { font-size: 11px; color: #007bff; font-weight: 600; margin-top: 2px; }
    .notes-card { background: #f8f9fa; border: 1px dashed #007bff; color: #333; border-radius: 8px; padding: 10px 12px; font-size: 10px; margin-top: 8px; }
    .footer { border-top: 2px solid #007bff; text-align: center; font-size: 9px; color: #6c757d; margin-top: 20px; padding-top: 6px; }
</style></head><body>';

    // Cabeçalho
    $html .= '<div class="header">';
    $logoExibida = false;
    if ($showLogo && !empty($logoToUse)) {
        if (extension_loaded('gd')) {
            try {
                $imgData = base64_encode(file_get_contents($logoToUse));
                $imgSrc = 'data:image/png;base64,' . $imgData;
                $html .= '<div class="logo"><img src="' . $imgSrc . '" alt="Logo" style="max-width:400px; max-height:100px; display:block; margin:0 auto;"></div>';
                $logoExibida = true;
            } catch (Exception $e) {}
        }
    }
    // Só exibe o nome da empresa se não houver logo
    if (!$logoExibida) {
        $html .= '<div class="company">' . htmlspecialchars($companyName) . '</div>';
    }
    $html .= '<div class="title">' . htmlspecialchars($reportTitle) . '</div>';
    $html .= '<div class="divider"></div>';
    $html .= '</div>';

    // Dados da Obra - Card expandido
    $html .= '<div class="card" style="margin-bottom:12px; padding:14px;">';
    $html .= '<div class="card-title">Dados da Obra</div>';
    $html .= '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
    $html .= '<tr>';
    $html .= '<td style="padding-bottom:6px;"><span style="color:#2c5aa0; font-weight:600;">Nome da Obra:</span> <span style="font-weight:500;">' . htmlspecialchars($simulacao['nome'] ?? '-') . '</span></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="padding-bottom:6px;"><span style="color:#2c5aa0; font-weight:600;">Endereço:</span> <span style="font-weight:500;">' . htmlspecialchars($simulacao['endereco'] ?? '-') . '</span></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="padding-bottom:6px;"><span style="color:#2c5aa0; font-weight:600;">Município:</span> <span style="font-weight:500;">' . htmlspecialchars($simulacao['municipio'] ?? '-') . '</span> <span style="margin-left:12px; color:#2c5aa0; font-weight:600;">UF:</span> <span style="font-weight:500;">' . htmlspecialchars($simulacao['uf'] ?? '-') . '</span></td>';
    $html .= '</tr>';
    $html .= '</table>';
    $html .= '</div>';

    // Cards principais
    $html .= '<div class="row">';

    // Dados da Aferição - Coluna 1 (agora em tabela 2x3)
    $html .= '<div class="col-half"><div class="card"><div class="card-title">Dados da Aferição</div>';
    $html .= '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
    $html .= '<tr>';
    $html .= '<td style="width:50%; padding-bottom:2px;"><span style="color:#2c5aa0; font-weight:600;">Responsável:</span> <span style="font-weight:500;">' . htmlspecialchars($simulacao['responsavel'] ?? '-') . '</span></td>';
    $html .= '<td style="width:50%; padding-bottom:2px;">';
    if (($simulacao['responsavel'] ?? '') === 'Pessoa Jurídica' && !empty($simulacao['regime_tributario'])) {
        $html .= '<span style="color:#2c5aa0; font-weight:600;">Regime Trib.:</span> <span style="font-weight:500;">' . htmlspecialchars($simulacao['regime_tributario']) . '</span>';
    }
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="padding-bottom:2px;"><span style="color:#2c5aa0; font-weight:600;">Destinação:</span> <span style="font-weight:500;">' . htmlspecialchars($simulacao['destinacao'] ?? '-') . '</span></td>';
    $html .= '<td style="padding-bottom:2px;"><span style="color:#2c5aa0; font-weight:600;">Tipo de Obra:</span> <span style="font-weight:500;">' . htmlspecialchars($simulacao['tipo_obra'] ?? '-') . '</span></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td><span style="color:#2c5aa0; font-weight:600;">Área Total:</span> <span style="font-weight:500;">' . number_format($simulacao['area'] ?? 0, 2, ',', '.') . ' m²</span></td>';
    $html .= '<td><span style="color:#2c5aa0; font-weight:600;">Data Aferição:</span> <span style="font-weight:500;">' . (isset($simulacao['criado_em']) ? date('d/m/Y H:i', strtotime($simulacao['criado_em'])) : '-') . '</span></td>';
    $html .= '</tr>';
    $html .= '</table>';
    $html .= '</div></div>';

    // Detalhamento Técnico - Coluna 1
    $html .= '<div class="col-half"><div class="card"><div class="card-title">Detalhamento Técnico - IN RFB 2.021/2021</div>';

    // VAU com detalhes
    $mes = date('m', strtotime($simulacao['criado_em'] ?? 'now'));
    $ano = date('Y', strtotime($simulacao['criado_em'] ?? 'now'));
    $html .= '<div class="field"><span class="field-label">VAU Utilizado:</span> <span style="font-weight:400;">(' . htmlspecialchars($simulacao['uf'] ?? '-') . '/' . $mes . '/' . $ano . '):</span> <span style="font-weight:700;">R$ ' . number_format($dados_calculo['vau'] ?? 0, 2, ',', '.') . '</span></div>';

    // Área Equivalente com regra
    $html .= '<div class="field"><span class="field-label">Área Equivalente:</span> <span style="font-weight:400;">';
    if (isset($dados_calculo['percentual_equivalencia'])) {
        $html .= '(x' . number_format($dados_calculo['percentual_equivalencia'] / 100, 2, ',', '.') . ' regra ' . htmlspecialchars($simulacao['destinacao'] ?? '') . '):';
    }
    $html .= '</span> <span style="font-weight:700;">' . number_format($dados_calculo['area_equivalente'] ?? 0, 2, ',', '.') . ' m²</span></div>';

    // Custo da Obra
    $html .= '<div class="field"><span class="field-label">COD (Custo da Obra):</span> <span style="font-weight:400;">(VAU × Área Equiv.):</span> <span style="font-weight:700;">R$ ' . number_format($dados_calculo['cod'] ?? 0, 2, ',', '.') . '</span></div>';

    // RMT com fatores
    $html .= '<div class="field"><span class="field-label">RMT (Base de Cálculo):</span> <span style="font-weight:400;">(COD × Fatores):</span> <span style="font-weight:700;">R$ ' . number_format($dados_calculo['rmt'] ?? 0, 2, ',', '.') . '</span></div>';

    // Fatores aplicados
    $html .= '<div class="field"><span class="field-label">Fatores Aplicados:</span> ';
    if (isset($dados_calculo['percentual_rmt'])) {
        if (($simulacao['responsavel'] ?? '') === 'Pessoa Física') {
            // Para PF: Fator Social + Tipo de Obra
            $fator_tipo_obra = ($simulacao['tipo_obra'] ?? '') === 'Alvenaria' ? 20.0 : 15.0;
            $html .= 'Fator Social ' . number_format($dados_calculo['percentual_rmt'], 1, ',', '.') . '% Tipo de Obra ' . number_format($fator_tipo_obra, 1, ',', '.') . '% ';
        } else {
            // Para PJ: Apenas Tipo de Obra
            $html .= 'Tipo de Obra ' . number_format($dados_calculo['percentual_rmt'], 1, ',', '.') . '% ';
        }
    }
    if (isset($dados_calculo['percentual_equivalencia'])) {
        $html .= 'Equivalência ' . number_format($dados_calculo['percentual_equivalencia'], 1, ',', '.') . '%';
    }
    $html .= '</div>';

    // Alíquota com origem
    $aliquotaOrigem = '';
    if (($simulacao['responsavel'] ?? '') === 'Pessoa Física') {
        $aliquotaOrigem = ' (Pessoa Física)';
    } else {
        $aliquotaOrigem = ' (PJ - ' . htmlspecialchars($simulacao['regime_tributario'] ?? '') . ')';
    }
    $html .= '<div class="field"><span class="field-label">Alíquota INSS:</span> <span style="font-weight:400;">' . $aliquotaOrigem . ':</span> <span style="font-weight:700;">' . number_format($dados_calculo['aliquota_aplicada'] ?? 0, 1, ',', '.') . '%</span></div>';

    $html .= '</div></div>';

    // Resumo Final
    $html .= '<div class="col"><div class="summary-card">R$ ' . number_format($dados_calculo['inss'] ?? 0, 2, ',', '.') . '<div class="summary-label">INSS a Recolher</div></div>';
    $html .= '<div class="card notes-card"><div class="card-title" style="color:#007bff;">Observações/Notas</div>';
    $html .= '<div>Este relatório é uma aferição técnica e não substitui a aferição oficial. Valores calculados conforme IN RFB Nº 2.021/2021.<br>Consulte sempre a legislação vigente e profissionais especializados.</div></div>';
    $html .= '</div>';
    $html .= '</div>';

    // Segunda linha - Apenas Informações Complementares
    $html .= '<div class="row" style="margin-top: 12px;">';

    // Informações complementares (agora ocupa toda a largura)
    $html .= '<div class="col"><div class="card"><div class="card-title">Informações Complementares</div>';
    // Linha única: Gerado em | Base legal
    $html .= '<div style="display:flex; justify-content:flex-start; align-items:center; font-size:11px; margin-bottom:8px;">';
    $html .= '<span><span style="color:#2c5aa0; font-weight:600;">Gerado em:</span> <span style="font-weight:500;">' . date('d/m/Y H:i:s') . '</span></span>';
    $html .= '&nbsp;&nbsp;&nbsp;';
    $html .= '<span><span style="color:#2c5aa0; font-weight:600;">Base legal:</span> <span style="font-weight:500;">IN RFB Nº 2.021/2021</span></span>';
    $html .= '</div>';
    // Destaque Hevcon - Mensagem condicional baseada no tipo de responsável
    $mensagemHevcon = '';
    if (($simulacao['responsavel'] ?? '') === 'Pessoa Física') {
        $mensagemHevcon = 'Para Redução de até 70% do INSS a Recolher,<br>contate a Equipe de Especialistas da <span style="font-size:16px; color:#007bff; font-weight:900; letter-spacing:1px;">Hevcon</span>.';
    } else {
        $mensagemHevcon = 'Quer orientação para economizar e evitar multas?<br>Procure os Especialistas da <span style="font-size:16px; color:#007bff; font-weight:900; letter-spacing:1px;">Hevcon</span>.';
    }
    $html .= '<div style="margin-top:16px; text-align:center; font-size:14px; font-weight:bold; color:#ff8800; letter-spacing:0.5px; line-height:1.3;">' . $mensagemHevcon . '</div>';
    $html .= '</div></div>';
    $html .= '</div>';

    // Rodapé
    $footerEmpresa = $config['company']['name'] ?? ($config['system']['name'] ?? 'Empresa');
    $footerAno = date('Y');
    $footerSite = $config['company']['website'] ?? '';
    $html .= '<div class="footer">Relatório gerado por ' . htmlspecialchars($footerEmpresa) . ' | ' . $footerAno . ' | ' . htmlspecialchars($footerSite) . '</div>';

    $html .= '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    function slugify($str) {
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str); // Remove acentos
        $str = strtolower($str);
        $str = preg_replace('/[^a-z0-9 ]/', '', $str); // Remove caracteres especiais, exceto espaço
        $str = preg_replace('/\s+/', '_', $str); // Substitui espaços por _
        $str = preg_replace('/_+/', '_', $str); // Evita múltiplos underlines
        $str = trim($str, '_');
        return $str;
    }
    $nome_arquivo = 'Relatorio_Afericao_' . slugify($simulacao['nome']) . '_' . date('Y-m-d_H-i') . '.pdf';
    $dompdf->stream($nome_arquivo, ['Attachment' => false]);
    exit; 
} finally {
    // Garante que o lock seja liberado, não importa o que aconteça
    ProcessLock::releaseLock('pdf_generation');
} 