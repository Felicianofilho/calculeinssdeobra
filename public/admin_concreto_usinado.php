<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/ConcretoUsinado.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

if (!Auth::check() || !Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$user = Auth::user();

// Listas de opções
$destinacoes = [
    'Casa Popular',
    'Comercial Salas/Lojas',
    'Conj. Hab. Popular',
    'Galpão Ind.',
    'Res. Multifamiliar',
    'Res. Unifamiliar',
    'Edifício de Garagens'
];

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// Processar ações
$message = '';
$messageType = '';

// Importação inteligente via textarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dados_colados'])) {
    $texto = $_POST['dados_colados'];
    $linhas = preg_split('/\r\n|\r|\n/', $texto);
    
    $importados = 0;
    $erros = [];
    
    try {
        // Encontrar o início da tabela (após o cabeçalho)
        $inicio_tabela = false;
        $linhas_dados = [];
        
        $uf_atual = null;
        $percentuais_uf = [];
        
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (empty($linha)) continue;
            
            // Detectar início da tabela (linha com "UF" e "Casa Popular")
            if (strpos($linha, 'UF') !== false && strpos($linha, 'Casa Popular') !== false) {
                $inicio_tabela = true;
                continue;
            }
            
            // Detectar início da tabela (linha apenas com "UF")
            if (trim($linha) === 'UF') {
                $inicio_tabela = true;
                continue;
            }
            
            // Se estamos na tabela, processar as linhas
            if ($inicio_tabela) {
                // Parar quando encontrar linha de rodapé ou fim da tabela
                if (strpos($linha, 'Onde estamos') !== false || 
                    strpos($linha, 'Fale conosco') !== false ||
                    strpos($linha, 'E-mail') !== false) {
                    break;
                }
                
                // Verificar se é uma linha com UF (2 letras maiúsculas)
                if (preg_match('/^([A-Z]{2})$/', $linha, $matches)) {
                    // Se já temos dados de uma UF anterior, salvar
                    if ($uf_atual && count($percentuais_uf) >= 7) {
                        $dados = [
                            'uf' => $uf_atual,
                            'casa_popular' => $percentuais_uf[0],
                            'comercial_salas_lojas' => $percentuais_uf[1],
                            'conj_hab_popular' => $percentuais_uf[2],
                            'galpao_ind' => $percentuais_uf[3],
                            'res_multifamiliar' => $percentuais_uf[4],
                            'res_unifamiliar' => $percentuais_uf[5],
                            'edificio_garagens' => $percentuais_uf[6]
                        ];
                        
                        if (ConcretoUsinado::salvar($dados)) {
                            $importados++;
                        } else {
                            $erros[] = "Erro ao salvar dados para UF: $uf_atual";
                        }
                    }
                    
                    // Iniciar nova UF
                    $uf_atual = $matches[1];
                    $percentuais_uf = [];
                    
                    // Verificar se UF é válida
                    if (!in_array($uf_atual, $ufs)) {
                        $erros[] = "UF inválida: $uf_atual";
                        $uf_atual = null;
                    }
                }
                // Verificar se é uma linha com percentual
                elseif (preg_match('/(\d+[,.]?\d*)%?/', $linha, $matches) && $uf_atual) {
                    $valor = str_replace(',', '.', $matches[1]);
                    $percentuais_uf[] = floatval($valor);
                }
            }
        }
        
        // Salvar última UF processada
        if ($uf_atual && count($percentuais_uf) >= 7) {
            $dados = [
                'uf' => $uf_atual,
                'casa_popular' => $percentuais_uf[0],
                'comercial_salas_lojas' => $percentuais_uf[1],
                'conj_hab_popular' => $percentuais_uf[2],
                'galpao_ind' => $percentuais_uf[3],
                'res_multifamiliar' => $percentuais_uf[4],
                'res_unifamiliar' => $percentuais_uf[5],
                'edificio_garagens' => $percentuais_uf[6]
            ];
            
            if (ConcretoUsinado::salvar($dados)) {
                $importados++;
            } else {
                $erros[] = "Erro ao salvar dados para UF: $uf_atual";
            }
        } elseif ($uf_atual && count($percentuais_uf) < 7) {
            $erros[] = "Dados insuficientes para UF: $uf_atual (encontrados " . count($percentuais_uf) . " valores)";
        }
        
        if ($importados > 0) {
            $message = "$importados registro(s) importado(s) com sucesso!";
            $messageType = 'success';
            
            if (!empty($erros)) {
                $message .= " " . count($erros) . " erro(s) encontrado(s).";
            }
        } else {
            $message = "Nenhum registro foi importado. Verifique se copiou a tabela completa.";
            $messageType = 'danger';
            
            if (!empty($erros)) {
                $message .= "\nErros encontrados:\n" . implode("\n", $erros);
            }
        }
        
    } catch (Exception $e) {
        $message = "Erro durante a importação: " . $e->getMessage();
        $messageType = 'danger';
    }
}

/**
 * Extrai percentuais de uma linha de texto
 */
function extrairPercentuais($linha) {
    // Remover espaços extras e dividir por espaços
    $partes = preg_split('/\s+/', trim($linha));
    $percentuais = [];
    
    foreach ($partes as $parte) {
        $parte = trim($parte);
        if (empty($parte)) continue;
        
        // Procurar por padrão de percentual (ex: 4,69% ou 4.69%)
        if (preg_match('/(\d+[,.]?\d*)%?/', $parte, $matches)) {
            $valor = str_replace(',', '.', $matches[1]);
            $valor_float = floatval($valor);
            $percentuais[] = $valor_float;
        }
    }
    
    return $percentuais;
}

// Limpar tabela
if (isset($_GET['limpar']) && $_GET['limpar'] === 'confirmar') {
    if (ConcretoUsinado::limparTabela()) {
        $message = 'Tabela limpa com sucesso!';
        $messageType = 'success';
    } else {
        $message = 'Erro ao limpar a tabela.';
        $messageType = 'danger';
    }
}

// Listar registros
$concreto_list = ConcretoUsinado::listarTodos();

// Estatísticas
$stats = ConcretoUsinado::obterEstatisticas();

// Carregar configurações
$config = LocalConfig::getAll();

// Cache-busting para logo e favicon
$baseUrl = LocalConfig::getBaseUrl();
$logoFile = $config['theme']['branding']['logo_file'] ?? '';
$logoFilePath = !empty($logoFile) ? __DIR__ . '/' . $logoFile : '';
$logoVersion = ($logoFilePath && file_exists($logoFilePath) ? filemtime($logoFilePath) : time());

$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$faviconFilePath = !empty($faviconFile) ? __DIR__ . '/' . $faviconFile : '';
$faviconVersion = ($faviconFilePath && file_exists($faviconFilePath) ? filemtime($faviconFilePath) : time());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração - Tabela Concreto Usinado</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark" id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading text-center py-4">
                    <?php if (!empty($logoFile)): ?>
                        <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($logoFile); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="text-white fw-bold fs-5"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
                    <?php endif; ?>
                    <div class="text-white mt-2">
                        <i class="fas fa-concrete me-2"></i>Concreto Usinado
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    <a href="javascript:void(0);" onclick="window.history.back();" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-arrow-left me-2"></i><span class="sidebar-text">Voltar</span>
                    </a>
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-tachometer-alt me-2"></i><span class="sidebar-text">Dashboard</span>
                    </a>
                    <a href="admin_vau.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-table me-2"></i><span class="sidebar-text">Tabela VAU</span>
                    </a>
                    <a href="admin_concreto_usinado.php" class="list-group-item list-group-item-action bg-dark text-white active">
                        <i class="fas fa-concrete me-2"></i><span class="sidebar-text">Concreto Usinado</span>
                    </a>

                    <a href="#importacao" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('importacao')">
                        <i class="fas fa-upload me-2"></i><span class="sidebar-text">Importação</span>
                    </a>
                    <a href="#listagem" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('listagem')">
                        <i class="fas fa-list me-2"></i><span class="sidebar-text">Listagem</span>
                    </a>
                </div>
            </div>
            <div class="sidebar-toggler-wrapper">
                <button class="sidebar-toggler" id="sidebar-toggle-btn">
                    <i class="fas fa-angle-double-left"></i>
                </button>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom">
                <div class="container-fluid">

                    <div class="navbar-nav ms-auto">
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($user['nome']) ?> (Admin)
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="admin.php">Dashboard Admin</a>
                                <a class="dropdown-item" href="dashboard.php">Dashboard Usuário</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Sair</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-concrete me-2"></i>Gestão da Tabela Concreto Usinado
                    </h1>
                    <div>
                        <button class="btn btn-outline-danger me-2" onclick="limparTabela()">
                            <i class="fas fa-trash me-2"></i>Limpar Tabela
                        </button>
                        <button class="btn btn-outline-success" onclick="exportarConcreto()">
                            <i class="fas fa-download me-2"></i>Exportar CSV
                        </button>
                    </div>
                </div>

                <!-- Mensagens -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= nl2br(htmlspecialchars($message)) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total de UFs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_registros'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-map fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">UFs Cobertas</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['ufs_cobertas'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Importação -->
                <div class="card shadow mb-4" id="importacao">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="m-0 font-weight-bold text-white">Importação de Dados</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-12">
                                <h6>Importação Inteligente (Colar Tabela)</h6>
                                <p class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Acesse a <a href="https://tabelavau.seroassessoria.com.br/imprimir_concreto_usinado.php" target="_blank">Tabela de Concreto Usinado</a>, 
                                    selecione todo o conteúdo da página (Ctrl+A) e cole no campo abaixo.
                                </p>
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Cole aqui os dados copiados da tabela de concreto usinado:</label>
                                        <textarea name="dados_colados" rows="10" class="form-control" 
                                                  placeholder="Cole aqui a tabela completa incluindo cabeçalhos..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-magic me-2"></i>Importar Tabela Colada
                                    </button>
                                </form>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    O sistema identifica automaticamente as UFs e percentuais de cada destinação.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Listagem -->
                <div class="card shadow mb-4" id="listagem">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="m-0 font-weight-bold text-white">Tabela de Concreto Usinado</h6>
                    </div>
                    <div class="card-body">
                        <!-- Tabela -->
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>UF</th>
                                        <th>Casa Popular</th>
                                        <th>Comercial Salas/Lojas</th>
                                        <th>Conj. Hab. Popular</th>
                                        <th>Galpão Ind.</th>
                                        <th>Res. Multifamiliar</th>
                                        <th>Res. Unifamiliar</th>
                                        <th>Edifício de Garagens</th>
                                        <th>Data Importação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($concreto_list)): ?>
                                        <?php foreach ($concreto_list as $c): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($c['uf']) ?></strong></td>
                                                <td><?= number_format($c['casa_popular'], 2, ',', '.') ?>%</td>
                                                <td><?= number_format($c['comercial_salas_lojas'], 2, ',', '.') ?>%</td>
                                                <td><?= number_format($c['conj_hab_popular'], 2, ',', '.') ?>%</td>
                                                <td><?= number_format($c['galpao_ind'], 2, ',', '.') ?>%</td>
                                                <td><?= number_format($c['res_multifamiliar'], 2, ',', '.') ?>%</td>
                                                <td><?= number_format($c['res_unifamiliar'], 2, ',', '.') ?>%</td>
                                                <td><?= number_format($c['edificio_garagens'], 2, ',', '.') ?>%</td>
                                                <td><?= date('d/m/Y H:i', strtotime($c['data_importacao'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                                    <p class="mt-2">Nenhum registro encontrado. Importe os dados da tabela oficial.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (!empty($concreto_list)): ?>
                            <div class="mt-3 text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Mostrando <?= count($concreto_list) ?> registro(s)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php include 'footer_simple.php'; ?>
        </div>
        <!-- /#page-content-wrapper -->
    </div>
    <!-- /#wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportarConcreto() {
            // Criar CSV dos dados atuais
            var csv = 'UF;Casa Popular;Comercial Salas/Lojas;Conj. Hab. Popular;Galpão Ind.;Res. Multifamiliar;Res. Unifamiliar;Edifício de Garagens;Data Importação\n';
            <?php foreach ($concreto_list as $c): ?>
            csv += '<?= $c['uf'] ?>;<?= $c['casa_popular'] ?>;<?= $c['comercial_salas_lojas'] ?>;<?= $c['conj_hab_popular'] ?>;<?= $c['galpao_ind'] ?>;<?= $c['res_multifamiliar'] ?>;<?= $c['res_unifamiliar'] ?>;<?= $c['edificio_garagens'] ?>;<?= $c['data_importacao'] ?>\n';
            <?php endforeach; ?>
            
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'concreto_usinado_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function limparTabela() {
            if (confirm('Tem certeza que deseja limpar toda a tabela de concreto usinado? Esta ação não pode ser desfeita.')) {
                window.location.href = '?limpar=confirmar';
            }
        }

        function scrollToSection(id) {
            document.getElementById(id).scrollIntoView({ behavior: 'smooth' });
        }
    </script>
    
</body>
</html>
